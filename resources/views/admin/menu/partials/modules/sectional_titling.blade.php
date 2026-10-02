<!-- 12. Sectional Titling -->

@if(
    $hasRole('ST - Overview') || $hasRole('ST - Primary Application') || $hasRole('ST - Unit Application') ||
    $hasRole('ST - Applications') || $hasRole('ST - Field Data Integration') ||
    $hasRole('ST - Bills & Payments') ||
    $hasRole('ST - Approvals') || $hasRole('ST - Approvals (Other Departments)') ||
    $hasRole('ST - ST Memo') || $hasRole('ST - Certificate') || $hasRole('ST - e-Registry') ||
    $hasRole('ST - GIS') || $hasRole('ST - Survey') || $hasRole('ST - Reports') || $hasRole('ST - Sectional Titling BaseMap')
  )
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="sectionalTitling">
      <div class="flex items-center gap-2">
        <i data-lucide="building-2" class="h-5 w-5 text-blue-400"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Sectional Titling</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
        data-chevron="sectionalTitling"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="sectionalTitling">
      @if($hasRole('ST - Overview'))
        <a href="{{ route('sectionaltitling.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sectionaltitling.index') ? 'active' : '' }}">
          <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
          <span>Overview</span>
        </a>
      @endif

      @if($hasRole('New ST FileNo'))
        <a href="{{ route('st-file-numbers.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('st-file-numbers.index') ? 'active' : '' }}">
          <i data-lucide="file-plus-2" class="h-4 w-4 text-blue-500"></i>
          <span>New ST FileNo</span>
        </a>
      @endif

      <!-- a. Applications -->
      @if($hasRole('ST - Primary Application') || $hasRole('ST - Unit Application') || $hasRole('ST - Applications'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="applications">
          <div class="flex items-center gap-2">
            <i data-lucide="file-plus" class="h-4 w-4 text-blue-500"></i>
            <span>Applications</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="applications"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="applications">
          @if($hasRole('ST - Primary Application') || $hasRole('ST - Applications'))
            <a href="{{ route('sectionaltitling.primary') }}?url=infopro"
               class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sectionaltitling.primary') && request()->query('url') === 'infopro' ? 'active' : '' }}">
              <i data-lucide="file-plus" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Primary Applications</span>
            </a>
          @endif
          <!-- Unit Applications -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="unitApplications">
            <div class="flex items-center gap-2">
              <i data-lucide="building" class="h-4 w-4 text-blue-500"></i>
              <span>Unit Applications</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
              data-chevron="unitApplications"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="unitApplications">
            @if($hasRole('ST - Unit Application') || $hasRole('ST - Applications'))
              <a href="{{ route('sectionaltitling.units') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sectionaltitling.units') && !request()->query('url') ? 'active' : '' }}">
                <i data-lucide="file-plus-2" class="h-3.5 w-3.5 text-blue-400"></i>
                <span>Parented Units</span>
              </a>

              <a href="{{ route('sua.index') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('sua.index') ? 'active' : '' }}">
                <i data-lucide="building" class="h-3.5 w-3.5 text-blue-400"></i>
                <span>Standalone Unit</span>
              </a>
            @endif
          </div>
      @endif
      </div>



      @if($hasRole('ST - Field Data') || $hasRole('ST - Field Data Integration'))
        <a href="{{route('programmes.field-data')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('programmes.field-data') ? 'active' : '' }}">
          <i data-lucide="clipboard-list" class="h-4 w-4 text-blue-500"></i>
          <span>Field Data Integration</span>
        </a>
      @endif

      @if($hasRole('ST - Bills & Payments'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="stPayments">
          <div class="flex items-center gap-2">
            <i data-lucide="credit-card" class="h-4 w-4 text-blue-500"></i>
            <span>Bills & Payments</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="stPayments"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="stPayments">
          <a href="{{route('programmes.bills')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('programmes.bills') && !request()->query('url') && !request()->query('source') ? 'active' : '' }}">
            <i data-lucide="receipt" class="h-3.5 w-3.5 text-blue-400"></i>
            <span>Bills</span>
          </a>
          <a href="{{route('programmes.payments')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('programmes.payments') && !request()->query('url') ? 'active' : '' }}">
            <i data-lucide="credit-card" class="h-3.5 w-3.5 text-blue-400"></i>
            <span>Payments</span>
          </a>
          <a href="{{route('programmes.payments')}}?url=report"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('programmes.payments') && request()->query('url') === 'report' ? 'active' : '' }}">
            <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-blue-400"></i>
            <span>Payments Report</span>
          </a>

        </div>
      @endif

      @if($hasRole('ST - Approvals') || $hasRole('ST - Approvals (Other Departments)'))
        <div
          class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
          data-section="stApprovals">
          <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="h-4 w-4 text-blue-500"></i>
            <span>Approvals (Other Departments)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
            data-chevron="stApprovals"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="stApprovals">
          <a href="{{ route('instrument_registration.index', ['url' => 'st_deeds']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('instrument_registration.index') && request()->query('url') === 'st_deeds' ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>ST Deeds Registration View</span>
          </a>
          <a href="{{ route('programmes.approvals.planning_recomm', ['url' => 'view_st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.approvals.planning_recomm') && request()->query('url') === 'view_st' ? 'active' : '' }}">
            <i data-lucide="ruler" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Planning Recommendation</span>
          </a>
          <a href="{{route('other_departments.survey_primary')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('other_departments.survey_primary') ? 'active' : '' }}">
            <i data-lucide="building-2" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Other Departments</span>
          </a>




        </div>
        @if($hasRole("ST - Director's Approval"))
          <a href="{{route('programmes.approvals.director')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.approvals.director') ? 'active' : '' }}">
            <i data-lucide="stamp" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Director's Approval</span>
          </a>
        @endif



        @if($hasRole('ST - ST Memo'))
          <div
            class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
            data-section="stMemo">
            <div class="flex items-center gap-2">
              <i data-lucide="clipboard-list" class="h-4 w-4 text-blue-500"></i>
              <span>ST Memo</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
              data-chevron="stMemo"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="stMemo">
            <a href="{{route('programmes.memo')}}?type=primary"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.memo') && request()->query('type') === 'primary' ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Primary</span>
            </a>

            <a href="{{ route('programmes.unit_scheme_memo') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.unit_scheme_memo') ? 'active' : '' }}">
              <i data-lucide="building" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Unit (Scheme)</span>
            </a>

            <a href="{{ route('programmes.unit_nonscheme_memo') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.unit_nonscheme_memo') ? 'active' : '' }}">
              <i data-lucide="building-2" class="h-3.5 w-3.5 text-blue-400"></i>
              <span>Unit (Non-Scheme)</span>
            </a>
          </div>
        @endif
        @if($hasRole('ST - Final Conveyance'))
          <a href="/sectionaltitling/conveyance"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->is('sectionaltitling/conveyance') ? 'active' : '' }}">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Final Conveyance</span>
          </a>
        @endif
      @endif

      @if($hasRole('ST - Certificate'))
        <div
          class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
          data-section="certificate">
          <div class="flex items-center gap-2">
            <i data-lucide="award" class="h-4 w-4 text-blue-500"></i>
            <span>Certificate</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
            data-chevron="certificate"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="certificate">
          @if($hasRole('ST - Certificate'))
            <a href="{{route('programmes.rofo')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.rofo') ? 'active' : '' }}">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-blue-500"></i>
              <span>RofO</span>
            </a>
          @endif

          {{-- C of O Workflow.
               Two stages, and the order of the names is the order of the work:

                 Front Page   the existing /programmes/certificates screen. It IS the front
                              page -- there is one front page, not a separate module for it.
                 CofO         the finished two-sided certificate: that front page on the
                              front, the TDP from KANGIS/GIS on the back.

               CofO used to sit flat beside RofO and own programmes.certificates. That route
               now belongs to Front Page, which is why CofO moved a level down rather than
               simply being renamed. --}}
          @if($hasRole('ST - Certificate'))
            <div
              class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
              data-section="cofo-workflow">
              <div class="flex items-center gap-2">
                <i data-lucide="git-branch" class="h-3.5 w-3.5 text-blue-500"></i>
                <span>CofO Workflow</span>
              </div>
              <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
                data-chevron="cofo-workflow"></i>
            </div>

            <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cofo-workflow">
              <a href="{{route('programmes.certificates')}}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.certificates') ? 'active' : '' }}">
                <i data-lucide="file-text" class="h-3.5 w-3.5 text-blue-500"></i>
                <span>Front Page</span>
              </a>

              {{-- CofO = the complete two-sided certificate: the front page above, with the
                   TDP from KANGIS/GIS on the back. Its screen lists every generated front
                   page beside its TDP, and prints the pair. --}}
              <a href="{{route('programmes.cofo_complete')}}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.cofo_complete*') ? 'active' : '' }}">
                <i data-lucide="file-cog" class="h-3.5 w-3.5 text-blue-500"></i>
                <span>CofO</span>
              </a>
            </div>
          @endif
        </div>
      @endif

      @if($hasRole('ST - e-Registry'))
        <div
          class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
          data-section="digitalArchive">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-blue-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
            data-chevron="digitalArchive"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive">
          {{-- i. File Tracker Dashboard --}}
          <a href="{{ route('file-tracker.dashboard', ['url' => 'st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'st' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>File Tracker Dashboard</span>
          </a>

          {{-- ii. File Tracker (Archive) --}}
          <a href="{{ route('track-file-archive.index', ['url' => 'st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('track-file-archive.index') && request('url') === 'st' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>File Tracker (Archive)</span>
          </a>

          {{-- iii. Quick Search --}}
          @php $stQuickSearchActive = request()->routeIs('create-file-tracker.quick-search') && request('url') === 'st'; @endphp
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ $stQuickSearchActive ? 'active' : '' }}"
            @if($stQuickSearchActive) style="color: #1d4ed8;" @endif>
            <i data-lucide="search" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Quick Search</span>
          </a>

          {{-- iv. Log a File --}}
          <a href="{{ route('create-file-tracker.index', ['url' => 'st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('create-file-tracker.index') && request('url') === 'st' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Log a File</span>
          </a>

          {{-- iv. File Digital Library – Doc-WARE --}}
          <a href="{{ route('filearchive.index', ['url' => 'st']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('filearchive.index') && request('url') === 'st' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>

          {{-- v. e-Registry --}}
          <div
            class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
            data-section="st-eRegistry">
            <div class="flex items-center gap-2">
              <i data-lucide="database" class="h-3.5 w-3.5 text-blue-500"></i>
              <span>e-Registry</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
              data-chevron="st-eRegistry"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="st-eRegistry">
            {{-- 1. Files --}}
            <a href="{{route('programmes.eRegistry')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.eRegistry') ? 'active' : '' }}">
              <i data-lucide="folder" class="h-3.5 w-3.5 text-blue-500"></i>
              <span>Files</span>
            </a>
            {{-- 2. Print File Label --}}
            <a href="{{ route('st-printlabel.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('st-printlabel.index') ? 'active' : '' }}">
              <i data-lucide="printer" class="h-3.5 w-3.5 text-blue-500"></i>
              <span>Print File Label</span>
            </a>
          </div>

          {{-- vi. EDMS Update --}}
          <a href="#"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>DMS Update</span>
          </a>
        </div>
      @endif

      @if($hasRole('ST - Survey'))
        <div
          class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
          data-section="stSurvey">
          <div class="flex items-center gap-2">
            <i data-lucide="land-plot" class="h-4 w-4 text-blue-500"></i>
            <span>Survey</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
            data-chevron="stSurvey"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="stSurvey">
          <a href="{{route('attribution.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('attribution.index') ? 'active' : '' }}">
            <i data-lucide="land-plot" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Attribution</span>
          </a>
        </div>
      @endif
      @if($hasRole('ST - GIS'))
        <div
          class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md text-black"
          data-section="stGis">
          <div class="flex items-center gap-2">
            <i data-lucide="map" class="h-4 w-4 text-blue-500"></i>
            <span>GIS</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 text-black"
            data-chevron="stGis"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="stGis">
          <a href="{{route('gis.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('gis.index') ? 'active' : '' }}">
            <i data-lucide="database" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Attribution</span>
          </a>
          {{-- The Title Deed Plan store: the BACK PAGE of the ST Certificate of Occupancy.
               It also appears under the top-level GIS module for KANGIS staff; it is listed
               here because the ST officer producing the certificate is the one who needs to
               see whether the back page has arrived. --}}
          <a href="{{route('tdp.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('tdp.*') ? 'active' : '' }}">
            <i data-lucide="map-pinned" class="h-3.5 w-3.5 text-blue-500"></i>
            <span>Title Deed Plan Management</span>
          </a>
        </div>
      @endif

      @if($hasRole('ST - Reports') || $hasRole('ST - Sectional Titling BaseMap'))
        <a href="{{ route('map.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('map.index') ? 'active' : '' }}">
          <i data-lucide="map-pin" class="h-3.5 w-3.5 text-blue-500"></i>
          <span>Sectional Titling BaseMap</span>
        </a>
      @endif
      @if($hasRole('ST - Reports'))
        <a href="{{route('programmes.report')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 text-black {{ request()->routeIs('programmes.report') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-blue-500"></i>
          <span>Reports</span>
        </a>
      @endif
    </div>
  </div>
@endif