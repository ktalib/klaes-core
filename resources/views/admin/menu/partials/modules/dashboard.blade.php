    <!-- 0. Dashboard -->
    @if($hasRole('Dashboard'))
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="dashboard">
        <div class="flex items-center gap-2">
          <i data-lucide="layout-dashboard" class="h-5 w-5 text-blue-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Dashboard</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="dashboard"></i>
      </div>
      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="dashboard">
        <a href="{{ route('dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
          <i data-lucide="home" class="h-4 w-4 text-blue-500"></i>
          <span>Dashboard</span>
        </a>
      </div>
    </div>
    @endif
