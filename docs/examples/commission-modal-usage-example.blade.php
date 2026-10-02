@extends('layouts.app')

@section('page-title')
    File Tracking Dashboard
@endsection

@section('content')
    <!-- Header -->
    @include('admin.header', [
        'PageTitle' => 'File Tracking Dashboard',
        'PageDescription' => 'Track and manage all file movements'])

    <div class="p-6">
        <div class="container mx-auto py-6">

            <!-- Action Buttons Section -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Recent Tracked Files</h2>
                
                <div class="flex space-x-3">
                    {{-- Commission New File Button - This triggers the global modal --}}
                    <button onclick="openCommissionFileNoModal()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors shadow-md">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Commission New File</span>
                    </button>

                    {{-- Your other action buttons --}}
                    <button onclick="exportData()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors shadow-md">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>Export</span>
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-blue-100 rounded-lg text-blue-600">
                            <i data-lucide="file-check" class="w-8 h-8"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Files</p>
                            <h3 class="text-2xl font-bold text-gray-900">1,234</h3>
                        </div>
                    </div>
                </div>
                <!-- Add more stat cards as needed -->
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6">
                    <table id="trackingTable" class="w-full table-auto">
                        <thead>
                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">S/N</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">File Number</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">File Name</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Location</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <!-- Data loaded via DataTables AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')

    {{-- ============================================ --}}
    {{-- INCLUDE THE COMMISSION FILE NUMBER MODAL     --}}
    {{-- This is the ONLY line you need to add!       --}}
    {{-- ============================================ --}}
    @include('components.commission-fileno-modal-include')

@endsection

@push('scripts')
    <script>
        // Initialize DataTable
        let trackingTable;

        $(document).ready(function() {
            trackingTable = $('#trackingTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route("file-tracking.data") }}',
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'file_number', name: 'file_number' },
                    { data: 'file_name', name: 'file_name' },
                    { data: 'location', name: 'location' },
                    { data: 'status', name: 'status' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ]
            });

            // Initialize Lucide icons
            lucide.createIcons();
        });

        /**
         * Custom success callback - Called after file number is successfully commissioned
         * This function is automatically invoked by the modal component
         */
        window.commissionFileNoSuccessCallback = function(response) {
            console.log('File commissioned successfully:', response);

            // 1. Reload the DataTable to show the new file
            if (trackingTable) {
                trackingTable.ajax.reload(null, false); // false = stay on current page
            }

            // 2. Show success notification
            toastr.success('File Number ' + response.file_number + ' has been successfully commissioned!', 'Success', {
                timeOut: 5000,
                closeButton: true,
                progressBar: true
            });

            // 3. Optional: Update statistics (if you have live stats)
            updateDashboardStats();

            // 4. Optional: Scroll to top to see the notification
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // 5. Optional: Log the activity
            logUserActivity('file_commissioned', response.file_number);
        };

        /**
         * Example: Update dashboard statistics
         */
        function updateDashboardStats() {
            // Fetch updated stats from your API
            $.get('{{ route("dashboard.stats") }}', function(data) {
                // Update your stat cards
                $('.total-files-count').text(data.total_files);
                $('.today-count').text(data.today_count);
            });
        }

        /**
         * Example: Log user activity
         */
        function logUserActivity(action, fileNumber) {
            $.post('{{ route("activity.log") }}', {
                _token: '{{ csrf_token() }}',
                action: action,
                file_number: fileNumber,
                timestamp: new Date().toISOString()
            });
        }

        /**
         * Example: Export function
         */
        function exportData() {
            window.location.href = '{{ route("file-tracking.export") }}';
        }
    </script>
@endpush
