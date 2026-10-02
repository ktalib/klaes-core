/**
 * Change of Purpose - full client-side logic
 * Covers: tab switching, 3-step application wizard, approve/reject,
 *         view application, acknowledgement sheet, commission confirmation.
 */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var COP_TOTAL_STEPS = 3;
    var copStep = 1;
    var selectedFileRecord = null;
    var commissionRecordId = null; // ID of the record currently being commissioned

    /* ----------------------------------------
       Land-use helpers
       ---------------------------------------- */
    var LAND_USE_MAP = {
        'RES': 'Residential',   'RESIDENTIAL': 'Residential',
        'COM': 'Commercial',    'COMMERCIAL': 'Commercial',
        'IND': 'Industrial',    'INDUSTRIAL': 'Industrial',
        'AGR': 'Agricultural',  'AG': 'Agricultural',  'AGRIC': 'Agricultural', 'AGRICULTURAL': 'Agricultural',
        'MIX': 'Mixed Use',     'MIXED': 'Mixed Use'
    };

    var KNOWN_PREFIXES = ['RES','COM','IND','AGR','AGRIC','AG','MIX','MIXED',
                          'RESIDENTIAL','COMMERCIAL','INDUSTRIAL','AGRICULTURAL'];

    function detectLandUseLabel(fileNo) {
        if (!fileNo) return '';
        var u = fileNo.toUpperCase().trim();
        var m = u.match(new RegExp('^(?:ST|CON)-(' + KNOWN_PREFIXES.join('|') + ')-', 'i'));
        if (m && LAND_USE_MAP[m[1]]) return LAND_USE_MAP[m[1]];
        m = u.match(new RegExp('^(' + KNOWN_PREFIXES.join('|') + ')-', 'i'));
        if (m && LAND_USE_MAP[m[1]]) return LAND_USE_MAP[m[1]];
        return '';
    }

    function detectLandUseCode(fileNo) {
        if (!fileNo) return '';
        var u = fileNo.toUpperCase().trim();
        var m = u.match(new RegExp('^(?:ST|CON)-(' + KNOWN_PREFIXES.join('|') + ')-', 'i'));
        if (m && LAND_USE_MAP[m[1]]) return m[1];
        m = u.match(new RegExp('^(' + KNOWN_PREFIXES.join('|') + ')-', 'i'));
        if (m && LAND_USE_MAP[m[1]]) return m[1];
        return '';
    }

    function computeNewFileNo(oldFileNo, newPurpose) {
        if (!oldFileNo || !newPurpose) return null;
        var old = oldFileNo.toUpperCase().trim();
        var np  = newPurpose.toUpperCase().trim();
        var pat = '(' + KNOWN_PREFIXES.join('|') + ')';
        var m;
        m = old.match(new RegExp('^(ST)-' + pat + '-(.+)$', 'i'));
        if (m) return m[1] + '-' + np + '-' + m[3];
        m = old.match(new RegExp('^(CON)-' + pat + '-(.+)$', 'i'));
        if (m) return m[1] + '-' + np + '-' + m[3];
        m = old.match(new RegExp('^' + pat + '-(.+)$', 'i'));
        if (m) return np + '-' + m[2];
        return null;
    }

    /* ----------------------------------------
       Land Use / Purpose dynamic dropdowns
       ---------------------------------------- */
    var copLandUsesCache = null; // [{id, name, prefixes:[]}]

    function copFetchLandUses(cb) {
        if (copLandUsesCache) { cb(copLandUsesCache); return; }
        fetch('/api/reference/land-uses', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                copLandUsesCache = res.success ? res.data : [];
                cb(copLandUsesCache);
            })
            .catch(function () { cb([]); });
    }

    function copPopulateLandUseSelect(landUses) {
        var sel = document.getElementById('cop-land-use-id');
        if (!sel) return;
        var current = sel.value;
        sel.innerHTML = '<option value="">- Select New Land Use -</option>';
        (landUses || []).forEach(function (lu) {
            var opt = document.createElement('option');
            opt.value = lu.id;
            opt.textContent = lu.name;
            if (String(lu.id) === String(current)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function copOnLandUseChange(landUseId) {
        var purposeSel = document.getElementById('cop-new-purpose');
        var purposeCode = document.getElementById('cop-purpose');
        if (!purposeSel) return;

        // Reset purpose code
        if (purposeCode) purposeCode.value = '';

        if (!landUseId) {
            purposeSel.innerHTML = '<option value="">- Select Land Use First -</option>';
            purposeSel.disabled = true;
            return;
        }

        // Set prefix code from cached land use data
        if (copLandUsesCache) {
            var lu = copLandUsesCache.find(function (l) { return String(l.id) === String(landUseId); });
            if (lu && lu.prefixes && lu.prefixes.length > 0 && purposeCode) {
                purposeCode.value = lu.prefixes[0].toUpperCase();
            }
        }

        purposeSel.innerHTML = '<option value="">Loading...</option>';
        purposeSel.disabled = true;

        fetch('/api/reference/purposes?landuseid=' + landUseId, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                purposeSel.innerHTML = '<option value="">- Select New Purpose -</option>';
                (res.data || []).forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.name;
                    opt.textContent = p.name;
                    purposeSel.appendChild(opt);
                });
                purposeSel.disabled = false;
            })
            .catch(function () {
                purposeSel.innerHTML = '<option value="">- Failed to load -</option>';
                purposeSel.disabled = false;
            });
    }

    /* ----------------------------------------
       Tab switching
       ---------------------------------------- */
    window.copSwitchTab = function (tab, btn) {
        document.querySelectorAll('.cop-tab-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.cop-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        var panel = document.getElementById('cop-tab-' + tab);
        if (panel) panel.classList.add('active');
        if (btn) btn.classList.add('active');
    };

    /* ----------------------------------------
       Pre-filled field greying
       ---------------------------------------- */
    var PREFILL_READONLY = 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-400 cursor-not-allowed cop-prefill';
    var PREFILL_DEFAULT  = 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm cop-prefill';

    function greyOutPrefilled() {
        document.querySelectorAll('.cop-prefill').forEach(function (el) {
            if (el.value && el.value.trim()) { el.readOnly = true; el.className = PREFILL_READONLY; }
        });
    }

    function resetPrefilled() {
        document.querySelectorAll('.cop-prefill').forEach(function (el) {
            el.readOnly = false; el.className = PREFILL_DEFAULT;
        });
    }

    /* ----------------------------------------
       Property location preview + select helpers
       ---------------------------------------- */
    // Select an <option> whose value (or visible text) matches the given value,
    // case-insensitively. Returns true when a match was applied.
    function copSetSelectByValue(selectEl, value) {
        if (!selectEl || value == null) return false;
        var target = String(value).trim().toLowerCase();
        if (!target) return false;
        for (var i = 0; i < selectEl.options.length; i++) {
            var opt = selectEl.options[i];
            if (opt.value.trim().toLowerCase() === target ||
                opt.textContent.trim().toLowerCase() === target) {
                selectEl.selectedIndex = i;
                // Keep the Select2 widget (searchable dropdowns) in sync with
                // the programmatic selection without firing change handlers.
                if (window.jQuery && window.jQuery(selectEl).hasClass('select2-hidden-accessible')) {
                    window.jQuery(selectEl).trigger('change.select2');
                }
                return true;
            }
        }
        return false;
    }

    // Property-location dropdowns that offer an "Other" choice, paired with the
    // free-text box used to spell the value out.
    var COP_LOC_OTHER_FIELDS = [
        { select: 'cop-street',   other: 'cop-street-other'   },
        { select: 'cop-district', other: 'cop-district-other' },
        { select: 'cop-lga',      other: 'cop-lga-other'      }
    ];

    // Resolve one of those fields: either the dropdown value, or the free-text
    // "Other" input when "Other" is selected.
    function copResolveLocField(selectId, otherId) {
        var sel   = document.getElementById(selectId);
        var other = document.getElementById(otherId);
        if (!sel) return '';
        if ((sel.value || '').trim().toLowerCase() === 'other') {
            return (other?.value || '').trim();
        }
        return (sel.value || '').trim();
    }

    function copResolveStreet() {
        return copResolveLocField('cop-street', 'cop-street-other');
    }

    // Set a property-location field to `value`. When the value is not one of the
    // dropdown's options, fall back to "Other" + the free-text box so nothing is
    // silently dropped.
    function copSetLocValue(selectId, otherId, value) {
        var sel   = document.getElementById(selectId);
        var other = document.getElementById(otherId);
        if (!sel) return;
        if (copSetSelectByValue(sel, value)) {
            copToggleLocOther(selectId, otherId);
            return;
        }
        if (!other || !copSetSelectByValue(sel, 'Other')) return;
        other.value = String(value).trim();
        other.classList.remove('hidden');
    }

    // Writes the Full Property Location into the hidden `location` field and the
    // on-screen preview (they always mirror each other).
    function copApplyLocation(fullLocation) {
        var value = (fullLocation || '').trim();
        var locEl = document.getElementById('cop-location');
        if (locEl) locEl.value = value;

        var preview = document.getElementById('cop-location-preview');
        if (!preview) return;
        if (value) {
            preview.textContent = value;
            preview.classList.remove('italic', 'text-slate-700');
            preview.classList.add('text-slate-800');
        } else {
            preview.textContent = 'No location details entered yet.';
            preview.classList.remove('text-slate-800');
            preview.classList.add('italic');
        }
    }

    // Builds the Full Property Location from the individual fields (street, plot,
    // district, LGA, state). Used while the user fills the form manually.
    function copUpdateLocationPreview() {
        var street   = copResolveStreet();
        var plot     = (document.getElementById('cop-plot-no')?.value || '').trim();
        var district = copResolveLocField('cop-district', 'cop-district-other');
        var lga      = copResolveLocField('cop-lga', 'cop-lga-other');
        var state    = (document.getElementById('cop-state')?.value || '').trim();
        copApplyLocation([
            plot ? 'Plot ' + plot : '',
            street, district, lga, state
        ].filter(Boolean).join(', '));
    }

    // Show/hide the "Other" text box of a property-location field.
    function copToggleLocOther(selectId, otherId) {
        var sel   = document.getElementById(selectId);
        var other = document.getElementById(otherId);
        if (!sel || !other) return;
        var isOther = (sel.value || '').trim().toLowerCase() === 'other';
        other.classList.toggle('hidden', !isOther);
        if (!isOther) other.value = '';
    }

    /* ----------------------------------------
       Wizard
       ---------------------------------------- */
    function setStep(step) {
        copStep = step;
        document.querySelectorAll('.cop-step').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.dataset.step, 10) !== step);
        });
        for (var i = 1; i <= COP_TOTAL_STEPS; i++) {
            var circle = document.getElementById('cop-wizard-circle-' + i);
            var label  = document.getElementById('cop-wizard-label-' + i);
            if (circle) circle.className = 'w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all duration-300 ' +
                (i <= step ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400');
            if (label) label.className = 'text-[11px] font-semibold mt-1.5 transition-colors duration-300 ' +
                (i <= step ? 'text-blue-600' : 'text-slate-400');
        }
        document.querySelectorAll('.cop-wizard-connector').forEach(function (conn) {
            var before = parseInt(conn.dataset.beforeStep, 10);
            var bar = conn.querySelector('.connector-bar');
            if (bar) bar.style.backgroundColor = before <= step ? 'rgb(59 130 246)' : 'rgb(226 232 240)';
        });
        document.getElementById('cop-prev-btn').disabled = step === 1;
        document.getElementById('cop-next-btn').classList.toggle('hidden', step === COP_TOTAL_STEPS);
        document.getElementById('cop-save-btn').classList.toggle('hidden', step !== COP_TOTAL_STEPS);
        if (step === COP_TOTAL_STEPS) populateReview();
    }

    window.copNextStep = function () {
        if (copStep === 1) {
            var fileNo      = (document.getElementById('cop-file-no')?.value || '').trim();
            var landUseId   = (document.getElementById('cop-land-use-id')?.value || '').trim();
            var newPurpose  = (document.getElementById('cop-new-purpose')?.value || '').trim();
            var purposeCode = (document.getElementById('cop-purpose')?.value || '').trim();
            if (!fileNo)     { Swal.fire({ icon: 'warning', title: 'Select a file number' }); return; }
            if (!landUseId)  { Swal.fire({ icon: 'warning', title: 'Select a new land use' }); return; }
            if (!newPurpose) { Swal.fire({ icon: 'warning', title: 'Select a new purpose' }); return; }
            var curCode = detectLandUseCode(fileNo);
            if (purposeCode && curCode && curCode === purposeCode.toUpperCase()) {
                Swal.fire({ icon: 'warning', title: 'Same Land Use', text: 'The new land use matches the current land use.' });
                return;
            }
            if (purposeCode && !computeNewFileNo(fileNo, purposeCode)) {
                Swal.fire({ icon: 'warning', title: 'Unsupported Format', text: 'Cannot compute new file number from this file number format.' });
                return;
            }
        }
        if (copStep === 2) {
            var name = (document.getElementById('cop-applicant-name')?.value || '').trim();
            if (!name) { Swal.fire({ icon: 'warning', title: 'Applicant name is required' }); return; }
        }
        if (copStep < COP_TOTAL_STEPS) setStep(copStep + 1);
    };

    window.copPrevStep = function () {
        if (copStep > 1) setStep(copStep - 1);
    };

    function copGetSelectedLandUseName() {
        var sel = document.getElementById('cop-land-use-id');
        if (!sel || !sel.value) return '';
        var opt = sel.options[sel.selectedIndex];
        return opt ? opt.textContent : '';
    }

    function populateReview() {
        var fileNo  = (document.getElementById('cop-file-no')?.value || '').trim();
        var knupdaStatus = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        var rows = [
            ['Applicant',     document.getElementById('cop-applicant-name')?.value],
            ['Phone',         document.getElementById('cop-phone')?.value],
            ['File No',       fileNo],
            ['Current Use',   document.getElementById('cop-land-use')?.value],
            ['New Land Use',  copGetSelectedLandUseName()],
            ['New Purpose',   document.getElementById('cop-new-purpose')?.value],
            ['Plot No',       document.getElementById('cop-plot-no')?.value],
            ['Plan No',       document.getElementById('cop-plan-no')?.value],
            ['Location',      document.getElementById('cop-location')?.value],
            ['Land Size',     document.getElementById('cop-land-size')?.value ? document.getElementById('cop-land-size').value + ' Sqm' : null],
            ['Address',       document.getElementById('cop-residential-address')?.value],
            ['Comment',       document.getElementById('cop-comment')?.value],
        ];
        var container = document.getElementById('cop-review-summary');
        if (container) container.innerHTML = rows.filter(function (r) { return r[1]; }).map(function (r) {
            return '<div class="flex gap-2"><strong class="shrink-0 w-32">' + safe(r[0]) + ':</strong><span>' + safe(r[1]) + '</span></div>';
        }).join('');
    }

    /* ----------------------------------------
       Modal open / close
       ---------------------------------------- */
    window.copOpenCreate = function () {
        resetForm();
        document.getElementById('cop-create-modal').classList.remove('hidden');
        setStep(1);
    };

    window.copCloseCreateModal = function () {
        document.getElementById('cop-create-modal').classList.add('hidden');
    };

    function resetForm() {
        var f = document.getElementById('cop-form');
        if (f) f.reset();
        // form.reset() clears the native selects; re-render their Select2 widgets.
        if (window.syncSearchableSelects) window.syncSearchableSelects(f);
        document.getElementById('cop-file-indicator')?.classList.add('hidden');
        document.getElementById('cop-file-details')?.classList.add('hidden');
        document.getElementById('cop-land-use').value = '';
        document.getElementById('cop-purpose').value = '';
        document.getElementById('cop-res-addr-street-other-wrapper')?.classList.add('hidden');
        document.getElementById('cop-res-addr-district-other-wrapper')?.classList.add('hidden');
        COP_LOC_OTHER_FIELDS.forEach(function (f) {
            var other = document.getElementById(f.other);
            if (!other) return;
            other.value = '';
            other.classList.add('hidden');
        });
        document.getElementById('cop-residential-address').value = '';
        // Reset land use select
        var luSel = document.getElementById('cop-land-use-id');
        if (luSel) luSel.value = '';
        // Reset and disable purpose select
        var purSel = document.getElementById('cop-new-purpose');
        if (purSel) { purSel.innerHTML = '<option value="">- Select Land Use First -</option>'; purSel.disabled = true; }
        selectedFileRecord = null;
        resetPrefilled();
        copUpdateLocationPreview();
        setStep(1);
    }

    /* ----------------------------------------
       Submit application
       ---------------------------------------- */
    window.copSubmitForm = async function () {
        var form = document.getElementById('cop-form');
        var formData = new FormData(form);
        // District / LGA post the free-text value when "Other" is selected.
        formData.set('district', copResolveLocField('cop-district', 'cop-district-other'));
        formData.set('lga', copResolveLocField('cop-lga', 'cop-lga-other'));

        try {
            var response = await fetch('/change-of-purpose', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: formData
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || (payload.errors ? Object.values(payload.errors)[0][0] : 'Submission failed.'));
            copCloseCreateModal();
            await Swal.fire({ icon: 'success', title: 'Application Submitted', text: payload.message, timer: 2000, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Submission Failed', text: err.message });
        }
    };

    /* ----------------------------------------
       KNUPDA Handshake Modal
       ---------------------------------------- */
    window.copOpenKnupdaModal = async function(id) {
        try {
            var response = await fetch('/change-of-purpose/' + id);
            var result = await response.json();
            if (result.success) {
                var data = result.data;
                Swal.fire({
                    title: 'KNUPDA Handshake',
                    html: `
                        <div class="text-left text-sm space-y-4 p-2">
                            <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Land Size (Sqm)</label>
                                        <input type="number" id="knupda_land_size" oninput="copCalcCopFee(this)" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" value="${data.land_size || '0'}">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (NGN)</label>
                                        <input type="text" id="knupda_fee_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm font-bold bg-slate-100" value="${data.knupda_fee || '0'}" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">KNUPDA Approval</label>
                                    <div class="flex items-center gap-4 mt-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="knupda_status" value="Approved" ${data.knupda_status === 'Approved' ? 'checked' : ''} class="w-4 h-4 text-emerald-600">
                                            <span class="text-xs font-medium text-slate-700">Approve</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="knupda_status" value="Declined" ${data.knupda_status === 'Declined' ? 'checked' : ''} class="w-4 h-4 text-red-600">
                                            <span class="text-xs font-medium text-slate-700">Decline</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Remarks</label>
                                    <textarea id="knupda_remarks_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" rows="2" placeholder="KNUPDA feedback...">${data.knupda_remarks || ''}</textarea>
                                </div>
                                <button onclick="copSaveKnupda(${data.id})" class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">
                                    Update KNUPDA Status
                                </button>
                            </div>
                        </div>
                    `,
                    width: 500,
                    showConfirmButton: false,
                    showCloseButton: true
                });
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to fetch details.', 'error');
        }
    };

    window.copSaveKnupda = async function(id) {
        var size = document.getElementById('knupda_land_size').value;
        var fee = document.getElementById('knupda_fee_input').value;
        var status = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        var remarks = document.getElementById('knupda_remarks_input').value;

        try {
            var response = await fetch('/change-of-purpose/' + id + '/knupda', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ land_size: size, knupda_fee: fee, knupda_status: status, knupda_remarks: remarks })
            });

            var result = await response.json();
            if (result.success) {
                await Swal.fire({ icon: 'success', title: 'Updated', text: 'KNUPDA status updated successfully.', timer: 1500, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire('Error', result.message || 'Failed to update KNUPDA status', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        }
    };

    /* ----------------------------------------
       Approve / Reject
       ---------------------------------------- */
    window.copApproveRecord = async function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        var res = await Swal.fire({
            title: 'Approve Application?',
            text: 'This will move the application to the Initiate queue.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve',
            confirmButtonColor: '#16a34a'
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('/change-of-purpose/' + data.id + '/approve', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Approval failed.');
            await Swal.fire({ icon: 'success', title: 'Approved', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message });
        }
    };

    window.copRejectRecord = async function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        var res = await Swal.fire({
            title: 'Reject Application',
            input: 'textarea',
            inputLabel: 'Reason for rejection (optional)',
            inputPlaceholder: 'Enter reason...',
            showCancelButton: true,
            confirmButtonText: 'Reject',
            confirmButtonColor: '#dc2626',
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('/change-of-purpose/' + data.id + '/reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ reason: res.value || '' })
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Rejection failed.');
            await Swal.fire({ icon: 'info', title: 'Rejected', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message });
        }
    };

    /* ----------------------------------------
       View Application
       ---------------------------------------- */
    window.copViewApplication = function (btn) {
        var data = rowData(btn);
        if (!data) return;

        var currentPurpose = safe(data.land_use || '-');
        var newLandUse = safe(LAND_USE_MAP[(data.purpose || '').toUpperCase()] || data.purpose || '-');
        var newPurpose = safe(data.new_purpose || '-');
        var statusRaw = (data.status || 'pending').toLowerCase();
        var statusColors = { pending: 'bg-amber-100 text-amber-700', approved: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700', processing: 'bg-blue-100 text-blue-700', commissioned: 'bg-emerald-100 text-emerald-700' };
        var statusClass = statusColors[statusRaw] || 'bg-slate-100 text-slate-600';
        var dateStr = data.created_at ? data.created_at.substring(0, 10) : '-';

        var html = '';
        // Purpose change card
        html += '<div class="rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 p-4">';
        html += '<p class="text-[10px] font-bold uppercase tracking-widest text-indigo-400 mb-3">Purpose Change</p>';
        html += '<div class="flex items-center gap-3">';
        html += '<div class="flex-1 text-center"><p class="text-[10px] text-slate-400 mb-1">Current Land Use</p><p class="text-sm font-bold text-slate-700">' + currentPurpose + '</p></div>';
        html += '<div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0"><i data-lucide="arrow-right" class="w-4 h-4 text-indigo-500"></i></div>';
        html += '<div class="flex-1 text-center"><p class="text-[10px] text-slate-400 mb-1">New Land Use</p><p class="text-sm font-bold text-indigo-600">' + newLandUse + '</p></div>';
        html += '</div>';
        html += '<div class="mt-3 pt-3 border-t border-indigo-100">';
        html += '<p class="text-[10px] text-slate-400 mb-1">New Purpose</p><p class="text-sm font-semibold text-slate-700">' + newPurpose + '</p>';
        html += '</div>';
        html += '</div>';

        // File & property details grid
        html += '<div class="grid grid-cols-2 gap-3">';
        html += '<div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">';
        html += '<p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">File Details</p>';
        html += '<div class="space-y-2">';
        html += '<div><p class="text-[10px] text-slate-400">File No</p><p class="text-sm font-semibold text-slate-800">' + safe(data.file_no || '-') + '</p></div>';
        html += '<div><p class="text-[10px] text-slate-400">Applicant</p><p class="text-sm font-semibold text-slate-800">' + safe(data.applicant_name || '-') + '</p></div>';
        html += '</div></div>';
        html += '<div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">';
        html += '<p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Property Details</p>';
        html += '<div class="space-y-2">';
        html += '<div><p class="text-[10px] text-slate-400">Plot No</p><p class="text-sm font-semibold text-slate-800">' + safe(data.plot_no || '-') + '</p></div>';
        html += '<div><p class="text-[10px] text-slate-400">Plan No</p><p class="text-sm font-semibold text-slate-800">' + safe(data.plan_no || '-') + '</p></div>';
        html += '<div><p class="text-[10px] text-slate-400">Comment</p><p class="text-xs text-slate-600">' + safe(data.comment || '-') + '</p></div>';
        html += '<div class="bg-blue-50 p-2 rounded-lg border border-blue-100 mt-2">';
        html += '<p class="text-[10px] font-black text-blue-600 uppercase">Application Fee (NGN)</p>';
        html += '<p class="font-bold text-blue-700">₦' + (data.knupda_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</p>';
        html += '</div>';
        html += '</div></div></div>';

        // Location Section
        html += '<div class="mt-4 pt-4 border-t border-slate-100">';
        html += '<p class="text-[10px] font-black text-slate-400 uppercase mb-1">Property Location</p>';
        html += '<p class="text-sm font-bold text-slate-700">' + (data.plot_no ? 'Plot ' + data.plot_no + ', ' : '') + (data.location || '') + (data.district ? ', ' + data.district : '') + (data.lga ? ', ' + data.lga : '') + (data.state ? ', ' + data.state : '') + '</p>';
        html += '</div>';

        var subtitle = document.getElementById('cop-view-subtitle');
        if (subtitle) subtitle.textContent = data.file_no ? 'File No: ' + data.file_no : '';
        var body = document.getElementById('cop-view-body');
        if (body) body.innerHTML = html || '<p class="text-slate-400 text-sm">No details available.</p>';
        document.getElementById('cop-view-modal').classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };


    window.copGenerateApplication = async function(id) {
        try {
            const response = await fetch(`/change-of-purpose/${id}/generate-application`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) Swal.fire('Success', 'Application generated.', 'success');
        } catch (error) { Swal.fire('Error', 'Failed to generate', 'error'); }
    };

    window.copGenerateRecommendation = async function(id) {
        try {
            const response = await fetch(`/change-of-purpose/${id}/generate-recommendation`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) Swal.fire('Success', 'Recommendation generated.', 'success');
            else Swal.fire('Error', res.message || 'Recommendation failed.', 'error');
        } catch (error) { Swal.fire('Error', 'Failed to generate', 'error'); }
    };

    /* ----------------------------------------
       Acknowledgement
       ---------------------------------------- */
    window.copViewAcknowledgement = function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        window.open('/change-of-purpose/' + data.id + '/acknowledgement/print', '_blank', 'width=900,height=700');
    };

    window.copPrintAcknowledgement = function () {
        window.print();
    };

    /* ----------------------------------------
       Commission  (uses shared commission-fileno modal)
       ---------------------------------------- */
    window.copOpenCommission = function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        commissionRecordId = data.id;

        // Helper to set hidden fields in the shared modal form
        var setHidden = function (name, value) {
            var els = document.querySelectorAll('form#generateForm input[name="' + name + '"]');
            els.forEach(function (el) { el.value = value || ''; });
        };

        // Prefill source context
        setHidden('source', 'change_of_purpose');
        setHidden('sub_source', 'cop_' + data.id);
        setHidden('source_original_owner', data.applicant_name || '');

        // Force the Alpine component into Change of Purpose mode. Setting the
        // hidden "source" input's value above does not trigger Alpine's
        // reactivity, so applicationType must be set directly on the
        // component instance (same pattern used in instruments-capture.js).
        // changeOfPurposeAppId and originalFileNo must ALSO be set directly on
        // the component (x-model inputs reflect Alpine state, not the other
        // way around) — without this the backend never receives the link back
        // to this application, so it can't flip its status to "commissioned"
        // once the file number is generated.
        var modalContainer = document.querySelector('#generateModal [x-data="fileNumberGenerator()"]');
        var component = modalContainer && modalContainer._x_dataStack ? modalContainer._x_dataStack[0] : null;
        if (component) {
            component.applicationType = 'change_of_purpose';
            component.changeOfPurposeAppId = data.id;
            component.originalFileNo = data.file_no || '';
            component.newPurpose = data.purpose || '';
        }

        // Pre-select land use for the new purpose
        var purposeSelect = document.getElementById('landUse');
        if (purposeSelect && data.purpose) {
            var purposeLabel = LAND_USE_MAP[(data.purpose || '').toUpperCase()] || data.purpose;
            for (var i = 0; i < purposeSelect.options.length; i++) {
                if (purposeSelect.options[i].text.toLowerCase() === purposeLabel.toLowerCase() ||
                    purposeSelect.options[i].value.toLowerCase() === purposeLabel.toLowerCase()) {
                    purposeSelect.selectedIndex = i;
                    purposeSelect.dispatchEvent(new Event('change'));
                    break;
                }
            }
        }

        // Pre-fill the file name from the applicant
        var fileNameInput = document.getElementById('fileName');
        if (fileNameInput && data.applicant_name) {
            fileNameInput.value = data.applicant_name;
            fileNameInput.dispatchEvent(new Event('input'));
        }

        // Pre-fill location fields
        var locInput = document.getElementById('location');
        if (locInput && data.location) { locInput.value = data.location; }
        var plotInput = document.getElementById('plotNo');
        if (plotInput && data.plot_no) { plotInput.value = data.plot_no; }

        // Hide allocation source section (not relevant for COP)
        var allocSrc = document.getElementById('allocation-source-section');
        if (allocSrc) allocSrc.style.display = 'none';

        // Restore allocation source section when modal closes
        var origClose = window.closeGenerateModal;
        window.closeGenerateModal = function () {
            if (allocSrc) allocSrc.style.display = '';
            window.closeGenerateModal = origClose;
            return origClose.apply(this, arguments);
        };

        // Open the shared modal
        if (typeof window.openCommissionFileNoModal === 'function') {
            window.openCommissionFileNoModal();
        }
    };

    // Success callback — when the shared modal generates a file number,
    // update the COP record status to commissioned
    window.commissionFileNoSuccessCallback = function (response) {
        if (!commissionRecordId) return;
        var newFileNo = response?.fileNumber || response?.generated_file_number || '';

        fetch('/change-of-purpose/' + commissionRecordId + '/commission', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ new_file_no: newFileNo })
        })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            if (payload.success) {
                Swal.fire({ icon: 'success', title: 'Commissioned', text: payload.message || 'Change of purpose commissioned successfully.', timer: 2000, showConfirmButton: false });
                setTimeout(function () { location.reload(); }, 2200);
            }
        })
        .catch(function () {});
        commissionRecordId = null;
    };

    /* ----------------------------------------
       Delete
       ---------------------------------------- */
    window.copDelete = async function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        var res = await Swal.fire({
            title: 'Delete Record?',
            text: 'This removes the application record only. No file number changes will be reversed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            confirmButtonColor: '#dc2626'
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('/change-of-purpose/' + data.id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Delete failed.');
            await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message });
        }
    };

    window.copGenerateApplication = async function (id) {
        var confirm = await Swal.fire({
            title: 'Generate Application?',
            text: "Are you sure you want to generate the application document?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate',
            confirmButtonColor: '#f97316'
        });

        if (!confirm.isConfirmed) return;

        try {
            var response = await fetch('/change-of-purpose/' + id + '/generate-application', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            var res = await response.json();
            if (res.success) {
                await Swal.fire('Success', 'Application generated. You can now print it.', 'success');
                location.reload();
            } else {
                Swal.fire('Error', res.message || 'Failed to generate application', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate application', 'error');
        }
    };

    window.copGenerateRecommendation = async function (id) {
        var confirm = await Swal.fire({
            title: 'Generate Recommendation?',
            text: "Are you sure you want to generate the recommendation document?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate',
            confirmButtonColor: '#10b981'
        });

        if (!confirm.isConfirmed) return;

        try {
            var response = await fetch('/change-of-purpose/' + id + '/generate-recommendation', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            var res = await response.json();
            if (res.success) {
                await Swal.fire('Success', 'Recommendation generated. You can now print it.', 'success');
                location.reload();
            } else {
                Swal.fire('Error', res.message || 'Recommendation can only be generated for Approved applications.', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate recommendation', 'error');
        }
    };

    /* ----------------------------------------
       Helpers
       ---------------------------------------- */
    function rowData(btn) {
        var tr = btn.closest('tr');
        if (!tr) return null;
        try { return JSON.parse(tr.getAttribute('data-record')); } catch (e) { return null; }
    }

    function safe(value) {
        return (value == null ? '-' : String(value)).replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
        });
    }

    function showFileDetails(record) {
        var box = document.getElementById('cop-file-details');
        if (!box) return;
        box.classList.remove('hidden');
        document.getElementById('cop-det-filename').textContent = record.FileName || record.file_name || '-';
        document.getElementById('cop-det-location').textContent = record.location || '-';
        document.getElementById('cop-det-lga').textContent      = record.lga || '-';
        document.getElementById('cop-det-source').textContent   = record.SOURCE || record.source || '-';
    }

    /* ----------------------------------------
       Address builder
       ---------------------------------------- */
    function copIsOther(val) { return (val || '').trim().toLowerCase() === 'other'; }

    function copToggleOther(selectEl, wrapperId, otherEl) {
        var wrapper = document.getElementById(wrapperId);
        if (!wrapper || !selectEl) return;
        var show = copIsOther(selectEl.value);
        wrapper.classList.toggle('hidden', !show);
        if (!show && otherEl) otherEl.value = '';
    }

    function copSelectedOrOther(selectEl, otherEl) {
        if (!selectEl) return '';
        if (copIsOther(selectEl.value)) return (otherEl?.value || '').trim();
        return (selectEl.value || '').trim();
    }

    function copBuildAddress() {
        var prefix = 'cop_res_addr';
        var plot    = (document.getElementById(prefix + '_plot')?.value || '').trim();
        var street  = document.getElementById(prefix + '_street');
        var streetO = document.getElementById(prefix + '_street_other');
        var dist    = document.getElementById(prefix + '_district');
        var distO   = document.getElementById(prefix + '_district_other');
        var lga     = (document.getElementById(prefix + '_lga')?.value || '').trim();
        var state   = (document.getElementById(prefix + '_state')?.value || '').trim();
        var streetVal = copSelectedOrOther(street, streetO);
        var distVal   = copSelectedOrOther(dist, distO);
        var built = [plot, streetVal, distVal, lga, state].filter(Boolean).join(', ').toUpperCase();
        var output = document.getElementById('cop-residential-address');
        if (output) output.value = built;
    }

    function copInitAddressBuilder() {
        var prefix = 'cop_res_addr';
        var dashP  = 'cop-res-addr';
        var plotEl    = document.getElementById(prefix + '_plot');
        var streetEl  = document.getElementById(prefix + '_street');
        var streetOEl = document.getElementById(prefix + '_street_other');
        var distEl    = document.getElementById(prefix + '_district');
        var distOEl   = document.getElementById(prefix + '_district_other');
        var lgaEl     = document.getElementById(prefix + '_lga');
        var stateEl   = document.getElementById(prefix + '_state');

        [plotEl, streetEl, streetOEl, distEl, distOEl, lgaEl, stateEl].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', copBuildAddress);
            el.addEventListener('change', copBuildAddress);
        });

        if (streetEl) streetEl.addEventListener('change', function () {
            copToggleOther(streetEl, dashP + '-street-other-wrapper', streetOEl);
        });
        if (distEl) distEl.addEventListener('change', function () {
            copToggleOther(distEl, dashP + '-district-other-wrapper', distOEl);
        });
    }

    /* ----------------------------------------
       Dropdown menu toggle
       ---------------------------------------- */
    window.copToggleActionMenu = function (id) {
        const button = document.querySelector(`#cop-dropdown-${id} button`);
        const menu = document.getElementById(`cop-menu-${id}`);
        const allMenus = document.querySelectorAll('[id^="cop-menu-"]');
        
        allMenus.forEach(m => { if(m.id !== `cop-menu-${id}`) m.classList.add('hidden'); });
        
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            const rect = button.getBoundingClientRect();
            
            menu.style.position = 'fixed';
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            
            if (rect.bottom + menu.offsetHeight > window.innerHeight) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            }
        } else {
            menu.classList.add('hidden');
        }
        
        const closeDropdown = (e) => {
            if (!document.getElementById(`cop-dropdown-${id}`).contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                document.removeEventListener('click', closeDropdown);
            }
        };
        if (!menu.classList.contains('hidden')) {
            setTimeout(() => document.addEventListener('click', closeDropdown), 0);
        }
    };


    window.copToggleDropdown = function (button, event) {
        event.stopPropagation();
        var menu = button.closest('.dropdown-container').querySelector('.cop-action-menu');
        var isHidden = menu.classList.contains('hidden');

        // Close all open menus first
        document.querySelectorAll('.cop-action-menu').forEach(function (m) { m.classList.add('hidden'); });

        if (isHidden) {
            menu.classList.remove('hidden');
            var rect = button.getBoundingClientRect();
            menu.style.left = (rect.left - menu.offsetWidth + rect.width) + 'px';
            // Show above if there's room, otherwise below
            if (rect.top - menu.offsetHeight > 0) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            } else {
                menu.style.top = (rect.bottom + 5) + 'px';
            }
        }
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function () {
        document.querySelectorAll('.cop-action-menu').forEach(function (m) { m.classList.add('hidden'); });
    });

    // Close dropdown when a menu item is clicked
    document.addEventListener('click', function (e) {
        if (e.target.closest('.cop-action-menu li')) {
            document.querySelectorAll('.cop-action-menu').forEach(function (m) { m.classList.add('hidden'); });
        }
    });

    /* ----------------------------------------
       Init
       ---------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {

        // DataTables
        var dtOptions = {
            dom: '<"overflow-x-auto"t><"px-4 py-3 flex items-center justify-between border-t border-slate-100"ip>',
            pageLength: parseInt(document.getElementById('cop-length')?.value || '50', 10),
            lengthChange: false,
            language: {
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                emptyTable: 'No records found.',
                paginate: { previous: 'Prev', next: 'Next' }
            }
        };

        var pendingTable  = $('#cop-pending-table').DataTable(Object.assign({}, dtOptions, {
            order: [[6, 'desc']],
            columnDefs: [{ orderable: false, targets: [7] }]
        }));

        var approvedTable = $('#cop-approved-table').DataTable(Object.assign({}, dtOptions, {
            // Sort by Date (index 7 after the Status column was added), Actions (8) not orderable.
            order: [[7, 'desc']],
            columnDefs: [{ orderable: false, targets: [8] }]
        }));

        document.getElementById('cop-search')?.addEventListener('input', function () {
            pendingTable.search(this.value).draw();
            approvedTable.search(this.value).draw();
        });
        document.getElementById('cop-length')?.addEventListener('change', function () {
            var len = parseInt(this.value, 10);
            pendingTable.page.len(len).draw();
            approvedTable.page.len(len).draw();
        });

        // Pre-fetch land uses and populate NEW LAND USE dropdown
        copFetchLandUses(function (landUses) {
            copPopulateLandUseSelect(landUses);
        });

        // Wire NEW LAND USE change → populate NEW PURPOSE + set hidden purpose code
        var landUseSel = document.getElementById('cop-land-use-id');
        if (landUseSel) {
            landUseSel.addEventListener('change', function () {
                copOnLandUseChange(this.value);
            });
        }

        // Init the global file-number modal once (binds its events, loads recent selections)
        if (window.GlobalFileNoModal) GlobalFileNoModal.init();

        // Address builder
        copInitAddressBuilder();

        // Live "Full Property Location Preview" + hidden location updates
        ['cop-plot-no', 'cop-street', 'cop-street-other', 'cop-district', 'cop-district-other',
         'cop-lga', 'cop-lga-other', 'cop-state'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', copUpdateLocationPreview);
            el.addEventListener('change', copUpdateLocationPreview);
        });
        // Toggle the free-text "Other" boxes (street / district / LGA)
        COP_LOC_OTHER_FIELDS.forEach(function (f) {
            document.getElementById(f.select)?.addEventListener('change', function () {
                copToggleLocOther(f.select, f.other);
            });
        });

        // File Number Selector button
        document.getElementById('cop-select-file-btn')?.addEventListener('click', function () {
            if (!window.GlobalFileNoModal) return;
            GlobalFileNoModal.open({
                callback: function (data) {
                    if (!data.fileNumber) return;
                    var fn = data.fileNumber.toUpperCase();
                    document.getElementById('cop-file-no').value = fn;
                    document.getElementById('cop-file-indicator')?.classList.remove('hidden');
                    // Auto-fill land use
                    document.getElementById('cop-land-use').value = detectLandUseLabel(fn);
                    // Auto-fill applicant name from record
                    if (data.record) {
                        showFileDetails(data.record);
                        var rec = data.record;
                        var nameEl   = document.getElementById('cop-applicant-name');
                        var plotEl   = document.getElementById('cop-plot-no');
                        var distEl   = document.getElementById('cop-district');
                        var lgaEl    = document.getElementById('cop-lga');
                        var applicantName = rec.FileName || rec.file_name || '';
                        if (nameEl && applicantName) { nameEl.value = applicantName; }
                        if (plotEl && !plotEl.value && rec.plot_no)   { plotEl.value = rec.plot_no;  }
                        // Backfill the District / LGA dropdowns from the file record.
                        // The Street dropdown is left untouched — it is for the street only.
                        if (distEl && !distEl.value && rec.district) { copSetLocValue('cop-district', 'cop-district-other', rec.district); }
                        if (lgaEl  && !lgaEl.value  && rec.lga)      { copSetLocValue('cop-lga', 'cop-lga-other', rec.lga); }
                        greyOutPrefilled();
                        // The record's full property location backfills the hidden location field.
                        if (rec.location) { copApplyLocation(rec.location); }
                    }
                }
            });
        });

        // Lucide icons re-init for dynamically rendered content
        if (window.lucide) lucide.createIcons();
    });

})();
