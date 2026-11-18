<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\RecommendationFeedback;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports for evaluating the recommenders. Streamed; UTF-8 with a BOM so Excel shows
 * names like "Peñaranda" correctly. Cells that a spreadsheet would run as a formula
 * (=, +, -, @) are prefixed with an apostrophe (CSV injection).
 */
class ExportService
{
    public function placements(): StreamedResponse
    {
        return $this->stream('gias-placements', [
            'student', 'program', 'partner', 'algorithm_used', 'rank_cos', 'rank_wsm', 'rank_fuzzy', 'date', 'status',
        ], function () {
            foreach (Deployment::query()->with(['student.user.program', 'industryPartner'])->orderBy('start_date')->orderBy('id')->lazy() as $d) {
                yield [
                    $d->student?->user?->name,
                    $d->student?->user?->program?->code,
                    $d->industryPartner?->company_name,
                    $d->algorithm_used,
                    $d->rank_cos,
                    $d->rank_wsm,
                    $d->rank_fuzzy,
                    $d->start_date?->toDateString(),
                    $d->status->value,
                ];
            }
        });
    }

    public function feedback(): StreamedResponse
    {
        return $this->stream('gias-recommendation-feedback', [
            'student', 'program', 'algorithm', 'rating', 'comment', 'rated_by', 'date',
        ], function () {
            foreach (RecommendationFeedback::query()->with(['student.user.program', 'ratedBy'])->orderBy('created_at')->orderBy('id')->lazy() as $f) {
                yield [
                    $f->student?->user?->name,
                    $f->student?->user?->program?->code,
                    $f->algorithm,
                    $f->rating,
                    $f->comment,
                    $f->ratedBy?->name,
                    $f->created_at?->toDateString(),
                ];
            }
        });
    }

    /**
     * Stream a CSV download (shared by the other exports).
     *
     * @param  list<string>  $header
     * @param  callable(): iterable<list<mixed>>  $rows
     */
    public function stream(string $name, array $header, callable $rows): StreamedResponse
    {
        return $this->streamAs($name.'-'.now()->format('Ymd').'.csv', $header, $rows);
    }

    /**
     * Same as stream(), with an exact file name.
     *
     * @param  list<string>  $header
     * @param  callable(): iterable<list<mixed>>  $rows
     */
    public function streamAs(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '');
            foreach ($rows() as $row) {
                fputcsv($out, array_map($this->safeCell(...), $row), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
