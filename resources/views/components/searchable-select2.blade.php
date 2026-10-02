{{-- Searchable dropdowns (Select2) for District / Street Name selects.
     Include ONCE inside a page's footer-scripts section, then either add the
     `searchable-select` class to the target <select> elements (auto-initialised
     on DOM ready) or call window.initSearchableSelects(container) after
     injecting selects dynamically.

     jQuery is loaded in layouts.app's <head>, so $ is available here.
     Forms on these pages live inside `fixed inset-0 z-[9999]` modals, so the
     dropdown must be attached to the modal (dropdownParent) — the default
     body-attached dropdown would render behind the overlay. --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Match the Tailwind inputs used on the parcel-update / CoP forms */
    .select2-container--default .select2-selection--single {
        height: 42px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.75rem !important;
        padding: 6px 10px !important;
        font-size: 0.875rem !important;
        background: #fff;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #0f172a;
        padding-left: 2px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-dropdown {
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.75rem !important;
        overflow: hidden;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.5rem !important;
        padding: 6px 10px !important;
        font-size: 0.875rem !important;
        outline: none;
    }
    .select2-results__option {
        font-size: 0.8rem;
        padding: 8px 12px;
    }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #2563eb;
    }
    /* Stay above the fixed modals (z-[9999] / z-[10001]) even when the
       dropdownParent fallback is <body>. */
    .select2-container { z-index: 10050; }
</style>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    function s2Available() {
        return typeof window.$ !== 'undefined' && typeof $.fn.select2 !== 'undefined';
    }

    /* Enhance every select.searchable-select inside `root` (default: document)
       that is not already enhanced. Safe to call repeatedly. */
    window.initSearchableSelects = function (root) {
        if (!s2Available()) return;
        $(root || document).find('select.searchable-select').each(function () {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) return; // already enhanced
            var $modal = $el.closest('div.fixed'); // the page's fixed-position modal, if any
            $el.select2({
                width: '100%',
                dropdownParent: $modal.length ? $modal : $(document.body),
                placeholder: ($el.find('option:first').text() || 'Select...').trim(),
                allowClear: true,
                dropdownAutoWidth: true
            });
            /* Select2 only raises jQuery events, which never reach inline
               onchange= attributes or addEventListener('change') listeners —
               re-dispatch a real DOM event so the pages' existing handlers
               (toggleOtherInput / location previews) keep firing. */
            $el.off('select2:select.native select2:clear.native')
               .on('select2:select.native select2:clear.native', function () {
                    this.dispatchEvent(new Event('change', { bubbles: true }));
               });
        });
    };

    /* Call after setting a select's value programmatically (el.value = ...)
       so the Select2 widget re-renders without firing change handlers. */
    window.syncSearchableSelects = function (root) {
        if (!s2Available()) return;
        $(root || document).find('select.searchable-select').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).trigger('change.select2');
            }
        });
    };

    $(function () { window.initSearchableSelects(); });
})();
</script>
