/**
 * Lands One Stop Shop – Applications page JS (CRUD)
 *
 * Handles:
 *  - DataTable initialisation (search / length / type filter)
 *  - Application form modal with 3 SEPARATE forms (Residential / Commercial / Industrial)
 *  - View detail modal
 *  - Delete confirmation via SweetAlert2
 *  - AJAX CRUD against /lands-one-stop-shop/applications
 */

var OSS_BASE_URL = '/lands-one-stop-shop/applications';
var OSS_CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function _ossEnsureSwalOnTop() {
    if (document.getElementById('oss-swal-zindex-fix')) return;
    var style = document.createElement('style');
    style.id = 'oss-swal-zindex-fix';
    style.textContent = '.swal2-container{z-index:20050 !important;}';
    document.head.appendChild(style);
}

/* ═══════════════════════════════════════════════════════════════════════
   DOMContentLoaded – DataTable + bindings
   ═══════════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {

    _ossEnsureSwalOnTop();

    /* The Land page renders two fewer columns than the Change of Ownership page
       (it hides OP Serial No and Original Holder), so the position of the last
       two columns — Date Created, then Actions — is not the same on both. Read
       the count off the rendered header instead of hardcoding the indices, so
       adding or hiding a column can never silently mis-target them again. */
    var _lssColCount = document.querySelectorAll('#lss-applications-table thead th').length;

    var table = $('#lss-applications-table').DataTable({
        dom: '<"lss-table-scroll"t>',
        pageLength: -1,
        lengthChange: false,
        searching: false,
        ordering: true,
        info: false,
        paging: false,
        autoWidth: false,
        scrollX: false,
        order: _lssColCount >= 2 ? [[_lssColCount - 2, 'desc']] : [],
        columnDefs: _lssColCount >= 1
            ? [{ orderable: false, targets: [_lssColCount - 1] }]
            : [],
        language: {
            info: 'Showing _START_ to _END_ of _TOTAL_ applications',
            infoEmpty: 'No applications available',
            emptyTable: 'No applications found.',
            paginate: { previous: 'Prev', next: 'Next' }
        },
        drawCallback: function () {
            if (window.lucide) window.lucide.createIcons();
            // Re-initialise Alpine on action dropdowns after DataTable re-renders rows
            if (typeof Alpine !== 'undefined') {
                document.querySelectorAll('#lss-applications-table [x-data]').forEach(function (el) {
                    if (!el._x_dataStack) {
                        Alpine.initTree(el);
                    }
                });
            }
        }
    });

    /* Search – server-side with debounce (records are limited server-side) */
    var searchInput = document.getElementById('lss-search');
    var _lssSearchTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(_lssSearchTimer);
            var val = this.value;
            _lssSearchTimer = setTimeout(function () {
                var url = new URL(window.location.href);
                if (val) {
                    url.searchParams.set('search', val);
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            }, 600);
        });
    }

    /* Page length – reload the page so the server returns the right number of rows */
    var lengthSelect = document.getElementById('lss-length');
    if (lengthSelect) {
        lengthSelect.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('limit', this.value);
            window.location.href = url.toString();
        });
    }

    /* Type filter – server-side reload */
    var typeFilter = document.getElementById('lss-type-filter');
    if (typeFilter) {
        typeFilter.addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value) {
                url.searchParams.set('app_type', this.value);
            } else {
                url.searchParams.delete('app_type');
            }
            window.location.href = url.toString();
        });
    }

    /* Lucide icons */
    if (window.lucide) window.lucide.createIcons();
});

/* Separate DOMContentLoaded for Address Builder so DataTable errors don't block it */
document.addEventListener('DOMContentLoaded', function () {
    try {
        _ossInitAddressBuilders();
        _ossInitResidentialAddressMirroring();
        _ossInitLocationAndTp();
        console.log('[OSS] Address builders initialised successfully.');
    } catch (e) {
        console.error('[OSS] Address builder init failed:', e);
    }
});


/* ═══════════════════════════════════════════════════════════════════════
   Helper – get record JSON from a button's <tr>
   ═══════════════════════════════════════════════════════════════════════ */
function _ossGetRecord(btn) {
    var tr = btn.closest('tr');
    if (!tr) return null;
    try { return JSON.parse(tr.getAttribute('data-record')); } catch (e) { return null; }
}

/* ═══════════════════════════════════════════════════════════════════════
   Residential Passport Upload Helpers
   ═══════════════════════════════════════════════════════════════════════ */
function ossResPreviewPassport(input) {
    var img = document.getElementById('oss_res_passport_img');
    var placeholder = document.getElementById('oss_res_passport_placeholder');
    var removeBtn = document.getElementById('oss_res_passport_remove');

    if (!input || !input.files || !input.files[0]) {
        return;
    }

    var file = input.files[0];
    if (!file.type || file.type.indexOf('image/') !== 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Invalid file', text: 'Please select an image file for the passport photo.' });
        }
        input.value = '';
        return;
    }

    var reader = new FileReader();
    reader.onload = function (e) {
        if (img) {
            img.src = e.target.result;
            img.classList.remove('hidden');
        }
        if (placeholder) placeholder.classList.add('hidden');
        if (removeBtn) removeBtn.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function ossResRemovePassport() {
    var input = document.getElementById('oss_res_passport_input');
    var img = document.getElementById('oss_res_passport_img');
    var placeholder = document.getElementById('oss_res_passport_placeholder');
    var removeBtn = document.getElementById('oss_res_passport_remove');

    if (input) input.value = '';
    if (img) {
        img.src = '';
        img.classList.add('hidden');
    }
    if (placeholder) placeholder.classList.remove('hidden');
    if (removeBtn) removeBtn.classList.add('hidden');
}


/* ═══════════════════════════════════════════════════════════════════════
   Address Builder – helpers & initialisation
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Each address builder has a unique prefix. The builder has these sub-IDs:
 *   {prefix}_house, {prefix}_plot, {prefix}_street, {prefix}_street_other,
 *   {prefix}_district, {prefix}_district_other, {prefix}_lga, {prefix}_state
 *
 * The output goes into the real textarea/input whose ID is also specified.
 */
var _ossAddressBuilderDefs = [
    { prefix: 'oss_res_addr', output: 'oss_residential_address' },
    { prefix: 'oss_res_corr', output: 'oss_res_correspondence_address' },
    { prefix: 'oss_res_biz', output: 'oss_business_address' },
    { prefix: 'oss_com_biz', output: 'oss_com_business_location' },
    { prefix: 'oss_com_corr', output: 'oss_com_correspondence_address' },
    { prefix: 'oss_ind_biz', output: 'oss_ind_business_location' },
    { prefix: 'oss_ind_corr', output: 'oss_ind_correspondence_address' },
    { prefix: 'oss_agr_biz', output: 'oss_agr_business_location' },
    { prefix: 'oss_agr_corr', output: 'oss_agr_correspondence_address' },
    { prefix: 'coo_orig_addr', output: 'coo_original_address' },
    { prefix: 'coo_curr_addr', output: 'coo_current_address' },
    { prefix: 'ver_addr', output: 'ver_address' }
];

/**
 * A stored location with its plot number taken out.
 *
 * Locations backfilled from a file record routinely lead with the plot ("1540,
 * W/B PASS, KUMBOTSO, KANO STATE"), which is the Plot No field repeated. A full
 * property location does not carry it, so it comes out on the way in. The
 * record's own plot number is passed through so the match is exact.
 */
function _ossLocationWithoutPlot(location, plotNo) {
    var raw = (location || '').toString().trim();
    if (!raw || !window.PropertyLocation) return raw;
    return window.PropertyLocation.stripPlot(raw, (plotNo || '').toString().trim());
}

function _ossIsOtherValue(val) {
    return (val || '').trim().toLowerCase() === 'other';
}

function _ossToggleOther(selectEl, wrapperId, otherEl) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper || !selectEl) return;
    var show = _ossIsOtherValue(selectEl.value);
    wrapper.classList.toggle('hidden', !show);
    if (otherEl) otherEl.required = show;
    if (!show && otherEl) otherEl.value = '';
}

function _ossSelectedOrOther(selectEl, otherEl) {
    if (!selectEl) return '';
    if (_ossIsOtherValue(selectEl.value)) {
        return (otherEl?.value || '').trim();
    }
    return (selectEl.value || '').trim();
}

function _ossBuildAddress(prefix) {
    var house = (document.getElementById(prefix + '_house')?.value || '').trim();
    var plot = (document.getElementById(prefix + '_plot')?.value || '').trim();
    var street = document.getElementById(prefix + '_street');
    var streetO = document.getElementById(prefix + '_street_other');
    var dist = document.getElementById(prefix + '_district');
    var distO = document.getElementById(prefix + '_district_other');
    var lga = (document.getElementById(prefix + '_lga')?.value || '').trim();
    var state = (document.getElementById(prefix + '_state')?.value || '').trim();

    var streetVal = _ossSelectedOrOther(street, streetO);
    var distVal = _ossSelectedOrOther(dist, distO);

    // House No and Plot No are separate entries. A house number belongs in
    // front of the street it is on ("12A ZOO ROAD"), while a plot number is a
    // cadastral reference that stands on its own, so the two cannot simply be
    // concatenated. The plot is labelled in the output because "12A ZOO ROAD,
    // 45, NASSARAWA" reads as a second house number otherwise.
    //
    // This also repairs a long-standing fault in the line below: it read
    // `plot ? plot : streetVal`, so entering a plot number silently dropped the
    // street from every assembled address on this form.
    var streetLine = [house, streetVal].filter(Boolean).join(' ');
    var plotLine = plot ? (/^plot\b/i.test(plot) ? plot : 'PLOT ' + plot) : '';

    var parts = [
        streetLine,
        plotLine,
        distVal,
        lga,
        state
    ].filter(Boolean);

    return parts.join(', ').toUpperCase();
}

function _ossUpdateBuilderOutput(prefix, outputId) {
    var outputEl = document.getElementById(outputId);
    if (!outputEl) {
        console.warn('[OSS] Address output element not found:', outputId);
        return;
    }
    var compiled = _ossBuildAddress(prefix);
    outputEl.value = compiled;
}

function _ossInitAddressBuilders() {
    _ossAddressBuilderDefs.forEach(function (def) {
        var prefix = def.prefix;
        var outputId = def.output;

        var houseEl = document.getElementById(prefix + '_house');
        var plotEl = document.getElementById(prefix + '_plot');
        var streetEl = document.getElementById(prefix + '_street');
        var streetOEl = document.getElementById(prefix + '_street_other');
        var distEl = document.getElementById(prefix + '_district');
        var distOEl = document.getElementById(prefix + '_district_other');
        var lgaEl = document.getElementById(prefix + '_lga');
        var stateEl = document.getElementById(prefix + '_state');

        // Wrapper IDs follow pattern: convert prefix underscores to dashes + '-{field}-other-wrapper'
        var dashPrefix = prefix.replace(/_/g, '-');
        var streetWrapperId = dashPrefix + '-street-other-wrapper';
        var districtWrapperId = dashPrefix + '-district-other-wrapper';

        // Wire toggle + rebuild on all sub-field events
        var allInputs = [houseEl, plotEl, streetEl, streetOEl, distEl, distOEl, lgaEl, stateEl];
        allInputs.forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', function () { _ossUpdateBuilderOutput(prefix, outputId); });
            el.addEventListener('change', function () { _ossUpdateBuilderOutput(prefix, outputId); });
        });

        // Toggle "Other" wrappers on select change
        if (streetEl) {
            streetEl.addEventListener('change', function () {
                _ossToggleOther(streetEl, streetWrapperId, streetOEl);
                _ossUpdateBuilderOutput(prefix, outputId);
            });
        }
        if (distEl) {
            distEl.addEventListener('change', function () {
                _ossToggleOther(distEl, districtWrapperId, distOEl);
                _ossUpdateBuilderOutput(prefix, outputId);
            });
        }
    });
}

/* ═══════════════════════════════════════════════════════════════════════
   Occupancy Permit – TP No. dropdown + Location sub-section
   ═══════════════════════════════════════════════════════════════════════ */

/** Show/hide the "Specify TP No." input when the TP No. select is set to Other. */
function _ossToggleTpNoOther() {
    var sel = document.getElementById('oss_opd_tp_no');
    var wrapper = document.getElementById('oss-opd-tp-no-other-wrapper');
    var other = document.getElementById('oss_opd_tp_no_other');
    if (!sel || !wrapper) return;
    var show = _ossIsOtherValue(sel.value);
    wrapper.classList.toggle('hidden', !show);
    if (other) {
        other.required = show;
        if (!show) other.value = '';
    }
}

/** The effective TP No. value (the typed value when "Other" is selected). */
function _ossTpNoValue() {
    var sel = document.getElementById('oss_opd_tp_no');
    var other = document.getElementById('oss_opd_tp_no_other');
    return _ossSelectedOrOther(sel, other);
}

/** Compose the Full Location from Plot No / District / LGA. */
function _ossBuildOpLocation() {
    var out = document.getElementById('oss_lb_full_location');
    if (!out) return;
    // The plot number is not part of a full property location — it has its own
    // field on this card, and repeating it here only produced a leading number
    // that read as part of the address.
    var dist = (document.getElementById('oss_lb_district')?.value || '').trim();
    var lga = (document.getElementById('oss_lb_lga')?.value || '').trim();
    out.value = [dist, lga].filter(Boolean).join(', ').toUpperCase();
}

/**
 * Enhance the OP TP No. / District / LGA selects with Select2 (loaded on this
 * page) and wire the "Other → specify" toggle + Full Location compose.
 * Safe to call repeatedly.
 */
function _ossInitLocationAndTp() {
    var hasS2 = typeof window.$ !== 'undefined' && typeof window.$.fn !== 'undefined' && typeof window.$.fn.select2 !== 'undefined';
    if (hasS2) {
        var $modal = window.$('#ossApplicationModal');
        window.$('.oss-searchable-select').each(function () {
            var $el = window.$(this);
            if ($el.hasClass('select2-hidden-accessible')) return; // already enhanced
            $el.select2({
                width: '100%',
                dropdownParent: $modal.length ? $modal : window.$(document.body),
                placeholder: (($el.find('option:first').text() || 'Select...').trim()),
                allowClear: true
            });
            // Select2 fires jQuery events only — re-dispatch a native change so
            // the vanilla listeners below (Other toggle / location compose) run.
            $el.off('select2:select.oss select2:clear.oss')
               .on('select2:select.oss select2:clear.oss', function () {
                   this.dispatchEvent(new Event('change', { bubbles: true }));
               });
        });
    }

    var tpSel = document.getElementById('oss_opd_tp_no');
    if (tpSel && !tpSel._ossTpWired) {
        tpSel._ossTpWired = true;
        tpSel.addEventListener('change', _ossToggleTpNoOther);
    }

    ['oss_lb_plot_no', 'oss_lb_district', 'oss_lb_lga'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el || el._ossLocWired) return;
        el._ossLocWired = true;
        el.addEventListener('input', _ossBuildOpLocation);
        el.addEventListener('change', _ossBuildOpLocation);
    });
}

/** Push a value into a Select2-enhanced select (re-rendering the widget). */
function _ossSetSelectValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value || '';
    if (typeof window.$ !== 'undefined' && window.$(el).hasClass('select2-hidden-accessible')) {
        window.$(el).trigger('change.select2');
    }
}

/**
 * Preselect the TP No. dropdown. If the value isn't an existing option, add it
 * so the record's TP No. is preserved without forcing the user onto "Other".
 */
function _ossSetTpNo(value) {
    var val = (value || '').toString().trim();
    var sel = document.getElementById('oss_opd_tp_no');
    if (!sel) return;
    if (!val) { _ossSetSelectValue('oss_opd_tp_no', ''); _ossToggleTpNoOther(); return; }
    var exists = Array.prototype.some.call(sel.options, function (o) { return o.value === val; });
    if (!exists) {
        var opt = document.createElement('option');
        opt.value = val;
        opt.textContent = val;
        // Insert before the trailing "Other" option so it stays last.
        var otherOpt = Array.prototype.filter.call(sel.options, function (o) { return _ossIsOtherValue(o.value); })[0];
        if (otherOpt) sel.insertBefore(opt, otherOpt); else sel.appendChild(opt);
    }
    _ossSetSelectValue('oss_opd_tp_no', val);
    _ossToggleTpNoOther();
}

/** Reset the TP No. + Location fields (used on modal open / clear). */
function _ossResetLocationAndTp() {
    _ossSetSelectValue('oss_opd_tp_no', '');
    var tpOther = document.getElementById('oss_opd_tp_no_other');
    if (tpOther) tpOther.value = '';
    _ossToggleTpNoOther();
    ['oss_lb_plot_no', 'oss_lb_full_location'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    _ossSetSelectValue('oss_lb_district', '');
    _ossSetSelectValue('oss_lb_lga', '');
}

function _ossMirrorAddressBuilder(sourcePrefix, targetPrefix, targetOutputId) {
    var fields = ['plot', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'];

    fields.forEach(function (field) {
        var sourceEl = document.getElementById(sourcePrefix + '_' + field);
        var targetEl = document.getElementById(targetPrefix + '_' + field);
        if (sourceEl && targetEl) {
            targetEl.value = sourceEl.value;
        }
    });

    var targetStreet = document.getElementById(targetPrefix + '_street');
    var targetStreetOther = document.getElementById(targetPrefix + '_street_other');
    var targetDistrict = document.getElementById(targetPrefix + '_district');
    var targetDistrictOther = document.getElementById(targetPrefix + '_district_other');
    var targetDashPrefix = targetPrefix.replace(/_/g, '-');

    if (targetStreet) {
        _ossToggleOther(targetStreet, targetDashPrefix + '-street-other-wrapper', targetStreetOther);
    }
    if (targetDistrict) {
        _ossToggleOther(targetDistrict, targetDashPrefix + '-district-other-wrapper', targetDistrictOther);
    }

    _ossUpdateBuilderOutput(targetPrefix, targetOutputId);
}

function _ossInitResidentialAddressMirroring() {
    var sourcePrefix = 'oss_res_addr';
    var sourceFields = ['plot', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'];

    var syncTargets = function () {
        _ossMirrorAddressBuilder(sourcePrefix, 'oss_res_corr', 'oss_res_correspondence_address');
        _ossMirrorAddressBuilder(sourcePrefix, 'oss_res_biz', 'oss_business_address');
    };

    sourceFields.forEach(function (field) {
        var sourceEl = document.getElementById(sourcePrefix + '_' + field);
        if (!sourceEl) return;
        sourceEl.addEventListener('input', syncTargets);
        sourceEl.addEventListener('change', syncTargets);
    });
}

/** Clear a single address builder by prefix */
function _ossClearSingleAddressBuilder(prefix, outputId) {
    ['_house', '_plot', '_street', '_street_other', '_district', '_district_other', '_lga', '_state'].forEach(function (suffix) {
        var el = document.getElementById(prefix + suffix);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            if (suffix === '_state') {
                for (var i = 0; i < el.options.length; i++) {
                    if (el.options[i].value === 'Kano') { el.selectedIndex = i; return; }
                }
                el.selectedIndex = 0;
            } else {
                el.selectedIndex = 0;
            }
        } else {
            el.value = '';
        }
    });
    var dashPrefix = prefix.replace(/_/g, '-');
    var streetWrapper = document.getElementById(dashPrefix + '-street-other-wrapper');
    var distWrapper = document.getElementById(dashPrefix + '-district-other-wrapper');
    if (streetWrapper) streetWrapper.classList.add('hidden');
    if (distWrapper) distWrapper.classList.add('hidden');
    var outputEl = document.getElementById(outputId);
    if (outputEl) outputEl.value = '';
}

/** Clear all address builder sub-fields (called from _ossClearAllFields) */
function _ossClearAddressBuilders() {
    _ossAddressBuilderDefs.forEach(function (def) {
        var prefix = def.prefix;
        ['_house', '_plot', '_street', '_street_other', '_district', '_district_other', '_lga', '_state'].forEach(function (suffix) {
            var el = document.getElementById(prefix + suffix);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                // Reset to first option (empty) — except state which defaults to Kano
                if (suffix === '_state') {
                    // Try to select Kano
                    var found = false;
                    for (var i = 0; i < el.options.length; i++) {
                        if (el.options[i].value === 'Kano') { el.selectedIndex = i; found = true; break; }
                    }
                    if (!found) el.selectedIndex = 0;
                } else {
                    el.selectedIndex = 0;
                }
            } else {
                el.value = '';
            }
        });

        // Hide "Other" wrappers
        var dashPrefix = prefix.replace(/_/g, '-');
        var streetWrapper = document.getElementById(dashPrefix + '-street-other-wrapper');
        var distWrapper = document.getElementById(dashPrefix + '-district-other-wrapper');
        if (streetWrapper) streetWrapper.classList.add('hidden');
        if (distWrapper) distWrapper.classList.add('hidden');

        // Clear output
        var outputEl = document.getElementById(def.output);
        if (outputEl) outputEl.value = '';
    });
}

/**
 * When editing, populate the address builder sub-fields and output fields from stored data.
 * The builder sub-fields are restored from their dedicated DB columns, then the
 * output is re-built to ensure consistency.
 */
function _ossPopulateAddressOnEdit(type, data) {
    // Map: address builder prefix → list of sub-field suffixes → data column names
    var builderMap = {
        residential: [
            { prefix: 'oss_res_addr', cols: { _house: 'res_addr_house', _plot: 'res_addr_plot', _street: 'res_addr_street', _street_other: 'res_addr_street_other', _district: 'res_addr_district', _district_other: 'res_addr_district_other', _lga: 'res_addr_lga', _state: 'res_addr_state' }, output: 'oss_residential_address', outputCol: 'residential_address' },
            { prefix: 'oss_res_corr', cols: { _house: 'res_corr_house', _plot: 'res_corr_plot', _street: 'res_corr_street', _street_other: 'res_corr_street_other', _district: 'res_corr_district', _district_other: 'res_corr_district_other', _lga: 'res_corr_lga', _state: 'res_corr_state' }, output: 'oss_res_correspondence_address', outputCol: 'correspondence_address' },
            { prefix: 'oss_res_biz', cols: { _house: 'res_biz_house', _plot: 'res_biz_plot', _street: 'res_biz_street', _street_other: 'res_biz_street_other', _district: 'res_biz_district', _district_other: 'res_biz_district_other', _lga: 'res_biz_lga', _state: 'res_biz_state' }, output: 'oss_business_address', outputCol: 'business_address' }
        ],
        commercial: [
            { prefix: 'oss_com_biz', cols: { _house: 'com_biz_house', _plot: 'com_biz_plot', _street: 'com_biz_street', _street_other: 'com_biz_street_other', _district: 'com_biz_district', _district_other: 'com_biz_district_other', _lga: 'com_biz_lga', _state: 'com_biz_state' }, output: 'oss_com_business_location', outputCol: 'business_location' },
            { prefix: 'oss_com_corr', cols: { _house: 'com_corr_house', _plot: 'com_corr_plot', _street: 'com_corr_street', _street_other: 'com_corr_street_other', _district: 'com_corr_district', _district_other: 'com_corr_district_other', _lga: 'com_corr_lga', _state: 'com_corr_state' }, output: 'oss_com_correspondence_address', outputCol: 'correspondence_address' }
        ],
        industrial: [
            { prefix: 'oss_ind_biz', cols: { _house: 'ind_biz_house', _plot: 'ind_biz_plot', _street: 'ind_biz_street', _street_other: 'ind_biz_street_other', _district: 'ind_biz_district', _district_other: 'ind_biz_district_other', _lga: 'ind_biz_lga', _state: 'ind_biz_state' }, output: 'oss_ind_business_location', outputCol: 'business_location' },
            { prefix: 'oss_ind_corr', cols: { _house: 'ind_corr_house', _plot: 'ind_corr_plot', _street: 'ind_corr_street', _street_other: 'ind_corr_street_other', _district: 'ind_corr_district', _district_other: 'ind_corr_district_other', _lga: 'ind_corr_lga', _state: 'ind_corr_state' }, output: 'oss_ind_correspondence_address', outputCol: 'correspondence_address' }
        ],
        agricultural: [
            { prefix: 'oss_agr_biz', cols: { _house: 'agr_biz_house', _plot: 'agr_biz_plot', _street: 'agr_biz_street', _street_other: 'agr_biz_street_other', _district: 'agr_biz_district', _district_other: 'agr_biz_district_other', _lga: 'agr_biz_lga', _state: 'agr_biz_state' }, output: 'oss_agr_business_location', outputCol: 'business_location' },
            { prefix: 'oss_agr_corr', cols: { _house: 'agr_corr_house', _plot: 'agr_corr_plot', _street: 'agr_corr_street', _street_other: 'agr_corr_street_other', _district: 'agr_corr_district', _district_other: 'agr_corr_district_other', _lga: 'agr_corr_lga', _state: 'agr_corr_state' }, output: 'oss_agr_correspondence_address', outputCol: 'correspondence_address' }
        ]
    };

    var defs = builderMap[type] || [];
    defs.forEach(function (def) {
        var hasSubFields = false;

        // Restore each sub-field
        Object.keys(def.cols).forEach(function (suffix) {
            var el = document.getElementById(def.prefix + suffix);
            var val = data[def.cols[suffix]] || '';
            if (el && val) {
                el.value = val;
                hasSubFields = true;

                // Show "Other" wrapper if street/district is Other
                if (suffix === '_street' && _ossIsOtherValue(val)) {
                    var dashPfx = def.prefix.replace(/_/g, '-');
                    var w = document.getElementById(dashPfx + '-street-other-wrapper');
                    if (w) w.classList.remove('hidden');
                }
                if (suffix === '_district' && _ossIsOtherValue(val)) {
                    var dashPfx2 = def.prefix.replace(/_/g, '-');
                    var w2 = document.getElementById(dashPfx2 + '-district-other-wrapper');
                    if (w2) w2.classList.remove('hidden');
                }
            }
        });

        // If sub-fields were stored, rebuild the output from them;
        // otherwise fall back to the stored assembled string.
        if (hasSubFields) {
            _ossUpdateBuilderOutput(def.prefix, def.output);
        } else {
            var outputEl = document.getElementById(def.output);
            if (outputEl && data[def.outputCol]) {
                outputEl.value = data[def.outputCol];
            }
        }
    });
}


/* ═══════════════════════════════════════════════════════════════════════
   Type Tab Switching – show / hide entire form sections
   ═══════════════════════════════════════════════════════════════════════ */
var _ossTypeColors = {
    residential: { from: 'from-yellow-500', to: 'to-yellow-600', active: 'bg-yellow-500 text-white shadow-sm' },
    commercial: { from: 'from-blue-600', to: 'to-blue-700', active: 'bg-blue-600 text-white shadow-sm' },
    industrial: { from: 'from-red-600', to: 'to-red-700', active: 'bg-red-600 text-white shadow-sm' },
    agricultural: { from: 'from-green-600', to: 'to-green-700', active: 'bg-green-600 text-white shadow-sm' }
};

function ossSelectType(type) {
    document.getElementById('oss_application_type').value = type;

    // Toggle tab buttons
    ['residential', 'commercial', 'industrial', 'agricultural'].forEach(function (t) {
        var btn = document.getElementById('ossTab_' + t);
        if (!btn) return;
        if (t === type) {
            btn.className = 'oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition ' + _ossTypeColors[t].active;
        } else {
            btn.className = 'oss-tab-btn px-5 py-2 rounded-lg text-sm font-semibold transition bg-white text-slate-500 border border-slate-200 hover:bg-slate-50';
        }
    });

    // Header gradient
    var header = document.getElementById('ossModalHeader');
    if (header) {
        header.className = 'flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r ' + _ossTypeColors[type].from + ' ' + _ossTypeColors[type].to;
    }

    // Show/hide form sections
    var resForm = document.getElementById('ossResidentialForm');
    var comForm = document.getElementById('ossCommercialForm');
    var indForm = document.getElementById('ossIndustrialForm');
    var agrForm = document.getElementById('ossAgriculturalForm');
    if (resForm) resForm.classList.toggle('hidden', type !== 'residential');
    if (comForm) comForm.classList.toggle('hidden', type !== 'commercial');
    if (indForm) indForm.classList.toggle('hidden', type !== 'industrial');
    if (agrForm) agrForm.classList.toggle('hidden', type !== 'agricultural');

    if (window.lucide) window.lucide.createIcons();
}


/* ═══════════════════════════════════════════════════════════════════════
   Field mapping: element IDs → database column names for each type
   ═══════════════════════════════════════════════════════════════════════ */
var _ossFieldMap = {
    residential: {
        'oss_file_no': 'file_no',
        'oss_applicant_name': 'applicant_name',
        'oss_age': 'age',
        'oss_sex': 'sex',
        'oss_marital_status': 'marital_status',
        'oss_husband_name_address': 'husband_name_address',
        'oss_residential_address': 'residential_address',
        'oss_res_correspondence_address': 'correspondence_address',
        'oss_res_email': 'email',
        'oss_res_phone': 'phone',
        'oss_business_address': 'business_address',
        'oss_res_nationality': 'nationality',
        'oss_res_state_of_origin': 'state_of_origin',
        'oss_res_lga': 'lga',
        'oss_occupation': 'occupation',
        'oss_res_annual_income': 'annual_income',
        'oss_res_prev_allocated': 'prev_allocated',
        'oss_remarks': 'remarks',
        // Residential Address builder sub-fields
        'oss_res_addr_house': 'res_addr_house',
        'oss_res_addr_plot': 'res_addr_plot',
        'oss_res_addr_street': 'res_addr_street',
        'oss_res_addr_street_other': 'res_addr_street_other',
        'oss_res_addr_district': 'res_addr_district',
        'oss_res_addr_district_other': 'res_addr_district_other',
        'oss_res_addr_lga': 'res_addr_lga',
        'oss_res_addr_state': 'res_addr_state',
        // Correspondence Address builder sub-fields
        'oss_res_corr_house': 'res_corr_house',
        'oss_res_corr_plot': 'res_corr_plot',
        'oss_res_corr_street': 'res_corr_street',
        'oss_res_corr_street_other': 'res_corr_street_other',
        'oss_res_corr_district': 'res_corr_district',
        'oss_res_corr_district_other': 'res_corr_district_other',
        'oss_res_corr_lga': 'res_corr_lga',
        'oss_res_corr_state': 'res_corr_state',
        // Business Address builder sub-fields
        'oss_res_biz_house': 'res_biz_house',
        'oss_res_biz_plot': 'res_biz_plot',
        'oss_res_biz_street': 'res_biz_street',
        'oss_res_biz_street_other': 'res_biz_street_other',
        'oss_res_biz_district': 'res_biz_district',
        'oss_res_biz_district_other': 'res_biz_district_other',
        'oss_res_biz_lga': 'res_biz_lga',
        'oss_res_biz_state': 'res_biz_state'
    },
    commercial: {
        'oss_file_no': 'file_no',
        'oss_com_applicant_name': 'applicant_name',
        'oss_com_nationality': 'nationality',
        'oss_com_state_of_origin': 'state_of_origin',
        'oss_com_home_domicile': 'home_domicile',
        'oss_com_occupation_or_business': 'occupation_or_business',
        'oss_com_nature_of_commerce': 'nature_of_commerce',
        'oss_com_annual_income': 'annual_income',
        'oss_com_company_registered_under': 'company_registered_under',
        'oss_com_registration_particulars': 'registration_particulars',
        'oss_com_business_location': 'business_location',
        'oss_com_correspondence_address': 'correspondence_address',
        'oss_com_annual_income_anticipation': 'annual_income_anticipation',
        'oss_com_prev_allocated': 'prev_allocated',
        'oss_com_prev_allocation_details': 'prev_allocation_details',
        'oss_com_prev_land_purpose': 'prev_land_purpose',
        'oss_com_purpose': 'purpose',
        'oss_com_intended_activities': 'intended_activities',
        'oss_remarks': 'remarks',        // Business Location Address builder sub-fields
        'oss_com_biz_house': 'com_biz_house',
        'oss_com_biz_plot': 'com_biz_plot',
        'oss_com_biz_street': 'com_biz_street',
        'oss_com_biz_street_other': 'com_biz_street_other',
        'oss_com_biz_district': 'com_biz_district',
        'oss_com_biz_district_other': 'com_biz_district_other',
        'oss_com_biz_lga': 'com_biz_lga',
        'oss_com_biz_state': 'com_biz_state',        // Correspondence Address builder sub-fields
        'oss_com_corr_house': 'com_corr_house',
        'oss_com_corr_plot': 'com_corr_plot',
        'oss_com_corr_street': 'com_corr_street',
        'oss_com_corr_street_other': 'com_corr_street_other',
        'oss_com_corr_district': 'com_corr_district',
        'oss_com_corr_district_other': 'com_corr_district_other',
        'oss_com_corr_lga': 'com_corr_lga',
        'oss_com_corr_state': 'com_corr_state'
    },
    industrial: {
        'oss_file_no': 'file_no',
        'oss_ind_applicant_name': 'applicant_name',
        'oss_ind_nationality': 'nationality',
        'oss_ind_state_of_origin': 'state_of_origin',
        'oss_ind_home_domicile': 'home_domicile',
        'oss_ind_occupation_or_business': 'occupation_or_business',
        'oss_ind_nature_of_occupation': 'nature_of_occupation',
        'oss_ind_company_registered_under': 'company_registered_under',
        'oss_ind_registration_particulars': 'registration_particulars',
        'oss_ind_business_location': 'business_location',
        'oss_ind_correspondence_address': 'correspondence_address',
        'oss_ind_annual_income_turnover': 'annual_income_turnover',
        'oss_ind_number_of_employees': 'number_of_employees',
        'oss_ind_prev_allocated': 'prev_allocated',
        'oss_ind_prev_allocation_details': 'prev_allocation_details',
        'oss_ind_purpose': 'purpose',
        'oss_ind_nature_of_industrial': 'nature_of_industrial',
        'oss_ind_waste_disposal': 'waste_disposal_requirements',
        'oss_remarks': 'remarks',
        // Business Location Address builder sub-fields
        'oss_ind_biz_house': 'ind_biz_house',
        'oss_ind_biz_plot': 'ind_biz_plot',
        'oss_ind_biz_street': 'ind_biz_street',
        'oss_ind_biz_street_other': 'ind_biz_street_other',
        'oss_ind_biz_district': 'ind_biz_district',
        'oss_ind_biz_district_other': 'ind_biz_district_other',
        'oss_ind_biz_lga': 'ind_biz_lga',
        'oss_ind_biz_state': 'ind_biz_state',
        // Correspondence Address builder sub-fields
        'oss_ind_corr_house': 'ind_corr_house',
        'oss_ind_corr_plot': 'ind_corr_plot',
        'oss_ind_corr_street': 'ind_corr_street',
        'oss_ind_corr_street_other': 'ind_corr_street_other',
        'oss_ind_corr_district': 'ind_corr_district',
        'oss_ind_corr_district_other': 'ind_corr_district_other',
        'oss_ind_corr_lga': 'ind_corr_lga',
        'oss_ind_corr_state': 'ind_corr_state'
    },
    agricultural: {
        'oss_file_no': 'file_no',
        'oss_agr_applicant_name': 'applicant_name',
        'oss_agr_nationality': 'nationality',
        'oss_agr_state_of_origin': 'state_of_origin',
        'oss_agr_home_domicile': 'home_domicile',
        'oss_agr_occupation_or_business': 'occupation_or_business',
        'oss_agr_nature_of_occupation': 'nature_of_occupation',
        'oss_agr_company_registered_under': 'company_registered_under',
        'oss_agr_registration_particulars': 'registration_particulars',
        'oss_agr_business_location': 'business_location',
        'oss_agr_correspondence_address': 'correspondence_address',
        'oss_agr_annual_income_turnover': 'annual_income_turnover',
        'oss_agr_number_of_employees': 'number_of_employees',
        'oss_agr_prev_allocated': 'prev_allocated',
        'oss_agr_prev_allocation_details': 'prev_allocation_details',
        'oss_agr_purpose': 'purpose',
        'oss_agr_nature_of_agricultural': 'nature_of_agricultural',
        'oss_agr_waste_disposal': 'waste_disposal_requirements',
        'oss_remarks': 'remarks',
        // Business Location Address builder sub-fields
        'oss_agr_biz_house': 'agr_biz_house',
        'oss_agr_biz_plot': 'agr_biz_plot',
        'oss_agr_biz_street': 'agr_biz_street',
        'oss_agr_biz_street_other': 'agr_biz_street_other',
        'oss_agr_biz_district': 'agr_biz_district',
        'oss_agr_biz_district_other': 'agr_biz_district_other',
        'oss_agr_biz_lga': 'agr_biz_lga',
        'oss_agr_biz_state': 'agr_biz_state',
        // Correspondence Address builder sub-fields
        'oss_agr_corr_house': 'agr_corr_house',
        'oss_agr_corr_plot': 'agr_corr_plot',
        'oss_agr_corr_street': 'agr_corr_street',
        'oss_agr_corr_street_other': 'agr_corr_street_other',
        'oss_agr_corr_district': 'agr_corr_district',
        'oss_agr_corr_district_other': 'agr_corr_district_other',
        'oss_agr_corr_lga': 'agr_corr_lga',
        'oss_agr_corr_state': 'agr_corr_state'
    }
};


/* ═══════════════════════════════════════════════════════════════════════
   Collect form data from the active type's fields
   ═══════════════════════════════════════════════════════════════════════ */
function _ossCollectPayload() {
    var type = document.getElementById('oss_application_type').value;
    var map = _ossFieldMap[type] || {};
    var payload = { application_type: type };

    Object.keys(map).forEach(function (elId) {
        var el = document.getElementById(elId);
        payload[map[elId]] = el ? el.value : '';
    });

    // Include property details from linked PRA record
    if (_ossLinkedPraData) {
        if (!payload.plot_no) payload.plot_no = (_ossLinkedPraData.plot_no || '').toString().trim();
        if (!payload.plan_no) payload.plan_no = (_ossLinkedPraData.plan_no || _ossLinkedPraData.tp_no || '').toString().trim();
        if (!payload.location) payload.location = (_ossLinkedPraData.location || _ossLinkedPraData.property_description || '').toString().trim();
    }

    // ── TP No. + Location (OP-scoped; shared across all application types) ──
    // These live in the Occupancy Permit Details card and override any values
    // inherited from the linked PRA record above.
    var tpNo = _ossTpNoValue();
    if (tpNo) payload.plan_no = tpNo;
    var lbPlot = (document.getElementById('oss_lb_plot_no')?.value || '').trim();
    if (lbPlot) payload.plot_no = lbPlot;
    var lbDistrict = (document.getElementById('oss_lb_district')?.value || '').trim();
    if (lbDistrict) payload.district = lbDistrict;
    var lbLocation = (document.getElementById('oss_lb_full_location')?.value || '').trim();
    if (lbLocation) payload.location = lbLocation;

    // ── Occupancy Permit Details ──
    // The OPD section is now the single source of the OP serial (the old manual
    // box is gone). op_from_record tells the backend whether this OP already
    // exists (update) or was entered by the user (insert).
    var opdVal = function (elId) {
        var el = document.getElementById(elId);
        return el ? (el.value || '').toString().trim() : '';
    };
    // The OP section is visible only when the selected file has an Occupancy Permit.
    var opdSection = document.getElementById('oss_opd_section');
    var hasOpd = !!(opdSection && !opdSection.classList.contains('hidden'));
    payload.has_occupancy_permit = hasOpd ? 1 : 0;
    payload.op_serial_number = opdVal('oss_opd_op_serial_number');
    if (hasOpd) {
        payload.op_from_record = _ossOpdFromRecord ? 1 : 0;
        payload.occupancy_permit = {
            // Primary key of the PRA row this section was backfilled from — the
            // backend updates THAT row, not whichever sibling shares its prop_id.
            record_id: _ossOpdRecordId || 0,
            status: opdVal('oss_opd_status'),
            op_type: opdVal('oss_opd_op_type'),
            op_serial_number: payload.op_serial_number,
            transaction_date: opdVal('oss_opd_date'),
            file_number: opdVal('oss_opd_file_number'),
            land_use: opdVal('oss_opd_land_use'),
            grantor: opdVal('oss_opd_grantor'),
            grantee: opdVal('oss_opd_grantee'),
            serial_no: opdVal('oss_opd_serial_no'),
            page_no: opdVal('oss_opd_page_no'),
            vol_no: opdVal('oss_opd_vol_no'),
            deeds_time: opdVal('oss_opd_deeds_time'),
            deeds_date: opdVal('oss_opd_deeds_date'),
            from_record: _ossOpdFromRecord ? 1 : 0
        };
    }

    // For residential: collect previous allocation details as JSON
    if (type === 'residential') {
        var prevEntries = [];
        for (var i = 1; i <= 3; i++) {
            var plot = (document.getElementById('oss_prev_plot_' + i) || {}).value || '';
            var loc = (document.getElementById('oss_prev_location_' + i) || {}).value || '';
            var cert = (document.getElementById('oss_prev_cert_' + i) || {}).value || '';
            if (plot || loc || cert) {
                prevEntries.push({ plot_no: plot, location: loc, cert_no: cert });
            }
        }
        if (prevEntries.length) {
            payload.prev_allocation_details = JSON.stringify(prevEntries);
        }
    }

    return payload;
}


/* ────────────────────────────────────────────────────────────────────────────────
    File Number Lookup (via Global File Number Selector → PRA)
    ──────────────────────────────────────────────────────────────────────────────── */

/**
 * Open the global file number selector modal.
 * On selection, look up PRA and prefill the application form.
 */
function ossOpenFileNumberSelector() {
    if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
        alert('File Number Selector is not available on this page.');
        return;
    }

    window.GlobalFileNoModal.open({
        callback: function (fileData) {
            var selectedFileNo = (fileData && fileData.fileNumber ? fileData.fileNumber : '').toString().trim().replace(/-+$/, '');
            if (!selectedFileNo) return;

            // Set the file number on the form
            var displayEl = document.getElementById('oss_file_no_display');
            var hiddenEl = document.getElementById('oss_file_no');
            if (displayEl) displayEl.value = selectedFileNo;
            if (hiddenEl) hiddenEl.value = selectedFileNo;
            _ossSetOpdFileNumber(selectedFileNo);

            // Fetch PRA record details
            _ossFetchFileIndexing(selectedFileNo);

            // Check for duplicate file number and disable Save if found
            _ossCheckFileNoDuplicate(selectedFileNo);

            // Show clear button
            var clearBtn = document.getElementById('oss_file_no_clear_btn');
            if (clearBtn) clearBtn.classList.remove('hidden');

            // Close the global modal
            window.GlobalFileNoModal.close();
        }
    });
}

/**
 * Fetch PRA record by file number, display linked card, and prefill modal fields.
 */
var _ossLinkedPraData = null;

function _ossIsChangeOfNamePage() {
    return new URLSearchParams(window.location.search).get('type') === 'change-of-name';
}

function _ossSetChangeOfNameEligibility(isEligible, message) {
    var submitBtn = document.getElementById('ossSubmitBtn');
    var existingBanner = document.getElementById('oss_transfer_requirement_banner');
    if (existingBanner) existingBanner.remove();

    if (!isEligible) {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.classList.remove('hover:bg-blue-700');
        }

        var container = document.getElementById('oss_file_indexing_card');
        if (!container) return;

        var banner = document.createElement('div');
        banner.id = 'oss_transfer_requirement_banner';
        banner.className = 'mt-2 p-3 rounded-lg border border-amber-300 bg-amber-50 text-sm';
        banner.innerHTML = '<div class="flex items-start gap-2">' +
            '<svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M12 3l9 16H3L12 3z"/></svg>' +
            '<div>' +
            '<p class="font-bold text-amber-700">Occupancy Permit Required</p>' +
            '<p class="text-amber-700 text-xs mt-0.5">' + _ossEsc(message || 'This file number is not linked to any Occupancy Permit (OP) record in PRA. You cannot proceed on the Change of Name page.') + '</p>' +
            '</div></div>';
        container.parentElement.appendChild(banner);
        return;
    }

    _ossEnableSubmitBtn();
}

function _ossFetchFileIndexing(fileNo) {
    var card = document.getElementById('oss_file_indexing_card');
    var details = document.getElementById('oss_file_indexing_details');
    var empty = document.getElementById('oss_file_indexing_empty');
    var idField = document.getElementById('oss_file_indexing_id');

    _ossLinkedPraData = null;
    if (details) details.innerHTML = '<span class="text-slate-500">Looking up record...</span>';
    if (card) card.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');

    fetch(OSS_BASE_URL + '/lookup-file-indexing?file_no=' + encodeURIComponent(fileNo), {
        headers: { 'Accept': 'application/json' }
    })
        .then(function (res) { return res.json(); })
        .then(function (body) {
            if (!body || body.success !== true || !body.data) {
                if (details) details.innerHTML = '<span class="text-amber-600">' + (body.message || 'No PRA record found for this file number.') + '</span>';
                if (idField) idField.value = '';
                if (_ossIsChangeOfNamePage()) {
                    _ossSetChangeOfNameEligibility(false, 'No PRA record found for this file number. You cannot proceed on the Change of Name page without an Occupancy Permit (OP) record.');
                }
                return;
            }
            var d = body.data;
            _ossLinkedPraData = d;
            if (idField) idField.value = d.id || '';

            // Render the card BEFORE prefilling. Prefill touches dozens of
            // widgets (select2, flatpickr); if any of them throws, the card
            // must not be left showing "Looking up record..." forever.
            if (details) {
                details.innerHTML =
                    _ossFileField('File Number', d.file_number) +
                    _ossFileField('File Name', d.file_name) +
                    _ossFileField('OP Serial No', d.op_serial_number) +
                    _ossFileField('Land Use', _ossExpandLandUsePrefix(d.land_use)) +
                    _ossFileField('Purpose', d.purpose) +
                    _ossFileField('Plot No', d.plot_no) +
                    _ossFileField('Plan No', d.plan_no) +
                    _ossFileField('Location', d.location) +
                    _ossFileField('LGA', d.lga) +
                    _ossFileFieldIfValue('State', d.state) +
                    _ossFileField('TP No', d.tp_no) +
                    _ossFileField('Reg. Number', d.registration_number) +
                    _ossFileFieldIfValue('Customer Type', d.customer_type);
            }

            try {
                _ossPrefillFromPra(d);
            } catch (e) {
                console.error('OSS prefill failed:', e);
                if (details) {
                    details.innerHTML += '<div class="col-span-2 mt-1 text-amber-600">'
                        + 'Record loaded, but some fields could not be prefilled automatically.</div>';
                }
            }

            if (_ossIsChangeOfNamePage()) {
                if (d.has_occupancy_permit) {
                    _ossSetChangeOfNameEligibility(true);
                } else {
                    _ossSetChangeOfNameEligibility(false, 'This file number is not linked to any Occupancy Permit (OP) record in PRA. You cannot proceed on the Change of Name page without an OP.');
                }
            }
        })
        .catch(function () {
            if (details) details.innerHTML = '<span class="text-red-500">Network error looking up file.</span>';
            if (_ossIsChangeOfNamePage()) {
                _ossSetChangeOfNameEligibility(false, 'File lookup failed. Change of Name cannot proceed until a PRA Occupancy Permit (OP) record is confirmed.');
            }
        });
}

function _ossFileField(label, value) {
    return '<div><span class="text-slate-500">' + _ossEsc(label) + ':</span> <span class="font-semibold text-slate-700">' + _ossEsc(value || '—') + '</span></div>';
}

function _ossFileFieldIfValue(label, value) {
    var v = (value == null ? '' : String(value)).trim();
    if (!v || v === '—') return '';
    return _ossFileField(label, v);
}

/**
 * Check if file number already has an application, disable Save if duplicate.
 */
function _ossCheckFileNoDuplicate(fileNo) {
    var submitBtn = document.getElementById('ossSubmitBtn');
    var dupBanner = document.getElementById('oss_duplicate_banner');

    // Remove any previous banner
    if (dupBanner) dupBanner.remove();

    // Skip check in edit mode
    var editId = document.getElementById('oss_id').value;
    if (editId) return;

    fetch(OSS_BASE_URL + '/check-duplicate?file_no=' + encodeURIComponent(fileNo), {
        headers: { 'Accept': 'application/json' }
    })
        .then(function (res) { return res.json(); })
        .then(function (body) {
            if (body.duplicate) {
                // Disable Save button
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    submitBtn.classList.remove('hover:bg-blue-700');
                }

                // Show warning banner below file number section
                var container = document.getElementById('oss_file_indexing_card');
                if (!container) container = document.getElementById('oss_file_no_display');
                if (container && container.parentElement) {
                    var banner = document.createElement('div');
                    banner.id = 'oss_duplicate_banner';
                    banner.className = 'mt-2 p-3 rounded-lg border border-red-300 bg-red-50 text-sm';
                    banner.innerHTML = '<div class="flex items-start gap-2">' +
                        '<svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                        '<div>' +
                        '<p class="font-bold text-red-700">Duplicate File Number</p>' +
                        '<p class="text-red-600 text-xs mt-0.5">An application with file number <strong>' + _ossEsc(fileNo) + '</strong> already exists.</p>' +
                        '<p class="text-red-600 text-xs">Applicant: <strong>' + _ossEsc(body.existing_applicant || '—') + '</strong> | Type: <strong>' + _ossEsc(body.existing_type || '—') + '</strong> | Created: <strong>' + _ossEsc(body.existing_date || '—') + '</strong></p>' +
                        '<p class="text-red-500 text-[11px] mt-1 font-semibold">Save is disabled. Clear the file number to proceed with a different one.</p>' +
                        '</div></div>';
                    container.parentElement.appendChild(banner);
                }
            } else {
                // Ensure Save is enabled
                _ossEnableSubmitBtn();
            }
        })
        .catch(function () {
            // On error, don't block — keep Save enabled
            _ossEnableSubmitBtn();
        });
}

/**
 * Re-enable the Save Application button.
 */
function _ossEnableSubmitBtn() {
    if (_ossIsChangeOfNamePage()) {
        var selectedFileNo = (document.getElementById('oss_file_no')?.value || '').toString().trim();
        // On the Change of Name page the selected file must be linked to an OP.
        // Keep Save disabled if there's no PRA record, or a record with no OP —
        // this blocks other callers (e.g. the duplicate check) from re-enabling it.
        if (selectedFileNo && (!_ossLinkedPraData || !_ossLinkedPraData.has_occupancy_permit)) {
            return;
        }
    }

    var submitBtn = document.getElementById('ossSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        submitBtn.classList.add('hover:bg-blue-700');
    }
}

/**
 * Clear the file number selection.
 */
function ossClearFileNumberSelection() {
    _ossLinkedPraData = null;
    var displayEl = document.getElementById('oss_file_no_display');
    var hiddenEl = document.getElementById('oss_file_no');
    var idField = document.getElementById('oss_file_indexing_id');
    var card = document.getElementById('oss_file_indexing_card');
    var empty = document.getElementById('oss_file_indexing_empty');
    var clearBtn = document.getElementById('oss_file_no_clear_btn');

    if (displayEl) displayEl.value = '';
    if (hiddenEl) hiddenEl.value = '';
    if (idField) idField.value = '';
    _ossSetOpdFileNumber('');
    if (card) card.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    if (clearBtn) clearBtn.classList.add('hidden');

    // Remove duplicate banner and re-enable Save
    var dupBanner = document.getElementById('oss_duplicate_banner');
    if (dupBanner) dupBanner.remove();
    var transferBanner = document.getElementById('oss_transfer_requirement_banner');
    if (transferBanner) transferBanner.remove();
    _ossEnableSubmitBtn();
}

/* ═══════════════════════════════════════════════════════════════════════
   Occupancy Permit Details section

   Behaviour:
     - When the selected file already has an OP, the fields are backfilled and
       locked. An "Edit" button unlocks them so the user can override.
     - When the file has no OP, the section stays editable so the user can
       enter a new OP that will be created on save.
   The collected values ride on the payload (see _ossCollectPayload) as an
   `occupancy_permit` group carrying the PRA row id, and the backend writes
   them back onto that row (ApplicationController::syncOccupancyPermitToPra).
   ═══════════════════════════════════════════════════════════════════════ */

// Editable OPD inputs (instrument type, grantor, file number and page number
// are always kept read-only, so they are excluded here).
var _OSS_OPD_EDITABLE = [
    'oss_opd_status', 'oss_opd_op_type', 'oss_opd_op_serial_number', 'oss_opd_date',
    'oss_opd_land_use', 'oss_opd_grantee', 'oss_opd_serial_no', 'oss_opd_vol_no',
    'oss_opd_deeds_time', 'oss_opd_deeds_date'
];

// True when the OP was backfilled from an existing record (so save = update).
var _ossOpdFromRecord = false;

// Primary key of the PRA row the OP section was backfilled from (0 = none).
var _ossOpdRecordId = 0;

// Show or hide the whole Occupancy Permit Details section (header + body).
function _ossSetOpdVisible(show) {
    var section = document.getElementById('oss_opd_section');
    if (section) section.classList.toggle('hidden', !show);
    var body = document.getElementById('oss_opd_body');
    if (body) body.classList.toggle('hidden', !show);
    if (window.lucide) window.lucide.createIcons();
}

// Enable/disable the Save button (blocked when the selected file has no OP).
function _ossSetSaveDisabled(disabled) {
    var btn = document.getElementById('ossSubmitBtn');
    if (!btn) return;
    btn.disabled = !!disabled;
    btn.classList.toggle('opacity-50', !!disabled);
    btn.classList.toggle('cursor-not-allowed', !!disabled);
}

function ossSyncOpdPageNo() {
    var serial = document.getElementById('oss_opd_serial_no');
    var page = document.getElementById('oss_opd_page_no');
    if (serial && page) page.value = serial.value;
}

function _ossSetOpdFileNumber(fileNo) {
    var el = document.getElementById('oss_opd_file_number');
    if (el) el.value = fileNo || '';
}

// Lock or unlock the editable OPD inputs.
function _ossSetOpdLocked(locked) {
    _OSS_OPD_EDITABLE.forEach(function (elId) {
        var el = document.getElementById(elId);
        if (!el) return;
        el.disabled = !!locked;
        el.classList.toggle('bg-slate-100', !!locked);
        el.classList.toggle('text-slate-500', !!locked);
    });
    var btnText = document.getElementById('oss_opd_edit_btn_text');
    if (btnText) btnText.textContent = locked ? 'Edit' : 'Lock';
}

// Toggle the backfilled OP between locked and editable (the "Edit" button).
function ossToggleOpdEdit() {
    var first = document.getElementById('oss_opd_op_serial_number');
    var isLocked = first ? first.disabled : true;
    _ossSetOpdLocked(!isLocked);
}

// Treat empty / "0" / "0.0" as no value — these are placeholder junk in PRA
// and must not be backfilled as if they were real serials.
function _ossMeaningful(value) {
    var v = (value == null ? '' : value).toString().trim();
    if (v === '') return '';
    if (/^0+(\.0+)?$/.test(v)) return '';
    return v;
}

function _ossSetOpdValue(elId, value) {
    var el = document.getElementById(elId);
    if (!el) return;
    var v = value || '';
    // Date inputs are wrapped by the global flatpickr enhancer (altInput), so a
    // plain `.value =` is ignored — the picker owns the displayed/real value.
    if (el._flatpickr) {
        el._flatpickr.setDate(v, false);
    } else {
        el.value = v;
    }
}

/**
 * Match a PRA op_type onto the dropdown's options.
 * PRA stores these as "OP Resettlement" / "OP Direct Allocation"; the select
 * offers "Resettlement" / "Direct Allocation", so a literal comparison never
 * matches and the field silently stays blank.
 */
function _ossNormalizeOpType(value) {
    var v = (value || '').toString().trim().toUpperCase();
    if (!v) return '';
    if (v.indexOf('RESETTLE') !== -1) return 'Resettlement';
    if (v.indexOf('DIRECT') !== -1) return 'Direct Allocation';
    return (value || '').toString().trim();
}

/**
 * Force a value onto a select, overwriting whatever is there.
 * _ossSelectIfFound only writes into an empty select, which is right for the
 * applicant fields but wrong here: re-running the lookup for a different file
 * must replace the previous file's OP details, not keep them.
 */
function _ossForceSelect(elId, value) {
    var el = document.getElementById(elId);
    if (!el) return;
    var target = (value || '').toString().trim().toLowerCase();
    if (!target) { _ossSetSelectValue(elId, ''); return; }

    for (var i = 0; i < el.options.length; i++) {
        var opt = el.options[i];
        if (!opt) continue;
        if ((opt.value || '').toString().trim().toLowerCase() === target
            || (opt.text || '').toString().trim().toLowerCase() === target) {
            _ossSetSelectValue(elId, opt.value);
            return;
        }
    }
}

// Backfill and lock the section from a looked-up PRA record.
function _ossApplyOccupancyPermitFromPra(d) {
    if (!d) return;

    var opSerial = _ossMeaningful(d.op_serial_number || d.ic_op_serial_number || d.pra_op_serial_number);
    var editBtn = document.getElementById('oss_opd_edit_btn');
    var badge = document.getElementById('oss_opd_source_badge');

    var opRecordId = parseInt(d.op_record_id, 10) || 0;
    if (!opRecordId) {
        // Selected file has no captured OP row → hide the details. Block saving
        // only on a new application: an application that already exists must stay
        // editable, otherwise a file whose OP was never captured locks its own
        // record out of every other correction.
        _ossOpdFromRecord = false;
        _ossOpdRecordId = 0;
        _ossSetOpdVisible(false);
        _ossSetSaveDisabled(!_ossIsEditMode());
        if (editBtn) editBtn.classList.add('hidden');
        if (badge) badge.classList.add('hidden');
        return;
    }

    // Selected file has an OP → reveal the section and allow saving.
    _ossSetOpdVisible(true);
    _ossSetSaveDisabled(false);

    // The PRA row this section will write back to on save.
    _ossOpdRecordId = opRecordId;

    // Common backfill from the OP transaction.
    _ossSetOpdFileNumber((d.file_number || '').toString().trim());
    _ossSetOpdValue('oss_opd_grantee', (d.grantee || d.file_name || '').toString().trim());
    _ossForceSelect('oss_opd_land_use', _ossExpandLandUsePrefix(d.op_land_use || d.land_use));
    _ossForceSelect('oss_opd_op_type', _ossNormalizeOpType(d.op_type));
    if (_ossMeaningful(d.op_status)) _ossForceSelect('oss_opd_status', d.op_status);
    _ossSetOpdValue('oss_opd_op_serial_number', opSerial);

    // Deeds registration particulars.
    _ossSetOpdValue('oss_opd_serial_no', _ossMeaningful(d.deed_serial_no));
    _ossSetOpdValue('oss_opd_vol_no', _ossMeaningful(d.deed_vol_no));
    ossSyncOpdPageNo(); // Page No mirrors Serial No
    _ossSetOpdValue('oss_opd_deeds_date', (d.deeds_date || '').toString().trim());
    _ossSetOpdValue('oss_opd_deeds_time', (d.deeds_time || '').toString().trim());
    _ossSetOpdValue('oss_opd_date', (d.transaction_date || '').toString().trim());

    // Existing OP → lock the section and expose the Edit button.
    _ossOpdFromRecord = true;
    // A captured OP with a missing serial must remain visible and editable.
    _ossSetOpdLocked(!!opSerial);
    if (editBtn) editBtn.classList.remove('hidden');
    if (badge) badge.classList.remove('hidden');
}

function _ossResetOccupancyPermit() {
    _ossOpdFromRecord = false;
    _ossOpdRecordId = 0;

    _ossSetOpdVisible(false);
    _ossSetSaveDisabled(false);

    ['oss_opd_op_type', 'oss_opd_op_serial_number', 'oss_opd_date', 'oss_opd_file_number',
     'oss_opd_land_use', 'oss_opd_grantee', 'oss_opd_serial_no', 'oss_opd_page_no',
     'oss_opd_vol_no', 'oss_opd_deeds_time', 'oss_opd_deeds_date'].forEach(function (elId) {
        // Use the flatpickr-aware setter so wrapped date inputs actually clear.
        _ossSetOpdValue(elId, '');
    });

    var status = document.getElementById('oss_opd_status');
    if (status) status.value = 'Normal';
    var grantor = document.getElementById('oss_opd_grantor');
    if (grantor) grantor.value = 'KANO STATE GOVERNMENT';

    // Back to the default editable-but-unlocked state; hide Edit button + badge.
    _ossSetOpdLocked(false);
    var editBtn = document.getElementById('oss_opd_edit_btn');
    if (editBtn) editBtn.classList.add('hidden');
    var badge = document.getElementById('oss_opd_source_badge');
    if (badge) badge.classList.add('hidden');
}

function _ossEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function _ossSetIfEmpty(elId, value) {
    var el = document.getElementById(elId);
    if (!el) return;
    var incoming = (value || '').toString().trim();
    if (!incoming) return;
    if ((el.value || '').toString().trim() !== '') return;
    el.value = incoming;
}

function _ossSelectIfFound(elId, value) {
    var el = document.getElementById(elId);
    if (!el || !value) return;
    var target = value.toString().trim().toLowerCase();
    if (!target) return;

    for (var i = 0; i < el.options.length; i++) {
        var opt = el.options[i];
        if (!opt) continue;
        var optValue = (opt.value || '').toString().trim().toLowerCase();
        var optText = (opt.text || '').toString().trim().toLowerCase();
        if (optValue === target || optText === target) {
            if ((el.value || '').toString().trim() === '') {
                el.value = opt.value;
            }
            return;
        }
    }
}

function _ossExpandLandUsePrefix(landUse) {
    var lu = (landUse || '').toString().trim().toUpperCase();
    if (!lu) return '';
    if (lu.indexOf('RES') === 0) return 'RESIDENTIAL';
    if (lu.indexOf('COM') === 0 || lu.indexOf('CON') === 0) return 'COMMERCIAL';
    if (lu.indexOf('IND') === 0) return 'INDUSTRIAL';
    if (lu.indexOf('AGR') === 0 || lu === 'AG' || lu.indexOf('AGRIC') === 0) return 'AGRICULTURAL';
    if (lu.indexOf('MIX') === 0) return 'MIXED USE';
    return landUse;
}

function _ossLandUseToType(landUse) {
    var lu = (landUse || '').toString().trim().toUpperCase();
    if (lu.indexOf('RES') === 0) return 'residential';
    if (lu.indexOf('COM') === 0 || lu.indexOf('CON') === 0) return 'commercial';
    if (lu.indexOf('IND') === 0) return 'industrial';
    if (lu.indexOf('AGR') === 0) return 'agricultural';
    return '';
}

/**
 * True while an existing application is open for editing. The lookup that
 * ossEditRecord fires resolves AFTER the saved values have been written into
 * the form, so prefill must not treat the form as blank: PRA is the source for
 * the OP section, but the saved application wins for everything the user typed.
 */
function _ossIsEditMode() {
    var idEl = document.getElementById('oss_id');
    return !!(idEl && (idEl.value || '').toString().trim());
}

function _ossPrefillFromPra(d) {
    if (!d) return;

    var isEdit = _ossIsEditMode();
    var applicantName = (d.file_name || '').toString().trim();
    var lga = (d.lga || '').toString().trim();
    var state = (d.state || '').toString().trim() || 'Kano';
    var planNo = (d.plan_no || '').toString().trim();
    var purpose = (d.purpose || d.land_use || '').toString().trim();
    var phone = (d.phone || '').toString().trim();

    // Land use only *suggests* the tab on a new application. On edit the saved
    // application_type is authoritative — deriving it from PRA here would flip
    // the tab out from under the fields ossEditRecord has already populated.
    var detectedType = _ossLandUseToType(d.land_use);
    if (detectedType && !isEdit) {
        ossSelectType(detectedType);
    }

    // Applicant names (all tabs)
    _ossSetIfEmpty('oss_applicant_name', applicantName);
    _ossSetIfEmpty('oss_com_applicant_name', applicantName);
    _ossSetIfEmpty('oss_ind_applicant_name', applicantName);
    _ossSetIfEmpty('oss_agr_applicant_name', applicantName);

    // Main identity/contact fields
    _ossSetIfEmpty('oss_res_phone', phone);
    _ossSetIfEmpty('oss_res_nationality', 'Nigerian');
    _ossSetIfEmpty('oss_com_nationality', 'Nigerian');
    _ossSetIfEmpty('oss_ind_nationality', 'Nigerian');
    _ossSetIfEmpty('oss_agr_nationality', 'Nigerian');

    // State/LGA selects
    _ossSelectIfFound('oss_res_lga', lga);
    _ossSelectIfFound('oss_res_state_of_origin', state);
    _ossSelectIfFound('oss_com_state_of_origin', state);
    _ossSelectIfFound('oss_ind_state_of_origin', state);
    _ossSelectIfFound('oss_agr_state_of_origin', state);

    var purposeWithPlan = [purpose, planNo ? ('PLAN NO: ' + planNo) : ''].filter(Boolean).join(' | ');
    _ossSetIfEmpty('oss_com_purpose', purposeWithPlan);
    _ossSetIfEmpty('oss_ind_purpose', purposeWithPlan);
    _ossSetIfEmpty('oss_agr_purpose', purposeWithPlan);

    // TP No. + Location (OP card) — prefill from the linked record.
    // On edit these already hold the saved values (this lookup resolves after
    // ossEditRecord wrote them), so leave them alone; TP No. in particular was
    // being overwritten by PRA's value on every edit.
    if (!isEdit) {
        _ossSetTpNo((d.op_tp_no || d.tp_no || d.plan_no || planNo || '').toString().trim());
    }
    _ossSetIfEmpty('oss_lb_plot_no', (d.op_plot_no || d.plot_no || '').toString().trim());

    // District and LGA come off the OP row; the old code read a `district` key
    // the lookup never returned, and never touched the LGA select at all.
    var district = (d.op_district || d.district || '').toString().trim();
    if (district && !(document.getElementById('oss_lb_district') || {}).value) {
        _ossForceSelect('oss_lb_district', district);
    }
    var opLga = (d.op_lga || lga || '').toString().trim();
    if (opLga && !(document.getElementById('oss_lb_lga') || {}).value) {
        _ossForceSelect('oss_lb_lga', opLga);
    }

    var recLocation = (d.op_location || d.location || d.property_description || '').toString().trim();
    var locEl = document.getElementById('oss_lb_full_location');
    if (recLocation) {
        if (locEl && !locEl.value.trim()) locEl.value = recLocation.toUpperCase();
    } else if (!locEl || !locEl.value.trim()) {
        _ossBuildOpLocation();
    }

    // Backfill the Occupancy Permit Details from the record (locks it if an OP exists).
    _ossApplyOccupancyPermitFromPra(d);
}
/* ═══════════════════════════════════════════════════════════════════════
   Clear all fields in all 3 form sections
   ═══════════════════════════════════════════════════════════════════════ */
function _ossClearAllFields() {
    // Clear all mapped fields
    ['residential', 'commercial', 'industrial', 'agricultural'].forEach(function (type) {
        var map = _ossFieldMap[type] || {};
        Object.keys(map).forEach(function (elId) {
            var el = document.getElementById(elId);
            if (el) el.value = '';
        });
    });

    // Clear file number selection
    ossClearFileNumberSelection();

    // Clear residential previous allocation sub-fields
    for (var i = 1; i <= 3; i++) {
        ['oss_prev_plot_', 'oss_prev_location_', 'oss_prev_cert_'].forEach(function (prefix) {
            var el = document.getElementById(prefix + i);
            if (el) el.value = '';
        });
    }

    // Hide conditional sections
    var resPrev = document.getElementById('ossResPrevDetails');
    var comPrev = document.getElementById('ossComPrevDetails');
    var indPrev = document.getElementById('ossIndPrevDetails');
    var agrPrev = document.getElementById('ossAgrPrevDetails');
    if (resPrev) resPrev.classList.add('hidden');
    if (comPrev) comPrev.classList.add('hidden');
    if (indPrev) indPrev.classList.add('hidden');
    if (agrPrev) agrPrev.classList.add('hidden');

    // Clear address builder sub-fields
    _ossClearAddressBuilders();

    // Reset residential passport photo
    if (typeof ossResRemovePassport === 'function') ossResRemovePassport();

    // Reset TP No. + Location (OP card)
    _ossResetLocationAndTp();

    _ossResetOccupancyPermit();
}


/* ═══════════════════════════════════════════════════════════════════════
   Open Modal – Create
   ═══════════════════════════════════════════════════════════════════════ */
function ossOpenCreateModal() {
    _ossClearAllFields();
    document.getElementById('oss_id').value = '';
    document.getElementById('ossModalTitle').textContent = 'New Application';
    document.getElementById('ossModalSubtitle').textContent = 'Application for Statutory Right of Occupancy';
    document.getElementById('ossSubmitText').textContent = 'Save Application';

    ossSelectType('residential');
    document.getElementById('ossApplicationModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}


/* ═══════════════════════════════════════════════════════════════════════
   Open Modal – Edit
   ═══════════════════════════════════════════════════════════════════════ */
function ossEditRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) { alert('Could not read record data.'); return; }

    var editId = _ossEditableApplicationId(data);
    if (!editId || (!data.oss_application_id && data.workflow_record_id)) {
        Swal.fire({ icon: 'info', title: 'No OSS Form', text: 'This row is coming from PRA history. There is no OSS application form row to edit.' });
        return;
    }

    _ossClearAllFields();

    var type = data.application_type || 'residential';

    // Set edit mode
    document.getElementById('oss_id').value = editId;
    document.getElementById('ossModalTitle').textContent = 'Edit Application #' + editId;
    document.getElementById('ossModalSubtitle').textContent = 'Update the application details';
    document.getElementById('ossSubmitText').textContent = 'Update Application';

    // Select correct type & show its form
    ossSelectType(type);

    // Populate fields for the active type
    var map = _ossFieldMap[type] || {};
    Object.keys(map).forEach(function (elId) {
        var el = document.getElementById(elId);
        if (el) el.value = data[map[elId]] || '';
    });

    if (data.file_no) {
        var displayEl = document.getElementById('oss_file_no_display');
        if (displayEl) displayEl.value = data.file_no;
        // The field-map loop above fills the hidden input, but only when the
        // active type's map is the one carrying oss_file_no — set it outright.
        var hiddenFileEl = document.getElementById('oss_file_no');
        if (hiddenFileEl) hiddenFileEl.value = data.file_no;
        _ossSetOpdFileNumber(data.file_no);
        var clearBtn = document.getElementById('oss_file_no_clear_btn');
        if (clearBtn) clearBtn.classList.remove('hidden');
    }

    // Residential: populate previous allocation sub-fields
    if (type === 'residential' && data.prev_allocation_details) {
        try {
            var entries = JSON.parse(data.prev_allocation_details);
            if (Array.isArray(entries)) {
                entries.forEach(function (entry, idx) {
                    var n = idx + 1;
                    if (n > 3) return;
                    var p = document.getElementById('oss_prev_plot_' + n);
                    var l = document.getElementById('oss_prev_location_' + n);
                    var c = document.getElementById('oss_prev_cert_' + n);
                    if (p) p.value = entry.plot_no || '';
                    if (l) l.value = _ossLocationWithoutPlot(entry.location, entry.plot_no);
                    if (c) c.value = entry.cert_no || '';
                });
            }
        } catch (e) { /* non-JSON, just show raw in the first row */ }
    }

    // Show prev details sections if prev_allocated == 'Yes'
    if (data.prev_allocated === 'Yes') {
        if (type === 'residential') document.getElementById('ossResPrevDetails')?.classList.remove('hidden');
        if (type === 'commercial') document.getElementById('ossComPrevDetails')?.classList.remove('hidden');
        if (type === 'industrial') document.getElementById('ossIndPrevDetails')?.classList.remove('hidden');
        if (type === 'agricultural') document.getElementById('ossAgrPrevDetails')?.classList.remove('hidden');
    }

    // Populate address builder output fields from stored data
    // (The builder sub-fields stay empty; the stored assembled address shows in the output)
    _ossPopulateAddressOnEdit(type, data);

    // TP No. + Location (OP card) from the stored record.
    _ossSetTpNo((data.plan_no || '').toString().trim());
    var lbPlotEl = document.getElementById('oss_lb_plot_no');
    if (lbPlotEl) lbPlotEl.value = (data.plot_no || '').toString().trim();
    _ossSelectIfFound('oss_lb_district', (data.district || '').toString().trim());
    _ossSetSelectValue('oss_lb_district', (document.getElementById('oss_lb_district')?.value || ''));
    var lbLocEl = document.getElementById('oss_lb_full_location');
    if (lbLocEl) lbLocEl.value = _ossLocationWithoutPlot(data.location, data.plot_no);

    // Load existing passport photo if available
    if (data.passport_photo_url) {
        var img = document.getElementById('oss_res_passport_img');
        var placeholder = document.getElementById('oss_res_passport_placeholder');
        var removeBtn = document.getElementById('oss_res_passport_remove');
        if (img) {
            img.src = data.passport_photo_url;
            img.classList.remove('hidden');
            img.onerror = function () {
                img.classList.add('hidden');
                if (placeholder) placeholder.classList.remove('hidden');
                if (removeBtn) removeBtn.classList.add('hidden');
            };
        }
        if (placeholder) placeholder.classList.add('hidden');
        if (removeBtn) removeBtn.classList.remove('hidden');
    }

    document.getElementById('ossApplicationModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();

    // Fire the PRA lookup last, once every saved value is on the form. It is
    // what backfills the Occupancy Permit Details, and its callback must not
    // race the assignments above.
    if (data.file_no) {
        _ossFetchFileIndexing(data.file_no);
    }
}


/* ═══════════════════════════════════════════════════════════════════════
   Close Modal
   ═══════════════════════════════════════════════════════════════════════ */
function ossCloseModal() {
    document.getElementById('ossApplicationModal').classList.add('hidden');
}


/* ═══════════════════════════════════════════════════════════════════════
   Save / Update Application (AJAX)
   ═══════════════════════════════════════════════════════════════════════ */
function ossSaveApplication() {
    var id = document.getElementById('oss_id').value;
    var isEdit = !!id;
    var payload = _ossCollectPayload();

    // ── Validate required fields per application type ──
    var errors = _ossValidatePayload(payload);
    if (errors.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Required Fields',
            html: '<ul style="text-align:left;font-size:13px;line-height:1.8;list-style:disc;padding-left:20px;">' +
                errors.map(function (e) { return '<li>' + e + '</li>'; }).join('') +
                '</ul>'
        });
        return;
    }

    // ── Duplicate file number check (only for new applications with a file number) ──
    var fileNo = (payload.file_no || '').trim();
    if (!isEdit && fileNo) {
        var submitBtn = document.getElementById('ossSubmitBtn');
        var submitText = document.getElementById('ossSubmitText');
        var origText = submitText.textContent;
        submitBtn.disabled = true;
        submitText.textContent = 'Checking...';

        fetch(OSS_BASE_URL + '/check-duplicate?file_no=' + encodeURIComponent(fileNo) + '&application_type=' + encodeURIComponent(payload.application_type), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (body.duplicate) {
                    submitBtn.disabled = false;
                    submitText.textContent = origText;
                    Swal.fire({
                        icon: 'error',
                        title: 'Duplicate File Number',
                        html: '<p style="font-size:13px;">An application with file number <strong>' + _ossEsc(fileNo) + '</strong> already exists.</p>' +
                            '<div style="text-align:left;font-size:12px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-top:10px;">' +
                            '<p><strong>Applicant:</strong> ' + _ossEsc(body.existing_applicant || '—') + '</p>' +
                            '<p><strong>Type:</strong> ' + _ossEsc(body.existing_type || '—') + '</p>' +
                            '<p><strong>Created:</strong> ' + _ossEsc(body.existing_date || '—') + '</p>' +
                            '</div>'
                    });
                } else {
                    _ossDoSave(id, isEdit, payload);
                }
            })
            .catch(function () {
                // On network error, proceed with save (server will also check)
                _ossDoSave(id, isEdit, payload);
            });
        return;
    }

    _ossDoSave(id, isEdit, payload);
}

/**
 * Validate payload fields based on application type.
 * Returns an array of error messages (empty = valid).
 */
function _ossValidatePayload(payload) {
    var errors = [];
    var type = payload.application_type || 'residential';

    // OP Serial Number is mandatory for every Change of Name application. It is
    // sourced from the Occupancy Permit Details section — backfilled when the
    // record already has one, or entered by the user when it does not.
    if (_ossIsChangeOfNamePage() || payload.has_occupancy_permit) {
        if (!/^[1-9][0-9]*$/.test((payload.op_serial_number || '').trim())) {
            errors.push('A valid OP Serial Number is required. Enter positive digits without leading zeros in "Occupancy Permit Details".');
        }
    }

    // File number is required for all types
    if (!(payload.file_no || '').trim()) {
        errors.push('File Number is required. Please select a file number.');
    }

    // Applicant name is required for all types
    if (!(payload.applicant_name || '').trim()) {
        errors.push('Applicant Name is required.');
    }

    if (type === 'residential') {
        if (!(payload.residential_address || '').trim()) {
            errors.push('Residential Address is required.');
        }
        if (!(payload.sex || '').trim()) {
            errors.push('Sex is required.');
        }
        if (!(payload.occupation || '').trim()) {
            errors.push('Occupation is required.');
        }
        if (!(payload.res_addr_lga || '').trim()) {
            errors.push('Residential Address LGA is required.');
        }

        // Passport photo: required for new residential applications, or when no
        // existing photo is loaded in edit mode.
        var passportInput = document.getElementById('oss_res_passport_input');
        var passportImg = document.getElementById('oss_res_passport_img');
        var hasFile = !!(passportInput && passportInput.files && passportInput.files[0]);
        var hasExisting = !!(passportImg && !passportImg.classList.contains('hidden') && passportImg.src && passportImg.src.indexOf('data:') !== 0);
        // For edit mode, an existing passport (loaded from server as URL) counts as valid.
        // For new applications, a file must be picked.
        var idEl = document.getElementById('oss_id');
        var isEdt = !!(idEl && idEl.value);
        if (!hasFile && !(isEdt && hasExisting)) {
            errors.push('A passport photograph (JPEG/PNG, max 2 MB) is required for residential applications.');
        }
        if (hasFile && typeof ossResValidatePassportFile === 'function') {
            var chk = ossResValidatePassportFile(passportInput.files[0]);
            if (!chk.ok) {
                errors.push(chk.message);
            }
        }
    }

    if (type === 'commercial') {
        if (!(payload.nationality || '').trim()) {
            errors.push('Nationality is required.');
        }
        if (!(payload.occupation_or_business || '').trim()) {
            errors.push('Occupation or Business is required.');
        }
        if (!(payload.purpose || '').trim()) {
            errors.push('Purpose for which land is required is missing.');
        }
        if (!(payload.com_biz_lga || '').trim()) {
            errors.push('Business Location LGA is required.');
        }
    }

    if (type === 'industrial') {
        if (!(payload.nationality || '').trim()) {
            errors.push('Nationality is required.');
        }
        if (!(payload.occupation_or_business || '').trim()) {
            errors.push('Occupation or Business is required.');
        }
        if (!(payload.purpose || '').trim()) {
            errors.push('Purpose for which land is acquired is missing.');
        }
        if (!(payload.ind_biz_lga || '').trim()) {
            errors.push('Business Location LGA is required.');
        }
    }

    if (type === 'agricultural') {
        if (!(payload.nationality || '').trim()) {
            errors.push('Nationality is required.');
        }
        if (!(payload.occupation_or_business || '').trim()) {
            errors.push('Occupation or Business is required.');
        }
        if (!(payload.purpose || '').trim()) {
            errors.push('Purpose for which land is acquired is missing.');
        }
        if (!(payload.agr_biz_lga || '').trim()) {
            errors.push('Business Location LGA is required.');
        }
    }

    return errors;
}

/**
 * Actually submit the form (called after validation & duplicate check pass).
 */
function _ossDoSave(id, isEdit, payload) {
    var submitBtn = document.getElementById('ossSubmitBtn');
    var submitText = document.getElementById('ossSubmitText');
    var origText = submitText.textContent;
    submitBtn.disabled = true;
    submitText.textContent = 'Saving...';

    // Auto-stamp system_source when creating from the Change of Name page
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'change-of-name' && !payload.system_source) {
        payload.system_source = 'OSSOPCHANGEOFNAME';
    }

    var url = isEdit ? OSS_BASE_URL + '/' + id : OSS_BASE_URL;

    // Use FormData to support file upload (passport photo)
    var formData = new FormData();
    Object.keys(payload).forEach(function (key) {
        var value = payload[key];
        // Nested groups (occupancy_permit) must be flattened into key[sub]
        // entries — appending the object itself stringifies it to
        // "[object Object]" and the whole section silently never reaches PHP.
        if (value && typeof value === 'object' && !(value instanceof Blob)) {
            Object.keys(value).forEach(function (sub) {
                var subVal = value[sub];
                formData.append(key + '[' + sub + ']', subVal == null ? '' : subVal);
            });
            return;
        }
        // `|| ''` would turn a legitimate 0 (has_occupancy_permit) into a blank.
        formData.append(key, value == null ? '' : value);
    });
    if (isEdit) {
        formData.append('_method', 'PUT');
    }

    // Attach passport photo if present (residential)
    var passportInput = document.getElementById('oss_res_passport_input');
    if (passportInput && passportInput.files && passportInput.files[0]) {
        formData.append('passport_photo', passportInput.files[0]);
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': OSS_CSRF,
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(function (res) { return res.json().then(function (data) { return { status: res.status, body: data }; }); })
        .then(function (result) {
            submitBtn.disabled = false;
            submitText.textContent = origText;

            if (result.body.success) {
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? 'Updated' : 'Created',
                    text: result.body.message,
                    timer: 1800,
                    showConfirmButton: false
                });
                ossCloseModal();
                setTimeout(function () { location.reload(); }, 1000);
            } else {
                var errMsg = result.body.message || 'An error occurred.';
                if (result.body.errors) {
                    var errList = [];
                    Object.values(result.body.errors).forEach(function (arr) {
                        arr.forEach(function (e) { errList.push(e); });
                    });
                    errMsg += '\n' + errList.join('\n');
                }
                Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
            }
        })
        .catch(function (err) {
            submitBtn.disabled = false;
            submitText.textContent = origText;
            console.error('OSS save error:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
}


/* ═══════════════════════════════════════════════════════════════════════
   View Record (Detail Modal)
   ═══════════════════════════════════════════════════════════════════════ */
function ossViewRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) { alert('Could not read record data.'); return; }

    var type = data.application_type || 'residential';
    var typeBadge = '<span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase oss-badge-' + type + '">' + type.toUpperCase() + '</span>';
    var statusBadge = '<span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase oss-status-' + (data.status || 'pending') + '">' + (data.status || 'pending').toUpperCase() + '</span>';

    var html = '<div class="space-y-4">';
    html += '<div class="flex items-center gap-3 mb-4">' + typeBadge + ' ' + statusBadge + '</div>';

    // Passport photo at top of view
    if (data.passport_photo_url) {
        html += '<div class="flex justify-center mb-4">';
        html += '<img src="' + _ossEsc(data.passport_photo_url) + '" alt="Passport Photo" class="w-24 h-28 object-cover rounded-lg border-2 border-slate-200 shadow-sm" onerror="this.parentElement.style.display=\'none\'">';
        html += '</div>';
    }

    if (type === 'residential') {
        html += _vSection('Applicant Information', 'blue', [
            ['Applicant Name', data.applicant_name],
            ['Age', data.age],
            ['Sex', data.sex],
            ['Marital Status', data.marital_status],
            ['Husband Name & Address', data.husband_name_address],
            ['Residential Address', data.residential_address],
            ['Correspondence Address', data.correspondence_address],
            ['Email', data.email],
            ['Phone', data.phone],
            ['Business Address', data.business_address],
            ['Nationality', data.nationality],
            ['State of Origin', data.state_of_origin],
            ['L.G. of Origin', data.lga],
            ['Occupation', data.occupation],
            ['Annual Income', data.annual_income],
            ['Previously Allocated', data.prev_allocated]
        ]);
        if (data.prev_allocation_details) {
            try {
                var entries = JSON.parse(data.prev_allocation_details);
                if (Array.isArray(entries)) {
                    var prevHtml = '';
                    entries.forEach(function (e, i) {
                        prevHtml += '<div class="text-xs text-slate-600">(' + (i + 1) + ') Plot: ' + (e.plot_no || '—') + ' | Location: ' + (e.location || '—') + ' | C of O: ' + (e.cert_no || '—') + '</div>';
                    });
                    html += '<div class="pl-4 mb-3">' + prevHtml + '</div>';
                }
            } catch (e) {
                html += _vRow('Previous Details', data.prev_allocation_details);
            }
        }
    }

    if (type === 'commercial') {
        html += _vSection('Applicant & Nationality', 'blue', [
            ['Applicant Name', data.applicant_name],
            ['Nationality', data.nationality],
            ['State of Origin', data.state_of_origin],
            ['Home Domicile', data.home_domicile],
            ['Occupation or Business', data.occupation_or_business],
            ['Nature of Commerce', data.nature_of_commerce],
            ['Annual Income', data.annual_income]
        ]);
        html += _vSection('Company Details', 'purple', [
            ['Registered Under', data.company_registered_under],
            ['Registration Particulars', data.registration_particulars],
            ['Business Location', data.business_location],
            ['Correspondence Address', data.correspondence_address],
            ['Annual Income / Anticipation', data.annual_income_anticipation]
        ]);
        html += _vSection('Allocation & Purpose', 'amber', [
            ['Previously Allocated', data.prev_allocated],
            ['Previous Details', data.prev_allocation_details],
            ['Previous Land Purpose', data.prev_land_purpose],
            ['Purpose Applied For', data.purpose],
            ['Intended Activities', data.intended_activities]
        ]);
    }

    if (type === 'industrial') {
        html += _vSection('Applicant & Nationality', 'blue', [
            ['Name of Applicant / Company', data.applicant_name],
            ['Nationality', data.nationality],
            ['State of Origin', data.state_of_origin],
            ['Home Domicile', data.home_domicile],
            ['Occupation or Business', data.occupation_or_business],
            ['Nature of Occupation', data.nature_of_occupation]
        ]);
        html += _vSection('Registration Details', 'purple', [
            ['Registered Under', data.company_registered_under],
            ['Registration Particulars', data.registration_particulars],
            ['Business Location', data.business_location],
            ['Correspondence Address', data.correspondence_address],
            ['Annual Income / Turnover', data.annual_income_turnover],
            ['Number of Employees', data.number_of_employees]
        ]);
        html += _vSection('Allocation & Purpose', 'amber', [
            ['Previously Allocated', data.prev_allocated],
            ['Previous Details', data.prev_allocation_details],
            ['Purpose Applied For', data.purpose],
            ['Nature of Industrial', data.nature_of_industrial],
            ['Waste Disposal Requirements', data.waste_disposal_requirements]
        ]);
    }

    if (type === 'agricultural') {
        html += _vSection('Applicant & Nationality', 'blue', [
            ['Name of Applicant / Company', data.applicant_name],
            ['Nationality', data.nationality],
            ['State of Origin', data.state_of_origin],
            ['Home Domicile', data.home_domicile],
            ['Occupation or Business', data.occupation_or_business],
            ['Nature of Occupation', data.nature_of_occupation]
        ]);
        html += _vSection('Registration Details', 'purple', [
            ['Registered Under', data.company_registered_under],
            ['Registration Particulars', data.registration_particulars],
            ['Business Location', data.business_location],
            ['Correspondence Address', data.correspondence_address],
            ['Annual Income / Turnover', data.annual_income_turnover],
            ['Number of Employees', data.number_of_employees]
        ]);
        html += _vSection('Allocation & Purpose', 'emerald', [
            ['Previously Allocated', data.prev_allocated],
            ['Previous Details', data.prev_allocation_details],
            ['Purpose Applied For', data.purpose],
            ['Nature of Agricultural', data.nature_of_agricultural],
            ['Waste Disposal Requirements', data.waste_disposal_requirements]
        ]);
    }

    // Remarks & dates
    html += '<div class="border-t border-slate-100 pt-3">';
    html += _vRow('Remarks', data.remarks);
    html += _vRow('Created', data.created_at ? new Date(data.created_at).toLocaleString() : null);
    html += _vRow('Updated', data.updated_at ? new Date(data.updated_at).toLocaleString() : null);
    html += '</div></div>';

    document.getElementById('ossViewSubtitle').textContent = type.toUpperCase() + ' LAND Application #' + data.id;
    document.getElementById('ossViewBody').innerHTML = html;
    document.getElementById('ossViewModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function _vSection(title, color, rows) {
    var html = '<div class="border-b border-slate-100 pb-3">';
    html += '<p class="text-[10px] font-bold uppercase tracking-widest text-' + color + '-400 mb-2">' + title + '</p>';
    rows.forEach(function (r) { html += _vRow(r[0], r[1]); });
    html += '</div>';
    return html;
}

function _vRow(label, value) {
    if (!value) value = '—';
    return '<div class="grid grid-cols-3 gap-2 py-1"><span class="text-xs font-semibold text-slate-500">' + label + '</span><span class="col-span-2 text-sm text-slate-700">' + value + '</span></div>';
}

function ossCloseViewModal() {
    document.getElementById('ossViewModal').classList.add('hidden');
}

/* ═══════════════════════════════════════════════════════════════════════
   Change-of-Name Action Helpers
   ═══════════════════════════════════════════════════════════════════════ */
function _ossRecordFileNo(data) {
    return (data?.file_no || data?.temp_fileno || '').toString().trim();
}

function _ossWorkflowRecordId(data) {
    return data?.workflow_record_id || data?.source_instrument_capture_id || data?.id || 0;
}

function _ossEditableApplicationId(data) {
    return data?.oss_application_id || data?.id || 0;
}

/**
 * Fetch workflow prerequisites for a Change of Name record.
 * Returns a promise resolving to { verification_done, acknowledgement_done, land12_exists, land12_approved }.
 */
function _ossCheckWorkflow(data) {
    var recordId = _ossWorkflowRecordId(data);
    var fileNo = _ossRecordFileNo(data);
    var url = '/lands-one-stop-shop/applications/workflow-status?record_id=' + encodeURIComponent(recordId) + '&file_no=' + encodeURIComponent(fileNo);
    return fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (resp) { return resp.data || {}; })
        .catch(function () { return {}; });
}

/**
 * Update workflow button states (disabled/enabled) in the dropdown when it opens.
 * Called from the Alpine @click handler on the trigger button.
 */
function ossUpdateWorkflowButtons(triggerEl) {
    var tr = triggerEl.closest('tr');
    if (!tr) return;
    var data;
    try { data = JSON.parse(tr.getAttribute('data-record')); } catch (e) { return; }
    var dropdown = triggerEl.closest('div[x-data]');
    if (!dropdown) return;
    var wfBtns = dropdown.querySelectorAll('[data-wf]');
    if (!wfBtns.length) return;

    // Start with all workflow buttons disabled while loading
    wfBtns.forEach(function (btn) { btn.classList.add('wf-disabled'); });

    _ossCheckWorkflow(data).then(function (wf) {
        wfBtns.forEach(function (btn) {
            var step = btn.getAttribute('data-wf');
            var enabled = false;
            if (step === 'acknowledgement') {
                // Verification is no longer a prerequisite for Acknowledgement.
                enabled = true;
            } else if (step === 'land12') {
                // Land 12 requires Acknowledgement only (Verification bypassed).
                enabled = !!wf.acknowledgement_done;
            } else if (step === 'recommendation') {
                enabled = true;
            }
            if (enabled) {
                btn.classList.remove('wf-disabled');
            } else {
                btn.classList.add('wf-disabled');
            }
        });
    });
}

function _ossFirstFilled() {
    for (var i = 0; i < arguments.length; i++) {
        var val = (arguments[i] || '').toString().trim();
        if (val) return val;
    }
    return '';
}

function _ossFormatNaira(value) {
    var raw = (value || '').toString().trim();
    if (!raw) return '';
    if (/^to\s*follow$/i.test(raw)) return 'To Follow';

    var normalized = raw.replace(/[^0-9.-]/g, '');
    if (!normalized || isNaN(Number(normalized))) {
        return raw;
    }

    return '₦' + Number(normalized).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function _ossBindRecommendationMoneyFormatters() {
    var ids = ['rec_dev_value', 'rec_ground_rent', 'rec_dev_charge', 'rec_survey_charges'];
    ids.forEach(function (id) {
        var input = document.getElementById(id);
        if (!input || input.dataset.moneyBound === '1') return;
        input.dataset.moneyBound = '1';

        input.addEventListener('blur', function () {
            var formatted = _ossFormatNaira(input.value);
            if (formatted) input.value = formatted;
        });
    });
}

function _ossAsOpLikeRecord(data) {
    var createdAt = data?.created_at ? new Date(data.created_at) : null;
    var dateText = createdAt ? createdAt.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : '';
    var timeText = createdAt ? createdAt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '';
    return {
        id: _ossWorkflowRecordId(data),
        file_no: _ossRecordFileNo(data),
        party_1: _ossFirstFilled(data?.ic_original_holder, data?.source_party_1_name, data?.Grantor, data?.party_1) || 'KANO STATE GOVERNMENT',
        party_2: (data?.applicant_name || '').toString().trim(),
        plot_no: _ossFirstFilled(data?.plot_no, data?.ic_plot_number, data?.pra_plot_no, data?.fi_plot_no, data?.fn_plot_no, data?.plot_number),
        plan_no: _ossFirstFilled(data?.plan_no, data?.ic_plan_no, data?.pra_plan_no, data?.fi_plan_no, data?.fn_plan_no, data?.tp_no, data?.op_serial_number, data?.ic_op_serial_number),
        property_description: _ossFirstFilled(data?.location, data?.ic_location, data?.pra_location, data?.fi_location, data?.fn_location, data?.property_description, data?.district),
        land_use: (data?.application_type || '').toString().trim().toUpperCase(),
        op_serial_number: _ossFirstFilled(data?.ic_op_serial_number, data?.pra_op_serial_number, data?.op_serial_number),
        date_captured: dateText,
        time_captured: timeText,
        source_instrument_capture_id: _ossWorkflowRecordId(data)
    };
}

/* ═══════════════════════════════════════════════════════════════════════
   Change of Ownership Form (OP) – open / close / save / print
   Approved template: docs/templates/landsoss/one-stop3.html
   ═══════════════════════════════════════════════════════════════════════ */
function ossChangeOfOwnershipRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    var rec = _ossAsOpLikeRecord(data);

    // --- Prefill from the record (fallback layer) ---
    document.getElementById('coo_op_number').value = rec.op_serial_number || '';
    document.getElementById('coo_location').value = rec.property_description || '';
    document.getElementById('coo_plot_no').value = rec.plot_no || '';
    document.getElementById('coo_plan_no').value = rec.plan_no || '';
    document.getElementById('coo_record_id').value = rec.id || '';

    document.getElementById('coo_date_of_issuance').value = '';
    document.getElementById('coo_original_name').value = rec.party_1 || '';
    _ossPopulateAddressBuilder('coo_orig_addr', 'coo_original_address', '');
    document.getElementById('coo_original_phone').value = _ossFirstFilled(data.source_party_1_phone, data.oa_phone);
    document.getElementById('coo_current_name').value = rec.party_2 || '';
    var currentAddress = _ossFirstFilled(data.address, data.residential_address, data.correspondence_address, data.source_party_2_address);
    _ossPopulateAddressBuilder('coo_curr_addr', 'coo_current_address', currentAddress);
    document.getElementById('coo_current_phone').value = _ossFirstFilled(data.phone, data.source_party_2_phone);
    document.querySelectorAll('input[name="coo_ownership_method"]').forEach(function (r) { r.checked = false; });

    document.getElementById('changeOfOwnershipModal').classList.remove('hidden');
    var printBtn = document.getElementById('cooPrintBtn');
    if (printBtn) printBtn.disabled = true;
    if (window.lucide) window.lucide.createIcons();

    // --- Override with previously-saved change of ownership, if any ---
    var recordId = (rec.id || '').toString().trim();
    if (!recordId) return;
    fetch('/lands-one-stop-shop/applications/change-of-ownership-status?record_id=' + encodeURIComponent(recordId))
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || !resp.success || !resp.data || !resp.data.exists) return;
            var d = resp.data;
            if (d.op_number) document.getElementById('coo_op_number').value = d.op_number;
            if (d.location) document.getElementById('coo_location').value = _ossLocationWithoutPlot(d.location, d.plot_no);
            if (d.plot_no) document.getElementById('coo_plot_no').value = d.plot_no;
            if (d.plan_no) document.getElementById('coo_plan_no').value = d.plan_no;
            document.getElementById('coo_date_of_issuance').value = d.date_of_issuance || '';
            if (d.original_name) document.getElementById('coo_original_name').value = d.original_name;
            if (d.original_address) _ossPopulateAddressBuilder('coo_orig_addr', 'coo_original_address', d.original_address);
            document.getElementById('coo_original_phone').value = d.original_phone || document.getElementById('coo_original_phone').value;
            if (d.current_name) document.getElementById('coo_current_name').value = d.current_name;
            if (d.current_address) _ossPopulateAddressBuilder('coo_curr_addr', 'coo_current_address', d.current_address);
            document.getElementById('coo_current_phone').value = d.current_phone || document.getElementById('coo_current_phone').value;
            if (d.ownership_method) {
                document.querySelectorAll('input[name="coo_ownership_method"]').forEach(function (r) {
                    r.checked = (r.value === d.ownership_method);
                });
            }
            // A saved record exists → allow printing immediately.
            if (printBtn) printBtn.disabled = false;
        })
        .catch(function () { });
}

function closeChangeOfOwnershipModal() {
    document.getElementById('changeOfOwnershipModal').classList.add('hidden');
}

function saveChangeOfOwnership() {
    var payload = {
        record_id: document.getElementById('coo_record_id').value,
        op_number: document.getElementById('coo_op_number').value,
        location: document.getElementById('coo_location').value,
        plot_no: document.getElementById('coo_plot_no').value,
        plan_no: document.getElementById('coo_plan_no').value,
        date_of_issuance: document.getElementById('coo_date_of_issuance').value,
        original_name: document.getElementById('coo_original_name').value,
        original_address: document.getElementById('coo_original_address').value,
        original_phone: document.getElementById('coo_original_phone').value,
        current_name: document.getElementById('coo_current_name').value,
        current_address: document.getElementById('coo_current_address').value,
        current_phone: document.getElementById('coo_current_phone').value,
        ownership_method: (document.querySelector('input[name="coo_ownership_method"]:checked') || {}).value || ''
    };
    if (!payload.current_name) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the current owner name.' });
        return;
    }
    fetch('/lands-one-stop-shop/applications/save-change-of-ownership', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': OSS_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result && result.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: result.message || 'Change of ownership saved.', timer: 1800, showConfirmButton: false });
                var printBtn = document.getElementById('cooPrintBtn');
                if (printBtn) printBtn.disabled = false;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) || 'Could not save change of ownership.' });
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
}

function printChangeOfOwnership() {
    var fields = {
        file_no: document.getElementById('coo_record_id').value,
        op_number: document.getElementById('coo_op_number').value,
        location: document.getElementById('coo_location').value,
        plot_no: document.getElementById('coo_plot_no').value,
        plan_no: document.getElementById('coo_plan_no').value,
        date_of_issuance: document.getElementById('coo_date_of_issuance').value,
        original_name: document.getElementById('coo_original_name').value,
        original_address: document.getElementById('coo_original_address').value,
        original_phone: document.getElementById('coo_original_phone').value,
        current_name: document.getElementById('coo_current_name').value,
        current_address: document.getElementById('coo_current_address').value,
        current_phone: document.getElementById('coo_current_phone').value,
        ownership_method: (document.querySelector('input[name="coo_ownership_method"]:checked') || {}).value || ''
    };
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/lands-one-stop-shop/applications/print-change-of-ownership';
    form.target = '_blank';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = OSS_CSRF;
    form.appendChild(csrf);
    Object.keys(fields).forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = key; input.value = fields[key] || '';
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function ossVerificationRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    var rec = _ossAsOpLikeRecord(data);

    // --- Prefill from the record (fallback layer) ---
    document.getElementById('ver_applicant_name').value = rec.party_2 || '';
    document.getElementById('ver_original_allottee').value = rec.party_1 || '';
    document.getElementById('ver_op_no').value = rec.op_serial_number || '';
    document.getElementById('ver_plot_plan').value = (rec.plot_no || '') + ' / ' + (rec.plan_no || '');
    document.getElementById('ver_location').value = rec.property_description || '';
    document.getElementById('ver_record_id').value = rec.id || '';

    var recordAddress = _ossFirstFilled(data.address, data.residential_address, data.correspondence_address, data.source_party_2_address);
    _ossPopulateAddressBuilder('ver_addr', 'ver_address', recordAddress);
    document.getElementById('ver_phone').value = _ossFirstFilled(data.phone, data.oa_phone, data.source_party_2_phone);
    document.getElementById('ver_email').value = '';
    document.getElementById('ver_chairman_name').value = '';
    document.querySelectorAll('input[name="ver_id_type"]').forEach(function (r) { r.checked = false; });
    document.querySelectorAll('input[name="ver_recommendation"]').forEach(function (r) { r.checked = false; });
    verRemovePassport();
    if (data.passport_photo_url) {
        _ossSetVerificationPassport(data.passport_photo_url);
    }

    document.getElementById('verificationModal').classList.remove('hidden');
    var printBtn = document.getElementById('verPrintBtn');
    if (printBtn) printBtn.disabled = true;
    if (window.lucide) window.lucide.createIcons();

    // --- Override with previously-saved verification, if any ---
    var recordId = (rec.id || '').toString().trim();
    if (!recordId) return;
    fetch('/lands-one-stop-shop/applications/verification-status?record_id=' + encodeURIComponent(recordId))
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || !resp.success || !resp.data || !resp.data.exists) return;
            var d = resp.data;
            if (d.applicant_name) document.getElementById('ver_applicant_name').value = d.applicant_name;
            if (d.original_allottee) document.getElementById('ver_original_allottee').value = d.original_allottee;
            if (d.op_number) document.getElementById('ver_op_no').value = d.op_number;
            if (d.plot_no || d.plan_no) {
                document.getElementById('ver_plot_plan').value = (d.plot_no || '') + ' / ' + (d.plan_no || '');
            }
            if (d.location) document.getElementById('ver_location').value = _ossLocationWithoutPlot(d.location, d.plot_no);
            if (d.applicant_address) _ossPopulateAddressBuilder('ver_addr', 'ver_address', d.applicant_address);
            document.getElementById('ver_phone').value = d.applicant_phone || document.getElementById('ver_phone').value;
            document.getElementById('ver_email').value = d.applicant_email || '';
            document.getElementById('ver_chairman_name').value = d.chairman_name || '';
            if (d.id_type) {
                document.querySelectorAll('input[name="ver_id_type"]').forEach(function (r) {
                    r.checked = (r.value === d.id_type);
                });
            }
            if (d.recommendation) {
                document.querySelectorAll('input[name="ver_recommendation"]').forEach(function (r) {
                    r.checked = (r.value === d.recommendation);
                });
            }
            if (d.passport_photo_url) _ossSetVerificationPassport(d.passport_photo_url);
            // A saved verification exists → allow printing immediately.
            if (printBtn) printBtn.disabled = false;
        })
        .catch(function () { });
}

/** Show a passport photo in the verification modal from a URL. */
function _ossSetVerificationPassport(url) {
    var verImg = document.getElementById('ver_passport_img');
    if (!verImg) return;
    verImg.src = url;
    verImg.classList.remove('hidden');
    verImg.onerror = function () { verRemovePassport(); };
    var ph = document.getElementById('ver_passport_placeholder');
    var rm = document.getElementById('ver_passport_remove');
    if (ph) ph.classList.add('hidden');
    if (rm) rm.classList.remove('hidden');
}

/**
 * Best-effort reconstruction of an address-builder from a composed address
 * string (e.g. "992, N TERMINAL, DAWAKIN TOFA, KANO"). Matches select options
 * case-insensitively; a leading numeric token becomes the Plot Number and any
 * unmatched street/district tokens fall back to the "Other" free-text field.
 * The authoritative composed string is preserved in the output textarea.
 */
function _ossPopulateAddressBuilder(prefix, outputId, address) {
    _ossClearSingleAddressBuilder(prefix, outputId);
    var raw = (address || '').toString().trim();
    var outputEl = document.getElementById(outputId);
    if (!raw) return;
    if (outputEl) outputEl.value = raw.toUpperCase();

    var tokens = raw.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    if (!tokens.length) return;

    var houseEl = document.getElementById(prefix + '_house');
    var plotEl = document.getElementById(prefix + '_plot');
    var streetEl = document.getElementById(prefix + '_street');
    var streetOtherEl = document.getElementById(prefix + '_street_other');
    var distEl = document.getElementById(prefix + '_district');
    var distOtherEl = document.getElementById(prefix + '_district_other');
    var lgaEl = document.getElementById(prefix + '_lga');
    var stateEl = document.getElementById(prefix + '_state');
    var dashPrefix = prefix.replace(/_/g, '-');

    function matchOption(sel, val) {
        if (!sel) return false;
        var v = (val || '').trim().toLowerCase();
        if (!v) return false;
        for (var i = 0; i < sel.options.length; i++) {
            var ov = (sel.options[i].value || '').trim().toLowerCase();
            if (ov && ov !== 'other' && ov === v) { sel.selectedIndex = i; return true; }
        }
        return false;
    }

    var remaining = tokens.slice();

    // State: prefer the trailing token.
    if (stateEl && remaining.length && matchOption(stateEl, remaining[remaining.length - 1])) {
        remaining.pop();
    }
    // LGA: any remaining token that matches an LGA option.
    if (lgaEl) {
        for (var i = remaining.length - 1; i >= 0; i--) {
            if (matchOption(lgaEl, remaining[i])) { remaining.splice(i, 1); break; }
        }
    }
    // District: any remaining token that matches a district option.
    if (distEl) {
        for (var j = remaining.length - 1; j >= 0; j--) {
            if (matchOption(distEl, remaining[j])) { remaining.splice(j, 1); break; }
        }
    }
    // Plot No: only a token that says so. Addresses composed since House No
    // and Plot No became separate entries carry the plot labelled ("PLOT 45"),
    // which is the one token that can be identified with certainty. The label
    // is stripped because the builder puts it back when it recomposes.
    if (plotEl) {
        for (var k = 0; k < remaining.length; k++) {
            if (/^plot\b/i.test(remaining[k])) {
                plotEl.value = remaining[k].replace(/^plot\b[.:\s]*/i, '').trim() || remaining[k];
                remaining.splice(k, 1);
                break;
            }
        }
    }
    // House No: the first remaining token carrying a digit. In a legacy string
    // such as "992, N TERMINAL, DAWAKIN TOFA, KANO" the 992 is the number in
    // front of the street, not a plot reference — this used to land in the plot
    // field, which is precisely the conflation the split was asked to undo.
    // Nothing is lost if the guess is wrong: the composed string below stays
    // authoritative for display and for saving.
    if (houseEl) {
        for (var h = 0; h < remaining.length; h++) {
            if (/\d/.test(remaining[h])) { houseEl.value = remaining[h]; remaining.splice(h, 1); break; }
        }
    }
    // Street: whatever is left. Match an option or fall back to "Other".
    if (remaining.length && streetEl) {
        var streetVal = remaining.join(', ');
        if (!matchOption(streetEl, streetVal)) {
            var hasOther = false;
            for (var m = 0; m < streetEl.options.length; m++) {
                if ((streetEl.options[m].value || '').trim().toLowerCase() === 'other') {
                    streetEl.selectedIndex = m; hasOther = true; break;
                }
            }
            if (hasOther && streetOtherEl) {
                _ossToggleOther(streetEl, dashPrefix + '-street-other-wrapper', streetOtherEl);
                streetOtherEl.value = streetVal;
            }
        }
    }

    // Keep the original composed address authoritative for display/save.
    if (outputEl) outputEl.value = raw.toUpperCase();
}

function ossAcknowledgementRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    // Verification is no longer required before Acknowledgement.
    _ossOpenAcknowledgementModal(data);
}

function _ossOpenAcknowledgementModal(data) {
    var rec = _ossAsOpLikeRecord(data);
    document.getElementById('ack_date').value = rec.date_captured || '';
    document.getElementById('ack_time').value = rec.time_captured || '';
    document.getElementById('ack_applicant_name').value = rec.party_2 || '';
    document.getElementById('ack_plot_no').value = rec.plot_no || '';
    document.getElementById('ack_plan_no').value = rec.plan_no || '';
    document.getElementById('ack_location').value = rec.property_description || '';
    document.getElementById('ack_record_id').value = rec.id || '';
    // Store file_no for DB persistence in saveAcknowledgement
    var ackFileNoEl = document.getElementById('ack_file_no');
    if (ackFileNoEl) ackFileNoEl.value = rec.file_no || '';
    document.getElementById('ack_sig_applicant_name').value = rec.party_2 || rec.party_1 || '';
    document.getElementById('ack_sig_applicant').value = '';
    document.getElementById('ack_sig_chairman_name').value = '';
    document.getElementById('ack_sig_chairman').value = '';
    document.getElementById('acknowledgementModal').classList.remove('hidden');
    var printBtn = document.getElementById('ackPrintBtn');
    if (printBtn) printBtn.disabled = true;
    if (window.lucide) window.lucide.createIcons();
}

// ── The Recommendation Form's one print button ──────────────────────────────
//
// It is one button with two jobs, in the order the work is actually done: run
// the proof, read it, then print the document. Two buttons side by side would
// offer the official print to someone who has not proofread anything, which is
// exactly what the White Copy stage exists to prevent.
//
// The card is the same hand-off the Print Manager makes between its two
// Recommendation tiles, and it reads the same state from the same endpoint, so
// the two boards can never disagree about whether the proof has been run.
var _ossRecState = { id: '', fileRef: '', whiteCopyDone: false, printed: false, saved: false };

function _ossResetRecState(fileRef) {
    _ossRecState = { id: '', fileRef: fileRef || '', whiteCopyDone: false, printed: false, saved: false };
    _ossApplyRecButtonState();
}

// Which of the two the button is offering, and whether it may be pressed. The
// official print is offered once the proof has been run — or once the document
// has been printed before, since a reprint has nothing left to proofread.
function _ossApplyRecButtonState() {
    var btn = document.getElementById('recPrintBtn');
    if (!btn) return;

    var label = btn.querySelector('[data-rec-print-label]');
    var iconHolder = btn.querySelector('[data-rec-print-icon]');
    var official = _ossRecState.whiteCopyDone || _ossRecState.printed;

    btn.disabled = !_ossRecState.saved;
    btn.dataset.recPrintMode = official ? 'official' : 'white-copy';
    // Taken over from the markup's plain white-copy handler. The partial is shared
    // with pages that never load this file, so the button only becomes two-state
    // where something is here to drive it.
    btn.onclick = ossRecommendationPrintAction;

    if (label) {
        label.textContent = official ? 'Print Recommendation' : 'Print White Copy';
    }
    // Re-seeded rather than re-attributed: lucide swaps the <i> for an <svg> on
    // render, so there is no <i> left to change after the first pass.
    if (iconHolder) {
        var wanted = official ? 'printer' : 'file-search';
        if (iconHolder.dataset.recPrintIconName !== wanted) {
            iconHolder.dataset.recPrintIconName = wanted;
            iconHolder.innerHTML = '<i data-lucide="' + wanted + '" class="w-4 h-4"></i>';
        }
    }
    btn.title = official
        ? 'Print the official recommendation for this file'
        : 'Black & white proof of this form for vetting — not an official copy';

    // The official print is the document, so it stops looking like the quiet
    // secondary action once it is the one on offer.
    btn.classList.toggle('text-white', official);
    btn.classList.toggle('bg-blue-600', official);
    btn.classList.toggle('border-blue-600', official);
    btn.classList.toggle('hover:bg-blue-700', official);
    btn.classList.toggle('text-slate-700', !official);
    btn.classList.toggle('bg-white', !official);
    btn.classList.toggle('border-slate-300', !official);
    btn.classList.toggle('hover:bg-slate-50', !official);

    if (window.lucide) window.lucide.createIcons();
}

// Read the proof/print state for the file on the card. Called when the card
// opens and again after a proof is run, so the button is answering the print
// log rather than only what happened in this tab.
function _ossRefreshRecState(fileRef) {
    fileRef = (fileRef || _ossRecState.fileRef || '').toString().trim();
    if (!fileRef) return Promise.resolve();

    return fetch('/lands-one-stop-shop/applications/recommendation-status?file_ref=' + encodeURIComponent(fileRef), {
        headers: { 'Accept': 'application/json' }
    })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (!resp || !resp.success || !resp.data || !resp.data.exists) return null;
            // The card may have moved to another file while this was in flight.
            if (_ossRecState.fileRef !== fileRef) return null;

            _ossRecState.id = String(resp.data.id || '');
            _ossRecState.saved = true;
            _ossRecState.printed = !!resp.data.printed;
            // Only ever confirmed, never stood back down: the proof is rendered by
            // another tab's request, so a log row that has not landed yet must not
            // take the official print away from someone who just ran one.
            if (resp.data.white_copy_done) _ossRecState.whiteCopyDone = true;
            _ossApplyRecButtonState();
            return resp.data;
        })
        .catch(function () { return null; });
}

// Re-ask the log until it shows the proof, a few times, for the same reason the
// Print Manager does: the row is written by the request the other tab is still
// making.
function _ossConfirmRecWhiteCopy(attempt) {
    attempt = attempt || 0;
    if (attempt >= 4) return;
    var fileRef = _ossRecState.fileRef;
    window.setTimeout(function () {
        if (_ossRecState.fileRef !== fileRef) return;
        _ossRefreshRecState(fileRef).then(function () {
            _ossConfirmRecWhiteCopy(attempt + 1);
        });
    }, 1500 * (attempt + 1));
}

// The button itself. Which document it runs depends on the state above.
function ossRecommendationPrintAction() {
    if (_ossRecState.whiteCopyDone || _ossRecState.printed) {
        ossPrintOfficialRecommendation();
        return;
    }
    printRecommendation();
}

// The official recommendation — security serial, QR, the lot. It stands behind
// the same question the Print Manager asks, because it is the same document and
// the same paper; answering "no" opens the proof again instead.
function ossPrintOfficialRecommendation() {
    var recId = String(_ossRecState.id || '').trim();
    if (!recId) {
        Swal.fire({ icon: 'warning', title: 'Missing Recommendation', text: 'Could not resolve the saved recommendation for this file. Save it first.' });
        return;
    }

    var printUrl = '/land-recommendations/' + recId + '/print';
    var proofUrl = '/land-recommendations/' + recId + '/white-copy';

    if (!window.WhiteCopy || typeof window.WhiteCopy.confirmProofread !== 'function') {
        window.open(printUrl, '_blank');
        return;
    }

    window.WhiteCopy.confirmProofread({
        subject: _ossRecState.fileRef || 'this recommendation',
        onYes: function () { window.WhiteCopy.openTab(printUrl); },
        onWhiteCopy: function () { window.WhiteCopy.openTab(proofUrl); }
    });
}

function ossRecommendationRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    _ossOpenRecommendationModal(data);
}

function _ossOpenRecommendationModal(data) {
    var rec = _ossAsOpLikeRecord(data);
    document.getElementById('rec_applicant_name').value = rec.party_2 || rec.party_1 || '';
    document.getElementById('rec_file_ref').value = rec.file_no || '';
    document.getElementById('rec_purpose').value = rec.land_use || '';
    document.getElementById('rec_location').value = rec.property_description || '';
    document.getElementById('rec_plot_no').value = rec.plot_no || '';
    document.getElementById('rec_plan_no').value = rec.plan_no || '';
    document.getElementById('rec_record_id').value = rec.id || '';
    var appDate = (data && data.created_at ? String(data.created_at) : '').substring(0, 10);
    if (appDate && !/^\d{4}-\d{2}-\d{2}$/.test(appDate)) {
        var parsedDate = new Date(data.created_at);
        appDate = isNaN(parsedDate.getTime()) ? '' : parsedDate.toISOString().substring(0, 10);
    }
    document.getElementById('rec_application_date').value = appDate || '';
    document.getElementById('rec_applicant_address').value = _ossFirstFilled(
        data && data.address,
        data && data.residential_address,
        data && data.correspondence_address,
        data && data.source_party_2_address
    );
    document.getElementById('rec_ps_plot').value = rec.plot_no || '';
    document.getElementById('rec_ps_location').value = rec.property_description || '';
    // Default term based on land use: RES/AG = 99, COM/IND = 40
    var lu = (rec.land_use || '').toString().toUpperCase();
    var defaultTerm = '';
    if (lu.indexOf('RES') >= 0 || lu.indexOf('AGR') >= 0 || lu.indexOf('AG') >= 0) defaultTerm = '99';
    else if (lu.indexOf('COM') >= 0 || lu.indexOf('IND') >= 0) defaultTerm = '40';
    document.getElementById('rec_term').value = defaultTerm;
    document.getElementById('rec_dev_value').value = '';
    document.getElementById('rec_completion_time').value = '';
    document.getElementById('rec_ground_rent').value = '';
    document.getElementById('rec_dev_charge').value = 'To Follow';
    document.getElementById('rec_survey_charges').value = '';
    document.getElementById('rec_director_reasons').value = '';
    document.getElementById('rec_director_sign').value = '';
    document.getElementById('rec_director_date').value = '';
    document.getElementById('rec_ps_sign').value = '';
    document.getElementById('rec_ps_date').value = '';
    document.getElementById('rec_commissioner_name').value = '';
    document.getElementById('rec_commissioner_date').value = '';
    document.querySelectorAll('input[name="rec_approval_status"]').forEach(function (r) { r.checked = false; });
    document.getElementById('recommendationModal').classList.remove('hidden');
    var saveBtn = document.getElementById('recSaveBtn');
    if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.classList.remove('opacity-40', 'cursor-not-allowed');
    }
    _ossBindRecommendationMoneyFormatters();

    // Fetch saved recommendation data and prefill if exists. The same call settles
    // the print button: whether there is anything to print, and whether the proof
    // has already been run — see _ossRefreshRecState().
    var fileRef = (rec.file_no || '').toString().trim();
    _ossResetRecState(fileRef);
    if (fileRef) {
        _ossRefreshRecState(fileRef).then(function (d) {
            if (!d) return;
            if (d.term) document.getElementById('rec_term').value = d.term;
            if (d.dev_value) document.getElementById('rec_dev_value').value = _ossFormatNaira(d.dev_value);
            if (d.completion_time) document.getElementById('rec_completion_time').value = d.completion_time;
            if (d.ground_rent) document.getElementById('rec_ground_rent').value = _ossFormatNaira(d.ground_rent);
            if (d.dev_charge) document.getElementById('rec_dev_charge').value = _ossFormatNaira(d.dev_charge);
            if (d.survey_charges) document.getElementById('rec_survey_charges').value = _ossFormatNaira(d.survey_charges);
            if (d.director_reasons) document.getElementById('rec_director_reasons').value = d.director_reasons;
        });
    }
    if (window.lucide) window.lucide.createIcons();
}

function ossPrinterManagerRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    var rec = _ossAsOpLikeRecord(data);
    var ref = rec.file_no || '—';
    window.dispatchEvent(new CustomEvent('open-print-manager', {
        detail: {
            ref: ref,
            type: 'OSS OP',
            url: '',
            module: 'oss',
            record: rec,
            hideCommissioningSheet: true
        }
    }));
}

function ossLand12Record(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return;
    _ossCheckWorkflow(data).then(function (wf) {
        // Verification bypassed; Acknowledgement remains the prerequisite for Land 12.
        if (!wf.acknowledgement_done) {
            Swal.fire({ icon: 'warning', title: 'Acknowledgement Required', text: 'Please complete Acknowledgement before proceeding to Land 12.' });
            return;
        }
        var fileNo = _ossRecordFileNo(data);
        var fileTitle = (data.applicant_name || data.file_title || '').toString().trim();
        if (fileTitle === '—' || fileTitle === '-') fileTitle = '';
        var url = '/survey-report/create?url=lands';
        if (fileNo) url += '&file_no=' + encodeURIComponent(fileNo);
        if (fileTitle) url += '&file_title=' + encodeURIComponent(fileTitle);
        if (window.openTailwindModal) {
            openTailwindModal(url, 'xl');
        } else {
            window.open(url, '_blank');
        }
    });
}

function closeVerificationModal() {
    document.getElementById('verificationModal').classList.add('hidden');
}

function closeAcknowledgementModal() {
    document.getElementById('acknowledgementModal').classList.add('hidden');
}

function closeRecommendationModal() {
    document.getElementById('recommendationModal').classList.add('hidden');
}

function verPreviewPassport(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = document.getElementById('ver_passport_img');
            img.src = e.target.result;
            img.classList.remove('hidden');
            document.getElementById('ver_passport_placeholder').classList.add('hidden');
            document.getElementById('ver_passport_remove').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function verRemovePassport() {
    var input = document.getElementById('ver_passport_input');
    if (input) input.value = '';
    var img = document.getElementById('ver_passport_img');
    if (img) { img.src = ''; img.classList.add('hidden'); }
    var ph = document.getElementById('ver_passport_placeholder');
    if (ph) ph.classList.remove('hidden');
    var rm = document.getElementById('ver_passport_remove');
    if (rm) rm.classList.add('hidden');
}

function saveAcknowledgement() {
    var payload = {
        record_id: document.getElementById('ack_record_id').value,
        sig_applicant_name: document.getElementById('ack_sig_applicant_name').value
    };
    if (!payload.sig_applicant_name) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the Name of the Applicant for signature.' });
        return;
    }
    if (payload.record_id) localStorage.setItem('oss_ack_generated_' + payload.record_id, '1');

    // Persist to database for workflow gating
    fetch('/lands-one-stop-shop/applications/save-acknowledgement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': OSS_CSRF },
        body: JSON.stringify({
            record_id: payload.record_id,
            file_no: document.getElementById('ack_file_no')?.value || '',
            signature_name: payload.sig_applicant_name
        })
    }).catch(function () { });

    Swal.fire({ icon: 'success', title: 'Saved', text: 'Acknowledgement data captured successfully.', timer: 1800, showConfirmButton: false });
    var printBtn = document.getElementById('ackPrintBtn');
    if (printBtn) printBtn.disabled = false;
}

function printAcknowledgement() {
    var recordId = document.getElementById('ack_record_id').value;
    if (!recordId) return;
    window.open('/lands-one-stop-shop/applications/' + recordId + '/print-acknowledgement', '_blank');
}

function saveRecommendation() {
    _ossBindRecommendationMoneyFormatters();

    var devValue = _ossFormatNaira(document.getElementById('rec_dev_value').value);
    var groundRent = _ossFormatNaira(document.getElementById('rec_ground_rent').value);
    var devCharge = _ossFormatNaira(document.getElementById('rec_dev_charge').value);
    var surveyCharges = _ossFormatNaira(document.getElementById('rec_survey_charges').value);

    if (devValue) document.getElementById('rec_dev_value').value = devValue;
    if (groundRent) document.getElementById('rec_ground_rent').value = groundRent;
    if (devCharge) document.getElementById('rec_dev_charge').value = devCharge;
    if (surveyCharges) document.getElementById('rec_survey_charges').value = surveyCharges;

    var payload = {
        record_id: document.getElementById('rec_record_id').value,
        applicant_name: document.getElementById('rec_applicant_name').value,
        file_ref: document.getElementById('rec_file_ref').value,
        purpose: document.getElementById('rec_purpose').value,
        location: document.getElementById('rec_location').value,
        plot_no: document.getElementById('rec_plot_no').value,
        plan_no: document.getElementById('rec_plan_no').value,
        term: document.getElementById('rec_term').value,
        dev_value: document.getElementById('rec_dev_value').value,
        completion_time: document.getElementById('rec_completion_time').value,
        ground_rent: document.getElementById('rec_ground_rent').value,
        dev_charge: document.getElementById('rec_dev_charge').value,
        survey_charges: document.getElementById('rec_survey_charges').value,
        director_reasons: document.getElementById('rec_director_reasons').value,
        application_date: document.getElementById('rec_application_date').value,
        applicant_address: document.getElementById('rec_applicant_address').value
    };

    fetch('/lands-one-stop-shop/applications/save-recommendation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': OSS_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result && result.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: result.message || 'Recommendation saved successfully.', timer: 1800, showConfirmButton: false });
                // There is something to print now. Which of the two the button
                // offers is still the log's call, so take the id from the save and
                // let the state settle the rest.
                _ossRecState.saved = true;
                if (result.data && result.data.id) _ossRecState.id = String(result.data.id);
                _ossApplyRecButtonState();
                _ossRefreshRecState();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) || 'Could not save recommendation.' });
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
}

// The Recommendation Form's "Print White Copy" button. It posts the card's live
// fields and the server logs the proof — the same proof the Print Manager's own
// White Copy tile runs — so it tells the board, which opens the official
// Recommendation tile without waiting to be reopened.
function printRecommendation() {
    var fields = {
        applicant_name: document.getElementById('rec_applicant_name').value,
        file_ref: document.getElementById('rec_file_ref').value,
        purpose: document.getElementById('rec_purpose').value,
        location: document.getElementById('rec_location').value,
        plot_no: document.getElementById('rec_plot_no').value,
        plan_no: document.getElementById('rec_plan_no').value,
        term: document.getElementById('rec_term').value,
        dev_value: document.getElementById('rec_dev_value').value,
        completion_time: document.getElementById('rec_completion_time').value,
        ground_rent: document.getElementById('rec_ground_rent').value,
        dev_charge: document.getElementById('rec_dev_charge').value,
        survey_charges: document.getElementById('rec_survey_charges').value,
        director_reasons: document.getElementById('rec_director_reasons').value,
        ps_plot: document.getElementById('rec_ps_plot').value,
        ps_location: document.getElementById('rec_ps_location').value
    };
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/lands-one-stop-shop/applications/print-recommendation';
    form.target = '_blank';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = OSS_CSRF;
    form.appendChild(csrf);
    Object.keys(fields).forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = key; input.value = fields[key] || '';
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    window.dispatchEvent(new CustomEvent('white-copy-generated', {
        detail: { fileRef: fields.file_ref || '' }
    }));

    // The proof has been run, so this button becomes the official print — the next
    // step in the same piece of work, on the card the officer is already looking
    // at. Flipped now and confirmed against the print log a moment later.
    _ossRecState.whiteCopyDone = true;
    _ossApplyRecButtonState();
    _ossConfirmRecWhiteCopy();
}

function saveVerification() {
    var applicantName = document.getElementById('ver_applicant_name').value;
    if (!applicantName) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Applicant name is required.' });
        return;
    }
    var formData = new FormData();
    formData.append('record_id', document.getElementById('ver_record_id').value);
    formData.append('applicant_name', applicantName);
    formData.append('address', document.getElementById('ver_address').value);
    formData.append('phone', document.getElementById('ver_phone').value);
    formData.append('email', document.getElementById('ver_email').value);
    formData.append('id_type', (document.querySelector('input[name="ver_id_type"]:checked') || {}).value || '');
    formData.append('original_allottee', document.getElementById('ver_original_allottee').value);
    formData.append('op_number', document.getElementById('ver_op_no').value);
    formData.append('plot_plan', document.getElementById('ver_plot_plan').value);
    formData.append('location', document.getElementById('ver_location').value);
    formData.append('recommendation', (document.querySelector('input[name="ver_recommendation"]:checked') || {}).value || '');
    formData.append('chairman_name', document.getElementById('ver_chairman_name').value);
    var passportInput = document.getElementById('ver_passport_input');
    if (passportInput && passportInput.files && passportInput.files[0]) {
        formData.append('passport_photo', passportInput.files[0]);
    }
    fetch('/lands-one-stop-shop/applications/save-verification', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': OSS_CSRF, 'Accept': 'application/json' },
        body: formData
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result && result.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: result.message || 'Verification data saved.', timer: 1800, showConfirmButton: false });
                var printBtn = document.getElementById('verPrintBtn');
                if (printBtn) printBtn.disabled = false;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) || 'Could not save verification.' });
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
}

function printVerification() {
    var fields = {
        applicant_name: document.getElementById('ver_applicant_name').value,
        applicant_address: document.getElementById('ver_address').value,
        applicant_phone: document.getElementById('ver_phone').value,
        applicant_email: document.getElementById('ver_email').value,
        id_type: (document.querySelector('input[name="ver_id_type"]:checked') || {}).value || '',
        original_allottee: document.getElementById('ver_original_allottee').value,
        op_number: document.getElementById('ver_op_no').value,
        plot_no: document.getElementById('ver_plot_plan').value.split('/')[0]?.trim() || '',
        plan_no: document.getElementById('ver_plot_plan').value.split('/')[1]?.trim() || '',
        location: document.getElementById('ver_location').value,
        recommendation: (document.querySelector('input[name="ver_recommendation"]:checked') || {}).value || '',
        chairman_name: document.getElementById('ver_chairman_name').value,
        record_id: document.getElementById('ver_record_id').value
    };
    // Print whatever passport is currently shown in the modal (uploaded preview or saved image),
    // so it works even if the record hasn't been (re)saved yet.
    var _verImg = document.getElementById('ver_passport_img');
    if (_verImg && !_verImg.classList.contains('hidden') && _verImg.src) {
        fields.passport_photo_url = _verImg.src;
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/lands-one-stop-shop/applications/print-verification';
    form.target = '_blank';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = OSS_CSRF;
    form.appendChild(csrf);
    Object.keys(fields).forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = key; input.value = fields[key] || '';
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}


/* ═══════════════════════════════════════════════════════════════════════
   Delete Record
   ═══════════════════════════════════════════════════════════════════════ */
function ossDeleteRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) { alert('Could not read record data.'); return; }

    var deleteId = _ossEditableApplicationId(data);
    if (!deleteId || (!data.oss_application_id && data.workflow_record_id)) {
        Swal.fire({ icon: 'info', title: 'No OSS Form', text: 'This row is coming from PRA history. There is no OSS application form row to delete.' });
        return;
    }

    Swal.fire({
        title: 'Delete Application?',
        text: 'Are you sure you want to delete application #' + deleteId + ' for "' + (data.applicant_name || 'Unknown') + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        fetch(OSS_BASE_URL + '/' + deleteId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': OSS_CSRF,
                'Accept': 'application/json'
            }
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (body.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: body.message, timer: 1500, showConfirmButton: false });
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: body.message || 'Could not delete.' });
                }
            })
            .catch(function (err) {
                console.error('OSS delete error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            });
    });
}


/* ═══════════════════════════════════════════════════════════════════════
   BILL – Open / Close / Save
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Bill rates per application type.
 * Keys: Change_of_Name, Processing_Fee, survey_fee (Survey Deposit), Application_Form
 */
var _ossBillRates = {
    residential: { Change_of_Name: 25000, Processing_Fee: 3000, survey_fee: 10000, Application_Form: 2000 },
    commercial: { Change_of_Name: 25000, Processing_Fee: 20000, survey_fee: 20000, Application_Form: 10000 },
    industrial: { Change_of_Name: 25000, Processing_Fee: 30000, survey_fee: 30000, Application_Form: 20000 },
    agricultural: { Change_of_Name: 25000, Processing_Fee: 3000, survey_fee: 10000, Application_Form: 2000 }
};

function _ossFormatMoney(n) {
    return '₦' + Number(n).toLocaleString('en-NG');
}

/** Title-case a name: "GREGG ANDERSON" → "Gregg Anderson" */
function _ossTitleCase(str) {
    if (!str || str === '—') return str;
    return str.replace(/\S+/g, function (w) {
        return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
    });
}

/** Fill bill amounts for a given type */
function _ossFillBillAmounts(type) {
    var rates = _ossBillRates[type] || _ossBillRates['residential'];
    document.getElementById('ossBill_application_type').value = type;
    document.getElementById('ossBill_change_of_name').value = Number(rates.Change_of_Name).toLocaleString('en-NG');
    document.getElementById('ossBill_processing_fee').value = Number(rates.Processing_Fee).toLocaleString('en-NG');
    document.getElementById('ossBill_survey_deposit').value = Number(rates.survey_fee).toLocaleString('en-NG');
    document.getElementById('ossBill_application_form').value = Number(rates.Application_Form).toLocaleString('en-NG');
    var total = rates.Change_of_Name + rates.Processing_Fee + rates.survey_fee + rates.Application_Form;
    document.getElementById('ossBill_total').textContent = _ossFormatMoney(total);
}

/** Called when the land_use dropdown changes (shared modal) */
function ossBillLandUseChanged(val) {
    if (!val) return;
    _ossFillBillAmounts(val);
    var sub = document.getElementById('ossBillSubtitle');
    if (sub) sub.textContent = sub.textContent.replace(/\([^)]*\)$/, '(' + val.charAt(0).toUpperCase() + val.slice(1) + ')');
}

/**
 * Open bill modal for a record, auto-fill amounts by type.
 */
function ossBillRecord(btn) {
    var data = _ossGetRecord(btn);
    if (!data) return Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load record data.' });

    var type = (data.application_type || 'residential').toLowerCase();

    document.getElementById('ossBill_application_id').value = data.id;

    // Hide land_use dropdown (applications page always has type)
    var dropdownRow = document.getElementById('ossBill_landuse_row');
    if (dropdownRow) dropdownRow.classList.add('hidden');

    _ossFillBillAmounts(type);

    var subtitle = '#' + data.id + ' — ' + _ossTitleCase(data.applicant_name || '') + ' (' + type.charAt(0).toUpperCase() + type.slice(1) + ')';
    document.getElementById('ossBillSubtitle').textContent = subtitle;

    // Type-based header colour
    var headerColours = {
        residential: 'from-green-600 to-green-700',
        commercial: 'from-blue-600 to-blue-700',
        industrial: 'from-red-600 to-red-700',
        agricultural: 'from-emerald-600 to-emerald-700'
    };
    var hdr = document.getElementById('ossBillModalHeader');
    if (hdr) {
        hdr.className = 'flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r ' + (headerColours[type] || headerColours['residential']);
    }

    // Resolve bill status and set button mode
    var billBtn = document.getElementById('ossBillSaveBtn');
    if (billBtn) {
        billBtn.dataset.mode = 'save';
        billBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill';
    }

    fetch(OSS_BASE_URL + '/' + data.id + '/bill-status', {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (!billBtn) return;
            if (result && result.success && result.data && result.data.exists) {
                billBtn.dataset.mode = 'print';
                billBtn.innerHTML = '<i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Print Bill';
            } else {
                billBtn.dataset.mode = 'save';
                billBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill';
            }
            if (window.lucide) window.lucide.createIcons();
        })
        .catch(function () {
            // Keep default save mode if status lookup fails
        });

    document.getElementById('ossBillModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function ossCloseBillModal() {
    document.getElementById('ossBillModal').classList.add('hidden');
}

/**
 * Save the bill via AJAX POST.
 */
function ossSaveBill() {
    var appId = document.getElementById('ossBill_application_id').value;
    if (!appId) return;

    var saveBtn = document.getElementById('ossBillSaveBtn');
    var mode = (saveBtn && saveBtn.dataset && saveBtn.dataset.mode) ? saveBtn.dataset.mode : 'save';

    // Existing bill mode: print directly
    if (mode === 'print') {
        ossCloseBillModal();
        window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
        return;
    }

    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

    fetch(OSS_BASE_URL + '/' + appId + '/bill', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': OSS_CSRF,
            'Accept': 'application/json'
        },
        body: JSON.stringify({})
    })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (result) {
            if (saveBtn) { saveBtn.disabled = false; }

            if (result.body.success) {
                if (saveBtn) {
                    saveBtn.dataset.mode = 'print';
                    saveBtn.innerHTML = '<i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Print Bill';
                }
                ossCloseBillModal();
                Swal.fire({ icon: 'success', title: 'Bill Ready', text: result.body.message, timer: 1200, showConfirmButton: false });
                window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
            } else {
                var msg = (result.body && result.body.message) ? String(result.body.message) : '';
                if (msg.toLowerCase().indexOf('already been generated') !== -1) {
                    if (saveBtn) {
                        saveBtn.dataset.mode = 'print';
                        saveBtn.innerHTML = '<i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Print Bill';
                    }
                    ossCloseBillModal();
                    window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
                } else {
                    if (saveBtn) {
                        saveBtn.dataset.mode = 'save';
                        saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill';
                    }
                    Swal.fire({ icon: 'error', title: 'Error', text: result.body.message || 'Could not generate bill.' });
                }
            }

            if (window.lucide) window.lucide.createIcons();
        })
        .catch(function (err) {
            console.error('OSS bill error:', err);
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill'; saveBtn.dataset.mode = 'save'; }
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
}
