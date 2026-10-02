<style>
    .survey-proto .address-builder {
        border: 1.5px solid var(--gray-200);
        border-radius: var(--radius-sm);
        padding: 16px;
        margin-bottom: 20px;
        background: var(--gray-50);
    }
    .survey-proto .address-builder .ab-legend {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--primary);
        margin-bottom: 12px;
    }
    /* Select2 has to match the prototype's inputs, not its own default skin. */
    .survey-proto .select2-container--default .select2-selection--single,
    .survey-proto .select2-container--default .select2-selection--multiple {
        min-height: 42px;
        border: 1.5px solid var(--gray-300);
        border-radius: var(--radius-sm);
        font-family: var(--font);
    }
    .survey-proto .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
        padding-left: 12px;
        color: var(--gray-800);
        font-size: 14px;
    }
    .survey-proto .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    /* Multi-select tags */
    .survey-proto .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--primary-50);
        border: 1px solid var(--primary-100);
        color: var(--primary);
        font-size: 13px;
        padding: 2px 8px;
        margin: 5px 4px 0 0;
        border-radius: var(--radius-xs);
    }
    .survey-proto .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--primary);
        margin-right: 5px;
    }
    .survey-proto .select2-container--default.select2-container--focus .select2-selection--single,
    .survey-proto .select2-container--default.select2-container--focus .select2-selection--multiple,
    .survey-proto .select2-container--default.select2-container--open .select2-selection--single,
    .survey-proto .select2-container--default.select2-container--open .select2-selection--multiple {
        border-color: var(--primary);
    }
    /* The dropdown is appended to <body>, so it is NOT inside .survey-proto and
       must be styled unscoped. */
    .select2-dropdown { border-color: #dee2e6; font-family: 'Inter', sans-serif; font-size: 14px; }
    .select2-container { z-index: 10050; }
</style>

<script>
(function () {
    'use strict';

    // Mirrors App\Support\AddressBuilder so the preview matches what is stored.
    var SHOW_GLUE = ' & ';

    /** Every selected value of a field, whether it is multi-select or not. */
    function values(root, name) {
        var el = root.querySelector('[name="' + name + '"]') ||
                 root.querySelector('[name="' + name + '[]"]');
        if (!el) return [];
        if (el.multiple) {
            return Array.prototype.slice.call(el.selectedOptions)
                .map(function (o) { return (o.value || '').trim(); })
                .filter(function (v) { return v !== ''; });
        }
        var v = (el.value || '').trim();
        return v === '' ? [] : [v];
    }

    function scalar(root, name) {
        var v = values(root, name);
        return v.length ? v[0] : '';
    }

    /**
     * Join the selections for one segment, with the "Other" free text standing
     * in for the Other entry — and only when Other is actually selected.
     */
    function resolve(selected, other) {
        other = (other || '').trim();
        var kept = [];
        var sawOther = false;

        selected.forEach(function (v) {
            if (v.toLowerCase() === 'other') { sawOther = true; return; }
            if (kept.indexOf(v) === -1) kept.push(v);
        });

        if (sawOther && other !== '' && kept.indexOf(other) === -1) kept.push(other);

        return kept.join(SHOW_GLUE);
    }

    function join(parts) {
        return parts.map(function (p) { return (p || '').trim(); })
                    .filter(function (p) { return p !== ''; })
                    .join(', ');
    }

    function compose(root) {
        var prefix = root.dataset.prefix;
        var mode   = root.dataset.mode;

        var district = resolve(values(root, prefix + 'district'), scalar(root, prefix + 'district_other'));
        var lga      = resolve(values(root, prefix + 'lga'), '');
        var state    = scalar(root, prefix + 'state');

        if (mode === 'person') {
            var plot  = scalar(root, prefix + 'plot');
            var house = scalar(root, prefix + 'house');
            // Plot beats house; they never both appear.
            var street = resolve(values(root, prefix + 'street'), scalar(root, prefix + 'street_other'));
            return join([street, plot || house, district, lga, state]);
        }
        return join([district, lga, state]);
    }

    function refresh(root) {
        var preview = root.querySelector('.ab-preview');
        if (preview) preview.value = compose(root);
    }

    // Show the "specify" box only while Other is among the selections, and clear
    // it on the way out so a stale value cannot resurface.
    function syncOther(root, kind) {
        var prefix = root.dataset.prefix;
        var box = root.querySelector('.ab-' + kind + '-other');
        if (!box) return;

        var isOther = values(root, prefix + kind).some(function (v) {
            return v.toLowerCase() === 'other';
        });

        box.style.display = isOther ? '' : 'none';
        if (!isOther) {
            var input = box.querySelector('input');
            if (input) input.value = '';
        }
    }

    /** Select2 backed by one of the survey-module.lookup.* endpoints. */
    function remoteSelect($s, url, placeholder) {
        $s.select2({
            width: '100%',
            placeholder: placeholder,
            closeOnSelect: false,          // picking several is the common case
            // No dropdownParent: the default (<body>) keeps the panel clear of
            // the app shell's `.flex-1.overflow-auto` scroll container, which
            // would otherwise clip it.
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                // The endpoint already returns {results, pagination:{more}}.
                processResults: function (data) { return data; },
                cache: true
            },
            minimumInputLength: 0
        });
    }

    function init(root) {
        if (root.dataset.abReady) return;
        root.dataset.abReady = '1';

        var hasSelect2 = window.jQuery && jQuery.fn && jQuery.fn.select2;

        if (hasSelect2) {
            var $root = jQuery(root);

            var $district = $root.find('.ab-district');
            var $street   = $root.find('.ab-street');

            if ($district.length) remoteSelect($district, root.dataset.districtsUrl, $district.data('placeholder'));
            if ($street.length)   remoteSelect($street,   root.dataset.streetsUrl,   $street.data('placeholder'));

            // LGA (45) and State (37) are small enough to stay inline.
            $root.find('.ab-lga').each(function () {
                var $s = jQuery(this);
                $s.select2({
                    width: '100%',
                    placeholder: $s.data('placeholder') || 'Select…',
                    closeOnSelect: false
                });
            });
            $root.find('.ab-state').each(function () {
                var $s = jQuery(this);
                $s.select2({ width: '100%', placeholder: $s.data('placeholder') || 'Select…' });
            });

            $root.find('select').on('change', function () {
                var cls = this.className || '';
                if (cls.indexOf('ab-district') !== -1) syncOther(root, 'district');
                if (cls.indexOf('ab-street') !== -1)   syncOther(root, 'street');
                refresh(root);
            });
        } else {
            // Select2 missing or failed to load: the selects still submit, and
            // the Other toggle plus the preview keep working.
            root.querySelectorAll('select').forEach(function (s) {
                s.addEventListener('change', function () {
                    syncOther(root, 'district');
                    syncOther(root, 'street');
                    refresh(root);
                });
            });
        }

        root.querySelectorAll('input').forEach(function (i) {
            i.addEventListener('input', function () { refresh(root); });
        });

        syncOther(root, 'district');
        syncOther(root, 'street');
        refresh(root);
    }

    function initAll() {
        document.querySelectorAll('.address-builder').forEach(init);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    // Address builders inside modals or steps revealed later.
    window.initAddressBuilders = initAll;
})();
</script>
