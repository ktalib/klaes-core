{{--
    Embeddable Virtual Folder System explorer.

        <x-vfs-explorer :file-indexing-id="$id" />
        <x-vfs-explorer :file-indexing-id="$id" section="timeline" />

    Drops the explorer into any screen — Legal Search, File History View, the
    indexed-file action menu — without duplicating markup. The host page must also
    include vfs.assets.style once, and, if the full page viewer is wanted,
    filearchive.partials.document_viewer_modal + filearchive.assets.js.

    Read-only, like everything else in this module.
--}}

@props([
    'fileIndexingId',
    'section' => 'documents',
    'folder' => null,
])

@php
    $vfsFile = \App\Models\FileIndexing::find($fileIndexingId);
@endphp

@if(!$vfsFile)
    <div class="bg-white border border-gray-200 rounded-lg px-4 py-6 text-center text-sm text-gray-500">
        File record not found.
    </div>
@else
    @php
        $vfsPresenter = app(\App\Services\Vfs\VfsFolioPresenter::class);
        $vfsFolders = $vfsPresenter->folders($vfsFile);
        $vfsFolder = $folder ?? ($vfsFolders[0]['key'] ?? null);
    @endphp

    @include('vfs.partials.explorer', [
        'file' => $vfsFile,
        'summary' => $vfsPresenter->summary($vfsFile),
        'status' => $vfsPresenter->status($vfsFile),
        'folders' => $vfsFolders,
        'activeFolder' => $vfsFolder,
        'documents' => $vfsPresenter->documents($vfsFile, $vfsFolder),
        'section' => $section,
        'lineage' => $vfsPresenter->lineage($vfsFile),
        'timeline' => $vfsPresenter->statusTimeline($vfsFile),
        'sources' => $vfsPresenter->physicalSources($vfsFile),
        'pagesUrl' => route('filearchive.document-pages', $vfsFile->id),
    ])
@endif
