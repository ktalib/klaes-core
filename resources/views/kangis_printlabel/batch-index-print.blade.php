<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KANGIS Batch Index &mdash; QR Coded Files</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
      color-scheme: light;
      --grid: #7f7f7f;
      --head-bg: #ffff99;
      --ink: #000000;
    }

    body {
      margin: 0;
      padding: 18px 0;
      background: #f1f5f9;
      color: var(--ink);
      font-family: Calibri, 'Segoe UI', Arial, sans-serif;
      font-size: 11pt;
    }

    .sheet {
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto 18px;
      background: #fff;
      padding: 14mm 12mm 12mm;
      box-shadow: 0 12px 25px rgba(15, 23, 42, 0.08);
      display: flex;
      flex-direction: column;
    }

    /* ── Toolbar (screen only) ── */
    .toolbar {
      width: 210mm;
      margin: 0 auto 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      font-size: 10pt;
      color: #475569;
    }

    .toolbar button {
      border: 1px solid #1d4ed8;
      background: #2563eb;
      color: #fff;
      border-radius: 6px;
      padding: 8px 16px;
      font-size: 10pt;
      font-weight: 600;
      cursor: pointer;
    }

    /* ── Document header ── */
    .doc-head {
      display: flex;
      align-items: center;
      gap: 8mm;
      margin-bottom: 8mm;
    }

    .doc-head .head-logo {
      width: 24mm;
      flex: 0 0 24mm;
      display: flex;
      align-items: center;
    }

    .doc-head .head-logo img {
      max-width: 100%;
      max-height: 22mm;
      object-fit: contain;
    }

    .doc-head .head-logo--right { justify-content: flex-end; }

    .doc-head .head-text {
      flex: 1 1 auto;
      text-align: center;
    }

    .doc-head .org {
      font-size: 12pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .doc-head .dept {
      font-size: 10pt;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 2px;
    }

    .doc-head .title {
      margin-top: 6mm;
      font-size: 13pt;
      font-weight: 700;
      text-transform: uppercase;
      text-decoration: underline;
      letter-spacing: 0.03em;
    }

    .meta {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 4px 24px;
      font-size: 9.5pt;
      margin: 5mm 0 3mm;
    }

    .meta span strong { font-weight: 700; }

    /* ── Index table (mirrors the registry spreadsheet) ── */
    table.index {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    table.index th,
    table.index td {
      border: 1px solid var(--grid);
      padding: 3px 5px;
      font-size: 10pt;
      text-align: center;
      vertical-align: middle;
      word-break: break-word;
    }

    table.index thead th {
      background: var(--head-bg);
      font-weight: 700;
      font-size: 9.5pt;
      line-height: 1.15;
    }

    table.index col.c-sn      { width: 8%; }
    table.index col.c-batch   { width: 15%; }
    table.index col.c-prefix  { width: 15%; }
    table.index col.c-serial  { width: 20%; }
    table.index col.c-rack    { width: 10%; }
    table.index col.c-shelf   { width: 10%; }
    table.index col.c-full    { width: 22%; }

    table.index td.strong { font-weight: 700; }

    /* File numbers belonging to the batch, gridded under its index row. */
    table.index td.files {
      text-align: left;
      padding: 0;
      background: #fbfbfb;
    }

    table.index td.files .files-label {
      display: block;
      padding: 2px 5px;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 7.5pt;
      letter-spacing: 0.04em;
      border-bottom: 1px solid var(--grid);
    }

    table.files-grid {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    table.files-grid th,
    table.files-grid td {
      border: 1px solid var(--grid);
      border-top: none;
      border-left: none;
      padding: 2px 5px;
      font-size: 8.5pt;
      text-align: center;
      word-break: break-word;
    }

    table.files-grid thead th {
      background: #f1f1e0;
      font-weight: 700;
      font-size: 7.5pt;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    /* Names read better ranged left; the codes stay centred. */
    table.files-grid th:nth-child(3),
    table.files-grid td:nth-child(3) {
      text-align: left;
      padding-left: 8px;
    }

    table.files-grid col.f-sn    { width: 8%; }
    table.files-grid col.f-no    { width: 24%; }
    table.files-grid col.f-title { width: 42%; }
    table.files-grid col.f-shelf { width: 26%; }

    table.files-grid tr th:last-child,
    table.files-grid tr td:last-child { border-right: none; }
    table.files-grid tbody tr:last-child td { border-bottom: none; }

    .empty {
      border: 1px dashed #94a3b8;
      padding: 24px;
      text-align: center;
      color: #475569;
      font-size: 10pt;
      margin-top: 6mm;
    }

    /* ── Signatures ── */
    .signatures {
      margin-top: 14mm;
      display: flex;
      gap: 18mm;
      page-break-inside: avoid;
      break-inside: avoid;
    }

    .sign-block { flex: 1 1 0; font-size: 10pt; }

    .sign-block .role {
      font-weight: 700;
      text-transform: uppercase;
      font-size: 9.5pt;
      letter-spacing: 0.04em;
      margin-bottom: 12mm;
    }

    .sign-line {
      border-bottom: 1px solid var(--ink);
      height: 0;
      margin-bottom: 2px;
    }

    .sign-caption {
      font-size: 8.5pt;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 9mm;
    }

    .sign-caption:last-child { margin-bottom: 0; }

    .foot-note {
      margin-top: auto;
      padding-top: 4mm;
      border-top: 1px solid #e2e8f0;
      font-size: 8pt;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 6mm;
      page-break-inside: avoid;
      break-inside: avoid;
    }

    .foot-note .foot-logo {
      width: 22mm;
      flex: 0 0 22mm;
      display: flex;
      align-items: center;
    }

    .foot-note .foot-logo img {
      max-width: 100%;
      max-height: 14mm;
      object-fit: contain;
    }

    .foot-note .foot-logo--right { justify-content: flex-end; }

    .foot-note .foot-text {
      flex: 1 1 auto;
      display: flex;
      justify-content: space-between;
      gap: 6mm;
    }

    /* Bottom margin reserves the strip the fixed print footer sits in. */
    @page { size: A4 portrait; margin: 12mm 10mm 24mm; }

    @media print {
      body { background: #fff; padding: 0; }
      .no-print { display: none !important; }
      .sheet {
        width: auto;
        min-height: 0;
        margin: 0;
        padding: 0;
        box-shadow: none;
      }

      /* Anchor the footer to the foot of every printed page rather than letting
         it float directly under the signatures. */
      .foot-note {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        margin-top: 0;
        padding-top: 3mm;
        border-top: 0.4pt solid #cbd5e1;
        background: #fff;
      }
      table.index thead { display: table-header-group; }
      table.index tr { page-break-inside: avoid; }
      /* A big batch's file grid may legitimately outgrow one page. */
      table.index tr.files-row { page-break-inside: auto; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  @php
      $f = $filters ?? [];
      $scopeParts = [];
      $scopeParts[] = 'Prefix: ' . (!empty($f['prefix']) ? $f['prefix'] : 'All');
      $scopeParts[] = 'Registry Batch No: ' . (!empty($f['registry_batch_no']) ? implode(', ', $f['registry_batch_no']) : 'All');
      $scopeParts[] = 'Rack: ' . (!empty($f['rack']) ? $f['rack'] : 'All');
      if (!empty($f['rack_secondary'])) {
          $scopeParts[] = 'Backup Rack: ' . $f['rack_secondary'];
      }
      $scopeParts[] = 'Shelf: ' . (($f['shelf'] ?? null) !== null ? $f['shelf'] : 'All');
      if (!empty($f['full_label'])) {
          $scopeParts[] = 'Full Label: ' . $f['full_label'];
      }
      // Status only earns a place on the sheet when the index was deliberately
      // narrowed to one status; the default "all generated" scope says nothing.
      if (!empty($f['status']) && $f['status'] !== 'any') {
          $scopeParts[] = 'Status: ' . ucfirst($f['status']);
      }

      $totalFiles = collect($rows)->sum('file_count');
  @endphp

  <div class="toolbar no-print">
    <span>{{ count($rows) }} batch{{ count($rows) === 1 ? '' : 'es' }} &middot; {{ number_format($totalFiles) }} QR-coded file{{ $totalFiles === 1 ? '' : 's' }}</span>
    <button type="button" onclick="window.print()">Print Batch Index</button>
  </div>

  <div class="sheet">
    <div class="doc-head">
      <div class="head-logo head-logo--left">
        <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry of Land &amp; Physical Planning" onerror="this.style.display='none'">
      </div>
      <div class="head-text">
        <div class="org">Kano State Ministry of Land &amp; Physical Planning</div>
        <div class="dept">Kano Geographic Information Service (KANGIS) &mdash; Records Registry</div>
        <div class="title">Batch Index &mdash; QR Coded Files</div>
      </div>
      <div class="head-logo head-logo--right">
        <img src="http://app.klaes.ng/assets/logo/kangis.jpg" alt="KANGIS" onerror="this.style.display='none'">
      </div>
    </div>

    <div class="meta">
      <span><strong>Scope:</strong> {{ implode(' | ', $scopeParts) }}</span>
      <span><strong>Generated:</strong> {{ now()->format('d/m/Y H:i') }}</span>
    </div>

    @if (count($rows) === 0)
      <div class="empty">No QR-coded label batches match the selected filters.</div>
    @else
      <table class="index">
        <colgroup>
          <col class="c-sn">
          <col class="c-batch">
          <col class="c-prefix">
          <col class="c-serial">
          <col class="c-rack">
          <col class="c-shelf">
          <col class="c-full">
        </colgroup>
        <thead>
          <tr>
            <th>SN</th>
            <th>Registry BatchNo</th>
            <th>File Prefix</th>
            <th>Serial Range</th>
            <th>Rack</th>
            <th>Shelf</th>
            <th>Shelf/Rack</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $row)
            <tr>
              <td>{{ $row['sn'] }}</td>
              <td>{{ $row['registry_batch_no'] ?? '—' }}</td>
              <td>{{ $row['file_prefix'] ?? '—' }}</td>
              <td>{{ $row['serial_range'] }}</td>
              <td>{{ $row['rack'] !== '' ? $row['rack'] : '—' }}</td>
              <td>{{ $row['shelf'] !== '' ? $row['shelf'] : '—' }}</td>
              <td class="strong">{{ $row['shelf_rack'] !== '' ? $row['shelf_rack'] : '—' }}</td>
            </tr>
            @if (!empty($row['files']))
              <tr class="files-row">
                <td class="files" colspan="7">
                  <span class="files-label">Files in Batch ({{ count($row['files']) }})</span>
                  <table class="files-grid">
                    <colgroup>
                      <col class="f-sn">
                      <col class="f-no">
                      <col class="f-title">
                      <col class="f-shelf">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>SN</th>
                        <th>FileNo</th>
                        <th>File Title</th>
                        <th>Shelf/Rack Location</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($row['files'] as $fileIndex => $file)
                        <tr>
                          <td>{{ $fileIndex + 1 }}</td>
                          <td>{{ $file['file_number'] }}</td>
                          <td>{{ $file['file_title'] !== '' ? $file['file_title'] : '—' }}</td>
                          <td>{{ $file['shelf_location'] !== '' ? $file['shelf_location'] : ($row['shelf_rack'] !== '' ? $row['shelf_rack'] : '—') }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    @endif

    <div class="signatures">
      <div class="sign-block">
        <div class="role">Prepared By</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Name</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Signature</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Date</div>
      </div>
      <div class="sign-block">
        <div class="role">Verified / Approved By</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Name</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Signature</div>
        <div class="sign-line"></div>
        <div class="sign-caption">Date</div>
      </div>
    </div>

    <div class="foot-note">
      <div class="foot-logo foot-logo--left">
        <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="" onerror="this.style.display='none'">
      </div>
      <div class="foot-text">
        <span>KANGIS Batch Index &mdash; generated from printed QR label batches.</span>
        <span>{{ count($rows) }} batches &middot; {{ number_format($totalFiles) }} files</span>
      </div>
      <div class="foot-logo foot-logo--right">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="" onerror="this.style.display='none'">
      </div>
    </div>
  </div>

  @if (!empty($autoPrint))
    <script>
      window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
      });
    </script>
  @endif
</body>
</html>
