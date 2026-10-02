/**
 * Change of Name - full client-side logic
 * Covers: tab switching, 3-step application wizard, approve/reject,
 *         view application, acknowledgement sheet, name composition.
 */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var CON_TOTAL_STEPS = 3;
    var conStep = 1;
    var selectedFileRecord = null;

    /* ----------------------------------------
       Land-use helpers (for auto-fill only)
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

    /* ----------------------------------------
       Tab switching
       ---------------------------------------- */
    window.conSwitchTab = function (tab, btn) {
        document.querySelectorAll('.con-tab-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.con-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        var panel = document.getElementById('con-tab-' + tab);
        if (panel) panel.classList.add('active');
        if (btn) btn.classList.add('active');
    };

    /* ----------------------------------------
       Pre-filled field greying
       ---------------------------------------- */
    var PREFILL_READONLY = 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-400 cursor-not-allowed con-prefill';
    var PREFILL_DEFAULT  = 'w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm con-prefill';

    function greyOutPrefilled() {
        document.querySelectorAll('.con-prefill').forEach(function (el) {
            if (el.value && el.value.trim()) { el.readOnly = true; el.className = PREFILL_READONLY; }
        });
    }

    function resetPrefilled() {
        document.querySelectorAll('.con-prefill').forEach(function (el) {
            el.readOnly = false; el.className = PREFILL_DEFAULT;
        });
    }

    /* ----------------------------------------
       Name composition
       ---------------------------------------- */
    function conBuildFullName() {
        var title  = (document.getElementById('con-title')?.value || '').trim();
        var first  = (document.getElementById('con-first-name')?.value || '').trim();
        var middle = (document.getElementById('con-middle-name')?.value || '').trim();
        var last   = (document.getElementById('con-last-name')?.value || '').trim();
        var parts  = [title, first, middle, last].filter(Boolean);
        var full   = parts.join(' ').toUpperCase();
        var el = document.getElementById('con-new-full-name');
        if (el) el.value = full;
    }

    /* ----------------------------------------
       Wizard
       ---------------------------------------- */
    function setStep(step) {
        conStep = step;
        document.querySelectorAll('.con-step').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.dataset.step, 10) !== step);
        });
        for (var i = 1; i <= CON_TOTAL_STEPS; i++) {
            var circle = document.getElementById('con-wizard-circle-' + i);
            var label  = document.getElementById('con-wizard-label-' + i);
            if (circle) circle.className = 'w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all duration-300 ' +
                (i <= step ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400');
            if (label) label.className = 'text-[11px] font-semibold mt-1.5 transition-colors duration-300 ' +
                (i <= step ? 'text-blue-600' : 'text-slate-400');
        }
        document.querySelectorAll('.con-wizard-connector').forEach(function (conn) {
            var before = parseInt(conn.dataset.beforeStep, 10);
            var bar = conn.querySelector('.connector-bar');
            if (bar) bar.style.backgroundColor = before <= step ? 'rgb(59 130 246)' : 'rgb(226 232 240)';
        });
        document.getElementById('con-prev-btn').disabled = step === 1;
        document.getElementById('con-next-btn').classList.toggle('hidden', step === CON_TOTAL_STEPS);
        document.getElementById('con-save-btn').classList.toggle('hidden', step !== CON_TOTAL_STEPS);
        if (step === CON_TOTAL_STEPS) populateReview();
    }

    window.conNextStep = function () {
        if (conStep === 1) {
            var fileNo = (document.getElementById('con-file-no')?.value || '').trim();
            if (!fileNo) { Swal.fire({ icon: 'warning', title: 'Select a file number' }); return; }
        }
        if (conStep === 2) {
            var first = (document.getElementById('con-first-name')?.value || '').trim();
            var last  = (document.getElementById('con-last-name')?.value || '').trim();
            if (!first) { Swal.fire({ icon: 'warning', title: 'First name is required' }); return; }
            if (!last)  { Swal.fire({ icon: 'warning', title: 'Last name is required' }); return; }
            conBuildFullName();
        }
        if (conStep < CON_TOTAL_STEPS) setStep(conStep + 1);
    };

    window.conPrevStep = function () {
        if (conStep > 1) setStep(conStep - 1);
    };

    function populateReview() {
        conBuildFullName();
        var rows = [
            ['Current Name',  document.getElementById('con-current-name')?.value],
            ['File No',       document.getElementById('con-file-no')?.value],
            ['Title',         document.getElementById('con-title')?.value],
            ['First Name',    document.getElementById('con-first-name')?.value],
            ['Middle Name',   document.getElementById('con-middle-name')?.value],
            ['Last Name',     document.getElementById('con-last-name')?.value],
            ['New Full Name', document.getElementById('con-new-full-name')?.value],
            ['Phone',         document.getElementById('con-phone')?.value],
            ['Land Use',      document.getElementById('con-land-use')?.value],
            ['Plot No',       document.getElementById('con-plot-no')?.value],
            ['Plan No',       document.getElementById('con-plan-no')?.value],
            ['Location',      document.getElementById('con-location')?.value],
            ['Address',       document.getElementById('con-residential-address')?.value],
            ['Comment',       document.getElementById('con-comment')?.value],
        ];
        var container = document.getElementById('con-review-summary');
        if (container) container.innerHTML = rows.filter(function (r) { return r[1]; }).map(function (r) {
            return '<div class="flex gap-2"><strong class="shrink-0 w-32">' + safe(r[0]) + ':</strong><span>' + safe(r[1]) + '</span></div>';
        }).join('');
    }

    /* ----------------------------------------
       Modal open / close
       ---------------------------------------- */
    window.conOpenCreate = function () {
        resetForm();
        document.getElementById('con-create-modal').classList.remove('hidden');
        setStep(1);
    };

    window.conCloseCreateModal = function () {
        document.getElementById('con-create-modal').classList.add('hidden');
    };

    function resetForm() {
        var f = document.getElementById('con-form');
        if (f) f.reset();
        document.getElementById('con-file-indicator')?.classList.add('hidden');
        document.getElementById('con-file-details')?.classList.add('hidden');
        document.getElementById('con-current-name').value = '';
        document.getElementById('con-land-use').value = '';
        document.getElementById('con-new-full-name').value = '';
        document.getElementById('con-res-addr-street-other-wrapper')?.classList.add('hidden');
        document.getElementById('con-res-addr-district-other-wrapper')?.classList.add('hidden');
        document.getElementById('con-residential-address').value = '';
        selectedFileRecord = null;
        resetPrefilled();
        setStep(1);
    }

    /* ----------------------------------------
       Submit application
       ---------------------------------------- */
    window.conSubmitForm = async function () {
        conBuildFullName();
        var body = {
            file_no:                (document.getElementById('con-file-no')?.value || '').trim(),
            current_name:           (document.getElementById('con-current-name')?.value || '').trim(),
            title:                  (document.getElementById('con-title')?.value || '').trim(),
            first_name:             (document.getElementById('con-first-name')?.value || '').trim(),
            middle_name:            (document.getElementById('con-middle-name')?.value || '').trim(),
            last_name:              (document.getElementById('con-last-name')?.value || '').trim(),
            land_use:               (document.getElementById('con-land-use')?.value || '').trim(),
            plot_no:                (document.getElementById('con-plot-no')?.value || '').trim(),
            plan_no:                (document.getElementById('con-plan-no')?.value || '').trim(),
            location:               (document.getElementById('con-location')?.value || '').trim(),
            phone:                  (document.getElementById('con-phone')?.value || '').trim(),
            residential_address:    (document.getElementById('con-residential-address')?.value || '').trim(),
            comment:                (document.getElementById('con-comment')?.value || '').trim(),
        };
        try {
            var response = await fetch('/change-of-name', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(body)
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || (payload.errors ? Object.values(payload.errors)[0][0] : 'Submission failed.'));
            conCloseCreateModal();
            await Swal.fire({ icon: 'success', title: 'Application Submitted', text: payload.message, timer: 2000, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Submission Failed', text: err.message });
        }
    };

    /* ----------------------------------------
       Approve / Reject
       ---------------------------------------- */
    window.conApproveRecord = async function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        var res = await Swal.fire({
            title: 'Approve Application?',
            html: 'This will update the file name from <strong>' + safe(data.current_name || '-') + '</strong> to <strong>' + safe(data.new_full_name || '-') + '</strong> across all related records.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve & Update',
            confirmButtonColor: '#16a34a'
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('/change-of-name/' + data.id + '/approve', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Approval failed.');
            await Swal.fire({ icon: 'success', title: 'Approved & Updated', text: payload.message, timer: 2000, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message });
        }
    };

    window.conRejectRecord = async function (btn) {
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
            var response = await fetch('/change-of-name/' + data.id + '/reject', {
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
    window.conViewApplication = function (btn) {
        var data = rowData(btn);
        if (!data) return;

        // Status badge helper
        var statusColors = {
            'approved': 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'pending': 'bg-amber-50 text-amber-700 border-amber-200',
            'rejected': 'bg-red-50 text-red-700 border-red-200',
            'processing': 'bg-blue-50 text-blue-700 border-blue-200',
            'commissioned': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };
        var sc = statusColors[(data.status || '').toLowerCase()] || 'bg-slate-50 text-slate-600 border-slate-200';
        var statusBadge = '<span class="px-2.5 py-1 text-[11px] font-bold rounded-lg border ' + sc + '">' + safe(data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '-') + '</span>';

        // Build sectioned HTML
        var html = '';

        // Name change highlight card
        html += '<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4">';
        html += '<div class="flex items-center gap-2 mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg><span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Name Change</span></div>';
        html += '<div class="grid grid-cols-2 gap-4">';
        html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Old Name</p><p class="text-sm font-semibold text-slate-700">' + safe(data.current_name || '-') + '</p></div>';
        html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">New Full Name</p><p class="text-sm font-bold text-blue-700">' + safe(data.new_full_name || '-') + '</p></div>';
        html += '</div>';
        if (data.title || data.first_name || data.last_name) {
            html += '<div class="mt-3 pt-3 border-t border-blue-200/60 grid grid-cols-4 gap-3">';
            html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase mb-0.5">Title</p><p class="text-xs text-slate-600">' + safe(data.title || '-') + '</p></div>';
            html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase mb-0.5">First</p><p class="text-xs text-slate-600">' + safe(data.first_name || '-') + '</p></div>';
            html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase mb-0.5">Middle</p><p class="text-xs text-slate-600">' + safe(data.middle_name || '-') + '</p></div>';
            html += '<div><p class="text-[10px] font-semibold text-slate-400 uppercase mb-0.5">Last</p><p class="text-xs text-slate-600">' + safe(data.last_name || '-') + '</p></div>';
            html += '</div>';
        }
        html += '</div>';

        // File & Property section
        html += '<div class="grid grid-cols-2 gap-4">';

        // Left: File Details
        html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-100">';
        html += '<p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>File Details</p>';
        html += '<div class="space-y-2">';
        html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">File No</p><p class="text-sm font-mono font-bold text-slate-700">' + safe(data.file_no || '-') + '</p></div>';
        html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Land Use</p><p class="text-sm text-slate-600">' + safe(data.land_use || '-') + '</p></div>';
        html += '</div></div>';

        // Right: Property Details
        html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-100">';
        html += '<p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>Property</p>';
        html += '<div class="space-y-2">';
        html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Plot No</p><p class="text-sm text-slate-600">' + safe(data.plot_no || '-') + '</p></div>';
        html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Plan No</p><p class="text-sm text-slate-600">' + safe(data.plan_no || '-') + '</p></div>';
        html += '</div></div>';

        html += '</div>';

        // Location & Address
        if (data.location || data.residential_address) {
            html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-100 space-y-2">';
            if (data.location) {
                html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Location</p><p class="text-sm text-slate-600">' + safe(data.location) + '</p></div>';
            }
            if (data.residential_address) {
                html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Residential Address</p><p class="text-sm text-slate-600">' + safe(data.residential_address) + '</p></div>';
            }
            if (data.phone) {
                html += '<div><p class="text-[10px] text-slate-400 font-semibold uppercase">Phone</p><p class="text-sm text-slate-600">' + safe(data.phone) + '</p></div>';
            }
            html += '</div>';
        }

        // Status & Meta row
        html += '<div class="flex items-center justify-between pt-2">';
        html += '<div class="flex items-center gap-3">' + statusBadge;
        if (data.created_at) {
            html += '<span class="text-xs text-slate-400">' + safe(data.created_at.substring(0, 10)) + '</span>';
        }
        html += '</div>';
        html += '<span class="text-[10px] text-slate-400 font-mono">ID: ' + safe(String(data.id || '-')) + '</span>';
        html += '</div>';

        // Comment
        if (data.comment) {
            html += '<div class="bg-amber-50/60 border border-amber-200 rounded-xl p-3">';
            html += '<p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">Comment</p>';
            html += '<p class="text-sm text-amber-800">' + safe(data.comment) + '</p>';
            html += '</div>';
        }

        var body = document.getElementById('con-view-body');
        if (body) body.innerHTML = html || '<p class="text-slate-400 text-sm">No details available.</p>';

        // Set subtitle
        var subtitle = document.getElementById('con-view-subtitle');
        if (subtitle) subtitle.textContent = data.file_no ? 'File: ' + data.file_no : '';

        document.getElementById('con-view-modal').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    };

    /* ----------------------------------------
       Acknowledgement
       ---------------------------------------- */
    window.conViewAcknowledgement = function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        window.open('/change-of-name/' + data.id + '/acknowledgement/print', '_blank', 'width=900,height=700');
    };

    /* ----------------------------------------
       Delete
       ---------------------------------------- */
    window.conDelete = async function (btn) {
        var data = rowData(btn);
        if (!data || !data.id) return;
        var res = await Swal.fire({
            title: 'Delete Record?',
            text: 'This removes the application record only. No name changes will be reversed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            confirmButtonColor: '#dc2626'
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('/change-of-name/' + data.id, {
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

    // A stored location with its plot number taken out. A location backfilled
    // from a file record routinely opens with the plot ("1540, W/B PASS, ...");
    // that is the Plot No field repeated, and a full property location does not
    // carry it.
    function conLocationWithoutPlot(location, plotNo) {
        var raw = (location || '').toString().trim();
        if (!raw || !window.PropertyLocation) return raw;
        return window.PropertyLocation.stripPlot(raw, (plotNo || '').toString().trim());
    }

    function showFileDetails(record) {
        var box = document.getElementById('con-file-details');
        if (!box) return;
        box.classList.remove('hidden');
        document.getElementById('con-det-filename').textContent = record.FileName || record.file_name || '-';
        // The file record's stored location routinely leads with the plot
        // number, which is not part of a full property location.
        document.getElementById('con-det-location').textContent =
            conLocationWithoutPlot(record.location, record.plot_no) || '-';
        document.getElementById('con-det-lga').textContent      = record.lga || '-';
        document.getElementById('con-det-source').textContent   = record.SOURCE || record.source || '-';
    }

    /* ----------------------------------------
       Address builder
       ---------------------------------------- */
    function conIsOther(val) { return (val || '').trim().toLowerCase() === 'other'; }

    function conToggleOther(selectEl, wrapperId, otherEl) {
        var wrapper = document.getElementById(wrapperId);
        if (!wrapper || !selectEl) return;
        var show = conIsOther(selectEl.value);
        wrapper.classList.toggle('hidden', !show);
        if (!show && otherEl) otherEl.value = '';
    }

    function conSelectedOrOther(selectEl, otherEl) {
        if (!selectEl) return '';
        if (conIsOther(selectEl.value)) return (otherEl?.value || '').trim();
        return (selectEl.value || '').trim();
    }

    function conBuildAddress() {
        var prefix = 'con_res_addr';
        var house   = (document.getElementById(prefix + '_house')?.value || '').trim();
        var plot    = (document.getElementById(prefix + '_plot')?.value || '').trim();
        var street  = document.getElementById(prefix + '_street');
        var streetO = document.getElementById(prefix + '_street_other');
        var dist    = document.getElementById(prefix + '_district');
        var distO   = document.getElementById(prefix + '_district_other');
        var lga     = (document.getElementById(prefix + '_lga')?.value || '').trim();
        var state   = (document.getElementById(prefix + '_state')?.value || '').trim();
        var streetVal = conSelectedOrOther(street, streetO);
        var distVal   = conSelectedOrOther(dist, distO);
        // House No and Plot No are separate entries. A house number belongs
        // in front of the street it is on ("12A ZOO ROAD"), while a plot number
        // is a cadastral reference that stands on its own, so the two cannot
        // simply be concatenated. The plot is labelled in the output because
        // "12A ZOO ROAD, 45, NASSARAWA" reads as a second house number.
        var streetLine = [house, streetVal].filter(Boolean).join(' ');
        var plotLine = plot ? (/^plot\b/i.test(plot) ? plot : 'PLOT ' + plot) : '';
        var built = [streetLine, plotLine, distVal, lga, state].filter(Boolean).join(', ').toUpperCase();
        var output = document.getElementById('con-residential-address');
        if (output) output.value = built;
    }

    function conInitAddressBuilder() {
        var prefix = 'con_res_addr';
        var dashP  = 'con-res-addr';
        var houseEl   = document.getElementById(prefix + '_house');
        var plotEl    = document.getElementById(prefix + '_plot');
        var streetEl  = document.getElementById(prefix + '_street');
        var streetOEl = document.getElementById(prefix + '_street_other');
        var distEl    = document.getElementById(prefix + '_district');
        var distOEl   = document.getElementById(prefix + '_district_other');
        var lgaEl     = document.getElementById(prefix + '_lga');
        var stateEl   = document.getElementById(prefix + '_state');

        [houseEl, plotEl, streetEl, streetOEl, distEl, distOEl, lgaEl, stateEl].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', conBuildAddress);
            el.addEventListener('change', conBuildAddress);
        });

        if (streetEl) streetEl.addEventListener('change', function () {
            conToggleOther(streetEl, dashP + '-street-other-wrapper', streetOEl);
        });
        if (distEl) distEl.addEventListener('change', function () {
            conToggleOther(distEl, dashP + '-district-other-wrapper', distOEl);
        });
    }

    /* ----------------------------------------
       Name composition listeners
       ---------------------------------------- */
    function conInitNameComposition() {
        ['con-title', 'con-first-name', 'con-middle-name', 'con-last-name'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', conBuildFullName);
                el.addEventListener('change', conBuildFullName);
            }
        });
    }

    /* ----------------------------------------
       Dropdown menu toggle
       ---------------------------------------- */
    window.conToggleDropdown = function (button, event) {
        event.stopPropagation();
        var menu = button.closest('.dropdown-container').querySelector('.con-action-menu');
        var isHidden = menu.classList.contains('hidden');

        // Close all open menus first
        document.querySelectorAll('.con-action-menu').forEach(function (m) { m.classList.add('hidden'); });

        if (isHidden) {
            menu.classList.remove('hidden');
            var rect = button.getBoundingClientRect();
            menu.style.left = (rect.left - menu.offsetWidth + rect.width) + 'px';
            if (rect.top - menu.offsetHeight > 0) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            } else {
                menu.style.top = (rect.bottom + 5) + 'px';
            }
        }
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function () {
        document.querySelectorAll('.con-action-menu').forEach(function (m) { m.classList.add('hidden'); });
    });

    // Close dropdown when a menu item is clicked
    document.addEventListener('click', function (e) {
        if (e.target.closest('.con-action-menu li')) {
            document.querySelectorAll('.con-action-menu').forEach(function (m) { m.classList.add('hidden'); });
        }
    });

    /* ----------------------------------------
       Init
       ---------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {

        // DataTables
        var dtOptions = {
            dom: '<"overflow-x-auto"t><"px-4 py-3 flex items-center justify-between border-t border-slate-100"ip>',
            pageLength: parseInt(document.getElementById('con-length')?.value || '50', 10),
            lengthChange: false,
            language: {
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                emptyTable: 'No records found.',
                paginate: { previous: 'Prev', next: 'Next' }
            }
        };

        var pendingTable = $('#con-pending-table').DataTable(Object.assign({}, dtOptions, {
            order: [[6, 'desc']],
            columnDefs: [{ orderable: false, targets: [7] }]
        }));

        var approvedTable = $('#con-approved-table').DataTable(Object.assign({}, dtOptions, {
            order: [[5, 'desc']],
            columnDefs: [{ orderable: false, targets: [6] }]
        }));

        document.getElementById('con-search')?.addEventListener('input', function () {
            pendingTable.search(this.value).draw();
            approvedTable.search(this.value).draw();
        });
        document.getElementById('con-length')?.addEventListener('change', function () {
            var len = parseInt(this.value, 10);
            pendingTable.page.len(len).draw();
            approvedTable.page.len(len).draw();
        });

        // Init the global file-number modal
        if (window.GlobalFileNoModal) GlobalFileNoModal.init();

        // Address builder
        conInitAddressBuilder();

        // Name composition
        conInitNameComposition();

        // File Number Selector button
        document.getElementById('con-select-file-btn')?.addEventListener('click', function () {
            if (!window.GlobalFileNoModal) return;
            GlobalFileNoModal.open({
                callback: function (data) {
                    if (!data.fileNumber) return;
                    var fn = data.fileNumber.toUpperCase();
                    document.getElementById('con-file-no').value = fn;
                    document.getElementById('con-file-indicator')?.classList.remove('hidden');
                    // Auto-fill land use
                    document.getElementById('con-land-use').value = detectLandUseLabel(fn);
                    // Auto-fill current name and file details from record
                    if (data.record) {
                        showFileDetails(data.record);
                        var rec = data.record;
                        var currentName = rec.FileName || rec.file_name || '';
                        var nameEl  = document.getElementById('con-current-name');
                        var locEl   = document.getElementById('con-location');
                        var plotEl  = document.getElementById('con-plot-no');
                        if (nameEl && currentName) { nameEl.value = currentName; }
                        if (locEl  && !locEl.value  && rec.location) { locEl.value  = conLocationWithoutPlot(rec.location, rec.plot_no); }
                        if (plotEl && !plotEl.value && rec.plot_no)   { plotEl.value = rec.plot_no;  }
                        greyOutPrefilled();
                    }
                }
            });
        });

        // Lucide icons re-init for dynamically rendered content
        if (window.lucide) lucide.createIcons();
    });

})();
