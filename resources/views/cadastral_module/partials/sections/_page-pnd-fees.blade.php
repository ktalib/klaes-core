@include('cadastral_module.partials._flash')

@php
    $money = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
    $ok    = $preview['ok'] ?? false;
    $flags = $preview['flags'] ?? [];
@endphp

<div class="unit-tag"><i class="fas fa-ruler-combined"></i> 4.4 · Plan and Description · Fee Calculator</div>

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

{{-- File: a GET form, so choosing a file just opens it here. --}}
<form method="GET" action="{{ route('cadastral-module.plan-description.fees') }}" class="form-container" id="fee-pick" style="margin-top:18px;">
    <div class="card-header">
        <strong>File</strong>
        <span class="helper-text" style="margin:0;">
            Files with a plan and description record. A file without one starts on
            <a href="{{ route('cadastral-module.plan-description.area') }}">Area &amp; Pillars</a>.
        </span>
    </div>
    <div class="form-body">
        <div class="form-group" style="margin:0;">
            <select name="record" id="fee-file"
                    data-url="{{ route('cadastral-module.plan-description.fee-files') }}"
                    data-placeholder="Type at least 2 characters of the file number, reference or owner…">
                <option value=""></option>
                @if ($picked)
                    <option value="{{ $picked['id'] }}" selected>{{ $picked['text'] }}</option>
                @endif
            </select>
        </div>
    </div>
</form>

@if ($record)
    {{-- What the record contributes. Read from the record; not editable here. --}}
    <div class="calc-grid" style="margin:18px 0;">
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
            <div class="kpi-label">Owner</div>
            <div class="kpi-value" style="font-size:15px;">{{ $record->file_title ?: '—' }}</div>
            {{-- District, LGA, State. Never the plot. --}}
            <div class="helper-text">{{ $record->property_location ?: 'Location not recorded' }}</div>
        </div>
    </div>
    <div class="helper-text" style="margin:-8px 0 14px;">
        Area and pillars are changed on
        <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}">Area &amp; Pillars</a>;
        beacons are counted from the pillar rows, never typed here.
    </div>

    {{-- Unconfirmed rules (rebuild plan Q1-Q3). --}}
    <div class="caveat" id="fee-flags">
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

    {{--
        The officer's inputs. Only these are posted; every amount is worked out
        again on the server when the bill is saved.
    --}}
    <form method="POST" action="{{ route('cadastral-module.plan-description.bill.generate', $record) }}"
          class="form-container" style="margin-top:18px;" id="fee-form"
          data-preview-url="{{ route('cadastral-module.plan-description.fees.preview') }}"
          data-record="{{ $record->id }}"
          data-installed="{{ $installed ? '1' : '0' }}">
        @csrf
        <div class="card-header">
            <strong>Officer Entries</strong>
            <span class="helper-text" style="margin:0;">Leave a box at 0 when the line does not apply.</span>
        </div>
        <div class="form-body">
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
        </div>

        {{-- One card per line of the sheet, then the total. Filled by the server, refreshed by the preview. --}}
        <div class="calc-grid" style="padding:0 18px 6px;" id="fee-lines">
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

        <div class="caveat" id="fee-refusal" style="margin:6px 18px 0;{{ $ok ? 'display:none;' : '' }}">
            <i class="fas fa-ban"></i>
            <div><strong>This bill cannot be issued.</strong> <span data-text>{{ $preview['refusal'] }}</span></div>
        </div>

        <div class="form-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            @canDo('Cad - Records', 'create')
                <button type="submit" class="btn btn-primary" id="fee-save"
                        @disabled(! $installed || ! $ok)
                        title="{{ $installed ? 'Recomputed on the server and saved with every rate' : 'Pending installation' }}">
                    <i class="fas fa-receipt"></i> Save Bill
                </button>
                @unless ($installed)
                    <span class="helper-text" style="margin:0;">Save Bill: pending installation.</span>
                @endunless
            @endcanDo
            {{-- Phase 8 (Q5): the Revenue contract is not settled. --}}
            <button type="button" class="btn btn-outline" disabled title="Revenue integration pending">
                <i class="fas fa-building-columns"></i> Push to Revenue
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
    File picker and the live preview. Picking a file opens it on this page. Any
    change to an entry asks the server for the lines again; the browser does no
    arithmetic of its own.

    Runs after DOMContentLoaded plus a tick, because Select2 is pushed to the
    page footer after this partial.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var $    = window.jQuery;
    var file = document.getElementById('fee-file');
    var pick = document.getElementById('fee-pick');

    if (file && $ && $.fn && $.fn.select2) {
        var $file = $(file);
        $file.select2({
            width: '100%',
            placeholder: file.dataset.placeholder,
            minimumInputLength: 2,
            ajax: {
                url: file.dataset.url,
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) { return data; }
            },
            templateResult: function (item) {
                if (item.loading || !item.file_number) return item.text;
                return $('<div>').append(
                    $('<div>').text(item.text),
                    $('<div style="font-size:11px;opacity:.7">').text([item.location, item.area_ha !== null ? item.area_ha + ' ha' : 'No area yet'].filter(Boolean).join(' · '))
                );
            }
        });
        $file.on('select2:select', function (e) {
            if (e.params.data && e.params.data.url) window.location.href = e.params.data.url; else pick.submit();
        });
    } else if (file) {
        file.addEventListener('change', function () { pick.submit(); });
    }

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
