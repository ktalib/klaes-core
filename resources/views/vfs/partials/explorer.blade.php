{{--
    The whole Virtual Folder System view for one folio.

    Self-contained on purpose: this partial is what makes the explorer embeddable
    anywhere (Legal Search, File History View, the indexed-file action menu) via
    <x-vfs-explorer :file-indexing-id="$id" />. It takes a payload and renders; it
    queries nothing itself.
--}}

@php
    $sections = [
        'documents' => ['label' => 'Current Documents', 'icon' => 'folder', 'count' => $summary['page_count']],
        'lineage'   => ['label' => 'Linked Files', 'icon' => 'git-branch', 'count' => $lineage['total']],
        'timeline'  => ['label' => 'Status Timeline', 'icon' => 'clock', 'count' => count($timeline)],
        'sources'   => ['label' => 'Source Folders', 'icon' => 'server', 'count' => count($sources)],
    ];
@endphp

@include('vfs.partials.banner', ['status' => $status, 'summary' => $summary])

{{-- File header --}}
<div class="bg-white border-b border-gray-200 px-6 py-3 flex flex-wrap items-center gap-6">
    <div>
        <div class="text-lg font-bold">{{ $summary['display_number'] }}</div>
        <div class="text-xs text-gray-500 mt-0.5">
            {{ collect([$summary['file_title'], $summary['plot_no'] ? 'Plot ' . $summary['plot_no'] : null, $summary['location'], $summary['land_use']])->filter()->implode(' · ') ?: '—' }}
        </div>
    </div>

    <div class="flex gap-6 pl-6 border-l border-gray-200">
        <div>
            <div class="text-xs font-bold tracking-wider text-gray-400">REGISTRY</div>
            <div class="mt-1">
                <span class="inline-block text-xs font-semibold px-2 py-1 rounded {{ $summary['registry_badge']['class'] }}">
                    {{ $summary['registry_badge']['label'] }}
                </span>
            </div>
        </div>
        <div>
            <div class="text-xs font-bold tracking-wider text-gray-400">PROP ID</div>
            <div class="text-sm mt-0.5">{{ $summary['prop_id'] ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs font-bold tracking-wider text-gray-400">STATUS</div>
            {{-- Coloured by the state itself. It read green for "Decommissioned",
                 which is the colour of a healthy file. --}}
            <div class="text-sm mt-0.5 font-semibold {{ $summary['structural_class'] }}">{{ $summary['structural_status'] }}</div>
        </div>
    </div>

    <div class="flex-1"></div>

    <a href="{{ route('vfs.index') }}" class="text-xs font-semibold border border-gray-300 text-gray-700 rounded-md py-2 px-4">Back to register</a>
</div>

{{-- Temporary file explanation --}}
@if($summary['is_temporary'])
    <div class="bg-blue-50 border-b border-blue-200 px-6 py-3 flex gap-3">
        <i data-lucide="info" class="h-4 w-4 text-blue-700 flex-shrink-0" style="margin-top: 2px;"></i>
        <p class="text-xs text-blue-900 leading-relaxed m-0">
            <strong>Viewing temporary file {{ $summary['temp_file_no'] ?: $summary['display_number'] }}.</strong>
            The main file is recorded as missing. Pages below come from the temporary folder. When the main file is found, the mapping re-points and the temporary folder is archived — its scans are retained, never deleted.
        </p>
    </div>
@endif

{{-- Toolbar --}}
<div class="bg-white border-b border-gray-200 px-6 py-2 flex items-center gap-2">
    <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm">
        <a href="{{ route('vfs.index') }}" class="text-gray-600">Register</a>
        <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400"></i>
        <a href="{{ route('vfs.show', $file->id) }}" class="text-gray-600">{{ $summary['display_number'] }}</a>
        @if($section === 'documents' && $activeFolder)
            <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400"></i>
            <span class="font-semibold">{{ $activeFolder === '__unclassified' ? 'Unclassified' : $activeFolder }}</span>
        @elseif($section !== 'documents')
            <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400"></i>
            <span class="font-semibold">{{ $sections[$section]['label'] ?? '' }}</span>
        @endif
    </nav>
</div>

<div class="vfs-shell p-6 gap-4" style="align-items: flex-start;">

    {{-- Section nav --}}
    <div class="vfs-nav bg-white border border-gray-200 rounded-lg p-2">
        @foreach($sections as $key => $meta)
            <a href="{{ route('vfs.show', ['fileIndexing' => $file->id, 'section' => $key]) }}"
               class="flex items-center gap-2 py-2 px-3 rounded-md text-sm {{ $section === $key ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700' }}">
                <i data-lucide="{{ $meta['icon'] }}" class="h-4 w-4 {{ $section === $key ? 'text-blue-700' : 'text-gray-500' }}"></i>
                <span class="flex-1">{{ $meta['label'] }}</span>
                <span class="text-xs {{ $section === $key ? 'text-blue-700 font-semibold' : 'text-gray-400' }}">{{ $meta['count'] }}</span>
            </a>
        @endforeach

        {{-- The founding rule of the VFS, said in the reader's terms rather than the
             system's: the folders above are assembled from page_type at read time and
             exist nowhere on disk. Counts are live so the claim is checkable against
             the Source Folders tab rather than being a slogan. --}}
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="shield-check" class="h-4 w-4 text-blue-700 flex-shrink-0"></i>
                <span class="text-xs font-bold tracking-wider text-blue-900">NOTHING HERE IS MOVED OR COPIED</span>
            </div>
            <p class="text-xs text-blue-900 leading-relaxed m-0">
                These folders are a view, not real folders.
                @if(count($sources) === 1)
                    All {{ $summary['page_count'] }} {{ \Illuminate\Support\Str::plural('page', $summary['page_count']) }}
                    live in one EDMS folder and stay there.
                @elseif(count($sources) > 1)
                    All {{ $summary['page_count'] }} {{ \Illuminate\Support\Str::plural('page', $summary['page_count']) }}
                    live in {{ count($sources) }} EDMS folders and stay there.
                @else
                    Every tile points at a scan that stays where it is.
                @endif
                Browsing and opening never changes the file server.
            </p>
        </div>
    </div>

    {{-- Section body --}}
    <div class="flex-1" style="min-width: 0;">
        @if($section === 'lineage')
            @include('vfs.partials.lineage', ['lineage' => $lineage, 'summary' => $summary])
        @elseif($section === 'timeline')
            @include('vfs.partials.timeline', ['timeline' => $timeline])
        @elseif($section === 'sources')
            @include('vfs.partials.sources', ['sources' => $sources, 'summary' => $summary])
        @else
            @include('vfs.partials.documents', [
                'file' => $file,
                'folders' => $folders,
                'activeFolder' => $activeFolder,
                'documents' => $documents,
                'status' => $status,
            ])
        @endif
    </div>
</div>
