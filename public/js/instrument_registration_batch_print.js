(function () {
  let cachedBatchPrintSessions = [];
  let activeBatchAction = null;
  let refreshBatchSessionsOnFocus = false;

  function modal() {
    return document.getElementById('batchPrintSessionModal');
  }

  function sessionSelect() {
    return document.getElementById('batchPrintSessionSelect');
  }

  function summaryBox() {
    return document.getElementById('batchPrintSessionSummary');
  }

  function summaryText() {
    return document.getElementById('batchPrintSessionSummaryText');
  }

  function emptyState() {
    return document.getElementById('batchPrintSessionEmptyState');
  }

  function summaryStat(id) {
    return document.getElementById(id);
  }

  function actionButtons() {
    return {
      generateRds: document.getElementById('generateBatchRdsBtn'),
      downloadRds: document.getElementById('downloadBatchRdsBtn'),
      generateCor: document.getElementById('generateBatchCorBtn'),
      downloadCor: document.getElementById('downloadBatchCorBtn')
    };
  }

  function selectedBatchSession() {
    const select = sessionSelect();
    return cachedBatchPrintSessions.find((item) => item.session_id === select?.value) || null;
  }

  function setButtonsDisabled(disabled) {
    Object.values(actionButtons()).forEach((button) => {
      if (button) {
        button.disabled = disabled;
      }
    });
  }

  function syncActionButtons(selected) {
    const buttons = actionButtons();

    if (!selected) {
      setButtonsDisabled(true);
      [buttons.downloadRds, buttons.downloadCor, buttons.generateCor].forEach((b) => {
        if (b) b.title = 'Select a batch registration session first.';
      });
      return;
    }

    if (buttons.generateRds) {
      buttons.generateRds.disabled = activeBatchAction !== null || !selected.can_generate_rds;
      buttons.generateRds.title = selected.can_generate_rds
        ? 'Generate RDS tracking rows for every instrument in this session.'
        : 'Session has no records to generate RDS for.';
    }
    if (buttons.downloadRds) {
      buttons.downloadRds.disabled = activeBatchAction !== null || !selected.can_download_rds_pdf;
      buttons.downloadRds.title = selected.can_download_rds_pdf
        ? 'Open the batch RDS print page.'
        : 'Click "Generate RDS" first — every record in the session must have RDS generated before printing.';
    }
    if (buttons.generateCor) {
      buttons.generateCor.disabled = activeBatchAction !== null || !selected.can_generate_cor;
      buttons.generateCor.title = selected.can_generate_cor
        ? 'Generate CoR records for every instrument in this session.'
        : 'Generate and print the RDS set before generating CoR.';
    }
    if (buttons.downloadCor) {
      buttons.downloadCor.disabled = activeBatchAction !== null || !selected.can_download_cor_pdf;
      buttons.downloadCor.title = selected.can_download_cor_pdf
        ? 'Open the batch CoR print page.'
        : 'Click "Generate CoR" first — every record must have CoR generated before printing.';
    }
  }

  function inferNextStep(selected) {
    if (!selected) {
      return 'Select a session';
    }
    if (!selected.all_rds_generated) {
      return 'Generate the session RDS set';
    }
    if (!selected.all_rds_printed) {
      return 'Open batch RDS print page';
    }
    if (!selected.all_cor_generated) {
      return 'Generate the session CoR set';
    }
    return 'Open batch CoR print page';
  }

  function closePrintTabs(tabs) {
    tabs.forEach((tab) => {
      try {
        if (tab && !tab.closed) {
          tab.close();
        }
      } catch (error) {
        console.warn('Unable to close pre-opened print tab:', error);
      }
    });
  }

  function preOpenPrintTabs(count, label) {
    const tabs = [];

    for (let index = 0; index < count; index += 1) {
      const tab = window.open('', '_blank');
      if (!tab) {
        break;
      }

      try {
        tab.document.write(`<!doctype html><html><head><title>Preparing ${label}</title><style>body{font-family:Arial,sans-serif;padding:32px;color:#1f2937} .muted{color:#6b7280}</style></head><body><h2>Preparing ${label} ${index + 1} of ${count}</h2><p class="muted">The official print template is loading.</p></body></html>`);
        tab.document.close();
      } catch (error) {
        console.warn('Unable to write placeholder content to pre-opened tab:', error);
      }

      tabs.push(tab);
    }

    return tabs;
  }

  function assignDocumentsToTabs(tabs, documents) {
    documents.forEach((document, index) => {
      const tab = tabs[index];
      if (!tab || tab.closed) {
        return;
      }

      tab.location = document.url;
    });

    if (tabs.length > documents.length) {
      closePrintTabs(tabs.slice(documents.length));
    }
  }

  function updateBatchPrintSummary() {
    const selected = selectedBatchSession();

    if (!selected) {
      if (summaryBox()) summaryBox().classList.add('hidden');
      if (summaryStat('batchPrintRdsGeneratedStat')) summaryStat('batchPrintRdsGeneratedStat').textContent = '0 / 0';
      if (summaryStat('batchPrintRdsPrintedStat')) summaryStat('batchPrintRdsPrintedStat').textContent = '0 / 0';
      if (summaryStat('batchPrintCorGeneratedStat')) summaryStat('batchPrintCorGeneratedStat').textContent = '0 / 0';
      if (summaryStat('batchPrintNextStepStat')) summaryStat('batchPrintNextStepStat').textContent = 'Select a session';
      syncActionButtons(null);
      return;
    }

    if (summaryBox()) summaryBox().classList.remove('hidden');
    if (summaryText()) {
      summaryText().textContent = `${selected.record_count} instrument${selected.record_count === 1 ? '' : 's'} registered on ${selected.created_at_label}.`;
    }
    if (summaryStat('batchPrintRdsGeneratedStat')) {
      summaryStat('batchPrintRdsGeneratedStat').textContent = `${selected.rds_generated_count} / ${selected.record_count}`;
    }
    if (summaryStat('batchPrintRdsPrintedStat')) {
      summaryStat('batchPrintRdsPrintedStat').textContent = `${selected.rds_printed_count} / ${selected.record_count}`;
    }
    if (summaryStat('batchPrintCorGeneratedStat')) {
      summaryStat('batchPrintCorGeneratedStat').textContent = `${selected.cor_generated_count} / ${selected.record_count}`;
    }
    if (summaryStat('batchPrintNextStepStat')) {
      summaryStat('batchPrintNextStepStat').textContent = inferNextStep(selected);
    }

    syncActionButtons(selected);
  }

  async function loadBatchPrintSessions(preferredSessionId = '') {
    const select = sessionSelect();
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">Loading sessions...</option>';
    select.disabled = true;
    setButtonsDisabled(true);
    if (emptyState()) emptyState().classList.add('hidden');
    if (summaryBox()) summaryBox().classList.add('hidden');

    try {
      const response = await fetch(window.KlaesConfig.urls.batchPrintSessions, {
        headers: {
          Accept: 'application/json'
        },
        credentials: 'same-origin'
      });
      const payload = await response.json();

      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Failed to load batch print sessions.');
      }

      cachedBatchPrintSessions = Array.isArray(payload.data) ? payload.data : [];
      select.innerHTML = '<option value="">Select a batch session</option>';

      if (cachedBatchPrintSessions.length === 0) {
        select.disabled = true;
        if (emptyState()) emptyState().classList.remove('hidden');
        updateBatchPrintSummary();
        return;
      }

      cachedBatchPrintSessions.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.session_id;
        option.textContent = item.label;
        select.appendChild(option);
      });

      if (preferredSessionId && cachedBatchPrintSessions.some((item) => item.session_id === preferredSessionId)) {
        select.value = preferredSessionId;
      }

      select.disabled = false;
      updateBatchPrintSummary();
    } catch (error) {
      console.error('Failed to load batch print sessions:', error);
      select.innerHTML = '<option value="">Unable to load sessions</option>';
      if (emptyState()) {
        emptyState().textContent = error.message || 'Unable to load batch print sessions.';
        emptyState().classList.remove('hidden');
      }
      syncActionButtons(null);
    }
  }

  async function postBatchAction(url, sessionId) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.KlaesConfig.csrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify({ session_id: sessionId })
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Unable to complete the selected batch action.');
    }

    return payload;
  }

  window.openBatchPrintSessionModal = async function () {
    const element = modal();
    if (!element) {
      return;
    }

    element.style.display = 'block';
    await loadBatchPrintSessions();
  };

  window.closeBatchPrintSessionModal = function () {
    const element = modal();
    if (!element) {
      return;
    }

    element.style.display = 'none';
  };

  window.runBatchPrintAction = async function (action) {
    const selected = selectedBatchSession();
    const sessionId = selected?.session_id ? String(selected.session_id).trim() : '';
    let preopenedTabs = [];

    if (!sessionId) {
      Swal.fire({
        icon: 'warning',
        title: 'Session Required',
        text: 'Select a batch registration session before running a batch action.'
      });
      return;
    }

    if (action === 'download_rds' && !selected.can_download_rds_pdf) {
      Swal.fire({
        icon: 'warning',
        title: 'Generate RDS First',
        text: 'You must click "Generate RDS" and ensure every record in this session has an RDS before printing.'
      });
      return;
    }

    if (action === 'generate_cor' && !selected.can_generate_cor) {
      Swal.fire({
        icon: 'warning',
        title: 'Print RDS First',
        text: 'Generate and print the RDS set for this session before generating CoR.'
      });
      return;
    }

    if (action === 'download_cor' && !selected.can_download_cor_pdf) {
      Swal.fire({
        icon: 'warning',
        title: 'Generate CoR First',
        text: 'You must click "Generate CoR" and ensure every record in this session has a CoR before printing.'
      });
      return;
    }

    try {
      activeBatchAction = action;
      updateBatchPrintSummary();

      if (action === 'generate_rds') {
        const payload = await postBatchAction(window.KlaesConfig.urls.batchGenerateRds, sessionId);
        await loadBatchPrintSessions(sessionId);
        Swal.fire({ icon: 'success', title: 'RDS Generated', text: payload.message, timer: 1800, showConfirmButton: false });
        return;
      }

      if (action === 'generate_cor') {
        const payload = await postBatchAction(window.KlaesConfig.urls.batchGenerateCor, sessionId);
        await loadBatchPrintSessions(sessionId);
        Swal.fire({ icon: 'success', title: 'CoR Generated', text: payload.message, timer: 1800, showConfirmButton: false });
        return;
      }

      if (action === 'download_rds') {
        const tabs = preOpenPrintTabs(1, 'RDS Batch');
        preopenedTabs = tabs;
        if (tabs.length === 0) {
          throw new Error('Your browser blocked the print tab. Allow popups for this site and try again.');
        }

        const payload = await postBatchAction(window.KlaesConfig.urls.batchDownloadRdsPdf, sessionId);
        const documents = Array.isArray(payload.documents) ? payload.documents : [];

        if (documents.length === 0) {
          closePrintTabs(tabs);
          throw new Error('No RDS batch print URL was returned for this session.');
        }

        assignDocumentsToTabs(tabs, documents);
        preopenedTabs = [];
        refreshBatchSessionsOnFocus = true;
        window.setTimeout(() => loadBatchPrintSessions(sessionId), 3000);

        Swal.fire({
          icon: 'success',
          title: 'RDS Batch Print Opened',
          text: 'A single tab now lists every RDS in this session. Use the browser print dialog to print or save them all at once.',
          timer: 2800,
          showConfirmButton: false
        });
        return;
      }

      if (action === 'download_cor') {
        const tabs = preOpenPrintTabs(1, 'CoR Batch');
        preopenedTabs = tabs;
        if (tabs.length === 0) {
          throw new Error('Your browser blocked the print tab. Allow popups for this site and try again.');
        }

        const payload = await postBatchAction(window.KlaesConfig.urls.batchDownloadCorPdf, sessionId);
        const documents = Array.isArray(payload.documents) ? payload.documents : [];

        if (documents.length === 0) {
          closePrintTabs(tabs);
          throw new Error('No CoR batch print URL was returned for this session.');
        }

        assignDocumentsToTabs(tabs, documents);
        preopenedTabs = [];
        refreshBatchSessionsOnFocus = true;
        window.setTimeout(() => loadBatchPrintSessions(sessionId), 5000);

        Swal.fire({
          icon: 'success',
          title: 'CoR Batch Print Opened',
          text: 'A single tab now lists every CoR in this session. Printing from that tab finalises the session automatically.',
          timer: 3000,
          showConfirmButton: false
        });
        return;
      }
    } catch (error) {
      if (preopenedTabs.length > 0) {
        closePrintTabs(preopenedTabs);
      }

      console.error('Batch print action failed:', error);
      Swal.fire({
        icon: 'error',
        title: 'Batch Action Failed',
        text: error.message || 'Unable to complete the selected batch action.'
      });
    } finally {
      activeBatchAction = null;
      updateBatchPrintSummary();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    const select = sessionSelect();
    if (select) {
      select.addEventListener('change', updateBatchPrintSummary);
    }

    window.addEventListener('focus', function () {
      if (!refreshBatchSessionsOnFocus) {
        return;
      }

      const element = modal();
      if (!element || element.style.display !== 'block') {
        return;
      }

      refreshBatchSessionsOnFocus = false;
      const preferredSessionId = sessionSelect()?.value || '';
      loadBatchPrintSessions(preferredSessionId);
    });

    window.addEventListener('message', function (event) {
      if (event.origin !== window.location.origin || !event.data || !event.data.type) {
        return;
      }

      if (event.data.type !== 'klaes-batch-print-finished' && event.data.type !== 'klaes-batch-print-session-completed') {
        return;
      }

      const element = modal();
      if (!element || element.style.display !== 'block') {
        refreshBatchSessionsOnFocus = true;
        return;
      }

      const preferredSessionId = sessionSelect()?.value || '';
      loadBatchPrintSessions(preferredSessionId);
    });
  });
})();