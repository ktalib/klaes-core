<div class="bg-white rounded-xl shadow border border-slate-100">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 p-6 border-b border-slate-100">
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress Tracking</p>
            <h2 class="text-2xl font-semibold text-gray-900">Target vs Actual</h2>
        </div>
        <p class="text-sm text-gray-500" x-text="state.leaderboard?.meta?.limited
            ? `Showing top ${state.leaderboard.meta.limit} of ${state.leaderboard.meta.total} users (auto-truncated for performance).`
            : 'Sorted by total units processed across selected Work Stations.'">
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Work Station</th>
                    <template x-for="column in state.leaderboard?.columns ?? []" :key="column.key">
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide" x-text="column.label"></th>
                    </template>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Target</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">% Complete</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Trend</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <template x-for="row in state.leaderboard?.rows ?? []" :key="row.user.id">
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center font-semibold text-white"
                                     :style="`background:${row.user.department_color || '#2563eb'}`"
                                     x-text="row.user.name.split(' ').map(word => word[0]).slice(0,2).join('')"></div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900" x-text="row.user.name"></p>
                                    <p class="text-xs text-gray-500 flex items-center gap-1">
                                        <span class="inline-flex h-2 w-2 rounded-full"
                                              :style="`background:${row.user.department_color || '#cbd5f5'}`"></span>
                                        <span x-text="row.user.department || 'No Department'"></span>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-600" x-text="row.user.work_station || '—'"></td>
                        <template x-for="column in state.leaderboard?.columns ?? []" :key="`${row.user.id}-${column.key}`">
                            <td class="px-4 py-4 text-center text-sm text-gray-900" x-text="formatNumber(row.metrics[column.key] ?? 0)"></td>
                        </template>
                        <td class="px-4 py-4 text-center text-sm font-semibold text-gray-900" x-text="formatNumber(row.total)"></td>
                        <td class="px-4 py-4 text-center text-sm text-gray-600" x-text="formatNumber(row.target)"></td>
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
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-end gap-1 justify-center h-10">
                                <template x-for="value in row.sparkline" :key="`${row.user.id}-spark-${value}-${$index}`">
                                    <span class="w-1.5 rounded-full bg-blue-500/70" :style="`height:${Math.max(2, value)}px`"></span>
                                </template>
                            </div>
                            <div class="text-xs text-indigo-600 mt-2 flex items-center justify-center gap-2">
                                <button class="hover:underline font-semibold" @click="openUserModal(row)">Detail</button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
