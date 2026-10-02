<!-- LAAS Portal — applications submitted by the public. Standalone module, gated on
     the same roles that already review and allocate land applications. -->
@if($hasRole('Land') || $hasRole('Land One Stop Shop') || $hasRole('Generate New FileNo (MLSFileNo)'))
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="laas-portal">
      <div class="flex items-center gap-2">
        <i data-lucide="globe" class="h-5 w-5 text-orange-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">LAAS Portal</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 text-black transition-transform duration-200"
        data-chevron="laas-portal"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="laas-portal">
  
      <a href="{{ route('laas-admin.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('laas-admin.index') || request()->routeIs('laas-admin.show') ? 'active' : '' }}">
        <i data-lucide="globe" class="h-4 w-4 text-orange-500"></i>
        <span>Applications</span>
      </a>
          <a href="{{ route('laas-admin.applicants') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('laas-admin.applicants*') ? 'active' : '' }}">
        <i data-lucide="users" class="h-4 w-4 text-orange-500"></i>
        <span>Applicants</span>
      </a>
    </div>
  </div>
@endif
