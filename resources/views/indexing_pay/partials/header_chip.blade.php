{{-- Header chip: today's indexing earnings, on every page, for users who earn (indexing role + MDC/MDCM).
     Filled after the page loads (indexing-earnings.summary), so it never slows a page down.
     The Create File Indexing strip announces saves with the klaes:indexing-earnings event. --}}
@php
    $ipChipShow = false;
    try {
        $ipSettings = app(\App\Services\IndexingPay\IndexingPaySettings::class)->current();
        // Only users who earn (indexing role + MDC/MDCM staff type): not admins or MLPP.
        $ipChipShow = $ipSettings['show']['header'] && auth()->check()
            && \App\Http\Controllers\IndexingPay\IndexingEarningsController::isMdcIndexer(auth()->user());
    } catch (\Throwable $e) {
        $ipChipShow = false;
    }
@endphp
@if($ipChipShow)
    <a href="{{ route('indexing-earnings.me') }}" id="ip-header-chip" title="My indexing earnings"
       class="hidden md:inline-flex items-center gap-2 mr-3 px-3 py-1.5 rounded-full border text-sm"
       style="border-color:#a7f3d0;background:#ecfdf5;color:#065f46;text-decoration:none">
        <i data-lucide="wallet" class="w-4 h-4"></i>
        <span>Today <b id="ip-chip-today" style="font-variant-numeric:tabular-nums">…</b></span>
        <span style="color:#6ee7b7">|</span>
        <span id="ip-chip-month-label">Month</span> <b id="ip-chip-month" style="font-variant-numeric:tabular-nums">…</b>
    </a>
    <script>
        (function () {
            var url = @json(route('indexing-earnings.summary'));
            function naira(v) { v = Number(v || 0); return '₦' + v.toLocaleString('en-NG', { maximumFractionDigits: Math.abs(v - Math.round(v)) < 0.005 ? 0 : 2 }); }
            function paint(s) {
                if (!s || s.eligible === false) { var c = document.getElementById('ip-header-chip'); if (c) c.style.display = 'none'; return; }
                document.getElementById('ip-chip-today').textContent = naira(s.today.amount);
                document.getElementById('ip-chip-month').textContent = naira(s.month_totals.amount);
                document.getElementById('ip-chip-month-label').textContent = (s.month_label || 'Month').split(' ')[0];
            }
            function load(fresh) {
                fetch(url + (fresh ? '?fresh=1' : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; }).then(paint).catch(function () {});
            }
            window.addEventListener('klaes:indexing-earnings', function (e) { if (e.detail) paint(e.detail); });
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { load(false); }); else load(false);
        })();
    </script>
@endif
