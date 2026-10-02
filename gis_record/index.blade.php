@extends('layouts.app')
@section('page-title')
    {{ __('GIS Data Management') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<style>
    /* Required for proper z-index stacking and overflow */
    .dropdown-wrapper { position: relative; }
    .dropdown-menu { z-index: 1000; }
    /* Ensure overflow is visible */
    .overflow-x-auto { overflow-y: visible !important; }
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
                <h2 class="text-xl font-bold">GIS Data Records</h2>
               <div class="relative inline-block text-left">
              <a href="{{ route('gis_record.create', ['is' => 'primary']) }}" class="flex items-center space-x-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
              <i data-lucide="plus" class="w-4 h-4"></i>
              <span> Create New GIS Record </span>
              
              </a>
              
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
              const dropdownButton = document.getElementById('survey-dropdown-button');
              const dropdownMenu = document.getElementById('survey-dropdown-menu');
              
              dropdownButton.addEventListener('click', function() {
              dropdownMenu.classList.toggle('hidden');
              });
              
              document.addEventListener('click', function(event) {
              if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
              dropdownMenu.classList.add('hidden');
              }
              });
              });
            </script>
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

            <!-- Data Table -->
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
                        <tr class="text-xs">
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
                            <td class="table-cell px-1 py-1 truncate">
                                <div class="dropdown-wrapper" x-data="{ 
                                    open: false,
                                    toggle() {
                                        this.open = !this.open;
                                        if (this.open) {
                                            this.$nextTick(() => {
                                                // Get elements
                                                const button = this.$refs.button;
                                                const dropdown = this.$refs.dropdown;
                                                const buttonRect = button.getBoundingClientRect();
                                                
                                                // Calculate appropriate position
                                                const dropdownWidth = dropdown.offsetWidth;
                                                
                                                // Position dropdown to appear on the right of the button, not the left edge
                                                // This ensures it's not at the screen edge
                                                dropdown.style.position = 'fixed';
                                                
                                                // Align right edge of dropdown with right edge of the viewport, 
                                                // but not further right than button position
                                                const rightEdge = Math.min(window.innerWidth - 10, buttonRect.right + 10);
                                                dropdown.style.left = (rightEdge - dropdownWidth) + 'px';
                                                
                                                // Decide whether to show above or below
                                                const spaceBelow = window.innerHeight - buttonRect.bottom;
                                                const dropdownHeight = dropdown.offsetHeight;
                                                const showAbove = spaceBelow < dropdownHeight;
                                                
                                                if (showAbove) {
                                                    dropdown.style.bottom = (window.innerHeight - buttonRect.top) + 'px';
                                                    dropdown.style.top = 'auto';
                                                } else {
                                                    dropdown.style.top = buttonRect.bottom + 'px';
                                                    dropdown.style.bottom = 'auto';
                                                }
                                            });
                                        }
                                    }
                                }">
                                    <button 
                                        x-ref="button" 
                                        @click.prevent="toggle()" 
                                        class="text-gray-600 hover:text-blue-600 p-1 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 ml-auto block">
                                        <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                    </button>
                                    
                                    <div 
                                        x-ref="dropdown"
                                        x-show="open" 
                                        @click.away="open = false"
                                        x-transition
                                        class="dropdown-menu w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
                                        style="display: none; z-index: 9999;"
                                    >
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
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
    });
</script>
@endsection
