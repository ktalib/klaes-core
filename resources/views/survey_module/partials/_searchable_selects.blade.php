{{-- Long Survey dropdowns are type-to-search (user, 2026-10-30: "all the long listed drop downs should have search").
     Loaded once by the Survey layout. Any single select with more than 10 options is turned into a select2 box,
     and so is any select with class="sv-searchable" whatever its length.
       data-width        box width (default: 100% inside a form field, the select's own width in a filter bar)
       data-placeholder  empty text (adds a clear button)
       data-no-search    opt out (pages that run select2 themselves, cloned template rows)
     select2 fires jQuery events only, so page scripts listening with addEventListener('change') would miss a pick:
     the change is re-sent as a native event (inline onchange= runs once, not twice). Setting .value / .selectedIndex
     from page code refreshes the box. Call svSearchable(node) after adding rows, and svSearchable.cleanClone(node)
     before initialising a cloned row. --}}
@once
@push('scripts')
<script>
(function () {
    'use strict';
    var MIN_OPTIONS = 10;

    function jq() { return window.jQuery && jQuery.fn && jQuery.fn.select2 ? jQuery : null; }

    function wanted(el) {
        if (el.multiple || el.hasAttribute('data-no-search')) return false;
        if (el.classList.contains('select2-hidden-accessible')) return false;
        if (el.closest('template, [data-no-search]')) return false;
        if (/__i__/.test(el.name || '')) return false;
        return el.classList.contains('sv-searchable') || el.options.length > MIN_OPTIONS;
    }

    function widthFor(el) {
        if (el.dataset.width) return el.dataset.width;
        if (el.closest('.form-group, .field, td')) return '100%';
        return el.offsetWidth > 0 ? (Math.max(el.offsetWidth, 160) + 'px') : '100%';
    }

    // Redraw the box from the select's current value without firing any change handler.
    function redraw(el) {
        var $ = jq(), inst = $ && $(el).data('select2');
        if (!inst) return;
        inst.dataAdapter.current(function (data) { inst.trigger('selection:update', { data: data }); });
    }

    function watchValue(el) {
        ['value', 'selectedIndex'].forEach(function (prop) {
            var d = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, prop);
            if (!d || !d.set) return;
            Object.defineProperty(el, prop, {
                configurable: true,
                get: function () { return d.get.call(this); },
                set: function (v) { d.set.call(this, v); redraw(this); }
            });
        });
    }

    function enhance(el) {
        var $ = jq();
        if (!$ || !wanted(el)) return;
        var $s = $(el), ph = el.dataset.placeholder;
        var opts = { width: widthFor(el), placeholder: ph || undefined, allowClear: !!ph };
        if (el.options.length > 1500) opts.minimumInputLength = 2;   // e.g. 28k beneficiaries: too many to draw unfiltered
        $s.select2(opts);

        $s.on('change.svbridge input.svbridge', function (e) {
            if (e.originalEvent) return;                      // already a native event
            var inline = 'on' + e.type, saved = el[inline];
            el[inline] = null;                                // jQuery has just run it
            $.event.triggered = e.type;                       // keep jQuery handlers from running twice
            try { el.dispatchEvent(new Event(e.type, { bubbles: true })); }
            finally { $.event.triggered = undefined; el[inline] = saved; }
        });
        watchValue(el);
    }

    function svSearchable(root) {
        (root || document).querySelectorAll('select').forEach(enhance);
    }
    svSearchable.cleanClone = function (node) {
        node.querySelectorAll('.select2-container').forEach(function (c) { c.remove(); });
        node.querySelectorAll('select, option').forEach(function (s) {
            s.classList.remove('select2-hidden-accessible');
            s.removeAttribute('data-select2-id');
            if (s.tagName === 'SELECT') { s.removeAttribute('aria-hidden'); s.removeAttribute('tabindex'); }
        });
        return node;
    };
    window.svSearchable = svSearchable;

    // After page scripts (which may run select2 on their own selects) have finished.
    function boot() { setTimeout(function () { svSearchable(document); }, 0); }
    if (document.readyState === 'complete') boot(); else window.addEventListener('load', boot);
})();
</script>
<style>
    .survey-proto .select2-container--default .select2-results__option--disabled { color: #9ca3af; }
</style>
@endpush
@endonce
