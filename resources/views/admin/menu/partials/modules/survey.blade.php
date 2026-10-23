<!-- 15. Survey -->
{{-- Menu order follows the Survey outline (user, 2026-10-22). Items without a page yet render as
     muted "Soon" entries via $svSoon; give them a route when the page is built. --}}

    @if(
      $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') ||
      $hasRole('Survey - GIS') || $hasRole('Survey - E-Registry') ||
      $hasRole('Survey Reports') || $hasRole('Generate New FileNo (GKNFileNo)') || $hasRole('Supper Admin')
    )
    @php
      $svSoon = fn (string $label, string $icon) =>
          '<a href="#" onclick="return false;" aria-disabled="true" title="Coming soon" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md opacity-60 cursor-default">'
          . '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i>'
          . '<span>' . e($label) . '</span>'
          . '<span class="ml-auto text-[10px] uppercase tracking-wide text-gray-400">Soon</span></a>';
      $svRecords = $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') || $hasRole('Supper Admin');
    @endphp
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

        @if($svRecords)
        {{-- b. Compensation --}}
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-compensation">
          <div class="flex items-center gap-2">
            <i data-lucide="hand-coins" class="h-4 w-4 text-pink-500"></i>
            <span>Compensation</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-compensation"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-compensation">

          {{-- b.i Records --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-records">
            <div class="flex items-center gap-2">
              <i data-lucide="folder-open" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Records</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-records"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-records">
            {!! $svSoon('CO Files', 'folder') !!}
            {!! $svSoon('Allocation Records', 'clipboard-list') !!}
            <a href="{{ route('survey-module.workflow.examination') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.examination') ? 'active' : '' }}">
              <i data-lucide="check-check" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>OP Verification</span>
            </a>
          </div>

          {{-- b.ii Project Management --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-projects">
            <div class="flex items-center gap-2">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Project Management</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-projects"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-projects">
            {!! $svSoon('Commission CO File', 'file-plus') !!}
            {{-- Create Project is a button on the Projects page. --}}
            <a href="{{ route('survey-module.compensation.projects') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.projects', 'survey-module.compensation.projects.create', 'survey-module.compensation.projects.edit') ? 'active' : '' }}">
              <i data-lucide="folders" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>All Projects</span>
            </a>
          </div>

          {{-- b.iii Compensation Cases --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-cases">
            <div class="flex items-center gap-2">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Cases</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-cases"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-cases">
            <a href="{{ route('survey-module.compensation.beneficiaries') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.beneficiaries') ? 'active' : '' }}">
              <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Beneficiaries</span>
            </a>
            <a href="{{ route('survey-module.compensation.trees') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.trees') ? 'active' : '' }}">
              <i data-lucide="tree-pine" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Economic Trees</span>
            </a>
            {{-- Register Case is a button on the Cases page. --}}
            <a href="{{ route('survey-module.compensation.cases') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.cases', 'survey-module.compensation.cases.register', 'survey-module.compensation.cases.show', 'survey-module.compensation.cases.edit') ? 'active' : '' }}">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Cases</span>
            </a>
            <a href="{{ route('survey-module.compensation.calculator') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.calculator') ? 'active' : '' }}">
              <i data-lucide="calculator" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Calculator</span>
            </a>
            <a href="{{ route('survey-module.compensation.land') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.land') ? 'active' : '' }}">
              <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>OP Allocation</span>
            </a>
          </div>

          {{-- b.iv Complaints --}}
          {!! $svSoon('Complaints', 'message-square-warning') !!}
        </div>
        @endif

        {{-- c. GKN --}}
        @if($svRecords || $hasRole('Generate New FileNo (GKNFileNo)'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gkn">
          <div class="flex items-center gap-2">
            <i data-lucide="building-2" class="h-4 w-4 text-pink-500"></i>
            <span>GKN</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-gkn"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gkn">

          {{-- c.i Applications --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-applications">
            <div class="flex items-center gap-2"><i data-lucide="folders" class="h-3.5 w-3.5 text-pink-400"></i><span>Applications</span></div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-applications"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-applications">
            @if($svRecords)
            {{-- GKN Files = the Government Lands register; Register GKN is its button. --}}
            <a href="{{ route('survey-module.gkn.lands') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.lands', 'survey-module.gkn.register', 'survey-module.gkn.edit') ? 'active' : '' }}">
              <i data-lucide="landmark" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>GKN Files</span>
            </a>
            @endif
            {{-- Generate New FileNo is kept for users who hold only that role. --}}
            @if($hasRole('Generate New FileNo (GKNFileNo)') || $hasRole('Supper Admin'))
            <a href="{{ route('gkn-generation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('gkn-generation.index') ? 'active' : '' }}">
              <i data-lucide="plus-square" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Generate New FileNo (GKNFileNo)</span>
            </a>
            @endif
            @if($svRecords)
            <a href="{{ route('survey-module.records.lpkn') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn') && request('view') === 'lpkn' ? 'active' : '' }}">
              <i data-lucide="layers" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>LPKN</span>
            </a>
            {!! $svSoon('LPPL', 'layers-3') !!}
            <a href="{{ route('survey-module.records.misc') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.misc') ? 'active' : '' }}">
              <i data-lucide="tags" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Misc KN</span>
            </a>
            @endif
          </div>

          @if($svRecords)
          {{-- c.ii - c.vii --}}
          <a href="{{ route('survey-module.records.lpkn') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn') ? 'active' : '' }}">
            <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i><span>Layout Plan</span>
          </a>
          <a href="{{ route('survey-module.records.surveyors.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.surveyors.*') ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors</span>
          </a>
          <a href="{{ route('survey-module.records.lpkn.instruction') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn.instruction') ? 'active' : '' }}">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-pink-400"></i><span>Instruction to Survey</span>
          </a>
          <a href="{{ route('survey-module.records.lpkn.report') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn.report') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors Report</span>
          </a>
          <a href="{{ route('survey-module.records.lpkn.coordinates') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn.coordinates') ? 'active' : '' }}">
            <i data-lucide="map-pin" class="h-3.5 w-3.5 text-pink-400"></i><span>List of Coordinates</span>
          </a>
          <a href="{{ route('survey-module.records.lpkn.observations') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn.observations') ? 'active' : '' }}">
            <i data-lucide="calculator" class="h-3.5 w-3.5 text-pink-400"></i><span>Computation Field Observations</span>
          </a>
          @endif
        </div>
        @endif

        {{-- d. Geo-informatics --}}
        @if($hasRole('Survey - GIS') || $hasRole('Survey - Records') || $hasRole('Survey Reports') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gis">
          <div class="flex items-center gap-2">
            <i data-lucide="map-pinned" class="h-4 w-4 text-pink-500"></i>
            <span>Geo-informatics</span>
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

        @if($svRecords)
        {{-- e. Examination/Verification --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-examination">
            <div class="flex items-center gap-2">
              <i data-lucide="workflow" class="h-4 w-4 text-pink-500"></i>
              <span>Examination/Verification</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-examination"></i>
          </div>
          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-examination">
            {!! $svSoon('Register File', 'file-input') !!}
            {!! $svSoon('Boundary Dispute', 'split') !!}
            {!! $svSoon('Old Surveys', 'history') !!}
            {!! $svSoon('Control Points', 'crosshair') !!}
            <a href="{{ route('survey-module.workflow.beacon-register.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.beacon-register.*') ? 'active' : '' }}">
              <i data-lucide="map-pin" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Beacon Register</span>
            </a>
            {!! $svSoon('Coordinate Register', 'grid-3x3') !!}
            {!! $svSoon('Surveyors\' Report', 'file-check') !!}
          </div>
        @endif

        {{-- f. File Digital Library / g. Digital Archive --}}
        @if($hasRole('Survey - E-Registry') || $hasRole('Supper Admin'))
          <a href="{{ route('filearchive.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="library" class="h-4 w-4 text-pink-500"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>
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

          <a href="#" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>DMS Update</span>
          </a>
        </div>
        @endif

        {{-- h. Survey Reports --}}
        @if($hasRole('Survey Reports') || $hasRole('Survey - Records') || $hasRole('Supper Admin'))
        <a href="{{ route('survey-module.reports') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.reports') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-pink-500"></i>
          <span>Survey Reports</span>
        </a>
        @endif

      </div>
    </div>
    @endif
