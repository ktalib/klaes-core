<!-- 16. Cadastral -->
{{-- NEW LAYOUT, ENABLED FOR EVERYONE 2026-10-12 (built 2026-10-11): the user's proposed sidebar in
     docs/plans/CADASTRAL_SIDEBAR_CURRENT.md. cadastral.blade.php is the previous layout, kept as it
     was and switched off in admin/menu.blade.php.

     The old module's Registry, Cadastral Report, Cadastral Information and Plans &
     Descriptions groups are not in this layout; their pages still open by URL.

     Earlier layout notes, from the Cadastral sidebar brief (2026-10-03):
     a. Registry  b. Cadastral Report  c. Cadastral Information  d. Plans & Descriptions
     e. Other Departments  f. GIS  h. Digital Archive  i. Reports. (g. File Digital
     Library is now the first entry under Digital Archive, 2026-10-11.)
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
    $cadBadge  = fn ($n, $title) => '';
    $cadQueues = $cadQueueCounts = [];
    try {
        // The desk groups; legacy index-card users need Cadastral Report's too.
        $cadQueues = (new \App\Services\CadastralDept\WorkflowDefinition())->queues();
    } catch (\Throwable $cadQueueError) {
        $cadQueues = [];
    }

    if ($cadModule) {
        // Inline colours: the rose palette is not in the Tailwind v2 CDN build.
        $cadBadge = function ($n, $title) {
            $n = (int) $n;
            if ($n <= 0) {
                return '';
            }
            return '<span class="sidebar-badge ml-auto" title="' . e($title) . '" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;padding:1px 6px;border-radius:9999px;background:#ffe4e6;color:#be123c;font-size:10px;font-weight:700;line-height:14px;">'
                . ($n > 999 ? '999+' : $n) . '</span>';
        };

        // Cadastral Department lists (config cadastral_dept.queues), counted and cached
        // for a minute by DeskQueues; a failure hides the counts, never the menu.
        try {
            $cadQueueCounts = app(\App\Services\CadastralDept\DeskQueues::class)->counts();
        } catch (\Throwable $cadDeptError) {
            $cadQueueCounts = [];
        }
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

      {{-- The desks (user's proposed sidebar, docs/plans/CADASTRAL_SIDEBAR_CURRENT.md, 2026-10-11), each on its
           own in the Cadastral menu; the "Cadastral Department" group around them was removed (user, 2026-10-12).
           One group per desk; each entry opens the desk's inbox filtered to the files where that action
           is due (config cadastral_dept.queues). The actions themselves stay in each row's menu. --}}
      {{-- Also shown to legacy index-card users, who see only their entry under Cadastral Report. --}}
      @if(($cadModule || $cadLegacyIc) && $cadQueues)
          @foreach ($cadQueues as $cadDeskKey => $cadQ)
            @php
              $cadDeskOn = request()->routeIs('cadastral-dept.desks.show') && request()->route('desk') === $cadDeskKey;
              $cadDeskCount = $cadQueueCounts[$cadDeskKey]['_all'] ?? 0;
              // Old-module screens kept in the new layout, under the desk that uses them (user-approved 2026-10-11).
              $cadExtras = [];
              if ($cadModule && $cadDeskKey === 'ad_report') {
                  // The old "Commission Correspondence File (Match MLSFileNo)" page, back by request (2026-10-12).
                  $cadExtras[] = ['Create Correspondence File (Match MLSFileNo)', route('mls-file-no-matching.index'), 'check', request()->routeIs('mls-file-no-matching.index')];
                  $cadExtras[] = ['Index Cards for File Movement', route('cadastral-module.index-cards.index'), 'id-card', request()->routeIs('cadastral-module.index-cards.*')];
              }
              if ($cadModule && $cadDeskKey === 'ad_pnd') {
                  $cadExtras[] = ['Surveyors', route('cadastral-module.surveyors.index'), 'users', request()->routeIs('cadastral-module.surveyors.*')];
              }
              $cadLegacyHere = $cadLegacyIc && $cadDeskKey === 'ad_report';
            @endphp
            @if (! $cadModule && ! $cadLegacyHere)
              {{-- Desks are for Cad - Records; a legacy index-card user sees only Cadastral Report. --}}
            @elseif (empty($cadQ['entries']) && ! $cadExtras && ! $cadLegacyHere)
              {{-- A desk with no lists is a single link to its inbox. --}}
              <a href="{{ route('cadastral-dept.desks.show', $cadDeskKey) }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadDeskOn ? 'active' : '' }}">
                <i data-lucide="inbox" class="h-4 w-4 text-rose-500"></i>
                <span>{{ $cadQ['group'] }}</span>{!! $cadBadge($cadDeskCount, 'Files at ' . $cadQ['group']) !!}
              </a>
            @else
              <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="cadastralDept-{{ $cadDeskKey }}">
                <div class="flex items-center gap-2">
                  <i data-lucide="inbox" class="h-4 w-4 text-rose-500"></i>
                  <span>{{ $cadQ['group'] }}</span>
                  {!! str_replace('ml-auto', 'ml-1', $cadBadge($cadDeskCount, 'Files at ' . $cadQ['group'])) !!}
                </div>
                <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastralDept-{{ $cadDeskKey }}"></i>
              </div>
              <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-{{ $cadDeskKey }}">
                @foreach ($cadModule ? $cadQ['entries'] : [] as $cadQKey => $cadEntry)
                  @php
                    // A list with no rules is the whole inbox, so it also lights with no ?queue=.
                    $cadIsAll = ! array_intersect(['steps', 'tasks', 'to', 'at', 'ready'], array_keys($cadEntry));
                    $cadOn = $cadDeskOn && (request('queue') === $cadQKey || ($cadIsAll && ! request('queue')));
                  @endphp
                  <a href="{{ route('cadastral-dept.desks.show', $cadIsAll ? ['desk' => $cadDeskKey] : ['desk' => $cadDeskKey, 'queue' => $cadQKey]) }}"
                    class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadOn ? 'active' : '' }}">
                    <i data-lucide="{{ $cadIsAll ? 'list' : 'corner-down-right' }}" class="h-3.5 w-3.5 text-rose-400"></i>
                    <span>{{ $cadEntry['label'] }}</span>{!! $cadBadge($cadQueueCounts[$cadDeskKey][$cadQKey] ?? 0, $cadEntry['label']) !!}
                  </a>
                @endforeach

                @foreach ($cadExtras as [$cadXLabel, $cadXUrl, $cadXIcon, $cadXOn])
                  <a href="{{ $cadXUrl }}"
                    class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $cadXOn ? 'active' : '' }}">
                    <i data-lucide="{{ $cadXIcon }}" class="h-3.5 w-3.5 text-rose-400"></i>
                    <span>{{ $cadXLabel }}</span>
                  </a>
                @endforeach

                {{-- Property Index Cards Assistant (Legacy Records): 1. Table  2. Scans --}}
                @if ($cadLegacyHere)
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
                    <a href="{{ route('cadastral.index-cards.index') }}"
                      class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral.index-cards.index') ? 'active' : '' }}">
                      <i data-lucide="scan-line" class="h-3.5 w-3.5 text-rose-400"></i>
                      <span>Scans</span>
                    </a>
                  </div>
                @endif
              </div>
            @endif
          @endforeach
      @endif

      {{-- Entries on their own (2026-10-12, user): Print File Label, as the old layout had it
           (open to everyone who sees this menu), then Title Status Update and Cadastral to Land
           (Land 12), which were under "Other Departments" (group removed). --}}
      @if(! $cadModule && $hasRole('Match Existing FileNo (MLSFileNo) '))
        {{-- Matching-only users cannot see Cadastral Report, where this page sits for everyone else. --}}
        <a href="{{ route('mls-file-no-matching.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mls-file-no-matching.index') ? 'active' : '' }}">
          <i data-lucide="check" class="h-4 w-4 text-rose-500"></i>
          <span>Create Correspondence File (Match MLSFileNo)</span>
        </a>
      @endif
      <a href="{{ route('cadastral_printlabel.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral_printlabel.index') ? 'active' : '' }}">
        <i data-lucide="printer" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral Print File Label</span>
      </a>
      <a href="{{ route('title-status.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="badge-check" class="h-4 w-4 text-rose-500"></i>
        <span>Title Status Update</span>
      </a>
      <a href="{{ route('survey-report.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="file-text" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral to Land (Land 12)</span>
      </a>

      {{-- Records: the legacy Survey Cadastral Attributions list, restored 2026-10-10. --}}
      @if($hasRole('Cad - Records'))
        <a href="{{ route('survey_cadastral.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_cadastral.index') ? 'active' : '' }}">
          <i data-lucide="clipboard" class="h-4 w-4 text-rose-500"></i>
          <span>Records</span>
        </a>
      @endif

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
          {{-- File Digital Library – Doc-WARE (the client's "Digital Registry"), under Digital Archive per the user 2026-10-11. --}}
          <a href="{{ route('filearchive.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>
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
