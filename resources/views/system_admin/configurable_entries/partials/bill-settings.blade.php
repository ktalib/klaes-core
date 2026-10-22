{{-- KLAES REV-M Bill Settings (docs/plans/KLAES_REVM_Bill_Master_Plan.md).
     One sub-tab per Bill Master type: its settings, then its bill items. Items come from
     the module's own table where it has one (Contravention = SPAS Bill Items), otherwise
     from the Bill Formulas engine. "Other formulas" keeps formulas tied to no bill type. --}}
@php
    $bsMethods = config('billing.payment_methods', []);
    $bsUrl = fn ($code) => route('configurable-entries.index', ['tab' => 'bill-settings', 'sub' => $code]);
@endphp

<div class="iw-alert info">
    <i data-lucide="receipt-text" class="h-4 w-4 mt-0.5"></i>
    <div>
        Each <strong>bill type</strong> under KLAES REV-M has its own settings and bill items here.
        Bills already generated keep their own copy of every line, so a change applies only to bills raised from now on.
    </div>
</div>

<div class="ce-subtabs" role="tablist" aria-label="Bill types" style="flex-wrap:wrap">
    @foreach($billSettingsTypes as $bsType)
        <a href="{{ $bsUrl($bsType->code) }}" role="tab" aria-selected="{{ $billSettingsSub === $bsType->code ? 'true' : 'false' }}"
           class="ce-subtab {{ $billSettingsSub === $bsType->code ? 'active' : '' }}">
            {{ $bsType->name }}
            @unless($bsType->is_active)<span class="ce-subtab-count">Off</span>@endunless
        </a>
    @endforeach
    <a href="{{ $bsUrl('other') }}" role="tab" aria-selected="{{ $billSettingsSub === 'other' ? 'true' : 'false' }}"
       class="ce-subtab {{ $billSettingsSub === 'other' ? 'active' : '' }}">
        <i data-lucide="sigma" class="h-4 w-4"></i> Other formulas
    </a>
</div>

@if($billSettingsType)
    {{-- Settings --}}
    <form method="POST" action="{{ route('configurable-entries.bill-settings.save', $billSettingsType->code) }}" class="iw-card"
          x-data="{ accepts: @js((bool) $billSettingsType->accepts_payment), recurring: @js((bool) $billSettingsType->is_recurring) }">
        @csrf
        <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
            <div>
                <div class="iw-card-title">
                    <span class="iw-icon-tile green"><i data-lucide="settings-2" class="h-5 w-5"></i></span>
                    {{ $billSettingsType->name }}
                    @if($billSettingsType->is_active)<span class="ce-pill green">On</span>@else<span class="ce-pill red">Off</span>@endif
                </div>
                <div class="iw-card-sub">
                    Settings for this bill type.
                    @if($billSettingsChildren->isNotEmpty())
                        Also covers {{ $billSettingsChildren->pluck('name')->implode(', ') }}.
                    @endif
                </div>
            </div>
            <span class="ce-muted iw-mono">{{ $billSettingsType->code }}</span>
        </div>
        <div class="iw-card-body space-y-4">
            {{-- One Revenue Code per bill type (2026-10-21): shown, not editable here (user). --}}
            <div class="text-sm">
                <span class="iw-label" style="display:block;margin-bottom:4px">Revenue Code</span>
                <span class="iw-mono" style="font-weight:700;color:#dc2626;font-size:1.05rem">{{ $billSettingsType->revenue_code ?: '—' }}</span>
                <span class="ce-muted text-xs" style="margin-left:10px">
                    @if($billSettingsChildren->isNotEmpty()) Also used by {{ $billSettingsChildren->pluck('name')->implode(', ') }}. @endif
                    Entering this code on an on-the-fly bill picks {{ $billSettingsType->name }}.
                </span>
            </div>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-3 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($billSettingsType->is_active)><span></span></span>
                    Bills can be generated
                </label>
                <label class="flex items-center gap-3 text-sm">
                    <input type="hidden" name="accepts_payment" value="0">
                    <span class="ce-switch"><input type="checkbox" name="accepts_payment" value="1" x-model="accepts" @checked($billSettingsType->accepts_payment)><span></span></span>
                    Accepts payment at the bill stage
                </label>
            </div>

            <div x-show="accepts" x-cloak>
                <div class="iw-label" style="margin-bottom:6px">Payment methods</div>
                <div class="flex flex-wrap gap-3">
                    @foreach($bsMethods as $bsKey => $bsMethod)
                        @php $bsEnabled = (bool) ($bsMethod['enabled'] ?? false); @endphp
                        <label class="flex items-start gap-2 text-sm border rounded-lg px-3 py-2 {{ $bsEnabled ? 'border-gray-300 bg-white' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}"
                               title="{{ $bsMethod['note'] ?? '' }}">
                            <input type="checkbox" name="payment_methods[]" value="{{ $bsKey }}" class="mt-0.5"
                                   @checked(in_array($bsKey, $billSettingsType->paymentMethods(), true))
                                   @disabled(! $bsEnabled)>
                            @unless($bsEnabled)
                                {{-- A disabled box is not submitted; keep the stored choice so saving never drops it. --}}
                                @if(in_array($bsKey, $billSettingsType->paymentMethods(), true))<input type="hidden" name="payment_methods[]" value="{{ $bsKey }}">@endif
                            @endunless
                            <span>
                                <span class="font-semibold">{{ $bsMethod['label'] }}</span>
                                @unless($bsEnabled)<span class="ce-pill" style="margin-left:4px">Not available yet</span>@endunless
                                <span class="block ce-muted">{{ $bsMethod['note'] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Payment cycle (REV-M recurring bills, 2026-10-21) --}}
            <div style="border-top:1px dashed #e2e8f0;padding-top:12px">
                <label class="flex items-center gap-3 text-sm">
                    <input type="hidden" name="is_recurring" value="0">
                    <span class="ce-switch"><input type="checkbox" name="is_recurring" value="1" x-model="recurring" @checked($billSettingsType->is_recurring)><span></span></span>
                    Recurring bill (paid on a payment cycle)
                </label>
                <div x-show="recurring" x-cloak class="flex flex-wrap gap-4 mt-3">
                    <label class="text-sm">
                        <span class="iw-label" style="display:block;margin-bottom:4px">Payment cycle <span style="color:#dc2626">*</span></span>
                        <select name="payment_cycle" class="border border-gray-300 rounded-lg px-3 py-2" :required="recurring">
                            <option value="">Choose…</option>
                            @foreach (config('billing.cycles') as $bsCycle => $bsMeta)
                                <option value="{{ $bsCycle }}" @selected($billSettingsType->payment_cycle === $bsCycle)>{{ $bsMeta['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="iw-label" style="display:block;margin-bottom:4px">SMS reminder, days before the due date</span>
                        <input type="number" name="remind_days_before" min="0" max="90" value="{{ (int) ($billSettingsType->remind_days_before ?? 7) }}" class="border border-gray-300 rounded-lg px-3 py-2 w-28">
                    </label>
                    <div class="ce-muted text-xs" style="flex-basis:100%">
                        Each bill covers one period from its bill date. The next payment falls due the day after the period ends: the payer gets the "next payment due" SMS this many days before, and on the due date the next cycle's bill is raised and its SMS sent (daily at 07:30). The SMS themselves are switched on in the SMS Control Centre.
                    </div>
                </div>
            </div>
        </div>
        <div class="iw-card-body" style="border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end">
            <button type="submit" class="iw-btn iw-btn-success"><i data-lucide="save" class="h-4 w-4"></i> Save settings</button>
        </div>
    </form>

@endif

{{-- Bill items --}}
@if($billSettingsSource === 'spa')
    @if($billSettingsSpaReady)
        @include('system_admin.configurable_entries.partials.spa-bill-items')
    @else
        <div class="iw-card"><div class="iw-card-body"><div class="iw-empty">SPAS Bill Items is not installed on this deployment.</div></div></div>
    @endif
@elseif($billSettingsSource === 'hold')
    <div class="iw-alert warn">
        <i data-lucide="pause-circle" class="h-4 w-4 mt-0.5"></i>
        <div><strong>On hold.</strong> Land Use Charge items and rates will be set up once the Ministry's LuC details are available.</div>
    </div>
@else
    @include('system_admin.configurable_entries.partials.billing')
@endif
