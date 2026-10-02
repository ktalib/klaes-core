/* Title Status */
'use strict';

let tsTable        = null;
let tsEditId       = null;
let tsSelectedType = '';

/* ── Type themes ── */
const TS_TYPE_THEMES = {
    'Withdrawal (Application)': {
        gradient: 'linear-gradient(135deg,#312e81 0%,#4338ca 60%,#6366f1 100%)',
        icon:     'undo-2',
        label:    'Withdrawal · New Files',
    },
    'Cancellation (RofO)': {
        gradient: 'linear-gradient(135deg,#78350f 0%,#d97706 60%,#fbbf24 100%)',
        icon:     'ban',
        label:    'Cancellation · Existing or New Files',
    },
    'Revoke (CofO)': {
        gradient: 'linear-gradient(135deg,#7f1d1d 0%,#dc2626 60%,#f87171 100%)',
        icon:     'shield-off',
        label:    'Revoke · Existing Files',
    },
    'Litigation': {
        gradient: 'linear-gradient(135deg,#881337 0%,#e11d48 60%,#fb7185 100%)',
        icon:     'scale',
        label:    'Litigation · Existing or New Files',
    },
    'Amendment/Reconsideration (Application/RofO/CofO)': {
        gradient: 'linear-gradient(135deg,#064e3b 0%,#059669 60%,#34d399 100%)',
        icon:     'file-edit',
        label:    'Amendment / Reconsideration',
    },
    'Surrender': {
        gradient: 'linear-gradient(135deg,#0c4a6e 0%,#0284c7 60%,#38bdf8 100%)',
        icon:     'flag',
        label:    'Surrender · Voluntary Relinquishment',
    },
    'Re-grant': {
        gradient: 'linear-gradient(135deg,#134e4a 0%,#0d9488 60%,#2dd4bf 100%)',
        icon:     'refresh-cw',
        label:    'Re-grant',
    },
    'Resettlement': {
        gradient: 'linear-gradient(135deg,#4c1d95 0%,#7c3aed 60%,#a78bfa 100%)',
        icon:     'map-pin',
        label:    'Resettlement · Existing or New Files',
    },
};

/* ── Verbs used in the unified remark template ── */
const TS_TYPE_VERB = {
    'Withdrawal (Application)':                            'Withdrawal',
    'Cancellation (RofO)':                                 'Cancellation',
    'Revoke (CofO)':                                       'Revocation',
    'Litigation':                                          'Litigation',
    'Amendment/Reconsideration (Application/RofO/CofO)':   'Amendment/Reconsideration',
    'Surrender':                                           'Surrender',
    'Re-grant':                                            'Re-grant',
    'Resettlement':                                        'Resettlement',
};

/* ────────────────────────────── Init ────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    tsTable = $('#title-status-table').DataTable({
        paging: false, ordering: true, info: true, searching: false,
    });

    $('#ts-search').on('input', function () { reloadTsPage(); });
    $('#ts-length').on('change', function () { reloadTsPage(); });

    if (window.GlobalFileNoModal) GlobalFileNoModal.init();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

function reloadTsPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('search', $('#ts-search').val() || '');
    params.set('limit',  $('#ts-length').val() || '50');
    params.set('url', TS_URL);
    if (page) params.set('page', page);
    window.location.href = window.location.pathname + '?' + params.toString();
}

/* ────────────────── Type Selection ────────────────── */
function tsOpenTypeSelect() {
    document.getElementById('ts-type-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsCloseTypeModal() {
    document.getElementById('ts-type-modal').classList.add('hidden');
}

function tsSelectType(type) {
    tsCloseTypeModal();
    tsEditId = null;
    tsClearForm();
    tsSelectedType = type;
    tsApplyTheme(type);
    tsToggleCofoSection(type);
    tsToggleSeeSection(type);
    const defaultInitiator = (typeof TS_INITIATED_BY_DEFAULT !== 'undefined' && TS_INITIATED_BY_DEFAULT[type]) || null;
    tsApplyInitiatedByOptions(type, defaultInitiator);
    tsRefreshRemark();
    document.getElementById('ts-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/* Rebuild the Initiated By <select> based on the type's allowed options.
   Single-option types (Withdrawal/Revoke/Surrender) lock the value and gray it out. */
function tsApplyInitiatedByOptions(type, preselect) {
    const select = document.getElementById('ts-initiated_by');
    if (!select) return;

    const options = (TS_INITIATED_BY_BY_TYPE && TS_INITIATED_BY_BY_TYPE[type]) || ['Ministry', 'Allottee'];

    select.innerHTML = '';

    if (options.length > 1) {
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '— Select —';
        select.appendChild(blank);
    }

    options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        select.appendChild(o);
    });

    const isLocked = options.length === 1;
    if (isLocked) {
        select.value = options[0];
        select.disabled = true;
        select.classList.add('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
    } else {
        select.disabled = false;
        select.classList.remove('bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
        if (preselect && options.includes(preselect)) {
            select.value = preselect;
        }
    }
}

/* Show the Certificate of Occupancy detail fields only for CofO revocation,
   since those fields map directly onto the Certificate of Revocation. */
function tsToggleCofoSection(type) {
    const section = document.getElementById('ts-cofo-section');
    if (!section) return;
    if (type === 'Revoke (CofO)') {
        section.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        section.classList.add('hidden');
    }
}

/* Show the "See / Additional File Number" selector only for Re-grant. */
function tsToggleSeeSection(type) {
    const section = document.getElementById('ts-see-section');
    if (!section) return;
    if (type === 'Re-grant') {
        section.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        section.classList.add('hidden');
    }
}

/* Open the global file-number picker for the additional ("See") file. */
function tsOpenSeeFileSearch() {
    if (!window.GlobalFileNoModal) return;
    GlobalFileNoModal.open({
        callback: function (data) {
            if (!data.fileNumber) return;
            document.getElementById('ts-see_fileno').value = data.fileNumber;
            document.getElementById('ts-see-no-display').textContent = data.fileNumber;
            document.getElementById('ts-see-placeholder').classList.add('hidden');
            document.getElementById('ts-see-selected').classList.remove('hidden');
            document.getElementById('ts-see-card').classList.add('selected');
            if (typeof lucide !== 'undefined') lucide.createIcons();
            tsRefreshRemark();
        }
    });
}

function tsClearSeeSelection(event) {
    event.stopPropagation();
    const el = document.getElementById('ts-see_fileno');
    if (el) el.value = '';
    document.getElementById('ts-see-no-display').textContent = '';
    document.getElementById('ts-see-placeholder').classList.remove('hidden');
    document.getElementById('ts-see-selected').classList.add('hidden');
    document.getElementById('ts-see-card').classList.remove('selected');
    tsRefreshRemark();
}

function tsApplyTheme(type) {
    const theme  = TS_TYPE_THEMES[type] || TS_TYPE_THEMES['Withdrawal (Application)'];
    const header = document.getElementById('ts-modal-header');
    if (header) header.style.background = theme.gradient;
    const lbl = document.getElementById('ts-modal-type-label');
    if (lbl) lbl.textContent = theme.label;
    const icon = document.getElementById('ts-modal-icon');
    if (icon) {
        icon.setAttribute('data-lucide', theme.icon);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function tsGoBack() {
    document.getElementById('ts-modal').classList.add('hidden');
    tsOpenTypeSelect();
}

function tsOpenCreate() { tsOpenTypeSelect(); }

/* ────────────────── File Search ────────────────── */
function tsOpenFileSearch() {
    if (!window.GlobalFileNoModal) return;
    GlobalFileNoModal.open({
        callback: function (data) {
            if (!data.fileNumber) return;
            tsFetchFileInfo(data.fileNumber);
        }
    });
}

function tsFetchFileInfo(fileNo) {
    fetch(`${TS_FILE_INFO_URL}?file_no=${encodeURIComponent(fileNo)}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': TS_CSRF },
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            Swal.fire({ icon: 'warning', title: 'Not Found', text: res.message || 'File not found in records.' });
            return;
        }
        const d = res.data;

        // Store hidden values
        document.getElementById('ts-file_no').value        = d.file_no;
        document.getElementById('ts-file_title').value     = d.file_title || '';
        document.getElementById('ts-applicant_name').value = d.current_holder || d.file_title || '';
        document.getElementById('ts-plot_no').value        = d.plot_no || '';
        document.getElementById('ts-house_no').value       = d.house_no || '';
        document.getElementById('ts-street_name').value    = d.street_name || '';
        document.getElementById('ts-location').value       = d.location || '';
        document.getElementById('ts-land_use').value       = d.land_use || '';
        document.getElementById('ts-district').value       = d.district || '';
        document.getElementById('ts-lga').value            = d.lga || '';
        document.getElementById('ts-state').value          = d.state || '';
        document.getElementById('ts-source_table').value   = d.source_table || '';
        document.getElementById('ts-source_id').value      = d.source_id || '';

        // Show file number in card
        document.getElementById('ts-file-no-display').textContent = d.file_no;
        document.getElementById('ts-file-placeholder').classList.add('hidden');
        document.getElementById('ts-file-selected').classList.remove('hidden');
        document.getElementById('ts-file-card').classList.add('selected');

        // Populate summary
        const setText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val || '—';
        };
        setText('ts-sum-title',    d.file_title);
        setText('ts-sum-holder',   d.current_holder);
        setText('ts-sum-plot',     d.plot_no);
        setText('ts-sum-location', d.location);
        setText('ts-sum-landuse',  d.land_use);
        setText('ts-sum-lga',      d.lga);
        document.getElementById('ts-file-summary').classList.remove('hidden');

        tsRefreshRemark();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Could not fetch file info.' }));
}

function tsClearFileSelection(event) {
    event.stopPropagation();
    ['ts-file_no','ts-file_title','ts-applicant_name','ts-plot_no','ts-house_no',
     'ts-street_name','ts-location','ts-land_use','ts-district','ts-lga','ts-state',
     'ts-source_table','ts-source_id']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    document.getElementById('ts-file-placeholder').classList.remove('hidden');
    document.getElementById('ts-file-selected').classList.add('hidden');
    document.getElementById('ts-file-card').classList.remove('selected');
    document.getElementById('ts-file-summary').classList.add('hidden');
    document.getElementById('ts-remark').value = '';
}

/* ────────────────── Auto Remark ──────────────────
   Template: "[Status type] was initiated by [Ministry | Allottee name] on [Time/Date] due to [Reason]"
   When "Allottee" is selected, the actual holder name (applicant_name / file_title) is used.
*/
function tsRefreshRemark() {
    const initiatedBy   = (document.getElementById('ts-initiated_by')?.value   || '').trim();
    const reason        = (document.getElementById('ts-reason')?.value         || '').trim();
    const applicantName = (document.getElementById('ts-applicant_name')?.value || '').trim();
    const fileTitle     = (document.getElementById('ts-file_title')?.value     || '').trim();
    const fileNo        = (document.getElementById('ts-file_no')?.value        || '').trim();

    if (tsSelectedType === 'Re-grant') {
        const seeFileno = (document.getElementById('ts-see_fileno')?.value || '').trim();
        document.getElementById('ts-remark').value = seeFileno
            ? `This File has been Re-granted from ${seeFileno}`
            : 'This File has been Re-granted';
        return;
    }

    const verb = TS_TYPE_VERB[tsSelectedType] || tsSelectedType || '[Status type]';

    let initiator;
    if (initiatedBy === 'Allottee' || initiatedBy === 'Applicant') {
        const name = applicantName || fileTitle;
        initiator  = name ? `${name} (${initiatedBy})` : initiatedBy;
    } else if (initiatedBy) {
        initiator = initiatedBy;
    } else {
        initiator = '[Ministry/Allottee]';
    }

    const fileRef    = fileNo ? ` FileNo ${fileNo}` : '';
    const reasonText = reason || '[Reason]';

    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const datetime = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;

    document.getElementById('ts-remark').value =
        `${verb}${fileRef} was initiated by ${initiator} on ${datetime} due to ${reasonText}`;
}

/* ────────────────── Edit ────────────────── */
function tsOpenEdit(btn) {
    const record = JSON.parse(btn.closest('tr').dataset.record);
    tsEditId       = record.id;
    tsSelectedType = record.title_type ?? '';

    tsApplyTheme(tsSelectedType);
    tsToggleCofoSection(tsSelectedType);
    tsToggleSeeSection(tsSelectedType);
    tsApplyInitiatedByOptions(tsSelectedType, record.initiated_by);

    // Additional ("See") file number
    const seeInput = document.getElementById('ts-see_fileno');
    if (seeInput) seeInput.value = record.see_fileno ?? '';
    if (record.see_fileno) {
        document.getElementById('ts-see-no-display').textContent = record.see_fileno;
        document.getElementById('ts-see-placeholder').classList.add('hidden');
        document.getElementById('ts-see-selected').classList.remove('hidden');
        document.getElementById('ts-see-card').classList.add('selected');
    }

    // File card
    document.getElementById('ts-file_no').value          = record.file_no ?? '';
    document.getElementById('ts-source_table').value      = record.source_table ?? '';
    document.getElementById('ts-source_id').value         = record.source_id ?? '';
    document.getElementById('ts-file-no-display').textContent    = record.file_no ?? '';
    const fileTitleDisplay = document.getElementById('ts-file-title-display');
    if (fileTitleDisplay) fileTitleDisplay.textContent = record.file_title ?? '';
    document.getElementById('ts-file-placeholder').classList.add('hidden');
    document.getElementById('ts-file-selected').classList.remove('hidden');
    document.getElementById('ts-file-card').classList.add('selected');

    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    setVal('ts-file_title',          record.file_title);
    setVal('ts-applicant_name',      record.applicant_name);
    setVal('ts-cofo_number',         record.cofo_number);
    setVal('ts-title_no',            record.title_no);
    setVal('ts-reg_page',            record.reg_page);
    setVal('ts-reg_volume',          record.reg_volume);
    // Every property field the form submits must be restored here — an unset hidden
    // input submits as blank and would wipe the stored value on update.
    setVal('ts-plot_no',             record.plot_no);
    setVal('ts-house_no',            record.house_no);
    setVal('ts-street_name',         record.street_name);
    setVal('ts-location',            record.location);
    setVal('ts-land_use',            record.land_use);
    setVal('ts-district',            record.district);
    setVal('ts-lga',                 record.lga);
    setVal('ts-state',               record.state);
    setVal('ts-date_of_issue',       record.date_of_issue ? String(record.date_of_issue).substring(0, 10) : '');
    setVal('ts-date_of_expiry',      record.date_of_expiry);
    setVal('ts-initiated_by',        record.initiated_by);
    setVal('ts-reason',              record.reason);
    setVal('ts-remark',              record.remark);

    document.getElementById('ts-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/* ────────────────── View ────────────────── */
function tsView(btn) {
    const record = JSON.parse(btn.closest('tr').dataset.record);
    const body   = document.getElementById('ts-view-body');

    const row = (label, value) => `
        <div class="flex gap-2 border-b border-slate-50 pb-2">
            <span class="w-44 shrink-0 text-xs font-semibold text-slate-500 uppercase tracking-wide">${label}</span>
            <span class="text-slate-800">${value || '—'}</span>
        </div>`;

    body.innerHTML = [
        row('File No',           record.file_no),
        row('See (Add. File No)', record.see_fileno),
        row('File Title',        record.file_title),
        row('Applicant',         record.applicant_name),
        row('Title Type',        record.title_type),
        row('CofO No',           record.cofo_number),
        row('Registered as No',  record.title_no),
        row('At Page',           record.reg_page),
        row('In Volume',         record.reg_volume),
        row('Plot No',           record.plot_no),
        row('Location',          record.location),
        row('Land Use',          record.land_use),
        row('Date of Issue',     record.date_of_issue),
        row('Date of Expiry',    record.date_of_expiry),
        row('Initiated By',      record.initiated_by),
        row('Reason',            record.reason),
        row('Remark',            record.remark),
        row('Status',            record.status),
    ].join('');

    document.getElementById('ts-view-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsCloseViewModal() {
    document.getElementById('ts-view-modal').classList.add('hidden');
}

/* ────────────────── Submit ────────────────── */
function tsSubmit() {
    const fileNo = document.getElementById('ts-file_no').value.trim();
    if (!fileNo) {
        Swal.fire({ icon: 'warning', title: 'File Required', text: 'Please select a file number first.' });
        return;
    }

    const url    = tsEditId ? `${TS_BASE_URL}/${tsEditId}` : TS_STORE_URL;
    const method = tsEditId ? 'PUT' : 'POST';

    const g = id => (document.getElementById(id)?.value || '').trim();

    const initiatedBy = g('ts-initiated_by');
    const reason      = g('ts-reason');

    if (!initiatedBy) {
        Swal.fire({ icon: 'warning', title: 'Initiated By Required', text: 'Please select who initiated this action (Ministry or Allottee).' });
        return;
    }
    if (!reason) {
        Swal.fire({ icon: 'warning', title: 'Reason Required', text: 'Please enter a reason for this action.' });
        return;
    }

    const payload = {
        url:             TS_URL,
        title_type:      tsSelectedType,
        file_no:         fileNo,
        see_fileno:      g('ts-see_fileno'),
        source_table:    g('ts-source_table'),
        source_id:       g('ts-source_id'),
        file_title:      g('ts-file_title'),
        applicant_name:  g('ts-applicant_name'),
        plot_no:         g('ts-plot_no'),
        house_no:        g('ts-house_no'),
        street_name:     g('ts-street_name'),
        location:        g('ts-location'),
        land_use:        g('ts-land_use'),
        district:        g('ts-district'),
        lga:             g('ts-lga'),
        state:           g('ts-state'),
        cofo_number:     g('ts-cofo_number'),
        title_no:        g('ts-title_no'),
        reg_page:        g('ts-reg_page'),
        reg_volume:      g('ts-reg_volume'),
        date_of_issue:   g('ts-date_of_issue'),
        initiated_by:    initiatedBy,
        reason:          reason,
        remark:          g('ts-remark'),
    };

    fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': TS_CSRF,
            'Accept':       'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            const msgs = res.errors
                ? Object.values(res.errors).flat().join('\n')
                : (res.message || 'Something went wrong.');
            Swal.fire({ icon: 'error', title: 'Error', text: msgs });
            return;
        }
        tsCloseModal();
        const detail = res.data?.remark || res.message;
        Swal.fire({ icon: 'success', title: 'Saved', text: detail, timer: 4000, showConfirmButton: true, confirmButtonText: 'OK' })
            .then(() => reloadTsPage());
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' }));
}

/* ────────────────── Approve / Reject / Delete ────────────────── */
function tsApprove(id) {
    Swal.fire({
        title: 'Approve Application?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Yes, Approve', confirmButtonColor: '#10b981',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}/approve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

function tsReject(id) {
    Swal.fire({
        title: 'Reject Application', input: 'textarea',
        inputLabel: 'Reason (optional)', inputPlaceholder: 'Enter rejection reason...',
        showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ef4444',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ reason: result.value || '' }),
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

function tsDelete(id) {
    Swal.fire({
        title: 'Delete Application?', text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Yes, Delete', confirmButtonColor: '#ef4444',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

/* ────────────────── Action Menu ────────────────── */
function tsToggleMenu(btn, event) {
    if (event) event.stopPropagation();
    const wrapper = btn.closest('.ts-actions');
    if (!wrapper) return;
    const menu = wrapper.querySelector('.ts-actions-menu');
    if (!menu) return;

    const isOpen = !menu.classList.contains('hidden');
    tsCloseAllMenus();
    if (isOpen) return;

    // Fixed-position so the table's overflow doesn't clip it
    const rect      = btn.getBoundingClientRect();
    const menuWidth = 176; // matches w-44
    menu.style.position = 'fixed';
    menu.style.top      = (rect.bottom + 4) + 'px';
    menu.style.left     = Math.max(8, rect.right - menuWidth) + 'px';
    menu.style.right    = 'auto';
    menu.classList.remove('hidden');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsCloseAllMenus() {
    document.querySelectorAll('.ts-actions-menu').forEach(m => {
        m.classList.add('hidden');
        m.style.position = '';
        m.style.top = m.style.left = m.style.right = '';
    });
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.ts-actions')) tsCloseAllMenus();
});
document.addEventListener('scroll', tsCloseAllMenus, true);
window.addEventListener('resize', tsCloseAllMenus);

/* ────────────────── Helpers ────────────────── */
function tsCloseModal() {
    document.getElementById('ts-modal').classList.add('hidden');
    tsClearForm();
}

function tsClearForm() {
    ['ts-file_no','ts-see_fileno','ts-file_title','ts-applicant_name','ts-plot_no','ts-location',
     'ts-land_use','ts-district','ts-lga','ts-source_table','ts-source_id',
     'ts-cofo_number','ts-title_no','ts-reg_page','ts-reg_volume','ts-date_of_issue',
     'ts-initiated_by','ts-reason','ts-remark']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    document.getElementById('ts-file-placeholder').classList.remove('hidden');
    document.getElementById('ts-file-selected').classList.add('hidden');
    document.getElementById('ts-file-card').classList.remove('selected');
    document.getElementById('ts-file-summary').classList.add('hidden');
    tsClearSeeSelection({ stopPropagation() {} });
    tsToggleCofoSection('');
    tsToggleSeeSection('');

    tsEditId       = null;
    tsSelectedType = '';
}
