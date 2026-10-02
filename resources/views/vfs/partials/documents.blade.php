{{-- Current Documents: virtual folders over page_type, then the tile grid. --}}

<div class="space-y-4">

    {{-- Folder cards --}}
    <div>
        <div class="text-xs font-bold tracking-wider text-gray-500 mb-2">FOLDERS — grouped by page type</div>

        @if(empty($folders))
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
                <i data-lucide="folder" class="h-7 w-7 mx-auto text-gray-300"></i>
                <div class="mt-2 text-sm text-gray-600">No documents mapped to this folio.</div>
                <div class="text-xs text-gray-500 mt-1">The file is indexed but has no scanned pages.</div>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($folders as $folder)
                    @php $isActive = $activeFolder === $folder['key']; @endphp
                    <a href="{{ route('vfs.show', ['fileIndexing' => $file->id, 'folder' => $folder['key']]) }}"
                       class="rounded-lg p-3 flex items-center gap-3 {{ $isActive ? 'bg-blue-50 border-2 border-blue-600' : 'bg-white border border-gray-200' }}">
                        <i data-lucide="folder"
                           class="h-7 w-7 flex-shrink-0 {{ $folder['classified'] ? 'text-yellow-600' : 'text-gray-400' }}"></i>
                        <span style="min-width: 0;">
                            <span class="block text-sm font-semibold {{ $isActive ? 'text-blue-900' : 'text-gray-900' }}">{{ $folder['label'] }}</span>
                            <span class="block text-xs {{ $isActive ? 'text-blue-700' : 'text-gray-500' }}">{{ $folder['count'] }} {{ \Illuminate\Support\Str::plural('page', $folder['count']) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tile grid --}}
    @if($documents->total() > 0)
        <div>
            <div class="flex items-baseline justify-between mb-2">
                <div class="text-xs font-bold tracking-wider text-gray-500">
                    DOCUMENTS IN {{ strtoupper($activeFolder === '__unclassified' ? 'Unclassified' : ($activeFolder ?: 'this folio')) }}
                </div>
                <div class="text-xs text-gray-400">sorted by folio sequence</div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 {{ ($status['watermark'] ?? null) ? 'vfs-watermark' : '' }}"
                 @if($status['watermark'] ?? null) data-watermark="{{ $status['watermark'] }}" @endif>

                @foreach($documents as $doc)
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="vfs-thumb border-b border-gray-200 relative">
                            @if($doc['is_unavailable'])
                                {{-- The mapping is KEPT: a page whose source cannot be read is
                                     reported, never silently dropped from the folder. --}}
                                <div class="text-center px-2">
                                    <i data-lucide="alert-triangle" class="h-6 w-6 text-gray-400 mx-auto"></i>
                                    <div class="text-xs text-gray-500 mt-1">Source unavailable</div>
                                </div>
                            @elseif($doc['thumbnail_url'])
                                <img src="{{ $doc['thumbnail_url'] }}" alt="{{ $doc['label'] }}" loading="lazy">
                            @else
                                <div class="text-center px-2">
                                    <i data-lucide="file-text" class="h-6 w-6 text-gray-400 mx-auto"></i>
                                    <div class="text-xs text-gray-500 mt-1">{{ strtoupper($doc['extension'] ?: 'file') }}</div>
                                </div>
                            @endif

                            @if($doc['media_type'] === 'pdf')
                                <span class="absolute text-xs font-bold bg-gray-900 text-white px-2 py-0.5 rounded" style="bottom: 6px; left: 6px;">PDF</span>
                            @endif
                        </div>

                        <div class="p-2">
                            <div class="vfs-tile-label text-xs font-semibold text-gray-900">{{ $doc['label'] }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ collect([
                                    $doc['page_number'] ? 'folio ' . $doc['page_number'] : null,
                                    strtoupper($doc['extension'] ?: ''),
                                ])->filter()->implode(' · ') }}
                            </div>

                            @if($doc['is_unavailable'])
                                <div class="text-xs text-gray-500 mt-1">Scan {{ $doc['scanning_id'] ?: '—' }}</div>
                            @else
                                <button type="button"
                                        data-vfs-open-viewer
                                        data-page-id="{{ $doc['id'] }}"
                                        class="mt-2 w-full text-xs font-semibold border border-gray-300 text-gray-700 rounded-md py-1">
                                    Open
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    {{ $documents->count() }} of {{ $documents->total() }} documents in this folder · {{ $file->pagetypings()->count() }} mapped across the folio
                </div>
                <div>{{ $documents->appends(['folder' => $activeFolder])->links() }}</div>
            </div>
        </div>
    @elseif(!empty($folders))
        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
            <i data-lucide="folder-open" class="h-7 w-7 mx-auto text-gray-300"></i>
            <div class="mt-2 text-sm text-gray-600">No documents in this folder.</div>
        </div>
    @endif
</div>
