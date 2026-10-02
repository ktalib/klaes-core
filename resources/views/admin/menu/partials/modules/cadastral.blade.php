<!-- 10. Cadastral -->
{{-- The NEW badge marks the four-unit Cadastral Module added alongside the
     existing screens, so staff can tell at a glance which entries are new.
     ml-auto pushes it to the right of the existing "flex items-center gap-2"
     row without touching any existing class; on a sub-module header (which
     already ends in a chevron) it is swapped for ml-1. --}}
@php
  // Block form, not the inline form: Blade pairs an inline opener with the next
  // block closer, which broke the Cadastral Module block further down.
  $cadNew = '<span class="ml-auto inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-rose-700 ring-1 ring-inset ring-rose-200">New</span>';
@endphp
@if(
    $hasRole('Cad - Records') || $hasRole('Cad - GIS') || $hasRole('Cad - Approvals') ||
    $hasRole('Cad - E-Registry') || $hasRole('Cadastral Reports')|| $hasRole('Match Existing FileNo (MLSFileNo) ') ||
    $hasRole('Deeds – Property Index Cards Assistant (Legacy Records)') || $hasRole('Cad - Digital Archive') || $hasRole('Supper Admin')
  )
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="cadastral">
      <div class="flex items-center gap-2">
        <i data-lucide="map" class="h-5 w-5 text-rose-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Cadastral</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastral"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="cadastral">

      <!-- a. Commission Correspondence File (Match MLSFileNo) -->
      @if($hasRole('Match Existing FileNo (MLSFileNo) '))
        <a href="{{ route('mls-file-no-matching.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mls-file-no-matching.index') ? 'active' : '' }}">
          <i data-lucide="check" class="h-4 w-4 text-rose-500"></i>
          <span>Commission Correspondence File (Match MLSFileNo)</span>
        </a>
      @endif

      <!-- b. Cadastral Print File Label -->
      <a href="{{ route('cadastral_printlabel.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral_printlabel.index') ? 'active' : '' }}">
        <i data-lucide="printer" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral Print File Label</span>
      </a>

      <!-- c. Title Status -->
      <a href="{{ route('title-status.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="badge-check" class="h-4 w-4 text-rose-500"></i>
        <span>Title Status Update</span>
      </a>

      <!-- c. Cadastral to Lands (Lands 12) -->
      <a href="{{ route('survey-report.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="file-text" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral to Land (Land 12)</span>
      </a>

      <!-- c. Records -->
      @if($hasRole('Cad - Records'))
        <a href="{{route('survey_cadastral.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_cadastral.index') ? 'active' : '' }}">
          <i data-lucide="clipboard" class="h-4 w-4 text-rose-500"></i>
          <span>Records</span>
        </a>
      @endif

      {{-- NEW: Cadastral Module, grouped as the rebuild brief lays it out:
           Main, Registry, Reports, Information, Plans and Descriptions, Analytics.
           Sits with the other record-keeping entries and above the legacy
           archive/GIS/report links. The legacy screens above are untouched.
           The per-unit dashboards are still reachable by URL but are folded
           into the one Dashboard and Analytics here. --}}
      @if($hasRole('Cad - Records') || $hasRole('Supper Admin'))
        @php
          // Pending counts, cached for a minute in DashboardMetrics because this
          // partial renders on every page. A failing query hides the badges; it
          // must never take the sidebar down with it.
          $cadBadges = ['intake' => 0, 'reports' => 0];
          try {
              $cadBadges = app(\App\Services\Cadastral\DashboardMetrics::class)->sidebarBadges();
          } catch (\Throwable $cadBadgeError) {
              $cadBadges = ['intake' => 0, 'reports' => 0];
          }

          // Inline colours: the rose palette is not in the Tailwind v2 CDN build.
          $cadBadge = function ($n, $title) {
              $n = (int) $n;
              if ($n <= 0) {
                  return '';
              }
              return '<span class="sidebar-badge ml-auto" title="' . e($title) . '" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;padding:1px 6px;border-radius:9999px;background:#ffe4e6;color:#be123c;font-size:10px;font-weight:700;line-height:14px;">'
                  . ($n > 999 ? '999+' : $n) . '</span>';
          };

          // Which report-type entry is lit: its own page, a report of that type
          // (detail, edit, prints), Start Report for it, or an old ?report_type= link.
          $cadReportType = null;
          if (request()->routeIs('cadastral-module.reports.verification', 'cadastral-module.reports.customary', 'cadastral-module.reports.statutory')) {
              $cadReportType = \Illuminate\Support\Str::afterLast((string) request()->route()->getName(), '.');
          } elseif (request()->routeIs('cadastral-module.reports.index')) {
              $cadReportType = request('report_type') ?: null;
          } elseif (request()->routeIs('cadastral-module.reports.create')) {
              $cadReportType = request('type') ?: null;
          } elseif (request()->routeIs('cadastral-module.reports.*')) {
              $cadRouteReport = request()->route('report');
              $cadReportType = is_object($cadRouteReport) ? ($cadRouteReport->report_type ?? null) : null;
          }

          // Area & Pillars and Descriptions are their own pages (Phase 6); the
          // Fee Calculator still opens the register with ?view=fees until Phase 7.
          // Anything else under plan-description lights Area & Pillars.
          $cadPnd     = request()->routeIs('cadastral-module.plan-description.*');
          $cadPndView = $cadPnd ? request('view') : null;
          $cadPndDesc = $cadPnd && ($cadPndView === 'descriptions' || request()->routeIs(
              'cadastral-module.plan-description.descriptions',
              'cadastral-module.plan-description.description-files',
              'cadastral-module.plan-description.description.*'
          ));
          $cadPndFees = $cadPnd && ($cadPndView === 'fees' || request()->routeIs('cadastral-module.plan-description.bill.*'));
        @endphp

        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
             data-section="cadastralModule">
          <div class="flex items-center gap-2">
            <i data-lucide="layers" class="h-4 w-4 text-rose-500"></i>
            <span>Cadastral Module</span>
            {!! str_replace('ml-auto', 'ml-1', $cadNew) !!}
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralModule"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule">

          <!-- Main -->
          <a href="{{ route('cadastral-module.dashboard') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.dashboard') ? 'active' : '' }}">
            <i data-lucide="gauge" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Dashboard</span>
          </a>

          <!-- Registry -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
               data-section="cadastralModule-registry">
            <div class="flex items-center gap-2">
              <i data-lucide="inbox" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Registry</span>
              {!! str_replace('ml-auto', 'ml-1', $cadBadge($cadBadges['intake'] ?? 0, 'Files awaiting registration')) !!}
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadastralModule-registry"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-registry">
            {{-- File movements is a step of intake, so it lights Intake Queue. --}}
            <a href="{{ route('cadastral-module.registry.receipts') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.registry.receipts*') || request()->routeIs('cadastral-module.registry.movements') ? 'active' : '' }}">
              <i data-lucide="inbox" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Intake Queue</span>{!! $cadBadge($cadBadges['intake'] ?? 0, 'Files awaiting registration') !!}
            </a>
            {{-- The duplicate check feeds the correspondence hold (Phase 3). --}}
            <a href="{{ route('cadastral-module.registry.correspondence') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.registry.correspondence') || request()->routeIs('cadastral-module.registry.duplicates*') ? 'active' : '' }}">
              <i data-lucide="link-2" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Correspondence Files</span>
            </a>
          </div>

          <!-- Reports -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
               data-section="cadastralModule-reports">
            <div class="flex items-center gap-2">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Reports</span>
              {!! str_replace('ml-auto', 'ml-1', $cadBadge($cadBadges['reports'] ?? 0, 'Reports not yet dispatched')) !!}
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadastralModule-reports"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-reports">
            <a href="{{ route('cadastral-module.reports.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.reports.*') && ! $cadReportType ? 'active' : '' }}">
              <i data-lucide="files" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>All Reports</span>{!! $cadBadge($cadBadges['reports'] ?? 0, 'Reports not yet dispatched') !!}
            </a>
            <a href="{{ route('cadastral-module.reports.verification') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadReportType === 'verification' ? 'active' : '' }}">
              <i data-lucide="search-check" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Verification</span>
            </a>
            <a href="{{ route('cadastral-module.reports.customary') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadReportType === 'customary' ? 'active' : '' }}">
              <i data-lucide="scroll" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Customary</span>
            </a>
            <a href="{{ route('cadastral-module.reports.statutory') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadReportType === 'statutory' ? 'active' : '' }}">
              <i data-lucide="landmark" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Statutory</span>
            </a>
          </div>

          <!-- Information -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
               data-section="cadastralModule-info">
            <div class="flex items-center gap-2">
              <i data-lucide="pen-tool" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Information</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadastralModule-info"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-info">
            <a href="{{ route('cadastral-module.index-cards.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.index-cards.*') ? 'active' : '' }}">
              <i data-lucide="id-card" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Index Cards</span>
            </a>
            <a href="{{ route('cadastral-module.charting.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.charting.*') ? 'active' : '' }}">
              <i data-lucide="map-pinned" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Charting</span>
            </a>
            {{-- The surveyor directory is reached from Survey Jobs. --}}
            <a href="{{ route('cadastral-module.survey-jobs.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.survey-jobs.*') || request()->routeIs('cadastral-module.surveyors.*') ? 'active' : '' }}">
              <i data-lucide="hard-hat" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Survey Jobs (I to S)</span>
            </a>
            <a href="{{ route('cadastral-module.file-status.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.file-status.*') ? 'active' : '' }}">
              <i data-lucide="toggle-left" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>File Status</span>
            </a>
          </div>

          <!-- Plans & Descriptions -->
          {{-- TODO(cadastral Phase 7): point Fee Calculator at its own page once it
               exists. Area & Pillars and Descriptions have theirs (Phase 6). --}}
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
               data-section="cadastralModule-pnd">
            <div class="flex items-center gap-2">
              <i data-lucide="ruler" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Plans &amp; Descriptions</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadastralModule-pnd"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-pnd">
            <a href="{{ route('cadastral-module.plan-description.area') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadPnd && ! $cadPndDesc && ! $cadPndFees ? 'active' : '' }}">
              <i data-lucide="ruler" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Area &amp; Pillars</span>
            </a>
            <a href="{{ route('cadastral-module.plan-description.descriptions') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadPndDesc ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Descriptions</span>
            </a>
            <a href="{{ route('cadastral-module.plan-description.index', ['view' => 'fees']) }}#fee-calculator"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadPndFees ? 'active' : '' }}">
              <i data-lucide="calculator" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Fee Calculator</span>
            </a>
          </div>

          <!-- Analytics -->
          <a href="{{ route('cadastral-module.analytics') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.analytics') ? 'active' : '' }}">
            <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Reports / Analytics</span>
          </a>
        </div>
      @endif

      <!-- d. Property Index Cards Assistant (Legacy Records) -->
      @if($hasRole('Deeds – Property Index Cards Assistant (Legacy Records)'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="propertyIndexCards-cadastral">
          <div class="flex items-center gap-2">
            <i data-lucide="id-card" class="h-4 w-4 text-rose-500"></i>
            <span>Property Index Cards Assistant (Legacy Records)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="propertyIndexCards-cadastral"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="propertyIndexCards-cadastral">
          <!-- i. Table -->
          <a href="{{ route('property_index_card.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('property_index_card.index') ? 'active' : '' }}">
            <i data-lucide="table" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Table</span>
          </a>

          <!-- ii. Index Cards -->
          <a href="{{ route('cadastral.index-cards.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral.index-cards.index') ? 'active' : '' }}">
            <i data-lucide="id-card" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Index Cards</span>
          </a>
        </div>
      @endif

      <!-- e. Survey Plan Extraction -->
      @if($hasRole('Cadastral Reports'))
        <a href="{{ route('survey_plan_extraction.index') }}?url=survey"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'survey' ? 'active' : '' }}">
          <i data-lucide="sparkles" class="h-4 w-4 text-rose-500"></i>
          <span>Survey Plan Extraction</span>
        </a>
      @endif

      <!-- f. GIS -->
      @if($hasRole('Cad - GIS'))
        <a href="/cadastral/gis"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/gis*') ? 'active' : '' }}">
          <i data-lucide="map" class="h-4 w-4 text-rose-500"></i>
          <span>GIS</span>
        </a>
      @endif

      <!-- g. Approvals -->
      @if($hasRole('Cad - Approvals'))
        <a href="/cadastral/approvals"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/approvals*') ? 'active' : '' }}">
          <i data-lucide="check-circle" class="h-4 w-4 text-rose-500"></i>
          <span>Approvals</span>
        </a>
      @endif

      <!-- h. File Digital Library – Doc-WARE -->
      @if($hasRole('Cad - Digital Archive') || $hasRole('Supper Admin'))
        <a href="{{ route('filearchive.index', ['url' => 'cadastral']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
          <i data-lucide="library" class="h-4 w-4 text-rose-500"></i>
          <span>File Digital Library – Doc-WARE</span>
        </a>
      @endif

      <!-- i. Digital Archive -->
      @if($hasRole('Cad - Digital Archive') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-cadastral">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-rose-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-cadastral"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-cadastral">
          <!-- i. File Tracker Dashboard -->
          <a href="{{ route('file-tracker.dashboard', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker Dashboard</span>
          </a>

          <!-- ii. File Tracker (Archive) -->
          <a href="{{ route('track-file-archive.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker (Archive)</span>
          </a>

          <!-- iii. Quick Search (scoped to Cadastral registry files) -->
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Quick Search</span>
          </a>

          <!-- iv. Log a File -->
          <a href="{{ route('create-file-tracker.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Log a File</span>
          </a>

          <!-- v. EDMS Update -->
          <a href="#"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>DMS Update</span>
          </a>
        </div>
      @endif

      <!-- j. Cadastral Reports -->
      @if($hasRole('Cadastral Reports'))
        <a href="/cadastral/reports"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/reports*') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-rose-500"></i>
          <span>Cadastral Reports</span>
        </a>
      @endif
    </div>
  </div>
@endif