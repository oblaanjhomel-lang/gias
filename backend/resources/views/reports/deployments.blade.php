@extends('reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th style="width: 2.5%">#</th>
                <th style="width: 13%">Student</th>
                <th style="width: 10%">Student no.</th>
                <th style="width: 6%">Program</th>
                <th style="width: 16%">Partner</th>
                <th style="width: 9%">City</th>
                <th style="width: 10%">Province</th>
                <th style="width: 7%">Start date</th>
                <th style="width: 5.5%">Status</th>
                <th class="num" style="width: 5%">Signed h</th>
                <th class="num" style="width: 5.5%">Required h</th>
                <th style="width: 10.5%">Final rating</th>
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
                    <td>{{ $r[4] }}</td>
                    <td>{{ $r[5] }}</td>
                    <td>{{ \Carbon\CarbonImmutable::parse($r[6])->format('j M Y') }}</td>
                    <td>{{ ucfirst($r[7]) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($r[8], 2), '0'), '.') }}</td>
                    <td class="num">{{ $r[9] }}</td>
                    <td>{{ $r[10] !== null ? number_format($r[10], 2).' · '.$r[11] : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="muted">No deployments in this term and scope.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="note">{{ count($rows) }} deployment(s). A deployment belongs to the term of its start date; signed hours are partner-signed weekly reports.</div>
@endsection
