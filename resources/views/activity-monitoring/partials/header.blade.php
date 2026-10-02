@php
    $activityTabs = [
        ['key' => 'team', 'label' => 'Team Performance', 'icon' => 'fa-gauge-high'],
        ['key' => 'performance', 'label' => 'Performance Metrics', 'icon' => 'fa-chart-area'],
        ['key' => 'workstations', 'label' => 'Work Station Highlights', 'icon' => 'fa-layer-group'],
        ['key' => 'target', 'label' => 'Target vs Actual', 'icon' => 'fa-table-list'],
        ['key' => 'leaderboard', 'label' => 'Leaderboard (Top 15)', 'icon' => 'fa-ranking-star'],
    ];
@endphp
<div class="bg-white rounded-2xl shadow border border-slate-100 px-4 sm:px-6 py-4 flex flex-wrap gap-3">
    @foreach ($activityTabs as $tab)
        <button
            class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-semibold transition"
            :class="activeTab === '{{ $tab['key'] }}'
                ? 'bg-indigo-600 text-white border-indigo-600 shadow'
                : 'text-slate-600 border-slate-200 hover:text-slate-900'"
            @click="setTab('{{ $tab['key'] }}')">
            <i class="fas {{ $tab['icon'] }} text-xs"></i>
            {{ $tab['label'] }}
        </button>
    @endforeach
</div>
