@extends('survey_module.layouts.klaes')

@section('page-title', 'Letter of Grant — KLAES')

@php
  $PageTitle = 'Letter of Grant';
  $PageDescription = 'Every Direct Allocation Occupancy Permit, from Deeds (captured instruments and the older registry records).';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $filtered = request()->hasAny(['q', 'source', 'from', 'to']);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<style>
    .survey-proto .lg-sub { font-size:12px; color:var(--gray-500); }
    .survey-proto .lg-kv { display:grid; grid-template-columns: 150px 1fr; gap:6px 12px; font-size:14px; }
    .survey-proto .lg-kv dt { color:var(--gray-500); }
    .survey-proto .lg-kv dd { margin:0; font-weight:600; overflow-wrap:anywhere; }
</style>

<div x-data="{ view: null }">
<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Direct Allocation OPs</div><div class="kpi-value">{{ number_format($totals['all']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Captured instruments</div><div class="kpi-value">{{ number_format($totals['capture']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Older registry records</div><div class="kpi-value">{{ number_format($totals['pra']) }}</div></div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>Direct Allocation OPs</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="OP no., allottee, plot, file, reg. no., district…" style="{{ $field }}width:260px;">
            <select name="source" style="{{ $field }}">
                <option value="">All records</option>
                <option value="capture" @selected(request('source') === 'capture')>Captured instruments</option>
                <option value="pra" @selected(request('source') === 'pra')>Older registry records</option>
            </select>
            <input type="date" name="from" value="{{ request('from') }}" style="{{ $field }}" title="Registered from">
            <input type="date" name="to" value="{{ request('to') }}" style="{{ $field }}" title="Registered to">
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ route('survey-module.compensation.letter-of-grant') }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>OP No.</th><th>Allottee</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Layout</th><th>File No.</th><th>Reg. No.</th><th>Reg. Date</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $x)
                    @php $regDate = $x->reg_date ? \Illuminate\Support\Carbon::parse($x->reg_date)->format('d M Y') : null; @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $x->serial ?: '—' }}</strong></td>
                        <td>{{ $x->allottee ?: '—' }}@if ($x->allottee && $x->allottee_from_file)<div class="lg-sub">from the land file</div>@endif</td>
                        <td>{{ $x->plot_no ?: '—' }}</td>
                        <td>{{ $x->district ?: '—' }}</td>
                        <td>{{ $x->lga ?: '—' }}</td>
                        <td>{{ $x->location ?: '—' }}</td>
                        <td>{{ $x->layout ?: '—' }}</td>
                        <td>{{ $x->file_number ?: '—' }}</td>
                        <td>{{ $x->reg_no ?: '—' }}</td>
                        <td>{{ $regDate ?: '—' }}</td>
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <button type="button" class="{{ $svItem }}" @click.stop="open = false; view = @js([
                                        'title' => 'OP ' . ($x->serial ?: '—'),
                                        'rows'  => array_filter([
                                            'Allottee' => $x->allottee ? $x->allottee . ($x->allottee_from_file ? ' (from the land file)' : '') : null,
                                            'Plot No.' => $x->plot_no, 'District' => $x->district, 'LGA' => $x->lga, 'Location' => $x->location,
                                            'Plot size' => $x->plot_size, 'Land use' => $x->land_use, 'Layout' => $x->layout,
                                            'File No.' => $x->file_number, 'Reg. No.' => $x->reg_no, 'Reg. Date' => $regDate,
                                            'Record' => $x->src === 'capture' ? 'Captured instrument' : 'Older registry record',
                                        ]),
                                    ])">
                                        <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View details
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No Direct Allocation OP matches the filter.' : 'No Direct Allocation OPs found.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($rows->total()) }} OPs</span><div class="pagination">{{ $rows->links() }}</div></div>
</div>

<template x-if="view">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.45);">
        <div class="bg-white rounded-xl shadow-xl w-full" style="max-width:600px;max-height:90vh;overflow:auto;padding:20px;" @click.outside="view = null" @keydown.escape.window="view = null">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;" x-text="view.title"></h3>
                <button type="button" class="btn btn-secondary btn-sm" @click="view = null" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <dl class="lg-kv">
                <template x-for="(v, k) in view.rows" :key="k"><div style="display:contents;"><dt x-text="k"></dt><dd x-text="v"></dd></div></template>
            </dl>
        </div>
    </div>
</template>
</div>
@endsection
