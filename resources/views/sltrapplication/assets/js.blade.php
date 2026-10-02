<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Mock data for applications
    const mockApplications = [
        {
            id: "SLTR-2023-001",
            applicant: "John Doe",
            type: "residential",
            location: "Garki District, Abuja",
            date: "2023-06-15",
            status: "pending"
        },
        {
            id: "SLTR-2023-002",
            applicant: "ABC Corporation",
            type: "commercial",
            location: "Central Business District, Abuja",
            date: "2023-06-14",
            status: "approved"
        },
        {
            id: "SLTR-2023-003",
            applicant: "XYZ Logistics Ltd",
            type: "warehouse",
            location: "Idu Industrial Area, Abuja",
            date: "2023-06-13",
            status: "pending"
        },
        {
            id: "SLTR-2023-004",
            applicant: "Ministry of Agriculture",
            type: "agriculture",
            location: "Gwagwalada Area Council",
            date: "2023-06-12",
            status: "rejected"
        },
        {
            id: "SLTR-2023-005",
            applicant: "Jane Smith",
            type: "residential",
            location: "Wuse II, Abuja",
            date: "2023-06-11",
            status: "approved"
        }
    ];

    // State management
    let currentTab = 'all';
    let searchQuery = '';
    let typeFilter = 'all';
    let sortFilter = 'newest';

    // DOM elements
    const elements = {
        dashboardView: document.getElementById('dashboard-view'),
        searchInput: document.getElementById('search-input'),
        typeFilter: document.getElementById('type-filter'),
        sortFilter: document.getElementById('sort-filter'),
        exportBtn: document.getElementById('export-btn'),
       
        applicationDropdown: document.getElementById('application-dropdown'),
        applicationFormModal: document.getElementById('application-form-modal'),
        formTitle: document.getElementById('form-title'),
        closeFormBtn: document.getElementById('close-form-btn'),
        tabTriggers: document.querySelectorAll('.tab-trigger'),
        tabContents: document.querySelectorAll('.tab-content'),
        allApplicationsTable: document.getElementById('all-applications-table'),
        pendingApplicationsTable: document.getElementById('pending-applications-table'),
        approvedApplicationsTable: document.getElementById('approved-applications-table'),
        rejectedApplicationsTable: document.getElementById('rejected-applications-table')
    };

    // Helper functions
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'approved': 
                return 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-green-600 text-white';
            case 'rejected': 
                return 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-red-600 text-white';
            case 'pending': 
                return 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-blue-600 text-white';
            default: 
                return 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-gray-200 border border-gray-200 text-gray-700';
        }
    }

    function getTypeIcon(type) {
        const icons = {
            residential: 'home',
            commercial: 'building',
            warehouse: 'warehouse',
            agriculture: 'tractor'
        };
        return icons[type] || 'home';
    }

    function getTypeColor(type) {
        const colors = {
            residential: 'text-blue-500',
            commercial: 'text-purple-500',
            warehouse: 'text-amber-500',
            agriculture: 'text-green-500'
        };
        return colors[type] || 'text-gray-500';
    }

    function filterApplications(status) {
        let filtered = mockApplications;

        // Filter by status
        if (status !== 'all') {
            filtered = filtered.filter(app => app.status === status);
        }

        // Filter by type
        if (typeFilter !== 'all') {
            filtered = filtered.filter(app => app.type === typeFilter);
        }

        // Filter by search query
        if (searchQuery) {
            filtered = filtered.filter(app =>
                app.applicant.toLowerCase().includes(searchQuery.toLowerCase()) ||
                app.id.toLowerCase().includes(searchQuery.toLowerCase()) ||
                app.location.toLowerCase().includes(searchQuery.toLowerCase())
            );
        }

        // Sort applications
        filtered.sort((a, b) => {
            switch (sortFilter) {
                case 'newest':
                    return new Date(b.date) - new Date(a.date);
                case 'oldest':
                    return new Date(a.date) - new Date(b.date);
                case 'name':
                    return a.applicant.localeCompare(b.applicant);
                case 'status':
                    return a.status.localeCompare(b.status);
                default:
                    return 0;
            }
        });

        return filtered;
    }

    function renderApplicationsTable(tableElement, status) {
        const applications = filterApplications(status);
        tableElement.innerHTML = '';

        if (applications.length === 0) {
            tableElement.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">No applications found</td>
                </tr>
            `;
            return;
        }

        applications.forEach(app => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50/30 transition-colors';
            row.innerHTML = `
                <td class="px-3 py-3 border-b border-gray-200 font-medium">${app.id}</td>
                <td class="px-3 py-3 border-b border-gray-200">${app.applicant}</td>
                <td class="px-3 py-3 border-b border-gray-200">
                    <div class="flex items-center gap-1">
                        <i data-lucide="${getTypeIcon(app.type)}" class="h-4 w-4 ${getTypeColor(app.type)}"></i>
                        <span class="capitalize">${app.type}</span>
                    </div>
                </td>
                <td class="px-3 py-3 border-b border-gray-200">${app.location}</td>
                <td class="px-3 py-3 border-b border-gray-200">${app.date}</td>
                <td class="px-3 py-3 border-b border-gray-200">
                    <span class="${getStatusBadgeClass(app.status)}">
                        ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                    </span>
                </td>
                <td class="px-3 py-3 border-b border-gray-200 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <button class="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-gray-700 hover:bg-gray-100 transition-colors" onclick="viewApplication('${app.id}')">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                        </button>
                        <button class="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-gray-700 hover:bg-gray-100 transition-colors" onclick="editApplication('${app.id}')">
                            <i data-lucide="edit" class="h-4 w-4"></i>
                        </button>
                        ${app.status === 'pending' ? `
                            <button class="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-gray-700 hover:bg-gray-100 transition-colors" onclick="approveApplication('${app.id}')">
                                <i data-lucide="check-circle" class="h-4 w-4"></i>
                            </button>
                            <button class="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-gray-700 hover:bg-gray-100 transition-colors" onclick="rejectApplication('${app.id}')">
                                <i data-lucide="x-circle" class="h-4 w-4"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tableElement.appendChild(row);
        });

        // Re-initialize Lucide icons
        lucide.createIcons();
    }

    function renderAllTables() {
        renderApplicationsTable(elements.allApplicationsTable, 'all');
        renderApplicationsTable(elements.pendingApplicationsTable, 'pending');
        renderApplicationsTable(elements.approvedApplicationsTable, 'approved');
        renderApplicationsTable(elements.rejectedApplicationsTable, 'rejected');
    }

    function switchTab(tabName) {
        currentTab = tabName;
        
        // Update tab triggers
        elements.tabTriggers.forEach(trigger => {
            trigger.classList.remove('bg-white', 'text-primary', 'shadow-sm');
            trigger.classList.add('bg-transparent', 'text-muted-foreground', 'hover:text-gray-900');
            if (trigger.dataset.tab === tabName) {
                trigger.classList.remove('bg-transparent', 'text-muted-foreground', 'hover:text-gray-900');
                trigger.classList.add('bg-white', 'text-primary', 'shadow-sm');
            }
        });

        // Update tab contents
        elements.tabContents.forEach(content => {
            content.classList.remove('block');
            content.classList.add('hidden');
            if (content.id === `${tabName}-tab`) {
                content.classList.remove('hidden');
                content.classList.add('block');
            }
        });
    }

    function openApplicationForm(type) {
        const typeNames = {
            residential: 'Residential',
            commercial: 'Commercial',
            warehouse: 'Warehouse',
            agriculture: 'Agriculture'
        };
        
        elements.formTitle.textContent = `New ${typeNames[type]} SLTR Application`;
        elements.applicationFormModal.classList.add('show');
        elements.applicationDropdown.classList.remove('show');
    }

    function closeApplicationForm() {
        elements.applicationFormModal.classList.remove('show');
    }

    // Global functions for onclick handlers
    window.viewApplication = function(id) {
        alert(`Viewing application: ${id}`);
    };

    window.editApplication = function(id) {
        alert(`Editing application: ${id}`);
    };

    window.approveApplication = function(id) {
        if (confirm(`Approve application ${id}?`)) {
            alert(`Application ${id} approved`);
            // Update application status and re-render tables
            const app = mockApplications.find(a => a.id === id);
            if (app) app.status = 'approved';
            renderAllTables();
        }
    };

    window.rejectApplication = function(id) {
        if (confirm(`Reject application ${id}?`)) {
            alert(`Application ${id} rejected`);
            // Update application status and re-render tables
            const app = mockApplications.find(a => a.id === id);
            if (app) app.status = 'rejected';
            renderAllTables();
        }
    };

    // Event listeners
    elements.searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        renderAllTables();
    });

    elements.typeFilter.addEventListener('change', (e) => {
        typeFilter = e.target.value;
        renderAllTables();
    });

    elements.sortFilter.addEventListener('change', (e) => {
        sortFilter = e.target.value;
        renderAllTables();
    });

    elements.exportBtn.addEventListener('click', () => {
        alert('Exporting applications data...');
    });

 

    elements.closeFormBtn.addEventListener('click', closeApplicationForm);

    elements.applicationFormModal.addEventListener('click', (e) => {
        if (e.target === elements.applicationFormModal) {
            closeApplicationForm();
        }
    });

    // Fixed dropdown item handlers
    document.addEventListener('DOMContentLoaded', () => {
        const dropdownButtons = document.querySelectorAll('#application-dropdown button');
        dropdownButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const type = button.dataset.type;
                openApplicationForm(type);
                elements.applicationDropdown.classList.remove('show');
            });
        });
    });

    // Tab triggers
    elements.tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            switchTab(trigger.dataset.tab);
        });
    });



    // Initialize the page
    function init() {
        renderAllTables();
        lucide.createIcons();
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', init);
</script>