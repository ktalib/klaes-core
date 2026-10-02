@php
    $isAttendanceOnly = request()->query('url') === 'attendance';
@endphp

<div class="space-y-2">
    @if($isAttendanceOnly)
        <h1 class="text-3xl font-bold tracking-tight">Attendance Tracking</h1>
        <p class="text-gray-600">Monitor employee login sessions, attendance records, and work hours</p>
    @else
        <h1 class="text-3xl font-bold tracking-tight">HR Management System</h1>
        <p class="text-gray-600">Manage employee rates, salaries, and payroll with flexible shift hours</p>
    @endif
</div>
