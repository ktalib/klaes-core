@php
    use App\Models\Laas\LaasApplication;

    /**
     * $application — the application to draw.
     * $compact    — optional; a single slim progress bar instead of the full list.
     *
     * `draft` is dropped: an application the applicant is still filling has no
     * journey to show yet. `rejected` is off the main line entirely and gets its
     * own banner rather than a position on the tracker.
     */
    $steps = array_values(array_filter(
        LaasApplication::ORDER,
        fn ($s) => $s !== LaasApplication::STAGE_DRAFT
    ));

    $icons = [
        LaasApplication::STAGE_SUBMITTED               => 'send',
        LaasApplication::STAGE_DIRECTOR_APPROVED       => 'user-check',
        LaasApplication::STAGE_FILENO_ASSIGNED         => 'hash',
        LaasApplication::STAGE_LAND12_RAISED           => 'file-text',
        LaasApplication::STAGE_AT_CADASTRAL            => 'ruler',
        LaasApplication::STAGE_LAND12_COMPLETED        => 'map',
        LaasApplication::STAGE_RECOMMENDATION_PENDING  => 'hourglass',
        LaasApplication::STAGE_RECOMMENDATION_APPROVED => 'clipboard-check',
        LaasApplication::STAGE_ROFO_GENERATED          => 'file-check-2',
        LaasApplication::STAGE_ROFO_SIGNED             => 'stamp',
    ];

    $currentRank = LaasApplication::rank($application->stage);
    $isRejected  = $application->stage === LaasApplication::STAGE_REJECTED;
    $done        = $isRejected ? 0 : max($currentRank, 0);
    $percent     = (int) round($done / count($steps) * 100);
@endphp

@if($isRejected)
    <div class="flex items-start gap-3 rounded-xl border p-4"
         style="border-color: var(--danger); background: rgba(159,18,57,.07);">
        <i data-lucide="x-circle" class="mt-0.5 h-5 w-5 flex-shrink-0" style="color: var(--danger);"></i>
        <div>
            <p class="text-sm font-semibold" style="color: var(--ink);">This application was not approved</p>
            @if($application->rejection_reason)
                <p class="mt-1 text-sm" style="color: var(--ink-soft);">{{ $application->rejection_reason }}</p>
            @endif
        </div>
    </div>
@elseif(!empty($compact))
    <div>
        <div class="mb-1.5 flex items-center justify-between text-xs">
            <span class="font-medium text-[var(--ink)] dark:text-[var(--ink-soft)]">{{ LaasApplication::label($application->stage) }}</span>
            <span class="text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $percent }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-[var(--border)] dark:bg-[var(--brand-tint)]">
            <div class="h-full rounded-full bg-[var(--brand)] transition-all" style="width: {{ $percent }}%"></div>
        </div>
    </div>
@else
    <ol class="space-y-0">
        @foreach($steps as $i => $step)
            @php
                $rank      = LaasApplication::rank($step);
                $isDone    = $rank < $currentRank;
                $isCurrent = $rank === $currentRank;
                $isLast    = $i === count($steps) - 1;
            @endphp
            <li class="flex gap-4">
                <!-- Rail -->
                <div class="flex flex-col items-center">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 transition
                        @if($isDone) border-[var(--brand)] bg-[var(--brand)] text-[var(--on-brand)]
                        @elseif($isCurrent) border-[var(--brand)] bg-[var(--surface-card)] text-[var(--brand)]
                        @else border-[var(--border)] bg-[var(--surface-card)] text-[var(--ink-faint)] dark:border-[var(--border)] dark:bg-[var(--surface-card)] dark:text-[var(--ink-faint)] @endif">
                        <i data-lucide="{{ $isDone ? 'check' : ($icons[$step] ?? 'circle') }}" class="h-4 w-4"></i>
                    </div>
                    @unless($isLast)
                        <div class="w-0.5 flex-1 {{ $isDone ? 'bg-[var(--brand)]' : 'bg-[var(--border)] dark:bg-[var(--brand-tint)]' }}"></div>
                    @endunless
                </div>

                <!-- Label -->
                <div class="{{ $isLast ? 'pb-0' : 'pb-6' }} pt-1">
                    <p class="text-sm font-semibold
                        @if($isCurrent) text-[var(--brand)]
                        @elseif($isDone) text-[var(--ink)] dark:text-[var(--ink)]
                        @else text-[var(--ink-faint)] dark:text-[var(--ink-faint)] @endif">
                        {{ LaasApplication::label($step) }}
                        @if($isCurrent)
                            <span class="ml-2 rounded-full bg-[var(--brand-tint)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--brand)]">Current</span>
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
