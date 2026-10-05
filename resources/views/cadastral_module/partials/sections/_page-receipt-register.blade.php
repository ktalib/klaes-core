@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($receipt->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.registry.receipts.update', $receipt)
        : route('cadastral-module.registry.receipts.store');

    // The picked file as CadastralRegistryLookup::resolveFile() describes it
    // (FileReceiptController::create/edit). Its supplied values are put on the
    // in-memory model so the builder renders them before the picker locks
    // them; nothing saves.
    $picked  = $picked ?? null;
    $sources = $sources ?? [];
    if ($picked && in_array($picked['status'], ['ok', 'refused'], true)) {
        foreach (\App\Services\Cadastral\CadastralRegistryLookup::lockedInput($picked['values'], \App\Services\Cadastral\CadastralRegistryLookup::VALUE_FIELDS) as $col => $value) {
            if (str_starts_with($col, 'prop_')) $receipt->{$col} = $value;
        }
    }
@endphp

<div class="page-header">
    <div>@if ($isEdit) <strong>{{ $receipt->receipt_ref }}</strong> · {{ $receipt->file_number }} @endif</div>
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

@include('cadastral_module.partials._wizard')

<form method="POST" action="{{ $action }}" class="form-container" data-wizard
      data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-stepper" data-wizard-header></div>

    <div class="form-body">

        {{-- 1. The file. Only the global selector can set it; the source is
             read from the file's own registry, not chosen. --}}
        <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                 data-subtitle="{{ $isEdit ? 'The file is fixed once logged. If the wrong file was logged, return or reject this receipt and log the right one.' : 'Pick the incoming file with the file-number selector. Its source department is read from the file index.' }}">
            <div class="form-grid">
                @include('cadastral_module.partials._file_picker', [
                    'scope'   => 'intake',
                    'hidden'  => $isEdit ? [] : ['file_indexing_id', 'source_registry'],
                    'initial' => $picked,
                    'fixed'   => $isEdit,
                    'number'  => $isEdit ? $receipt->file_number : '',
                    'help'    => $isEdit ? null : 'Files can be logged in from ' . implode(', ', $sources) . '. Deeds is not supported yet. A file already open in the queue, or decommissioned, is refused.',
                ])

                <div class="form-group">
                    <label>Source Department</label>
                    <input type="text" data-fp-value="source" class="cad-locked" disabled
                           value="{{ $isEdit ? $receipt->source_registry : ($picked['file']['source'] ?? '') }}"
                           placeholder="Read from the file index" />
                </div>
                <div class="form-group">
                    <label>Registry</label>
                    <input type="text" data-fp-value="registry_label" class="cad-locked" disabled
                           value="{{ $picked['file']['registry_label'] ?? '' }}" placeholder="Read from the file index" />
                </div>
            </div>
        </section>

        {{-- 2. What the file index says about it. Supplied values are locked;
             blanks stay open, and the server re-reads the file on save. --}}
        <section class="form-step" data-step data-title="File Details" data-icon="file-text"
                 data-subtitle="Filled from the file index. Greyed fields come from the file; complete any the index leaves blank.">
            <div class="form-grid">
                <div class="form-group">
                    <label>Owner / Title</label>
                    <input type="text" data-fp-value="owner" class="cad-locked" disabled
                           value="{{ $picked['file']['owner'] ?? $receipt->file_title }}" placeholder="From the file index" />
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <input type="text" data-fp-value="type" class="cad-locked" disabled
                           value="{{ $picked['file']['type'] ?? ($isEdit ? \App\Services\Cadastral\CadastralRegistryLookup::typeLabel($receipt->file_number, $receipt->source_registry, $receipt->file_class) : '') }}"
                           placeholder="From the file number" />
                </div>
                <div class="form-group">
                    <label>Land Use</label>
                    <input type="text" data-fp-value="land_use" class="cad-locked" disabled
                           value="{{ $picked['file']['land_use'] ?? '' }}" placeholder="From the file index" />
                </div>
            </div>

            {{-- District, LGA and State compose "District, LGA, Kano". The plot
                 number is its own field and never appears in the location. --}}
            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $receipt,
                'legend' => 'Property Location',
            ])
        </section>

        {{-- 3. The receipt itself: who brought it, when, why, and its state. --}}
        <section class="form-step" data-step data-title="Receipt Details" data-icon="inbox"
                 data-subtitle="How and when the physical file arrived in Cadastral.">
            <div class="form-grid">
                <div class="form-group">
                    <label>Received From</label>
                    <input type="text" name="received_from" value="{{ old('received_from', $receipt->received_from) }}"
                           placeholder="Officer or desk" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Received At <span class="required">*</span></label>
                    <input type="datetime-local" name="received_at"
                           value="{{ old('received_at', optional($receipt->received_at)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}"
                           required />
                </div>

                <div class="form-group">
                    <label>Purpose</label>
                    <select name="purpose" id="intake-purpose">
                        <option value="">—</option>
                        @foreach (\App\Models\Cadastral\CadastralFileReceipt::PURPOSES as $p)
                            <option value="{{ $p }}" @selected(old('purpose', $receipt->purpose)===$p)>{{ $p }}</option>
                        @endforeach
                    </select>
                    <div class="helper-text">A conversion file cannot come in for Charting.</div>
                </div>

                <div class="form-group">
                    <label>Their Reference</label>
                    <input type="text" name="source_reference" value="{{ old('source_reference', $receipt->source_reference) }}"
                           placeholder="Dispatch / transmittal number" maxlength="100" />
                </div>

                <div class="form-group">
                    <label>Pages</label>
                    <input type="number" name="num_pages" min="0" max="100000" value="{{ old('num_pages', $receipt->num_pages) }}" />
                </div>

                <div class="form-group">
                    <label>Shelf Location</label>
                    <input type="text" name="shelf_location" value="{{ old('shelf_location', $receipt->shelf_location) }}"
                           placeholder="Derived from the workbooks if left blank" maxlength="100" />
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

                <div class="form-group full" style="grid-column:1/-1;">
                    <label>Condition</label>
                    <input type="text" name="condition_note" value="{{ old('condition_note', $receipt->condition_note) }}"
                           placeholder="Anything wrong with the physical file" maxlength="500" />
                </div>
            </div>
        </section>

        <section class="form-step" data-step data-review data-title="{{ $isEdit ? 'Review & Save' : 'Review & Log' }}" data-icon="clipboard-check"
                 data-subtitle="Check everything below. {{ $isEdit ? 'Saving' : 'Logging' }} re-reads the file from the index, so the greyed values are the index's, whatever this page shows.">
            <div data-wizard-summary></div>
        </section>
    </div>

    <div class="form-actions" data-wizard-nav>
        <a href="{{ route('cadastral-module.registry.receipts') }}" class="btn btn-secondary">Cancel</a>
        <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
        <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary" data-wizard-submit>
                <i data-lucide="{{ $isEdit ? 'save' : 'file-input' }}"></i> {{ $isEdit ? 'Save Changes' : 'Log File' }}
            </button>
        @endcanDo
    </div>
</form>

{{-- A conversion file is not charted, so it cannot come in for Charting; the
     server refuses it too (FileReceiptController::guardConversionPurpose). --}}
<script>
document.addEventListener('cadastral:file-picked', function (e) {
    var purpose = document.getElementById('intake-purpose');
    if (!purpose) return;
    var p = e.detail;
    var conversion = !!(p && p.file && p.file.file_class === 'conversion');
    Array.prototype.forEach.call(purpose.options, function (o) {
        if (o.value === 'Charting') o.disabled = conversion;
    });
    if (conversion && purpose.value === 'Charting') purpose.value = '';
});
</script>
