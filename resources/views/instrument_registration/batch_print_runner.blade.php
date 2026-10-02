<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $runnerTitle ?? 'Batch Print Runner' }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 980px;
            margin: 32px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
            padding: 24px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .muted {
            color: #6b7280;
            font-size: 14px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 6px 12px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 13px;
            font-weight: 700;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .stat {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            background: #fafafa;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
        }

        .status-box {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            margin-top: 18px;
        }

        .status-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .status-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .status-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .button {
            appearance: none;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .button-primary {
            background: #2563eb;
            color: #fff;
        }

        .button-secondary {
            background: #fff;
            border: 1px solid #d1d5db;
            color: #111827;
        }

        .document-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }

        .document-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            background: #fff;
        }

        .document-type {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            background: #ecfeff;
            color: #0f766e;
        }

        .document-link {
            color: #2563eb;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .document-link:hover {
            text-decoration: underline;
        }

        .hidden-frame {
            position: fixed;
            width: 1px;
            height: 1px;
            left: -9999px;
            top: -9999px;
            border: 0;
        }
    </style>
</head>

<body>
    @php
        $runnerTitle = $runnerTitle ?? 'Batch Print Runner';
        $runnerDescription = $runnerDescription ?? 'This page opens the official print templates for the selected registration batch one after another.';
        $queueCompletedMessage = $queueCompletedMessage ?? 'Batch print queue completed. If any browser print dialog was blocked, use the manual document links below.';
        $completionUrl = $completionUrl ?? null;
        $completionSessionId = $completionSessionId ?? null;
        $completionButtonLabel = $completionButtonLabel ?? 'Mark Session Complete';
        $completionPendingMessage = $completionPendingMessage ?? 'Finalizing the batch session...';
        $completionSuccessMessage = $completionSuccessMessage ?? $queueCompletedMessage;
        $completionFailureMessage = $completionFailureMessage ?? 'The documents opened, but the batch session could not be finalized automatically. Use the completion button after printing manually.';
    @endphp
    <div class="container">
        <div class="card">
            <div class="pill">Batch Session {{ $sessionId }}</div>
            <h1 class="title">{{ $runnerTitle }}</h1>
            <p class="muted">{{ $runnerDescription }}</p>

            <div class="stats">
                <div class="stat">
                    <div class="stat-label">Registrations</div>
                    <div class="stat-value">{{ count($registrations) }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Documents Queued</div>
                    <div class="stat-value">{{ count($documents) }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Session Status</div>
                    <div class="stat-value">{{ $errorMessage ? 'Failed' : 'Queued' }}</div>
                </div>
            </div>

            @if($errorMessage)
                <div class="status-box status-error">{{ $errorMessage }}</div>
            @else
                <div id="batchPrintStatus" class="status-box status-info">
                    Preparing print queue...
                </div>
            @endif

            <div class="actions">
                @if(!$errorMessage && count($documents) > 0)
                    <button type="button" class="button button-primary" onclick="restartBatchPrint()">Restart Print Queue</button>
                @endif
                @if(!$errorMessage && $completionUrl)
                    <button type="button" id="batchCompleteButton" class="button button-secondary" onclick="completeBatchPrint()" disabled>{{ $completionButtonLabel }}</button>
                @endif
                <button type="button" class="button button-secondary" onclick="window.close()">Close Tab</button>
            </div>
        </div>

        <div class="card">
            <h2 class="title" style="font-size: 22px;">Queued Documents</h2>
            <p class="muted">If the automatic print flow is blocked by the browser, use these links manually.</p>

            <ul class="document-list">
                @forelse($documents as $document)
                    <li class="document-item">
                        <div>
                            <span class="document-type">{{ $document['type'] }}</span>
                            <div style="margin-top: 8px; font-weight: 700;">{{ $document['title'] }}</div>
                        </div>
                        <a class="document-link" href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer">Open Document</a>
                    </li>
                @empty
                    <li class="document-item">
                        <div>No documents are available for this session.</div>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    <iframe id="batchPrintFrame" class="hidden-frame" title="Batch Print Frame"></iframe>

    <script>
        const batchDocuments = @json($documents);
        const hasBatchPrintError = @json((bool) $errorMessage);
        const batchQueueCompletedMessage = @json($queueCompletedMessage);
        const batchCompletionUrl = @json($completionUrl);
        const batchCompletionSessionId = @json($completionSessionId);
        const batchCompletionPendingMessage = @json($completionPendingMessage);
        const batchCompletionSuccessMessage = @json($completionSuccessMessage);
        const batchCompletionFailureMessage = @json($completionFailureMessage);
        const batchCompletionCsrfToken = @json(csrf_token());
        let batchPrintIndex = -1;
        let batchPrintFallbackTimer = null;
        let batchCompletionInFlight = false;
        let batchCompletionDone = false;

        function setBatchPrintStatus(message, variant) {
            const box = document.getElementById('batchPrintStatus');
            if (!box) {
                return;
            }

            box.textContent = message;
            box.className = 'status-box ' + (variant === 'success'
                ? 'status-success'
                : variant === 'error'
                    ? 'status-error'
                    : 'status-info');
        }

        function setCompletionButtonState(disabled) {
            const button = document.getElementById('batchCompleteButton');
            if (!button) {
                return;
            }

            button.disabled = disabled;
            button.style.opacity = disabled ? '0.65' : '1';
            button.style.cursor = disabled ? 'not-allowed' : 'pointer';
        }

        async function completeBatchPrint() {
            if (!batchCompletionUrl || !batchCompletionSessionId) {
                setBatchPrintStatus(batchQueueCompletedMessage, 'success');
                return;
            }

            if (batchCompletionInFlight) {
                return;
            }

            if (batchCompletionDone) {
                setBatchPrintStatus(batchCompletionSuccessMessage, 'success');
                return;
            }

            batchCompletionInFlight = true;
            setCompletionButtonState(true);
            setBatchPrintStatus(batchCompletionPendingMessage, 'info');

            try {
                const response = await fetch(batchCompletionUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': batchCompletionCsrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        session_id: batchCompletionSessionId,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || batchCompletionFailureMessage);
                }

                batchCompletionDone = true;
                setBatchPrintStatus(payload.message || batchCompletionSuccessMessage, 'success');
            } catch (error) {
                setCompletionButtonState(false);
                setBatchPrintStatus(error.message || batchCompletionFailureMessage, 'error');
            } finally {
                batchCompletionInFlight = false;
            }
        }

        function queueNextDocument() {
            batchPrintIndex += 1;

            if (batchPrintIndex >= batchDocuments.length) {
                setCompletionButtonState(false);
                completeBatchPrint();
                return;
            }

            const current = batchDocuments[batchPrintIndex];
            const frame = document.getElementById('batchPrintFrame');
            setBatchPrintStatus(`Printing ${batchPrintIndex + 1} of ${batchDocuments.length}: ${current.title}`, 'info');

            clearTimeout(batchPrintFallbackTimer);
            batchPrintFallbackTimer = setTimeout(() => {
                queueNextDocument();
            }, 5000);

            frame.src = current.url;
        }

        function restartBatchPrint() {
            if (!Array.isArray(batchDocuments) || batchDocuments.length === 0) {
                setBatchPrintStatus('No documents are available in this batch.', 'error');
                return;
            }

            batchPrintIndex = -1;
            queueNextDocument();
        }

        window.restartBatchPrint = restartBatchPrint;
        window.completeBatchPrint = completeBatchPrint;

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin) {
                return;
            }

            if (!event.data || event.data.type !== 'klaes-batch-print-finished') {
                return;
            }

            clearTimeout(batchPrintFallbackTimer);
            setTimeout(() => {
                queueNextDocument();
            }, 500);
        });

        window.addEventListener('load', function () {
            if (batchCompletionUrl) {
                setCompletionButtonState(true);
            }

            if (!hasBatchPrintError && batchDocuments.length > 0) {
                setTimeout(() => {
                    restartBatchPrint();
                }, 700);
            }
        });
    </script>
</body>

</html>