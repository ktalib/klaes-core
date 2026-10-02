<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 bg-slate-50 rounded-xl p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-xs font-semibold text-blue-500 uppercase tracking-wide">Heatmap</p>
                <h3 class="text-lg font-semibold text-gray-900">Activity over time</h3>
            </div>
            <span class="text-sm text-gray-500" x-text="`${state.charts?.activity_heatmap?.categories?.length ?? 0} days`"></span>
        </div>
        <div class="h-80" x-ref="heatmapChart"></div>
    </div>
    <div class="space-y-6">
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs font-semibold text-emerald-500 uppercase tracking-wide">Top contributors</p>
                    <h3 class="text-lg font-semibold text-gray-900">User contribution</h3>
                </div>
            </div>
            <div class="h-60" x-ref="contributionChart"></div>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">Distribution</p>
                    <h3 class="text-lg font-semibold text-gray-900">Work Station completion</h3>
                </div>
            </div>
            <div class="h-60" x-ref="moduleChart"></div>
        </div>
    </div>
</div>
