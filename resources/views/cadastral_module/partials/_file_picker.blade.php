{{--
    The Cadastral module's one file picker.

    A greyed, disabled File Number box filled only by the app-wide global
    file-number selector (components.global-fileno-modal, opened through
    GlobalFileNoModal.open — wrapped here, never edited). The picked number is
    sent to cadastral-module.lookup.file, which says which indexed file it is,
    what the module already holds for it, and whether this form may take it.

    On a good pick the script below:
      - sets the hidden ids the form posts (only those named in 'hidden');
      - fills every form field whose name matches a value the file supplies
        (CadastralRegistryLookup::VALUE_FIELDS, plus 'map' renames), and
        disables + greys each one that received a value; a field the records
        leave blank stays open for the clerk;
      - fills [data-fp-value="key"] display boxes (file_number, owner, type,
        land_use, location, source, registry_label);
      - shows a summary card with the file's flags;
      - fires 'cadastral:file-picked' on the form (detail = the payload).
    Several matching index rows come back as a list to choose from; a file the
    scope refuses shows why and posts nothing.

    The server is the control, not this: every store()/update() re-reads the
    file from its records and merges the supplied values over the request
    (CadastralRegistryLookup::lockedInput) before validating.

    Usage:
        @include('cadastral_module.partials._file_picker', [
            'scope'   => 'receipt',          // intake | indexed | receipt | plan | card
            'purpose' => 'chart',            // optional: chart | commission
            'hidden'  => ['cadastral_file_receipt_id'],
            'initial' => $picked,            // resolveFile() payload, for a re-render or a preload
            'fixed'   => false,              // true on an edit form: the file cannot change
            'navigate'=> null,               // a URL: lookup-only, go to URL?file_number=…
            'label'   => 'File Number',
            'help'    => '…',
            'map'     => [],                 // value key => form field name
        ])
--}}
@php
    $fpScope    = $scope ?? 'indexed';
    $fpPurpose  = $purpose ?? null;
    $fpHidden   = $hidden ?? [];
    $fpInitial  = $initial ?? null;
    $fpFixed    = (bool) ($fixed ?? false);
    $fpNavigate = $navigate ?? null;
    $fpLabel    = $label ?? 'File Number';
    $fpHelp     = $help ?? null;
    $fpMap      = $map ?? [];
    $fpRequired = (bool) ($required ?? ! $fpNavigate);
    $fpNumber   = $fpInitial['file']['file_number'] ?? ($fpInitial['query']['file_number'] ?? ($number ?? ''));
@endphp

<div class="form-group full cad-fp"
     data-fp
     data-url="{{ route('cadastral-module.lookup.file') }}"
     data-scope="{{ $fpScope }}"
     data-purpose="{{ $fpPurpose }}"
     data-map="{{ json_encode((object) $fpMap) }}"
     data-initial="{{ $fpInitial ? json_encode($fpInitial) : '' }}"
     @if ($fpFixed) data-fixed="1" @endif
     @if ($fpNavigate) data-navigate="{{ $fpNavigate }}" @endif
     @if ($fpRequired) data-required="1" @endif>

    <label>{{ $fpLabel }} @if ($fpRequired)<span class="required">*</span>@endif</label>

    <div class="cad-fp-row">
        {{-- Never typed into and never posted: the selector is the only way in. --}}
        <input type="text" class="cad-fp-number cad-locked" value="{{ $fpNumber }}" disabled
               placeholder="{{ $fpFixed ? '' : 'No file selected — use Select File Number' }}" />

        @unless ($fpFixed)
            <button type="button" class="btn btn-primary cad-fp-open">
                <i data-lucide="folder-search" style="width:16px;height:16px;"></i>
                {{ $fpNavigate ? 'Select File Number to Check' : 'Select File Number' }}
            </button>
            @unless ($fpNavigate)
                <button type="button" class="btn btn-outline cad-fp-clear" title="Clear the selected file" style="display:none;">
                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                </button>
            @endunless
        @endunless
    </div>

    @foreach ($fpHidden as $h)
        <input type="hidden" name="{{ $h }}" data-fp-hidden
               value="{{ old($h, $fpInitial['hidden'][$h] ?? '') }}" />
    @endforeach

    @if ($fpHelp)
        <div class="helper-text">{{ $fpHelp }}</div>
    @endif

    <div class="cad-fp-status" style="display:none;"></div>
    <div class="cad-fp-choices" style="display:none;"></div>
    <div class="cad-fp-card" style="display:none;"></div>
</div>

@once
    @push('scripts')
        {{-- The shared selector, outside the .survey-proto wrapper so the
             prototype CSS cannot reach it. Its own script initialises it. --}}
        @include('components.global-fileno-modal')
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        @include('cadastral_module.partials._file_picker_js')
    @endpush
@endonce
