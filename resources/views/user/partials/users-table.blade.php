@php
    // Column key => label. A key of null means the column is not sortable server-side.
    $columns = [
        ['key' => 'name', 'label' => __('User Profile'), 'class' => ''],
        ['key' => 'username', 'label' => __('Username'), 'class' => ''],
        ['key' => 'email', 'label' => __('Email'), 'class' => ''],
        ['key' => 'user_level', 'label' => __('User Level'), 'class' => ''],
        ['key' => null, 'label' => __('Department'), 'class' => ''],
        ['key' => null, 'label' => __('Role'), 'class' => ''],
        ['key' => null, 'label' => __('Work Days'), 'class' => 'text-center'],
        ['key' => null, 'label' => __('Man Hours'), 'class' => 'text-center'],
        ['key' => 'staff_type', 'label' => __('Staff Type'), 'class' => 'text-center'],
        ['key' => null, 'label' => __('User Actions'), 'class' => ''],
        ['key' => null, 'label' => __('Actions'), 'class' => ''],
    ];
@endphp
<div class="overflow-x-auto">
    <table id="{{ $tableId }}" class="users-table min-w-full divide-y divide-gray-200" style="width:100%;">
        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ $column['class'] }}">
                        @if ($column['key'])
                            @php
                                $isSorted = $sort === $column['key'];
                                $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                            @endphp
                            <a href="{{ route('users.index', [
                                'tab' => $activeTab,
                                'search' => $search,
                                'per_page' => $perPage,
                                'sort' => $column['key'],
                                'dir' => $nextDirection,
                            ]) }}">
                                <span>{{ $column['label'] }}</span>
                                @if ($isSorted)
                                    <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        @if ($direction === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        @endif
                                    </svg>
                                @else
                                    <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 3l3 4H7l3-4zm0 14l-3-4h6l-3 4z"/>
                                    </svg>
                                @endif
                            </a>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr class="table-row">
                    <td class="px-6 py-5 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 relative">
                                <img class="user-avatar h-12 w-12 rounded-full object-cover border-2 border-gray-200 shadow-sm"
                                    src="{{ $user->profile_url ?? asset(Storage::url('upload/profile')) . '/avatar.png' }}"
                                    alt="User image">
                                <div class="absolute bottom-0 right-0 h-3 w-3 bg-green-400 border-2 border-white rounded-full"></div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-semibold text-gray-900 user-name">
                                    {{ $user->name }}
                                </div>
                                <div class="text-sm text-gray-500 user-phone">
                                    @if(!empty($user->phone_number))
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                            <span>{{ $user->phone_number }}</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic">{{ __('No phone') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(!empty($user->username))
                            <span class="text-sm font-medium text-gray-700 user-username">{{ $user->username }}</span>
                        @else
                            <span class="text-sm text-gray-400 italic">{{ __('No username') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm text-gray-700 user-email">{{ $user->email }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(!empty($user->user_level))
                            @php
                                $levelColors = [
                                    'Administrative' => 'bg-orange-100 text-orange-800',
                                    'Technical' => 'bg-blue-100 text-blue-800',
                                    'Finance' => 'bg-green-100 text-green-800',
                                    'Lowest' => 'bg-gray-100 text-gray-800',
                                    'High' => 'bg-yellow-100 text-yellow-800',
                                    'Highest' => 'bg-red-100 text-red-800'
                                ];
                                $levelColorClass = $levelColors[$user->user_level] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $levelColorClass }}">
                                {{ ucfirst($user->user_level) }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                {{ __('No level') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(!empty($user->department) && is_object($user->department))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-800">
                                {{ $user->department->name ?? 'Unknown Department' }}
                            </span>
                        @elseif(!empty($user->department) && is_string($user->department))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-800">
                                {{ ucfirst($user->department) }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                {{ __('No department') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(!empty($user->assign_role))
                            @php
                                $roles = is_array($user->assign_role) ? $user->assign_role : explode(',', $user->assign_role);
                                $displayRoles = array_slice($roles, 0, 2);
                                $moreRoles = count($roles) > 2;
                            @endphp
                            <div class="flex flex-wrap gap-1">
                                @foreach($displayRoles as $role)
                                    @if(trim($role))
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ ucfirst(trim($role)) }}
                                        </span>
                                    @endif
                                @endforeach
                                @if($moreRoles)
                                    <button class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors" 
                                            @click.prevent="rolesModalOpen = true; modalRoles = '{{ implode(', ', array_map('ucfirst', $roles)) }}'">
                                        +{{ count($roles) - 2 }} {{ __('more') }}
                                    </button>
                                @endif
                            </div>
                        @else  
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                                {{ __('No roles assigned') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap text-center">
                        @if(!empty($user->work_days_per_week))
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-emerald-100 text-emerald-800">
                                {{ $user->work_days_per_week }} {{ __('days') }}
                            </span>
                        @else
                            <span class="text-sm text-gray-400">{{ __('N/A') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap text-center">
                        @if(!empty($user->man_hours_per_day))
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-sky-100 text-sky-800">
                                {{ $user->man_hours_per_day }} {{ __('hrs') }}
                            </span>
                        @else
                            <span class="text-sm text-gray-400">{{ __('N/A') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap text-center">
                        @if(!empty($user->staff_type_category))
                            @php
                                $staffLabels = [
                                    'MDC' => __('MDC - Mass Data Capture'),
                                    'MLPP' => __('MLPP - Ministry of Land and Physical Planning'),
                                ];
                                $normalizedStaffType = strtoupper(trim($user->staff_type_category));
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800">
                                {{ $staffLabels[$normalizedStaffType] ?? $user->staff_type_category }}
                            </span>
                        @else
                            <span class="text-sm text-gray-400">{{ __('N/A') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-5 whitespace-nowrap">
                        @if(!empty($user->user_actions))
                            @php
                                $actions = explode(',', $user->user_actions);
                                $actionColors = [
                                    'create' => 'bg-green-100 text-green-800 border-green-200',
                                    'view' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'view_all' => 'bg-cyan-100 text-cyan-800 border-cyan-200',
                                    'update' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'delete' => 'bg-red-100 text-red-800 border-red-200',
                                ];
                            @endphp
                            <div class="flex flex-wrap gap-1">
                                @foreach($actions as $action)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $actionColors[strtolower(trim($action))] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        {{ ucwords(str_replace('_', ' ', trim($action))) }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-gray-400 italic">{{ __('None') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-5 whitespace-nowrap text-sm font-medium">
                        <div class="relative inline-block text-left"
                             x-data="{ open: false, dropPos: { top: 0, right: 0 } }"
                             @keydown.escape.window="open = false"
                             @scroll.window="open = false">
                            <button type="button"
                                    @click="const r = $event.currentTarget.getBoundingClientRect(); dropPos = { top: r.bottom + 4, right: window.innerWidth - r.right }; open = !open"
                                    class="action-btn inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-800 transition-all duration-200 focus:outline-none"
                                    aria-haspopup="true" :aria-expanded="open">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 9a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 9a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                </svg>
                            </button>
                            <template x-teleport="body">
                                <div x-show="open"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     @click.outside="open = false"
                                     :style="'position:fixed;top:' + dropPos.top + 'px;right:' + dropPos.right + 'px;z-index:9999'"
                                     class="w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                    <div class="py-1">
                                        @canDo('User Account', 'edit')
                                        <button type="button" class="customModal flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors" data-size="xxl" data-url="{{ route('users.edit', $user->id) }}" data-title="{{ __('Edit User') }}" @click="open = false">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            {{ __('Edit User') }}
                                        </button>
                                        @endcanDo

                                        @canDo('User Account', 'edit')
                                        <button type="button" class="customModal flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                            data-size="md" data-url="{{ route('users.profile-photo.edit', $user->id) }}"
                                            data-title="{{ __('Profile Picture') }}" @click="open = false">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {{ __('Profile Picture') }}
                                        </button>
                                        @endcanDo

                                        {{-- "Login as user" borrows this account's session so the admin sees
                                             the system exactly as this officer does. Far stronger than an
                                             edit on User Account, which many desk supervisors hold, so it is
                                             Super Admin only -- the controller enforces the same rule. --}}
                                        @if(auth()->user()?->isSuperAdmin() && (int) $user->id !== (int) auth()->id() && (int) ($user->is_active ?? 1) === 1)
                                        <form method="POST" action="{{ route('impersonate.take', $user->id) }}">
                                            @csrf
                                            <button type="submit" class="confirm_dialog flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors" data-dialog-title="{{ __('Sign in as this user?') }}" data-dialog-text="{{ __('You will see the system as :name until you return to your own account. Anything you do will be recorded against them.', ['name' => $user->name]) }}" data-dialog-confirm="{{ __('Yes, sign in as them') }}" data-dialog-variant="warning" @click="open = false">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                                </svg>
                                                {{ __('Login as User') }}
                                            </button>
                                        </form>
                                        @endif

                                        {{-- Suspending is an edit, not a delete: the account stays, it just
                                             loses access. Deleting a user is gated separately below. --}}
                                        @canDo('User Account', 'edit')
                                        @if((int) ($user->is_active ?? 1) === 1)
                                            <form method="POST" action="{{ route('users.suspend', $user->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="confirm_dialog flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors" data-dialog-title="{{ __('Suspend this user?') }}" data-dialog-text="{{ __(':name will lose access to the system until they are reactivated.', ['name' => $user->name]) }}" data-dialog-confirm="{{ __('Yes, suspend') }}" data-dialog-variant="warning" @click="open = false">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0m12.728 0A9 9 0 005.636 18.364M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    {{ __('Suspend User') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.unsuspend', $user->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="confirm_dialog flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors" data-dialog-title="{{ __('Reactivate this user?') }}" data-dialog-text="{{ __(':name will regain access to the system.', ['name' => $user->name]) }}" data-dialog-confirm="{{ __('Yes, reactivate') }}" data-dialog-variant="info" @click="open = false">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    {{ __('Unsuspend User') }}
                                                </button>
                                            </form>
                                        @endif
                                        @endcanDo

                                        @canDo('User Account', 'delete')
                                        <form method="POST" action="{{ route('users.destroy', $user->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="confirm_dialog flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors" data-dialog-title="{{ __('Delete this user?') }}" data-dialog-text="{{ __(':name will be permanently deleted. This cannot be undone.', ['name' => $user->name]) }}" data-dialog-confirm="{{ __('Yes, delete') }}" data-dialog-variant="danger" @click="open = false">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                {{ __('Delete User') }}
                                            </button>
                                        </form>
                                        @endcanDo
                                    </div>
                                </div>
                            </template>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center justify-center space-y-3">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            <div class="text-gray-500">
                                <p class="text-lg font-medium">{{ $emptyStateTitle ?? __('No users found') }}</p>
                                <p class="text-sm">{{ $emptyStateSubtitle ?? __('Get started by creating your first user.') }}</p>
                            </div>
                            @canDo('User Account', 'create')
                            <a href="#" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md transition-colors customModal"
                               data-size="xxl" data-url="{{ route('users.create') }}" data-title="{{ __('Create New User') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Create User') }}
                            </a>
                            @endcanDo
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
