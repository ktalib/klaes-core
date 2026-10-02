{{--
    Reusable KLAES address builder -- Cadastral Module copy.

    Usage:
        @include('cadastral_module.partials._address_builder', [
            'prefix'  => 'prop_',          // column prefix: prop_ or addr_
            'mode'    => 'property',       // 'property' | 'person'
            'model'   => $case ?? null,    // optional, for edit forms
            'legend'  => 'Property Location',
            'plotField' => 'plot_no',      // optional, see below
        ])

    Emits the eight sub-fields the convention expects:
        {prefix}house, {prefix}plot, {prefix}street, {prefix}street_other,
        {prefix}district, {prefix}district_other, {prefix}lga, {prefix}state

    District (1,818 rows) and street (826) are fetched over AJAX from the
    cadastral-module.lookup.* endpoints, searched and paged server-side. Only the
    currently-selected value is rendered as an <option>; inlining every row cost
    ~244 KB per form page and made the markup the slowest part of the page.

    LGA (45) and State (37) are small enough to inline.

    Choosing "Other" reveals a free-text box whose value wins when composing.
    Plot number beats house number, so only one ever appears in the address.

    WHY THIS IS A COPY OF THE SURVEY PARTIAL RATHER THAN AN INCLUDE OF IT.

    The Survey version hardcodes route('survey-module.lookup.districts|streets'),
    and those route names map to the module "Survey - Records". A user who holds
    Cad - Records but not Survey - Records would get a 403 from the AJAX call and
    silently empty district and street dropdowns. Only the two URLs differ; the
    JavaScript (survey_module.partials._address_builder_js) reads them off the
    data-* attributes and is shared unchanged.

    Do not "fix" this by editing the Survey partial -- it is shared.

    THE ONE ADDITION: plotField.

    In the Survey module the builder's Plot No. box IS the parcel's plot field.
    Charts, index cards and reports here also have their own plot_no column,
    shown higher up the form, so the builder's box would be a second plot
    number free to disagree with the first. Pass 'plotField' => 'plot_no' and
    the box is left out; CadastralAddress::normalise() then copies plot_no into
    prop_plot on save. Property mode only -- a person address composes the plot.
    Without plotField the markup is identical to the Survey partial.
--}}
@php
    use App\Http\Controllers\Survey\LookupController;

    $prefix = $prefix ?? 'prop_';
    $mode   = $mode ?? 'property';
    $model  = $model ?? null;
    $legend = $legend ?? ($mode === 'person' ? 'Address' : 'Property Location');
    $plotField = $mode === 'property' ? ($plotField ?? null) : null;

    $val = function (string $suffix) use ($prefix, $model) {
        return old($prefix . $suffix, $model->{$prefix . $suffix} ?? ($suffix === 'state' ? 'Kano' : ''));
    };

    // Small enough to ship inline.
    $lgas   = LookupController::options('lgas');
    $states = LookupController::options('states');

    $curDistrict = $val('district');
    $curStreet   = $val('street');
@endphp

<div class="address-builder"
     data-prefix="{{ $prefix }}"
     data-mode="{{ $mode }}"
     data-districts-url="{{ route('cadastral-module.lookup.districts') }}"
     data-streets-url="{{ route('cadastral-module.lookup.streets') }}">

    <div class="ab-legend">{{ $legend }}</div>

    <div class="form-grid">
        <div class="form-group">
            <label>House No.</label>
            <input type="text" name="{{ $prefix }}house" value="{{ $val('house') }}" placeholder="e.g. 12" />
        </div>

        @if ($plotField === null)
            <div class="form-group">
                <label>Plot No.</label>
                <input type="text" name="{{ $prefix }}plot" value="{{ $val('plot') }}" placeholder="e.g. 4" />
                <p class="helper-text">If both are given, the plot number is used.</p>
            </div>
        @endif

        <div class="form-group">
            <label>Street Name</label>
            <select name="{{ $prefix }}street" class="ab-street" data-placeholder="Search street…">
                <option value=""></option>
                @if ($curStreet !== '')
                    <option value="{{ $curStreet }}" selected>{{ $curStreet === 'Other' ? 'Other (specify)' : $curStreet }}</option>
                @endif
            </select>
            <p class="helper-text">Type to search {{ number_format(count(LookupController::options('streets'))) }} streets.</p>
        </div>

        <div class="form-group ab-other ab-street-other" @style(['display:none' => $curStreet !== 'Other'])>
            <label>Specify Street <span class="required">*</span></label>
            <input type="text" name="{{ $prefix }}street_other" value="{{ $val('street_other') }}"
                   placeholder="Type the street name" />
        </div>

        <div class="form-group">
            <label>District <span class="required">*</span></label>
            <select name="{{ $prefix }}district" class="ab-district" data-placeholder="Search district…">
                <option value=""></option>
                @if ($curDistrict !== '')
                    <option value="{{ $curDistrict }}" selected>{{ $curDistrict === 'Other' ? 'Other (specify)' : $curDistrict }}</option>
                @endif
            </select>
            <p class="helper-text">Type to search {{ number_format(count(LookupController::options('districts'))) }} districts.</p>
        </div>

        <div class="form-group ab-other ab-district-other" @style(['display:none' => $curDistrict !== 'Other'])>
            <label>Specify District <span class="required">*</span></label>
            <input type="text" name="{{ $prefix }}district_other" value="{{ $val('district_other') }}"
                   placeholder="Type the district name" />
        </div>

        <div class="form-group">
            <label>LGA <span class="required">*</span></label>
            <select name="{{ $prefix }}lga" class="ab-lga" data-placeholder="Select LGA…">
                <option value=""></option>
                @foreach ($lgas as $l)
                    <option value="{{ $l }}" @selected($val('lga') === $l)>{{ $l }}</option>
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
