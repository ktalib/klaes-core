/**
 * Recommendation capture (/land-recommendations/create) — session trace.
 *
 * WHY THIS EXISTS
 * A batch capture on this screen runs for as long as it takes to key 40+ children.
 * That is long enough for the session to go, for a draft restore to paint the table
 * back differently than the officer remembers, or for one script error to leave the
 * form half-wired — and none of it reaches the server on its own, so the log would
 * show a capture that simply stopped.
 *
 * This file records what the page does — batch mode and kind changes, the mother
 * that was picked and how many children came back, ticked-row counts, draft saves
 * and restores, the submit and its outcome, script errors, unloads — and ships the
 * batch to /land-recommendations/client-log. Batches go out on a timer, immediately
 * for anything alarming, and by sendBeacon on the way out so a trace survives the
 * page that produced it. Everything lands in storage/logs/land_recommendation.log
 * next to the server's own entries, under one trace id per page visit.
 *
 * It observes; it does not participate. Every hook is a passive listener or a
 * wrapper that returns exactly what it wrapped, so nothing here can change how the
 * form saves. It reads counts, file numbers and HTTP statuses — not the capture.
 *
 * Loaded from resources/views/land_recommendations/form.blade.php.
 */
(function () {
    'use strict';

    if (window.__landRecDiagnostics) return;
    window.__landRecDiagnostics = true;

    var ENDPOINT = '/land-recommendations/client-log';
    var FLUSH_INTERVAL_MS = 15000;
    var BUFFER_LIMIT = 40;

    // Events worth interrupting the timer for — the ones that describe a capture in
    // trouble, which is exactly the case where a later flush may never come.
    var URGENT = {
        script_error: 1,
        unhandled_rejection: 1,
        session_expired: 1,
        draft_save_failed: 1,
        draft_restore_failed: 1,
        rows_dropped: 1,
        children_load_failed: 1,
        submit_http_error: 1,
        submit_blocked: 1,
        unload_with_unsaved_rows: 1,
        validation_errors_shown: 1
    };

    var state = {
        traceId: null,
        buffer: [],
        submitted: false,
        lastCheckedCount: 0
    };

    /* ---------------------------------------------------------------- trace */

    function traceId() {
        if (!state.traceId) {
            state.traceId = 'lr-' + Date.now().toString(36) + '-' +
                Math.random().toString(36).slice(2, 8);
        }
        return state.traceId;
    }

    function el(id) { return document.getElementById(id); }
    function val(id) { var node = el(id); return node ? node.value : ''; }

    function batchOn() {
        var toggle = el('batch-mode-toggle');
        return !!(toggle && toggle.checked);
    }

    function checkedRows() {
        return document.querySelectorAll('.batch-row-check:checked').length;
    }

    function log(event, detail) {
        state.buffer.push({
            event: event,
            at: new Date().toISOString(),
            detail: detail || {}
        });

        if (URGENT[event] || state.buffer.length >= BUFFER_LIMIT) flush();
    }

    function payloadFor(events) {
        var form = new FormData();
        form.append('trace_id', traceId());
        // The draft key ties the browser's account of the capture to the draft rows
        // and to the batch that eventually closes it — one key across three sources.
        if (val('batch-draft-key')) form.append('draft_key', val('batch-draft-key'));
        if (val('batch-mother-file-no')) form.append('mother_file_no', val('batch-mother-file-no'));
        form.append('batch_kind', batchOn() ? (val('batch-kind') || 'subdivision') : 'single');

        events.forEach(function (entry, i) {
            form.append('events[' + i + '][event]', entry.event);
            form.append('events[' + i + '][at]', entry.at);
            Object.keys(entry.detail || {}).forEach(function (key) {
                form.append('events[' + i + '][detail][' + key + ']', String(entry.detail[key]));
            });
        });

        return form;
    }

    /**
     * @param {boolean} useBeacon send via sendBeacon, for unload paths where a
     *        fetch would be cancelled along with the page.
     */
    function flush(useBeacon) {
        if (!state.buffer.length) return;

        var events = state.buffer.splice(0, state.buffer.length);
        var body = payloadFor(events);

        try {
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon(ENDPOINT, body);
                return;
            }

            window.__landRecNativeFetch(ENDPOINT, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                keepalive: true
            }).catch(function () {
                // A trace that cannot be delivered must never surface to the officer
                // as an error on a screen they are working in.
            });
        } catch (e) { /* ignore */ }
    }

    /* ------------------------------------------------- observing the traffic */

    // The capture's own requests are the clearest record of what happened, and they
    // are already being made — so they are observed rather than duplicated. The
    // wrapper returns the original promise untouched; the page cannot tell it is
    // there. The native reference is kept so the trace's own flush never re-enters.
    window.__landRecNativeFetch = window.fetch.bind(window);

    var WATCHED = [
        { match: /land-recommendations\/batch-drafts\/?(\?|$)/, ok: 'draft_saved', bad: 'draft_save_failed' },
        { match: /land-recommendations\/batch-drafts\/.+/, ok: 'draft_fetched', bad: 'draft_restore_failed' },
        { match: /land-recommendations\/subdivision-children/, ok: 'children_loaded', bad: 'children_load_failed' },
        { match: /land-recommendations\/subdivision-mothers/, ok: 'mothers_loaded', bad: 'mothers_load_failed' },
        { match: /land-recommendations\/batch-file-details/, ok: 'file_details_loaded', bad: 'file_details_failed' }
    ];

    // Draft autosave fires every few seconds. Its successes are counted and reported
    // once per flush instead of one line each, so the file stays readable; a failure
    // is always reported in full.
    var draftSaves = 0;

    window.fetch = function (input, init) {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        var watch = null;

        for (var i = 0; i < WATCHED.length; i++) {
            if (WATCHED[i].match.test(url)) { watch = WATCHED[i]; break; }
        }

        var promise = window.__landRecNativeFetch(input, init);
        if (!watch) return promise;

        promise.then(function (response) {
            if (response.status === 419 || response.status === 401) {
                // The session went while the form was still open — the single
                // clearest explanation for a capture that dies on submit.
                log('session_expired', { url: url, status: response.status });
                return;
            }

            if (!response.ok) {
                log(watch.bad, { url: url, status: response.status });
                return;
            }

            if (watch.ok === 'draft_saved') {
                draftSaves++;
                return;
            }

            log(watch.ok, { url: url, checked_rows: checkedRows() });
        }).catch(function (e) {
            log(watch.bad, { url: url, error: String((e && e.message) || e) });
        });

        return promise;
    };

    /* -------------------------------------------------------------- listeners */

    function start() {
        var form = el('land-recommendation-form');

        log('capture_opened', {
            batch_mode: batchOn(),
            is_edit: !!document.querySelector('input[name="_method"]'),
            action: form ? form.getAttribute('action') : '',
            referrer: document.referrer || ''
        });

        // Errors painted by the server on the way back. The rejection itself is
        // logged server-side; this records that the officer actually saw it, and
        // that the form they are now looking at is a re-render, not a fresh one.
        var shown = [];
        document.querySelectorAll('.text-red-500, .text-red-600').forEach(function (node) {
            var text = (node.textContent || '').trim();
            if (text && text.length < 200) shown.push(text);
        });
        if (shown.length) {
            log('validation_errors_shown', { count: shown.length, first: shown[0] });
        }

        var toggle = el('batch-mode-toggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                log('batch_mode_changed', { on: toggle.checked });
            });
        }

        document.querySelectorAll('.batch-kind-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                log('batch_kind_changed', { kind: radio.value });
            });
        });

        var motherApply = el('batch-mother-apply');
        if (motherApply) {
            motherApply.addEventListener('click', function () {
                var select = el('batch-mother-select');
                log('mother_selected', { mother: select ? select.value : '' });
            });
        }

        if (form) {
            // A ticked-row count that falls without the officer unticking anything is
            // the batch-table version of "my rows vanished", so the drop is recorded
            // with both counts rather than only the new one.
            form.addEventListener('change', function (e) {
                if (!e.target || !e.target.classList || !e.target.classList.contains('batch-row-check')) return;

                var now = checkedRows();
                if (now < state.lastCheckedCount - 1) {
                    log('rows_dropped', { from: state.lastCheckedCount, to: now });
                }
                state.lastCheckedCount = now;
            });

            form.addEventListener('submit', function (e) {
                if (e.defaultPrevented) {
                    // Something on the page stopped the save. The officer sees a
                    // message and nothing is posted, so the server log stays silent.
                    log('submit_blocked', {
                        batch_mode: batchOn(),
                        checked_rows: checkedRows()
                    });
                    return;
                }

                state.submitted = true;
                log('submit', {
                    batch_mode: batchOn(),
                    batch_kind: val('batch-kind'),
                    mother: val('batch-mother-file-no'),
                    checked_rows: checkedRows(),
                    children_expected: val('batch-children-expected'),
                    draft_saves: draftSaves
                });
                flush();
            });
        }

        window.addEventListener('error', function (e) {
            log('script_error', {
                message: e.message || '',
                source: (e.filename || '') + ':' + (e.lineno || 0)
            });
        });

        window.addEventListener('unhandledrejection', function (e) {
            log('unhandled_rejection', {
                reason: String((e.reason && e.reason.message) || e.reason || '')
            });
        });

        window.addEventListener('pagehide', function () {
            var checked = checkedRows();

            if (!state.submitted && checked > 0) {
                log('unload_with_unsaved_rows', { checked_rows: checked, draft_saves: draftSaves });
            } else {
                log('unload', { submitted: state.submitted, draft_saves: draftSaves });
            }

            flush(true);
        });

        setInterval(function () {
            if (draftSaves) {
                // One line per flush rather than one per autosave: enough to show the
                // draft was keeping up, without burying the rest of the file.
                log('draft_autosaves', { since_last_flush: draftSaves, checked_rows: checkedRows() });
                draftSaves = 0;
            }
            flush();
        }, FLUSH_INTERVAL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
