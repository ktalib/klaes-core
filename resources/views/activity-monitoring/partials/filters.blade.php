<div class="bg-white rounded-2xl shadow-sm p-6 space-y-6 border border-slate-100">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Users</label>
            <div class="relative" x-data="{ open: false }" @click.away="open = false">
                <button @click="open = !open"
                        class="w-full flex items-center justify-between px-4 py-2 border rounded-lg text-left text-sm focus:ring-2 focus:ring-blue-500">
                    <div>
                        <span class="font-medium" x-text="filters.users.length ? `${filters.users.length} selected` : 'All eligible users'"></span>
                        <p class="text-xs text-gray-500" x-text="filters.users.length ? 'Filters apply to selected users' : 'Includes everyone in your department'"></p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                </button>
                <div x-show="open" x-transition
                     class="absolute mt-2 inset-x-0 bg-white border rounded-xl shadow-lg p-4 max-h-80 overflow-y-auto z-30">
                    <div class="flex items-center gap-2 mb-3">
                        <input type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search users..." x-model="userSearch">
                        <button class="text-xs text-blue-600 font-semibold" @click="selectAllUsers()">All</button>
                        <button class="text-xs text-gray-500" @click="clearUsers()">Clear</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="user in processedUsers()" :key="user.id">
                            <label class="flex items-center gap-3 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600"
                                       :checked="filters.users.includes(user.id)"
                                       @change="toggleUser(user.id)">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                        <span class="inline-flex h-2.5 w-2.5 rounded-full border border-white shadow"
                                              :style="`background:${user.department_color || '#c7d2fe'}`"></span>
                                        <span x-text="user.name"></span>
                                    </p>
                                    <p class="text-xs text-gray-500" x-text="user.department || 'No Department'"></p>
                                    <p class="text-xs text-gray-400" x-text="user.work_station ? `Work Station: ${user.work_station}` : 'Work Station: —'"></p>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="lg:col-span-4 space-y-2">
            <label class="block text-sm font-semibold text-gray-700">Date range</label>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" placeholder="Start date" x-ref="startDate"
                       class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                <input type="text" placeholder="End date" x-ref="endDate"
                       class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="px-3 py-1.5 rounded-full text-xs font-semibold border"
                        :class="filters.preset === 'today' ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 border-gray-200'"
                        @click="applyPreset('today')">
                    Today
                </button>
                <button class="px-3 py-1.5 rounded-full text-xs font-semibold border"
                        :class="filters.preset === 'this_week' ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 border-gray-200'"
                        @click="applyPreset('this_week')">
                    This Week
                </button>
                <button class="px-3 py-1.5 rounded-full text-xs font-semibold border"
                        :class="filters.preset === 'this_month' ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 border-gray-200'"
                        @click="applyPreset('this_month')">
                    This Month
                </button>
                <button class="px-3 py-1.5 rounded-full text-xs font-semibold border"
                        :class="filters.preset === 'last_30_days' ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 border-gray-200'"
                        @click="applyPreset('last_30_days')">
                    Last 30 Days
                </button>
            </div>
        </div>
        <div class="lg:col-span-3 space-y-3">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Work Stations</label>
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button @click="open = !open"
                            class="w-full flex items-center justify-between px-4 py-2 border rounded-lg text-left text-sm focus:ring-2 focus:ring-emerald-500">
                        <div>
                            <span class="font-medium" x-text="filters.workstations.length ? `${filters.workstations.length} selected` : 'All Work Stations'"></span>
                            <p class="text-xs text-gray-500" x-text="filters.workstations.length ? 'Filtering dashboards by selected stations' : 'Includes every configured station'"></p>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute inset-x-0 mt-2 bg-white border rounded-xl shadow-lg p-4 max-h-72 overflow-y-auto z-30">
                        <template x-if="(state.filters?.workstations?.options ?? []).length === 0">
                            <p class="text-xs text-gray-400">Work station assignments populate as soon as users are tagged.</p>
                        </template>
                        <template x-if="(state.filters?.workstations?.options ?? []).length">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                                    <span>Toggle stations to refine metrics.</span>
                                    <button class="font-semibold text-blue-600" @click="filters.workstations = []; open = false">Clear</button>
                                </div>
                                <template x-for="station in state.filters?.workstations?.options ?? []" :key="station.value">
                                    <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" class="rounded text-emerald-600"
                                                   :checked="filters.workstations.includes(station.value)"
                                                   @change="toggleWorkStation(station.value)">
                                            <span class="text-sm font-semibold text-gray-900" x-text="station.label"></span>
                                        </div>
                                        <span class="text-[11px] font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-600"
                                              :class="filters.workstations.includes(station.value) ? 'bg-emerald-100 text-emerald-700' : ''"
                                              x-text="formatNumber(station.count ?? 0)"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700"
                        @click="applyFilters">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-semibold text-gray-600 hover:bg-slate-50"
                        @click="resetFilters">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
            </div>
        </div>
    </div>
</div>
