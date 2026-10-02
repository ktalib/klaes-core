{{--
    Reusable KLAES address builder.

    Usage:
        @include('survey_module.partials._address_builder', [
            'prefix'  => 'prop_',          // column prefix: prop_ or addr_
            'mode'    => 'property',       // 'property' | 'person'
            'model'   => $case ?? null,    // optional, for edit forms
            'legend'  => 'Property Location',
        ])

    Emits the eight sub-fields the convention expects:
        {prefix}house, {prefix}plot, {prefix}street, {prefix}street_other,
        {prefix}district, {prefix}district_other, {prefix}lga, {prefix}state

    District (1,818 rows) and street (826) are fetched over AJAX from the
    survey-module.lookup.* endpoints, searched and paged server-side. Only the
    currently-selected value is rendered as an <option>; inlining every row cost
    ~244 KB per form page and made the markup the slowest part of the page.

    LGA (45) and State (37) are small enough to inline.

    Choosing "Other" reveals a free-text box whose value wins when composing.
    Plot number beats house number, so only one ever appears in the address.
--}}
@php
    use App\Http\Controllers\Survey\LookupController;

    $prefix = $prefix ?? 'prop_';
    $mode   = $mode ?? 'property';
    $model  = $model ?? null;
    $legend = $legend ?? ($mode === 'person' ? 'Address' : 'Property Location');

    $val = function (string $suffix) use ($prefix, $model) {
        return old($prefix . $suffix, $model->{$prefix . $suffix} ?? ($suffix === 'state' ? 'Kano' : ''));
    };

    // Small enough to ship inline.
    $lgas   = LookupController::options('lgas');
    $states = LookupController::options('states');

    // Stored columns hold several names joined with " | "; split them back so
    // each one renders as a selected <option>.
    $curDistrict = \App\Support\AddressBuilder::split($val('district'));
    $curStreet   = \App\Support\AddressBuilder::split($val('street'));
    $curLgas     = \App\Support\AddressBuilder::split($val('lga'));
@endphp

<div class="address-builder"
     data-prefix="{{ $prefix }}"
     data-mode="{{ $mode }}"
     data-districts-url="{{ route('survey-module.lookup.districts') }}"
     data-streets-url="{{ route('survey-module.lookup.streets') }}">

    <div class="ab-legend">{{ $legend }}</div>

    <div class="form-grid">
        <div class="form-group">
            <label>House No.</label>
            <input type="text" name="{{ $prefix }}house" value="{{ $val('house') }}" placeholder="e.g. 12" />
        </div>

        <div class="form-group">
            <label>Plot No.</label>
            <input type="text" name="{{ $prefix }}plot" value="{{ $val('plot') }}" placeholder="e.g. 4" />
            <p class="helper-text">If both are given, the plot number is used.</p>
        </div>

        <div class="form-group">
            <label>Street Name</label>
            <select name="{{ $prefix }}street[]" class="ab-street" multiple data-placeholder="Search street…">
                @foreach ($curStreet as $v)
                    <option value="{{ $v }}" selected>{{ $v === 'Other' ? 'Other (specify)' : $v }}</option>
                @endforeach
            </select>
            <p class="helper-text">Type to search {{ number_format(count(LookupController::options('streets'))) }} streets. Pick more than one for a corner plot.</p>
        </div>

        <div class="form-group ab-other ab-street-other" @style(['display:none' => !in_array('Other', $curStreet, true)])>
            <label>Specify Street <span class="required">*</span></label>
            <input type="text" name="{{ $prefix }}street_other" value="{{ $val('street_other') }}"
                   placeholder="Type the street name" />
        </div>

        <div class="form-group">
            <label>District <span class="required">*</span></label>
            <select name="{{ $prefix }}district[]" class="ab-district" multiple data-placeholder="Search district…">
                @foreach ($curDistrict as $v)
                    <option value="{{ $v }}" selected>{{ $v === 'Other' ? 'Other (specify)' : $v }}</option>
                @endforeach
            </select>
            <p class="helper-text">Type to search {{ number_format(count(LookupController::options('districts'))) }} districts. Pick more than one if the parcel straddles a boundary.</p>
        </div>

        <div class="form-group ab-other ab-district-other" @style(['display:none' => !in_array('Other', $curDistrict, true)])>
            <label>Specify District <span class="required">*</span></label>
            <input type="text" name="{{ $prefix }}district_other" value="{{ $val('district_other') }}"
                   placeholder="Type the district name" />
        </div>

        <div class="form-group">
            <label>LGA <span class="required">*</span></label>
            <select name="{{ $prefix }}lga[]" class="ab-lga" multiple data-placeholder="Select LGA…">
                @foreach ($lgas as $l)
                    <option value="{{ $l }}" @selected(in_array($l, $curLgas, true))>{{ $l }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>State <span class="required">*</span></label>
            <select name="{{ $prefix }}state" class="ab-state" data-placeholder="Select state…">
                @foreach ($states as $st)
                    <option value="{{ $st }}" @selected($val('state') === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group full">
            <label>{{ $mode === 'person' ? 'Full Address' : 'Property Location' }} (auto)</label>
            <input type="text" class="ab-preview" readonly
                   style="background:var(--gray-100);font-weight:600;color:var(--primary);"
                   placeholder="{{ $mode === 'person' ? 'Street, Plot, District, LGA, State' : 'District, LGA, State' }}" />
            <p class="helper-text">
                Built from the fields above. Stored as separate columns; this is the composed form.
            </p>
        </div>
    </div>
</div>
