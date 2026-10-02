/**
 * Primary Applications JavaScript Module
 * Handles Primary ST applications management functionality
 */

class PrimaryApplicationsManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalItems = 0;
        this.applications = [];
        this.filters = {
            search: '',
            status: '',
            landUse: '',
            dateRange: ''
        };
        
        this.init();
    }

    init() {
        console.log('Initializing Primary Applications Manager');
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Search functionality
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('primary-search');
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
            const response = await fetch('/commission-new-st/primary-data?' + new URLSearchParams({
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
                console.error('Failed to load applications:', data.message);
                this.showError('Failed to load primary applications');
            }
        } catch (error) {
            console.error('Error loading applications:', error);
            this.showError('Error loading primary applications');
        }
    }

    async loadStatistics() {
        try {
            const response = await fetch('/commission-new-st/primary-data?stats=true', {
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
            console.error('Error loading statistics:', error);
        }
    }

    renderTable() {
        const tbody = document.getElementById('primary-applications-tbody');
        if (!tbody) return;

        if (this.applications.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-folder-open text-4xl text-gray-300 mb-2"></i>
                            <span>No primary applications found</span>
                            <button type="button" 
                                    class="mt-2 text-blue-600 hover:text-blue-500 text-sm font-medium"
                                    onclick="primaryManager.loadApplications()">
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
                    ${app.file_number || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatApplicantName(app)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatPropertyLocation(app)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getLandUseColorClass(app.land_use)}">
                        ${app.land_use || 'N/A'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColorClass(app.status)}">
                        ${this.formatStatus(app.status)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatDate(app.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                        <button type="button" 
                                class="text-blue-600 hover:text-blue-900 text-sm"
                                onclick="primaryManager.viewApplication(${app.id})">
                            View
                        </button>
                        <button type="button" 
                                class="text-green-600 hover:text-green-900 text-sm"
                                onclick="primaryManager.editApplication(${app.id})">
                            Edit
                        </button>
                        <button type="button" 
                                class="text-red-600 hover:text-red-900 text-sm"
                                onclick="primaryManager.deleteApplication(${app.id})">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderPagination() {
        const paginationContainer = document.getElementById('primary-pagination');
        if (!paginationContainer) return;

        const totalPages = Math.ceil(this.totalItems / this.itemsPerPage);
        
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let paginationHTML = '<div class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">';
        paginationHTML += '<div class="flex justify-between flex-1 sm:hidden">';
        
        // Previous button (mobile)
        if (this.currentPage > 1) {
            paginationHTML += `<button onclick="primaryManager.goToPage(${this.currentPage - 1})" 
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                              </button>`;
        }
        
        // Next button (mobile)
        if (this.currentPage < totalPages) {
            paginationHTML += `<button onclick="primaryManager.goToPage(${this.currentPage + 1})" 
                                class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                              </button>`;
        }
        
        paginationHTML += '</div>';
        
        // Desktop pagination
        paginationHTML += '<div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">';
        paginationHTML += `<div><p class="text-sm text-gray-700">
                            Showing <span class="font-medium">${((this.currentPage - 1) * this.itemsPerPage) + 1}</span>
                            to <span class="font-medium">${Math.min(this.currentPage * this.itemsPerPage, this.totalItems)}</span>
                            of <span class="font-medium">${this.totalItems}</span> results
                          </p></div>`;
        
        paginationHTML += '<div><nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">';
        
        // Previous button (desktop)
        paginationHTML += `<button onclick="primaryManager.goToPage(${this.currentPage - 1})" 
                            ${this.currentPage <= 1 ? 'disabled' : ''}
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${this.currentPage <= 1 ? 'cursor-not-allowed opacity-50' : ''}">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                          </button>`;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === this.currentPage) {
                paginationHTML += `<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                                    ${i}
                                  </span>`;
            } else {
                paginationHTML += `<button onclick="primaryManager.goToPage(${i})" 
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    ${i}
                                  </button>`;
            }
        }
        
        // Next button (desktop)
        paginationHTML += `<button onclick="primaryManager.goToPage(${this.currentPage + 1})" 
                            ${this.currentPage >= totalPages ? 'disabled' : ''}
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${this.currentPage >= totalPages ? 'cursor-not-allowed opacity-50' : ''}">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                          </button>`;
        
        paginationHTML += '</nav></div></div></div>';
        
        paginationContainer.innerHTML = paginationHTML;
    }

    goToPage(page) {
        if (page >= 1 && page <= Math.ceil(this.totalItems / this.itemsPerPage)) {
            this.currentPage = page;
            this.loadApplications();
        }
    }

    updateStatistics(stats) {
        const elements = {
            'total-primary-count': stats.total || 0,
            'pending-primary-count': stats.pending || 0,
            'approved-primary-count': stats.approved || 0,
            'rejected-primary-count': stats.rejected || 0
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // Utility methods
    formatApplicantName(app) {
        const parts = [app.applicant_title, app.first_name, app.surname].filter(Boolean);
        return parts.length > 0 ? parts.join(' ') : 'N/A';
    }

    formatPropertyLocation(app) {
        const parts = [app.property_house_no, app.property_street_name, app.property_lga].filter(Boolean);
        return parts.length > 0 ? parts.join(', ') : 'N/A';
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

    getLandUseColorClass(landUse) {
        const colorMap = {
            'COMMERCIAL': 'bg-blue-100 text-blue-800',
            'RESIDENTIAL': 'bg-green-100 text-green-800',
            'INDUSTRIAL': 'bg-gray-100 text-gray-800',
            'MIXED': 'bg-purple-100 text-purple-800'
        };
        return colorMap[landUse] || 'bg-gray-100 text-gray-800';
    }

    getStatusColorClass(status) {
        const colorMap = {
            'draft': 'bg-gray-100 text-gray-800',
            'submitted': 'bg-blue-100 text-blue-800',
            'under_review': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-green-100 text-green-800',
            'rejected': 'bg-red-100 text-red-800'
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
        console.log('Viewing primary application:', id);
        // Implement view functionality
    }

    editApplication(id) {
        console.log('Editing primary application:', id);
        // Implement edit functionality
    }

    deleteApplication(id) {
        console.log('Deleting primary application:', id);
        // Implement delete functionality with confirmation
    }
}

// Global functions for HTML onclick handlers
function refreshPrimaryData() {
    if (window.primaryManager) {
        window.primaryManager.loadInitialData();
    }
}

function createNewPrimary() {
    console.log('Creating new primary application');
    // Implement create functionality
}

function filterPrimaryApplications(searchTerm) {
    if (window.primaryManager) {
        window.primaryManager.filters.search = searchTerm;
        window.primaryManager.filterAndReload();
    }
}

function filterPrimaryByStatus(status) {
    if (window.primaryManager) {
        window.primaryManager.filters.status = status;
        window.primaryManager.filterAndReload();
    }
}

function filterPrimaryByLandUse(landUse) {
    if (window.primaryManager) {
        window.primaryManager.filters.landUse = landUse;
        window.primaryManager.filterAndReload();
    }
}

function filterPrimaryByDate(dateRange) {
    if (window.primaryManager) {
        window.primaryManager.filters.dateRange = dateRange;
        window.primaryManager.filterAndReload();
    }
}

function loadPrimaryApplications() {
    if (window.primaryManager) {
        window.primaryManager.loadApplications();
    }
}

/**
 * Handle Primary Application Type Change
 */
function handlePrimaryApplicationTypeChange(radioElement) {
    console.log('Primary Application Type changed to:', radioElement.value);
    
    // Remove 'selected' class from all application type options
    document.querySelectorAll('.application-type-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add 'selected' class to parent label
    const label = radioElement.closest('.application-type-option');
    if (label) {
        label.classList.add('selected');
    }
    
    // Store value for later use
    console.log('✅ Application Type set to:', radioElement.value);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.primaryManager = new PrimaryApplicationsManager();
    
    // Initialize application type selection
    const selectedAppType = document.querySelector('input[name="application_type"]:checked');
    if (selectedAppType) {
        const label = selectedAppType.closest('.application-type-option');
        if (label) {
            label.classList.add('selected');
        }
    }
});