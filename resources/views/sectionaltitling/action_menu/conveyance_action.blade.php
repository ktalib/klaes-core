@php
  $isApproved = $PrimaryApplication->application_status === 'Approved' &&
    $PrimaryApplication->planning_recommendation_status === 'Approved';

  $finalConveyanceStatus = strtolower($PrimaryApplication->final_conveyance_status ?? '');
  $hasFinalConveyanceRecord = !empty($PrimaryApplication->final_conveyance_id)
    || !empty($PrimaryApplication->fc_generated_date)
    || !empty($PrimaryApplication->final_conveyance_generated_at);
  $finalConveyanceGeneratedFlag = $PrimaryApplication->final_conveyance_generated ?? null;
  $isLegacyGenerated = false;

  if (!is_null($finalConveyanceGeneratedFlag)) {
    if (is_bool($finalConveyanceGeneratedFlag)) {
      $isLegacyGenerated = $finalConveyanceGeneratedFlag;
    } elseif (is_numeric($finalConveyanceGeneratedFlag)) {
      $isLegacyGenerated = (int) $finalConveyanceGeneratedFlag === 1;
    } elseif (is_string($finalConveyanceGeneratedFlag)) {
      $isLegacyGenerated = in_array(strtolower($finalConveyanceGeneratedFlag), ['1', 'true', 'yes'], true);
    }
  }

  $finalConveyanceGenerated = in_array($finalConveyanceStatus, ['generated', 'approved', 'finalized'])
    || $isLegacyGenerated
    || $hasFinalConveyanceRecord;

  $canGenerate = $isApproved && !$finalConveyanceGenerated;
  $canView = $finalConveyanceGenerated;
@endphp

<div class="relative dropdown-container">
  <!-- Dropdown Toggle Button -->
  <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full"
    onclick="customToggleDropdown(this, event)">
    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
  </button>
  <!-- Dropdown Menu -->
  <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">

    <li class="{{ $canGenerate ? '' : 'opacity-50 cursor-not-allowed' }}"
      title="{{ $canGenerate ? '' : (!$isApproved ? 'Both Application Status and Planning Recommendation must be approved' : 'Final Conveyance already generated') }}">
      @if($canGenerate)
        <button type="button" onclick="showFinalConveyanceModal({{ $PrimaryApplication->id }})"
          class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
          <i data-lucide="file-text" class="w-4 h-4 text-orange-500"></i>
          <span>Generate Final Conveyance</span>
        </button>
      @else
        <span class="w-full text-left px-4 py-2 flex items-center space-x-2 pointer-events-none">
          <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i>
          <span>Generate Final Conveyance</span>
        </span>
      @endif
    </li>

    @php
      $hasUpload = !empty($PrimaryApplication->latest_upload_id);
     @endphp

    <li class="{{ ($canView && !$hasUpload) ? '' : 'opacity-50 cursor-not-allowed' }}"
      title="{{ !$canView ? 'Generate Final Conveyance first to enable viewing' : ($hasUpload ? 'An edited version has been uploaded. View that instead.' : '') }}">
      @if($canView && !$hasUpload)
        <a href="{{ route('actions.final-conveyance', ['id' => $PrimaryApplication->id]) }}" target="_blank"
          class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
          <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
          <span>View Final Conveyance</span>
        </a>
      @else
        <span class="w-full text-left px-4 py-2 flex items-center space-x-2 pointer-events-none">
          <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i>
          <span>View Final Conveyance</span>
        </span>
      @endif
    </li>

    <li class="{{ ($canView && !$hasUpload) ? '' : 'opacity-50 cursor-not-allowed' }}"
      title="{{ !$canView ? 'Generate Final Conveyance first to enable upload' : ($hasUpload ? 'An edited version has already been uploaded.' : '') }}">
      @if($canView && !$hasUpload)
        <button type="button"
          onclick="openFinalConveyanceUploadModal({{ $PrimaryApplication->id }}, '{{ $PrimaryApplication->np_fileno ?: $PrimaryApplication->fileno }}')"
          class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
          <i data-lucide="upload" class="w-4 h-4 text-green-500"></i>
          <span>Upload Edited Final Conveyance</span>
        </button>
      @else
        <span class="w-full text-left px-4 py-2 flex items-center space-x-2 pointer-events-none">
          <i data-lucide="upload" class="w-4 h-4 text-gray-500"></i>
          <span>Upload Edited Final Conveyance</span>
        </span>
      @endif
    </li>

    <li class="{{ $hasUpload ? '' : 'opacity-50 cursor-not-allowed' }}"
      title="{{ $hasUpload ? '' : 'No edited version uploaded yet' }}">
      @if($hasUpload)
        <a href="{{ route('actions.final-conveyance.uploads.download', ['uploadId' => $PrimaryApplication->latest_upload_id]) }}"
          target="_blank" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
          <i data-lucide="file-check" class="w-4 h-4 text-amber-500"></i>
          <span>View Edit Final Conveyance</span>
        </a>
      @else
        <span class="w-full text-left px-4 py-2 flex items-center space-x-2 pointer-events-none">
          <i data-lucide="file-check" class="w-4 h-4 text-gray-500"></i>
          <span>View Edit Final Conveyance</span>
        </span>
      @endif
    </li>

  </ul>
</div>
<script>
  function customToggleDropdown(button, event) {
    event.stopPropagation();
    const currentDropdown = button.closest('.dropdown-container').querySelector('.action-menu');
    const isCurrentlyHidden = currentDropdown.classList.contains('hidden');

    // Close all other dropdowns first
    const allDropdowns = document.querySelectorAll('.action-menu');
    allDropdowns.forEach(dropdown => {
      dropdown.classList.add('hidden');
    });

    // If the current dropdown was hidden, show it
    if (isCurrentlyHidden) {
      currentDropdown.classList.remove('hidden');

      // Get button position
      const rect = button.getBoundingClientRect();

      // Position dropdown above the button
      currentDropdown.style.top = (rect.top - currentDropdown.offsetHeight - 5) + 'px';
      currentDropdown.style.left = (rect.left - currentDropdown.offsetWidth + rect.width) + 'px';

      // Check if dropdown would appear off the top of the screen
      if (rect.top - currentDropdown.offsetHeight < 0) {
        // If so, position it below the button instead
        currentDropdown.style.top = (rect.bottom + 5) + 'px';
      }
    }
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function (event) {
    const dropdowns = document.querySelectorAll('.action-menu');
    dropdowns.forEach(dropdown => {
      if (!dropdown.contains(event.target) &&
        !dropdown.closest('.dropdown-container').querySelector('.dropdown-toggle').contains(event.target)) {
        dropdown.classList.add('hidden');
      }
    });
  });
</script>