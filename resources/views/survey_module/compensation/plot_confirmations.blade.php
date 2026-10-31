@extends('survey_module.layouts.klaes')

@section('page-title', 'Plot Confirmation — KLAES')

@php
  use App\Models\Survey\SurveyOpShowing as Showing;
  use App\Models\Survey\SurveyPlotCheck as Check;

  $PageTitle = 'Plot Confirmation';
  $PageDescription = 'From the Plot Confirmation app: OP plots shown to their allottees (Showing History) and ground checks of plots against their layout and the Beacon Register.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $tabUrl = fn ($t) => route('survey-module.compensation.plot-confirmations', ['tab' => $t]);
  $beaconCls = ['registered' => 'completed', 'other_surveyor' => 'pending', 'unregistered' => 'rejected'];
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<style>
    .survey-proto .pc-tabs { display:flex; gap:6px; margin-bottom:12px; }
    .survey-proto .pc-tabs a { padding:8px 14px; border-radius:8px; border:1.5px solid var(--gray-300,#d1d5db); font-weight:600; font-size:14px; color:var(--gray-700,#374151); text-decoration:none; }
    .survey-proto .pc-tabs a.on { background:var(--primary); border-color:var(--primary); color:#fff; }
    .survey-proto .pc-sub { font-size:12px; color:var(--gray-500); }
    .survey-proto .pc-photos { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .survey-proto .pc-photos img { width:120px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--gray-200,#e5e7eb); }
    .survey-proto .pc-kv { display:grid; grid-template-columns: 140px 1fr; gap:6px 12px; font-size:14px; }
    .survey-proto .pc-kv dt { color:var(--gray-500); }
    .survey-proto .pc-kv dd { margin:0; font-weight:600; }
</style>

<div x-data="{ view: null }">
<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ number_format($totals['showings']) }}</div><div class="pc-sub">{{ number_format($totals['ops']) }} OPs shown</div></div>
    <div class="kpi-card"><div class="kpi-label">Ground checks</div><div class="kpi-value">{{ number_format($totals['checks']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Discrepancies</div><div class="kpi-value" style="color:#b91c1c;">{{ number_format($totals['discrepancies']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Recorded with</div><div style="font-size:15px;font-weight:600;">Plot Confirmation app</div><div class="pc-sub">Records are made in the field, not here.</div></div>
</div>

<div class="pc-tabs">
    <a href="{{ $tabUrl('showings') }}" class="{{ $tab === 'showings' ? 'on' : '' }}">OP Showings</a>
    <a href="{{ $tabUrl('checks') }}" class="{{ $tab === 'checks' ? 'on' : '' }}">Ground Checks</a>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>{{ $tab === 'checks' ? 'Ground Checks' : 'OP Showings' }}</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $tab === 'checks' ? 'Ref, OP, plot, layout, beacon…' : 'OP, allottee, plot, surveyor…' }}" style="{{ $field }}width:220px;">
            @if ($tab === 'checks')
                <select name="result" style="{{ $field }}">
                    <option value="">All results</option>
                    @foreach (Check::RESULTS as $x)<option value="{{ $x }}" @selected(request('result') === $x)>{{ $x }}</option>@endforeach
                </select>
            @else
                <select name="reason" style="{{ $field }}">
                    <option value="">All showings</option>
                    <option value="first" @selected(request('reason') === 'first')>1st showings</option>
                    @foreach (Showing::REASONS as $x)<option value="{{ $x }}" @selected(request('reason') === $x)>{{ $x }}</option>@endforeach
                </select>
            @endif
            <input type="date" name="from" value="{{ request('from') }}" style="{{ $field }}" title="From">
            <input type="date" name="to" value="{{ request('to') }}" style="{{ $field }}" title="To">
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if (request()->hasAny(['q', 'result', 'reason', 'from', 'to']))<a class="btn btn-secondary btn-sm" href="{{ $tabUrl($tab) }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            @if ($tab === 'checks')
                <thead><tr><th>S/N</th><th>Ref</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>OP / Layout</th><th>Checked</th><th>Surveyor</th><th>Beacons</th><th>Result</th><th></th></tr></thead>
            @else
                <thead><tr><th>S/N</th><th>OP</th><th>Allottee</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Showing</th><th>Shown</th><th>Surveyor</th><th>Allottee there</th><th></th></tr></thead>
            @endif
            <tbody>
                @forelse ($rows as $x)
                    @php
                        $photos = $x->photos->map(fn ($p) => route($tab === 'checks' ? 'survey-module.mobile.plots.checks.photos.show' : 'survey-module.mobile.plots.showings.photos.show', $p))->values();
                        $map = $x->latitude !== null ? 'https://www.google.com/maps?q=' . $x->latitude . ',' . $x->longitude : null;
                    @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        @if ($tab === 'checks')
                            <td><strong>{{ $x->check_ref }}</strong></td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            <td>{{ $x->op_serial ? 'OP ' . $x->op_serial : '' }}{{ $x->op_serial && $x->layout_no ? ' · ' : '' }}{{ $x->layout_no }}<div class="pc-sub">{{ $x->holder_name }}</div></td>
                            <td>{{ $x->checked_on?->format('d M Y') }} <span class="pc-sub">{{ $x->checked_at }}</span></td>
                            <td>{{ $x->surveyor_name }}</td>
                            <td>@foreach ((array) $x->beacon_check as $b)<span class="status-badge {{ $beaconCls[$b['status']] ?? 'pending' }}" title="{{ $b['label'] }}" style="margin:0 3px 3px 0;">{{ $b['beacon'] }}</span>@endforeach{{ empty($x->beacon_check) ? '—' : '' }}</td>
                            <td><span class="status-badge {{ $x->result === Check::CONFIRMED ? 'completed' : 'rejected' }}">{{ $x->result }}</span>
                                @if ($x->discrepancy_label)<div class="pc-sub">{{ $x->discrepancy_label }}</div>@endif</td>
                        @else
                            <td><strong>{{ $x->op_serial ?: '—' }}</strong></td>
                            <td>{{ $x->allottee_name ?: '—' }}</td>
                            <td>{{ $x->plot_no ?: '—' }}</td>
                            <td>{{ $x->district ?: '—' }}</td>
                            <td>{{ $x->lga ?: '—' }}</td>
                            <td>{{ $x->location ?: '—' }}</td>
                            <td>{{ $x->label }}<div class="pc-sub">{{ $x->reason }}</div></td>
                            <td>{{ $x->shown_on?->format('d M Y') }} <span class="pc-sub">{{ $x->shown_at }}</span></td>
                            <td>{{ $x->surveyor_name }}</td>
                            <td>{{ $x->allottee_present === null ? '—' : ($x->allottee_present ? 'Yes' : 'No') }}</td>
                        @endif
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <button type="button" class="{{ $svItem }}"
                                            @click.stop="open = false; view = @js([
                                                'title'  => $tab === 'checks' ? 'Ground check ' . $x->check_ref : 'OP ' . ($x->op_serial ?: '—') . ' · ' . $x->label,
                                                'rows'   => array_filter($tab === 'checks' ? [
                                                    'Plot' => $x->target_label, 'Allottee' => $x->holder_name, 'Result' => $x->result . ($x->discrepancy_label ? ' — ' . $x->discrepancy_label : ''),
                                                    'Beacons' => collect((array) $x->beacon_check)->map(fn ($b) => $b['beacon'] . ': ' . $b['label'])->implode('; '),
                                                    'Checked' => trim($x->checked_on?->format('d M Y') . ' ' . $x->checked_at), 'Surveyor' => $x->surveyor_name, 'Notes' => $x->notes,
                                                    'GPS' => $x->latitude !== null ? round($x->latitude, 6) . ', ' . round($x->longitude, 6) . ($x->accuracy_m ? ' (±' . round($x->accuracy_m) . ' m)' : '') : null,
                                                ] : [
                                                    'Allottee' => $x->allottee_name, 'Showing' => $x->label . ($x->reason ? ' — ' . $x->reason : ''),
                                                    'Shown' => trim($x->shown_on?->format('d M Y') . ' ' . $x->shown_at), 'Surveyor' => $x->surveyor_name,
                                                    'Allottee there' => $x->allottee_present === null ? null : ($x->allottee_present ? 'Yes' : 'No'), 'Notes' => $x->notes,
                                                    'GPS' => $x->latitude !== null ? round($x->latitude, 6) . ', ' . round($x->longitude, 6) . ($x->accuracy_m ? ' (±' . round($x->accuracy_m) . ' m)' : '') : null,
                                                ]),
                                                'photos' => $photos,
                                            ])">
                                        <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View details
                                    </button>
                                    @if ($map)
                                        <a href="{{ $map }}" target="_blank" rel="noopener" class="{{ $svItem }}" @click="open = false"><i data-lucide="map-pin" class="w-4 h-4 text-gray-500"></i> Open in Maps</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $tab === 'checks' ? 'No ground checks' : 'No showings' }} {{ request()->hasAny(['q', 'result', 'reason', 'from', 'to']) ? 'match the filter' : 'recorded yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($rows->total()) }} {{ $tab === 'checks' ? 'ground checks' : 'showings' }}</span><div class="pagination">{{ $rows->links() }}</div></div>
</div>

{{-- Details --}}
<template x-if="view">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
        <div class="bg-white rounded-xl shadow-xl w-full" style="max-width:640px;max-height:90vh;overflow:auto;padding:20px;" @click.outside="view = null" @keydown.escape.window="view = null">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;" x-text="view.title"></h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="view = null" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <dl class="pc-kv">
                <template x-for="(v, k) in view.rows" :key="k"><div style="display:contents;"><dt x-text="k"></dt><dd x-text="v"></dd></div></template>
            </dl>
            <div class="pc-photos" x-show="view.photos.length">
                <template x-for="src in view.photos" :key="src"><a :href="src" target="_blank" rel="noopener"><img :src="src" alt="Photograph"></a></template>
            </div>
            <div class="pc-sub" x-show="!view.photos.length" style="margin-top:10px;">No photographs.</div>
        </div>
    </div>
</template>
</div>
@endsection
