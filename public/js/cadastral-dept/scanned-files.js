/**
 * Cadastral Department: View Scanned Files (recording 355, 2026-11-09: "there will be two, just like what we did
 * on that indexing, they can view the land. Then when correspondence file has been opened too, they can view it").
 *
 * Copied from openScannedFile / chooseScannedFile in public/js/indexed-files/index.js (copy per module, never
 * shared). One pop-up always lists both: the Land File's scans (Blind Scan, Scan Upload, Records server, Digital
 * Archive) and the Correspondence File's (the Cadastral_* folders under the same number), saying plainly which
 * has not been scanned. Pages open in the read-only KlaesScanViewer (public/js/scanning/scan-viewer.js).
 *
 * Commissioning (user, 2026-11-10): a commissioned file also lists its Commissioning Sheet and, if any, its LGA
 * Confirmation Sheet, drawn read-only as the VFS Commissioning folder draws them (/cadastral-dept/commissioning-docs).
 */
(function () {
  if (window.cdScannedFiles) return;

  const SOURCE_ORDER = ['blind_scan', 'scan_upload', 'upload', 'records_server'];

  const esc = (value) => String(value === undefined || value === null ? '' : value)
    .replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const notify = (icon, title, text) => (window.Swal ? Swal.fire({ icon, title, text }) : alert(title + '\n\n' + text));

  function choose(fileNumber, land, archive, correspondence, commissioning, openSource) {
    const pages = (n) => `${n || 0} page${n === 1 ? '' : 's'}`;
    const row = (index, title, detail, colour) => `
      <button type="button" data-scan-choice="${index}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:10px 12px;margin-top:6px;text-align:left;background:#fff;border:1px solid #e2e8f0;border-left:4px solid ${colour};border-radius:8px;cursor:pointer;">
        <span style="min-width:0;">
          <span style="display:block;font-size:13px;font-weight:600;color:#1e293b;">${esc(title)}</span>
          <span style="display:block;font-size:11px;color:#64748b;word-break:break-all;">${esc(detail)}</span>
        </span>
        <span style="flex-shrink:0;font-size:12px;font-weight:600;color:#2563eb;">Open</span>
      </button>`;
    const heading = (text, colour) => `<p style="margin:14px 0 2px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:${colour};">${text}</p>`;
    const none = (text) => `<div style="margin-top:6px;padding:10px 12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;font-size:12px;color:#64748b;">${esc(text)}</div>`;

    const actions = [];
    const add = (action, ...rowArgs) => { actions.push(action); return row(actions.length - 1, ...rowArgs); };

    const landRows = land.map((s) => add(() => openSource(s), s.label, `${s.folder} · ${pages(s.pages)}`, '#059669'));
    if (archive) {
      landRows.push(add(() => window.open(archive.view_url, '_blank'), archive.label, `${archive.folder} · ${pages(archive.pages)}`, '#059669'));
    }
    const corrRows = correspondence.map((s) => add(() => openSource(s), s.label, `${s.folder} · ${pages(s.pages)}`, '#d97706'));
    const reopen = () => choose(fileNumber, land, archive, correspondence, commissioning, openSource);
    const commRows = commissioning.map((d) => (d.available && d.url
      ? add(() => openSheet(fileNumber, d, reopen), d.title, d.date ? 'Issued ' + String(d.date).slice(0, 10) : 'Issued by KLAES', '#4f46e5')
      : `<div style="margin-top:6px;padding:10px 12px;background:#f8fafc;border:1px dashed #cbd5e1;border-left:4px solid #a5b4fc;border-radius:8px;">
          <span style="display:block;font-size:13px;font-weight:600;color:#64748b;">${esc(d.title)}</span>
          <span style="display:block;font-size:11px;color:#94a3b8;">${esc(d.note || 'Not issued')}</span>
        </div>`));

    Swal.fire({
      title: 'Scanned files',
      width: 560,
      showConfirmButton: false,
      showCancelButton: true,
      cancelButtonText: 'Close',
      html: `<div style="text-align:left;">
          <p style="font-size:13px;color:#475569;"><strong>${esc(fileNumber)}</strong></p>
          ${heading('Land File', '#047857')}${landRows.length ? landRows.join('') : none('No Land File scan was found for this number.')}
          ${heading('Correspondence File', '#b45309')}${corrRows.length ? corrRows.join('') : none('The Correspondence File has not been scanned yet.')}
          ${commRows.length ? heading('Commissioning', '#4338ca') + commRows.join('') : ''}
        </div>`,
      didOpen: (popup) => {
        popup.querySelectorAll('[data-scan-choice]').forEach((button) => {
          button.addEventListener('click', () => {
            Swal.close();
            actions[Number(button.getAttribute('data-scan-choice'))]();
          });
        });
      },
    });
  }

  /** One commissioning sheet, read-only in a frame; Back returns to the list. */
  function openSheet(fileNumber, doc, back) {
    Swal.fire({
      title: doc.title,
      width: 'min(960px, 96vw)',
      showConfirmButton: true,
      confirmButtonText: 'Back',
      showCancelButton: true,
      cancelButtonText: 'Close',
      html: `<div style="text-align:left;">
          <p style="font-size:13px;color:#475569;margin-bottom:8px;"><strong>${esc(fileNumber)}</strong> · read-only</p>
          <iframe src="${esc(doc.url)}" title="${esc(doc.title)}" style="width:100%;height:72vh;border:1px solid #e2e8f0;border-radius:8px;background:#fff;"></iframe>
        </div>`,
    }).then((result) => { if (result.isConfirmed) back(); });
  }

  // Never blocks the scan list: a failure here just leaves the Commissioning section out.
  const loadCommissioning = (fileNumber) => fetch('/cadastral-dept/commissioning-docs?file_number=' + encodeURIComponent(fileNumber), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : null))
    .then((d) => (d && Array.isArray(d.documents) ? d.documents : []))
    .catch(() => []);

  window.cdScannedFiles = function (fileNumber) {
    fileNumber = String(fileNumber || '').trim();
    if (!fileNumber) {
      notify('info', 'No file number', 'This file has no number to look up.');
      return;
    }
    if (!window.KlaesScanViewer || !window.Swal) {
      notify('info', 'Viewer unavailable', 'The scanned-page viewer is not available on this page.');
      return;
    }

    Swal.fire({ title: 'Finding scanned files…', text: fileNumber, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const commissioningRequest = loadCommissioning(fileNumber);
    fetch('/scan-check?file_number=' + encodeURIComponent(fileNumber), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
      .then((r) => r.json().then((d) => ({ ok: r.ok, d })))
      .then(({ ok, d }) => commissioningRequest.then((commissioning) => ({ ok, d, commissioning })))
      .then(({ ok, d, commissioning }) => {
        if (!ok || !d.success) throw new Error(d.message || 'The scan check failed.');
        const sources = Array.isArray(d.sources) ? d.sources : [];
        const scanned = sources
          .filter((s) => s.token && SOURCE_ORDER.includes(s.source))
          .sort((a, b) => (SOURCE_ORDER.indexOf(a.source) - SOURCE_ORDER.indexOf(b.source)) || ((b.pages || 0) - (a.pages || 0)));
        const correspondence = scanned.filter((s) => s.is_correspondence);
        const land = scanned.filter((s) => !s.is_correspondence);
        const archive = sources.find((s) => s.source === 'digital_archive' && s.view_url) || null;
        Swal.close();

        const openSource = (s) => window.KlaesScanViewer.open({
          token: s.token,
          title: fileNumber + ' — ' + (s.is_correspondence ? 'Correspondence File' : (s.label || 'Land File')),
          subtitle: (s.folder || '') + ' · ' + (s.pages || 0) + ' page(s)',
          actions: [],
        });
        choose(fileNumber, land, archive, correspondence, commissioning, openSource);
      })
      .catch((err) => {
        Swal.close();
        notify('error', 'Could not open the scanned files', err.message || 'Please try again.');
      });
  };
})();
