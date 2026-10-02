<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <template x-if="state.summary_cards?.team_completion">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-600 p-6 rounded-2xl shadow text-white flex flex-col lg:col-span-2 order-first">
            <div class="text-xs uppercase tracking-wide text-blue-100 font-semibold">
                Team Completion Rate
            </div>
            <div class="mt-3 text-4xl font-bold" x-text="formatPercent(state.summary_cards.team_completion.current ?? 0)"></div>
            <div class="mt-2 flex items-center gap-2 text-sm">
                <span x-text="state.summary_cards.team_completion.direction === 'up' ? '↑ Improving' : (state.summary_cards.team_completion.direction === 'down' ? '↓ Declining' : '→ Flat')"></span>
                <span class="text-blue-100" x-text="`vs ${formatPercent(state.summary_cards.team_completion.previous ?? 0)}`"></span>
            </div>
            <div class="mt-4 text-sm text-blue-100 flex flex-wrap gap-4">
                <span x-text="`Total: ${formatNumber(state.summary_cards.team_completion.total ?? 0)}`"></span>
                <span x-text="`Target: ${formatNumber(state.summary_cards.team_completion.target ?? 0)}`"></span>
            </div>
        </div>
    </template>

    <template x-for="card in (state.summary_cards?.items ?? []).filter(item => item.key !== 'team_completion_rate')" :key="card.key">
        <div class="bg-white p-5 rounded-2xl shadow ring-1 ring-slate-100 flex flex-col">
            <div class="text-sm text-gray-500 font-medium" x-text="card.label"></div>
            <div class="mt-3 text-3xl font-semibold text-gray-900">
                <span x-text="formatNumber(card.value)"></span>
                <span class="text-lg font-medium text-gray-500" x-show="card.suffix" x-text="card.suffix"></span>
            </div>
        </div>
    </template>
</div>
