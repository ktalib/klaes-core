<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="smart-fileno-selector">
    <!-- Hidden input for the main fileno field that gets submitted -->
    <input type="hidden" id="fileno" name="fileno" value="">
    <!-- Authoritative main selection; related-file picker never writes to this -->
    <input type="hidden" id="primary_selected_fileno" name="primary_selected_fileno" value="">
    <!-- Hidden inputs to mirror manual entry names so both modes are identical -->
    <input type="hidden" id="mlsFNo" name="mlsFNo" value="">
    <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="">
    <input type="hidden" id="NewKANGISFileno" name="NewKANGISFileno" value="">
    <input type="hidden" id="activeFileTab" name="activeFileTab" value="">

    <!-- Dropdown Selection Mode -->
    <div id="dropdown-mode" class="fileno-mode">
        <div class="flex gap-2 items-start">
            <div class="relative w-full">
                <input type="text" id="fileno-display" readonly placeholder="No file number selected"
                    class="w-full p-2.5 border border-gray-300 rounded-md bg-gray-100 text-gray-700 focus:outline-none cursor-not-allowed font-medium">
            </div>

            <button type="button" id="clear-selection"
                class="hidden px-3 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200 whitespace-nowrap">
                Clear
            </button>

            <button type="button"
                class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 whitespace-nowrap"
                onclick="openPrimaryFileNumberModal()">
                Browse…
            </button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Browse and select an official file number.</p>



        <div id="pra-status-banner"
            class="hidden mt-3 px-3 py-2 text-sm rounded-md border border-transparent bg-gray-50 text-gray-700"></div>




    </div>
</div>

<script>
    // Simple, direct JavaScript implementation
    document.addEventListener('DOMContentLoaded', function () {
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
        console.log('Initializing File Selector (Simplified)...');

        // Clear button logic
        $('#clear-selection').on('click', function () {
            console.log('Selection cleared');
            clearFileSelection();
        });

        console.log('File Selector initialized successfully');
    }

    function handleFileSelection(selectedOption) {
        if (!selectedOption) {
            return;
        }

        const payload = {
            fileno: selectedOption.getAttribute('data-fileno') || '',
            mlsFileno: selectedOption.getAttribute('data-mls-fileno') || '',
            kangisFileno: selectedOption.getAttribute('data-kangis-fileno') || '',
            newKangisFileno: selectedOption.getAttribute('data-newkangis-fileno') || ''
        };

        applyPrimaryFileSelection(payload, 'smart-selector');
    }

    function applyPrimaryFileSelection(payload, source) {
        if (!payload) {
            return;
        }

        const fileno = (payload.fileno || '').trim();
        const mlsFileno = (payload.mlsFileno || '').trim();
        const kangisFileno = (payload.kangisFileno || '').trim();
        const newKangisFileno = (payload.newKangisFileno || '').trim();

        document.getElementById('fileno').value = fileno;
        document.getElementById('primary_selected_fileno').value = fileno;
        document.getElementById('mlsFNo').value = '';
        document.getElementById('kangisFileNo').value = '';
        document.getElementById('NewKANGISFileno').value = '';
        document.getElementById('activeFileTab').value = '';

        if (mlsFileno) {
            document.getElementById('mlsFNo').value = mlsFileno;
            document.getElementById('activeFileTab').value = 'mls';
        } else if (kangisFileno) {
            document.getElementById('kangisFileNo').value = kangisFileno;
            document.getElementById('activeFileTab').value = 'kangis';
        } else if (newKangisFileno) {
            document.getElementById('NewKANGISFileno').value = newKangisFileno;
            document.getElementById('activeFileTab').value = 'newkangis';
        }

        // Update read-only input
        document.getElementById('fileno-display').value = fileno || mlsFileno || kangisFileno || newKangisFileno;

        // Toggle Clear Button
        const displayInput = document.getElementById('fileno-display');
        const clearBtn = document.getElementById('clear-selection');

        if (displayInput.value) {
            clearBtn.classList.remove('hidden');
            displayInput.classList.remove('text-gray-400');
            displayInput.classList.add('text-gray-900', 'bg-white');
        } else {
            clearBtn.classList.add('hidden');
            displayInput.classList.add('text-gray-400', 'bg-gray-100');
            displayInput.classList.remove('text-gray-900', 'bg-white');
        }

        const filenoInput = document.getElementById('fileno');
        filenoInput.dispatchEvent(new Event('input', { bubbles: true }));
        filenoInput.dispatchEvent(new Event('change', { bubbles: true }));

        /* 
        // Disabled in favor of matching records list in form-controller.js
        if (typeof window.queuePraPrefillForFileNumber === 'function') {
            const candidates = [mlsFileno, kangisFileno, newKangisFileno, fileno]
                .map(value => (value || '').trim())
                .filter(value => value.length);
            window.queuePraPrefillForFileNumber(candidates, source || 'smart-selector');
        }
        */
    }

    function clearFileSelection() {
        console.log('Clearing file selection');

        // Clear all hidden fields
        document.getElementById('fileno').value = '';
        document.getElementById('primary_selected_fileno').value = '';
        document.getElementById('mlsFNo').value = '';
        document.getElementById('kangisFileNo').value = '';
        window.applyPrimaryFileSelection = applyPrimaryFileSelection;
        document.getElementById('NewKANGISFileno').value = '';
        document.getElementById('activeFileTab').value = '';

        // Clear display input
        document.getElementById('fileno-display').value = '';
        document.getElementById('clear-selection').classList.add('hidden');

        // Reset style
        const displayInput = document.getElementById('fileno-display');
        displayInput.classList.add('text-gray-400', 'bg-gray-100');
        displayInput.classList.remove('text-gray-900', 'bg-white');

        console.log('File selection cleared');

        if (typeof window.queuePraPrefillForFileNumber === 'function') {
            window.queuePraPrefillForFileNumber([], 'smart-selector-clear');
        }
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

        {}
</style>