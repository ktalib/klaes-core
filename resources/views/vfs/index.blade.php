@extends('layouts.app')

@section('page-title', 'Virtual File/Folder System (Folio)')

@push('styles')
    @include('vfs.assets.style')
@endpush

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="p-6 space-y-4">

        {{-- Stat tiles. Each one but the first is a filter. --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-bold tracking-wider text-gray-500">INDEXED FILES</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($stats['indexed']) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">across {{ $registries->count() }} registries</div>
            </div>

            {{-- Links to the SAME population it counts (any restriction, not just
                 litigation), and resets the scope: intersecting with a scope the user
                 set earlier is how this tile led to an empty screen. --}}
            <a href="{{ route('vfs.index', array_merge($filters, ['state' => \App\Services\Vfs\VfsFolioPresenter::STATE_ANY_HOLD, 'scope' => 'all'])) }}"
               class="bg-white border border-red-200 rounded-lg p-4 block hover:border-red-400 transition-colors">
                <div class="text-xs font-bold tracking-wider text-red-800">UNDER LEGAL HOLD</div>
                <div class="text-2xl font-bold mt-1 text-red-700">{{ number_format($stats['on_hold']) }}</div>
                <div class="text-xs text-red-700 mt-0.5">litigation, cancellation, revocation, withdrawal, surrender or closure</div>
            </a>

            <a href="{{ route('vfs.index', array_merge($filters, ['scope' => 'temporary', 'state' => null])) }}"
               class="bg-white border border-gray-200 rounded-lg p-4 block hover:border-gray-400 transition-colors"
               title="Clears any legal-status filter so the count matches what you see">
                <div class="text-xs font-bold tracking-wider text-gray-500">TEMPORARY FILES</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($stats['temporary']) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">main file missing</div>
            </a>

            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-bold tracking-wider text-gray-500">NO PAGES MAPPED</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($stats['unmapped']) }}</div>
                <div class="text-xs text-gray-500 mt-0.5">indexed but not scanned</div>
            </div>
        </div>

        {{-- Search and filters --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <form method="GET" action="{{ route('vfs.index') }}" class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1" style="min-width: 240px;">
                    <i data-lucide="search" class="h-4 w-4 text-gray-400 absolute" style="left: 11px; top: 11px;"></i>
                    <label for="vfs-q" class="sr-only">Search indexed files</label>
                    <input id="vfs-q" name="q" type="search" value="{{ $filters['q'] }}"
                           placeholder="Search by FileNo, holder, plot or PropID"
                           class="w-full border border-gray-300 rounded-md text-sm py-2 pr-3"
                           style="padding-left: 34px;">
                </div>

                <label for="vfs-registry" class="sr-only">Registry</label>
                <select id="vfs-registry" name="registry" class="border border-gray-300 rounded-md text-sm py-2 px-3">
                    <option value="">All registries</option>
                    @foreach($registries as $registry)
                        <option value="{{ $registry }}" @selected($filters['registry'] === $registry)>{{ $registry }}</option>
                    @endforeach
                </select>

                <label for="vfs-state" class="sr-only">Legal status</label>
                <select id="vfs-state" name="state" class="border border-gray-300 rounded-md text-sm py-2 px-3">
                    <option value="">Any legal status</option>
                    @foreach([\App\Services\Vfs\VfsFolioPresenter::STATE_ANY_HOLD => 'Any restriction', 'LITIGATION_HOLD' => 'Litigation', 'CANCELLED' => 'Cancelled', 'REVOKED' => 'Revoked', 'WITHDRAWN' => 'Withdrawn', 'SURRENDERED' => 'Surrendered', 'CLOSED' => 'Closed', 'AMENDED' => 'Amended'] as $key => $label)
                        <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="vfs-scope" class="sr-only">Which files</label>
                <select id="vfs-scope" name="scope" class="border border-gray-300 rounded-md text-sm py-2 px-3">
                    {{-- The default sends no scope: browsing shows files with scanned pages,
                         while a search covers every indexed file (no-page rows in red). --}}
                    @foreach(['' => 'With scanned pages', 'all' => 'All indexed files', 'temporary' => 'Temporary files', 'unmapped' => 'Not yet scanned'] as $key => $label)
                        <option value="{{ $key }}" @selected((string) ($filters['scope'] === 'mapped' ? '' : $filters['scope']) === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="bg-blue-700 text-white text-sm font-semibold rounded-md py-2 px-5">Search</button>

                @if($filters['q'] || $filters['registry'] || $filters['state'] || $filters['scope'])
                    <a href="{{ route('vfs.index') }}" class="text-sm text-gray-600 py-2 px-3">Clear</a>
                @endif
            </form>

            @php $activeScope = \App\Services\Vfs\VfsFolioPresenter::effectiveScope($filters); @endphp
            @if($filters['q'] && !$filters['scope'])
                <div class="mt-3 text-xs text-gray-600">
                    Searching all {{ number_format($stats['indexed']) }} indexed files.
                    <span class="font-semibold text-red-700">Rows in red have no pages in EDMS.</span>
                </div>
            @elseif($activeScope === 'mapped')
                {{-- Most indexed files have never been scanned, so an unfiltered register
                     would be mostly rows the explorer cannot open. Say which slice is on
                     screen rather than letting the count look like the whole register. --}}
                <div class="mt-3 text-xs text-gray-600">
                    Showing the {{ number_format($stats['indexed'] - $stats['unmapped']) }} files that have scanned pages.
                    <a href="{{ route('vfs.index', array_merge($filters, ['scope' => 'all'])) }}" class="font-semibold">Show all {{ number_format($stats['indexed']) }}</a>
                </div>
            @endif

        </div>

        {{-- Results --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-left">
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">PROP ID</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">FILE NUMBER</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">HOLDER &amp; PROPERTY</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">REGISTRY</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">PAGES</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">LEGAL STATUS</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($files as $row)
                        {{-- Red: no pages anywhere. Purple: no typed pages, but the pages are
                             still in the Blind Scanning folder. --}}
                        @php
                            $vfsBlindOnly = $row->vfs_pages === 0 && ($row->vfs_blind ?? 0) > 0;
                        @endphp
                        <tr class="border-b {{ $vfsBlindOnly ? 'bg-purple-50 border-purple-100 text-purple-900' : ($row->vfs_pages === 0 ? 'bg-red-50 border-red-100 text-red-900' : 'border-gray-100') }}"
                            @if($vfsBlindOnly) title="Pages are in the Blind Scanning folder, not yet page-typed"
                            @elseif($row->vfs_pages === 0) title="{{ $row->vfs_scans ? 'Scanned but no page has been typed' : 'No EDMS record: never scanned or uploaded' }}" @endif>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ $row->prop_id ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-sm {{ $row->vfs_state['rail'] ?? 'bg-gray-200' }}" style="width: 3px; height: 26px;"></span>
                                    <span>
                                        <span class="font-bold">{{ $row->vfs_display_number }}</span>
                                        {{-- Only when it carries information the line above does not. --}}
                                        @if($row->temp_file_no && $row->temp_file_no !== $row->vfs_display_number)
                                            <span class="block text-xs text-gray-500">temp {{ $row->temp_file_no }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $row->current_holder ?: $row->original_holder ?: $row->file_title ?: '—' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ collect([
                                        $row->plot_number ? 'Plot ' . $row->plot_number : null,
                                        $row->location ?: $row->district,
                                        $row->land_use_type,
                                    ])->filter()->implode(' · ') ?: '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block text-xs font-semibold px-2 py-1 rounded {{ $row->vfs_registry_badge['class'] }}">
                                    {{ $row->vfs_registry_badge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs {{ $vfsBlindOnly ? 'text-purple-700 font-bold' : ($row->vfs_pages === 0 ? 'text-red-700 font-bold' : 'text-gray-600') }}">
                                {{ $row->vfs_pages }}
                                @if(($row->vfs_blind ?? 0) > 0)
                                    <span class="block text-xs font-semibold text-purple-700">+{{ number_format($row->vfs_blind) }} in blind scan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if(($row->vfs_blind ?? 0) > 0)
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded bg-purple-100 text-purple-800 border border-purple-300 mb-1"
                                          title="{{ number_format($row->vfs_blind) }} {{ \Illuminate\Support\Str::plural('page', $row->vfs_blind) }} still in the Blind Scanning folder">
                                        IN BLIND SCAN
                                    </span>
                                @endif
                                @if($row->vfs_state)
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded {{ $row->vfs_state['chip'] }}">
                                        {{ strtoupper($row->vfs_state['label']) }}
                                    </span>
                                    @if(($row->vfs_state['other_active'] ?? 0) > 0)
                                        {{-- A summary badge must never hide a second active hold. --}}
                                        <span class="inline-block text-xs font-semibold px-2 py-1 rounded bg-gray-200 text-gray-700">
                                            +{{ $row->vfs_state['other_active'] }} more
                                        </span>
                                    @endif
                                @elseif($row->vfs_is_temporary)
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded bg-yellow-100 text-yellow-800 border border-yellow-300">TEMPORARY FILE</span>
                                @elseif($row->vfs_pages === 0 && !$vfsBlindOnly)
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded bg-red-100 text-red-700 border border-red-300">
                                        {{ $row->vfs_scans ? 'NOT PAGE-TYPED (' . $row->vfs_scans . ' ' . \Illuminate\Support\Str::plural('scan', $row->vfs_scans) . ')' : 'NO EDMS RECORD' }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{-- Always open: a file with no pages yet can still have linked
                                     files (lineage), blind-scan pages or KLAES-issued sheets. --}}
                                <a href="{{ route('vfs.show', $row->id) }}"
                                   @if($row->vfs_pages === 0) title="No typed pages yet — open for linked files, blind scans and status" @endif
                                   class="inline-block text-xs font-semibold rounded-md py-1 px-3 {{ $row->vfs_state && ($row->vfs_state['restricting'] ?? false) ? 'bg-blue-700 text-white' : 'border border-gray-300 text-gray-700' }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                {{-- "No files match" is FALSE whenever the scope is what
                                     hid them. The register defaults to scanned files only,
                                     which is 5% of the register, so an exact file number
                                     that exists can land here. Say which it is. --}}
                                @if(($missedByScope ?? 0) > 0)
                                    <i data-lucide="eye-off" class="h-7 w-7 mx-auto text-yellow-500"></i>
                                    <div class="mt-2 text-sm text-gray-800">
                                        Nothing here <span class="font-semibold">with scanned pages</span> —
                                        but {{ $missedByScope }} {{ \Illuminate\Support\Str::plural('file', $missedByScope) }}
                                        {{ $missedByScope === 1 ? 'matches' : 'match' }} across all indexed files.
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        This filter shows only files that have been scanned. The rest are indexed but have no pages yet.
                                    </div>
                                    <a href="{{ request()->fullUrlWithQuery(['scope' => 'all', 'page' => null]) }}"
                                       class="inline-flex items-center gap-1 mt-3 text-sm text-white bg-blue-700 font-semibold rounded-md px-3 py-1.5">
                                        <i data-lucide="search" class="h-4 w-4"></i>
                                        Show {{ $missedByScope === 1 ? 'it' : 'them' }}
                                    </a>
                                @else
                                    <i data-lucide="folder-search" class="h-7 w-7 mx-auto text-gray-300"></i>
                                    <div class="mt-2 text-sm">No indexed files match this search.</div>
                                    <a href="{{ route('vfs.index') }}" class="text-sm text-blue-700 font-semibold">Clear filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 bg-gray-50 px-4 py-2">
                {{ $files->links() }}
            </div>
        </div>

        {{-- Folders sitting only in Blind Scanning: the file number is not indexed, so
             there is no folio to open yet, but the search must still show they exist. --}}
        @if(($blindOnly ?? collect())->isNotEmpty())
            <div class="bg-white border border-purple-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2 bg-purple-50 border-b border-purple-200 flex items-center gap-2">
                    <i data-lucide="scan-line" class="h-4 w-4 text-purple-700"></i>
                    <span class="text-xs font-bold tracking-wider text-purple-900">ALSO IN BLIND SCANNING — NOT INDEXED YET</span>
                    <span class="text-xs text-purple-700">({{ $blindOnly->count() }}{{ $blindOnly->count() >= 20 ? '+' : '' }})</span>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-left">
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">S/N</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">FILE NUMBER</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">REGISTRY</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">PAGES</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">LAST SCANNED</th>
                            <th class="px-4 py-2 text-xs font-bold tracking-wider text-gray-500">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blindOnly as $blind)
                            <tr class="border-b border-purple-100 bg-purple-50 text-purple-900">
                                <td class="px-4 py-2 text-xs">{{ $loop->iteration }}</td>
                                <td class="px-4 py-2 font-bold">{{ $blind->file_number }}</td>
                                <td class="px-4 py-2 text-xs">{{ strtoupper((string) $blind->registry_type) ?: '—' }}</td>
                                <td class="px-4 py-2 text-xs font-semibold">{{ number_format($blind->pages) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $blind->last_at ? \Carbon\Carbon::parse($blind->last_at)->format('d M Y') : '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded bg-purple-100 text-purple-800 border border-purple-300"
                                          title="Pages are in the Blind Scanning folder; index the file to open its folio">IN BLIND SCAN · NOT INDEXED</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex gap-3">
            <i data-lucide="info" class="h-4 w-4 text-blue-700 flex-shrink-0" style="margin-top: 2px;"></i>
            <p class="text-xs text-gray-700 leading-relaxed m-0">
                <strong>Legal status is resolved per row, not on open.</strong> Staff see a hold before committing to a file, so nobody starts work on a frozen folio. Status shown here is advisory: it describes the legal position but does not yet block transactions elsewhere in KLAES.
            </p>
        </div>
    </div>
</div>
@endsection
