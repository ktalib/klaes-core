<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ strtoupper($scope) }} Batch Print &mdash; {{ count($documents) }} record{{ count($documents) === 1 ? '' : 's' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111827;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #1e3a8a;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        .toolbar strong { font-size: 1rem; letter-spacing: 0.5px; }
        .toolbar .status { font-size: 0.85rem; opacity: 0.9; flex: 1; }
        .toolbar button {
            background: #fff;
            color: #1e3a8a;
            border: 0;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .toolbar button:disabled { opacity: 0.5; cursor: not-allowed; }
        .toolbar button.secondary { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.5); }

        .pages {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .page-frame {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            display: block;
        }

        @media print {
            body { background: #fff; }
            .toolbar, .toolbar * { display: none !important; }
            .pages { padding: 0; gap: 0; }
            .page-frame {
                width: 100% !important;
                /* Leave breathing room below the grid so footer row isn't clipped. */
                height: 260mm !important;
                min-height: 260mm !important;
                max-height: 260mm !important;
                border: 0 !important;
                margin: 0 !important;
                display: block !important;
                overflow: hidden !important;
                page-break-inside: avoid;
                break-inside: avoid;
                page-break-after: always;
                break-after: page;
            }
            .page-frame:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ strtoupper($scope) }} Batch Print</strong>
        <span class="status" id="batch-status">Loading {{ count($documents) }} document{{ count($documents) === 1 ? '' : 's' }}&hellip;</span>
        <button id="print-btn" type="button" disabled onclick="window.print()">Print All</button>
        <button type="button" class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="pages">
        @foreach($documents as $i => $doc)
            <iframe class="page-frame" data-idx="{{ $i }}" src="{{ $doc['url'] }}" scrolling="no" loading="eager" title="{{ $doc['title'] ?? ('Document '.($i + 1)) }}"></iframe>
        @endforeach
    </div>

    <script>
        (function () {
            const total = {{ count($documents) }};
            const scope = @json($scope);
            const sessionId = @json($sessionId);
            const batchCompleteUrl = @json($batchCompleteUrl ?? null);
            const autoPrint = @json($autoprint ?? true);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            const statusEl = document.getElementById('batch-status');
            const printBtn = document.getElementById('print-btn');

            let loaded = 0;
            let printed = false;
            let completionAttempted = false;

            function updateStatus(text) { if (statusEl) statusEl.textContent = text; }

            function fitIframe(frame) {
                try {
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    if (!doc || !doc.body) return;
                    // Cap at ~A4 height (1122px ≈ 297mm at 96dpi) so a single
                    // iframe never claims more than one printed page.
                    const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
                    if (h > 0) frame.style.height = Math.min(h, 1118) + 'px';
                } catch (e) { /* cross-origin or not ready */ }
            }

            function notifyOpener(type) {
                try {
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage({
                            type: type,
                            scope: scope,
                            session_id: sessionId,
                        }, window.location.origin);
                    }
                } catch (e) { /* ignore */ }
            }

            async function finalizeSession() {
                if (completionAttempted) return;
                completionAttempted = true;

                if (scope === 'cor' && batchCompleteUrl && sessionId) {
                    try {
                        await fetch(batchCompleteUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ session_id: sessionId }),
                        });
                    } catch (e) {
                        console.warn('Batch session finalize failed:', e);
                    }
                }

                notifyOpener('klaes-batch-print-session-completed');
            }

            document.querySelectorAll('iframe.page-frame').forEach(function (frame) {
                frame.addEventListener('load', function () {
                    loaded++;
                    fitIframe(frame);
                    updateStatus('Loaded ' + loaded + ' / ' + total + ' document' + (total === 1 ? '' : 's'));

                    if (loaded === total) {
                        updateStatus('Ready. ' + total + ' document' + (total === 1 ? '' : 's') + ' queued for printing.');
                        printBtn.disabled = false;
                        notifyOpener('klaes-batch-print-finished');

                        if (autoPrint && !printed) {
                            printed = true;
                            // Allow a short tick for layout to settle
                            setTimeout(function () { window.print(); }, 700);
                        }
                    }
                });
            });

            window.addEventListener('afterprint', function () {
                finalizeSession();
            });
        })();
    </script>
</body>
</html>
