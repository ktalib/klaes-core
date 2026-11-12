{{--
    Address builder for the SG's Add Company / Add Surveyor forms (MLPP-SG Portal Admin > Registrations, 2026-11-10):
    a copy of the portal's own (surveyor_portal.partials._address_builder) in the staff page's form layout. As on the
    portal (user, 2026-11-10: "a surveyor can be from any state, not just Kano"), State comes before LGA and the LGA
    list is the chosen state's; District is optional. Lookups are the portal's public, throttled ones
    (surveyor-portal.lookup.*), so the SG needs neither Cad - Records nor Survey - Records. The JavaScript is the shared
    survey_module.partials._address_builder_js, loaded by the Survey layout. Saved to the eight addr_* columns.
--}}
@php
    use App\Http\Controllers\Survey\LookupController;

    $prefix = 'addr_';
    $legend = $legend ?? 'Address';
    // $fill: a record's values for an Edit pop-up (2026-11-12); a failed save's old() input wins.
    $fill = $fill ?? [];
    $val = fn (string $suffix) => old($prefix . $suffix, ($fill[$prefix . $suffix] ?? null) ?: ($suffix === 'state' ? 'Kano' : ''));
    $states = LookupController::options('states');
@endphp

<div class="address-builder" data-prefix="{{ $prefix }}" data-mode="person"
     data-districts-url="{{ route('surveyor-portal.lookup.districts') }}"
     data-streets-url="{{ route('surveyor-portal.lookup.streets') }}"
     data-lgas-url="{{ route('surveyor-portal.lookup.lgas') }}">

    <div class="ab-legend">{{ $legend }}</div>

    <div class="form-grid">
        <div class="form-group">
            <label>House No.</label>
            <input type="text" name="addr_house" value="{{ $val('house') }}" maxlength="100" placeholder="e.g. 12" />
        </div>
        <div class="form-group">
            <label>Plot No.</label>
            <input type="text" name="addr_plot" value="{{ $val('plot') }}" maxlength="100" placeholder="e.g. 4" />
            <p class="helper-text">If both are given, the plot number is used.</p>
        </div>
        <div class="form-group">
            <label>Street Name</label>
            <input type="text" class="ab-street" name="addr_street" value="{{ $val('street') }}" maxlength="255" placeholder="Type the street name" />
        </div>
        <div class="form-group">
            <label>District</label>
            <input type="text" class="ab-district" name="addr_district" value="{{ $val('district') }}" maxlength="255" placeholder="Type the district" />
        </div>
        <div class="form-group">
            <label>State <span class="required">*</span></label>
            <select class="ab-state" name="addr_state" data-placeholder="Select state…" data-no-search required>
                @foreach ($states as $st)
                    <option value="{{ $st }}" @selected($val('state') === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>LGA <span class="required">*</span></label>
            <select class="ab-lga" name="addr_lga" data-placeholder="Select state first…" data-no-search required disabled>
                <option value="">Select state first…</option>
                @if ($val('lga'))
                    <option value="{{ $val('lga') }}" selected>{{ $val('lga') }}</option>
                @endif
            </select>
            <p class="helper-text ab-lga-error" role="alert" style="color:#b91c1c;" hidden>Could not load LGAs for this state. Please try again.</p>
        </div>
        <div class="form-group full">
            <label>Full Address (auto)</label>
            {{-- Composed from the fields above; it has no name, so it never submits. --}}
            <input type="text" class="ab-preview" readonly disabled tabindex="-1" aria-readonly="true" data-locked="always"
                   style="background:var(--gray-100);font-weight:600;cursor:not-allowed;" placeholder="Street, Plot, District, LGA, State" />
        </div>
    </div>
</div>
