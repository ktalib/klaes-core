<div x-show="detailModal.open" x-cloak class="fixed inset-0 z-40">
    <div class="absolute inset-0 bg-slate-900/40" @click="detailModal.open = false"></div>
    <div class="absolute right-0 top-0 bottom-0 w-full max-w-md bg-white shadow-2xl flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
                <p class="text-xs text-gray-500 uppercase font-semibold">User Detail</p>
                <h3 class="text-xl font-semibold text-gray-900" x-text="detailModal.user?.name"></h3>
                <p class="text-sm text-gray-500">
                    <span x-text="detailModal.user?.department || 'No Department'"></span>
                    <span class="mx-1 text-gray-400">•</span>
                    <span x-text="detailModal.user?.work_station || 'Unassigned Work Station'"></span>
                </p>
            </div>
            <button class="text-gray-400 hover:text-gray-600" @click="detailModal.open = false">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Weekly Breakdown</p>
                <template x-if="detailModal.weekly">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="text-left text-gray-500 font-semibold py-1">Activity Source</th>
                                    <template x-for="day in detailModal.weekly.days" :key="day">
                                        <th class="text-center text-gray-500 font-semibold py-1" x-text="day"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="module in Object.keys(detailModal.weekly.series)" :key="module">
                                    <tr>
                                        <td class="py-1 font-semibold text-gray-900" x-text="detailModal.weekly.series[module].label"></td>
                                        <template x-for="day in detailModal.weekly.days" :key="`${module}-${day}`">
                                            <td class="text-center py-1 text-gray-700"
                                                x-text="formatNumber(detailModal.weekly.series[module].data[day] ?? 0)"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
                <template x-if="!detailModal.weekly">
                    <p class="text-sm text-gray-500">Weekly data will appear once loaded.</p>
                </template>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Recent Activity</p>
                <div class="space-y-3 max-h-80 overflow-y-auto" x-show="detailModal.timeline.length">
                    <template x-for="item in detailModal.timeline" :key="`${item.module}-${item.timestamp}`">
                        <div class="border border-slate-100 rounded-lg p-3">
                            <p class="text-sm font-semibold text-gray-900" x-text="item.label"></p>
                            <p class="text-xs text-gray-500" x-text="new Date(item.timestamp).toLocaleString()"></p>
                            <p class="text-xs text-gray-600 mt-1" x-text="item.module"></p>
                        </div>
                    </template>
                </div>
                <p class="text-sm text-gray-500" x-show="!detailModal.timeline.length">Loading timeline...</p>
            </div>
        </div>
        <div class="px-6 py-4 border-t text-right">
            <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-semibold text-gray-600 hover:bg-slate-50"
                    @click="detailModal.open = false">
                Close
            </button>
        </div>
    </div>
</div>
