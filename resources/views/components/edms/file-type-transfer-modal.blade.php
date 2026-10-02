{{--
  EDMS File Type modal — shared by Scan Upload, the Page Typing workspace and
  the File Archive (Doc-WARE) grid.

  Open it with:  EdmsFileType.open(fileIndexingId, fileNumber, onDone)

  Everything it needs comes from /edms/file-type/{options,search,cover,preview,move},
  so all three interfaces behave identically.

  The cover preview is the point of the dialog: the instruction that decides a
  file's type ("subdivision — mother", "extension", "change of purpose") is
  written on the file's cover, so the operator reads it here rather than guessing
  from the file number.
--}}
<div id="file-type-transfer-modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col relative">

    <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-200">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <i data-lucide="folder-tree" class="h-5 w-5 text-indigo-600"></i>
          File into a Master Folder
        </h3>
        <p class="text-sm text-gray-500 mt-1">
          Moves the scans, typed pages and Doc-WARE archive copies of
          <span class="font-semibold text-gray-700" id="file-type-transfer-file-number">—</span>
          into its master folder, inside the same registry.
        </p>
      </div>
      <button type="button" class="btn btn-ghost btn-icon" onclick="EdmsFileType.close()" title="Close">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="p-5 overflow-y-auto flex-1">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- LEFT: the cover, so the operator can read the instruction on it --}}
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-semibold text-gray-900">Cover page</label>
            <div class="flex items-center gap-2">
              <button type="button" id="file-type-cover-previous"
                      class="hidden btn btn-ghost btn-sm btn-icon" title="Previous page"
                      onclick="EdmsFileType.navigateCover(-1)">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
              </button>
              <span class="text-xs font-medium text-gray-600" id="file-type-cover-caption"></span>
              <button type="button" id="file-type-cover-next"
                      class="hidden btn btn-ghost btn-sm btn-icon" title="Next page"
                      onclick="EdmsFileType.navigateCover(1)">
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
              </button>
            </div>
          </div>
          <div id="file-type-cover-box"
               class="flex-1 min-h-[320px] rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
            <p class="text-sm text-gray-500 px-4 text-center">Select a file to see its cover.</p>
          </div>
          <p class="text-xs text-gray-500 mt-2">
            The classification instruction is usually written on the cover. Click the image to open it full size.
          </p>
        </div>

        {{-- RIGHT: pick the file, then pick the folder --}}
        <div class="space-y-4">

          {{-- File picker. The file number is chosen in the shared Global File
               Number selector — the same MLPP / KANGIS / New KANGIS picker every
               other module uses — rather than typed here, so a number that reaches
               this card is always one that exists. Its file_indexings row is then
               resolved by an exact lookup, since the transfer needs the row id and
               the selector deals only in numbers. --}}
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">File number</label>

            <button type="button" id="file-type-transfer-pick-file"
                    class="input input-bordered w-full flex items-center gap-2 text-left text-gray-500 hover:border-indigo-400 transition-colors"
                    onclick="EdmsFileType.pickFile()">
              <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
              <span id="file-type-transfer-pick-file-label">Choose a file number…</span>
            </button>

            {{-- Resolving the chosen number to its indexing row, or saying why it
                 could not be. --}}
            <p id="file-type-transfer-lookup-status" class="hidden mt-2 text-xs"></p>

            {{-- The chosen file --}}
            <div id="file-type-transfer-selected"
                 class="hidden mt-2 px-3 py-2 bg-indigo-50 border border-indigo-200 rounded-lg text-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="font-semibold text-indigo-900" id="file-type-transfer-selected-number">—</div>
                  <div class="text-xs text-indigo-700 truncate" id="file-type-transfer-selected-title"></div>
                </div>
                <div class="flex items-center gap-2 whitespace-nowrap">
                  <div class="text-xs text-indigo-700" id="file-type-transfer-selected-counts"></div>
                  <button type="button" id="file-type-transfer-clear-file"
                          class="btn btn-ghost btn-icon" title="Pick a different file"
                          onclick="EdmsFileType.clearFile()">
                    <i data-lucide="x" class="h-4 w-4"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {{-- Registry, then the master folder, picked the same way as on the
               Document Upload dialog: Category, then Type, then Old/New where
               the type has one (only Regrant and Resettlement do). The three
               resolve into the hidden #file-type-transfer-target input, which is
               what selectedTarget() reads. --}}
          <input type="hidden" id="file-type-transfer-target" value="">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-2">Registry</label>
              <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700"
                   id="file-type-transfer-current">—</div>
            </div>
            <div>
              <label for="file-type-transfer-category" class="block text-sm font-semibold text-gray-900 mb-2">File type</label>
              <select id="file-type-transfer-category" class="input input-bordered w-full">
                <option value="">Select a file first…</option>
              </select>
            </div>

            {{-- Revealed only for a category that has types under it. --}}
            <div id="file-type-transfer-type-wrap" class="hidden">
              <label for="file-type-transfer-type" class="block text-xs font-medium text-gray-600 mb-1">Type</label>
              <select id="file-type-transfer-type" class="input input-bordered w-full">
                <option value="">Select type…</option>
              </select>
            </div>

            {{-- Only Regrant and Resettlement reach this. The heading is built
                 from the type's own variant labels rather than hardcoded, so a
                 type added to the lookup table that splits some other way labels
                 itself correctly. --}}
            <div id="file-type-transfer-variant-wrap" class="hidden">
              <label for="file-type-transfer-variant" id="file-type-transfer-variant-label"
                     class="block text-xs font-medium text-gray-600 mb-1">Variant</label>
              <select id="file-type-transfer-variant" class="input input-bordered w-full">
                <option value="">Select…</option>
              </select>
            </div>
          </div>

          {{-- Where it is now, and where the current selection would put it. The
               cascade offers every folder including the one it is already in, so
               this line is how the operator sees that — and picking it back is
               caught as a blocker rather than filed as a no-op move. --}}
          <p class="text-xs text-gray-500 -mt-2">
            Currently: <span class="font-medium text-gray-700" id="file-type-transfer-current-type">—</span>
            <span id="file-type-transfer-target-trail" class="hidden"> · Filing into
              <span class="font-medium text-gray-700" id="file-type-transfer-target-trail-text"></span>
            </span>
          </p>

          <div>
            <label for="file-type-transfer-reason" class="block text-sm font-semibold text-gray-900 mb-2">
              Reason <span class="font-normal text-gray-500">(stored in the audit log)</span>
            </label>
            <input type="text" id="file-type-transfer-reason" maxlength="500"
                   class="input input-bordered w-full"
                   placeholder="e.g. Cover marked 'Subdivision — Mother'">
          </div>

          {{-- What will move --}}
          <div id="file-type-transfer-preview" class="hidden border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wide">
              What will move
            </div>
            <div class="p-4 space-y-3">
              <div class="flex flex-wrap gap-4 text-sm">
                <span><strong id="file-type-transfer-count-scans">0</strong> scan(s)</span>
                <span><strong id="file-type-transfer-count-pages">0</strong> typed page(s)</span>
                <span id="file-type-transfer-count-missing-wrap" class="hidden text-amber-700">
                  <strong id="file-type-transfer-count-missing">0</strong> not found on disk
                </span>
              </div>
              <div class="text-xs font-mono bg-gray-50 rounded p-2 overflow-x-auto whitespace-nowrap">
                <span id="file-type-transfer-from-slug" class="text-gray-500"></span>
                <span class="mx-2">&rarr;</span>
                <span id="file-type-transfer-to-slug" class="text-indigo-700 font-semibold"></span>
              </div>
              <div class="max-h-40 overflow-y-auto overflow-x-auto border border-gray-100 rounded">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr class="text-left text-gray-500">
                      <th class="px-3 py-2 font-medium">Record</th>
                      <th class="px-3 py-2 font-medium">On disk</th>
                      <th class="px-3 py-2 font-medium">Destination</th>
                    </tr>
                  </thead>
                  <tbody id="file-type-transfer-rows" class="divide-y divide-gray-100"></tbody>
                </table>
              </div>
            </div>
          </div>

          {{-- Blocked --}}
          <div id="file-type-transfer-blockers" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

          <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
            <i data-lucide="alert-triangle" class="h-4 w-4 flex-shrink-0 mt-0.5"></i>
            <span>
              The master folder is part of every document's folder path. This moves the files on disk and
              re-points the indexing record, its scans and its typed pages together, in one transaction.
              The registry is not changed.
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-200 bg-gray-50 rounded-b-xl">
      <button type="button" class="btn btn-ghost" onclick="EdmsFileType.close()">Cancel</button>
      <button type="button" id="file-type-transfer-confirm" class="btn btn-primary" disabled
              onclick="EdmsFileType.confirm()">
        <i data-lucide="folder-tree" class="h-4 w-4 mr-1"></i>
        File Away
      </button>
    </div>

    <div id="file-type-transfer-loading" class="hidden absolute inset-0 bg-white/70 items-center justify-center rounded-xl">
      <div class="flex flex-col items-center gap-2">
        <span class="loading loading-spinner loading-lg text-indigo-600"></span>
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

  // The category-level choice that means "move it back out to the registry
  // root". Not a real file type, so it needs a value no catalogue key can take.
  const UNCLASSIFY = '__unclassify__';

  const EdmsFileType = {
    fileIndexingId: null,
    fileNumber: null,
    onDone: null,
    preview: null,
    coverToken: 0,
    coverPages: [],
    coverPageIndex: 0,
    // The master-folder catalogue and where this file sits in it, both from
    // /edms/file-type/options.
    fileTypeTree: null,
    currentFileType: null,

    /**
     * Open the dialog. Both arguments are optional — called with no file, it
     * opens as a standalone tool and the operator picks one with the Global File
     * Number selector.
     */
    async open(fileIndexingId, fileNumber, onDone) {
      this.onDone = typeof onDone === 'function' ? onDone : null;
      this.preview = null;

      el('file-type-transfer-reason').value = '';
      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;
      this.setLookupStatus('', false);

      const modal = el('file-type-transfer-modal');
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
      const status = el('file-type-transfer-lookup-status');

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
      el('file-type-transfer-pick-file-label').textContent = number;
      this.setLookupStatus('Looking up ' + number + '…', false);

      try {
        const res = await fetch(`/edms/file-type/search?search=${encodeURIComponent(number)}&limit=50`, {
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
          el('file-type-transfer-pick-file-label').textContent = number;
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

      el('file-type-transfer-file-number').textContent = this.fileNumber || `#${file.id}`;
      el('file-type-transfer-selected-number').textContent = this.fileNumber || `#${file.id}`;
      el('file-type-transfer-selected-title').textContent = file.file_title || '';
      el('file-type-transfer-selected-counts').textContent =
        (file.scannings_count !== undefined)
          ? `${file.scannings_count} scan(s), ${file.pagetypings_count} typed page(s)`
          : '';

      el('file-type-transfer-selected').classList.remove('hidden');
      el('file-type-transfer-pick-file-label').textContent = this.fileNumber || `#${file.id}`;
      this.setLookupStatus('', false);

      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;

      await Promise.all([this.loadOptions(), this.loadCover()]);
      if (window.lucide) lucide.createIcons();
    },

    clearFile() {
      this.fileIndexingId = null;
      this.fileNumber = null;
      this.preview = null;
      this.coverToken++;
      this.coverPages = [];
      this.coverPageIndex = 0;

      el('file-type-transfer-pick-file-label').textContent = 'Choose a file number…';
      el('file-type-transfer-file-number').textContent = '—';
      el('file-type-transfer-current').textContent = '—';
      el('file-type-transfer-current-type').textContent = '—';
      el('file-type-transfer-selected').classList.add('hidden');
      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;
      this.resetCascade('Select a file first…');
      this.setLookupStatus('', false);
      el('file-type-cover-caption').textContent = '';
      el('file-type-cover-previous').classList.add('hidden');
      el('file-type-cover-next').classList.add('hidden');
      el('file-type-cover-box').innerHTML =
        '<p class="text-sm text-gray-500 px-4 text-center">Select a file to see its cover.</p>';
    },

    close() {
      const modal = el('file-type-transfer-modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      el('file-type-transfer-loading').classList.add('hidden');
      el('file-type-transfer-loading').classList.remove('flex');
    },

    renderCoverPage() {
      const box = el('file-type-cover-box');
      const caption = el('file-type-cover-caption');
      const previous = el('file-type-cover-previous');
      const next = el('file-type-cover-next');
      const page = this.coverPages[this.coverPageIndex];
      if (!page) return;

      caption.textContent = `page ${this.coverPageIndex + 1} of ${this.coverPages.length}`;
      const showNavigation = this.coverPages.length > 1;
      previous.classList.toggle('hidden', !showNavigation);
      next.classList.toggle('hidden', !showNavigation);
      previous.disabled = this.coverPageIndex === 0;
      next.disabled = this.coverPageIndex >= this.coverPages.length - 1;

      box.innerHTML = page.is_pdf
        ? `<embed src="${escapeHtml(page.url)}#page=1&view=FitH" type="application/pdf" class="w-full h-full min-h-[320px]">`
        : `<a href="${escapeHtml(page.url)}" target="_blank" rel="noopener" class="block w-full h-full">
             <img src="${escapeHtml(page.url)}" alt="Page ${this.coverPageIndex + 1} of ${escapeHtml(this.fileNumber)}"
                  class="w-full h-full object-contain max-h-[52vh]">
           </a>`;

      if (window.lucide) lucide.createIcons();
    },

    navigateCover(direction) {
      const targetIndex = this.coverPageIndex + Number(direction);
      if (targetIndex < 0 || targetIndex >= this.coverPages.length) return;
      this.coverPageIndex = targetIndex;
      this.renderCoverPage();
    },

    async loadCover() {
      const box = el('file-type-cover-box');
      const caption = el('file-type-cover-caption');
      const token = ++this.coverToken;

      if (!this.fileIndexingId) return;

      caption.textContent = '';
      this.coverPages = [];
      this.coverPageIndex = 0;
      el('file-type-cover-previous').classList.add('hidden');
      el('file-type-cover-next').classList.add('hidden');
      box.innerHTML = '<p class="text-sm text-gray-500">Loading cover…</p>';

      try {
        const res = await fetch(`/edms/file-type/cover?file_indexing_id=${encodeURIComponent(this.fileIndexingId)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (token !== this.coverToken) return;

        const data = json.data || {};

        const pages = Array.isArray(data.pages) && data.pages.length
          ? data.pages
          : (data.url ? [{
              url: data.url,
              filename: data.filename,
              is_pdf: data.is_pdf,
              scanning_id: data.scanning_id,
            }] : []);

        if (!pages.length) {
          box.innerHTML = `<p class="text-sm text-gray-500 px-4 text-center">${escapeHtml(data.message || 'No cover available.')}</p>`;
          return;
        }

        this.coverPages = pages;
        this.coverPageIndex = 0;
        this.renderCoverPage();
      } catch (err) {
        if (token !== this.coverToken) return;
        box.innerHTML = `<p class="text-sm text-red-600 px-4 text-center">Could not load the cover: ${escapeHtml(err.message)}</p>`;
      }
    },

    /* ─────────────────── The master-folder cascade ────────────────────
       Category -> Type -> Old/New, from the edms_file_types lookup table the
       options endpoint sends as `file_type_tree`. The same three-step pick as
       the Document Upload dialog, so an operator learns it once.

       Unlike that one, this cascade offers the folder the file is already in:
       hiding it would leave the operator wondering where it went. Choosing it
       is caught in loadPreview() as a blocker instead of filed as a no-op.

       Whatever the three resolve to is written into the hidden
       #file-type-transfer-target input, which selectedTarget() reads.
       ──────────────────────────────────────────────────────────────── */

    /** Find the catalogue row for a stored key, or null. */
    findTypeByCode(code) {
      if (!code) return null;

      for (const [categoryKey, category] of Object.entries(this.fileTypeTree || {})) {
        for (const [typeKey, type] of Object.entries(category.types || {})) {
          for (const variant of type.variants || []) {
            if (variant.code === code) {
              return { categoryKey, typeKey, variantKey: variant.key || '', label: variant.label };
            }
          }
        }
      }

      return null;
    },

    /** Replace a select's options, keeping `selected` if it is still offered. */
    fillCascadeSelect(id, options, placeholder, selected) {
      const select = el(id);
      if (!select) return;

      select.innerHTML = '';
      select.appendChild(new Option(placeholder, ''));
      options.forEach((option) => select.appendChild(new Option(option.label, option.value)));
      select.value = options.some((o) => o.value === selected) ? selected : '';
    },

    /** The single code under a category that has no further choices. */
    onlyCodeIn(category) {
      const type = Object.values(category.types || {})[0];

      return type?.variants?.[0]?.code || '';
    },

    /**
     * Repaint the Type and Old/New dropdowns for the chosen category, resolve
     * the three into the hidden input, then re-run the preview.
     *
     * `keep` preserves the current type/variant where they still exist, so
     * re-entering a category does not silently drop a valid selection.
     */
    refreshCascade(keep = true) {
      const categoryKey = el('file-type-transfer-category').value || '';

      // "Unclassified — back to the registry root" is a category-level choice:
      // it is the way to undo a mis-filing, and it has nothing below it.
      if (categoryKey === UNCLASSIFY) {
        el('file-type-transfer-type-wrap').classList.add('hidden');
        el('file-type-transfer-variant-wrap').classList.add('hidden');
        this.commitTarget(UNCLASSIFY);
        return;
      }

      const category = (this.fileTypeTree || {})[categoryKey] || null;
      const wantedType = keep ? (el('file-type-transfer-type').value || '') : '';
      const wantedVariant = keep ? (el('file-type-transfer-variant').value || '') : '';

      // No category, or one that is its own answer (Regular): both extra
      // dropdowns stay hidden and the hidden input takes the category's only
      // code straight away.
      if (!category || !category.has_children) {
        el('file-type-transfer-type-wrap').classList.add('hidden');
        el('file-type-transfer-variant-wrap').classList.add('hidden');
        el('file-type-transfer-type').value = '';
        el('file-type-transfer-variant').value = '';
        this.commitTarget(category ? this.onlyCodeIn(category) : '');
        return;
      }

      el('file-type-transfer-type-wrap').classList.remove('hidden');

      const types = Object.entries(category.types || {})
        .map(([value, type]) => ({ value, label: type.label }));
      this.fillCascadeSelect('file-type-transfer-type', types, 'Select type…', wantedType);

      const typeKey = el('file-type-transfer-type').value || '';
      const type = typeKey ? (category.types || {})[typeKey] : null;
      const variants = (type?.variants || []).filter((v) => v.key !== '');

      if (!type || variants.length === 0) {
        el('file-type-transfer-variant-wrap').classList.add('hidden');
        el('file-type-transfer-variant').value = '';
        this.commitTarget(type ? (type.variants?.[0]?.code || '') : '');
        return;
      }

      el('file-type-transfer-variant-wrap').classList.remove('hidden');
      el('file-type-transfer-variant-label').textContent = variants.map((v) => v.label).join(' / ');
      this.fillCascadeSelect(
        'file-type-transfer-variant',
        variants.map((v) => ({ value: v.key, label: v.label })),
        'Select…',
        wantedVariant
      );

      const variantKey = el('file-type-transfer-variant').value || '';
      const chosen = variants.find((v) => v.key === variantKey);
      this.commitTarget(chosen ? chosen.code : '');
    },

    /** Write the resolved key into the hidden input, show the trail, re-preview. */
    commitTarget(code) {
      el('file-type-transfer-target').value = code || '';

      const trail = el('file-type-transfer-target-trail');
      const trailText = el('file-type-transfer-target-trail-text');

      if (!code) {
        trail.classList.add('hidden');
      } else if (code === UNCLASSIFY) {
        trailText.textContent = 'the registry root (unclassified)';
        trail.classList.remove('hidden');
      } else {
        const found = this.findTypeByCode(code);
        const category = (this.fileTypeTree || {})[found?.categoryKey] || null;
        const type = category?.types?.[found?.typeKey] || null;
        const parts = [category?.label, type?.label, found?.variantKey ? found.label : null].filter(Boolean);
        trailText.textContent = parts.join(' › ');
        trail.classList.toggle('hidden', parts.length === 0);
      }

      this.loadPreview();
    },

    /** Bound once — the three selects all funnel back through refreshCascade(). */
    bindCascade() {
      const category = el('file-type-transfer-category');
      if (category.dataset.bound) return;
      category.dataset.bound = '1';

      category.addEventListener('change', () => this.refreshCascade(false));
      el('file-type-transfer-type').addEventListener('change', () => {
        el('file-type-transfer-variant').value = '';
        this.refreshCascade(true);
      });
      el('file-type-transfer-variant').addEventListener('change', () => this.refreshCascade(true));
    },

    /** Put the cascade back to "nothing picked". */
    resetCascade(placeholder) {
      this.fileTypeTree = null;
      this.currentFileType = null;

      el('file-type-transfer-category').innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
      el('file-type-transfer-type-wrap').classList.add('hidden');
      el('file-type-transfer-variant-wrap').classList.add('hidden');
      el('file-type-transfer-target').value = '';
      el('file-type-transfer-target-trail').classList.add('hidden');
    },

    async loadOptions() {
      if (!this.fileIndexingId) {
        this.resetCascade('Select a file first…');
        return;
      }

      this.resetCascade('Loading…');

      try {
        const res = await fetch(`/edms/file-type/options?file_indexing_id=${encodeURIComponent(this.fileIndexingId)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not load the file types.');

        el('file-type-transfer-current').textContent = json.data.current_registry || 'Unknown registry';
        el('file-type-transfer-current-type').textContent = json.data.current_file_type_label || 'Unclassified';

        this.fileTypeTree = json.data.file_type_tree || {};
        this.currentFileType = json.data.current_file_type || null;

        const categories = Object.entries(this.fileTypeTree)
          .map(([value, category]) => ({ value, label: category.label }));

        this.fillCascadeSelect('file-type-transfer-category', categories, 'Select a master folder…', '');

        // Moving a file back out to the registry root — the way to undo a
        // mis-filing without leaving it in the wrong folder meanwhile. Offered
        // only when there is something to undo.
        if (this.currentFileType) {
          el('file-type-transfer-category').appendChild(
            new Option('Unclassified  (back to the registry root)', UNCLASSIFY)
          );
        }

        this.bindCascade();
        this.refreshCascade(false);
      } catch (err) {
        this.resetCascade('Could not load the file types');
        this.showBlockers([err.message]);
      }
    },

    /**
     * The chosen target, or null for "unclassified". `chosen` separates "nothing
     * picked yet" (and half-finished cascades) from a real choice, so the
     * preview does not fire on an incomplete selection.
     */
    selectedTarget() {
      const value = el('file-type-transfer-target').value || '';

      if (value === UNCLASSIFY) return { chosen: true, value: null };
      if (!value) return { chosen: false, value: null };

      return { chosen: true, value };
    },

    async loadPreview() {
      const target = this.selectedTarget();
      const confirmBtn = el('file-type-transfer-confirm');

      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-preview').classList.add('hidden');
      confirmBtn.disabled = true;

      if (!target.chosen || !this.fileIndexingId) return;

      // The cascade offers the current folder — hiding it would leave the
      // operator wondering where it went — so the no-op is caught here.
      if (target.value && target.value === this.currentFileType) {
        this.showBlockers(['This file is already in that master folder.']);
        return;
      }

      try {
        const res = await fetch('/edms/file-type/preview', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_file_type: target.value,
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
      el('file-type-transfer-count-scans').textContent = data.counts.scannings;
      el('file-type-transfer-count-pages').textContent = data.counts.pagetypings;
      el('file-type-transfer-from-slug').textContent = `${data.from_slug}/${data.file_number}/`;
      el('file-type-transfer-to-slug').textContent = `${data.to_slug}/${data.file_number}/`;

      const missingWrap = el('file-type-transfer-count-missing-wrap');
      if (data.counts.missing_on_disk > 0) {
        el('file-type-transfer-count-missing').textContent = data.counts.missing_on_disk;
        missingWrap.classList.remove('hidden');
      } else {
        missingWrap.classList.add('hidden');
      }

      const rows = [
        ...data.scannings.map((r) => ({ ...r, label: `Scan #${r.id}` })),
        ...data.pagetypings.map((r) => ({ ...r, label: `Page #${r.id}` })),
      ];

      el('file-type-transfer-rows').innerHTML = rows.length
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

      el('file-type-transfer-preview').classList.remove('hidden');
    },

    showBlockers(messages) {
      const box = el('file-type-transfer-blockers');
      box.innerHTML = messages.map((m) => `<p>${escapeHtml(m)}</p>`).join('');
      box.classList.remove('hidden');
    },

    async confirm() {
      const target = this.selectedTarget();
      if (!target.chosen || !this.fileIndexingId) return;

      const loading = el('file-type-transfer-loading');
      const confirmBtn = el('file-type-transfer-confirm');

      loading.classList.remove('hidden');
      loading.classList.add('flex');
      confirmBtn.disabled = true;

      try {
        const res = await fetch('/edms/file-type/move', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_file_type: target.value,
            reason: el('file-type-transfer-reason').value || null,
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

  window.EdmsFileType = EdmsFileType;
})();
</script>
