{{-- Create File Indexing: the indexer's progress and earnings for today and the month.
     Filled from indexing-earnings.summary after the page loads; after every save (the
     klaes:indexing-saved event from showIndexingSavedCard) it refreshes and shows what that
     save earned. Hidden for users who cannot earn or when switched off in Configurable Entries. --}}
@php
    $ipStripShow = false;
    try {
        $ipCard = app(\App\Services\IndexingPay\IndexingPaySettings::class)->current();
        // Indexers, and admins (as a preview) so they can see what indexers see.
        $ipStripShow = $ipCard['show']['create_page'] && auth()->check()
            && \App\Http\Controllers\IndexingPay\IndexingEarningsController::canSeeProgress(auth()->user());
    } catch (\Throwable $e) {
        $ipStripShow = false;
    }
@endphp
@if($ipStripShow)
<style>
    .ips-strip { margin-top:14px; border:1px solid #d1fae5; border-radius:14px; background:linear-gradient(100deg,#f0fdf4 0%,#ffffff 55%,#f0fdfa 100%); padding:12px 16px; display:flex; flex-wrap:wrap; align-items:center; gap:14px 22px; }
    .ips-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#10b981,#0f766e); color:#fff; box-shadow:0 4px 10px rgba(16,185,129,.25); flex-shrink:0; }
    .ips-label { font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6b7280; }
    .ips-value { font-size:1.35rem; font-weight:800; color:#064e3b; font-variant-numeric:tabular-nums; line-height:1.15; }
    .ips-sub { font-size:.75rem; color:#6b7280; }
    .ips-div { width:1px; align-self:stretch; background:#d1fae5; }
    .ips-progress { flex:1 1 220px; min-width:200px; }
    .ips-bar { height:9px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:6px; }
    .ips-bar > span { display:block; height:100%; width:0; border-radius:999px; background:linear-gradient(90deg,#10b981,#059669); transition:width .7s ease; }
    .ips-link { font-size:.8rem; font-weight:600; color:#047857; white-space:nowrap; padding:7px 12px; border-radius:9px; border:1px solid #a7f3d0; background:#fff; }
    .ips-link:hover { background:#ecfdf5; }
    .ips-skel { display:inline-block; width:70px; height:18px; border-radius:6px; background:linear-gradient(90deg,#e5e7eb,#f3f4f6,#e5e7eb); background-size:200% 100%; animation:ips-sh 1.2s infinite; }
    @keyframes ips-sh { 0% { background-position:200% 0 } 100% { background-position:-200% 0 } }
    .ips-bump { animation:ips-bump .9s ease; }
    @keyframes ips-bump { 0% { transform:scale(1) } 30% { transform:scale(1.12); color:#059669 } 100% { transform:scale(1) } }
    .ips-toast { position:fixed; right:22px; bottom:22px; z-index:2000; width:330px; max-width:calc(100vw - 32px); background:#fff; border-radius:14px; box-shadow:0 18px 40px rgba(6,78,59,.22); border:1px solid #a7f3d0; overflow:hidden; transform:translateY(20px); opacity:0; transition:all .35s ease; pointer-events:none; }
    .ips-toast.show { transform:translateY(0); opacity:1; pointer-events:auto; }
    .ips-toast-head { background:linear-gradient(120deg,#047857,#0f766e); color:#fff; padding:10px 14px; display:flex; align-items:center; justify-content:space-between; }
    .ips-toast-amt { font-size:1.4rem; font-weight:800; font-variant-numeric:tabular-nums; }
    .ips-toast-body { padding:10px 14px 12px; font-size:.82rem; color:#374151; }
    .ips-toast-row { display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px dashed #e5e7eb; }
    .ips-toast-row:last-child { border-bottom:none; }
    .ips-toast.neutral { border-color:#e5e7eb; }
    .ips-toast.neutral .ips-toast-head { background:#4b5563; }
</style>

<div class="ips-strip" id="ips-strip" data-url="{{ route('indexing-earnings.summary') }}" aria-live="polite">
    <div class="flex items-center gap-3">
        <div class="ips-icon"><i data-lucide="wallet" class="h-5 w-5"></i></div>
        <div>
            <div class="ips-label">Today</div>
            <div class="ips-value" id="ips-today"><span class="ips-skel"></span></div>
            <div class="ips-sub" id="ips-today-sub">&nbsp;</div>
        </div>
    </div>
    <div class="ips-div hidden sm:block"></div>
    <div>
        <div class="ips-label" id="ips-month-label">This month</div>
        <div class="ips-value" id="ips-month"><span class="ips-skel"></span></div>
        <div class="ips-sub" id="ips-month-sub">&nbsp;</div>
    </div>
    <div class="ips-div hidden sm:block"></div>
    <div class="ips-progress">
        <div class="flex justify-between items-baseline">
            <span class="ips-label">Files today</span>
            <span class="ips-sub"><b id="ips-files" class="text-gray-800">0</b> of <span id="ips-target">0</span></span>
        </div>
        <div class="ips-bar"><span id="ips-bar"></span></div>
        <div class="ips-sub mt-1" id="ips-status">&nbsp;</div>
    </div>
    <span id="ips-preview" style="display:none;font-size:.7rem;font-weight:700;padding:4px 10px;border-radius:999px;background:#fef3c7;color:#92400e"
          title="You are not on indexing pay: these are your own figures, shown so you can see the screen.">Admin preview</span>
    <a href="{{ route('indexing-earnings.me') }}" class="ips-link" target="_blank" rel="noopener">My earnings →</a>
</div>

<div class="ips-toast" id="ips-toast" role="status">
    <div class="ips-toast-head">
        <div><div style="font-size:.7rem;opacity:.85;text-transform:uppercase;letter-spacing:.06em" id="ips-toast-title">This save earned</div><div class="ips-toast-amt" id="ips-toast-amt"></div></div>
        <button type="button" aria-label="Close" style="opacity:.8" onclick="document.getElementById('ips-toast').classList.remove('show')">✕</button>
    </div>
    <div class="ips-toast-body" id="ips-toast-body"></div>
</div>

<script>
(function () {
    var strip = document.getElementById('ips-strip');
    if (!strip) return;
    var url = strip.getAttribute('data-url');
    var last = null;
    var TYPES = { regular: 'Regular Land File', sltr: 'SLTR File', kangis_land: 'KANGIS Land File', kangis: 'KANGIS File' };
    var RECORDS = { trans: ['Transaction', 'Transactions'], gr: ['Ground Rent', 'Ground Rent'], bb: ['Bill Balance', 'Bill Balances'] };

    function naira(v) {
        v = Number(v || 0);
        var whole = Math.abs(v - Math.round(v)) < 0.005;
        return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 });
    }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
    function set(id, text) { var el = document.getElementById(id); if (el) el.textContent = text; }
    function bump(id) { var el = document.getElementById(id); if (!el) return; el.classList.remove('ips-bump'); void el.offsetWidth; el.classList.add('ips-bump'); }

    function paint(s) {
        if (!s || s.eligible === false) { strip.style.display = 'none'; return; }
        document.getElementById('ips-preview').style.display = s.preview ? 'inline-block' : 'none';
        var t = s.today, m = s.month_totals;
        set('ips-today', naira(t.amount));
        set('ips-today-sub', plural(t.files, 'file', 'files') + ' · ' + plural(t.records, 'record', 'records'));
        set('ips-month-label', s.month_label || 'This month');
        set('ips-month', naira(m.amount));
        set('ips-month-sub', plural(s.days_worked, 'day', 'days') + ' worked · ' + naira(s.projected) + ' at your pace');
        set('ips-files', t.files);
        set('ips-target', s.targets.daily_files);
        var pct = s.targets.daily_files > 0 ? Math.min(100, Math.round(t.files / s.targets.daily_files * 100)) : 0;
        document.getElementById('ips-bar').style.width = pct + '%';
        var status = s.status === 'not_started'
            ? 'Starts ' + new Date(s.programme.start + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }) + ' — files you index from then earn.'
            : (s.status === 'ended' ? 'The programme has ended.' : (pct >= 100 ? 'Daily target reached — well done!' : pct + '% of today\'s target'));
        set('ips-status', status);
        try { window.dispatchEvent(new CustomEvent('klaes:indexing-earnings', { detail: s })); } catch (e) {}
    }

    function toast(prev, next) {
        var box = document.getElementById('ips-toast');
        var body = document.getElementById('ips-toast-body');
        var delta = Math.round((next.today.amount - prev.today.amount) * 100) / 100;
        var rows = [];
        Object.keys(TYPES).forEach(function (k) {
            var dc = next.today.by_type[k].count - prev.today.by_type[k].count;
            var da = next.today.by_type[k].amount - prev.today.by_type[k].amount;
            if (dc !== 0) rows.push([(dc > 0 ? '+' : '') + dc + ' ' + TYPES[k], da]);
        });
        Object.keys(RECORDS).forEach(function (k) {
            var dc = next.today.by_record[k].count - prev.today.by_record[k].count;
            var da = next.today.by_record[k].amount - prev.today.by_record[k].amount;
            if (dc !== 0) rows.push([(dc > 0 ? '+' : '') + dc + ' ' + (Math.abs(dc) === 1 ? RECORDS[k][0] : RECORDS[k][1]), da]);
        });
        body.innerHTML = '';
        if (rows.length === 0) {
            box.classList.add('neutral');
            set('ips-toast-title', 'Saved');
            set('ips-toast-amt', 'No new earnings');
            var p = document.createElement('div');
            p.textContent = 'A file is paid once, when it is first indexed, and each record once, when it is first captured.';
            body.appendChild(p);
        } else {
            box.classList.remove('neutral');
            set('ips-toast-title', delta >= 0 ? 'This save earned' : 'Change in today\'s earnings');
            set('ips-toast-amt', (delta > 0 ? '+' : '') + naira(delta));
            rows.forEach(function (r) {
                var row = document.createElement('div');
                row.className = 'ips-toast-row';
                var a = document.createElement('span'); a.textContent = r[0];
                var b = document.createElement('b'); b.textContent = naira(r[1]);
                row.appendChild(a); row.appendChild(b); body.appendChild(row);
            });
            var tot = document.createElement('div');
            tot.style.cssText = 'margin-top:8px;color:#065f46;font-weight:600';
            tot.textContent = 'Today so far: ' + naira(next.today.amount);
            body.appendChild(tot);
        }
        box.classList.add('show');
        clearTimeout(box._t);
        box._t = setTimeout(function () { box.classList.remove('show'); }, 9000);
    }

    function load(fresh) {
        return fetch(url + (fresh ? '?fresh=1' : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (s) {
                if (!s) return null;
                var prev = last;
                last = s;
                paint(s);
                return { prev: prev, next: s };
            })
            .catch(function () { return null; });
    }

    window.addEventListener('klaes:indexing-saved', function () {
        // The save has committed by the time the card shows; give related writes a moment.
        setTimeout(function () {
            load(true).then(function (r) {
                if (!r || !r.prev || r.next.eligible === false) return;
                if (r.next.today.amount !== r.prev.today.amount) { bump('ips-today'); bump('ips-month'); }
                toast(r.prev, r.next);
            });
        }, 700);
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) load(true); });
    setInterval(function () { if (!document.hidden) load(false); }, 120000);
    load(true);
})();
</script>
@endif
