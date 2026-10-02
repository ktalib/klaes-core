<div class="bg-white rounded-xl shadow p-5 border border-slate-100">
    <div class="flex items-center gap-3 mb-4">
        <span class="text-2xl">📊</span>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">This Week's Snapshot</p>
            <h3 class="text-lg font-semibold text-gray-900">Work Station highlights</h3>
        </div>
    </div>
    <div class="flex flex-wrap gap-4">
        <template x-for="item in state.summary_bar ?? []" :key="item.key">
            <div class="flex items-center gap-2 text-sm px-3 py-2 rounded-lg bg-slate-50 border border-slate-100">
                <span class="font-semibold text-gray-900" x-text="`${item.label}: ${formatNumber(item.value)}`"></span>
                <span class="text-xs font-semibold"
                      :class="item.trend?.direction === 'up' ? 'text-emerald-600' : (item.trend?.direction === 'down' ? 'text-rose-500' : 'text-gray-500')"
                      x-text="item.trend?.direction === 'up'
                        ? `↑ ${item.trend.change ?? 0}%`
                        : (item.trend?.direction === 'down'
                            ? `↓ ${item.trend.change ?? 0}%`
                            : '→ 0%')">
                </span>
            </div>
        </template>
    </div>
</div>
