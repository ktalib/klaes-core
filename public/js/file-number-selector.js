/**
 * File Number Selector JavaScript
 * Handles all file number selection functionality
 */

// Global variables for file number selector
var selectedFileNumber = null;
var fileNumberSearchTimeout = null;

// Initialize file number selector when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeFileNumberSelector();
});

function initializeFileNumberSelector() {
    console.log('Initializing file number selector...');
    
    const trigger = document.getElementById('file-number-trigger');
    const popover = document.getElementById('file-number-popover');
    const searchInput = document.getElementById('file-number-search');
    
    console.log('Elements found:', {
        trigger: !!trigger,
        popover: !!popover,
        searchInput: !!searchInput
    });

    if (!trigger || !popover) {
        console.error('Required file number elements not found');
        return;
    }

    // Show popover when trigger is clicked
    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('File number trigger clicked');
        showFileNumberPopover();
    });

    // Close popover when clicking outside
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !popover.contains(e.target)) {
            hideFileNumberPopover();
        }
    });

    // Search input event listeners
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            console.log('Search input changed:', query);
            
            if (query.length >= 2) {
                debounceFileNumberSearch(query);
            } else if (query.length === 0) {
                loadTopFileNumbers();
            } else {
                clearFileNumberResults();
            }
        });

        searchInput.addEventListener('focus', function() {
            const query = this.value.trim();
            console.log('Search input focused, query:', query);
            
            if (query.length >= 2) {
                debounceFileNumberSearch(query);
            } else if (query.length === 0) {
                loadTopFileNumbers();
            }
        });
    }

    // Load initial results
    loadTopFileNumbers();
}

function showFileNumberPopover() {
    console.log('showFileNumberPopover called');
    
    const popover = document.getElementById('file-number-popover');
    if (popover) {
        popover.classList.remove('hidden');
        
        // Focus on search input
        const searchInput = document.getElementById('file-number-search');
        if (searchInput) {
            setTimeout(() => searchInput.focus(), 100);
        }
        
        // Load top file numbers if no search query
        const query = searchInput?.value.trim() || '';
        if (query.length < 2) {
            loadTopFileNumbers();
        }
    }
}

function hideFileNumberPopover() {
    console.log('hideFileNumberPopover called');
    
    const popover = document.getElementById('file-number-popover');
    if (popover) {
        popover.classList.add('hidden');
    }
    
    // Clear search input
    const searchInput = document.getElementById('file-number-search');
    if (searchInput) {
        searchInput.value = '';
    }
    
    clearFileNumberResults();
}

function debounceFileNumberSearch(query) {
    console.log('debounceFileNumberSearch called with:', query);
    
    // Clear previous timeout
    if (fileNumberSearchTimeout) {
        clearTimeout(fileNumberSearchTimeout);
    }
    
    // Set new timeout
    fileNumberSearchTimeout = setTimeout(() => {
        searchFileNumbers(query);
    }, 300);
}

function loadTopFileNumbers() {
    console.log('loadTopFileNumbers called');
    
    const loadingDiv = document.getElementById('file-number-loading');
    const resultsDiv = document.getElementById('file-number-results');
    const noResultsDiv = document.getElementById('file-number-no-results');

    console.log('Loading elements found:', {
        loadingDiv: !!loadingDiv,
        resultsDiv: !!resultsDiv,
        noResultsDiv: !!noResultsDiv
    });

    if (!loadingDiv || !resultsDiv || !noResultsDiv) {
        console.error('Required elements not found for loadTopFileNumbers');
        return;
    }

    // Show loading state
    loadingDiv.classList.remove('hidden');
    resultsDiv.classList.add('hidden');
    noResultsDiv.classList.add('hidden');

    console.log('Making API call to /file-numbers/api/top');

    // Make API call to get top file numbers
    fetch('/file-numbers/api/top?limit=10')
        .then(response => {
            console.log('API response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            loadingDiv.classList.add('hidden');
            
            if (data.success && data.data && data.data.length > 0) {
                console.log('Rendering', data.data.length, 'results');
                renderFileNumberResults(data.data);
                resultsDiv.classList.remove('hidden');
            } else {
                console.log('No results found');
                noResultsDiv.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-inbox text-2xl mb-2"></i>
                        <div>No file numbers found</div>
                    </div>
                `;
                noResultsDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Load top file numbers error:', error);
            loadingDiv.classList.add('hidden');
            noResultsDiv.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <div>Failed to load file numbers</div>
                    <div class="text-xs mt-1">Error: ${error.message}</div>
                </div>
            `;
            noResultsDiv.classList.remove('hidden');
        });
}

function searchFileNumbers(query) {
    console.log('searchFileNumbers called with query:', query);
    
    const loadingDiv = document.getElementById('file-number-loading');
    const resultsDiv = document.getElementById('file-number-results');
    const noResultsDiv = document.getElementById('file-number-no-results');

    if (!loadingDiv || !resultsDiv || !noResultsDiv) {
        console.error('Required elements not found for searchFileNumbers');
        return;
    }

    // Show loading state
    loadingDiv.classList.remove('hidden');
    resultsDiv.classList.add('hidden');
    noResultsDiv.classList.add('hidden');

    console.log('Making API call to /file-numbers/api/search?query=' + query);

    // Make API call to search file numbers
    fetch(`/file-numbers/api/search?query=${encodeURIComponent(query)}&limit=10`)
        .then(response => {
            console.log('Search API response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Search API response data:', data);
            loadingDiv.classList.add('hidden');
            
            if (data.success && data.data && data.data.length > 0) {
                console.log('Rendering', data.data.length, 'search results');
                renderFileNumberResults(data.data);
                resultsDiv.classList.remove('hidden');
            } else {
                console.log('No search results found');
                noResultsDiv.innerHTML = `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-search text-2xl mb-2"></i>
                        <div>No file numbers found for "${query}"</div>
                        <div class="text-xs mt-1">Try a different search term</div>
                    </div>
                `;
                noResultsDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Search file numbers error:', error);
            loadingDiv.classList.add('hidden');
            noResultsDiv.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <div>Search failed. Please try again.</div>
                    <div class="text-xs mt-1">Error: ${error.message}</div>
                </div>
            `;
            noResultsDiv.classList.remove('hidden');
        });
}

function renderFileNumberResults(results) {
    console.log('renderFileNumberResults called with', results.length, 'results');
    
    const resultsDiv = document.getElementById('file-number-results');
    if (!resultsDiv) {
        console.error('Results div not found');
        return;
    }

    // Helper function to check if a value is valid (not null, empty, or N/A)
    const isValidValue = (value) => {
        return value && 
               value !== null && 
               value !== undefined && 
               String(value).trim() !== '' && 
               String(value).toUpperCase().trim() !== 'N/A' && 
               String(value).toUpperCase().trim() !== 'NULL';
    };

    // Filter out results where all file number fields are NULL/empty/N/A
    const validResults = results.filter(result => {
        const hasValidKangis = isValidValue(result.kangis_file_no);
        const hasValidMlsf = isValidValue(result.mlsf_no);
        const hasValidNewKangis = isValidValue(result.new_kangis_file_no);
        
        // Must have at least one valid file number
        return hasValidKangis || hasValidMlsf || hasValidNewKangis;
    });

    console.log('Filtered results:', validResults.length, 'valid out of', results.length, 'total');

    const html = validResults.map(result => {
        // Use the same validation logic to select the first valid file number
        let fileNumber = '';
        let fileNumberType = 'Property File';
        
        if (isValidValue(result.kangis_file_no)) {
            fileNumber = result.kangis_file_no;
            fileNumberType = 'KANGIS File';
        } else if (isValidValue(result.new_kangis_file_no)) {
            fileNumber = result.new_kangis_file_no;
            fileNumberType = 'New KANGIS File';
        } else if (isValidValue(result.mlsf_no)) {
            fileNumber = result.mlsf_no;
            fileNumberType = 'MLSF File';
        }
        
        const fileName = result.file_name || fileNumberType;
        const status = result.status || 'Active';
        
        return `
            <div class="file-number-item p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors"
                 onclick="selectFileNumber('${fileNumber}', '${fileNumberType}', '${status}', ${result.id})"
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">${fileNumber}</div>
                        <div class="text-sm text-gray-600">${fileNumberType}</div>
                    </div>
                    <div class="text-xs px-2 py-1 rounded-full ${status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        ${status}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    resultsDiv.innerHTML = html;
    console.log('Results rendered to DOM');
}

function selectFileNumber(fileNumber, property, status, id = null) {
    console.log('selectFileNumber called:', fileNumber, property, status, id);
    
    // Store selected file number
    selectedFileNumber = fileNumber;
    
    // Update hidden input
    const hiddenInput = document.getElementById('file_number');
    if (hiddenInput) {
        hiddenInput.value = fileNumber;
        console.log('Updated hidden input with:', fileNumber);
    }

    // Update trigger button text
    const triggerValue = document.getElementById('file-number-value');
    if (triggerValue) {
        triggerValue.textContent = fileNumber;
        triggerValue.classList.remove('text-gray-500');
        triggerValue.classList.add('text-gray-900');
        console.log('Updated trigger button text');
    }

    // Update display text in selector area
    const displayText = document.getElementById('selected-file-info');
    if (displayText) {
        displayText.innerHTML = `
            <div class="text-sm font-medium text-green-800">${fileNumber}</div>
            <div class="text-xs text-green-600">${property}</div>
            <div class="text-xs text-gray-500">Status: ${status}</div>
        `;
        displayText.classList.remove('hidden');
        console.log('Updated display text');
    }

    // Update Current Selection Preview
    const previewContainer = document.getElementById('current-selection-preview');
    const previewFileNumber = document.getElementById('preview-file-number');
    const previewFileType = document.getElementById('preview-file-type');
    
    if (previewContainer && previewFileNumber && previewFileType) {
        // Remove leading zeros for manual selection display
        try {
            previewFileNumber.textContent = removeLeadingZerosFromFileNumber(fileNumber);
        } catch (e) {
            previewFileNumber.textContent = fileNumber;
        }
        previewFileType.textContent = property;
        previewContainer.classList.remove('hidden');
        console.log('Updated current selection preview');
    }

    // Hide the popover
    hideFileNumberPopover();
    
    // Trigger file number search to check if record exists and update button states
    if (typeof window.searchFileNumberInTables === 'function') {
        console.log('Triggering file number search for button state management');
        window.searchFileNumberInTables(fileNumber);
    } else {
        console.warn('searchFileNumberInTables function not available');
    }
    
    console.log('File number selection completed');
}

function clearFileNumberResults() {
    const loadingDiv = document.getElementById('file-number-loading');
    const resultsDiv = document.getElementById('file-number-results');
    const noResultsDiv = document.getElementById('file-number-no-results');
    
    if (loadingDiv) loadingDiv.classList.add('hidden');
    if (resultsDiv) resultsDiv.classList.add('hidden');
    if (noResultsDiv) noResultsDiv.classList.add('hidden');
}

/**
 * Remove leading zeros from the serial number part of a file number
 * @param {string} fileNumber - The file number to clean
 * @returns {string} - The cleaned file number without leading zeros
 */
function removeLeadingZerosFromFileNumber(fileNumber) {
    if (!fileNumber) return fileNumber;
    
    // Handle different file number patterns
    // Pattern: CON-RES-2025-0003 -> CON-RES-2025-3
    // Pattern: RES-2025-0456 -> RES-2025-456
    // Pattern: COM-2016-0208 -> COM-2016-208
    
    // Split by dashes and process the last part (serial number)
    const parts = fileNumber.split('-');
    if (parts.length >= 2) {
        // Get the last part (serial number)
        const lastPart = parts[parts.length - 1];
        
        // Remove leading zeros but keep at least one digit
        const cleanedLastPart = lastPart.replace(/^0+/, '') || '0';
        
        // Reconstruct the file number
        parts[parts.length - 1] = cleanedLastPart;
        return parts.join('-');
    }
    
    return fileNumber;
}

// Export functions for global access if needed
window.selectFileNumber = selectFileNumber;
window.hideFileNumberPopover = hideFileNumberPopover;
window.showFileNumberPopover = showFileNumberPopover;
