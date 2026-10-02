    <!-- 4. Programmes -->
    @if(
      $hasRole('Allocation') || $hasRole('Compensation/Resettlement') || 
      $hasRole('Recertification - Application') || $hasRole('Recertification - Bills & Payments') || 
      $hasRole('Recertification - Migrate Data') || $hasRole('Recertification - Verification Sheet') || 
      $hasRole('GIS - Data Capture') || $hasRole('Recertification - Vetting Sheet') ||
      $hasRole('Recertification - EDMS') || $hasRole('Recertification - Certification') || 
      $hasRole("Recertification - DG's List") || $hasRole('Recertification - Governors List') || 
      $hasRole('Conversion/Regularization') || $hasRole('Land Property Enumeration - Data Repository') || 
      $hasRole('Land Property Enumeration - Migrate Data')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="programmes">
      <div class="flex items-center gap-2">
        <i data-lucide="briefcase" class="h-5 w-5 text-purple-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Programmes</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="programmes"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="programmes">
      <!-- Allocation Section -->
      @if($hasRole('Allocation'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="allocation">
        <div class="flex items-center gap-2">
        <i data-lucide="building" class="h-4 w-4 text-purple-500"></i>
        <span>Allocation</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="allocation"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="allocation">
        @if($hasRole('Governors List'))
        <a href="/programmes/allocation/governors-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="list" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Governors List</span>
        </a>
        @endif
        @if($hasRole('Commissioners List'))
        <a href="/programmes/allocation/commissioners-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="list-checks" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Commissioners List</span>
        </a>
        @endif
      </div>
      @endif

      <!-- Compensation/Resettlement Section -->
      @if($hasRole('Compensation/Resettlement'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="resettlement">
        <div class="flex items-center gap-2">
          <i data-lucide="home" class="h-4 w-4 text-purple-500"></i>
          <span>Resettlement/Compensation</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="resettlement"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="resettlement">
        <a href="/programmes/resettlement/governors-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="list" class="h-3.5 w-3.5 text-purple-400"></i>
          <span>Governors List</span>
        </a>
        <a href="/programmes/resettlement/commissioners-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="list-checks" class="h-3.5 w-3.5 text-purple-400"></i>
          <span>Commissioners List</span>
        </a>
      </div>
      @endif

      <!-- Recertification Section --> 
      @if($hasRole('Recertification - Application') || $hasRole('Supper Admin'))
        <a href="{{ route('recertification.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.index') && request('url') !== 'kangis' ? 'active' : '' }}">
          <i data-lucide="refresh-cw" class="h-4 w-4 text-purple-500"></i>
          <span>Recertification</span>
        </a>
      @endif


      <!-- Conversion/Regularization Section -->
      @if($hasRole('Conversion/Regularization'))
    <a href="/programmes/regularization" 
       class="sidebar-item flex items-center gap-2 py-2 rounded-md transition-all duration-200">
        <i data-lucide="arrow-left-right" class="h-4 w-4 text-purple-500"></i>
        <span class="truncate">Conversion/Regularization</span>
    </a>
@endif

      <!-- Land Property Enumeration Section -->
      @if($hasRole('Land Property Enumeration - Data Repository') || $hasRole('Land Property Enumeration - Migrate Data'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="enumeration">
        <div class="flex items-center gap-2">
        <i data-lucide="clipboard-list" class="h-4 w-4 text-purple-500"></i>
        <span>Land Property Enumeration</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="enumeration"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="enumeration">
        @if($hasRole('Land Property Enumeration - Data Repository'))
        <a href="/programmes/enumeration/data-repository" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="database" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Data Repository</span>
        </a>
        @endif
        @if($hasRole('Land Property Enumeration - Migrate Data'))
        <a href="/programmes/enumeration/migrate-data" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="file-input" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Migrate Data</span>
        </a>
        @endif
      </div>
      @endif
      </div>
    </div>
    @endif
