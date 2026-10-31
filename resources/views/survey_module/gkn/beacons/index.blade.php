@extends('survey_module.layouts.klaes')

@section('page-title', 'Beacon Nos — KLAES')

@php
  $PageTitle = 'Beacon Nos';
  $PageDescription = "Each issued Instruction to Survey's beacon numbers, checked against the Beacon Register.";
  $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Issued I to S</div><div class="kpi-value">{{ number_format($totals['its']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Beacons recorded</div><div class="kpi-value">{{ number_format($totals['beacons']) }}</div></div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search I to S, file, surveyor…" style="{{ $inputStyle }}width:280px;" />
        <select name="has" style="{{ $inputStyle }}">
            <option value="">All</option>
            <option value="with" @selected(request('has') === 'with')>With beacons</option>
            <option value="without" @selected(request('has') === 'without')>Without beacons</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'has']))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.beacons.index') }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>S/N</th><th>Source</th><th>I to S No.</th><th>File No.</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Surveyor</th><th style="text-align:right;">Beacons</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $a)
                    @php $p = $a['prefill']; @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{!! $a['source'] === 'survey' ? $chip('Survey', 'pink') : $chip('Cadastral', 'indigo') !!}</td>
                        <td><strong style="font-family:monospace;">{{ $p['its_number'] }}</strong></td>
                        <td style="font-family:monospace;">{{ $p['file_number'] ?: '—' }}</td>
                        <td>{{ $p['plot_no'] ?: '—' }}</td>
                        <td>{{ $p['district'] ?: '—' }}</td>
                        <td>{{ $p['lga'] ?: '—' }}</td>
                        <td>{{ $p['surveyor_name'] ?: '—' }}</td>
                        <td style="text-align:right;">{{ number_format($a['beacons']) }}</td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.beacons.show', $a['key']) }}"><i class="fas fa-eye"></i> Check</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--gray-500);">No issued Instructions to Survey{{ request()->hasAny(['q', 'has']) ? ' match these filters' : ' yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
