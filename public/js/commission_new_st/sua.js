// SuA (Standalone Unit Application) JavaScript Module for Commission Interface

// Handle applicant type changes for SuA
function handleSuaApplicantTypeChange(type) {
    console.log('SuA Applicant type changed to:', type);
    
    const detailsDiv = document.getElementById('sua_applicant_details');
    if (detailsDiv) {
        if (type) {
            detailsDiv.style.display = 'block';
        } else {
            detailsDiv.style.display = 'none';
        }
    }
}
        this.applications = [];
        this.filters = {
            search: '',
            primaryFileNo: '',
            unitType: '',
            status: '',
            dateRange: ''
        };
        
        this.init();
    }

    init() {
        console.log('Initializing SuA Applications Manager');
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Search functionality
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('sua-search');
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
        this.loadPrimaryFiles();
    }

    async loadApplications() {
        try {
            const response = await fetch('/commission-new-st/sua-data?' + new URLSearchParams({
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
                console.error('Failed to load SuA applications:', data.message);
                this.showError('Failed to load sub applications');
            }
        } catch (error) {
            console.error('Error loading SuA applications:', error);
            this.showError('Error loading sub applications');
        }
    }

    async loadStatistics() {
        try {
            const response = await fetch('/commission-new-st/sua-data?stats=true', {
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
            console.error('Error loading SuA statistics:', error);
        }
    }

    async loadPrimaryFiles() {
        try {
            const response = await fetch('/commission-new-st/primary-data?list=true', {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.populatePrimaryFileSelect(data.data.files || []);
            }
        } catch (error) {
            console.error('Error loading primary files:', error);
        }
    }

    populatePrimaryFileSelect(files) {
        const select = document.getElementById('sua-primary-fileno');
        if (!select) return;

        // Clear existing options (except first one)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        files.forEach(file => {
            const option = document.createElement('option');
            option.value = file.file_number;
            option.textContent = `${file.file_number} - ${file.applicant_name || 'Unknown'}`;
            select.appendChild(option);
        });
    }

    renderTable() {
        const tbody = document.getElementById('sua-applications-tbody');
        if (!tbody) return;

        if (this.applications.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-building text-4xl text-gray-300 mb-2"></i>
                            <span>No sub applications found</span>
                            <button type="button" 
                                    class="mt-2 text-green-600 hover:text-green-500 text-sm font-medium"
                                    onclick="suaManager.loadApplications()">
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
                    ${app.unit_number || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="text-blue-600 font-medium">${app.primary_file_number || 'N/A'}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${this.formatApplicantName(app)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getUnitTypeColorClass(app.unit_type)}">
                        ${this.formatUnitType(app.unit_type)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${app.floor_area ? app.floor_area + ' m²' : 'N/A'}
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
                                class="text-green-600 hover:text-green-900 text-sm"
                                onclick="suaManager.viewApplication(${app.id})">
                            View
                        </button>
                        <button type="button" 
                                class="text-blue-600 hover:text-blue-900 text-sm"
                                onclick="suaManager.editApplication(${app.id})">
                            Edit
                        </button>
                        <button type="button" 
                                class="text-red-600 hover:text-red-900 text-sm"
                                onclick="suaManager.deleteApplication(${app.id})">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    updateStatistics(stats) {
        const elements = {
            'total-sua-count': stats.total || 0,
            'pending-sua-count': stats.pending || 0,
            'approved-sua-count': stats.approved || 0,
            'rejected-sua-count': stats.rejected || 0,
            'linked-units-count': stats.linked_units || 0
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

    formatUnitType(unitType) {
        if (!unitType) return 'N/A';
        return unitType.replace('_', ' ').toUpperCase();
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

    getUnitTypeColorClass(unitType) {
        const colorMap = {
            'residential': 'bg-green-100 text-green-800',
            'commercial': 'bg-blue-100 text-blue-800',
            'parking': 'bg-gray-100 text-gray-800',
            'storage': 'bg-yellow-100 text-yellow-800',
            'common_area': 'bg-purple-100 text-purple-800'
        };
        return colorMap[unitType] || 'bg-gray-100 text-gray-800';
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
        console.log('Viewing SuA application:', id);
        // Implement view functionality
    }

    editApplication(id) {
        console.log('Editing SuA application:', id);
        // Implement edit functionality
    }

    deleteApplication(id) {
        console.log('Deleting SuA application:', id);
        // Implement delete functionality with confirmation
    }
}

// Global functions for HTML onclick handlers
function refreshSuAData() {
    if (window.suaManager) {
        window.suaManager.loadInitialData();
    }
}

function createNewSuA() {
    console.log('Creating new SuA application');
    // Implement create functionality
}

function filterSuAApplications(searchTerm) {
    if (window.suaManager) {
        window.suaManager.filters.search = searchTerm;
        window.suaManager.filterAndReload();
    }
}

function filterSuAByPrimary(primaryFileNo) {
    if (window.suaManager) {
        window.suaManager.filters.primaryFileNo = primaryFileNo;
        window.suaManager.filterAndReload();
    }
}

function filterSuAByUnitType(unitType) {
    if (window.suaManager) {
        window.suaManager.filters.unitType = unitType;
        window.suaManager.filterAndReload();
    }
}

function filterSuAByStatus(status) {
    if (window.suaManager) {
        window.suaManager.filters.status = status;
        window.suaManager.filterAndReload();
    }
}

function filterSuAByDate(dateRange) {
    if (window.suaManager) {
        window.suaManager.filters.dateRange = dateRange;
        window.suaManager.filterAndReload();
    }
}

function loadSuAApplications() {
    if (window.suaManager) {
        window.suaManager.loadApplications();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.suaManager = new SuAApplicationsManager();
});