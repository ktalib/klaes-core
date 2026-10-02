<!-- 7. KANGIS -->
@if(
  $hasRole('KANGIS') || $hasRole('Kangis-Indexing') ||
  $hasRole('Recertification - Application') || $hasRole('Recertification - Bills & Payments') ||
  $hasRole('Recertification - EDMS') || $hasRole('Recertification - Certification') ||
  $hasRole("Recertification - DG's List") || $hasRole('Recertification - Governors List') ||
  $hasRole('GIS - e-Registry') ||
  $hasRole('KANGIS - Indexing') || $hasRole('KANGIS - Recertification') ||
  $hasRole('KANGIS - File Digital Library') || $hasRole('KANGIS - Digital Archive') ||
  $hasRole('Supper Admin')
)
<div class="py-1 px-3 mb-0.5 border-t border-slate-100">
  <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="sside">
    <div class="flex items-center gap-2">
      <i data-lucide="map" class="h-5 w-5 text-yellow-600"></i>
      <span class="text-sm font-bold uppercase tracking-wider">KANGIS LIS</span>
    </div>
    <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sside"></i>
  </div>

  <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="sside">
    <!-- a. Indexing (collapsible) -->
    @if($hasRole('KANGIS - Indexing') || $hasRole('KANGIS') || $hasRole('Kangis-Indexing') || $hasRole('Supper Admin'))
    <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="kangis-indexing">
      <div class="flex items-center gap-2">
        <i data-lucide="file-search" class="h-4 w-4 text-yellow-500"></i>
        <span>Indexing</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="kangis-indexing"></i>
    </div>
    <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="kangis-indexing">
      <!-- i. File Indexing Assistant -->
      <a href="{{ route('kangis.indexed-files') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('kangis.indexed-files') ? 'active' : '' }}">
        <i data-lucide="folder-open" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>File Indexing Assistant</span>
      </a>
      <!-- ii. File History View -->
      <a href="{{ route('file-index-view.index', ['url' => 'kangis']) }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') && request()->query('url') === 'kangis' ? 'active' : '' }}">
        <i data-lucide="history" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>File History View</span>
      </a>
      <!-- iii. File SerialNo Grouping (collapsible) -->
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="kangis-serial-grouping">
        <div class="flex items-center gap-2">
          <i data-lucide="layers" class="h-3.5 w-3.5 text-yellow-400"></i>
          <span>File SerialNo Grouping</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="kangis-serial-grouping"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="kangis-serial-grouping">
        <!-- 1. SerialNo Grouping -->
        <a href="#"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="list-ordered" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>SerialNo Grouping</span>
        </a>
        <!-- 2. Print Files Label -->
        <a href="{{ route('kangis-printlabel.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('kangis-printlabel.index') ? 'active' : '' }}">
          <i data-lucide="printer" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>Print Files Label</span>
        </a>
      </div>
    </div>
    @endif

    <!-- b. Recertification -->
    @if($hasRole('KANGIS - Recertification') || $hasRole('Recertification - Application') || $hasRole('Recertification - Bills & Payments') || 
        $hasRole('Recertification - Migrate Data') || $hasRole('Recertification - Verification Sheet') || 
        $hasRole('GIS - Data Capture') || $hasRole('Recertification - Vetting Sheet') ||
        $hasRole('Recertification - EDMS') || $hasRole('Recertification - Certification') || 
        $hasRole("Recertification - DG's List") || $hasRole('Recertification - Governors List') || $hasRole('Supper Admin'))
    <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="recertification-kangis">
      <div class="flex items-center gap-2">
        <i data-lucide="refresh-cw" class="h-4 w-4 text-yellow-500"></i>
        <span>Recertification</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="recertification-kangis"></i>
    </div>

    <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="recertification-kangis">
      <!-- i. Application -->
      <a href="{{ route('recertification.index', ['url' => 'kangis']) }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.index') && request('url') === 'kangis' ? 'active' : '' }}">
        <i data-lucide="file-plus" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Application</span>
      </a>
    
      <!-- ii. Bills & Payments -->
      <a href="{{ route('recertification.bills-payments') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.bills-payments') ? 'active' : '' }}">
        <i data-lucide="dollar-sign" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Bills & Payments</span>
      </a>

      <!-- iii. Migrate Data -->
      <a href="{{ route('recertification.migrate') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.migrate') ? 'active' : '' }}">
        <i data-lucide="database-backup" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Migrate Data</span>
      </a>

      <!-- iv. Verification Sheet -->
      <a href="{{ route('recertification.verification-sheet') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.verification-sheet') ? 'active' : '' }}">
        <i data-lucide="check-square" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Verification Sheet</span>
      </a>

      <!-- v. GIS Data Capture -->
      <a href="{{ route('recertification.gis-data-capture') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.gis-data-capture') ? 'active' : '' }}">
        <i data-lucide="map" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>GIS Data Capture</span>
      </a>

      <!-- vi. Vetting Sheet -->
      <a href="{{ route('recertification.vetting-sheet') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.vetting-sheet') ? 'active' : '' }}">
        <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Vetting Sheet</span>
      </a>

      <!-- vii. EDMS -->
      <a href="{{ route('recertification.edms') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.edms') ? 'active' : '' }}">
        <i data-lucide="hard-drive" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>DMS</span>
      </a>

      <!-- viii. Certification -->
      <a href="{{ route('recertification.certification') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.certification') ? 'active' : '' }}">
        <i data-lucide="award" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Certification</span>
      </a>

      <!-- ix. DG's List -->
      <a href="{{ route('recertification.dg-list') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.dg-list') ? 'active' : '' }}">
        <i data-lucide="list-end" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>DG's List</span>
      </a>

      <!-- x. Governors List -->
      <a href="{{ route('recertification.governors-list') }}" 
         class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.governors-list') ? 'active' : '' }}">
        <i data-lucide="list" class="h-3.5 w-3.5 text-yellow-400"></i>
        <span>Governors List</span>
      </a>
    </div>
    @endif

    <!-- c. Digital Archive -->
    @if($hasRole('KANGIS - Digital Archive') || $hasRole('Supper Admin'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-kangis">
        <div class="flex items-center gap-2">
          <i data-lucide="archive" class="h-4 w-4 text-yellow-500"></i>
          <span>Digital Archive</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-kangis"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-kangis">
        <!-- i. File Tracker Dashboard -->
        <a href="{{ route('file-tracker.dashboard', ['url' => 'kangis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'kangis' ? 'active' : '' }}">
          <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-yellow-400"></i>
          <span>File Tracker Dashboard</span>
        </a>

        <!-- ii. File Tracker (Archive) -->
        <a href="{{ route('track-file-archive.index', ['url' => 'kangis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'kangis' ? 'active' : '' }}">
          <i data-lucide="archive" class="h-3.5 w-3.5 text-yellow-400"></i>
          <span>File Tracker (Archive)</span>
        </a>

        <!-- iii. Quick Search (scoped to KANGIS registry files) -->
        <a href="{{ route('create-file-tracker.quick-search', ['url' => 'kangis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'kangis' ? 'active' : '' }}">
          <i data-lucide="search" class="h-3.5 w-3.5 text-yellow-400"></i>
          <span>Quick Search</span>
        </a>

        <!-- iv. Log a File -->
        <a href="{{ route('create-file-tracker.index', ['url' => 'kangis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'kangis' && request('action') !== 'request-land' ? 'active' : '' }}">
          <i data-lucide="file-plus" class="h-3.5 w-3.5 text-yellow-400"></i>
          <span>Log a File</span>
        </a>

        <!-- iv. EDMS Update -->
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-kangis-edms">
          <div class="flex items-center gap-2">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-yellow-400"></i>
            <span>DMS Update</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-kangis-edms"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-kangis-edms">
          <a href="/scanning?url=kangis" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-yellow-300"></i>
            <span>Scan More</span>
          </a>
          <a href="/pagetyping?url=kangis" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-yellow-300"></i>
            <span>Type More</span>
          </a>
        </div>
      </div>
    @endif

    <!-- d. Director GIS Recommendation -->
    @if($hasRole('DGIS') || $hasRole('Supper Admin'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="dgis-recommendation">
        <div class="flex items-center gap-2">
          <i data-lucide="map-pin-check" class="h-4 w-4 text-yellow-500"></i>
          <span>Director GIS Recommendation</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="dgis-recommendation"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ request()->routeIs('create-file-tracker.index') && (request('url') === 'dgis' || (request('url') === 'kangis' && request('action') === 'request-land' && request('from') === 'dgis')) ? '' : 'hidden' }}" data-content="dgis-recommendation">
        <a href="{{ route('create-file-tracker.index', ['url' => 'dgis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'dgis' && request('action') !== 'request-land' ? 'active' : '' }}">
          <i data-lucide="folder-search" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>Track Existing Files (Recommendation)</span>
        </a>
        <a href="{{ route('create-file-tracker.index', ['url' => 'kangis', 'action' => 'request-land', 'from' => 'dgis']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'kangis' && request('action') === 'request-land' && request('from') === 'dgis' ? 'active' : '' }}">
          <i data-lucide="send" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>Request Land File</span>
        </a>
      </div>
    @endif

    <!-- e. DG Approval -->
    @if($hasRole('DG') || $hasRole('Supper Admin'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="dg-approval">
        <div class="flex items-center gap-2">
          <i data-lucide="badge-check" class="h-4 w-4 text-yellow-500"></i>
          <span>DG Approval</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="dg-approval"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ request()->routeIs('create-file-tracker.index') && (request('url') === 'dg' || (request('url') === 'kangis' && request('action') === 'request-land' && request('from') === 'dg')) ? '' : 'hidden' }}" data-content="dg-approval">
        <a href="{{ route('create-file-tracker.index', ['url' => 'dg']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'dg' && request('action') !== 'request-land' ? 'active' : '' }}">
          <i data-lucide="folder-check" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>Track Existing File (Approval)</span>
        </a>
        <a href="{{ route('create-file-tracker.index', ['url' => 'kangis', 'action' => 'request-land', 'from' => 'dg']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'kangis' && request('action') === 'request-land' && request('from') === 'dg' ? 'active' : '' }}">
          <i data-lucide="send" class="h-3.5 w-3.5 text-yellow-300"></i>
          <span>Request Land File</span>
        </a>
      </div>
    @endif

    <!-- f. File Digital Library – Doc-WARE -->
    @if($hasRole('KANGIS - File Digital Library') || $hasRole('KANGIS') || $hasRole('Supper Admin'))
      <a href="{{ route('filearchive.index', ['url' => 'kangis']) }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'kangis' ? 'active' : '' }}">
        <i data-lucide="library" class="h-4 w-4 text-yellow-500"></i>
        <span>File Digital Library – Doc-WARE</span>
      </a>
    @endif


  </div>
</div>
@endif
