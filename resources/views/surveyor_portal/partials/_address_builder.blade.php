{{--
    The KLAES address builder, portal copy (user, 2026-11-05: "we forget to add address, use address builder form").

    Person mode of cadastral_module.partials._address_builder: House, Plot, Street, District, LGA, State, with the
    composed address beneath. A copy because the staff builders search through lookup routes that need a staff
    login, and someone registering has none; the JavaScript (survey_module.partials._address_builder_js) reads the
    two URLs off the data-* attributes and is shared unchanged. Only the markup and the look are the portal's own.

    Saved to the eight addr_* columns; validated by CadastralAddress::rules('addr_').
--}}
@php
    use App\Http\Controllers\Survey\LookupController;

    $prefix = 'addr_';
    $val = fn (string $suffix) => old($prefix . $suffix, $suffix === 'state' ? 'Kano' : '');
    $lgas = LookupController::options('lgas');
    $states = LookupController::options('states');
    $curDistrict = $val('district');
    $curStreet = $val('street');
@endphp

<div class="address-builder ks-address" data-prefix="{{ $prefix }}" data-mode="person"
     data-districts-url="{{ route('surveyor-portal.lookup.districts') }}"
     data-streets-url="{{ route('surveyor-portal.lookup.streets') }}">

    <div class="ab-legend">Address</div>

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <label class="ks-label" for="addr_house">House No.</label>
            <input class="ks-input" id="addr_house" name="addr_house" value="{{ $val('house') }}" maxlength="100" placeholder="e.g. 12">
            @error('addr_house')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="addr_plot">Plot No.</label>
            <input class="ks-input" id="addr_plot" name="addr_plot" value="{{ $val('plot') }}" maxlength="100" placeholder="e.g. 4">
            @error('addr_plot')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <p class="ks-help sm:col-span-2" style="margin-top:-6px;font-size:12px;">If both are given, the plot number is used.</p>

        <div class="sm:col-span-2">
            <label class="ks-label" for="addr_street">Street Name</label>
            <select class="ks-input ab-street" id="addr_street" name="addr_street" data-placeholder="Search street…">
                <option value=""></option>
                @if ($curStreet !== '')
                    <option value="{{ $curStreet }}" selected>{{ $curStreet === 'Other' ? 'Other (specify)' : $curStreet }}</option>
                @endif
            </select>
            @error('addr_street')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 ab-other ab-street-other" @style(['display:none' => $curStreet !== 'Other'])>
            <label class="ks-label" for="addr_street_other">Specify Street <span class="ks-req">*</span></label>
            <input class="ks-input" id="addr_street_other" name="addr_street_other" value="{{ $val('street_other') }}" maxlength="255" placeholder="Type the street name">
            @error('addr_street_other')<p class="ks-error">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label class="ks-label" for="addr_district">District</label>
            <select class="ks-input ab-district" id="addr_district" name="addr_district" data-placeholder="Search district…">
                <option value=""></option>
                @if ($curDistrict !== '')
                    <option value="{{ $curDistrict }}" selected>{{ $curDistrict === 'Other' ? 'Other (specify)' : $curDistrict }}</option>
                @endif
            </select>
            @error('addr_district')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 ab-other ab-district-other" @style(['display:none' => $curDistrict !== 'Other'])>
            <label class="ks-label" for="addr_district_other">Specify District <span class="ks-req">*</span></label>
            <input class="ks-input" id="addr_district_other" name="addr_district_other" value="{{ $val('district_other') }}" maxlength="255" placeholder="Type the district name">
            @error('addr_district_other')<p class="ks-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="ks-label" for="addr_lga">LGA <span class="ks-req">*</span></label>
            <select class="ks-input ab-lga" id="addr_lga" name="addr_lga" data-placeholder="Select LGA…">
                <option value=""></option>
                @foreach ($lgas as $l)
                    <option value="{{ $l }}" @selected($val('lga') === $l)>{{ $l }}</option>
                @endforeach
            </select>
            @error('addr_lga')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="addr_state">State <span class="ks-req">*</span></label>
            <select class="ks-input ab-state" id="addr_state" name="addr_state" data-placeholder="Select state…">
                @foreach ($states as $st)
                    <option value="{{ $st }}" @selected($val('state') === $st)>{{ $st }}</option>
                @endforeach
            </select>
            @error('addr_state')<p class="ks-error">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label class="ks-label">Full Address (auto)</label>
            {{-- Composed from the fields above; it has no name, so it never submits. --}}
            <input type="text" class="ks-input ab-preview" readonly disabled tabindex="-1" aria-readonly="true" data-locked="always"
                   style="background:#E5E7EB;color:#4B5563;font-weight:600;cursor:not-allowed;" placeholder="Street, Plot, District, LGA, State">
        </div>
    </div>
    <p id="ksAddressError" class="ks-error" role="alert" style="display:none;"></p>
</div>
