@extends('layouts.app')

@section('page-title', 'Virtual Folder System — ' . ($file->file_number ?: $file->temp_file_no))

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

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    })();
    </script>
@endpush
