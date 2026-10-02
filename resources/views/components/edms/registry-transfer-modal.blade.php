{{--
  Registry Transfer modal — shared by the Page Typing workspace and the
  File Archive (Doc-WARE) grid.

  Open it with:  EdmsRegistryTransfer.open(fileIndexingId, fileNumber, onDone)

  Everything it needs comes from /edms/registry-transfer/{options,preview,move},
  so both interfaces behave identically.
--}}
<div id="registry-transfer-modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

    <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-200">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <i data-lucide="folder-symlink" class="h-5 w-5 text-blue-600"></i>
          Move File to Another Registry
        </h3>
        <p class="text-sm text-gray-500 mt-1">
          Moves the scans, typed pages and Doc-WARE archive copies of
          <span class="font-semibold text-gray-700" id="registry-transfer-file-number">—</span>
          into a different registry folder.
        </p>
      </div>
      <button type="button" class="btn btn-ghost btn-icon" onclick="EdmsRegistryTransfer.close()" title="Close">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="p-5 space-y-4 overflow-y-auto flex-1">

      {{-- File picker. The file number is chosen in the shared Global File
           Number selector — the same MLPP / KANGIS / New KANGIS picker every
           other module uses — rather than typed here, so a number that reaches
           this card is always one that exists. Its file_indexings row is then
           resolved by an exact lookup, since the transfer needs the row id and
           the selector deals only in numbers. --}}
      <div>
        <label class="block text-sm font-semibold text-gray-900 mb-2">File number</label>

        <button type="button" id="registry-transfer-pick-file"
                class="input input-bordered w-full flex items-center gap-2 text-left text-gray-500 hover:border-blue-400 transition-colors"
                onclick="EdmsRegistryTransfer.pickFile()">
          <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
          <span id="registry-transfer-pick-file-label">Choose a file number…</span>
        </button>

        {{-- Resolving the chosen number to its indexing row, or saying why it
             could not be. --}}
        <p id="registry-transfer-lookup-status" class="hidden mt-2 text-xs"></p>

        {{-- The chosen file --}}
        <div id="registry-transfer-selected"
             class="hidden mt-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm">
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="font-semibold text-blue-900" id="registry-transfer-selected-number">—</div>
              <div class="text-xs text-blue-700 truncate" id="registry-transfer-selected-title"></div>
            </div>
            <div class="flex items-center gap-2 whitespace-nowrap">
              <div class="text-xs text-blue-700" id="registry-transfer-selected-counts"></div>
              <button type="button" id="registry-transfer-clear-file"
                      class="btn btn-ghost btn-icon" title="Pick a different file"
                      onclick="EdmsRegistryTransfer.clearFile()">
                <i data-lucide="x" class="h-4 w-4"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-2">Current registry</label>
          <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700"
               id="registry-transfer-current">—</div>
        </div>
        <div>
          <label for="registry-transfer-target" class="block text-sm font-semibold text-gray-900 mb-2">Move to</label>
          <select id="registry-transfer-target" class="input input-bordered w-full">
            <option value="">Select a registry…</option>
          </select>
        </div>
      </div>

      <div>
        <label for="registry-transfer-reason" class="block text-sm font-semibold text-gray-900 mb-2">
          Reason <span class="font-normal text-gray-500">(stored in the audit log)</span>
        </label>
        <input type="text" id="registry-transfer-reason" maxlength="500"
               class="input input-bordered w-full"
               placeholder="e.g. Indexed under the wrong registry at capture">
      </div>

      {{-- What will move --}}
      <div id="registry-transfer-preview" class="hidden border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wide">
          What will move
        </div>
        <div class="p-4 space-y-3">
          <div class="flex flex-wrap gap-4 text-sm">
            <span><strong id="registry-transfer-count-scans">0</strong> scan(s)</span>
            <span><strong id="registry-transfer-count-pages">0</strong> typed page(s)</span>
            <span id="registry-transfer-count-missing-wrap" class="hidden text-amber-700">
              <strong id="registry-transfer-count-missing">0</strong> not found on disk
            </span>
          </div>
          <div class="text-xs font-mono bg-gray-50 rounded p-2 overflow-x-auto whitespace-nowrap">
            <span id="registry-transfer-from-slug" class="text-gray-500"></span>
            <span class="mx-2">&rarr;</span>
            <span id="registry-transfer-to-slug" class="text-blue-700 font-semibold"></span>
          </div>
          <div class="max-h-52 overflow-y-auto overflow-x-auto border border-gray-100 rounded">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 sticky top-0">
                <tr class="text-left text-gray-500">
                  <th class="px-3 py-2 font-medium">Record</th>
                  <th class="px-3 py-2 font-medium">On disk</th>
                  <th class="px-3 py-2 font-medium">Destination</th>
                </tr>
              </thead>
              <tbody id="registry-transfer-rows" class="divide-y divide-gray-100"></tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Blocked --}}
      <div id="registry-transfer-blockers" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

      {{-- Warning: this rewrites paths --}}
      <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
        <i data-lucide="alert-triangle" class="h-4 w-4 flex-shrink-0 mt-0.5"></i>
        <span>
          The registry is part of every document's folder path. This moves the files on disk and
          re-points the indexing record, its scans and its typed pages together, in one transaction.
        </span>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-200 bg-gray-50 rounded-b-xl">
      <button type="button" class="btn btn-ghost" onclick="EdmsRegistryTransfer.close()">Cancel</button>
      <button type="button" id="registry-transfer-confirm" class="btn btn-primary" disabled
              onclick="EdmsRegistryTransfer.confirm()">
        <i data-lucide="folder-symlink" class="h-4 w-4 mr-1"></i>
        Move File
      </button>
    </div>

    <div id="registry-transfer-loading" class="hidden absolute inset-0 bg-white/70 items-center justify-center rounded-xl">
      <div class="flex flex-col items-center gap-2">
        <span class="loading loading-spinner loading-lg text-blue-600"></span>
        <span class="text-sm text-gray-600">Moving documents…</span>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  const el = (id) => document.getElementById(id);

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));

  const EdmsRegistryTransfer = {
    fileIndexingId: null,
    fileNumber: null,
    onDone: null,
    preview: null,

    /**
     * Open the dialog. Both arguments are optional — called with no file, it
     * opens as a standalone tool and the operator picks one with the Global File
     * Number selector.
     */
    async open(fileIndexingId, fileNumber, onDone) {
      this.onDone = typeof onDone === 'function' ? onDone : null;
      this.preview = null;

      el('registry-transfer-reason').value = '';
      el('registry-transfer-preview').classList.add('hidden');
      el('registry-transfer-blockers').classList.add('hidden');
      el('registry-transfer-confirm').disabled = true;
      this.setLookupStatus('', false);

      const modal = el('registry-transfer-modal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      if (fileIndexingId) {
        await this.selectFile({ id: fileIndexingId, file_number: fileNumber || '' });
      } else {
        this.clearFile();
      }

      if (window.lucide) lucide.createIcons();
    },

    /* ─────────────────── Choosing the file ───────────────────
       The file number comes from the shared Global File Number selector, the
       same MLPP / KANGIS / New KANGIS picker the rest of the system uses, so a
       number reaching this card always exists.

       That selector returns a NUMBER, but this transfer works on a
       file_indexings row, so the number is resolved through the card's own
       search endpoint and the exact match is taken. A number the selector knows
       but that has never been indexed has nothing to move, and says so.
       ──────────────────────────────────────────────────────── */

    pickFile() {
      if (typeof GlobalFileNoModal === 'undefined') {
        this.setLookupStatus('The file number selector is not available on this page.', true);
        return;
      }

      GlobalFileNoModal.open({
        callback: (fileData) => {
          const number = (fileData && fileData.fileNumber) ? String(fileData.fileNumber).trim() : '';
          if (number) this.resolveFileNumber(number);
        }
      });
    },

    /** The line under the picker button: progress, or why nothing was found. */
    setLookupStatus(message, isError) {
      const status = el('registry-transfer-lookup-status');

      if (!message) {
        status.classList.add('hidden');
        status.textContent = '';
        return;
      }

      status.textContent = message;
      status.classList.remove('hidden');
      status.classList.toggle('text-red-600', !!isError);
      status.classList.toggle('text-gray-500', !isError);
    },

    /** Turn a chosen file number into the indexing row this card operates on. */
    async resolveFileNumber(number) {
      el('registry-transfer-pick-file-label').textContent = number;
      this.setLookupStatus('Looking up ' + number + '…', false);

      try {
        const res = await fetch(`/edms/registry-transfer/search?search=${encodeURIComponent(number)}&limit=50`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        const files = json.data || [];

        // The endpoint matches on a LIKE, so a number that is a prefix of others
        // brings them along; only the exact one is this file.
        const wanted = number.toLowerCase();
        const match = files.find((f) => String(f.file_number || '').trim().toLowerCase() === wanted);

        if (!match) {
          this.clearFile();
          el('registry-transfer-pick-file-label').textContent = number;
          this.setLookupStatus(
            number + ' is not indexed, so it has no documents to move. Index it first.',
            true
          );
          return;
        }

        this.setLookupStatus('', false);
        await this.selectFile(match);
      } catch (err) {
        this.setLookupStatus('Could not look up ' + number + ': ' + err.message, true);
      }
    },

    async selectFile(file) {
      this.fileIndexingId = file.id;
      this.fileNumber = file.file_number || '';

      el('registry-transfer-file-number').textContent = this.fileNumber || `#${file.id}`;
      el('registry-transfer-selected-number').textContent = this.fileNumber || `#${file.id}`;
      el('registry-transfer-selected-title').textContent = file.file_title || '';
      el('registry-transfer-selected-counts').textContent =
        (file.scannings_count !== undefined)
          ? `${file.scannings_count} scan(s), ${file.pagetypings_count} typed page(s)`
          : '';

      el('registry-transfer-selected').classList.remove('hidden');
      el('registry-transfer-pick-file-label').textContent = this.fileNumber || `#${file.id}`;
      this.setLookupStatus('', false);

      el('registry-transfer-preview').classList.add('hidden');
      el('registry-transfer-blockers').classList.add('hidden');
      el('registry-transfer-confirm').disabled = true;

      await this.loadOptions();
      if (window.lucide) lucide.createIcons();
    },

    clearFile() {
      this.fileIndexingId = null;
      this.fileNumber = null;
      this.preview = null;

      el('registry-transfer-pick-file-label').textContent = 'Choose a file number…';
      el('registry-transfer-file-number').textContent = '—';
      el('registry-transfer-current').textContent = '—';
      el('registry-transfer-selected').classList.add('hidden');
      el('registry-transfer-preview').classList.add('hidden');
      el('registry-transfer-blockers').classList.add('hidden');
      el('registry-transfer-confirm').disabled = true;
      el('registry-transfer-target').innerHTML = '<option value="">Select a file first…</option>';
      this.setLookupStatus('', false);
    },

    close() {
      const modal = el('registry-transfer-modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      el('registry-transfer-loading').classList.add('hidden');
      el('registry-transfer-loading').classList.remove('flex');
    },

    async loadOptions() {
      const select = el('registry-transfer-target');

      if (!this.fileIndexingId) {
        select.innerHTML = '<option value="">Select a file first…</option>';
        return;
      }

      select.innerHTML = '<option value="">Loading…</option>';

      try {
        const res = await fetch(`/edms/registry-transfer/options?file_indexing_id=${encodeURIComponent(this.fileIndexingId)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not load registries.');

        el('registry-transfer-current').textContent = json.data.current_registry || 'Unknown';

        select.innerHTML = '<option value="">Select a registry…</option>';
        json.data.registries.forEach((r) => {
          if (r.is_current) return; // no point offering the one it is already in
          const opt = document.createElement('option');
          opt.value = r.name;
          opt.textContent = `${r.name}  (${r.slug}/)`;
          select.appendChild(opt);
        });

        select.onchange = () => this.loadPreview();
      } catch (err) {
        select.innerHTML = '<option value="">Could not load registries</option>';
        this.showBlockers([err.message]);
      }
    },

    async loadPreview() {
      const target = el('registry-transfer-target').value;
      const confirmBtn = el('registry-transfer-confirm');

      el('registry-transfer-blockers').classList.add('hidden');
      el('registry-transfer-preview').classList.add('hidden');
      confirmBtn.disabled = true;

      if (!target || !this.fileIndexingId) return;

      try {
        const res = await fetch('/edms/registry-transfer/preview', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_registry: target,
          }),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Preview failed.');

        this.preview = json.data;
        this.renderPreview(json.data);

        if (json.data.blockers.length) {
          this.showBlockers(json.data.blockers);
          confirmBtn.disabled = true;
        } else {
          confirmBtn.disabled = false;
        }
      } catch (err) {
        this.showBlockers([err.message]);
      }
    },

    renderPreview(data) {
      el('registry-transfer-count-scans').textContent = data.counts.scannings;
      el('registry-transfer-count-pages').textContent = data.counts.pagetypings;
      el('registry-transfer-from-slug').textContent = `${data.from_slug}/${data.file_number}/`;
      el('registry-transfer-to-slug').textContent = `${data.to_slug}/${data.file_number}/`;

      const missingWrap = el('registry-transfer-count-missing-wrap');
      if (data.counts.missing_on_disk > 0) {
        el('registry-transfer-count-missing').textContent = data.counts.missing_on_disk;
        missingWrap.classList.remove('hidden');
      } else {
        missingWrap.classList.add('hidden');
      }

      const rows = [
        ...data.scannings.map((r) => ({ ...r, label: `Scan #${r.id}` })),
        ...data.pagetypings.map((r) => ({ ...r, label: `Page #${r.id}` })),
      ];

      el('registry-transfer-rows').innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
              <td class="px-3 py-1.5 whitespace-nowrap">${escapeHtml(r.label)}</td>
              <td class="px-3 py-1.5 whitespace-nowrap">
                ${r.on_disk
                  ? '<span class="text-green-700">yes</span>'
                  : '<span class="text-amber-700">missing</span>'}
                ${r.conflict ? '<span class="text-red-700 ml-1">conflict</span>' : ''}
              </td>
              <td class="px-3 py-1.5 font-mono text-[11px] text-gray-600 whitespace-nowrap">${escapeHtml(r.target_path || '—')}</td>
            </tr>`).join('')
        : '<tr><td colspan="3" class="px-3 py-3 text-gray-500">No scans or typed pages — only the indexing record will change.</td></tr>';

      el('registry-transfer-preview').classList.remove('hidden');
    },

    showBlockers(messages) {
      const box = el('registry-transfer-blockers');
      box.innerHTML = messages.map((m) => `<p>${escapeHtml(m)}</p>`).join('');
      box.classList.remove('hidden');
    },

    async confirm() {
      const target = el('registry-transfer-target').value;
      if (!target || !this.fileIndexingId) return;

      const loading = el('registry-transfer-loading');
      const confirmBtn = el('registry-transfer-confirm');

      loading.classList.remove('hidden');
      loading.classList.add('flex');
      confirmBtn.disabled = true;

      try {
        const res = await fetch('/edms/registry-transfer/move', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_registry: target,
            reason: el('registry-transfer-reason').value || null,
          }),
        });

        const json = await res.json();

        if (!json.success) {
          this.showBlockers([json.message || 'The move failed.']);
          confirmBtn.disabled = false;
          return;
        }

        this.close();

        if (window.Swal) {
          Swal.fire({ icon: 'success', title: 'File moved', text: json.message, timer: 4000 });
        } else {
          alert(json.message);
        }

        if (this.onDone) this.onDone(json.data);
      } catch (err) {
        this.showBlockers(['The move failed: ' + err.message]);
        confirmBtn.disabled = false;
      } finally {
        loading.classList.add('hidden');
        loading.classList.remove('flex');
      }
    },
  };

  window.EdmsRegistryTransfer = EdmsRegistryTransfer;
})();
</script>
