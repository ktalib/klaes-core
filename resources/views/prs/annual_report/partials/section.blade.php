@php
    $deptMeta = \App\Services\Prs\PrsSampleData::DEPARTMENTS[$section['department']] ?? ['label' => '—', 'icon' => 'folder'];
    $chartH   = in_array($section['chart']['type'], ['bar-h', 'stacked-bar-h'], true)
                ? max(200, count($section['chart']['labels']) * 34 + 70)
                : 300;
    $ins      = $section['insights'] ?? [];
@endphp

<section id="section-{{ $section['key'] }}"
         class="prs-section group bg-white rounded-xl border border-slate-200 shadow-sm scroll-mt-24"
         data-dept="{{ $section['department'] }}">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <header class="px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-white to-slate-50">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-start gap-3.5 min-w-0">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 text-slate-700 shrink-0 text-sm font-bold tabular-nums border border-slate-200 shadow-sm">
                    {{ $section['no'] }}
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-extrabold text-slate-800 leading-snug flex items-center gap-2">
                        {{ $section['title'] }}
                        <a href="#section-{{ $section['key'] }}"
                           class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity text-slate-300 hover:text-indigo-600"
                           aria-label="Link to this section">
                            <i data-lucide="link" class="w-4 h-4"></i>
                        </a>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1 font-medium">{{ $section['subtitle'] }}</p>
                </div>
            </div>

            <span class="dept-tag dept-{{ $section['department'] }}">
                <i data-lucide="{{ $deptMeta['icon'] }}" class="w-4 h-4"></i>
                {{ $deptMeta['label'] }}
            </span>
        </div>

        <div class="flex items-center gap-x-6 gap-y-1.5 flex-wrap mt-4 text-[11px] text-slate-500 border-t border-slate-100 pt-3">
            <span class="inline-flex items-center gap-2">
                <i data-lucide="sigma" class="w-4 h-4 text-slate-400"></i>
                <span class="text-slate-400">Measure:</span>
                <strong class="text-slate-700 font-bold border-b border-dotted border-slate-300 pb-0.5">{{ $section['measure'] }}</strong>
            </span>
            <span class="inline-flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
                <span class="text-slate-400">Basis:</span>
                <strong class="text-slate-700 font-bold border-b border-dotted border-slate-300 pb-0.5">{{ $section['date_basis'] }}</strong>
            </span>
        </div>
    </header>

    <div class="p-6 space-y-6">

        {{-- ── Headline figure + chart ─────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- Hero number --}}
            <div class="lg:col-span-1">
                <div class="metric-highlight-card dept-{{ $section['department'] }}-line">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">
                        {{ $year }} Total
                    </div>
                    <div class="text-[2.25rem] leading-none font-extrabold text-slate-800 mt-2.5 tabular-nums tracking-tight">
                        {{ number_format($section['headline']['value']) }}
                    </div>
                    <div class="text-xs text-slate-500 mt-2 font-medium bg-slate-100 px-2 py-1 rounded inline-flex self-start">{{ $section['headline']['unit'] }}</div>

                    @if(!empty($ins['top']))
                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <div class="flex items-baseline justify-between gap-2 mb-2">
                                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold truncate">
                                    {{ $ins['top']['label'] }}
                                </span>
                                <span class="text-xs font-extrabold text-slate-700 tabular-nums shrink-0">
                                    {{ $ins['top']['share'] }}%
                                </span>
                            </div>
                            <div class="insight-progress-bar">
                                <div class="insight-progress-fill"
                                     style="width: {{ min(100, $ins['top']['share']) }}%; background-color: {{ sscanf($ins['top']['color'], '#%2x%2x%2x') ? $ins['top']['color'] : '#3b82f6' }}"></div>
                            </div>
                        </div>
                    @endif

                    @if(!empty($ins['peak']))
                        <div class="mt-4 pt-4 border-t border-slate-200 space-y-2.5">
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="inline-flex items-center gap-1.5 text-slate-400 font-medium">
                                    <i data-lucide="trending-up" class="w-3.5 h-3.5 text-emerald-500"></i> Peak Period
                                </span>
                                <span class="text-slate-700 font-bold truncate">
                                    {{ $ins['peak']['label'] }}
                                    <span class="text-slate-400 font-semibold tabular-nums">({{ number_format($ins['peak']['value']) }})</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="inline-flex items-center gap-1.5 text-slate-400 font-medium">
                                    <i data-lucide="trending-down" class="w-3.5 h-3.5 text-rose-500"></i> Lowest Period
                                </span>
                                <span class="text-slate-700 font-bold truncate">
                                    {{ $ins['trough']['label'] }}
                                    <span class="text-slate-400 font-semibold tabular-nums">({{ number_format($ins['trough']['value']) }})</span>
                                </span>
                            </div>
                        </div>
                    @endif

                    <div class="text-[11px] text-slate-400 mt-auto pt-4 border-t border-slate-100 leading-relaxed font-medium">
                        {{ $section['headline']['caption'] }}
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="chart-container-wrapper">
                    <div style="height: {{ $chartH }}px" class="relative">
                        <canvas id="chart-{{ $section['key'] }}"
                                data-chart="{{ json_encode($section['chart']) }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Small multiple --}}
        @if(!empty($section['chart_secondary']))
            @php
                $sec  = $section['chart_secondary'];
                $secH = in_array($sec['type'], ['bar-h', 'stacked-bar-h'], true)
                        ? max(140, count($sec['labels']) * 30 + 50)
                        : 200;
            @endphp
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5">
                <div class="secondary-insight-alert mb-4">
                    <div class="p-1 rounded bg-slate-200 shrink-0">
                        <i data-lucide="{{ $sec['icon'] ?? ($sec['title'] ?? '') === 'Gender breakdown' ? 'users' : 'zoom-in' }}" class="w-4 h-4 text-slate-600"></i>
                    </div>
                    <div>
                        {{-- Titled by the panel itself: this slot now carries the gender
                             cut on every section, not only a minor-category zoom. --}}
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">{{ $sec['title'] ?? 'Minor Categories Zoom' }}</h4>
                        <p class="text-[11px] text-slate-500 font-medium leading-normal">{{ $sec['caption'] }}</p>
                    </div>
                </div>
                <div class="secondary-chart-wrapper">
                    <div style="height: {{ $secH }}px" class="relative">
                        <canvas id="chart-{{ $section['key'] }}-minor"
                                data-chart="{{ json_encode($sec) }}"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Table ───────────────────────────────────────────────────────── --}}
        <div>
            <button type="button"
                    class="js-toggle-table w-full flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors"
                    data-target="table-{{ $section['key'] }}"
                    aria-expanded="true">
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <i data-lucide="table-2" class="w-3.5 h-3.5"></i>
                    Data table
                    <span class="text-slate-400 font-normal">{{ count($section['table']['rows']) }} rows</span>
                </span>
                <i data-lucide="chevron-up" class="w-4 h-4 text-slate-400 transition-transform js-chevron"></i>
            </button>

            <div id="table-{{ $section['key'] }}" class="js-table-wrap mt-3">
                @include('prs.annual_report.partials.table', ['table' => $section['table']])
            </div>
        </div>

        {{-- ── Gender table ────────────────────────────────────────────────── --}}
        @if(!empty($section['table_secondary']))
            <div>
                <button type="button"
                        class="js-toggle-table w-full flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors"
                        data-target="table-{{ $section['key'] }}-gender"
                        aria-expanded="true">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        {{ $section['table_secondary']['title'] ?? 'Gender breakdown' }}
                        <span class="text-slate-400 font-normal">
                            {{ count($section['table_secondary']['rows']) }} rows · same total as above
                        </span>
                    </span>
                    <i data-lucide="chevron-up" class="w-4 h-4 text-slate-400 transition-transform js-chevron"></i>
                </button>

                <div id="table-{{ $section['key'] }}-gender" class="js-table-wrap mt-3">
                    @include('prs.annual_report.partials.table', ['table' => $section['table_secondary']])
                </div>
            </div>
        @endif

        {{-- Per-section notes & caveats previously rendered here.
             They now live in docs/prs-2025/19-ui-caveat-log.md. --}}
    </div>
</section>
