{{-- Sidebar tab cards (user, 2026-11-06): a sidebar page that has tabs asks where to go first.
     A link opts in with data-tab-card="<key>"; a plain click opens this card on that key's options
     and each option goes straight to the tab. The link keeps its real href, so Ctrl/middle click and
     a browser without dialog support still open the page. The card title is the sidebar label.

     Only tabs reached by URL can be listed. A tab that is shown only when it has rows (Batches,
     Re-issuance, Mother, OSS) is still listed: the page opens it, empty if there is nothing in it.
     Survey File Status repeats survey_module.partials._file_status_tabs; change them together. --}}
@php
  // [text/icon, light tint]
  $tcTones = [
      'amber' => ['#b45309', '#fffbeb'], 'green' => ['#15803d', '#f0fdf4'], 'violet' => ['#6d28d9', '#f5f3ff'],
      'red' => ['#b91c1c', '#fef2f2'], 'rose' => ['#be123c', '#fff1f2'], 'indigo' => ['#4338ca', '#eef2ff'],
      'blue' => ['#1d4ed8', '#eff6ff'], 'teal' => ['#0f766e', '#f0fdfa'], 'slate' => ['#475569', '#f1f5f9'],
      'orange' => ['#c2410c', '#fff7ed'], 'pink' => ['#be185d', '#fdf2f8'],
  ];

  // The printed / not printed strip of the RofO and Recommendation pages, for one set of URLs.
  $tcRofo = fn (string $route, array $base, bool $batches = true, bool $reissuance = true) => array_values(array_filter([
      ['Not Printed', 'file-clock', route($route, $base + ['tab' => 'not_printed']), 'amber'],
      ['Printed', 'printer-check', route($route, $base + ['tab' => 'printed']), 'green'],
      $batches ? ['Batches', 'layers', route($route, $base + ['tab' => 'batches']), 'violet'] : null,
      $reissuance ? ['Re-issuance', 'refresh-cw', route($route, $base + ['tab' => 'reissuance']), 'red'] : null,
  ]));
  $tcRecommendation = fn (string $route, string $type) => array_values(array_filter([
      ['Not Printed', 'file-clock', route($route, ['type' => $type, 'tab' => 'not_printed']), 'amber'],
      ['Printed', 'printer-check', route($route, ['type' => $type, 'tab' => 'printed']), 'green'],
      $type === 'OSS' ? null : ['Mother Recommendations', 'file-check-2', route($route, ['type' => $type, 'tab' => 'extant']), 'indigo'],
      $type === 'OSS' ? null : ['OSS Recommendations', 'store', route($route, ['type' => $type, 'tab' => 'oss']), 'rose'],
      $type === 'OSS' ? null : ['Batch Recommendations', 'layers', route($route, ['type' => $type, 'tab' => 'batches']), 'violet'],
  ]));
  $tcGovPrivate = fn (string $route) => [
      ['Public', 'landmark', route($route), 'blue'],
      ['Private', 'user-round', route($route, ['tab' => 'private']), 'teal'],
  ];
  $tcFileNos = ['source' => 'lands-one-stop-shop', 'type' => 'change-of-name'];

  $tabCards = [
      // Lands
      'land-recommendation' => $tcRecommendation('land-recommendations.index', 'ROFO'),
      'oss-recommendation' => $tcRecommendation('land-recommendations.index', 'OSS'),
      'land-rofo' => $tcRofo('land-rofos.index', []),
      // OSS has no Batches or Re-issuance on its cards (user, 2026-11-06).
      'oss-rofo' => $tcRofo('land-rofos.index', ['view' => 'only'], false, false),
      'regrant' => [
          ['Re-grant Register', 'list', route('regrant.index', ['tab' => 'register']), 'blue'],
          ['To Be Re-granted', 'alarm-clock', route('regrant.index', ['tab' => 'due']), 'red'],
      ],
      'oss-generate-filenos' => [
          ['File Commissioning (FC)', 'file-plus-2', route('lands-one-stop-shop.applications.index', $tcFileNos + ['record_type' => 'fc']), 'blue'],
          ['Fetch Existing File Record (FEFR)', 'folder-search-2', route('lands-one-stop-shop.applications.index', $tcFileNos + ['record_type' => 'fefr']), 'orange'],
      ],

      // Cadastral: view only, so no Batches, as on the page.
      'cadastral-rofo' => $tcRofo('land-rofos.index', ['url' => 'cadastral'], false),

      // Survey
      'survey-file-status' => [
          ['Search', 'search', route('survey-module.file-status'), 'pink'],
          ['RofO', 'file-check', route('land-rofos.index', ['url' => 'survey']), 'rose'],
          ['CofO', 'award', route('propertycard.cofo', ['url' => 'survey']), 'amber'],
          ['Decommissioned', 'archive', route('file-decommissioning.decommissioned', ['url' => 'survey']), 'slate'],
          ['Duplicates', 'copy', route('file-search-db.index', ['url' => 'survey']), 'violet'],
          ['Title Status', 'badge-check', route('title-status.index') . '?url=survey', 'green'],
          ['DCIV', 'folder-kanban', route('dciv-generation.index', ['url' => 'survey']), 'blue'],
          ['group' => 'Regrant Files', 'items' => [
              ['Regrant Register', 'list', route('regrant.index', ['tab' => 'register', 'url' => 'survey']), 'teal'],
              ['To be Regranted', 'alarm-clock', route('regrant.index', ['tab' => 'due', 'url' => 'survey']), 'teal'],
          ]],
          ['group' => 'Encumbrance', 'items' => [
              ['Caveat', 'shield-alert', route('caveat.index') . '?url=survey', 'orange'],
              ['Mortgage', 'landmark', route('mortgages.index') . '?url=survey', 'orange'],
              ['Surrender & Release', 'key-round', route('surrender-release.index', ['url' => 'survey']), 'orange'],
              ['Lien', 'lock', null, 'slate'],
          ]],
      ],
      'survey-recommendation' => $tcRecommendation('survey-module.lg.land-recommendations.index', 'ROFO'),
      'survey-rofo' => $tcRofo('survey-module.lg.land-rofos.index', []),
      'survey-docware' => [
          ['Files', 'folder', route('filearchive.index', ['url' => 'survey']), 'pink'],
          ['Scanned Map Layouts', 'map', route('filearchive.index', ['url' => 'survey', 'section' => 'layouts']), 'blue'],
      ],
      'survey-op' => [
          ['Existing', 'archive', route('survey-module.compensation.op', ['tab' => 'existing']), 'slate'],
          ['New', 'file-signature', route('survey-module.compensation.op', ['tab' => 'new']), 'blue'],
      ],
      'survey-trees' => [
          ['Economic Trees', 'tree-pine', route('survey-module.compensation.trees', ['tab' => 'economic']), 'green'],
          ['Cash Crops', 'sprout', route('survey-module.compensation.trees', ['tab' => 'cash']), 'amber'],
      ],
      'survey-complaints' => collect(\App\Models\Survey\SurveyComplaint::CATEGORIES)
          ->map(fn ($label, $key) => [$label, 'message-square-warning', route('survey-module.compensation.complaints', ['tab' => $key]), 'orange'])
          ->values()->all(),
      'survey-surveyors' => $tcGovPrivate('survey-module.records.surveyors.index'),
      'survey-computation-files' => $tcGovPrivate('survey-module.gkn.computation-files'),
      'survey-its' => $tcGovPrivate('survey-module.gkn.its.index'),
      'survey-plot-showing-resettlement' => [
          ['Plot Showing', 'check-check', route('survey-module.compensation.plot-confirmations'), 'pink'],
          ['Ground Check', 'locate-fixed', route('survey-module.compensation.plot-confirmations', ['tab' => 'checks']), 'teal'],
      ],
      'survey-plot-showing' => [
          ['Plot Showing', 'check-check', route('survey-module.gkn.plot-showing'), 'pink'],
          ['Ground Check', 'locate-fixed', route('survey-module.gkn.plot-showing', ['tab' => 'checks']), 'teal'],
      ],
      'survey-production-unit' => [
          ['Queue', 'list', route('survey-module.sdi.production', ['tab' => 'queue']), 'amber'],
          ['Produced', 'check-circle', route('survey-module.sdi.production', ['tab' => 'produced']), 'green'],
          ['Rejected', 'x-circle', route('survey-module.sdi.production', ['tab' => 'rejected']), 'red'],
          ['MDAs', 'building-2', route('survey-module.sdi.registry'), 'blue'],
          ['Map Types', 'layers', route('survey-module.sdi.registry', ['tab' => 'types']), 'violet'],
      ],

      // System Admin
      'users' => [
          ['MDC - Mass Data Capture', 'users', route('users.index', ['tab' => 'mdc']), 'blue'],
          ['MLPP - Ministry of Land and Physical Planning', 'landmark', route('users.index', ['tab' => 'mlpp']), 'green'],
      ],
  ];

  // Match the create permission and destination of the page's New Recommendation button.
  if (auth()->check() && auth()->user()->canDo('Letter of Grant (RofO)', 'create')) {
      $tabCards['land-recommendation'][] = [
          'New Recommendation',
          'plus-circle',
          route('land-recommendations.create'),
          'green',
      ];
  }

  $tcStyle = function (string $tone) use ($tcTones) {
      [$c, $bg] = $tcTones[$tone] ?? $tcTones['slate'];

      return '--c:' . $c . ';--bg:' . $bg;
  };
@endphp
<style>
  .tab-card { width: min(36rem, calc(100vw - 2rem)); max-height: calc(100vh - 3rem); margin: auto; padding: 1.25rem; border: 0; border-radius: 1rem; background: #fff; color: #0f172a; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35); overflow-y: auto; }
  .tab-card::backdrop { background: rgba(15,23,42,.5); }
  .tab-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
  .tab-card__title { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.3; }
  .tab-card__hint { margin: .15rem 0 0; font-size: .8rem; color: #64748b; }
  .tab-card__close { flex-shrink: 0; display: inline-flex; padding: .3rem; border: 0; border-radius: .375rem; background: none; color: #64748b; cursor: pointer; }
  .tab-card__close:hover { background: #f1f5f9; color: #0f172a; }
  .tab-card__close svg { width: 18px; height: 18px; }
  .tab-card__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(13rem, 100%), 1fr)); gap: .5rem; }
  .tab-card__grid[hidden] { display: none; }
  .tab-card__group { grid-column: 1 / -1; margin-top: .5rem; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
  .tab-card__opt { display: flex; align-items: center; gap: .6rem; min-height: 3rem; padding: .6rem .75rem; border: 1px solid #e2e8f0; border-radius: .625rem; background: #fff; color: var(--c, #475569); font-size: .85rem; font-weight: 600; line-height: 1.25; text-decoration: none; transition: background .12s, border-color .12s; }
  .tab-card__opt:hover { background: var(--bg, #f1f5f9); border-color: var(--c, #475569); color: var(--c, #475569); }
  .tab-card__opt:focus-visible, .tab-card__close:focus-visible { outline: 2px solid var(--c, #2563eb); outline-offset: 1px; }
  .tab-card__opt svg { width: 18px; height: 18px; flex-shrink: 0; }
  .tab-card__opt.is-disabled { background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; cursor: default; }
</style>
<dialog id="sidebarTabCard" class="tab-card" aria-labelledby="sidebarTabCardTitle">
  <div class="tab-card__head">
    <div>
      <h2 class="tab-card__title" id="sidebarTabCardTitle"></h2>
      <p class="tab-card__hint">Choose where to go</p>
    </div>
    <button type="button" class="tab-card__close" data-tab-card-close aria-label="Close">
      <i data-lucide="x"></i>
    </button>
  </div>
  @foreach ($tabCards as $cardKey => $cardOptions)
    <div class="tab-card__grid" data-tab-card-panel="{{ $cardKey }}" hidden>
      @foreach ($cardOptions as $option)
        @foreach (isset($option['group']) ? $option['items'] : [$option] as [$optLabel, $optIcon, $optHref, $optTone])
          @if (isset($option['group']) && $loop->first)
            <div class="tab-card__group">{{ $option['group'] }}</div>
          @endif
          @if ($optHref)
            <a href="{{ $optHref }}" class="tab-card__opt" style="{{ $tcStyle($optTone) }}">
              <i data-lucide="{{ $optIcon }}"></i><span>{{ $optLabel }}</span>
            </a>
          @else
            <span class="tab-card__opt is-disabled" aria-disabled="true" title="No page yet">
              <i data-lucide="{{ $optIcon }}"></i><span>{{ $optLabel }}</span>
            </span>
          @endif
        @endforeach
      @endforeach
    </div>
  @endforeach
</dialog>
<script>
  (function () {
    var card = document.getElementById('sidebarTabCard');
    if (!card || typeof card.showModal !== 'function') return;
    var title = document.getElementById('sidebarTabCardTitle');

    // Capture phase: the sidebar's own click handlers must not move the active highlight
    // to a link the user may still cancel out of.
    document.addEventListener('click', function (e) {
      var link = e.target.closest ? e.target.closest('a[data-tab-card]') : null;
      if (!link || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      var panel = card.querySelector('[data-tab-card-panel="' + link.getAttribute('data-tab-card') + '"]');
      if (!panel) return;

      e.preventDefault();
      e.stopPropagation();
      card.querySelectorAll('[data-tab-card-panel]').forEach(function (p) { p.hidden = p !== panel; });
      var label = link.querySelector('span');
      title.textContent = label ? label.textContent.trim() : '';
      card.showModal();
      var first = panel.querySelector('a');
      if (first) first.focus();
    }, true);

    card.addEventListener('click', function (e) {
      // The dialog itself is only the target for a click on its backdrop.
      if (e.target === card || e.target.closest('[data-tab-card-close]')) card.close();
    });
  })();
</script>
