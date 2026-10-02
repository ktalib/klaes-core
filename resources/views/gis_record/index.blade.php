@extends('layouts.app')
@section('page-title')
    {{ __('GIS Data Capture') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<style>
    /* Required for proper z-index stacking and overflow */
    .dropdown-wrapper { 
        position: static; 
    }
    .dropdown-menu { 
        position: fixed;
        z-index: 9999;
        min-width: 12rem;
        margin-top: 0.25rem;
    }
    /* Ensure table doesn't expand due to dropdown */
    .overflow-x-auto { 
        overflow-x: auto;
        overflow-y: visible;
    }
    /* Prevent table cell from expanding */
    .action-cell {
        width: 60px;
        min-width: 60px;
        max-width: 60px;
        position: relative;
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
    .status-active {
        background-color: #dcfce7;
        color: #166534;
    }
    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    .status-completed {
        background-color: #dbeafe;
        color: #1e40af;
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
                <h2 class="text-xl font-bold">GIS Data Capture Records</h2>
                <!-- Primary GIS Create Button -->
                <a href="{{ route('gis_record.create', ['is' => 'primary']) }}" 
                   id="primary-gis-button"
                   class="flex items-center space-x-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                    <span>Create New GIS Record</span>
                </a>
                <!-- Unit GIS Create Button -->
                <a href="{{ route('gis_record.create', ['is' => 'secondary']) }}" 
                   id="unit-gis-button"
                   class="hidden flex items-center space-x-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Create Unit GIS Record</span>
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
                <!-- Total GIS Records Counter -->
                <div class="counter-card primary rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">Total GIS Records</p>
                            <p class="text-3xl font-bold mt-2" id="total-counter">{{ count($gisData) }}</p>
                            <p class="text-white/70 text-xs mt-1">All captured data</p>
                        </div>
                        <div class="bg-white/20 p-3 rounded-lg">
                            <i data-lucide="database" class="w-8 h-8"></i>
                        </div>
                    </div>
                </div>

                <!-- Recent Records Counter -->
                <div class="counter-card recent rounded-xl p-6 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm font-medium">This Month</p>
                            <p class="text-3xl font-bold mt-2" id="recent-counter">
                                {{ 
                                    collect($gisData)->filter(function($item) {
                                        return $item->created_at && \Carbon\Carbon::parse($item->created_at)->isCurrentMonth();
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
                            <input type="text" id="search-input" placeholder="Search GIS records..." 
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
                            <th class="table-header text-green-500">MLSF No</th>
                            <th class="table-header text-green-500">KANGIS File No</th>
                            <th class="table-header text-green-500">New KANGIS File No</th>
                            <th class="table-header text-green-500">Plot No</th>
                            <th class="table-header text-green-500">Block No</th>
                            <th class="table-header text-green-500">Approved Plan No</th>
                            <th class="table-header text-green-500">TP Plan No</th>
                            <th class="table-header text-green-500">Old Title Serial No</th>
                            <th class="table-header text-green-500">Old Title Page No</th>
                            <th class="table-header text-green-500">Old Title Volume No</th>
                            <th class="table-header text-green-500">Created At</th>
                            <th class="table-header text-green-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($gisData as $data)
                        <tr class="text-xs table-row">
                            <td class="table-cell px-1 py-1 truncate">{{ $data->mlsfNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->kangisFileNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->NewKANGISFileno ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->plotNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->blockNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->approvedPlanNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->tpPlanNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->oldTitleSerialNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->oldTitlePageNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->oldTitleVolumeNo ?? 'N/A' }}</td>
                            <td class="table-cell px-1 py-1 truncate">{{ $data->created_at ? date('d M, Y', strtotime($data->created_at)) : 'N/A' }}</td>
                            <td class="table-cell action-cell px-1 py-1">
                                <div class="dropdown-wrapper" x-data="{ 
                                    open: false, 
                                    toggle() { 
                                        this.open = !this.open; 
                                        if (this.open) {
                                            this.$nextTick(() => {
                                                const button = this.$refs.button;
                                                const dropdown = this.$refs.dropdown;
                                                const rect = button.getBoundingClientRect();
                                                dropdown.style.top = (rect.bottom + 4) + 'px';
                                                dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px';
                                            });
                                        }
                                    } 
                                }">
                                    <button x-ref="button" @click.prevent="toggle()" class="text-gray-600 hover:text-blue-600 p-1 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 ml-auto block">
                                        <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                    </button>
                                    
                                    <div x-ref="dropdown" x-show="open" @click.away="open = false" x-transition
                                        class="dropdown-menu w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                        style="display: none; z-index: 9999;">
                                        <div class="py-1">
                                            <a href="{{ route('gis.view', $data->id) }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i data-lucide="eye" class="h-4 w-4 mr-2 text-gray-500"></i>
                                                View
                                            </a>
                                            <a href="{{ route('gis.edit', $data->id) }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i data-lucide="edit" class="h-4 w-4 mr-2 text-gray-500"></i>
                                                Edit
                                            </a>
                                            <form action="{{ route('gis.destroy', $data->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Are you sure you want to delete this record?')" 
                                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                    <i data-lucide="trash" class="h-4 w-4 mr-2 text-red-500"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
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

<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
            const activeCounter = document.getElementById('active-counter');
            const completedCounter = document.getElementById('completed-counter');
            const recentCounter = document.getElementById('recent-counter');
            
            if (totalCounter) animateCounter(totalCounter, parseInt(totalCounter.textContent));
            if (activeCounter) animateCounter(activeCounter, parseInt(activeCounter.textContent));
            if (completedCounter) animateCounter(completedCounter, parseInt(completedCounter.textContent));
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
            a.download = `gis-data-capture-${new Date().toISOString().split('T')[0]}.csv`;
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
                    const originalTotal = {{ count($gisData) }};
                    const originalRecent = {{ collect($gisData)->filter(function($item) { return $item->created_at && \Carbon\Carbon::parse($item->created_at)->isCurrentMonth(); })->count() }};
                    
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
            total: {{ count($gisData) }},
            recent: {{ collect($gisData)->filter(function($item) { return $item->created_at && \Carbon\Carbon::parse($item->created_at)->isCurrentMonth(); })->count() }}
        };
    });
</script>
@endsection