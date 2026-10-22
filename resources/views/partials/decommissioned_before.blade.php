{{-- File Already Decommissioned Before: shown only when the picked source file(s) already
     have a decommissioning record (YES, locked). See public/js/decommissioned-before.js.
     Params: $dbSource = CSS selector of the source file field(s), or the name of a global
     function returning the file numbers; $dbAnchor = where the panel goes (optional). --}}
@once
<script>window.KLAES_DECOMM_BEFORE_URL = @json(route('parcel-update.decommissioned-before'));</script>
<script src="{{ asset('js/decommissioned-before.js') . '?v=' . filemtime(public_path('js/decommissioned-before.js')) }}"></script>
@endonce
<script>
(function () {
    var source = @json($dbSource), anchor = @json($dbAnchor ?? null), isFn = @json((bool) ($dbSourceIsFunction ?? false));
    function go() {
        if (!window.KlaesDecommBefore) return;
        KlaesDecommBefore.watch(isFn ? function () { return typeof window[source] === 'function' ? window[source]() : []; } : source, anchor);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
</script>
