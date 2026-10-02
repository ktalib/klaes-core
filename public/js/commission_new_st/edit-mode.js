/**
 * ST Commissioning — Edit Mode
 * ---------------------------------------------------------------------------
 * Loaded only when the page is opened as /commission-new-st?edit={id}, from the
 * View/Edit card on the ST Commissioning table.
 *
 * The commissioning form is reused verbatim. This module:
 *   1. prefills it from the record already commissioned (window.ST_EDIT_RECORD),
 *   2. locks everything that decides a file number — the NPFN/unit numbers, land
 *      use, allocation type, the applied/parent file picker — because those are
 *      already issued and referenced across the registry,
 *   3. leaves applicant details, gender and location editable, and
 *   4. repoints the Generate button at the update endpoint.
 *
 * The three tabs use different id conventions: Primary has bare ids
 * (primary_first_name, propertyHouseNo), while SuA/PuA are prefixed
 * (sua_first_name, suaPropertyHouseNo) — see partials/location-details-map.
 */
(function () {
    'use strict';

    var record = window.ST_EDIT_RECORD;
    if (!record) return;

    // This script loads after the form's own modules, so the numbering helpers can
    // be neutralised before their DOMContentLoaded handlers fire. Left live they
    // would fetch a *next available* number and overwrite the issued one on screen,
    // and the commission endpoints would mint a second record.
    ['updateNPFNDisplay', 'updateSerialNumber', 'commissionFileNumber',
     'generateSuaFileNumbers', 'commissionPuaFileNumber',
     // The SuA/PuA tabs preview (and clear) their numbers from the land-use
     // selector. Selecting the record's land use below would otherwise blank the
     // issued numbers via clearSuaFileNumbers() or replace them with a preview.
     'showSuaFileNumberPreview', 'clearSuaFileNumbers',
     'showPuaFileNumberPreview', 'clearPuaFileNumbers'].forEach(function (fn) {
        if (typeof window[fn] === 'function') {
            window[fn] = function () {
                console.warn('[ST edit mode] ' + fn + '() suppressed — file numbers are locked.');
            };
        }
    });

    var TYPE = String(record.file_no_type || 'PRIMARY').toUpperCase();
    var IS_PRIMARY = TYPE === 'PRIMARY';
    var PREFIX = IS_PRIMARY ? 'primary' : TYPE.toLowerCase(); // primary | sua | pua

    // The number this record is registered under: np_fileno for a PRIMARY (whose
    // `fileno` column holds the applied/mother file), fileno for a SuA / PuA unit.
    var OWN_FILE_NO = record.registry_file_number || record.np_fileno;

    /* ---------------------------------------------------------------- utils */

    function $(id) { return document.getElementById(id); }

    function setValue(id, value) {
        var el = $(id);
        if (!el) return null;
        el.value = (value === null || value === undefined) ? '' : String(value);
        return el;
    }

    /** Set a Select2-backed reference dropdown (district / street). */
    function setReference(id, value) {
        if (!value) return;
        if (typeof window.setReferenceSelectValue === 'function') {
            window.setReferenceSelectValue(id, value, true);
            return;
        }
        var el = $(id);
        if (!el) return;
        if (!Array.prototype.some.call(el.options, function (o) { return o.value === value; })) {
            el.appendChild(new Option(value, value, true, true));
        }
        el.value = value;
    }

    /** Grey out and disable a control so it reads as locked. */
    function lock(el, title) {
        if (!el) return;
        el.disabled = true;
        el.readOnly = true;
        el.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        el.setAttribute('title', title || 'Locked in edit mode — file numbers cannot be changed');
        if (el.tagName === 'SELECT' && typeof window.setReferenceSelectDisabled === 'function') {
            window.setReferenceSelectDisabled(el, true);
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            try { window.jQuery(el).prop('disabled', true).trigger('change.select2'); } catch (e) { /* not a select2 */ }
        }
    }

    function lockAll(selector, root) {
        (root || document).querySelectorAll(selector).forEach(function (el) { lock(el); });
    }

    /* ------------------------------------------------------- prefill: values */

    var applicantType = String(record.applicant_type || 'Individual');
    var applicantTypeKey = applicantType.toLowerCase();

    function prefillApplicant() {
        // Applicant type radios: Primary uses name="applicant_type", SuA/PuA use
        // name="{prefix}_applicant_type".
        var radioName = IS_PRIMARY ? 'applicant_type' : PREFIX + '_applicant_type';
        var radio = document.querySelector(
            'input[name="' + radioName + '"][value="' + applicantType + '"]'
        );
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }

        setValue(PREFIX + '_first_name', record.first_name);
        setValue(PREFIX + '_middle_name', record.middle_name);
        setValue(PREFIX + '_last_name', record.surname);
        setValue(PREFIX + '_corporate_name', record.corporate_name);
        setValue(PREFIX + '_rc_number', record.rc_number);
        setValue(PREFIX + '_title', record.applicant_title);

        // "Multiple" keeps its primary owner in a separate block.
        if (applicantTypeKey === 'multiple') {
            setValue(PREFIX + '_owner_first_name', record.first_name);
            setValue(PREFIX + '_owner_middle_name', record.middle_name);
            setValue(PREFIX + '_owner_last_name', record.surname);
            setValue(PREFIX + '_owner_title', record.applicant_title);
        }

        // Gender exists on the Primary form only.
        setValue('primary_gender', record.gender);
    }

    /** Location field ids: bare on Primary, prefixed on SuA/PuA. */
    function locId(bare, prefixed) {
        return IS_PRIMARY ? bare : PREFIX + prefixed;
    }

    /**
     * Allocation Information (SuA only). Answered at commissioning and read-only
     * on the Standalone Unit Application form, so this edit screen is the one
     * place it can be corrected after the fact.
     */
    /**
     * The institution a file commissioned before the lookup lists existed belongs
     * under. The JS twin of AllocationSourceResolver::resolve()'s legacy branch: a
     * council allocation is LOCAL GOVERNMENT, and the three state entities were
     * stored under short codes.
     */
    function legacyInstitutionName() {
        if (record.allocation_source === 'Local Government') return 'LOCAL GOVERNMENT';

        var LEGACY_ENTITIES = {
            KUNPDA: 'KANO URBAN PLANNING DEVELOPMENT AGENCY',
            KAMMA: 'KANO URBAN PLANNING DEVELOPMENT AGENCY',
            HOUSING: 'KANO STATE MINISTRY OF HOUSING',
            KSIP: 'KSIP'
        };

        var entity = (record.allocation_entity || '').trim();

        return LEGACY_ENTITIES[entity.toUpperCase()] || entity;
    }

    function prefillSuaAllocation() {
        if (PREFIX !== 'sua') return;

        // Handed over before the source is set: choosing the institution rebuilds
        // the council list, and that is where this value lands.
        var entity = $('sua_allocation_entity');
        if (entity && record.allocation_entity) {
            entity.dataset.pendingValue = record.allocation_entity;
        }

        var source = $('sua_allocation_source');
        if (source) {
            var institution = record.institution_name || legacyInstitutionName() || '';

            source.value = institution;

            // The lists arrive over the wire, so the option may not exist yet -
            // setupSuaAllocationDropdowns() re-applies this once they do.
            if (!source.value && institution) {
                source.dataset.pendingValue = institution;
            }

            // Reveals the council half, or the "specify" box.
            source.dispatchEvent(new Event('change', { bubbles: true }));
        }

        setValue('sua_allocation_ref_no', record.allocation_ref_no);
        setValue('sua_allocation_reference_no', record.allocation_reference_no);
    }

    function prefillLocation() {
        setValue(locId('propertyHouseNo', 'PropertyHouseNo'), record.property_house_no);
        setValue(locId('propertyPlotNo', 'PropertyPlotNo'), record.property_plot_no);
        setReference(locId('propertyStreetName', 'PropertyStreetName'), record.property_street_name);
        setReference(locId('propertyDistrict', 'PropertyDistrict'), record.property_district);

        var stateId = locId('propertyState', 'PropertyState');
        var lgaId = locId('propertyLga', 'PropertyLga');

        // The LGA list is filled from the chosen state, so the state has to be set
        // (and its handler run) before the LGA value will stick.
        applyStateAndLga(stateId, lgaId, record.property_state, record.property_lga, 12);

        var lat = parseFloat(record.latitude);
        var lng = parseFloat(record.longitude);
        if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
            if (IS_PRIMARY && typeof window.setPropertyPin === 'function') {
                window.setPropertyPin(lat, lng, true, 'Saved on this record');
            } else if (window.STLocationMaps && window.STLocationMaps[PREFIX]
                       && typeof window.STLocationMaps[PREFIX].setPin === 'function') {
                window.STLocationMaps[PREFIX].setPin(lat, lng, true, 'Saved on this record');
            }
        }
    }

    /**
     * Select the state, let its change handler repopulate the LGA list, then
     * select the LGA. The states/LGA reference data loads asynchronously, so this
     * retries until it is available.
     */
    function applyStateAndLga(stateId, lgaId, stateName, lgaName, attemptsLeft) {
        if (!stateName && !lgaName) return;

        var stateSelect = $(stateId);
        var lgaSelect = $(lgaId);
        if (!stateSelect || !lgaSelect) return;

        var stateReady = stateSelect.options.length > 1;
        if (!stateReady && attemptsLeft > 0) {
            setTimeout(function () {
                applyStateAndLga(stateId, lgaId, stateName, lgaName, attemptsLeft - 1);
            }, 300);
            return;
        }

        // No stored state (Primary never persisted one): resolve it from the LGA.
        if (!stateName && lgaName && typeof nigerianStatesData !== 'undefined') {
            var matched = nigerianStatesData.find(function (s) {
                return (s.local_governments || []).some(function (l) {
                    return l.toLowerCase() === String(lgaName).toLowerCase();
                });
            });
            if (matched) stateName = matched.name;
        }

        if (stateName) {
            stateSelect.value = stateName;
            stateSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (IS_PRIMARY && typeof window.selectPropertyLGA === 'function') {
                window.selectPropertyLGA(stateSelect);
            }
        }

        if (lgaName) {
            var option = Array.prototype.find.call(lgaSelect.options, function (o) {
                return o.value.toLowerCase() === String(lgaName).toLowerCase();
            });
            if (option) {
                lgaSelect.value = option.value;
            } else if (attemptsLeft > 0) {
                // LGA list not repopulated yet.
                setTimeout(function () {
                    applyStateAndLga(stateId, lgaId, stateName, lgaName, attemptsLeft - 1);
                }, 300);
                return;
            }
        }

        updateAddressPreview();
    }

    function updateAddressPreview() {
        if (IS_PRIMARY && typeof window.updatePropertyAddressDisplay === 'function') {
            window.updatePropertyAddressDisplay();
        } else if (window.STLocationMaps && window.STLocationMaps[PREFIX]
                   && typeof window.STLocationMaps[PREFIX].updateAddress === 'function') {
            window.STLocationMaps[PREFIX].updateAddress();
        }
    }

    /** Location Details defaults to read-only; edit mode opens it. */
    function unlockLocationSection() {
        var fields = document.querySelectorAll(
            IS_PRIMARY ? '.location-detail-field' : '.' + PREFIX + '-location-field'
        );
        if (!fields.length || !fields[0].disabled) return;

        if (IS_PRIMARY && typeof window.toggleLocationDetailsEdit === 'function') {
            window.toggleLocationDetailsEdit();
        } else if (window.STLocationMaps && window.STLocationMaps[PREFIX]
                   && typeof window.STLocationMaps[PREFIX].toggleEdit === 'function') {
            window.STLocationMaps[PREFIX].toggleEdit();
        }
    }

    /* ----------------------------------------------------- lock file numbers */

    function lockFileNumberControls() {
        // The issued numbers themselves — every tab's, since all three are rendered.
        [
            'np-fileno-display', 'conversion-fileno-display',
            'sua_primary_fileno', 'sua_fileno', 'mls_fileno',
            'pua_np_fileno', 'pua_unit_fileno'
        ].forEach(function (id) {
            var el = $(id);
            if (el) lock(el, 'Issued file number — cannot be changed');
        });

        // Only the record's own tab is filled; the other tabs keep their previews.
        if (IS_PRIMARY) {
            // A PRIMARY's own number is np_fileno; `fileno` holds the applied /
            // mother file it was raised on, which belongs in the picker below.
            setValue('np-fileno-display', record.np_fileno);
            if (record.mls_fileno && String(record.mls_fileno).indexOf('CON-') === 0) {
                setValue('conversion-fileno-display', record.mls_fileno);
            }
        } else if (PREFIX === 'sua') {
            setValue('sua_primary_fileno', record.np_fileno);
            setValue('sua_fileno', record.fileno);
            setValue('mls_fileno', record.mls_fileno);
        } else {
            setValue('pua_np_fileno', record.np_fileno);
            setValue('pua_unit_fileno', record.fileno);
        }

        // Tracking ID belongs to the issued number.
        setValue('primary-tracking-id', record.tra);
        var trackingDisplay = $('primary-tracking-display');
        if (trackingDisplay && record.tra) {
            trackingDisplay.textContent = record.tra;
            trackingDisplay.classList.remove('text-red-600');
            trackingDisplay.classList.add('text-green-600');
        }

        // Land use, allocation type and conversion mode all feed the numbering.
        lockAll('input[name="selectedLandUse"], input[name="sua_selectedLandUse"], input[name="pua_land_use"]');
        lockAll('input[name="application_type"], input[name="conversion_mode"]');
        document.querySelectorAll('.primary-allocation-tab').forEach(function (tab) {
            tab.disabled = true;
            tab.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
        });

        // The file this commissioning was raised on.
        var applied = $('applied-file-number');
        if (applied) {
            if (record.applied_file_number
                && !Array.prototype.some.call(applied.options, function (o) {
                    return o.value === record.applied_file_number;
                })) {
                applied.appendChild(new Option(record.applied_file_number, record.applied_file_number, true, true));
            }
            if (record.applied_file_number) applied.value = record.applied_file_number;
            lock(applied, 'The file this record was commissioned on — cannot be changed');
        }

        var parentPicker = $('pua_parent_file_number');
        if (parentPicker) lock(parentPicker, 'Parent file — cannot be changed');

        reflectLandUse();
    }

    /**
     * Tick the record's land use on its own tab's (now locked) selector.
     *
     * Each tab uses a different input name, and the SuA tab labels Industrial as
     * INDUSTRY — without this a SuA/PuA edit opens with no land use selected.
     */
    function reflectLandUse() {
        var landUse = String(record.land_use || '').toUpperCase();
        if (!landUse) return;

        var inputName = IS_PRIMARY
            ? 'selectedLandUse'
            : (PREFIX === 'sua' ? 'sua_selectedLandUse' : 'pua_land_use');

        var boxes = document.querySelectorAll('input[name="' + inputName + '"]');
        if (!boxes.length) return;

        var wanted = (PREFIX === 'sua' && landUse === 'INDUSTRIAL') ? 'INDUSTRY' : landUse;
        var match = Array.prototype.find.call(boxes, function (cb) {
            return String(cb.value).toUpperCase() === wanted;
        });

        boxes.forEach(function (cb) {
            cb.checked = false;
            var label = cb.closest('label');
            if (label) label.classList.remove('selected');
        });

        if (match) {
            match.checked = true;
            var matchedLabel = match.closest('label');
            if (matchedLabel) matchedLabel.classList.add('selected');
        }

        var hiddenId = IS_PRIMARY
            ? 'hiddenLandUse'
            : (PREFIX === 'sua' ? 'sua_land_use_hidden' : 'pua_land_use_hidden');
        setValue(hiddenId, match ? match.value : landUse);
    }

    /* ------------------------------------------------- submit: update, not create */

    /**
     * Swap the tab's Generate/Commission button for an Update button. Every tab has
     * its own button, so the one belonging to the record's tab is repointed and the
     * others are removed to leave one action on the page.
     */
    function repointSubmitButtons() {
        var target = PREFIX === 'primary'
            ? $('generateSTFileNoBtn')
            : (PREFIX === 'pua'
                ? $('pua_generate_btn')
                : document.querySelector('button[onclick*="generateSuaFileNumbers"]'));

        // One action per page: the other tabs' commission buttons are removed so
        // there is no way to mint a new record from an edit session.
        document.querySelectorAll(
            '#generateSTFileNoBtn, #pua_generate_btn, ' +
            'button[onclick*="commissionFileNumber"], button[onclick*="generateSuaFileNumbers"]'
        ).forEach(function (btn) {
            if (btn === target) return;
            btn.remove();
        });

        if (!target) return;

        target.removeAttribute('onclick');
        // PuA disables its button until a parent file is picked; that picker is
        // locked here, so the button has to be re-enabled explicitly.
        target.disabled = false;
        target.classList.remove('opacity-50', 'cursor-not-allowed');
        target.innerHTML =
            '<div class="flex items-center justify-center">' +
            '<div class="bg-white/20 p-2 rounded-lg mr-3"><i data-lucide="save" class="w-5 h-5"></i></div>' +
            '<span>Update ST Record</span></div>';
        target.addEventListener('click', function (e) {
            e.preventDefault();
            submitUpdate(target);
        });

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function buildPayload() {
        // Gender is captured on the Primary form only; a unit keeps what it was
        // commissioned with. Omitted rather than blanked when there is none.
        var gender = ($('primary_gender') ? $('primary_gender').value : '') || record.gender || null;

        var payload = {
            applicant_type: applicantTypeKey,
            gender: gender,
            property_house_no: valueOf(locId('propertyHouseNo', 'PropertyHouseNo')),
            property_plot_no: valueOf(locId('propertyPlotNo', 'PropertyPlotNo')),
            property_street_name: valueOf(locId('propertyStreetName', 'PropertyStreetName')),
            property_district: valueOf(locId('propertyDistrict', 'PropertyDistrict')),
            property_lga: valueOf(locId('propertyLga', 'PropertyLga')),
            property_state: valueOf(locId('propertyState', 'PropertyState')),
            property_address: valueOf(locId('propertyAddressDisplay', 'PropertyAddressDisplay')),
            latitude: valueOf(locId('propertyLatitude', 'PropertyLatitude')) || null,
            longitude: valueOf(locId('propertyLongitude', 'PropertyLongitude')) || null
        };

        if (PREFIX === 'sua') {
            // Blank means "left as it was" — an empty string would blank a column
            // this screen never touched.
            ['allocation_entity', 'allocation_ref_no', 'allocation_reference_no'].forEach(function (key) {
                var value = valueOf('sua_' + key);
                if (key === 'allocation_entity' && value === 'Other') {
                    value = valueOf('sua_allocation_entity_other').trim();
                }
                if (value) payload[key] = value;
            });

            // Allocation Source is the institution now; the server derives the
            // legacy source/entity pair from it.
            var institution = valueOf('sua_allocation_source');
            if (institution === 'OTHERS (SPECIFY)') {
                institution = valueOf('sua_allocation_source_other').trim();
            }

            if (institution) {
                payload.institution_name = institution;
                payload.institution_category = (typeof window.suaSelectedInstitutionCategory === 'function')
                    ? window.suaSelectedInstitutionCategory()
                    : 'GOVERNMENT';
            }
        }

        if (applicantTypeKey === 'corporate') {
            payload.corporate_name = valueOf(PREFIX + '_corporate_name');
            payload.rc_number = valueOf(PREFIX + '_rc_number');
        } else if (applicantTypeKey === 'multiple') {
            payload.first_name = valueOf(PREFIX + '_owner_first_name');
            payload.middle_name = valueOf(PREFIX + '_owner_middle_name');
            payload.surname = valueOf(PREFIX + '_owner_last_name');
            payload.applicant_title = valueOf(PREFIX + '_owner_title');
        } else {
            payload.first_name = valueOf(PREFIX + '_first_name');
            payload.middle_name = valueOf(PREFIX + '_middle_name');
            payload.surname = valueOf(PREFIX + '_last_name');
            payload.applicant_title = valueOf(PREFIX + '_title');
        }

        return payload;
    }

    function valueOf(id) {
        var el = $(id);
        return el ? (el.value || '') : '';
    }

    function validate(payload) {
        if (payload.applicant_type === 'corporate') {
            if (!payload.corporate_name) return 'Company name is required for a corporate applicant.';
        } else if (!payload.first_name || !payload.surname) {
            return 'First name and surname are required.';
        }
        return null;
    }

    function submitUpdate(button) {
        // The applicant type may have been switched since load.
        var checked = document.querySelector(
            'input[name="' + (IS_PRIMARY ? 'applicant_type' : PREFIX + '_applicant_type') + '"]:checked'
        );
        if (checked) {
            applicantType = checked.value;
            applicantTypeKey = applicantType.toLowerCase();
        }

        var payload = buildPayload();
        var error = validate(payload);
        if (error) {
            Swal.fire({ icon: 'warning', title: 'Check the form', text: error });
            return;
        }

        var fileNo = OWN_FILE_NO;
        Swal.fire({
            icon: 'question',
            title: 'Save changes?',
            text: 'Update the commissioned record ' + fileNo + '? The file number itself stays unchanged.',
            showCancelButton: true,
            confirmButtonText: 'Save changes',
            confirmButtonColor: '#2563eb'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="flex items-center justify-center">Saving…</span>';

            fetch(window.ST_EDIT_UPDATE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Update failed');
                    return Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    }).then(function () {
                        window.location.href = '/st-file-numbers';
                    });
                })
                .catch(function (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#ef4444' });
                })
                .finally(function () {
                    button.disabled = false;
                    button.innerHTML = original;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
        });
    }

    /* ------------------------------------------------------------------ init */

    function init() {
        prefillApplicant();
        prefillSuaAllocation();
        lockFileNumberControls();
        unlockLocationSection();
        prefillLocation();
        repointSubmitButtons();

        // Headings still read "Commission New ST File Number" — retitle them.
        document.querySelectorAll('h2').forEach(function (h) {
            if (/Commission New ST File Number/i.test(h.textContent)) {
                h.textContent = h.textContent.replace(/Commission New ST File Number/i, 'Edit Commissioned ST Record');
            }
        });

        console.log('✏️ ST edit mode active for', OWN_FILE_NO);
    }

    // Run after the form's own DOMContentLoaded handlers (Select2 setup, tab
    // painting, land-use defaults) have had their turn.
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(init, 800);
    });
})();
