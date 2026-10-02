<!-- 16. System Admin -->
@if($hasRole('User Account') || $hasRole('Departments') || $hasRole('User Roles') || $hasRole('Land Officers') || $hasRole('Digital Signature Control') || $hasRole('System Settings'))
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="admin">
      <div class="flex items-center gap-2">
        <i data-lucide="cog" class="h-5 w-5 text-slate-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">System Admin</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="admin"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="admin">
      @if($hasRole('User Account'))
        <a href="{{ route('user-activity-logs.index') }}?url=admin"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('user-activity-log.index') ? 'active' : '' }}">
          <i data-lucide="users" class="h-4 w-4 text-slate-500"></i>
          <span>Activity Logs</span>
        </a>

        @php
          $assignRolesCollection = collect(auth()->user()->assignedRoleNames());
          $showsActivityMonitoring = auth()->user()->type === 'super admin'
            || auth()->user()->can('view-activity-reports')
            || $assignRolesCollection->contains('super admin')
            || $assignRolesCollection->contains('supper admin');
        @endphp
        @if($hasRole('Activity Monitoring') || $hasRole('Supper Admin'))

          <a href="{{ route('activity-monitoring.index') }}?url=admin"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('activity-monitoring.*') && request()->query('url') === 'admin') ? 'active' : '' }}">
            <i data-lucide="bar-chart-3" class="h-4 w-4 text-slate-500"></i>
            <span>Activity Monitoring</span>
          </a>
        @endif

        <a href="{{route('users.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('users.index') ? 'active' : '' }}">
          <i data-lucide="user-cog" class="h-4 w-4 text-slate-500"></i>
          <span>User Account</span>
        </a>
      @endif

      <!-- New menu items for departments and roles -->
      @if($hasRole('Departments'))
        <a href="{{ route('departments.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('departments.index') ? 'active' : '' }}">
          <i data-lucide="building" class="h-4 w-4 text-slate-500"></i>
          <span>Departments</span>
        </a>
      @endif

      @if($hasRole('User Roles'))
        <a href="{{ route('user-roles.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('user-roles.index') ? 'active' : '' }}">
          <i data-lucide="shield" class="h-4 w-4 text-slate-500"></i>
          <span>User Roles</span>
        </a>
      @endif

      @if($hasRole('Land Officers') || $hasRole('Digital Signature Control'))
        <a href="{{ route('digital-signatures.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('digital-signatures.index') ? 'active' : '' }}">
          <i data-lucide="pen-tool" class="h-4 w-4 text-slate-500"></i>
          <span>Digital Signature Control</span>
        </a>
      @endif

      @if($hasRole('System Settings'))
        <a href="{{ route('setting.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('setting.*') ? 'active' : '' }}">
          <i data-lucide="settings" class="h-4 w-4 text-slate-500"></i>
          <span>System Settings</span>
        </a>
        {{--
        <a href="{{ route('system-admin.csv-import.file-indexing') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.csv-import.file-indexing') ? 'active' : '' }}">
          <i data-lucide="files" class="h-4 w-4 text-slate-500"></i>
          <span>File Indexing Import</span>
        </a>
        <a href="{{ route('system-admin.csv-import.file-number') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.csv-import.file-number') ? 'active' : '' }}">
          <i data-lucide="upload-cloud" class="h-4 w-4 text-slate-500"></i>
          <span>File Number Import</span>
        </a>
        --}}
        <a href="{{ route('system-admin.folder-watcher') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.folder-watcher') ? 'active' : '' }}">
          <i data-lucide="server" class="h-4 w-4 text-slate-500"></i>
          <span>Folder Watcher</span>
        </a>
      @endif
    </div>
  </div>
@endif