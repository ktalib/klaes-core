@extends('layouts.app')
@section('page-title')
    {{ __('Cadastral Records') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<style>
    /* Required for proper z-index stacking and overflow */
    .dropdown-wrapper { 
        position: static; 
    }
    
    .dropdown-menu { 
        position: fixed !important;
        z-index: 10000 !important;
        min-width: 10rem;
        margin-top: 0.25rem;
        white-space: nowrap;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
    }
    
    /* Ensure table container allows overflow */
    .overflow-x-auto { 
        overflow-x: auto;
        overflow-y: visible !important;
        position: relative;
    }
    
    /* Table positioning */
    .table-container {
        position: relative;
        overflow: visible;
    }
    
    /* Prevent table cell from expanding */
    .action-cell {
        width: 80px;
        min-width: 80px;
        max-width: 80px;
        position: relative;
    }
    
    /* Responsive dropdown adjustments */
    @media (max-width: 768px) {
        .dropdown-menu {
            min-width: 8rem;
            font-size: 0.75rem;
        }
        .action-cell {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }
    }
    
    /* Tab styling */
    .tab-nav { 
        border-bottom: 2px solid #e5e7eb; 
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 8px 8px 0 0;
    }
    .tab-nav button { 
        padding: 1rem 2rem; 
        margin-right: 0.25rem; 
        border-bottom: 3px solid transparent; 
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        font-weight: 500;
        color: #64748b;
    }
    .tab-nav button:hover {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .tab-nav button.active { 
        border-bottom-color: #10b981; 
        color: #10b981; 
        font-weight: 700;
        background-color: white;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
    }
    .tab-content > div { display: none; }
    .tab-content > div.active { display: block; }
    
    /* Counter animations */
    .counter-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: all 0.3s ease;
    }
    .counter-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .counter-card.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .counter-card.secondary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    .counter-card.total {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }
    .counter-card.recent {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    /* Table improvements */
    .table-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.75rem;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .table-row {
        transition: all 0.2s ease;
    }
    .table-row:hover {
        background-color: #f8fafc;
        transform: scale(1.001);
    }
    
    /* Search and filter styling */
    .search-container {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
    }
    
    /* Status badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .status-primary {
        background-color: #dcfce7;
        color: #166534;
    }
    .status-unit {
        background-color: #dbeafe;
        color: #1e40af;
    }
    .status-unknown {
        background-color: #f3f4f6;
        color: #374151;
    }
</style>
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Main Content Container -->
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <!-- Header with actions -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Cadastral Records</h2>
                <!-- Cadastral Create Button -->
                <a href="{{ route('survey_cadastral.create', ['is' => 'primary']) }}" 
                   id="cadastral-create-button"
                   class="flex items-center space-x-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                    <span>Create New Cadastral Record</span>
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <!-- Smart Counters Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
                <!-- Total Cadastral Counter -->
                <div class="counter-card primary rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Total Cadastral Records</p>
                            <p class="text-3xl font-bold mt-2" id="total-counter">{{ count($surveys) }}</p>
                            <p class="text-white/70 text-xs mt-1">All cadastral data</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="database" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Recent Cadastral Counter -->
                <div class="counter-card recent rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">This Month</p>
                            <p class="text-3xl font-bold mt-2" id="recent-counter">
                                {{ 
                                    collect($surveys)->filter(function($survey) {
                                        return $survey->created_at && \Carbon\Carbon::parse($survey->created_at)->isCurrentMonth();
                                    })->count()
                                }}
                            </p>
                            <p class="text-white/70 text-xs mt-1">New records</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="trending-up" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-container">
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="search-input" placeholder="Search cadastral records..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button id="export-btn" class="flex items-center space-x-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-xs">
                            <th class="table-header text-green-500">File No</th>
                            <th class="table-header text-green-500">Plot No</th>
                            <th class="table-header text-green-500">Block No</th>
                            <th class="table-header text-green-500">Approved Plan No</th>
                            <th class="table-header text-green-500">TP Plan No</th>
                            {{-- <th class="table-header text-green-500">Survey Type</th> --}}
                            <th class="table-header text-green-500">Control Beacon Name</th>
                            <th class="table-header text-green-500">Control Beacon X</th>
                            <th class="table-header text-green-500">Control Beacon Y</th>
                            <th class="table-header text-green-500">Layout Name</th>
                            <th class="table-header text-green-500">District Name</th>
                            <th class="table-header text-green-500">LGA Name</th>
                            <th class="table-header text-green-500">Survey By</th>
                            <th class="table-header text-green-500">Survey Date</th>
                            <th class="table-header text-green-500">Action</th>
                        </tr>
                    </thead> 
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($surveys as $survey)
                        <tr class="text-xs table-row">
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->fileno ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->plot_no ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->block_no ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->approved_plan_no ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->tp_plan_no ?? 'N/A' }}</td>
                            {{-- <td class="table-cell px-2 py-2">
                                @if(!empty($survey->application_id))
                                    <span class="status-badge status-primary">Primary</span>
                                @elseif(!empty($survey->sub_application_id))
                                    <span class="status-badge status-unit">Unit</span>
                                @else
                                    <span class="status-badge status-unknown">Unknown</span>
                                @endif
                            </td> --}}
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->beacon_control_name ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->Control_Beacon_Coordinate_X ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->Control_Beacon_Coordinate_Y ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->layout_name ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->district_name ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->lga_name ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->survey_by ?? 'N/A' }}</td>
                            <td class="table-cell px-2 py-2 truncate">{{ $survey->survey_by_date ?? 'N/A' }}</td>
                            <td class="table-cell action-cell px-2 py-2">
                                <div class="dropdown-wrapper">
                                    <button class="flex items-center px-3 py-1 text-xs bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors dropdown-toggle" 
                                            type="button" 
                                            data-survey-id="{{ $survey->ID }}"
                                            onclick="toggleDropdown({{ $survey->ID }})">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Survey Plan Modal -->
<div id="surveyPlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b">
                <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Cadastral Plan</h3>
                <button onclick="closeSurveyPlanModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="mt-4">
                <div id="surveyPlanContent" class="text-center">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end pt-4 border-t mt-4">
                <button onclick="closeSurveyPlanModal()" 
                        class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 mr-2">
                    Close
                </button>
                <button id="downloadPlanBtn" onclick="downloadSurveyPlan()" 
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 hidden">
                    <i data-lucide="download" class="w-4 h-4 inline mr-1"></i>
                    Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Dropdown Container (will be positioned absolutely) -->
<div id="dynamicDropdown" class="dropdown-menu hidden bg-white border border-gray-200 rounded-md shadow-lg py-1" style="position: fixed; z-index: 10000;">
    <!-- Content will be dynamically populated -->
</div>

<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        
        // Counter animation function
        function animateCounter(element, target, duration = 1000) {
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 16);
        }
        
        // Animate counters on page load
        setTimeout(() => {
            const totalCounter = document.getElementById('total-counter');
            const recentCounter = document.getElementById('recent-counter');
            
            if (totalCounter) animateCounter(totalCounter, parseInt(totalCounter.textContent));
            if (recentCounter) animateCounter(recentCounter, parseInt(recentCounter.textContent));
        }, 300);
        
        // Search functionality
        const searchInput = document.getElementById('search-input');
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase();
            
            // Get all table rows
            const rows = document.querySelectorAll('tbody tr');
            
            let visibleTotal = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let textContent = '';
                cells.forEach(cell => textContent += cell.textContent.toLowerCase() + ' ');
                
                const matchesSearch = textContent.includes(searchTerm);
                
                if (matchesSearch) {
                    row.style.display = '';
                    visibleTotal++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update counters based on visible rows
            if (searchTerm) {
                document.getElementById('total-counter').textContent = visibleTotal;
            }
        }
        
        // Add search event listeners
        searchInput.addEventListener('input', performSearch);
        
        // Export functionality
        const exportBtn = document.getElementById('export-btn');
        exportBtn.addEventListener('click', function() {
            const table = document.querySelector('table');
            
            if (!table) return;
            
            // Get visible rows only
            const rows = Array.from(table.querySelectorAll('tr')).filter(row => 
                row.style.display !== 'none'
            );
            
            let csv = '';
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = Array.from(cells).slice(0, -1).map(cell => 
                    `"${cell.textContent.trim().replace(/"/g, '""')}"`
                ).join(',');
                csv += rowData + '\n';
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cadastral-records-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        });
        
        // Reset counters when search is cleared
        searchInput.addEventListener('input', function() {
            if (!this.value) {
                // Reset to original values
                setTimeout(() => {
                    const originalTotal = {{ count($surveys) }};
                    const originalRecent = {{ collect($surveys)->filter(function($survey) { return $survey->created_at && \Carbon\Carbon::parse($survey->created_at)->isCurrentMonth(); })->count() }};
                    
                    document.getElementById('total-counter').textContent = originalTotal;
                    document.getElementById('recent-counter').textContent = originalRecent;
                }, 100);
            }
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                performSearch();
                searchInput.blur();
            }
        });
        
        // Store original values for reset
        window.originalCounters = {
            total: {{ count($surveys) }},
            recent: {{ collect($surveys)->filter(function($survey) { return $survey->created_at && \Carbon\Carbon::parse($survey->created_at)->isCurrentMonth(); })->count() }}
        };
    });

    // New Dropdown functionality using external dropdown
    let currentDropdownSurveyId = null;
    
    window.toggleDropdown = function(surveyId) {
        const dropdown = document.getElementById('dynamicDropdown');
        const button = document.querySelector(`[data-survey-id="${surveyId}"]`);
        
        // If clicking the same button, close dropdown
        if (currentDropdownSurveyId === surveyId && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
            currentDropdownSurveyId = null;
            return;
        }
        
        // Get survey data for this row
        const surveyData = getSurveyData(surveyId);
        
        // Populate dropdown content
        dropdown.innerHTML = `
            <a href="{{ url('survey_cadastral/edit') }}/${surveyId}" 
               class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-100 transition-colors">
                <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                Edit
            </a>
            <button onclick="viewSurveyPlan(${surveyId}, '${surveyData.fileno}', '${surveyData.planPath}')" 
                    class="flex items-center w-full px-4 py-2 text-xs text-gray-700 hover:bg-gray-100 transition-colors">
                <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                View Cadastral Plan
            </button>
            <button onclick="confirmDelete(${surveyId})" 
                    class="flex items-center w-full px-4 py-2 text-xs text-red-600 hover:bg-red-50 transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                Delete
            </button>
        `;
        
        // Position dropdown
        const rect = button.getBoundingClientRect();
        const dropdownWidth = 160;
        const dropdownHeight = 120;
        
        let left = rect.right - dropdownWidth;
        let top = rect.bottom + window.scrollY;
        
        // Ensure dropdown doesn't go off screen
        if (left < 10) {
            left = rect.left;
        }
        if (left + dropdownWidth > window.innerWidth - 10) {
            left = window.innerWidth - dropdownWidth - 10;
        }
        
        // Check if dropdown would go below viewport
        if (rect.bottom + dropdownHeight > window.innerHeight) {
            top = rect.top + window.scrollY - dropdownHeight;
        }
        
        // Apply positioning and show
        dropdown.style.left = `${left}px`;
        dropdown.style.top = `${top}px`;
        dropdown.classList.remove('hidden');
        
        currentDropdownSurveyId = surveyId;
        
        // Recreate icons
        setTimeout(() => {
            lucide.createIcons();
        }, 10);
    }
    
    // Helper function to get survey data from the row
    function getSurveyData(surveyId) {
        const surveys = @json($surveys);
        const survey = surveys.find(s => s.ID == surveyId);
        return {
            fileno: survey ? (survey.fileno || 'N/A') : 'N/A',
            planPath: survey ? (survey.survey_plan_path || '') : ''
        };
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('dynamicDropdown');
        if (!event.target.closest('.dropdown-wrapper') && !event.target.closest('#dynamicDropdown')) {
            dropdown.classList.add('hidden');
            currentDropdownSurveyId = null;
        }
    });

    // Delete confirmation function
    window.confirmDelete = function(surveyId) {
        // Close dropdown first
        document.getElementById('dynamicDropdown').classList.add('hidden');
        currentDropdownSurveyId = null;
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a form and submit it for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('survey_cadastral/delete') }}/${surveyId}`;
                
                // Add CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                // Add method spoofing for DELETE
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Survey Plan Modal Functions
    let currentSurveyPlanPath = '';

    window.viewSurveyPlan = function(surveyId, fileNo, planPath) {
        // Close dropdown first
        document.getElementById('dynamicDropdown').classList.add('hidden');
        currentDropdownSurveyId = null;
        
        const modal = document.getElementById('surveyPlanModal');
        const modalTitle = document.getElementById('modalTitle');
        const planContent = document.getElementById('surveyPlanContent');
        const downloadBtn = document.getElementById('downloadPlanBtn');
        
        // Set modal title
        modalTitle.textContent = `Cadastral Plan - ${fileNo}`;
        
        // Store current plan path for download
        currentSurveyPlanPath = planPath;
        
        // Clear previous content
        planContent.innerHTML = '<div class="flex justify-center items-center py-8"><i data-lucide="loader" class="w-8 h-8 animate-spin"></i><span class="ml-2">Loading...</span></div>';
        
        // Show modal
        modal.classList.remove('hidden');
        
        // Load plan content
        if (planPath && planPath !== '') {
            const fullPath = `{{ asset('storage') }}/${planPath}`;
            const fileExtension = planPath.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Display image
                planContent.innerHTML = `
                    <div class="max-w-full max-h-96 overflow-auto">
                        <img src="${fullPath}" alt="Cadastral Plan" class="max-w-full h-auto mx-auto rounded-lg shadow-md">
                    </div>
                `;
                downloadBtn.classList.remove('hidden');
            } else if (fileExtension === 'pdf') {
                // Display PDF
                planContent.innerHTML = `
                    <div class="w-full h-96">
                        <iframe src="${fullPath}" class="w-full h-full border rounded-lg" frameborder="0">
                            <p>Your browser does not support PDFs. <a href="${fullPath}" target="_blank">Download the PDF</a>.</p>
                        </iframe>
                    </div>
                `;
                downloadBtn.classList.remove('hidden');
            } else if (['dwg', 'dxf'].includes(fileExtension)) {
                // CAD files - show download option
                planContent.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="file-text" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-semibold text-gray-700 mb-2">CAD File (${fileExtension.toUpperCase()})</h4>
                        <p class="text-gray-500 mb-4">This file requires CAD software to view. Click download to save the file.</p>
                        <a href="${fullPath}" download class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                            Download ${fileExtension.toUpperCase()} File
                        </a>
                    </div>
                `;
                downloadBtn.classList.remove('hidden');
            } else {
                // Unknown file type
                planContent.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="file" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                        <h4 class="text-lg font-semibold text-gray-700 mb-2">File Available</h4>
                        <p class="text-gray-500 mb-4">File type: ${fileExtension.toUpperCase()}</p>
                        <a href="${fullPath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                            Open File
                        </a>
                    </div>
                `;
                downloadBtn.classList.remove('hidden');
            }
        } else {
            // No plan available
            planContent.innerHTML = `
                <div class="text-center py-8">
                    <i data-lucide="file-x" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                    <h4 class="text-lg font-semibold text-gray-700 mb-2">No Cadastral Plan Available</h4>
                    <p class="text-gray-500">No cadastral plan has been uploaded for this record.</p>
                </div>
            `;
            downloadBtn.classList.add('hidden');
        }
        
        // Recreate icons for new content
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
    }

    window.closeSurveyPlanModal = function() {
        const modal = document.getElementById('surveyPlanModal');
        modal.classList.add('hidden');
        currentSurveyPlanPath = '';
    }

    window.downloadSurveyPlan = function() {
        if (currentSurveyPlanPath) {
            const fullPath = `{{ asset('storage') }}/${currentSurveyPlanPath}`;
            const link = document.createElement('a');
            link.href = fullPath;
            link.download = currentSurveyPlanPath.split('/').pop();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('surveyPlanModal');
        if (event.target === modal) {
            closeSurveyPlanModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('surveyPlanModal');
            if (!modal.classList.contains('hidden')) {
                closeSurveyPlanModal();
            }
            
            // Also close dropdown
            const dropdown = document.getElementById('dynamicDropdown');
            if (!dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
                currentDropdownSurveyId = null;
            }
        }
    });

    // Legacy functions for backward compatibility
    window.showFullNames = function(owners) {
        if (!Array.isArray(owners)) {
            owners = [];
        }
        if (owners.length > 0) {
            Swal.fire({
                title: 'Full Names of Multiple Owners',
                html: '<ul>' + owners.map(name => `<li>${name}</li>`).join('') + '</ul>',
                icon: 'info',
                confirmButtonText: 'Close'
            });
        } else {
            Swal.fire({
                title: 'Full Names of Multiple Owners',
                text: 'No owners available',
                icon: 'info',
                confirmButtonText: 'Close'
            });
        }
    }
</script>
@endsection