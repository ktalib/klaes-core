/**
 * Add/Edit Buyers — session trace and draft autosave.
 *
 * WHY THIS EXISTS
 * Officers report that while they are keying buyers the form "just closes" and
 * everything typed is gone. Nothing reaches the server when that happens, so
 * storage/logs told us nothing and the fault could not be reproduced on demand.
 *
 * This file does two things, and neither of them changes how the form saves:
 *
 *   1. TRACE. It records what the page does — the Alpine component initialising,
 *      rows added and removed, the filled-row count dropping, submits and their
 *      HTTP status, script errors, navigations and unloads — and ships the batch
 *      to /buyer/client-log. Batches go out on a timer, immediately for anything
 *      alarming, and by sendBeacon on the way out so a trace survives the page
 *      that produced it. Everything lands in storage/logs/buyer_list.log next to
 *      the server's own entries, under one trace id per page visit.
 *
 *   2. DRAFT. It writes the in-progress rows to /buyer/draft/save every few
 *      seconds, keyed on the file number, and offers to restore them on the way
 *      back in. One draft per file: returning to the same file updates it rather
 *      than leaving a trail of copies.
 *
 * The trace carries field names, counts and lengths — not what was typed. The
 * draft carries the rows themselves, because restoring them is its whole point.
 *
 * Loaded from resources/views/sectionaltitling/partials/buyers-list-tab.blade.php,
 * so both pages that embed that partial are covered.
 */
(function () {
    'use strict';

    if (window.__buyerListDiagnostics) {
        return; // The partial can appear twice on a page; one trace is enough.
    }

    var ROW_FIELDS = [
        'buyerTitle', 'customTitle', 'firstName', 'middleName', 'surname',
        'landUse', 'unit_no', 'sectionNumber', 'unitMeasurement', 'cubicMeasurement'
    ];

    var FLUSH_INTERVAL_MS = 15000;
    var DRAFT_DEBOUNCE_MS = 2500;
    var BUFFER_LIMIT = 40;

    // Events worth interrupting the timer for — the ones that describe work
    // being lost, which is exactly what we may never get a later flush to send.
    var URGENT = {
        component_reinitialised: 1,
        rows_dropped: 1,
        form_emptied: 1,
        script_error: 1,
        unhandled_rejection: 1,
        submit_failed: 1,
        submit_http_error: 1,
        session_expired: 1,
        unload_with_unsaved_rows: 1,
        draft_save_failed: 1
    };

    var state = {
        traceId: null,
        applicationId: null,
        fileNo: null,
        buffer: [],
        draftTimer: null,
        lastFilledCount: 0,
        lastRowCount: 0,
        lastDraftSignature: null,
        submitting: false,
        savedThisVisit: false,
        componentInits: 0
    };

    /* ---------------------------------------------------------------- trace */

    function traceId() {
        if (!state.traceId) {
            state.traceId = 'bl-' + Date.now().toString(36) + '-' +
                Math.random().toString(36).slice(2, 8);
        }
        return state.traceId;
    }

    function log(event, detail) {
        state.buffer.push({
            event: event,
            at: new Date().toISOString(),
            detail: detail || {}
        });

        if (URGENT[event] || state.buffer.length >= BUFFER_LIMIT) {
            flush();
        }
    }

    function payloadFor(events) {
        var form = new FormData();
        form.append('trace_id', traceId());
        if (state.applicationId) form.append('application_id', state.applicationId);
        if (state.fileNo) form.append('file_no', state.fileNo);

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
     *        fetch would be cancelled with the page.
     */
    function flush(useBeacon) {
        if (!state.buffer.length) return;

        var events = state.buffer.splice(0, state.buffer.length);
        var body = payloadFor(events);

        try {
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon('/buyer/client-log', body);
                return;
            }

            fetch('/buyer/client-log', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                keepalive: true
            }).catch(function () {
                // A trace that cannot be delivered must never surface to the
                // officer as an error on a screen they are working in.
            });
        } catch (e) {
            /* ignore */
        }
    }

    /* ----------------------------------------------------------- form state */

    function form() {
        return document.getElementById('add-buyers-form');
    }

    /** Read the capture straight out of the DOM — the inputs are the source of
     *  truth here, not the Alpine array, which only controls how many rows exist. */
    function collectRows() {
        var el = form();
        if (!el) return [];

        var rows = [];

        el.querySelectorAll('[name^="records["]').forEach(function (input) {
            var match = input.name.match(/^records\[(\d+)\]\[(\w+)\]$/);
            if (!match) return;

            var index = parseInt(match[1], 10);
            var field = match[2];

            if (ROW_FIELDS.indexOf(field) === -1) return;

            rows[index] = rows[index] || {};
            rows[index][field] = input.value || '';
        });

        return rows.filter(function (row) { return !!row; });
    }

    function filledCount(rows) {
        return rows.filter(function (row) {
            return Object.keys(row).some(function (key) {
                return String(row[key] || '').trim() !== '';
            });
        }).length;
    }

    /** A cheap value-equality check, so an idle form does not autosave forever. */
    function signature(rows) {
        return JSON.stringify(rows);
    }

    /* ---------------------------------------------------------------- draft */

    function saveDraft(reason) {
        var rows = collectRows();
        var filled = filledCount(rows);
        var sig = signature(rows);

        if (sig === state.lastDraftSignature) return;
        if (!state.applicationId) return;

        // Never let an empty form overwrite a draft that has work in it. The
        // server enforces this too; doing it here as well saves the round trip.
        if (filled === 0 && state.lastFilledCount > 0) {
            log('draft_save_skipped_empty', { reason: reason, previous_filled: state.lastFilledCount });
            return;
        }
        if (filled === 0) return;

        state.lastDraftSignature = sig;

        var body = new FormData();
        body.append('application_id', state.applicationId);
        body.append('client_trace_id', traceId());
        if (state.fileNo) body.append('file_no', state.fileNo);
        body.append('_token', csrfToken());

        rows.forEach(function (row, i) {
            ROW_FIELDS.forEach(function (field) {
                body.append('rows[' + i + '][' + field + ']', row[field] || '');
            });
        });

        fetch('/buyer/draft/save', {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            keepalive: true
        })
            .then(function (response) {
                if (!response.ok) {
                    draftSaveFailed(reason, { status: response.status });
                    return null;
                }
                return response.json();
            })
            .then(function (data) {
                if (!data) return;
                if (data.success) {
                    markDraftSaved(data.last_saved_at, data.rows_filled);
                    log('draft_saved', { reason: reason, rows: rows.length, filled: filled });
                } else if (!data.ignored) {
                    draftSaveFailed(reason, { message: data.message || '' });
                }
            })
            .catch(function (error) {
                draftSaveFailed(reason, { error: String(error).slice(0, 200) });
            });
    }

    /**
     * A safety net that has stopped working must say so. Silence here would leave
     * the officer trusting a "Draft saved" they last saw ten minutes ago.
     *
     * The signature is cleared so the next keystroke retries rather than being
     * skipped as unchanged.
     */
    function draftSaveFailed(reason, detail) {
        state.lastDraftSignature = null;

        log('draft_save_failed', Object.assign({ reason: reason }, detail || {}));
        toast('error', 'Draft not saved — your entries are only in this page.');
    }

    function scheduleDraftSave(reason) {
        clearTimeout(state.draftTimer);
        state.draftTimer = setTimeout(function () { saveDraft(reason); }, DRAFT_DEBOUNCE_MS);
    }

    function closeDraft(reason) {
        if (!state.applicationId) return;

        var body = new FormData();
        body.append('application_id', state.applicationId);
        body.append('reason', reason);
        body.append('_token', csrfToken());

        fetch('/buyer/draft/close', {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            keepalive: true
        }).catch(function () { /* best effort */ });
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;

        var input = document.querySelector('#add-buyers-form input[name="_token"]');
        return input ? input.value : '';
    }

    /* ------------------------------------------------------------- restore  */

    function checkForDraft() {
        if (!state.applicationId || !form()) return;

        fetch('/buyer/draft/' + encodeURIComponent(state.applicationId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.has_draft) return;

                state.lastFilledCount = data.rows_filled || 0;
                log('draft_offered', {
                    rows: data.rows_total,
                    filled: data.rows_filled,
                    saved_at: data.last_saved_at,
                    own: data.is_own
                });
                showResumeBanner(data);
            })
            .catch(function () { /* a missing draft must not block the screen */ });
    }

    function showResumeBanner(draft) {
        var host = document.getElementById('buyer-draft-banner');
        if (!host) return;

        var who = draft.is_own ? 'You' : (draft.last_saved_by_name || 'Another officer');
        var when = formatWhen(draft.last_saved_at);

        host.innerHTML =
            '<div class="flex flex-col gap-3 rounded-md border border-blue-200 bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">' +
              '<div class="text-sm text-blue-900">' +
                '<p class="font-semibold">Unsaved buyers found for ' + escapeHtml(draft.draft_name || '') + '</p>' +
                '<p class="mt-1 text-xs">' + escapeHtml(who) + ' left ' + draft.rows_filled +
                  ' buyer' + (draft.rows_filled === 1 ? '' : 's') + ' unsaved' + (when ? ' on ' + when : '') + '.</p>' +
              '</div>' +
              '<div class="flex items-center gap-2">' +
                '<button type="button" id="buyer-draft-restore" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Restore them</button>' +
                '<button type="button" id="buyer-draft-discard" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:text-gray-800">Discard</button>' +
              '</div>' +
            '</div>';

        host.style.display = 'block';

        document.getElementById('buyer-draft-restore').addEventListener('click', function () {
            restoreRows(draft.rows || []);
        });

        document.getElementById('buyer-draft-discard').addEventListener('click', function () {
            closeDraft('discarded');
            state.lastFilledCount = 0;
            host.style.display = 'none';
            log('draft_discarded', { rows: draft.rows_total });
        });
    }

    /**
     * Put the rows back. The Alpine component owns how many row templates exist,
     * so ask it to grow first, then fill the inputs it rendered.
     */
    function restoreRows(rows) {
        if (!rows.length) return;

        if (typeof window.showBuyersForm === 'function') {
            window.showBuyersForm();
        }

        var component = alpineComponent();
        if (component && Array.isArray(component.buyers)) {
            while (component.buyers.length < rows.length) {
                component.addBuyer();
            }
        }

        // One frame for Alpine to render the rows it was just given.
        setTimeout(function () {
            var el = form();
            if (!el) return;

            var restored = 0;

            rows.forEach(function (row, index) {
                ROW_FIELDS.forEach(function (field) {
                    var input = el.querySelector('[name="records[' + index + '][' + field + ']"]');
                    if (!input || !row[field]) return;

                    input.value = row[field];
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    restored++;
                });
            });

            var host = document.getElementById('buyer-draft-banner');
            if (host) host.style.display = 'none';

            state.lastRowCount = rows.length;
            state.lastFilledCount = filledCount(collectRows());
            state.lastDraftSignature = signature(collectRows());

            log('draft_restored', { rows: rows.length, fields: restored });
            toast('success', rows.length + ' buyer' + (rows.length === 1 ? '' : 's') + ' restored');
        }, 60);
    }

    function markDraftSaved(savedAt, filled) {
        var badge = document.getElementById('buyer-draft-status');

        if (badge) {
            badge.textContent = 'Draft saved ' + (formatWhen(savedAt) || 'just now') +
                (filled ? ' — ' + filled + ' buyer' + (filled === 1 ? '' : 's') : '');
            badge.style.display = 'inline-flex';
        }

        toast('success', 'Draft saved' +
            (filled ? ' — ' + filled + ' buyer' + (filled === 1 ? '' : 's') : ''));
    }

    /**
     * Autosave receipt. Deliberately small and short-lived: this fires whenever
     * the capture changes, so it has to be readable at a glance and gone before it
     * is in the way. SweetAlert2 replaces a live toast rather than stacking them,
     * so a fast typist sees one that keeps refreshing, not a column of them.
     *
     * Swal is loaded by both pages that embed this form, but the fallback keeps the
     * receipt working on any page that later embeds it without SweetAlert2.
     */
    function toast(icon, title) {
        // SweetAlert2 REPLACES the open popup rather than stacking on it, so a
        // toast landing while a dialog is up would close it — throwing away the
        // Edit Buyer form mid-typing, or the "Saving..." loader mid-submit. An
        // autosave receipt is never worth that: skip it and let the badge report.
        if (isBlockingDialogOpen()) {
            return;
        }

        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: title,
                showConfirmButton: false,
                timer: icon === 'error' ? 4000 : 1800,
                timerProgressBar: true
            });
            return;
        }

        var host = document.getElementById('buyer-draft-toast');
        if (!host) {
            host = document.createElement('div');
            host.id = 'buyer-draft-toast';
            host.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:11000;' +
                'padding:.6rem .9rem;border-radius:.375rem;font-size:.8125rem;' +
                'box-shadow:0 4px 12px rgba(0,0,0,.15);transition:opacity .3s;';
            document.body.appendChild(host);
        }

        host.style.background = icon === 'error' ? '#fef2f2' : '#ecfdf5';
        host.style.color = icon === 'error' ? '#991b1b' : '#065f46';
        host.style.border = '1px solid ' + (icon === 'error' ? '#fecaca' : '#a7f3d0');
        host.textContent = title;
        host.style.opacity = '1';

        clearTimeout(host.__hideTimer);
        host.__hideTimer = setTimeout(function () {
            host.style.opacity = '0';
        }, icon === 'error' ? 4000 : 1800);
    }

    function formatWhen(value) {
        if (!value) return '';
        var date = new Date(value.replace ? value.replace(' ', 'T') : value);
        if (isNaN(date.getTime())) return '';

        var sameDay = date.toDateString() === new Date().toDateString();
        return sameDay
            ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString([], { day: '2-digit', month: 'short' }) + ' ' +
              date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    /** True when a SweetAlert dialog (not another toast) currently owns the screen. */
    function isBlockingDialogOpen() {
        if (!window.Swal || typeof window.Swal.isVisible !== 'function') return false;
        if (!window.Swal.isVisible()) return false;

        var popup = typeof window.Swal.getPopup === 'function' ? window.Swal.getPopup() : null;

        // A live toast may be safely replaced by the next one; a dialog may not.
        return !popup || !popup.classList.contains('swal2-toast');
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function alpineComponent() {
        var tab = document.getElementById('buyers-tab');
        return tab && tab._x_dataStack ? tab._x_dataStack[0] : null;
    }

    /* --------------------------------------------------------- watch the DOM */

    /**
     * The reported symptom, stated as a check: rows that had content in them no
     * longer do, and no submit explains it. Whatever the cause turns out to be,
     * this is the line in the log that dates it.
     */
    function checkForLoss(trigger) {
        var rows = collectRows();
        var filled = filledCount(rows);

        if (state.lastFilledCount > 0 && filled === 0 && !state.submitting) {
            log('form_emptied', {
                trigger: trigger,
                previous_filled: state.lastFilledCount,
                previous_rows: state.lastRowCount,
                rows_now: rows.length,
                form_visible: isFormVisible(),
                component_inits: state.componentInits
            });
        } else if (filled > 0 && filled < state.lastFilledCount && !state.submitting) {
            log('rows_dropped', {
                trigger: trigger,
                previous_filled: state.lastFilledCount,
                filled_now: filled
            });
        }

        // Hold the count steady across a submit: the page clears the form itself
        // on success, and that clearing is not a loss.
        if (!state.submitting) {
            state.lastFilledCount = filled;
        }

        state.lastRowCount = rows.length;
    }

    function isFormVisible() {
        var container = document.getElementById('buyers-form-container');
        if (!container) return false;
        return container.style.display !== 'none' && container.offsetParent !== null;
    }

    /* --------------------------------------------------------------- wiring */

    /**
     * Watch the /buyer/* calls the existing page makes, without changing them.
     * The status code is the point: a 419 or a redirect to the login page is the
     * difference between "the server refused" and "the session was already gone".
     */
    function wrapFetch() {
        var original = window.fetch;
        if (!original || original.__buyerListWrapped) return;

        var wrapped = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var watched = url.indexOf('/buyer/') !== -1 && url.indexOf('/buyer/client-log') === -1;
            var started = watched ? Date.now() : 0;

            var result = original.apply(this, arguments);

            if (!watched) return result;

            return result.then(function (response) {
                var detail = {
                    url: url,
                    status: response.status,
                    ms: Date.now() - started,
                    redirected: response.redirected
                };

                if (response.status === 419 || response.status === 401 ||
                    (response.redirected && /login/i.test(response.url || ''))) {
                    log('session_expired', detail);
                } else if (!response.ok) {
                    log('submit_http_error', detail);
                } else {
                    log('request_ok', detail);
                }

                return response;
            }, function (error) {
                log('submit_failed', { url: url, error: String(error).slice(0, 200) });
                throw error;
            });
        };

        wrapped.__buyerListWrapped = true;
        window.fetch = wrapped;
    }

    function wireForm() {
        var el = form();
        if (!el || el.__buyerListWired) return;
        el.__buyerListWired = true;

        // Travels with the submit so the server log and this trace share an id.
        var trace = document.createElement('input');
        trace.type = 'hidden';
        trace.name = 'client_trace_id';
        trace.value = traceId();
        el.appendChild(trace);

        el.addEventListener('input', function () {
            scheduleDraftSave('input');
        }, true);

        el.addEventListener('change', function () {
            scheduleDraftSave('change');
        }, true);

        // Capture phase: this runs before the page's own submit handler, so the
        // attempt is recorded even if that handler then throws.
        el.addEventListener('submit', function () {
            state.submitting = true;
            var rows = collectRows();

            log('submit_started', {
                rows: rows.length,
                filled: filledCount(rows),
                application_id: state.applicationId
            });

            saveDraft('pre_submit');

            // Long enough to outlast the page's own fetch and its Swal.
            setTimeout(function () { state.submitting = false; }, 20000);
        }, true);

        // Enter in a text box submits a form; on a long capture that reads as the
        // form closing on its own. Worth knowing it happened.
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target && event.target.tagName === 'INPUT' &&
                event.target.type !== 'submit') {
                log('enter_key_in_field', { field: event.target.name || '' });
            }
        }, true);
    }

    function wireLifecycle() {
        window.addEventListener('error', function (event) {
            log('script_error', {
                message: String(event.message || '').slice(0, 300),
                source: String(event.filename || '').slice(0, 200),
                line: event.lineno,
                col: event.colno
            });
        });

        window.addEventListener('unhandledrejection', function (event) {
            log('unhandled_rejection', {
                reason: String((event.reason && event.reason.message) || event.reason || '').slice(0, 300)
            });
        });

        document.addEventListener('visibilitychange', function () {
            log('visibility_' + document.visibilityState, {});
            if (document.visibilityState === 'hidden') {
                saveDraft('hidden');
                flush(true);
            }
        });

        window.addEventListener('pagehide', function () {
            reportUnload('pagehide');
        });

        window.addEventListener('beforeunload', function () {
            reportUnload('beforeunload');
        });

        // Anything that re-renders the panel under the form is a candidate cause
        // for the report, so date it rather than infer it later.
        var panel = document.getElementById('buyers-tab');
        if (panel && window.MutationObserver) {
            var mutationTimer = null;
            var observer = new MutationObserver(function () {
                // Alpine renders a long list as a burst of mutations; one check
                // once the burst settles says the same thing far more cheaply.
                clearTimeout(mutationTimer);
                mutationTimer = setTimeout(function () {
                    checkForLoss('dom_mutation');
                }, 250);
            });
            observer.observe(panel, { childList: true, subtree: true });
        }

        setInterval(function () {
            checkForLoss('poll');
            flush();
        }, FLUSH_INTERVAL_MS);
    }

    function reportUnload(via) {
        var rows = collectRows();
        var filled = filledCount(rows);

        if (filled > 0 && !state.savedThisVisit) {
            log('unload_with_unsaved_rows', { via: via, filled: filled, rows: rows.length });
            saveDraft('unload');
        } else {
            log('unload', { via: via, filled: filled });
        }

        flush(true);
    }

    /**
     * Called by the Alpine component in buyers-list-tab.blade.php each time it is
     * constructed. A second call on one page visit means the component was rebuilt
     * — which resets `buyers` to a single empty row and would look, from the
     * officer's chair, exactly like the form closing and the work vanishing.
     */
    function noteComponentInit(rowCount) {
        state.componentInits++;

        if (state.componentInits > 1) {
            log('component_reinitialised', {
                init_count: state.componentInits,
                rows_before: state.lastRowCount,
                filled_before: state.lastFilledCount,
                rows_now: rowCount
            });
        } else {
            log('component_init', { rows: rowCount });
        }
    }

    function start() {
        var appInput = document.getElementById('application_id');
        state.applicationId = appInput ? (appInput.value || '').trim() : null;

        var fileNoEl = document.getElementById('buyer-draft-file-no');
        state.fileNo = fileNoEl ? (fileNoEl.value || '').trim() : null;

        wrapFetch();
        wireForm();
        wireLifecycle();

        log('page_ready', {
            application_id: state.applicationId,
            file_no: state.fileNo,
            has_form: !!form(),
            url: window.location.pathname
        });

        checkForDraft();
    }

    window.__buyerListDiagnostics = {
        log: log,
        flush: flush,
        traceId: traceId,
        saveDraft: saveDraft,
        closeDraft: closeDraft,
        noteComponentInit: noteComponentInit,
        // Called by buyer-list-management.js once a save comes back successful:
        // the rows are in buyer_list now, so the draft has done its job.
        noteSaved: function (detail) {
            state.savedThisVisit = true;
            state.lastFilledCount = 0;
            state.lastDraftSignature = null;
            log('buyers_saved', detail || {});
            closeDraft('submitted');

            var badge = document.getElementById('buyer-draft-status');
            if (badge) badge.style.display = 'none';
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
