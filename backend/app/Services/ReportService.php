<?php

namespace App\Services;

use App\Enums\EvaluationType;
use App\Enums\UserRole;
use App\Models\Program;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\JoinClause;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/reports/{type}?format=csv|pdf — the same scope and filters as Analytics.
 *  deployments  admin, coordinator (own program)
 *  partners     admin only
 *  programs     admin, coordinator (own row)
 *  algorithms   admin, coordinator (own program)
 */
class ReportService
{
    public const TYPES = ['deployments', 'partners', 'programs', 'algorithms'];

    public const TITLES = [
        'deployments' => 'Deployment masterlist',
        'partners' => 'Partner directory',
        'programs' => 'Program summary',
        'algorithms' => 'Recommendation algorithm comparison',
    ];

    private const LANDSCAPE = ['deployments', 'partners'];

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly ExportService $export,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(User $viewer, string $type, string $format, array $filters): Response
    {
        $this->authorize($viewer, $type);
        $scope = $this->analytics->scope($viewer, $filters);
        $filename = "gias-{$type}-{$scope['term']->key()}";

        if ($format === 'csv') {
            return $this->csv($type, $scope, "{$filename}.csv");
        }

        return $this->pdf($viewer, $type, $filters)->download("{$filename}.pdf");
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pdf(User $viewer, string $type, array $filters): DomPdf
    {
        $this->authorize($viewer, $type);
        $scope = $this->analytics->scope($viewer, $filters);

        $data = [
            'title' => self::TITLES[$type],
            'term' => $scope['term']->label(),
            'filters' => $this->filterText($scope),
            'generated' => CarbonImmutable::now(config('gias.local_timezone'))->format('j M Y, g:i A'),
            'generatedBy' => $viewer->name,
            'criteria' => EvaluationService::criteria(),
        ] + match ($type) {
            'deployments' => ['rows' => iterator_to_array($this->deploymentRows($scope), false)],
            'partners' => ['rows' => iterator_to_array($this->partnerRows($scope), false)],
            'programs' => ['rows' => $this->analytics->programSummaries($scope)],
            'algorithms' => [
                'comparison' => $this->analytics->algorithmComparison($scope),
                'rows' => iterator_to_array($this->placementRows($scope), false),
            ],
        };

        $pdf = Pdf::loadView("reports.{$type}", $data)
            ->setOption(['default_font' => 'DejaVu Sans', 'enable_font_subsetting' => true])
            ->setPaper('a4', in_array($type, self::LANDSCAPE, true) ? 'landscape' : 'portrait');

        // "Page x of y": dompdf only knows the page count after rendering (CSS counter(pages) isn't supported).
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $mm = 72 / 25.4;
        $canvas->page_text($canvas->get_width() - 14 * $mm - 62, $canvas->get_height() - 15 * $mm, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.4, 0.4, 0.4]);

        return $pdf;
    }

    private function authorize(User $viewer, string $type): void
    {
        if ($type === 'partners' && $viewer->role !== UserRole::Admin) {
            throw new AuthorizationException('Only the Admin can export the partner directory.');
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function csv(string $type, array $scope, string $filename): StreamedResponse
    {
        $criteria = EvaluationService::criteria();

        return match ($type) {
            'deployments' => $this->export->streamAs($filename, [
                'student', 'student_number', 'program', 'partner', 'city', 'province', 'start_date', 'status',
                'signed_hours', 'required_hours', 'final_rating', 'descriptor',
            ], fn () => $this->deploymentRows($scope)),

            'partners' => $this->export->streamAs($filename, [
                'company', 'contact_person', 'city', 'province', 'slots', 'filled_term', 'utilization_percent',
                'geocode_status', 'latitude', 'longitude',
            ], fn () => $this->partnerRows($scope)),

            'programs' => $this->export->streamAs($filename, array_merge([
                'program', 'program_name', 'coordinator', 'students', 'placed', 'placement_rate_percent', 'placements',
                'partners_used', 'avg_distance_km', 'completion_rate_percent', 'avg_final_rating', 'descriptor', 'avg_partner_mean',
            ], array_values($criteria)), function () use ($scope) {
                foreach ($this->analytics->programSummaries($scope) as $p) {
                    yield array_merge([
                        $p['program']['code'], $p['program']['name'], $p['coordinator'], $p['students'], $p['placed'],
                        $p['placement_rate'], $p['placements'], $p['partners_used'], $p['avg_distance_km'],
                        $p['completion_rate'], $p['avg_final_rating'], $p['descriptor'], $p['avg_partner_mean'],
                    ], array_values($p['criteria']));
                }
            }),

            'algorithms' => $this->export->streamAs($filename, [
                'algorithm', 'placements_using', 'top1_agreement_percent', 'top3_agreement_percent',
                'mean_rank_of_chosen', 'avg_coordinator_rating', 'ratings_n',
            ], function () use ($scope) {
                $comparison = $this->analytics->algorithmComparison($scope);
                foreach ($comparison['algorithms'] as $a) {
                    yield [$a['label'], $a['placements_using'], $a['top1_agreement'], $a['top3_agreement'],
                        $a['mean_rank_of_chosen'], $a['avg_feedback_rating'], $a['feedback_n']];
                }
                yield [];
                yield ["Based on {$comparison['total_with_rank_data']} placements with recommendation data; {$comparison['manual_placements']} manual placements excluded."];
                yield [];
                yield ['student', 'program', 'partner', 'algorithm_used', 'rank_cos', 'rank_wsm', 'rank_fuzzy'];
                yield from $this->placementRows($scope);
            }),
        };
    }

    /**
     * Every deployment of the term in scope (withdrawn included, so the masterlist is complete).
     *
     * @param  array<string, mixed>  $scope
     * @return \Generator<int, list<mixed>>
     */
    private function deploymentRows(array $scope): \Generator
    {
        $rows = $this->analytics->deployments($scope, withWithdrawn: true)
            ->leftJoin('evaluations as e', fn (JoinClause $j) => $j->on('e.deployment_id', '=', 'd.id')
                ->where('e.type', '=', EvaluationType::Final->value)->where('e.status', '=', 'reviewed'))
            ->selectRaw(
                'su.name as student, s.student_number, p.code as program, ip.company_name as partner, ip.city, ip.province, '
                .'d.start_date, d.status, '.AnalyticsService::signedSql().' as signed, coalesce(p.required_ojt_hours, 486) as required, e.final_rating'
            )
            ->orderBy('p.code')->orderBy('su.name')->orderBy('d.id')
            ->cursor();

        foreach ($rows as $r) {
            yield [
                $r->student, $r->student_number, $r->program, $r->partner, $r->city, $r->province,
                $r->start_date, $r->status, round((float) $r->signed, 2), (int) $r->required,
                $r->final_rating === null ? null : round((float) $r->final_rating, 2),
                EvaluationService::descriptor($r->final_rating),
            ];
        }
    }

    /**
     * Active partners; filled = the term's interns there (in the program filter, if any).
     *
     * @param  array<string, mixed>  $scope
     * @return \Generator<int, list<mixed>>
     */
    private function partnerRows(array $scope): \Generator
    {
        [$start, $end] = $this->analytics->termDates($scope['term']);
        $filledSql = "(select count(*) from deployments x join students xs on xs.id = x.student_id join users xu on xu.id = xs.user_id where x.industry_partner_id = ip.id and x.status <> 'withdrawn' and x.start_date between ? and ?"
            .($scope['program_id'] !== null ? ' and xu.program_id = ?' : '').')';
        $bindings = array_merge([$start, $end], $scope['program_id'] !== null ? [$scope['program_id']] : []);

        $rows = $this->analytics->activePartners($scope)
            ->selectRaw(
                "ip.company_name, ip.contact_person, ip.city, ip.province, ip.intern_slots, {$filledSql} as filled, ip.geocode_status, "
                .'ST_Y(ip.location::geometry) as lat, ST_X(ip.location::geometry) as lng',
                $bindings,
            )
            ->orderBy('ip.company_name')
            ->cursor();

        foreach ($rows as $r) {
            yield [
                $r->company_name, $r->contact_person, $r->city, $r->province, (int) $r->intern_slots, (int) $r->filled,
                AnalyticsService::ratio((int) $r->filled, (int) $r->intern_slots), $r->geocode_status,
                $r->lat === null ? null : round((float) $r->lat, 6), $r->lng === null ? null : round((float) $r->lng, 6),
            ];
        }
    }

    /**
     * The term's placements in scope that went through the recommender (or were manual).
     *
     * @param  array<string, mixed>  $scope
     * @return \Generator<int, list<mixed>>
     */
    private function placementRows(array $scope): \Generator
    {
        $rows = $this->analytics->deployments($scope)
            ->whereNotNull('d.algorithm_used')
            ->select('su.name as student', 'p.code as program', 'ip.company_name as partner', 'd.algorithm_used', 'd.rank_cos', 'd.rank_wsm', 'd.rank_fuzzy')
            ->orderBy('d.start_date')->orderBy('su.name')->orderBy('d.id')
            ->cursor();

        foreach ($rows as $r) {
            yield [$r->student, $r->program, $r->partner, $r->algorithm_used, $r->rank_cos, $r->rank_wsm, $r->rank_fuzzy];
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function filterText(array $scope): string
    {
        $program = $scope['program_id'] !== null
            ? (Program::query()->whereKey($scope['program_id'])->value('code') ?? 'No program')
            : 'All programs';

        return $program.' · '.($scope['province'] ?? 'All provinces');
    }
}
