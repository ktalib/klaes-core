<!-- 16. Cadastral -->
{{-- NEW LAYOUT, ENABLED FOR EVERYONE 2026-10-12 (built 2026-10-11), restructured to the user's corrected
     list (WhatsApp image, 2026-10-12): a. Dashboard  b. Registry  c. D CAD  d. DD CAD  e. Cadastral Information
     f. Cadastral Report  g. Plans & Descriptions  h. Other Departments  i. GIS  j. File Digital Library
     k. Digital Archive. Earlier notes: the user's proposed sidebar in
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

      {{-- b–k: the user's corrected sidebar (WhatsApp image, 2026-10-12). Desk entries open the desk's
           inbox, or one of its lists (config cadastral_dept.queues); the actions stay in each row's menu. --}}
      @php
        $cadDeskOn = fn (string $desk, ?string $queue = null) => request()->routeIs('cadastral-dept.desks.show')
            && request()->route('desk') === $desk && ($queue === null ? ! request('queue') : request('queue') === $queue);
        $cadDeskUrl = fn (string $desk, ?string $queue = null) => route('cadastral-dept.desks.show', $queue === null ? $desk : ['desk' => $desk, 'queue' => $queue]);
        $cadCount = fn (string $desk, ?string $queue = null) => $cadQueueCounts[$desk][$queue ?? '_all'] ?? 0;
        $cadLabel = fn (string $desk, string $queue, string $fallback) => $cadQueues[$desk]['entries'][$queue]['label'] ?? $fallback;
        // One sidebar link. $sub: an entry inside a group (smaller icon).
        $cadLink = function (string $label, string $url, string $icon, bool $on, string $badge = '', bool $sub = true) {
            $size = $sub ? 'h-3.5 w-3.5 text-rose-400' : 'h-4 w-4 text-rose-500';
            return '<a href="' . e($url) . '" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 ' . ($on ? 'active' : '') . '">'
                . '<i data-lucide="' . e($icon) . '" class="' . $size . '"></i><span>' . e($label) . '</span>' . $badge . '</a>';
        };
        // A collapsible group header; its entries go in the matching data-content div.
        $cadGroup = function (string $key, string $label, string $icon, bool $sub = false) {
            $size = $sub ? 'h-3.5 w-3.5' : 'h-4 w-4';
            return '<div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="' . e($key) . '">'
                . '<div class="flex items-center gap-2"><i data-lucide="' . e($icon) . '" class="' . $size . ' text-rose-500"></i><span>' . e($label) . '</span></div>'
                . '<i data-lucide="chevron-right" class="' . $size . ' transition-transform duration-200" data-chevron="' . e($key) . '"></i></div>';
        };
      @endphp

      {{-- b. Registry: Intake Queue, Cadastral Print File Label (open to everyone who sees this menu) --}}
      {!! $cadGroup('cadastralDept-registry', 'Registry', 'inbox') !!}
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-registry">
        @if($cadModule)
          {!! $cadLink('Intake Queue', $cadDeskUrl('registry'), 'list', $cadDeskOn('registry'), $cadBadge($cadCount('registry'), 'Files at Registry')) !!}
        @endif
        {!! $cadLink('Cadastral Print File Label', route('cadastral_printlabel.index'), 'printer', request()->routeIs('cadastral_printlabel.index')) !!}
      </div>

      @if($cadModule)
        {{-- c. D CAD  d. DD CAD --}}
        {!! $cadLink('D CAD', $cadDeskUrl('director_cadastral'), 'inbox', $cadDeskOn('director_cadastral'), $cadBadge($cadCount('director_cadastral'), 'Files at D Cad'), false) !!}
        {!! $cadLink('DD CAD', $cadDeskUrl('deputy_director_cadastral'), 'inbox', $cadDeskOn('deputy_director_cadastral'), $cadBadge($cadCount('deputy_director_cadastral'), 'Files at DD Cad'), false) !!}

        {{-- e. Cadastral Information --}}
        {!! $cadGroup('cadastralDept-ad_information', 'Cadastral Information', 'inbox') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-ad_information">
          @foreach (['fmc' => ['File Movement Card (FMC)', 'id-card'], 'first_charting' => ['1st Charting (Direct Allocation Files)', 'map']] as $cadQKey => [$cadQLabel, $cadQIcon])
            {!! $cadLink($cadLabel('ad_information', $cadQKey, $cadQLabel), $cadDeskUrl('ad_information', $cadQKey), $cadQIcon, $cadDeskOn('ad_information', $cadQKey), $cadBadge($cadCount('ad_information', $cadQKey), $cadQLabel)) !!}
          @endforeach
        </div>
      @endif

      {{-- f. Cadastral Report (also shown to legacy index-card users, who see only Property Index Cards) --}}
      @if($cadModule || $cadLegacyIc)
        {!! $cadGroup('cadastralDept-ad_report', 'Cadastral Report', 'inbox') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-ad_report">
          @if($cadModule)
            {!! $cadLink($cadLabel('ad_report', 'all', 'All Reports'), $cadDeskUrl('ad_report'), 'list', $cadDeskOn('ad_report') || $cadDeskOn('ad_report', 'all'), $cadBadge($cadCount('ad_report'), 'Files at AD Cad Report')) !!}

            {!! $cadGroup('cadastralDept-ad_report-charting', 'Charting', 'map', true) !!}
            <div class="pl-3 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-ad_report-charting">
              @foreach (['charting_first' => '1st Charting (Conversion Files)', 'charting_second' => '2nd Charting (Direct Allocation Files)'] as $cadQKey => $cadQLabel)
                {!! $cadLink($cadLabel('ad_report', $cadQKey, $cadQLabel), $cadDeskUrl('ad_report', $cadQKey), 'corner-down-right', $cadDeskOn('ad_report', $cadQKey), $cadBadge($cadCount('ad_report', $cadQKey), $cadQLabel)) !!}
              @endforeach
            </div>
          @endif
          @if($cadModule || $hasRole('Match Existing FileNo (MLSFileNo) '))
            {{-- The old "Commission Correspondence File (Match MLSFileNo)" page. --}}
            {!! $cadLink('Commission/Match Correspondence File', route('mls-file-no-matching.index'), 'check', request()->routeIs('mls-file-no-matching.index')) !!}
          @endif
          {!! $cadLink('Property Index Cards (Legacy Records)', route('property_index_card.index'), 'book-open', request()->routeIs('property_index_card.index')) !!}
        </div>
      @elseif($hasRole('Match Existing FileNo (MLSFileNo) '))
        {{-- Matching-only users see just this page. --}}
        {!! $cadLink('Commission/Match Correspondence File', route('mls-file-no-matching.index'), 'check', request()->routeIs('mls-file-no-matching.index'), '', false) !!}
      @endif

      @if($cadModule)
        {{-- g. Plans & Descriptions --}}
        {!! $cadGroup('cadastralDept-ad_pnd', 'Plans & Descriptions', 'inbox') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastralDept-ad_pnd">
          {!! $cadLink($cadLabel('ad_pnd', 'all', 'All Files at P&D'), $cadDeskUrl('ad_pnd'), 'list', $cadDeskOn('ad_pnd') || $cadDeskOn('ad_pnd', 'all'), $cadBadge($cadCount('ad_pnd'), 'Files at AD P&D')) !!}
          {!! $cadLink('Jobs', route('cadastral-module.survey-jobs.index'), 'briefcase', request()->routeIs('cadastral-module.survey-jobs.*')) !!}
          {!! $cadLink('Surveyors', route('cadastral-module.surveyors.index'), 'users', request()->routeIs('cadastral-module.surveyors.*')) !!}
          @foreach (['its' => ['Assign to Surveyor (I to S)', 'send'], 'bill' => ['Fees, Plan and Description', 'receipt']] as $cadQKey => [$cadQLabel, $cadQIcon])
            {!! $cadLink($cadLabel('ad_pnd', $cadQKey, $cadQLabel), $cadDeskUrl('ad_pnd', $cadQKey), $cadQIcon, $cadDeskOn('ad_pnd', $cadQKey), $cadBadge($cadCount('ad_pnd', $cadQKey), $cadQLabel)) !!}
          @endforeach
        </div>
      @endif

      @if($cadModule)
        {{-- h. Verification ▸ Logs (user's sidebar update, 2026-10-12): every Verification file, with its status. --}}
        {!! $cadGroup('cadastral-verification', 'Verification', 'shield-check') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastral-verification">
          {!! $cadLink('Logs', route('cadastral-dept.cases.index', ['workflow' => 'verification']), 'scroll-text', request()->routeIs('cadastral-dept.cases.index') && request('workflow') === 'verification') !!}
        </div>
      @endif

      {{-- i. Other Departments (back by the corrected sidebar, 2026-10-12) --}}
      {!! $cadGroup('cadastral-other-departments', 'Other Departments', 'building-2') !!}
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastral-other-departments">
        {!! $cadLink('Title Status Update', route('title-status.index') . '?url=cadastral', 'badge-check', request()->routeIs('title-status.index') && request('url') === 'cadastral') !!}
        {!! $cadLink('Cadastral to Land (Land 12)', route('survey-report.index') . '?url=cadastral', 'file-text', request()->routeIs('survey-report.index') && request()->query('url') === 'cadastral') !!}
      </div>

      {{-- j. GIS --}}
      @if($cadExtract)
        {!! $cadGroup('cadastral-gis', 'GIS', 'map-pinned') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadastral-gis">
          {{-- ?url= only decides which sidebar lights the entry. --}}
          {!! $cadLink('Survey Plan Extraction', route('survey_plan_extraction.index') . '?url=cadastral', 'sparkles', request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'cadastral') !!}
        </div>
      @endif

      @if($cadArchive)
        {{-- k. File Digital Library – Doc-WARE (the client's "Digital Registry"), on its own again --}}
        {!! $cadLink('File Digital Library – Doc-WARE', route('filearchive.index', ['url' => 'cadastral']), 'library', request()->routeIs('filearchive.index') && request('url') === 'cadastral', '', false) !!}

        {{-- l. Digital Archive --}}
        {!! $cadGroup('digitalArchive-cadastral', 'Digital Archive', 'archive') !!}
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-cadastral">
          {!! $cadLink('File Tracker Dashboard', route('file-tracker.dashboard', ['url' => 'cadastral']), 'bar-chart-2', request()->routeIs('file-tracker.dashboard') && request('url') === 'cadastral') !!}
          {!! $cadLink('File Tracker (Archive)', route('track-file-archive.index', ['url' => 'cadastral']), 'archive', request()->routeIs('track-file-archive.index') && request('url') === 'cadastral') !!}
        </div>
      @endif

      {{-- m. Reports, after Digital Archive (user, 2026-10-13): the module's report page, as in the previous layout --}}
      @if($cadModule)
        {!! $cadLink('Reports', route('cadastral-module.analytics'), 'file-bar-chart', request()->routeIs('cadastral-module.analytics'), '', false) !!}
      @endif
    </div>
  </div>
@endif
