    <!-- 13. SLTR/First Registration -->
    @if(
      $hasRole('SLTR - Overview') || $hasRole('SLTR - Indexing') || $hasRole('SLTR - Form') ||
      $hasRole('SLTR - Application') || $hasRole('SLTR - Claimants') ||
      $hasRole('SLTR - Legacy Data') || $hasRole('SLTR - Field Data') || $hasRole('SLTR - Payments') ||
      $hasRole('SLTR - Approvals') || $hasRole('SLTR - Other Departments') || $hasRole('SLTR - Memo') ||
      $hasRole('SLTR - Certificate') || $hasRole('SLTR - Digital Archive') || $hasRole('SLTR - GIS') || $hasRole('SLTR - Survey') ||
      $hasRole('SLTR - Reports') ||
      $hasRole('SLTR - CofO Front Page') || $hasRole('SLTR - CofO')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="sltr">
        <div class="flex items-center gap-2">
          <i data-lucide="file-search" class="h-5 w-5 text-[#65a30d]"></i>
          <span class="text-sm font-bold uppercase tracking-wider">SLTR/First Registration</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltr"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="sltr">
        @if($hasRole('SLTR - Overview'))
        <a href="{{route('sltroverview.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltroverview.index') ? 'active' : '' }}">
          <i data-lucide="file-text" class="h-4 w-4 text-[#65a30d]"></i>
          <span>Overview</span>
        </a>
        @endif
        @if($hasRole('SLTR - Indexing'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltr-indexing">
          <div class="flex items-center gap-2">
            <i data-lucide="file-search" class="h-4 w-4 text-[#65a30d]"></i>
            <span>SLTR Indexing</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltr-indexing"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltr-indexing">
          <!-- i. File Indexing Assistant -->
          <a href="{{ route('sltr.indexed-files') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltr.indexed-files') ? 'active' : '' }}">
            <i data-lucide="folder-open" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>File Indexing Assistant</span>
          </a>
          <!-- ii. File History View -->
          <a href="/file-index-view?url=sltr" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') && request()->has('sltr') ? 'active' : '' }}">
            <i data-lucide="history" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>File History View</span>
          </a>
          <!-- iii. File SerialNo Grouping (collapsible) -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltr-serial-grouping">
            <div class="flex items-center gap-2">
              <i data-lucide="layers" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>File SerialNo Grouping</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltr-serial-grouping"></i>
          </div>
          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltr-serial-grouping">
            <!-- 1. SerialNo Grouping -->
            <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="list-ordered" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>SerialNo Grouping</span>
            </a>
            <!-- 2. Print Files Label -->
            <a href="{{ route('sltr-printlabel.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltr-printlabel.index') ? 'active' : '' }}">
              <i data-lucide="printer" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>Print Files Label</span>
            </a>
          </div>
        </div>
        @endif
        @if($hasRole('SLTR - Form') || $hasRole('SLTR - Application') || $hasRole('SLTR - Claimants') || $hasRole('SLTR - Legacy Data') || $hasRole('SLTR - Field Data'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrForm">
          <div class="flex items-center gap-2">
            <i data-lucide="file-edit" class="h-4 w-4 text-[#65a30d]"></i>
            <span>SLTR Form</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrForm"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrForm">
          @if($hasRole('SLTR - Application'))
          <a href="{{route('sltrapplication.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltrapplication.index') ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Application</span>
          </a>
          @endif
          @if($hasRole('SLTR - Claimants'))
          <a href="/programmes/sltr/claimants" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="users" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Claimants</span>
          </a>
          @endif
          @if($hasRole('SLTR - Legacy Data'))
          <a href="/programmes/sltr/legacy-data" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltrlegacydata.index') ? 'active' : '' }}">
            <i data-lucide="history" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Legacy Data</span>
          </a>
          @endif
          @if($hasRole('SLTR - Field Data'))
          <a href="{{route('sltr_field_data.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Field Data</span>
          </a>
          @endif
        </div>
        @endif
        @if($hasRole('SLTR - Payments'))
        <a href="/programmes/sltr/payments" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="credit-card" class="h-4 w-4 text-[#65a30d]"></i>
          <span>Payments</span>
        </a>
        @endif
        
        @if($hasRole('SLTR - Approvals'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrApprovals">
          <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="h-4 w-4 text-[#65a30d]"></i>
            <span>Approvals</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrApprovals"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrApprovals">
          <a href="/programmes/sltr/approvals/planning" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Planning Recommendation</span>
          </a>
          <a href="/programmes/sltr/approvals/director" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="stamp" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Director SLTR</span>
          </a>
        </div>
        @endif
        
        @if($hasRole('SLTR - Other Departments'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrDepartments">
          <div class="flex items-center gap-2">
            <i data-lucide="building-2" class="h-4 w-4 text-[#65a30d]"></i>
            <span>Other Departments</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrDepartments"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrDepartments">
          <a href="/programmes/sltr/departments/lands" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Lands</span>
          </a>
          <a href="{{route('sltrapproval.deeds')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltrapproval.deeds') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Deeds</span>
          </a>
          <a href="/programmes/sltr/departments/survey" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Survey</span>
          </a>
          <a href="/programmes/sltr/departments/cadastral" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Cadastral</span>
          </a>
        </div>
        @endif
        
        @if($hasRole('SLTR - Memo'))
        <a href="/programmes/sltr/memo" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="clipboard-list" class="h-4 w-4 text-[#65a30d]"></i>
          <span>SLTR Memo</span>
        </a>
        @endif        
        @if($hasRole('SLTR - Certificate') || $hasRole('SLTR - GIS') || $hasRole('SLTR - Survey') || $hasRole('SLTR - Reports') || $hasRole('SLTR - CofO Front Page') || $hasRole('SLTR - CofO'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrCertificate">
          <div class="flex items-center gap-2">
            <i data-lucide="file-badge" class="h-4 w-4 text-[#65a30d]"></i>
            <span>Certificate</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrCertificate"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrCertificate">
          <a href="{{ route('sltr-recommendations.index') }}"
             class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltr-recommendations.index') ? 'active' : '' }}">
            <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Recommendation</span>
          </a>
          <a href="/sltr-rofos"
             class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('sltr-rofos*') ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>RofO</span>
          </a>
          {{-- CofO Workflow, as ST has it: a group holding Front Page (generate the front
               side) and CofO (front + TDP, the complete certificate). The section name is
               SLTR's own so it does not open and close with ST's cofo-workflow group.
               Each screen is its own module; the group shows if either is held. --}}
          @if($hasRole('SLTR - CofO Front Page') || $hasRole('SLTR - CofO'))
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
               data-section="sltr-cofo-workflow">
            <div class="flex items-center gap-2">
              <i data-lucide="git-branch" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>CofO Workflow</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltr-cofo-workflow"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltr-cofo-workflow">
            @if($hasRole('SLTR - CofO Front Page'))
            <a href="{{ route('sltr-cofo.front-page') }}"
               class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltr-cofo.front-page', 'sltr-cofo.generate') ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>Front Page</span>
            </a>
            @endif
            @if($hasRole('SLTR - CofO'))
            <a href="{{ route('sltr-cofo.index') }}"
               class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sltr-cofo.index') ? 'active' : '' }}">
              <i data-lucide="file-cog" class="h-3.5 w-3.5 text-[#65a30d]"></i>
              <span>CofO</span>
            </a>
            @endif
          </div>
          @endif
        </div>
        @endif
        
        @if($hasRole('SLTR - Digital Archive') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-sltr">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-[#65a30d]"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-sltr"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-sltr">
          <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>File Tracker Dashboard</span>
          </a>
          <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>File Tracker (Archive)</span>
          </a>
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'sltr']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'sltr' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Quick Search</span>
          </a>
          <a href="{{ route('create-file-tracker.index', ['url' => 'sltr']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'sltr' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Log a File</span>
          </a>
          <a href="{{ route('filearchive.index', ['url' => 'sltr']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'sltr' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>
          <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>DMS Update</span>
          </a>
        </div>
        @endif
        
        @if($hasRole('SLTR - GIS'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrGis">
          <div class="flex items-center gap-2">
            <i data-lucide="map" class="h-4 w-4 text-[#65a30d]"></i>
            <span>GIS</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrGis"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrGis">
          <a href="/programmes/sltr/gis/attribution" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="database" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Attribution</span>
          </a>
          <a href="/programmes/sltr/gis/map" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="map-pin" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Map</span>
          </a>
        </div>
        @endif
        
        @if($hasRole('SLTR - Survey'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="sltrSurvey">
          <div class="flex items-center gap-2">
            <i data-lucide="land-plot" class="h-4 w-4 text-[#65a30d]"></i>
            <span>Survey</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="sltrSurvey"></i>
        </div>
        
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="sltrSurvey">
          <a href="/programmes/sltr/survey/attribution" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="land-plot" class="h-3.5 w-3.5 text-[#65a30d]"></i>
            <span>Attribution</span>
          </a>
        </div>
        @endif

        <a href="/programmes/sltr/reports" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-[#65a30d]"></i>
          <span>Reports</span>
        </a>
      </div>
    </div>
    @endif
