/**
 * Deeds workflow indicator — Valuation -> Consent -> Registration.
 *
 * Paints the stage state of a file wherever a page marks a spot for it:
 *
 *     <span data-pipeline-file="KN3013"></span>
 *     <span data-pipeline-file="KN3013" data-pipeline-consent-type="Assignment"></span>
 *
 * Every marker on the page is resolved in ONE request, so a list of several
 * hundred rows costs a single round trip rather than one per row.
 *
 * Read-only: it reports what the server says and never decides policy. Whether
 * a missing stage actually blocks a save is config, applied server-side — this
 * only shows where a file stands.
 */
(function () {
    'use strict';

    var ENDPOINT = '/deeds-pipeline/status';

    var STAGES = [
        { key: 'valuation', letter: 'V', label: 'Valuation' },
        { key: 'consent', letter: 'C', label: 'Consent' },
        { key: 'print', letter: 'P', label: 'Print Consent Letter' },
        { key: 'registration', letter: 'R', label: 'Registration' }
    ];

    /**
     * One hue per stage, so the four steps are distinguishable at a glance
     * rather than being four identical grey circles.
     *
     * The hue is used while a stage is still outstanding or current. A CLEARED
     * stage is always emerald and a SKIPPED one always amber, whichever stage it
     * is: "done" and "out of order" are states an officer reads across the whole
     * strip, and re-colouring them per stage would destroy that.
     */
    var STAGE_HUE = {
        valuation: '#7c3aed',     // violet
        consent: '#2563eb',       // blue
        print: '#0891b2',         // cyan
        registration: '#db2777'   // magenta
    };

    var TONE = {
        done: 'background:#10b981;border-color:#10b981;color:#fff',
        skipped: 'background:#f59e0b;border-color:#f59e0b;color:#fff',
        pending: 'background:#fff;border-color:#cbd5e1;color:#94a3b8',
        na: 'background:#f8fafc;border-color:#e2e8f0;color:#cbd5e1'
    };

    /**
     * A stage is "skipped" rather than merely "pending" when a LATER stage is
     * already done — that is the case worth flagging, because it means the
     * pipeline was worked out of order.
     */
    function stateOf(stages, index) {
        var stage = stages[STAGES[index].key];
        // A stage that does not apply to this dealing — Valuation on a Gift or
        // a Mortgage — is neither done nor outstanding. Shown neutral so it
        // never reads as a green tick for work nobody did, and never as a red
        // step somebody has to go and clear.
        if (stage && stage.not_required) {
            return 'na';
        }
        if (stage && stage.done) {
            return 'done';
        }
        for (var i = index + 1; i < STAGES.length; i++) {
            var later = stages[STAGES[i].key];
            if (later && later.done) {
                return 'skipped';
            }
        }
        return 'pending';
    }

    function paint(el, stages) {
        if (!stages) {
            el.textContent = '';
            return;
        }

        var wrap = document.createElement('span');
        wrap.style.cssText = 'display:inline-flex;align-items:center;gap:3px';

        STAGES.forEach(function (meta, i) {
            var state = stateOf(stages, i);
            var stage = stages[meta.key] || {};

            var dot = document.createElement('span');
            dot.textContent = meta.letter;
            // Outstanding steps carry their own stage hue so a row's four dots
            // are told apart at a glance; cleared and out-of-order stay emerald
            // and amber, which are read across the whole column.
            var hue = STAGE_HUE[meta.key] || '#94a3b8';
            var tone = state === 'pending'
                ? 'background:#fff;border-color:' + hue + '59;color:' + hue
                : TONE[state];
            dot.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;'
                + 'width:18px;height:18px;border-radius:9999px;border:1.5px solid;'
                + 'font-size:10px;font-weight:700;line-height:1;' + tone;
            dot.title = meta.label + ': ' + (stage.message || (state === 'done' ? 'Done' : 'Pending'))
                + (state === 'skipped' ? ' (taken out of order)' : '');
            wrap.appendChild(dot);
        });

        el.innerHTML = '';
        el.appendChild(wrap);
    }

    function refresh(root) {
        var nodes = Array.prototype.slice.call(
            (root || document).querySelectorAll('[data-pipeline-file]')
        ).filter(function (el) {
            return (el.dataset.pipelineFile || '').trim() !== '';
        });

        if (!nodes.length) {
            return;
        }

        // One marker may carry a consent type (the capture screen cares about a
        // single dealing); those are asked for individually. Everything else is
        // batched.
        var batch = [];
        var singles = [];

        nodes.forEach(function (el) {
            if ((el.dataset.pipelineConsentType || '').trim() !== '') {
                singles.push(el);
            } else {
                batch.push(el);
                var key = el.dataset.pipelineFile.trim().toUpperCase();
                if (batch.files === undefined) { batch.files = {}; }
                batch.files[key] = true;
            }
        });

        if (batch.length) {
            var files = Object.keys(batch.files || {});
            var qs = files.map(function (f) { return 'files[]=' + encodeURIComponent(f); }).join('&');

            fetch(ENDPOINT + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) { return; }
                    batch.forEach(function (el) {
                        paint(el, res.data[el.dataset.pipelineFile.trim().toUpperCase()]);
                    });
                })
                .catch(function (err) {
                    // A failed lookup must leave the row readable, not wedged on
                    // a spinner — the indicator is supplementary to the row.
                    console.error('deeds-pipeline: batch lookup failed', err);
                    batch.forEach(function (el) { el.textContent = ''; });
                });
        }

        singles.forEach(function (el) {
            var url = ENDPOINT
                + '?file_number=' + encodeURIComponent(el.dataset.pipelineFile.trim())
                + '&consent_type=' + encodeURIComponent(el.dataset.pipelineConsentType.trim());

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success) { return; }
                    paint(el, res.data[el.dataset.pipelineFile.trim().toUpperCase()]);
                })
                .catch(function (err) {
                    console.error('deeds-pipeline: lookup failed', err);
                    el.textContent = '';
                });
        });
    }

    /* ------------------------------------------------------------------ *
     * Full strip, for the capture cards
     * ------------------------------------------------------------------ */

    /**
     * Which stage each card is performing, and therefore which stage must
     * already be done before it may proceed. Valuation opens the pipeline, so
     * it has no predecessor and can never be blocked.
     */
    var REQUIRES = {
        valuation: null,
        consent: 'valuation',
        registration: 'print'
    };

    var STAGE_LABEL = { valuation: 'Valuation', consent: 'Consent', print: 'Print Consent Letter', registration: 'Registration' };



    /**
     * One icon per stage, drawn inline so the strip does not depend on an icon
     * library having initialised — these modals render after Lucide has already
     * swept the page, so a <i data-lucide> would stay an empty tag.
     *
     *   valuation    scales — the property being assessed
     *   consent      stamped document — the Commissioner's approval
     *   registration bound register — the instrument entered on the register
     */
    var ICONS = {
        valuation: '<path d="M12 3v18M7 7l-4 7h8L7 7zm10 0l-4 7h8l-4-7zM5 21h14"/>',
        consent: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 14l2 2 4-4"/>',
        print: '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/>',
        registration: '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5z"/><path d="M9 7h7M9 11h7"/>'
    };

    function icon(stage, colour) {
        return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="' + colour
            + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            + ICONS[stage] + '</svg>';
    }

    /**
     * Whether this card may proceed for this file.
     *
     * Mirrors the server rules exactly — ConsentApplicationController::store()
     * and InstrumentController::consentGateFailure() — including reading the
     * same gate modes, so the button state and the save result always agree.
     * Only 'block' stops the card; 'warn' reports and lets it through.
     */
    function evaluate(stages, stage, gates) {
        var needs = REQUIRES[stage];
        if (!needs || !stages) {
            return { blocked: false, warn: null };
        }

        var prior = stages[needs];
        if (prior && prior.done) {
            return { blocked: false, warn: null };
        }

        var gateName = stage === 'consent'
            ? 'valuation_before_consent'
            : 'consent_print_before_registration';
        var mode = (gates && gates[gateName]) || 'off';
        var reason = STAGE_LABEL[needs] + ' must be completed for this file before '
            + STAGE_LABEL[stage] + ' can be captured.'
            + (prior && prior.message ? ' ' + prior.message + '.' : '');

        if (mode === 'block') {
            return { blocked: true, warn: reason };
        }
        if (mode === 'warn') {
            return { blocked: false, warn: reason };
        }
        return { blocked: false, warn: null };
    }

    function stripHtml(stages, stage, verdict) {
        var html = '<div style="display:flex;align-items:flex-start;gap:0">';

        STAGES.forEach(function (meta, i) {
            var state = stateOf(stages, i);
            var s = stages[meta.key] || {};
            var isCurrent = meta.key === stage && state !== 'done';

            /* Four states, four readings:
                 done      solid emerald, white tick — nothing to do here
                 skipped   solid amber — done out of order, look at this
                 current   white disc, indigo ring — you are here
                 pending   slate on a tinted disc, NOT near-white: at #cbd5e1 on
                           white the step was barely visible and the strip read
                           as one item rather than three. */
            var hue = STAGE_HUE[meta.key] || '#64748b';
            var colour = state === 'na' ? '#94a3b8'
                : state === 'done' ? '#059669'
                    : state === 'skipped' ? '#d97706'
                        : hue;
            var fill = state === 'done' || state === 'skipped'
                ? colour
                : (isCurrent ? '#fff' : '#f1f5f9');
            var ring = isCurrent ? ';box-shadow:0 0 0 4px ' + hue + '26' : '';
            // A cleared stage shows a tick and a skipped one a warning, because
            // what happened matters more there than which stage it was. A stage
            // still to do shows its own icon, so the strip reads as a pipeline
            // rather than as three anonymous circles.
            var glyph = state === 'na'
                ? '<span style="font-size:15px;font-weight:700">&ndash;</span>'
                : state === 'done'
                ? '<span style="font-size:13px;font-weight:700">&#10003;</span>'
                : state === 'skipped'
                    ? '<span style="font-size:14px;font-weight:700">!</span>'
                    : icon(meta.key, colour);

            html += '<div style="flex:1;min-width:0">'
                + '<div style="display:flex;align-items:center">'
                + '<span style="display:inline-flex;align-items:center;justify-content:center;'
                + 'width:30px;height:30px;border-radius:9999px;border:2px solid ' + colour + ';'
                + 'background:' + fill + ';color:'
                + (state === 'done' || state === 'skipped' ? '#fff' : colour) + ';'
                + 'font-size:12px;font-weight:700;flex:0 0 auto' + ring + '">' + glyph + '</span>'
                + (i < STAGES.length - 1
                    ? '<span style="flex:1;height:3px;margin:0 8px;border-radius:2px;background:'
                    + (state === 'done' ? '#059669' : '#e2e8f0') + '"></span>'
                    : '')
                + '</div>'
                + '<div style="margin-top:6px;padding-right:8px">'
                + '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">'
                + '<span style="font-size:13px;font-weight:700;color:'
                + (state === 'na' ? '#94a3b8' : state === 'done' ? '#047857' : state === 'skipped' ? '#b45309' : hue)
                + '">' + meta.label + '</span>'
                /* Says outright whether this step applies to the dealing being
                   captured, so "Not required" is a stated rule rather than
                   something inferred from a greyed-out circle. */
                + '<span style="font-size:9px;font-weight:700;letter-spacing:.04em;'
                + 'text-transform:uppercase;padding:1px 6px;border-radius:9999px;'
                + (state === 'na'
                    ? 'background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0'
                    : 'background:' + hue + '14;color:' + hue + ';border:1px solid ' + hue + '33')
                + '">' + (state === 'na' ? 'Not required' : 'Required') + '</span>'
                + '</div>'
                + '<div style="font-size:11.5px;color:#475569;line-height:1.35;margin-top:1px">'
                + (s.message || '—') + '</div>'
                + (meta.key === 'valuation' && s.amount
                    ? '<div style="font-size:11px;font-weight:600;color:#334155">&#8358;'
                    + Number(s.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) + '</div>'
                    : '')
                + '</div></div>';
        });

        html += '</div>';

        if (verdict.warn) {
            var bg = verdict.blocked ? '#fef2f2' : '#fffbeb';
            var bd = verdict.blocked ? '#fecaca' : '#fde68a';
            var fg = verdict.blocked ? '#b91c1c' : '#b45309';
            html += '<div style="margin-top:10px;padding:8px 10px;border-radius:8px;'
                + 'background:' + bg + ';border:1px solid ' + bd + ';color:' + fg + ';'
                + 'font-size:12px;font-weight:600">'
                + (verdict.blocked ? 'Blocked — ' : 'Out of order — ') + verdict.warn
                + '</div>';
        }

        return html;
    }

    /**
     * Render the workflow strip for one file into a container, and report back
     * whether this card may proceed.
     *
     * @param {Element}  el         container to render into
     * @param {Object}   opts
     * @param {string}   opts.file          file number (empty clears the strip)
     * @param {string}   [opts.consentType] narrows consent/registration to one dealing
     * @param {string}   opts.stage         valuation | consent | registration
     * @param {Function} [opts.onState]     called with { blocked, warn, stages }
     */
    function mount(el, opts) {
        if (!el) { return; }

        var file = (opts.file || '').trim();
        var stage = opts.stage;

        // Several things can ask for the same strip — a picker callback, a type
        // change, the watcher below. Re-rendering the identical state would mean
        // duplicate round trips, so an unchanged file/type pair is a no-op.
        var key = file.toUpperCase() + '|' + (opts.consentType || '') + '|' + stage;
        if (el.dataset.pipelineKey === key && !opts.force) { return; }
        el.dataset.pipelineKey = key;

        if (!file) {
            el.innerHTML = '';
            el.style.display = 'none';
            if (opts.onState) { opts.onState({ blocked: false, warn: null, stages: null }); }
            return;
        }

        el.style.display = '';
        el.innerHTML = '<div style="font-size:12px;color:#64748b">Checking workflow for ' + file + '…</div>';

        var url = ENDPOINT + '?file_number=' + encodeURIComponent(file)
            + (opts.consentType ? '&consent_type=' + encodeURIComponent(opts.consentType) : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) { throw new Error('lookup failed'); }
                var stages = res.data[file.toUpperCase()];
                var verdict = evaluate(stages, stage, res.gates);
                el.innerHTML = stripHtml(stages, stage, verdict);
                if (opts.onState) { opts.onState({ blocked: verdict.blocked, warn: verdict.warn, stages: stages }); }
            })
            .catch(function (err) {
                console.error('deeds-pipeline: mount failed', err);
                // The server enforces the same rule on save, so a failed lookup
                // must not hard-block the officer here — it reports and lets the
                // save be the authority.
                el.innerHTML = '<div style="font-size:12px;color:#b45309">'
                    + 'Could not check the workflow for this file. It will be verified when you save.</div>';
                if (opts.onState) { opts.onState({ blocked: false, warn: null, stages: null }); }
            });
    }

    /**
     * Keep a strip in step with whatever file is currently selected.
     *
     * Polled rather than event-driven on purpose. These screens set the file
     * number every possible way — a picker modal writing .value directly, a
     * searchable select, a server-rendered seed value, a form reset — and a
     * programmatic assignment fires no input or change event, so listeners miss
     * most of them. A cheap poll catches all of it; mount() dedupes, so an
     * unchanged selection costs nothing but a string compare.
     *
     * @param {string}   containerSel  the strip container
     * @param {Object}   opts
     * @param {string}   opts.fileSel  selector of the input holding the file number
     * @param {string}   opts.stage    valuation | consent | registration
     * @param {Function} [opts.consentType] returns the consent type to narrow by
     * @param {Function} [opts.onState]
     */
    var watched = {};

    function watch(containerSel, opts) {
        // Idempotent: callers register from inside handlers that run many times
        // over a screen's life, and one timer per call would pile up intervals
        // all polling the same container.
        if (watched[containerSel]) { return; }
        watched[containerSel] = true;

        function tick() {
            var el = document.querySelector(containerSel);
            if (!el) { return; }

            var input = document.querySelector(opts.fileSel);
            // Hidden inputs and comboboxes both expose .value; fall back to the
            // element's text for anything rendering the number as content.
            var file = input ? (input.value || input.textContent || '') : '';

            mount(el, {
                file: file,
                consentType: opts.consentType ? opts.consentType() : '',
                stage: opts.stage,
                onState: opts.onState
            });
        }

        tick();
        setInterval(tick, 600);
    }

    // Exposed so a screen that adds rows after load — a filter, a paginated
    // table, a modal — can repaint without a reload.
    window.DeedsPipeline = { refresh: refresh, mount: mount, evaluate: evaluate, watch: watch };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { refresh(); });
    } else {
        refresh();
    }
})();
