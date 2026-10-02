    <!-- 0.1 HC/PS View -->
    @if($hasRole('HC/PS View'))
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="hc_ps_view">
        <div class="flex items-center gap-2">
          <i data-lucide="briefcase" class="h-5 w-5 text-indigo-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">HC/PS VIEW</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="hc_ps_view"></i>
      </div>
      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="hc_ps_view">
        {{-- Honorable Commissioner: dashboard + document library --}}
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="hc_view">
          <div class="flex items-center gap-2">
            <i data-lucide="layout-dashboard" class="h-4 w-4 text-indigo-500"></i>
            <span>Honorable Commissioner</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="hc_view"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="hc_view">
          <a href="{{ route('commissioner.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('commissioner.dashboard') && request('url') !== 'ps' ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" class="h-4 w-4 text-indigo-500"></i>
            <span>Commissioner’s Dashboard</span>
          </a>
          <a href="{{ route('documents.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('documents.index') && request('url') !== 'ps' ? 'active' : '' }}">
            <i data-lucide="library" class="h-4 w-4 text-indigo-500"></i>
            <span>Report &amp; Manual</span>
          </a>
        </div>

        {{-- Permanent Secretary: dashboard + document library --}}
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="ps_view">
          <div class="flex items-center gap-2">
            <i data-lucide="user-check" class="h-4 w-4 text-indigo-500"></i>
            <span>Permanent Secretary</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="ps_view"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="ps_view">
          <a href="{{ route('commissioner.dashboard', ['url' => 'ps']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('commissioner.dashboard') && request('url') === 'ps' ? 'active' : '' }}">
            <i data-lucide="user-check" class="h-4 w-4 text-indigo-500"></i>
            <span>Permanent Secretary’s Dashboard</span>
          </a>
          <a href="{{ route('documents.index', ['url' => 'ps']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('documents.index') && request('url') === 'ps' ? 'active' : '' }}">
            <i data-lucide="library" class="h-4 w-4 text-indigo-500"></i>
            <span>Report &amp; Manual</span>
          </a>
        </div>
      </div>
    </div>
    @endif
