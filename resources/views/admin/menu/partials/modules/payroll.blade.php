<!-- Payroll Module -->
@if(
  $hasRole('Manual Attendance') ||
  $hasRole('Payroll') ||
  $hasRole('Attendance Tracking') ||
  $hasRole('Activity Monitoring') ||
  $hasRole('Super Admin') ||
  $hasRole('Supper Admin') ||
  auth()->user()->type === 'super admin'
)
<div class="py-1 px-3 mb-0.5 border-t border-slate-100">
  <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="payroll">
  <div class="flex items-center gap-2">
    <i data-lucide="calculator" class="h-5 w-5 text-slate-600"></i>
    <span class="text-sm font-bold uppercase tracking-wider">HR Manager</span>
  </div>
  <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="payroll"></i>
  </div>

  <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="payroll">
  @if($hasRole('Manual Attendance') || $hasRole('Super Admin') || $hasRole('Supper Admin') || auth()->user()->type === 'super admin')
  <a href="{{ route('manual-attendance.index') }}"
     class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('manual-attendance.*') ? 'active' : '' }}">
    <i data-lucide="clipboard-list" class="h-4 w-4 text-blue-500"></i>
    <span>Manual Attendance</span>
  </a>
  @endif

  @if($hasRole('Attendance Tracking') || $hasRole('Super Admin') || $hasRole('Supper Admin') || auth()->user()->type === 'super admin')
  <a href="{{ route('payroll.index') }}?url=attendance"
     class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('payroll.index') && request('url') === 'attendance' ? 'active' : '' }}">
    <i data-lucide="users" class="h-4 w-4 text-blue-500"></i>
    <span>Attendance Tracking</span>
  </a>
  @endif

  @if($hasRole('Payroll') || $hasRole('Super Admin') || $hasRole('Supper Admin') || auth()->user()->type === 'super admin')
  <a href="{{ route('payroll.index') }}"
     class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('payroll.index') && !request('url') ? 'active' : '' }}">
    <i data-lucide="users" class="h-4 w-4 text-blue-500"></i>
    <span>Payroll</span>
  </a>
  @endif

  @if($hasRole('Activity Monitoring') || $hasRole('Super Admin') || $hasRole('Supper Admin') || auth()->user()->type === 'super admin')
  <a href="{{ route('activity-monitoring.index') }}?url=hr"
     class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('activity-monitoring.index') ? 'active' : '' }}">
    <i data-lucide="activity" class="h-4 w-4 text-blue-500"></i>
    <span>Activity Monitoring</span>
  </a>
  @endif
  </div>
</div>
@endif