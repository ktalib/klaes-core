/**
 * "What happened to this duplex?" summary sheet.
 *
 * Laid out like the Generation Summary card on the MLS commissioning modal —
 * tinted header block, boxed sections with micro-labels, numbered detail cards,
 * a warning strip — because officers already read that card and the duplex should
 * not invent a second visual language for the same job.
 *
 * The "where the records went" block is rendered by renderRecordSummaryGroups()
 * from record-summary-card.js, so it stays identical to the File Indexing and MLS
 * commissioning cards instead of drifting apart.
 *
 * Works at any status: before commissioning it reports holding numbers and what is
 * planned; afterwards, the real file numbers.
 *
 * Requires SweetAlert (Swal); falls back to a plain alert when it is absent.
 */
(function () {
    'use strict';

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    var MONO = 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace';

    /** Micro-label above a value — the pattern the Generation Summary uses throughout. */
    function field(label, value, valueStyle) {
        return '<div>'
            + '<p style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8">'
            + esc(label) + '</p>'
            + '<p style="font-size:13px;font-weight:700;color:#1e293b;margin-top:2px;' + (valueStyle || '') + '">'
            + esc(value || '—') + '</p>'
            + '</div>';
    }

    function box(title, inner, tone) {
        if (!inner) return '';
        var tones = {
            plain: 'background:#fff;border-color:#e2e8f0',
            tint:  'background:#f8fafc;border-color:#e2e8f0',
            good:  'background:#f0fdf4;border-color:#bbf7d0',
            bad:   'background:#fef2f2;border-color:#fecaca'
        };
        return '<div style="border:1px solid;border-radius:12px;padding:12px 14px;margin-top:10px;text-align:left;'
            + (tones[tone] || tones.plain) + '">'
            + (title
                ? '<p style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;'
                  + 'color:#64748b;margin-bottom:8px">' + esc(title) + '</p>'
                : '')
            + inner + '</div>';
    }

    function numberList(items, colour) {
        if (!items || !items.length) return '';
        return '<p style="' + MONO + ';font-size:12px;font-weight:800;color:' + colour + ';line-height:1.7">'
            + items.map(esc).join('<br>') + '</p>';
    }

    /** One stage as a numbered detail card, mirroring the Location Details cards. */
    function stageCard(s) {
        var rows = (s.files || []).map(function (f) {
            var right = f.final
                ? '<span style="' + MONO + ';font-weight:800;color:' + (f.carried ? '#94a3b8' : '#15803d') + '">'
                  + esc(f.final) + '</span>'
                : '<span style="font-size:11px;color:#cbd5e1;font-style:italic">not commissioned yet</span>';

            return '<div style="display:flex;align-items:center;gap:8px;font-size:11px;margin:4px 0">'
                + '<span style="' + MONO + ';color:#64748b;min-width:150px">' + esc(f.holding) + '</span>'
                + '<span style="color:#cbd5e1">&rarr;</span>'
                + right
                + (f.carried ? '<span style="font-size:9px;color:#94a3b8;font-weight:700">UNCHANGED</span>' : '')
                + '</div>';
        }).join('');

        return '<div style="border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px 12px;margin-bottom:8px">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">'
                + '<p style="font-size:12px;font-weight:800;color:#1e293b">' + esc(s.label)
                + (s.new_land_use ? '<span style="font-weight:600;color:#64748b"> &rarr; ' + esc(s.new_land_use) + '</span>' : '')
                + '</p>'
                + '<span style="font-size:10px;font-weight:800;color:#64748b;background:#f1f5f9;'
                + 'border-radius:999px;padding:2px 8px">#' + esc(s.rank) + '</span>'
            + '</div>'
            + (rows || '<p style="font-size:11px;color:#cbd5e1">Nothing captured yet.</p>')
            + '</div>';
    }

    /**
     * "Committed" is the internal state name; the officer's word for it is
     * "commissioned", which is what the rest of the registry calls the same event.
     */
    function statusLabel(status) {
        const s = String(status || '').replace('_', ' ');
        return (s === 'committed' ? 'commissioned' : s).toUpperCase();
    }

    function buildHtml(d) {
        var m = d.duplex || {};
        var t = d.totals || {};
        var committed = m.status === 'committed';

        // Header block — the tinted panel the Generation Summary opens with.
        var header = '<div style="border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;'
            + 'padding:14px;text-align:left;display:grid;grid-template-columns:1fr 1fr;gap:14px">'
            + field('Duplex ID', m.duplex_id, MONO + ';color:#1d4ed8')
            + field('Status', statusLabel(m.status))
            + field('Land Use', m.land_use)
            + field('File Name', m.applicant)
            + '</div>';


        var sources = box('Parcel Files This Duplex Started From',
            numberList(d.sources, '#334155'), 'tint');

        var stages = box('Stages — In Execution Order',
            (d.stages || []).map(stageCard).join(''), 'tint');

        /**
         * Every number this run issued, in the order the stages minted them.
         *
         * This panel used to list d.commissioned — what is ACTIVE at the end — under
         * the heading "File Numbers Generated". For a merger-then-extension duplex
         * that is one number where two were minted, so the sheet contradicted both the
         * stage cards above it and its own "2 file number(s) issued" footer.
         *
         * It answers only "what did this process generate". Which of them the parcel
         * still holds is a separate question, and has its own panel below.
         */
        function issuedList() {
            var all = (d.issued && d.issued.length) ? d.issued : (d.commissioned || []);

            return numberList(all, '#15803d')
                + '<p style="font-size:10px;color:#94a3b8;margin-top:6px;font-family:system-ui,sans-serif">'
                + esc(all.length) + ' file number(s) generated in this process.</p>';
        }

        // After commissioning these are real file numbers; before it they are the
        // holding numbers that will become them.
        var generated = committed
            ? box('File Numbers Generated', issuedList(), 'good')
            : box('Files To Be Generated',
                numberList(d.planned, '#64748b')
                + '<p style="font-size:10px;color:#94a3b8;margin-top:6px">'
                + esc((d.planned || []).length) + ' file number(s) will be issued at the Land step.</p>',
                'tint');

        /**
         * What the parcel actually holds now.
         *
         * Separate from the panel above on purpose: a duplex issues a number per stage
         * and each stage retires what the previous one produced, so "generated" and
         * "active" are almost never the same list. Reading one as the other is what
         * made a two-file run look like a one-file run.
         *
         * Only after commissioning — before it there is nothing active to report, and
         * the holding numbers are already shown as "Files To Be Generated".
         */
        var active = (committed && (d.commissioned || []).length)
            ? box('Active', numberList(d.commissioned, '#15803d')
                + '<p style="font-size:10px;color:#94a3b8;margin-top:6px;font-family:system-ui,sans-serif">'
                + esc((d.commissioned || []).length) + ' file(s) active at the end of this duplex'
                + (((d.issued || []).length > (d.commissioned || []).length)
                    ? '; the rest were retired by a later stage.'
                    : '.')
                + '</p>', 'good')
            : '';

        var retired = (d.retired || []).length
            ? box('Decommissioned', d.retired.map(function (r) {
                return '<div style="font-size:11px;margin:3px 0">'
                    + '<span style="' + MONO + ';font-weight:800;color:#b91c1c">' + esc(r.file_no) + '</span>'
                    + (r.successor
                        ? '<span style="color:#cbd5e1"> &rarr; </span><span style="' + MONO + ';font-weight:800;color:#15803d">'
                          + esc(r.successor) + '</span>'
                        : '')
                    + '<span style="display:block;color:#94a3b8;font-size:10px">' + esc(r.reason || '') + '</span>'
                    + '</div>';
            }).join(''), 'bad')
            : '';

        // The plot as the ISSUED files actually carry it. A duplex ending in an
        // extension issues "C & B & EXTENSION" while the duplex row still holds the
        // "C & B" captured up front, and the sheet named the one no file has. Several
        // active files (a subdivision) list every plot; nothing commissioned yet falls
        // back to what was captured.
        var plots = [];
        Object.keys(d.plot_numbers || {}).forEach(function (fileNo) {
            var p = d.plot_numbers[fileNo];
            if (p && plots.indexOf(p) === -1) plots.push(p);
        });

        var location = box('Location Details',
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
            + field('Plot', plots.length ? plots.join(' · ') : m.plot_no)
            + field('Location', m.location)
            + field('Captured', m.captured)
            + field('Commissioned', m.committed || 'Not yet')
            + '</div>', 'tint');

        var where = (typeof window.renderRecordSummaryGroups === 'function' && d.storage_summary)
            ? box('Where The Records Went', window.renderRecordSummaryGroups(d.storage_summary), 'tint')
            : '';

        // A stage still to capture is the single most useful thing this sheet can say,
        // and "Nothing captured yet" three panels up is easy to scroll past. The duplex
        // sits as a draft until every stage is done, and the register only says "draft"
        // — so the sheet names the stage and the action that finishes it.
        var outstanding = (d.stages || []).filter(function (st) { return st.status !== 'done'; });

        var todo = (!committed && outstanding.length)
            ? '<div style="margin-top:12px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;'
              + 'padding:10px 12px;text-align:left;font-size:11px;color:#b91c1c">'
              + '<b>' + esc(outstanding.length) + ' stage'
              + (outstanding.length === 1 ? '' : 's') + ' still to capture:</b> '
              + outstanding.map(function (st) {
                    return esc(st.label) + ' (runs ' + esc(st.rank) + ')';
                }).join(', ')
              + '. Until then this duplex stays a draft and cannot be approved — reopen it '
              + 'from the register with <b>Continue capture</b>.</div>'
            : '';

        // Closing strip: green once done, amber while it is still only a plan.
        var strip = committed
            ? '<div style="margin-top:12px;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;'
              + 'padding:10px 12px;text-align:left;font-size:11px;color:#15803d">'
              + '<b>Commissioned.</b> ' + esc(t.issued || 0) + ' file number(s) issued, '
              + esc(t.commissioned || 0) + ' active, ' + esc(t.retired || 0) + ' decommissioned.</div>'
            : '<div style="margin-top:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:10px;'
              + 'padding:10px 12px;text-align:left;font-size:11px;color:#b45309">'
              + '<b>Nothing commissioned yet.</b> The numbers above are holding numbers; real file '
              + 'numbers are issued at the Land step.</div>';

        /**
         * Generated and Active, side by side.
         *
         * They answer two halves of one question - what this run issued, and what the
         * parcel still holds - and reading them as a pair is the whole point: "2
         * generated, 1 active" says the extension retired the merged file, which is
         * invisible when one panel sits a scroll below the other.
         *
         * Falls back to the stacked pair whenever there is only one of them: before
         * commissioning there is no Active panel, and a duplex whose last stage keeps
         * every file it made has nothing to contrast. A lone panel in a two-column grid
         * would sit at half width for no reason.
         *
         * Each column is min-width:0 so a long file number wraps inside its own box
         * instead of forcing the grid wider than the sheet.
         */
        var outcome = (generated && active)
            ? '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:start">'
              + '<div style="min-width:0">' + generated + '</div>'
              + '<div style="min-width:0">' + active + '</div>'
              + '</div>'
            : generated + active;

        return header + sources + stages + outcome + retired + location + where + todo + strip;
    }

    /** Fetch and show. `base` is the duplex-parcel-update URL prefix. */
    window.showDuplexSummary = function (base, id) {
        return fetch(base.replace(/\/$/, '') + '/' + id + '/summary', {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || !res.success) {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Could not load the summary' });
                return;
            }

            if (typeof Swal === 'undefined') {
                alert('Duplex ' + res.data.duplex.duplex_id);
                return;
            }

            return Swal.fire({
                title: '<span style="font-size:16px;font-weight:900;color:#1e293b">Duplex Summary</span>'
                    + '<p style="font-size:11px;font-weight:500;color:#94a3b8;margin-top:2px">'
                    + 'Everything this duplex did, from parcel files to commissioning</p>',
                html: buildHtml(res.data),
                width: 700,
                showCloseButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#2563eb',
                // This is the record of what just happened to the registry — losing it
                // to a stray click on the backdrop means re-opening it to read what was
                // commissioned. It closes on Close, the X, or Esc, and nothing else.
                allowOutsideClick: false
            });
        });
    };
})();
