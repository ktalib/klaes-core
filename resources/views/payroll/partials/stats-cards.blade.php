@php
    $isAttendanceOnly = request()->query('url') === 'attendance';
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ $isAttendanceOnly ? 'Attendance Overview' : 'Payroll Overview' }}</h2>
            <p class="text-sm text-gray-500">{{ $isAttendanceOnly ? 'Latest attendance metrics for the selected period.' : 'Key payroll metrics for the selected period.' }}</p>
        </div>
        <button type="button" class="export-dashboard-btn inline-flex items-center gap-2 rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
            <i class="fas fa-download"></i>
            Export Summary
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white border rounded-lg card-shadow p-6">
            <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium" id="metric-card-1-title">Total Employees</h3>
                <i class="fas fa-users-cog text-gray-400"></i>
            </div>
            <div class="pt-2">
                <div class="text-2xl font-bold" id="total-employees">0</div>
                <p class="text-xs text-gray-500" id="metric-card-1-note">Active staff members</p>
            </div>
        </div>
        <div class="bg-white border rounded-lg card-shadow p-6">
            <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium" id="metric-card-2-title">Present Today</h3>
                <i class="fas fa-calendar-alt text-gray-400"></i>
            </div>
            <div class="pt-2">
                <div class="text-2xl font-bold" id="present-today">0</div>
                <p class="text-xs text-gray-500" id="metric-card-2-note">Logged in today</p>
            </div>
        </div>
        <div class="bg-white border rounded-lg card-shadow p-6">
            <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium" id="metric-card-3-title">Avg Attendance</h3>
                <i class="fas fa-chart-line text-gray-400"></i>
            </div>
            <div class="pt-2">
                <div class="text-2xl font-bold" id="avg-attendance">0.0%</div>
                <p class="text-xs text-gray-500" id="metric-card-3-note">Average for current period</p>
            </div>
        </div>
        @if($isAttendanceOnly)
            <div class="bg-white border rounded-lg card-shadow p-6">
                <div class="flex flex-row items-center justify-between pb-2">
                    <h3 class="text-sm font-medium" id="metric-card-4-title">Total Hours Logged</h3>
                    <i class="fas fa-clock text-gray-400"></i>
                </div>
                <div class="pt-2">
                    <div class="text-2xl font-bold" id="total-payroll">0.00h</div>
                    <p class="text-xs text-gray-500" id="metric-card-4-note">Cumulative hours this period</p>
                </div>
            </div>
        @else
            <div class="bg-white border rounded-lg card-shadow p-6">
                <div class="flex flex-row items-center justify-between pb-2">
                    <h3 class="text-sm font-medium" id="metric-card-4-title">Total Payroll</h3>
                    <i class="fas fa-dollar-sign text-gray-400"></i>
                </div>
                <div class="pt-2">
                    <div class="text-2xl font-bold" id="total-payroll">₦0.00</div>
                    <p class="text-xs text-gray-500" id="metric-card-4-note">Net salaries this period</p>
                </div>
            </div>
        @endif
    </div>
</div>
