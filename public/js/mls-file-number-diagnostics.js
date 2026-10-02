/**
 * MLPP File Number commissioning (/mls-fileno -> Commission New File Number) —
 * session trace.
 *
 * WHY THIS EXISTS
 * When Generate fails, the officer is shown one sentence: "An error occurred while
 * generating the file number". That dialog is the `.catch` on the submit fetch, so
 * it fires for every distinct failure — a 500, a 419 after the session went, a 422
 * whose body was corrupted by PHP notice output, a client-side timeout, a dropped
 * connection — and looks identical in all of them. Worse, the failures that never
 * reach the controller leave nothing server-side to read afterwards: a 500 rendered
 * while the route middleware is still resolving (an empty APP_KEY does exactly this)
 * produces no entry for the click at all.
 *
 * This file records what the modal does — opened and closed, application and
 * allocation type, batch mode, prefix, land use, serial and preview changes,
 * related-file selection, the Generate and what actually came back from it, script
 * errors, unloads — and ships the batch to /mls-fileno/client-log. Batches go out on
 * a timer, immediately for anything alarming, and by sendBeacon on the way out so a
 * trace survives the page that produced it. Everything lands in
 * storage/logs/mls_file_number.log next to MlsFileNoController's own entries, under
 * one trace id per page visit and stamped with the tracking id the commissioning
 * carries.
 *
 * It observes; it does not participate. Every hook is a passive listener or a
 * wrapper that returns exactly what it wrapped, and response bodies are read from a
 * clone, so nothing here can change how a file number is generated or consume the
 * response the page is waiting for. It reads file numbers, serials, statuses and
 * counts — not the capture.
 *
 * Loaded from resources/views/generate_fileno/mlsfno.blade.php, first in the scripts
 * push so the wrapper is in place before mls_js.blade.php makes its first request.
 */
(function () {
    'use strict';

    if (window.__mlsFileNoDiagnostics) return;
    window.__mlsFileNoDiagnostics = true;

    var ENDPOINT = '/mls-fileno/client-log';
    var FLUSH_INTERVAL_MS = 15000;
    var BUFFER_LIMIT = 40;

    // Events worth interrupting the timer for — the ones that describe a
    // commissioning in trouble, which is exactly the case where a later flush may
    // never come because the officer closes the tab.
    var URGENT = {
        script_error: 1,
        unhandled_rejection: 1,
        session_expired: 1,
        generate_http_error: 1,
        generate_validation_failed: 1,
        generate_not_json: 1,
        generate_dirty_body: 1,
        generate_timeout: 1,
        generate_network_error: 1,
        generate_rejected: 1,
        generate_blocked: 1,
        serial_status_failed: 1,
        dependent_data_failed: 1,
        preview_empty_on_submit: 1,
        unload_with_unsaved_form: 1
    };

    var state = {
        traceId: null,
        buffer: [],
        submits: 0,
        generateRequests: 0,
        generated: false,
        modalOpen: false
    };

    /* ---------------------------------------------------------------- context */

    function traceId() {
        if (!state.traceId) {
            state.traceId = 'mls-' + Date.now().toString(36) + '-' +
                Math.random().toString(36).slice(2, 8);
        }
        return state.traceId;
    }

    function el(id) { return document.getElementById(id); }

    function val(id) {
        var node = el(id);
        return node ? String(node.value || '') : '';
    }

    function text(id) {
        var node = el(id);
        return node ? String(node.textContent || '').trim() : '';
    }

    // The modal is an Alpine component, so the state the form actually posts lives
    // there rather than on the inputs. mls_js.blade.php reaches it the same way.
    function alpine() {
        try {
            var root = document.querySelector('[x-data="fileNumberGenerator()"]');
            return (root && root._x_dataStack) ? root._x_dataStack[0] : null;
        } catch (e) {
            return null;
        }
    }

    function applicationType() {
        var checked = document.querySelector('input[name="application_type"]:checked');
        return checked ? checked.value : '';
    }

    function batchOn() {
        var data = alpine();
        return !!(data && data.batchMode);
    }

    // The preview is what the officer believes they are about to create, so an empty
    // one at submit time is worth recording on its own.
    function preview() {
        return text('mlsfPreview');
    }

    function snapshot() {
        var data = alpine();

        return {
            application_type: applicationType(),
            allocation_type: data ? (data.defaultAllocationType || '') : '',
            file_option: val('fileOption'),
            prefix: val('prefix'),
            land_use: val('landUse'),
            purpose: val('purpose'),
            customer_type: val('customerType'),
            year: val('year'),
            serial_no: val('serialNo'),
            batch_qty: batchOn() ? val('batchQuantity') : '',
            preview: preview(),
            has_related: data ? !!data.hasRelatedFile : false,
            related_file_no: data ? String(data.relatedFileNo || '') : ''
        };
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
        // The tracking id is the one identifier shared by the browser, the fileNumber
        // row and both tracking lines — the handle for finding the record later.
        if (val('trackingIdInput')) form.append('tracking_id', val('trackingIdInput'));
        if (preview()) form.append('file_number_preview', preview());
        form.append('application_type', applicationType());
        form.append('batch_mode', batchOn() ? 'batch' : 'single');

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
     * @param {boolean} useBeacon send via sendBeacon, for unload paths where a fetch
     *        would be cancelled along with the page.
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

            window.__mlsFileNoNativeFetch(ENDPOINT, {
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

    // The screen's own requests are the clearest record of what happened, and they
    // are already being made — so they are observed rather than duplicated. The
    // wrapper returns the original promise untouched; the page cannot tell it is
    // there. The native reference is kept so the trace's own flush never re-enters.
    window.__mlsFileNoNativeFetch = window.fetch.bind(window);

    var WATCHED = [
        { match: /mls-fileno\/generate-batch/, kind: 'batch' },
        { match: /mls-fileno\/generate(\?|$)/, kind: 'generate' },
        { match: /mls-fileno\/serial-status/, ok: 'serial_status_loaded', bad: 'serial_status_failed' },
        { match: /mls-fileno\/get-dependent-data/, ok: 'dependent_data_loaded', bad: 'dependent_data_failed' },
        { match: /mls-fileno\/temp-file-details/, ok: 'temp_file_details_loaded', bad: 'temp_file_details_failed' }
    ];

    /**
     * PHP notice/warning output leaking into a JSON response.
     *
     * display_errors writes straight into the response body, ahead of whatever the
     * framework returns. The status line is then perfectly correct — a 422 with its
     * validation messages — while response.json() still throws, so the officer gets
     * the generic dialog instead of the reason their form was rejected. Detecting the
     * shape here is what tells the two apart in the log.
     */
    function looksLikePhpOutput(body) {
        return /^\s*(<br\s*\/?>|<b>(Warning|Notice|Deprecated|Fatal error|Parse error)<\/b>)/i.test(body) ||
            /<b>(Warning|Notice|Deprecated|Fatal error|Parse error)<\/b>/i.test(body.slice(0, 400));
    }

    /**
     * Read a response body without touching the one the page is waiting on.
     *
     * This is the whole point of the file: today the officer's "An error occurred"
     * and the server's silence are all anyone has. An excerpt of the actual body
     * separates a Laravel exception page from a 419 redirect from a PHP notice
     * prepended to a valid 422 — different repairs behind one dialog.
     */
    function describeBody(event, response, extra) {
        var detail = { status: response.status, content_type: response.headers.get('content-type') || '' };
        Object.keys(extra).forEach(function (k) { detail[k] = extra[k]; });

        try {
            response.clone().text().then(function (body) {
                body = String(body || '');
                detail.body_excerpt = body.slice(0, 600);

                if (looksLikePhpOutput(body)) {
                    // The response the page could not parse was otherwise fine. This
                    // is a php.ini problem on the server, not a bug in the form.
                    detail.php_output_in_body = 'true';
                    log('generate_dirty_body', detail);
                    return;
                }

                log(event, detail);
            }).catch(function () {
                log(event, detail);
            });
        } catch (e) {
            log(event, detail);
        }
    }

    function watchGenerate(promise, url, kind) {
        var startedAt = Date.now();
        state.generateRequests++;

        log('generate_request_sent', {
            url: url,
            kind: kind,
            attempt: state.generateRequests,
            preview: preview(),
            tracking_id: val('trackingIdInput'),
            serial_no: val('serialNo')
        });

        promise.then(function (response) {
            var elapsed = Date.now() - startedAt;
            var contentType = response.headers.get('content-type') || '';

            if (response.status === 419 || response.status === 401) {
                // The session went while the modal was open — the single clearest
                // explanation for a Generate that dies with no server-side trace.
                describeBody('session_expired', response, { url: url, ms: elapsed });
                return;
            }

            if (response.status === 422) {
                // A rejection the officer is entitled to see field by field. It is
                // recorded separately from a 500 because nothing is broken here
                // except what the page does with it.
                describeBody('generate_validation_failed', response, { url: url, ms: elapsed });
                return;
            }

            if (!response.ok) {
                describeBody('generate_http_error', response, { url: url, ms: elapsed });
                return;
            }

            if (contentType.indexOf('json') === -1) {
                // A 200 that is not JSON still ends in the generic dialog, because
                // the page calls response.json() unconditionally.
                describeBody('generate_not_json', response, { url: url, ms: elapsed });
                return;
            }

            response.clone().json().then(function (data) {
                if (data && data.success) {
                    state.generated = true;
                    log('generate_succeeded', {
                        ms: elapsed,
                        attempt: state.generateRequests,
                        file_number: String(
                            (data.data && (data.data.file_number || data.data.full_file_number || data.data.mlsfNo)) ||
                            data.file_number || ''
                        ),
                        tracking_id: val('trackingIdInput')
                    });
                } else {
                    // A clean rejection from the controller: the server logged its
                    // own reason, this records what the officer was shown.
                    log('generate_rejected', {
                        ms: elapsed,
                        attempt: state.generateRequests,
                        message: String((data && data.message) || '').slice(0, 300),
                        preview: preview()
                    });
                }
            }).catch(function () {
                // A 200 with a JSON content type whose body still will not parse is
                // the leaked-output case again, so the body itself decides.
                describeBody('generate_not_json', response, { url: url, ms: elapsed });
            });
        }).catch(function (e) {
            var elapsed = Date.now() - startedAt;
            var name = (e && e.name) || '';

            // The page aborts its own request on a timer; an abort here is that
            // timer firing, not a network fault, and the two need different repairs.
            log(name === 'AbortError' ? 'generate_timeout' : 'generate_network_error', {
                url: url,
                ms: elapsed,
                attempt: state.generateRequests,
                error: String((e && e.message) || e)
            });
        });
    }

    window.fetch = function (input, init) {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        var watch = null;

        for (var i = 0; i < WATCHED.length; i++) {
            if (WATCHED[i].match.test(url)) { watch = WATCHED[i]; break; }
        }

        var promise = window.__mlsFileNoNativeFetch(input, init);
        if (!watch) return promise;

        if (watch.kind) {
            watchGenerate(promise, url, watch.kind);
            return promise;
        }

        promise.then(function (response) {
            if (response.status === 419 || response.status === 401) {
                log('session_expired', { url: url, status: response.status });
                return;
            }

            if (!response.ok) {
                log(watch.bad, { url: url, status: response.status });
                return;
            }

            log(watch.ok, { url: url });
        }).catch(function (e) {
            log(watch.bad, { url: url, error: String((e && e.message) || e) });
        });

        return promise;
    };

    /* -------------------------------------------------------------- listeners */

    function start() {
        log('screen_opened', { referrer: document.referrer || '' });

        var modal = el('generateModal');
        if (modal) {
            // The modal is shown by toggling `hidden`, so its class list is the only
            // signal that a commissioning has actually begun.
            new MutationObserver(function () {
                var open = !modal.classList.contains('hidden');
                if (open === state.modalOpen) return;

                state.modalOpen = open;
                if (open) {
                    state.submits = 0;
                    state.generateRequests = 0;
                    state.generated = false;
                    log('modal_opened', snapshot());
                } else {
                    log('modal_closed', {
                        submits: state.submits,
                        requests: state.generateRequests,
                        generated: state.generated
                    });
                }
            }).observe(modal, { attributes: true, attributeFilter: ['class'] });
        }

        var form = el('generateForm');
        if (form) {
            form.addEventListener('submit', function () {
                state.submits++;
                var snap = snapshot();
                snap.submit_no = state.submits;
                snap.tracking_id = val('trackingIdInput');

                // A submit is not yet a request: the form runs its own validation
                // gates and a confirmation summary that replays the event. Pairing
                // this with generate_request_sent is what shows whether a click ever
                // became a POST — and, when it did not, that the page stopped it.
                log('submit', snap);

                if (!snap.preview) {
                    log('preview_empty_on_submit', snap);
                }

                setTimeout(function () {
                    if (state.submits > 0 && state.generateRequests === 0) {
                        log('generate_blocked', {
                            submits: state.submits,
                            reason: 'no request left the page within 5s of submit'
                        });
                    }
                }, 5000);
            });

            // Override reopens the serial/year for a manual number, which is the
            // usual response to a clash — worth marking so a second Generate in the
            // log is not read as a retry of the same one.
            var override = el('overrideButton');
            if (override) {
                override.addEventListener('click', function () {
                    log('override_opened', {
                        year: val('year'),
                        serial_no: val('serialNo'),
                        preview: preview()
                    });
                });
            }

            ['prefix', 'landUse', 'purpose', 'customerType', 'fileOption', 'year', 'serialNo']
                .forEach(function (id) {
                    var node = el(id);
                    if (!node) return;

                    node.addEventListener('change', function () {
                        log('field_changed', {
                            field: id,
                            value: String(node.value || '').slice(0, 120),
                            preview: preview()
                        });
                    });
                });

            document.querySelectorAll('input[name="application_type"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    log('application_type_changed', { value: radio.value, batch_mode: batchOn() });
                });
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
            if (state.modalOpen && !state.generated) {
                // The officer left with the form open and nothing commissioned. Read
                // with the submit/request counts, this separates "gave up" from
                // "clicked Generate and the page died".
                log('unload_with_unsaved_form', {
                    submits: state.submits,
                    requests: state.generateRequests,
                    preview: preview()
                });
            } else {
                log('unload', { generated: state.generated, requests: state.generateRequests });
            }

            flush(true);
        });

        setInterval(function () { flush(); }, FLUSH_INTERVAL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
