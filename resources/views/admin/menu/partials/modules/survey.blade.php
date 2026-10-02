<!-- 15. Survey -->

    @if(
      $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') ||
      $hasRole('Survey - GIS') || $hasRole('Survey - E-Registry') ||
      $hasRole('Survey Reports') || $hasRole('Generate New FileNo (GKNFileNo)')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="survey">
        <div class="flex items-center gap-2">
          <i data-lucide="compass" class="h-5 w-5 text-pink-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Survey</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="survey">

        {{-- a. Dashboard --}}
        @if($hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') || $hasRole('Survey - GIS') || $hasRole('Survey - E-Registry') || $hasRole('Survey Reports') || $hasRole('Supper Admin'))
        <a href="{{ route('survey-module.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.dashboard') ? 'active' : '' }}">
          <i data-lucide="layout-dashboard" class="h-4 w-4 text-pink-500"></i>
          <span>Dashboard</span>
        </a>
        @endif

        @if($hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') || $hasRole('Supper Admin'))

        {{-- b. Compensation --}}
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-compensation">
          <div class="flex items-center gap-2">
            <i data-lucide="hand-coins" class="h-4 w-4 text-pink-500"></i>
            <span>Compensation</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-compensation"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-compensation">
          <a href="{{ route('survey-module.compensation.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.dashboard') ? 'active' : '' }}">
            <i data-lucide="gauge" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Dashboard</span>
          </a>

          {{-- Project Management --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-projects">
            <div class="flex items-center gap-2">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Project Management</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-projects"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-projects">
            <a href="{{ route('survey-module.compensation.projects') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.projects') ? 'active' : '' }}">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>All Projects</span>
            </a>
            <a href="{{ route('survey-module.compensation.projects.create') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.projects.create') ? 'active' : '' }}">
              <i data-lucide="plus-circle" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Create Project</span>
            </a>
          </div>

          {{-- Compensation Cases --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-cases">
            <div class="flex items-center gap-2">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Cases</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-cases"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-cases">
            <a href="{{ route('survey-module.compensation.cases') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.cases') ? 'active' : '' }}">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>All Cases</span>
            </a>
            <a href="{{ route('survey-module.compensation.cases.register') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.cases.register') ? 'active' : '' }}">
              <i data-lucide="user-plus" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Register Case</span>
            </a>
            <a href="{{ route('survey-module.compensation.beneficiaries') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.beneficiaries') ? 'active' : '' }}">
              <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Beneficiaries</span>
            </a>
            <a href="{{ route('survey-module.compensation.trees') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.trees') ? 'active' : '' }}">
              <i data-lucide="tree-pine" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Economic Trees</span>
            </a>
            <a href="{{ route('survey-module.compensation.calculator') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.calculator') ? 'active' : '' }}">
              <i data-lucide="calculator" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Calculator</span>
            </a>
            <a href="{{ route('survey-module.compensation.land') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.land') ? 'active' : '' }}">
              <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Land Allocation</span>
            </a>
            <a href="{{ route('survey-module.compensation.op') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.op') ? 'active' : '' }}">
              <i data-lucide="file-signature" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>OP Generation</span>
            </a>
            <a href="{{ route('survey-module.compensation.reports') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.reports') ? 'active' : '' }}">
              <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Reports</span>
            </a>
          </div>

          {{-- b.i Workflow — sits under Compensation --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-workflow">
            <div class="flex items-center gap-2">
              <i data-lucide="workflow" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Workflow</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-workflow"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-workflow">
            <a href="{{ route('survey-module.workflow.examination') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.examination') ? 'active' : '' }}">
              <i data-lucide="check-check" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Examination</span>
            </a>
            <a href="{{ route('survey-module.workflow.occupancy') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.occupancy') ? 'active' : '' }}">
              <i data-lucide="git-branch" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Occupancy Permit Workflow</span>
            </a>
          </div>
        </div>

        {{-- c. LPKN --}}
        <a href="{{ route('survey-module.records.lpkn') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn') ? 'active' : '' }}">
          <i data-lucide="layers" class="h-4 w-4 text-pink-500"></i>
          <span>LPKN</span>
        </a>

        {{-- d. Misc KN --}}
        <a href="{{ route('survey-module.records.misc') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.misc') ? 'active' : '' }}">
          <i data-lucide="tags" class="h-4 w-4 text-pink-500"></i>
          <span>Misc KN</span>
        </a>

        {{-- e. GKN --}}
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gkn">
          <div class="flex items-center gap-2">
            <i data-lucide="building-2" class="h-4 w-4 text-pink-500"></i>
            <span>GKN</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-gkn"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gkn">
          <a href="{{ route('survey-module.gkn.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.dashboard') ? 'active' : '' }}">
            <i data-lucide="gauge" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Dashboard</span>
          </a>
          <a href="{{ route('survey-module.gkn.lands') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.lands') ? 'active' : '' }}">
            <i data-lucide="landmark" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Government Lands</span>
          </a>
          <a href="{{ route('survey-module.gkn.register') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.register') ? 'active' : '' }}">
            <i data-lucide="plus-circle" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Register GKN</span>
          </a>
          <a href="{{ route('survey-module.gkn.tracking') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.tracking') ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Tracking</span>
          </a>
          <a href="{{ route('survey-module.gkn.reports') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.reports') ? 'active' : '' }}">
            <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Reports</span>
          </a>

          {{-- e.i Generate New FileNo — sits under GKN --}}
          @if($hasRole('Generate New FileNo (GKNFileNo)') || $hasRole('Supper Admin'))
          <a href="{{ route('gkn-generation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('gkn-generation.index') ? 'active' : '' }}">
            <i data-lucide="plus-square" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Generate New FileNo (GKNFileNo)</span>
          </a>
          @endif
        </div>
        @endif

        {{-- e.i fallback: the FileNo generator for users who only hold that role --}}
        @if($hasRole('Generate New FileNo (GKNFileNo)') && !($hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') || $hasRole('Supper Admin')))
        <a href="{{ route('gkn-generation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('gkn-generation.index') ? 'active' : '' }}">
          <i data-lucide="plus-square" class="h-4 w-4 text-pink-500"></i>
          <span>Generate New FileNo (GKNFileNo)</span>
        </a>
        @endif

        {{-- f. Records --}}
        @if($hasRole('Survey - Records') || $hasRole('Supper Admin'))
        <a href="{{ route('survey_record.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_record.index') ? 'active' : '' }}">
          <i data-lucide="clipboard" class="h-4 w-4 text-pink-500"></i>
          <span>Records</span>
        </a>
        @endif

        {{-- g. GIS --}}
        @if($hasRole('Survey - GIS') || $hasRole('Survey - Records') || $hasRole('Survey Reports') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gis">
          <div class="flex items-center gap-2">
            <i data-lucide="map-pinned" class="h-4 w-4 text-pink-500"></i>
            <span>GIS</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-gis"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gis">
          <a href="{{ route('survey-module.tools.gis') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.tools.gis') ? 'active' : '' }}">
            <i data-lucide="wrench" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Tools</span>
          </a>
          <a href="{{ route('survey-module.tools.plot-allocation') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.tools.plot-allocation') ? 'active' : '' }}">
            <i data-lucide="table" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Plot Allocation</span>
          </a>
          @if($hasRole('Survey Reports') || $hasRole('Supper Admin'))
          <a href="{{ route('survey_plan_extraction.index') }}?url=survey" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Survey Plan Extraction</span>
          </a>
          @endif
        </div>
        @endif

        {{-- h. Digital Archive --}}
        @if($hasRole('Survey - E-Registry') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-survey">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-pink-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-survey"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-survey">
          <a href="{{ route('file-tracker.dashboard', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Tracker Dashboard</span>
          </a>
          <a href="{{ route('track-file-archive.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Tracker (Archive)</span>
          </a>
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Quick Search</span>
          </a>
          <a href="{{ route('create-file-tracker.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Log a File</span>
          </a>
          <a href="{{ route('filearchive.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>
          <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>DMS Update</span>
          </a>
        </div>
        @endif

        {{-- i. Survey Reports --}}
        @if($hasRole('Survey Reports') || $hasRole('Survey - Records') || $hasRole('Supper Admin'))
        <a href="{{ route('survey-module.reports') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.reports') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-pink-500"></i>
          <span>Survey Reports</span>
        </a>
        @endif

      </div>
    </div>
    @endif
