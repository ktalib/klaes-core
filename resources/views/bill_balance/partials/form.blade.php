@php
  $billingValues = $billingValues ?? [
    'bill_balance_reciept' => null,
    'bill_balance_date' => null,
    'Site_Plan_Fee' => 1000,
    'survey_fee' => 0,
    'Processing_Fee' => 0,
    'Betterment_Charges' => 0,
    'Land_Use_Charge' => 0,
    'Penalty_Fees' => 0,
  ];

  $streetOptions = ($streetNames ?? collect());
  $addressDistrictOptions = ($districts ?? collect())->pluck('name')->values()->all();
  $lgaOptions = ($lgas ?? collect())->pluck('LGAName')->values()->all();
  $stateOptions = ($states ?? collect())->pluck('StateName')->values()->all();

  $selectedDistrict = old('district', $billBalance->district);
  $isDistrictOther = $selectedDistrict && (
    strtolower((string) $selectedDistrict) === 'other' ||
    !in_array($selectedDistrict, $addressDistrictOptions, true)
  );

  $preparedAtValue = old(
    'prepared_at',
    optional($billBalance->prepared_at)->format('Y-m-d')
  );

  $dateOfExpiryValue = old(
    'date_of_expiry',
    optional($billBalance->date_of_expiry)->format('Y-m-d') ?? '2099-12-31'
  );
@endphp
@csrf

{{-- Wizard Step Indicator --}}
<div class="mb-6">
  <div class="flex items-center justify-between">
    @foreach([
      ['step' => 1, 'label' => 'Basic Info', 'icon' => 'file-text'],
      ['step' => 2, 'label' => 'Location', 'icon' => 'map'],
      ['step' => 3, 'label' => 'Fees', 'icon' => 'naira'],
      ['step' => 4, 'label' => 'Payment', 'icon' => 'credit-card'],
    ] as $i => $s)
      @if($i > 0)
        <div class="flex-1 h-0.5 mx-2 wizard-connector" data-before-step="{{ $s['step'] }}">
          <div class="h-full bg-slate-200 rounded transition-colors duration-300"></div>
        </div>
      @endif
      <div class="flex flex-col items-center wizard-step-indicator cursor-pointer" data-step="{{ $s['step'] }}">
        <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300
          {{ $s['step'] === 1 ? 'border-teal-500 bg-teal-500 text-white' : 'border-slate-300 bg-white text-slate-400' }}"
          id="wizard-circle-{{ $s['step'] }}">
          @if($s['icon'] === 'naira')
            <span class="text-sm font-bold" style="line-height: 1;">₦</span>
          @else
            <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
          @endif
        </div>
        <span class="text-xs font-medium mt-1 transition-colors duration-300
          {{ $s['step'] === 1 ? 'text-teal-600' : 'text-slate-400' }}"
          id="wizard-label-{{ $s['step'] }}">{{ $s['label'] }}</span>
      </div>
    @endforeach
  </div>
</div>

{{-- ═══════════ STEP 1: Basic Information & Applicant ═══════════ --}}
<div class="wizard-panel" data-wizard-step="1">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- Basic Information Card --}}
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
      <div class="flex items-center gap-2 mb-4">
        <i data-lucide="file-text" class="w-4 h-4 text-teal-600"></i>
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Basic Information</h3>
      </div>
      <div class="space-y-4">
        <div>
          <div class="flex items-center gap-2">
            <label class="form-label">File Number</label>
            {{-- Shown by bill_balance.js when the selected file is a Sectional Titling unit --}}
            <span id="st-file-badge" class="hidden items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-indigo-100 text-indigo-700 border border-indigo-300">
              <i data-lucide="layers" class="w-3 h-3"></i>
              <span id="st-file-badge-text">ST File</span>
            </span>
          </div>
          <div class="flex gap-2">
            <div class="relative flex-1">
              <input type="text" name="file_number" id="file_number" class="form-input pl-10" value="{{ old('file_number', $billBalance->file_number) }}" readonly placeholder="Select file number...">
              <i data-lucide="folder" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            </div>
            <button type="button" id="select-file-number-btn" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm text-sm">
              Select
            </button>
          </div>
        </div>
        <div>
          <label class="form-label">Date of Issue</label>
          <div class="relative">
            <input type="date" name="prepared_at" id="prepared_at" class="form-input pl-10" value="{{ $preparedAtValue }}" max="2099-12-31">
            <i data-lucide="calendar" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="form-label">Date of Expiry</label>
          <div class="relative">
            <input type="date" name="date_of_expiry" id="date_of_expiry" class="form-input pl-10" value="{{ $dateOfExpiryValue }}">
            <i data-lucide="calendar-x" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Applicant Details Card --}}
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
      <div class="flex items-center gap-2 mb-4">
        <i data-lucide="user" class="w-4 h-4 text-blue-600"></i>
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Applicant Details</h3>
      </div>
      <div class="space-y-4">
        <div>
          <label class="form-label">Applicant Name *</label>
          <div class="relative">
            <input type="text" name="applicant_name" id="applicant_name" class="form-input pl-10" value="{{ old('applicant_name', $billBalance->applicant_name) }}" placeholder="Full name" required>
            <i data-lucide="user-circle" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3 rounded-lg bg-slate-100 border border-slate-200">
          <div>
            <label class="form-label">House No</label>
            <input type="text" id="app_house_number" name="app_house_number" class="form-input" value="{{ old('app_house_number') }}" placeholder="e.g., 5">
          </div>
          <div>
            <label class="form-label">Plot Number</label>
            <input type="text" id="app_plot_number" name="app_plot_number" class="form-input" value="{{ old('app_plot_number') }}" placeholder="e.g., 12A" required>
          </div>
          <div>
            <label class="form-label">Street Name</label>
            <select id="app_street_name" name="app_street_name" class="form-input">
              <option value="">SELECT STREET</option>
              @foreach($streetOptions as $street)
                <option value="{{ $street->name }}" {{ old('app_street_name') === $street->name ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
              @endforeach
            </select>
          </div>
          <div id="app-street-other-wrapper" class="md:col-span-2 hidden">
            <label class="form-label">Specify Street Name</label>
            <input type="text" id="app_street_other" name="app_street_other" class="form-input" value="{{ old('app_street_other') }}" placeholder="Type street name">
          </div>
          <div>
            <label class="form-label">District</label>
            <select id="app_district" name="app_district" class="form-input">
              <option value="">SELECT DISTRICT</option>
              @foreach($addressDistrictOptions as $option)
                <option value="{{ $option }}" {{ old('app_district') === $option ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
              @endforeach
            </select>
          </div>
          <div id="app-district-other-wrapper" class="hidden">
            <label class="form-label">Specify District</label>
            <input type="text" id="app_district_other" name="app_district_other" class="form-input" value="{{ old('app_district_other') }}" placeholder="Type district name">
          </div>
          <div>
            <label class="form-label">LGA</label>
            <select id="app_lga" name="app_lga" class="form-input" required>
              <option value="">SELECT LGA</option>
              @foreach($lgaOptions as $option)
                <option value="{{ $option }}" {{ old('app_lga') === $option ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">State</label>
            <select id="app_state" name="app_state" class="form-input" required>
              <option value="">SELECT STATE</option>
              @foreach($stateOptions as $option)
                <option value="{{ $option }}" {{ old('app_state', 'Kano') === $option ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="form-label">Applicant Address</label>
          <div class="relative">
            <textarea name="applicant_address" id="applicant_address" rows="3" class="form-input pl-10 bg-gray-50" placeholder="Address builder output" readonly required>{{ old('applicant_address', $billBalance->applicant_address) }}</textarea>
            <i data-lucide="map-pin" class="w-4 h-4 text-slate-400 absolute left-3 top-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════ STEP 2: Location & Rent ═══════════ --}}
<div class="wizard-panel hidden" data-wizard-step="2">
  <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
    <div class="flex items-center gap-2 mb-4">
      <i data-lucide="map" class="w-4 h-4 text-purple-600"></i>
      <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Location & Rent</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">House No</label>
        <input type="text" id="loc_house_number" name="loc_house_number" class="form-input" value="{{ old('loc_house_number') }}" placeholder="e.g., 5">
      </div>
      <div>
        <label class="form-label">Plot Number</label>
        <input type="text" id="loc_plot_number" name="loc_plot_number" class="form-input" value="{{ old('loc_plot_number') }}" placeholder="e.g., 12A" required>
      </div>
      <div>
        <label class="form-label">Street Name</label>
        <select id="loc_street_name" name="loc_street_name" class="form-input">
          <option value="">SELECT STREET</option>
          @foreach($streetOptions as $street)
            <option value="{{ $street->name }}" {{ old('loc_street_name') === $street->name ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
          @endforeach
        </select>
      </div>
      <div id="loc-street-other-wrapper" class="md:col-span-2 hidden">
        <label class="form-label">Specify Street Name</label>
        <input type="text" id="loc_street_other" name="loc_street_other" class="form-input" value="{{ old('loc_street_other') }}" placeholder="Type street name">
      </div>
      <div>
        <label class="form-label">District</label>
        <select id="loc_district" name="loc_district" class="form-input">
          <option value="">SELECT DISTRICT</option>
          @foreach($addressDistrictOptions as $option)
            <option value="{{ $option }}" {{ (($isDistrictOther && $option === 'Other') || $selectedDistrict === $option || old('loc_district') === $option) ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
          @endforeach
        </select>
      </div>
      <div id="loc-district-other-wrapper" class="hidden">
        <label class="form-label">Specify District</label>
        <input type="text" id="loc_district_other" name="loc_district_other" class="form-input" value="{{ $isDistrictOther ? $selectedDistrict : old('loc_district_other') }}" placeholder="Type district name">
      </div>
      <div>
        <label class="form-label">LGA</label>
        <select id="loc_lga" name="loc_lga" class="form-input" required>
          <option value="">SELECT LGA</option>
          @foreach($lgaOptions as $option)
            <option value="{{ $option }}" {{ old('loc_lga') === $option ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label">State</label>
        <select id="loc_state" name="loc_state" class="form-input" required>
          <option value="">SELECT STATE</option>
          @foreach($stateOptions as $option)
            <option value="{{ $option }}" {{ old('loc_state', 'Kano') === $option ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $option) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label">Location / Station *</label>
        <div class="relative">
          <input type="text" name="location_station" id="location_station" class="form-input pl-10 bg-gray-50" value="{{ old('location_station', $billBalance->location_station) }}" placeholder="Address builder output" readonly required>
          <i data-lucide="map-pin" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <input type="hidden" name="district" id="district" value="{{ old('district', $billBalance->district) }}">
    </div>
  </div>
</div>

{{-- ═══════════ STEP 3: Fees & Charges ═══════════ --}}
<div class="wizard-panel hidden" data-wizard-step="3">
  <div class="bg-gradient-to-br from-teal-50 to-blue-50 rounded-xl border border-teal-200 p-4">
    <div class="flex items-center gap-3 mb-4">
      <i data-lucide="dollar-sign" class="w-4 h-4 text-teal-600"></i>
      <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Fees & Charges</h3>
      <p id="fees-reference-display" class="text-xs font-bold text-red-600{{ $billBalance->reference ? '' : ' hidden' }}">Reference ID: <span class="uppercase">{{ $billBalance->reference }}</span></p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Area (sqm)</label>
        <div class="relative">
          <input type="number" step="0.01" name="area" id="area" class="form-input pl-10 bg-white" value="{{ old('area', $billBalance->area ?? '') }}" placeholder="0.00">
          <i data-lucide="ruler" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Unit Cost (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="unit_cost" id="unit_cost" class="form-input pl-10 bg-white" value="{{ old('unit_cost', $billBalance->unit_cost ?? '') }}" placeholder="0.00">
          <i data-lucide="tag" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
        <p class="text-xs text-slate-500 mt-1">Area × Unit Cost = Rent Per Annum.</p>
      </div>
      <div>
        <label class="form-label">Rent Per Annum (₦) *</label>
        <div class="relative">
          <input type="number" step="0.01" name="amount" id="amount" class="form-input pl-10 bg-gray-100 text-slate-500" value="{{ old('amount', $billBalance->amount) }}" placeholder="0.00" required readonly>
          <i data-lucide="banknote" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Registration Fees (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="Site_Plan_Fee" class="form-input pl-10 bg-gray-100 text-slate-500 fee-input" value="{{ old('Site_Plan_Fee', $billingValues['Site_Plan_Fee'] ?: 1000) }}" placeholder="1000.00" readonly>
          <i data-lucide="file-check" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Survey Fees (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="survey_fee" class="form-input pl-10 bg-white fee-input" value="{{ old('survey_fee', $billingValues['survey_fee']) }}" placeholder="0.00">
          <i data-lucide="compass" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Preparation Fees (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="Processing_Fee" class="form-input pl-10 bg-white fee-input" value="1000" placeholder="0.00">
          <i data-lucide="file-edit" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Compensation Fees (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="Betterment_Charges" class="form-input pl-10 bg-white fee-input" value="{{ old('Betterment_Charges', $billingValues['Betterment_Charges']) }}" placeholder="0.00">
          <i data-lucide="home" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Development Charges (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="Land_Use_Charge" class="form-input pl-10 bg-white fee-input" value="{{ old('Land_Use_Charge', $billingValues['Land_Use_Charge']) }}" placeholder="0.00">
          <i data-lucide="building" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="form-label">From (Year)</label>
          <div class="relative">
            <select name="rent_from_year" id="rent_from_year" class="form-input pl-10 bg-white">
              <option value="">Select year</option>
              @for($y = 2026; $y >= 1970; $y--)
                <option value="{{ $y }}" {{ (string) old('rent_from_year', $billBalance->rent_from_year ?? '') === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
              @endfor
            </select>
            <i data-lucide="calendar" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="form-label">To (Year)</label>
          <div class="relative">
            <select name="rent_to_year" id="rent_to_year" class="form-input pl-10 bg-white">
              <option value="">Select year</option>
              @for($y = 2026; $y >= 1970; $y--)
                <option value="{{ $y }}" {{ (string) old('rent_to_year', $billBalance->rent_to_year ?? '2026') === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
              @endfor
            </select>
            <i data-lucide="calendar-x" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="form-label">Rent Period</label>
          <div class="relative">
            <input type="text" id="rent_year_range_text" class="form-input pl-10 bg-gray-50 text-slate-600" readonly placeholder="Select From and To years">
            <i data-lucide="calendar-range" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
          </div>
        </div>
      </div>
      <div class="md:col-span-2">
        <label class="form-label">Cumulative Annual (₦)</label>
        <div class="relative">
          <input type="text" id="total-rent-over-term" class="form-input pl-10 bg-gray-50 font-semibold" readonly placeholder="0.00">
          <i data-lucide="calendar-range" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
        <p class="text-xs text-slate-500 mt-1">Rent Per Annum × |From − To|</p>
        <p id="rent-year-range-display" class="text-xs font-semibold text-teal-700 mt-1 hidden">
          From <span id="rent-from-display"></span> to <span id="rent-to-display"></span>
          (<span id="rent-term-years-display"></span> year<span id="rent-term-years-plural"></span>)
        </p>
      </div>

    </div>

    {{-- Total Calculation --}}
    <div class="mt-4 p-4 bg-white rounded-lg border-2 border-teal-300">
      <div class="flex items-center gap-2 mb-3">
        <i data-lucide="calculator" class="w-4 h-4 text-teal-600"></i>
        <h4 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Total Charges</h4>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Total Amount (₦)</label>
          <div class="relative">
            <input type="text" id="total-amount" class="form-input pl-10 bg-gray-50" readonly placeholder="0.00">
            <i data-lucide="receipt" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
          </div>
        </div>
        <div>
          <label class="form-label">Amount in Words</label>
          <div class="relative">
            <textarea id="amount-in-words" class="form-input pl-10 bg-gray-50" rows="2" readonly placeholder="Zero naira only"></textarea>
            <i data-lucide="type" class="w-4 h-4 text-slate-400 absolute left-3 top-3"></i>
          </div>
        </div>
      </div>
    </div>

    {{-- Hidden field for form submission --}}
    <input type="hidden" id="reference" name="reference" value="{{ old('reference', $billBalance->reference) }}">
  </div>
</div>

{{-- ═══════════ STEP 4: Payment Details ═══════════ --}}
<div class="wizard-panel hidden" data-wizard-step="4">
  <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-xl border border-emerald-200 p-4">
    <div class="flex items-center gap-2 mb-4">
      <i data-lucide="credit-card" class="w-4 h-4 text-emerald-600"></i>
      <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Payment Details</h3>
    </div>

    {{-- Total Amount Preview --}}
    <div class="mb-4 p-3 bg-white rounded-lg border border-emerald-200 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <i data-lucide="receipt" class="w-4 h-4 text-emerald-600"></i>
        <span class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Total Amount (₦)</span>
      </div>
      <span id="step4-total-amount" class="text-base font-bold text-emerald-700">0.00</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="form-label">Amount Deposited (₦)</label>
        <div class="relative">
          <input type="number" step="0.01" name="Penalty_Fees" id="amount-deposited" class="form-input pl-10 bg-white" value="{{ old('Penalty_Fees', $billingValues['Penalty_Fees']) }}" placeholder="0.00">
          <i data-lucide="banknote" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Receipt Number (CRC No)</label>
        <div class="relative">
          <input type="text" name="bill_balance_reciept" class="form-input pl-10 bg-white" value="{{ old('bill_balance_reciept', $billingValues['bill_balance_reciept']) }}" placeholder="Receipt no.">
          <i data-lucide="receipt" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
      </div>
      <div>
        <label class="form-label">Receipt Date</label>
        <div class="relative">
          <input type="date" name="bill_balance_date" class="form-input pl-10 bg-white" value="{{ old('bill_balance_date', $billingValues['bill_balance_date']) }}">
          <i data-lucide="calendar-check" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="form-label">Balance Due To</label>
        <select id="balance-due-to" class="form-input bg-gray-50">
          <option value="">SELECT OPTION</option>
          <option value="Government">Government</option>
          <option value="Applicant">Applicant</option>
        </select>
      </div>
      <div>
        <label class="form-label">Balance Due (₦)</label>
        <input type="text" id="balance-due-amount" class="form-input bg-gray-50" readonly placeholder="0.00">
      </div>
    </div>
  </div>
</div>
 