@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
@php
    $naira = function ($v) {
        $v = (float) $v;
        return '₦' . number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2);
    };
    $t = $sheet['totals'];
    $paidPeople = count(array_filter($sheet['rows'], fn ($r) => $r['totals']['amount'] > 0));
    $query = ['from' => $from, 'to' => $to, 'q' => $search];
@endphp
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6 space-y-5"
         x-data="{
            menu: null, menuTop: 0, menuLeft: 0, detail: null, detailName: '', loading: false,
            openMenu(id, el) { const r = el.getBoundingClientRect(); this.menuTop = r.bottom + 4; this.menuLeft = Math.max(8, r.right - 200); this.menu = this.menu === id ? null : id; },
            async view(id, name) {
                this.menu = null; this.detailName = name; this.loading = true; this.detail = { items: [], totals: { amount: 0 } };
                try {
                    const r = await fetch('{{ url('indexing-earnings/sheet/users') }}/' + id + '?from={{ $from }}&to={{ $to }}', { headers: { 'Accept': 'application/json' } });
                    this.detail = await r.json();
                } catch (e) { this.detail = { items: [], totals: { amount: 0 }, error: true }; }
                this.loading = false;
            },
            naira(v) { v = Number(v || 0); return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: Math.abs(v - Math.round(v)) < 0.005 ? 0 : 2, maximumFractionDigits: 2 }); }
         }"
         @keydown.escape.window="menu = null; detail = null" @scroll.window="menu = null" @resize.window="menu = null">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Indexing Earnings</h1>
                <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($from)->format('j M Y') }} – {{ \Carbon\Carbon::parse($to)->format('j M Y') }} · base fee per file indexed + rate per record captured</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('indexing-earnings.sheet.export', $query) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white hover:bg-gray-50"><i data-lucide="download" class="h-4 w-4"></i> Export CSV</a>
                <a href="{{ route('configurable-entries.index', ['tab' => 'indexing-pay']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white hover:bg-gray-50"><i data-lucide="settings" class="h-4 w-4"></i> Rate card</a>
            </div>
        </div>

        <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Month</label>
                <select name="month" class="border border-gray-300 rounded-md px-3 py-2 text-sm" onchange="this.form.from.value='';this.form.to.value='';this.form.submit()">
                    @foreach($months as $value => $label)<option value="{{ $value }}" @selected($value === $month)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>
            <div class="flex-1 min-w-0" style="min-width:200px">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search name</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="Type a name…" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>
            <button class="px-4 py-2 rounded-md text-sm font-medium text-white bg-green-700 hover:bg-green-800">Apply</button>
        </form>

        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
            @foreach([
                ['Total earned', $naira($t['amount']), 'wallet'],
                ['Files indexed', number_format($t['files']), 'file-check'],
                ['Records captured', number_format($t['records']), 'list-checks'],
                ['People earning', $paidPeople . ' of ' . count($sheet['rows']), 'users'],
            ] as [$label, $value, $icon])
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500"><i data-lucide="{{ $icon }}" class="h-4 w-4 text-green-700"></i> {{ $label }}</div>
                    <div class="text-2xl font-extrabold text-gray-800 mt-1 tabular-nums">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
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
                        @forelse($sheet['rows'] as $row)
                            @php $r = $row['totals']; @endphp
                            <tr class="hover:bg-gray-50 {{ $r['amount'] > 0 ? '' : 'text-gray-400' }}">
                                <td class="px-3 py-2.5 text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2.5 font-medium whitespace-nowrap {{ $r['amount'] > 0 ? 'text-gray-800' : '' }}">{{ $row['name'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['days_worked'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_type']['regular']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_type']['sltr']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_type']['kangis_land']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_type']['kangis']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_record']['trans']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_record']['gr']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($r['by_record']['bb']['count']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $naira($r['base_amount']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $naira($r['records_amount']) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $r['amount'] > 0 ? 'text-green-800' : '' }}">{{ $naira($r['amount']) }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <button type="button" aria-label="Actions" class="p-1.5 rounded-md hover:bg-gray-100 text-gray-500" @click.stop="openMenu({{ $row['user_id'] }}, $el)"><i data-lucide="more-vertical" class="h-4 w-4"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="px-4 py-10 text-center text-gray-500">No indexers found.</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($sheet['rows']))
                        <tfoot>
                            <tr class="bg-green-50 font-bold text-green-900">
                                <td></td><td class="px-3 py-3">Total</td><td></td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_type']['regular']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_type']['sltr']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_type']['kangis_land']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_type']['kangis']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_record']['trans']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_record']['gr']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($t['by_record']['bb']['count']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $naira($t['base_amount']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $naira($t['records_amount']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $naira($t['amount']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if(count($sheet['not_eligible']))
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                <div class="font-semibold mb-1">Indexed files in this period but cannot earn (they do not hold {{ implode(', ', $card['eligible_modules']) }}):</div>
                <div>@foreach($sheet['not_eligible'] as $ne){{ $ne['name'] }} ({{ number_format($ne['files']) }} files)@if(!$loop->last), @endif @endforeach</div>
                <div class="text-xs mt-1 text-yellow-800">Give them the module if they should be paid. Their files then count from the day they were indexed.</div>
            </div>
        @endif

        {{-- Row action menu, positioned against the viewport so the table's overflow cannot clip it. --}}
        <div x-show="menu !== null" x-cloak class="fixed z-50 w-48 rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1" :style="'top:' + menuTop + 'px;left:' + menuLeft + 'px'" @click.outside="menu = null" @click.stop>
            @foreach($sheet['rows'] as $row)
                <div x-show="menu === {{ $row['user_id'] }}">
                    <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="view({{ $row['user_id'] }}, @js($row['name']))"><i data-lucide="list" class="h-4 w-4 text-gray-500"></i> View items</button>
                    <a href="{{ route('indexing-earnings.me', ['user' => $row['user_id']]) }}" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i data-lucide="layout-dashboard" class="h-4 w-4 text-gray-500"></i> Open progress page</a>
                </div>
            @endforeach
        </div>

        <template x-if="detail !== null">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(17,24,39,.45)" @click.self="detail = null">
                <div class="bg-white rounded-xl shadow-xl w-full overflow-hidden" style="max-width:860px" @click.stop>
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100" style="background:linear-gradient(120deg,#065f46,#0f766e);color:#fff">
                        <div><div class="text-xs uppercase tracking-wide opacity-80">{{ \Carbon\Carbon::parse($from)->format('j M') }} – {{ \Carbon\Carbon::parse($to)->format('j M Y') }}</div><div class="font-semibold" x-text="detailName"></div></div>
                        <div class="flex items-center gap-4"><b class="text-lg" x-text="naira(detail.totals.amount)"></b><button type="button" @click="detail = null" aria-label="Close">✕</button></div>
                    </div>
                    <div style="max-height:65vh;overflow:auto">
                        <p x-show="loading" class="p-5 text-sm text-gray-500">Loading…</p>
                        <p x-show="!loading && detail.error" class="p-5 text-sm text-red-600">Could not load the items. Please try again.</p>
                        <p x-show="!loading && !detail.error && detail.items.length === 0" class="p-5 text-sm text-gray-500">Nothing earned in this period.</p>
                        <table x-show="!loading && detail.items.length > 0" class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 sticky top-0"><tr><th class="px-4 py-2 text-left w-12">S/N</th><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">File No.</th><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-right">Earned</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, i) in detail.items" :key="it.source + it.source_id">
                                    <tr><td class="px-4 py-2 text-gray-400" x-text="i + 1"></td><td class="px-4 py-2 text-gray-500" x-text="it.date + ' ' + it.time"></td><td class="px-4 py-2 font-medium" x-text="it.file_number || '—'"></td><td class="px-4 py-2"><span x-text="it.kind === 'file' ? 'File indexed — ' + it.label : it.label"></span><span x-show="it.note" class="block text-xs text-gray-400" x-text="it.note"></span></td><td class="px-4 py-2 text-right tabular-nums" x-text="naira(it.amount)"></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
    @include('admin.footer')
</div>
@endsection
