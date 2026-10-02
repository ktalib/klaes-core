<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="smart-fileno-selector">
    <!-- Hidden input for the main fileno field that gets submitted -->
    <input type="hidden" id="fileno" name="fileno" value="">
    <!-- Hidden inputs to mirror manual entry names so both modes are identical -->
    <input type="hidden" id="mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="NewKANGISFileno" name="NewKANGISFileno" value="">
    <input type="hidden" id="activeFileTab" name="activeFileTab" value="">
    
    <!-- Dropdown Selection Mode -->
    <div id="dropdown-mode" class="fileno-mode">
        <select id="fileno-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Select File Number</option>
            @php
                $fileNumbers = DB::connection('sqlsrv')
                    ->select("SELECT TOP (500)
                                [id],
                                [kangisFileNo],
                                [mlsfNo], 
                                [NewKANGISFileNo]
                              FROM [klas].[dbo].[fileNumber]
                              ORDER BY [id] DESC");
            @endphp
            @foreach($fileNumbers as $fileRecord)
                @php
                    // Determine which file number to display (priority: MLS -> KANGIS -> New KANGIS)
                    $displayFileNo = '';
                    
                    if (!empty($fileRecord->mlsfNo)) {
                        $displayFileNo = $fileRecord->mlsfNo;
                    } elseif (!empty($fileRecord->kangisFileNo)) {
                        $displayFileNo = $fileRecord->kangisFileNo;
                    } elseif (!empty($fileRecord->NewKANGISFileNo)) {
                        $displayFileNo = $fileRecord->NewKANGISFileNo;
                    }
                    
                    // Only show records that have at least one file number
                    if (empty($displayFileNo)) continue;
                @endphp
                
                <option value="{{ $fileRecord->id }}" 
                        data-id="{{ $fileRecord->id }}"
                        data-fileno="{{ $displayFileNo }}"
                        data-kangis-fileno="{{ $fileRecord->kangisFileNo ?? '' }}"
                        data-mls-fileno="{{ $fileRecord->mlsfNo ?? '' }}"
                        data-newkangis-fileno="{{ $fileRecord->NewKANGISFileNo ?? '' }}">
                    {{ $displayFileNo }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Search and select file numbers from fileNumber database</p>
        
        <!-- Selected File Number Display -->
        <div id="selected-fileno-display" class="hidden mt-3">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-green-800 mb-1">Selected File Number</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-lg font-bold text-green-900 font-mono bg-white px-3 py-1 rounded border border-green-200" id="selected-fileno-text"></span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800" id="file-type-badge">
                                    ✓ Selected
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button type="button" id="clear-selection" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple, direct JavaScript implementation
document.addEventListener('DOMContentLoaded', function() {
    console.log('Smart File Selector Loading...');
    
    // Wait for jQuery and Select2 to load
    let initAttempts = 0;
    function tryInitialize() {
        initAttempts++;
        
        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
            if (initAttempts < 20) {
                console.log('Waiting for jQuery/Select2... attempt', initAttempts);
                setTimeout(tryInitialize, 200);
                return;
            } else {
                console.error('jQuery or Select2 not loaded after 4 seconds');
                return;
            }
        }
        
        initializeFileSelector();
    }
    
    // Start initialization
    setTimeout(tryInitialize, 100);
});

function initializeFileSelector() {
    console.log('Initializing File Selector...');
    
    const selectElement = $('#fileno-select');
    if (!selectElement.length) {
        console.error('Select element not found');
        return;
    }
    
    // Destroy existing Select2 if present
    if (selectElement.hasClass('select2-hidden-accessible')) {
        selectElement.select2('destroy');
    }
    
    // Initialize Select2
    selectElement.select2({
        placeholder: 'Search and select file number...',
        allowClear: true,
        width: '100%',
        minimumInputLength: 1
    });
    
    // Handle selection change
    selectElement.on('select2:select', function(e) {
        console.log('File number selected!');
        
        const selectedOption = e.params.data.element;
        handleFileSelection(selectedOption);
    });
    
    // Handle clear
    selectElement.on('select2:clear', function() {
        console.log('Selection cleared');
        clearFileSelection();
    });
    
    // Clear button
    $('#clear-selection').on('click', function() {
        selectElement.val(null).trigger('change');
        clearFileSelection();
    });
    
    console.log('File Selector initialized successfully');
}

function handleFileSelection(selectedOption) {
    if (!selectedOption) {
        console.error('No option selected');
        return;
    }
    
    console.log('Processing selection:', selectedOption);
    
    // Get file number data
    const fileno = selectedOption.getAttribute('data-fileno') || '';
    const mlsFileno = selectedOption.getAttribute('data-mls-fileno') || '';
    const kangisFileno = selectedOption.getAttribute('data-kangis-fileno') || '';
    const newKangisFileno = selectedOption.getAttribute('data-newkangis-fileno') || '';
    
    console.log('File number data:', {
        fileno: fileno,
        mlsFileno: mlsFileno,
        kangisFileno: kangisFileno,
        newKangisFileno: newKangisFileno
    });
    
    // Clear all hidden fields first
    document.getElementById('fileno').value = '';
    document.getElementById('mlsFNo').value = '';
    document.getElementById('kangisFileNo').value = '';
    document.getElementById('NewKANGISFileno').value = '';
    document.getElementById('activeFileTab').value = '';
    
    // Set the main fileno field
    document.getElementById('fileno').value = fileno;
    
    // Set the appropriate specific field and active tab
    if (mlsFileno && mlsFileno.trim() !== '') {
        document.getElementById('mlsFNo').value = mlsFileno;
        document.getElementById('activeFileTab').value = 'mls';
        console.log('Set MLS file number:', mlsFileno);
    } else if (kangisFileno && kangisFileno.trim() !== '') {
        document.getElementById('kangisFileNo').value = kangisFileno;
        document.getElementById('activeFileTab').value = 'kangis';
        console.log('Set KANGIS file number:', kangisFileno);
    } else if (newKangisFileno && newKangisFileno.trim() !== '') {
        document.getElementById('NewKANGISFileno').value = newKangisFileno;
        document.getElementById('activeFileTab').value = 'newkangis';
        console.log('Set New KANGIS file number:', newKangisFileno);
    }
    
    // Show selection display
    document.getElementById('selected-fileno-text').textContent = fileno;
    document.getElementById('selected-fileno-display').classList.remove('hidden');
    
    // Debug: Log all field values
    console.log('Final field values:');
    console.log('fileno:', document.getElementById('fileno').value);
    console.log('mlsFNo:', document.getElementById('mlsFNo').value);
    console.log('kangisFileNo:', document.getElementById('kangisFileNo').value);
    console.log('NewKANGISFileno:', document.getElementById('NewKANGISFileno').value);
    console.log('activeFileTab:', document.getElementById('activeFileTab').value);
}

function clearFileSelection() {
    console.log('Clearing file selection');
    
    // Clear all hidden fields
    document.getElementById('fileno').value = '';
    document.getElementById('mlsFNo').value = '';
    document.getElementById('kangisFileNo').value = '';
    document.getElementById('NewKANGISFileno').value = '';
    document.getElementById('activeFileTab').value = '';
    
    // Hide selection display
    document.getElementById('selected-fileno-display').classList.add('hidden');
    
    console.log('File selection cleared');
}
</script>

<style>
/* Custom Select2 styling */
.select2-container--default .select2-selection--single {
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px;
    padding-left: 12px;
    color: #374151;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
    right: 8px;
}

.select2-dropdown {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 8px 12px;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3b82f6;
}

.select2-container--default .select2-selection--single:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>