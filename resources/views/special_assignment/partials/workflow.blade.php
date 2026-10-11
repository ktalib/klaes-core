{{--
    SPAS workflow stepper, shown at the top of every SPAS page.

    Included with: current => ['memo', 'decision'] (the step keys this page works on).

    current  the step(s) this page works on — highlighted as "You are here"
    counts   false to hide the per-step numbers (e.g. inside a form)
    compact  true for the smaller version used inside a form
--}}
@php
    $wfCurrent = (array) ($current ?? []);
    $wfCompact = (bool) ($compact ?? false);
    $wfCounts  = ($counts ?? true) ? (rescue(fn () => app(\App\Services\SpaPipelineService::class)->summary(), null, false)) : null;

    $wfSteps = [
        'field'         => ['Field Data',    'Inspect the site',             '#0891b2', '#22d3ee', route('special-assignment.field-data'),
                            '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.6"/>'],
        'contravention' => ['Contravention', 'Bill + 1st Serve',             '#dc2626', '#f87171', route('special-assignment.notice'),
                            '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>'],
        'response'      => ['Response',      "Allottee's reply in 14 days",  '#2563eb', '#60a5fa', route('special-assignment.notice'),
                            '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/>'],
        'second'        => ['2nd Serve',     'If no reply in 14 days',       '#ea580c', '#fb923c', route('special-assignment.notice').'#second',
                            '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>'],
        'payment'       => ['Payment',       'Bill settled',                 '#16a34a', '#4ade80', route('programmes.bills').'?url=spa',
                            '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>'],
        'memo'          => ['Memo',          'Forward to Commissioner',      '#7c3aed', '#a78bfa', route('special-assignment.memo'),
                            '<path d="M4 4h16v16H4z"/><path d="m4 6 8 6 8-6"/>'],
        'decision'      => ['Decision',      'Approve or reject',            '#9333ea', '#c084fc', route('special-assignment.memo'),
                            '<path d="m14 13-7.5 7.5a2.1 2.1 0 0 1-3-3L11 10"/><path d="m16 16 6-6M8 8l6-6M9 7l8 8M21 11l-8-8"/>'],
        'certificate'   => ['Certificate',   'Change of Purpose sheet',      '#db2777', '#f472b6', route('special-assignment.certificate'),
                            '<circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/>'],
    ];
@endphp

<div class="spas-wf bg-white rounded-2xl border border-gray-200 shadow-sm {{ $wfCompact ? 'p-3' : 'p-4 sm:p-5' }} mb-5">
    @unless($wfCompact)
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg" style="background:linear-gradient(135deg,#babf0c,#8c9008)">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="6" r="2.5"/><circle cx="19" cy="18" r="2.5"/><path d="M7.5 6H15a4 4 0 0 1 0 8H9a4 4 0 0 0 0 8h7.5"/></svg>
            </span>
            <div>
                <div class="text-sm font-bold text-gray-800 leading-tight">SPAS Workflow</div>
                <div class="text-xs text-gray-500">From land record to Change of Purpose sheet</div>
            </div>
        </div>
        @if($wfCounts)
        <div class="flex items-center gap-3 text-xs text-gray-500">
            <span><b class="text-gray-800">{{ number_format($wfCounts['total']) }}</b> records</span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background:#f59e0b"></span>waiting here</span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background:#10b981"></span>done</span>
        </div>
        @endif
    </div>
    @endunless

    <div class="spas-wf__track">
        @foreach($wfSteps as $key => [$label, $hint, $hue, $light, $href, $icon])
            @php
                $here  = in_array($key, $wfCurrent, true);
                $n     = $wfCounts['stages'][$key] ?? null;
                $tile  = $wfCompact ? 34 : 46;
            @endphp
            {{-- Inside a form the steps are not links: a stray click must not lose what was typed. --}}
            <{{ $wfCompact ? 'div' : 'a' }} @unless($wfCompact) href="{{ $href }}" @endunless class="spas-wf__step {{ $here ? 'is-here' : '' }}" style="--hue: {{ $hue }}; --light: {{ $light }}" title="{{ $label }} — {{ $hint }}">
                <span class="spas-wf__rail" style="top:{{ 8 + $tile / 2 - 1 }}px"></span>
                <span class="spas-wf__tile" style="width:{{ $tile }}px;height:{{ $tile }}px">
                    <svg viewBox="0 0 24 24" width="{{ $wfCompact ? 17 : 22 }}" height="{{ $wfCompact ? 17 : 22 }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                    <span class="spas-wf__num">{{ $loop->iteration }}</span>
                </span>
                <span class="spas-wf__label">{{ $label }}</span>
                @unless($wfCompact)<span class="spas-wf__hint">{{ $hint }}</span>@endunless
                @if($here)<span class="spas-wf__you">You are here</span>@endif
                @if($n && ! $wfCompact)
                    <span class="spas-wf__counts">
                        <span class="spas-wf__pill" style="background:#fef3c7;color:#b45309" title="Records waiting at this step">{{ $n['here'] }}</span>
                        <span class="spas-wf__pill" style="background:#d1fae5;color:#047857" title="Records that have done this step">{{ $n['done'] }}</span>
                    </span>
                @endif
            </{{ $wfCompact ? 'div' : 'a' }}>
        @endforeach
    </div>
</div>

@once
<style>
    .spas-wf__track { display: flex; align-items: flex-start; overflow-x: auto; padding: 4px 2px 2px; gap: 0; }
    .spas-wf__step {
        position: relative; flex: 1 1 0; min-width: 104px; display: flex; flex-direction: column; align-items: center;
        text-align: center; padding: 8px 4px 10px; border-radius: 14px; text-decoration: none; transition: background .15s;
    }
    .spas-wf__step:hover { background: #f8fafc; }
    .spas-wf__rail {
        position: absolute; top: 31px; left: calc(50% + 30px); right: calc(-50% + 30px); height: 3px; border-radius: 3px;
        background: linear-gradient(90deg, var(--light), #e5e7eb);
    }
    .spas-wf__step:last-child .spas-wf__rail { display: none; }
    .spas-wf__tile {
        position: relative; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px;
        color: var(--hue); background: linear-gradient(145deg, #fff, color-mix(in srgb, var(--light) 22%, #fff));
        border: 2px solid color-mix(in srgb, var(--hue) 35%, #fff); box-shadow: 0 2px 6px rgba(15,23,42,.06); z-index: 1;
    }
    .spas-wf__num {
        position: absolute; top: -7px; right: -7px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px;
        background: #fff; color: var(--hue); border: 1.5px solid var(--hue); font-size: 10px; font-weight: 800; line-height: 15px;
    }
    .spas-wf__label { margin-top: 8px; font-size: 12.5px; font-weight: 700; color: #1f2937; line-height: 1.2; }
    .spas-wf__hint  { margin-top: 2px; font-size: 10.5px; color: #6b7280; line-height: 1.25; }
    .spas-wf__counts { display: inline-flex; gap: 4px; margin-top: 6px; }
    .spas-wf__pill { min-width: 22px; padding: 1px 7px; border-radius: 9999px; font-size: 10.5px; font-weight: 700; }
    .spas-wf__you {
        margin-top: 5px; padding: 1px 8px; border-radius: 9999px; font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
        text-transform: uppercase; color: #fff; background: var(--hue);
    }
    /* The page's own step: filled tile, tinted card, ring. */
    .spas-wf__step.is-here { background: color-mix(in srgb, var(--light) 14%, #fff); box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--hue) 30%, #fff); }
    .spas-wf__step.is-here .spas-wf__tile {
        color: #fff; background: linear-gradient(145deg, var(--light), var(--hue)); border-color: var(--hue);
        box-shadow: 0 0 0 5px color-mix(in srgb, var(--hue) 16%, transparent), 0 6px 14px color-mix(in srgb, var(--hue) 30%, transparent);
    }
    .spas-wf__step.is-here .spas-wf__label { color: var(--hue); }
</style>
@endonce
