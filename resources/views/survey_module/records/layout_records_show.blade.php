@extends('survey_module.layouts.klaes')

@section('page-title', 'Layout ' . $layout['key'] . ' — Layout Records — KLAES')

@php
  $PageTitle = 'Layout ' . $layout['key'] . ($layout['name'] ? ' · ' . $layout['name'] : '');
  $PageDescription = 'Plan numbers: ' . collect(array_keys($layout['plans']))->take(6)->implode(', ') . (count($layout['plans']) > 6 ? ' …' : '');
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $filtered = request()->hasAny(['q', 'show']);
  $here = fn (array $extra = []) => route('survey-module.records.layout-records', array_merge(['layout' => $layout['key']], $extra));
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<style>
    .survey-proto .lr-sub { font-size:12px; color:var(--gray-500); }
    .survey-proto .lr-kv { display:grid; grid-template-columns: 130px 1fr; gap:6px 12px; font-size:14px; margin-bottom:12px; }
    .survey-proto .lr-kv dt { color:var(--gray-500); }
    .survey-proto .lr-kv dd { margin:0; font-weight:600; overflow-wrap:anywhere; }
    .survey-proto table.lr-t { width:100%; border-collapse:collapse; font-size:13.5px; }
    .survey-proto table.lr-t th, .survey-proto table.lr-t td { text-align:left; padding:6px 8px; border-bottom:1px solid var(--gray-100,#f3f4f6); }
</style>

<div x-data="{ view: null }">
<div class="page-header">
    <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.layout-records') }}"><i class="fas fa-arrow-left"></i> All layouts</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Plots</div><div class="kpi-value">{{ number_format($totals['plots']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">On allocation lists</div><div class="kpi-value">{{ number_format($totals['listed']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">With an OP</div><div class="kpi-value">{{ number_format($totals['with_op']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Shown to allottee</div><div class="kpi-value">{{ number_format($totals['shown']) }}</div></div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>Records</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="layout" value="{{ $layout['key'] }}">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Plot, allottee or OP no.…" style="{{ $field }}width:220px;">
            <select name="show" style="{{ $field }}">
                <option value="">All plots</option>
                <option value="op" @selected(request('show') === 'op')>With an OP</option>
                <option value="no-op" @selected(request('show') === 'no-op')>No OP yet</option>
                <option value="shown" @selected(request('show') === 'shown')>Shown to allottee</option>
                <option value="unshown" @selected(request('show') === 'unshown')>OP not yet shown</option>
            </select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ $here() }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Allottee</th><th>Allocation list</th><th>OP No.</th><th style="text-align:right;">Times shown</th><th>Last shown</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $x)
                    @php $n = count($x['showings']); @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $x['plot'] ?: '—' }}</strong></td>
                        <td>{{ $x['district'] ?: '—' }}</td>
                        <td>{{ $x['lga'] ?: '—' }}</td>
                        <td>{{ $x['location'] ?: '—' }}</td>
                        <td>{{ $x['allottee'] ?: '—' }}</td>
                        <td>@if ($x['lines']){{ collect($x['lines'])->pluck('case')->unique()->implode(', ') }}<div class="lr-sub">{{ collect($x['lines'])->pluck('status')->filter()->unique()->implode(', ') }}</div>@else — @endif</td>
                        <td>{{ collect($x['ops'])->pluck('serial')->filter()->implode(', ') ?: '—' }}</td>
                        <td style="text-align:right;">{{ $x['ops'] ? $n : '—' }}</td>
                        <td>@if ($x['last_shown']){{ \Illuminate\Support\Carbon::parse($x['last_shown'])->format('d M Y') }}<div class="lr-sub">{{ $x['last_by'] }}</div>@else — @endif</td>
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <button type="button" class="{{ $svItem }}" @click.stop="open = false; view = @js(['plot' => $x['plot'], 'allottee' => $x['allottee'], 'lines' => $x['lines'], 'ops' => array_values($x['ops']), 'showings' => $x['showings']])">
                                        <i data-lucide="history" class="w-4 h-4 text-gray-500"></i> Records and showing history
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No plot matches the filter.' : 'No records on this layout.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($rows->total()) }} plots · showings are recorded with the Plot Confirmation app</span><div class="pagination">{{ $rows->links() }}</div></div>
</div>

<template x-if="view">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
        <div class="bg-white rounded-xl shadow-xl w-full" style="max-width:720px;max-height:90vh;overflow:auto;padding:20px;" @click.outside="view = null" @keydown.escape.window="view = null">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;" x-text="'Plot ' + (view.plot || '—')"></h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="view = null" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <dl class="lr-kv"><dt>Allottee</dt><dd x-text="view.allottee || '—'"></dd></dl>

            <h4 style="font-size:14px;font-weight:700;margin:8px 0;">Allocation lists</h4>
            <template x-if="!view.lines.length"><p class="lr-sub">Not on an imported allocation list.</p></template>
            <template x-if="view.lines.length">
                <table class="lr-t" data-column-picker="off"><thead><tr><th>Land</th><th>F/N</th><th>Area (ha)</th><th>Surveyor</th><th>Status</th></tr></thead>
                    <tbody><template x-for="(l, i) in view.lines" :key="i"><tr><td x-text="l.case"></td><td x-text="l.f_no || '—'"></td><td x-text="l.area || '—'"></td><td x-text="l.surveyor || '—'"></td><td x-text="l.status || '—'"></td></tr></template></tbody></table>
            </template>

            <h4 style="font-size:14px;font-weight:700;margin:14px 0 8px;">OPs</h4>
            <template x-if="!view.ops.length"><p class="lr-sub">No OP found on this plot.</p></template>
            <template x-if="view.ops.length">
                <table class="lr-t" data-column-picker="off"><thead><tr><th>OP No.</th><th>Allottee</th><th>Type</th><th>File No.</th></tr></thead>
                    <tbody><template x-for="o in view.ops" :key="o.key"><tr><td x-text="o.serial || '—'"></td><td x-text="o.allottee || '—'"></td><td x-text="o.type || '—'"></td><td x-text="o.file || '—'"></td></tr></template></tbody></table>
            </template>

            <h4 style="font-size:14px;font-weight:700;margin:14px 0 8px;">Showings to the allottee</h4>
            <template x-if="!view.showings.length"><p class="lr-sub">Not shown yet.</p></template>
            <template x-if="view.showings.length">
                <table class="lr-t" data-column-picker="off"><thead><tr><th>Showing</th><th>Date</th><th>Surveyor</th><th>Reason</th><th>Allottee there</th></tr></thead>
                    <tbody><template x-for="(s, i) in view.showings" :key="i"><tr><td x-text="'#' + s.no"></td><td x-text="s.on + (s.at ? ' ' + s.at : '')"></td><td x-text="s.surveyor"></td><td x-text="s.reason || '—'"></td><td x-text="s.present === null ? '—' : (s.present ? 'Yes' : 'No')"></td></tr></template></tbody></table>
            </template>
        </div>
    </div>
</template>
</div>
@endsection
