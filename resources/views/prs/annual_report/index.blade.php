@extends('layouts.app')

@section('page-title')
    PRS Annual Progress Report {{ $year }}
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/prs/annual-report.css') }}">
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', [
        'PageTitle'       => 'PRS Annual Progress Report',
        'PageDescription' => 'Survey, Deeds and Land Department progress statistics, prepared by the Department of Planning, Research and Statistics.',
    ])

    <div class="p-6 space-y-6">

        {{-- Data-quality caveats and the prototype notice previously rendered here.
             They now live in docs/prs-2025/19-ui-caveat-log.md. --}}

        {{-- ── Filters: one row above the charts ──────────────────────────── --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="inline-flex items-center gap-1.5 p-1.5 rounded-xl bg-slate-100 border border-slate-200">
                <a href="{{ route('prs-report.index', ['dept' => 'all']) }}"
                   class="dept-btn {{ $department === 'all' ? 'dept-btn-active bg-white text-slate-900 shadow-sm' : 'dept-btn-inactive text-slate-500 hover:text-slate-800' }}">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    All Departments
                </a>
                @foreach($departments as $key => $meta)
                    <a href="{{ route('prs-report.index', ['dept' => $key]) }}"
                       class="dept-btn {{ $department === $key ? 'dept-btn-active bg-white text-slate-900 shadow-sm' : 'dept-btn-inactive text-slate-500 hover:text-slate-800' }}">
                        <i data-lucide="{{ $meta['icon'] }}" class="w-4 h-4"></i>
                        {{ $meta['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2.5">
                <div class="dropdown-combo">
                    {{-- Years come from the data, not a hardcoded list: only years that
                         actually carry activity are offered. --}}
                    <select id="prs_year"
                            onchange="window.location = '{{ route('prs-report.index', ['dept' => $department]) }}&year=' + this.value"
                            @disabled(count($years ?? []) < 2)
                            title="{{ count($years ?? []) < 2 ? 'Only ' . $year . ' has data' : 'Reporting year' }}">
                        @forelse($years ?? [] as $y)
                            <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                        @empty
                            <option>{{ $year }}</option>
                        @endforelse
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 dropdown-combo-icon"></i>
                </div>

                <button type="button" id="btn_print" class="btn-action-secondary">
                    <i data-lucide="printer" class="w-4 h-4 text-slate-500"></i> Print Report
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="btn-action-primary">
                        <i data-lucide="download" class="w-4 h-4"></i> Export
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 mt-1.5 w-60 bg-white rounded-xl border border-slate-200 shadow-xl z-20 py-1.5 p-1">
                        @foreach([
                            ['word',  'file-text',       'Word (.docx)',  'Editable narrative — priority 1'],
                            ['excel', 'table-2',         'Excel (.xlsx)', 'One sheet per section'],
                            ['pdf',   'file-down',       'PDF',           'Charts embedded as images'],
                        ] as [$fmt, $icon, $label, $hint])
                            <button type="button" disabled
                                    class="w-full text-left px-3 py-2 flex items-start gap-2.5 opacity-50 cursor-not-allowed rounded-lg hover:bg-slate-50 transition">
                                <i data-lucide="{{ $icon }}" class="w-4.5 h-4.5 mt-0.5 text-slate-400"></i>
                                <span>
                                    <span class="block text-xs font-semibold text-slate-700">{{ $label }}</span>
                                    <span class="block text-[10px] text-slate-400 mt-0.5">{{ $hint }}</span>
                                </span>
                            </button>
                        @endforeach
                        <p class="px-3 pt-2 mt-1.5 border-t border-slate-100 text-[10px] text-slate-400 leading-relaxed">
                            Exports arrive with the backend. All three libraries are already installed.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Headline tiles ─────────────────────────────────────────────── --}}
        <div>
            <div class="flex items-center gap-2 mb-4">
                <i data-lucide="line-chart" class="w-4.5 h-4.5 text-slate-500"></i>
                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">{{ $year }} At a Glance</h2>
            </div>

            <div class="stats-cards-grid">
                @foreach($highlights as $tile)
                    @if($department === 'all' || $tile['dept'] === $department)
                        @php
                            $palette = [
                                'survey plots laid out' => ['surface' => '#f0fdf8', 'border' => '#a7f3d0', 'accent' => '#059669', 'ink' => '#065f46', 'icon' => '#d1fae5', 'shadow' => 'rgba(5,150,105,0.26)'],
                                'occupancy permits' => ['surface' => '#eff6ff', 'border' => '#bfdbfe', 'accent' => '#2563eb', 'ink' => '#1e3a8a', 'icon' => '#dbeafe', 'shadow' => 'rgba(37,99,235,0.24)'],
                                'direct allocation apps' => ['surface' => '#faf5ff', 'border' => '#ddd6fe', 'accent' => '#7c3aed', 'ink' => '#581c87', 'icon' => '#ede9fe', 'shadow' => 'rgba(124,58,237,0.24)'],
                                'conversion applications' => ['surface' => '#fff7ed', 'border' => '#fed7aa', 'accent' => '#ea580c', 'ink' => '#9a3412', 'icon' => '#ffedd5', 'shadow' => 'rgba(234,88,12,0.26)'],
                                'oss applications' => ['surface' => '#fdf2f8', 'border' => '#fbcfe8', 'accent' => '#db2777', 'ink' => '#9d174d', 'icon' => '#fce7f3', 'shadow' => 'rgba(219,39,119,0.24)'],
                                'deeds of assignment' => ['surface' => '#ecfeff', 'border' => '#a5f3fc', 'accent' => '#0891b2', 'ink' => '#155e75', 'icon' => '#cffafe', 'shadow' => 'rgba(8,145,178,0.24)'],
                                'certificates of occupancy' => ['surface' => '#fefce8', 'border' => '#fde68a', 'accent' => '#ca8a04', 'ink' => '#854d0e', 'icon' => '#fef3c7', 'shadow' => 'rgba(202,138,4,0.24)'],
                                'mortgages' => ['surface' => '#fff1f2', 'border' => '#fecdd3', 'accent' => '#e11d48', 'ink' => '#9f1239', 'icon' => '#ffe4e6', 'shadow' => 'rgba(225,29,72,0.24)'],
                            ][strtolower($tile['label'])] ?? ['surface' => '#f8fafc', 'border' => '#cbd5e1', 'accent' => '#64748b', 'ink' => '#1e293b', 'icon' => '#e2e8f0', 'shadow' => 'rgba(100,116,139,0.25)'];
                        @endphp
                        <div class="stat-glow-card metric-card"
                             style="--metric-surface: {{ $palette['surface'] }}; --metric-border: {{ $palette['border'] }}; --metric-accent: {{ $palette['accent'] }}; --metric-ink: {{ $palette['ink'] }}; --metric-icon-bg: {{ $palette['icon'] }}; --metric-shadow: {{ $palette['shadow'] }};">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">
                                    {{ $tile['label'] }}
                                </span>
                                <div class="p-1.5 rounded-lg bg-slate-50">
                                    <i data-lucide="{{ $tile['icon'] }}" class="w-4 h-4 text-slate-400"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-extrabold text-slate-800 leading-none tabular-nums tracking-tight">
                                {{ number_format($tile['value']) }}
                            </div>
                            <div class="text-xs text-slate-500 mt-2 font-medium flex items-center gap-1">
                                <span class="badge-soft {{ $tile['dept'] === 'survey' ? 'badge-soft-emerald' : ($tile['dept'] === 'deeds' ? 'badge-soft-blue' : 'badge-soft-violet') }}">
                                    {{ $tile['dept'] }}
                                </span>
                                <span class="text-slate-400 font-normal">— {{ $tile['note'] }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

        </div>

        {{-- ── Sections ───────────────────────────────────────────────────── --}}
        <div class="space-y-5">
            @forelse($sections as $section)
                @include('prs.annual_report.partials.section', ['section' => $section, 'year' => $year])
            @empty
                <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
                    <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                    <p class="text-sm text-slate-500 mt-2">No sections for this department.</p>
                </div>
            @endforelse
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    /* Print: drop the app chrome, keep tables and charts, never split a section. */
    @media print {
        .sidebar, nav, header.app-header, #btn_print, .prs-noprint { display: none !important; }
        .prs-section { break-inside: avoid; page-break-inside: avoid; border-color: #cbd5e1 !important; box-shadow: none !important; }
        body { background: #fff !important; }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/prs/annual-report.js') }}"></script>

@if(request()->query('sec'))
<script>
    // ?sec= comes from the PRS sidebar, which deep-links to a section rather than
    // sending every entry to the top of the same page.
    document.addEventListener('DOMContentLoaded', function () {
        var target = document.getElementById('section-{{ request()->query('sec') }}');

        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.classList.add('ring-2', 'ring-indigo-400', 'ring-offset-2');
        setTimeout(function () {
            target.classList.remove('ring-2', 'ring-indigo-400', 'ring-offset-2');
        }, 2200);
    });
</script>
@endif
@endpush
