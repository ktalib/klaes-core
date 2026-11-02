{{--
    The file's Cadastral & Survey progress (LaasCadastralProgress, user 2026-11-02).
    $progress  — LaasCadastralProgress::forFile() result (null: nothing drawn).
    $compact   — optional; one line for the dashboard card.
--}}
@if(!empty($progress))
    @if(!empty($compact))
        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-[var(--ink-soft)]">
            <i data-lucide="ruler" class="h-3 w-3"></i>
            @if($progress['completed'])
                Cadastral: completed
            @elseif($progress['returned'])
                Cadastral: returned, the office will contact you
            @else
                Cadastral: {{ $progress['current'] ?? 'in progress' }}
            @endif
        </p>
    @else
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6">
            <h2 class="mb-1 text-sm font-extrabold uppercase tracking-widest text-[var(--ink-soft)]">Cadastral &amp; Survey</h2>
            <p class="mb-5 text-xs text-[var(--ink-faint)]">{{ $progress['workflow'] }} · where your file is in the Cadastral Department, including the survey of the land</p>

            @if($progress['returned'])
                <div class="mb-4 flex items-start gap-3 rounded-xl border p-3" style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                    <i data-lucide="undo-2" class="mt-0.5 h-4 w-4 flex-shrink-0" style="color: var(--danger);"></i>
                    <p class="text-sm text-[var(--ink)]">Your file has been returned by the Cadastral Department. The office will contact you.</p>
                </div>
            @elseif($progress['completed'])
                <div class="mb-4 flex items-start gap-3 rounded-xl border border-[var(--brand-line)] bg-[var(--brand-tint)] p-3">
                    <i data-lucide="badge-check" class="mt-0.5 h-4 w-4 flex-shrink-0 text-[var(--brand)]"></i>
                    <p class="text-sm text-[var(--ink)]">Cadastral work on your file is complete{{ $progress['closed_at'] ? ' (' . $progress['closed_at']->format('j M Y') . ')' : '' }}.</p>
                </div>
            @endif

            <ol class="space-y-0">
                @foreach($progress['phases'] as $i => $ph)
                    @php
                        $isDone = $ph['state'] === 'done';
                        $isCurrent = $ph['state'] === 'current' && !$progress['returned'];
                        $isLast = $i === count($progress['phases']) - 1;
                    @endphp
                    <li class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold
                                @if($isDone) border-[var(--brand)] bg-[var(--brand)] text-[var(--on-brand)]
                                @elseif($isCurrent) border-[var(--brand)] bg-[var(--surface-card)] text-[var(--brand)]
                                @else border-[var(--border)] bg-[var(--surface-card)] text-[var(--ink-faint)] @endif">
                                @if($isDone)<i data-lucide="check" class="h-3.5 w-3.5"></i>@else{{ $ph['n'] }}@endif
                            </div>
                            @unless($isLast)
                                <div class="w-0.5 flex-1 {{ $isDone ? 'bg-[var(--brand)]' : 'bg-[var(--border)]' }}"></div>
                            @endunless
                        </div>
                        <div class="{{ $isLast ? 'pb-0' : 'pb-5' }} pt-0.5">
                            <p class="text-sm font-semibold
                                @if($isCurrent) text-[var(--brand)]
                                @elseif($isDone) text-[var(--ink)]
                                @else text-[var(--ink-faint)] @endif">
                                {{ $ph['label'] }}
                                @if($isCurrent)
                                    <span class="ml-2 rounded-full bg-[var(--brand-tint)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--brand)]">Current</span>
                                @endif
                            </p>
                            @if($ph['at'])
                                <p class="text-xs text-[var(--ink-faint)]">{{ $isCurrent ? 'Since' : 'Reached' }} {{ \Illuminate\Support\Carbon::parse($ph['at'])->format('j M Y') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
@endif
