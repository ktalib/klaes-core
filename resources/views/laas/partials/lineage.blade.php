{{--
    Where this file sits in its parcel's history (LaasLineageService::forApplication).
    A related file on the holder's own account links through; any other shows its
    number and property facts only, never another holder's name or documents.
--}}
@php
    $showRelated = function (array $rows, int $limit = 20) {
        return [array_slice($rows, 0, $limit), max(0, count($rows) - $limit)];
    };
    [$parentRows, $parentMore] = $showRelated($lineage['parents'] ?? []);
    [$successorRows, $successorMore] = $showRelated($lineage['successors'] ?? []);
@endphp

@if(!empty($lineage['parents']) || !empty($lineage['successors']))
    <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
        <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">File history</h2>

        @foreach([
            ['title' => 'Came from', 'icon' => 'arrow-up-left', 'rows' => $parentRows, 'more' => $parentMore],
            ['title' => ($lineage['event'] ? $lineage['event'] . ': became' : 'Became'), 'icon' => 'arrow-down-right', 'rows' => $successorRows, 'more' => $successorMore],
        ] as $group)
            @if(!empty($group['rows']))
                <p class="mb-2 mt-4 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[var(--ink-faint)] first:mt-0">
                    <i data-lucide="{{ $group['icon'] }}" class="h-3.5 w-3.5"></i> {{ $group['title'] }}
                </p>
                <ul class="space-y-2">
                    @foreach($group['rows'] as $row)
                        <li class="rounded-lg border border-[var(--border)] px-4 py-2.5 dark:border-[var(--border)]">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                @if($row['on_this_account'])
                                    <a href="{{ route('laas.application.show', $row['reference_no']) }}"
                                       class="font-mono text-sm font-semibold text-[var(--brand)] hover:underline">{{ $row['file_number'] }}</a>
                                @else
                                    <span class="font-mono text-sm font-semibold text-[var(--ink)] dark:text-[var(--ink)]">{{ $row['file_number'] }}</span>
                                @endif
                                @if($row['on_this_account'])
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-[var(--brand)]">On your account</span>
                                @endif
                            </div>
                            {{-- Property order: Plot No., District, LGA, Location --}}
                            <p class="mt-1 text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
                                {{ collect([
                                    $row['plot_no'] ? 'Plot ' . $row['plot_no'] : null,
                                    $row['district'],
                                    $row['lga'],
                                    $row['location'],
                                ])->filter()->implode(' · ') ?: '—' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
                @if($group['more'] > 0)
                    <p class="mt-2 text-xs text-[var(--ink-soft)]">and {{ $group['more'] }} more.</p>
                @endif
            @endif
        @endforeach

        <p class="mt-5 text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            The full record of transactions on a file is in a Legal Search report.
            <a href="{{ route('ols.landing') }}" class="font-semibold text-[var(--brand)] hover:underline">Request a Legal Search</a>.
        </p>
    </div>
@endif
