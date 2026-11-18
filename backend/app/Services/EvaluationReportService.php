<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\EvaluationType;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evaluation reporting. Aggregates run in SQL over the jsonb ratings (no row-by-row loading).
 * Scope: coordinator = own program; admin = all.
 * "Evaluated" means submitted or reviewed (drafts and returned ones don't count).
 */
class EvaluationReportService
{
    private const COUNTED = ['submitted', 'reviewed'];

    public function __construct(
        private readonly DeploymentMonitoringService $monitoring,
        private readonly ExportService $export,
    ) {}

    /**
     * $deploymentIds (a subquery selecting deployment ids) narrows the viewer's scope further,
     * e.g. to one term and province for analytics.
     *
     * @return array<string, mixed>
     */
    public function summary(User $viewer, ?EvaluationType $type, mixed $deploymentIds = null): array
    {
        $scopeIds = $this->monitoring->inScope($viewer)
            ->when($deploymentIds !== null, fn ($q) => $q->whereIn('deployments.id', $deploymentIds))
            ->select('deployments.id');
        $evaluations = fn (): QueryBuilder => DB::table('evaluations as e')
            ->join('deployments as d', 'd.id', '=', 'e.deployment_id')
            ->whereIn('d.id', $scopeIds)
            ->when($type, fn (QueryBuilder $q) => $q->where('e.type', $type->value));

        $counts = $evaluations()->selectRaw('e.status, count(*) as n')->groupBy('e.status')->pluck('n', 'status');
        $statusCounts = [];
        foreach (['draft', 'submitted', 'returned', 'reviewed'] as $status) {
            $statusCounts[$status] = (int) ($counts[$status] ?? 0);
        }

        $average = $evaluations()->whereIn('e.status', self::COUNTED)->avg('e.partner_mean');

        // One query: avg((ratings->>'key')::numeric) per criterion, keys bound as parameters.
        $criteria = EvaluationService::criteria();
        $keys = array_keys($criteria);
        $selects = array_map(fn (int $i) => "avg((e.ratings->>?)::numeric) as c{$i}", array_keys($keys));
        $row = (array) $evaluations()->whereIn('e.status', self::COUNTED)->selectRaw(implode(', ', $selects), $keys)->first();

        $perCriterion = [];
        foreach ($keys as $i => $key) {
            $value = $row["c{$i}"] ?? null;
            $perCriterion[] = ['key' => $key, 'label' => $criteria[$key], 'average' => $value === null ? null : round((float) $value, 2)];
        }
        $rated = array_values(array_filter($perCriterion, fn (array $c) => $c['average'] !== null));
        usort($rated, fn (array $a, array $b) => $a['average'] <=> $b['average']);

        return [
            'type' => $type?->value,
            'counts' => $statusCounts,
            'evaluations_submitted' => $statusCounts['submitted'] + $statusCounts['reviewed'],
            'awaiting_review' => $statusCounts['submitted'],
            'average_partner_mean' => $average === null ? null : round((float) $average, 2),
            'average_descriptor' => EvaluationService::descriptor($average),
            'criteria' => $perCriterion,
            'lowest_criterion' => $rated[0] ?? null,
            'programs' => $this->programs($scopeIds, $type),
        ];
    }

    /**
     * Admin CSV for one evaluation type.
     */
    public function exportCsv(EvaluationType $type): StreamedResponse
    {
        $criteria = EvaluationService::criteria();

        return $this->export->stream(
            "gias-evaluations-{$type->value}",
            array_merge(['student', 'student_number', 'program', 'partner', 'type'], array_values($criteria), [
                'partner_mean', 'coordinator_rating', 'final_rating', 'status', 'reviewed_at',
            ]),
            function () use ($type, $criteria) {
                $rows = Evaluation::query()
                    ->where('type', $type)
                    ->with(['deployment.student.user.program', 'deployment.industryPartner'])
                    ->orderBy('id')
                    ->lazy();

                foreach ($rows as $e) {
                    $student = $e->deployment?->student;
                    yield array_merge([
                        $student?->user?->name,
                        $student?->student_number,
                        $student?->user?->program?->code,
                        $e->deployment?->industryPartner?->company_name,
                        $e->type->value,
                    ], array_map(fn (string $key) => $e->ratings[$key] ?? null, array_keys($criteria)), [
                        $e->partner_mean,
                        $e->coordinator_rating,
                        $e->final_rating,
                        $e->status->value,
                        $e->reviewed_at?->toDateString(),
                    ]);
                }
            },
        );
    }

    /**
     * Per program: deployments (active or completed), how many are evaluated, averages.
     *
     * @return list<array<string, mixed>>
     */
    private function programs(mixed $scopeIds, ?EvaluationType $type): array
    {
        [$a, $b] = self::COUNTED;

        return DB::table('deployments as d')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('programs as p', 'p.id', '=', 'u.program_id')
            ->leftJoin('evaluations as e', function ($join) use ($type) {
                $join->on('e.deployment_id', '=', 'd.id');
                if ($type) {
                    $join->where('e.type', '=', $type->value);
                }
            })
            ->whereIn('d.id', $scopeIds)
            ->whereIn('d.status', [DeploymentStatus::Active->value, DeploymentStatus::Completed->value])
            ->groupBy('p.id', 'p.code', 'p.name')
            ->orderBy('p.code')
            ->selectRaw(
                'p.code, p.name, count(distinct d.id) as total, '
                .'count(distinct case when e.status in (?, ?) then d.id end) as evaluated, '
                .'avg(case when e.status in (?, ?) then e.partner_mean end) as avg_partner_mean, '
                .'avg(case when e.status = ? then e.final_rating end) as avg_final',
                [$a, $b, $a, $b, 'reviewed'],
            )
            ->get()
            ->map(function (object $r) {
                $mean = $r->avg_partner_mean === null ? null : round((float) $r->avg_partner_mean, 2);
                $final = $r->avg_final === null ? null : round((float) $r->avg_final, 2);

                return [
                    'program' => ['code' => $r->code, 'name' => $r->name],
                    'evaluated' => (int) $r->evaluated,
                    'total_active_or_completed' => (int) $r->total,
                    'avg_partner_mean' => $mean,
                    'avg_final' => $final,
                    'descriptor' => EvaluationService::descriptor($final ?? $mean),
                ];
            })
            ->all();
    }
}
