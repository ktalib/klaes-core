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
    $states = LookupController::options('states');
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
            <input class="ks-input ab-street" id="addr_street" name="addr_street" value="{{ $val('street') }}" maxlength="255" placeholder="Type the street name">
            @error('addr_street')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="ks-label" for="addr_district">District</label>
            <input class="ks-input ab-district" id="addr_district" name="addr_district" value="{{ $val('district') }}" maxlength="255" placeholder="Type the district">
            @error('addr_district')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="addr_lga">LGA <span class="ks-req">*</span></label>
            <input class="ks-input ab-lga" id="addr_lga" name="addr_lga" value="{{ $val('lga') }}" maxlength="255" placeholder="Type the LGA" required>
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
