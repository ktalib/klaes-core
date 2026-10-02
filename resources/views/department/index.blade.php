@extends('layouts.app')

@section('page-title')
    {{ __('Departments') }}
@endsection

@section('content')
    <!-- Hide Alpine x-cloak elements until initialized -->
    <style>
        [x-cloak] { display: none !important; }
        .department-card {
            transition: all 0.2s ease-in-out;
        }
        .department-card:hover {
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
        filteredDepartments: [],
        init() {
            this.filteredDepartments = this.getAllDepartments();
        },
        getAllDepartments() {
            return Array.from(document.querySelectorAll('tbody tr')).map(row => ({
                element: row,
                name: row.querySelector('.dept-name')?.textContent.toLowerCase() || '',
                code: row.querySelector('.dept-code')?.textContent.toLowerCase() || '',
                description: row.querySelector('.dept-description')?.textContent.toLowerCase() || ''
            }));
        },
        filterDepartments() {
            const term = this.searchTerm.toLowerCase();
            this.filteredDepartments.forEach(dept => {
                const matches = dept.name.includes(term) || 
                               dept.code.includes(term) || 
                               dept.description.includes(term);
                dept.element.style.display = matches ? '' : 'none';
            });
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
                            <p class="text-blue-100 text-sm font-medium">{{ __('Total Departments') }}</p>
                            <p class="text-3xl font-bold">{{ $departments->count() }}</p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">{{ __('Active Departments') }}</p>
                            <p class="text-3xl font-bold">{{ $departments->where('is_active', true)->count() }}</p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">{{ __('Inactive Departments') }}</p>
                            <p class="text-3xl font-bold">{{ $departments->where('is_active', false)->count() }}</p>
                        </div>
                        <div class="bg-red-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-indigo-100 text-sm font-medium">{{ __('New This Month') }}</p>
                            <p class="text-3xl font-bold">{{ $departments->where('created_at', '>=', now()->startOfMonth())->count() }}</p>
                        </div>
                        <div class="bg-indigo-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Departments Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <!-- Enhanced Header -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-100 rounded-lg p-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-xl font-bold text-gray-900">{{ __('Department Management') }}</h5>
                                <p class="text-sm text-gray-600">{{ __('Manage and organize company departments') }}</p>
                            </div>
                        </div>
                         
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                            <!-- Search Input -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <input type="text" 
                                       x-model="searchTerm" 
                                       @input="filterDepartments()"
                                       class="search-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                                       placeholder="{{ __('Search departments...') }}">
                            </div>

                                                        @canDo('Departments', 'create')
<!-- Create Department Button -->
                            <a href="#" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 customModal" 
                               data-url="{{ route('departments.create') }}" data-title="{{ __('Create New Department') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Create Department') }}
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
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span>{{ __('Department Name') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                            </svg>
                                            <span>{{ __('Code') }}</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b-2 border-gray-200">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span>{{ __('Description') }}</span>
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
                                @forelse ($departments as $department)
                                    <tr class="department-card hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200">
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-semibold text-gray-900 dept-name">{{ $department->name }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ __('Created') }} 
                                                        @if($department->created_at instanceof \Carbon\Carbon)
                                                            {{ $department->created_at->format('M d, Y') }}
                                                        @else
                                                            {{ \Carbon\Carbon::parse($department->created_at)->format('M d, Y') }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dept-code">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                                </svg>
                                                {{ $department->code }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-5">
                                            <div class="text-sm text-gray-900 dept-description">
                                                {{ Str::limit($department->description, 60) }}
                                            </div>
                                            @if(strlen($department->description) > 60)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    {{ __('Click to view full description') }}
                                                </div>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-5 whitespace-nowrap">
                                            @if($department->is_active)
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
                                                                                                @canDo('Departments', 'edit')
<!-- Edit Button -->
                                                <button type="button" class="action-btn inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600 hover:bg-green-200 hover:text-green-700 transition-all duration-200 customModal" 
                                                        title="{{ __('Edit Department') }}" data-url="{{ route('departments.edit', $department->id) }}" data-title="{{ __('Edit Department') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                @endcanDo
                                                
                                                                                                @canDo('Departments', 'delete')
<!-- Delete Button -->
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['departments.destroy', $department->id], 'class' => 'inline-block']) !!}
                                                    <button type="button" class="action-btn inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-700 transition-all duration-200 confirm_dialog" 
                                                            title="{{ __('Delete Department') }}">
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
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center space-y-3">
                                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                </svg>
                                                <div class="text-gray-500">
                                                    <p class="text-lg font-medium">{{ __('No departments found') }}</p>
                                                    <p class="text-sm">{{ __('Get started by creating your first department.') }}</p>
                                                </div>
                                                                                                @canDo('Departments', 'create')
<a href="#" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md transition-colors customModal" 
                                                   data-url="{{ route('departments.create') }}" data-title="{{ __('Create New Department') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ __('Create Department') }}
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
