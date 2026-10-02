<script>
    document.addEventListener('DOMContentLoaded', function() {
        // State variables
        var caveats = [];
        var searchTerm = "";
        var statusFilter = "all";
        var selectedCaveat = null;
        var activeTab = "place";
        var isLoading = false;
        var fileNumberMode = "selector"; // "selector" or "manual"

        // Form data
        const formData = {
            encumbranceType: "",
            typeOfDeed: "",
            fileNumber: "",
            location: "",
            petitioner: "",
            grantee: "",
            serialNo: "",
            pageNo: "",
            volumeNo: "",
            registrationNumber: "",
            startDate: "",
            endDate: "",
            instructions: "",
            remarks: "",
            liftingDate: "",
            liftInstructions: "",
            liftRemarks: ""
        };

        // Initialize the application
        initializeTabs();
        initializeEventListeners();
        updateStats();
        renderCaveatsList();
        renderActiveCaveatsList();
        renderCaveatsTable();
        updateCaveatNumber();
        updateDateCreated();
        setDefaultStartDate();

        // Initialize tab functionality
        function initializeTabs() {
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            tabTriggers.forEach(trigger => {
                trigger.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    setActiveTab(tab);
                });
            });
        }

        function setActiveTab(tabName) {
            activeTab = tabName;
            
            // Update triggers
            document.querySelectorAll('.tab-trigger').forEach(trigger => {
                trigger.classList.remove('border-blue-500', 'text-blue-600');
                trigger.classList.add('border-transparent', 'text-gray-500');
            });
            
            const activeTrigger = document.querySelector(`[data-tab="${tabName}"]`);
            if (activeTrigger) {
                activeTrigger.classList.remove('border-transparent', 'text-gray-500');
                activeTrigger.classList.add('border-blue-500', 'text-blue-600');
            }
            
            // Show/hide content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const activeContent = document.getElementById(`${tabName}-tab`);
            if (activeContent) {
                activeContent.classList.remove('hidden');
            }
        }

        // Initialize event listeners
        function initializeEventListeners() {
            // Encumbrance info icon click handler
            const encumbranceIcon = document.getElementById('encumbrance-info-icon');
            if (encumbranceIcon) {
                encumbranceIcon.addEventListener('click', function() {
                    const descriptionDiv = document.getElementById('encumbrance-description');
                    if (descriptionDiv) {
                        descriptionDiv.classList.toggle('hidden');
                    }
                });
            }

            // Search inputs
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    searchTerm = e.target.value.toLowerCase();
                    renderCaveatsList();
                });
            }
            
            const liftSearchInput = document.getElementById('lift-search-input');
            if (liftSearchInput) {
                liftSearchInput.addEventListener('input', function(e) {
                    searchTerm = e.target.value.toLowerCase();
                    renderActiveCaveatsList();
                });
            }
            
            const logSearchInput = document.getElementById('log-search-input');
            if (logSearchInput) {
                logSearchInput.addEventListener('input', function(e) {
                    searchTerm = e.target.value.toLowerCase();
                    renderCaveatsTable();
                });
            }
            
            // Status filters
            const statusFilter = document.getElementById('status-filter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function(e) {
                    statusFilter = e.target.value;
                    renderCaveatsList();
                });
            }
            
            const logStatusFilter = document.getElementById('log-status-filter');
            if (logStatusFilter) {
                logStatusFilter.addEventListener('change', function(e) {
                    statusFilter = e.target.value;
                    renderCaveatsTable();
                });
            }

            // Form input handlers for auto-completion
            const locationInput = document.getElementById('location');
            if (locationInput) {
                locationInput.addEventListener('input', function(e) {
                    formData.location = e.target.value;
                });
            }

            const petitionerInput = document.getElementById('petitioner');
            if (petitionerInput) {
                petitionerInput.addEventListener('input', function(e) {
                    formData.petitioner = e.target.value;
                });
            }

            const granteeInput = document.getElementById('grantee');
            if (granteeInput) {
                granteeInput.addEventListener('input', function(e) {
                    formData.grantee = e.target.value;
                });
            }

            const serialNoInput = document.getElementById('serial-no');
            if (serialNoInput) {
                serialNoInput.addEventListener('input', function(e) {
                    formData.serialNo = e.target.value;
                    updateDateCreated();
                });
            }

            const volumeNoInput = document.getElementById('volume-no');
            if (volumeNoInput) {
                volumeNoInput.addEventListener('input', function(e) {
                    formData.volumeNo = e.target.value;
                    updateDateCreated();
                });
            }

            const instructionsInput = document.getElementById('instructions');
            if (instructionsInput) {
                instructionsInput.addEventListener('input', function(e) {
                    formData.instructions = e.target.value;
                });
            }

            const remarksInput = document.getElementById('remarks');
            if (remarksInput) {
                remarksInput.addEventListener('input', function(e) {
                    formData.remarks = e.target.value;
                });
            }

            const liftInstructionsInput = document.getElementById('lift-instructions');
            if (liftInstructionsInput) {
                liftInstructionsInput.addEventListener('input', function(e) {
                    formData.liftInstructions = e.target.value;
                });
            }

            const liftRemarksInput = document.getElementById('lift-remarks');
            if (liftRemarksInput) {
                liftRemarksInput.addEventListener('input', function(e) {
                    formData.liftRemarks = e.target.value;
                });
            }
        }

        // Render functions (stubs - add actual implementation as needed)
        function renderCaveatsList() {
            console.log('renderCaveatsList called');
        }

        function renderActiveCaveatsList() {
            console.log('renderActiveCaveatsList called');
        }

        function renderCaveatsTable() {
            console.log('renderCaveatsTable called');
        }

        function updateStats() {
            console.log('updateStats called');
        }

        function updateCaveatNumber() {
            const numberInput = document.getElementById('caveat-number');
            if (numberInput) {
                const timestamp = Date.now();
                const caveatNumber = `CV-${timestamp.toString().slice(-8)}`;
                numberInput.value = caveatNumber;
                formData.caveatNumber = caveatNumber;
            }
        }

        function updateDateCreated() {
            const dateInput = document.getElementById('date-created');
            if (dateInput) {
                const now = new Date();
                const formattedDate = now.toISOString().slice(0, 16);
                dateInput.value = formattedDate;
                formData.dateCreated = formattedDate;
            }
        }

        function setDefaultStartDate() {
            const startDateInput = document.getElementById('start-date');
            if (startDateInput && !startDateInput.value) {
                const now = new Date();
                const formattedDate = now.toISOString().slice(0, 16);
                startDateInput.value = formattedDate;
                formData.startDate = formattedDate;
            }
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            
            if (!toast || !toastMessage) return;
            
            toastMessage.textContent = message;
            
            // Remove existing classes
            toast.classList.remove('border-red-200', 'bg-red-50', 'border-green-200', 'bg-green-50', 'border-blue-200', 'bg-blue-50');
            
            // Add appropriate classes based on type
            if (type === 'error') {
                toast.classList.add('border-red-200', 'bg-red-50');
            } else if (type === 'success') {
                toast.classList.add('border-green-200', 'bg-green-50');
            } else {
                toast.classList.add('border-blue-200', 'bg-blue-50');
            }
            
            // Show toast
            toast.classList.remove('hidden');
            
            // Hide after 5 seconds
            setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('border-red-200', 'bg-red-50', 'border-green-200', 'bg-green-50', 'border-blue-200', 'bg-blue-50');
            }, 5000);
        }

        // Make functions available globally if needed
        window.showToast = showToast;
        window.setActiveTab = setActiveTab;
    });
</script>
