@extends('layouts.app')
@section('page-title')
    {{ __('SLTR Applications') }}
@endsection

@section('content')

 @include('sltrapplication.assets.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto bg-gray-50">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
 
         <!-- Main Dashboard View -->
    <div id="dashboard-view">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-4">
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="rounded-full bg-blue-100 p-3 mb-2">
                            <i data-lucide="home" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <h3 class="text-lg font-medium">Residential</h3>
                        <p class="text-sm text-muted-foreground mb-2">124 Applications</p>
                        <p class="text-xs text-green-600">32 Approved</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-4">
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="rounded-full bg-purple-100 p-3 mb-2">
                            <i data-lucide="building" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <h3 class="text-lg font-medium">Commercial</h3>
                        <p class="text-sm text-muted-foreground mb-2">87 Applications</p>
                        <p class="text-xs text-green-600">45 Approved</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-4">
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="rounded-full bg-amber-100 p-3 mb-2">
                            <i data-lucide="warehouse" class="h-6 w-6 text-amber-600"></i>
                        </div>
                        <h3 class="text-lg font-medium">Warehouse</h3>
                        <p class="text-sm text-muted-foreground mb-2">36 Applications</p>
                        <p class="text-xs text-green-600">18 Approved</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-4">
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="rounded-full bg-green-100 p-3 mb-2">
                            <i data-lucide="tractor" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <h3 class="text-lg font-medium">Agriculture</h3>
                        <p class="text-sm text-muted-foreground mb-2">52 Applications</p>
                        <p class="text-xs text-green-600">29 Approved</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
                <div class="relative w-full md:w-300">
                    <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                    <input id="search-input" type="text" placeholder="Search applications..." class="block w-full rounded-md border border-gray-200 pl-8 pr-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20 outline-none transition-colors">
                </div>

                <div class="flex gap-2">
                    <select id="type-filter" class="custom-select block w-180 rounded-md border border-gray-200 px-3 py-2 pr-10 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20 outline-none transition-colors appearance-none">
                        <option value="all">All Types</option>
                        <option value="residential">Residential</option>
                        <option value="commercial">Commercial</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="agriculture">Agriculture</option>
                    </select>

                    <select id="sort-filter" class="custom-select block w-180 rounded-md border border-gray-200 px-3 py-2 pr-10 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20 outline-none transition-colors appearance-none">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name">Applicant Name</option>
                        <option value="status">Status</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2 w-full md:w-auto">
                <button id="export-btn" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors w-full md:w-auto">
                    <i data-lucide="download" class="h-4 w-4 mr-2"></i>
                    Export
                </button>

                <div class="relative inline-block">
                    <button id="new-application-btn" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors w-full md:w-auto">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        New Application
                        <i data-lucide="chevron-down" class="h-4 w-4 ml-2"></i>
                    </button>

                    <div id="application-dropdown" class="dropdown-content absolute top-full right-0 z-50 bg-white border border-gray-200 rounded-lg shadow-lg py-2 min-w-56 mt-1">
                        <button class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors" data-type="residential">
                            <i data-lucide="home" class="h-4 w-4 mr-2 text-blue-500"></i>
                            Residential
                        </button>
                        <button class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors" data-type="commercial">
                            <i data-lucide="building" class="h-4 w-4 mr-2 text-purple-500"></i>
                            Commercial
                        </button>
                        <button class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors" data-type="warehouse">
                            <i data-lucide="warehouse" class="h-4 w-4 mr-2 text-amber-500"></i>
                            Warehouse
                        </button>
                        <button class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors" data-type="agriculture">
                            <i data-lucide="tractor" class="h-4 w-4 mr-2 text-green-500"></i>
                            Agriculture
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Applications Table -->  
        @include('sltrapplication.partial.table')

    </div>

    <!-- Application Form Modal -->
    <div id="application-form-modal" class="modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="modal-content bg-white rounded-lg max-w-4xl w-11/12 max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 id="form-title" class="text-xl font-semibold">New SLTR Application</h2>
                    <button id="close-form-btn" class="inline-flex items-center justify-center rounded-md bg-transparent p-2 text-gray-700 hover:bg-gray-100 transition-colors">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
            <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
                <div class="text-center py-8">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <i data-lucide="file-text" class="h-6 w-6"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">Application Form</h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        The detailed SLTR application form would be displayed here.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        This would include fields for applicant information, property details, 
                        document uploads, and all required SLTR application data.
                    </p>
                </div>
            </div>
        </div>
    </div>
       </div>
   
        <!-- Footer -->
        @include('admin.footer')
        @include('sltrapplication.assets.js')
    </div>
 
@endsection
