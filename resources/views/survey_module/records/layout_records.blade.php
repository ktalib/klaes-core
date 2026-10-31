@extends('survey_module.layouts.klaes')

@section('page-title', 'Layout Records — KLAES')

@php
  $PageTitle = 'Layout Records';
  $PageDescription = 'The records attached to each layout plan: plots on the allocation lists and the OPs issued, with how often each plot was shown to its allottee.';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $filtered = request()->hasAny(['q', 'has']);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Layouts</div><div class="kpi-value">{{ number_format($totals['layouts']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Allocation list lines</div><div class="kpi-value">{{ number_format($totals['lines']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">OPs on layouts</div><div class="kpi-value">{{ number_format($totals['ops']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ number_format($totals['showings']) }}</div></div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3>Layouts</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Layout no., plan or name…" style="{{ $field }}width:230px;">
            <select name="has" style="{{ $field }}">
                <option value="">All layouts</option>
                <option value="lists" @selected(request('has') === 'lists')>With allocation lists</option>
                <option value="shown" @selected(request('has') === 'shown')>With showings</option>
            </select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.layout-records') }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Layout No.</th><th>Name</th><th>Plan numbers as written</th><th style="text-align:right;">Allocation lines</th><th style="text-align:right;">OPs</th><th style="text-align:right;">Showings</th><th></th></tr></thead>
            <tbody>
                @forelse ($layouts as $l)
                    @php $url = route('survey-module.records.layout-records', ['layout' => $l['key']]); @endphp
                    <tr>
                        <td>{{ $layouts->firstItem() + $loop->index }}</td>
                        <td><a href="{{ $url }}"><strong>{{ $l['key'] }}</strong></a></td>
                        <td>{{ $l['name'] ?: '—' }}</td>
                        <td style="font-size:12.5px;">{{ collect(array_keys($l['plans']))->take(4)->implode(', ') }}{{ count($l['plans']) > 4 ? ' +' . (count($l['plans']) - 4) . ' more' : '' }}</td>
                        <td style="text-align:right;">{{ $l['lines'] ? number_format($l['lines']) : '—' }}</td>
                        <td style="text-align:right;">{{ $l['ops'] ? number_format($l['ops']) : '—' }}</td>
                        <td style="text-align:right;">{{ $l['showings'] ? number_format($l['showings']) : '—' }}</td>
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a class="{{ $svItem }}" href="{{ $url }}"><i data-lucide="table-2" class="w-4 h-4 text-gray-500"></i> Open records</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No layout matches the filter.' : 'No layout records yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($layouts->total()) }} layouts · counts refresh every 10 minutes (<a href="{{ route('survey-module.records.layout-records', ['refresh' => 1]) }}">refresh now</a>)</span><div class="pagination">{{ $layouts->links() }}</div></div>
</div>
@endsection
