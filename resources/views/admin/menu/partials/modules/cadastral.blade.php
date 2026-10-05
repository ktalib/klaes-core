<!-- 16. Cadastral -->
{{-- Layout follows the Cadastral sidebar brief (2026-10-03):
     a. Registry  b. Cadastral Report  c. Cadastral Information  d. Plans & Descriptions
     e. Other Departments  f. GIS  g. File Digital Library  h. Digital Archive  i. Reports.
     Each entry keeps the role check it had before the reorganisation; a group header
     shows when at least one of its entries does.

     The NEW badge marks the recently added Cadastral Module entries. ml-auto pushes it
     to the right of the "flex items-center gap-2" row; on a sub-module header (which
     already ends in a chevron) it is swapped for ml-1. --}}
@php
  // Block form, not the inline form: Blade pairs an inline opener with the next
  // block closer, which broke the Cadastral Module block further down.
  // Switched off on request (2026-10-02). To show the NEW badges again, set
  // $cadShowNew to true; every placement below is left in place.
  $cadShowNew = false;
  $cadNew = $cadShowNew
      ? '<span class="ml-auto inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-rose-700 ring-1 ring-inset ring-rose-200">New</span>'
      : '';

  // Who sees what. Kept as the gates the entries had before the reorganisation.
  $cadModule   = $hasRole('Cad - Records') || $hasRole('Supper Admin');   // the rebuilt Cadastral Module screens
  $cadMatch    = $hasRole('Match Existing FileNo (MLSFileNo) ');
  $cadLegacyIc = $hasRole('Deeds – Property Index Cards Assistant (Legacy Records)');
  $cadExtract  = $hasRole('Cadastral Reports') || $hasRole('Supper Admin');
  $cadArchive  = $hasRole('Cad - Digital Archive') || $hasRole('Supper Admin');
@endphp
@if(
    $hasRole('Cad - Records') || $hasRole('Cad - GIS') || $hasRole('Cad - Approvals') ||
    $hasRole('Cad - E-Registry') || $hasRole('Cadastral Reports')|| $hasRole('Match Existing FileNo (MLSFileNo) ') ||
    $hasRole('Deeds – Property Index Cards Assistant (Legacy Records)') || $hasRole('Cad - Digital Archive') || $hasRole('Supper Admin')
  )
  @php
    $cadBadges = ['intake' => 0, 'reports' => 0];
    $cadBadge  = fn ($n, $title) => '';
    $cadReportType = null;
    $cadPnd = $cadPndDesc = $cadPndFees = false;

    if ($cadModule) {
        // Pending counts, cached for a minute in DashboardMetrics because this
        // partial renders on every page. A failing query hides the badges; it
        // must never take the sidebar down with it.
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

        // Area & Pillars, Descriptions (Phase 6) and the Fee Calculator (Phase 7)
        // are their own pages; an old ?view= link redirects to them.
        // Anything else under plan-description lights Area & Pillars.
        $cadPnd     = request()->routeIs('cadastral-module.plan-description.*');
        $cadPndView = $cadPnd ? request('view') : null;
        $cadPndDesc = $cadPnd && ($cadPndView === 'descriptions' || request()->routeIs(
            'cadastral-module.plan-description.descriptions',
            'cadastral-module.plan-description.description-files',
            'cadastral-module.plan-description.description.*'
        ));
        $cadPndFees = $cadPnd && ($cadPndView === 'fees' || request()->routeIs(
            'cadastral-module.plan-description.fees',
            'cadastral-module.plan-description.fees.*',
            'cadastral-module.plan-description.fee-files',
            'cadastral-module.plan-description.bill.*'
        ));
    }
  @endphp
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

      {{-- Module home; not in the brief's list, kept so the rebuilt module keeps its landing page. --}}
      @if($cadModule)
        <a href="{{ route('cadastral-module.dashboard') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.dashboard') ? 'active' : '' }}">
          <i data-lucide="gauge" class="h-4 w-4 text-rose-500"></i>
          <span>Dashboard</span>{!! $cadNew !!}
        </a>
      @endif

      {{-- a. Registry --}}
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
           data-section="cadastralModule-registry">
        <div class="flex items-center gap-2">
          <i data-lucide="inbox" class="h-4 w-4 text-rose-500"></i>
          <span>Registry</span>
          {!! str_replace('ml-auto', 'ml-1', $cadNew) !!}
          {!! str_replace('ml-auto', 'ml-1', $cadBadge($cadBadges['intake'] ?? 0, 'Files awaiting registration')) !!}
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralModule-registry"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-registry">
        @if($cadModule)
          {{-- i. File movements is a step of intake, so it lights Intake Queue. --}}
          <a href="{{ route('cadastral-module.registry.receipts') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.registry.receipts*') || request()->routeIs('cadastral-module.registry.movements') ? 'active' : '' }}">
            <i data-lucide="inbox" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Intake Queue</span>{!! $cadBadge($cadBadges['intake'] ?? 0, 'Files awaiting registration') !!}
          </a>
        @endif

        {{-- ii. Commission Correspondence File (Match MLSFileNo) --}}
        @if($cadMatch)
          <a href="{{ route('mls-file-no-matching.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mls-file-no-matching.index') ? 'active' : '' }}">
            <i data-lucide="check" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Commission Correspondence File (Match MLSFileNo)</span>
          </a>
        @endif

        @if($cadModule)
          {{-- iii. The duplicate check feeds the correspondence hold (Phase 3). --}}
          <a href="{{ route('cadastral-module.registry.correspondence') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.registry.correspondence') || request()->routeIs('cadastral-module.registry.duplicates*') ? 'active' : '' }}">
            <i data-lucide="link-2" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Correspondence Files</span>
          </a>
        @endif

        {{-- iv. Cadastral Print File Label --}}
        <a href="{{ route('cadastral_printlabel.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral_printlabel.index') ? 'active' : '' }}">
          <i data-lucide="printer" class="h-3.5 w-3.5 text-rose-400"></i>
          <span>Cadastral Print File Label</span>
        </a>
      </div>

      {{-- b. Cadastral Report --}}
      @if($cadModule)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
             data-section="cadastralModule-reports">
          <div class="flex items-center gap-2">
            <i data-lucide="file-text" class="h-4 w-4 text-rose-500"></i>
            <span>Cadastral Report</span>
            {!! str_replace('ml-auto', 'ml-1', $cadNew) !!}
            {!! str_replace('ml-auto', 'ml-1', $cadBadge($cadBadges['reports'] ?? 0, 'Reports not yet dispatched')) !!}
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralModule-reports"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-reports">
          {{-- i. All Reports --}}
          <a href="{{ route('cadastral-module.reports.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.reports.*') && ! $cadReportType ? 'active' : '' }}">
            <i data-lucide="files" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>All Reports</span>{!! $cadBadge($cadBadges['reports'] ?? 0, 'Reports not yet dispatched') !!}
          </a>

          {{-- ii. Verification, with its two streams nested under it: 1. Customary  2. Statutory.
               Verification is still its own report stream and keeps its own page. --}}
          <a href="{{ route('cadastral-module.reports.verification') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadReportType === 'verification' ? 'active' : '' }}">
            <i data-lucide="search-check" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Verification</span>
          </a>
          {{-- Inline border colour: the rose palette is not in the Tailwind v2 CDN build. --}}
          <div class="ml-4 pl-2 border-l space-y-0.5" style="border-color:#ffe4e6;">
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
        </div>
      @endif

      {{-- c. Cadastral Information --}}
      @if($cadModule || $cadLegacyIc)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
             data-section="cadastralModule-info">
          <div class="flex items-center gap-2">
            <i data-lucide="pen-tool" class="h-4 w-4 text-rose-500"></i>
            <span>Cadastral Information</span>
            {!! str_replace('ml-auto', 'ml-1', $cadNew) !!}
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralModule-info"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralModule-info">
          {{-- i. Index Cards for File Movement --}}
          @if($cadModule)
            <a href="{{ route('cadastral-module.index-cards.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.index-cards.*') ? 'active' : '' }}">
              <i data-lucide="id-card" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Index Cards for File Movement</span>
            </a>
          @endif

          {{-- ii. Property Index Cards Assistant (Legacy Records): 1. Table  2. Scans --}}
          @if($cadLegacyIc)
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="propertyIndexCards-cadastral">
              <div class="flex items-center gap-2">
                <i data-lucide="book-open" class="h-3.5 w-3.5 text-rose-400"></i>
                <span>Property Index Cards Assistant (Legacy Records)</span>
              </div>
              <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="propertyIndexCards-cadastral"></i>
            </div>
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="propertyIndexCards-cadastral">
              <a href="{{ route('property_index_card.index') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('property_index_card.index') ? 'active' : '' }}">
                <i data-lucide="table" class="h-3.5 w-3.5 text-rose-400"></i>
                <span>Table</span>
              </a>
              {{-- The scanned index-card folders. --}}
              <a href="{{ route('cadastral.index-cards.index') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral.index-cards.index') ? 'active' : '' }}">
                <i data-lucide="scan-line" class="h-3.5 w-3.5 text-rose-400"></i>
                <span>Scans</span>
              </a>
            </div>
          @endif

          @if($cadModule)
            {{-- iii. Charting --}}
            <a href="{{ route('cadastral-module.charting.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.charting.*') ? 'active' : '' }}">
              <i data-lucide="map-pinned" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Charting</span>
            </a>
            {{-- iv. Survey Jobs. The surveyor directory is reached from here. --}}
            <a href="{{ route('cadastral-module.survey-jobs.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.survey-jobs.*') || request()->routeIs('cadastral-module.surveyors.*') ? 'active' : '' }}">
              <i data-lucide="hard-hat" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>Survey Jobs (I to S)</span>
            </a>
            {{-- v. File Status --}}
            <a href="{{ route('cadastral-module.file-status.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.file-status.*') ? 'active' : '' }}">
              <i data-lucide="toggle-left" class="h-3.5 w-3.5 text-rose-400"></i>
              <span>File Status</span>
            </a>
          @endif
        </div>
      @endif

      {{-- d. Plans & Descriptions --}}
      @if($cadModule)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
             data-section="cadastralModule-pnd">
          <div class="flex items-center gap-2">
            <i data-lucide="ruler" class="h-4 w-4 text-rose-500"></i>
            <span>Plans &amp; Descriptions</span>
            {!! str_replace('ml-auto', 'ml-1', $cadNew) !!}
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralModule-pnd"></i>
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
          <a href="{{ route('cadastral-module.plan-description.fees') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadPndFees ? 'active' : '' }}">
            <i data-lucide="calculator" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Fee Calculator</span>
          </a>
        </div>
      @endif

      {{-- e. Other Departments --}}
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="cadastral-otherDepts">
        <div class="flex items-center gap-2">
          <i data-lucide="building-2" class="h-4 w-4 text-rose-500"></i>
          <span>Other Departments</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastral-otherDepts"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastral-otherDepts">
        <a href="{{ route('title-status.index') }}?url=cadastral"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'cadastral' ? 'active' : '' }}">
          <i data-lucide="badge-check" class="h-3.5 w-3.5 text-rose-400"></i>
          <span>Title Status Update</span>
        </a>
        <a href="{{ route('survey-report.index') }}?url=cadastral"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'cadastral' ? 'active' : '' }}">
          <i data-lucide="file-text" class="h-3.5 w-3.5 text-rose-400"></i>
          <span>Cadastral to Land (Land 12)</span>
        </a>
      </div>

      {{-- f. GIS --}}
      @if($cadExtract)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="cadastral-gis">
          <div class="flex items-center gap-2">
            <i data-lucide="map-pinned" class="h-4 w-4 text-rose-500"></i>
            <span>GIS</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastral-gis"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastral-gis">
          {{-- ?url= only decides which sidebar lights the entry. --}}
          <a href="{{ route('survey_plan_extraction.index') }}?url=cadastral"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Survey Plan Extraction</span>
          </a>
        </div>
      @endif

      {{-- g. File Digital Library – Doc-WARE --}}
      @if($cadArchive)
        <a href="{{ route('filearchive.index', ['url' => 'cadastral']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
          <i data-lucide="library" class="h-4 w-4 text-rose-500"></i>
          <span>File Digital Library – Doc-WARE</span>
        </a>
      @endif

      {{-- h. Digital Archive --}}
      @if($cadArchive)
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-cadastral">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-rose-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-cadastral"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-cadastral">
          <a href="{{ route('file-tracker.dashboard', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker Dashboard</span>
          </a>
          <a href="{{ route('track-file-archive.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker (Archive)</span>
          </a>
          {{-- Scoped to Cadastral registry files. --}}
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Quick Search</span>
          </a>
          <a href="{{ route('create-file-tracker.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Log a File</span>
          </a>
          <a href="#"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>DMS Update</span>
          </a>
        </div>
      @endif

      {{-- i. Reports --}}
      @if($cadModule)
        <a href="{{ route('cadastral-module.analytics') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral-module.analytics') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-rose-500"></i>
          <span>Reports</span>{!! $cadNew !!}
        </a>
      @endif
    </div>
  </div>
@endif
