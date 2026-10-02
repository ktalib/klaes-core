<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        const mainFormButtons = document.getElementById('main-form-buttons');

        // Set initial active tab to "Attach Site Plan" (notes)
        const initialTab = document.querySelector('[data-tab="notes"]');
        if (initialTab) {
            // Remove active from all tabs first
            tabs.forEach(t => t.classList.remove('active', 'border-blue-500', 'text-blue-600'));
            tabs.forEach(t => t.classList.add('border-transparent', 'text-gray-500'));
            
            // Set notes tab as active
            initialTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            initialTab.classList.remove('border-transparent', 'text-gray-500');
            
            // Hide all tab contents and show notes tab
            tabContents.forEach(content => content.classList.add('hidden'));
            document.getElementById('notes-tab').classList.remove('hidden');
            
            // Hide main form buttons for notes tab
            mainFormButtons.classList.add('hidden');
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active', 'border-blue-500',
                    'text-blue-600'));
                tabs.forEach(t => t.classList.add('border-transparent', 'text-gray-500'));

                // Add active class to clicked tab
                tab.classList.add('active', 'border-blue-500', 'text-blue-600');
                tab.classList.remove('border-transparent', 'text-gray-500');

                // Hide all tab contents
                tabContents.forEach(content => content.classList.add('hidden'));

                // Show the corresponding tab content
                const tabId = tab.dataset.tab;
                document.getElementById(`${tabId}-tab`).classList.remove('hidden');
                
                // Hide/show main form buttons based on active tab
                if (tabId === 'notes') {
                    mainFormButtons.classList.add('hidden');
                } else {
                    mainFormButtons.classList.remove('hidden');
                }
            });
        });

        // Hide the site plan tab form buttons initially if needed
        document.querySelectorAll('.hide-in-notes-tab').forEach(el => {
            el.style.display = 'none';
        });

        // Add measurement row
        document.getElementById('addMeasurement').addEventListener('click', function() {
            const container = document.getElementById('measurements-container');
            const newRow = document.createElement('div');
            newRow.className =
                'measurement-row grid grid-cols-1 md:grid-cols-3 gap-4 p-4 border rounded-md bg-gray-50';
            newRow.innerHTML = `
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Section/Unit No</label>
            <input type="text" name="sections[]" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Measurement (sqm)</label>
            <input type="text" name="measurements[]" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
        
    `;
            container.appendChild(newRow);

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Add event listener to the new remove button
            attachRemoveEventListeners();
        });

        // Function to attach remove event listeners
        function attachRemoveEventListeners() {
            document.querySelectorAll('.remove-measurement').forEach(button => {
                button.addEventListener('click', function() {
                    const container = document.getElementById('measurements-container');
                    const row = this.closest('.measurement-row');

                    // Only remove if there's more than one measurement row
                    if (container.querySelectorAll('.measurement-row').length > 1) {
                        container.removeChild(row);
                    } else {
                        // Clear the inputs instead of removing
                        row.querySelectorAll('input').forEach(input => {
                            input.value = '';
                        });
                    }
                });
            });
        }

        // Initial attachment of remove event listeners
        attachRemoveEventListeners();

        // Form validation
        document.getElementById('stMemoForm').addEventListener('submit', function(e) {
            const sections = document.querySelectorAll('input[name="sections[]"]');
            const measurements = document.querySelectorAll('input[name="measurements[]"]');
            const sharedFacilities = document.querySelector('textarea[name="shared_facilities"]');

            let valid = true;
            let errorMessage = '';

            if (!sharedFacilities.value.trim()) {
                valid = false;
                errorMessage = 'Please enter shared facilities';
                sharedFacilities.classList.add('border-red-500');
            } else {
                sharedFacilities.classList.remove('border-red-500');
            }

            sections.forEach((section, index) => {
                if (!section.value.trim()) {
                    valid = false;
                    errorMessage = 'Please enter all section/unit numbers';
                    section.classList.add('border-red-500');
                } else {
                    section.classList.remove('border-red-500');
                }

                if (!measurements[index].value.trim()) {
                    valid = false;
                    errorMessage = 'Please enter all measurements';
                    measurements[index].classList.add('border-red-500');
                } else {
                    measurements[index].classList.remove('border-red-500');
                }
            });

            if (!valid) {
                e.preventDefault();
                Swal.fire({
                    title: 'Validation Error',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
</script>