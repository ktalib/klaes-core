<!-- Right column - Property Description -->
<div class="form-section" data-section="property-description">
    <h4 class="form-section-title">Property Description</h4>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="houseNo" class="text-xs text-gray-600">House No</label>
                <input id="houseNo" name="house_no" type="text" class="form-input text-sm property-input"
                       data-model="houseNo" value="{{ old('house_no') }}">
            </div>
            <div>
                <label for="plotNo" class="text-xs text-gray-600">Plot No.</label>
                <input id="plotNo" name="plot_no" type="text" class="form-input text-sm property-input"
                       placeholder="Enter plot number" data-model="plotNo" value="{{ old('plot_no') }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3" data-section="property-location">
            @include('components.StreetName2')
            @include('components.District')
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="lga" class="text-xs text-gray-600">LGA</label>
               @include('propertycard.partials.lga')
            </div>

            <div>
                <label for="state" class="text-xs text-gray-600">State</label>
                <input id="state" name="state" type="text" class="form-input text-sm property-input"
                       placeholder="Enter state" value="{{ old('state', 'Kano') }}" data-model="state">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="tp_no_prop" class="text-xs text-gray-600">
                    TP No
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="tp_no_prop" name="tp_no" type="text" class="form-input text-sm property-input"
                       placeholder="Enter TP number (optional)" data-model="tpNo" value="{{ old('tp_no') }}"
                       data-index-class="border-blue-300 focus:border-blue-500"
                       data-index-placeholder="Enter TP number (required)">
            </div>
            <div>
                <label for="lpkn_no_prop" class="text-xs text-gray-600">
                    LPKN No
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="lpkn_no_prop" name="lpkn_no" type="text" class="form-input text-sm property-input"
                       placeholder="Enter LPKN number (optional)" data-model="lpknNo" value="{{ old('lpkn_no') }}"
                       data-index-class="border-blue-300 focus:border-blue-500"
                       data-index-placeholder="Enter LPKN number (required)">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="approved_plan_no_prop" class="text-xs text-gray-600">
                    Approved Plan No
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="approved_plan_no_prop" name="approved_plan_no" type="text" class="form-input text-sm property-input"
                       placeholder="Enter approved plan number (optional)" data-model="approvedPlanNo"
                       value="{{ old('approved_plan_no') }}" data-index-class="border-blue-300 focus:border-blue-500"
                       data-index-placeholder="Enter approved plan number (required)">
            </div>
            <div>
                <label for="plot_size_prop" class="text-xs text-gray-600">
                    Plot Size
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="plot_size_prop" name="plot_size" type="text" class="form-input text-sm property-input"
                       placeholder="Enter plot size (optional)" data-model="plotSize" value="{{ old('plot_size') }}"
                       data-index-class="border-blue-300 focus:border-blue-500"
                       data-index-placeholder="Enter plot size (required)">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="date_recommended_prop" class="text-xs text-gray-600">
                    Date Recommended
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="date_recommended_prop" name="date_recommended" type="date"
                       class="form-input text-sm property-input" data-model="dateRecommended" value="{{ old('date_recommended') }}"
                       data-index-class="border-blue-300 focus:border-blue-500">
            </div>
            <div>
                <label for="date_approved_prop" class="text-xs text-gray-600">
                    Date Approved
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="date_approved_prop" name="date_approved" type="date"
                       class="form-input text-sm property-input" data-model="dateApproved" value="{{ old('date_approved') }}"
                       data-index-class="border-blue-300 focus:border-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="lease_begins_prop" class="text-xs text-gray-600">
                    Lease Begins
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="lease_begins_prop" name="lease_begins" type="date"
                       class="form-input text-sm property-input" data-model="leaseBegins" value="{{ old('lease_begins') }}"
                       data-index-class="border-blue-300 focus:border-blue-500">
            </div>
            <div>
                <label for="lease_expires_prop" class="text-xs text-gray-600">
                    Lease Expires
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="lease_expires_prop" name="lease_expires" type="date"
                       class="form-input text-sm property-input" data-model="leaseExpires" value="{{ old('lease_expires') }}"
                       data-index-class="border-blue-300 focus:border-blue-500">
            </div>
        </div>
        @include('components.metricSheetNo2')

        <div class="grid grid-cols-1 gap-3">
            <div>
                <label for="scale_prop" class="text-xs text-gray-600">
                    Scale
                    <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                </label>
                <input id="scale_prop" name="scale" type="text" class="form-input text-sm property-input"
                       placeholder="Enter scale (optional)" data-model="scale" value="{{ old('scale') }}"
                       data-index-class="border-blue-300 focus:border-blue-500"
                       data-index-placeholder="Enter scale (required)">
            </div>
        </div>
    </div>
</div>
