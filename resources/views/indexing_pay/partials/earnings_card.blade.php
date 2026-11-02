{{-- Indexing progress & earnings card (profile page and My Indexing Earnings).
     $s = EarningsService::summary(). $showDetailsLink adds the "Details" link.
     Every figure comes from the summary, so it matches the Create File Indexing strip. --}}
@php
    $naira = function ($v) {
        $v = (float) $v;
        return '₦' . number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2);
    };
    $pct = fn ($done, $target) => $target > 0 ? min(100, round($done / $target * 100)) : 0;
    $m = $s['month_totals'];
    $maxDay = max(array_merge([0], array_column($s['calendar'], 'amount')));
    $heat = function ($amount) use ($maxDay) {
        if ($amount <= 0 || $maxDay <= 0) return 0;
        return min(4, (int) ceil($amount / $maxDay * 4));
    };
    $firstWeekday = $s['calendar'][0]['weekday'] ?? 1;
    $typeLabels = \App\Services\IndexingPay\IndexingPaySettings::TYPES;
    $recordLabels = \App\Services\IndexingPay\IndexingPaySettings::RECORDS;
    $statusPill = match ($s['status']) {
        'not_started' => ['Starts ' . \Carbon\Carbon::parse($s['programme']['start'])->format('D j M'), 'ipe-pill-amber'],
        'ended' => ['Programme ended', 'ipe-pill-gray'],
        default => ['Programme running', 'ipe-pill-live'],
    };
    $showDetailsLink = $showDetailsLink ?? true;
    // Admins: $viewUserId shows another indexer's card; $preview marks an admin not on indexing pay.
    $viewUserId = $viewUserId ?? null;
    $preview = $preview ?? false;
    $cardTitle = $cardTitle ?? 'My Indexing Progress & Earnings';
    $dayUrl = route('indexing-earnings.day') . '?' . ($viewUserId ? 'user=' . (int) $viewUserId . '&' : '') . 'date=';
@endphp

@once
<style>
    .ipe-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(16,24,40,.06); }
    .ipe-head { background:linear-gradient(120deg,#065f46 0%,#047857 45%,#0f766e 100%); color:#fff; padding:18px 22px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; }
    .ipe-head-icon { width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,.16); display:flex; align-items:center; justify-content:center; }
    .ipe-head h3 { font-size:1.1rem; font-weight:700; line-height:1.2; }
    .ipe-head p { font-size:.8rem; opacity:.85; }
    .ipe-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.72rem; font-weight:600; }
    .ipe-pill-live { background:rgba(255,255,255,.18); color:#ecfdf5; }
    .ipe-pill-live::before { content:''; width:7px; height:7px; border-radius:50%; background:#6ee7b7; box-shadow:0 0 0 3px rgba(110,231,183,.25); }
    .ipe-pill-amber { background:#fef3c7; color:#92400e; }
    .ipe-pill-gray { background:rgba(255,255,255,.2); color:#fff; }
    .ipe-link { color:#fff; font-size:.8rem; font-weight:600; padding:6px 12px; border-radius:8px; background:rgba(255,255,255,.14); }
    .ipe-link:hover { background:rgba(255,255,255,.24); }
    .ipe-tiles { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; background:#eef2f1; border-bottom:1px solid #eef2f1; }
    @media (max-width: 900px) { .ipe-tiles { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    .ipe-tile { background:#fff; padding:16px 20px; }
    .ipe-tile-label { font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#6b7280; display:flex; align-items:center; gap:6px; }
    .ipe-tile-value { font-size:1.65rem; font-weight:800; color:#064e3b; margin-top:4px; font-variant-numeric:tabular-nums; letter-spacing:-.01em; }
    .ipe-tile-sub { font-size:.78rem; color:#6b7280; margin-top:2px; }
    .ipe-tile.today { background:linear-gradient(180deg,#f0fdf4,#fff); }
    .ipe-body { padding:20px 22px; }
    .ipe-bar { height:10px; border-radius:999px; background:#e5e7eb; overflow:hidden; }
    .ipe-bar > span { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#10b981,#059669); transition:width .6s ease; }
    .ipe-bar.money > span { background:linear-gradient(90deg,#14b8a6,#0f766e); }
    .ipe-section-title { font-size:.8rem; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px; }
    .ipe-table { width:100%; font-size:.85rem; }
    .ipe-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; font-weight:600; padding:6px 8px; border-bottom:1px solid #e5e7eb; }
    .ipe-table td { padding:7px 8px; border-bottom:1px solid #f3f4f6; }
    .ipe-table td.num, .ipe-table th.num { text-align:right; font-variant-numeric:tabular-nums; }
    .ipe-table tr.total td { font-weight:700; color:#064e3b; border-top:2px solid #d1fae5; border-bottom:none; }
    .ipe-cal { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:5px; }
    .ipe-cal-head { font-size:.65rem; font-weight:700; color:#9ca3af; text-align:center; text-transform:uppercase; }
    .ipe-day { border-radius:8px; padding:5px 4px 4px; min-height:50px; text-align:center; border:1px solid #f1f5f4; background:#fafafa; cursor:default; }
    .ipe-day button { width:100%; height:100%; }
    .ipe-day .d { font-size:.7rem; color:#6b7280; font-weight:600; }
    .ipe-day .a { font-size:.7rem; font-weight:700; color:#064e3b; margin-top:2px; font-variant-numeric:tabular-nums; }
    .ipe-day.h0 { background:#fff; }
    .ipe-day.h1 { background:#ecfdf5; border-color:#d1fae5; }
    .ipe-day.h2 { background:#d1fae5; border-color:#a7f3d0; }
    .ipe-day.h3 { background:#a7f3d0; border-color:#6ee7b7; }
    .ipe-day.h4 { background:#6ee7b7; border-color:#34d399; }
    .ipe-day.off { background:#f9fafb; opacity:.45; }
    .ipe-day.future .d { color:#d1d5db; }
    .ipe-day.today { box-shadow:0 0 0 2px #059669; }
    .ipe-day.clickable { cursor:pointer; }
    .ipe-day.clickable:hover { box-shadow:0 0 0 2px #10b981; }
    .ipe-day.selected { box-shadow:0 0 0 2px #0f766e; }
    .ipe-rates { font-size:.75rem; color:#6b7280; background:#f9fafb; border-top:1px solid #f3f4f6; padding:10px 22px; display:flex; flex-wrap:wrap; gap:6px 14px; }
    .ipe-rates b { color:#374151; font-weight:600; }
    .ipe-banner { margin:16px 22px 0; border-radius:10px; padding:10px 14px; font-size:.85rem; background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
</style>
@endonce

<div class="ipe-card"
     x-data="{
        sel: null, loading: false, day: null, err: null,
        async open(date) {
            if (this.sel === date) { this.sel = null; this.day = null; return; }
            this.sel = date; this.loading = true; this.err = null;
            try {
                const r = await fetch(@js($dayUrl) + date, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.day = await r.json();
            } catch (e) { this.err = 'Could not load that day. Please try again.'; this.day = null; }
            this.loading = false;
        },
        naira(v) { v = Number(v || 0); return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: Math.abs(v - Math.round(v)) < 0.005 ? 0 : 2, maximumFractionDigits: 2 }); }
     }">
    <div class="ipe-head">
        <div class="flex items-center gap-3">
            <div class="ipe-head-icon"><i data-lucide="wallet" class="h-5 w-5"></i></div>
            <div>
                <h3>{{ $cardTitle }}</h3>
                <p>{{ $s['month_label'] }} · Programme {{ \Carbon\Carbon::parse($s['programme']['start'])->format('j M') }} – {{ \Carbon\Carbon::parse($s['programme']['end'])->format('j M Y') }}, weekends included</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($preview)
                <span class="ipe-pill ipe-pill-amber" title="You are not on indexing pay: these are your own figures, shown so you can see the screen. Pick an indexer on My Indexing Earnings to see theirs.">Admin preview</span>
            @endif
            <span class="ipe-pill {{ $statusPill[1] }}">{{ $statusPill[0] }}</span>
            @if($showDetailsLink)
                <a href="{{ route('indexing-earnings.me') }}" class="ipe-link">Details →</a>
            @endif
        </div>
    </div>

    @if($s['status'] === 'not_started')
        <div class="ipe-banner"><strong>Starts {{ \Carbon\Carbon::parse($s['programme']['start'])->format('l j F Y') }}.</strong> Every file you index and every record you capture from that day earns. Your figures will appear here as you work.</div>
    @endif

    <div class="ipe-tiles mt-0">
        <div class="ipe-tile today">
            <div class="ipe-tile-label"><i data-lucide="sun" class="h-3.5 w-3.5"></i> Today</div>
            <div class="ipe-tile-value">{{ $naira($s['today']['amount']) }}</div>
            <div class="ipe-tile-sub">{{ number_format($s['today']['files']) }} {{ \Illuminate\Support\Str::plural('file', $s['today']['files']) }} · {{ number_format($s['today']['records']) }} {{ \Illuminate\Support\Str::plural('record', $s['today']['records']) }}</div>
        </div>
        <div class="ipe-tile">
            <div class="ipe-tile-label"><i data-lucide="calendar-range" class="h-3.5 w-3.5"></i> This week</div>
            <div class="ipe-tile-value">{{ $naira($s['week']['amount']) }}</div>
            <div class="ipe-tile-sub">{{ number_format($s['week']['files']) }} files · {{ number_format($s['week']['records']) }} records</div>
        </div>
        <div class="ipe-tile">
            <div class="ipe-tile-label"><i data-lucide="calendar" class="h-3.5 w-3.5"></i> {{ \Carbon\Carbon::parse($s['month'] . '-01')->format('F') }}</div>
            <div class="ipe-tile-value">{{ $naira($m['amount']) }}</div>
            <div class="ipe-tile-sub">{{ $s['days_worked'] }} {{ \Illuminate\Support\Str::plural('day', $s['days_worked']) }} worked · {{ number_format($m['files']) }} files</div>
        </div>
        <div class="ipe-tile">
            <div class="ipe-tile-label"><i data-lucide="trending-up" class="h-3.5 w-3.5"></i> At your pace</div>
            <div class="ipe-tile-value">{{ $naira($s['projected']) }}</div>
            <div class="ipe-tile-sub">for {{ \Carbon\Carbon::parse($s['month'] . '-01')->format('F') }} · about {{ $naira($s['avg_per_day']) }} a day</div>
        </div>
    </div>

    <div class="ipe-body">
        <div class="grid gap-5 md:grid-cols-2 mb-6">
            <div>
                <div class="flex justify-between text-sm mb-1.5"><span class="font-semibold text-gray-700">Files today</span><span class="text-gray-500"><b class="text-gray-800">{{ number_format($s['today']['files']) }}</b> of {{ number_format($s['targets']['daily_files']) }} target</span></div>
                <div class="ipe-bar"><span style="width: {{ $pct($s['today']['files'], $s['targets']['daily_files']) }}%"></span></div>
            </div>
            <div>
                <div class="flex justify-between text-sm mb-1.5"><span class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($s['month'] . '-01')->format('F') }} earnings</span><span class="text-gray-500"><b class="text-gray-800">{{ $naira($m['amount']) }}</b> of {{ $naira($s['targets']['monthly']) }} target</span></div>
                <div class="ipe-bar money"><span style="width: {{ $pct($m['amount'], $s['targets']['monthly']) }}%"></span></div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <div class="ipe-section-title">{{ \Carbon\Carbon::parse($s['month'] . '-01')->format('F') }} breakdown</div>
                <table class="ipe-table">
                    <thead><tr><th style="width:44px">S/N</th><th>Item</th><th class="num">Count</th><th class="num">Rate now</th><th class="num">Earned</th></tr></thead>
                    <tbody>
                        @php $sn = 0; @endphp
                        @foreach($typeLabels as $key => $label)
                            <tr>
                                <td class="text-gray-400">{{ ++$sn }}</td>
                                <td>{{ $label }}</td>
                                <td class="num">{{ number_format($m['by_type'][$key]['count']) }}</td>
                                <td class="num text-gray-500">{{ $naira($s['card']['base'][$key]) }}</td>
                                <td class="num">{{ $naira($m['by_type'][$key]['amount']) }}</td>
                            </tr>
                        @endforeach
                        @foreach($recordLabels as $key => $label)
                            <tr>
                                <td class="text-gray-400">{{ ++$sn }}</td>
                                <td>{{ $label }} records</td>
                                <td class="num">{{ number_format($m['by_record'][$key]['count']) }}</td>
                                <td class="num text-gray-500">{{ $naira($s['card']['rates'][$key]) }}</td>
                                <td class="num">{{ $naira($m['by_record'][$key]['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total"><td></td><td>Total</td><td class="num">{{ number_format($m['files'] + $m['records']) }}</td><td></td><td class="num">{{ $naira($m['amount']) }}</td></tr>
                    </tbody>
                </table>
                @unless($s['card']['sltr_pays_gr_bb'])
                    <p class="text-xs text-gray-400 mt-2">Ground Rent / Bill Balance on SLTR files are counted but not paid (approved rate card).</p>
                @endunless
            </div>

            <div>
                <div class="ipe-section-title flex justify-between"><span>Daily earnings</span><span class="normal-case font-normal text-gray-400 tracking-normal">Click a day to see it</span></div>
                <div class="ipe-cal mb-1">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd)<div class="ipe-cal-head">{{ $wd }}</div>@endforeach
                </div>
                <div class="ipe-cal">
                    @for($i = 1; $i < $firstWeekday; $i++)<div></div>@endfor
                    @foreach($s['calendar'] as $d)
                        @php
                            $cls = 'h' . $heat($d['amount']);
                            if (!$d['in_programme']) $cls .= ' off';
                            if ($d['is_future']) $cls .= ' future';
                            if ($d['is_today']) $cls .= ' today';
                            $clickable = $d['in_programme'] && !$d['is_future'];
                        @endphp
                        <div class="ipe-day {{ $cls }} {{ $clickable ? 'clickable' : '' }}"
                             @if($clickable) @click="open('{{ $d['date'] }}')" :class="{ 'selected': sel === '{{ $d['date'] }}' }" title="{{ \Carbon\Carbon::parse($d['date'])->format('D j M') }}: {{ $naira($d['amount']) }} · {{ $d['files'] }} files · {{ $d['records'] }} records" @endif>
                            <div class="d">{{ $d['day'] }}</div>
                            @if($d['amount'] > 0)<div class="a">{{ $naira($d['amount']) }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div x-show="sel" x-cloak class="mt-6 rounded-xl border border-green-100" style="background:#f8fdfb">
            <div class="flex items-center justify-between px-4 py-3 border-b border-green-100">
                <div class="font-semibold text-gray-800 text-sm" x-text="day ? day.label : 'Loading…'"></div>
                <div class="text-sm" x-show="day"><span class="text-gray-500">Earned</span> <b class="text-green-800" x-text="day ? naira(day.totals.amount) : ''"></b> <button type="button" class="ml-3 text-gray-400 hover:text-gray-700" @click="sel = null; day = null" aria-label="Close">✕</button></div>
            </div>
            <div class="px-4 py-3">
                <template x-if="loading"><p class="text-sm text-gray-500">Loading…</p></template>
                <template x-if="err"><p class="text-sm text-red-600" x-text="err"></p></template>
                <template x-if="day && !loading && day.items.length === 0"><p class="text-sm text-gray-500">Nothing earned on this day.</p></template>
                <template x-if="day && !loading && day.items.length > 0">
                    <div class="overflow-x-auto" style="max-height:340px; overflow-y:auto">
                        <table class="ipe-table">
                            <thead><tr><th style="width:44px">S/N</th><th>Time</th><th>File No.</th><th>Item</th><th class="num">Earned</th></tr></thead>
                            <tbody>
                                <template x-for="(it, i) in day.items" :key="it.source + it.source_id">
                                    <tr>
                                        <td class="text-gray-400" x-text="i + 1"></td>
                                        <td class="text-gray-500" x-text="it.time"></td>
                                        <td class="font-medium text-gray-800" x-text="it.file_number || '—'"></td>
                                        <td><span x-text="it.label"></span><span x-show="it.note" class="block text-xs text-gray-400" x-text="it.note"></span></td>
                                        <td class="num" x-text="naira(it.amount)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div class="ipe-rates">
        <span>Rates now:</span>
        @foreach($typeLabels as $key => $label)<span>{{ $label }} <b>{{ $naira($s['card']['base'][$key]) }}</b></span>@endforeach
        <span>Transaction <b>{{ $naira($s['card']['rates']['trans']) }}</b></span>
        <span>Ground Rent <b>{{ $naira($s['card']['rates']['gr']) }}</b></span>
        <span>Bill Balance <b>{{ $naira($s['card']['rates']['bb']) }}</b></span>
    </div>
</div>
