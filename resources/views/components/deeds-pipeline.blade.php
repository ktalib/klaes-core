{{--
  Deeds pipeline strip — Valuation -> Consent -> Print -> Registration.

  Shows which stages a file has cleared and which are still outstanding. Used on
  the Valuation, Consent and Instrument Registration screens so an officer sees
  the same picture wherever they are standing in the pipeline.

  Every stage comes from App\Services\DeedsPipelineStatus, never from the page's
  own queries, so the strip and the gates can never disagree.

  Props
    stages   the array from DeedsPipelineStatus::forFile()
    current  which stage this screen is: valuation | consent | print | registration
    file     the file number the strip describes, shown in the header
    compact  true renders the slim variant used inside a table row or a modal

  A stage reads as:
    done      cleared
    current   the stage this screen is for, not yet done
    skipped   not done, but a LATER stage is — the pipeline was taken out of
              order, which is the thing worth surfacing
    pending   not done, nothing after it done either
--}}
@props([
    'stages' => [],
    'current' => null,
    'file' => null,
    'compact' => false,
])

@php
    $order = ['valuation', 'consent', 'print', 'registration'];

    $labels = [
        'valuation' => 'Valuation',
        'consent' => 'Consent',
        'print' => 'Print Consent Letter',
        'registration' => 'Registration',
    ];

    // A stage is "skipped" only when something after it is already done — that
    // is what separates work taken out of order from work simply not reached.
    $laterDone = [];
    $seen = false;
    foreach (array_reverse($order) as $key) {
        $laterDone[$key] = $seen;
        $seen = $seen || (bool) ($stages[$key]['done'] ?? false);
    }

    $state = function (string $key) use ($stages, $current, $laterDone): string {
        if (($stages[$key]['done'] ?? false)) {
            return 'done';
        }
        if ($laterDone[$key] ?? false) {
            return 'skipped';
        }
        return $key === $current ? 'current' : 'pending';
    };

    $tone = [
        'done' => ['dot' => 'bg-emerald-500 border-emerald-500 text-white', 'text' => 'text-emerald-700', 'bar' => 'bg-emerald-500'],
        'skipped' => ['dot' => 'bg-amber-500 border-amber-500 text-white', 'text' => 'text-amber-700', 'bar' => 'bg-slate-200'],
        'current' => ['dot' => 'bg-white border-teal-500 text-teal-600 ring-4 ring-teal-100', 'text' => 'text-teal-700', 'bar' => 'bg-slate-200'],
        'pending' => ['dot' => 'bg-white border-slate-300 text-slate-400', 'text' => 'text-slate-500', 'bar' => 'bg-slate-200'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'deeds-pipeline rounded-lg border border-slate-200 bg-white ' . ($compact ? 'p-3' : 'p-4')]) }}>

    @unless ($compact)
        <div class="flex items-baseline justify-between mb-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Deeds Workflow</span>
            @if ($file)
                <span class="text-xs font-semibold text-slate-700">{{ $file }}</span>
            @endif
        </div>
    @endunless

    <div class="flex items-start">
        @foreach ($order as $i => $key)
            @php
                $s = $state($key);
                $t = $tone[$s];
                $stage = $stages[$key] ?? [];
            @endphp

            <div class="flex-1 min-w-0">
                <div class="flex items-center">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 {{ $t['dot'] }}">
                        @if ($s === 'done')
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/>
                            </svg>
                        @elseif ($s === 'skipped')
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 4a1 1 0 1 0-2 0v4a1 1 0 0 0 2 0V6zm-1 8.5a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <span class="text-xs font-bold">{{ $i + 1 }}</span>
                        @endif
                    </div>

                    @if ($i < count($order) - 1)
                        <div class="mx-2 h-0.5 flex-1 rounded {{ $t['bar'] }}"></div>
                    @endif
                </div>

                <div class="mt-1.5 pr-2">
                    <div class="text-sm font-semibold {{ $t['text'] }}">{{ $labels[$key] }}</div>

                    @unless ($compact)
                        <div class="text-xs text-slate-500 leading-snug">
                            {{ $stage['message'] ?? '—' }}
                        </div>

                        @if ($key === 'valuation' && !empty($stage['amount']))
                            <div class="text-xs font-semibold text-slate-700 mt-0.5">
                                &#8358;{{ number_format((float) $stage['amount'], 2) }}
                            </div>
                        @endif

                        @if ($key === 'consent' && ($stage['done'] ?? false))
                            <div class="text-xs mt-0.5 {{ ($stage['printed'] ?? false) ? 'text-slate-600' : 'text-amber-600 font-medium' }}">
                                {{ ($stage['printed'] ?? false) ? 'Letter printed' : 'Letter not printed' }}
                            </div>
                        @endif

                        @if ($key === 'registration' && !empty($stage['registration_number']))
                            <div class="text-xs font-semibold text-slate-700 mt-0.5">
                                {{ $stage['registration_number'] }}
                            </div>
                        @endif
                    @endunless

                    @if ($s === 'skipped')
                        <div class="text-xs font-semibold text-amber-600 mt-0.5">Taken out of order</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
