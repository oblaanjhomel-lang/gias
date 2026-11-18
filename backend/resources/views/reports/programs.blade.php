@extends('reports.layout')

@php
    $pct = fn ($v) => $v === null ? '—' : $v.'%';
    $num = fn ($v, $d = 2) => $v === null ? '—' : number_format($v, $d);
@endphp

@section('content')
    <h2>Placement and completion</h2>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th>Coordinator</th>
                <th class="num">Placed / students</th>
                <th class="num">Placement rate</th>
                <th class="num">Partners used</th>
                <th class="num">Avg distance</th>
                <th class="num">Completion</th>
                <th>Avg final rating</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><strong>{{ $r['program']['code'] }}</strong></td>
                    <td>{{ $r['coordinator'] ?? '— none —' }}</td>
                    <td class="num">{{ $r['placed'] }} / {{ $r['students'] }}</td>
                    <td class="num">{{ $pct($r['placement_rate']) }}</td>
                    <td class="num">{{ $r['partners_used'] }}</td>
                    <td class="num">{{ $r['avg_distance_km'] !== null ? $r['avg_distance_km'].' km' : '—' }}</td>
                    <td class="num">{{ $pct($r['completion_rate']) }}</td>
                    <td>{{ $r['avg_final_rating'] !== null ? $num($r['avg_final_rating']).' · '.$r['descriptor'] : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Final evaluation averages by criterion (1–5)</h2>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                @foreach ($criteria as $label)
                    <th class="num">{{ $label }}</th>
                @endforeach
                <th class="num">Partner mean</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><strong>{{ $r['program']['code'] }}</strong></td>
                    @foreach (array_keys($criteria) as $key)
                        <td class="num">{{ $num($r['criteria'][$key]) }}</td>
                    @endforeach
                    <td class="num">{{ $num($r['avg_partner_mean']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="note">Placement rate = students placed in the term ÷ active students (plus any placed then). Completion = completed ÷ (completed + active past their required hours). Evaluation averages use submitted and reviewed final evaluations.</div>
@endsection
