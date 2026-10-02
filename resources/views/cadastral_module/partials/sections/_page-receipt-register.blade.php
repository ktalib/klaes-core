@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($receipt->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.registry.receipts.update', $receipt)
        : route('cadastral-module.registry.receipts.store');

    // The file picked from the source index (FileReceiptController::create/edit
    // re-read it from file_indexings). Its address is put on the in-memory model
    // so the builder renders it before the script below locks it; nothing saves.
    $picked    = $picked ?? null;
    $sources   = $sources ?? [];
    $curSource = $isEdit ? $receipt->source_registry : old('source_registry', $picked['source'] ?? '');
    if ($picked) {
        foreach ($picked['address'] as $col => $value) {
            if ($value !== null) $receipt->{$col} = $value;
        }
    }
@endphp

<div class="unit-tag"><i class="fas fa-inbox"></i> 4.1 · Cadastral Registry</div>

<div class="page-header">
    <div></div>
    <a href="{{ route('cadastral-module.registry.receipts') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to the log
    </a>
</div>

@if ($isEdit && ($summary ?? null) && $summary['has_warning'])
    <div class="caveat">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>This file number needs a second look.</strong>
            @if ($summary['duplicates']->isNotEmpty())
                {{ $summary['duplicates']->count() }} entry(ies) in the duplicate register.
            @endif
            @if ($summary['doubles']->isNotEmpty())
                {{ $summary['doubles']->count() }} other file(s) share its plot number:
                {{ $summary['doubles']->pluck('file_number')->take(5)->implode(', ') }}.
            @endif
            <a href="{{ route('cadastral-module.registry.duplicates', ['file_number' => $receipt->file_number]) }}">Open the duplicate check</a>.
        </div>
    </div>
@endif

@if ($isEdit && $receipt->isOnHold())
    <div class="caveat">
        <i class="fas fa-hand"></i>
        <div>
            <strong>On hold for investigation.</strong>
            {{ $receipt->hold_reason }}
            It cannot be registered until an officer clears the hold, with a remark, from the
            <a href="{{ route('cadastral-module.registry.receipts', ['queue' => 'On Hold']) }}">Intake Queue</a>.
        </div>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="form-container">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-body">

        <fieldset style="border:none;padding:0;margin:0 0 18px;" id="intake-file-group"
                  data-picked="{{ $picked ? json_encode($picked) : '' }}">
            <div class="ab-legend">The File</div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Source Department <span class="required">*</span></label>
                    @if ($isEdit)
                        <input type="text" value="{{ $receipt->source_registry }}" readonly class="intake-locked" />
                    @else
                        <select name="source_registry" id="intake-source" required>
                            <option value="">Choose the source first…</option>
                            @foreach ($sources as $src)
                                <option value="{{ $src }}" @selected($curSource === $src)>{{ $src }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="form-group">
                    <label>File No <span class="required">*</span></label>
                    @if ($isEdit)
                        <input type="text" value="{{ $receipt->file_number }}" readonly class="intake-locked" />
                        <div class="helper-text">The file cannot be changed. If the wrong file was logged, return or reject this receipt and log the right one.</div>
                    @else
                        {{-- Only an id from the source index can be posted: there is no free-text number. --}}
                        <select name="file_indexing_id" id="intake-file" required
                                data-url="{{ route('cadastral-module.registry.source-files') }}"
                                data-placeholder="Type at least 2 characters of the file number…"
                                @disabled($curSource === '')>
                            <option value=""></option>
                            @if ($picked)
                                <option value="{{ $picked['id'] }}" selected>{{ $picked['text'] }}</option>
                            @endif
                        </select>
                        <div class="helper-text">Searched in the chosen source's index. A file already open in the queue cannot be picked again.</div>
                    @endif
                </div>

                <div class="form-group">
                    <label>Type</label>
                    <input type="text" id="intake-type" readonly class="intake-locked"
                           value="{{ $picked['type'] ?? ($isEdit ? \App\Services\Cadastral\CadastralRegistryLookup::typeLabel($receipt->file_number, $receipt->source_registry, $receipt->file_class) : '') }}"
                           placeholder="Filled from the file number" />
                </div>

                <div class="form-group">
                    <label>Owner / Title</label>
                    <input type="text" id="intake-owner" readonly class="intake-locked"
                           value="{{ $picked['owner'] ?? $receipt->file_title }}"
                           placeholder="Filled from the source file" />
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <div id="intake-notes" style="display:flex;gap:8px;flex-wrap:wrap;font-size:12px;"></div>
                </div>

                <div class="form-group">
                    <label>Their Reference</label>
                    <input type="text" name="source_reference" value="{{ old('source_reference', $receipt->source_reference) }}"
                           placeholder="Dispatch / transmittal number" />
                </div>

                <div class="form-group">
                    <label>Received From</label>
                    <input type="text" name="received_from" value="{{ old('received_from', $receipt->received_from) }}"
                           placeholder="Officer or desk" />
                </div>

                <div class="form-group">
                    <label>Received At <span class="required">*</span></label>
                    <input type="datetime-local" name="received_at"
                           value="{{ old('received_at', optional($receipt->received_at)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}"
                           required />
                </div>

                <div class="form-group">
                    <label>Purpose</label>
                    <select name="purpose">
                        <option value="">—</option>
                        @foreach (\App\Models\Cadastral\CadastralFileReceipt::PURPOSES as $p)
                            <option value="{{ $p }}" @selected(old('purpose', $receipt->purpose)===$p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Pages</label>
                    <input type="number" name="num_pages" min="0" value="{{ old('num_pages', $receipt->num_pages) }}" />
                </div>

                <div class="form-group">
                    <label>Shelf Location</label>
                    <input type="text" name="shelf_location" value="{{ old('shelf_location', $receipt->shelf_location) }}"
                           placeholder="Derived from the workbooks if left blank" />
                </div>

                @if ($isEdit)
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            @foreach (\App\Models\Cadastral\CadastralFileReceipt::STATUSES as $s)
                                <option value="{{ $s }}" @selected(old('status', $receipt->status)===$s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="form-group" style="grid-column:1/-1;">
                    <label>Condition Note</label>
                    <input type="text" name="condition_note" value="{{ old('condition_note', $receipt->condition_note) }}"
                           placeholder="Anything wrong with the physical file" />
                </div>
            </div>
        </fieldset>

        {{-- District, LGA and State compose "District, LGA, Kano". The plot
             number is its own field and never appears in the location.
             Fields the source file fills are locked by the script below and
             re-read from file_indexings on save; fields it leaves blank stay
             open for the clerk. --}}
        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $receipt,
            'legend' => 'Property Location',
        ])
    </div>

    <div class="form-actions">
        <a href="{{ route('cadastral-module.registry.receipts') }}" class="btn btn-secondary">Cancel</a>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ $isEdit ? 'Save Changes' : 'Log File' }}
            </button>
        @endcanDo
    </div>
</form>

<style>
    .survey-proto .intake-locked,
    .survey-proto .address-builder input[readonly] { background: var(--gray-100); color: var(--gray-700); }
    .survey-proto .address-builder .select2-container--disabled .select2-selection--single { background: var(--gray-100); }
</style>

{{--
    Intake picker. Source first; the File No select stays disabled until one is
    chosen, then searches cadastral-module.registry.source-files for that source.
    Picking a file fills Type, Owner and the property fields and locks every one
    the source file supplies; the server re-reads the same row on save, so the
    lock is a courtesy, not the control.

    Runs after DOMContentLoaded plus a tick: Select2 and the address-builder
    script are pushed to the page footer after this partial, and the builder
    must have initialised its own Select2 boxes before they can be filled.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var group = document.getElementById('intake-file-group');
    var form  = group ? group.closest('form') : null;
    var ab    = form ? form.querySelector('.address-builder[data-prefix="prop_"]') : null;
    if (!group || !ab) return;

    var $       = window.jQuery;
    var hasS2   = !!($ && $.fn && $.fn.select2);
    var source  = document.getElementById('intake-source');
    var file    = document.getElementById('intake-file');
    var typeBox = document.getElementById('intake-type');
    var owner   = document.getElementById('intake-owner');
    var notes   = document.getElementById('intake-notes');

    // Builder fields a source file can supply, in fill order: a select is
    // filled before its "specify" box, because changing the select clears it.
    var FIELDS = ['prop_district', 'prop_district_other', 'prop_street', 'prop_street_other',
                  'prop_lga', 'prop_state', 'prop_plot'];
    var locked = [];

    function el(name) { return ab.querySelector('[name="' + name + '"]'); }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setValue(field, value) {
        if (!field) return;
        if (field.tagName === 'SELECT') {
            var has = Array.prototype.some.call(field.options, function (o) { return o.value === value; });
            if (value && !has) field.add(new Option(value === 'Other' ? 'Other (specify)' : value, value));
            field.value = value || '';
            if (hasS2) $(field).trigger('change'); else field.dispatchEvent(new Event('change'));
        } else {
            field.value = value || '';
            field.dispatchEvent(new Event('input'));
        }
    }

    function setLocked(field, on) {
        if (!field) return;
        if (field.tagName === 'SELECT') {
            // A disabled select does not post; the server fills it from the source.
            field.disabled = on;
            if (hasS2) $(field).prop('disabled', on);
        } else {
            field.readOnly = on;
        }
    }

    function release() {
        locked.forEach(function (name) {
            var f = el(name);
            setLocked(f, false);
            setValue(f, name === 'prop_state' ? 'Kano' : '');
        });
        locked = [];
        if (typeBox) typeBox.value = '';
        if (owner) owner.value = '';
        if (notes) notes.innerHTML = '';
    }

    function badge(cls, text) {
        return '<span class="status-badge ' + cls + '"><span class="dot"></span>' + esc(text) + '</span>';
    }

    function apply(item) {
        release();
        if (!item || !item.address) return;

        if (typeBox) typeBox.value = item.type || '';
        if (owner) owner.value = item.owner || '';

        FIELDS.forEach(function (name) {
            var value = item.address[name];
            if (value === null || value === undefined || value === '') return;
            setValue(el(name), value);
            locked.push(name);
        });
        locked.forEach(function (name) { setLocked(el(name), true); });

        var bits = [];
        if (item.file_class === 'conversion') bits.push(badge('review', 'Conversion file: charting not required'));
        if (item.land_use) bits.push('<span style="color:var(--gray-600)">' + esc(item.land_use) + '</span>');
        if (item.location) bits.push('<span style="color:var(--gray-600)">' + esc(item.location) + '</span>');
        if (item.other_numbers && item.other_numbers.length) {
            bits.push('<span style="color:var(--gray-600)">Also numbered ' + esc(item.other_numbers.join(', ')) + '</span>');
        }
        if (item.open_receipt) bits.push(badge('rejected', 'Already in the queue as ' + item.open_receipt));
        if (item.decommissioned) {
            bits.push(badge('rejected', 'Decommissioned' + (item.successor ? ': now ' + item.successor : '')));
        }
        if (notes) notes.innerHTML = bits.join('');
    }

    if (file && hasS2) {
        var $file = $(file);
        $file.select2({
            width: '100%',
            placeholder: file.dataset.placeholder,
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: file.dataset.url,
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term || '', source: source ? source.value : '' }; },
                processResults: function (data) { return data; }
            },
            templateResult: function (item) {
                if (item.loading || !item.address) return item.text;
                return $('<div>').append(
                    $('<div>').text(item.text),
                    $('<div style="font-size:11px;opacity:.7">').text([item.type, item.plot ? 'Plot ' + item.plot : '', item.location].filter(Boolean).join(' · '))
                );
            }
        });
        $file.on('select2:select', function (e) { apply(e.params.data); });
        $file.on('select2:clear', release);
    }

    if (source && file) {
        source.addEventListener('change', function () {
            // A file belongs to one source; changing the source drops the pick.
            if (hasS2) $(file).val(null).trigger('change'); else file.value = '';
            release();
            file.disabled = !source.value;
            if (hasS2) $(file).prop('disabled', !source.value);
        });
    }

    // Re-rendered after a failed save, or the edit form: lock what the file supplies.
    if (group.dataset.picked) {
        try { apply(JSON.parse(group.dataset.picked)); } catch (e) { /* leave the form open */ }
    }
}, 0); });
</script>
