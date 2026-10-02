{{-- ═══════════════════════ Reference lists, once ═══════════════════════
     The districts, street names and TP numbers every form on this page
     chooses from, sent as data instead of as markup.

     They used to be rendered into each <select> that needed them. There are
     twenty-odd such selects across the modals on this page and the lists are
     1,818 districts, 826 streets and 3,913 TP numbers, so the page shipped
     39,109 <option> tags — 5.6MB of the 6.9MB it weighed, every load, for
     dropdowns inside modals most visits never open.

     Now each of those selects carries a marker option saying which list it
     wants, the lists travel once as JSON, and the options are built when the
     modal is actually opened. Same lists, same order, same values.

     A select is hydrated exactly once. The marker is replaced in place, so
     whatever sat around it — the "Select district" placeholder above, an
     "Other (specify)" below — keeps its position.
--}}
@once
@php
    $ossOptionLists = [
        'districts' => collect($districts ?? [])->pluck('name')->filter()->values(),
        'streets'   => collect($streetNames ?? [])->pluck('name')->filter()->values(),
        'tp'        => collect($tpNumbers ?? [])->filter()->values(),
    ];
@endphp
<script type="application/json" id="oss-option-lists">{!! json_encode($ossOptionLists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
(function () {
    var LISTS = null;
    function lists() {
        if (LISTS) return LISTS;
        var el = document.getElementById('oss-option-lists');
        try { LISTS = el ? JSON.parse(el.textContent) : {}; } catch (e) { LISTS = {}; }
        return LISTS;
    }

    // Districts and streets print upper-cased; TP numbers print as stored.
    // This mirrors what the Blade loops emitted, character for character.
    function label(listName, value) {
        return listName === 'tp' ? value : String(value).toUpperCase();
    }

    function fill(marker) {
        var listName = marker.getAttribute('data-options-slot');
        var values = lists()[listName] || [];
        var frag = document.createDocumentFragment();
        for (var i = 0; i < values.length; i++) {
            frag.appendChild(new Option(label(listName, values[i]), values[i]));
        }
        marker.parentNode.replaceChild(frag, marker);
    }

    // Every not-yet-hydrated select inside `root`. Cheap enough to call on each
    // modal open: once a marker is gone the select is skipped for good.
    function hydrate(root) {
        var markers = (root || document).querySelectorAll('option[data-options-slot]');
        for (var i = 0; i < markers.length; i++) fill(markers[i]);
        return markers.length;
    }

    // Select2 reads a select's options when it initialises, so anything it
    // enhances has to be filled first — see _ossInitLocationAndTp().
    document.addEventListener('DOMContentLoaded', function () {
        hydrate(document.querySelector('#ossApplicationModal'));
    });

    // The rest are filled when their modal is shown. These modals open by
    // having `hidden` taken off them, which is what this watches for, so no
    // modal has to remember to ask.
    document.addEventListener('DOMContentLoaded', function () {
        var seen = [];
        document.querySelectorAll('option[data-options-slot]').forEach(function (marker) {
            var modal = marker.closest('.fixed.inset-0, [role="dialog"]');
            if (modal && seen.indexOf(modal) === -1) seen.push(modal);
        });
        seen.forEach(function (modal) {
            new MutationObserver(function (_, obs) {
                if (modal.classList.contains('hidden')) return;
                hydrate(modal);
                obs.disconnect();
            }).observe(modal, { attributes: true, attributeFilter: ['class'] });
        });
    });

    // A select reached some other way still fills itself before it can be used.
    document.addEventListener('focusin', function (e) {
        var sel = e.target && e.target.closest ? e.target.closest('select') : null;
        if (sel && sel.querySelector('option[data-options-slot]')) hydrate(sel);
    }, true);

    window.OssOptionLists = { hydrate: hydrate };
})();
</script>
@endonce
