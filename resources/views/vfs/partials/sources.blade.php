{{--
    Source Physical Folders.

    One folio is never equal to one directory: a file born of a subdivision inherits
    its parent's folder by pointer, and a reconciled temporary file keeps its own.
    These are derived from where the pages actually resolve on disk.
--}}

<div class="space-y-4">

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <h2 class="text-sm font-bold m-0 mb-1">Source Physical Folders</h2>
        <p class="text-xs text-gray-500 m-0 mb-4">The real EDMS directories this folio draws from. Read-only — nothing here is moved or renamed by the explorer.</p>

        @forelse($sources as $source)
            <div class="border border-gray-200 rounded-lg px-4 py-3 mb-2 flex items-center gap-4">
                <i data-lucide="folder" class="h-8 w-8 flex-shrink-0 {{ $source['is_temporary'] ? 'text-gray-400' : 'text-yellow-600' }}"></i>

                <div class="flex-1" style="min-width: 0;">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold">{{ $source['label'] }}</span>

                        @if($source['is_temporary'])
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-gray-200 text-gray-700">TEMPORARY</span>
                        @endif

                        @if($source['label'] !== $summary['file_number'])
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600">INHERITED</span>
                        @else
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600">PRIMARY</span>
                        @endif
                    </div>

                    <div class="text-xs text-gray-600 mt-1" style="font-family: ui-monospace, monospace; word-break: break-all;">{{ $source['path'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $source['pages'] }} {{ \Illuminate\Support\Str::plural('page', $source['pages']) }} mapped · {{ $source['registry'] ?: 'registry unknown' }}
                    </div>
                </div>
            </div>
        @empty
            <div class="text-xs text-gray-500 border border-dashed border-gray-300 rounded-md px-3 py-6 text-center">
                No physical source resolved. Either no pages are mapped, or their stored paths do not resolve on this server.
            </div>
        @endforelse
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex gap-3">
        <i data-lucide="info" class="h-4 w-4 text-blue-700 flex-shrink-0" style="margin-top: 2px;"></i>
        <p class="text-xs text-blue-900 leading-relaxed m-0">
            Paths are resolved through the shared EDMS path resolver, which walks every historical folder layout so a stale stored path still renders. They are shown for reference only — the browser is never handed a raw server path it could request directly.
        </p>
    </div>
</div>
