@extends('survey_module.layouts.klaes')

@section('page-title', 'Beacon Nos ' . $anchor['prefill']['its_number'] . ' — KLAES')

@php
  use App\Services\Survey\BeaconNumberCheck as B;

  $PageTitle = 'Beacon Nos · ' . $anchor['prefill']['its_number'];
  $PageDescription = $anchor['prefill']['file_number'] . ($anchor['prefill']['grantee_name'] ? ' · ' . $anchor['prefill']['grantee_name'] : '')
      . ' · ' . (['cadastral' => 'Cadastral I to S', 'private' => "Surveyor's Report (Private)"][$anchor['source']] ?? 'Survey I to S');
  $rp = $anchor['routes'] ?? 'survey-module.gkn';
  $key = $anchor['key'];
  $tone = [B::REGISTERED => 'completed', B::OTHER_SURVEYOR => 'review', B::UNREGISTERED => 'rejected'];
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<div class="page-header">
    @if (! empty($anchor['back']))
        <a class="btn btn-secondary btn-sm" href="{{ $anchor['back']['url'] }}"><i class="fas fa-arrow-left"></i> {{ $anchor['back']['label'] }}</a>
    @else
        <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.gkn.beacons.index') }}"><i class="fas fa-arrow-left"></i> All instructions</a>
    @endif
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline btn-sm" href="{{ route($rp . '.coordinates.show', $key) }}"><i class="fas fa-map-pin"></i> List of Coordinates</a>
        <a class="btn btn-outline btn-sm" href="{{ route($rp . '.observations.show', $key) }}"><i class="fas fa-calculator"></i> Field Observations</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Beacons</div><div class="kpi-value">{{ number_format($summary['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">In Beacon Register</div><div class="kpi-value">{{ number_format($summary[B::REGISTERED]) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Issued to another surveyor</div><div class="kpi-value" style="{{ $summary[B::OTHER_SURVEYOR] ? 'color:#b45309;' : '' }}">{{ number_format($summary[B::OTHER_SURVEYOR]) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Not in Beacon Register</div><div class="kpi-value" style="{{ $summary[B::UNREGISTERED] ? 'color:#b91c1c;' : '' }}">{{ number_format($summary[B::UNREGISTERED]) }}</div></div>
</div>

<p class="helper-text" style="margin:-6px 0 12px;">
    The beacon numbers come from this survey's List of Coordinates; add or change them there.
    Each is checked against the Beacon Register{{ $anchor['prefill']['surveyor_name'] ? ' for ' . $anchor['prefill']['surveyor_name'] : '' }}.
</p>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Beacon No.</th><th style="text-align:right;">Northing</th><th style="text-align:right;">Easting</th>
                    <th>Beacon Register</th><th>Issued To / Date</th><th>Also Used On</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $b)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong style="font-family:monospace;">{{ $b->beacon_id }}</strong></td>
                        <td style="text-align:right;font-family:monospace;">{{ number_format((float) $b->northing, 3) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ number_format((float) $b->easting, 3) }}</td>
                        <td><span class="status-badge {{ $tone[$b->status] }}"><span class="dot"></span>{{ B::LABELS[$b->status] }}</span></td>
                        <td>
                            @if ($b->issue)
                                {{ $b->issue->surveyor_name ?: '—' }}
                                <div style="font-size:12px;color:var(--gray-500);">{{ $b->issue->register_no ?: 'Line #' . $b->issue->register_id }}{{ $b->issue->issue_date ? ' · ' . \Illuminate\Support\Carbon::parse($b->issue->issue_date)->format('d M Y') : '' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-size:12px;">
                            @forelse ($b->elsewhere as $e)<div style="color:#b45309;">{{ $e }}</div>@empty — @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--gray-500);">
                        No beacons yet. Add them on the <a href="{{ route($rp . '.coordinates.show', $key) }}">List of Coordinates</a>.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
