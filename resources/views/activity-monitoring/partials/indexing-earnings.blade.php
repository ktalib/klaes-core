{{-- Activity Monitoring > Indexing Earnings tab (2026-11-01). Same figures as the Indexing Earnings
     sheet (EarningsService), for the dates in the page's filter. Loaded when the tab is opened. --}}
<div class="space-y-5" @keydown.escape.window="earningsTab.menu = null" @scroll.window="earningsTab.menu = null">
    <div class="bg-white rounded-2xl shadow border border-slate-100 p-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-wallet text-green-700"></i> Indexing Earnings</h2>
            <p class="text-sm text-gray-500 mt-1">
                Base fee per file indexed + rate per record captured, for
                <b x-text="earningsTab.data ? earningsTab.data.from + ' to ' + earningsTab.data.to : 'the selected dates'"></b>.
                <span x-show="earningsTab.data" x-text="earningsTab.data ? 'Pay counts from ' + earningsTab.data.programme.start + '.' : ''"></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" x-model="earningsTab.q" placeholder="Search name…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" style="min-width:200px">
            <button type="button" @click="loadEarnings()" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50"><i class="fas fa-rotate"></i> Refresh</button>
            <a :href="earningsTab.data?.export_url" x-show="earningsTab.data" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50"><i class="fas fa-file-csv"></i> Export CSV</a>
            <a :href="earningsTab.data?.sheet_url" x-show="earningsTab.data" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-white bg-green-700 hover:bg-green-800"><i class="fas fa-up-right-from-square"></i> Full sheet</a>
        </div>
    </div>

    <template x-if="earningsTab.loading">
        <div class="bg-white rounded-2xl shadow border border-slate-100 p-10 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading indexing earnings…</div>
    </template>
    <template x-if="earningsTab.error && !earningsTab.loading">
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" x-text="earningsTab.error"></div>
    </template>

    <template x-if="earningsTab.data && !earningsTab.loading">
        <div class="space-y-5">
            <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                <div class="bg-white rounded-2xl shadow border border-slate-100 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total earned</div>
                    <div class="text-2xl font-extrabold mt-1" style="color:#065f46" x-text="naira(earningsTab.data.totals.amount)"></div>
                </div>
                <div class="bg-white rounded-2xl shadow border border-slate-100 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Files indexed</div>
                    <div class="text-2xl font-extrabold text-gray-800 mt-1" x-text="earningsTab.data.totals.files.toLocaleString()"></div>
                </div>
                <div class="bg-white rounded-2xl shadow border border-slate-100 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Records captured</div>
                    <div class="text-2xl font-extrabold text-gray-800 mt-1" x-text="earningsTab.data.totals.records.toLocaleString()"></div>
                </div>
                <div class="bg-white rounded-2xl shadow border border-slate-100 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">People earning</div>
                    <div class="text-2xl font-extrabold text-gray-800 mt-1" x-text="earningsTab.data.rows.filter(r => r.totals.amount > 0).length + ' of ' + earningsTab.data.rows.length"></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow border border-slate-100 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3 text-left w-12">S/N</th>
                            <th class="px-3 py-3 text-left">Name</th>
                            <th class="px-3 py-3 text-right">Days</th>
                            <th class="px-3 py-3 text-right">Regular</th>
                            <th class="px-3 py-3 text-right">SLTR</th>
                            <th class="px-3 py-3 text-right">KANGIS Land</th>
                            <th class="px-3 py-3 text-right">KANGIS</th>
                            <th class="px-3 py-3 text-right">Transactions</th>
                            <th class="px-3 py-3 text-right">Ground Rent</th>
                            <th class="px-3 py-3 text-right">Bill Balance</th>
                            <th class="px-3 py-3 text-right">Files ₦</th>
                            <th class="px-3 py-3 text-right">Records ₦</th>
                            <th class="px-3 py-3 text-right">Total ₦</th>
                            <th class="px-3 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(row, i) in earningsPeople()" :key="row.user_id">
                            <tr class="hover:bg-gray-50" :class="row.totals.amount > 0 ? '' : 'text-gray-400'">
                                <td class="px-3 py-2.5 text-gray-400" x-text="i + 1"></td>
                                <td class="px-3 py-2.5 font-medium whitespace-nowrap" :class="row.totals.amount > 0 ? 'text-gray-800' : ''" x-text="row.name"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.days_worked"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_type.regular.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_type.sltr.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_type.kangis_land.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_type.kangis.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_record.trans.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_record.gr.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="row.totals.by_record.bb.count"></td>
                                <td class="px-3 py-2.5 text-right" x-text="naira(row.totals.base_amount)"></td>
                                <td class="px-3 py-2.5 text-right" x-text="naira(row.totals.records_amount)"></td>
                                <td class="px-3 py-2.5 text-right font-bold" :style="row.totals.amount > 0 ? 'color:#065f46' : ''" x-text="naira(row.totals.amount)"></td>
                                <td class="px-3 py-2.5 text-right">
                                    <button type="button" aria-label="Actions" class="p-1.5 rounded-md hover:bg-gray-100 text-gray-500"
                                        @click.stop="const r = $el.getBoundingClientRect(); earningsTab.menuTop = r.bottom + 4; earningsTab.menuLeft = Math.max(8, r.right - 200); earningsTab.menu = earningsTab.menu === row.user_id ? null : row.user_id">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="earningsPeople().length === 0">
                            <tr><td colspan="14" class="px-4 py-10 text-center text-gray-500">No indexers match.</td></tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-green-50 font-bold text-green-900">
                            <td></td><td class="px-3 py-3">Total</td><td></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_type.regular.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_type.sltr.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_type.kangis_land.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_type.kangis.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_record.trans.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_record.gr.count"></td>
                            <td class="px-3 py-3 text-right" x-text="earningsTab.data.totals.by_record.bb.count"></td>
                            <td class="px-3 py-3 text-right" x-text="naira(earningsTab.data.totals.base_amount)"></td>
                            <td class="px-3 py-3 text-right" x-text="naira(earningsTab.data.totals.records_amount)"></td>
                            <td class="px-3 py-3 text-right" x-text="naira(earningsTab.data.totals.amount)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <template x-if="earningsTab.data.not_eligible.length">
                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                    <b>Indexed files in this period but are not on indexing pay:</b>
                    <span x-text="earningsTab.data.not_eligible.map(n => n.name + ' (' + n.files + ' files)').join(', ')"></span>
                </div>
            </template>
        </div>
    </template>

    {{-- Row menu, fixed to the viewport so the table's scroll box cannot clip it. --}}
    <div x-show="earningsTab.menu !== null" x-cloak @click.outside="earningsTab.menu = null"
         class="fixed z-50 w-52 rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1"
         :style="'top:' + earningsTab.menuTop + 'px;left:' + earningsTab.menuLeft + 'px'">
        <template x-for="row in (earningsTab.data?.rows ?? []).filter(r => r.user_id === earningsTab.menu)" :key="row.user_id">
            <div>
                <a :href="row.progress_url" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fas fa-gauge-high w-4 text-gray-500"></i> Open progress page</a>
                <a :href="earningsTab.data.sheet_url + '&q=' + encodeURIComponent(row.name)" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fas fa-list w-4 text-gray-500"></i> Items on the sheet</a>
            </div>
        </template>
    </div>
</div>
