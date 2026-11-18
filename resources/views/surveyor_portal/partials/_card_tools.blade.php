{{-- Reset and Refresh on the portal's registration cards (user, 2026-11-17), as on the Upload Survey Plan card:
     Reset clears everything entered on the card; Refresh reloads the page (the approved companies, LGA and district
     lists) and puts back what was typed. Passwords and a chosen photo cannot be put back by a browser, so Refresh says
     so. $ctForm: the form's id. $ctTitle, $ctSub: the card's heading. --}}
@php
  $ctKey = 'ksCardTools.' . $ctForm;
@endphp
<div class="ks-card-tools">
    <div class="ks-card-tools-t">
        <h1 class="mb-1" @isset($ctHeadingId) id="{{ $ctHeadingId }}" @endisset>{{ $ctTitle }}</h1>
        @if (! empty($ctSub))<p class="ks-help" style="margin:0;">{{ $ctSub }}</p>@endif
    </div>
    <div class="ks-card-tools-btns">
        <button type="button" class="ks-tool-btn reset" data-ct-reset="{{ $ctForm }}" title="Clear everything entered on this card and start again"><i data-lucide="eraser" class="w-4 h-4"></i> Reset</button>
        <button type="button" class="ks-tool-btn refresh" data-ct-refresh="{{ $ctForm }}" title="Reload the lists; what you typed is kept"><i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh</button>
    </div>
</div>
<p class="ks-tool-msg" id="ksToolMsg-{{ $ctForm }}" role="status" hidden></p>

@once
@push('styles')
<style>
    .ks-card-tools { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    .ks-card-tools-t { flex:1 1 220px; min-width:0; }
    .ks-card-tools-btns { display:flex; gap:8px; flex-shrink:0; }
    .ks-tool-btn { display:inline-flex; align-items:center; gap:6px; height:34px; padding:0 12px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; border:1px solid transparent; background:#fff; }
    .ks-tool-btn.reset { color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
    .ks-tool-btn.reset:hover { background:#fee2e2; }
    .ks-tool-btn.refresh { color:#1d4ed8; border-color:#bfdbfe; background:#eff6ff; }
    .ks-tool-btn.refresh:hover { background:#dbeafe; }
    .ks-tool-btn:disabled { opacity:.6; cursor:wait; }
    .ks-tool-msg { margin:-6px 0 12px; padding:8px 12px; border-radius:8px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; font-size:13px; }
    @media (max-width:520px) { .ks-card-tools-btns { width:100%; } .ks-tool-btn { flex:1; justify-content:center; } }
</style>
@endpush
@endonce

@push('scripts')
<script>
(function () {
    var formId = @json($ctForm), key = @json($ctKey);
    var form = document.getElementById(formId), msg = document.getElementById('ksToolMsg-' + formId);
    if (!form) return;
    var $ = window.jQuery;
    var say = function (text) { msg.textContent = text; msg.hidden = !text; };
    var fields = function () {
        return Array.prototype.filter.call(form.elements, function (el) {
            return el.name && el.name !== '_token' && !el.disabled && el.type !== 'submit' && el.type !== 'button';
        });
    };
    var changed = function (el) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        if ($ && $(el).data('select2')) $(el).trigger('change.select2');
    };

    // Reset: every entry back to blank (hidden fields the page set itself are left alone).
    document.querySelector('[data-ct-reset="' + formId + '"]').addEventListener('click', function () {
        fields().forEach(function (el) {
            if (el.type === 'hidden') return;
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
            else if (el.tagName === 'SELECT') { if (el.multiple) Array.prototype.forEach.call(el.options, function (o) { o.selected = false; }); else el.selectedIndex = 0; }
            else el.value = '';
            changed(el);
        });
        form.querySelectorAll('.ks-error').forEach(function (e) { e.remove(); });
        form.querySelectorAll('img[data-preview], .ks-passport-preview img').forEach(function (i) { i.removeAttribute('src'); });
        if (window.ksAfterReset) window.ksAfterReset(form);
        say('Cleared. Fill in the card again.');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Refresh: keep what was typed (not passwords or files), reload, then put it back.
    document.querySelector('[data-ct-refresh="' + formId + '"]').addEventListener('click', function () {
        var data = [], lostFile = false;
        fields().forEach(function (el) {
            if (el.type === 'password') return;
            if (el.type === 'file') { lostFile = lostFile || el.files.length > 0; return; }
            if (el.type === 'hidden' && el.name !== 'username_auto') return;
            if (el.type === 'checkbox' || el.type === 'radio') data.push([el.name, el.value, el.checked]);
            else if (el.tagName === 'SELECT' && el.multiple) data.push([el.name, Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; }), null]);
            else data.push([el.name, el.value, null]);
        });
        try { sessionStorage.setItem(key, JSON.stringify({ data: data, lostFile: lostFile })); } catch (e) {}
        this.disabled = true;
        location.reload();
    });

    var saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(key) || 'null'); sessionStorage.removeItem(key); } catch (e) {}
    if (!saved) return;
    // Dependent lists (State → LGA → District) fill in after their parent changes, so put the values back in a few passes.
    var restore = function () {
        saved.data.forEach(function (row) {
            var els = form.querySelectorAll('[name="' + row[0].replace(/"/g, '\\"') + '"]');
            Array.prototype.forEach.call(els, function (el) {
                if (el.type === 'checkbox' || el.type === 'radio') { if (el.value === row[1] && el.checked !== row[2]) { el.checked = row[2]; changed(el); } return; }
                if (Array.isArray(row[1])) { Array.prototype.forEach.call(el.options, function (o) { o.selected = row[1].indexOf(o.value) >= 0; }); changed(el); return; }
                if (el.value !== row[1] && (el.tagName !== 'SELECT' || Array.prototype.some.call(el.options, function (o) { return o.value === row[1]; }))) {
                    el.value = row[1]; changed(el);
                }
            });
        });
    };
    window.addEventListener('load', function () {
        restore(); setTimeout(restore, 400); setTimeout(restore, 1200);
        var hasPw = !!form.querySelector('input[type="password"]');
        say('Refreshed: the lists were reloaded and your entries kept.' + (saved.lostFile ? ' Choose the passport photo again.' : '') + (hasPw ? ' Passwords are never kept: type them again.' : ''));
    });
})();
</script>
@endpush
