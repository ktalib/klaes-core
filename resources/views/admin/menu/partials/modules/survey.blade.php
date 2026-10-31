<!-- 15. Survey -->
{{-- Menu order follows the Survey outline (user, 2026-10-22). Items without a page yet render as
     muted "Soon" entries via $svSoon; give them a route when the page is built. --}}

    @if(
      $hasRole('Survey - Records') || $hasRole('Survey - AI Digital Assistant') ||
      $hasRole('Survey - GIS') || $hasRole('Survey - E-Registry') ||
      $hasRole('Survey Reports') || $hasRole('Survey - Beacon Register') || $hasRole('Supper Admin') ||
      $hasRole('Survey - Compensation Check') || $hasRole('Survey - DSG') || $hasRole('Survey - Examination') || $hasRole('Survey - SG') ||
      $hasRole('Survey - Compensation') || $hasRole('Survey - Public Survey')
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
      // Page-area roles (user, 2026-10-30); Survey - Records still sees both areas.
      $svCompensation = $svRecords || $hasRole('Survey - Compensation');
      $svPublic       = $svRecords || $hasRole('Survey - Public Survey');
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

        @if($svCompensation)
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
            @php
              // Commission CO File = commissioned files, CO Files = captured files (user, 2026-10-30); an open
              // form or edit lights the entry of its own kind.
              $coEdit = request()->routeIs('survey-module.compensation.co-files.edit') ? request()->route('coFile') : null;
              $coCommissionOn = request()->routeIs('survey-module.compensation.co-files.commissioned')
                  || (request()->routeIs('survey-module.compensation.co-files.create') && request('mode') !== 'captured')
                  || ($coEdit && $coEdit->source === \App\Models\Survey\SurveyCoFile::SOURCE_COMMISSIONED);
              $coCapturedOn = request()->routeIs('survey-module.compensation.co-files')
                  || (request()->routeIs('survey-module.compensation.co-files.create') && request('mode') === 'captured')
                  || ($coEdit && $coEdit->source !== \App\Models\Survey\SurveyCoFile::SOURCE_COMMISSIONED);
            @endphp
            <a href="{{ route('survey-module.compensation.co-files.commissioned') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $coCommissionOn ? 'active' : '' }}">
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
            <a href="{{ route('survey-module.compensation.co-files') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $coCapturedOn ? 'active' : '' }}">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>CO Files</span>
            </a>
            {{-- Allocation Records removed from Records (CR 30102026 item 18); the page keeps its route. --}}
            {{-- The Deeds OP Verification page, opened with ?url=survey (user, 2026-10-27); Deeds' own entry stays unlit.
                 Was "Resettlement Plot Verification" (CR 30102026 item 20). --}}
            <a href="{{ route('op-verifications.index', ['url' => 'survey']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('op-verifications.*') && request('url') === 'survey') || (request()->routeIs('survey-module.compensation.op-verification') && request('via') !== 'examination') ? 'active' : '' }}">
              <i data-lucide="check-check" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Plot Showing Register</span>
            </a>
            {{-- Showings and ground checks from the Plot Confirmation app (2026-10-30); hidden from the sidebar for now (user, 2026-10-30). --}}
            @if (false)
            <a href="{{ route('survey-module.compensation.plot-confirmations') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.plot-confirmations') ? 'active' : '' }}">
              <i data-lucide="locate-fixed" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Plot Confirmation</span>
            </a>
            @endif
          </div>

          {{-- b.iii Compensation Management (was Compensation Cases; Workflow_V02 2.1, 2026-10-29) --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-cases">
            <div class="flex items-center gap-2">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Management</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-cases"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-cases">
            <a href="{{ route('survey-module.compensation.beneficiaries') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.beneficiaries') ? 'active' : '' }}">
              <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Farmers</span>
            </a>
            <a href="{{ route('survey-module.compensation.trees') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.trees') ? 'active' : '' }}">
              <i data-lucide="tree-pine" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Farm Assessment</span>
            </a>
            {{-- Register Land is a button on the Lands page (Cases renamed Lands, 2026-10-29); so is
                 Capture Compensation List (sidebar link removed 2026-10-30), which keeps this item lit. --}}
            <a href="{{ route('survey-module.compensation.cases') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.cases', 'survey-module.compensation.cases.register', 'survey-module.compensation.cases.show', 'survey-module.compensation.cases.edit', 'survey-module.compensation.capture-list') ? 'active' : '' }}">
              <i data-lucide="list" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Lands Assessment</span>
            </a>
            {{-- The Resettlement Calculator now opens from the Land to Land calculator here (CR 30102026 item 10). --}}
            <a href="{{ route('survey-module.compensation.calculator') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.calculator', 'survey-module.compensation.resettlement-calculator*') ? 'active' : '' }}">
              <i data-lucide="calculator" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Compensation Calculator</span>
            </a>
            {!! $svSoon('Map Sheet No', 'map') !!}
          </div>

          {{-- b.iv Resettlement Plot Allocation: standalone (was OP Allocation under Compensation Cases; Workflow_V02 2.3) --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-resettlement">
            <div class="flex items-center gap-2">
              <i data-lucide="land-plot" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Resettlement Plot Allocation</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-resettlement"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-resettlement">
            {{-- The Land to Land lands page (survey-module.compensation.land) is hidden from the menu (user, 2026-10-30).
                 The page still works by its link. Plot Showing Register > OP, Letter of Grant (CR 30102026 item 19 and
                 the sidebar outline, 2026-10-31); the Resettlement Calculator moved under the Land to Land calculator (item 10). --}}
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-comp-showing">
              <div class="flex items-center gap-2">
                <i data-lucide="book-open" class="h-3.5 w-3.5 text-pink-400"></i>
                <span>Plot Showing Register</span>
              </div>
              <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-comp-showing"></i>
            </div>
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-comp-showing">
              <a href="{{ route('survey-module.compensation.op') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.op', 'survey-module.compensation.op.*') ? 'active' : '' }}">
                <i data-lucide="file-signature" class="h-3.5 w-3.5 text-pink-400"></i>
                <span>OP</span>
              </a>
              <a href="{{ route('survey-module.compensation.letter-of-grant') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.letter-of-grant') ? 'active' : '' }}">
                <i data-lucide="scroll-text" class="h-3.5 w-3.5 text-pink-400"></i><span>Letter of Grant</span>
              </a>
            </div>
          </div>

          {{-- b.v Complaints --}}
          <a href="{{ route('survey-module.compensation.complaints') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.compensation.complaints*') ? 'active' : '' }}">
            <i data-lucide="message-square-warning" class="h-4 w-4 text-pink-400"></i>
            <span>Complaints</span>
          </a>
        </div>
        @endif

        {{-- c. GKN --}}
        @if($svPublic)
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

          @if($svPublic)
          {{-- c.ii - c.vii --}}
          {{-- Public Survey (was Layout Plan; Workflow_V02 3 + sidebar update 2026-10-29) --}}
          {{-- A collapsible group with its pages nested under it (user, 2026-10-30). --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gkn-public">
            <div class="flex items-center gap-2">
              <i data-lucide="landmark" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Public Survey</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-gkn-public"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gkn-public">
            {{-- Public Layout Plans shows the TP Nos table; the separate TP Nos entry is gone (user, 2026-10-30).
                 The LPKN register stays reachable from the dashboard, GKN Applications and the Coordinate Register. --}}
            <a href="{{ route('survey-module.records.tp-numbers') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.tp-numbers*', 'survey-module.records.lpkn*') ? 'active' : '' }}">
              <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i><span>Public Layout Plans</span>
            </a>
            <a href="{{ route('survey-module.records.private-layouts') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.private-layouts*') ? 'active' : '' }}">
              <i data-lucide="map-pinned" class="h-3.5 w-3.5 text-pink-400"></i><span>Private Layout</span>
            </a>
            <a href="{{ route('survey-module.records.layout-records') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.layout-records') ? 'active' : '' }}">
              <i data-lucide="table-2" class="h-3.5 w-3.5 text-pink-400"></i><span>Layout Records</span>
            </a>
          </div>
          @endif
          @if($svRecords)
          {{-- Survey Management (CR 30102026 item 15 and the sidebar outline, 2026-10-31). --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="survey-gkn-mgmt">
            <div class="flex items-center gap-2">
              <i data-lucide="briefcase" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Survey Management</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="survey-gkn-mgmt"></i>
          </div>
          <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-gkn-mgmt">
          <a href="{{ route('survey-module.records.surveyors.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.records.surveyors.*') ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors</span>
          </a>
          {{-- Blank until the source is confirmed (CR 30102026 item 12). --}}
          <a href="{{ route('survey-module.gkn.computation-files') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.computation-files') ? 'active' : '' }}">
            <i data-lucide="folder-archive" class="h-3.5 w-3.5 text-pink-400"></i><span>Computation Files (Field Jackets)</span>
          </a>
          <a href="{{ route('survey-module.gkn.its.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.gkn.its.*') ? 'active' : '' }}">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-pink-400"></i><span>Instruction to Survey</span>
          </a>
          {{-- v. Surveyors Report > 1 List of Coordinates, 2 Beacon Nos, 3 Computation Field Observations (spec outline,
               user 2026-10-30). The name still opens the reports; the chevron folds the three records under it. --}}
          @php $rptOn = request()->routeIs('survey-module.gkn.reports.*'); @endphp
          {{-- The name no longer opens the reports list (user, 2026-10-30: "comment out the url for now, let it only
               expand when clicked"). The link was:
               <a href="{{ route('survey-module.gkn.reports.index') }}" class="sidebar-item ...">Surveyors Report</a>
               On a reports page the heading is marked current so the menus around it open. --}}
          <{{ $rptOn ? 'a role=button' : 'div' }} class="sidebar-submodule-header flex items-center justify-between py-2 px-3 cursor-pointer rounded-md {{ $rptOn ? 'active font-semibold' : '' }}" data-section="survey-gkn-reports">
            <div class="flex items-center gap-2">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-pink-400"></i><span>Surveyors Report</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200 {{ $rptOn ? 'rotate-90' : '' }}" data-chevron="survey-gkn-reports"></i>
          </{{ $rptOn ? 'a' : 'div' }}>
          <div class="pl-6 mt-1 mb-1 space-y-0.5 {{ $rptOn ? '' : 'hidden' }}" data-content="survey-gkn-reports">
            <a href="{{ route('survey-module.gkn.coordinates.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-sm {{ request()->routeIs('survey-module.gkn.coordinates.*') ? 'active' : '' }}">
              <i data-lucide="map-pin" class="h-3.5 w-3.5 text-pink-400"></i><span>List of Coordinates</span>
            </a>
            <a href="{{ route('survey-module.gkn.beacons.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-sm {{ request()->routeIs('survey-module.gkn.beacons.*') ? 'active' : '' }}">
              <i data-lucide="locate-fixed" class="h-3.5 w-3.5 text-pink-400"></i><span>Beacon Nos</span>
            </a>
            <a href="{{ route('survey-module.gkn.observations.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-sm {{ request()->routeIs('survey-module.gkn.observations.*') ? 'active' : '' }}">
              <i data-lucide="calculator" class="h-3.5 w-3.5 text-pink-400"></i><span>Computation Field Observations</span>
            </a>
          </div>
          </div>
          {{-- CR 30102026 item 21; no page yet. --}}
          {!! $svSoon('Passing & Checking (Final Survey Plan)', 'clipboard-check') !!}
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
            <span>Resettlement Plots (Production)</span>
          </a>
          {{-- SDI (ported from ALAES, 2026-10-29): Map Request feeds the Production Unit. --}}
          <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">SDI</div>
          <a href="{{ route('survey-module.sdi.map-requests') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.sdi.map-requests*') || request()->routeIs('survey-module.sdi.registry*') ? 'active' : '' }}">
            <i data-lucide="map" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Map Request</span>
          </a>
          <a href="{{ route('survey-module.sdi.production') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.sdi.production*') ? 'active' : '' }}">
            <i data-lucide="factory" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Production Unit</span>
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
            {{-- Order and nesting as the spec outline (user, 2026-10-30): i Register File, ii Compensation Status
                 > Verification (Records), iii Boundary Dispute > Property / Public > LGA / Interstate, iv Old Surveys,
                 v Control Points, vi Beacon Register, vii Coordinate Register, viii Surveyor's Report (Private) > its
                 four records. Groups collapse and open by themselves on their current page. --}}
            @php
              // A group whose own page is open with no sub-item for it (e.g. all disputes) is marked current:
              // the sidebar script opens the menus around it, and the group renders open.
              $exGroup = fn (string $key, string $label, string $icon, bool $on = false) =>
                  '<' . ($on ? 'a role="button"' : 'div') . ' class="sidebar-submodule-header flex items-center justify-between py-2 px-3 cursor-pointer rounded-md' . ($on ? ' active font-semibold' : '') . '" data-section="' . e($key) . '">'
                  . '<div class="flex items-center gap-2">' . ($icon ? '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i>' : '') . '<span>' . e($label) . '</span></div>'
                  . '<i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200' . ($on ? ' rotate-90' : '') . '" data-chevron="' . e($key) . '"></i></' . ($on ? 'a' : 'div') . '>';
              $exLink = fn (string $label, string $href, bool $on, string $icon = '') =>
                  '<a href="' . e($href) . '" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-sm ' . ($on ? 'active' : '') . '">'
                  . ($icon ? '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i>' : '') . '<span>' . e($label) . '</span></a>';
              $bdOn = request()->routeIs('survey-module.workflow.boundary-disputes.*');
              $bdType = (string) request()->query('type');
              $bdSub = fn (string $label, string $type) => $exLink($label, route('survey-module.workflow.boundary-disputes.index', ['type' => $type]), $bdOn && $bdType === $type);
              // Surveyor's Report (Private) records: the report list in that mode, or that record page of a report.
              $bdAll = $bdOn && ! in_array($bdType, \App\Models\Survey\SurveyBoundaryDispute::TYPES, true);
              $prAll = request()->routeIs('survey-module.workflow.change-reports.*') && ! in_array(request('records'), ['coordinates', 'beacons', 'observations'], true);
              $prOn = fn (string $mode, string $route) => (request()->routeIs('survey-module.workflow.change-reports.index') && request('records') === $mode)
                  || request()->routeIs('survey-module.workflow.private.' . $route);
            @endphp
            @if($svRecords)
            <a href="{{ route('survey-module.workflow.register-file.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-module.workflow.register-file.*') ? 'active' : '' }}">
              <i data-lucide="file-input" class="h-3.5 w-3.5 text-pink-400"></i>
              <span>Register File</span>
            </a>
            {{-- ii. Compensation Status > Verification (Records): hidden from the sidebar for now (user, 2026-10-30). --}}
            @if (false)
            {!! $exGroup('survey-exam-comp', 'Compensation Status', 'badge-check') !!}
            <div class="pl-6 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-exam-comp">
              {!! $exLink('Verification (Records)', route('survey-module.compensation.op-verification', ['via' => 'examination']),
                  request()->routeIs('survey-module.compensation.op-verification') && request('via') === 'examination') !!}
            </div>
            @endif
            {!! $exGroup('survey-bd', 'Boundary Dispute', 'split', $bdAll) !!}
            <div class="pl-6 mt-1 mb-1 space-y-0.5 {{ $bdAll ? '' : 'hidden' }}" data-content="survey-bd">
              {!! $bdSub('Property Boundary', \App\Models\Survey\SurveyBoundaryDispute::TYPE_PROPERTY) !!}
              {!! $exGroup('survey-bd-public', 'Public Boundary', '') !!}
              <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-bd-public">
                {!! $bdSub('LGA Boundary', \App\Models\Survey\SurveyBoundaryDispute::TYPE_PUBLIC_LGA) !!}
                {!! $bdSub('Interstate Boundary', \App\Models\Survey\SurveyBoundaryDispute::TYPE_PUBLIC_INTERSTATE) !!}
              </div>
            </div>
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
            {!! $exGroup('survey-exam-private', "Surveyor's Report (Private)", 'file-check', $prAll) !!}
            <div class="pl-6 mt-1 mb-1 space-y-0.5 {{ $prAll ? '' : 'hidden' }}" data-content="survey-exam-private">
              {!! $exLink('List of Coordinates', route('survey-module.workflow.change-reports.index', ['records' => 'coordinates']), $prOn('coordinates', 'coordinates.*'), 'map-pin') !!}
              {!! $exLink('Beacon Nos', route('survey-module.workflow.change-reports.index', ['records' => 'beacons']), $prOn('beacons', 'beacons.*'), 'locate-fixed') !!}
              {!! $exLink('Computation Field Observations', route('survey-module.workflow.change-reports.index', ['records' => 'observations']), $prOn('observations', 'observations.*'), 'calculator') !!}
              {{-- 4. Sketch Plan: off the sidebar for now (user, 2026-10-30). --}}
            </div>
            @endif
          </div>
        @endif

        {{-- File Status (user, 2026-10-26): the same pages as Cadastral > Verification > File Status,
             right after Examination/Verification. ?url=survey opens each one view only and keeps
             the other modules' entries for these pages unlit. One entry since 2026-10-30: the lists
             (RofO .. Lien) are tabs on the page, survey_module.partials._file_status_tabs. --}}
        @if($svRecords)
          @php
            // Opens the File Status search page (CR 30102026 item 29); the lists are its tabs.
            $svFsOn = request()->routeIs('survey-module.file-status') || request('url') === 'survey' && request()->routeIs('land-rofos.index', 'propertycard.cofo', 'file-decommissioning.decommissioned', 'file-search-db.index', 'regrant.index', 'title-status.index', 'dciv-generation.index', 'caveat.index', 'mortgages.index', 'surrender-release.index');
          @endphp
          <a href="{{ route('survey-module.file-status') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $svFsOn ? 'active' : '' }}">
            <i data-lucide="file-search" class="h-4 w-4 text-pink-500"></i>
            <span>File Status</span>
          </a>
        @endif

        {{-- f. Other Departments ▸ Cadastral (user, 2026-10-28): the Cadastral files now with each Survey
             unit, which sends them on itself. Each entry is its unit role's (config cadastral_dept.external_desk_roles);
             Supper Admin and Cad - Central Admin see all four. --}}
        @php
          $svUnit = fn (string $role) => $hasRole($role) || $hasRole('Supper Admin') || $hasRole(\App\Services\CadastralDept\DeskAccess::centralAdminRole());
          $svComp = $svUnit('Survey - Compensation Check');
          $svDsg  = $svUnit('Survey - DSG');
          $svExam = $svUnit('Survey - Examination');
          $svSg   = $svUnit('Survey - SG');
          $svOn   = fn (string $desk) => request()->routeIs('cadastral-dept.other-departments.show') && request()->route('desk') === $desk && request()->query('url') !== 'cadastral';
          $svOdLink = fn (string $label, string $desk, string $icon) =>
              '<a href="' . e(route('cadastral-dept.other-departments.show', $desk)) . '" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 ' . ($svOn($desk) ? 'active' : '') . '">'
              . '<i data-lucide="' . e($icon) . '" class="h-3.5 w-3.5 text-pink-400"></i><span>' . e($label) . '</span></a>';
          $svOdGroup = fn (string $key, string $label, string $icon, bool $sub = false) =>
              '<div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="' . e($key) . '">'
              . '<div class="flex items-center gap-2"><i data-lucide="' . e($icon) . '" class="' . ($sub ? 'h-3.5 w-3.5 text-pink-400' : 'h-4 w-4 text-pink-500') . '"></i><span>' . e($label) . '</span></div>'
              . '<i data-lucide="chevron-right" class="' . ($sub ? 'h-3.5 w-3.5' : 'h-4 w-4') . ' transition-transform duration-200" data-chevron="' . e($key) . '"></i></div>';
        @endphp
        @if($svComp || $svDsg || $svExam || $svSg)
          {!! $svOdGroup('survey-other-departments', 'Other Departments', 'building-2') !!}
          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-other-departments">
            {!! $svOdGroup('survey-other-cadastral', 'Cadastral', 'map', true) !!}
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-other-cadastral">
              @if($svComp)
                {!! $svOdLink('Compensation Check', 'compensation_unit', 'scale') !!}
              @endif
              @if($svDsg)
                {!! $svOdGroup('survey-other-dsg', 'DSG', 'user-check', true) !!}
                <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-other-dsg">
                  {!! $svOdLink('Approval', 'dsg', 'check-circle') !!}
                </div>
              @endif
              @if($svExam)
                {!! $svOdLink('Examination Check', 'examination_unit', 'search-check') !!}
              @endif
              @if($svSg)
                {!! $svOdGroup('survey-other-sg', 'SG', 'award', true) !!}
                <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="survey-other-sg">
                  {!! $svOdLink('Approval', 'surveyor_general', 'check-circle') !!}
                </div>
              @endif
            </div>
          </div>
        @endif

        {{-- g. File Digital Library / h. Digital Archive --}}
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
