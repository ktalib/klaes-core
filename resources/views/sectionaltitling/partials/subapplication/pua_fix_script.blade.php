<script>
    /**
     * Fix for PUA Unit Selection and Persistence
     * populates unit-file-select from SUB_APP_BUYERS and handles selection
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Only run for PUA (when not SUA)
        const isSUA = document.querySelector('input[name="is_sua"]')?.value === '1';
        if (isSUA) {
            console.log('[PUA Fix] Skipping PUA logic (is SUA application)');
            return;
        }

        console.log('[PUA Fix] Initializing PUA Unit Selection Logic');

        const unitSelect = document.getElementById('unit-file-select');
        if (!unitSelect) {
            console.error('[PUA Fix] unit-file-select element not found');
            return;
        }

        // populate dropdown if empty (except placeholder)
        if (unitSelect.options.length <= 1) {
            populateUnitDropdown(unitSelect);
        }

        // Attach global handler if not verified
        window.handleUnitFileSelection = function (selectElement) {
            console.log('[PUA Fix] handleUnitFileSelection called', selectElement.value);

            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                console.log('[PUA Fix] No valid option selected');
                return;
            }

            const unitNo = selectedOption.getAttribute('data-unit-no') || selectedOption.value;
            const unitSize = selectedOption.getAttribute('data-unit-size');
            const buyerId = selectedOption.getAttribute('data-buyer-id');
            const unitFileNo = selectedOption.getAttribute('data-fileno');

            console.log('[PUA Fix] Selected Unit:', { unitNo, unitSize, buyerId, unitFileNo });

            // Update file number across the application
            if (unitFileNo && typeof window.syncFileNumber === 'function') {
                window.syncFileNumber(unitFileNo);
            }

            // Populate Form Fields
            const unitNoInput = document.querySelector('input[name="unit_number"]');
            if (unitNoInput) {
                unitNoInput.value = unitNo;
                // Trigger change manually to ensure any listeners fire (though readonly might not emit)
                unitNoInput.dispatchEvent(new Event('change', { bubbles: true }));
                unitNoInput.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                console.error('[PUA Fix] unit_number input not found');
            }

            const unitSizeInput = document.querySelector('input[name="unit_size"]');
            if (unitSizeInput) {
                unitSizeInput.value = unitSize || '';
            }

            // If a buyer is associated, try to select them in buyer dropdown too
            // This ensures consistency between selected unit and buyer
            if (buyerId) {
                const buyerSelect = document.getElementById('buyerSelect');
                if (buyerSelect) {
                    buyerSelect.value = buyerId;
                    buyerSelect.dispatchEvent(new Event('change'));
                }
            }

            // Fix for validation error: "Please select a file number first to determine applicant type"
            // We explicitly set the applicant type to individual, which matches the behavior of applyBuyer
            const applicantTypeInput = document.getElementById('mainApplicantTypeInput');
            if (applicantTypeInput) {
                applicantTypeInput.value = 'individual';

                // CRITICAL: Trigger UI visibility for individual fields
                if (typeof showIndividualFields === 'function') {
                    console.log('[PUA Fix] Showing individual fields');
                    showIndividualFields();
                } else {
                    console.error('[PUA Fix] showIndividualFields function not found');
                }
            }
            const legacyApplicantTypeField = document.getElementById('applicantType');
            if (legacyApplicantTypeField) {
                legacyApplicantTypeField.value = 'individual';
            }

            // Update property location text
            // Assumes updatePropertyLocation is defined globally (it is in the main script)
            if (typeof updatePropertyLocation === 'function') {
                updatePropertyLocation();
            } else {
                console.warn('[PUA Fix] updatePropertyLocation function not found');
            }

            // Update Preview Card or other UI elements
            updateUnitPreview(unitNo, unitSize);
        };
    });

    /**
     * Resolve the commissioned PUA file number for a buyer.
     * 1) buyer.unit_fileno (joined in the blade via buyer_list_id)
     * 2) match against window.ST_UNIT_FILES by buyer_list_id
     * 3) derive from np_fileno + zero-padded unit_sequence
     */
    function resolvePuaFileNo(buyer) {
        if (!buyer) return '';
        if (buyer.unit_fileno && buyer.unit_fileno !== 'N/A') return buyer.unit_fileno;

        const stFiles = window.ST_UNIT_FILES || [];
        const buyerId = Number(buyer.buyer_id || buyer.id);
        const match = stFiles.find(f => Number(f.buyer_list_id) === buyerId);
        if (match) {
            if (match.fileno && match.fileno !== 'N/A') return match.fileno;
            const np = (match.np_fileno || '').toString().trim();
            const seq = parseInt(match.unit_sequence, 10);
            if (np && !Number.isNaN(seq)) return `${np}-${seq.toString().padStart(3, '0')}`;
        }
        return '';
    }

    function populateUnitDropdown(arg) {
        // Accept either the <select> element or (from the main script) an array/undefined.
        const selectElement = (arg && arg.tagName === 'SELECT')
            ? arg
            : document.getElementById('unit-file-select');
        if (!selectElement) {
            console.warn('[PUA Fix] unit-file-select not found');
            return;
        }

        // data comes from window.SUB_APP_BUYERS
        const buyers = window.SUB_APP_BUYERS || [];
        console.log('[PUA Fix] Populating dropdown with', buyers.length, 'units');

        // Clear existing (keep first)
        while (selectElement.options.length > 1) {
            selectElement.remove(1);
        }

        if (buyers.length === 0) {
            const opt = document.createElement('option');
            opt.text = "No available units found";
            opt.disabled = true;
            selectElement.add(opt);
            return;
        }

        buyers.forEach(buyer => {
            const option = document.createElement('option');
            // Value is unit_no as that's what we want to save
            option.value = buyer.unit_no;

            const fileno = resolvePuaFileNo(buyer);

            if (!fileno) {
                option.disabled = true;
                option.text = `Unit ${buyer.unit_no} (${buyer.unit_size || 'N/A'}) - ${buyer.buyer_name} - (Not Commissioned)`;
            } else {
                option.text = `Unit ${buyer.unit_no} (${buyer.unit_size || 'N/A'}) - ${buyer.buyer_name} - ${fileno}`;
            }

            // Set data attributes for easy access
            option.setAttribute('data-unit-no', buyer.unit_no);
            option.setAttribute('data-unit-size', buyer.unit_size);
            option.setAttribute('data-buyer-id', buyer.buyer_id);
            option.setAttribute('data-buyer-name', buyer.buyer_name);
            option.setAttribute('data-fileno', fileno || 'N/A');

            selectElement.add(option);
        });
    }

    function updateUnitPreview(unitNo, unitSize) {
        // Update any summary display if exists
        const displayEx = document.getElementById('unitNumberDisplay');
        if (displayEx) displayEx.textContent = unitNo;
    }
</script>