/**
 * Public Applications (PuA) JavaScript Module
 * Handles PuA community and public-facing applications management functionality
 */

class PuAApplicationsManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalItems = 0;
        this.applications = [];
        this.filters = {
            search: '',
            communityType: '',
            publicInvolvement: '',
            status: '',
            dateRange: ''
        };
        
        this.init();
    }

    init() {
        console.log('Initializing PuA Applications Manager');
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Search functionality
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('pua-search');
            if (searchInput) {
                searchInput.addEventListener('input', this.debounce((e) => {
                    this.filters.search = e.target.value;
                    this.filterAndReload();
                }, 300));
            }
        });
    }

    loadInitialData() {
        this.loadApplications();
        this.loadStatistics();
    }

    async loadApplications() {
        try {
            const response = await fetch('/commission-new-st/pua-data?' + new URLSearchParams({
                page: this.currentPage,
                per_page: this.itemsPerPage,
                ...this.filters
            }), {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.applications = data.data.applications || [];
                this.totalItems = data.data.total || 0;
                this.renderTable();
                this.renderPagination();
            } else {
                console.error('Failed to load PuA applications:', data.message);
                this.showError('Failed to load public applications');
            }
        } catch (error) {
            console.error('Error loading PuA applications:', error);
            this.showError('Error loading public applications');
        }
    }

    async loadStatistics() {
        try {
            const response = await fetch('/commission-new-st/pua-data?stats=true', {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateStatistics(data.data.statistics || {});
            }
        } catch (error) {
            console.error('Error loading PuA statistics:', error);
        }
    }

    renderTable() {
        const tbody = document.getElementById('pua-applications-tbody');
        if (!tbody) return;

        if (this.applications.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-users text-4xl text-gray-300 mb-2"></i>
                            <span>No public applications found</span>
                            <button type="button" 
                                    class="mt-2 text-purple-600 hover:text-purple-500 text-sm font-medium"
                                    onclick="puaManager.loadApplications()">
                                Reload Applications
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.applications.map(app => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${app.application_id || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div>
                        <div class="font-medium">${app.community_name || 'N/A'}</div>
                        <div class="text-xs text-gray-500">${app.developer_name || ''}</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getCommunityTypeColorClass(app.community_type)}">
                        ${this.formatCommunityType(app.community_type)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatLocation(app)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getPublicStatusColorClass(app.public_involvement)}">
                        ${this.formatPublicInvolvement(app.public_involvement)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColorClass(app.status)}">
                        ${this.formatStatus(app.status)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatDate(app.consultation_end_date)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                        <button type="button" 
                                class="text-purple-600 hover:text-purple-900 text-sm"
                                onclick="puaManager.viewApplication(${app.id})">
                            View
                        </button>
                        <button type="button" 
                                class="text-blue-600 hover:text-blue-900 text-sm"
                                onclick="puaManager.manageConsultation(${app.id})">
                            Consultation
                        </button>
                        <button type="button" 
                                class="text-green-600 hover:text-green-900 text-sm"
                                onclick="puaManager.editApplication(${app.id})">
                            Edit
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Render pagination controls
     */
    renderPagination() {
        const paginationContainer = document.getElementById('pua-pagination');
        if (!paginationContainer) {
            console.warn('Pagination container not found');
            return;
        }

        const totalPages = Math.ceil(this.totalItems / this.itemsPerPage);
        
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        const currentPage = this.currentPage;
        let paginationHTML = '<div class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">';
        
        // Previous button
        paginationHTML += `
            <button type="button" 
                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 ${currentPage === 1 ? 'cursor-not-allowed opacity-50' : ''}"
                    ${currentPage === 1 ? 'disabled' : `onclick="puaManager.goToPage(${currentPage - 1})"`}>
                Previous
            </button>
        `;
        
        // Page info
        paginationHTML += `
            <span class="text-sm text-gray-700">
                Showing ${((currentPage - 1) * this.itemsPerPage) + 1} to ${Math.min(currentPage * this.itemsPerPage, this.totalItems)} of ${this.totalItems} results
            </span>
        `;
        
        // Next button
        paginationHTML += `
            <button type="button" 
                    class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 ${currentPage >= totalPages ? 'cursor-not-allowed opacity-50' : ''}"
                    ${currentPage >= totalPages ? 'disabled' : `onclick="puaManager.goToPage(${currentPage + 1})"`}>
                Next
            </button>
        `;
        
        paginationHTML += '</div>';
        paginationContainer.innerHTML = paginationHTML;
    }

    /**
     * Navigate to specific page
     */
    goToPage(page) {
        if (page < 1 || page > Math.ceil(this.totalItems / this.itemsPerPage)) {
            return;
        }
        this.currentPage = page;
        this.loadApplications();
    }

    updateStatistics(stats) {
        const elements = {
            'total-pua-count': stats.total || 0,
            'consultation-pua-count': stats.in_consultation || 0,
            'objections-pua-count': stats.objections || 0,
            'approved-pua-count': stats.approved || 0,
            'onhold-pua-count': stats.on_hold || 0
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // Utility methods
    formatLocation(app) {
        const parts = [app.location_area, app.location_lga, app.location_state].filter(Boolean);
        return parts.length > 0 ? parts.join(', ') : 'N/A';
    }

    formatCommunityType(type) {
        if (!type) return 'N/A';
        return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    formatPublicInvolvement(involvement) {
        if (!involvement) return 'N/A';
        return involvement.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    formatStatus(status) {
        if (!status) return 'N/A';
        return status.replace('_', ' ').toUpperCase();
    }

    getCommunityTypeColorClass(type) {
        const colorMap = {
            'residential_complex': 'bg-green-100 text-green-800',
            'commercial_center': 'bg-blue-100 text-blue-800',
            'mixed_development': 'bg-purple-100 text-purple-800',
            'gated_community': 'bg-yellow-100 text-yellow-800',
            'industrial_park': 'bg-gray-100 text-gray-800'
        };
        return colorMap[type] || 'bg-gray-100 text-gray-800';
    }

    getPublicStatusColorClass(involvement) {
        const colorMap = {
            'consultation_required': 'bg-yellow-100 text-yellow-800',
            'notice_published': 'bg-blue-100 text-blue-800',
            'objections_received': 'bg-red-100 text-red-800',
            'public_hearing': 'bg-orange-100 text-orange-800',
            'no_objections': 'bg-green-100 text-green-800'
        };
        return colorMap[involvement] || 'bg-gray-100 text-gray-800';
    }

    getStatusColorClass(status) {
        const colorMap = {
            'draft': 'bg-gray-100 text-gray-800',
            'public_consultation': 'bg-blue-100 text-blue-800',
            'under_review': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-green-100 text-green-800',
            'rejected': 'bg-red-100 text-red-800',
            'on_hold': 'bg-orange-100 text-orange-800'
        };
        return colorMap[status] || 'bg-gray-100 text-gray-800';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    showError(message) {
        console.error(message);
        // You can implement a toast notification here
    }

    // Public methods for UI interactions
    filterAndReload() {
        this.currentPage = 1;
        this.loadApplications();
    }

    viewApplication(id) {
        console.log('Viewing PuA application:', id);
        // Implement view functionality
    }

    manageConsultation(id) {
        console.log('Managing consultation for PuA application:', id);
        // Implement consultation management functionality
    }

    editApplication(id) {
        console.log('Editing PuA application:', id);
        // Implement edit functionality
    }

    deleteApplication(id) {
        console.log('Deleting PuA application:', id);
        // Implement delete functionality with confirmation
    }
}

// Global functions for HTML onclick handlers
function refreshPuAData() {
    if (window.puaManager) {
        window.puaManager.loadInitialData();
    }
}

function createNewPuA() {
    console.log('Creating new PuA application');
    // Implement create functionality
}

function filterPuAApplications(searchTerm) {
    if (window.puaManager) {
        window.puaManager.filters.search = searchTerm;
        window.puaManager.filterAndReload();
    }
}

function filterPuAByCommunityType(communityType) {
    if (window.puaManager) {
        window.puaManager.filters.communityType = communityType;
        window.puaManager.filterAndReload();
    }
}

function filterPuAByInvolvement(involvement) {
    if (window.puaManager) {
        window.puaManager.filters.publicInvolvement = involvement;
        window.puaManager.filterAndReload();
    }
}

function filterPuAByStatus(status) {
    if (window.puaManager) {
        window.puaManager.filters.status = status;
        window.puaManager.filterAndReload();
    }
}

function filterPuAByDate(dateRange) {
    if (window.puaManager) {
        window.puaManager.filters.dateRange = dateRange;
        window.puaManager.filterAndReload();
    }
}

function loadPuAApplications() {
    if (window.puaManager) {
        window.puaManager.loadApplications();
    }
}

/**
 * Open file number modal for PuA parent selection
 */
function openPuaFileNumberModal() {
    console.log('Opening PuA parent file number modal...');
    
    if (typeof GlobalFileNoModal !== 'undefined') {
        GlobalFileNoModal.open({
            targetFields: ['#pua_parent_file_number'],
            initialTab: 'mls',
            callback: function(selectedData) {
                console.log('PuA parent file number selected:', selectedData);
                
                // Populate the parent file number field
                const parentFileField = document.getElementById('pua_parent_file_number');
                if (parentFileField && selectedData.fileNumber) {
                    parentFileField.value = selectedData.fileNumber;
                    // Trigger the change handler
                    handleParentFileNumberChange(selectedData.fileNumber);
                }
            }
        });
    } else {
        console.error('GlobalFileNoModal not available');
        showErrorMessage('File number modal is not available. Please refresh the page and try again.');
    }
}

/**
 * Handle parent file number change - validates and sets up the form
 * @param {string} parentFileNumber - The selected parent file number
 */
function handleParentFileNumberChange(parentFileNumber) {
    console.log('🎯 Parent file number changed:', parentFileNumber);
    
    if (!parentFileNumber || parentFileNumber.trim() === '') {
        resetPuaForm();
        return;
    }
    
    // Validate file number format (ST-XXX-YYYY-N)
    const stFileNumberPattern = /^ST-(RES|COM|IND|MIXED)-\d{4}-\d+$/;
    if (!stFileNumberPattern.test(parentFileNumber.trim())) {
        showErrorMessage('Invalid file number format. Expected: ST-XXX-YYYY-N (e.g., ST-RES-2025-1)');
        resetPuaForm();
        return;
    }
    
    // Validate parent exists and get its details
    validateAndSetupParentFileNumber(parentFileNumber.trim());
}

/**
 * Validate parent file number and setup form
 * @param {string} parentFileNumber - The parent file number to validate
 */
async function validateAndSetupParentFileNumber(parentFileNumber) {
    try {
        console.log('🔍 Validating parent file number:', parentFileNumber);
        
        // Call API to validate parent file number
        const response = await fetch(`/api/st-file-numbers/validate/${encodeURIComponent(parentFileNumber)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            const parentData = data.data;
            console.log('✅ Parent file number validated:', parentData);
            
            // Check if it's a PRIMARY application
            if (parentData.file_no_type !== 'PRIMARY') {
                showErrorMessage('PuA applications can only be linked to PRIMARY applications, not SUA applications.');
                resetPuaForm();
                return;
            }
            
            // Setup the form with parent data
            setupPuaFormWithParent(parentData);
        } else {
            showErrorMessage(data.message || 'Parent file number not found or invalid.');
            resetPuaForm();
        }
    } catch (error) {
        console.error('❌ Error validating parent file number:', error);
        showErrorMessage('Error validating parent file number: ' + error.message);
        resetPuaForm();
    }
}

/**
 * Setup PuA form with parent data
 * @param {Object} parentData - The parent file number data
 */
function setupPuaFormWithParent(parentData) {
    console.log('🔧 Setting up PuA form with parent data:', parentData);
    
    // Set the NP file number (same as parent)
    const npFileNoInput = document.getElementById('pua_np_fileno');
    if (npFileNoInput) {
        npFileNoInput.value = parentData.np_fileno;
        npFileNoInput.classList.remove('bg-blue-100');
        npFileNoInput.classList.add('bg-green-100', 'text-green-700');
    }
    
    // Set unit file number placeholder
    const unitFileNoInput = document.getElementById('pua_unit_fileno');
    if (unitFileNoInput) {
        unitFileNoInput.value = `${parentData.np_fileno}-001 (next available)`;
        unitFileNoInput.classList.remove('bg-green-100');
        unitFileNoInput.classList.add('bg-blue-100', 'text-blue-700');
    }
    
    // Auto-select and enable the correct land use
    const landUseCode = parentData.land_use_code; // RES, COM, IND, MIXED
    const landUseMap = {
        'RES': 'RESIDENTIAL',
        'COM': 'COMMERCIAL', 
        'IND': 'INDUSTRIAL',
        'MIXED': 'MIXED'
    };
    
    const landUseName = landUseMap[landUseCode];
    if (landUseName) {
        selectPuaLandUse(landUseName, landUseCode);
    }
    
    // Enable all form fields
    enablePuaFormFields();
    
    // Enable the generate button
    const generateBtn = document.getElementById('pua_generate_btn');
    if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.className = 'px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-medium rounded-lg hover:from-purple-700 hover:to-pink-700 focus:ring-4 focus:ring-purple-300 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5';
    }
    
    showSuccessMessage(`Parent file number validated. Land use set to ${landUseName}.`);
}

/**
 * Select and highlight the appropriate land use
 * @param {string} landUseName - The land use name (RESIDENTIAL, COMMERCIAL, etc.)
 * @param {string} landUseCode - The land use code (RES, COM, etc.)
 */
function selectPuaLandUse(landUseName, landUseCode) {
    console.log('🎨 Selecting land use:', landUseName, landUseCode);
    
    // Reset all land use options
    const allLabels = document.querySelectorAll('#pua_land_use_container label');
    allLabels.forEach(label => {
        label.className = 'relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all';
        const span = label.querySelector('span');
        if (span) span.className = 'text-sm font-medium text-gray-500';
        const input = label.querySelector('input');
        if (input) {
            input.checked = false;
            input.disabled = true;
        }
    });
    
    // Find and select the correct land use
    const targetLabel = document.getElementById(`pua_land_use_${landUseName.toLowerCase()}`);
    if (targetLabel) {
        const colorMap = {
            'RESIDENTIAL': { border: 'border-green-500', bg: 'bg-green-50', text: 'text-green-700' },
            'COMMERCIAL': { border: 'border-blue-500', bg: 'bg-blue-50', text: 'text-blue-700' },
            'INDUSTRIAL': { border: 'border-orange-500', bg: 'bg-orange-50', text: 'text-orange-700' },
            'MIXED': { border: 'border-purple-500', bg: 'bg-purple-50', text: 'text-purple-700' }
        };
        
        const colors = colorMap[landUseName] || colorMap['RESIDENTIAL'];
        
        targetLabel.className = `relative flex items-center p-3 ${colors.bg} rounded-lg border-2 ${colors.border} cursor-default opacity-100 transition-all ring-2 ring-offset-2 ring-${colors.border.split('-')[1]}-200`;
        
        const span = targetLabel.querySelector('span');
        if (span) span.className = `text-sm font-medium ${colors.text}`;
        
        const input = targetLabel.querySelector('input');
        if (input) {
            input.checked = true;
            input.disabled = false; // Enable it temporarily for form submission
        }
    }
}

/**
 * Reset PuA form to initial state
 */
function resetPuaForm() {
    console.log('🔄 Resetting PuA form');
    
    // Clear file number fields
    const npFileNoInput = document.getElementById('pua_np_fileno');
    if (npFileNoInput) {
        npFileNoInput.value = 'Select parent file number first';
        npFileNoInput.className = 'w-full p-3 border border-gray-300 rounded-md bg-blue-100 text-blue-700 cursor-not-allowed';
    }
    
    const unitFileNoInput = document.getElementById('pua_unit_fileno');
    if (unitFileNoInput) {
        unitFileNoInput.value = 'Will be generated automatically';
        unitFileNoInput.className = 'w-full p-3 border border-gray-300 rounded-md bg-green-100 text-green-700 cursor-not-allowed';
    }
    
    // Reset land use options
    const allLabels = document.querySelectorAll('#pua_land_use_container label');
    allLabels.forEach(label => {
        label.className = 'relative flex items-center p-3 bg-gray-100 rounded-lg border-2 border-gray-300 cursor-not-allowed opacity-60 transition-all';
        const span = label.querySelector('span');
        if (span) span.className = 'text-sm font-medium text-gray-500';
        const input = label.querySelector('input');
        if (input) {
            input.checked = false;
            input.disabled = true;
        }
    });
    
    // Disable form fields
    disablePuaFormFields();
    
    // Disable generate button
    const generateBtn = document.getElementById('pua_generate_btn');
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.className = 'px-8 py-3 bg-gray-400 text-white font-medium rounded-lg cursor-not-allowed transition-all duration-200 shadow-lg';
    }
}

/**
 * Enable all PuA form fields
 */
function enablePuaFormFields() {
    // Enable applicant fields (they should be handled by shared-applicant.js)
    const applicantFields = document.querySelectorAll('[name^="pua_"]:not([name="pua_np_fileno"]):not([name="pua_unit_fileno"]):not([name="pua_land_use"])');
    applicantFields.forEach(field => {
        if (field.type !== 'hidden' && !field.hasAttribute('readonly')) {
            field.disabled = false;
            field.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }
    });
}

/**
 * Disable all PuA form fields
 */
function disablePuaFormFields() {
    const applicantFields = document.querySelectorAll('[name^="pua_"]:not([name="pua_parent_file_number"])');
    applicantFields.forEach(field => {
        if (field.type !== 'hidden' && !field.hasAttribute('readonly')) {
            field.disabled = true;
            field.classList.add('bg-gray-100', 'cursor-not-allowed');
        }
    });
}

/**
 * Generate PuA file number from form - called by the generate button
 */
function generatePuaFileNumberFromForm() {
    const parentFileNumber = document.getElementById('pua_parent_file_number')?.value?.trim();
    
    if (!parentFileNumber) {
        showErrorMessage('Please select a parent file number first.');
        return;
    }
    
    // Get applicant data from form
    const applicantData = gatherPuaApplicantData();
    
    generatePuaFileNumber(parentFileNumber, applicantData);
}

/**
 * Gather applicant data from PuA form
 * @returns {Object} The applicant data
 */
function gatherPuaApplicantData() {
    return {
        applicant_type: document.getElementById('pua_applicant_type')?.value || 'Individual',
        applicant_title: document.getElementById('pua_applicant_title')?.value || null,
        first_name: document.getElementById('pua_first_name')?.value || null,
        surname: document.getElementById('pua_surname')?.value || null,
        corporate_name: document.getElementById('pua_corporate_name')?.value || null,
        rc_number: document.getElementById('pua_rc_number')?.value || null,
        multiple_owners_names: document.getElementById('pua_multiple_owners_names')?.value || null
    };
}

/**
 * Generate PUA file number based on parent file number
 * @param {string} parentFileNumber - The parent primary file number
 * @param {Object} applicantData - The applicant data
 */
function generatePuaFileNumber(parentFileNumber, applicantData = {}) {
    console.log('🚀 Generating PUA file number for parent:', parentFileNumber);
    console.log('👤 Applicant data:', applicantData);
    
    if (!parentFileNumber) {
        showErrorMessage('Please provide a parent file number for PUA generation.');
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]') || 
                     document.querySelector('input[name="_token"]');
    
    // Show loading state
    const npFileNoInput = document.getElementById('pua_np_fileno');
    const unitFileNoInput = document.getElementById('pua_unit_fileno');
    
    if (npFileNoInput) npFileNoInput.value = 'Generating...';
    if (unitFileNoInput) unitFileNoInput.value = 'Generating...';
    
    // Prepare request data with applicant information
    const requestData = {
        parent_file_number: parentFileNumber,
        applicant_type: applicantData.applicant_type || 'Individual',
        applicant_title: applicantData.applicant_title || null,
        first_name: applicantData.first_name || null,
        surname: applicantData.surname || null,
        corporate_name: applicantData.corporate_name || null,
        rc_number: applicantData.rc_number || null,
        multiple_owners_names: applicantData.multiple_owners_names || null
    };
    
    // Call the new ST File Number Service API
    fetch('/api/st-file-numbers/pua', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('📡 PUA API Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('📊 PUA API Response data:', data);
        
        if (data.success && data.data) {
            // Update the input fields with generated file numbers
            if (npFileNoInput) {
                npFileNoInput.value = data.data.np_fileno;
                console.log('✅ Set PUA NP FileNo:', data.data.np_fileno);
            }
            if (unitFileNoInput) {
                unitFileNoInput.value = data.data.unit_fileno;
                console.log('✅ Set PUA Unit FileNo:', data.data.unit_fileno);
            }
            
            console.log('✅ PUA file number generated successfully:', data.data);
            console.log('📋 Tracking ID:', data.data.tracking_id);
            
            // Show success message
            showSuccessMessage('PUA file number generated successfully!');
        } else {
            console.error('❌ PUA API returned success=false:', data.message || 'Unknown error');
            throw new Error(data.message || 'API returned success=false');
        }
    })
    .catch((error) => {
        console.error('❌ Failed to generate PUA file number:', error);
        
        // Show error message
        showErrorMessage('Error generating PUA file number: ' + error.message);
        
        // Reset loading state
        if (npFileNoInput) npFileNoInput.value = '';
        if (unitFileNoInput) unitFileNoInput.value = '';
    });
}

/**
 * Handle PUA land use selection
 * @param {HTMLElement} selectedCheckbox - The selected checkbox element
 */
function handlePuaLandUseChange(selectedCheckbox) {
    console.log('🎯 PUA Land use checkbox changed:', selectedCheckbox.value, 'checked:', selectedCheckbox.checked);
    
    // Handle checkbox selection (only allow one selection like radio button)
    const allCheckboxes = document.querySelectorAll('input[name="pua_selectedLandUse"]');
    const checkedBoxes = [];
    
    allCheckboxes.forEach(checkbox => {
        if (checkbox !== selectedCheckbox && selectedCheckbox.checked) {
            // Uncheck other checkboxes when one is selected
            checkbox.checked = false;
            checkbox.parentElement.classList.remove('selected');
        }
        
        if (checkbox.checked) {
            checkedBoxes.push(checkbox.value);
            checkbox.parentElement.classList.add('selected');
        } else {
            checkbox.parentElement.classList.remove('selected');
        }
    });
    
    // Update hidden input
    const selectedLandUse = checkedBoxes.length > 0 ? checkedBoxes[0] : '';
    const hiddenInput = document.getElementById('pua_land_use_hidden');
    if (hiddenInput) {
        hiddenInput.value = selectedLandUse;
    }
    
    console.log('🎯 Selected PUA land use:', selectedLandUse);
}

// Utility functions for PUA
function showSuccessMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function showErrorMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

/**
 * Load PRIMARY file numbers based on land use filter
 */
async function loadPrimaryFileNumbers() {
    const landUseFilter = document.getElementById('pua_land_use_filter');
    const fileNumberSelect = document.getElementById('pua_parent_file_number');
    const loadingIndicator = document.getElementById('pua_loading_indicator');
    
    if (!landUseFilter || !fileNumberSelect) return;
    
    const selectedLandUse = landUseFilter.value;
    
    // Reset file number dropdown
    fileNumberSelect.innerHTML = '<option value="">Loading...</option>';
    fileNumberSelect.disabled = true;
    loadingIndicator.classList.remove('hidden');
    
    try {
        // Build search parameters
        const params = new URLSearchParams({
            file_no_type: 'PRIMARY',
            status: 'USED'
        });
        
        if (selectedLandUse) {
            params.append('land_use_code', selectedLandUse);
        }
        
        const response = await fetch(`/api/st-file-numbers/search?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            // Clear options
            fileNumberSelect.innerHTML = '<option value="">Select a file number...</option>';
            
            // Add file number options
            data.data.forEach(record => {
                const applicantName = record.applicant_type === 'Corporate' 
                    ? record.corporate_name 
                    : `${record.first_name || ''} ${record.surname || ''}`.trim();
                
                const option = document.createElement('option');
                option.value = record.np_fileno;
                option.textContent = `${record.np_fileno} - ${record.land_use} (${applicantName})`;
                option.dataset.details = JSON.stringify(record);
                fileNumberSelect.appendChild(option);
            });
            
            fileNumberSelect.disabled = false;
            
            if (data.data.length === 0) {
                fileNumberSelect.innerHTML = '<option value="">No PRIMARY files found for this land use</option>';
            }
            
        } else {
            console.error('Failed to load file numbers:', data.message);
            fileNumberSelect.innerHTML = '<option value="">Error loading file numbers</option>';
        }
        
    } catch (error) {
        console.error('Error loading file numbers:', error);
        fileNumberSelect.innerHTML = '<option value="">Error loading file numbers</option>';
    } finally {
        loadingIndicator.classList.add('hidden');
    }
}

/**
 * Handle parent file number selection change
 */
async function handleParentFileNumberChange(selectedValue) {
    const fileNumberSelect = document.getElementById('pua_parent_file_number');
    const parentDetails = document.getElementById('pua_parent_details');
    
    if (!selectedValue) {
        // Reset form when no parent selected
        resetPuaForm();
        parentDetails.classList.add('hidden');
        return;
    }
    
    try {
        // Get details from the selected option
        const selectedOption = fileNumberSelect.querySelector(`option[value="${selectedValue}"]`);
        let details = null;
        
        if (selectedOption && selectedOption.dataset.details) {
            details = JSON.parse(selectedOption.dataset.details);
        } else {
            // Fallback: validate with API
            const response = await fetch(`/api/st-file-numbers/validate/${selectedValue}`);
            const data = await response.json();
            
            if (data.success) {
                details = data.data;
            } else {
                throw new Error(data.message);
            }
        }
        
        if (details) {
            // Show parent details
            document.getElementById('parent_file_display').textContent = details.np_fileno;
            document.getElementById('parent_land_use_display').textContent = details.land_use;
            document.getElementById('parent_status_display').textContent = details.status;
            
            const applicantName = details.applicant_type === 'Corporate' 
                ? details.corporate_name 
                : `${details.first_name || ''} ${details.surname || ''}`.trim();
            document.getElementById('parent_applicant_display').textContent = applicantName || 'N/A';
            
            parentDetails.classList.remove('hidden');

            // Setup parent file number in form
            await validateAndSetupParentFileNumber(selectedValue, details);

            // Backfill Location Details / map from the parent's indexing record
            await backfillPuaLocationFromParent(details.np_fileno || selectedValue);
        }
        
    } catch (error) {
        console.error('❌ Error handling parent selection:', error);
        alert('Error loading parent file number details: ' + error.message);
        resetPuaForm();
        parentDetails.classList.add('hidden');
    }
}

/**
 * Backfill the PuA Location Details section (and pin the map) from the parent
 * PRIMARY file number's indexing record. The parent NPFN is indexed when it is
 * commissioned, so its district / LGA / street / plot and — when present —
 * latitude & longitude carry straight down to the unit.
 */
async function backfillPuaLocationFromParent(parentFileNumber) {
    const locationMap = window.STLocationMaps && window.STLocationMaps.pua;
    if (!locationMap) return;

    const indexed = await fetchIndexedLocation(parentFileNumber);

    if (!indexed) {
        console.log('ℹ️ No indexing record found for parent', parentFileNumber, '- location left blank');
        locationMap.clear();
        return;
    }

    locationMap.backfill(indexed);
}

/**
 * Validate parent file number and setup form
 */
async function validateAndSetupParentFileNumber(fileNumber, details = null) {
    try {
        // If we don't have details, validate with API
        if (!details) {
            const response = await fetch(`/api/st-file-numbers/validate/${fileNumber}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }
            
            details = data.data;
        }
        
        // Validate that it's a PRIMARY file number
        if (details.file_no_type !== 'PRIMARY') {
            throw new Error('Only PRIMARY file numbers can be used as parents for PuA applications');
        }
        
        // Validate status - accept both USED and ACTIVE statuses
        if (details.status !== 'USED' && details.status !== 'ACTIVE') {
            throw new Error('Parent file number must have USED or ACTIVE status');
        }
        
        // Set up the form with parent details
        onParentFileNumberSelected(fileNumber, details);
        
        console.log('✅ Parent file number validated and set up:', fileNumber);
        
    } catch (error) {
        console.error('❌ Error validating parent file number:', error);
        throw error;
    }
}

/**
 * Setup form when parent file number is selected
 */
function onParentFileNumberSelected(fileNumber, details) {
    // Set NP File Number (same as parent)
    const npFileNoInput = document.getElementById('pua_np_fileno');
    if (npFileNoInput) {
        npFileNoInput.value = fileNumber;
        npFileNoInput.classList.remove('bg-blue-100', 'text-blue-700');
        npFileNoInput.classList.add('bg-green-100', 'text-green-700');
    }
    
    // Generate Unit File Number preview
    generateUnitFileNumberPreview(fileNumber);
    
    // Auto-fill and highlight the correct land use checkbox
    const landUseCode = details.land_use_code;
    selectPuaLandUse(landUseCode);
    
    // Inherit Application Type from parent
    inheritApplicationType(details.application_type || 'Direct Allocation');
    
    // Load buyers for selected parent file number
    loadBuyersForParentFileNumber(fileNumber);
    
    // Enable all other form fields
    enablePuaFormFields();
    
    console.log('✅ Form set up with parent:', fileNumber, 'Land Use:', details.land_use);
}

/**
 * Inherit Application Type from parent PRIMARY file number
 */
function inheritApplicationType(applicationType) {
    const applicationTypeInput = document.getElementById('pua_application_type');
    if (applicationTypeInput) {
        applicationTypeInput.value = applicationType || 'Direct Allocation';
        // Update styling to show it's populated
        applicationTypeInput.classList.remove('bg-purple-50', 'text-purple-800');
        applicationTypeInput.classList.add('bg-green-50', 'text-green-800');
        console.log('✅ PuA Application Type inherited:', applicationType);
    }
}

/**
 * Select and highlight the correct land use based on parent
 */
function selectPuaLandUse(landUseCode) {
    // Map land use codes to element IDs
    const landUseMapping = {
        'RES': 'pua_land_use_residential',
        'COM': 'pua_land_use_commercial', 
        'IND': 'pua_land_use_industrial',
        'MIXED': 'pua_land_use_mixed'
    };
    
    // Reset all land use options to disabled/unselected state
    Object.values(landUseMapping).forEach(elementId => {
        const container = document.getElementById(elementId);
        const checkbox = container?.querySelector('input[type="checkbox"]');
        const label = container;
        
        if (container && checkbox) {
            checkbox.checked = false;
            checkbox.disabled = true;
            label.classList.remove('border-green-500', 'bg-green-50', 'border-blue-500', 'bg-blue-50', 'border-orange-500', 'bg-orange-50', 'border-purple-500', 'bg-purple-50');
            label.classList.add('border-gray-300', 'bg-gray-100', 'opacity-60', 'cursor-not-allowed');
        }
    });
    
    // Activate the selected land use
    const selectedElementId = landUseMapping[landUseCode];
    if (selectedElementId) {
        const container = document.getElementById(selectedElementId);
        const checkbox = container?.querySelector('input[type="checkbox"]');
        const label = container;
        
        if (container && checkbox) {
            checkbox.checked = true;
            checkbox.disabled = true; // Keep disabled since it's auto-selected
            
            // Remove default styling and add selected styling
            label.classList.remove('border-gray-300', 'bg-gray-100', 'opacity-60');
            label.classList.add('cursor-not-allowed'); // Keep cursor not allowed since it's locked
            
            // Add appropriate color based on land use
            switch(landUseCode) {
                case 'RES':
                    label.classList.add('border-green-500', 'bg-green-50');
                    break;
                case 'COM':
                    label.classList.add('border-blue-500', 'bg-blue-50');
                    break;
                case 'IND':
                    label.classList.add('border-orange-500', 'bg-orange-50');
                    break;
                case 'MIXED':
                    label.classList.add('border-purple-500', 'bg-purple-50');
                    break;
            }
        }
    }
    
    console.log('✅ Land use selected and locked:', landUseCode);
}

/**
 * Generate unit file number preview
 */
async function generateUnitFileNumberPreview(parentFileNumber) {
    try {
        const response = await fetch(`/api/st-file-numbers/units/${parentFileNumber}`);
        const data = await response.json();
        
        let nextUnitSequence = 1;
        if (data.success && data.data.length > 0) {
            // Find the highest unit sequence and add 1
            const maxSequence = Math.max(...data.data.map(unit => {
                const match = unit.fileno.match(/-(\d+)$/);
                return match ? parseInt(match[1]) : 0;
            }));
            nextUnitSequence = maxSequence + 1;
        }
        
        const paddedSequence = String(nextUnitSequence).padStart(3, '0');
        const unitFileNumber = `${parentFileNumber}-${paddedSequence}`;
        
        // Update the unit file number input
        const unitFileNoInput = document.getElementById('pua_unit_fileno');
        if (unitFileNoInput) {
            unitFileNoInput.value = unitFileNumber;
        }
        
        console.log('✅ Unit file number preview generated:', unitFileNumber);
        return unitFileNumber;
        
    } catch (error) {
        console.error('❌ Error generating unit file number preview:', error);
        // Fallback to simple increment
        const unitFileNoInput = document.getElementById('pua_unit_fileno');
        if (unitFileNoInput) {
            unitFileNoInput.value = `${parentFileNumber}-001`;
        }
        return `${parentFileNumber}-001`;
    }
}

/**
 * Enable PuA form fields after parent selection
 */
function enablePuaFormFields() {
    // Enable applicant type and related fields
    const fieldsToEnable = [
        'applicant_type',
        'applicant_title', 
        'first_name',
        'surname',
        'corporate_name',
        'rc_number'
    ];
    
    fieldsToEnable.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.disabled = false;
            element.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }
    });
    
    // Enable generate button
    const generateBtn = document.getElementById('generate-pua-btn');
    if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        generateBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
    }
    
    console.log('✅ PuA form fields enabled');
}

/**
 * Disable PuA form fields when no parent selected
 */  
function disablePuaFormFields() {
    // Disable applicant type and related fields
    const fieldsToDisable = [
        'applicant_type',
        'applicant_title',
        'first_name', 
        'surname',
        'corporate_name',
        'rc_number'
    ];
    
    fieldsToDisable.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.disabled = true;
            element.classList.add('bg-gray-100', 'cursor-not-allowed');
            element.value = '';
        }
    });
    
    // Disable generate button  
    const generateBtn = document.getElementById('generate-pua-btn');
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        generateBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
    }
    
    console.log('🔒 PuA form fields disabled');
}

/**
 * Reset PuA form to initial state
 */
function resetPuaForm() {
    // Reset parent selection
    const parentSelect = document.getElementById('pua_parent_file_number');
    const landUseFilter = document.getElementById('pua_land_use_filter');
    
    if (parentSelect) parentSelect.value = '';
    if (landUseFilter) landUseFilter.value = '';
    
    // Reset NP File Number display
    const npFileNoInput = document.getElementById('pua_np_fileno');
    if (npFileNoInput) {
        npFileNoInput.value = 'Select parent file number first';
        npFileNoInput.classList.remove('bg-green-100', 'text-green-700');
        npFileNoInput.classList.add('bg-blue-100', 'text-blue-700');
    }
    
    // Reset Unit File Number display
    const unitFileNoInput = document.getElementById('pua_unit_fileno');
    if (unitFileNoInput) {
        unitFileNoInput.value = 'Will be generated automatically';
        unitFileNoInput.classList.remove('bg-blue-100', 'text-blue-700');
        unitFileNoInput.classList.add('bg-green-100', 'text-green-700');
    }
    
    // Reset land use checkboxes to default disabled state
    const landUseIds = ['pua_land_use_residential', 'pua_land_use_commercial', 'pua_land_use_industrial', 'pua_land_use_mixed'];
    landUseIds.forEach(elementId => {
        const container = document.getElementById(elementId);
        const checkbox = container?.querySelector('input[type="checkbox"]');
        
        if (container && checkbox) {
            checkbox.checked = false;
            checkbox.disabled = true;
            
            // Reset to default gray styling
            container.classList.remove('border-green-500', 'bg-green-50', 'border-blue-500', 'bg-blue-50', 'border-orange-500', 'bg-orange-50', 'border-purple-500', 'bg-purple-50');
            container.classList.add('border-gray-300', 'bg-gray-100', 'opacity-60', 'cursor-not-allowed');
        }
    });
    
    // Hide parent details
    const parentDetails = document.getElementById('pua_parent_details');
    if (parentDetails) {
        parentDetails.classList.add('hidden');
    }
    
    // Hide buyer selection card and reset buyer fields
    const buyerCard = document.getElementById('pua_buyer_selection_card');
    if (buyerCard) {
        buyerCard.style.display = 'none';
    }
    
    const buyerSelect = document.getElementById('pua_buyer_select');
    if (buyerSelect) {
        buyerSelect.value = '';
        buyerSelect.innerHTML = '<option value="">-- No buyer selected (Manual entry) --</option>';
    }
    
    const buyerInfo = document.getElementById('pua_selected_buyer_info');
    if (buyerInfo) {
        buyerInfo.classList.add('hidden');
    }
    
    const buyerIdField = document.getElementById('pua_selected_buyer_id');
    if (buyerIdField) {
        buyerIdField.value = '';
    }
    
    // Reset Application Type
    const applicationTypeInput = document.getElementById('pua_application_type');
    if (applicationTypeInput) {
        applicationTypeInput.value = 'Select parent to inherit type';
        applicationTypeInput.classList.remove('bg-green-50', 'text-green-800');
        applicationTypeInput.classList.add('bg-purple-50', 'text-purple-800');
    }
    
    // Disable all other form fields
    disablePuaFormFields();

    // Clear the Location Details / map inherited from the parent
    if (window.STLocationMaps && window.STLocationMaps.pua) {
        window.STLocationMaps.pua.clear();
    }

    console.log('🔄 PuA form reset to initial state');
}

/**
 * Collect PuA applicant data from the form
 */
function collectPuAApplicantData() {
    // Determine which applicant type is selected (using actual form values)
    const individualRadio = document.querySelector('input[name="pua_applicant_type"][value="Individual"]');
    const corporateRadio = document.querySelector('input[name="pua_applicant_type"][value="Corporate"]');
    const multipleRadio = document.querySelector('input[name="pua_applicant_type"][value="Multiple"]');

    let applicantType = null;
    if (individualRadio && individualRadio.checked) {
        applicantType = 'individual';
    } else if (corporateRadio && corporateRadio.checked) {
        applicantType = 'corporate';
    } else if (multipleRadio && multipleRadio.checked) {
        applicantType = 'multiple';
    }

    if (!applicantType) {
        alert('Please select an applicant type');
        return null;
    }

    const data = {
        applicant_type: applicantType
    };

    /*
     | Applicant contact, for the ST commissioning SMS. This payload is
     | hand-built, so a new input on the blade sends NOTHING until it is named
     | here. phone_confirmed is set by public/js/sms-applicant-phone.js when the
     | officer has cleared the shared-number warning.
     */
    data.applicant_phone = document.getElementById('pua_applicant_phone')?.value || '';
    data.phone_confirmed = document.querySelector('#pua_applicant_phone ~ input[name="phone_confirmed"]')?.value === '1';

    // Collect data based on applicant type
    if (applicantType === 'individual') {
        data.applicant_title = document.getElementById('pua_title')?.value || '';
        data.first_name = document.getElementById('pua_first_name')?.value || '';
        data.middle_name = document.getElementById('pua_middle_name')?.value || '';
        data.surname = document.getElementById('pua_last_name')?.value || '';

        // Validate required fields for individual
        if (!data.first_name || !data.surname) {
            console.log('Individual validation failed:', { first_name: data.first_name, surname: data.surname });
            alert('Please fill in first name and last name for individual applicant');
            return null;
        }
    } else if (applicantType === 'corporate') {
        data.corporate_name = document.getElementById('pua_corporate_name')?.value || '';
        data.rc_number = document.getElementById('pua_rc_number')?.value || '';

        // Validate required fields for corporate
        if (!data.corporate_name) {
            console.log('Corporate validation failed:', { corporate_name: data.corporate_name });
            alert('Please fill in corporate name for corporate applicant');
            return null;
        }
    } else if (applicantType === 'multiple') {
        // For multiple applicants, we need at least the primary applicant info
        data.first_name = document.getElementById('pua_owner_first_name')?.value || '';
        data.middle_name = document.getElementById('pua_owner_middle_name')?.value || '';  
        data.surname = document.getElementById('pua_owner_last_name')?.value || '';

        // Validate required fields for multiple
        if (!data.first_name || !data.surname) {
            console.log('Multiple validation failed:', { first_name: data.first_name, surname: data.surname });
            alert('Please fill in first name and last name for primary owner');
            return null;
        }
    }

    console.log('Collected applicant data:', data);
    return data;
}

/**
 * Commission PuA file number
 */
async function commissionPuaFileNumber() {
    const puaUnitFileNo = document.getElementById('pua_unit_fileno');
    const puaParentSelect = document.querySelector('#pua_parent_file_number');
    
    // Check if parent file is selected
    if (!puaParentSelect || !puaParentSelect.value) {
        alert('Please select a parent file number first');
        return;
    }
    
    const selectedParent = puaParentSelect.value;
    
    // Extract parent details from the selected option
    const selectedOption = puaParentSelect.selectedOptions[0];
    if (!selectedOption) {
        alert('Please select a valid parent file number');
        return;
    }
    
    // For now, we'll extract land use and year from the parent file number
    // Format: ST-{LAND_USE}-{YEAR}-{SERIAL}
    const fileNumberParts = selectedParent.split('-');
    if (fileNumberParts.length < 4) {
        alert('Invalid parent file number format');
        return;
    }
    
    const landUse = fileNumberParts[1];
    const year = parseInt(fileNumberParts[2]);
    
    try {
        // Log the commission attempt
        console.log('Commissioning PuA file number for parent:', selectedParent);
        
        // Collect applicant data from the form
        const applicantData = collectPuAApplicantData();
        if (!applicantData) {
            alert('Please fill in the applicant information completely');
            return;
        }

        // Get selected buyer ID (if any)
        const buyerListId = document.getElementById('pua_selected_buyer_id')?.value || null;

        // Prepare the request payload
        const requestData = {
            parent_file_number: selectedParent,
            land_use: landUse,
            year: parseInt(year),
            buyer_list_id: buyerListId,
            ...applicantData,
            ...(window.STLocationMaps?.pua ? window.STLocationMaps.pua.getPayload() : {}),
            commissioned_by: document.getElementById('pua_commissioned_by')?.value || '',
            commissioned_date: document.getElementById('pua_commissioned_date')?.value || ''
        };

        console.log('Request payload:', requestData);
        console.log('Buyer List ID:', buyerListId);

        // Make API call to commission
        const response = await fetch('/commission-new-st/commission-pua', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            const fileNumber = result.unitFileNumber || result.data.unit_file_number;
            
            // Update the unit file number field to show the generated number
            if (puaUnitFileNo) {
                puaUnitFileNo.value = fileNumber;
                puaUnitFileNo.classList.remove('bg-green-100', 'text-green-700');
                puaUnitFileNo.classList.add('bg-blue-100', 'text-blue-700');
            }
            
            // Shared commissioning card (sua_commission.js): where the record went,
            // plus the EDMS scan folder created for the file.
            if (typeof showStCommissioningCard === 'function') {
                showStCommissioningCard(result, `PuA file number ${fileNumber} commissioned successfully!`);
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: `PuA file number ${fileNumber} commissioned successfully!`,
                    confirmButtonColor: '#10b981'
                });
            }
            
            // Disable the generate button after successful commission
            const generateBtn = document.getElementById('pua_generate_btn');
            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                generateBtn.classList.remove('bg-blue-400');
                generateBtn.innerHTML = '<i data-lucide="check" class="inline-block h-5 w-5 mr-2"></i>PuA File Number Generated';
            }
            
            // Optionally reload parent dropdown to reflect changes
            if (typeof loadAvailablePrimaryFileNumbers === 'function') {
                loadAvailablePrimaryFileNumbers();
            }
            
        } else {
            console.error('Commission failed:', result);
            
            // Handle validation errors specifically
            if (result.errors) {
                const errorMessages = Object.values(result.errors).flat();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: errorMessages.join('\n'),
                    confirmButtonColor: '#ef4444'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to commission PuA file number',
                    confirmButtonColor: '#ef4444'
                });
            }
        }
        
    } catch (error) {
        console.error('Error commissioning PuA file number:', error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Network error occurred while commissioning',
            confirmButtonColor: '#ef4444'
        });
    }
}

/**
 * Load buyers when parent file number is selected
 */
async function loadBuyersForParentFileNumber(parentFileNumber) {
    try {
        console.log('📊 Loading buyers for parent:', parentFileNumber);
        
        const response = await fetch(`/api/st-file-numbers/buyers/${parentFileNumber}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            populatePuaBuyerDropdown(data.data);
            // Show buyer selection card
            const buyerCard = document.getElementById('pua_buyer_selection_card');
            if (buyerCard) {
                buyerCard.style.display = 'block';
            }
        } else {
            // Hide buyer card if no buyers available
            const buyerCard = document.getElementById('pua_buyer_selection_card');
            if (buyerCard) {
                buyerCard.style.display = 'none';
            }
        }
        
    } catch (error) {
        console.error('❌ Error loading buyers:', error);
        // Hide buyer card on error
        const buyerCard = document.getElementById('pua_buyer_selection_card');
        if (buyerCard) {
            buyerCard.style.display = 'none';
        }
    }
}

/**
 * Populate buyer dropdown
 */
function populatePuaBuyerDropdown(buyers) {
    const dropdown = document.getElementById('pua_buyer_select');
    if (!dropdown) return;
    
    dropdown.innerHTML = '<option value="">-- No buyer selected (Manual entry) --</option>';
    
    if (!buyers || buyers.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No buyers available for this parent application';
        option.disabled = true;
        dropdown.appendChild(option);
        return;
    }
    
    buyers.forEach(buyer => {
        const option = document.createElement('option');
        option.value = buyer.buyer_id;
        option.textContent = `${buyer.buyer_name} (Unit: ${buyer.unit_no})`;
        option.dataset.buyerTitle = buyer.buyer_title || '';
        option.dataset.buyerName = buyer.buyer_name || '';
        option.dataset.unitNo = buyer.unit_no || '';
        option.dataset.landUse = buyer.land_use || '';
        option.dataset.measurement = buyer.measurement || '';
        dropdown.appendChild(option);
    });
    
    console.log('✅ Populated buyer dropdown with', buyers.length, 'buyers');
}

/**
 * Handle buyer selection
 */
function handlePuaBuyerSelection(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const buyerId = selectElement.value;
    
    // Store selected buyer ID
    const buyerIdField = document.getElementById('pua_selected_buyer_id');
    if (buyerIdField) {
        buyerIdField.value = buyerId;
    }
    
    const buyerInfo = document.getElementById('pua_selected_buyer_info');
    
    if (buyerId && buyerInfo) {
        // Show buyer info
        buyerInfo.classList.remove('hidden');
        
        // Update display fields
        const nameDisplay = document.getElementById('pua_buyer_name_display');
        const unitDisplay = document.getElementById('pua_buyer_unit_display');
        const landuseDisplay = document.getElementById('pua_buyer_landuse_display');
        const measurementDisplay = document.getElementById('pua_buyer_measurement_display');
        
        if (nameDisplay) nameDisplay.textContent = selectedOption.dataset.buyerName || '-';
        if (unitDisplay) unitDisplay.textContent = selectedOption.dataset.unitNo || '-';
        if (landuseDisplay) landuseDisplay.textContent = selectedOption.dataset.landUse || '-';
        if (measurementDisplay) measurementDisplay.textContent = selectedOption.dataset.measurement || 'N/A';
        
        // Auto-fill applicant fields
        autofillPuaApplicantFields(selectedOption.dataset);
    } else if (buyerInfo) {
        // Hide buyer info
        buyerInfo.classList.add('hidden');
        // Clear applicant fields and re-enable Individual fields
        clearPuaApplicantFields();
    }
}

/**
 * Auto-fill applicant fields from buyer data
 */
function autofillPuaApplicantFields(buyerData) {
    // Get the selected applicant type
    const selectedApplicantType = document.querySelector('input[name="pua_applicant_type"]:checked');
    if (!selectedApplicantType) {
        console.log('No applicant type selected');
        return;
    }
    
    const applicantType = selectedApplicantType.value;
    console.log('Auto-filling for applicant type:', applicantType);
    console.log('Buyer data:', buyerData);
    
    if (applicantType === 'Individual') {
        autofillIndividualFields(buyerData);
    } else if (applicantType === 'Corporate') {
        autofillCorporateFields(buyerData);
    } else if (applicantType === 'Multiple') {
        autofillMultipleOwnersFields(buyerData);
    }
}

/**
 * Auto-fill fields for Individual applicant type
 */
function autofillIndividualFields(buyerData) {
    // Parse buyer_name which might include title (e.g., "Mr. JOHN MICHAEL DOE")
    if (buyerData.buyerName) {
        const parsedName = parseFullNameWithTitle(buyerData.buyerName, buyerData.buyerTitle);
        
        // Fill title
        const titleSelect = document.getElementById('pua_title');
        if (titleSelect && parsedName.title) {
            // Try to match with available options
            for (let option of titleSelect.options) {
                if (option.value.toLowerCase() === parsedName.title.toLowerCase()) {
                    titleSelect.value = option.value;
                    break;
                }
            }
        }
        
        // Fill first name
        const firstNameField = document.getElementById('pua_first_name');
        if (firstNameField && parsedName.firstName) {
            firstNameField.value = parsedName.firstName;
        }
        
        // Fill middle name
        const middleNameField = document.getElementById('pua_middle_name');
        if (middleNameField && parsedName.middleName) {
            middleNameField.value = parsedName.middleName;
        }
        
        // Fill last name
        const lastNameField = document.getElementById('pua_last_name');
        if (lastNameField && parsedName.lastName) {
            lastNameField.value = parsedName.lastName;
        }
        
        // Disable and grey out Individual fields when buyer is selected
        disableIndividualFields(true);
        
        console.log('Individual fields filled and disabled:', parsedName);
    }
}

/**
 * Auto-fill fields for Corporate applicant type
 */
function autofillCorporateFields(buyerData) {
    // For corporate, use the full buyer_name as company name
    const corporateNameField = document.getElementById('pua_corporate_name');
    if (corporateNameField && buyerData.buyerName) {
        corporateNameField.value = buyerData.buyerName;
        console.log('Corporate name filled:', buyerData.buyerName);
    }
    
    // RC Number would typically come from additional buyer data if available
    // For now, we'll leave it empty as it's not part of the current buyer_name format
}

/**
 * Auto-fill fields for Multiple Owners applicant type
 */
function autofillMultipleOwnersFields(buyerData) {
    // Fill the Primary Owner (Owner 1) fields
    if (buyerData.buyerName) {
        const parsedName = parseFullNameWithTitle(buyerData.buyerName, buyerData.buyerTitle);
        
        // Fill primary owner title
        const ownerTitleSelect = document.getElementById('pua_owner_title');
        if (ownerTitleSelect && parsedName.title) {
            // Try to match with available options
            for (let option of ownerTitleSelect.options) {
                if (option.value.toLowerCase() === parsedName.title.toLowerCase()) {
                    ownerTitleSelect.value = option.value;
                    break;
                }
            }
        }
        
        // Fill primary owner first name
        const ownerFirstNameField = document.getElementById('pua_owner_first_name');
        if (ownerFirstNameField && parsedName.firstName) {
            ownerFirstNameField.value = parsedName.firstName;
        }
        
        // Fill primary owner middle name
        const ownerMiddleNameField = document.getElementById('pua_owner_middle_name');
        if (ownerMiddleNameField && parsedName.middleName) {
            ownerMiddleNameField.value = parsedName.middleName;
        }
        
        // Fill primary owner last name
        const ownerLastNameField = document.getElementById('pua_owner_last_name');
        if (ownerLastNameField && parsedName.lastName) {
            ownerLastNameField.value = parsedName.lastName;
        }
        
        console.log('Multiple owners primary owner fields filled:', parsedName);
    }
}

/**
 * Parse full name with title into components
 * Handles formats like:
 * - "Mr. JOHN MICHAEL DOE"
 * - "JOHN MICHAEL DOE" (with separate buyer_title)
 * - "Mrs. JANE DOE"
 * - "COMPANY NAME LIMITED"
 */
function parseFullNameWithTitle(fullName, separateTitle = null) {
    if (!fullName) {
        return { title: '', firstName: '', middleName: '', lastName: '' };
    }
    
    let title = separateTitle || '';
    let nameWithoutTitle = fullName.trim();
    
    // Common titles to look for at the beginning
    const titlePrefixes = ['Mr.', 'Mrs.', 'Miss.', 'Ms.', 'Dr.', 'Prof.', 'Eng.', 'Arch.', 'Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof', 'Eng', 'Arch'];
    
    // Check if the name starts with a title
    for (let titlePrefix of titlePrefixes) {
        const regex = new RegExp(`^${titlePrefix.replace('.', '\\.')}\\s+`, 'i');
        if (regex.test(nameWithoutTitle)) {
            title = titlePrefix.replace('.', ''); // Remove dot for consistency
            nameWithoutTitle = nameWithoutTitle.replace(regex, '');
            break;
        }
    }
    
    // Split remaining name into parts
    const nameParts = nameWithoutTitle.trim().split(/\s+/).filter(part => part.length > 0);
    
    let firstName = '';
    let middleName = '';
    let lastName = '';
    
    if (nameParts.length === 1) {
        // Only one name part
        firstName = nameParts[0];
    } else if (nameParts.length === 2) {
        // First and last name
        firstName = nameParts[0];
        lastName = nameParts[1];
    } else if (nameParts.length >= 3) {
        // First, middle, and last name
        firstName = nameParts[0];
        // All middle parts (in case there are multiple middle names)
        middleName = nameParts.slice(1, -1).join(' ');
        lastName = nameParts[nameParts.length - 1];
    }
    
    return {
        title: title,
        firstName: firstName,
        middleName: middleName,
        lastName: lastName
    };
}

/**
 * Disable or enable Individual fields based on buyer selection
 */
function disableIndividualFields(disable = true) {
    const individualFields = ['pua_title', 'pua_first_name', 'pua_middle_name', 'pua_last_name'];
    
    individualFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            if (disable) {
                // Disable field and add disabled styling
                field.disabled = true;
                field.style.backgroundColor = '#f3f4f6'; // Light gray background
                field.style.color = '#6b7280'; // Gray text
                field.style.cursor = 'not-allowed';
                field.style.opacity = '0.6';
                field.setAttribute('readonly', 'readonly');
                
                // Add disabled class for consistent styling
                field.classList.add('pua-field-disabled');
            } else {
                // Enable field and restore normal styling
                field.disabled = false;
                field.style.backgroundColor = '';
                field.style.color = '';
                field.style.cursor = '';
                field.style.opacity = '';
                field.removeAttribute('readonly');
                
                // Remove disabled class
                field.classList.remove('pua-field-disabled');
            }
        }
    });
    
    console.log('Individual fields', disable ? 'disabled' : 'enabled');
}

/**
 * Clear applicant fields for all applicant types
 */
function clearPuaApplicantFields() {
    // Individual fields
    const individualFields = ['pua_title', 'pua_first_name', 'pua_middle_name', 'pua_last_name'];
    individualFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        }
    });
    
    // Re-enable Individual fields when clearing
    disableIndividualFields(false);
    
    // Corporate fields
    const corporateFields = ['pua_corporate_name', 'pua_rc_number'];
    corporateFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) field.value = '';
    });
    
    // Multiple owners fields (primary owner)
    const multipleFields = ['pua_owner_title', 'pua_owner_first_name', 'pua_owner_middle_name', 'pua_owner_last_name'];
    multipleFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        }
    });
    
    console.log('All PuA applicant fields cleared and Individual fields re-enabled');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.puaManager = new PuAApplicationsManager();
    
    // Initialize PuA form in disabled state
    resetPuaForm();
    
    // Load initial PRIMARY file numbers (all land uses)
    loadPrimaryFileNumbers();
    
    console.log('🚀 PuA file number system initialized with parent selection');
});