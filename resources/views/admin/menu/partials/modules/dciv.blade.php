    <!-- 9.1 DCIV -->
    @if(
      $hasRole('Generate New FileNo (DCIVFileNo)') || $hasRole('DCIV - Records')||
      $hasRole('DCIV - Digital Archive') 
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="dciv">
        <div class="flex items-center gap-2">
          <i data-lucide="layers" class="h-5 w-5 text-blue-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">DCIV Management</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="dciv"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="dciv">
        @if($hasRole('Generate New FileNo (DCIVFileNo)'))
        <a href="{{ route('dciv-generation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('dciv-generation.index') ? 'active' : '' }}">
          <i data-lucide="plus-square" class="h-4 w-4 text-blue-500"></i>
          <span>Generate New FileNo (DCIVFileNo)</span>
        </a>
        @endif

        <!-- a2. Master Link Table -->
        <a href="{{ route('master-dciv-links.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('master-dciv-links.index') ? 'active' : '' }}">
          <i data-lucide="link" class="h-4 w-4 text-blue-500"></i>
          <span>DCIV Call-up</span>
        </a>

        <!-- b. Title Status Update -->
        <a href="{{ route('title-status.index') }}?url=dciv"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'dciv' ? 'active' : '' }}">
          <i data-lucide="badge-check" class="h-4 w-4 text-blue-500"></i>
          <span>Title Status Update</span>
        </a>

        <!-- c. Digital Archive -->
        @if($hasRole('DCIV - Digital Archive') || $hasRole('Supper Admin'))
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-dciv">
            <div class="flex items-center gap-2">
              <i data-lucide="archive" class="h-4 w-4 text-blue-500"></i>
              <span>Digital Archive</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-dciv"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-dciv">
            <!-- i. File Tracker Dashboard -->
            <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>File Tracker Dashboard</span>
            </a>

            <!-- ii. File Tracker (Archive) -->
            <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="archive" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>File Tracker (Archive)</span>
            </a>



            <!-- Quick Search (scoped to DCIV registry files) -->
            <a href="{{ route('create-file-tracker.quick-search', ['url' => 'dciv']) }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'dciv' ? 'active' : '' }}">
              <i data-lucide="file-search" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Quick Search</span>
            </a>

            <!-- iii. Log a File -->
            <a href="{{ route('create-file-tracker.index', ['url' => 'dciv']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'dciv' ? 'active' : '' }}">
              <i data-lucide="file-plus" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Log a File</span>
            </a>

            <!-- iv. File Digital Library – Doc-WARE -->
            <a href="{{ route('filearchive.index', ['url' => 'dciv']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'dciv' ? 'active' : '' }}">
              <i data-lucide="library" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>File Digital Library – Doc-WARE</span>
            </a>

            <!-- v. EDMS Update -->
            <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>DMS Update</span>
            </a>
          </div>
        @endif
      </div>
    </div>
    @endif
