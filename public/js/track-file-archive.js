/**
 * Track File Archive JavaScript
 * Handles file number selection, file details display, and archive search functionality
 */

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initializeGlobalFileNoModal();
    initializeQrScanner();
    initializeLucideIcons();
});

/**
 * Initialize Global File Number Modal integration
 */
function initializeGlobalFileNoModal() {
    if (typeof GlobalFileNoModal === 'undefined') {
        console.error('GlobalFileNoModal is not loaded');
        return;
    }

    GlobalFileNoModal.init();
    
    // Set up the file number selector button
    const selectorBtn = document.getElementById('archive-fileno-selector-btn');
    if (selectorBtn) {
        selectorBtn.addEventListener('click', function() {
            GlobalFileNoModal.open({
                targetFields: ['archive-file-no'],
                callback: handleFileNumberSelection
            });
        });
    }
}

/* ─── QR Scanner (hardware keyboard-wedge device) ────────────────────────── */

/**
 * Wire up the search-mode toggle and the QR scan input
 */
function initializeQrScanner() {
    document.querySelectorAll('.archive-mode-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            setArchiveSearchMode(btn.dataset.mode);
        });
    });

    const qrInput = document.getElementById('archive-qr-input');
    if (!qrInput) return;

    // Hardware scanners type the payload then send Enter
    qrInput.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        submitArchiveScan(qrInput.value);
    });
}

/**
 * Resolve a scanned payload to a file number and load its record
 * @param {string} rawValue - Raw text delivered by the scanner
 */
function submitArchiveScan(rawValue) {
    const qrInput = document.getElementById('archive-qr-input');
    const fileNumber = extractFileNumberFromQr(rawValue);

    if (!fileNumber) {
        updateArchiveScanStatus('QR code did not contain a file number.', 'error');
        if (qrInput) qrInput.select();
        return;
    }

    updateArchiveScanStatus('Scanned: ' + fileNumber, 'success');
    loadArchiveFileNumber(fileNumber);

    // Clear and refocus so the next scan lands here too
    if (qrInput) {
        qrInput.value = '';
        qrInput.focus();
    }
}

/**
 * Switch between "File Number" and "QR Scan" input modes
 * @param {string} mode - 'fileno' or 'qr'
 */
function setArchiveSearchMode(mode) {
    document.querySelectorAll('.archive-mode-btn').forEach(function(btn) {
        const active = btn.dataset.mode === mode;
        btn.classList.toggle('bg-blue-600', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('bg-white', !active);
        btn.classList.toggle('text-gray-700', !active);
    });

    const panel = document.getElementById('archive-qr-panel');
    if (panel) panel.classList.toggle('hidden', mode !== 'qr');

    if (mode === 'qr') {
        const qrInput = document.getElementById('archive-qr-input');
        if (qrInput) {
            qrInput.value = '';
            qrInput.focus();
        }
        updateArchiveScanStatus('Waiting for scan — keep this box focused', 'info');
    }

    initializeLucideIcons();
}

/**
 * Pull a file number out of a QR payload (plain text or JSON)
 * @param {string} decodedText
 * @returns {string} file number, or '' if none found
 */
function extractFileNumberFromQr(decodedText) {
    const raw = (decodedText || '').trim();
    if (!raw) return '';

    if (raw.charAt(0) === '{') {
        try {
            const payload = JSON.parse(raw);
            return (payload.file_number
                || payload.fileNumber
                || payload.NewKANGISFileNo
                || payload.kangisFileNo
                || payload.mlsfNo
                || payload.tracking_id
                || '').toString().trim();
        } catch (e) {
            // Not JSON after all — fall through to the raw value
        }
    }

    return raw;
}

function updateArchiveScanStatus(message, type) {
    const el = document.getElementById('archive-qr-status');
    if (!el) return;

    el.textContent = message;
    el.className = 'mt-3 text-sm text-center px-3 py-2 border rounded-md '
        + (type === 'success' ? 'text-green-700 bg-green-50 border-green-200'
        : type === 'error' ? 'text-red-700 bg-red-50 border-red-200'
        : 'text-gray-600 bg-gray-50 border-gray-200');
}

/**
 * Load a file number (or scanned tracking ID) and populate every File Details
 * field from the archive endpoint. Shared by QR scanning, manual entry and the
 * file number picker — one request, one authoritative payload.
 * @param {string} fileNumber
 */
async function loadArchiveFileNumber(fileNumber) {
    resetFileDetails();

    const fileNoInput = document.getElementById('archive-file-no');
    if (fileNoInput) fileNoInput.value = fileNumber;

    showFileNumberBadge(fileNumber);
    setArchiveLoadingState();

    try {
        const response = await fetch(
            `/api/track-file-archive/details?file_number=${encodeURIComponent(fileNumber)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const payload = await response.json();

        if (!response.ok || !payload || !payload.success || !payload.data) {
            setArchiveNotFoundState((payload && payload.message) || 'No archive record found for this file.');
            return;
        }

        applyArchiveDetails(payload.data);
    } catch (error) {
        console.error('Error loading archive details:', error);
        setArchiveNotFoundState('Could not load archive details.');
    }
}

/**
 * Put every badge into a loading state while the lookup is in flight
 */
function setArchiveLoadingState() {
    ['detail-tracking-id', 'detail-land-use', 'detail-registry', 'detail-current-location',
     'detail-shelf', 'detail-rack', 'detail-full-shelf-rack', 'detail-status'
    ].forEach(function(id) {
        updateBadge(id, 'Loading...', 'gray');
    });

    const fileNameInput = document.getElementById('archive-file-name');
    if (fileNameInput) fileNameInput.value = 'Loading...';

    setFileHistoryTimelineLoading();
}

/**
 * Nothing resolved — leave the land use we can infer from the number itself
 * and blank everything that has to come from a record.
 * @param {string} message
 */
function setArchiveNotFoundState(message) {
    resetBadgesToNA();
    updateBadge('detail-registry', 'N/A', 'gray');
    updateBadge('detail-current-location', 'N/A', 'gray');
    updateBadge('detail-status', message || 'Not found', 'gray');

    const fileNameInput = document.getElementById('archive-file-name');
    if (fileNameInput) fileNameInput.value = '';

    const fileNumber = (document.getElementById('archive-file-no') || {}).value || '';
    if (fileNumber) applyDerivedLandUseFromFileNumber(fileNumber);

    setFileHistoryTimelineFallback(message || 'No movement history available.');
}

/**
 * Render a resolved archive payload into the File Details card
 * @param {Object} d - Data block from /api/track-file-archive/details
 */
function applyArchiveDetails(d) {
    // The endpoint accepts a tracking ID too, so reflect back the file number
    // it actually resolved to.
    const fileNoInput = document.getElementById('archive-file-no');
    if (d.file_number && fileNoInput && fileNoInput.value !== d.file_number) {
        fileNoInput.value = d.file_number;
        showFileNumberBadge(d.file_number);
    }

    const fileNameInput = document.getElementById('archive-file-name');
    if (fileNameInput) {
        fileNameInput.value = d.file_title || '';
        fileNameInput.classList.toggle('text-gray-900', Boolean(d.file_title));
        fileNameInput.classList.toggle('text-gray-500', !d.file_title);
    }

    updateBadge('detail-tracking-id', d.tracking_id || 'Not Tracked', d.tracking_id ? 'blue' : 'gray');

    if (d.land_use_type) {
        updateBadge('detail-land-use', String(d.land_use_type).toUpperCase(), getLandUseColor(d.land_use_type));
    } else {
        applyDerivedLandUseFromFileNumber(d.file_number || '');
    }

    updateBadge('detail-registry', d.registry || 'N/A', 'purple');
    updateBadge('detail-current-location', composeDestinationLabel(d), 'cyan');

    // "Rack" holds the letter, "Shelf" the number — note the two badge ids are
    // swapped relative to their labels in the markup.
    updateBadge('detail-shelf', d.rack || 'N/A', 'orange');
    updateBadge('detail-rack', d.shelf || 'N/A', 'amber');
    updateBadge('detail-full-shelf-rack', d.rack_shelf || 'N/A', 'indigo');

    const fullShelfBadge = document.getElementById('detail-full-shelf-rack');
    if (fullShelfBadge) {
        fullShelfBadge.title = d.shelf_is_derived
            ? 'Derived from the shelf/rack range map — not a recorded shelf location.'
            : '';
        if (d.shelf_is_derived && d.rack_shelf) {
            fullShelfBadge.textContent = d.rack_shelf + ' (derived)';
        }
    }

    applyArchiveStatus(d);
    renderArchiveTimeline(d);
}

/**
 * Destination (Location) badge text: who currently holds the file and in which
 * department/office. Falls back to the resolved current_location when the
 * holder/department are not known.
 * @param {Object} d - Archive detail payload
 * @returns {string}
 */
function composeDestinationLabel(d) {
    const holder = (d.holder || '').toString().trim();
    const department = ((d.tracker && (d.tracker.department || d.tracker.current_office_name)) || d.current_location || '')
        .toString().trim();

    const parts = [holder, department].filter(Boolean);
    return parts.length ? parts.join(' — ') : 'N/A';
}

/**
 * Compose the File Log History badge from status, holder and location
 * @param {Object} d - Archive detail payload
 */
function applyArchiveStatus(d) {
    const statusLabel = formatStatusText(d.status || '') || 'Unknown';
    const parts = [statusLabel];

    if (d.holder) parts.push('Held by ' + d.holder);
    if (d.duration_with_holder) parts.push(d.duration_with_holder);

    const isOverdue = Boolean(d.tracker && d.tracker.is_overdue);
    updateBadge('detail-status', parts.join(' • '), getArchiveStatusColor(d.status, isOverdue));
}

/**
 * Badge colour for a resolver status
 * @param {string} status - Resolver status (IN_ARCHIVE, IN_TRANSIT, ...)
 * @param {boolean} isOverdue
 */
function getArchiveStatusColor(status, isOverdue) {
    if (isOverdue) return 'red';

    switch (String(status || '').toUpperCase()) {
        case 'IN_ARCHIVE':   return 'green';
        case 'IN_POOL':      return 'teal';
        case 'IN_TRANSIT':   return 'blue';
        case 'PENDING_FILE': return 'yellow';
        case 'MISSING_FILE': return 'red';
        case 'REFER':        return 'orange';
        default:             return 'gray';
    }
}

/* ─── Movement History ───────────────────────────────────────────────────────
 * Mirrors the mobile dashboard's "Movement timeline" panel (same /track data,
 * same arrangement): the Archive / Registry home row first, then movement
 * entries oldest → newest, each carrying Receiving Officer, Log In, Log Out,
 * Request Purpose, Timeline, Expected Return Date and Delay Reason. Approval
 * steps are split out into their own Workflow Approvals list below.
 * ─────────────────────────────────────────────────────────────────────────── */

const APPROVAL_PURPOSES = ['recommendation', 'approval'];

/**
 * Render the movement timeline for a resolved archive payload
 * @param {Object} d - Archive detail payload
 */
function renderArchiveTimeline(d) {
    const container = document.getElementById('file-history-timeline');
    if (!container) return;

    const priorLogs = Array.isArray(d.prior_movements) ? d.prior_movements : [];
    const currentLogs = Array.isArray(d.movement_history) ? d.movement_history : [];
    const allLogs = priorLogs.concat(currentLogs).sort(compareMovementEntries);

    const isApproval = (e) => APPROVAL_PURPOSES.includes(String(e.purpose || '').toLowerCase());
    const movementLogs = allLogs.filter((e) => !isApproval(e));
    const approvalLogs = allLogs.filter(isApproval);

    // Request Purpose / Timeline / Expected Return Date belong to the tracker
    // (one per tracking cycle), so every row shows the same values.
    const trackerMeta = {
        requestPurposeName: (d.tracker && d.tracker.request_purpose_name) || '',
        timelineStatus: (d.tracker && d.tracker.timeline_status) || null,
        daysUntilDeadline: d.tracker && d.tracker.days_until_deadline !== null && d.tracker.days_until_deadline !== undefined
            ? Number(d.tracker.days_until_deadline)
            : null,
        expectedReturnDate: (d.tracker && d.tracker.deadline) || null,
    };

    const emptyRow = (message) =>
        `<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">${escapeHtml(message)}</div>`;

    container.innerHTML = `
        ${priorLogs.length ? `<div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-500">Tracking cycles for this file.</div>` : ''}
        ${buildArchiveHomeRow(d)}
        ${movementLogs.length
            ? movementLogs.map((entry) => buildMovementRow(entry, trackerMeta)).join('')
            : emptyRow(allLogs.length
                ? 'No physical movement entries found. Approval steps are listed below.'
                : 'No movement history available for this file.')}
        ${approvalLogs.length ? `
            <div class="mt-3 mb-1 text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Workflow Approvals</div>
            <div class="flex flex-col gap-2">${approvalLogs.map(buildApprovalRow).join('')}</div>` : ''}`;

    applyIndexingLastUpdated(d);
    initializeLucideIcons();
}

/**
 * The file's resting place — always the first row of the timeline
 * @param {Object} d - Archive detail payload
 * @returns {string} HTML
 */
function buildArchiveHomeRow(d) {
    if (!d.registry && !d.rack_shelf) return '';

    const home = [d.registry || 'Registry / Archive', d.rack_shelf ? 'Shelf/Rack ' + d.rack_shelf : null]
        .filter(Boolean)
        .join(' — ');

    return `
        <div class="relative pb-3">
            <span class="absolute -left-6 top-1 h-3 w-3 rounded-full bg-emerald-500 border-2 border-emerald-200 shadow-sm"></span>
            <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Archive / Registry</div>
                    <div class="text-[13px] font-bold text-gray-900">${escapeHtml(home)}</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap">In Archive</span>
            </div>
        </div>`;
}

/**
 * One movement entry, laid out like the mobile timeline row
 * @param {Object} entry - Movement log entry
 * @param {Object} trackerMeta - Tracker-level request/timeline values
 * @returns {string} HTML
 */
function buildMovementRow(entry, trackerMeta) {
    const office = entry.office_name || entry.office || entry.receiving_office_name || 'Unknown';
    const status = resolveMovementStatus(entry);
    const statusLower = (status.label || '').trim().toLowerCase();
    const isCompletedStatus = statusLower === 'completed' || statusLower === 'complete' || (entry.status || '').toString().trim().toLowerCase() === 'completed';
    const officer = isCompletedStatus
        ? 'Archive'
        : (entry.receiving_officer_name || entry.receivingOfficerName || entry.accepted_by_name || '-');

    // Log In is only meaningful once the file has actually been logged back in.
    const loggedIn = status.label === 'Log-in' || status.label === 'Completed';
    const inDate = loggedIn
        ? formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime)
        : '-';

    const outDateRaw = entry.log_out_date || entry.logOutDate;
    const rawStatus = String(entry.status || '').trim().toLowerCase();
    const outDate = outDateRaw
        ? formatMovementDate(outDateRaw, entry.log_out_time || entry.logOutTime)
        : (['active', 'pending_acceptance', 'in-transit', 'in transit'].includes(rawStatus) ? 'In transit' : '-');

    const timelineMeta = formatTimelineMeta(trackerMeta);

    return `
        <div class="relative pb-4">
            <span class="absolute -left-6 top-1 h-3 w-3 rounded-full bg-blue-500 border-2 border-blue-200 shadow-sm"></span>
            <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Office</div>
                    <div class="text-[13px] font-bold text-gray-900">${escapeHtml(office)}</div>
                </div>
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap ${status.classes}">${escapeHtml(status.label)}</span>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2">
                ${timelineField('Receiving Officer', officer, 'col-span-2')}
                ${timelineField('Log In', inDate)}
                ${timelineField('Log Out', outDate)}
                ${timelineField('Request Purpose', trackerMeta.requestPurposeName || '—', 'col-span-2')}
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Timeline</div>
                    ${timelineMeta
                        ? `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-bold ${timelineMeta.classes}">${escapeHtml(timelineMeta.label)}</span>`
                        : '<div class="text-[13px] text-gray-400">—</div>'}
                </div>
                ${timelineField('Expected Return Date', formatExpectedReturnDate(trackerMeta.expectedReturnDate))}
                ${timelineField('Delay Reason', entry.delay_reason || '—', 'col-span-2')}
            </div>
            ${entry.notes ? `<div class="mt-2 text-xs text-gray-500">${escapeHtml(entry.notes)}</div>` : ''}
        </div>`;
}

/**
 * A recommendation / approval step, rendered as a flat card
 * @param {Object} entry - Movement log entry with an approval purpose
 * @returns {string} HTML
 */
function buildApprovalRow(entry) {
    const office = entry.office_name || entry.office || entry.receiving_office_name || 'Unknown';
    const officer = entry.receiving_officer_name || entry.accepted_by_name || '—';
    const status = resolveMovementStatus(entry);
    const purposeLabel = String(entry.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval';
    const eventDate = formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);

    return `
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
            <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                    <div class="text-xs font-bold text-gray-900">${escapeHtml(purposeLabel)} — ${escapeHtml(office)}</div>
                    <div class="mt-0.5 text-[11px] text-gray-500">${escapeHtml(officer)} · ${escapeHtml(eventDate)}</div>
                </div>
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap ${status.classes}">${escapeHtml(status.label)}</span>
            </div>
            ${entry.notes ? `<div class="mt-2 text-xs text-gray-500">${escapeHtml(entry.notes)}</div>` : ''}
        </div>`;
}

/**
 * A label/value pair inside a movement row
 * @param {string} label
 * @param {string} value
 * @param {string} [extraClasses] - Extra grid classes for the wrapper
 * @returns {string} HTML
 */
function timelineField(label, value, extraClasses) {
    return `
        <div class="${extraClasses || ''}">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">${escapeHtml(label)}</div>
            <div class="text-[13px] font-semibold text-gray-800">${escapeHtml(value)}</div>
        </div>`;
}

/**
 * Status label + badge classes for a movement entry
 * @param {Object} entry - Movement log entry
 * @returns {{label: string, classes: string}}
 */
function resolveMovementStatus(entry) {
    const green = 'bg-emerald-100 text-emerald-800 border-emerald-200';
    const amber = 'bg-amber-100 text-amber-800 border-amber-200';
    const red = 'bg-red-100 text-red-700 border-red-200';
    const gray = 'bg-gray-100 text-gray-600 border-gray-300';
    const indigo = 'bg-indigo-100 text-indigo-800 border-indigo-200';

    const override = (entry.status_label || entry.statusLabel || entry.new_status || entry.newStatus || '').toString().trim();
    const normalize = (value) => value.toLowerCase().replace(/_/g, ' ');

    if (override) {
        switch (normalize(override)) {
            case 'log-in':
            case 'log in':  return { label: 'Log-in', classes: green };
            case 'log-out':
            case 'log out': return { label: 'Log-out', classes: green };
            case 'pending acceptance':
            case 'in-transit':
            case 'in transit': return { label: 'In-Transit', classes: amber };
            case 'rejected': return { label: 'Rejected', classes: red };
            case 'cancelled':
            case 'canceled': return { label: 'Cancelled', classes: gray };
            default: return { label: override, classes: indigo };
        }
    }

    switch (String(entry.status || '').trim().toLowerCase()) {
        case 'pending_acceptance': return { label: 'In-Transit', classes: amber };
        case 'active':    return { label: 'Log-out', classes: green };
        case 'completed': return { label: 'Log-in', classes: green };
        case 'rejected':  return { label: 'Rejected', classes: red };
        default: return { label: String(entry.status || 'Completed').replace(/_/g, ' '), classes: indigo };
    }
}

/**
 * Green/amber/red timeline badge for the tracker's deadline — mirrors
 * FileTracker::getTimelineStatusAttribute().
 * @param {Object} meta - trackerMeta block
 * @returns {{label: string, classes: string}|null}
 */
function formatTimelineMeta(meta) {
    const byStatus = {
        green: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        amber: 'bg-amber-100 text-amber-800 border-amber-200',
        red: 'bg-red-100 text-red-700 border-red-200',
    };

    if (!meta || !meta.timelineStatus || !byStatus[meta.timelineStatus]) return null;

    const days = meta.daysUntilDeadline;
    let label;

    if (days === null || days === undefined || Number.isNaN(days)) {
        label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timelineStatus];
    } else if (days > 0) {
        label = `${days} day${days === 1 ? '' : 's'} left`;
    } else if (days === 0) {
        label = 'Due today';
    } else {
        const abs = Math.abs(days);
        label = `${abs} day${abs === 1 ? '' : 's'} overdue`;
    }

    return { label, classes: byStatus[meta.timelineStatus] };
}

/**
 * Order movement entries oldest → newest
 */
function compareMovementEntries(a, b) {
    return movementEntryTimestamp(a) - movementEntryTimestamp(b);
}

/**
 * Sortable timestamp for a movement entry: log-in, else log-out, else created
 * @param {Object} entry - Movement log entry
 * @returns {number} Epoch milliseconds
 */
function movementEntryTimestamp(entry) {
    const parseDateTime = (date, time) => {
        const d = (date || '').toString().trim();
        if (!d) return null;
        const parsed = Date.parse(`${d} ${(time || '').toString().trim() || '00:00'}`);
        return Number.isNaN(parsed) ? null : parsed;
    };

    const inTs = parseDateTime(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
    if (inTs !== null) return inTs;

    const outTs = parseDateTime(entry.log_out_date || entry.logOutDate, entry.log_out_time || entry.logOutTime);
    if (outTs !== null) return outTs;

    const createdTs = Date.parse(entry.created_at || entry.createdAt || '');
    return Number.isNaN(createdTs) ? Number.POSITIVE_INFINITY : createdTs;
}

/**
 * "2026-07-15" + "11:51" => "2026-07-15 11:51 AM"
 */
function formatMovementDate(date, time) {
    const d = (date || '').toString().trim();
    if (!d) return '—';
    if (!time) return d;

    const parts = time.toString().trim().split(':');
    if (parts.length < 2) return `${d} ${time}`;

    let hour = parseInt(parts[0], 10);
    if (Number.isNaN(hour)) return `${d} ${time}`;

    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    return `${d} ${hour}:${parts[1].padStart(2, '0')} ${period}`;
}

/**
 * Expected return date as dd/mm/yyyy
 */
function formatExpectedReturnDate(value) {
    if (!value) return '—';

    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);

    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

/**
 * Escape a value for interpolation into timeline markup
 * @param {*} value
 * @returns {string}
 */
function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * "Last updated" reports the file_indexings row's updated_at (who/when the
 * indexing record last changed) — not the tracker's own timestamp.
 * @param {Object} d - Archive detail payload
 */
function applyIndexingLastUpdated(d) {
    const el = document.getElementById('file-history-last-updated');
    if (!el) return;

    const formatted = formatDateTimeDisplay(d.indexing_updated_at);

    if (!formatted) {
        el.textContent = 'Last updated: --';
        return;
    }

    el.textContent = 'Last updated: ' + formatted
        + (d.indexing_updated_by ? ' by ' + d.indexing_updated_by : '');
}

/**
 * Handle file number selection from modal
 * @param {Object} data - Data from modal callback
 */
function handleFileNumberSelection(data) {
    if (!data || !data.fileNumber) {
        console.warn('No file number selected');
        return;
    }

    loadArchiveFileNumber(data.fileNumber);
}

/**
 * Reset all badges to N/A
 */
function resetBadgesToNA() {
    resetBadgeIfLoading('detail-tracking-id');
    resetBadgeIfLoading('detail-land-use');
    resetBadgeIfLoading('detail-shelf');
    resetBadgeIfLoading('detail-rack');
    resetBadgeIfLoading('detail-full-shelf-rack');
}

/**
 * Reset badge text if it is still in a loading or empty state
 * @param {string} elementId - Badge element identifier
 */
function resetBadgeIfLoading(elementId) {
    const badge = document.getElementById(elementId);
    if (!badge) {
        return;
    }

    const current = (badge.textContent || '').trim().toLowerCase();
    if (current === '' || current === 'loading...' || current === 'n/a' || current === 'not selected') {
        updateBadge(elementId, 'N/A', 'gray');
    }
}

/**
 * Update a badge element
 * @param {string} elementId - Element ID
 * @param {string} text - Text to display
 * @param {string} color - Color theme (blue, green, purple, etc.)
 */
function updateBadge(elementId, text, color) {
    const badge = document.getElementById(elementId);
    if (!badge) return;
    
    const resolvedText = text === undefined || text === null || text === ''
        ? 'N/A'
        : String(text);

    badge.textContent = resolvedText;
    
    const isNA = resolvedText === 'N/A' || resolvedText === 'Not Selected';
    const colorClass = isNA ? 'gray-400' : `${color}-600`;
    
    badge.className = `inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-semibold bg-${colorClass} text-white`;
}

/**
 * Get color for land use type
 * @param {string} landUse - Land use type
 * @returns {string} Color name
 */
function getLandUseColor(landUse) {
    const landUseUpper = landUse.toUpperCase();
    
    switch(landUseUpper) {
        case 'RESIDENTIAL':
        case 'RES':
            return 'green';
        case 'COMMERCIAL':
        case 'COM':
            return 'blue';
        case 'INDUSTRIAL':
        case 'IND':
            return 'orange';
        case 'AGRICULTURAL':
        case 'AG':
            return 'yellow';
        default:
            return 'gray';
    }
}

/**
 * Show file number as bold green badge
 * @param {string} fileNumber - File number to display
 */
function showFileNumberBadge(fileNumber) {
    const badgeContainer = document.getElementById('file-number-badge-container');
    const badge = document.getElementById('file-number-badge');
    
    if (badgeContainer && badge && fileNumber) {
        badge.textContent = fileNumber;
        badgeContainer.classList.remove('hidden');
    }
}

/**
 * Hide file number badge
 */
function hideFileNumberBadge() {
    const badgeContainer = document.getElementById('file-number-badge-container');
    const badge = document.getElementById('file-number-badge');
    
    if (badgeContainer && badge) {
        badge.textContent = '';
        badgeContainer.classList.add('hidden');
    }
}

/**
 * Reset file details to default values
 */
function resetFileDetails() {
    hideFileNumberBadge();
    updateBadge('detail-tracking-id', 'Not Selected', 'gray');
    updateBadge('detail-land-use', 'N/A', 'gray');
    updateBadge('detail-registry', '-', 'gray');
    updateBadge('detail-shelf', 'N/A', 'gray');
    updateBadge('detail-rack', 'N/A', 'gray');
    updateBadge('detail-full-shelf-rack', 'N/A', 'gray');
    updateBadge('detail-current-location', 'N/A', 'gray');
    updateBadge('detail-status', 'Ready', 'gray');
    resetFileHistoryTimeline();
}

/**
 * Derive land use from the file number structure
 * @param {string} fileNumber - Selected file number
 * @returns {{code: string, label: string}|null}
 */
function deriveLandUseFromFileNumber(fileNumber) {
    if (!fileNumber) {
        return null;
    }

    const landUseLookup = {
        RES: 'Residential',
        RESIDENTIAL: 'Residential',
        COM: 'Commercial',
        COMMERCIAL: 'Commercial',
        IND: 'Industrial',
        INDUSTRIAL: 'Industrial',
        AG: 'Agricultural',
        AGR: 'Agricultural',
        AGRICULTURAL: 'Agricultural',
        MIX: 'Mixed',
        MIXED: 'Mixed'
    };

    const safeSegments = String(fileNumber)
        .toUpperCase()
        .split(/[-\s_\/\\]+/)
        .filter(Boolean);

    for (const segment of safeSegments) {
        if (landUseLookup[segment]) {
            return { code: segment, label: landUseLookup[segment] };
        }

        const alphaOnly = segment.replace(/[^A-Z]/g, '');
        if (alphaOnly && landUseLookup[alphaOnly]) {
            return { code: alphaOnly, label: landUseLookup[alphaOnly] };
        }
    }

    return null;
}

/**
 * Apply derived land use to the UI if no explicit land use is present
 * @param {string} fileNumber - Selected file number
 */
function applyDerivedLandUseFromFileNumber(fileNumber) {
    const landUse = deriveLandUseFromFileNumber(fileNumber);
    if (!landUse) {
        return;
    }

    const badge = document.getElementById('detail-land-use');
    if (!badge) {
        return;
    }

    const currentValue = badge.textContent ? badge.textContent.trim().toUpperCase() : '';
    const shouldUpdate = !currentValue
        || currentValue === 'N/A'
        || currentValue === 'NOT SELECTED'
        || currentValue === 'LOADING...';

    if (!shouldUpdate) {
        return;
    }

    const displayLabel = landUse.label.toUpperCase();
    updateBadge('detail-land-use', displayLabel, getLandUseColor(displayLabel));
}

/**
 * Format status label text for display
 * @param {string} status - Raw status string
 * @returns {string} Human-readable status
 */
function formatStatusText(status) {
    const safeStatus = String(status || '').trim();
    if (!safeStatus) {
        return '';
    }

    return safeStatus
        .toLowerCase()
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}


function formatDateTimeDisplay(value) {
    if (!value) {
        return null;
    }

    const parsed = Date.parse(value);
    if (Number.isNaN(parsed)) {
        return null;
    }

    const date = new Date(parsed);

    try {
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch (error) {
        // Fall back to ISO-style formatting if locale conversion fails
    }

    return date.toISOString().replace('T', ' ').slice(0, 16);
}

function setFileHistoryTimelineLoading() {
    const container = document.getElementById('file-history-timeline');
    const lastUpdatedEl = document.getElementById('file-history-last-updated');

    if (!container) {
        return;
    }

    container.innerHTML = '';

    const loadingEl = document.createElement('div');
    loadingEl.className = 'text-sm text-gray-500';
    loadingEl.textContent = 'Loading movement history...';
    container.appendChild(loadingEl);

    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = 'Last updated: --';
    }
}

function resetFileHistoryTimeline(message) {
    setFileHistoryTimelineFallback(message || 'Select a file to view movement history.');
}

function setFileHistoryTimelineFallback(message) {
    const container = document.getElementById('file-history-timeline');
    const lastUpdatedEl = document.getElementById('file-history-last-updated');

    if (!container) {
        return;
    }

    container.innerHTML = '';

    const placeholderEl = document.createElement('div');
    placeholderEl.className = 'text-sm text-gray-500';
    placeholderEl.textContent = message || 'No movement history available.';
    container.appendChild(placeholderEl);

    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = 'Last updated: --';
    }
}

/**
 * Initialize Lucide icons
 */
function initializeLucideIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Archive search functionality (placeholder)
 */
function searchArchive() {
    const fileNumber = document.getElementById('archive-file-no').value;
    
    if (!fileNumber) {
        alert('Please enter or select a file number');
        return;
    }
    
    console.log('Searching archive for:', fileNumber);
    loadArchiveFileNumber(fileNumber.trim());
}
