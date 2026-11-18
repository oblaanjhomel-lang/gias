@extends('reports.layout')

@php
    $algos = $comparison['algorithms'];
    // Best per column: highest agreement / rating, lowest mean rank.
    $best = function (string $key, bool $lowest = false) use ($algos) {
        $values = array_filter(array_column($algos, $key), fn ($v) => $v !== null);
        return $values === [] ? null : ($lowest ? min($values) : max($values));
    };
    $bests = [
        'top1_agreement' => $best('top1_agreement'),
        'top3_agreement' => $best('top3_agreement'),
        'mean_rank_of_chosen' => $best('mean_rank_of_chosen', true),
        'avg_feedback_rating' => $best('avg_feedback_rating'),
    ];
    $cls = fn ($a, $key) => $a[$key] !== null && $a[$key] === $bests[$key] ? 'num best' : 'num';
@endphp

@section('content')
    <table>
        <thead>
            <tr>
                <th>Algorithm</th>
                <th class="num">Placements using</th>
                <th class="num">Top-1 agreement</th>
                <th class="num">Top-3 agreement</th>
                <th class="num">Mean rank of chosen</th>
                <th class="num">Avg coordinator rating (n)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($algos as $a)
                <tr>
                    <td><strong>{{ $a['label'] }}</strong></td>
                    <td class="num">{{ $a['placements_using'] }}</td>
                    <td class="{{ $cls($a, 'top1_agreement') }}">{{ $a['top1_agreement'] !== null ? $a['top1_agreement'].'%' : '—' }}</td>
                    <td class="{{ $cls($a, 'top3_agreement') }}">{{ $a['top3_agreement'] !== null ? $a['top3_agreement'].'%' : '—' }}</td>
                    <td class="{{ $cls($a, 'mean_rank_of_chosen') }}">{{ $a['mean_rank_of_chosen'] ?? '—' }}</td>
                    <td class="{{ $cls($a, 'avg_feedback_rating') }}">{{ $a['avg_feedback_rating'] !== null ? number_format($a['avg_feedback_rating'], 2) : '—' }} ({{ $a['feedback_n'] }})</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="note">
        Agreement = how often the partner the coordinator actually chose was ranked first (or in the top 3) by that algorithm.
        Based on {{ $comparison['total_with_rank_data'] }} placements with recommendation data; {{ $comparison['manual_placements'] }} manual placements excluded.
    </div>

    <h2>Placements</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Program</th>
                <th>Partner</th>
                <th>Algorithm used</th>
                <th class="num">Rank (cos)</th>
                <th class="num">Rank (WSM)</th>
                <th class="num">Rank (fuzzy)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>{{ $r[0] }}</td>
                    <td>{{ $r[1] }}</td>
                    <td>{{ $r[2] }}</td>
                    <td>{{ $r[3] }}</td>
                    <td class="num">{{ $r[4] ?? '—' }}</td>
                    <td class="num">{{ $r[5] ?? '—' }}</td>
                    <td class="num">{{ $r[6] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No placements with recommendation data in this term and scope.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
