@extends('reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th style="width: 3%">#</th>
                <th style="width: 20%">Company</th>
                <th style="width: 14%">Contact person</th>
                <th style="width: 10%">City</th>
                <th style="width: 10%">Province</th>
                <th class="num" style="width: 5%">Slots</th>
                <th class="num" style="width: 7%">Filled (term)</th>
                <th class="num" style="width: 7%">Utilization</th>
                <th style="width: 8%">Geocode</th>
                <th class="num" style="width: 8%">Latitude</th>
                <th class="num" style="width: 8%">Longitude</th>
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
                    <td class="num">{{ $r[4] }}</td>
                    <td class="num">{{ $r[5] }}</td>
                    <td class="num">{{ $r[6] !== null ? $r[6].'%' : '—' }}</td>
                    <td>{{ ucfirst($r[7]) }}</td>
                    <td class="num">{{ $r[8] ?? '—' }}</td>
                    <td class="num">{{ $r[9] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted">No active partners in this scope.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="note">{{ count($rows) }} active Industry Partner(s). Coordinates are WGS 84 (SRID 4326).</div>
@endsection
