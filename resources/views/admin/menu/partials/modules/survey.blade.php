<!-- 15. Survey -->
{{-- Menu order follows the Survey outline (user, 2026-10-22). Items without a page yet render as
     muted "Soon" entries via $svSoon; give them a route when the page is built. --}}

    @if(
      $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') ||
      $hasRole('Survey - GIS') || $hasRole('Survey - E-Registry') ||
      $hasRole('Survey Reports') || $hasRole('Survey - Beacon Register') || $hasRole('Supper Admin')
    )
    @php
      $svSoon = fn (string $label, string $icon) =>
          '<a href="#" onclick="return false;" aria-disabled="true" title="Coming soon" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md opacity-60 cursor-default">'
          . '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i>'
          . '<span>' . e($label) . '</span>'
          . '<span class="ml-auto text-[10px] uppercase tracking-wide text-gray-400">Soon</span></a>';
      $svRecords = $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') || $hasRole('Supper Admin');
      // Beacon Register has its own role (user, 2026-10-23); it shows alone for a user who holds only that.
      $svBeacon  = $hasRole('Survey - Beacon Register');
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

          {{-- b.i Project Management (before Records: spec p.3 arrow, user 2026-10-26) --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-projects">
            <div class="flex items-center gap-2">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Project Management</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-projects"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-projects">
            <a href="{{ route('survey-module.compensation.co-files.create') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.co-files.create') ? 'active' : '' }}">
              <i data-lucide="file-plus" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Commission CO File</span>
            </a>
            {{-- Create Project is a button on the Projects page. --}}
            <a href="{{ route('survey-module.compensation.projects') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.projects', 'survey-module.compensation.projects.create', 'survey-module.compensation.projects.edit') ? 'active' : '' }}">
              <i data-lucide="folders" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Projects</span>
            </a>
          </div>

          {{-- b.ii Records --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-records">
            <div class="flex items-center gap-2">
              <i data-lucide="folder-open" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Records</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-records"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-records">
            <a href="{{ route('survey-module.compensation.co-files') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.co-files', 'survey-module.compensation.co-files.edit') ? 'active' : '' }}">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>CO Files</span>
            </a>
            <a href="{{ route('survey-module.compensation.allocation-records') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.allocation-records') ? 'active' : '' }}">
              <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Allocation Records</span>
            </a>
            {{-- The Deeds OP Verification page, opened with ?url=survey (user, 2026-10-27); Deeds' own entry stays unlit. --}}
            <a href="{{ route('op-verifications.index', ['url' => 'survey']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('op-verifications.*') && request('url') === 'survey') || request()->routeIs('survey-module.compensation.op-verification') ? 'active' : '' }}">
              <i data-lucide="check-check" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>OP Verification</span>
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
          <a href="{{ route('survey-module.compensation.complaints') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.complaints*') ? 'active' : '' }}">
            <i data-lucide="message-square-warning" class="h-4 w-4 text-pink-400"></i>
            <span>Complaints</span>
          </a>
        </div>
        @endif

        {{-- c. GKN --}}
        @if($svRecords)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gkn">
          <div class="flex items-center gap-2">
            <i data-lucide="building-2" class="h-4 w-4 text-pink-500"></i>
            <span>GKN</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-gkn"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gkn">

          {{-- c.i Applications: GKN / LPKN / LPPL / Misc KN as tabs on one page (transcript, 2026-10-25).
               The old GKN Files, LPKN and Misc KN pages keep their routes but leave the sidebar. --}}
          @if($svRecords)
          <a href="{{ route('survey-module.gkn.applications') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.applications') ? 'active' : '' }}">
            <i data-lucide="folders" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Applications</span>
          </a>
          @endif

          @if($svRecords)
          {{-- c.ii - c.vii --}}
          <a href="{{ route('survey-module.records.lpkn') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.lpkn') ? 'active' : '' }}">
            <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i><span>Layout Plan</span>
          </a>
          <a href="{{ route('survey-module.records.surveyors.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.surveyors.*') ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors</span>
          </a>
          <a href="{{ route('survey-module.gkn.its.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.its.*') ? 'active' : '' }}">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-pink-400"></i><span>Instruction to Survey</span>
          </a>
          <a href="{{ route('survey-module.gkn.reports.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.reports.*') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors Report</span>
          </a>
          <a href="{{ route('survey-module.gkn.coordinates.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.coordinates.*') ? 'active' : '' }}">
            <i data-lucide="map-pin" class="h-3.5 w-3.5 text-pink-400"></i><span>List of Coordinates</span>
          </a>
          <a href="{{ route('survey-module.gkn.observations.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.observations.*') ? 'active' : '' }}">
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

        @if($svRecords || $svBeacon)
        {{-- e. Examination/Verification --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-examination">
            <div class="flex items-center gap-2">
              <i data-lucide="workflow" class="h-4 w-4 text-pink-500"></i>
              <span>Examination/Verification</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey-examination"></i>
          </div>
          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-examination">
            @if($svRecords)
            <a href="{{ route('survey-module.workflow.register-file.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.register-file.*') ? 'active' : '' }}">
              <i data-lucide="file-input" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Register File</span>
            </a>
            <a href="{{ route('survey-module.workflow.boundary-disputes.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.boundary-disputes.*') ? 'active' : '' }}">
              <i data-lucide="split" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Boundary Dispute</span>
            </a>
            <a href="{{ route('survey-module.workflow.old-surveys.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.old-surveys.*') ? 'active' : '' }}">
              <i data-lucide="history" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Old Surveys</span>
            </a>
            <a href="{{ route('survey-module.workflow.control-points.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.control-points.*') ? 'active' : '' }}">
              <i data-lucide="crosshair" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Control Points</span>
            </a>
            @endif
            @if($svBeacon)
            <a href="{{ route('survey-module.workflow.beacon-register.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.beacon-register.*') ? 'active' : '' }}">
              <i data-lucide="map-pin" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Beacon Register</span>
            </a>
            @endif
            @if($svRecords)
            <a href="{{ route('survey-module.workflow.coordinate-register.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.coordinate-register.*') ? 'active' : '' }}">
              <i data-lucide="grid-3x3" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Coordinate Register</span>
            </a>
            <a href="{{ route('survey-module.workflow.change-reports.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.change-reports.*') ? 'active' : '' }}">
              <i data-lucide="file-check" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Surveyors' Report</span>
            </a>
            @endif
          </div>
        @endif

        {{-- File Status (user, 2026-10-26): the same pages as Cadastral > Verification > File Status,
             right after Examination/Verification. ?url=survey opens each one view only and keeps
             the other modules' entries for these pages unlit. --}}
        @if($svRecords)
          @php
            $svFs = request('url') === 'survey';
            $svLink = fn (string $label, string $href, string $icon, bool $on) =>
                '<a href="' . e($href) . '" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 ' . ($on ? 'active' : '') . '">'
                . '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i><span>' . e($label) . '</span></a>';
            $svGroup = fn (string $key, string $label, string $icon, bool $sub = false) =>
                '<div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="' . e($key) . '">'
                . '<div class="flex items-center gap-2"><i data-lucide="' . e($icon) . '" class="' . ($sub ? 'h-3.5 w-3.5 text-pink-400' : 'h-4 w-4 text-pink-500') . '"></i><span>' . e($label) . '</span></div>'
                . '<i data-lucide="chevron-right" class="' . ($sub ? 'h-3.5 w-3.5' : 'h-4 w-4') . ' transition-transform duration-200" data-chevron="' . e($key) . '"></i></div>';
          @endphp
          {!! $svGroup('survey-file-status', 'File Status', 'file-search') !!}
          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-file-status">
            {!! $svLink('RofO', route('land-rofos.index', ['url' => 'survey']), 'file-check', $svFs && request()->routeIs('land-rofos.index')) !!}
            {!! $svLink('CofO', route('propertycard.cofo', ['url' => 'survey']), 'award', $svFs && request()->routeIs('propertycard.cofo')) !!}
            {!! $svLink('Decommissioned Files', route('file-decommissioning.decommissioned', ['url' => 'survey']), 'archive', $svFs && request()->routeIs('file-decommissioning.decommissioned')) !!}
            {!! $svLink('Duplicate Files', route('file-search-db.index', ['url' => 'survey']), 'copy', $svFs && request()->routeIs('file-search-db.index')) !!}

            {!! $svGroup('survey-regrant-files', 'Regrant Files', 'refresh-cw', true) !!}
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-regrant-files">
              {!! $svLink('Regrant Register', route('regrant.index', ['tab' => 'register', 'url' => 'survey']), 'list', $svFs && request()->routeIs('regrant.index') && request('tab') !== 'due') !!}
              {!! $svLink('To be Regranted', route('regrant.index', ['tab' => 'due', 'url' => 'survey']), 'alarm-clock', $svFs && request()->routeIs('regrant.index') && request('tab') === 'due') !!}
            </div>

            {!! $svLink('Title Status Update', route('title-status.index') . '?url=survey', 'badge-check', $svFs && request()->routeIs('title-status.index')) !!}
            {!! $svLink('DCIV Management', route('dciv-generation.index', ['url' => 'survey']), 'folder-kanban', $svFs && request()->routeIs('dciv-generation.index')) !!}

            {!! $svGroup('survey-encumbrance', 'Encumbrance', 'shield', true) !!}
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-encumbrance">
              {!! $svLink('Caveat', route('caveat.index') . '?url=survey', 'shield-alert', $svFs && request()->routeIs('caveat.index')) !!}
              {!! $svLink('Mortgage', route('mortgages.index') . '?url=survey', 'landmark', $svFs && request()->routeIs('mortgages.index')) !!}
              {!! $svLink('Surrender & Release', route('surrender-release.index', ['url' => 'survey']), 'key-round', $svFs && request()->routeIs('surrender-release.*')) !!}
              {!! $svLink('Lien', '#', 'lock', false) !!}
            </div>
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

          {{-- DMS Update: the shared DMS Scan More and More Pages screens, as Cadastral's Registry uses (2026-10-25). --}}
          <a href="{{ url('/scanning?url=scmore&survey=1') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('scanning') && request('url') === 'scmore' && request()->boolean('survey') ? 'active' : '' }}">
            <i data-lucide="scan" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>DMS Update · Scan More</span>
          </a>
          <a href="{{ url('/pagetyping?url=ptmore&survey=1') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('pagetyping') && request('url') === 'ptmore' && request()->boolean('survey') ? 'active' : '' }}">
            <i data-lucide="files" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>DMS Update · More Pages</span>
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
