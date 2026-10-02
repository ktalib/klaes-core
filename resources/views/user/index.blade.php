@extends('layouts.app')
@section('page-title')
{{ __('Users') }}
@endsection
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
 
@section('content')
     <!-- Hide Alpine x-cloak elements until initialized -->
     <style>
        [x-cloak] { display: none !important; }
        .user-avatar {
            transition: transform 0.2s ease-in-out;
        }
        .user-avatar:hover {
            transform: scale(1.1);
        }
        .table-row {
            transition: all 0.2s ease-in-out;
        }
        .table-row:hover {
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
        /* Users table */
        .users-table thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            padding: 0.75rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .users-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .users-table tbody tr:hover {
            background-color: #f0f4ff;
        }
        .users-table thead th a {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: inherit;
        }
        .users-table thead th a:hover {
            color: #4f46e5;
        }
        /* Above the row action menu, which teleports to <body> at z-index 9999. */
        .swal-topmost {
            z-index: 100000;
        }
     </style>
    <!-- Main Content -->
    <div class="flex-1 overflow-auto" x-data="{ 
        rolesModalOpen: false, 
        modalRoles: ''
    }">
    <!-- Enhanced Roles Modal -->
    <div x-show="rolesModalOpen" x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" 
         @click.away="rolesModalOpen = false">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-xl shadow-2xl overflow-hidden w-full max-w-md mx-auto">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">{{ __('All User Roles') }}</h3>
                </div>
                <button class="text-white hover:text-gray-200 transition-colors duration-200 p-1 rounded-full hover:bg-white hover:bg-opacity-20" 
                        @click="rolesModalOpen = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 mb-4">{{ __('Complete list of roles assigned to this user:') }}</p>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="role in (modalRoles ? modalRoles.split(', ') : [])" :key="role">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span x-text="role.trim()"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                <button @click="rolesModalOpen = false" 
                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6 space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">{{ __('Total Users') }}</p>
                            <p class="text-3xl font-bold">{{ $userStats['total'] ?? $users->count() }}</p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">{{ __('Admin Users') }}</p>
                            <p class="text-3xl font-bold">{{ $userStats['admins'] ?? 0 }}</p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">{{ __('Regular Users') }}</p>
                            <p class="text-3xl font-bold">{{ $userStats['regular'] ?? 0 }}</p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-indigo-100 text-sm font-medium">{{ __('New This Month') }}</p>
                            <p class="text-3xl font-bold">{{ $userStats['new_this_month'] ?? 0 }}</p>
                        </div>
                        <div class="bg-indigo-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <!-- Enhanced Header -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-100 rounded-lg p-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-xl font-bold text-gray-900">{{ __('User Management') }}</h5>
                                <p class="text-sm text-gray-600">{{ __('Manage and monitor all system users') }}</p>
                            </div>
                        </div>
                         
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                            {{-- A CSV import creates users in bulk, so it is gated as 'create'. --}}
                            @canDo('User Account', 'create')
                            <!-- Import Users Button -->
                            <button onclick="openImportModal()" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Import CSV') }}
                            </button>
                            @endcanDo

                            @canDo('User Account', 'create')
                            <!-- Create User Button -->
                            <a href="#" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 customModal"
                               data-size="xxl" data-url="{{ route('users.create') }}" data-title="{{ __('Create New User') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Create User') }}
                            </a>
                            @endcanDo
                        </div>
                    </div>
                </div>
                <div class="px-6 pt-6 border-b border-gray-200 bg-white">
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach ([
                            'mdc' => __('MDC - Mass Data Capture'),
                            'mlpp' => __('MLPP - Ministry of Land and Physical Planning'),
                        ] as $tabKey => $tabLabel)
                            @php $isActiveTab = $activeTab === $tabKey; @endphp
                            {{-- Tabs are links, not Alpine state: each tab is its own paginated query. --}}
                            <a href="{{ route('users.index', ['tab' => $tabKey, 'search' => $search, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $direction]) }}"
                               class="inline-flex items-center px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-200 {{ $isActiveTab ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-200/60' : 'bg-white text-gray-600 border-gray-200 hover:text-indigo-600 hover:border-indigo-300' }}">
                                <span>{{ $tabLabel }}</span>
                                <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full {{ $isActiveTab ? 'bg-white text-indigo-600' : 'bg-indigo-50 text-indigo-600' }}">
                                    {{ $tabCounts[$tabKey] ?? 0 }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Search / page size -->
                <div class="px-6 py-4 bg-white border-b border-gray-200">
                    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="dir" value="{{ $direction }}">
                        <div class="relative flex-1">
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                            </svg>
                            <input type="search" name="search" value="{{ $search }}"
                                   placeholder="{{ __('Search name, username, email, phone or role...') }}"
                                   class="search-input w-full rounded-lg border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="per_page" class="text-sm text-gray-600 whitespace-nowrap">{{ __('Show') }}</label>
                            <select id="per_page" name="per_page" onchange="this.form.submit()"
                                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                {{ __('Search') }}
                            </button>
                            @if ($search !== '')
                                <a href="{{ route('users.index', ['tab' => $activeTab, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $direction]) }}"
                                   class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg border border-gray-200 hover:bg-gray-50">
                                    {{ __('Clear') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Enhanced Table -->
                <div class="overflow-hidden">
                    @include('user.partials.users-table', [
                        'users' => $users,
                        'tableId' => $activeTab . 'UsersTable',
                        'emptyStateTitle' => $activeTab === 'mdc' ? __('No MDC users found') : __('No MLPP users found'),
                        'emptyStateSubtitle' => $search !== ''
                            ? __('No user matches ":term". Try a different search.', ['term' => $search])
                            : ($activeTab === 'mdc' ? __('Invite new MDC users or adjust filters.') : __('Create a new MLPP user or adjust filters.')),
                    ])

                    <div class="px-6 py-4 border-t border-gray-200 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-sm text-gray-600">
                            {{ __('Showing :from to :to of :total users', [
                                'from' => $users->total() ? $users->firstItem() : 0,
                                'to' => $users->total() ? $users->lastItem() : 0,
                                'total' => $users->total(),
                            ]) }}
                        </p>
                        <div>{{ $users->links() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

<script>
$(document).ready(function() {
    function attachModalHandlers() {
        $('.customModal').off('click').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('url');
            var size = $(this).data('size') || 'md';
            var title = $(this).data('title');
            if (typeof showModal === 'function') {
                showModal(url, size, title);
            }
        });
    }

    /**
     * Confirm styles per action. `danger` is the default because everything
     * wearing .confirm_dialog on this page is irreversible or locks someone out.
     */
    const CONFIRM_VARIANTS = {
        danger:  { icon: 'warning',  colour: '#dc2626' },
        warning: { icon: 'warning',  colour: '#d97706' },
        info:    { icon: 'question', colour: '#4f46e5' }
    };

    /**
     * Resolves true when the user confirms.
     *
     * Falls back to the native dialog if SweetAlert has not loaded — this page
     * gets it from layouts/app.blade.php, but a confirmation must never be
     * skipped just because a CDN was unreachable.
     */
    function confirmAction(options) {
        if (typeof Swal === 'undefined') {
            return Promise.resolve(window.confirm(options.text));
        }

        const variant = CONFIRM_VARIANTS[options.variant] || CONFIRM_VARIANTS.danger;

        return Swal.fire({
            title: options.title,
            text: options.text,
            icon: variant.icon,
            showCancelButton: true,
            confirmButtonText: options.confirmText,
            cancelButtonText: @json(__('Cancel')),
            confirmButtonColor: variant.colour,
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true,
            // The row action menu sits at z-index 9999; keep the dialog above it
            // so a slow dropdown close cannot overlap the confirmation.
            customClass: { container: 'swal-topmost' }
        }).then(function (result) {
            return result.isConfirmed === true;
        });
    }

    /**
     * Outcome message. Falls back to alert() for the same reason confirmAction
     * falls back to confirm() — the user must still be told what happened.
     */
    function notify(icon, message) {
        if (typeof Swal === 'undefined') {
            window.alert(message);
            return Promise.resolve();
        }

        return Swal.fire({
            icon: icon,
            text: message,
            confirmButtonColor: '#4f46e5',
            customClass: { container: 'swal-topmost' }
        });
    }

    function attachConfirmDialogs() {
        $('.confirm_dialog').off('click').on('click', function(e) {
            // SweetAlert answers asynchronously, so the click's own default can
            // never be the thing that submits — it is always cancelled here and
            // the form is submitted by hand once the user has confirmed.
            e.preventDefault();

            const $trigger = $(this);
            const form = $trigger.closest('form').get(0);

            confirmAction({
                title: $trigger.data('dialog-title') || @json(__('Please confirm')),
                text: $trigger.data('dialog-text') || '',
                confirmText: $trigger.data('dialog-confirm') || @json(__('Yes, continue')),
                variant: $trigger.data('dialog-variant')
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                if (form) {
                    form.submit();
                } else if ($trigger.attr('href')) {
                    window.location = $trigger.attr('href');
                }
            });
        });
    }

    attachModalHandlers();
    attachConfirmDialogs();

    window.openImportModal = function() {
        $.ajax({
            url: '{{ route("users.import.form") }}',
            type: 'GET',
            dataType: 'html',
            success: function(html) {
                let modalId = 'importModal_' + Date.now();
                let modalHTML = `
                    <div id="${modalId}" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                        <div class="my-auto w-full">
                            ${html}
                        </div>
                    </div>
                `;
                
                $('body').append(modalHTML);
                window.currentModalId = modalId;
            },
            error: function() {
                notify('error', @json(__('Failed to load import form')));
            }
        });
    };

    window.clearTestData = function() {
        // Reached from the Import CSV modal, which sits above this page.
        confirmAction({
            title: @json(__('Delete all TEST users?')),
            text: @json(__('Every user in the TEST environment will be permanently deleted. This cannot be undone.')),
            confirmText: @json(__('Yes, delete them')),
            variant: 'danger'
        }).then(function (confirmed) {
            if (confirmed) {
                clearTestDataRequest();
            }
        });
    };

    function clearTestDataRequest() {
        $.ajax({
            url: '{{ route("users.clear.test") }}',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    notify('success', response.message).then(function () {
                        location.reload();
                    });
                } else {
                    notify('error', response.message);
                }
            },
            error: function(xhr) {
                let errorMsg = @json(__('Error clearing test data'));
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                notify('error', errorMsg);
            }
        });
    }
});
</script>
    
@endsection
