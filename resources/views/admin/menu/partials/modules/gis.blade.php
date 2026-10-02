    <!-- 11. GIS -->
    @if(
      $hasRole('GIS - Records') || $hasRole('GIS - AI Digital Assistant') || $hasRole('GIS - GIS') ||
      $hasRole('GIS - Approvals') || $hasRole('GIS - e-Registry') || $hasRole('GIS Reports') ||
      $hasRole('GIS - Title Deed Plan')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="gis">
      <div class="flex items-center gap-2"> 
        <i data-lucide="map" class="h-5 w-5 text-yellow-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">GIS</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="gis"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="gis">
      @if($hasRole('GIS - Records'))
      <a href="{{route('gis_record.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('gis_record.index') ? 'active' : '' }}">
        <i data-lucide="clipboard" class="h-4 w-4 text-yellow-500"></i>
        <span>Records</span>
      </a>
      @endif
      {{-- The Title Deed Plan store: the BACK PAGE of the ST Certificate of Occupancy.
           The front page is produced in Sectional Titling → Certificate → Front Page. --}}
      @if($hasRole('GIS - Title Deed Plan'))
      <a href="{{ route('tdp.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('tdp.*') ? 'active' : '' }}">
        <i data-lucide="map-pinned" class="h-4 w-4 text-yellow-500"></i>
        <span>Title Deed Plan Management</span>
      </a>
      @endif
      @if($hasRole('GIS - AI Digital Assistant'))
      <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="bot" class="h-4 w-4 text-yellow-500"></i>
        <span>AI Digital Assistant</span>
      </a>
      @endif
      @if($hasRole('GIS - GIS'))
      <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="map" class="h-4 w-4 text-yellow-500"></i>
        <span>GIS</span>
      </a>
      @endif
      @if($hasRole('GIS Reports'))
      <a href="{{ route('survey_plan_extraction.index') }}?url=gis" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="sparkles" class="h-4 w-4 text-yellow-500"></i>
        <span>Survey Plan Extraction</span>
      </a>
      @endif
      @if($hasRole('GIS - Approvals'))
      <a href="/gis/approvals" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="check-circle" class="h-4 w-4 text-yellow-500"></i>
        <span>Approvals</span>
      </a>
      @endif
      @if($hasRole('GIS - e-Registry'))
      <a href="/gis/e-registry" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="database" class="h-4 w-4 text-yellow-500"></i>
        <span>E-Registry</span>
      </a>
      @endif
      @if($hasRole('GIS Reports'))
      <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="file-bar-chart" class="h-4 w-4 text-yellow-500"></i>
        <span>GIS Reports</span>
      </a>
      @endif
      </div>
    </div>
    @endif
