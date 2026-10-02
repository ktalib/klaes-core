@php
    $isAttendanceOnly = request()->query('url') === 'attendance';
@endphp

<div class="bg-white border rounded-lg card-shadow p-6">
    @if($isAttendanceOnly)
        {{-- Attendance-only mode: provide focused tab set --}}
        <div class="grid w-full grid-cols-3 gap-1 mb-6 bg-gray-100 rounded-lg p-1">
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-active" data-tab="daily-logins">
                Daily Logins/Attendance
            </button>
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-inactive" data-tab="cumulative-hours">
                Cumulative Hours
            </button>
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-inactive" data-tab="attendance">
                Attendance Tracking
            </button>
        </div>
        <div class="mt-6 space-y-6">
            @include('payroll.partials.tabs.daily-logins')
            @include('payroll.partials.tabs.cumulative-hours')
            @include('payroll.partials.tabs.attendance')
        </div>
    @else
        {{-- Full payroll mode: show standard payroll tabs only --}}
        <div class="grid w-full grid-cols-3 gap-1 mb-6 bg-gray-100 rounded-lg p-1">
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-active" data-tab="rates">
                User Rates &amp; Shifts
            </button>
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-inactive" data-tab="salary">
                Salary Management
            </button>
            <button class="tab-button py-2 px-4 text-sm font-medium transition-colors rounded-md tab-inactive" data-tab="payroll">
                Payroll Reports
            </button>
        </div>
        <div class="mt-6 space-y-6">
            @include('payroll.partials.tabs.rates')
            @include('payroll.partials.tabs.salary')
            @include('payroll.partials.tabs.reports')
        </div>
    @endif
</div>
