<!-- Statistics & Status Bar -->
<div class="mb-6 mt-4">
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl px-6 py-5 border border-blue-100 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Status Indicator -->
            <div class="flex items-center gap-3 col-span-1">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-green-100">
                        <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Status</p>
                    <p class="text-sm font-semibold text-green-700">Operational</p>
                </div>
            </div>

            <!-- Total Sessions -->
            <div class="flex items-center gap-3 col-span-1">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-blue-100">
                        <i class="fas fa-signal text-blue-600"></i>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Sessions</p>
                    <p class="text-lg font-bold text-gray-900" id="total-sessions">-</p>
                </div>
            </div>

            <!-- Unique Users -->
            <div class="flex items-center gap-3 col-span-1">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-purple-100">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Users</p>
                    <p class="text-lg font-bold text-gray-900" id="unique-users">-</p>
                </div>
            </div>

            <!-- Online Now -->
            <div class="flex items-center gap-3 col-span-1">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-emerald-100">
                        <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Online</p>
                    <p class="text-lg font-bold text-gray-900" id="online-users">-</p>
                </div>
            </div>

            <!-- Last Updated -->
            <div class="flex items-center gap-3 col-span-1">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-orange-100">
                        <i class="fas fa-clock text-orange-600"></i>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Updated</p>
                    <p class="text-sm font-semibold text-gray-900" id="last-refresh-time">--:--</p>
                </div>
            </div>
        </div>
    </div>
</div>
