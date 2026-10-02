@extends('layouts.app')
@section('page-title')
    {{ $existingTracking ? __('Update File Tracking') : __('Track New File') }}
@endsection

@section('content')
    @include('filetracker.assets.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="flex flex-col min-h-screen">
                <!-- Page Header -->
                <header class="bg-white shadow-sm px-6 py-4 border-b mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold">{{ $existingTracking ? 'Update File Tracking' : 'Track New File' }}</h1>
                            <p class="text-sm text-gray-500">
                                {{ $existingTracking ? 'Update file tracking information' : 'Register a new file for tracking and monitoring' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('filetracker.index') }}" class="border rounded-md px-4 py-2 text-sm flex items-center hover:bg-gray-50">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to File Tracker
                            </a>
                        </div>
                    </div>
                </header>

                <!-- Form Content -->
                <div class="flex-1">
                    <div class="max-w-4xl mx-auto">
                        <!-- Update Info (if updating) -->
                        @if($existingTracking)
                            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-sm font-medium text-green-800">Update Mode</h3>
                                        <p class="text-sm text-green-700">You are updating tracking information for: {{ $fileIndexing->file_number ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Batch Info (if batch tracking) -->
                        @if(request('batch') === 'true' && !$existingTracking)
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-sm font-medium text-blue-800">Batch Tracking Mode</h3>
                                        <p class="text-sm text-blue-700">You are creating tracking records for multiple files.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <form action="{{ $existingTracking ? route('filetracker.update', $existingTracking->id) : (request('batch') === 'true' ? route('filetracker.store-batch') : route('filetracker.store')) }}" 
                              method="POST" class="space-y-6" id="tracking-form">
                            @csrf
                            @if($existingTracking)
                                @method('PUT')
                            @endif
                            
                            <!-- Error Messages -->
                            @if ($errors->any())
                                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                            <div class="mt-2 text-sm text-red-700">
                                                <ul class="list-disc pl-5 space-y-1">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($existingTracking)
                                <!-- File Information Display (Update Mode) -->
                                <div class="bg-white rounded-lg shadow-sm border p-6">
                                    <h2 class="text-lg font-semibold mb-4">File Information</h2>
                                    <div class="bg-gray-50 rounded-md p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>File Number:</strong> {{ $fileIndexing->file_number ?? 'N/A' }}</div>
                                            <div><strong>File Title:</strong> {{ $fileIndexing->file_title ?? 'No Title' }}</div>
                                            <div><strong>Land Use:</strong> {{ $fileIndexing->land_use_type ?? 'N/A' }}</div>
                                            <div><strong>District:</strong> {{ $fileIndexing->district ?? 'N/A' }}</div>
                                            @if($fileIndexing->old_file_number)
                                                <div><strong>Old File Number:</strong> {{ $fileIndexing->old_file_number }}</div>
                                            @endif
                                            @if($fileIndexing->survey_plan_number)
                                                <div><strong>Survey Plan:</strong> {{ $fileIndexing->survey_plan_number }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- File Selection Section (Create Mode) -->
                                <div class="bg-white rounded-lg shadow-sm border p-6">
                                    <h2 class="text-lg font-semibold mb-4">File Selection</h2>
                                    
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label for="file_search" class="block text-sm font-medium text-gray-700 mb-2">
                                                Search and Select File
                                            </label>
                                            <div class="relative">
                                                <input type="text" 
                                                       id="file_search" 
                                                       placeholder="Type file number, title, or survey plan number..."
                                                       class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                       autocomplete="off">
                                                <div id="file_search_results" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-60 overflow-y-auto"></div>
                                            </div>
                                            <input type="hidden" name="file_indexing_id" id="file_indexing_id" value="{{ old('file_indexing_id') }}">
                                            <div id="selected_file_info" class="mt-3 p-3 bg-gray-50 rounded-md hidden">
                                                <h4 class="font-medium text-gray-900">Selected File:</h4>
                                                <div id="selected_file_details" class="text-sm text-gray-600 mt-1"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Tracking Information Section -->
                            <div class="bg-white rounded-lg shadow-sm border p-6">
                                <h2 class="text-lg font-semibold mb-4">Tracking Information</h2>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="current_location" class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Location <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="current_location" 
                                               id="current_location" 
                                               value="{{ old('current_location', $existingTracking->current_location ?? '') }}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_location') border-red-500 @enderror"
                                               placeholder="e.g., Archive Room A, Legal Department"
                                               required>
                                        @error('current_location')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="current_handler" class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Handler <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="current_handler" 
                                               id="current_handler" 
                                               value="{{ old('current_handler', $existingTracking->current_handler ?? '') }}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_handler') border-red-500 @enderror"
                                               placeholder="e.g., John Doe, Department Head"
                                               required>
                                        @error('current_handler')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="current_holder" class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Holder
                                        </label>
                                        <input type="text" 
                                               name="current_holder" 
                                               id="current_holder" 
                                               value="{{ old('current_holder', $existingTracking->current_holder ?? '') }}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_holder') border-red-500 @enderror"
                                               placeholder="e.g., Legal Department, Survey Unit">
                                        @error('current_holder')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <select name="status" 
                                                id="status" 
                                                class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror"
                                                required>
                                            <option value="">Select Status</option>
                                            <option value="in_process" {{ old('status', $existingTracking->status ?? '') == 'in_process' ? 'selected' : '' }}>In Process</option>
                                            <option value="pending" {{ old('status', $existingTracking->status ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="on_hold" {{ old('status', $existingTracking->status ?? '') == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                                            <option value="completed" {{ old('status', $existingTracking->status ?? '') == 'completed' ? 'selected' : '' }}>Completed</option>
                                        </select>
                                        @error('status')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="date_received" class="block text-sm font-medium text-gray-700 mb-2">
                                            Date Received <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" 
                                               name="date_received" 
                                               id="date_received" 
                                               value="{{ old('date_received', $existingTracking ? $existingTracking->date_received?->format('Y-m-d') : date('Y-m-d')) }}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('date_received') border-red-500 @enderror"
                                               required>
                                        @error('date_received')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                                            Due Date
                                        </label>
                                        <input type="date" 
                                               name="due_date" 
                                               id="due_date" 
                                               value="{{ old('due_date', $existingTracking ? $existingTracking->due_date?->format('Y-m-d') : '') }}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('due_date') border-red-500 @enderror">
                                        @error('due_date')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if($existingTracking)
                                        <!-- RFID and QR Code fields for updates -->
                                        <div>
                                            <label for="rfid_tag" class="block text-sm font-medium text-gray-700 mb-2">
                                                RFID Tag
                                            </label>
                                            <input type="text" 
                                                   name="rfid_tag" 
                                                   id="rfid_tag" 
                                                   value="{{ old('rfid_tag', $existingTracking->rfid_tag ?? '') }}"
                                                   class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('rfid_tag') border-red-500 @enderror"
                                                   placeholder="e.g., RFID123456">
                                            @error('rfid_tag')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="qr_code" class="block text-sm font-medium text-gray-700 mb-2">
                                                QR Code
                                            </label>
                                            <input type="text" 
                                                   name="qr_code" 
                                                   id="qr_code" 
                                                   value="{{ old('qr_code', $existingTracking->qr_code ?? '') }}"
                                                   class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('qr_code') border-red-500 @enderror"
                                                   placeholder="e.g., QR123456">
                                            @error('qr_code')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Notes Section -->
                            <div class="bg-white rounded-lg shadow-sm border p-6">
                                <h2 class="text-lg font-semibold mb-4">{{ $existingTracking ? 'Update Notes' : 'Additional Notes' }}</h2>
                                
                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ $existingTracking ? 'Update Notes' : 'Initial Notes' }}
                                    </label>
                                    <textarea name="notes" 
                                              id="notes" 
                                              rows="4"
                                              class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                                              placeholder="{{ $existingTracking ? 'Add notes about this update...' : 'Add any initial notes about this file tracking...' }}">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6">
                                <a href="{{ route('filetracker.index') }}" 
                                   class="border rounded-md px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    {{ $existingTracking ? 'Update File Tracking' : 'Create File Tracking' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- File Search and Batch Tracking Script -->
    <script>
        $(document).ready(function() {
            let searchTimeout;
            let selectedFiles = [];
            let isBatchMode = {{ request('batch') === 'true' ? 'true' : 'false' }};
            let isUpdateMode = {{ $existingTracking ? 'true' : 'false' }};
            
            // Initialize based on mode
            if (isUpdateMode) {
                // Update mode - no initialization needed, form is pre-filled
                console.log('Update mode initialized');
            } else if (isBatchMode) {
                initializeBatchMode();
            } else {
                initializeSingleMode();
            }
            
            // Initialize batch mode
            function initializeBatchMode() {
                const fileIds = '{{ request("files") }}'.split(',').filter(id => id.trim() !== '');
                
                // Load selected files directly
                if (fileIds.length > 0) {
                    loadSelectedFiles(fileIds);
                }
            }
            
            // Initialize single mode
            function initializeSingleMode() {
                const fileId = '{{ request("files") }}';
                if (fileId && fileId.trim() !== '') {
                    loadSelectedFiles([fileId]);
                }
            }
            
            // Load selected files and create forms
            function loadSelectedFiles(fileIds) {
                $.ajax({
                    url: '{{ route("filetracker.get-indexed-files") }}',
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            const allFiles = response.data;
                            selectedFiles = allFiles.filter(file => fileIds.includes(file.id.toString()));
                            
                            if (isBatchMode && selectedFiles.length > 1) {
                                createBatchForms();
                            } else if (selectedFiles.length === 1) {
                                autoFillSingleForm(selectedFiles[0]);
                            }
                        }
                    }
                });
            }
            
            // Auto-fill single form
            function autoFillSingleForm(file) {
                $('#file_indexing_id').val(file.id);
                $('#file_search').val(file.file_number);
                
                let detailsHtml = `
                    <div><strong>File Number:</strong> ${file.file_number}</div>
                    <div><strong>Title:</strong> ${file.file_title || 'No Title'}</div>
                    <div><strong>Land Use:</strong> ${file.land_use_type || 'N/A'}</div>
                    <div><strong>District:</strong> ${file.district || 'N/A'}</div>
                `;
                
                $('#selected_file_details').html(detailsHtml);
                $('#selected_file_info').removeClass('hidden');
                
                // Hide file search section since file is pre-selected
                $('#file_search').prop('readonly', true).addClass('bg-gray-100');
            }
            
            // Create batch forms
            function createBatchForms() {
                const formContainer = $('#tracking-form');
                
                // Clear the form and rebuild for batch
                formContainer.empty();
                
                // Add CSRF token
                formContainer.append(`
                    @csrf
                `);
                
                selectedFiles.forEach((file, index) => {
                    const isFirst = index === 0;
                    const formHtml = createFileTrackingForm(file, index, isFirst);
                    formContainer.append(formHtml);
                });
                
                // Add submit button
                formContainer.append(`
                    <div class="flex justify-end space-x-4 pt-6">
                        <a href="{{ route('filetracker.index') }}" 
                           class="border rounded-md px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Create Batch Tracking (${selectedFiles.length} files)
                        </button>
                    </div>
                `);
            }
            
            // Create individual file tracking form
            function createFileTrackingForm(file, index, isExpanded) {
                const expandedClass = isExpanded ? '' : 'collapsed';
                const displayStyle = isExpanded ? 'block' : 'none';
                
                return `
                    <div class="bg-white rounded-lg shadow-sm border mb-6 file-form ${expandedClass}">
                        <div class="px-6 py-4 border-b cursor-pointer form-header" data-index="${index}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center">
                                        <span class="text-lg font-semibold text-gray-900">${file.file_number}</span>
                                        <span class="ml-2 text-sm text-gray-500">${file.file_title || 'No Title'}</span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Batch Processing
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-500">Form ${index + 1} of ${selectedFiles.length}</span>
                                    <svg class="h-5 w-5 text-gray-400 transform transition-transform form-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-content" style="display: ${displayStyle};">
                            <div class="p-6 space-y-6">
                                <input type="hidden" name="files[${index}][file_indexing_id]" value="${file.id}">
                                
                                <!-- File Info Display -->
                                <div class="bg-gray-50 rounded-md p-4">
                                    <h4 class="font-medium text-gray-900 mb-2">File Information</h4>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>File Number:</strong> ${file.file_number}</div>
                                        <div><strong>Title:</strong> ${file.file_title || 'No Title'}</div>
                                        <div><strong>Land Use:</strong> ${file.land_use_type || 'N/A'}</div>
                                        <div><strong>District:</strong> ${file.district || 'N/A'}</div>
                                    </div>
                                </div>
                                
                                <!-- Tracking Information -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Location <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="files[${index}][current_location]" 
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="e.g., Archive Room A, Legal Department"
                                               required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Handler <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               name="files[${index}][current_handler]" 
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="e.g., John Doe, Department Head"
                                               required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Holder
                                        </label>
                                        <input type="text" 
                                               name="files[${index}][current_holder]" 
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="e.g., Legal Department, Survey Unit">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Status <span class="text-red-500">*</span>
                                        </label>
                                        <select name="files[${index}][status]" 
                                                class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                required>
                                            <option value="">Select Status</option>
                                            <option value="in_process" selected>In Process</option>
                                            <option value="pending">Pending</option>
                                            <option value="on_hold">On Hold</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Date Received <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" 
                                               name="files[${index}][date_received]" 
                                               value="${new Date().toISOString().split('T')[0]}"
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Due Date
                                        </label>
                                        <input type="date" 
                                               name="files[${index}][due_date]" 
                                               class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                
                                <!-- Notes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Initial Notes
                                    </label>
                                    <textarea name="files[${index}][notes]" 
                                              rows="3"
                                              class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="Add any initial notes about this file tracking..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Handle form header clicks (expand/collapse)
            $(document).on('click', '.form-header', function() {
                const $form = $(this).closest('.file-form');
                const $content = $form.find('.form-content');
                const $chevron = $(this).find('.form-chevron');
                
                if ($form.hasClass('collapsed')) {
                    $form.removeClass('collapsed');
                    $content.slideDown();
                    $chevron.removeClass('rotate-180');
                } else {
                    $form.addClass('collapsed');
                    $content.slideUp();
                    $chevron.addClass('rotate-180');
                }
            });
            
            // File search functionality (for single mode)
            if (!isBatchMode) {
                $('#file_search').on('input', function() {
                    const query = $(this).val().trim();
                    
                    clearTimeout(searchTimeout);
                    
                    if (query.length < 2) {
                        $('#file_search_results').addClass('hidden');
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        searchFiles(query);
                    }, 300);
                });
                
                // Hide results when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#file_search, #file_search_results').length) {
                        $('#file_search_results').addClass('hidden');
                    }
                });
            }
            
            function searchFiles(query) {
                $.ajax({
                    url: '{{ route("filetracker.search-files") }}',
                    method: 'GET',
                    data: { query: query },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            displaySearchResults(response.data);
                        } else {
                            displayNoResults();
                        }
                    },
                    error: function() {
                        displayError();
                    }
                });
            }
            
            function displaySearchResults(files) {
                let html = '';
                files.forEach(function(file) {
                    html += `
                        <div class="file-result p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0" 
                             data-file-id="${file.id}" 
                             data-file-number="${file.file_number}"
                             data-file-title="${file.file_title || ''}"
                             data-old-file-number="${file.old_file_number || ''}"
                             data-survey-plan="${file.survey_plan_number || ''}">
                            <div class="font-medium text-gray-900">${file.file_number}</div>
                            <div class="text-sm text-gray-600">${file.file_title || 'No Title'}</div>
                            ${file.old_file_number ? `<div class="text-xs text-gray-500">Old: ${file.old_file_number}</div>` : ''}
                            ${file.survey_plan_number ? `<div class="text-xs text-gray-500">Survey: ${file.survey_plan_number}</div>` : ''}
                        </div>
                    `;
                });
                
                $('#file_search_results').html(html).removeClass('hidden');
            }
            
            function displayNoResults() {
                $('#file_search_results').html(`
                    <div class="p-3 text-center text-gray-500 text-sm">
                        No files found matching your search
                    </div>
                `).removeClass('hidden');
            }
            
            function displayError() {
                $('#file_search_results').html(`
                    <div class="p-3 text-center text-red-500 text-sm">
                        Error searching files. Please try again.
                    </div>
                `).removeClass('hidden');
            }
            
            // Handle file selection (single mode)
            $(document).on('click', '.file-result', function() {
                const fileId = $(this).data('file-id');
                const fileNumber = $(this).data('file-number');
                const fileTitle = $(this).data('file-title');
                const oldFileNumber = $(this).data('old-file-number');
                const surveyPlan = $(this).data('survey-plan');
                
                // Set the hidden input
                $('#file_indexing_id').val(fileId);
                
                // Update the search input
                $('#file_search').val(fileNumber);
                
                // Show selected file info
                let detailsHtml = `
                    <div><strong>File Number:</strong> ${fileNumber}</div>
                    ${fileTitle ? `<div><strong>Title:</strong> ${fileTitle}</div>` : ''}
                    ${oldFileNumber ? `<div><strong>Old File Number:</strong> ${oldFileNumber}</div>` : ''}
                    ${surveyPlan ? `<div><strong>Survey Plan:</strong> ${surveyPlan}</div>` : ''}
                `;
                
                $('#selected_file_details').html(detailsHtml);
                $('#selected_file_info').removeClass('hidden');
                
                // Hide results
                $('#file_search_results').addClass('hidden');
            });
        });
    </script>
@endsection