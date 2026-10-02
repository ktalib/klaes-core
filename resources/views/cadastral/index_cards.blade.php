@extends('layouts.app')

@section('page-title')
    {{ __('Cadastral Index Cards') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')

    <div class="p-6 max-w-7xl mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold text-rose-600 uppercase tracking-widest">Cadastral</p>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Index Cards</h1>
                    <p class="text-sm text-slate-500 mt-1">Browse scanned cadastral registry documents — images and PDFs.</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <i data-lucide="layers" class="h-4 w-4 text-rose-400"></i>
                    <span id="total-count-label">Loading…</span>
                </div>
            </div>
        </div>

        {{-- Breadcrumb / Back bar (hidden on folder view) --}}
        <div id="back-bar" class="hidden items-center gap-3">
            <button id="back-btn"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-rose-600 bg-white border border-slate-200 hover:border-rose-300 rounded-lg px-4 py-2 shadow-sm transition-colors">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Back to folders
            </button>
            <div class="flex items-center gap-2 text-slate-500 text-sm">
                <i data-lucide="folder-open" class="h-4 w-4 text-rose-400"></i>
                <span id="current-folder-label" class="font-semibold text-slate-800"></span>
                <span id="folder-file-count" class="text-slate-400"></span>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none"></i>
                <input id="search-input" type="text"
                    placeholder="Search folder name or filename…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-slate-50" />
            </div>
            <div id="per-page-wrap" class="hidden items-center gap-2 shrink-0">
                <label class="text-xs text-slate-500 whitespace-nowrap">Per page</label>
                <select id="per-page-select" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-rose-300 bg-slate-50">
                    <option value="12">12</option>
                    <option value="24" selected>24</option>
                    <option value="48">48</option>
                    <option value="96">96</option>
                </select>
            </div>
        </div>

        {{-- Main grid --}}
        <div id="main-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"></div>

        {{-- Empty state --}}
        <div id="empty-state" class="hidden flex flex-col items-center justify-center py-20 text-slate-400">
            <i data-lucide="folder-open" class="h-16 w-16 mb-4 text-slate-200"></i>
            <p class="text-lg font-medium">No documents found</p>
            <p class="text-sm mt-1">Try adjusting your search.</p>
        </div>

        {{-- Loading skeleton --}}
        <div id="loading-state" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            @for($i = 0; $i < 12; $i++)
            <div class="animate-pulse bg-white rounded-xl border border-slate-100 overflow-hidden">
                <div class="bg-slate-200 aspect-[3/4]"></div>
                <div class="p-3 space-y-2">
                    <div class="h-3 bg-slate-200 rounded w-3/4"></div>
                    <div class="h-2 bg-slate-100 rounded w-1/2"></div>
                </div>
            </div>
            @endfor
        </div>

        {{-- Pagination (files view only) --}}
        <div id="pagination-bar" class="hidden flex-col sm:flex-row items-center justify-between gap-3 bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3">
            <p id="pagination-info" class="text-sm text-slate-500"></p>
            <div id="pagination-buttons" class="flex items-center gap-1"></div>
        </div>

    </div>

    @include('admin.footer')
</div>

{{-- ── Preview Modal ── --}}
<div id="preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4" role="dialog" aria-modal="true">
    <div class="relative bg-white rounded-2xl shadow-2xl flex flex-col w-full max-w-4xl max-h-[92vh] overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 shrink-0">
            <div class="min-w-0">
                <p id="modal-folder"   class="text-xs font-semibold text-rose-600 uppercase tracking-widest truncate"></p>
                <h3 id="modal-filename" class="text-base font-semibold text-slate-800 truncate"></h3>
            </div>
            <div class="flex items-center gap-2 shrink-0 ml-4">
                <a id="modal-open-btn" href="#" target="_blank"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 hover:text-rose-600 border border-slate-200 hover:border-rose-300 rounded-lg px-3 py-1.5 transition-colors">
                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i>Open
                </a>
                <button id="modal-prev-btn" title="Previous"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                    <i data-lucide="chevron-left" class="h-5 w-5"></i>
                </button>
                <button id="modal-next-btn" title="Next"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                    <i data-lucide="chevron-right" class="h-5 w-5"></i>
                </button>
                <button id="modal-close-btn" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>
        <div id="modal-body" class="flex-1 overflow-auto flex items-center justify-center bg-slate-50 min-h-0 p-2"></div>
        <div class="flex items-center gap-4 px-5 py-2 border-t border-slate-100 bg-white text-xs text-slate-400 shrink-0 flex-wrap">
            <span id="modal-doc-type"></span>
            <span id="modal-file-size"></span>
            <span id="modal-date"></span>
            <span id="modal-counter" class="ml-auto font-medium text-slate-500"></span>
        </div>
    </div>
</div>

<script>
(function () {
    const foldersUrl    = "{{ route('cadastral.index-cards.data') }}";
    const folderFilesUrl = "{{ route('cadastral.index-cards.folder-files') }}";

    // ── State ──
    let view          = 'folders'; // 'folders' | 'files'
    let currentFolder = null;
    let currentFiles  = [];
    let previewIndex  = 0;
    let filesPage     = 1;
    let filesPerPage  = 24;
    let filesLastPage = 1;
    let searchTimer   = null;

    // ── DOM ──
    const grid         = document.getElementById('main-grid');
    const loadingEl    = document.getElementById('loading-state');
    const emptyEl      = document.getElementById('empty-state');
    const paginationBar= document.getElementById('pagination-bar');
    const paginationInfo= document.getElementById('pagination-info');
    const paginationBtns= document.getElementById('pagination-buttons');
    const totalLabel   = document.getElementById('total-count-label');
    const searchInput  = document.getElementById('search-input');
    const perPageWrap  = document.getElementById('per-page-wrap');
    const perPageSel   = document.getElementById('per-page-select');
    const backBar      = document.getElementById('back-bar');
    const backBtn      = document.getElementById('back-btn');
    const folderLabel  = document.getElementById('current-folder-label');
    const folderCount  = document.getElementById('folder-file-count');

    // Preview modal
    const modal        = document.getElementById('preview-modal');
    const modalBody    = document.getElementById('modal-body');
    const modalFolder  = document.getElementById('modal-folder');
    const modalFName   = document.getElementById('modal-filename');
    const modalOpenBtn = document.getElementById('modal-open-btn');
    const modalClose   = document.getElementById('modal-close-btn');
    const modalPrev    = document.getElementById('modal-prev-btn');
    const modalNext    = document.getElementById('modal-next-btn');
    const modalDocType = document.getElementById('modal-doc-type');
    const modalSize    = document.getElementById('modal-file-size');
    const modalDate    = document.getElementById('modal-date');
    const modalCounter = document.getElementById('modal-counter');

    // ── Folder view ──
    function loadFolders() {
        showLoading();
        const search = searchInput.value.trim();
        fetch(`${foldersUrl}?search=${encodeURIComponent(search)}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) { showError(res.message); return; }
                const items = res.data || [];
                totalLabel.textContent = items.length + ' folder' + (items.length !== 1 ? 's' : '');
                if (!items.length) { emptyEl.classList.remove('hidden'); return; }
                grid.innerHTML = items.map(buildFolderCard).join('');
                grid.classList.remove('hidden');
                lucide.createIcons();
                grid.querySelectorAll('[data-folder]').forEach(el => {
                    el.addEventListener('click', () => openFolder(el.dataset.folder, el.dataset.display, parseInt(el.dataset.count)));
                });
            })
            .catch(e => { hideLoading(); showError(e.message); });
    }

    function buildFolderCard(item) {
        let preview;
        if (item.thumbnail) {
            preview = `<div class="w-full aspect-[3/4] bg-slate-100 overflow-hidden relative">
                <img src="${escHtml(item.thumbnail)}" loading="lazy" alt="${escHtml(item.display_name)}"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                     onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-slate-100\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'h-10 w-10 text-slate-300\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z\'/></svg></div>'">
                <div class="absolute bottom-0 right-0 m-2 bg-black/50 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-full">${item.file_count}</div>
            </div>`;
        } else if (item.is_pdf_first) {
            preview = `<div class="w-full aspect-[3/4] bg-gradient-to-br from-red-50 to-rose-100 flex flex-col items-center justify-center gap-2 relative">
                <i data-lucide="folder" class="h-12 w-12 text-rose-300"></i>
                <div class="absolute bottom-0 right-0 m-2 bg-black/50 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-full">${item.file_count}</div>
            </div>`;
        } else {
            preview = `<div class="w-full aspect-[3/4] bg-gradient-to-br from-amber-50 to-orange-100 flex flex-col items-center justify-center gap-2 relative">
                <i data-lucide="folder" class="h-12 w-12 text-amber-400"></i>
                <div class="absolute bottom-0 right-0 m-2 bg-black/50 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded-full">${item.file_count}</div>
            </div>`;
        }

        return `<div data-folder="${escHtml(item.folder)}" data-display="${escHtml(item.display_name)}" data-count="${item.file_count}"
                     class="group cursor-pointer bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-rose-200 transition-all duration-200 overflow-hidden flex flex-col">
            ${preview}
            <div class="p-3 flex flex-col gap-1">
                <p class="text-xs font-semibold text-slate-800 truncate" title="${escHtml(item.display_name)}">${escHtml(item.display_name)}</p>
                <p class="text-[11px] text-slate-400">${item.file_count} file${item.file_count !== 1 ? 's' : ''}</p>
                <p class="text-[10px] text-slate-300">${escHtml(item.modified_at || '')}</p>
            </div>
        </div>`;
    }

    // ── Files view ──
    function openFolder(folder, displayName, count) {
        view          = 'files';
        currentFolder = folder;
        filesPage     = 1;
        searchInput.value = '';

        folderLabel.textContent = displayName || folder;
        folderCount.textContent = count ? `(${count} files)` : '';

        backBar.classList.remove('hidden');
        backBar.classList.add('flex');
        perPageWrap.classList.remove('hidden');
        perPageWrap.classList.add('flex');
        totalLabel.textContent = '';

        loadFolderFiles();
    }

    function loadFolderFiles() {
        showLoading();
        const search = searchInput.value.trim();
        const params = new URLSearchParams({
            folder:   currentFolder,
            page:     filesPage,
            per_page: filesPerPage,
            search:   search,
        });
        fetch(`${folderFilesUrl}?${params}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) { showError(res.message); return; }
                currentFiles  = res.data || [];
                const pg      = res.pagination;
                filesLastPage = pg.last_page;

                totalLabel.textContent = pg.total + ' file' + (pg.total !== 1 ? 's' : '');

                if (!currentFiles.length) { emptyEl.classList.remove('hidden'); return; }

                grid.innerHTML = currentFiles.map((item, idx) => buildFileCard(item, idx)).join('');
                grid.classList.remove('hidden');
                lucide.createIcons();

                grid.querySelectorAll('[data-file-idx]').forEach(el => {
                    el.addEventListener('click', () => openPreview(parseInt(el.dataset.fileIdx)));
                });

                renderPagination(pg);
                paginationBar.classList.remove('hidden');
                paginationBar.classList.add('flex');
            })
            .catch(e => { hideLoading(); showError(e.message); });
    }

    function buildFileCard(item, idx) {
        let preview;
        if (item.is_pdf) {
            preview = `<div class="w-full aspect-[3/4] bg-gradient-to-br from-red-50 to-rose-100 flex flex-col items-center justify-center gap-2">
                <i data-lucide="file-text" class="h-10 w-10 text-rose-400"></i>
                <span class="text-xs font-bold text-rose-400 uppercase tracking-wide">PDF</span>
            </div>`;
        } else if (item.url) {
            preview = `<div class="w-full aspect-[3/4] bg-slate-100 overflow-hidden">
                <img src="${escHtml(item.url)}" loading="lazy" alt="${escHtml(item.original_filename)}"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                     onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-slate-100\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'h-10 w-10 text-slate-300\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
            </div>`;
        } else {
            preview = `<div class="w-full aspect-[3/4] bg-slate-100 flex items-center justify-center">
                <i data-lucide="image-off" class="h-10 w-10 text-slate-300"></i>
            </div>`;
        }

        return `<div data-file-idx="${idx}"
                     class="group cursor-pointer bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-rose-200 transition-all duration-200 overflow-hidden flex flex-col">
            ${preview}
            <div class="p-3 flex flex-col gap-1 flex-1">
                <p class="text-xs font-semibold text-slate-800 truncate leading-tight" title="${escHtml(item.original_filename)}">${escHtml(item.original_filename)}</p>
                <p class="text-[11px] text-slate-400">${escHtml(item.document_type || '')}</p>
                <p class="text-[10px] text-slate-300 mt-auto pt-1">${escHtml(item.created_at || '')}</p>
            </div>
        </div>`;
    }

    // ── Back to folders ──
    function goBackToFolders() {
        view          = 'folders';
        currentFolder = null;
        currentFiles  = [];
        searchInput.value = '';

        backBar.classList.add('hidden');
        backBar.classList.remove('flex');
        perPageWrap.classList.add('hidden');
        perPageWrap.classList.remove('flex');
        paginationBar.classList.add('hidden');
        paginationBar.classList.remove('flex');

        loadFolders();
    }

    backBtn.addEventListener('click', goBackToFolders);

    // ── Pagination ──
    function renderPagination(pg) {
        paginationInfo.textContent = `Showing ${pg.from}–${pg.to} of ${pg.total} files`;
        const maxVisible = 5;
        let start = Math.max(1, pg.current_page - Math.floor(maxVisible / 2));
        let end   = Math.min(pg.last_page, start + maxVisible - 1);
        if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);

        let html = btnHtml('prev', pg.current_page <= 1, '&laquo;', 'prev');
        if (start > 1) { html += btnHtml(1, false, '1'); if (start > 2) html += `<span class="px-1 text-slate-400 text-sm">…</span>`; }
        for (let i = start; i <= end; i++) html += btnHtml(i, false, i, null, i === pg.current_page);
        if (end < pg.last_page) { if (end < pg.last_page - 1) html += `<span class="px-1 text-slate-400 text-sm">…</span>`; html += btnHtml(pg.last_page, false, pg.last_page); }
        html += btnHtml('next', pg.current_page >= pg.last_page, '&raquo;', 'next');

        paginationBtns.innerHTML = html;
        paginationBtns.querySelectorAll('button[data-pg]').forEach(btn => {
            btn.addEventListener('click', () => {
                const v = btn.dataset.pg;
                if (v === 'prev') filesPage = Math.max(1, filesPage - 1);
                else if (v === 'next') filesPage = Math.min(filesLastPage, filesPage + 1);
                else filesPage = parseInt(v);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                loadFolderFiles();
            });
        });
    }

    function btnHtml(pg, disabled, label, pgOverride, active = false) {
        const pgVal = pgOverride != null ? pgOverride : pg;
        const base  = 'inline-flex items-center justify-center h-8 min-w-[2rem] px-2 rounded-lg text-sm font-medium transition-colors';
        const cls   = active ? `${base} bg-rose-600 text-white`
                    : disabled ? `${base} text-slate-300 cursor-not-allowed`
                    : `${base} text-slate-600 hover:bg-slate-100`;
        return `<button data-pg="${pgVal}" class="${cls}" ${disabled ? 'disabled' : ''}>${label}</button>`;
    }

    // ── Preview Modal ──
    function openPreview(idx) {
        previewIndex = idx;
        renderPreview();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        lucide.createIcons();
    }

    function renderPreview() {
        const item = currentFiles[previewIndex];
        if (!item) return;

        modalFolder.textContent  = currentFolder === '__root__' ? 'Uncategorized' : (currentFolder || '');
        modalFName.textContent   = item.original_filename || '';
        modalOpenBtn.href        = item.url || '#';
        modalDocType.textContent = item.document_type || '';
        modalSize.textContent    = item.file_size ? formatBytes(item.file_size) : '';
        modalDate.textContent    = item.created_at || '';
        modalCounter.textContent = `${previewIndex + 1} / ${currentFiles.length}`;

        modalPrev.disabled = previewIndex <= 0;
        modalPrev.classList.toggle('opacity-30', previewIndex <= 0);
        modalNext.disabled = previewIndex >= currentFiles.length - 1;
        modalNext.classList.toggle('opacity-30', previewIndex >= currentFiles.length - 1);

        if (item.is_pdf && item.url) {
            modalBody.innerHTML = `<iframe src="${escHtml(item.url)}" class="w-full h-full min-h-[60vh] rounded" style="border:none;"></iframe>`;
        } else if (!item.is_pdf && item.url) {
            modalBody.innerHTML = `<img src="${escHtml(item.url)}" alt="${escHtml(item.original_filename || '')}"
                class="max-w-full max-h-[70vh] object-contain rounded shadow-sm"
                onerror="this.outerHTML='<p class=\'text-slate-400 text-sm p-8\'>Image could not be loaded.</p>'">`;
        } else {
            modalBody.innerHTML = `<p class="text-slate-400 text-sm p-8">No preview available.</p>`;
        }
    }

    function closePreview() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        modalBody.innerHTML = '';
    }

    modalClose.addEventListener('click', closePreview);
    modal.addEventListener('click', e => { if (e.target === modal) closePreview(); });
    modalPrev.addEventListener('click', () => { if (previewIndex > 0) { previewIndex--; renderPreview(); } });
    modalNext.addEventListener('click', () => { if (previewIndex < currentFiles.length - 1) { previewIndex++; renderPreview(); } });
    document.addEventListener('keydown', e => {
        if (!modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closePreview();
            if (e.key === 'ArrowLeft' && previewIndex > 0)  { previewIndex--; renderPreview(); }
            if (e.key === 'ArrowRight' && previewIndex < currentFiles.length - 1) { previewIndex++; renderPreview(); }
        }
    });

    // ── Search ──
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            if (view === 'folders') { loadFolders(); }
            else { filesPage = 1; loadFolderFiles(); }
        }, 400);
    });

    perPageSel.addEventListener('change', () => {
        filesPerPage = parseInt(perPageSel.value);
        filesPage    = 1;
        if (view === 'files') loadFolderFiles();
    });

    // ── Helpers ──
    function showLoading() {
        grid.classList.add('hidden');
        emptyEl.classList.add('hidden');
        paginationBar.classList.add('hidden');
        paginationBar.classList.remove('flex');
        loadingEl.classList.remove('hidden');
    }
    function hideLoading() { loadingEl.classList.add('hidden'); }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatBytes(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function showError(msg) {
        grid.innerHTML = `<div class="col-span-full text-center py-12 text-rose-500 text-sm">${escHtml(msg)}</div>`;
        grid.classList.remove('hidden');
    }

    // ── Boot ──
    loadFolders();
})();
</script>
@endsection
