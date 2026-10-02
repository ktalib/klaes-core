<div class="bg-white rounded-xl shadow border border-indigo-100 overflow-hidden">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-indigo-50 px-6 py-4 bg-indigo-50/40">
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Extended Leaderboard</p>
            <h2 class="text-2xl font-semibold text-gray-900">Top 15 Contributors</h2>
        </div>
        <div class="text-sm text-gray-500" x-text="leaderboardTab.meta?.date_range
            ? `Reporting window: ${leaderboardTab.meta.date_range.start} → ${leaderboardTab.meta.date_range.end}`
            : 'Reporting window matches current filters.'"></div>
    </div>
    <div class="p-6 space-y-6">
        <template x-if="leaderboardTab.loading">
            <div class="flex items-center justify-center py-16 text-indigo-600 gap-3 text-lg font-semibold">
                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" d="M4 12a8 8 0 018-8v8z" fill="currentColor"></path>
                </svg>
                Loading leaderboard...
            </div>
        </template>
        <template x-if="!leaderboardTab.loading">
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <template x-for="card in leaderboardTab.summary?.items ?? []" :key="card.key">
                        <div class="rounded-2xl border border-indigo-50 bg-gradient-to-br from-white to-indigo-50/60 p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide" x-text="card.label"></p>
                            <p class="mt-2 text-2xl font-bold text-gray-900">
                                <span x-text="formatNumber(card.value ?? 0)"></span>
                                <span class="text-sm font-semibold text-gray-400" x-show="card.suffix" x-text="card.suffix"></span>
                            </p>
                        </div>
                    </template>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Work Station</th>
                                <template x-for="column in leaderboardTab.columns ?? []" :key="`leaderboard-head-${column.key}`">
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide" x-text="column.label"></th>
                                </template>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Target</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">% Complete</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white" x-show="(leaderboardTab.rows ?? []).length">
                            <template x-for="row in leaderboardTab.rows" :key="row.user.id">
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900" x-text="row.user.name"></td>
                                    <td class="px-4 py-4 text-sm text-gray-600" x-text="row.user.department || '—'"></td>
                                    <td class="px-4 py-4 text-sm text-gray-600" x-text="row.user.work_station || '—'"></td>
                                    <template x-for="column in leaderboardTab.columns ?? []" :key="`${row.user.id}-${column.key}`">
                                        <td class="px-4 py-4 text-center text-sm text-gray-900" x-text="formatNumber(row.metrics && row.metrics[column.key] ? row.metrics[column.key] : 0)"></td>
                                    </template>
                                    <td class="px-4 py-4 text-center text-sm font-semibold text-gray-900" x-text="formatNumber(row.total ?? 0)"></td>
                                    <td class="px-4 py-4 text-center text-sm text-gray-600" x-text="formatNumber(row.target ?? 0)"></td>
                                    <td class="px-4 py-4 text-center text-sm font-semibold" x-text="formatPercent(row.percent_complete ?? 0)"></td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                              :class="row.status === 'exceeded'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : (row.status === 'on_track'
                                                    ? 'bg-amber-100 text-amber-800'
                                                    : 'bg-rose-100 text-rose-800')"
                                              x-text="row.status === 'exceeded' ? 'Exceeded' : (row.status === 'on_track' ? 'On Track' : 'Behind')">
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="py-10 text-center text-sm text-gray-500" x-show="!leaderboardTab.rows?.length">
                        No leaderboard data for the current filters.
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
