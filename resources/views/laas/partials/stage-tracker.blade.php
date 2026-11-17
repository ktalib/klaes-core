@php
    use App\Models\Laas\LaasApplication;

    /**
     * $application — the application to draw.
     * $pipeline    — optional; LaasFilePipeline result for it (worked out here when not passed).
     * $compact     — optional; a single slim progress bar instead of the full list.
     *
     * The department-by-department pipeline from the "LAAS Portal Flow" document (user, 2026-11-16),
     * read live from the records by LaasFilePipeline. `rejected` is off the line entirely and gets
     * its own banner.
     */
    $isRejected = $application->stage === LaasApplication::STAGE_REJECTED;
    $pipeline   = $pipeline ?? ($isRejected ? null : app(\App\Services\Laas\LaasFilePipeline::class)->forApplication($application));
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
        <div class="mb-1.5 flex items-center justify-between gap-2 text-xs">
            <span class="font-medium text-[var(--ink)] dark:text-[var(--ink-soft)]">
                {{ $pipeline['complete'] ? 'All stages completed' : ($pipeline['current'] ?? LaasApplication::label($application->stage)) }}
            </span>
            <span class="text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">{{ $pipeline['percent'] }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-[var(--border)] dark:bg-[var(--brand-tint)]">
            <div class="h-full rounded-full bg-[var(--brand)] transition-all" style="width: {{ $pipeline['percent'] }}%"></div>
        </div>
    </div>
@else
    {{-- Each department folds up; the one holding the current step opens by default (user, 2026-11-16:
         "the list is too long ... collapse it based on the grouping"). --}}
    <style>
        .laas-stage-group > summary { list-style: none; cursor: pointer; }
        .laas-stage-group > summary::-webkit-details-marker { display: none; }
        .laas-stage-group[open] > summary .laas-stage-chev { transform: rotate(180deg); }
    </style>
    <div class="space-y-1">
        @foreach($pipeline['groups'] as $group)
            @if($group['separate'])
                <div class="border-t border-dashed border-[var(--border)] pt-3 mt-2">
                    <p class="text-[11px] text-[var(--ink-faint)]">Only if Applicant applies for Recertification</p>
                </div>
            @endif
            @php
                $stepsDone = collect($group['steps'])->whereIn('state', ['done', 'passed'])->count();
            @endphp
            <details class="laas-stage-group" @if($group['state'] === 'current') open @endif>
                <summary class="flex items-center gap-2 rounded-lg py-1.5 text-[11px] font-extrabold uppercase tracking-widest
                    @if($group['state'] === 'current') text-[var(--brand)]
                    @elseif($group['state'] === 'done') text-[var(--ink-soft)]
                    @else text-[var(--ink-faint)] @endif">
                    <span>{{ $group['dept'] }}{{ $group['title'] ? ' · ' . $group['title'] : '' }}</span>
                    @if($group['state'] === 'done')<i data-lucide="check" class="h-3 w-3"></i>@endif
                    <span class="ml-auto font-semibold normal-case tracking-normal text-[var(--ink-faint)]">{{ $stepsDone }}/{{ count($group['steps']) }}</span>
                    <i data-lucide="chevron-down" class="laas-stage-chev h-3.5 w-3.5 transition-transform"></i>
                </summary>
                <ol class="space-y-0 pb-3 pt-1">
                    @foreach($group['steps'] as $i => $step)
                        @php
                            $isDone    = $step['state'] === 'done';
                            $isPassed  = $step['state'] === 'passed';
                            $isCurrent = $step['state'] === 'current';
                            $isLast    = $i === count($group['steps']) - 1;
                        @endphp
                        <li class="flex gap-3 {{ $step['indent'] ? 'pl-5' : '' }}">
                            <!-- Rail -->
                            <div class="flex flex-col items-center">
                                <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 transition
                                    @if($isDone) border-[var(--brand)] bg-[var(--brand)] text-[var(--on-brand)]
                                    @elseif($isPassed) border-[var(--brand-line)] bg-[var(--surface-card)] text-[var(--brand)]
                                    @elseif($isCurrent) border-[var(--brand)] bg-[var(--surface-card)] text-[var(--brand)]
                                    @else border-[var(--border)] bg-[var(--surface-card)] text-[var(--ink-faint)] @endif">
                                    <i data-lucide="{{ $isDone || $isPassed ? 'check' : ($isCurrent ? 'loader' : 'circle') }}" class="h-3 w-3"></i>
                                </div>
                                @unless($isLast)
                                    <div class="w-0.5 flex-1 {{ $isDone || $isPassed ? 'bg-[var(--brand)]' : 'bg-[var(--border)] dark:bg-[var(--brand-tint)]' }}" style="min-height: 0.75rem;"></div>
                                @endunless
                            </div>

                            <!-- Label -->
                            <div class="{{ $isLast ? 'pb-0' : 'pb-3' }} pt-0.5">
                                <p class="text-sm
                                    @if($isCurrent) font-semibold text-[var(--brand)]
                                    @elseif($isDone) font-semibold text-[var(--ink)]
                                    @elseif($isPassed) text-[var(--ink-soft)]
                                    @else text-[var(--ink-faint)] @endif">
                                    {{ $step['label'] }}
                                    @if($isCurrent)
                                        <span class="ml-2 rounded-full bg-[var(--brand-tint)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--brand)]">Current</span>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endforeach
    </div>
@endif
