{{--
    Linked Files.

    Read from the file register (related_file_number) and the related_fileno column —
    the same sources Legal Search trusts — NOT from prop_id. Joining on prop_id pulled
    unrelated parcels into the panel, because thousands of file_indexings rows carry a
    migration row-ordinal instead of a real property id.

    The register's transaction_type is what lets the two groups below be separated:
    a same-property registry counterpart is not parcel ancestry, and showing them in
    one undifferentiated list is what made the old panel misleading.

    COLOUR RULE: the two groups are coloured apart on purpose. Blue reads as "the same
    parcel, another number"; purple reads as "this parcel changed". Every colour comes
    from the presenter (linkTypeBadge / registryBadge) so the same relationship never
    takes two different colours on one screen. Tailwind 2.2.19 defaults only — no
    slate/amber/emerald, which the CDN build does not ship.
--}}

@php
    $groups = [
        'same_property' => [
            // Title is decided per file below: claiming "other registry" when every
            // counterpart sits in the SAME registry is simply false, and it was.
            'title' => 'Same property — other registry',
            'blurb' => 'The same parcel indexed in another registry (KANGIS, MLPP). Not a parent or a child.',
            'icon'  => 'copy',
            'rail'  => 'border-blue-400',
            'chip'  => 'bg-blue-100 text-blue-700',
            'count' => 'bg-blue-100 text-blue-800',
        ],
        'parcel_history' => [
            'title' => 'Parcel history',
            'blurb' => 'Files this parcel was created from or gave rise to — subdivision, merger, change of purpose.',
            'icon'  => 'git-branch',
            'rail'  => 'border-purple-400',
            'chip'  => 'bg-purple-100 text-purple-700',
            'count' => 'bg-purple-100 text-purple-800',
        ],
        'unclassified' => [
            'title' => 'Linked — relationship not recorded',
            'blurb' => 'The register links these files but does not say how.',
            'icon'  => 'link',
            'rail'  => 'border-gray-300',
            'chip'  => 'bg-gray-100 text-gray-600',
            'count' => 'bg-gray-100 text-gray-700',
        ],
    ];
@endphp

<div class="space-y-4">

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="flex items-start gap-3 mb-4">
            <span class="inline-flex items-center justify-center rounded-md bg-blue-100 flex-shrink-0"
                  style="width: 34px; height: 34px;">
                <i data-lucide="link-2" class="h-4 w-4 text-blue-700"></i>
            </span>
            <div>
                <h2 class="text-sm font-bold m-0">Linked Files</h2>
                <p class="text-xs text-gray-500 m-0">
                    {{ $lineage['total'] }} {{ \Illuminate\Support\Str::plural('file', $lineage['total']) }}
                    linked to <span class="font-semibold text-gray-700">{{ $summary['display_number'] }}</span>
                    in the file register.
                </p>
            </div>
        </div>

        {{-- A cut list must say so; a partial family shown as complete is worse than
             no family at all. --}}
        @if($lineage['truncated'] ?? false)
            <div class="mb-4 px-3 py-2 rounded-md bg-yellow-50 border border-yellow-300 text-xs text-yellow-900 flex gap-2">
                <i data-lucide="alert-triangle" class="h-4 w-4 flex-shrink-0 text-yellow-700" style="margin-top: 1px;"></i>
                <span>This file has more linked files than can be listed here. Showing the first {{ \App\Services\Vfs\VfsFolioPresenter::maxRelated() }} — use Legal Search for the complete family.</span>
            </div>
        @endif

        @if($lineage['total'] === 0)
            <div class="text-xs text-gray-500 border border-dashed border-gray-300 rounded-md px-3 py-8 text-center">
                <i data-lucide="unlink" class="h-5 w-5 text-gray-300 mb-2" style="display: inline-block;"></i>
                <div>No linked files recorded for this file number.</div>
            </div>
        @endif

        @foreach($groups as $key => $meta)
            @continue(empty($lineage[$key]))

            @php
                // "Other registry" is a claim about the data, so only make it when the
                // data supports it. A Related File link inside one registry is still
                // the same property — it is just not in another registry, and saying
                // so wrongly is what made this panel look broken on COM-2026-322.
                if ($key === 'same_property') {
                    $elsewhere = collect($lineage[$key])
                        ->pluck('registry')
                        ->filter()
                        ->reject(fn ($r) => $r === ($summary['registry'] ?? null))
                        ->isNotEmpty();

                    if (!$elsewhere) {
                        $meta['title'] = 'Same property — related file';
                        $meta['blurb'] = 'The same parcel recorded under another file number in this registry. Not a parent or a child.';
                    }
                }
            @endphp

            {{-- The rail carries the group's colour down the whole block, so a long
                 list never loses which group it belongs to once the heading scrolls off. --}}
            <div class="mb-5 pl-3 border-l-2 {{ $meta['rail'] }}">
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center justify-center rounded {{ $meta['chip'] }} flex-shrink-0"
                          style="width: 22px; height: 22px;">
                        <i data-lucide="{{ $meta['icon'] }}" class="h-3 w-3"></i>
                    </span>
                    <span class="text-xs font-bold tracking-wider text-gray-700">{{ strtoupper($meta['title']) }}</span>
                    <span class="text-xs font-bold px-2 rounded-full {{ $meta['count'] }}">{{ count($lineage[$key]) }}</span>
                </div>
                <p class="text-xs text-gray-500 m-0 mb-3">{{ $meta['blurb'] }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($lineage[$key] as $link)
                        @php
                            $type = $link['type_badge'];
                            $reg  = $link['registry_badge'];
                        @endphp
                        <a href="{{ route('vfs.show', $link['id']) }}"
                           class="vfs-link-card block bg-white border border-gray-200 rounded-md px-3 py-2">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <span class="text-sm font-bold text-gray-900">{{ $link['file_number'] }}</span>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded whitespace-nowrap {{ $type['class'] }}">
                                    <i data-lucide="{{ $type['icon'] }}" class="h-3 w-3"></i>{{ $type['label'] }}
                                </span>
                            </div>

                            <div class="text-xs font-medium text-gray-700 truncate">{{ $link['holder'] ?: '—' }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $link['property'] ?: 'location not recorded' }}</div>
                            @if(!empty($link['land_use']))
                                <div class="text-xs text-gray-400 mb-1">{{ $link['land_use'] }}</div>
                            @else
                                <div class="mb-1"></div>
                            @endif

                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $reg['class'] }}">{{ $reg['label'] }}</span>

                                {{-- Scanned vs not scanned is the most useful thing to know
                                     before clicking, so it gets a colour rather than trailing
                                     grey text at the end of a line. --}}
                                @if($link['pages'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-800">
                                        <i data-lucide="file-text" class="h-3 w-3"></i>{{ $link['pages'] }} {{ \Illuminate\Support\Str::plural('page', $link['pages']) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500">
                                        <i data-lucide="file-x" class="h-3 w-3"></i>not scanned
                                    </span>
                                @endif
                            </div>

                            @if($link['comment'])
                                <div class="text-xs text-gray-500 mt-1 italic truncate">{{ $link['comment'] }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- A link to a file nobody has indexed is still a real link. Reported rather
             than dropped, because silently hiding it looks identical to "no relations". --}}
        @if(!empty($lineage['unindexed']))
            <div class="border-t border-gray-100 pt-3 mt-1">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="file-question" class="h-3 w-3 text-gray-400"></i>
                    <span class="text-xs font-bold tracking-wider text-gray-600">LINKED BUT NOT INDEXED</span>
                    <span class="text-xs font-bold px-2 rounded-full bg-gray-100 text-gray-700">{{ count($lineage['unindexed']) }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($lineage['unindexed'] as $link)
                        <span class="text-xs font-semibold border border-dashed border-gray-300 bg-gray-50 rounded px-2 py-1 text-gray-600"
                              title="{{ $link['type'] ?: 'relationship not recorded' }}">{{ $link['file_number'] }}</span>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-2 m-0">
                    These file numbers appear in the register but have no indexing record, so they cannot be opened here.
                </p>
            </div>
        @endif
    </div>

    {{-- Structural events recorded against this folio --}}
    @if($lineage['events']->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-lg p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center rounded bg-indigo-100 flex-shrink-0"
                      style="width: 22px; height: 22px;">
                    <i data-lucide="history" class="h-3 w-3 text-indigo-700"></i>
                </span>
                <span class="text-xs font-bold tracking-wider text-gray-700">RECORDED PARCEL EVENTS</span>
                <span class="text-xs font-bold px-2 rounded-full bg-indigo-100 text-indigo-800">{{ $lineage['events']->count() }}</span>
            </div>

            @foreach($lineage['events'] as $event)
                <div class="border border-gray-200 border-l-2 border-l-indigo-400 rounded-md px-3 py-2 mb-2 flex items-center gap-3">
                    <span class="text-xs font-semibold text-gray-500 flex-shrink-0" style="width: 80px;">
                        {{ ($event->approved_at ?: $event->created_at) ? \Illuminate\Support\Carbon::parse($event->approved_at ?: $event->created_at)->format('Y-m-d') : '—' }}
                    </span>
                    <span class="text-sm font-semibold text-gray-900">{{ $event->title_type }}</span>
                    <span class="flex-1 text-xs text-gray-500">{{ $event->see_fileno ? 'see ' . $event->see_fileno : '' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex gap-3">
        <i data-lucide="info" class="h-4 w-4 text-blue-700 flex-shrink-0" style="margin-top: 2px;"></i>
        <p class="text-xs text-blue-900 leading-relaxed m-0">
            <strong>Links come from the file register, not PropID.</strong>
            This panel reads <code>related_file_number</code> and the file's own related-file list, in both directions — the same sources Legal Search uses. PropID is shown for reference only; it is not used to find related files, because thousands of records share a PropID with an unrelated parcel.
        </p>
    </div>

    <div class="bg-yellow-50 border border-yellow-300 rounded-lg px-4 py-3 flex gap-3">
        <i data-lucide="alert-triangle" class="h-4 w-4 text-yellow-800 flex-shrink-0" style="margin-top: 2px;"></i>
        <p class="text-xs text-yellow-900 leading-relaxed m-0">
            <strong>Historical page versions are not available.</strong>
            This view shows which files are linked, using today's scan of each page. It does not reconstruct what a document looked like at an earlier stage — that needs a document-version table, which does not exist yet.
        </p>
    </div>
</div>
