<div class="smart-fileno-selector" x-data="smartFilenoSelector()">
    <!-- Hidden input for the main fileno field that gets submitted -->
    <input type="hidden" id="fileno" name="fileno" value="">
    
    <!-- Dropdown Selection Mode -->
    <div id="dropdown-mode" class="fileno-mode">
        <select id="fileno-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Select File Number</option>
            @php
                $ctApplications = DB::connection('sqlsrv')
                    ->select("SELECT [fileno], [applicant_title], [first_name], [surname], [corporate_name], [rc_number], [multiple_owners_names] FROM [klas].[dbo].[mother_applications]");
            @endphp
            @foreach($ctApplications as $application)
                <option value="{{ $application->fileno }}" 
                        data-fileno="{{ $application->fileno }}"
                        data-applicant-title="{{ $application->applicant_title ?? '' }}"
                        data-first-name="{{ $application->first_name ?? '' }}"
                        data-surname="{{ $application->surname ?? '' }}"
                        data-corporate-name="{{ $application->corporate_name ?? '' }}"
                        data-rc-number="{{ $application->rc_number ?? '' }}"
                        data-multiple-owners="{{ $application->multiple_owners_names ?? '' }}">
                    {{ $application->fileno }} - 
                    @if($application->corporate_name)
                        {{ $application->corporate_name }}
                    @else
                        {{ $application->applicant_title ?? '' }} {{ $application->first_name ?? '' }} {{ $application->surname ?? '' }}
                    @endif
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">File numbers from mother applications database</p>
        
        <!-- Selected File Number Display (in dropdown mode) -->
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
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✓ Ready
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

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
function smartFilenoSelector() {
    return {
        selectedFileno: '',
        selectedApplication: null,
        
        init() {
            console.log('Smart Fileno Selector initialized');
            this.initializeSelect2();
        },
        
        initializeSelect2() {
            // Initialize Select2 with search functionality
            $('#fileno-select').select2({
                placeholder: 'Search and select file number...',
                allowClear: true,
                width: '100%',
                templateResult: function(option) {
                    if (!option.id) {
                        return option.text;
                    }
                    
                    // Custom template for dropdown options
                    var $option = $(
                        '<div class="select2-result-option">' +
                            '<div class="font-medium text-blue-800">' + option.element.dataset.fileno + '</div>' +
                            '<div class="text-sm text-gray-600">' + option.text.split(' - ')[1] + '</div>' +
                        '</div>'
                    );
                    return $option;
                },
                templateSelection: function(option) {
                    if (!option.id) {
                        return option.text;
                    }
                    return option.element.dataset.fileno || option.text;
                }
            });
            
            // Handle selection change
            $('#fileno-select').on('select2:select', (e) => {
                const selectedOption = e.params.data.element;
                if (selectedOption) {
                    this.selectedFileno = selectedOption.getAttribute('data-fileno') || '';
                    
                    // Create application object from data attributes
                    this.selectedApplication = {
                        fileno: selectedOption.getAttribute('data-fileno'),
                        applicant_title: selectedOption.getAttribute('data-applicant-title'),
                        first_name: selectedOption.getAttribute('data-first-name'),
                        surname: selectedOption.getAttribute('data-surname'),
                        corporate_name: selectedOption.getAttribute('data-corporate-name'),
                        rc_number: selectedOption.getAttribute('data-rc-number'),
                        multiple_owners_names: selectedOption.getAttribute('data-multiple-owners')
                    };
                    
                    this.handleSelection();
                }
            });
            
            // Handle clear selection
            $('#fileno-select').on('select2:clear', () => {
                this.clearSelection();
            });
        },
        
        handleSelection() {
            // Set hidden input
            const filenoInput = document.getElementById('fileno');
            if (filenoInput) {
                filenoInput.value = this.selectedFileno;
            }
            
            // Show selected display
            const selectedDisplay = document.getElementById('selected-fileno-display');
            const selectedText = document.getElementById('selected-fileno-text');
            
            if (selectedText) selectedText.textContent = this.selectedFileno;
            if (selectedDisplay) selectedDisplay.classList.remove('hidden');
            
            // Dispatch event for other components
            this.$dispatch('fileno-selected', {
                fileno: this.selectedFileno,
                application: this.selectedApplication
            });
            
            console.log('File selected:', this.selectedApplication);
        },
        
        clearSelection() {
            this.selectedFileno = '';
            this.selectedApplication = null;
            
            // Clear hidden input
            const filenoInput = document.getElementById('fileno');
            if (filenoInput) {
                filenoInput.value = '';
            }
            
            // Hide selected display
            const selectedDisplay = document.getElementById('selected-fileno-display');
            if (selectedDisplay) selectedDisplay.classList.add('hidden');
            
            // Dispatch clear event
            this.$dispatch('fileno-cleared');
        }
    }
}

// Initialize clear button functionality
document.addEventListener('DOMContentLoaded', function() {
    const clearBtn = document.getElementById('clear-selection');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            // Clear Select2 selection
            $('#fileno-select').val(null).trigger('change');
            
            // Trigger Alpine.js clear method
            const component = document.querySelector('[x-data*="smartFilenoSelector"]');
            if (component && component._x_dataStack) {
                component._x_dataStack[0].clearSelection();
            }
        });
    }
});
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

.select2-result-option {
    padding: 4px 0;
}

.select2-container--default .select2-selection--single:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>