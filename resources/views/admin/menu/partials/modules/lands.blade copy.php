<!-- 7. Lands -->
@if(
    $hasRole('Lands - File Tracker/Tracking - RFID') ||
    $hasRole('Lands - File Digital Archive – Doc-WARE') ||
    $hasRole('Lands - Generate New FileNo (MLSFileNo)') ||
    $hasRole('Lands - Capture an Existing File') ||
    $hasRole('Lands - File Decommissioning') ||
    $hasRole('Lands - File Search - Scans') ||
    $hasRole('Lands - File Search - DB') ||
    $hasRole('Lands - Activity Monitoring') ||
    $hasRole('EDMS Update') ||
    $hasRole('Letter of Grant') ||
    $hasRole('Supper Admin')

  )
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="lands">
      <div class="flex items-center gap-2">
        <i data-lucide="landmark" class="h-5 w-5 text-orange-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Lands</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 text-black transition-transform duration-200"
        data-chevron="lands"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="lands">



       @if($hasRole('Allocation List Entry') || $hasRole('File Search – Table') || $hasRole('Supper Admin'))
        <a href="{{ route('allocation-list.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('allocation-list.index') ? 'active' : '' }}">
          <i data-lucide="list" class="h-4 w-4 text-orange-500"></i>
          <span>Allocation List</span>
        </a>
      @endif






      <!-- a. Generate New FileNo (MLSFileNo) -->
      @if($hasRole('Lands - Generate New FileNo (MLSFileNo)') || $hasRole('Supper Admin'))
        <a href="{{route('file-numbers.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-numbers.index') ? 'active' : '' }}">
          <i data-lucide="hash" class="h-4 w-4 text-orange-500"></i>
          <span>Generate New FileNo (MLSFileNo)</span>
        </a>
      @endif
             


      <!-- b. Capture an Existing File -->
      @if($hasRole('Lands - Capture an Existing File') || $hasRole('Supper Admin'))
        <a href="{{route('existing-file-numbers.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('existing-file-numbers.index') ? 'active' : '' }}">
          <i data-lucide="folder-plus" class="h-4 w-4 text-orange-500"></i>
          <span>Capture an Existing File</span>
        </a>
      @endif

      <!-- c. Manage MLSFileNo -->
      @if($hasRole('Lands - Generate New FileNo (MLSFileNo)') || $hasRole('Supper Admin'))
        <a href="{{route('mls-fileno.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mls-fileno.index') ? 'active' : '' }}">
          <i data-lucide="settings" class="h-4 w-4 text-orange-500"></i>
          <span>Manage MLSFileNo</span>
        </a>
      @endif

      <!-- d. File Decommissioning -->
      @if($hasRole('Lands - File Decommissioning') || $hasRole('Supper Admin'))
        <a href="{{route('file-decommissioning.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200  {{ request()->routeIs('file-decommissioning.index') ? 'active' : '' }}">
          <i data-lucide="archive" class="h-4 w-4 text-orange-500"></i>
          <span>File Decommissioning </span>
        </a>
      @endif

      <!-- e. File History View -->
      @if($hasRole('Lands - File Tracker/Tracking - RFID') || $hasRole('Supper Admin'))
        <a href="{{ route('file-index-view.index', ['url' => 'land']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') ? 'active' : '' }}">
          <i data-lucide="eye" class="h-4 w-4 text-orange-500"></i>
          <span>File History View</span>
        </a>
      @endif

      <!-- f. File Search -->
      @if($hasRole('File Search – Scans') || $hasRole('File Search – Table') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="fileSearch-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="search" class="h-4 w-4 text-orange-500"></i>
            <span>File Search</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="fileSearch-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="fileSearch-lands">
          @if($hasRole('File Search – Scans') || $hasRole('Supper Admin'))
            <a href="{{ route('file-search.scans') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search.scans') ? 'active' : '' }}">
              <i data-lucide="image" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Scans</span>
            </a>
          @endif

          @if($hasRole('File Search – Table') || $hasRole('Supper Admin'))
            <a href="{{ route('file-search-db.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search-db.index') ? 'active' : '' }}">
              <i data-lucide="table" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Table</span>
            </a>
          @endif
        </div>
      @endif

      @if(
        $hasRole('Lands One Stop Shop - Applications') ||
        $hasRole('Lands One Stop Shop - Bill') ||
        $hasRole('Supper Admin')
      )
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="lands-one-stop-shop">
          <div class="flex items-center gap-2">
            <i data-lucide="store" class="h-4 w-4 text-orange-500"></i>
            <span>Lands One Stop Shop</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="lands-one-stop-shop"></i>
        </div> 

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="lands-one-stop-shop">
          @if($hasRole('Lands One Stop Shop - Applications') || $hasRole('Supper Admin'))
            <a href="{{ route('lands-one-stop-shop.all-applications.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.all-applications.index') ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Applications</span>
            </a>
          @endif

          @if($hasRole('Lands One Stop Shop - Applications') || $hasRole('Supper Admin'))
            <a href="{{ route('lands-one-stop-shop.applications.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.applications.index') ? 'active' : '' }}">
              <i data-lucide="users" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Applications (Occupancy Permit)</span>
            </a>
          @endif

          @if($hasRole('Lands One Stop Shop - Bill') || $hasRole('Supper Admin'))
            <a href="{{ route('lands-one-stop-shop.bill.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.bill.index') ? 'active' : '' }}">
              <i data-lucide="receipt" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Bill</span>
            </a>
          @endif
        </div>
      @endif

      <!-- g. Letter of Grant (RofO) -->
      @if($hasRole('Letter of Grant') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="rofo-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="scroll" class="h-4 w-4 text-orange-500"></i>
            <span>Letter of Grant (RofO)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rofo-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rofo-lands">
          <a href="{{ route('land-recommendations.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.index') ? 'active' : '' }}">
            <i data-lucide="check-circle" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Recommendation</span>
          </a>

          <a href="{{ route('land-rofos.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-rofos.index') ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>RofO</span>
          </a>
        </div>
      @endif
      
      <!-- h. Lands to Cad/Survey -->
      @if($hasRole('Lands to Cad/Survey') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="cadSurvey-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="map" class="h-4 w-4 text-orange-500"></i>
            <span>Lands to Cad/Survey</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadSurvey-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadSurvey-lands">
            <a href="{{ route('survey-report.index') }}?url=lands"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'lands' ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Lands 12 (Request for Survey Report)</span>
          </a>
          <a href="{{ route('for-information.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('for-information.index') ? 'active' : '' }}">
            <i data-lucide="info" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>For Information</span>
          </a>

        
        </div>
      @endif

      <!-- i. EDMS Update -->
      @if($hasRole('EDMS Update') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="edmsUpdate-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="refresh-cw" class="h-4 w-4 text-orange-500"></i>
            <span>EDMS Update</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="edmsUpdate-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="edmsUpdate-lands">
          <a href="/scanning?url=scmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Scan More</span>
          </a>

          <a href="/pagetyping?url=ptmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Type More</span>
          </a>
        </div>
      @endif


    </div>
  </div>
@endif
