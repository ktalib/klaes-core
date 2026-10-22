{{--
    DMS update, on the file page (user, 2026-10-18): the file came back from Land with more pages
    than it went with, so the new pages are uploaded here into the file's DMS record (CaseController::dmsUpload, filed
    under EDMS/SCAN_UPLOAD/Cadastral_Registry/ like Cadastral's other documents). Page typing stays on the Page Typing page.
    $case, $pageCounts (PageCounts::state()) and $edms (EdmsUpdate::status()).
--}}
@php
  $dmsNew = $pageCounts['new_pages'] !== null ? max(0, $pageCounts['new_pages']) : null;
@endphp
<div id="cd-dms-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" role="dialog" aria-modal="true" aria-labelledby="cd-dms-title"
     x-data="cdDmsUpdate({ url: @js(route('cadastral-dept.cases.dms-upload', $case->id)), uploaded: {{ (int) $edms['new_pages'] }}, typed: {{ (int) $edms['typed'] }}, expected: {{ $dmsNew ?? 'null' }} })"
     @click.self="close()" @keydown.escape.window="close()">
  <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl max-h-full overflow-y-auto">
    <div class="flex items-start justify-between gap-3 px-5 pt-5">
      <div>
        <div class="text-xs font-bold uppercase text-gray-500">{{ $case->case_ref }} · {{ $case->file_number }}</div>
        <div class="text-lg font-bold text-gray-800" id="cd-dms-title">DMS update</div>
        <div class="text-xs text-gray-500">{{ $case->file_title }}</div>
      </div>
      <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Close">&times;</button>
    </div>

    <div class="px-5 py-4 space-y-4">
      {{-- The count that triggered it. --}}
      <div class="grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg border border-gray-200 px-3 py-2">
          <div class="text-[10px] font-bold uppercase text-gray-500">Went to Land with</div>
          <div class="text-2xl font-bold text-gray-800">{{ $pageCounts['out'] ? number_format($pageCounts['out']->pages) : '—' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 px-3 py-2">
          <div class="text-[10px] font-bold uppercase text-gray-500">Came back with</div>
          <div class="text-2xl font-bold text-gray-800">{{ $pageCounts['back'] ? number_format($pageCounts['back']->pages) : '—' }}</div>
        </div>
        <div class="rounded-lg border-2 border-yellow-400 bg-yellow-50 px-3 py-2">
          <div class="text-[10px] font-bold uppercase text-yellow-800">New pages</div>
          <div class="text-2xl font-extrabold text-yellow-800">{{ $dmsNew ?? '?' }}</div>
        </div>
      </div>
      <p class="text-sm text-gray-700">
        @if ($dmsNew)
          <strong>{{ $dmsNew }} new page{{ $dmsNew === 1 ? '' : 's' }}</strong> came back from Land (the signed recommendation and R of O). Scan them and upload them here; they go into {{ $case->file_number }}'s DMS record.
        @else
          No count was taken when the file went to Land. Scan the pages added at Land and upload them here; they go into {{ $case->file_number }}'s DMS record.
        @endif
      </p>

      {{-- Progress so far. --}}
      <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
          <span><strong x-text="uploaded"></strong> file(s) uploaded since it came back<span x-show="expected"> · <span x-text="expected"></span> new page(s) expected</span></span>
          <span><strong x-text="typed"></strong> page-typed</span>
        </div>
        <div class="h-2 rounded-full bg-gray-200 overflow-hidden" x-show="expected">
          <div class="h-2 bg-green-600 transition-all" :style="`width:${Math.min(100, expected ? uploaded / expected * 100 : 0)}%`"></div>
        </div>
      </div>

      {{-- Drop or pick the scans. --}}
      <label class="block rounded-xl border-2 border-dashed px-4 py-6 text-center cursor-pointer transition"
             :class="drag ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'"
             @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drag = false; add($event.dataTransfer.files)">
        <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-blue-600"></i>
        <div class="mt-1 text-sm font-semibold text-gray-800">Drop the scanned pages here, or click to choose</div>
        <div class="text-xs text-gray-500">PDF, JPG or PNG · up to 10 MB each</div>
        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="add($event.target.files); $event.target.value = ''">
      </label>

      <template x-if="files.length">
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
          <template x-for="(f, i) in files" :key="i">
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
              <span class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-gray-400" x-text="(uploaded + i + 1) + '.'"></span>
                <span class="truncate" x-text="f.name"></span>
                <span class="text-xs text-gray-500 whitespace-nowrap" x-text="(f.size / 1024 / 1024).toFixed(2) + ' MB'"></span>
              </span>
              <button type="button" @click="files.splice(i, 1)" :disabled="busy" class="text-xs text-red-700 hover:underline disabled:opacity-50">Remove</button>
            </li>
          </template>
        </ul>
      </template>

      <div x-show="busy" class="h-2 rounded-full bg-gray-200 overflow-hidden"><div class="h-2 bg-blue-600 transition-all" :style="`width:${progress}%`"></div></div>
      <p x-show="message" x-text="message" class="text-sm" :class="ok ? 'text-green-700' : 'text-red-700'"></p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-5 py-3">
      <a href="{{ $edms['type_url'] }}" target="_blank" x-show="uploaded > 0"
         class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline">
        <i data-lucide="tags" class="w-4 h-4"></i> Page-type the new pages (Page Typing)
      </a>
      <span x-show="uploaded === 0"></span>
      <div class="flex gap-2">
        <button type="button" @click="close()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Close</button>
        <button type="button" @click="upload()" :disabled="busy || !files.length"
                class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold disabled:opacity-50"
                x-text="busy ? 'Uploading…' : (files.length ? `Upload ${files.length} file(s)` : 'Upload')"></button>
      </div>
    </div>
  </div>
</div>

<script>
  function cdDmsUpdate(cfg) {
    return Object.assign({ files: [], drag: false, busy: false, progress: 0, message: '', ok: false, changed: false }, cfg, {
      add(list) {
        const okTypes = /\.(pdf|jpe?g|png)$/i;
        for (const f of Array.from(list || [])) {
          if (!okTypes.test(f.name)) { this.ok = false; this.message = f.name + ': only PDF, JPG or PNG.'; continue; }
          if (f.size > 10 * 1024 * 1024) { this.ok = false; this.message = f.name + ': larger than 10 MB.'; continue; }
          this.files.push(f);
        }
      },
      upload() {
        if (!this.files.length || this.busy) return;
        const fd = new FormData();
        this.files.forEach(f => fd.append('documents[]', f));
        const xhr = new XMLHttpRequest();
        xhr.open('POST', this.url);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = e => { if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100); };
        xhr.onload = () => {
          this.busy = false;
          let res = {};
          try { res = JSON.parse(xhr.responseText); } catch (e) {}
          this.ok = xhr.status < 300 && res.success;
          this.message = res.message || (this.ok ? 'Uploaded.' : 'The upload failed.');
          if (res.errors && !Array.isArray(res.errors)) this.message += ' ' + Object.values(res.errors).flat().join(' ');
          if (res.dms) { this.uploaded = res.dms.new_pages; this.typed = res.dms.typed; }
          if (this.ok) { this.files = []; this.changed = true; }
        };
        xhr.onerror = () => { this.busy = false; this.ok = false; this.message = 'The upload failed: no connection.'; };
        this.busy = true; this.progress = 0; this.message = '';
        xhr.send(fd);
      },
      close() {
        if (this.busy) return;
        document.getElementById('cd-dms-modal').classList.add('hidden');
        if (this.changed) window.location.reload();   // the task line shows the new counts
      },
    });
  }
</script>
