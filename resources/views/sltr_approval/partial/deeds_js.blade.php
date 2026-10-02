<script>
    // Initialize Lucide icons
    lucide.createIcons();
    
    // Mock data for deeds department applications
    const applications = [
        {
            id: "SLTR-APP-001",
            arn: "ARN-RES-2023-01-123456",
            applicantName: "John Doe",
            fileNumber: "SLTR-RES-2023-01",
            propertyType: "Residential",
            location: "Garki District",
            submissionDate: "2023-05-15",
            status: "pending",
            currentDepartment: "deeds",
            approvalSteps: [
                { department: "lands", status: "approved", date: "2023-05-20" },
                { department: "deeds", status: "pending", date: null },
            ],
        },
        {
            id: "SLTR-APP-002",
            arn: "ARN-COM-2023-01-789012",
            applicantName: "ABC Corporation",
            fileNumber: "SLTR-COM-2023-01",
            propertyType: "Commercial",
            location: "Central Business District",
            submissionDate: "2023-05-10",
            status: "in_progress",
            currentDepartment: "deeds",
            approvalSteps: [
                { department: "lands", status: "approved", date: "2023-05-12" },
                { department: "deeds", status: "in_progress", date: null },
            ],
        },
        {
            id: "SLTR-APP-003",
            arn: "ARN-RES-2023-02-246810",
            applicantName: "Jane Smith",
            fileNumber: "SLTR-RES-2023-02",
            propertyType: "Residential",
            location: "Maitama District",
            submissionDate: "2023-06-01",
            status: "approved",
            currentDepartment: "survey",
            approvalSteps: [
                { department: "lands", status: "approved", date: "2023-06-05" },
                { department: "deeds", status: "approved", date: "2023-06-10" },
                { department: "survey", status: "pending", date: null },
            ],
        },
        {
            id: "SLTR-APP-004",
            arn: "ARN-COM-2023-02-369123",
            applicantName: "XYZ Enterprises",
            fileNumber: "SLTR-COM-2023-02",
            propertyType: "Commercial",
            location: "Wuse 2",
            submissionDate: "2023-05-25",
            status: "rejected",
            currentDepartment: "deeds",
            approvalSteps: [
                { department: "lands", status: "approved", date: "2023-05-28" },
                { department: "deeds", status: "rejected", date: "2023-06-02" },
            ],
        },
    ];
    
    // State management
    let currentTab = 'pending';
    let searchQuery = '';
    let statusFilter = 'all';
    let selectedApplications = [];
    let currentApplication = null;
    let isSubmitting = false;
    
    // DOM elements
    const elements = {
        searchInput: document.getElementById('search-input'),
        statusFilter: document.getElementById('status-filter'),
        tabTriggers: document.querySelectorAll('.tab-trigger'),
        tabContents: document.querySelectorAll('.tab-content'),
        bulkApproveBtn: document.getElementById('bulk-approve-btn'),
        bulkRejectBtn: document.getElementById('bulk-reject-btn'),
        selectAllPending: document.getElementById('select-all-pending'),
        approvalModal: document.getElementById('approval-modal'),
        closeModalBtn: document.getElementById('close-modal-btn'),
        cancelApprovalBtn: document.getElementById('cancel-approval-btn'),
        approveApplicationBtn: document.getElementById('approve-application-btn'),
        rejectApplicationBtn: document.getElementById('reject-application-btn'),
        deedsComments: document.getElementById('deeds-comments'),
        modalApplicationInfo: document.getElementById('modal-application-info'),
        applicationDetails: document.getElementById('application-details'),
        approvalHistory: document.getElementById('approval-history'),
        approveBtnText: document.getElementById('approve-btn-text')
    };
    
    // Helper functions
    function getStatusBadge(status) {
        const badges = {
            approved: '<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-green-100 text-green-800"><i data-lucide="check-circle-2" class="h-3 w-3 mr-1"></i>Registered</span>',
            in_progress: '<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800"><i data-lucide="clock" class="h-3 w-3 mr-1"></i>In Progress</span>',
            pending: '<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800"><i data-lucide="clock" class="h-3 w-3 mr-1"></i>Pending</span>',
            rejected: '<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-red-100 text-red-800"><i data-lucide="alert-triangle" class="h-3 w-3 mr-1"></i>Rejected</span>'
        };
        return badges[status] || `<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">${status}</span>`;
    }
    
    function filterApplications() {
        return applications.filter(app => {
            const matchesSearch = 
                app.applicantName.toLowerCase().includes(searchQuery.toLowerCase()) ||
                app.fileNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
                app.arn.toLowerCase().includes(searchQuery.toLowerCase());
            
            const matchesStatus = statusFilter === 'all' || app.status === statusFilter;
            
            return matchesSearch && matchesStatus;
        });
    }
    
    function renderTable(tabName) {
        const tableBody = document.getElementById(`${tabName === 'in_progress' ? 'in-progress' : tabName}-table-body`);
        const filteredApps = filterApplications().filter(app => {
            if (tabName === 'pending') return app.status === 'pending' || app.status === 'in_progress';
            return app.status === tabName;
        });
    
        if (filteredApps.length === 0) {
            const colSpan = 6; // No checkbox column, so always 6
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${colSpan}" class="text-center py-8 text-gray-500">
                        No ${tabName === 'in_progress' ? 'in progress' : tabName} applications found
                    </td>
                </tr>
            `;
            return;
        }
    
        tableBody.innerHTML = filteredApps.map(app => `
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="py-3 px-4">
                    <div>
                        <div class="text-base font-semibold">${app.fileNumber}</div>
                        <div class="text-sm font-medium text-gray-600">${app.arn}</div>
                    </div>
                </td>
                <td class="py-3 px-4">${app.applicantName}</td>
                <td class="py-3 px-4">${app.propertyType}</td>
                <td class="py-3 px-4">${app.location}</td>
                <td class="py-3 px-4">${getStatusBadge(app.status)}</td>
                <td class="py-3 px-4 text-right">
                    <button class="view-application-btn inline-flex items-center justify-center px-3 py-1 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors" data-id="${app.id}">
                        View Details
                    </button>
                </td>
            </tr>
        `).join('');
    
        // Re-initialize Lucide icons
        lucide.createIcons();
    
        // Add event listeners for view buttons only
        document.querySelectorAll('.view-application-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const appId = e.target.closest('button').dataset.id;
                const application = applications.find(app => app.id === appId);
                if (application) {
                    openApprovalModal(application);
                }
            });
        });
    }
    
    // Hide bulk action buttons (they're no longer needed)
    function updateBulkActionButtons() {
        elements.bulkApproveBtn.classList.add('hidden');
        elements.bulkRejectBtn.classList.add('hidden');
    }
    
    function switchTab(tabName) {
        currentTab = tabName;
        
        // Update tab triggers
        elements.tabTriggers.forEach(trigger => {
            trigger.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
            trigger.classList.add('text-gray-600', 'hover:text-gray-900');
            
            if (trigger.dataset.tab === tabName) {
                trigger.classList.remove('text-gray-600', 'hover:text-gray-900');
                trigger.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
            }
        });
    
        // Update tab content
        elements.tabContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === `${tabName}-tab`) {
                content.classList.add('active');
            }
        });
    
        // Clear selections when switching tabs (not needed but keeping for compatibility)
        selectedApplications = [];
        updateBulkActionButtons();
        
        // Render the table for the current tab
        renderTable(tabName);
    }
    
    function openApprovalModal(application) {
        currentApplication = application;
        
        // Update modal content
        elements.modalApplicationInfo.textContent = `${application.fileNumber} - ${application.applicantName}`;
        
        // Update application details
        elements.applicationDetails.innerHTML = `
            <div class="flex justify-between text-sm">
                <span>File Number:</span>
                <span class="font-medium">${application.fileNumber}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>ARN:</span>
                <span class="font-medium">${application.arn}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Applicant:</span>
                <span class="font-medium">${application.applicantName}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Property Type:</span>
                <span class="font-medium">${application.propertyType}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Location:</span>
                <span class="font-medium">${application.location}</span>
            </div>
        `;
        
        // Update approval history
        elements.approvalHistory.innerHTML = application.approvalSteps.map(step => `
            <div class="flex items-center justify-between">
                <span class="text-sm capitalize">${step.department}:</span>
                ${getStatusBadge(step.status)}
            </div>
        `).join('');
        
        // Show/hide action buttons based on status
        const isReadOnly = application.status === 'approved' || application.status === 'rejected';
        elements.approveApplicationBtn.style.display = isReadOnly ? 'none' : 'inline-flex';
        elements.rejectApplicationBtn.style.display = isReadOnly ? 'none' : 'inline-flex';
        
        // Clear comments
        elements.deedsComments.value = '';
        
        // Show modal
        elements.approvalModal.classList.add('open');
        document.body.style.overflow = 'hidden';
        
        // Re-initialize Lucide icons
        lucide.createIcons();
    }
    
    function closeApprovalModal() {
        elements.approvalModal.classList.remove('open');
        document.body.style.overflow = 'auto';
        currentApplication = null;
        isSubmitting = false;
        elements.approveBtnText.textContent = 'Register & Forward to Survey';
    }
    
    function handleSubmitApproval(action) {
        if (isSubmitting) return;
        
        const comments = elements.deedsComments.value.trim();
        if (!comments && action === 'approve') {
            alert('Please enter verification comments before approving.');
            return;
        }
        
        isSubmitting = true;
        elements.approveBtnText.textContent = 'Processing...';
        elements.approveApplicationBtn.disabled = true;
        elements.rejectApplicationBtn.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Update application status in mock data
            const appIndex = applications.findIndex(app => app.id === currentApplication.id);
            if (appIndex !== -1) {
                applications[appIndex].status = action === 'approve' ? 'approved' : 'rejected';
                if (action === 'approve') {
                    applications[appIndex].currentDepartment = 'survey';
                    applications[appIndex].approvalSteps.find(step => step.department === 'deeds').status = 'approved';
                    applications[appIndex].approvalSteps.find(step => step.department === 'deeds').date = new Date().toISOString().split('T')[0];
                }
            }
            
            // Close modal and refresh table
            closeApprovalModal();
            renderTable(currentTab);
            
            // Show success message
            alert(`Application ${action === 'approve' ? 'approved' : 'rejected'} successfully!`);
            
            isSubmitting = false;
            elements.approveApplicationBtn.disabled = false;
            elements.rejectApplicationBtn.disabled = false;
        }, 1500);
    }
    
    function handleBulkAction(action) {
        if (selectedApplications.length === 0) return;
        
        const actionText = action === 'approve' ? 'approving' : 'rejecting';
        if (confirm(`Are you sure you want to ${actionText} ${selectedApplications.length} application(s)?`)) {
            // Update applications in mock data
            selectedApplications.forEach(appId => {
                const appIndex = applications.findIndex(app => app.id === appId);
                if (appIndex !== -1) {
                    applications[appIndex].status = action === 'approve' ? 'approved' : 'rejected';
                    if (action === 'approve') {
                        applications[appIndex].currentDepartment = 'survey';
                        applications[appIndex].approvalSteps.find(step => step.department === 'deeds').status = 'approved';
                        applications[appIndex].approvalSteps.find(step => step.department === 'deeds').date = new Date().toISOString().split('T')[0];
                    }
                }
            });
            
            // Clear selections and refresh
            selectedApplications = [];
            updateBulkActionButtons();
            renderTable(currentTab);
            
            alert(`${selectedApplications.length} application(s) ${action === 'approve' ? 'approved' : 'rejected'} successfully!`);
        }
    }
    
    // Event listeners
    elements.searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        renderTable(currentTab);
    });
    
    elements.statusFilter.addEventListener('change', (e) => {
        statusFilter = e.target.value;
        renderTable(currentTab);
    });
    
    elements.tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            switchTab(trigger.dataset.tab);
        });
    });
    
    elements.closeModalBtn.addEventListener('click', closeApprovalModal);
    elements.cancelApprovalBtn.addEventListener('click', closeApprovalModal);
    
    elements.approveApplicationBtn.addEventListener('click', () => handleSubmitApproval('approve'));
    elements.rejectApplicationBtn.addEventListener('click', () => handleSubmitApproval('reject'));
    
    // Close modal when clicking outside
    elements.approvalModal.addEventListener('click', (e) => {
        if (e.target === elements.approvalModal) {
            closeApprovalModal();
        }
    });
    
    // Escape key to close modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && elements.approvalModal.classList.contains('open')) {
            closeApprovalModal();
        }
    });
    
    // Initialize the page
    function init() {
        renderTable(currentTab);
        lucide.createIcons();
        // Hide bulk action buttons on initialization
        updateBulkActionButtons();
    }
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', init);
    </script>
    
    