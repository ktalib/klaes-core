@extends('layouts.app')
@section('page-title')
  {{ __('Secondary Application Form') }}
@endsection
@include('sectionaltitling.sub_app_css')
@include('sectionaltitling.partials.assets.css')

@php
  // SUA Detection - check from multiple sources
  $isSUA = isset($isSUA) ? $isSUA : (
    request()->query('sua', false) ||
    request()->route()->getName() === 'sua.create' ||
    (isset($sua) && $sua)
  );

  $isEdit = isset($isEdit) ? $isEdit : false;
  $mainApplicationId = request()->get('application_id');

  if ($isSUA) {
    // SUA-specific initialization
    $motherApplication = null;
    $totalUnitsInMotherApp = 1; // SUA is always 1 unit
    $totalSubApplications = 0;
    $remainingUnits = 1;
    $propertyLocation = '';
    $buyersWithUnits = [];
  } else {
    // Existing logic for regular sub-applications
    // Fetch data from the mother_applications table
    $motherApplication = DB::connection('sqlsrv')->table('mother_applications')->where('id', $mainApplicationId)->first();
    $totalUnitsInMotherApp = $motherApplication ? $motherApplication->NoOfUnits : 0;

    // Count the number of sub-applications linked to the main application
    $totalSubApplications = DB::connection('sqlsrv')->table('subapplications')->where('main_application_id', $mainApplicationId)->count();

    // Calculate the remaining units
    $remainingUnits = $totalUnitsInMotherApp - $totalSubApplications;

    // Get property location
    $propertyLocation = '';
    if ($motherApplication) {
      $locationParts = array_filter([
        $motherApplication->property_plot_no ?? null,
        $motherApplication->property_street_name ?? null,
        $motherApplication->property_district ?? null
      ]);
      $propertyLocation = implode(', ', $locationParts);
    }

    // Fetch buyers and their unit measurements for this mother application
    $buyersWithUnits = [];
    if ($motherApplication) {
      $buyersWithUnits = DB::connection('sqlsrv')
        ->table('buyer_list as bl')
        ->leftJoin('st_unit_measurements as sum', function ($join) use ($motherApplication) {
          $join->on('bl.id', '=', 'sum.buyer_id')
            ->where('sum.application_id', '=', $motherApplication->id);
        })
        ->where('bl.application_id', $motherApplication->id)
        // Exclude buyers already used in subapplications for this main application
        ->whereNotExists(function ($q) use ($motherApplication) {
          $q->select(DB::raw(1))
            ->from('subapplications as s')
            ->whereColumn('s.unit_number', 'bl.unit_no')
            ->where('s.main_application_id', $motherApplication->id);
        })
        // Link each buyer to its commissioned PUA file number via buyer_list_id
        // (the reliable key; unit_no like "A"/"B" does NOT match unit_sequence 1/2/3)
        ->leftJoin('st_file_numbers as sfn', function ($join) {
          $join->on('bl.id', '=', 'sfn.buyer_list_id')
               ->where('sfn.file_no_type', '=', 'PUA');
        })
        ->select(
          'bl.id as buyer_id',
          'bl.application_id',
          'bl.buyer_title',
          'bl.buyer_name',
          DB::raw('COALESCE(sum.unit_no, bl.unit_no) as unit_no'),
          'bl.land_use',
          DB::raw('sum.measurement as measurement'),
          DB::raw('sum.measurement as unit_size'),
          'sum.id as unit_measurement_id',
          'sfn.fileno as unit_fileno'
        )
        ->get();
    }
  }

  // Check for MIXED land use from URL parameter
  $isMixedLandUse = !$isSUA && request()->query('land_use') === 'Mixed Use';

  // Define variables that might be used in hidden form fields to prevent undefined variable errors
  $prefix = $isSUA ? 'SUA' : 'SUB';
  $currentYear = date('Y');
  $formattedSerialNumber = $isSUA ? '1' : ($totalSubApplications + 1);

  // Define file number variables for regular sub-applications
  if (!$isSUA && $motherApplication) {
    $appliedFileNo = $motherApplication->applied_file_number ?? '';
    
    // Extract ST primary file number from selected_file_data if available
    $stPrimaryFileNo = '';
    if (!empty($motherApplication->selected_file_data)) {
        $fileData = json_decode($motherApplication->selected_file_data, true);
        $stPrimaryFileNo = $fileData['np_fileno'] ?? '';
    }
    
    // Combined identifiers for API querying
    $combinedIdentifiers = array_filter([$appliedFileNo, $stPrimaryFileNo]);
    $apiIdentifiers = implode(',', $combinedIdentifiers);
    
    $npFileNo = $stPrimaryFileNo ?: ($appliedFileNo ?: 'N/A');
    $unitFileNo = $npFileNo . '-' . str_pad(($totalSubApplications + 1), 3, '0', STR_PAD_LEFT);

    // Direct query for unit (PUA) file numbers linked to this primary application.
    // Match on every reliable link so commissioned units always surface:
    //  - parent_id           -> the primary st_file_numbers row (most reliable)
    //  - mother_application_id-> the mother application
    //  - mls_fileno / np_fileno -> the legacy & new primary file numbers
    $primaryFileId = $motherApplication->primary_file_id ?? null;
    $stUnitFiles = DB::connection('sqlsrv')
        ->table('st_file_numbers')
        ->where('file_no_type', 'PUA')
        ->where(function($q) use ($appliedFileNo, $stPrimaryFileNo, $primaryFileId, $mainApplicationId) {
            if (!empty($primaryFileId))     $q->orWhere('parent_id', $primaryFileId);
            if (!empty($mainApplicationId)) $q->orWhere('mother_application_id', $mainApplicationId);
            if (!empty($appliedFileNo))     $q->orWhere('mls_fileno', $appliedFileNo);
            if (!empty($stPrimaryFileNo))   $q->orWhere('np_fileno', $stPrimaryFileNo);
        })
        ->get()
        ->map(function($item) {
            // Manually compute display name for consistency
            $item->display_name = trim(($item->first_name ?? '') . ' ' . ($item->middle_name ?? '') . ' ' . ($item->surname ?? ''));
            if (empty($item->display_name)) {
                $item->display_name = $item->corporate_name ?: ($item->multiple_owners_names ?: 'N/A');
            }
            $item->full_file_number = $item->fileno ?: ($item->mls_fileno ?: $item->np_fileno);
            return $item;
        });
  } else {
    $apiIdentifiers = '';
    $npFileNo = 'N/A';
    $unitFileNo = 'N/A';
    $stUnitFiles = collect([]);
  }

  // Draft bootstrap data
  $draftMeta = $draftBootstrap ?? [];
  $draftList = $draftMeta['drafts'] ?? [];
  // Show draft mode by default if drafts exist, otherwise fresh mode
  $initialMode = !empty($draftList) ? 'draft' : 'fresh';

  // Debug: Log initial draft data
  if (!empty($draftList)) {
    error_log('[Sub Application] Initial draft list has ' . count($draftList) . ' drafts');
  } else {
    error_log('[Sub Application] Initial draft list is empty');
  }
@endphp

<script>
  window.SUB_APP_BUYERS = @json($buyersWithUnits ?? []);
  window.ST_UNIT_FILES = @json($stUnitFiles ?? []);
  console.log('👥 Bootstrapped buyers dataset:', window.SUB_APP_BUYERS.length);
  console.log('🏠 Bootstrapped unit files dataset:', window.ST_UNIT_FILES.length);
</script>

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

{{-- Sub Application File Number API Integration --}}
<script>
  /*   
* * File Number API Integration for Sub Applications
* Handles both SUA and Unit file number selection with auto-fill
*/

  // Global variables
  let suaFileNumbers = [];
  let unitFileNumbers = [];
  window.SUB_APP_BUYERS = window.SUB_APP_BUYERS || [];

  /**
   * Initialize file number API integration
   */
  function initSubApplicationFileNumberAPI() {
    console.log('🚀 Initializing Sub Application File Number API...');

    // Check if this is SUA or Unit mode
    const suaElement = document.getElementById('sua-file-select');
    const unitElement = document.getElementById('unit-file-select');
    const isSUA = suaElement !== null;
    const isUnit = unitElement !== null;

    console.log('🔍 Element detection:', {
      suaElement: !!suaElement,
      unitElement: !!unitElement,
      isSUA,
      isUnit
    });

    if (isSUA) {
      console.log('📋 SUA mode detected - loading SUA file numbers');
      loadSUAFileNumbers();
    } else if (isUnit) {
      console.log('🏠 Unit mode detected - loading Unit file numbers');
      loadUnitFileNumbers();
    } else {
      console.warn('⚠️ No file select elements found - neither SUA nor Unit mode detected');
    }
  }

  /**
   * Load SUA file numbers from API
   */
  function loadSUAFileNumbers() {
    console.log('🔄 Starting to load SUA file numbers...');
    const loadingDiv = document.getElementById('sua-file-loading');
    const selectElement = document.getElementById('sua-file-select');

    console.log('📋 Elements found:', { loadingDiv: !!loadingDiv, selectElement: !!selectElement });

    if (loadingDiv) loadingDiv.classList.remove('hidden');

    // Get the land use from the server-side variable
    const landUse = "{{ $selectedLandUse ?? '' }}";

    // Build query parameters
    const params = new URLSearchParams({
      only_available: 'true',
      land_use: landUse,
      file_no_type: 'SUA', // Filter on backend
      _t: new Date().getTime()
    });

    const apiUrl = `/api/file-numbers/st-all?${params.toString()}`;
    console.log('📡 Fetching from:', apiUrl);

    fetch(apiUrl, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => {
        console.log('📡 API Response status:', response.status, response.statusText);
        // Check content type
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
          return response.json().then(data => {
            if (!response.ok) {
              throw new Error(data.message || `HTTP ${response.status}`);
            }
            return data;
          });
        } else {
          return response.text().then(text => {
            console.error('❌ Non-JSON Response:', text.substring(0, 500));
            throw new Error(`Invalid response format (not JSON). Status: ${response.status}`);
          });
        }
      })
      .then(data => {
        console.log('✅ SUA File Numbers API response:', data);

        if (data.status === 'success' && data.data) {
          // Use data directly since we are filtering on backend now
          let suaFiles = data.data;
          console.log(`📊 Loaded ${suaFiles.length} SUA file numbers from API`);

          suaFileNumbers = suaFiles;
          populateSUADropdown(suaFiles, landUse);
        } else {
          console.warn('⚠️ API response indicates failure:', data);
          throw new Error(data.message || 'Failed to load SUA file numbers');
        }
      })
      .catch(error => {
        console.error('❌ Error loading SUA file numbers:', error);
        if (selectElement) {
          selectElement.innerHTML = `<option value="">Error: ${error.message}</option>`;
        }
      })
      .finally(() => {
        if (loadingDiv) loadingDiv.classList.add('hidden');
      });
  }

  /**
   * Load Unit (PUA) file numbers from API
   */
  function loadUnitFileNumbers() {
    console.log('🔄 Loading Unit file numbers from bootstrapped data...');
    const selectElement = document.getElementById('unit-file-select');
    
    // Check if we have bootstrapped data
    if (window.ST_UNIT_FILES && window.ST_UNIT_FILES.length > 0) {
      console.log(`✅ Using ${window.ST_UNIT_FILES.length} bootstrapped unit files`);
      unitFileNumbers = window.ST_UNIT_FILES;
      populateUnitDropdown(window.ST_UNIT_FILES);
      return;
    }

    // Fallback to API if bootstrapped data is empty (only if it's supposed to have data)
    console.log('🔄 Bootstrapped data empty, falling back to API...');
    const loadingDiv = document.getElementById('unit-file-loading');
    if (loadingDiv) loadingDiv.classList.remove('hidden');

    // Get the mother_application identifiers from the server-side variables
    const motherAppId = @json($motherApplication->id ?? '');
    const apiIdentifiers = @json($apiIdentifiers ?? '');

    // Build query parameters
    const params = new URLSearchParams({
      mother_application_id: motherAppId,
      np_fileno: apiIdentifiers,
      file_no_type: 'PUA', // Filter for PUA on backend
      _t: new Date().getTime()
    });

    const apiUrl = `/api/file-numbers/st-all?${params.toString()}`;
    console.log('📡 Fetching from:', apiUrl);

    fetch(apiUrl, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('✅ Unit File Numbers loaded:', data);

        if (data.status === 'success' && data.data) {
          // Filter for PUA file numbers only
          const puaFiles = data.data.filter(file => file.file_no_type === 'PUA');
          console.log(`📊 Found ${puaFiles.length} PUA file numbers out of ${data.data.length} total`);
          unitFileNumbers = puaFiles;
          populateUnitDropdown(puaFiles);
        } else {
          throw new Error(data.message || 'Failed to load unit file numbers');
        }
      })
      .catch(error => {
        console.error('❌ Error loading unit file numbers:', error);
        if (selectElement && selectElement.options.length <= 1) {
          selectElement.innerHTML += `<option value="">⚠️ Error: ${error.message}</option>`;
        }
      })
      .finally(() => {
        if (loadingDiv) loadingDiv.classList.add('hidden');
      });
  }

  /**
   * Populate SUA dropdown with file numbers
   */
  function populateSUADropdown(fileNumbers, landUse = '') {
    const selectElement = document.getElementById('sua-file-select');
    if (!selectElement) return;

    // Clear existing options except the first one
    selectElement.innerHTML = '<option value="">🔍 Select a SUA File Number to begin...</option>';

    // Safety check
    if (!Array.isArray(fileNumbers)) {
      console.error('❌ Expected array for fileNumbers, got:', typeof fileNumbers);
      return;
    }

    if (fileNumbers.length === 0) {
      const option = document.createElement('option');
      option.value = "";
      const landUseText = landUse ? ` for ${landUse}` : '';
      option.textContent = `No available SUA file numbers found${landUseText}`;
      option.disabled = true;
      selectElement.appendChild(option);
      return;
    }

    fileNumbers.forEach(fileNumber => {
      // Skip invalid entries
      if (!fileNumber || !fileNumber.id) return;

      const option = document.createElement('option');
      option.value = fileNumber.id;
      
      // Get display components
      const unitFileNo = resolveUnitFileNo(fileNumber);
      const name = fileNumber.display_name || 'Unknown';

      // Try to find buyer info for unit designation and size
      const buyer = findBuyerForFileNumber(fileNumber);
      const unitNo = buyer && buyer.unit_no ? buyer.unit_no : (fileNumber.unit_sequence || 'N/A');
      const unitSize = buyer && (buyer.unit_size || buyer.measurement) ? ` (${buyer.unit_size || buyer.measurement})` : '';

      const unitDesignation = `Unit ${unitNo}${unitSize}`;

      // Check if commissioned (must have a specific unit file number assigned)
      if (!unitFileNo || unitFileNo === 'N/A') {
        option.disabled = true;
        option.textContent = `${unitDesignation} - ${name} - (Not Commissioned)`;
      } else {
        // Label format: Unit [No] ([Size]) - [Name] - [FileNo]
        option.textContent = `${unitDesignation} - ${name} - ${unitFileNo}`;
      }

      option.dataset.fileData = JSON.stringify(fileNumber);
      selectElement.appendChild(option);
    });

    // Refresh Select2 if available
    if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
      const $select = jQuery(selectElement);
      if ($select.hasClass('select2-hidden-accessible')) {
        $select.trigger('change.select2');
      }
    }

    console.log(`✅ Populated SUA dropdown with ${fileNumbers.length} file numbers`);
  }

  /**
   * Populate Unit dropdown with file numbers
   */
  function populateUnitDropdown(fileNumbers) {
    const selectElement = document.getElementById('unit-file-select');
    if (!selectElement) return;

    console.log('🏗️ Populating Unit Dropdown with', fileNumbers.length, 'files');

    // Clear existing options except the first one
    selectElement.innerHTML = '<option value="">🔍 Select a Unit File Number to begin...</option>';

    fileNumbers.forEach(fileNumber => {
      const option = document.createElement('option');
      option.value = fileNumber.id;
      
      // Get display components
      const unitFileNo = resolveUnitFileNo(fileNumber);
      // Prefer the buyer's name (matches the buyer list shown to staff); fall back to the st_file_numbers name
      const buyer = findBuyerForFileNumber(fileNumber);
      const name = (buyer && buyer.buyer_name) ? buyer.buyer_name : (fileNumber.display_name || 'Unknown');

      // Try to find buyer info for unit designation and size
      const unitNo = buyer && buyer.unit_no ? buyer.unit_no : (fileNumber.unit_sequence || 'N/A');
      const unitSize = buyer && (buyer.unit_size || buyer.measurement) ? ` (${buyer.unit_size || buyer.measurement})` : '';

      const unitDesignation = `Unit ${unitNo}${unitSize}`;

      // Check if commissioned (must have a specific unit file number assigned)
      if (!unitFileNo || unitFileNo === 'N/A') {
        option.disabled = true;
        option.textContent = `${unitDesignation} - ${name} - (Not Commissioned)`;
      } else {
        // Label format: Unit [No] ([Size]) - [Name] - [FileNo]
        // Use resolved fileno (stored, or derived from np_fileno + unit_sequence)
        option.textContent = `${unitDesignation} - ${name} - ${unitFileNo}`;
      }
      
      option.dataset.fileData = JSON.stringify(fileNumber);
      selectElement.appendChild(option);
    });

    // Refresh Select2 if available
    if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
      const $select = jQuery(selectElement);
      if ($select.hasClass('select2-hidden-accessible')) {
        $select.trigger('change.select2');
      }
    }

    console.log(`✅ Populated Unit dropdown with ${fileNumbers.length} file numbers`);
  }

  function getCachedBuyerDataset() {
    if (!Array.isArray(window.SUB_APP_BUYERS)) {
      window.SUB_APP_BUYERS = [];
    }
    return window.SUB_APP_BUYERS;
  }

  function normalizeUnitValue(value) {
    return value ? value.toString().trim().toUpperCase() : '';
  }

  function deriveUnitNumberFromFile(fileData) {
    if (!fileData) return '';
    if (fileData.unit_sequence !== undefined && fileData.unit_sequence !== null) {
      const seq = parseInt(fileData.unit_sequence, 10);
      if (!Number.isNaN(seq)) {
        return seq.toString().padStart(3, '0');
      }
    }
    if (fileData.unit_number) {
      return fileData.unit_number.toString().trim();
    }
    if (fileData.unit_no) {
      return fileData.unit_no.toString().trim();
    }
    if (fileData.fileno) {
      const parts = fileData.fileno.split('-');
      return parts.length ? parts[parts.length - 1] : '';
    }
    return '';
  }

  /**
   * Resolve the displayable unit/SUA file number for a file record.
   * Prefers a stored file number, otherwise derives one from np_fileno + unit_sequence.
   * Returns 'N/A' when no file number can be determined (i.e. not commissioned).
   */
  function resolveUnitFileNo(fileData) {
    if (!fileData) return 'N/A';

    // 1. Stored file number (any of the known keys)
    const stored = fileData.fileno || fileData.unit_fileno || fileData.full_file_number;
    if (stored && stored.toString().trim() && stored.toString().trim().toUpperCase() !== 'N/A') {
      return stored.toString().trim();
    }

    // 2. Derive from the primary file number + unit sequence
    const primary = fileData.np_fileno || fileData.mls_fileno || fileData.kangis_fileno;
    const suffix = deriveUnitNumberFromFile(fileData);
    if (primary && suffix) {
      return `${primary.toString().trim()}-${suffix}`;
    }

    return 'N/A';
  }

  function findBuyerForFileNumber(fileData) {
    const buyers = getCachedBuyerDataset();
    if (!buyers.length || !fileData) return null;

    // First priority: match by buyer_list_id from st_file_numbers
    const candidateIds = ['buyer_list_id', 'buyer_id', 'buyerId']
      .map(key => fileData[key])
      .filter(Boolean)
      .map(id => Number(id));

    if (candidateIds.length) {
      const matchById = buyers.find(buyer => {
        const buyerId = Number(buyer.buyer_id || buyer.id);
        return candidateIds.includes(buyerId);
      });
      if (matchById) {
        console.log('✅ Found buyer by ID:', matchById.buyer_id, 'Unit:', matchById.unit_no, 'Size:', matchById.measurement);
        return matchById;
      }
    }

    // Fallback: match by unit number (but prefer measurement data over file suffixes)
    const normalizedTargets = [
      fileData.unit_no,
      fileData.unit_number
    ]
      .map(normalizeUnitValue)
      .filter(Boolean);

    if (!normalizedTargets.length) {
      console.warn('⚠️ No buyer_list_id or unit identifiers found in file data:', Object.keys(fileData));
      return null;
    }

    const buyerMatch = buyers.find(buyer => normalizedTargets.includes(normalizeUnitValue(buyer.unit_no)));
    if (buyerMatch) {
      console.log('✅ Found buyer by unit number:', buyerMatch.unit_no, 'Size:', buyerMatch.measurement);
    }
    return buyerMatch;
  }

  function autoSelectBuyerOption(buyerData) {
    const buyerSelectEl = document.getElementById('buyerSelect');
    if (!buyerSelectEl || !buyerData) return;
    const targetValue = (buyerData.buyer_id || buyerData.id || '').toString();
    if (!targetValue) return;

    const option = Array.from(buyerSelectEl.options).find(opt => opt.value === targetValue);
    if (option) {
      buyerSelectEl.value = option.value;
      buyerSelectEl.dispatchEvent(new Event('change'));
    }
  }

  function applyUnitDetailsFromFile(fileData) {
    if (!fileData) return;

    console.log('📋 Applying unit details from file:', fileData.fileno || fileData.full_file_number);

    const unitNumberInput = document.getElementById('unit_number');
    const unitSizeInput = document.querySelector('input[name="unit_size"]');
    const buyerMatch = findBuyerForFileNumber(fileData);

    if (buyerMatch) {
      // Use buyer's actual unit_no and measurement from st_unit_measurements
      if (unitNumberInput && buyerMatch.unit_no) {
        unitNumberInput.value = buyerMatch.unit_no;
        console.log('✅ Set Unit No from buyer data:', buyerMatch.unit_no);
      }

      if (unitSizeInput && (buyerMatch.measurement || buyerMatch.unit_size)) {
        const unitSize = buyerMatch.measurement || buyerMatch.unit_size;
        unitSizeInput.value = unitSize;
        console.log('✅ Set Unit Size from buyer data:', unitSize);
      }

      // Update property location after setting unit number
      if (typeof updatePropertyLocation === 'function') {
        updatePropertyLocation();
      }

      // Auto-select this buyer in the dropdown
      autoSelectBuyerOption(buyerMatch);
    } else {
      console.warn('⚠️ No buyer match found for file:', fileData.fileno, 'buyer_list_id:', fileData.buyer_list_id);

      // Clear the fields if no buyer match
      if (unitNumberInput) unitNumberInput.value = '';
      if (unitSizeInput) unitSizeInput.value = '';
    }
  }

  /**
   * Handle SUA file selection
   */
  /**
   * Back-fill Allocation Information from the selected SUA file number.
   *
   * Allocation Source / Entity / Reference No are answered once, on the SuA File
   * Number Commissioning form, and stored on st_file_numbers. Here they are
   * display-only: the greyed-out controls show the answer and the hidden inputs
   * carry it into the POST.
   */
  function applyAllocationInfoFromFile(fileData) {
    const source = fileData.allocation_source || '';
    const entity = fileData.allocation_entity || '';
    const refNo = fileData.allocation_ref_no || '';

    const sourceHidden = document.getElementById('allocation_source');
    const entityHidden = document.getElementById('allocation_entity');
    const refNoInput = document.getElementById('allocationRefNo');
    const sourceDisplay = document.getElementById('allocation_source_display');
    const entityDisplay = document.getElementById('allocation_entity_display');

    if (sourceHidden) sourceHidden.value = source;
    if (entityHidden) entityHidden.value = entity;
    if (refNoInput) refNoInput.value = refNo;

    if (sourceDisplay) sourceDisplay.value = source;

    if (entityDisplay) {
      // The entity list depends on the source, so show the stored value itself
      // rather than rebuilding the dropdown for a control nobody can change.
      entityDisplay.innerHTML = '<option value="">Select Entity</option>';
      if (entity) {
        const option = document.createElement('option');
        option.value = entity;
        option.textContent = entity;
        option.selected = true;
        entityDisplay.appendChild(option);
      }
    }

    const warning = document.getElementById('allocation-missing-warning');
    if (warning) warning.classList.toggle('hidden', Boolean(source && entity));
  }

  function handleSUAFileSelection(selectElement) {
    const selectedOption = selectElement.selectedOptions[0];
    if (!selectedOption || !selectedOption.value) {
      hideSUAPreview();
      return;
    }

    try {
      const fileData = JSON.parse(selectedOption.dataset.fileData);
      console.log('📋 SUA File selected:', fileData);

      // Update hidden fields (SUA field mappings)
      const hiddenNpFileno = document.getElementById('hidden-sua-np-fileno');
      const hiddenMlsFileno = document.getElementById('hidden-sua-mls-fileno');
      const hiddenFileno = document.getElementById('hidden-sua-fileno');
      const hiddenFileId = document.getElementById('hidden-sua-file-id');
      const hiddenTrackingId = document.getElementById('hidden-tracking-id');
      const hiddenStBatchNo = document.getElementById('hidden-st-batch-no');

      // Also update the early form unit file inputs (for SUA these represent the same data)
      const unitFileNoHidden = document.getElementById('unitFileNoHidden');
      const unitFileNo = document.getElementById('unitFileNo');

      if (hiddenNpFileno) hiddenNpFileno.value = fileData.np_fileno || '';
      if (hiddenMlsFileno) hiddenMlsFileno.value = fileData.mls_fileno || '';
      if (hiddenFileno) hiddenFileno.value = fileData.fileno || '';
      if (hiddenFileId) hiddenFileId.value = fileData.id || '';
      if (hiddenTrackingId) hiddenTrackingId.value = fileData.tra || '';
      if (hiddenStBatchNo) hiddenStBatchNo.value = fileData.st_batch_no || fileData.st_batch || '';

      // Update the early form inputs as well
      if (unitFileNoHidden) unitFileNoHidden.value = fileData.fileno || '';
      if (unitFileNo) unitFileNo.value = fileData.fileno || '';

      // Update preview display
      showSUAPreview(fileData);

      // Update land use badge and hidden input for SUA
      updateSUALandUse(fileData.land_use);

      // Back-fill Allocation Information answered at commissioning
      applyAllocationInfoFromFile(fileData);

      // Auto-fill applicant information
      autoFillApplicantInfo(fileData);

      // Update buyers list based on the selected np_fileno
      if (fileData.np_fileno || fileData.mother_application_id) {
        loadBuyersList(fileData.np_fileno, {
          motherApplicationId: fileData.mother_application_id,
          preferredBuyerId: fileData.buyer_list_id || fileData.buyer_id || fileData.buyerId
        });
      }

    } catch (error) {
      console.error('❌ Error handling SUA file selection:', error);
    }
  }

  /**
   * Handle Unit file selection
   */
  function handleUnitFileSelection(selectElement) {
    const selectedOption = selectElement.selectedOptions[0];
    if (!selectedOption || !selectedOption.value) {
      hideUnitPreview();
      return;
    }

    try {
      const fileData = JSON.parse(selectedOption.dataset.fileData);
      console.log('🏠 Unit File selected:', fileData);

      // Update hidden fields (Unit field mappings)
      const hiddenUnitNpFileno = document.getElementById('hidden-unit-np-fileno');
      const hiddenUnitFileno = document.getElementById('hidden-unit-fileno');
      const hiddenUnitFileId = document.getElementById('hidden-unit-file-id');
      const hiddenTrackingId = document.getElementById('hidden-tracking-id');
      const hiddenStBatchNo = document.getElementById('hidden-st-batch-no');

      // Also update the early form unit file inputs
      const unitFileNoHidden = document.getElementById('unitFileNoHidden');
      const unitFileNo = document.getElementById('unitFileNo');

      if (hiddenUnitNpFileno) hiddenUnitNpFileno.value = fileData.np_fileno || '';
      if (hiddenUnitFileno) hiddenUnitFileno.value = fileData.fileno || '';
      if (hiddenUnitFileId) hiddenUnitFileId.value = fileData.id || '';
      if (hiddenTrackingId) hiddenTrackingId.value = fileData.tra || '';
      if (hiddenStBatchNo) hiddenStBatchNo.value = fileData.st_batch_no || fileData.st_batch || '';

      // Update the early form inputs as well
      if (unitFileNoHidden) unitFileNoHidden.value = fileData.fileno || '';
      if (unitFileNo) unitFileNo.value = fileData.fileno || '';

      // Update preview display
      showUnitPreview(fileData);

      // Auto-fill applicant information
      autoFillApplicantInfo(fileData);

      if (fileData.np_fileno) {
        fetchMainApplicationReference(fileData.np_fileno);
      }

      loadBuyersList(fileData.np_fileno, {
        motherApplicationId: fileData.mother_application_id,
        preferredBuyerId: fileData.buyer_list_id || fileData.buyer_id || fileData.buyerId
      });

      applyUnitDetailsFromFile(fileData);

    } catch (error) {
      console.error('❌ Error handling Unit file selection:', error);
    }
  }

  /**
   * Show SUA preview
   */
  function showSUAPreview(fileData) {
    const previewDiv = document.getElementById('sua-selection-preview');
    if (!previewDiv) return;

    document.getElementById('sua-primary-fileno-display').textContent = fileData.np_fileno || '-';
    document.getElementById('sua-mls-fileno-display').textContent = fileData.mls_fileno || '-';
    document.getElementById('sua-fileno-display').textContent = fileData.fileno || '-';
    document.getElementById('sua-land-use-display').textContent = fileData.land_use || '-';
    document.getElementById('sua-tracking-id-display').textContent = fileData.tra || '-';
    document.getElementById('sua-applicant-type-display').textContent = fileData.applicant_type || '-';
    document.getElementById('sua-applicant-display').textContent = fileData.display_name || '-';

    previewDiv.classList.remove('hidden');
  }

  /**
   * Show Unit preview
   */
  function showUnitPreview(fileData) {
    const previewDiv = document.getElementById('unit-selection-preview');
    if (!previewDiv) return;

    document.getElementById('unit-np-fileno-display').textContent = fileData.np_fileno || '-';
    document.getElementById('unit-fileno-display').textContent = fileData.fileno || '-';
    document.getElementById('unit-land-use-display').textContent = fileData.land_use || '-';
    document.getElementById('unit-tracking-id-display').textContent = fileData.tra || '-';
    document.getElementById('unit-applicant-type-display').textContent = fileData.applicant_type || '-';
    document.getElementById('unit-applicant-display').textContent = fileData.display_name || '-';

    previewDiv.classList.remove('hidden');
  }

  /**
   * Hide SUA preview
   */
  function hideSUAPreview() {
    const previewDiv = document.getElementById('sua-selection-preview');
    if (previewDiv) {
      previewDiv.classList.add('hidden');
    }

    // Reset land use display
    const landUseBadge = document.getElementById('sua-land-use-badge');
    const landUseInput = document.getElementById('sua_land_use_hidden') || document.getElementById('sua-land-use-input');

    if (landUseBadge) {
      landUseBadge.textContent = 'Not selected yet';
      landUseBadge.className = 'bg-gray-100 text-gray-500 px-3 py-2 rounded text-sm font-medium';
    }

    if (landUseInput) {
      landUseInput.value = '';
    }

    const landUseDisplay = document.getElementById('landUseDisplay');
    if (landUseDisplay) {
      landUseDisplay.textContent = 'N/A';
    }
  }

  /**
   * Hide Unit preview
   */
  function hideUnitPreview() {
    const previewDiv = document.getElementById('unit-selection-preview');
    if (previewDiv) {
      previewDiv.classList.add('hidden');
    }
  }

  /**
   * Update SUA land use display and hidden input
   */
  function updateSUALandUse(landUse) {
    const landUseBadge = document.getElementById('sua-land-use-badge');
    const landUseInput = document.getElementById('sua_land_use_hidden') || document.getElementById('sua-land-use-input');

    // Normalize land use to title case to match validation requirements
    const normalizedLandUse = landUse ? landUse.charAt(0).toUpperCase() + landUse.slice(1).toLowerCase() : '';

    if (landUseBadge) {
      if (landUse) {
        // Update the badge with appropriate styling
        landUseBadge.textContent = landUse;
        landUseBadge.className = 'px-3 py-2 rounded text-sm font-medium';

        // Apply color based on land use type
        switch (landUse.toLowerCase()) {
          case 'residential':
            landUseBadge.classList.add('bg-green-100', 'text-green-800');
            break;
          case 'commercial':
            landUseBadge.classList.add('bg-blue-100', 'text-blue-800');
            break;
          case 'industrial':
            landUseBadge.classList.add('bg-purple-100', 'text-purple-800');
            break;
          case 'mixed':
            landUseBadge.classList.add('bg-orange-100', 'text-orange-800');
            break;
          default:
            landUseBadge.classList.add('bg-gray-100', 'text-gray-800');
        }

        console.log('✅ SUA land use updated to:', landUse);
      } else {
        landUseBadge.textContent = 'Not selected yet';
        landUseBadge.className = 'bg-gray-100 text-gray-500 px-3 py-2 rounded text-sm font-medium';
      }
    }

    // Update hidden input for form submission with normalized value
    if (landUseInput) {
      landUseInput.value = normalizedLandUse || '';
    }

    const landUseDisplay = document.getElementById('landUseDisplay');
    if (landUseDisplay) {
      landUseDisplay.textContent = landUse || 'N/A';
    }
  }

  /**
   * Auto-fill applicant information from file data
   */
  function autoFillApplicantInfo(fileData) {
    console.log('🔄 Auto-filling applicant info with data:', fileData);

    // Set applicant type in hidden input
    if (fileData.applicant_type) {
      const applicantType = fileData.applicant_type.toLowerCase();
      const applicantTypeInput = document.getElementById('mainApplicantTypeInput');
      if (applicantTypeInput) {
        applicantTypeInput.value = applicantType;
        console.log('✅ Applicant type set to:', applicantType);
      } else {
        console.warn('⚠️ Applicant type input not found');
      }

      const legacyApplicantTypeField = document.getElementById('applicantType');
      if (legacyApplicantTypeField) {
        legacyApplicantTypeField.value = applicantType;
      }

      // Auto-fill based on applicant type
      if (applicantType === 'individual') {
        fillIndividualFields(fileData);
      } else if (applicantType === 'corporate') {
        fillCorporateFields(fileData);
      } else if (applicantType === 'multiple') {
        fillMultipleFields(fileData);
      }
    } else {
      console.warn('⚠️ No applicant_type in file data:', Object.keys(fileData));
    }

    // Update land use fields
    if (fileData.land_use) {
      updateLandUseFields(fileData.land_use);
    }
  }

  /**
   * Update land use fields based on selected file data
   */
  function updateLandUseFields(landUse) {
    console.log('🏷️ Updating land use fields with:', landUse);

    // Normalize land use to title case to match validation requirements
    // Backend expects: Residential, Commercial, Industrial (title case)
    const normalizedLandUse = landUse ? landUse.charAt(0).toUpperCase() + landUse.slice(1).toLowerCase() : '';
    console.log('🔧 Normalized land use:', normalizedLandUse);

    // Update SUA land use field if it exists
    const suaLandUseSelect = document.getElementById('sua_land_use');
    const suaLandUseHidden = document.getElementById('sua_land_use_hidden');

    if (suaLandUseSelect) {
      suaLandUseSelect.value = normalizedLandUse;
    }
    if (suaLandUseHidden) {
      suaLandUseHidden.value = normalizedLandUse;
    }

    // Update unit land use field if it exists (for mixed land use)
    const unitLandUseSelect = document.getElementById('unit_land_use');
    if (unitLandUseSelect) {
      unitLandUseSelect.value = normalizedLandUse;
      // Trigger change event to update fees
      unitLandUseSelect.dispatchEvent(new Event('change'));
    }

    // Update hidden land use field
    const hiddenLandUse = document.querySelector('input[name="land_use"]');
    if (hiddenLandUse) {
      hiddenLandUse.value = normalizedLandUse;
    }

    const summaryLandUse = document.getElementById('landUseDisplay');
    if (summaryLandUse) {
      summaryLandUse.textContent = landUse || 'N/A';
    }

    console.log('✅ Land use fields updated successfully');
  }

  /**
   * Fill individual applicant fields
   */
  function fillIndividualFields(fileData) {
    console.log('🔄 Filling individual fields with data:', fileData);

    // Show individual fields first
    if (typeof showIndividualFields === 'function') {
      showIndividualFields();
    }

    // Fill the fields after a short delay to ensure they are visible
    setTimeout(() => {
      // Fill title
      if (fileData.applicant_title) {
        const titleSelectField = document.getElementById('applicantTitleSelect');
        const titleHiddenField = document.getElementById('applicantTitle');
        if (titleSelectField) {
          titleSelectField.value = fileData.applicant_title;
          if (titleHiddenField) titleHiddenField.value = fileData.applicant_title;
        }
        console.log('✅ Title set to:', fileData.applicant_title);
      }

      // Fill first name
      if (fileData.first_name) {
        const firstNameField = document.getElementById('applicantName');
        if (firstNameField) {
          firstNameField.value = fileData.first_name;
          console.log('✅ First name set to:', fileData.first_name);
        }
      }

      // Fill middle name
      if (fileData.middle_name) {
        const middleNameField = document.getElementById('applicantMiddleName');
        if (middleNameField) {
          middleNameField.value = fileData.middle_name;
          console.log('✅ Middle name set to:', fileData.middle_name);
        }
      }

      // Fill surname
      if (fileData.surname) {
        const surnameField = document.getElementById('applicantSurname');
        if (surnameField) {
          surnameField.value = fileData.surname;
          console.log('✅ Surname set to:', fileData.surname);
        }
      }

      // Update the applicant name preview after filling all fields
      if (typeof updateApplicantNamePreview === 'function') {
        updateApplicantNamePreview();
        console.log('✅ Applicant name preview updated');
      } else {
        console.warn('⚠️ updateApplicantNamePreview function not available');
      }
    }, 100);
  }

  /**
   * Fill corporate applicant fields
   */
  function fillCorporateFields(fileData) {
    console.log('🔄 Filling corporate fields with data:', fileData);

    // Show corporate fields first
    if (typeof showCorporateFields === 'function') {
      showCorporateFields();
    }

    // Fill the fields after a short delay to ensure they are visible
    setTimeout(() => {
      if (fileData.corporate_name) {
        const corporateNameField = document.getElementById('corporateName') || document.querySelector('input[name="corporate_name"]');
        if (corporateNameField) {
          corporateNameField.value = fileData.corporate_name;
          console.log('✅ Corporate name set to:', fileData.corporate_name);
        }

        // For corporate applicants, show the corporate name in the preview
        const previewField = document.getElementById('applicantNamePreview');
        if (previewField) {
          previewField.value = fileData.corporate_name;
          console.log('✅ Corporate name preview updated');
        }
      }

      if (fileData.rc_number) {
        const rcNumberField = document.getElementById('rcNumber') || document.querySelector('input[name="rc_number"]');
        if (rcNumberField) {
          rcNumberField.value = fileData.rc_number;
          console.log('✅ RC number set to:', fileData.rc_number);
        }
      }
    }, 100);
  }

  /**
   * Fill multiple owners fields
   */
  function fillMultipleFields(fileData) {
    // Show multiple owners fields first
    if (typeof showMultipleOwnersFields === 'function') {
      showMultipleOwnersFields();
    }

    // Fill the fields after a short delay to ensure they are visible
    setTimeout(() => {
      if (fileData.multiple_owners_names) {
        try {
          const owners = JSON.parse(fileData.multiple_owners_names);
          console.log('Multiple owners data:', owners);
          // Handle multiple owners logic here - this would need to be implemented based on the form structure
        } catch (error) {
          console.error('Error parsing multiple owners:', error);
        }
      }

      // Fill first owner data if available
      if (fileData.first_name) {
        const firstNameField = document.querySelector('input[name="first_name"]');
        if (firstNameField) firstNameField.value = fileData.first_name;
      }

      if (fileData.surname) {
        const surnameField = document.querySelector('input[name="surname"]');
        if (surnameField) surnameField.value = fileData.surname;
      }
    }, 100);
  }

  /**
   * Fetch Main Application Reference info using np_fileno
   */
  function fetchMainApplicationReference(npFileno) {
    console.log('🔍 Fetching Main Application Reference for:', npFileno);

    // This would be implemented to fetch mother application details
    // For now, we'll just log the attempt
    console.log('📋 Main Application Reference lookup not yet implemented');
  }

  /**
   * Load buyers list based on np_fileno
   */
  function loadBuyersList(npFileno, options = {}) {
    const buyers = getCachedBuyerDataset();
    console.log('👥 Loading buyers list for:', npFileno || 'N/A', {
      totalBuyers: buyers.length,
      applicationId: options.motherApplicationId || null
    });

    if (!buyers.length) {
      console.warn('⚠️ No buyer dataset found for this application.');
      return [];
    }

    const buyerSelectEl = document.getElementById('buyerSelect');
    if (buyerSelectEl) {
      const placeholder = buyerSelectEl.getAttribute('data-placeholder') || 'Type to search buyer name...';
      buyerSelectEl.innerHTML = `<option value="">${placeholder}</option>`;

      buyers.forEach(buyer => {
        const option = document.createElement('option');
        const buyerId = (buyer.buyer_id || buyer.id || '').toString();
        const displayName = [buyer.buyer_title, buyer.buyer_name]
          .filter(Boolean)
          .join(' ')
          .replace(/\s+/g, ' ')
          .trim();

        option.value = buyerId;
        option.textContent = `${displayName || 'Buyer'} (Unit: ${buyer.unit_no || 'N/A'})${buyer.measurement ? ` • ${buyer.measurement}` : ''}`;
        option.dataset.buyerTitle = buyer.buyer_title || '';
        option.dataset.buyerName = buyer.buyer_name || '';
        option.dataset.unitNo = buyer.unit_no || '';
        option.dataset.landUse = buyer.land_use || '';
        option.dataset.measurement = buyer.measurement || '';
        option.dataset.buyerId = buyerId;
        option.dataset.applicationId = buyer.application_id || options.motherApplicationId || '';
        buyerSelectEl.appendChild(option);
      });

      if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
        const $select = jQuery(buyerSelectEl);
        if ($select.hasClass('select2-hidden-accessible')) {
          $select.trigger('change.select2');
        }
      }

      const preferredId = options.preferredBuyerId ? options.preferredBuyerId.toString() : '';
      if (preferredId) {
        const preferredOption = Array.from(buyerSelectEl.options).find(opt => opt.value === preferredId);
        if (preferredOption) {
          buyerSelectEl.value = preferredOption.value;
          buyerSelectEl.dispatchEvent(new Event('change'));
        }
      }
    } else {
      console.log('ℹ️ Buyer select element not present on this view.');
    }

    return buyers;
  }

  // Initialize when DOM is ready
  document.addEventListener('DOMContentLoaded', function () {
    console.log('🔧 Sub Application File Number API script loaded');
    initSubApplicationFileNumberAPI();
  });

  // Make functions globally available
  window.handleSUAFileSelection = handleSUAFileSelection;
  window.handleUnitFileSelection = handleUnitFileSelection;
  window.initSubApplicationFileNumberAPI = initSubApplicationFileNumberAPI;
</script>

@section('content')
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
  <!-- Animate.css for SweetAlert animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .swal-validation-popup {
      font-family: inherit;
    }

    .swal-validation-title {
      color: #dc2626 !important;
      font-weight: 600;
    }

    .swal-validation-content {
      color: #374151;
    }

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
      height: 42px;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 40px;
      padding-left: 8px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 40px;
    }

    .select2-dropdown {
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
      background-color: #3b82f6;
    }

    /* Enhanced Step Circle Styles */
    .step-circle {
      transition: all 0.2s ease;
    }

    .step-circle.cursor-pointer:hover {
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .step-circle.active-tab.cursor-pointer:hover {
      background-color: #059669;
      border-color: #059669;
    }

    .step-circle.inactive-tab.cursor-pointer:hover {
      background-color: #6b7280;
      border-color: #6b7280;
      color: white;
    }
  </style>

  <!-- SweetAlert2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
  <!-- jQuery (required by Select2) -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>



  <!-- Main Content -->
  <div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">



      {{-- Include appropriate instructions based on application type --}}

      {{-- Include appropriate instructions based on application type --}}
      @if($isSUA)
        @include('sectionaltitling.partials.subapplication.instructions_sua')
      @else
        @include('sectionaltitling.partials.subapplication.instructions_pua')
      @endif
      {{-- Draft Status Container --}}
      <div
        class="bg-blue-50 border border-blue-200 rounded-md px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
        id="draftStatusContainer">

        <div>
          <p class="text-sm text-blue-900 font-semibold">Draft status: <span id="draftStatusText"
              class="font-bold text-blue-700">Initializing…</span></p>
          <p class="text-xs text-blue-700" id="draftLastSavedText">Last saved:
            {{ isset($draftMeta['last_saved_at']) && $draftMeta['last_saved_at'] ? \Carbon\Carbon::parse($draftMeta['last_saved_at'])->diffForHumans() : 'Not yet saved' }}
          </p>
          <p class="text-xs text-blue-700 mt-1" id="draftCollaboratorText">Collaborators: <span
              id="draftCollaboratorCount">{{ isset($draftMeta['collaborators']) ? count($draftMeta['collaborators']) : 1 }}</span>
          </p>
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
          <div
            class="flex-1 md:flex-none w-full md:w-56 h-2 bg-white border border-blue-200 rounded-full overflow-hidden">
            <div id="draftProgressBar" class="h-full bg-blue-600 transition-all duration-500"
              style="width: {{ $draftMeta['progress_percent'] ?? 0 }}%"></div>
          </div>
          <span id="draftProgressValue"
            class="text-sm font-semibold text-blue-900">{{ number_format($draftMeta['progress_percent'] ?? 0, 0) }}%</span>
          <div class="flex items-center gap-2">
            <button type="button" id="draftHistoryButton"
              class="hidden items-center px-3 py-1.5 border border-blue-200 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-100">History</button>
            <button type="button" id="manualSaveButton" onclick="saveDraft({ auto: false, flash: true })"
              class="items-center px-3 py-1.5 border border-green-200 bg-green-50 text-green-700 text-xs font-medium rounded-md hover:bg-green-100">Save
              Draft</button>
          </div>
        </div>
      </div>

      {{-- Draft Locator Container --}}
      <div class="bg-white border border-blue-200 rounded-md px-4 py-4 space-y-4 mb-6" id="draftLocatorContainer">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
          <div class="space-y-3 w-full">
            <div class="flex flex-wrap items-center gap-2" id="draftModeSwitch">
              <button type="button" id="draftModeFreshButton" data-mode="fresh"
                class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'fresh' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Fresh
                Application</button>
              <button type="button" id="draftModeDraftButton" data-mode="draft"
                class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'draft' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Continue
                from Draft</button>
              <span class="text-xs text-gray-500">Choose how you'd like to begin.</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs uppercase tracking-wide text-gray-500">Current unit file no:</span>
              <code id="draftLocatorCurrentId"
                class="text-xs font-mono px-2 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded break-all">{{ $draftMeta['unit_file_no'] ?? 'Not assigned yet' }}</code>
              <button type="button" id="draftLocatorCopyButton"
                class="text-xs font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Copy</button>
            </div>
          </div>
          <form id="draftLocatorForm" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full xl:w-auto hidden">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
              <input type="text" id="draftLocatorInput" name="draft_locator"
                class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                placeholder="Enter unit file no">
              <button type="submit"
                class="px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Load
                Application</button>
            </div>
            <p id="draftLocatorFeedback" class="mt-1 text-xs font-medium hidden"></p>
          </form>
        </div>

        <div id="draftModeDraftPanel"
          class="rounded-md border border-dashed border-blue-200 bg-blue-50/40 px-3 py-3 {{ !empty($draftList) ? '' : 'hidden' }}">
          <div class="flex flex-col lg:flex-row lg:items-center gap-2">
            <label for="draftListSelect" class="text-xs font-semibold text-blue-800 uppercase tracking-wide">My
              drafts</label>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
              <select id="draftListSelect"
                class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">Select a draft to resume</option>
                @foreach ($draftList as $draftSummary)
                  @php
                    $optionValue = $draftSummary['unit_file_no'] ?? $draftSummary['draft_id'];
                    $defaultLabel = $draftSummary['unit_file_no'] ?? ('Unit ' . substr($draftSummary['draft_id'], 0, 8));
                    $optionLabel = $draftSummary['label'] ?? $defaultLabel;
                  @endphp
                  <option value="{{ $optionValue }}" data-draft-id="{{ $draftSummary['draft_id'] }}" {{ !empty($draftSummary['is_current']) ? 'selected' : '' }}>
                    {{ $optionLabel }}
                  </option>
                @endforeach
              </select>
              <button type="button" id="draftListLoadButton"
                class="px-3 py-2 bg-white text-blue-700 border border-blue-300 text-sm font-semibold rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Load
                Selected</button>
              <button type="button" id="draftListRefreshButton"
                class="px-3 py-2 bg-white text-gray-600 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"
                title="Refresh draft list">🔄</button>
            </div>
          </div>
          <p id="draftListEmpty" class="mt-2 text-xs text-blue-700 {{ empty($draftList) ? '' : 'hidden' }}">You don't have
            any saved drafts yet. Start a fresh application to create one.</p>
        </div>
      </div>

      <!-- sub Applications aka unit kaka secondary, yeah, thats fucking Table , this is not even a table-->
      <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
        <div class="container py-4">
          <div class="modal-content">
            <!-- Step 1: Basic Information -->
            <!-- Updating Notice -->
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-lg p-4 mb-4 hidden"
              id="updatingNotice">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="w-5 h-5 text-amber-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-amber-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    Updating...
                  </p>
                  <p class="text-xs text-amber-700 mt-1">
                    This page is currently being updated with new features and improvements. Thank you for your patience.
                  </p>
                </div>
              </div>
            </div>
            <div class="form-section active-tab" id="step1">
              <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                  <h2 class="text-xl font-bold text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
                  <button class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                    <i data-lucide="x" class="w-5 h-5"></i>
                  </button>
                </div>

                <div class="mb-6">


                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
                      <h3 class="text-lg font-bold items-center">
                        @if($isSUA)
                          Standalone Unit Application (SUA)
                        @else
                          Application for Sectional Titling - Unit Application (Secondary)
                        @endif
                      </h3>
                    </div>

                  </div>
                  <p class="text-gray-600 mt-1">
                    @if($isSUA)
                      Complete the form below to submit a standalone unit application
                    @else
                      Complete the form below to submit a new unit application for sectional titling
                    @endif
                  </p>
                </div>
                <div class="flex items-center mb-6">
                  <div class="flex items-center mr-4">
                    <div class="step-circle active-tab flex items-center justify-center cursor-pointer"
                      onclick="goToStep(1)">1</div>
                  </div>
                  <div class="flex items-center mr-4">
                    <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer"
                      onclick="goToStep(2)">2</div>
                  </div>
                  <div class="flex items-center mr-4">
                    <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer"
                      onclick="goToStep(3)">3</div>
                  </div>
                  <div class="flex items-center mr-4">
                    <div class="step-circle inactive-tab flex items-center justify-center cursor-pointer"
                      onclick="goToStep(4)">4</div>
                  </div>
                  <div class="ml-4">Step 1</div>
                </div>



                <div class="mb-6">
                  <div class="text-right text-sm text-gray-500">CODE: ST FORM - 2</div>
                  <hr class="my-4">
                  @php
                    $mainApplicationId = request()->get('application_id');
                    // Fetch data from the mother_applications table
                    $motherApplication = DB::connection('sqlsrv')->table('mother_applications')->where('id', $mainApplicationId)->first();
                    $totalUnitsInMotherApp = $motherApplication ? $motherApplication->NoOfUnits : 0;

                    // Count the number of sub-applications linked to the main application
                    $totalSubApplications = DB::connection('sqlsrv')->table('subapplications')->where('main_application_id', $mainApplicationId)->count();

                    // Calculate the remaining units
                    $remainingUnits = $totalUnitsInMotherApp - $totalSubApplications;

                    // Get property location
                    $propertyLocation = '';
                    if ($motherApplication) {
                      $locationParts = array_filter([
                        $motherApplication->property_plot_no ?? null,
                        $motherApplication->property_street_name ?? null,
                        $motherApplication->property_district ?? null
                      ]);
                      $propertyLocation = implode(', ', $locationParts);
                    }
                  @endphp

                  <form id="subApplicationForm" method="POST" action="javascript:void(0)" enctype="multipart/form-data"
                    onsubmit="return false;" class="space-y-6" novalidate
                    data-draft-id="{{ $draftMeta['draft_id'] ?? '' }}"
                    data-draft-version="{{ $draftMeta['version'] ?? 1 }}"
                    data-auto-save-frequency="{{ $draftMeta['auto_save_frequency'] ?? 30 }}"
                    data-sub-application-id="{{ $subApplicationId ?? '' }}"
                    data-main-application-id="{{ $mainApplicationId ?? '' }}">
                    @csrf
                    @if($isSUA && $isEdit)
                      @method('PUT')
                    @endif

                    <!-- Hidden input for unit file number -->
                    <input type="hidden" name="fileno" id="unitFileNoHidden"
                      value="{{ $draftMeta['unit_file_no'] ?? '' }}">
                    <input type="hidden" name="unit_file_no" id="unitFileNo"
                      value="{{ $draftMeta['unit_file_no'] ?? '' }}">

                    @if($isSUA)
                      <input type="hidden" name="is_sua_unit" value="1">
                      <input type="hidden" name="is_sua" value="1">
                      <input type="hidden" name="main_application_id" value="">
                      <input type="hidden" name="unit_type" value="SUA">
                    @else
                      <input type="hidden" name="main_application_id" value="{{ $mainApplicationId ?? '' }}">
                      <input type="hidden" name="main_id" id="mainIdHidden" value="@php
                        $mainYear = $motherApplication && $motherApplication->created_at ? date('Y', strtotime($motherApplication->created_at)) : date('Y');
                        $mainAppId = $motherApplication->id ?? '';
                        echo sprintf('ST-%s-%03d', $mainYear, $mainAppId);
                      @endphp">
                    @endif

                    {{-- Draft Fields --}}
                    <input type="hidden" name="draft_id" id="draftIdInput" value="{{ $draftMeta['draft_id'] ?? '' }}">
                    <input type="hidden" name="draft_version" id="draftVersionInput"
                      value="{{ $draftMeta['version'] ?? 1 }}">
                    <input type="hidden" name="draft_last_completed_step" id="draftStepInput"
                      value="{{ $draftMeta['last_completed_step'] ?? 1 }}">

                    {{-- Backward Compatibility Fields --}}
                    <input type="hidden" name="payment_date" value="{{ date('Y-m-d') }}">
                    <input type="hidden" name="receipt_number" value="N/A">

                    @if(!$isSUA)
                      <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-3">Main Application Reference</h2>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                          <div class="flex items-center justify-between mb-4">
                            <div>
                              <label class="block text-sm font-medium text-gray-700 mb-1">Main Application ID</label>
                              <div class="flex items-center">
                                <span
                                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-id-card-icon lucide-id-card">
                                    <path d="M16 10h2" />
                                    <path d="M16 14h2" />
                                    <path d="M6.17 15a3 3 0 0 1 5.66 0" />
                                    <circle cx="9" cy="11" r="2" />
                                    <rect x="2" y="5" width="20" height="14" rx="2" />
                                  </svg>

                                  {{ $motherApplication->applicationID ?? 'N/A' }}
                                </span>
                              </div>

                            </div>
                            <div class="flex items-center">
                              <span
                                class="px-3 py-1 text-sm rounded-full {{ $remainingUnits > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $remainingUnits }} units remaining
                              </span>
                            </div>
                          </div>

                          <!-- Main Application Details -->
                          <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <!-- Applicant Information -->
                            <div class="bg-gray-50 p-4 rounded-md">
                              <h3 class="text-md font-medium text-gray-700 mb-3 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                                  viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Applicant Information
                              </h3>
                              <div class="space-y-2 text-sm">
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Applicant Type:</span>
                                  <span class="font-medium">{{ $motherApplication->applicant_type ?? 'N/A' }}</span>
                                </div>
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Name:</span>
                                  <span class="font-medium">
                                    {{ $motherApplication->applicant_title ?? '' }}
                                    {{ $motherApplication->first_name ?? '' }}
                                    {{ $motherApplication->surname ?? '' }}
                                  </span>
                                </div>
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Form ID:</span>
                                  <span class="font-medium">{{ $motherApplication->id ?? 'N/A' }}</span>
                                </div>
                              </div>
                            </div>

                            <!-- Property Information -->
                            <div class="bg-gray-50 p-4 rounded-md">
                              <h3 class="text-md font-medium text-gray-700 mb-3 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none"
                                  viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Property Information
                              </h3>
                              <div class="space-y-2 text-sm">
                                <div class="flex">
                                  <span class="text-gray-500 w-36">File Number:</span>
                                  <span class="font-medium">{{ $motherApplication->fileno ?? 'N/A' }}</span>
                                </div>
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Land Use:</span>
                                  <span class="font-medium">{{ $motherApplication->land_use ?? 'N/A' }}</span>
                                </div>
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Property Location:</span>
                                  <span class="font-medium">{{ $propertyLocation ?: 'N/A' }}</span>
                                </div>
                                <div class="flex">
                                  <span class="text-gray-500 w-36">Total Units:</span>
                                  <span class="font-medium">{{ $totalUnitsInMotherApp }}</span>
                                </div>
                              </div>
                            </div>
                          </div>

                          <!-- Progress indicator -->
                          <div class="mt-5 pt-4 border-t border-gray-200">
                            <div class="flex items-center">
                              <div class="w-full bg-gray-200 rounded-full h-2.5">
                                @php $progressPercent = $totalUnitsInMotherApp > 0 ? (($totalSubApplications / $totalUnitsInMotherApp) * 100) : 0; @endphp
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ $progressPercent }}%"></div>
                              </div>
                              <span
                                class="ml-3 text-sm text-gray-600">{{ $totalSubApplications }}/{{ $totalUnitsInMotherApp }}
                                units registered</span>
                            </div>
                          </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">This sub-application will be linked to the main application
                          referenced above.</p>
                      </div>
                    @else
                      <!-- SUA Allocation Information Section.
                           Read-only here: the answers are given once on the SuA File Number
                           Commissioning form and back-filled below by handleSUAFileSelection()
                           when a SUA file number is picked. The disabled controls are display
                           only - the values actually posted come from the hidden inputs. -->
                      <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-3">Allocation Information</h2>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                          <div class="grid grid-cols-2 gap-4">
                            <!-- Allocation Source -->
                            <div>
                              <label for="allocation_source_display" class="block text-sm font-medium text-gray-700">Allocation
                                Source</label>
                              <select id="allocation_source_display"
                                class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed"
                                disabled title="Set on the SuA File Number Commissioning form">
                                <option value="">Select Allocation Source</option>
                                <option value="State Government" {{ isset($sua) && $sua->allocation_source === 'State Government' ? 'selected' : '' }}>State Government</option>
                                <option value="Local Government" {{ isset($sua) && $sua->allocation_source === 'Local Government' ? 'selected' : '' }}>Local Government (LGA)</option>
                              </select>
                              <input type="hidden" id="allocation_source" name="allocation_source"
                                value="{{ old('allocation_source', isset($sua) ? $sua->allocation_source : '') }}">

                              <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700" for="allocationRefNo">Allocation
                                  Reference No</label>
                                <input type="text" id="allocationRefNo"
                                  class="w-full p-2 border border-gray-300 rounded-md uppercase bg-gray-100 text-gray-500 cursor-not-allowed"
                                  name="allocation_ref_no" readonly
                                  title="Set on the SuA File Number Commissioning form"
                                  placeholder="set at commissioning"
                                  value="{{ old('allocation_ref_no', isset($sua) ? $sua->allocation_ref_no : '') }}">
                              </div>
                            </div>

                            <!-- Allocation Entity -->
                            <div>
                              <label for="allocation_entity_display" class="block text-sm font-medium text-gray-700">Allocation
                                Entity</label>
                              <select id="allocation_entity_display"
                                class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed"
                                disabled title="Set on the SuA File Number Commissioning form">
                                <option value="">Select Entity</option>
                                @if(isset($sua) && $sua->allocation_entity)
                                  <option value="{{ $sua->allocation_entity }}" selected>{{ $sua->allocation_entity }}</option>
                                @endif
                              </select>
                              <input type="hidden" id="allocation_entity" name="allocation_entity"
                                value="{{ old('allocation_entity', isset($sua) ? $sua->allocation_entity : '') }}">
                            </div>
                          </div>
                          <div id="allocation-missing-warning"
                            class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                            This SUA file number was commissioned before Allocation Information moved to the
                            commissioning form, so it has none stored. Set it on the SuA File Number Commissioning
                            form (View/Edit that record), then reselect the file number here.
                          </div>
                          <p class="text-xs text-gray-500 mt-2">
                            Captured on the SuA File Number Commissioning form and filled in here once a SUA file
                            number is selected. SUA applications are standalone and do not require a mother application.
                          </p>
                        </div>
                    @endif
                        
                      @if($isSUA)
                        {{-- SUA File Number Selection Card --}}
                        <div
                          class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-6 shadow-lg mb-6">
                          <div class="flex items-center mb-4">
                            <div class="bg-purple-500 p-3 rounded-lg mr-3">
                              <i data-lucide="search" class="w-6 h-6 text-white"></i>
                            </div>
                            <div class="flex-1">
                              <h3 class="text-lg font-semibold text-gray-900">Select SUA File Number</h3>
                              <p class="text-sm text-gray-600">Choose an existing SUA file number to auto-populate all form
                                fields</p>
                            </div>
                            <div class="bg-purple-600 px-3 py-1 rounded-full shadow-sm">
                              <span class="text-white text-xs font-medium">REQUIRED</span>
                            </div>
                          </div>

                          <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                              SUA File Number <span class="text-red-500">*</span>
                            </label>

                            {{-- Loading State --}}
                            <div id="sua-file-loading" class="hidden mb-4">
                              <div class="flex items-center justify-center py-4">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-purple-600"></div>
                                <span class="ml-2 text-sm text-gray-600">Loading SUA file numbers...</span>
                              </div>
                            </div>

                            {{-- Dropdown --}}
                            <div class="relative">
                              <select id="sua-file-select" name="sua_file_number_id"
                                class="w-full px-4 py-4 bg-white border-2 border-purple-200 rounded-lg text-purple-900 font-mono text-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                onchange="handleSUAFileSelection(this)" required>
                                <option value="">🔍 Select a SUA File Number to begin...</option>
                              </select>
                              <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400"></i>
                              </div>
                            </div>

                            {{-- Selection Preview --}}
                            <div id="sua-selection-preview"
                              class="hidden mt-4 p-4 bg-white rounded-lg border border-purple-200 shadow-sm">
                              <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">Primary FileNo:</div>
                                  <div id="sua-primary-fileno-display" class="font-mono text-lg font-bold text-purple-900">-
                                  </div>
                                </div>
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">MLPP FileNo:</div>
                                  <div id="sua-mls-fileno-display" class="font-mono text-lg font-bold text-purple-700">-
                                  </div>
                                </div>
                              </div>
                              <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">SUA FileNo:</div>
                                  <div id="sua-fileno-display" class="font-mono text-lg font-bold text-green-700">-</div>
                                </div>
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">Land Use:</div>
                                  <div id="sua-land-use-display" class="text-lg font-bold text-purple-700">-</div>
                                </div>
                              </div>
                              <div class="grid grid-cols-1 gap-4 mb-3">
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">Tracking ID:</div>
                                  <div id="sua-tracking-id-display" class="font-mono text-base font-bold text-red-600">-
                                  </div>
                                </div>
                              </div>
                              <div class="grid grid-cols-2 gap-4 pt-3 border-t border-purple-100">
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">Applicant Type:</div>
                                  <div id="sua-applicant-type-display" class="text-lg font-bold text-purple-700">-</div>
                                </div>
                                <div>
                                  <div class="text-sm text-purple-600 font-medium">Applicant:</div>
                                  <div id="sua-applicant-display" class="text-lg font-bold text-purple-900">-</div>
                                </div>
                              </div>
                            </div>

                            <div class="mt-4 p-3 bg-purple-50 rounded-lg border border-purple-200">
                              <div class="flex items-start">
                                <i data-lucide="info" class="w-4 h-4 text-purple-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                <p class="text-xs text-purple-800">
                                  <strong>Important:</strong> Select a SUA file number first to automatically populate all
                                  form sections including applicant information, land use, and file details.
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>

                        {{-- Hidden SUA File Number Fields --}}
                        <input type="hidden" name="np_fileno" id="hidden-sua-np-fileno" value="">
                        <input type="hidden" name="mls_fileno" id="hidden-sua-mls-fileno" value="">
                        <input type="hidden" name="fileno" id="hidden-sua-fileno" value="">
                        <input type="hidden" name="land_use" id="sua_land_use_hidden" value="">
                        <input type="hidden" name="sua_file_id" id="hidden-sua-file-id" value="">
                        <input type="hidden" name="tracking_id" id="hidden-tracking-id" value="">
                        <input type="hidden" name="st_batch_no" id="hidden-st-batch-no" value="">

                        {{-- Date and Scheme Fields for SUA --}}
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                          <div>
                            <label class="block text-sm mb-1">Scheme No</label>
                            <input type="text" id="schemeName" class="w-full p-2 border border-gray-300 rounded-md"
                              name="scheme_no" placeholder="enter scheme number. eg: ST/SP/0001"
                              value="{{ old('scheme_no', isset($sua) ? $sua->scheme_no : '') }}">
                          </div>
                          <div>
                            <label class="block text-sm mb-1">Application Date</label>
                            <input type="date" class="w-full p-2 border border-gray-300 rounded-md" name="application_date"
                              value="{{ old('application_date', isset($sua) ? $sua->application_date : date('Y-m-d')) }}">
                          </div>
                          <div>
                            <label class="block text-sm mb-1">Date Captured</label>
                            <input type="date"
                              class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                              name="current_date_captured" value="{{ date('Y-m-d') }}" readonly>
                          </div>

                        <div>
                                <label class="block text-sm mb-1">Value of Improvement</label>
                                <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="improvement_value"
                                  placeholder="Enter value"
                                  value="{{ old('improvement_value', isset($sua) ? $sua->improvement_value : '') }}">
                              </div>

                            </div>

                      @else
                          {{-- Unit/PUA File Number Selection Card --}}
                          <div
                            class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 shadow-lg mb-6">
                            <div class="flex items-center mb-4">
                              <div class="bg-blue-500 p-3 rounded-lg mr-3">
                                <i data-lucide="search" class="w-6 h-6 text-white"></i>
                              </div>
                              <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900">Select Unit File Number</h3>
                                <p class="text-sm text-gray-600">Choose an existing unit file number to auto-populate all form
                                  fields</p>
                              </div>
                              <div class="bg-blue-600 px-3 py-1 rounded-full shadow-sm">
                                <span class="text-white text-xs font-medium">REQUIRED</span>
                              </div>
                            </div>

                            <div class="space-y-4">
                              <label class="block text-sm font-medium text-gray-700 mb-3">
                                Unit File Number <span class="text-red-500">*</span>
                              </label>

                              {{-- Loading State --}}
                              <div id="unit-file-loading" class="hidden mb-4">
                                <div class="flex items-center justify-center py-4">
                                  <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                  <span class="ml-2 text-sm text-gray-600">Loading unit file numbers...</span>
                                </div>
                              </div>

                              {{-- Dropdown --}}
                              <div class="relative">
                                <select id="unit-file-select" name="unit_file_number_id"
                                  class="w-full px-4 py-4 bg-white border-2 border-blue-200 rounded-lg text-blue-900 font-mono text-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                                  onchange="handleUnitFileSelection(this)" required>
                                  <option value="">🔍 Select a Unit File Number to begin...</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                  <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400"></i>
                                </div>
                              </div>

                              {{-- Selection Preview --}}
                              <div id="unit-selection-preview"
                                class="hidden mt-4 p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">NP FileNo (NPFN):</div>
                                    <div id="unit-np-fileno-display" class="font-mono text-lg font-bold text-blue-900">-</div>
                                  </div>
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">Unit FileNo:</div>
                                    <div id="unit-fileno-display" class="font-mono text-lg font-bold text-green-700">-</div>
                                  </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">Land Use:</div>
                                    <div id="unit-land-use-display" class="text-lg font-bold text-blue-700">-</div>
                                  </div>
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">Tracking ID:</div>
                                    <div id="unit-tracking-id-display" class="font-mono text-base font-bold text-red-600">-
                                    </div>
                                  </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 pt-3 border-t border-blue-100">
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">Applicant Type:</div>
                                    <div id="unit-applicant-type-display" class="text-lg font-bold text-blue-700">-</div>
                                  </div>
                                  <div>
                                    <div class="text-sm text-blue-600 font-medium">Applicant:</div>
                                    <div id="unit-applicant-display" class="text-lg font-bold text-blue-900">-</div>
                                  </div>
                                </div>
                              </div>

                              <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex items-start">
                                  <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                  <p class="text-xs text-blue-800">
                                    <strong>Important:</strong> Select a unit file number first. The NPFN will fetch the Main
                                    Application Reference info automatically.
                                  </p>
                                </div>
                              </div>
                            </div>
                          </div>

                          {{-- Hidden Unit File Number Fields --}}
                          <input type="hidden" name="np_fileno" id="hidden-unit-np-fileno" value="">
                          <input type="hidden" name="fileno" id="hidden-unit-fileno" value="">
                          <input type="hidden" name="unit_file_id" id="hidden-unit-file-id" value="">

                          {{-- Date and Scheme Fields for Unit --}}
                          <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div>
                              <label class="block text-sm mb-1">Scheme No <span class="text-red-500">*</span></label>
                              <input type="text" id="schemeName" class="w-full p-2 border border-gray-300 rounded-md"
                                name="scheme_no" placeholder="enter scheme number. eg: ST/SP/0001"
                                value="{{ old('scheme_no', isset($sua) ? $sua->scheme_no : (isset($application) ? $application->scheme_no : '')) }}">
                            </div>
                            @if($isSUA)
                              <div>
                                <label class="block text-sm mb-1">Allocation Slip No</label>
                                <input type="text" id="allocationRefNo"
                                  class="w-full p-2 border border-gray-300 rounded-md uppercase" name="allocation_ref_no"
                                  placeholder="enter allocation slip no. eg: ALS/2025/001"
                                  value="{{ old('allocation_ref_no', isset($sua) ? $sua->allocation_ref_no : '') }}"
                                  oninput="this.value = this.value.toUpperCase();">
                              </div>
                            @endif
                            <div>
                              <label class="block text-sm mb-1">Application Date</label>
                              <input type="date" class="w-full p-2 border border-gray-300 rounded-md" name="date_captured"
                                value="{{ old('date_captured', isset($application) ? $application->date_captured : date('Y-m-d')) }}">
                            </div>
                            <div>
                              <label class="block text-sm mb-1">Date Captured</label>
                              <input type="date"
                                class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                                name="current_date_captured" value="{{ date('Y-m-d') }}" readonly>
                            </div>
                            <div>
                              <label class="block text-sm mb-1">Value of Improvement</label>
                              <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="improvement_value"
                                placeholder="Enter value"
                                value="{{ old('improvement_value', isset($application) ? $application->improvement_value : '') }}">
                            </div>
                          </div>

                          <!-- Land Use Field for Regular Sub-Applications (Unit Mode) -->
                          <div class="grid grid-cols-2 gap-6 mb-6">
                            <!-- Unit Land Use Field Column -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                              <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-1"></i>
                                Select Land Use <span class="text-red-500">*</span>
                                {{-- <span class="text-xs font-normal text-yellow-600">(Important: This determines
                                  unit-specific fees)</span> --}}
                              </label>
                              @if($isMixedLandUse)
                                <!-- MIXED Land Use Dropdown -->
                                <div class="space-y-2">
                                  <select name="specific_land_use" id="unit_land_use"
                                    class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required onchange="updateUnitFees(this.value)">
                                    <option value="">Select Land Use for This Unit</option>
                                    <option value="Residential">Residential</option>
                                    <option value="Commercial">Commercial</option>
                                  </select>
                                  <p class="text-xs text-gray-500">
                                    <strong>Primary Application Land Use:</strong> {{ $motherApplication->land_use ?? 'N/A' }}
                                    <br>Select specific land use for this unit (different from primary application)
                                  </p>
                                </div>
                              @else
                                <!-- Regular Land Use Display -->
                                <div class="space-y-2">
                                  <div class="flex items-center">
                                    <span class="bg-green-100 text-green-800 px-3 py-2 rounded text-sm font-medium">
                                      {{ $motherApplication->land_use ?? 'N/A' }}
                                    </span>
                                    <span class="ml-2 text-xs text-gray-500">(Inherited from primary application)</span>
                                  </div>
                                  <!-- Hidden field for non-mixed land use -->
                                  <input type="hidden" name="land_use" value="{{ $motherApplication->land_use ?? '' }}">
                                  <p class="text-xs text-gray-500">This unit inherits the land use from the primary application
                                  </p>
                                </div>
                              @endif
                            </div>
                          </div>
                        @endif
                        <input value="PUA" type="hidden" name="unit_type">

                        <!-- Unit Land Use Information Card Column (Hidden for now) -->
                        @if(!$isSUA)
                          <div style="display: none;">
                            <div class="bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-200 rounded-lg p-4"
                              style="display: none;">
                              <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-info-circle text-purple-600 mr-1"></i>
                                Unit Land Use Information
                              </h4>
                              <div class="space-y-2 text-xs">
                                <div class="bg-white/50 rounded p-2">
                                  <strong>Application Type:</strong> Unit Application (Secondary)
                                </div>
                                <div class="bg-white/50 rounded p-2">
                                  <strong>Primary App Land Use:</strong>
                                  <span class="text-purple-600 font-medium">{{ $motherApplication->land_use ?? 'N/A' }}</span>
                                </div>
                                @if($isMixedLandUse)
                                  <div class="bg-white/50 rounded p-2">
                                    <strong>Unit Selection:</strong>
                                    <span id="unit_selected_land_use" class="text-orange-600 font-medium">Not selected</span>
                                  </div>
                                  <div class="bg-white/50 rounded p-2">
                                    <strong>Fee Impact:</strong>
                                    <span id="unit_fee_impact" class="text-green-600">Select land use to view fees</span>
                                  </div>
                                @else
                                  <div class="bg-white/50 rounded p-2">
                                    <strong>Unit Land Use:</strong>
                                    <span class="text-green-600 font-medium">{{ $motherApplication->land_use ?? 'N/A' }}
                                      (Inherited)</span>
                                  </div>
                                @endif
                                <div class="text-xs text-gray-500 mt-2">
                                  <i class="fas fa-lightbulb mr-1"></i>
                                  @if($isMixedLandUse)
                                    MIXED applications allow individual unit land use selection
                                  @else
                                    This unit inherits land use from the primary application
                                  @endif
                                </div>
                              </div>
                            </div>
                        @endif
                        </div>


                        <!-- Hidden Applicant Type (Auto-filled from API) -->
                        <input type="hidden" name="applicant_type" id="mainApplicantTypeInput" value="">

                        @include('sectionaltitling.partials.subapplication.applicant')

                      </div>
                  </div>


                  <div class="bg-gray-50 p-4 rounded-md mb-6">



                    <div class="grid grid-cols-2 gap-4 mb-4">


                      <div style="display: none">
                        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="prefix"
                          value="{{ $prefix }}">
                        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="year"
                          value="{{ $currentYear }}">
                        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="serial_number"
                          value="{{ $formattedSerialNumber }}">

                      </div>



                    </div>
                  </div>

                  <div class="bg-gray-50 p-4 rounded-md mb-6">

                    <div class="mb-4">
                      <p class="text-sm mb-1">Unit Owner's Address</p>
                      <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                          <label class="block text-sm mb-1">House No. </label>
                          <input type="text" id="ownerHouseNo" class="w-full p-2 border border-gray-300 rounded-md"
                            placeholder="HOUSE NO." name="address_house_no" style="text-transform:uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                          <label class="block text-sm mb-1">Street Name </label>
                          <input type="text" id="ownerStreetName" class="w-full p-2 border border-gray-300 rounded-md"
                            placeholder="STREET NAME" name="address_street_name" style="text-transform:uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                        </div>
                      </div>

                      <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                          <label class="block text-sm mb-1">District </label>
                          <input type="text" id="ownerDistrict" class="w-full p-2 border border-gray-300 rounded-md"
                            placeholder="DISTRICT" name="address_district" style="text-transform:uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                        </div>

                        <div>
                          <label class="block text-sm mb-1">State <span class="text-red-500">*</span></label>
                          <select id="ownerState" name="address_state" class="w-full p-2 border border-gray-300 rounded-md"
                            onchange="selectLGA(this)" style="text-transform:uppercase">
                            <option value="">SELECT STATE</option>
                          </select>
                        </div>

                        <div>
                          <label class="block text-sm mb-1">LGA <span class="text-red-500">*</span></label>
                          <select id="ownerLga" name="address_lga" class="w-full p-2 border border-gray-300 rounded-md"
                            style="text-transform:uppercase">
                            <option value="">SELECT LGA</option>
                          </select>
                        </div>

                        <script>
                          // Hardcoded Nigeria States and LGAs
                          const nigeriaStatesAndLGAs = {
                            "Abia": ["Aba North", "Aba South", "Arochukwu", "Bende", "Ikwuano", "Isiala Ngwa North", "Isiala Ngwa South", "Isuikwuato", "Obi Ngwa", "Ohafia", "Osisioma", "Ugwunagbo", "Ukwa East", "Ukwa West", "Umuahia North", "Umuahia South", "Umu Nneochi"],
                            "Adamawa": ["Demsa", "Fufure", "Ganye", "Gayuk", "Gombi", "Grie", "Hong", "Jada", "Lamurde", "Madagali", "Maiha", "Mayo Belwa", "Michika", "Mubi North", "Mubi South", "Numan", "Shelleng", "Song", "Toungo", "Yola North", "Yola South"],
                            "Akwa Ibom": ["Abak", "Eastern Obolo", "Eket", "Esit Eket", "Essien Udim", "Etim Ekpo", "Etinan", "Ibeno", "Ibesikpo Asutan", "Ibiono-Ibom", "Ika", "Ikono", "Ikot Abasi", "Ikot Ekpene", "Ini", "Itu", "Mbo", "Mkpat-Enin", "Nsit-Atai", "Nsit-Ibom", "Nsit-Ubium", "Obot Akara", "Okobo", "Onna", "Oron", "Oruk Anam", "Udung-Uko", "Ukanafun", "Uruan", "Urue-Offong/Oruko", "Uyo"],
                            "Anambra": ["Aguata", "Anambra East", "Anambra West", "Anaocha", "Awka North", "Awka South", "Ayamelum", "Dunukofia", "Ekwusigo", "Idemili North", "Idemili South", "Ihiala", "Njikoka", "Nnewi North", "Nnewi South", "Ogbaru", "Onitsha North", "Onitsha South", "Orumba North", "Orumba South", "Oyi"],
                            "Bauchi": ["Alkaleri", "Bauchi", "Bogoro", "Damban", "Darazo", "Dass", "Gamawa", "Ganjuwa", "Giade", "Itas/Gadau", "Jama'are", "Katagum", "Kirfi", "Misau", "Ningi", "Shira", "Tafawa Balewa", "Toro", "Warji", "Zaki"],
                            "Bayelsa": ["Brass", "Ekeremor", "Kolokuma/Opokuma", "Nembe", "Ogbia", "Sagbama", "Southern Ijaw", "Yenagoa"],
                            "Benue": ["Ado", "Agatu", "Apa", "Buruku", "Gboko", "Guma", "Gwer East", "Gwer West", "Katsina-Ala", "Konshisha", "Kwande", "Logo", "Makurdi", "Obi", "Ogbadibo", "Ohimini", "Oju", "Okpokwu", "Otukpo", "Tarka", "Ukum", "Ushongo", "Vandeikya"],
                            "Borno": ["Abadam", "Askira/Uba", "Bama", "Bayo", "Biu", "Chibok", "Damboa", "Dikwa", "Gubio", "Guzamala", "Gwoza", "Hawul", "Jere", "Kaga", "Kala/Balge", "Konduga", "Kukawa", "Kwaya Kusar", "Mafa", "Magumeri", "Maiduguri", "Marte", "Mobbar", "Monguno", "Ngala", "Nganzai", "Shani"],
                            "Cross River": ["Abi", "Akamkpa", "Akpabuyo", "Bakassi", "Bekwarra", "Biase", "Boki", "Calabar Municipal", "Calabar South", "Etung", "Ikom", "Obanliku", "Obubra", "Obudu", "Odukpani", "Ogoja", "Yakuur", "Yala"],
                            "Delta": ["Aniocha North", "Aniocha South", "Bomadi", "Burutu", "Ethiope East", "Ethiope West", "Ika North East", "Ika South", "Isoko North", "Isoko South", "Ndokwa East", "Ndokwa West", "Okpe", "Oshimili North", "Oshimili South", "Patani", "Sapele", "Udu", "Ughelli North", "Ughelli South", "Ukwuani", "Uvwie", "Warri North", "Warri South", "Warri South West"],
                            "Ebonyi": ["Abakaliki", "Afikpo North", "Afikpo South", "Ebonyi", "Ezza North", "Ezza South", "Ikwo", "Ishielu", "Ivo", "Izzi", "Ohaozara", "Ohaukwu", "Onicha"],
                            "Edo": ["Akoko-Edo", "Egor", "Esan Central", "Esan North-East", "Esan South-East", "Esan West", "Etsako Central", "Etsako East", "Etsako West", "Igueben", "Ikpoba Okha", "Orhionmwon", "Oredo", "Ovia North-East", "Ovia South-West", "Owan East", "Owan West", "Uhunmwonde"],
                            "Ekiti": ["Ado Ekiti", "Efon", "Ekiti East", "Ekiti South-West", "Ekiti West", "Emure", "Gbonyin", "Ido Osi", "Ijero", "Ikere", "Ikole", "Ilejemeje", "Irepodun/Ifelodun", "Ise/Orun", "Moba", "Oye"],
                            "Enugu": ["Aninri", "Awgu", "Enugu East", "Enugu North", "Enugu South", "Ezeagu", "Igbo Etiti", "Igbo Eze North", "Igbo Eze South", "Isi Uzo", "Nkanu East", "Nkanu West", "Nsukka", "Oji River", "Udenu", "Udi", "Uzo Uwani"],
                            "FCT": ["Abaji", "Bwari", "Gwagwalada", "Kuje", "Kwali", "Municipal Area Council"],
                            "Gombe": ["Akko", "Balanga", "Billiri", "Dukku", "Funakaye", "Gombe", "Kaltungo", "Kwami", "Nafada", "Shongom", "Yamaltu/Deba"],
                            "Imo": ["Aboh Mbaise", "Ahiazu Mbaise", "Ehime Mbano", "Ezinihitte", "Ideato North", "Ideato South", "Ihitte/Uboma", "Ikeduru", "Isiala Mbano", "Isu", "Mbaitoli", "Ngor Okpala", "Njaba", "Nkwerre", "Nwangele", "Obowo", "Oguta", "Ohaji/Egbema", "Okigwe", "Orlu", "Orsu", "Oru East", "Oru West", "Owerri Municipal", "Owerri North", "Owerri West"],
                            "Jigawa": ["Auyo", "Babura", "Biriniwa", "Birnin Kudu", "Buji", "Dutse", "Gagarawa", "Garki", "Gumel", "Guri", "Gwaram", "Gwiwa", "Hadejia", "Jahun", "Kafin Hausa", "Kaugama", "Kazaure", "Kiri Kasama", "Kiyawa", "Maigatari", "Malam Madori", "Miga", "Ringim", "Roni", "Sule Tankarkar", "Taura", "Yankwashi"],
                            "Kaduna": ["Birnin Gwari", "Chikun", "Giwa", "Igabi", "Ikara", "Jaba", "Jema'a", "Kachia", "Kaduna North", "Kaduna South", "Kagarko", "Kajuru", "Kaura", "Kauru", "Kubau", "Kudan", "Lere", "Makarfi", "Sabon Gari", "Sanga", "Soba", "Zangon Kataf", "Zaria"],
                            "Kano": ["Ajingi", "Albasu", "Bagwai", "Bebeji", "Bichi", "Bunkure", "Dala", "Dambatta", "Dawakin Kudu", "Dawakin Tofa", "Doguwa", "Fagge", "Gabasawa", "Garko", "Garun Mallam", "Gaya", "Gezawa", "Gwale", "Gwarzo", "Kabo", "Kano Municipal", "Karaye", "Kibiya", "Kiru", "Kumbotso", "Kunchi", "Kura", "Madobi", "Makoda", "Minjibir", "Nasarawa", "Rano", "Rimin Gado", "Rogo", "Shanono", "Sumaila", "Takai", "Tarauni", "Tofa", "Tsanyawa", "Tudun Wada", "Ungogo", "Warawa", "Wudil"],
                            "Katsina": ["Bakori", "Batagarawa", "Batsari", "Baure", "Bindawa", "Charanchi", "Dandume", "Danja", "Dan Musa", "Daura", "Dutsi", "Dutsin Ma", "Faskari", "Funtua", "Ingawa", "Jibia", "Kafur", "Kaita", "Kankara", "Kankia", "Katsina", "Kurfi", "Kusada", "Mai'Adua", "Malumfashi", "Mani", "Mashi", "Matazu", "Musawa", "Rimi", "Sabuwa", "Safana", "Sandamu", "Zango"],
                            "Kebbi": ["Aleiro", "Arewa Dandi", "Argungu", "Augie", "Bagudo", "Birnin Kebbi", "Bunza", "Dandi", "Fakai", "Gwandu", "Jega", "Kalgo", "Koko/Besse", "Maiyama", "Ngaski", "Sakaba", "Shanga", "Suru", "Wasagu/Danko", "Yauri", "Zuru"],
                            "Kogi": ["Adavi", "Ajaokuta", "Ankpa", "Bassa", "Dekina", "Ibaji", "Idah", "Igalamela Odolu", "Ijumu", "Kabba/Bunu", "Kogi", "Lokoja", "Mopa Muro", "Ofu", "Ogori/Magongo", "Okehi", "Okene", "Olamaboro", "Omala", "Yagba East", "Yagba West"],
                            "Kwara": ["Asa", "Baruten", "Edu", "Ekiti", "Ifelodun", "Ilorin East", "Ilorin South", "Ilorin West", "Irepodun", "Isin", "Kaiama", "Moro", "Offa", "Oke Ero", "Oyun", "Pategi"],
                            "Lagos": ["Agege", "Ajeromi-Ifelodun", "Alimosho", "Amuwo-Odofin", "Apapa", "Badagry", "Epe", "Eti Osa", "Ibeju-Lekki", "Ifako-Ijaiye", "Ikeja", "Ikorodu", "Kosofe", "Lagos Island", "Lagos Mainland", "Mushin", "Ojo", "Oshodi-Isolo", "Shomolu", "Surulere"],
                            "Nasarawa": ["Akwanga", "Awe", "Doma", "Karu", "Keana", "Keffi", "Kokona", "Lafia", "Nasarawa", "Nasarawa Egon", "Obi", "Toto", "Wamba"],
                            "Niger": ["Agaie", "Agwara", "Bida", "Borgu", "Bosso", "Chanchaga", "Edati", "Gbako", "Gurara", "Katcha", "Kontagora", "Lapai", "Lavun", "Magama", "Mariga", "Mashegu", "Mokwa", "Muya", "Pailoro", "Rafi", "Rijau", "Shiroro", "Suleja", "Tafa", "Wushishi"],
                            "Ogun": ["Abeokuta North", "Abeokuta South", "Ado-Odo/Ota", "Egbado North", "Egbado South", "Ewekoro", "Ifo", "Ijebu East", "Ijebu North", "Ijebu North East", "Ijebu Ode", "Ikenne", "Imeko Afon", "Ipokia", "Obafemi Owode", "Odeda", "Odogbolu", "Ogun Waterside", "Remo North", "Shagamu"],
                            "Ondo": ["Akoko North-East", "Akoko North-West", "Akoko South-East", "Akoko South-West", "Akure North", "Akure South", "Ese Odo", "Idanre", "Ifedore", "Ilaje", "Ile Oluji/Okeigbo", "Irele", "Odigbo", "Okitipupa", "Ondo East", "Ondo West", "Ose", "Owo"],
                            "Osun": ["Atakunmosa East", "Atakunmosa West", "Aiyedaade", "Aiyedire", "Boluwaduro", "Boripe", "Ede North", "Ede South", "Egbedore", "Ejigbo", "Ife Central", "Ife East", "Ife North", "Ife South", "Ifedayo", "Ifelodun", "Ila", "Ilesa East", "Ilesa West", "Irepodun", "Irewole", "Isokan", "Iwo", "Obokun", "Odo Otin", "Ola Oluwa", "Olorunda", "Oriade", "Orolu", "Osogbo"],
                            "Oyo": ["Afijio", "Akinyele", "Atiba", "Atisbo", "Egbeda", "Ibadan North", "Ibadan North-East", "Ibadan North-West", "Ibadan South-East", "Ibadan South-West", "Ibarapa Central", "Ibarapa East", "Ibarapa North", "Ido", "Irepo", "Iseyin", "Itesiwaju", "Iwajowa", "Kajola", "Lagelu", "Ogbomosho North", "Ogbomosho South", "Ogo Oluwa", "Olorunsogo", "Oluyole", "Ona Ara", "Orelope", "Ori Ire", "Oyo East", "Oyo West", "Saki East", "Saki West", "Surulere"],
                            "Plateau": ["Barkin Ladi", "Bassa", "Bokkos", "Jos East", "Jos North", "Jos South", "Kanam", "Kanke", "Langtang North", "Langtang South", "Mangu", "Mikang", "Pankshin", "Qua'an Pan", "Riyom", "Shendam", "Wase"],
                            "Rivers": ["Abua/Odual", "Ahoada East", "Ahoada West", "Akuku-Toru", "Andoni", "Asari-Toru", "Bonny", "Degema", "Eleme", "Emohua", "Etche", "Gokana", "Ikwerre", "Khana", "Obio/Akpor", "Ogba/Egbema/Ndoni", "Ogu/Bolo", "Okrika", "Omuma", "Opobo/Nkoro", "Oyigbo", "Port Harcourt", "Tai"],
                            "Sokoto": ["Binji", "Bodinga", "Dange Shuni", "Gada", "Goronyo", "Gudu", "Gwadabawa", "Illela", "Isa", "Kebbe", "Kware", "Rabah", "Sabon Birni", "Shagari", "Silame", "Sokoto North", "Sokoto South", "Tambuwal", "Tangaza", "Tureta", "Wamako", "Wurno", "Yabo"],
                            "Taraba": ["Ardo Kola", "Bali", "Donga", "Gashaka", "Gassol", "Ibi", "Jalingo", "Karim Lamido", "Kurmi", "Lau", "Sardauna", "Takum", "Ussa", "Wukari", "Yorro", "Zing"],
                            "Yobe": ["Bade", "Bursari", "Damaturu", "Fika", "Fune", "Geidam", "Gujba", "Gulani", "Jakusko", "Karasuwa", "Machina", "Nangere", "Nguru", "Potiskum", "Tarmuwa", "Yunusari", "Yusufari"],
                            "Zamfara": ["Anka", "Bakura", "Birnin Magaji/Kiyaw", "Bukkuyum", "Bungudu", "Chafe", "Gummi", "Gusau", "Kaura Namoda", "Maradun", "Maru", "Shinkafi", "Talata Mafara", "Zurmi"]
                          };

                          document.addEventListener("DOMContentLoaded", function () {
                            populateStates();
                          });

                          function populateStates() {
                            var stateSelect = document.getElementById("ownerState");
                            // Clear existing options except first
                            while (stateSelect.options.length > 1) {
                              stateSelect.remove(1);
                            }

                            const states = Object.keys(nigeriaStatesAndLGAs).sort();
                            states.forEach(state => {
                              var option = document.createElement("option");
                              option.text = state;
                              option.value = state;
                              stateSelect.add(option);
                            });
                          }

                          // Fetch Local Governments based on selected state
                          function selectLGA(target) {
                            var state = target.value;
                            var lgaSelect = document.getElementById("ownerLga");

                            // Clear existing options
                            while (lgaSelect.options.length > 1) {
                              lgaSelect.remove(1);
                            }

                            if (!state) {
                              // lgaSelect.disabled = true; // No need to disable if using local data, just clear
                              return;
                            }

                            // lgaSelect.disabled = false;

                            const lgas = nigeriaStatesAndLGAs[state];
                            if (lgas) {
                              lgas.sort().forEach(lga => {
                                var option = document.createElement("option");
                                option.text = lga;
                                option.value = lga;
                                lgaSelect.add(option);
                              });
                            }
                          }
                        </script>

                      </div>
                      <input type="text" name="address" id="contactAddressHidden">
                      <div class="mb-4">
                        <label class="block text-sm mb-1">Contact Address: <span class="text-red-500">*</span></label>
                        <div id="contactAddressDisplay" class="p-2 bg-gray-50 border border-gray-200 rounded-md">
                          <span id="fullContactAddress" style="text-transform:uppercase"></span>
                        </div>
                      </div>

                      <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                          <label class="block text-sm mb-1">Phone No. 1</label>
                          <input type="text" class="w-full p-2 border border-gray-300 rounded-md"
                            placeholder="ENTER PHONE NUMBER" name="phone_number[]" style="text-transform:uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div>
                          <label class="block text-sm mb-1">Phone No. 2</label>
                          <input type="text" class="w-full p-2 border border-gray-300 rounded-md"
                            placeholder="ENTER ALTERNATE PHONE" name="phone_number[]" style="text-transform:uppercase"
                            oninput="this.value = this.value.toUpperCase()">
                        </div>
                      </div>

                      <div>
                        <label class="block text-sm mb-1">Email Address</label>
                        <input type="email" class="w-full p-2 border border-gray-300 rounded-md"
                          placeholder="ENTER EMAIL ADDRESS" name="email">
                      </div>
                    </div>
                  </div>
                  <script>
                    document.addEventListener('DOMContentLoaded', function () {
                      const addressFields = [
                        'ownerHouseNo',
                        'ownerStreetName',
                        'ownerDistrict',
                        'ownerLga',
                        'ownerState'
                      ];
                      const contactInput = document.getElementById('contactAddressHidden');
                      const contactDisplay = document.getElementById('fullContactAddress');
                      const form = document.getElementById('subApplicationForm');

                      function buildContactAddress() {
                        return addressFields
                          .map(id => document.getElementById(id)?.value?.trim())
                          .filter(Boolean)
                          .join(', ')
                          .toUpperCase();
                      }

                      function syncContactAddress() {
                        const address = buildContactAddress();
                        if (contactInput) contactInput.value = address;
                        if (contactDisplay) contactDisplay.textContent = address;
                      }

                      addressFields.forEach(id => {
                        const field = document.getElementById(id);
                        if (!field) return;
                        ['input', 'change', 'blur'].forEach(evt => field.addEventListener(evt, syncContactAddress));
                      });

                      if (form) {
                        form.addEventListener('submit', syncContactAddress);
                      }

                      syncContactAddress();
                    });
                  </script>
                  <div class="bg-gray-50 p-4 rounded-md mb-6" id="mainIdentificationSection">
                    <!-- Left column: Means of Identification options -->
                    <div id="meansOfIdentificationOptions">
                      <label class="block mb-2 font-medium">Means of Identification</label>
                      <div class="grid grid-cols-1 gap-2">
                        <label class="flex items-center">
                          <input type="radio" name="identification_type" class="mr-2" value="national id" checked>
                          <span>National ID</span>
                        </label>
                        <label class="flex items-center">
                          <input type="radio" name="identification_type" class="mr-2" value="drivers license">
                          <span>Driver's License</span>
                        </label>
                        <label class="flex items-center">
                          <input type="radio" name="identification_type" class="mr-2" value="voters card">
                          <span>Voter's Card</span>
                        </label>
                        <label class="flex items-center">
                          <input type="radio" name="identification_type" class="mr-2" value="international passport">
                          <span>International Passport</span>
                        </label>
                        <label class="flex items-center">
                          <input type="radio" name="identification_type" class="mr-2" value="others">
                          <span>Others</span>
                        </label>
                      </div>
                    </div>
                    <!-- Right column: Image upload and preview -->
                    <div class="flex flex-col justify-between">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="uploadIdentificationLabel">Upload
                          Means of Identification <span class="text-red-500">*</span></label>
                        <input type="file" name="identification_image" id="identification_image" accept="image/*,.pdf"
                          class="w-full p-2 border border-gray-300 rounded-md bg-white">
                        <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF. Max size: 5MB.</p>
                      </div>
                      <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preview</label>
                        <div id="identification_preview"
                          class="border border-gray-200 rounded-md bg-white flex items-center justify-center min-h-[120px]">
                          <span class="text-gray-400 text-xs">No file selected</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <script>
                    document.addEventListener('DOMContentLoaded', function () {
                      const input = document.getElementById('identification_image');
                      const preview = document.getElementById('identification_preview');
                      input.addEventListener('change', function (e) {
                        preview.innerHTML = '';
                        const file = e.target.files[0];
                        if (!file) {
                          preview.innerHTML = '<span class="text-gray-400 text-xs">No file selected</span>';
                          return;
                        }
                        if (file.type.startsWith('image/')) {
                          const img = document.createElement('img');
                          img.className = "max-h-32 mx-auto";
                          img.style.maxWidth = "100%";
                          img.alt = "Preview";
                          img.src = URL.createObjectURL(file);
                          preview.appendChild(img);
                        } else if (file.type === 'application/pdf') {
                          const icon = document.createElement('span');
                          icon.innerHTML = '<svg class="w-8 h-8 text-red-500 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span class="block text-xs mt-2">PDF Selected</span>';
                          preview.appendChild(icon);
                        } else {
                          preview.innerHTML = '<span class="text-red-500 text-xs">Unsupported file type</span>';
                        }
                      });
                    });
                  </script>

                  <div class="bg-gray-50 p-4 rounded-md mb-6">
                    <h3 class="font-medium mb-4">Unit Details</h3>

                    @include('sectionaltitling.types.ownership')
                    @include('sectionaltitling.types.commercial')
                    @include('sectionaltitling.types.residential')
                    @include('sectionaltitling.types.industrial')


                    <div class="grid grid-cols-3 gap-4 mb-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700">Block No <span
                            class="text-red-500">*</span></label>
                        <input type="text" name="block_number" id="block_number"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Enter block number" required onchange="updatePropertyLocation()">
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700">Section No (Floor) <span
                            class="text-red-500">*</span></label>
                        <input type="text" name="floor_number" id="floor_number"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Enter floor number" required onchange="updatePropertyLocation()">
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700">Unit No <span
                            class="text-red-500">*</span></label>
                        <input type="text" name="unit_number" id="unit_number"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $isSUA ? '' : 'bg-gray-100 text-gray-700 cursor-not-allowed' }}"
                          placeholder="Enter unit number" {{ $isSUA ? '' : 'readonly' }}
                          value="{{ $isSUA && isset($sua) ? $sua->unit_number : '' }}" onchange="updatePropertyLocation()">
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700">Unit Size </label>
                        <input type="text" name="unit_size"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $isSUA ? '' : 'bg-gray-100 text-gray-700 cursor-not-allowed' }}"
                          placeholder="Enter unit size (e.g. 120 sqm)" {{ $isSUA ? '' : 'readonly' }}
                          value="{{ $isSUA && isset($sua) ? $sua->unit_area : '' }}">
                      </div>
                    </div>

                    <!-- Location Details -->
                    <div class="grid grid-cols-3 gap-4 mb-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700">District </label>
                        <input type="text" name="unit_district" id="unit_district"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Enter district" required onchange="updatePropertyLocation()"
                          style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700">LGA <span
                            class="text-red-500">*</span></label>
                        <select name="unit_lga" id="unit_lga"
                          class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          required onchange="updatePropertyLocation()">
                          <option value="">SELECT LGA</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700">State</label>
                        <input type="text" name="unit_state" id="unit_state"
                          class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700 cursor-not-allowed"
                          value="KANO" readonly>
                      </div>


                      <script>
                        // Fetch Kano State LGAs
                        document.addEventListener('DOMContentLoaded', function () {
                          fetch('https://nga-states-lga.onrender.com/?state=Kano')
                            .then((res) => res.json())
                            .then((data) => {
                              var lgaSelect = document.getElementById("unit_lga");

                              for (let index = 0; index < Object.keys(data).length; index++) {
                                var option = document.createElement("option");
                                option.text = data[index];
                                option.value = data[index];
                                lgaSelect.add(option);
                              }
                            })
                            .catch(error => console.error('Error fetching Kano LGAs:', error));
                        });

                        // Function to update Property Location automatically
                        function updatePropertyLocation() {
                          const blockNoEl = document.getElementById('block_number');
                          const floorNoEl = document.getElementById('floor_number');
                          const unitNoEl = document.getElementById('unit_number');
                          const districtEl = document.getElementById('unit_district');
                          const lgaEl = document.getElementById('unit_lga');
                          const stateEl = document.getElementById('unit_state');

                          const blockNo = blockNoEl ? blockNoEl.value : '';
                          const floorNo = floorNoEl ? floorNoEl.value : '';
                          const unitNo = unitNoEl ? unitNoEl.value : '';
                          const district = districtEl ? districtEl.value : '';
                          const lga = lgaEl ? lgaEl.value : '';
                          const state = stateEl ? stateEl.value : '';

                          // Build address array with non-empty values
                          const addressParts = [];

                          if (blockNo) addressParts.push('Block ' + blockNo);
                          if (floorNo) addressParts.push('Floor ' + floorNo);
                          if (unitNo) addressParts.push('Unit ' + unitNo);
                          if (district) addressParts.push(district);
                          if (lga) addressParts.push(lga);
                          if (state) addressParts.push(state);

                          // Update the property location field
                          const propertyLocationField = document.getElementById('property_location');
                          if (propertyLocationField) {
                            propertyLocationField.value = addressParts.join(', ');
                          }
                        }
                      </script>


                    </div>

                    <!-- Property Location (Full Width) -->
                    <div class="mb-4">
                      <label for="property_location" class="block text-sm font-medium text-gray-700">Property Location
                        (Address) <span class="text-red-500">*</span></label>
                      <textarea id="property_location" name="property_location" rows="3"
                        class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700 cursor-not-allowed"
                        placeholder="Property location will be auto-generated based on unit details"
                        readonly>{{ isset($sua) ? $sua->property_location : '' }}</textarea>
                      <p class="text-xs text-gray-500 mt-1">This field is automatically generated from Block No, Floor, Unit
                        No, District, LGA, and State.</p>
                    </div>


                  </div>

                  <div class="bg-gray-50 p-4 rounded-md mb-6">
                    <label for="application_comment" class="block text-sm font-medium text-gray-700 mb-2">. Write any
                      comment that will assist in processing the application</label>
                    <textarea id="application_comment" name="application_comment" rows="3"
                      class="w-full p-2 border border-gray-300 rounded-md"
                      placeholder="Add any comments or notes here..."></textarea>
                  </div>

                  <div class="bg-gray-50 p-4 rounded-md mb-6" id="fee-section">
                    @php
                      $landUse = $motherApplication->land_use ?? 'Residential';

                      // For MIXED land use, start with default residential rates until user selects
                      if ($isMixedLandUse) {
                        $applicationFee = '10000.00';
                        $processingFee = '20000.00';
                        $surveyFee = '50000.00'; // Default to Block of Flat rate for residential
                      } else {
                        // Set fees based on land use type for Unit applications
                        if ($landUse === 'Commercial' || $landUse === 'Industrial') {
                          $applicationFee = '10000.00';
                          $processingFee = '20000.00';
                          $surveyFee = '100000.00';
                        } else {
                          // Residential rates - default to Block of Flat rate
                          $applicationFee = '10000.00';
                          $processingFee = '20000.00';
                          $surveyFee = '50000.00'; // Default to Block of Flat rate
                        }
                      }

                      $totalFee = floatval($applicationFee) + floatval($processingFee) + floatval($surveyFee);
                    @endphp

                    <h3 class="font-medium text-center mb-4">INITIAL BILL</h3>

                    <!-- Single Card with 3x3 Grid: 3 Rows (Fees) x 3 Columns (Amount, Date, Receipt) -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                      <!-- Grid Header Row -->
                      <div class="grid grid-cols-4 gap-4 mb-4 bg-gray-50 p-3 rounded-lg border-b border-gray-200">
                        <div class="font-semibold text-sm text-gray-700">Fee Type</div>
                        <div class="font-semibold text-sm text-gray-700 text-center">Amount (₦)</div>
                        <div class="font-semibold text-sm text-gray-700 text-center">Payment Date</div>
                        <div class="font-semibold text-sm text-gray-700 text-center">Receipt No.</div>
                      </div>

                      <!-- Processing Fee Row -->
                      <div class="grid grid-cols-4 gap-4 mb-4 items-center py-2 border-b border-gray-100">
                        <div class="flex items-center">
                          <i data-lucide="file-check" class="w-4 h-4 mr-2 text-green-600"></i>
                          <span class="font-medium text-sm text-green-700">Processing Fee</span>
                        </div>
                        <div>
                          <input type="text" name="processing_fee_display"
                            class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50 text-center font-semibold"
                            value="{{ number_format($processingFee, 2) }}" readonly>
                          <input type="hidden" name="processing_fee" value="{{ $processingFee }}">
                        </div>
                        <div>
                          <input type="date" name="processing_fee_payment_date"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                          <input type="text" name="processing_fee_receipt_no"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Enter receipt number">
                        </div>
                      </div>

                      <!-- Application Fee Row -->
                      <div class="grid grid-cols-4 gap-4 mb-4 items-center py-2 border-b border-gray-100">
                        <div class="flex items-center">
                          <i data-lucide="file-text" class="w-4 h-4 mr-2 text-blue-600"></i>
                          <span class="font-medium text-sm text-blue-700">Application Fee</span>
                        </div>
                        <div>
                          <input type="text" name="application_fee_display"
                            class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50 text-center font-semibold"
                            value="{{ number_format($applicationFee, 2) }}" readonly>
                          <input type="hidden" name="application_fee" value="{{ $applicationFee }}">
                        </div>
                        <div>
                          <input type="date" name="application_fee_payment_date"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                          <input type="text" name="application_fee_receipt_no"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Enter receipt number">
                        </div>
                      </div>

                      <!-- Survey Fee Row -->
                      <div class="grid grid-cols-4 gap-4 mb-4 items-center py-2">
                        <div class="flex items-center">
                          <i data-lucide="map" class="w-4 h-4 mr-2 text-purple-600"></i>
                          <span class="font-medium text-sm text-purple-700">Survey Fee</span>
                        </div>
                        <div>
                          @if($isMixedLandUse)
                            <!-- Dynamic survey fee for MIXED land use -->
                            <div id="mixed-survey-fee-container">
                              <select name="site_plan_fee" id="site_plan_fee_mixed"
                                class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50 text-center font-semibold"
                                onchange="updateSurveyFee(this)" disabled>
                                <option value="">Select land use first</option>
                                <option value="50000">N 50,000.00</option>
                                <option value="70000">N 70,000.00</option>
                                <option value="100000">N 100,000.00</option>
                              </select>
                            </div>
                          @elseif($landUse === 'Residential')
                            <select name="site_plan_fee"
                              class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50 text-center font-semibold"
                              onchange="updateSurveyFee(this)">
                              <option value="50000">N 50,000.00</option>
                              <option value="70000">N 70,000.00</option>
                            </select>
                          @else
                            <input type="text" name="site_plan_fee_display"
                              class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50 text-center font-semibold"
                              value="{{ number_format($surveyFee, 2) }}" readonly>
                            <input type="hidden" name="site_plan_fee" value="{{ $surveyFee }}">
                          @endif
                        </div>
                        <div>
                          <input type="date" name="survey_fee_payment_date"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div>
                          <input type="text" name="survey_fee_receipt_no"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Enter receipt number">
                        </div>
                      </div>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                      <div class="flex items-center">
                        <i data-lucide="file-text" class="w-4 h-4 mr-1 text-green-600"></i>
                        <span>Total:</span>
                      </div>
                      <span class="font-bold" id="total-amount">N{{ number_format($totalFee, 2) }}</span>
                    </div>


                  </div>

                  <div class="flex justify-between mt-8">
                    <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md"
                      onclick="window.history.back()">Cancel</button>
                    <div class="flex items-center">
                      <span class="text-sm text-gray-500 mr-4">Step 1 of 4</span>
                      <button class="px-4 py-2 bg-black text-white rounded-md" id="nextStep1">Next</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 2:shared areas -->
            @include('sectionaltitling.partials.subapplication.sharedareas')


            <!-- Step 3: Application documents -->
            @include('sectionaltitling.partials.subapplication.documents')

            <!-- Step 4: Application Summary -->
            @include('sectionaltitling.partials.subapplication.summary')



            </form>
          </div>{{-- Close container py-4 --}}
        </div>{{-- Close bg-white rounded-md --}}


        <!-- Footer -->
        @include('admin.footer')
      </div>{{-- Close flex-1 overflow-auto --}}

      {{-- Move the navigation script here, after all HTML content --}}
      <script>
        function toggleOtherAreasTextarea() {
          const checkbox = document.getElementById('other_areas');
          const container = document.getElementById('other_areas_container');
          if (checkbox && container) {
            if (checkbox.checked) {
              container.style.display = 'block';
            } else {
              container.style.display = 'none';
              const detail = document.getElementById('other_areas_detail');
              if (detail) detail.value = '';
            }
          }
        }
        document.addEventListener('DOMContentLoaded', function () {
          toggleOtherAreasTextarea();
        });

        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function () {
          if (window.lucide) lucide.createIcons();
        });

        // Step navigation and validation
        function goToStep(stepNumber) {
          // Get current active step
          const currentActiveStep = document.querySelector('.form-section.active-tab');
          let currentStepNumber = 1;
          if (currentActiveStep) {
            const stepId = currentActiveStep.id;
            currentStepNumber = parseInt(stepId.replace('step', ''));
          }
          if (currentStepNumber === stepNumber) return;

          // Enforce file selection on Step 1
          if (currentStepNumber === 1 && stepNumber > 1) {
            const suaSelect = document.getElementById('sua-file-select');
            const unitSelect = document.getElementById('unit-file-select');
            const selectedValue = (suaSelect ? suaSelect.value : (unitSelect ? unitSelect.value : ''));

            if (!selectedValue) {
              Swal.fire({
                icon: 'warning',
                title: 'File Selection Required',
                text: 'Please select a commissioned Unit File Number to proceed.',
                confirmButtonColor: '#3085d6'
              });
              return;
            }

            // Also check if the selected option is disabled (extra safety)
            const activeSelect = suaSelect || unitSelect;
            if (activeSelect && activeSelect.selectedOptions[0] && activeSelect.selectedOptions[0].disabled) {
               Swal.fire({
                icon: 'error',
                title: 'Invalid Selection',
                text: 'The selected unit is not commissioned. Please select a valid file number.',
                confirmButtonColor: '#d33'
              });
              return;
            }
          }

          // Skip validation during step navigation - only validate on final submission
          if (false) {
            let errors = [];
            switch (currentStepNumber) {
              case 1: errors = validateStep1(); break;
              case 2: errors = validateStep2(); break;
              case 3: errors = validateStep3(); break;
            }
            if (errors.length > 0) {
              showValidationErrors(errors);
              return;
            } else {
              Swal.fire({
                icon: 'success',
                title: `Step ${currentStepNumber} Complete!`,
                text: 'Validated successfully.',
                timer: 1200,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
              });
            }
          }

          // Hide all steps
          document.querySelectorAll('.form-section').forEach(step => step.classList.remove('active-tab'));
          // Show target step
          const targetStep = document.getElementById(`step${stepNumber}`);
          if (targetStep) targetStep.classList.add('active-tab');
          updateStepCircles(stepNumber);
          updateStepText(stepNumber);

          // Update application summary when reaching step 4
          if (stepNumber === 4 && typeof updateApplicationSummary === 'function') {
            updateApplicationSummary();
          }
        }
        window.goToStep = goToStep;

        function updateStepCircles(currentStep) {
          document.querySelectorAll('.step-circle').forEach((circle, idx) => {
            circle.classList.remove('active-tab', 'inactive-tab');
            circle.classList.add(idx + 1 === currentStep ? 'active-tab' : 'inactive-tab');
          });
        }
        function updateStepText(currentStep) {
          document.querySelectorAll('.ml-4').forEach(el => {
            if (el.textContent.includes('Step')) el.textContent = `Step ${currentStep} of 4`;
          });
        }

        // Navigation buttons
        document.addEventListener('DOMContentLoaded', function () {
          const navs = [
            { btn: 'nextStep1', step: 2 },
            { btn: 'nextStep2', step: 3 },
            { btn: 'nextStep3', step: 4 },
            { btn: 'backStep2', step: 1 },
            { btn: 'backStep3', step: 2 },
            { btn: 'backStep4', step: 3 }
          ];
          navs.forEach(({ btn, step }) => {
            const el = document.getElementById(btn);
            if (el) el.addEventListener('click', function (e) {
              e.preventDefault();
              goToStep(step);
            });
          });
        });

        // Update survey fee function for residential land use
        function updateSurveyFee(selectElement) {
          const selectedValue = selectElement.value;
          const totalAmountElement = document.getElementById('total-amount');

          if (totalAmountElement) {
            // Get current application and processing fees from hidden inputs
            const applicationFeeInput = document.querySelector('input[name="application_fee"]');
            const processingFeeInput = document.querySelector('input[name="processing_fee"]');

            let applicationFee = 0;
            let processingFee = 0;

            if (applicationFeeInput) {
              applicationFee = parseFloat(applicationFeeInput.value) || 0;
            }

            if (processingFeeInput) {
              processingFee = parseFloat(processingFeeInput.value) || 0;
            }

            const surveyFee = parseFloat(selectedValue) || 0;
            const total = applicationFee + processingFee + surveyFee;

            totalAmountElement.textContent = '₦' + total.toLocaleString();
          }

          // Update payment information in summary if it exists
          if (typeof updatePaymentInformation === 'function') {
            updatePaymentInformation();
          }
        }

        // Make function globally available
        window.updateSurveyFee = updateSurveyFee;

        // Function to handle unit land use changes for MIXED land use
        function updateUnitFees(selectedLandUse) {
          const surveyFeeSelect = document.getElementById('site_plan_fee_mixed');
          const totalAmountElement = document.getElementById('total-amount');

          // Update the unit land use information card
          updateUnitLandUseCard(selectedLandUse);

          if (surveyFeeSelect) {
            // Clear and enable the survey fee dropdown
            surveyFeeSelect.innerHTML = '';
            surveyFeeSelect.disabled = false;

            if (selectedLandUse === 'Residential') {
              // Add residential options
              surveyFeeSelect.innerHTML = `
                      <option value="">Select survey type</option>
                      <option value="50000">Block of Flat - ₦ 50,000.00</option>
                      <option value="70000">Apartment - ₦ 70,000.00</option>
                    `;
              // Set default to Block of Flat
              surveyFeeSelect.value = '50000';
            } else if (selectedLandUse === 'Commercial') {
              // Add commercial option
              surveyFeeSelect.innerHTML = `
                      <option value="100000" selected>Commercial - ₦ 100,000.00</option>
                    `;
              surveyFeeSelect.value = '100000';
            } else {
              // No selection, disable and show placeholder
              surveyFeeSelect.innerHTML = '<option value="">Select land use first</option>';
              surveyFeeSelect.disabled = true;
              return;
            }

            // Update total when land use changes
            updateSurveyFee(surveyFeeSelect);
          }
        }

        // Make function globally available
        window.updateUnitFees = updateUnitFees;

        // Function to update SUA land use information card
        function updateSUALandUseCard(selectedLandUse) {
          const selectedElement = document.getElementById('sua_selected_land_use');
          const feeImpactElement = document.getElementById('sua_fee_impact');

          if (selectedElement) {
            selectedElement.textContent = selectedLandUse || 'Not selected';
          }

          if (feeImpactElement) {
            if (selectedLandUse) {
              const fees = {
                'Residential': 'App: ₦10,000 + Process: ₦20,000 + Survey: ₦50,000-₦70,000',
                'Commercial': 'App: ₦10,000 + Process: ₦20,000 + Survey: ₦100,000',
                'Industrial': 'App: ₦10,000 + Process: ₦20,000 + Survey: ₦100,000'
              };
              feeImpactElement.textContent = fees[selectedLandUse] || 'Select land use to view fees';
            } else {
              feeImpactElement.textContent = 'Select land use to view fees';
            }
          }
        }

        // Function to update unit land use information card
        function updateUnitLandUseCard(selectedLandUse) {
          const selectedElement = document.getElementById('unit_selected_land_use');
          const feeImpactElement = document.getElementById('unit_fee_impact');

          if (selectedElement) {
            selectedElement.textContent = selectedLandUse || 'Not selected';
          }

          if (feeImpactElement) {
            if (selectedLandUse) {
              const fees = {
                'Residential': 'App: ₦10,000 + Process: ₦20,000 + Survey: ₦50,000-₦70,000',
                'Commercial': 'App: ₦10,000 + Process: ₦20,000 + Survey: ₦100,000'
              };
              feeImpactElement.textContent = fees[selectedLandUse] || 'Select land use to view fees';
            } else {
              feeImpactElement.textContent = 'Select land use to view fees';
            }
          }
        }

        // Make functions globally available
        window.updateSUALandUseCard = updateSUALandUseCard;
        window.updateUnitLandUseCard = updateUnitLandUseCard;

        // Initialize MIXED land use functionality on page load
        document.addEventListener('DOMContentLoaded', function () {
          @if($isMixedLandUse)
            const unitLandUseSelect = document.getElementById('unit_land_use');
            const surveyFeeSelect = document.getElementById('site_plan_fee_mixed');

            // Ensure survey fee dropdown is properly disabled on page load
            if (surveyFeeSelect && !unitLandUseSelect.value) {
              surveyFeeSelect.disabled = true;
              surveyFeeSelect.innerHTML = '<option value="">Select land use first</option>';
            }

            // Add event listener to update fees when land use changes
            if (unitLandUseSelect) {
              unitLandUseSelect.addEventListener('change', function () {
                updateUnitFees(this.value);
              });
            }
          @endif
              });

        // Validation functions
        function validateStep1() {
          const errors = [];
          const applicantTypeInput = document.getElementById('mainApplicantTypeInput');
          const applicantType = applicantTypeInput?.value;
          if (!applicantType) {
            errors.push('Please select a file number first to determine applicant type');
          } else {
            const type = applicantType;
            if (type === 'individual') {
              if (!document.getElementById('applicantName')?.value?.trim()) errors.push('Please enter first name');
              if (!document.getElementById('applicantSurname')?.value?.trim()) errors.push('Please enter surname');
              if (!document.getElementById('photoUpload')?.files[0]) errors.push('Please upload a passport photo');
            } else if (type === 'corporate') {
              if (!document.getElementById('corporateName')?.value?.trim()) errors.push('Please enter corporate body name');
              if (!document.getElementById('subCorporateDocumentUpload')?.files[0]) errors.push('Please upload RC document');
            } else if (type === 'multiple') {
              const ownerRows = document.querySelectorAll('#ownersContainer > div');
              if (ownerRows.length === 0) errors.push('Please add at least one owner');
              ownerRows.forEach((row, idx) => {
                if (!row.querySelector('input[name="multiple_owners_names[]"]')?.value?.trim())
                  errors.push(`Please enter name for owner ${idx + 1}`);
                if (!row.querySelector('textarea[name="multiple_owners_address[]"]')?.value?.trim())
                  errors.push(`Please enter address for owner ${idx + 1}`);
                if (!row.querySelector('input[name="multiple_owners_identification_image[]"]')?.files[0])
                  errors.push(`Please upload identification for owner ${idx + 1}`);
              });
            }
          }
          // Land Use validation
          @if($isSUA)
            const suaLandUseValue = document.getElementById('sua_land_use_hidden')?.value || document.querySelector('input[name="land_use"]')?.value;
            if (!suaLandUseValue) {
              errors.push('Please select a land use for this SUA application');
            }
          @elseif($isMixedLandUse)
            const unitLandUseSelect = document.getElementById('unit_land_use');
            if (!unitLandUseSelect?.value) {
              errors.push('Please select a land use for this unit (required for MIXED land use applications)');
            }
          @endif

                // Address validation (individual/corporate)
                if (applicantType && applicantType.value !== 'multiple') {
            if (!document.getElementById('ownerState')?.value) errors.push('Please select a state');
            if (!document.getElementById('ownerLga')?.value) errors.push('Please select an LGA');

            // Phone validation
            const phoneInputs = document.querySelectorAll('input[name="phone_number[]"]');
            let hasValidPhone = false;
            phoneInputs.forEach(input => {
              if (input.value?.trim()) {
                hasValidPhone = true;
                if (!/^[\d\s\-\+\(\)]{10,}$/.test(input.value.replace(/\s/g, '')))
                  errors.push('Please enter a valid phone number');
              }
            });
            if (!hasValidPhone) errors.push('Please enter at least one phone number');
            // Email validation
            const email = document.querySelector('input[name="owner_email"]')?.value;
            if (email?.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Please enter a valid email address');
          }
          // Unit details
          if (!document.querySelector('input[name="block_number"]')?.value?.trim()) errors.push('Please enter block number');
          if (!document.querySelector('input[name="floor_number"]')?.value?.trim()) errors.push('Please enter floor number');
          return errors;
        }
        function validateStep2() {
          const errors = [];
          if (document.querySelectorAll('input[name="shared_areas[]"]:checked').length === 0)
            errors.push('Please select at least one shared area');
          const otherCheckbox = document.getElementById('other_areas');
          if (otherCheckbox?.checked) {
            const otherDetails = document.getElementById('other_areas_detail')?.value;
            if (!otherDetails?.trim()) errors.push('Please specify other shared areas');
          }
          return errors;
        }
        function validateStep3() {
          // Document validation removed - documents are optional during form completion
          return [];
        }
        function showValidationErrors(errors) {
          if (errors.length > 0) {
            Swal.fire({
              icon: 'error',
              title: 'Please correct the following errors:',
              html: `<div style="text-align: left; font-size: 14px; line-height: 1.6;">${errors.map(e => `• ${e}`).join('<br>')}</div>`,
              confirmButtonText: 'OK',
              confirmButtonColor: '#dc2626',
              customClass: {
                popup: 'swal-validation-popup',
                title: 'swal-validation-title',
                htmlContainer: 'swal-validation-content'
              },
              showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
              },
              hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
              }
            });
            return false;
          }
          return true;
        }
        window.validateStep2 = validateStep2;
        window.validateStep3 = validateStep3;
        window.showValidationErrors = showValidationErrors;

        // Buyer selection: initialize and auto-fill
        document.addEventListener('DOMContentLoaded', function () {
          const buyerSelectEl = document.getElementById('buyerSelect');
          const clearBtn = document.getElementById('clearBuyerSelection');
          const info = document.getElementById('selectedBuyerInfo');
          const details = document.getElementById('selectedBuyerDetails');

          function setTitleFields(title) {
            const titleSelect = document.getElementById('applicantTitleSelect');
            const hiddenTitle = document.getElementById('applicantTitle');
            const otherWrap = document.getElementById('applicantTitleOtherWrapper');
            const otherInput = document.getElementById('applicantTitleOther');

            if (!hiddenTitle) return; // required for preview and submission

            if (titleSelect) {
              // Check if the title exists in the select options
              const hasOption = Array.from(titleSelect.options).some(opt => opt.value === title);
              if (title && hasOption) {
                titleSelect.value = title;
                if (otherWrap) otherWrap.classList.add('hidden');
                if (otherInput) otherInput.value = '';
                hiddenTitle.value = title;
              } else if (title && !hasOption) {
                titleSelect.value = 'Other';
                if (otherWrap) otherWrap.classList.remove('hidden');
                if (otherInput) otherInput.value = title;
                hiddenTitle.value = title;
              } else {
                // empty title
                titleSelect.value = '';
                if (otherWrap) otherWrap.classList.add('hidden');
                if (otherInput) otherInput.value = '';
                hiddenTitle.value = '';
              }
              // Fire change to keep any listeners in sync
              titleSelect.dispatchEvent(new Event('change'));
            } else {
              // Fallback: only hidden input exists
              hiddenTitle.value = title || '';
            }
          }

          function clearApplicantFields() {
            setTitleFields('');
            const firstNameEl = document.getElementById('applicantName') || document.querySelector('[name="first_name"]');
            const middleNameEl = document.getElementById('applicantMiddleName') || document.querySelector('[name="middle_name"]');
            const surnameEl = document.getElementById('applicantSurname') || document.querySelector('[name="surname"]');
            if (firstNameEl) firstNameEl.value = '';
            if (middleNameEl) middleNameEl.value = '';
            if (surnameEl) surnameEl.value = '';
            if (typeof updateApplicantNamePreview === 'function') updateApplicantNamePreview();

            // Clear land use for Mixed Use applications
            const isMixedUse = {{ $isMixedLandUse ? 'true' : 'false' }};
            if (isMixedUse) {
              const unitLandUseSelect = document.getElementById('unit_land_use');
              if (unitLandUseSelect) {
                unitLandUseSelect.selectedIndex = 0; // Reset to first option (usually "Select Land Use")
                // Re-enable the land use select for manual selection
                unitLandUseSelect.disabled = false;
                unitLandUseSelect.style.backgroundColor = '';
                unitLandUseSelect.style.color = '';
                unitLandUseSelect.style.cursor = '';
              }
              const unitSelectedLandUse = document.getElementById('unit_selected_land_use');
              if (unitSelectedLandUse) {
                unitSelectedLandUse.textContent = 'Not selected';
                unitSelectedLandUse.className = 'text-orange-600 font-medium';
              }
            }
          }

          function applyBuyer(optionEl) {
            if (!optionEl) {
              if (info) info.style.display = 'none';
              if (details) details.textContent = '';
              if (clearBtn) clearBtn.style.display = 'none';
              const unitNoInput = document.querySelector('input[name="unit_number"]');
              const unitSizeInput = document.querySelector('input[name="unit_size"]');
              if (unitNoInput) unitNoInput.value = '';
              if (unitSizeInput) unitSizeInput.value = '';
              if (typeof updatePropertyLocation === 'function') {
                updatePropertyLocation();
              }

              // Re-enable land use select when buyer is cleared
              const isMixedUse = {{ $isMixedLandUse ? 'true' : 'false' }};
              if (isMixedUse) {
                const unitLandUseSelect = document.getElementById('unit_land_use');
                if (unitLandUseSelect) {
                  unitLandUseSelect.disabled = false;
                  unitLandUseSelect.style.backgroundColor = '';
                  unitLandUseSelect.style.color = '';
                  unitLandUseSelect.style.cursor = '';
                }
              }

              clearApplicantFields();
              return;
            }

            const buyerId = optionEl.dataset.buyerId || optionEl.value || '';
            const cachedBuyer = getCachedBuyerDataset().find(buyer => (buyer.buyer_id || buyer.id || '').toString() === buyerId);

            const title = optionEl.dataset.buyerTitle || (cachedBuyer ? (cachedBuyer.buyer_title || '') : '');
            const name = optionEl.dataset.buyerName || (cachedBuyer ? (cachedBuyer.buyer_name || '') : '');
            const unitNo = optionEl.dataset.unitNo
              || (cachedBuyer ? (cachedBuyer.unit_no || cachedBuyer.unit_number || cachedBuyer.unit || '') : '');
            const landUse = optionEl.dataset.landUse || (cachedBuyer ? (cachedBuyer.land_use || '') : '');
            const measurement = optionEl.dataset.measurement
              || (cachedBuyer ? (cachedBuyer.measurement || cachedBuyer.unit_size || cachedBuyer.measurement_sqm || '') : '');

            console.log('🔍 Buyer data extracted:', {
              buyerId,
              title,
              name,
              unitNo,
              landUse,
              measurement,
              cachedBuyer: cachedBuyer ? 'found' : 'not found'
            });

            // Set applicant type to individual in hidden input
            const applicantTypeInput = document.getElementById('mainApplicantTypeInput');
            if (applicantTypeInput) {
              applicantTypeInput.value = 'individual';
              console.log('✅ Buyer selection: Applicant type set to individual');
            }
            const legacyApplicantTypeField = document.getElementById('applicantType');
            if (legacyApplicantTypeField) {
              legacyApplicantTypeField.value = 'individual';
            }
            // Show individual fields if function exists
            if (typeof showIndividualFields === 'function') showIndividualFields();

            // Set title (visible select + hidden input)
            setTitleFields(title);

            // Fill applicant name fields if present
            const firstNameEl = document.getElementById('applicantName') || document.querySelector('[name="first_name"]');
            const middleNameEl = document.getElementById('applicantMiddleName') || document.querySelector('[name="middle_name"]');
            const surnameEl = document.getElementById('applicantSurname') || document.querySelector('[name="surname"]');
            if (name) {
              const parts = name.trim().split(/\s+/);
              if (firstNameEl) firstNameEl.value = parts[0] || '';
              if (surnameEl) surnameEl.value = parts.slice(1).join(' ') || '';
              if (middleNameEl) middleNameEl.value = '';
            }

            // Trigger preview update if available
            if (typeof updateApplicantNamePreview === 'function') updateApplicantNamePreview();

            // Fill unit details
            const unitNoInput = document.querySelector('input[name="unit_number"]');
            const unitSizeInput = document.querySelector('input[name="unit_size"]');

            console.log('🏠 Setting unit fields:', {
              unitNo,
              measurement,
              unitNoInputFound: !!unitNoInput,
              unitSizeInputFound: !!unitSizeInput
            });

            if (unitNoInput) {
              unitNoInput.value = unitNo;
              console.log('✅ Unit Number set to:', unitNo);
            } else {
              console.warn('⚠️ Unit number input not found');
            }

            if (unitSizeInput) {
              unitSizeInput.value = measurement;
              console.log('✅ Unit Size set to:', measurement);
            } else {
              console.warn('⚠️ Unit size input not found');
            }

            // Auto-fill land use for Mixed Use applications
            const isMixedUse = {{ $isMixedLandUse ? 'true' : 'false' }};
            if (isMixedUse) {
              const unitLandUseSelect = document.getElementById('unit_land_use');
              const unitSelectedLandUse = document.getElementById('unit_selected_land_use');

              if (unitLandUseSelect) {
                if (landUse) {
                  // Buyer has land use data - auto-fill and disable
                  unitLandUseSelect.value = landUse;
                  unitLandUseSelect.disabled = true;
                  unitLandUseSelect.style.backgroundColor = '#f3f4f6';
                  unitLandUseSelect.style.color = '#6b7280';
                  unitLandUseSelect.style.cursor = 'not-allowed';

                  // Trigger change event to update fees if function exists
                  if (typeof updateUnitFees === 'function') {
                    updateUnitFees(landUse);
                  }

                  // Update the selected land use display
                  if (unitSelectedLandUse) {
                    unitSelectedLandUse.textContent = landUse + ' (Auto-filled from buyer)';
                    unitSelectedLandUse.className = 'text-green-600 font-medium';
                  }
                } else {
                  // Buyer has no land use data - leave enabled for manual selection
                  if (unitSelectedLandUse) {
                    unitSelectedLandUse.textContent = 'Please select manually (buyer has no land use)';
                    unitSelectedLandUse.className = 'text-amber-600 font-medium';
                  }
                }
              }
            }

            // Show selection info
            if (details) details.textContent = `${title ? title + ' ' : ''}${name}`.trim() + (unitNo ? ` • Unit ${unitNo}` : '') + (measurement ? ` • ${measurement}` : '');
            if (info) info.style.display = 'block';
            if (clearBtn) clearBtn.style.display = 'inline-flex';
          }

          function onBuyerChange() {
            if (!buyerSelectEl) return;
            const optionEl = buyerSelectEl.options[buyerSelectEl.selectedIndex];
            if (buyerSelectEl.value) applyBuyer(optionEl); else applyBuyer(null);
          }

          if (buyerSelectEl) {
            // Vanilla change listener
            buyerSelectEl.addEventListener('change', onBuyerChange);
            // Enhance with Select2 if jQuery is available
            if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
              jQuery(buyerSelectEl)
                .select2({ placeholder: 'Type to search buyer name...', allowClear: true, width: '100%' })
                .on('change', onBuyerChange);
            }
          }

          if (clearBtn) {
            clearBtn.addEventListener('click', function () {
              if (buyerSelectEl) {
                if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                  jQuery(buyerSelectEl).val(null).trigger('change');
                } else {
                  buyerSelectEl.value = '';
                  buyerSelectEl.dispatchEvent(new Event('change'));
                }
              }
            });
          }
        });

        // SUA-specific functionality
        document.addEventListener('DOMContentLoaded', function () {
          const isSUA = {{ $isSUA ? 'true' : 'false' }};

          if (isSUA) {
            // Setup allocation source/entity dropdowns
            setupAllocationDropdowns();

            // Don't auto-generate file numbers on page load to avoid conflicts
            // File numbers will be generated during form submission
            // generateSUAPrimaryFileNo('Residential');
          }
        });

        /**
         * Allocation Source / Entity are no longer chosen here - they are answered on
         * the SuA File Number Commissioning form and back-filled by
         * applyAllocationInfoFromFile(). Kept for the entity list, which is the
         * reference copy of what that form offers, and as the hook for any caller
         * that still asks for it.
         */
        function setupAllocationDropdowns() {
          const allocationSource = document.getElementById('allocation_source_display');
          const allocationEntity = document.getElementById('allocation_entity_display');

          const entityOptions = {
            'State Government': [
              { value: 'KSIP', text: 'KSIP' },
              { value: 'HOUSING', text: 'HOUSING' },
              { value: 'KUNPDA', text: 'KUNPDA' }
            ],
            'Local Government': [
              { value: 'Ajingi', text: 'Ajingi' },
              { value: 'Albasu', text: 'Albasu' },
              { value: 'Bagwai', text: 'Bagwai' },
              { value: 'Bebeji', text: 'Bebeji' },
              { value: 'Bichi', text: 'Bichi' },
              { value: 'Bunkure', text: 'Bunkure' },
              { value: 'Dala', text: 'Dala' },
              { value: 'Dambatta', text: 'Dambatta' },
              { value: 'Dawakin Kudu', text: 'Dawakin Kudu' },
              { value: 'Dawakin Tofa', text: 'Dawakin Tofa' },
              { value: 'Doguwa', text: 'Doguwa' },
              { value: 'Fagge', text: 'Fagge' },
              { value: 'Gabasawa', text: 'Gabasawa' },
              { value: 'Garko', text: 'Garko' },
              { value: 'Garum Mallam', text: 'Garum Mallam' },
              { value: 'Gaya', text: 'Gaya' },
              { value: 'Gezawa', text: 'Gezawa' },
              { value: 'Gwale', text: 'Gwale' },
              { value: 'Gwarzo', text: 'Gwarzo' },
              { value: 'Kabo', text: 'Kabo' },
              { value: 'Kano Municipal', text: 'Kano Municipal' },
              { value: 'Karaye', text: 'Karaye' },
              { value: 'Kibiya', text: 'Kibiya' },
              { value: 'Kiru', text: 'Kiru' },
              { value: 'Kumbotso', text: 'Kumbotso' },
              { value: 'Kunchi', text: 'Kunchi' },
              { value: 'Kura', text: 'Kura' },
              { value: 'Madobi', text: 'Madobi' },
              { value: 'Makoda', text: 'Makoda' },
              { value: 'Minjibir', text: 'Minjibir' },
              { value: 'Nasarawa', text: 'Nasarawa' },
              { value: 'Rano', text: 'Rano' },
              { value: 'Rimin Gado', text: 'Rimin Gado' },
              { value: 'Rogo', text: 'Rogo' },
              { value: 'Shanono', text: 'Shanono' },
              { value: 'Sumaila', text: 'Sumaila' },
              { value: 'Takai', text: 'Takai' },
              { value: 'Tarauni', text: 'Tarauni' },
              { value: 'Tofa', text: 'Tofa' },
              { value: 'Tsanyawa', text: 'Tsanyawa' },
              { value: 'Tudun Wada', text: 'Tudun Wada' },
              { value: 'Ungogo', text: 'Ungogo' },
              { value: 'Warawa', text: 'Warawa' },
              { value: 'Wudil', text: 'Wudil' }
            ]
          };

          if (allocationSource && allocationEntity) {
            allocationSource.addEventListener('change', function () {
              const selectedSource = this.value;
              allocationEntity.innerHTML = '<option value="">Select Entity</option>';

              if (selectedSource && entityOptions[selectedSource]) {
                allocationEntity.disabled = false;
                entityOptions[selectedSource].forEach(option => {
                  const optionElement = document.createElement('option');
                  optionElement.value = option.value;
                  optionElement.textContent = option.text;
                  allocationEntity.appendChild(optionElement);
                });
              } else {
                allocationEntity.disabled = true;
              }
            });
          }
        }

        function generateSUAPrimaryFileNo(landUse) {
          const landUseCode = {
            'Commercial': 'COM',
            'Industrial': 'IND',
            'Residential': 'RES'
          }[landUse] || 'RES';

          const currentYear = new Date().getFullYear();

          // Get CSRF token
          const csrfToken = document.querySelector('meta[name="csrf-token"]') ||
            document.querySelector('input[name="_token"]');

          // Fetch next file number from server with proper headers
          fetch(`/sua/next-fileno?landuse=${landUse}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          })
            .then(response => {
              if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
            })
            .then(data => {
              if (data.success) {
                const primaryFileNoInput = document.getElementById('sua_primary_fileno');
                const suaFileNoInput = document.getElementById('sua_fileno');
                const mlsFileNoInput = document.getElementById('mls_fileno');

                if (primaryFileNoInput && data.primary_fileno) {
                  primaryFileNoInput.value = data.primary_fileno;
                }
                if (suaFileNoInput && data.sua_fileno) {
                  suaFileNoInput.value = data.sua_fileno;
                }
                if (mlsFileNoInput && data.mls_fileno) {
                  mlsFileNoInput.value = data.mls_fileno;
                }
              } else {
                throw new Error('API returned success=false');
              }
            })
            .catch((error) => {
              console.warn('Failed to fetch next file number:', error);
              // Fallback: Don't change the values if they're already set from server
              const primaryFileNoInput = document.getElementById('sua_primary_fileno');
              const suaFileNoInput = document.getElementById('sua_fileno');
              const mlsFileNoInput = document.getElementById('mls_fileno');

              if (primaryFileNoInput && !primaryFileNoInput.value) {
                const serialNo = '1';
                const primaryFileNo = `ST-${landUseCode}-${currentYear}-${serialNo}`;
                primaryFileNoInput.value = primaryFileNo;

                // MLS should always match primary
                if (mlsFileNoInput) {
                  mlsFileNoInput.value = primaryFileNo;
                }
              }
              if (suaFileNoInput && !suaFileNoInput.value) {
                const serialNo = '1';
                suaFileNoInput.value = `ST-${landUseCode}-${currentYear}-${serialNo}-001`;
              }
            });
        }

        function updateSUAFees(landUse) {
          // Update the SUA land use information card
          updateSUALandUseCard(landUse);

          // Don't auto-generate file numbers on land use change to avoid conflicts
          // File numbers will be generated during form submission
          // generateSUAPrimaryFileNo(landUse);

          // Clear file number fields to show they will be auto-generated
          const primaryFileNoInput = document.getElementById('sua_primary_fileno');
          const suaFileNoInput = document.getElementById('sua_fileno');
          const mlsFileNoInput = document.getElementById('mls_fileno');

          if (primaryFileNoInput) {
            primaryFileNoInput.value = 'Auto-generated';
          }
          if (suaFileNoInput) {
            suaFileNoInput.value = 'Auto-generated';
          }
          if (mlsFileNoInput) {
            mlsFileNoInput.value = 'Auto-generated';
          }

          // Update fees based on land use
          const applicationFeeInput = document.querySelector('input[name="application_fee"]');
          const applicationFeeDisplay = document.querySelector('input[name="application_fee_display"]');
          const processingFeeInput = document.querySelector('input[name="processing_fee"]');
          const processingFeeDisplay = document.querySelector('input[name="processing_fee_display"]');
          const surveyFeeInput = document.querySelector('input[name="site_plan_fee"]');
          const totalAmountElement = document.getElementById('total-amount');

          let surveyFee;
          if (landUse === 'Commercial' || landUse === 'Industrial') {
            surveyFee = 100000;
          } else {
            surveyFee = 50000; // Default residential rate
          }

          // Update hidden inputs with raw values
          if (applicationFeeInput) applicationFeeInput.value = '10000';
          if (processingFeeInput) processingFeeInput.value = '20000';

          // Update display inputs with formatted values
          if (applicationFeeDisplay) applicationFeeDisplay.value = '10,000.00';
          if (processingFeeDisplay) processingFeeDisplay.value = '20,000.00';

          // Handle survey fee based on land use type
          if (landUse === 'Residential') {
            // Replace input with select for residential
            const container = surveyFeeInput?.parentElement;
            if (container) {
              container.innerHTML = `
                              <label class="flex items-center text-sm mb-1">
                                  <i data-lucide="map" class="w-4 h-4 mr-1 text-green-600"></i>
                                  Survey Fee (₦)
                              </label>
                              <select name="site_plan_fee" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" onchange="updateSurveyFee(this)">
                                  <option value="50000">Block of Flat - ₦ 50,000.00</option>
                                  <option value="70000">Apartment - ₦ 70,000.00</option>
                              </select>
                          `;
              surveyFee = 50000;
            }
          } else {
            // Use fixed input for commercial/industrial
            if (surveyFeeInput) {
              surveyFeeInput.value = surveyFee.toLocaleString();
            }
          }

          const total = 10000 + 20000 + surveyFee;
          if (totalAmountElement) {
            totalAmountElement.textContent = '₦' + total.toLocaleString();
          }
        }

        // Make functions globally available
        window.setupAllocationDropdowns = setupAllocationDropdowns;
        window.generateSUAPrimaryFileNo = generateSUAPrimaryFileNo;
        window.updateSUAFees = updateSUAFees;

        // Final validation for form submission
        function validateAllSteps() {
          let allErrors = [];

          // Validate Step 1
          const step1Errors = validateStep1();
          if (step1Errors.length > 0) {
            allErrors = allErrors.concat(step1Errors.map(error => `Step 1: ${error}`));
          }

          // Validate Step 2
          const step2Errors = validateStep2();
          if (step2Errors.length > 0) {
            allErrors = allErrors.concat(step2Errors.map(error => `Step 2: ${error}`));
          }

          // Step 3 documents are optional; only validate provided files for size/type
          const step3Errors = [];
          const optionalDocs = [
            { name: 'application_letter', label: 'Application Letter' },
            { name: 'building_plan', label: 'Building Plan' },
            { name: 'architectural_design', label: 'Architectural Design' },
            { name: 'ownership_document', label: 'Ownership Document' }
          ];
          optionalDocs.forEach(doc => {
            const file = document.getElementById(doc.name)?.files[0];
            if (file) {
              if (file.size > 5 * 1024 * 1024) {
                step3Errors.push(`${doc.label} file size must be less than 5MB`);
              }
              const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
              if (!allowedTypes.includes(file.type)) {
                step3Errors.push(`${doc.label} must be a JPG, PNG, or PDF file`);
              }
            }
          });
          if (step3Errors.length > 0) {
            allErrors = allErrors.concat(step3Errors.map(error => `Step 3: ${error}`));
          }

          return allErrors;
        }

        // Add event listener for submit button
        document.addEventListener('DOMContentLoaded', function () {
          const submitButton = document.getElementById('submitApplication');
          if (submitButton) {
            const originalButtonMarkup = submitButton.innerHTML;

            const resolveLandUseValue = () => {
              const landUseRadio = document.querySelector('input[name="land_use"]:checked');
              if (landUseRadio?.value) {
                return landUseRadio.value;
              }

              const landUseSelect = document.querySelector('select[name="land_use"]');
              if (landUseSelect?.value) {
                return landUseSelect.value;
              }

              const landUseInput = document.querySelector('input[name="land_use"]');
              if (landUseInput?.value) {
                return landUseInput.value;
              }

              const hiddenLandUse = document.getElementById('sua_land_use_hidden')?.value
                || document.querySelector('input[name="land_use_hidden"]')?.value;

              return hiddenLandUse || null;
            };

            submitButton.addEventListener('click', function (event) {
              event.preventDefault();

              // Validate all steps before submission
              const allErrors = validateAllSteps();

              if (allErrors.length > 0) {
                // Show validation errors
                Swal.fire({
                  icon: 'error',
                  title: 'Please correct the following errors before submitting:',
                  html: `<div style="text-align: left; font-size: 14px; line-height: 1.6;">${allErrors.map(e => `• ${e}`).join('<br>')}</div>`,
                  confirmButtonText: 'OK',
                  confirmButtonColor: '#dc2626',
                  customClass: {
                    popup: 'swal-validation-popup',
                    title: 'swal-validation-title',
                    htmlContainer: 'swal-validation-content'
                  },
                  showClass: {
                    popup: 'animate__animated animate__fadeInDown animate__faster'
                  },
                  hideClass: {
                    popup: 'animate__animated animate__fadeOutUp animate__faster'
                  }
                });
                return false;
              }

              // If validation passes, proceed with form submission
              Swal.fire({
                title: 'Submit Application?',
                text: 'Are you sure you want to submit this application? This action cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Submit',
                cancelButtonText: 'Cancel'
              }).then((result) => {
                if (result.isConfirmed) {
                  // Show loading state
                  submitButton.disabled = true;
                  submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';

                  // Ensure file number is generated and synced before submission
                  const generator = window.generateUnitFileNumber || (typeof generateUnitFileNumber === 'function' ? generateUnitFileNumber : null);
                  const finalFileNumber = typeof generator === 'function' ? generator() : null;

                  // Submit the form
                  const form = document.getElementById('subApplicationForm');
                  if (form) {
                    // Create and submit form data
                    const formData = new FormData(form);

                    // Normalize and enforce applicant type in payload
                    const applicantTypeValue = (document.getElementById('mainApplicantTypeInput')?.value || document.getElementById('applicantType')?.value || '').toLowerCase();
                    if (applicantTypeValue) {
                      formData.set('applicant_type', applicantTypeValue);
                      formData.set('applicantType', applicantTypeValue);
                      const legacyField = document.getElementById('applicantType');
                      if (legacyField) {
                        legacyField.value = applicantTypeValue;
                      }
                    }

                    // Ensure the file number is included in the submission
                    if (finalFileNumber) {
                      formData.set('fileno', finalFileNumber);
                      formData.set('unit_file_no', finalFileNumber);
                    }

                    // Ensure draft controller hydrates state before submission
                    if (!formData.has('hydrate_from_draft')) {
                      formData.set('hydrate_from_draft', '1');
                    }

                    // Ensure land use travels with the request
                    const landUseValue = resolveLandUseValue();
                    if (landUseValue) {
                      formData.set('land_use', landUseValue);
                    } else if (!formData.has('land_use')) {
                      formData.set('land_use', '');
                    }

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const submitUrl = window.FORM_SUBMIT_URL || window.FORM_SUBMIT_FALLBACK;

                    fetch(submitUrl, {
                      method: 'POST',
                      body: formData,
                      headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                      }
                    })
                      .then(async (response) => {
                        const contentType = response.headers.get('content-type') || '';
                        const isJson = contentType.includes('application/json');
                        const payload = isJson ? await response.json() : null;

                        if (!response.ok || !payload?.success) {
                          const validationMessages = payload?.errors ? Object.values(payload.errors).flat() : [];
                          const error = new Error(payload?.message || validationMessages[0] || `Request failed with status ${response.status}`);
                          error.validationMessages = validationMessages;
                          error.payload = payload;
                          error.status = response.status;
                          throw error;
                        }

                        return payload;
                      })
                      .then(data => {
                        Swal.fire({
                          icon: 'success',
                          title: 'Success!',
                          text: data.message || 'Your application has been submitted successfully.',
                          confirmButtonColor: '#10b981'
                        }).then(() => {
                          // Redirect to applications list or wherever appropriate
                          window.location.href = data.redirect || '{{ route("sectionaltitling.secondary") }}';
                        });
                      })
                      .catch(error => {
                        console.error('Submission error:', error);

                        const validationMessages = error.validationMessages
                          ? error.validationMessages
                          : (error.payload?.errors ? Object.values(error.payload.errors).flat() : []);

                        const hasValidationMessages = validationMessages && validationMessages.length > 0;
                        const errorHtml = hasValidationMessages
                          ? `<div style="text-align: left; font-size: 14px; line-height: 1.6;">${validationMessages.map(msg => `• ${msg}`).join('<br>')}</div>`
                          : (error.message || 'There was an error submitting your application. Please try again.');

                        Swal.fire({
                          icon: 'error',
                          title: 'Submission Failed',
                          html: errorHtml,
                          confirmButtonColor: '#dc2626'
                        });
                      })
                      .finally(() => {
                        // Reset button state
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonMarkup;
                      });
                  }
                }
              });
            });
          }
        });

        window.validateAllSteps = validateAllSteps;
      </script>

      {{-- Draft Autosave Configuration --}}
      <script>
        window.FORM_SUBMIT_URL = "{{ route('subapplication.draft.submit') }}";
        window.FORM_SUBMIT_FALLBACK = "{{ $isSUA ? route('sua.store') : route('secondaryform.save') }}";
        console.log('🔗 Form submit URL:', window.FORM_SUBMIT_URL);
      </script>

      <script>
        if (typeof window.generateUnitFileNumber !== 'function') {
          window.generateUnitFileNumber = function () {
            const unitFileInput = document.getElementById('unitFileNo') || document.querySelector('input[name="unit_file_no"]');
            const hiddenInput = document.getElementById('unitFileNoHidden');
            const existing = unitFileInput?.value || hiddenInput?.value;

            if (existing && existing.trim() !== '') {
              return existing;
            }

            const now = new Date();
            const year = now.getFullYear();
            const timeStamp = [now.getHours(), now.getMinutes(), now.getSeconds()]
              .map(part => String(part).padStart(2, '0'))
              .join('');
            const randomSuffix = Math.floor(Math.random() * 900 + 100); // 3-digit suffix ensures uniqueness
            const tempFileNo = `SUB-${year}-${timeStamp}${randomSuffix}`;

            const targets = [
              unitFileInput,
              hiddenInput,
              document.querySelector('input[name="fileno"]'),
              document.querySelector('input[name="unit_file_no"]')
            ];

            targets.forEach(element => {
              if (element) {
                element.value = tempFileNo;
              }
            });

            // Check if this is a SUA application to avoid overriding SUA file numbers
            const isSUA = document.querySelector('input[name="is_sua"]')?.value === '1' ||
              window.location.href.includes('/sua/create');

            const displayTargets = [
              document.getElementById('draftLocatorCurrentId'),
              document.getElementById('stFileNumberDisplay')
            ];

            // Only update suaFileNumberDisplay if this is NOT a SUA application
            if (!isSUA) {
              displayTargets.push(document.getElementById('suaFileNumberDisplay'));
            }

            displayTargets.forEach(element => {
              if (element) {
                element.textContent = tempFileNo;
              }
            });

            return tempFileNo;
          };
        }
      </script>

      <script>
        window.SUB_APPLICATION_DRAFT_BOOTSTRAP = @json($draftMeta ?? []);
        console.log('[Bootstrap] Raw draftMeta:', @json($draftMeta ?? []));
        console.log('[Bootstrap] Draft list:', @json($draftList ?? []));
        @php
          $loadTemplate = route('subapplication.draft.load', ['draftId' => '__DRAFT_ID__']);
          $deleteTemplate = route('subapplication.draft.delete', ['draftId' => '__DRAFT_ID__']);
          $exportTemplate = route('subapplication.draft.export', ['draftId' => '__DRAFT_ID__']);
          $analyticsTemplate = route('subapplication.draft.analytics', ['draftId' => '__DRAFT_ID__']);
        @endphp
        window.SUB_APPLICATION_DRAFT_ENDPOINTS = {
          save: "{{ route('subapplication.draft.save') }}",
          submit: "{{ route('subapplication.draft.submit') }}",
          load: "{{ $loadTemplate }}",
          delete: "{{ $deleteTemplate }}",
          export: "{{ $exportTemplate }}",
          analytics: "{{ $analyticsTemplate }}",
          check: "{{ route('subapplication.draft.check', ['subApplicationId' => '__SUB_APPLICATION_ID__']) }}",
          share: "{{ route('subapplication.draft.share') }}",
          start: "{{ route('subapplication.draft.start') }}",
          mine: "{{ route('subapplication.draft.mine') }}"
        };
      </script>

      {{-- Draft Autosave Functionality --}}
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          // Basic autosave functionality for sub-applications
          const form = document.getElementById('subApplicationForm');
          const statusText = document.getElementById('draftStatusText');
          const lastSavedText = document.getElementById('draftLastSavedText');
          const progressBar = document.getElementById('draftProgressBar');
          const progressValue = document.getElementById('draftProgressValue');

          if (!form) {
            console.warn('[Draft Autosave] Sub-application form not found.');
            return;
          }

          console.log('[Draft Autosave] Form found successfully, initializing...');

          let autoSaveTimer = null;
          let lastSaveTime = null;

          // Update status display
          function updateStatus(message, type = 'info') {
            if (statusText) {
              statusText.textContent = message;
              statusText.className = `font-bold ${type === 'error' ? 'text-red-700' :
                type === 'success' ? 'text-green-700' : 'text-blue-700'}`;
            }
          }

          // Update last saved time
          function updateLastSaved() {
            if (lastSavedText) {
              const now = new Date();
              lastSaveTime = now;
              lastSavedText.textContent = `Last saved: just now`;

              // Update relative time every minute
              setInterval(() => {
                if (lastSaveTime) {
                  const diff = Math.floor((Date.now() - lastSaveTime.getTime()) / 60000);
                  if (diff === 0) {
                    lastSavedText.textContent = 'Last saved: just now';
                  } else if (diff === 1) {
                    lastSavedText.textContent = 'Last saved: 1 minute ago';
                  } else {
                    lastSavedText.textContent = `Last saved: ${diff} minutes ago`;
                  }
                }
              }, 60000);
            }
          }

          // Calculate and update progress
          function updateProgress() {
            let filledFields = 0;
            let totalFields = 0;

            // Count form inputs
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
              if (input.type !== 'hidden' && input.name) {
                totalFields++;
                if (input.type === 'checkbox' || input.type === 'radio') {
                  if (input.checked) filledFields++;
                } else if (input.type === 'file') {
                  if (input.files && input.files.length > 0) filledFields++;
                } else {
                  if (input.value && input.value.trim()) filledFields++;
                }
              }
            });

            const progress = totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;

            if (progressBar) {
              progressBar.style.width = `${progress}%`;
            }
            if (progressValue) {
              progressValue.textContent = `${progress}%`;
            }

            return progress;
          }

          // Save draft function
          function saveDraft(options = {}) {
            console.log('[Draft Save] Starting save process...', options);
            const formData = new FormData(form);
            const applicantTypeValue = (document.getElementById('mainApplicantTypeInput')?.value || document.getElementById('applicantType')?.value || '').toLowerCase();
            if (applicantTypeValue) {
              formData.set('applicant_type', applicantTypeValue);
              formData.set('applicantType', applicantTypeValue);
              const legacyField = document.getElementById('applicantType');
              if (legacyField) {
                legacyField.value = applicantTypeValue;
              }
            }
            const progress = updateProgress();
            console.log('[Draft Save] Progress calculated:', progress);

            // Capture unit file number from form or generate if needed
            let unitFileNo = '';

            // Try to get file number from various possible sources
            const fileNoInput = document.getElementById('unitFileNo') ||
              document.querySelector('input[name="unit_file_no"]') ||
              document.querySelector('input[name="fileno"]') ||
              document.getElementById('unitFileNoHidden');

            if (fileNoInput && fileNoInput.value) {
              unitFileNo = fileNoInput.value;
            } else {
              // Try to get from displayed elements
              const fileNoDisplay = document.getElementById('stFileNumberDisplay') ||
                document.getElementById('suaFileNumberDisplay') ||
                document.querySelector('[data-file-number]');

              if (fileNoDisplay) {
                unitFileNo = fileNoDisplay.textContent || fileNoDisplay.getAttribute('data-file-number') || '';
              }
            }

            // Create form state object with all form data
            const formStateObj = {};

            const appendValue = (key, rawValue) => {
              const shouldStoreAsArray = key.endsWith('[]');

              if (!(key in formStateObj)) {
                formStateObj[key] = shouldStoreAsArray ? [] : rawValue;
              }

              if (shouldStoreAsArray) {
                if (!Array.isArray(formStateObj[key])) {
                  formStateObj[key] = formStateObj[key] !== undefined && formStateObj[key] !== null && formStateObj[key] !== ''
                    ? [formStateObj[key]]
                    : [];
                }

                if (rawValue !== null && rawValue !== undefined && rawValue !== '') {
                  formStateObj[key].push(rawValue);
                }
                return;
              }

              if (key in formStateObj) {
                const existing = formStateObj[key];
                if (Array.isArray(existing)) {
                  if (rawValue !== null && rawValue !== undefined && rawValue !== '') {
                    existing.push(rawValue);
                  }
                } else if (existing !== undefined && existing !== null && existing !== '' && rawValue !== existing) {
                  formStateObj[key] = [existing];
                  if (rawValue !== null && rawValue !== undefined && rawValue !== '') {
                    formStateObj[key].push(rawValue);
                  }
                } else {
                  formStateObj[key] = rawValue;
                }
              }
            };

            for (let [key, value] of formData.entries()) {
              let serializedValue = value;

              if (value instanceof File) {
                if (value && value.name) {
                  serializedValue = {
                    name: value.name,
                    size: value.size,
                    type: value.type,
                    lastModified: value.lastModified,
                  };
                } else {
                  serializedValue = null;
                }
              }

              appendValue(key, serializedValue);
            }

            // Add unit file number to form state if we have one
            if (unitFileNo) {
              formStateObj.unit_file_no = unitFileNo;
            }

            // Clear the formData and rebuild it properly
            const finalFormData = new FormData();
            finalFormData.append('form_state', JSON.stringify(formStateObj));
            finalFormData.append('progress_percent', progress);
            finalFormData.append('auto_save', options.auto ? '1' : '0');

            if (unitFileNo) {
              finalFormData.append('unit_file_no', unitFileNo);
            }

            // Get sub_application_id and main_application_id from form data attributes
            const subApplicationId = form.getAttribute('data-sub-application-id');
            const mainApplicationId = form.getAttribute('data-main-application-id');

            if (subApplicationId) {
              finalFormData.append('sub_application_id', subApplicationId);
            }

            if (mainApplicationId) {
              finalFormData.append('main_application_id', mainApplicationId);
            }

            updateStatus('Saving draft...', 'info');
            console.log('[Draft Save] About to send request to:', window.SUB_APPLICATION_DRAFT_ENDPOINTS?.save || '/subapplication/draft/save');
            console.log('[Draft Save] Sub Application ID:', subApplicationId);
            console.log('[Draft Save] Main Application ID:', mainApplicationId);
            console.log('[Draft Save] Unit File No:', unitFileNo);
            console.log('[Draft Save] Form data being sent:', finalFormData);

            fetch(window.SUB_APPLICATION_DRAFT_ENDPOINTS?.save || '/subapplication/draft/save', {
              method: 'POST',
              body: finalFormData,
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            })
              .then(response => {
                console.log('[Draft Save] Response status:', response.status);
                return response.json();
              })
              .then(data => {
                console.log('[Draft Save] Response data:', data);
                if (data.success) {
                  updateStatus('Draft saved', 'success');
                  updateLastSaved();

                  // Update draft list if response includes updated drafts
                  if (data.drafts) {
                    console.log('[Draft Save] Updating draft list with:', data.drafts);
                    updateDraftList(data.drafts);
                  } else {
                    console.log('[Draft Save] No drafts in response');
                  }

                  // Update current file number display if returned
                  if (data.unit_file_no) {
                    syncFileNumber(data.unit_file_no);
                  }

                  if (options.flash !== false && !options.auto) {
                    // Show brief success message for manual saves
                    setTimeout(() => {
                      updateStatus('Draft ready', 'info');
                    }, 2000);
                  }
                } else {
                  throw new Error(data.message || 'Save failed');
                }
              })
              .catch(error => {
                console.error('Draft save error:', error);
                updateStatus('Save failed', 'error');

                setTimeout(() => {
                  updateStatus('Draft ready', 'info');
                }, 3000);
              });
          }

          // Auto-save on form changes
          function handleFormChange() {
            console.log('[Draft Autosave] Form change detected, scheduling auto-save...');
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
              console.log('[Draft Autosave] Triggering auto-save after delay...');
              saveDraft({ auto: true, flash: false });
            }, 30000); // Auto-save every 30 seconds after changes
          }

          // Attach change listeners
          form.addEventListener('input', handleFormChange);
          form.addEventListener('change', handleFormChange);
          console.log('[Draft Autosave] Event listeners attached to form');

          // Also make the saveDraft function globally available for testing
          window.testSaveDraft = saveDraft;
          console.log('[Draft Autosave] saveDraft function made globally available as window.testSaveDraft');

          // Draft mode button handlers
          const freshButton = document.getElementById('draftModeFreshButton');
          const draftButton = document.getElementById('draftModeDraftButton');
          const draftPanel = document.getElementById('draftModeDraftPanel');

          if (freshButton) {
            freshButton.addEventListener('click', function () {
              console.log('[Draft Mode] Switching to fresh mode');

              // Update button states
              freshButton.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
              freshButton.classList.remove('text-blue-700', 'hover:bg-blue-50');

              if (draftButton) {
                draftButton.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                draftButton.classList.add('text-blue-700', 'hover:bg-blue-50');
              }

              // Keep draft panel visible if there are drafts (don't hide it)
              // Users should always be able to see and access their drafts
              console.log('[Draft Mode] Fresh mode selected, but keeping draft panel visible');

              // Clear form or reset to fresh state
              // You can add form clearing logic here if needed
            });
          }

          if (draftButton) {
            draftButton.addEventListener('click', function () {
              console.log('[Draft Mode] Switching to draft mode');

              // Update button states
              draftButton.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
              draftButton.classList.remove('text-blue-700', 'hover:bg-blue-50');

              if (freshButton) {
                freshButton.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                freshButton.classList.add('text-blue-700', 'hover:bg-blue-50');
              }

              // Show draft panel
              if (draftPanel) {
                draftPanel.classList.remove('hidden');
                console.log('[Draft Mode] Draft panel shown');
              } else {
                console.log('[Draft Mode] Draft panel not found');
              }

              // If no drafts are showing, try to fetch them
              const draftSelect = document.getElementById('draftListSelect');
              if (draftSelect && draftSelect.children.length <= 1) {
                console.log('[Draft Mode] No drafts in dropdown, fetching from server');
                fetchUserDrafts();
              }
            });
          }

          // Manual save on step changes
          document.querySelectorAll('[onclick*="goToStep"]').forEach(btn => {
            btn.addEventListener('click', () => {
              saveDraft({ flash: false });
            });
          });

          // Sync file number across all relevant fields
          function syncFileNumber(fileNumber) {
            console.log('[Sync File No] Updating fields with:', fileNumber);
            const fieldsToUpdate = [
              'unitFileNo',
              'unitFileNoHidden',
              'hidden-sua-fileno',
              'hidden-unit-fileno',
              'draftLocatorCurrentId'
            ];

            fieldsToUpdate.forEach(id => {
              const element = document.getElementById(id);
              if (element) {
                if (element.tagName && element.tagName.toLowerCase() === 'input') {
                  element.value = fileNumber;
                } else {
                  element.textContent = fileNumber;
                }
              }
            });

            // Also update summary displays if they exist
            // Check if this is a SUA application to avoid overriding SUA file numbers
            const isSUA = document.querySelector('input[name="is_sua"]')?.value === '1' ||
              window.location.href.includes('/sua/create');

            const summaryElements = ['stFileNumberDisplay'];

            // Only update suaFileNumberDisplay if this is NOT a SUA application
            if (!isSUA) {
              summaryElements.push('suaFileNumberDisplay');
            }

            summaryElements.forEach(id => {
              const element = document.getElementById(id);
              if (element) {
                element.textContent = fileNumber;
              }
            });
          }
          window.syncFileNumber = syncFileNumber;

          // Generate unit file number if not exists
          function generateUnitFileNumber() {
            const fileNoInput = document.getElementById('unitFileNo');
            const existingFileNo = fileNoInput?.value || document.getElementById('unitFileNoHidden')?.value;

            if (!existingFileNo) {
              // Generate a temporary file number in format: SUB-YYYY-HHMMSS
              const now = new Date();
              const year = now.getFullYear();
              const hours = String(now.getHours()).padStart(2, '0');
              const minutes = String(now.getMinutes()).padStart(2, '0');
              const seconds = String(now.getSeconds()).padStart(2, '0');
              const randomSuffix = Math.floor(Math.random() * 100).toString().padStart(2, '0');

              const tempFileNo = `DRAFT-ST-${year}-${hours}${minutes}${seconds}${randomSuffix}`;

              syncFileNumber(tempFileNo);

              console.log('Generated unit draft locator:', tempFileNo);
              return tempFileNo;
            }

            return existingFileNo;
          }
          window.generateUnitFileNumber = generateUnitFileNumber;

          // Update draft dropdown list
          function updateDraftList(drafts) {
            console.log('[Draft List] updateDraftList called with:', drafts);
            const draftSelect = document.getElementById('draftListSelect');
            if (!draftSelect || !drafts) {
              console.log('[Draft List] Missing draftSelect or drafts:', { draftSelect: !!draftSelect, drafts: drafts });
              return;
            }

            // Clear existing options except the first one (placeholder)
            while (draftSelect.children.length > 1) {
              draftSelect.removeChild(draftSelect.lastChild);
            }

            // Add updated draft options
            drafts.forEach((draft, index) => {
              console.log(`[Draft List] Processing draft ${index}:`, draft);
              const option = document.createElement('option');
              option.value = draft.draft_id; // Use draft_id as value for selection
              option.setAttribute('data-draft-id', draft.draft_id);
              option.setAttribute('data-file-no', draft.unit_file_no || '');

              // Display the file number (or fallback to draft ID) in the dropdown text
              const displayText = draft.unit_file_no || `Unit ${draft.draft_id ? draft.draft_id.substring(0, 8) : 'Unknown'}`;
              option.textContent = displayText;
              console.log(`[Draft List] Created option with text: "${displayText}"`);

              if (draft.is_current) {
                option.selected = true;
              }

              draftSelect.appendChild(option);
            });

            // Always show the draft panel when we have drafts (regardless of mode)
            const draftPanel = document.getElementById('draftModeDraftPanel');
            if (draftPanel) {
              if (drafts.length > 0) {
                draftPanel.classList.remove('hidden');
                console.log('[Draft List] Draft panel shown - found', drafts.length, 'drafts');
              } else {
                draftPanel.classList.add('hidden');
                console.log('[Draft List] Draft panel hidden - no drafts');
              }
            }
          }

          // Load Selected Draft Function
          function loadSelectedDraft() {
            console.log('[Load Draft] Load Selected clicked');
            const draftSelect = document.getElementById('draftListSelect');
            console.log('[Load Draft] Draft select element:', draftSelect);
            console.log('[Load Draft] Selected value:', draftSelect?.value);

            if (!draftSelect || !draftSelect.value) {
              console.log('[Load Draft] No draft selected');
              alert('Please select a draft to load.');
              return;
            }

            const selectedOption = draftSelect.options[draftSelect.selectedIndex];
            console.log('[Load Draft] Selected option:', selectedOption);

            if (!selectedOption || !selectedOption.getAttribute('data-draft-id')) {
              console.log('[Load Draft] Invalid draft selection');
              alert('Invalid draft selection.');
              return;
            }

            const draftId = selectedOption.getAttribute('data-draft-id');
            console.log('[Load Draft] Loading draft ID:', draftId);

            // Show loading state
            updateStatus('Loading application...', 'info');

            // Make AJAX request to load the draft
            fetch('/sub-application/draft/load', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              },
              body: JSON.stringify({ draft_id: draftId })
            })
              .then(response => {
                console.log('[Load Draft] Response status:', response.status);
                return response.json();
              })
              .then(data => {
                console.log('[Load Draft] Response data:', data);
                if (data.success) {
                  // Populate form with loaded data (form_state contains the actual form data)
                  console.log('[Load Draft] Populating form with:', data.form_state);
                  populateFormFromDraft(data.form_state);
                  updateStatus('Application loaded successfully', 'success');

                  // Update file number and other metadata
                  if (data.unit_file_no) {
                    syncFileNumber(data.unit_file_no);
                  }
                } else {
                  console.log('[Load Draft] Load failed:', data.message);
                  updateStatus('Failed to load application: ' + (data.message || 'Unknown error'), 'error');
                }
              })
              .catch(error => {
                console.error('Draft load error:', error);
                updateStatus('Error loading draft: ' + error.message, 'error');
              });
          }

          // Function to populate form from loaded draft data
          function populateFormFromDraft(draftData) {
            console.log('[Form Populate] Starting form population with:', draftData);
            if (!draftData) {
              console.log('[Form Populate] No draft data provided');
              return;
            }

            try {
              // Populate form fields based on the data structure
              Object.keys(draftData).forEach(key => {
                const value = draftData[key];

                // Special handling for checkbox arrays (like shared_areas[])
                if (key === 'shared_areas' && Array.isArray(value)) {
                  console.log('[Form Populate] Processing shared_areas array:', value);

                  // First, uncheck all shared_areas checkboxes
                  document.querySelectorAll('input[name="shared_areas[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                  });

                  // Then check the ones from the draft data
                  value.forEach(areaValue => {
                    const checkbox = document.querySelector(`input[name="shared_areas[]"][value="${areaValue}"]`);
                    if (checkbox) {
                      checkbox.checked = true;
                      console.log('[Form Populate] Checked shared area:', areaValue);
                    }

                    // If "other" is in the array, show the other areas container
                    if (areaValue === 'other') {
                      const otherContainer = document.getElementById('other_areas_container');
                      if (otherContainer) {
                        otherContainer.style.display = 'block';
                      }
                    }
                  });
                  return;
                }

                // Special handling for other_areas_detail
                if (key === 'other_areas_detail' && value) {
                  console.log('[Form Populate] Processing other_areas_detail:', value);
                  const textarea = document.getElementById('other_areas_detail');
                  if (textarea) {
                    textarea.value = value;
                    // Also show the container if it's hidden
                    const otherContainer = document.getElementById('other_areas_container');
                    if (otherContainer) {
                      otherContainer.style.display = 'block';
                    }
                  }
                  return;
                }

                // Handle regular fields
                const element = document.querySelector(`[name="${key}"]`);
                if (element && value !== null && value !== undefined) {
                  if (element.type === 'radio' || element.type === 'checkbox') {
                    if (element.value == value) {
                      element.checked = true;
                    }
                  } else {
                    element.value = value;
                  }
                }
              });

              // Update progress and status
              updateProgress();

              // Trigger change events to update dependent fields
              document.querySelectorAll('input[type="radio"]:checked, select').forEach(element => {
                element.dispatchEvent(new Event('change', { bubbles: true }));
              });

            } catch (error) {
              console.error('Error populating form:', error);
              updateStatus('Error populating form from draft', 'error');
            }
          }

          // Add event listener for Load Selected button
          const loadButton = document.getElementById('draftListLoadButton');
          if (loadButton) {
            loadButton.addEventListener('click', loadSelectedDraft);
          }

          // Add event listener for Refresh button
          const refreshButton = document.getElementById('draftListRefreshButton');
          if (refreshButton) {
            refreshButton.addEventListener('click', function () {
              console.log('[Draft Refresh] Refresh button clicked');
              fetchUserDrafts();
            });
          }

          // Initialize
          updateStatus('Draft ready', 'info');
          updateProgress();

          // Initialize draft list from bootstrap data
          const bootstrapData = window.SUB_APPLICATION_DRAFT_BOOTSTRAP || {};
          console.log('[Draft Init] Bootstrap data:', bootstrapData);

          if (bootstrapData.drafts && bootstrapData.drafts.length > 0) {
            console.log('[Draft Init] Found', bootstrapData.drafts.length, 'drafts in bootstrap data');
            updateDraftList(bootstrapData.drafts);

            // Ensure draft panel is visible since we have drafts
            const draftPanel = document.getElementById('draftModeDraftPanel');
            if (draftPanel) {
              draftPanel.classList.remove('hidden');
              console.log('[Draft Init] Draft panel made visible');
            }
          } else {
            console.log('[Draft Init] No drafts found in bootstrap data, fetching from server');
            // Fallback: fetch drafts from server
            fetchUserDrafts();
          }

          // Function to fetch user drafts from server
          function fetchUserDrafts() {
            console.log('[Draft Fetch] Fetching user drafts from server...');

            fetch('/sub-application-draft/mine', {
              method: 'GET',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
              }
            })
              .then(response => {
                console.log('[Draft Fetch] Response status:', response.status);
                return response.json();
              })
              .then(data => {
                console.log('[Draft Fetch] Server response:', data);
                if (data.success && data.drafts && data.drafts.length > 0) {
                  console.log('[Draft Fetch] Found', data.drafts.length, 'drafts from server');
                  updateDraftList(data.drafts);

                  // Update the empty message
                  const emptyMessage = document.getElementById('draftListEmpty');
                  if (emptyMessage) {
                    emptyMessage.classList.add('hidden');
                  }
                } else {
                  console.log('[Draft Fetch] No drafts found on server');
                }
              })
              .catch(error => {
                console.error('[Draft Fetch] Error fetching drafts:', error);
              });
          }

          // Generate file number on first load if needed
          if (typeof window.generateUnitFileNumber === 'function') {
            window.generateUnitFileNumber();
          }

          // Save draft every 5 minutes regardless
          setInterval(() => {
            saveDraft({ auto: true, flash: false });
          }, 300000);
        });
      </script>

      {{--
      <script src="{{ asset('js/sub-application-draft-autosave.js') }}"></script> --}}


      @include('sectionaltitling.partials.subapplication.pua_fix_script')
@endsection