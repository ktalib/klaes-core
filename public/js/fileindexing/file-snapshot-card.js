/**
 * The two cards shown after a file indexing submission.
 *
 *   1. SUBMISSION SUMMARY  every file this one submission touched — the main file,
 *      its temporary number, a standalone New KANGIS record, and each related /
 *      block file — with the destination each one landed in. Answers "did the
 *      OTHER files go where I meant them to?", which the existing storage-footprint
 *      card cannot: that one reports on the main file only. Closed with OK.
 *
 *   2. FILE SNAPSHOT  the file's whole captured state at this moment: how it was
 *      created and indexed, who indexed it, its details, tracking, EDMS folder,
 *      entity and customer, every transaction on it, every linked file, and — from
 *      the second version onward — exactly what changed since the last snapshot,
 *      who changed it and when.
 *
 * Both cards are built from `response.snapshot`, which the server wrote to
 * file_snapshots during the same request (App\Services\FileSnapshotService). They
 * are NOT built from the submitted form: the point of the pair is to show what was
 * actually persisted, and a card that re-renders the form input would agree with
 * the operator even when the save disagreed.
 *
 * Fail-open throughout. Snapshot capture is best-effort server side, so `snapshot`
 * can be absent after a perfectly successful save — every entry point resolves
 * immediately in that case rather than showing an empty card or blocking the flow.
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isBlank(value) {
        return value === null || value === undefined || String(value).trim() === '';
    }

    /**
     * A Lucide icon placeholder.
     *
     * Lucide's UMD build is loaded globally (layouts/app.blade.php), and it works
     * by REPLACING <i data-lucide="..."> elements with real <svg> at
     * createIcons() time — which is why both cards call it in their didOpen hook.
     * Attributes set here (size, style) are carried onto the generated <svg>.
     *
     * If Lucide is ever absent the <i> stays empty and collapses to nothing: the
     * labels beside it still read correctly, so a missing icon library costs
     * decoration rather than meaning.
     */
    function icon(name, size) {
        return '<i data-lucide="' + escapeHtml(name) + '"'
            + ' width="' + (size || 14) + '" height="' + (size || 14) + '"'
            + ' style="display:inline-block;vertical-align:-2px;flex:0 0 auto;"></i>';
    }

    /** Hand the card's markup to Lucide once SweetAlert has it in the DOM. */
    function paintIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            try {
                window.lucide.createIcons();
            } catch (error) {
                console.warn('Lucide icons could not be painted', error);
            }
        }
    }

    /** Non-blank values, case-insensitively de-duplicated, order preserved. */
    function dedupe(values) {
        const seen = Object.create(null);

        return values.filter(function (value) {
            if (isBlank(value)) return false;
            const key = String(value).trim().toUpperCase();
            if (seen[key]) return false;
            seen[key] = true;
            return true;
        });
    }

    /** snake_case / camelCase key -> readable label. */
    function humanise(key) {
        return String(key)
            .replace(/_/g, ' ')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/^./, function (c) { return c.toUpperCase(); });
    }

    /** Booleans arrive from SQL Server as 0/1 and are unreadable as "0". */
    function displayValue(value) {
        if (value === true || value === 1) return 'Yes';
        if (value === false || value === 0) return 'No';
        if (Array.isArray(value)) return value.length + ' item(s)';
        return String(value);
    }

    // ------------------------------------------------------------ card 1

    /**
     * One row per file the submission touched.
     *
     * Destinations are read off the snapshot the server just wrote, so a file that
     * did not actually persist cannot appear here with a destination beside it.
     */
    function buildSubmissionRows(data) {
        const snapshot = (data && data.snapshot) || null;
        const payload = (snapshot && snapshot.payload) || {};
        const identity = payload.identity || {};
        const indexing = payload.indexing || {};
        const rows = [];

        // Where the main file physically lands. Batch/serial/shelf is what the
        // operator actually needs to find the paper again.
        const placement = [
            indexing.batch_no ? 'Batch ' + indexing.batch_no : null,
            indexing.serial_no ? 'Serial ' + indexing.serial_no : null,
            indexing.shelf_location ? 'Shelf ' + indexing.shelf_location : null
        ].filter(Boolean).join(' · ');

        // `registry` frequently holds a bare code ("1") rather than a name, and a
        // destination reading just "1" is worse than none. Prefer the named columns,
        // and only fall back to `registry` when it is actually a name.
        const registry = identity.general_registry
            || identity.physical_registry
            || (/^\d+$/.test(String(identity.registry || '').trim()) ? '' : identity.registry)
            || '';

        const mainNumber = identity.file_number || (data && data.data && data.data.file_number) || '';
        if (!isBlank(mainNumber)) {
            rows.push({
                file: mainNumber,
                title: identity.file_title || '',
                kind: 'Main file',
                tone: 'main',
                destination: [registry, placement].filter(Boolean).join(' — ')
                    || 'Indexed (no shelf assigned yet)'
            });
        }

        // The temporary number is the SAME physical file under a different column,
        // not a second file — labelled so nobody reads it as a duplicate.
        if (!isBlank(identity.temp_file_no)) {
            rows.push({
                file: identity.temp_file_no,
                title: identity.file_title || '',
                kind: 'Temporary number',
                tone: 'temp',
                destination: 'file_indexings.temp_file_no (same physical file)'
            });
        }

        // A standalone record in its own right, with its own prop_id — see the
        // "Has New KANGIS FileNo" handling in FileIndexingController::store.
        const kangisNumber = (data && data.kangis_resolved) || identity.new_kangis_file_no;
        if (!isBlank(kangisNumber) && kangisNumber !== mainNumber) {
            rows.push({
                file: kangisNumber,
                title: identity.file_title || '',
                kind: 'New KANGIS record',
                tone: 'kangis',
                destination: 'Its own file_indexings row (own prop_id)'
            });
        }

        // Related / block files, de-duplicated: one file number can legitimately be
        // registered in several link tables, and listing it three times reads as
        // three files rather than as one file with three registrations.
        const links = Array.isArray(payload.links) ? payload.links : [];
        const seen = Object.create(null);
        links.forEach(function (link) {
            const number = link && link.file_number;
            if (isBlank(number)) return;

            const key = String(number).toUpperCase();
            if (seen[key]) {
                if (link.source && seen[key].sources.indexOf(link.source) === -1) {
                    seen[key].sources.push(link.source);
                    seen[key].destination = seen[key].sources.join(', ');
                }
                return;
            }

            const row = {
                file: number,
                title: link.file_title || '',
                kind: link.role || link.relationship || 'Related file',
                tone: 'link',
                sources: link.source ? [link.source] : [],
                destination: link.source || 'linked'
            };
            seen[key] = row;
            rows.push(row);
        });

        return rows;
    }

    /** The tables this save wrote to, as chips, from the existing storage summary. */
    function buildDestinationChips(data) {
        const summary = data && data.storage_summary;
        if (!summary || !Array.isArray(summary.groups)) return '';

        const tables = [];
        summary.groups.forEach(function (group) {
            (group.rows || []).forEach(function (row) {
                if (row && row.table && Number(row.count) > 0 && tables.indexOf(row.table) === -1) {
                    tables.push(row.table);
                }
            });
        });

        if (!tables.length) return '';

        return '<div style="margin-top:12px;text-align:left;">'
            + '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:6px;">'
            + 'Tables this submission wrote to</div>'
            + '<div style="display:flex;flex-wrap:wrap;gap:4px;">'
            + tables.map(function (table) {
                return '<span style="font-size:11px;padding:2px 8px;border-radius:9999px;'
                    + 'background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;">'
                    + escapeHtml(table) + '</span>';
            }).join('')
            + '</div></div>';
    }

    const TONE_COLORS = {
        main: '#059669',
        temp: '#d97706',
        kangis: '#2563eb',
        link: '#64748b'
    };

    const TONE_ICONS = {
        main: 'folder',
        temp: 'clock',
        kangis: 'file-plus',
        link: 'link'
    };

    function renderSubmissionRows(rows) {
        return '<div style="border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">'
            + rows.map(function (row, index) {
                const border = index === 0 ? '' : 'border-top:1px solid #e2e8f0;';
                // File number hard left, its role badge hard right, destination
                // beneath — so the badges form one scannable column down the card.
                return '<div style="' + border + 'padding:8px 10px;text-align:left;">'
                    + '<div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;">'
                    + '<span style="font-weight:600;color:#0f172a;word-break:break-all;min-width:0;">'
                    + icon(TONE_ICONS[row.tone] || 'file') + ' '
                    + escapeHtml(row.file) + '</span>'
                    + '<span style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;'
                    + 'padding:1px 6px;border-radius:9999px;color:#fff;white-space:nowrap;background:'
                    + (TONE_COLORS[row.tone] || '#64748b') + ';">'
                    + escapeHtml(row.kind) + '</span>'
                    + '</div>'
                    + (isBlank(row.title) ? '' :
                        '<div style="font-size:12px;color:#475569;margin-top:2px;">'
                        + escapeHtml(row.title) + '</div>')
                    + '<div style="font-size:11px;color:#64748b;margin-top:3px;">'
                    + icon('map-pin', 12) + ' '
                    + escapeHtml(row.destination) + '</div>'
                    + '</div>';
            }).join('')
            + '</div>';
    }

    /**
     * Card 1. Resolves when the operator clicks OK; resolves immediately when
     * there is nothing to show, so the caller's chain never stalls.
     */
    function showSubmissionSummaryCard(data) {
        if (typeof Swal === 'undefined') return Promise.resolve();

        const rows = buildSubmissionRows(data);
        if (!rows.length) return Promise.resolve();

        const fileWord = rows.length === 1 ? 'file' : 'files';

        return Swal.fire({
            title: 'Submitted — ' + rows.length + ' ' + fileWord,
            html: '<p style="font-size:13px;color:#475569;text-align:left;margin-bottom:10px;">'
                + 'Review where each file was filed, then click OK.</p>'
                + renderSubmissionRows(rows)
                + buildDestinationChips(data),
            icon: 'success',
            width: 620,
            confirmButtonText: 'OK',
            confirmButtonColor: '#059669',
            // Lucide replaces the <i data-lucide> placeholders with real <svg>,
            // and can only do that once SweetAlert has put them in the DOM.
            didOpen: paintIcons,
            // No timer and no click-outside: this is the only on-screen record of
            // where the submission landed, and a card that dismisses itself can
            // take it away before it has been read.
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }

    // ------------------------------------------------------------ card 2

    /**
     * Sections in reading order: [payload key, label, icon].
     *
     * Icons are emoji rather than inline SVG on purpose — this HTML is handed to
     * SweetAlert as a string, and a dozen inline <svg> sprites would triple the
     * card's size for decoration that has to survive being escaped and re-parsed.
     */
    const SECTIONS = [
        ['identity', 'File identity', 'file-text'],
        ['property', 'Property details', 'map-pin'],
        ['indexing', 'Indexing & storage', 'archive'],
        ['tracking', 'Tracking', 'compass'],
        ['edms', 'EDMS', 'folder-tree'],
        ['parties', 'Parties & holders', 'users'],
        ['entity', 'Entity', 'building-2'],
        ['customer', 'Customer', 'contact'],
        ['bills', 'Bills & ground rent', 'banknote']
    ];

    /**
     * Accent colour per section icon.
     *
     * Keyed by ICON NAME rather than section key so the four accordions rendered
     * outside SECTIONS (transactions, links, movements, changes) are coloured by
     * the same map instead of needing their own.
     *
     * Hues are assigned by what the section is about, not by position, so the
     * colour carries meaning once learned: blue = the file's own identity and
     * paperwork, emerald = place and property, amber = physical custody and
     * money, violet = people and organisations, slate = digital storage. All are
     * the 600 step of their ramp, which holds contrast against the #f8fafc
     * summary bar.
     */
    const ICON_COLORS = {
        // identity / documents
        'file-text':    '#2563eb',
        'scroll-text':  '#4f46e5',
        'link-2':       '#0891b2',
        // place & property
        'map-pin':      '#059669',
        // custody & storage
        'archive':      '#d97706',
        'compass':      '#ea580c',
        'truck':        '#c2410c',
        'folder-tree':  '#475569',
        // people
        'users':        '#7c3aed',
        'building-2':   '#9333ea',
        'contact':      '#c026d3',
        // money
        'banknote':     '#16a34a',
        // audit
        'pencil':       '#b45309'
    };

    /** The muted default for any icon without an entry above. */
    const ICON_COLOR_DEFAULT = '#64748b';

    /**
     * Keys rendered by a dedicated collection block rather than as a plain field,
     * so they never also appear in the field list as a useless "2 item(s)".
     */
    const COLLECTION_KEYS = ['movements', 'trackers', 'folders'];

    /**
     * Label left, value hard right, dotted leader between them.
     *
     * Justifying the two columns is what makes a 20-row section scannable: the
     * values line up in a single column the eye can run down, instead of starting
     * at whatever x-position each label happened to end at.
     */
    function renderFieldList(section) {
        const keys = Object.keys(section || {}).filter(function (key) {
            return COLLECTION_KEYS.indexOf(key) === -1 && !isBlank(section[key]);
        });

        if (!keys.length) return '';

        return '<div style="font-size:12px;">'
            + keys.map(function (key, index) {
                const border = index === 0 ? '' : 'border-top:1px dotted #e2e8f0;';
                return '<div style="' + border + 'display:flex;align-items:baseline;'
                    + 'justify-content:space-between;gap:12px;padding:4px 0;">'
                    + '<span style="color:#64748b;flex:0 0 auto;">'
                    + escapeHtml(humanise(key)) + '</span>'
                    + '<span style="color:#0f172a;font-weight:500;text-align:right;'
                    + 'word-break:break-word;min-width:0;">'
                    + escapeHtml(displayValue(section[key])) + '</span>'
                    + '</div>';
            }).join('')
            + '</div>';
    }

    function renderRowList(rows, renderer) {
        if (!Array.isArray(rows) || !rows.length) return '';

        return '<div style="display:flex;flex-direction:column;gap:6px;">'
            + rows.map(renderer).join('')
            + '</div>';
    }

    function renderTransaction(txn) {
        const meta = [
            txn.transaction_date,
            txn.reg_no ? 'Reg ' + txn.reg_no : null,
            txn.source_table
        ].filter(function (v) { return !isBlank(v); }).join(' · ');

        const parties = [txn.party_1, txn.party_2, txn.party_3]
            .filter(function (v) { return !isBlank(v); }).join(' → ');

        const audit = [
            txn.created_by_name ? 'by ' + txn.created_by_name : null,
            txn.created_at
        ].filter(Boolean).join(' · ');

        return '<div style="border:1px solid #e2e8f0;border-radius:5px;padding:6px 8px;text-align:left;">'
            + '<div style="font-weight:600;font-size:12px;color:#0f172a;">'
            + escapeHtml(txn.transaction_type || 'Transaction') + '</div>'
            + (meta ? '<div style="font-size:11px;color:#64748b;">' + escapeHtml(meta) + '</div>' : '')
            + (parties ? '<div style="font-size:11px;color:#475569;margin-top:2px;">'
                + escapeHtml(parties) + '</div>' : '')
            + (audit ? '<div style="font-size:10px;color:#94a3b8;margin-top:2px;">'
                + escapeHtml(audit) + '</div>' : '')
            + '</div>';
    }

    function renderLink(link) {
        const meta = [link.relationship || link.role, link.source, link.created_at]
            .filter(function (v) { return !isBlank(v); }).join(' · ');

        return '<div style="border:1px solid #e2e8f0;border-radius:5px;padding:6px 8px;text-align:left;">'
            + '<div style="font-weight:600;font-size:12px;color:#0f172a;word-break:break-all;">'
            + escapeHtml(link.file_number || '—') + '</div>'
            + (isBlank(link.file_title) ? '' : '<div style="font-size:11px;color:#475569;">'
                + escapeHtml(link.file_title) + '</div>')
            + (meta ? '<div style="font-size:10px;color:#94a3b8;margin-top:2px;">'
                + escapeHtml(meta) + '</div>' : '')
            + '</div>';
    }

    function renderMovement(move) {
        const meta = [move.current_location, move.status, move.created_at]
            .filter(function (v) { return !isBlank(v); }).join(' · ');

        return '<div style="border:1px solid #e2e8f0;border-radius:5px;padding:6px 8px;text-align:left;">'
            + '<div style="font-size:12px;color:#0f172a;">' + escapeHtml(meta || '—') + '</div>'
            + (isBlank(move.assigned_to_name) ? '' :
                '<div style="font-size:10px;color:#94a3b8;">to ' + escapeHtml(move.assigned_to_name)
                + (isBlank(move.assigned_by_name) ? '' : ' · by ' + escapeHtml(move.assigned_by_name))
                + '</div>')
            + '</div>';
    }

    /**
     * The opening file_tracker line — where the file physically went the moment
     * it was indexed. Distinct from a movement, which is a later hand-off.
     */
    function renderTrackerLine(tracker) {
        // current_office / destination / department frequently hold the SAME name
        // on an opening line, and repeating it three times reads as three places.
        const where = dedupe([tracker.current_office, tracker.destination, tracker.department])
            .join(' · ');

        const meta = [tracker.status, tracker.purpose, tracker.date_created]
            .filter(function (v) { return !isBlank(v); }).join(' · ');

        return '<div style="border:1px solid #e2e8f0;border-radius:5px;padding:6px 8px;text-align:left;">'
            + '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">'
            + '<span style="font-weight:600;font-size:12px;color:#0f172a;word-break:break-all;">'
            + escapeHtml(tracker.tracking_id || 'Tracking line') + '</span>'
            + (isBlank(tracker.registry_code) ? '' :
                '<span style="font-size:10px;color:#64748b;white-space:nowrap;">'
                + escapeHtml(tracker.registry_code) + '</span>')
            + '</div>'
            + (where ? '<div style="font-size:11px;color:#475569;margin-top:2px;">'
                + escapeHtml(where) + '</div>' : '')
            + (meta ? '<div style="font-size:10px;color:#94a3b8;margin-top:2px;">'
                + escapeHtml(meta) + '</div>' : '')
            + (isBlank(tracker.created_by_name) ? '' :
                '<div style="font-size:10px;color:#94a3b8;">opened by '
                + escapeHtml(tracker.created_by_name) + '</div>')
            + '</div>';
    }

    /** Colour by outcome, so a folder that was NOT created stands out. */
    const FOLDER_STATE_COLORS = {
        created: '#059669',
        existed: '#2563eb',
        'same as home': '#64748b'
    };

    /**
     * One row per scan folder: the file's home registry folder plus its
     * counterpart folios in Cadastral Registry and Physical Planning Registry.
     */
    function renderFolder(folder) {
        const color = FOLDER_STATE_COLORS[folder.state] || '#b45309';

        return '<div style="border:1px solid #e2e8f0;border-radius:5px;padding:6px 8px;text-align:left;">'
            + '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">'
            + '<span style="font-weight:600;font-size:12px;color:#0f172a;">'
            + escapeHtml(folder.registry || '—') + '</span>'
            + '<span style="font-size:10px;font-weight:600;color:' + color + ';white-space:nowrap;">'
            + escapeHtml(folder.state || 'unknown') + '</span>'
            + '</div>'
            + '<div style="font-size:10px;color:#94a3b8;">' + escapeHtml(folder.role || '') + '</div>'
            + (isBlank(folder.path) ? '' :
                '<div style="font-size:11px;color:#475569;margin-top:2px;word-break:break-all;'
                + 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">'
                + escapeHtml(folder.path) + '</div>')
            + '</div>';
    }

    /**
     * Collapsed by default except the first — a dozen open sections is a wall of
     * text. The icon and the count sit on the summary line so a collapsed section
     * still says what it holds and how much of it.
     */
    // `iconName`, not `icon` — the parameter would otherwise shadow the icon()
    // helper for the whole function body.
    function renderAccordion(iconName, label, body, count, open) {
        if (!body) return '';

        return '<details ' + (open ? 'open' : '') + ' style="border:1px solid #e2e8f0;'
            + 'border-radius:6px;margin-bottom:6px;overflow:hidden;">'
            + '<summary style="cursor:pointer;padding:7px 10px;background:#f8fafc;font-size:12px;'
            + 'font-weight:600;color:#334155;text-align:left;list-style:revert;">'
            + '<span style="margin-right:6px;color:'
            + (ICON_COLORS[iconName] || ICON_COLOR_DEFAULT) + ';">' + icon(iconName) + '</span>'
            + escapeHtml(label)
            + (count === undefined || count === null ? '' :
                ' <span style="font-weight:400;color:#94a3b8;">(' + count + ')</span>')
            + '</summary>'
            + '<div style="padding:8px 10px;">' + body + '</div>'
            + '</details>';
    }

    /** The "what changed" block — absent on version 1, which changed nothing. */
    function renderChanges(changes) {
        if (!Array.isArray(changes) || !changes.length) return '';

        const KIND_COLORS = { added: '#059669', removed: '#dc2626', changed: '#2563eb' };

        return '<div style="border:1px solid #fcd34d;background:#fffbeb;border-radius:6px;'
            + 'padding:8px 10px;margin-bottom:10px;text-align:left;">'
            + '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;'
            + 'color:#92400e;font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:5px;">'
            + icon('pencil', 12) + '<span>What changed (' + changes.length + ')</span></div>'
            + changes.map(function (change) {
                const color = KIND_COLORS[change.kind] || '#2563eb';
                const from = isBlank(change.from) ? '—' : change.from;
                const to = isBlank(change.to) ? '—' : change.to;

                // Label left, old → new hard right: the same justified column the
                // field lists use, so a change reads against the value it replaced.
                return '<div style="display:flex;justify-content:space-between;gap:12px;'
                    + 'align-items:baseline;font-size:12px;padding:3px 0;">'
                    + '<span style="flex:0 0 auto;">'
                    + '<span style="display:inline-block;min-width:56px;font-size:10px;'
                    + 'text-transform:uppercase;color:' + color + ';font-weight:700;">'
                    + escapeHtml(change.kind || 'changed') + '</span>'
                    + '<span style="color:#0f172a;font-weight:600;">'
                    + escapeHtml(change.label) + '</span>'
                    + '</span>'
                    + '<span style="color:#64748b;text-align:right;word-break:break-word;min-width:0;">'
                    + escapeHtml(from) + ' &rarr; <span style="color:#0f172a;font-weight:600;">'
                    + escapeHtml(to) + '</span></span>'
                    + '</div>';
            }).join('')
            + '</div>';
    }

    function renderSnapshotHtml(snapshot) {
        const payload = snapshot.payload || {};
        const counts = payload.counts || {};

        const identity = payload.identity || {};

        // Version badge hard left, timestamp hard right — the two things the
        // operator looks for first, at the two ends they expect to find them.
        const header = '<div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:6px;'
            + 'padding:8px 10px;margin-bottom:10px;text-align:left;">'
            + '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">'
            + '<span style="font-size:13px;color:#0f172a;font-weight:700;word-break:break-all;">'
            + icon('folder-open', 15) + ' '
            + escapeHtml(identity.file_number || snapshot.file_number || 'File')
            + '</span>'
            // A LIVE card is the file's state composed on the fly for a file with no
            // stored history (see FileIndexingController::snapshot). It gets its own
            // badge rather than "v" with nothing after it, because "v" reads as a
            // version that exists.
            + (snapshot.is_live
                ? '<span style="font-size:10px;font-weight:700;color:#fff;background:#0d9488;'
                  + 'border-radius:9999px;padding:2px 8px;white-space:nowrap;">LIVE</span>'
                : '<span style="font-size:10px;font-weight:700;color:#fff;background:#334155;'
                  + 'border-radius:9999px;padding:2px 8px;white-space:nowrap;">v'
                  + escapeHtml(snapshot.version) + '</span>')
            + '</div>'
            + '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;'
            + 'margin-top:4px;font-size:11px;color:#64748b;">'
            + '<span>' + escapeHtml(snapshot.event_label || snapshot.event_type) + '</span>'
            + '<span style="white-space:nowrap;">' + escapeHtml(snapshot.performed_at || '') + '</span>'
            + '</div>'
            // Nobody "performed" a live read, so the author line is omitted rather
            // than filled with "Unknown user" — who indexed the file is in the
            // Indexing section below, which is the honest place for it.
            + (snapshot.performed_by_name
                ? '<div style="font-size:11px;color:#64748b;">' + icon('user', 12) + ' '
                  + escapeHtml(snapshot.performed_by_name) + '</div>'
                : (snapshot.is_live ? '' : '<div style="font-size:11px;color:#64748b;">'
                  + icon('user', 12) + ' Unknown user</div>'))
            + '</div>';

        const tracking = payload.tracking || {};
        const edms = payload.edms || {};

        // Collection blocks that belong INSIDE a lettered section rather than in a
        // section of their own: the tracking lines are part of Tracking, the scan
        // folders are part of EDMS. Appended under that section's field list so
        // the operator reads "here is the tracking, and here is where it went".
        const EXTRA = {
            tracking: renderRowList(tracking.trackers, renderTrackerLine),
            edms: renderRowList(edms.folders, renderFolder)
        };

        let body = '';
        let first = true;

        SECTIONS.forEach(function (entry) {
            const key = entry[0];
            const fields = renderFieldList(payload[key]);
            const extra = EXTRA[key] || '';
            if (!fields && !extra) return;

            body += renderAccordion(
                entry[2],
                entry[1],
                fields + (extra
                    ? (fields ? '<div style="height:8px;"></div>' : '') + extra
                    : ''),
                key === 'tracking'
                    ? (counts.tracking_lines || null)
                    : (key === 'edms' ? (counts.edms_folders || null) : null),
                first
            );
            first = false;
        });

        body += renderAccordion(
            'scroll-text', 'Transactions',
            renderRowList(payload.transactions, renderTransaction),
            counts.transactions,
            false
        );
        body += renderAccordion(
            'link-2', 'Linked & related files',
            renderRowList(payload.links, renderLink),
            counts.links,
            false
        );
        body += renderAccordion(
            'truck', 'File movements',
            renderRowList(tracking.movements, renderMovement),
            counts.tracking_movements,
            false
        );

        return header
            + renderChanges(snapshot.changes)
            + body
            + '<p style="font-size:10px;color:#94a3b8;margin-top:8px;text-align:left;">'
            + (snapshot.is_live
                ? 'Read live from the record — this file was indexed before snapshots '
                  + 'were kept, so there is no version history to compare against yet. '
                  + 'Its next edit writes v1.'
                : 'Every edit, new transaction and new link writes a new snapshot version — '
                  + 'nothing here is ever overwritten.')
            + '</p>';
    }

    /**
     * Card 2. `snapshot` is the payload from `response.snapshot`; a null one means
     * the server could not write a snapshot, and the card is skipped silently
     * rather than reporting a failure the operator cannot act on.
     */
    function showFileSnapshotCard(snapshot) {
        if (typeof Swal === 'undefined' || !snapshot || !snapshot.payload) {
            return Promise.resolve();
        }

        return Swal.fire({
            title: 'File Snapshot',
            html: renderSnapshotHtml(snapshot),
            width: 720,
            confirmButtonText: 'Close',
            confirmButtonColor: '#334155',
            didOpen: paintIcons,
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }

    /**
     * Both cards in order, for the create/update flow. Each is skipped when its
     * data is absent, so a save with no snapshot behaves exactly as it did before
     * this file existed.
     */
    function showPostSubmitCards(data) {
        return showSubmissionSummaryCard(data)
            .then(function () {
                return showFileSnapshotCard(data && data.snapshot);
            });
    }

    window.showSubmissionSummaryCard = showSubmissionSummaryCard;
    window.showFileSnapshotCard = showFileSnapshotCard;
    window.showPostSubmitCards = showPostSubmitCards;
})();
