@extends('layouts.app')

@section('page-title', 'Virtual File/Folder System (Folio) — ' . ($file->file_number ?: $file->temp_file_no))

@push('styles')
    @include('vfs.assets.style')
@endpush

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    @include('vfs.partials.explorer', [
        'file' => $file,
        'summary' => $summary,
        'status' => $status,
        'folders' => $folders,
        'activeFolder' => $activeFolder,
        'documents' => $documents,
        'section' => $section,
        'lineage' => $lineage,
        'timeline' => $timeline,
        'sources' => $sources,
        'pagesUrl' => $pagesUrl,
    ])
</div>
@endsection

@push('scripts')
    {{-- The existing archive viewer, reused unchanged. window.openDocumentViewer is
         exported by filearchive/assets/js.blade.php and already reads the payload
         shape that /filearchive/document-pages/{id} returns. --}}
    @include('filearchive.partials.document_viewer_modal')
    @include('filearchive.assets.js')

    {{-- The Virtual Folder System is READ-ONLY. It assembles a view over the EDMS and
         never moves, renames or replaces a scan, so the viewer's editing toolbar has
         no business appearing here — the archive is where a page gets changed.

         Hidden with CSS rather than by editing the shared modal, because the same
         partial serves /filearchive where those controls must stay. This scopes the
         removal to this page only.

         The file-to-file pager goes too: it drives off a file LIST the archive builds,
         which the VFS does not have, so it renders a dead "File 0 of 0" with two
         buttons that do nothing. --}}
    <style>
        #master-edit-toggle,
        #master-edit-actions,
        #viewer-previous-file,
        #viewer-next-file,
        #viewer-file-position {
            display: none !important;
        }
    </style>

    <div id="vfs-sheet-viewer" class="vfs-sheet-viewer" role="dialog" aria-modal="true" aria-labelledby="vfs-sheet-viewer-title">
        <div class="vfs-sheet-viewer-panel shadow-lg">
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200">
                <div>
                    <div id="vfs-sheet-viewer-title" class="text-sm font-semibold text-gray-900">Document</div>
                    <div class="text-xs text-gray-500">{{ $file->file_number }} · issued by KLAES · read-only</div>
                </div>
                <button type="button" id="vfs-sheet-viewer-close" class="p-1 rounded hover:bg-gray-100" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5 text-gray-600"></i>
                </button>
            </div>
            <iframe id="vfs-sheet-viewer-frame" title="Document" src="about:blank"></iframe>
        </div>
    </div>

    <script>
    (function () {
        // Belt and braces: the toolbar is hidden above, but if edit mode were ever
        // left on by another screen in the same session, this puts it back off so the
        // editing actions cannot be revealed on a read-only page.
        if (typeof window.setMasterEditMode === 'function') {
            window.setMasterEditMode(false);
        }

        document.querySelectorAll('[data-vfs-open-viewer]').forEach(function (el) {
            el.addEventListener('click', function () {
                if (typeof window.openDocumentViewer !== 'function') {
                    console.warn('[VFS] document viewer not loaded');
                    return;
                }
                window.openDocumentViewer(@json($pagesUrl), false, {
                    fileNumber: @json($file->file_number),
                    startPageId: el.getAttribute('data-page-id') || null
                });
            });
        });

        // Commissioning sheet / LGA confirmation sheet: drawn by KLAES, not scanned,
        // so they open in their own read-only frame rather than the page viewer.
        const sheetViewer = document.getElementById('vfs-sheet-viewer');
        const sheetFrame = document.getElementById('vfs-sheet-viewer-frame');
        const closeSheet = function () {
            sheetViewer.classList.remove('is-open');
            sheetFrame.src = 'about:blank';
        };

        document.querySelectorAll('[data-vfs-open-system]').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('vfs-sheet-viewer-title').textContent = el.getAttribute('data-title') || 'Document';
                sheetFrame.src = el.getAttribute('data-url');
                sheetViewer.classList.add('is-open');
            });
        });
        document.getElementById('vfs-sheet-viewer-close').addEventListener('click', closeSheet);
        sheetViewer.addEventListener('click', function (e) { if (e.target === sheetViewer) closeSheet(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sheetViewer.classList.contains('is-open')) closeSheet();
        });

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    })();
    </script>
@endpush
