{{--
  Configurable Entries -> Valuation - Consent - Registration.

  The pipeline Deeds actually runs, and the hand-offs that hold it in order. Each
  hand-off is block / warn / off, and what each of those does is spelled out on
  the screen rather than left to the word: "warn" reads as harmless, and it is the
  setting that lets an unvalued consent through.

  Editable: the gates (App\Support\DeedsPipelineGates, stored in `settings`).
  Read-only: which instruments are gated and which consent stands for which --
  config/deeds_pipeline.php owns those.
--}}
@php
    $modeMeta = [
        'block' => ['label' => 'Block', 'pill' => 'red', 'says' => 'refused until the earlier stage exists'],
        'warn' => ['label' => 'Warn', 'pill' => 'yellow', 'says' => 'allowed, with a warning on screen'],
        'off' => ['label' => 'Off', 'pill' => '', 'says' => 'not checked at all'],
    ];

    // The four stages, and which gate guards entry to each. Entry to Registration
    // is guarded twice -- there must be a consent, and it must have been printed --
    // so an arrow carries a chip per gate rather than one per arrow.
    $flow = [
        ['stage' => 'Valuation', 'note' => 'Valuation Report', 'gates' => []],
        ['stage' => 'Consent', 'note' => 'Consent application', 'gates' => ['valuation_before_consent']],
        ['stage' => 'Print', 'note' => 'Consent letter printed', 'gates' => []],
        ['stage' => 'Registration', 'note' => 'Instrument registered', 'gates' => ['consent_before_registration', 'consent_print_before_registration']],
    ];

    $alpineGates = collect($gates)->map(fn ($g) => [
        'key' => $g['key'],
        'label' => $g['label'],
        'mode' => $g['mode'],
    ])->values();
@endphp

<form method="POST" action="{{ route('configurable-entries.deeds-workflow.save') }}" class="space-y-5"
      x-data="ceDeedsWorkflow(@js($alpineGates))">
    @csrf

    {{-- Flow preview --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="workflow" class="h-5 w-5"></i></span> Valuation &rarr; Consent &rarr; Registration</div>
                <div class="iw-card-sub">The order a Deeds file moves in. A hand-off between two stages can refuse the next stage, allow it with a warning, or not be checked. Nothing here touches a file that is already captured &mdash; a hand-off is consulted only at the moment an officer saves the next stage.</div>
            </div>
        </div>
        <div class="iw-card-body">
            <div class="cew-flow">
                @foreach($flow as $node)
                    @if(!$loop->first)
                        <span class="cew-link">
                            <span class="cew-link-arrow">&rarr;</span>
                            @foreach($node['gates'] as $gateKey)
                                <span class="cew-link-mode" :class="mode('{{ $gateKey }}')"
                                      x-text="onArrow(mode('{{ $gateKey }}'))"
                                      title="{{ $gates[$gateKey]['label'] }}"></span>
                            @endforeach
                        </span>
                    @endif
                    <span class="cew-node">{{ $node['stage'] }}<small>{{ $node['note'] }}</small></span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- The gates --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title">Hand-offs</div>
                <div class="iw-card-sub">What each setting does to an officer trying to save the next stage.</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead><tr><th style="min-width:270px">Hand-off</th><th style="min-width:300px">What it refuses</th><th style="width:230px">Setting</th><th style="width:130px">Saved as</th></tr></thead>
                <tbody>
                    @foreach($gates as $key => $gate)
                        <tr>
                            <td>
                                <div class="font-medium text-gray-900">{{ $gate['label'] }}</div>
                                <div class="ce-muted">{{ $gate['where'] }} &middot; <span class="iw-mono">{{ $key }}</span></div>
                                <div class="ce-muted">{{ $gate['custom'] ? 'Set here' : 'Default from configuration' }} &middot; shipped default {{ strtoupper($gate['default']) }}</div>
                            </td>
                            <td class="ce-muted" style="white-space:normal">{{ $gate['guards'] }}</td>
                            <td>
                                <select class="ce-select" name="gates[{{ $key }}]" x-model="gates[{{ $loop->index }}].mode">
                                    @foreach($gateModes as $mode)
                                        <option value="{{ $mode }}">{{ $modeMeta[$mode]['label'] }} &mdash; {{ $modeMeta[$mode]['says'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <span class="ce-pill {{ $modeMeta[$gate['mode']]['pill'] }}">{{ strtoupper($gate['mode']) }}</span>
                                <div class="ce-muted" x-show="gates[{{ $loop->index }}].mode !== @js($gate['mode'])" x-cloak>unsaved change</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="iw-alert warn" style="margin:0 22px 18px" x-show="loosened.length" x-cloak>
            <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
            <div><strong>Workflow alert:</strong> <span x-text="loosened.join(', ')"></span> will no longer block. Officers will be able to save past that stage. Saving writes this change to User Activity Logs.</div>
        </div>
        <div class="iw-alert info" style="margin:0 22px 18px" x-show="gates.every(g => g.mode === 'off')" x-cloak>
            <i data-lucide="info" class="h-4 w-4 mt-0.5"></i>
            <div>Every hand-off is off: the four stages can be done in any order, and the workflow strip on the Deeds screens becomes a report rather than a rule.</div>
        </div>
        <div class="iw-card-foot" style="border-radius:16px;border-top:0">
            <span class="ce-muted">Applies to the next save an officer makes. Files already captured are untouched.</span>
            <button class="iw-btn iw-btn-primary"><i data-lucide="save" class="h-4 w-4"></i> Save workflow</button>
        </div>
    </div>

    {{-- What is gated at all --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title">Which instruments need a consent</div>
                <div class="iw-card-sub">An instrument that is not listed here is never consent-gated, whatever the hand-offs above are set to. Held in <span class="iw-mono">config/deeds_pipeline.php</span>: which consent authorises which deed is law rather than a setting.</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead><tr><th style="min-width:260px">Instrument</th><th style="width:220px">Consent that authorises it</th><th>Consents accepted in its place</th></tr></thead>
                <tbody>
                    @forelse($consentInstruments as $instrument => $consentType)
                        @php $standIns = array_values(array_diff($consentGroups[$consentType] ?? [], [$consentType])); @endphp
                        <tr>
                            <td class="font-medium text-gray-900">{{ $instrument }}</td>
                            <td><span class="ce-pill blue">{{ $consentType }}</span></td>
                            <td class="ce-muted">{{ $standIns ? implode(', ', $standIns) : 'Only its own consent' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ce-muted">No instrument is consent-gated on this deployment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
    function ceDeedsWorkflow(gates) {
        return {
            gates,

            init() {
                // Captured once, on load, so the alert below tracks the saved
                // state rather than whatever the administrator last clicked.
                this.gates.forEach(g => { g.wasBlocking = g.mode === 'block'; });
            },

            mode(key) {
                const gate = this.gates.find(g => g.key === key);
                return gate ? gate.mode : 'off';
            },

            onArrow(mode) {
                return mode === 'block' ? 'must be done first' : (mode === 'warn' ? 'warns only' : 'not checked');
            },

            /** Gates that were blocking and are about to stop. The only change worth warning about. */
            get loosened() {
                return this.gates.filter(g => g.wasBlocking && g.mode !== 'block').map(g => g.label);
            },
        };
    }
</script>

<style>
    .cew-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .cew-node { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 12px; border: 2px solid #4d7c0f; background: #f7fee7; color: #365314; font-size: 13px; font-weight: 700; line-height: 1.2; }
    .cew-node small { font-size: 10px; font-weight: 600; color: #65a30d; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }
    .cew-link { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-width: 104px; padding: 0 4px; }
    .cew-link-arrow { font-size: 17px; line-height: 1; color: #9ca3af; }
    .cew-link-mode { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 1px 7px; border-radius: 99px; background: #f3f4f6; color: #6b7280; white-space: nowrap; }
    .cew-link-mode.block { background: #fee2e2; color: #991b1b; }
    .cew-link-mode.warn { background: #fef9c3; color: #854d0e; }
</style>
