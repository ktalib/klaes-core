<!-- Online Users Tab -->
<div id="online-users-content" class="tab-content hidden">
    <!-- Header Section -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <div class="p-2.5 rounded-lg bg-emerald-100 mr-3">
                    <i class="fas fa-users text-emerald-600 text-lg"></i>
                </div>
                Currently Online
            </h3>
            <p class="text-sm text-gray-500 mt-1 ml-12">Real-time active sessions</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="refreshOnlineUsers()" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
            <div class="flex items-center px-3 py-2 bg-emerald-50 rounded-lg border border-emerald-200">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse mr-2"></span>
                <span class="text-xs font-semibold text-emerald-700">Auto-refresh: <span id="auto-refresh-status">ON</span></span>
            </div>
        </div>
    </div>

    <!-- Online Users Grid -->
    <div id="online-users-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @if($onlineUsers->count() > 0)
            @foreach($onlineUsers as $user)
                <div class="bg-white shadow-lg rounded-xl p-5 hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-emerald-200 group">
                    <!-- User Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3 flex-1">
                            <!-- Avatar -->
                            <div class="flex-shrink-0 relative">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-md">
                                    {{ substr($user->user->first_name ?? 'U', 0, 1) }}{{ substr($user->user->last_name ?? 'U', 0, 1) }}
                                </div>
                                <span class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white animate-pulse shadow-lg"></span>
                            </div>
                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 truncate">
                                    {{ $user->user->name ?? 'Unknown User' }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $user->user->email ?? 'No email' }}
                                </p>
                            </div>
                        </div>
                        <!-- Status Badge -->
                        <div class="px-2.5 py-1 bg-emerald-100 rounded-full flex items-center gap-1">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            <span class="text-xs font-semibold text-emerald-700">Online</span>
                        </div>
                    </div>

                    <!-- Session Details -->
                    <div class="space-y-2.5 mb-4 pt-4 border-t border-gray-100">
                        <!-- Device Info -->
                        <div class="flex items-center gap-2 text-xs">
                            <div class="p-1.5 rounded-lg bg-blue-50">
                                <i class="fas fa-{{ $user->device_type == 'mobile' ? 'mobile-alt' : ($user->device_type == 'tablet' ? 'tablet-alt' : 'desktop') }} text-blue-600 w-4 text-center"></i>
                            </div>
                            <span class="text-gray-600">
                                <span class="font-semibold text-gray-900">{{ ucfirst($user->device_type) }}</span>
                            </span>
                        </div>

                        <!-- IP Address -->
                        <div class="flex items-center gap-2 text-xs">
                            <div class="p-1.5 rounded-lg bg-purple-50">
                                <i class="fas fa-globe text-purple-600 w-4 text-center"></i>
                            </div>
                            <span class="font-mono text-gray-600">{{ $user->ip_address }}</span>
                        </div>

                        <!-- Session Duration -->
                        <div class="flex items-center gap-2 text-xs">
                            <div class="p-1.5 rounded-lg bg-orange-50">
                                <i class="fas fa-hourglass-half text-orange-600 w-4 text-center"></i>
                            </div>
                            <span class="text-gray-600">
                                Online for <span class="font-semibold text-gray-900">{{ $user->login_time->diffForHumans(null, true) }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <button onclick="logoutUser('{{ $user->user->id }}')" class="w-full px-3 py-2 bg-red-50 text-red-700 rounded-lg font-semibold text-xs hover:bg-red-100 transition-all duration-200 border border-red-200 group-hover:bg-red-600 group-hover:text-white group-hover:border-red-600">
                        <i class="fas fa-sign-out-alt mr-1"></i>
                        Force Logout
                    </button>
                </div>
            @endforeach
        @else
            <!-- Empty State -->
            <div class="col-span-full">
                <div class="py-16 text-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border-2 border-dashed border-gray-300">
                    <div class="mb-4">
                        <i class="fas fa-user-slash text-gray-300 text-5xl mb-4 block"></i>
                    </div>
                    <p class="text-gray-600 font-semibold text-lg">No users currently online</p>
                    <p class="text-gray-400 text-sm mt-2">Users will appear here when they log in to the system</p>
                </div>
            </div>
        @endif
    </div>
</div>
