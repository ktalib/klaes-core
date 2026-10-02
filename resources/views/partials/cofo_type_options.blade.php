{{--
    Canonical "CofO Type" option list, shared by every CofO Type dropdown:
      - propertycard  → partials/add_pra/instrument_section
      - fileindexing  → addons/partials/sections/cofo_details
      - fileindexing  → addons/partials/sections/occupancy_permit
      - fileindexing  → partial/property_transaction_modal ("Add Property Transaction Details")
      - fileindexing  → edit

    Params:
      $selected    (string|null) currently stored value, so edit screens re-select it.
      $placeholder (string|null) label for the empty option; omit to skip the empty option.

    Legacy values already in CofO_staging.cofo_type ("Old CofO (Ministry)", and the
    never-saved "KANGIS CofO" / "New KANGIS CofO") are appended as a disabled-looking
    extra option ONLY when a record still carries one, so editing an old row does not
    silently blank its type.
--}}
@php
    $cofoTypeOptions = [
        'Land CofO',
        'KANGIS CofO - Old',
        'KANGIS CofO - New',
        'SLTR CofO',
        'ST CofO',
    ];
    $cofoTypeSelected = isset($selected) ? trim((string) $selected) : '';
    $cofoTypePlaceholder = $placeholder ?? 'Select CofO type';
@endphp
@if($cofoTypePlaceholder !== null)
    <option value="" {{ $cofoTypeSelected === '' ? 'selected' : '' }}>{{ $cofoTypePlaceholder }}</option>
@endif
@foreach($cofoTypeOptions as $cofoTypeOption)
    <option value="{{ $cofoTypeOption }}" {{ $cofoTypeSelected === $cofoTypeOption ? 'selected' : '' }}>{{ $cofoTypeOption }}</option>
@endforeach
@if($cofoTypeSelected !== '' && !in_array($cofoTypeSelected, $cofoTypeOptions, true))
    <option value="{{ $cofoTypeSelected }}" selected>{{ $cofoTypeSelected }} (legacy)</option>
@endif
