/**
 * Searchable District / Street Name dropdowns backed by the reference API.
 *
 * There are ~1,800 districts and ~800 street names, so options are fetched on
 * demand (server-side search) rather than rendered into the page — a static
 * option list at this size is what makes pages like OSS Applications crawl.
 *
 * Markup:
 *   <select id="propertyDistrict" name="property_district"
 *           class="reference-select" data-reference-source="districts"></select>
 *
 * Values are the plain names ("KOFAR WAIKA"), matching how district and street
 * are stored on file_indexings / mother_applications. Free text is allowed:
 * legacy records hold names that are not in the reference tables, and those
 * must survive a backfill rather than be silently dropped.
 */
(function () {
    var ENDPOINTS = {
        districts: '/api/reference/districts',
        streets: '/api/reference/streets'
    };

    function s2Available() {
        return typeof window.$ !== 'undefined' && typeof window.$.fn.select2 !== 'undefined';
    }

    /**
     * Enhance every select[data-reference-source] inside `root` that isn't
     * already enhanced. Safe to call repeatedly.
     */
    function initReferenceSelects(root) {
        if (!s2Available()) {
            // select2 comes from the global file-number modal partial; retry
            // briefly in case this runs before that script has landed.
            if (!initReferenceSelects._retries) initReferenceSelects._retries = 0;
            if (initReferenceSelects._retries++ < 20) {
                setTimeout(function () { initReferenceSelects(root); }, 300);
            }
            return;
        }

        $(root || document).find('select[data-reference-source]').each(function () {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) return;

            var source = $el.data('reference-source');
            var url = ENDPOINTS[source];
            if (!url) {
                console.warn('Unknown reference source:', source);
                return;
            }

            var $modal = $el.closest('div.fixed');
            var placeholder = ($el.find('option:first').text() || 'Search...').trim();

            $el.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $modal.length ? $modal : $(document.body),
                // Keep values that aren't in the reference tables (legacy data,
                // or a name the user needs to type in).
                tags: true,
                createTag: function (params) {
                    var term = (params.term || '').trim();
                    if (!term) return null;
                    return { id: term.toUpperCase(), text: term.toUpperCase() + ' (new)', isNew: true };
                },
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { search: params.term || '', limit: 30 };
                    },
                    processResults: function (payload) {
                        return {
                            results: (payload.data || []).map(function (row) {
                                return { id: row.name, text: row.name };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Select2 raises jQuery-only events, which never reach inline
            // onchange= attributes or addEventListener('change') listeners.
            $el.off('select2:select.native select2:clear.native')
               .on('select2:select.native select2:clear.native', function () {
                    this.dispatchEvent(new Event('change', { bubbles: true }));
               });
        });
    }

    /**
     * Set a reference select's value programmatically (backfill). The option is
     * created when the value isn't one of the currently loaded results, so
     * values absent from the reference tables still stick.
     *
     * @param {HTMLElement|string} target  element or element id
     * @param {string} value
     * @param {boolean} [silent]  skip firing a DOM change event
     */
    function setReferenceSelectValue(target, value, silent) {
        var el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) return;

        value = value == null ? '' : String(value).trim();

        if (!value) {
            clearReferenceSelect(el, silent);
            return;
        }

        var exists = Array.from(el.options).some(function (opt) {
            return opt.value.toUpperCase() === value.toUpperCase();
        });

        if (!exists) {
            el.appendChild(new Option(value, value, true, true));
        }

        el.value = Array.from(el.options).find(function (opt) {
            return opt.value.toUpperCase() === value.toUpperCase();
        }).value;

        if (s2Available() && $(el).hasClass('select2-hidden-accessible')) {
            $(el).trigger('change.select2');
        }
        if (!silent) {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /** Reset a reference select back to its placeholder. */
    function clearReferenceSelect(target, silent) {
        var el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) return;

        // Drop everything except the leading placeholder option.
        while (el.options.length > 1) {
            el.remove(el.options.length - 1);
        }
        el.value = '';

        if (s2Available() && $(el).hasClass('select2-hidden-accessible')) {
            $(el).trigger('change.select2');
        }
        if (!silent) {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /**
     * Enable/disable a reference select and keep the Select2 widget in step
     * (used by the Location Details read-only / Edit toggles).
     */
    function setReferenceSelectDisabled(target, disabled) {
        var el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) return;

        el.disabled = !!disabled;
        if (s2Available() && $(el).hasClass('select2-hidden-accessible')) {
            $(el).trigger('change.select2');
        }
    }

    window.initReferenceSelects = initReferenceSelects;
    window.setReferenceSelectValue = setReferenceSelectValue;
    window.clearReferenceSelect = clearReferenceSelect;
    window.setReferenceSelectDisabled = setReferenceSelectDisabled;

    document.addEventListener('DOMContentLoaded', function () {
        initReferenceSelects();
    });
})();
