@include('cadastral_module.partials._flash')

@php
    $money = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
    $ok    = $preview['ok'] ?? false;
    $flags = $preview['flags'] ?? [];
@endphp


<div class="page-header">
    <div>
        @if ($record)
            <strong>{{ $record->pd_ref }}</strong> · {{ $record->file_number }}
        @endif
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if ($record)
            <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-ruler-combined"></i> Area &amp; Pillars
            </a>
            <a href="{{ route('cadastral-module.plan-description.descriptions', ['record' => $record->id]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-file-lines"></i> Description
            </a>
        @endif
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> Register
        </a>
    </div>
</div>

@unless ($installed)
    <div class="caveat">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>Pending installation.</strong>
            The lines below are worked out from the live rates, but a bill cannot be saved until the
            fee-sheet columns are added to <code>cadastral_bills</code> (migration 2026_10_02_120000).
        </div>
    </div>
@endunless

@unless ($record)
    {{-- No file yet: the picker alone, at scope plan. A good pick opens the
         file's record here; a file without one is refused with the reason. --}}
    <div class="form-container" id="fee-pick" style="margin-top:18px;" data-open-url="{{ route('cadastral-module.plan-description.fees') }}">
        <div class="card-header">
            <strong>Select File</strong>
            <span class="helper-text" style="margin:0;">
                Files with a plan and description record. A file without one starts on
                <a href="{{ route('cadastral-module.plan-description.area') }}">Area &amp; Pillars</a>.
            </span>
        </div>
        <div class="form-body">
            <div class="form-grid">
                @include('cadastral_module.partials._file_picker', [
                    'scope'    => 'plan',
                    'hidden'   => ['cadastral_plan_description_id'],
                    'required' => false,
                    'help'     => 'Picking a file opens its bill here.',
                ])
            </div>
        </div>
    </div>
@endunless

@if ($record)
    @include('cadastral_module.partials._wizard')

    {{--
        The bill wizard: file, area and beacons, the officer's charges, the
        bill. Only the charges are posted; every amount is worked out again on
        the server when the bill is saved.
    --}}
    <form method="POST" action="{{ route('cadastral-module.plan-description.bill.generate', $record) }}"
          class="form-container" style="margin-top:18px;" id="fee-form" data-wizard
          data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate
          data-preview-url="{{ route('cadastral-module.plan-description.fees.preview') }}"
          data-open-url="{{ route('cadastral-module.plan-description.fees') }}"
          data-record="{{ $record->id }}"
          data-installed="{{ $installed ? '1' : '0' }}">
        @csrf

        <div class="form-stepper" data-wizard-header></div>

        <div class="form-body">

            {{-- 1. The file. Picking another opens that file's bill instead. --}}
            <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                     data-subtitle="The file being billed. Picking another file opens its own bill.">
                <div class="form-grid">
                    @include('cadastral_module.partials._file_picker', [
                        'scope'    => 'plan',
                        'hidden'   => ['cadastral_plan_description_id'],
                        'initial'  => $picked,
                        'number'   => $record->file_number,
                        'required' => false,
                    ])

                    <div class="form-group">
                        <label>Record</label>
                        <input type="text" class="cad-locked" disabled value="{{ $record->pd_ref }}" />
                    </div>
                    <div class="form-group">
                        <label>Owner</label>
                        <input type="text" class="cad-locked" disabled value="{{ $record->file_title ?: '—' }}" />
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Location</label>
                        {{-- District, LGA, State. Never the plot. --}}
                        <input type="text" class="cad-locked" disabled value="{{ $record->property_location ?: 'Location not recorded' }}" />
                    </div>
                </div>
            </section>

            {{-- 2. What the record contributes. Read from the record; not editable here. --}}
            <section class="form-step" data-step data-title="Area & Beacons" data-icon="ruler"
                     data-subtitle="Read from the record. Area and pillars are changed on Area & Pillars, never here.">
                <div class="calc-grid">
                    <div class="calc-card">
                        <div class="kpi-label">Area (Hectares)</div>
                        <div class="kpi-value">{{ $preview['area']['ha'] !== null ? number_format($preview['area']['ha'], 4) : '—' }}</div>
                        <div class="helper-text">
                            {{ $preview['area']['sqm'] !== null ? number_format($preview['area']['sqm'], 2) . ' m²' : 'No area recorded' }}
                        </div>
                    </div>
                    <div class="calc-card">
                        <div class="kpi-label">Pillars (Beacons)</div>
                        <div class="kpi-value">{{ $preview['pillars']['total'] }}</div>
                        <div class="helper-text">{{ $preview['pillars']['government'] }} government · {{ $preview['pillars']['private'] }} private</div>
                    </div>
                    <div class="calc-card">
                        <div class="kpi-label">Area-fee Schedule</div>
                        <div class="kpi-value" style="font-size:18px;">{{ $preview['area']['column_label'] }}</div>
                        <div class="helper-text">Kano S.L.N. No. 3 of 1983</div>
                    </div>
                    <div class="calc-card">
                        <div class="kpi-label">Land Use</div>
                        <div class="kpi-value" style="font-size:15px;">{{ $record->land_use ?: '—' }}</div>
                    </div>
                </div>
                <div class="helper-text" style="margin-top:10px;">
                    Beacons are counted from the pillar rows, never typed. To change the area or the pillars, open
                    <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}">Area &amp; Pillars</a>.
                </div>
            </section>

            {{-- 3. What the sheet leaves to the officer. --}}
            <section class="form-step" data-step data-title="Charges" data-icon="calculator"
                     data-subtitle="Leave a box at 0 when the line does not apply. The bill on the next step follows every change.">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Delay (days)</label>
                        <input type="number" name="delay_days" min="0" max="999" step="1" value="{{ $input['delay_days'] }}" data-fee-input />
                        <div class="helper-text">₦{{ number_format($rates['delay_per_day'], 2) }} per day</div>
                    </div>
                    <div class="form-group">
                        <label>Transport Band</label>
                        <select name="transport_band" data-fee-input>
                            <option value="none">None</option>
                            @foreach ($bands as $key => $band)
                                <option value="{{ $key }}" @selected($input['transport_band'] === (string) $key)>
                                    {{ $band['label'] }} — ₦{{ number_format($band['fee'], 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>or Distance from Kano (km)</label>
                        <input type="number" name="transport_km" min="0" step="0.1" value="{{ $input['transport_km'] }}" data-fee-input placeholder="e.g. 10.4" />
                        <div class="helper-text">A distance picks the band itself (rounded up to whole km) and overrides the box beside it.</div>
                    </div>
                    <div class="form-group">
                        <label>Additional Field Work (days)</label>
                        <input type="number" name="field_work_days" min="0" max="999" step="1" value="{{ $input['field_work_days'] }}" data-fee-input />
                        <div class="helper-text">₦{{ number_format($rates['field_work_per_day'], 2) }} per day</div>
                    </div>
                    <div class="form-group">
                        <label>Office Work (days)</label>
                        <input type="number" name="office_work_days" min="0" max="999" step="1" value="{{ $input['office_work_days'] }}" data-fee-input />
                        <div class="helper-text">₦{{ number_format($rates['office_work_per_day'], 2) }} per day</div>
                    </div>
                    <div class="form-group">
                        <label>Plan Prints (files)</label>
                        <input type="number" name="plan_prints" min="0" max="999" step="1" value="{{ $input['plan_prints'] }}" data-fee-input />
                        <div class="helper-text">₦{{ number_format($rates['plan_print'], 2) }} per file</div>
                    </div>
                </div>
            </section>

            {{-- 4. The bill. One card per line of the sheet, then the total;
                 filled by the server and refreshed by the live preview. --}}
            <section class="form-step" data-step data-review data-title="Review Bill" data-icon="receipt"
                     data-subtitle="Every line as the server works it out. Save Bill recomputes them all and issues the bill.">
                <div class="calc-grid" id="fee-lines">
                    @foreach ($preview['lines'] as $key => $line)
                        <div class="calc-card" data-line="{{ $key }}">
                            <div class="kpi-label">{{ $line['label'] }}</div>
                            <div class="kpi-value" data-amount>{{ $money($line['amount']) }}</div>
                            <div class="helper-text" data-detail>
                                {{ $line['qty_label'] }}{{ $line['rate'] !== null ? ' × ₦' . number_format($line['rate'], 2) : '' }}
                            </div>
                            <div class="helper-text" data-basis style="opacity:.75;">{{ $line['basis'] }}</div>
                        </div>
                    @endforeach
                    <div class="calc-card" data-line="total" style="border:2px solid var(--gray-400);">
                        <div class="kpi-label">Grand Total</div>
                        <div class="kpi-value" data-amount>{{ $money($preview['total']) }}</div>
                        <div class="helper-text">Each line rounded, then summed</div>
                    </div>
                </div>

                <div class="caveat" id="fee-refusal" style="margin-top:12px;{{ $ok ? 'display:none;' : '' }}">
                    <i class="fas fa-ban"></i>
                    <div><strong>This bill cannot be issued.</strong> <span data-text>{{ $preview['refusal'] }}</span></div>
                </div>

                {{-- Unconfirmed rules (rebuild plan Q1-Q3). --}}
                <div class="caveat" id="fee-flags" style="margin-top:12px;">
                    <i class="fas fa-circle-info"></i>
                    <div>
                        <strong>Rules the department has not confirmed.</strong>
                        <ul style="margin:6px 0 0 18px;list-style:disc;">
                            <li data-flag="Q1" style="{{ isset($flags['Q1']) ? 'font-weight:600;' : '' }}">
                                Q1 — an area between two schedule rows is charged by
                                "{{ \App\Services\Cadastral\CadastralSettings::BETWEEN_ROWS_RULES[$preview['area']['between_rule']] }}"{{ $confirmed['Q1'] ? ' (confirmed).' : ', unconfirmed.' }}
                            </li>
                            <li data-flag="Q2" style="{{ isset($flags['Q2']) ? 'font-weight:600;' : '' }}">
                                Q2 — above the largest row ({{ $maxHa !== null ? number_format($maxHa, 2) . ' ha' : 'none' }}):
                                {{ $aboveMax === 'refuse' ? 'the bill is refused' : 'the largest row is charged' }}, unconfirmed.
                            </li>
                            <li data-flag="Q3" style="{{ isset($flags['Q3']) ? 'font-weight:600;' : '' }}">
                                Q3 — the transport bands are read from a garbled line on the sheet{{ $confirmed['Q3'] ? ' (confirmed).' : ', unconfirmed.' }}
                            </li>
                        </ul>
                        <div class="helper-text" style="margin-top:4px;">Rates, the schedule and the bands are edited in System Admin → Configurable Entries → Cadastral.</div>
                    </div>
                </div>

                <div style="margin-top:16px;" data-wizard-summary></div>
            </section>
        </div>

        <div class="form-actions" data-wizard-nav style="flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
            <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
            @canDo('Cad - Records', 'create')
                <button type="submit" class="btn btn-primary" id="fee-save" data-wizard-submit
                        @disabled(! $installed || ! $ok)
                        title="{{ $installed ? 'Recomputed on the server and saved with every rate' : 'Pending installation' }}">
                    <i data-lucide="receipt"></i> Save Bill
                </button>
                @unless ($installed)
                    <span class="helper-text" style="margin:0;">Save Bill: pending installation.</span>
                @endunless
            @endcanDo
            {{-- Phase 8 (Q5): the Revenue contract is not settled. --}}
            <button type="button" class="btn btn-outline" disabled title="Revenue integration pending">
                <i data-lucide="landmark"></i> Push to Revenue
            </button>
            <span class="helper-text" style="margin:0;">Revenue integration pending.</span>
        </div>
    </form>
@endif

{{-- ===================== Bills ===================== --}}
<div class="table-wrapper" style="margin-top:22px;">
    <div class="card-header" style="padding:12px 16px;">
        <strong>Bills{{ $record && request('bills') !== 'all' ? ' — ' . $record->file_number : '' }}</strong>
        @if ($record)
            <span class="helper-text" style="margin:0;">
                @if (request('bills') === 'all')
                    <a href="{{ route('cadastral-module.plan-description.fees', ['record' => $record->id]) }}">This file only</a>
                @else
                    <a href="{{ route('cadastral-module.plan-description.fees', ['record' => $record->id, 'bills' => 'all']) }}">Every file</a>
                @endif
            </span>
        @endif
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>File No</th>
                    <th class="money">Area Fee</th>
                    <th class="money">Beacons</th>
                    <th class="money">Other</th>
                    <th class="money">Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $b)
                    <tr>
                        <td>
                            <strong>{{ $b->bill_ref }}</strong>
                            <div class="helper-text" style="margin:0;">{{ optional($b->issued_at)->format('d M Y') ?: '—' }}</div>
                        </td>
                        <td>
                            {{ $b->file_number }}
                            <div class="helper-text" style="margin:0;">{{ $b->planDescription?->pd_ref }}</div>
                        </td>
                        <td class="money">{{ number_format((float) $b->area_fee, 2) }}</td>
                        <td class="money">{{ number_format((float) $b->pillar_fee, 2) }}</td>
                        <td class="money">{{ number_format($b->other_amount, 2) }}</td>
                        <td class="money"><strong>{{ number_format((float) $b->grand_total, 2) }}</strong></td>
                        <td>
                            <span class="status-badge {{ $b->status_badge }}"><span class="dot"></span>{{ $b->status }}</span>
                            @if ($b->cancel_reason)
                                <div class="helper-text" style="margin:0;">{{ $b->cancel_reason }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="action-icons">
                                @canDo('Cad - Records', 'print')
                                    <a href="{{ route('cadastral-module.plan-description.bill.print', [$b->cadastral_plan_description_id, $b->id]) }}" target="_blank" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                @endcanDo
                                @if ($b->status === 'Draft')
                                    @canDo('Cad - Records', 'edit')
                                        <form method="POST" action="{{ route('cadastral-module.plan-description.bill.mark-cancelled', [$b->cadastral_plan_description_id, $b->id]) }}"
                                              style="display:inline;" onsubmit="return confirm('Cancel draft {{ $b->bill_ref }}? It stays on record.');">
                                            @csrf
                                            <button type="submit" title="Cancel draft" style="background:none;border:0;padding:0;cursor:pointer;color:inherit;">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    @endcanDo
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-receipt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No bills yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $bills->firstItem() ?? 0 }}–{{ $bills->lastItem() ?? 0 }} of {{ number_format($bills->total()) }}
            · "Other" is delay, transport, field work, office work and plan prints together
        </span>
        <div class="pagination">{{ $bills->links() }}</div>
    </div>
</div>

{{--
    Opening a picked file, and the live preview. A fresh good pick opens that
    file's bill on this page; a refused one stays in the picker with its
    reason. Any change to a charge asks the server for the lines again; the
    browser does no arithmetic of its own.

    Runs after DOMContentLoaded plus a tick, like the picker and the wizard.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    // Both pickers (the bare one, and step 1 of the wizard) open what they pick.
    ['fee-pick', 'fee-form'].forEach(function (id) {
        var host = document.getElementById(id);
        if (!host) return;
        host.addEventListener('cadastral:file-picked', function (e) {
            var p = e.detail;
            var pd = p && !p.initial && p.status === 'ok' && p.hidden ? p.hidden.cadastral_plan_description_id : null;
            if (pd && String(pd) !== String(host.dataset.record || '')) {
                window.location.href = host.dataset.openUrl + '?record=' + encodeURIComponent(pd);
            }
        });
    });

    var form = document.getElementById('fee-form');
    if (!form) return;

    var save      = document.getElementById('fee-save');
    var refusal   = document.getElementById('fee-refusal');
    var installed = form.dataset.installed === '1';
    var timer     = null;
    var seq       = 0;

    var money = function (v) {
        return v === null || v === undefined ? '—'
            : '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    var render = function (data) {
        (data.lines || []).forEach(function (line) {
            var card = form.querySelector('[data-line="' + line.key + '"]');
            if (!card) return;
            card.querySelector('[data-amount]').textContent = money(line.amount);
            card.querySelector('[data-detail]').textContent = line.qty_label + (line.rate !== null ? ' × ' + money(line.rate) : '');
            card.querySelector('[data-basis]').textContent = line.basis;
        });
        form.querySelector('[data-line="total"] [data-amount]').textContent = money(data.total);

        refusal.style.display = data.ok ? 'none' : '';
        refusal.querySelector('[data-text]').textContent = data.refusal || '';
        if (save) save.disabled = !installed || !data.ok;

        document.querySelectorAll('#fee-flags [data-flag]').forEach(function (li) {
            li.style.fontWeight = data.flags && data.flags[li.dataset.flag] ? '600' : '';
        });
    };

    var refresh = function () {
        var params = new URLSearchParams({ record: form.dataset.record });
        form.querySelectorAll('[data-fee-input]').forEach(function (el) { params.set(el.name, el.value); });
        var mine = ++seq;
        fetch(form.dataset.previewUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (mine === seq) render(data); })
            .catch(function () { /* the server-rendered figures stay; Save recomputes anyway */ });
    };

    form.querySelectorAll('[data-fee-input]').forEach(function (el) {
        ['input', 'change'].forEach(function (ev) {
            el.addEventListener(ev, function () { clearTimeout(timer); timer = setTimeout(refresh, 250); });
        });
    });
}, 0); });
</script>
