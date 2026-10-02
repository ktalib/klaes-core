<div id="attendance-tab" class="tab-content {{ isset($forceVisible) && $forceVisible ? '' : 'hidden' }} space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="space-y-2">
            <h3 class="text-lg font-bold">Attendance Filters</h3>
            <p class="text-sm text-gray-500">Filter attendance records by month and employee</p>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <label class="text-sm font-medium" for="attendance-month">Month</label>
                <div class="select-container">
                    <div class="select-trigger flex items-center justify-between w-full px-3 py-2 border border-gray-300 rounded-md cursor-pointer" data-type="attendance-month">
                        <span>January 2024</span>
                        <i class="fas fa-chevron-down text-gray-400"></i>
                    </div>
                    <div class="select-dropdown hidden" id="attendance-month-dropdown">
                        <div class="py-1">
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-01">January 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-02">February 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-03">March 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-04">April 2024</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium" for="attendance-search">Search Employee</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"></i>
                    <input class="attendance-search-input w-full px-3 py-2 pl-8 border border-gray-300 rounded-md" id="attendance-search" placeholder="Search by name or ID..." type="text">
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button class="sync-attendance-users-btn flex w-full items-center justify-center rounded-md border border-emerald-300 px-4 py-2 text-emerald-700 hover:bg-emerald-50 sm:flex-1">
                    <i class="fas fa-user-plus mr-2"></i>
                    Sync Eligible Users
                </button>
                <button class="regenerate-attendance-btn flex w-full items-center justify-center rounded-md border border-blue-300 px-4 py-2 text-blue-700 hover:bg-blue-50 sm:flex-1">
                    <i class="fas fa-sync mr-2"></i>
                    Regenerate Attendance
                </button>
                <button class="export-attendance-btn flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 sm:flex-1">
                    <i class="fas fa-download mr-2"></i>
                    Export Report
                </button>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="space-y-2">
            <h3 class="text-lg font-bold">Employee Attendance Records</h3>
            <p class="text-sm text-gray-500">Attendance calculated based on shift hours: 8 hours = 1 full day, 4 hours = 0.5 days</p>
        </div>
        <div class="mt-4">
            <div class="overflow-auto rounded-md border">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-gray-50 sticky-header">
                        <tr>
                            <th class="p-4 text-center font-medium w-14">S/N</th>
                            <th class="p-4 text-left font-medium">Employee ID</th>
                            <th class="p-4 text-left font-medium">Name</th>
                            <th class="p-4 text-left font-medium">Department</th>
                            <th class="p-4 text-left font-medium">Unit</th>
                            <th class="p-4 text-center font-medium">Shift</th>
                            <th class="p-4 text-center font-medium">Daily Breakdown</th>
                            <th class="p-4 text-center font-medium">Hours Worked</th>
                            <th class="p-4 text-center font-medium">Login Days</th>
                            <th class="p-4 text-center font-medium">Overtime</th>
                            <th class="p-4 text-center font-medium">Attendance %</th>
                            <th class="p-4 text-center font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-table-body"></tbody>
                </table>
            </div>
            <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                <strong>Note:</strong> Login Days are calculated from configured shift hours. 8-hour shift employees need 8 hours for 1 day, 4-hour shift employees need 4 hours for 1 day. Overtime is only accumulated when authorised; otherwise sessions auto-logout at shift end.
            </div>
        </div>
    </div>
</div>
