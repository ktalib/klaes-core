@extends('layouts.app')

@section('page-title')
    {{ __('User Roles') }}
@endsection

@section('content')
    <!-- Hide Alpine x-cloak elements until initialized -->
    <style>
        [x-cloak] { display: none !important; }
        .role-card {
            transition: all 0.2s ease-in-out;
        }
        .role-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .action-btn {
            transition: all 0.2s ease-in-out;
        }
        .action-btn:hover {
            transform: scale(1.1);
        }
        .search-input {
            transition: all 0.3s ease;
        }
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
    </style>
    
    <!-- Main Content -->
    <div class="flex-1 overflow-auto" x-data="{ 
        searchTerm: '',
        filteredRoles: [],
        selectedRoles: [],
        init() {
            this.filteredRoles = this.getAllRoles();
        },
        getAllRoles() {
            return Array.from(document.querySelectorAll('tbody tr:not(.empty-state)')).map(row => ({
                element: row,
                id: row.getAttribute('data-id'),
                name: row.querySelector('.role-name')?.textContent.toLowerCase() || '',
                department: row.querySelector('.role-department')?.textContent.toLowerCase() || '',
                userType: row.querySelector('.role-user-type')?.textContent.toLowerCase() || '',
                level: row.querySelector('.role-level')?.textContent.toLowerCase() || ''
            }));
        },
        filterRoles() {
            const term = this.searchTerm.toLowerCase();
            this.filteredRoles.forEach(role => {
                const matches = role.name.includes(term) || 
                               role.department.includes(term) || 
                               role.userType.includes(term) ||
                               role.level.includes(term);
                role.element.style.display = matches ? '' : 'none';
            });
        },
        toggleAll(checked) {
            if (checked) {
                this.selectedRoles = this.filteredRoles.map(r => r.id);
            } else {
                this.selectedRoles = [];
            }
        },
        async bulkDelete() {
            if (this.selectedRoles.length === 0) return;
            
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${this.selectedRoles.length} selected roles. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete selected'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('{{ route('user-roles.bulk-delete') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ ids: this.selectedRoles })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        Swal.fire('Deleted!', data.message, 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete roles', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'An error occurred while deleting roles', 'error');
                }
            }
        }
    }">
        <!-- Header -->
        @include('admin.header')
        
        <!-- Dashboard Content -->
        <div class="p-6 space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">{{ __('Total Roles') }}</p>
                            <p class="text-3xl font-bold">{{ $userRoles->count() }}</p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">{{ __('Active Roles') }}</p>
                            <p class="text-3xl font-bold">{{ $userRoles->where('is_active', true)->count() }}</p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">{{ __('System Level') }}</p>
                            <p class="text-3xl font-bold">{{ $userRoles->where('level', 'system')->count() }}</p>
                        </div>
                        <div class="bg-red-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div> --}}

                {{-- <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-indigo-100 text-sm font-medium">{{ __('Management Level') }}</p>
                            <p class="text-3xl font-bold">{{ $userRoles->where('level', 'management')->count() }}</p>
                        </div>
                        <div class="bg-indigo-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                        </div>
                    </div>
                </div> --}}
            </div>

            <!-- User Roles Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <!-- Enhanced Header -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-100 rounded-lg p-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-xl font-bold text-gray-900">{{ __('User Role Management') }}</h5>
                                <p class="text-sm text-gray-600">{{ __('Manage user roles and permissions') }}</p>
                            </div>
                        </div>
                         
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                            @canDo('User Roles', 'delete')
                            <!-- Bulk Delete Button -->
                            <button type="button"
                                    x-show="selectedRoles.length > 0"
                                    @click="bulkDelete()"
                                    class="inline-flex items-center justify-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                                    x-cloak>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('Delete Selected') }} (<span x-text="selectedRoles.length"></span>)
                            </button>
                            @endcanDo

                            <!-- Search Input -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <input type="text" 
                                       x-model="searchTerm" 
                                       @input="filterRoles()"
                                       class="search-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                                       placeholder="{{ __('Search roles...') }}">
                            </div>

                            @canDo('User Roles', 'create')
                            <!-- Create Role Button -->
                            <a href="#" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 customModal"
                               data-url="{{ route('user-roles.create') }}" data-title="{{ __('Create New User Role') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Create User Role') }}
                            </a>
                            @endcanDo
                        </div>
                    </div>
                </div>

                <!-- Enhanced Table -->
                <div class="overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left border-b-2 border-gray-200">
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                   @change="toggleAll($el.checked)"
                                                   :checked="selectedRoles.length === filteredRoles.length && filteredRoles.length > 0"
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                            </svg>
                                            <span>{{ __('Role Name') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span>{{ __('Department') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                            </svg>
                                            <span>{{ __('User Type') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/>
                                            </svg>
                                            <span>{{ __('Level') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ __('Status') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center justify-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                            <span>{{ __('Actions') }}</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($userRoles as $role)
                                    <tr class="role-card hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200" data-id="{{ $role->id }}">
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            <input type="checkbox" 
                                                   x-model="selectedRoles" 
                                                   value="{{ $role->id }}"
                                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        </td>
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-semibold text-gray-900 role-name">{{ $role->name }}</div>
                                                    
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            @if($role->department)
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-800 role-department">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                    </svg>
                                                    {{ $role->department->name }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 role-department">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                                    </svg>
                                                    {{ __('No Department') }}
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            @php
                                                $userTypeColors = [
                                                    'ALL' => 'bg-gray-100 text-gray-800',
                                                    'Management' => 'bg-blue-100 text-blue-800',
                                                    'Operations' => 'bg-green-100 text-green-800',
                                                    'User' => 'bg-yellow-100 text-yellow-800',
                                                    'System_Highest' => 'bg-red-100 text-red-800'
                                                ];
                                                $userTypeClass = $userTypeColors[$role->user_type] ?? 'bg-purple-100 text-purple-800';
                                            @endphp
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $userTypeClass }} role-user-type">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                </svg>
                                                {{ $role->user_type }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            @php
                                                $levelColors = [
                                                    'system' => 'bg-red-100 text-red-800',
                                                    'management' => 'bg-blue-100 text-blue-800',
                                                    'operational' => 'bg-green-100 text-green-800'
                                                ];
                                                $levelClass = $levelColors[$role->level] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $levelClass }} role-level">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    @if($role->level == 'system')
                                                        <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"/>
                                                    @elseif($role->level == 'management')
                                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                    @else
                                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    @endif
                                                </svg>
                                                {{ ucfirst($role->level) }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            @if($role->is_active)
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ __('Active') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ __('Inactive') }}
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center space-x-3">
                                                @canDo('User Roles', 'edit')
                                                <!-- Edit Button -->
                                                <button type="button" class="action-btn inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600 hover:bg-green-200 hover:text-green-700 transition-all duration-200 customModal" 
                                                        title="{{ __('Edit Role') }}" data-url="{{ route('user-roles.edit', $role->id) }}" data-title="{{ __('Edit User Role') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                @endcanDo

                                                @canDo('User Roles', 'delete')
                                                <!-- Delete Button -->
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['user-roles.destroy', $role->id], 'class' => 'inline-block']) !!}
                                                    <button type="button" class="action-btn inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-700 transition-all duration-200 confirm_dialog" 
                                                            title="{{ __('Delete Role') }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                {!! Form::close() !!}
                                                @endcanDo
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-state">
                                        <td colspan="7" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center space-y-3">
                                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                                </svg>
                                                <div class="text-gray-500">
                                                    <p class="text-lg font-medium">{{ __('No user roles found') }}</p>
                                                    <p class="text-sm">{{ __('Get started by creating your first user role.') }}</p>
                                                </div>
                                                @canDo('User Roles', 'create')
                                                <a href="#" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md transition-colors customModal"
                                                   data-url="{{ route('user-roles.create') }}" data-title="{{ __('Create New User Role') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ __('Create User Role') }}
                                                </a>
                                                @endcanDo
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>
@endsection
