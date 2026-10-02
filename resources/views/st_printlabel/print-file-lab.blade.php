<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ST Print File Labels</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <style>
    :root {
      --sheet-margin-x: 0.85mm;
      --sheet-margin-y: 1.4mm;
      --label-qr-size: 48px;
      --shelf-label-scale: 2;
    }

    *, *::before, *::after {
      box-sizing: border-box;
    }

    :root {
      color-scheme: light;
    }

    body {
      margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f1f5f9;
      color: #0f172a;
      padding: 20px 0;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .no-print {
      display: block;
    }

    .label-document {
      width: 100%;
      max-width: 210mm;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .document-header {
      background: #ffffff;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 12px 25px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .document-header h1 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #1e3a8a;
    }

    .document-header p {
      margin: 4px 0 0;
      font-size: 0.85rem;
      color: #475569;
    }

    .page-container {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .page-preview {
      background: #ffffff;
      border-radius: 8px;
      padding: 12px;
      box-shadow: 0 12px 25px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.2);
      position: relative;
      width: 210mm;
      margin: 0 auto;
    }

    .page-preview::after {
      content: attr(data-page-label);
      position: absolute;
      top: 12px;
      right: 16px;
      font-size: 0.75rem;
      color: #2563eb;
      font-weight: 600;
      letter-spacing: 0.08em;
    }

    .label-page {
      display: grid;
      grid-template-columns: repeat(3, calc((210mm - (var(--sheet-margin-x) * 2)) / 3));
      grid-template-rows: repeat(10, calc((297mm - (var(--sheet-margin-y) * 2)) / 10));
      row-gap: 0;
      column-gap: 0;
      width: calc(210mm - (var(--sheet-margin-x) * 2));
      height: calc(297mm - (var(--sheet-margin-y) * 2));
      background: transparent;
      padding: 0;
      border-radius: 0;
      border: none;
      box-sizing: border-box;
      margin: var(--sheet-margin-y) auto;
    }

    .label-item {
      border: none;
      border-radius: 0;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 1.8mm 1.6mm 2mm;
      gap: 0.6mm;
      width: 100%;
      height: 100%;
      box-sizing: border-box;
    }

    .label-item--shelf-only {
      padding: 2mm 0;
      gap: 0;
      justify-content: center;
    }

    .label-item--shelf-only .shelf-label {
      width: calc(var(--label-qr-size) * var(--shelf-label-scale));
      height: calc(var(--label-qr-size) * var(--shelf-label-scale));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: calc(var(--label-qr-size) * 0.82);
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border: none;
      border-radius: 0;
      white-space: nowrap;
      word-break: normal;
      line-height: 1;
      padding: 0.4em;
      color: #0f172a;
    }

    .label-item canvas,
    .label-item img {
      width: var(--label-qr-size);
      height: var(--label-qr-size);
      object-fit: contain;
      margin-top: 0.4mm;
    }

    .label-item--placeholder {
      visibility: hidden;
    }

    .file-number-group {
      display: flex;
      flex-direction: column;
      gap: 0.25mm;
      align-items: center;
      justify-content: center;
      width: 100%;
    }

    .file-number {
      font-weight: 600;
      font-size: 8.4pt;
      letter-spacing: 0.04em;
      margin-bottom: 0;
      line-height: 1.08;
    }

    .file-number--secondary {
      font-weight: 500;
      font-size: 7.4pt;
      letter-spacing: 0.04em;
      color: #1f2933;
    }

    .shelf-label {
      font-size: 7.4pt;
      font-weight: 600;
      color: #1f2933;
      line-height: 1.05;
      word-break: break-word;
      max-width: 100%;
      margin-top: 0;
    }

    .empty-state {
      background: #ffffff;
      border-radius: 12px;
      padding: 48px;
      border: 1px dashed rgba(99, 102, 241, 0.35);
      text-align: center;
      color: #475569;
      font-size: 0.95rem;
    }

    .empty-state strong {
      display: block;
      margin-bottom: 8px;
      font-size: 1.1rem;
      color: #1d4ed8;
    }

    .footer-note {
      font-size: 0.75rem;
      color: #94a3b8;
      text-align: right;
    }

    @page {
      size: A4 portrait;
      margin: 0;
    }

    @media print {
      body {
        background: #ffffff;
        padding: 0;
        display: block;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .label-document {
        gap: 0;
        padding: 0;
        max-width: none;
      }

      .page-container {
        gap: 0;
      }

      .no-print {
        display: none !important;
      }

      .page-preview {
        box-shadow: none;
        border-radius: 0;
        border: none;
        padding: 0;
        width: 210mm;
        margin: 0;
      }

      .page-preview::after {
        display: none;
      }

      .label-page {
        border-radius: 0;
        border: none;
        background: #ffffff;
        width: calc(210mm - (var(--sheet-margin-x) * 2));
        height: calc(297mm - (var(--sheet-margin-y) * 2));
        margin: var(--sheet-margin-y) auto;
      }

      .page-preview {
        page-break-after: always;
      }

      .page-preview:last-of-type {
        page-break-after: auto;
      }
    }
  </style>
</head>
<body>
  <div class="label-document" id="labelDocument">
    <header class="document-header no-print" id="documentHeader">
      <h1>ST Print File Labels</h1>
      <p id="documentSummary">Waiting for label data…</p>
    </header>

    <div id="emptyState" class="empty-state">
      <strong>No label data supplied</strong>
      Open this page from the Print Labels module to automatically load the QR-coded labels.
    </div>

    <div id="pageContainer" class="page-container" aria-live="polite"></div>

    <p class="footer-note no-print" id="footerNote"></p>
  </div>

  <script>
    (function () {
      const LABELS_PER_PAGE = 25;
      const pageContainer = document.getElementById('pageContainer');
      const documentSummary = document.getElementById('documentSummary');
      const emptyState = document.getElementById('emptyState');
      const footerNote = document.getElementById('footerNote');
      let lastPayload = null;

      function readPayloadFromStorage() {
        const url = new URL(window.location.href);
        const key = url.searchParams.get('payloadKey');
        if (!key) {
          return null;
        }

        try {
          const stored = localStorage.getItem(key);
          if (!stored) {
            return null;
          }
          localStorage.removeItem(key);
          return JSON.parse(stored);
        } catch (error) {
          console.warn('Unable to recover payload from localStorage', error);
          return null;
        }
      }

      function chunk(items, size) {
        const chunks = [];
        for (let i = 0; i < items.length; i += size) {
          chunks.push(items.slice(i, i + size));
        }
        return chunks;
      }

      function getDisplayShelfValue(primary, secondary) {
        const candidates = [primary, secondary]
          .map((value) => (value ?? '').toString().trim())
          .filter((value) => {
            if (!value) {
              return false;
            }

            const upper = value.toUpperCase();
            return upper !== 'N/A' && upper !== 'SHELF/RACK-N/A' && upper !== 'NULL';
          });

        if (!candidates.length) {
          return 'N/A';
        }

        const cleanValue = candidates[0].replace(/^(Shelf\/Rack[-:\s]*)/i, '').trim();
        return cleanValue || 'N/A';
      }

      function ensureQrValue(label) {
        if (label.qr_value) {
          return String(label.qr_value);
        }

        const trackingCandidate = label.tracking_id || label.trackingId;
        if (trackingCandidate) {
          return String(trackingCandidate);
        }

        if (label.file_number) {
          return String(label.file_number);
        }

        return `IDX-${Math.random().toString(36).slice(2, 8)}`;
      }

      function drawQr(canvas, value) {
        if (typeof QRious === 'undefined') {
          throw new Error('QRious library is not available');
        }

        const size = 320;
        canvas.width = size;
        canvas.height = size;

        return new QRious({
          element: canvas,
          value,
          size,
          level: 'M',
          background: '#ffffff',
          foreground: '#000000',
        });
      }

      function createLabelNode(label, shelfMode = false) {
        const item = document.createElement('div');
        item.className = 'label-item';

        if (shelfMode) {
          item.classList.add('label-item--shelf-only');
          const shelfDisplayValue = getDisplayShelfValue(label.shelf_value, label.shelf_label);
          const fullLabel = label.shelf_only_value || label.full_label || shelfDisplayValue;
          const shelf = document.createElement('div');
          shelf.className = 'shelf-label';
          shelf.textContent = fullLabel || shelfDisplayValue;
          item.appendChild(shelf);
          return item;
        }

        if (label.qr_image) {
          const img = document.createElement('img');
          img.src = label.qr_image;
          img.alt = `QR code for ${label.file_number || 'label'}`;
          img.loading = 'lazy';
          item.appendChild(img);
        } else {
          const canvas = document.createElement('canvas');
          canvas.setAttribute('aria-hidden', 'true');

          try {
            drawQr(canvas, ensureQrValue(label));
          } catch (error) {
            console.error('Failed to render QR code', error);
            const ctx = canvas.getContext('2d');
            canvas.width = 120;
            canvas.height = 120;
            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#cbd5f5';
            ctx.strokeRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#94a3b8';
            ctx.textAlign = 'center';
            ctx.font = '12px Inter, sans-serif';
            ctx.fillText('QR', canvas.width / 2, canvas.height / 2);
          }

          item.appendChild(canvas);
        }

        const numberGroup = document.createElement('div');
        numberGroup.className = 'file-number-group';

        const primaryText = (label.primary_number || label.file_number || '—').toString();
        const primaryNumber = document.createElement('div');
        primaryNumber.className = 'file-number';
        primaryNumber.textContent = primaryText;
        numberGroup.appendChild(primaryNumber);

        const secondaryRaw = label.secondary_number || label.secondary_file_number;
        const secondaryText = secondaryRaw === undefined || secondaryRaw === null ? '' : String(secondaryRaw).trim();

        if (secondaryText && (label.is_st || secondaryText !== primaryText)) {
          const secondaryNumber = document.createElement('div');
          secondaryNumber.className = 'file-number file-number--secondary';
          secondaryNumber.textContent = secondaryText;
          numberGroup.appendChild(secondaryNumber);
        }

        item.appendChild(numberGroup);

        const shelfDisplayValue = getDisplayShelfValue(label.shelf_value, label.shelf_label);
        const shelf = document.createElement('div');
        shelf.className = 'shelf-label';
        shelf.textContent = `Shelf/Rack: ${shelfDisplayValue}`;
        item.appendChild(shelf);

        return item;
      }

      let autoPrintPromise = null;

      function waitForImages(container) {
        const images = Array.from(container.querySelectorAll('img'));
        if (!images.length) {
          return Promise.resolve();
        }

        return Promise.all(
          images.map((img) => {
            if (img.complete && img.naturalWidth > 0) {
              return Promise.resolve();
            }

            return new Promise((resolve) => {
              const done = () => resolve();
              img.addEventListener('load', done, { once: true });
              img.addEventListener('error', done, { once: true });
            });
          })
        );
      }

      async function scheduleAutoPrint() {
        if (autoPrintPromise) {
          return autoPrintPromise;
        }

        autoPrintPromise = (async () => {
          try {
            await waitForImages(pageContainer);
            if (document.fonts && document.fonts.ready) {
              try {
                await document.fonts.ready;
              } catch (error) {
                console.warn('Unable to await font readiness', error);
              }
            }
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
          } finally {
            window.print();
          }
        })();

        return autoPrintPromise;
      }

      function renderPages(payload) {
        pageContainer.innerHTML = '';

        if (!payload || !payload.labels || payload.labels.length === 0) {
          emptyState.style.display = 'block';
          documentSummary.textContent = 'No labels available.';
          footerNote.textContent = '';
          lastPayload = null;
          return;
        }

        emptyState.style.display = 'none';

        const { labels, summary, meta } = payload;
        const printableLabels = labels.slice();
        const pages = chunk(printableLabels, LABELS_PER_PAGE);
        const isShelfMode = Boolean(meta?.shelfLabelMode);

        pages.forEach((pageLabels, index) => {
          const wrapper = document.createElement('section');
          wrapper.className = 'page-preview';
          wrapper.setAttribute('data-page-label', `Page ${index + 1}`);

          const grid = document.createElement('div');
          grid.className = 'label-page';

          pageLabels.forEach((label) => {
            const node = createLabelNode(label, isShelfMode);
            grid.appendChild(node);
          });

          const placeholdersNeeded = LABELS_PER_PAGE - pageLabels.length;
          for (let i = 0; i < placeholdersNeeded; i++) {
            const placeholder = document.createElement('div');
            placeholder.className = 'label-item label-item--placeholder';
            grid.appendChild(placeholder);
          }

          wrapper.appendChild(grid);
          pageContainer.appendChild(wrapper);
        });

        const summaryParts = [];
        summaryParts.push(`${summary.totalLabels} label${summary.totalLabels === 1 ? '' : 's'}`);
        summaryParts.push(`${summary.copies} cop${summary.copies === 1 ? 'y' : 'ies'} each`);
        summaryParts.push(`${pages.length} page${pages.length === 1 ? '' : 's'}`);
        if (meta?.templateLabel) {
          summaryParts.push(`${meta.templateLabel} • ${meta.formatLabel}`);
        }
        if (isShelfMode && meta?.shelfLabelRange) {
          summaryParts.push(meta.shelfLabelRange);
        }

        documentSummary.textContent = summaryParts.join(' · ');
        footerNote.textContent = `Generated ${new Date(meta?.generatedAt || Date.now()).toLocaleString()}`;

        document.title = meta?.templateLabel
          ? `Print Labels · ${meta.templateLabel}`
          : 'ST Print File Labels';

        lastPayload = payload;

        if (payload.autoPrint) {
          scheduleAutoPrint();
        }
      }

      function handleIncomingPayload(payload) {
        if (!payload || typeof payload !== 'object') {
          console.warn('Invalid payload received for printing', payload);
          return;
        }
        renderPages(payload);
      }

      window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) {
          return;
        }

        if (!event.data || typeof event.data !== 'object') {
          return;
        }

        if (event.data.type === 'print-labels') {
          handleIncomingPayload(event.data.payload);
        }
      });

      window.addEventListener('afterprint', () => {
        if (!lastPayload) {
          return;
        }

        try {
          if (window.opener && window.opener !== window && window.location.origin === window.opener.location.origin) {
            window.opener.postMessage({
              type: 'print-labels:afterprint',
              payload: {
                batchId: lastPayload.meta?.batchId || null,
              },
            }, window.location.origin);
          }
        } catch (error) {
          console.warn('Unable to notify opener about print completion', error);
        }

        if (lastPayload.autoClose !== false) {
          setTimeout(() => {
            window.close();
          }, 600);
        }
      });

      const initialPayload = readPayloadFromStorage();
      if (initialPayload) {
        handleIncomingPayload(initialPayload);
      }
    })();
  </script>
</body>
</html>
