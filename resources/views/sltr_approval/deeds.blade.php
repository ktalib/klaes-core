@extends('layouts.app')
@section('page-title')
    {{ __('Deeds Department - SLTR Application Approval') }}
@endsection

@section('content')
 <script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: '#3b82f6',
                'primary-foreground': '#ffffff',
                muted: '#f3f4f6',
                'muted-foreground': '#6b7280',
                border: '#e5e7eb',
                ring: '#3b82f6',
                success: '#10b981',
                warning: '#f59e0b',
                destructive: '#ef4444',
                secondary: '#f1f5f9',
                'secondary-foreground': '#0f172a',
            }
        }
    }
}
</script>
<style>
/* Custom styles for JavaScript-controlled states */
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.modal {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
}
.modal.open {
    opacity: 1;
    visibility: visible;
}
.modal-content {
    transform: scale(0.95);
    transition: transform 0.2s;
}
.modal.open .modal-content {
    transform: scale(1);
}
</style>
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            
    <!-- Search and Filter Controls -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
        <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
            <div class="relative w-full md:w-80">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                <input type="text" id="search-input" placeholder="Search applications..." class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="relative">
                <select id="status-filter" class="w-44 pl-8 pr-8 py-2 border border-gray-300 rounded-md text-sm bg-white appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Statuses</option>
                    <option value="approved">Registered</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
                <i data-lucide="filter" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none"></i>
                <i data-lucide="chevron-down" class="absolute right-2.5 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none"></i>
            </div>
        </div>

        <div class="flex gap-2 w-full md:w-auto">
            <button id="bulk-reject-btn" class="hidden inline-flex items-center justify-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition-colors">
                Reject Selected
            </button>
            <button id="bulk-approve-btn" class="hidden inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                Register Selected
            </button>
            <button class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                <i data-lucide="download" class="h-4 w-4 mr-2"></i>
                Export
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="w-full">
        <div class="flex bg-gray-100 rounded-md p-1 mb-4">
            <button class="tab-trigger flex-1 flex items-center justify-center px-4 py-2 rounded text-sm font-medium transition-all bg-white text-blue-600 shadow-sm" data-tab="pending">Pending Review</button>
            <button class="tab-trigger flex-1 flex items-center justify-center px-4 py-2 rounded text-sm font-medium transition-all text-gray-600 hover:text-gray-900" data-tab="in_progress">In Progress</button>
            <button class="tab-trigger flex-1 flex items-center justify-center px-4 py-2 rounded text-sm font-medium transition-all text-gray-600 hover:text-gray-900" data-tab="approved">Registered</button>
            <button class="tab-trigger flex-1 flex items-center justify-center px-4 py-2 rounded text-sm font-medium transition-all text-gray-600 hover:text-gray-900" data-tab="rejected">Rejected</button>
        </div>

        <!-- Pending Review Tab -->
        <div id="pending-tab" class="tab-content active">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Pending Deeds Review</h3>
                    <p class="text-sm text-gray-600 mt-1">Applications awaiting deed document verification by Deeds Department</p>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                  
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Application</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Applicant</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Property Type</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Location</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Status</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-600 text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-table-body">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- In Progress Tab -->
        <div id="in_progress-tab" class="tab-content">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">In Progress Deeds Review</h3>
                    <p class="text-sm text-gray-600 mt-1">Applications currently being reviewed by Deeds Department</p>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Application</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Applicant</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Property Type</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Location</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Status</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-600 text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="in-progress-table-body">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registered Tab -->
        <div id="approved-tab" class="tab-content">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Registered Applications</h3>
                    <p class="text-sm text-gray-600 mt-1">Applications approved by Deeds Department</p>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Application</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Applicant</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Property Type</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Location</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Status</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-600 text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="approved-table-body">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejected Tab -->
        <div id="rejected-tab" class="tab-content">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Rejected Applications</h3>
                    <p class="text-sm text-gray-600 mt-1">Applications rejected by Deeds Department</p>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Application</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Applicant</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Property Type</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Location</th>
                                    <th class="text-left py-3 px-4 font-medium text-gray-600 text-sm">Status</th>
                                    <th class="text-right py-3 px-4 font-medium text-gray-600 text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rejected-table-body">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approval-modal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="modal-content bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <i data-lucide="book-open" class="h-5 w-5 text-purple-500"></i>
                            Deeds Department Review
                        </h3>
                        <p id="modal-application-info" class="text-sm text-gray-600 mt-1">SLTR-RES-2023-01 - John Doe</p>
                    </div>
                    <button id="close-modal-btn" class="inline-flex items-center justify-center p-2 border border-gray-300 bg-white text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Application Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-600 mb-3">Application Details</h4>
                        <div class="space-y-2" id="application-details">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-600 mb-3">Previous Approvals</h4>
                        <div class="space-y-2" id="approval-history">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div>
                    <h4 class="text-sm font-medium text-gray-600 mb-3">Deed Documents to Review</h4>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm p-3 bg-gray-50 rounded-md">
                            <div class="flex items-center gap-2">
                                <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
                                <span>Certificate of Occupancy.pdf</span>
                            </div>
                            <button class="inline-flex items-center justify-center p-1 text-gray-500 hover:text-gray-700 transition-colors">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </button>
                        </div>
                        
                        <div class="flex items-center justify-between text-sm p-3 bg-gray-50 rounded-md">
                            <div class="flex items-center gap-2">
                                <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
                                <span>Deed of Assignment.pdf</span>
                            </div>
                            <button class="inline-flex items-center justify-center p-1 text-gray-500 hover:text-gray-700 transition-colors">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </button>
                        </div>
                        
                        <div class="flex items-center justify-between text-sm p-3 bg-gray-50 rounded-md">
                            <div class="flex items-center gap-2">
                                <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
                                <span>Power of Attorney.pdf</span>
                            </div>
                            <button class="inline-flex items-center justify-center p-1 text-gray-500 hover:text-gray-700 transition-colors">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="space-y-4">
                    <div>
                        <label for="deeds-comments" class="block text-sm font-medium text-gray-700 mb-2">Deeds Verification Comments</label>
                        <textarea id="deeds-comments" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter your verification comments..." required></textarea>
                    </div>

                    <!-- Document Upload -->
                    {{-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload Verification Documents (Optional)</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                            <i data-lucide="upload" class="h-8 w-8 mx-auto text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600 mb-2">Drag and drop verification documents here, or click to browse</p>
                            <input type="file" id="document-upload" class="hidden" multiple>
                            <button type="button" onclick="document.getElementById('document-upload').click()" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                                Browse Files
                            </button>
                        </div>
                    </div> --}}

                    <!-- Signature Section -->
                    {{-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Approval Signature/Stamp</label>
                        <div class="border border-gray-300 rounded-md p-6 bg-gray-50 flex items-center justify-center">
                            <i data-lucide="pen-tool" class="h-12 w-12 text-gray-400"></i>
                        </div>
                    </div> --}}
                </div>

                <!-- Approval Tracking -->
                <div>
                    <h4 class="text-sm font-medium text-gray-600 mb-3">Approval Status Tracking</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm">Approval History:</span>
                            <button class="inline-flex items-center justify-center px-3 py-1 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 transition-colors">
                                <i data-lucide="history" class="h-4 w-4 mr-2"></i>
                                View History
                            </button>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">Approval Timeline:</span>
                            <button class="inline-flex items-center justify-center px-3 py-1 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 transition-colors">
                                <i data-lucide="clock" class="h-4 w-4 mr-2"></i>
                                View Timeline
                            </button>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">Approval Reference Number:</span>
                            <span class="text-sm font-medium flex items-center">
                                <i data-lucide="key-round" class="h-4 w-4 mr-1"></i>
                                REF-2023-DEEDS-001
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end gap-2 p-6 pt-0 border-t border-gray-200">
                <button id="cancel-approval-btn" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                     <div class="hiden" style="display: none">
                <button id="reject-application-btn" class="inline-flex items-center justify-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition-colors">
                    <i data-lucide="x" class="h-4 w-4 mr-1"></i>
                    Reject Application
                </button>
                <button id="approve-application-btn" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                    <i data-lucide="check" class="h-4 w-4 mr-1"></i>
                    <span id="approve-btn-text">Register & Forward to Survey</span>
                </button>
                </div>

            </div>
        </div>
    </div>

        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    @include('sltr_approval.partial.deeds_js')
@endsection
