{{--
    Canonical "issuing LGA" option list for an Occupancy Permit granted by a Local
    Government rather than by the Kano State Government. Shared by every screen that
    captures an OP, so the name saved as party_1 is byte-identical across all of them:
      - fileindexing  → partial/property_transaction_modal   (Transaction History card)
      - propertycard  → partials/add_pra/instrument_section  (PRA form)
      - fileindexing  → addons/partials/sections/occupancy_permit (create indexing card)

    The 44 names come from App\Services\KanoLgaDirectory — see that class for why they are
    stated there rather than read from the `lgas` lookup table.

    Params:
      $selected    (string|null) currently stored value, so edit screens re-select it.
      $placeholder (string|null) label for the empty option; pass null to skip it.
--}}
@php
    $kanoLgaOptions = app(\App\Services\KanoLgaDirectory::class)->fullNames();
    $kanoLgaSelected = isset($selected) ? trim((string) $selected) : '';
    $kanoLgaPlaceholder = array_key_exists('placeholder', get_defined_vars()) ? $placeholder : 'Select Local Government';
@endphp
@if($kanoLgaPlaceholder !== null)
    <option value="" {{ $kanoLgaSelected === '' ? 'selected' : '' }}>{{ $kanoLgaPlaceholder }}</option>
@endif
@foreach($kanoLgaOptions as $kanoLgaOption)
    <option value="{{ $kanoLgaOption }}" {{ $kanoLgaSelected === $kanoLgaOption ? 'selected' : '' }}>{{ $kanoLgaOption }}</option>
@endforeach
{{-- A stored value that is not one of the 44 (a legacy hand-typed grantor) stays
     selectable, so opening an old record does not silently blank its party_1. --}}
@if($kanoLgaSelected !== '' && !in_array($kanoLgaSelected, $kanoLgaOptions, true))
    <option value="{{ $kanoLgaSelected }}" selected>{{ $kanoLgaSelected }}</option>
@endif
