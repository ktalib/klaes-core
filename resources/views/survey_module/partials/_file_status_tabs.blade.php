{{-- Survey > File Status as tabs (user, 2026-10-30): the sidebar shows File Status once and each
     list is a tab here. Every tab is the existing page opened with ?url=survey (view only), so the
     tables, filters and permissions stay those pages' own. Drawn under the page header by
     admin.header when ?url=survey is on one of these routes. Regrant Files and Encumbrance are
     dropdown tabs, as the sidebar grouped them; Lien has no page yet. --}}
@php
  // Back to the search with the file this list was opened for, if any.
  $fsBack = request('fs') ?: (is_string(request('search')) ? request('search') : null);
  $fsTabs = [
      // The search across every list (CR 30102026 item 29, 2026-10-31): where File Status opens.
      ['Search', 'search', route('survey-module.file-status', $fsBack ? ['by' => 'file', 'q' => $fsBack] : []), request()->routeIs('survey-module.file-status')],
      ['RofO', 'file-check', route('land-rofos.index', ['url' => 'survey']), request()->routeIs('land-rofos.index')],
      ['CofO', 'award', route('propertycard.cofo', ['url' => 'survey']), request()->routeIs('propertycard.cofo')],
      ['Decommissioned', 'archive', route('file-decommissioning.decommissioned', ['url' => 'survey']), request()->routeIs('file-decommissioning.decommissioned')],
      ['Duplicates', 'copy', route('file-search-db.index', ['url' => 'survey']), request()->routeIs('file-search-db.index')],
      ['Regrant Files', 'refresh-cw', [
          ['Regrant Register', 'list', route('regrant.index', ['tab' => 'register', 'url' => 'survey']), request()->routeIs('regrant.index') && request('tab') !== 'due'],
          ['To be Regranted', 'alarm-clock', route('regrant.index', ['tab' => 'due', 'url' => 'survey']), request()->routeIs('regrant.index') && request('tab') === 'due'],
      ]],
      ['Title Status', 'badge-check', route('title-status.index') . '?url=survey', request()->routeIs('title-status.index')],
      ['DCIV', 'folder-kanban', route('dciv-generation.index', ['url' => 'survey']), request()->routeIs('dciv-generation.index')],
      ['Encumbrance', 'shield', [
          ['Caveat', 'shield-alert', route('caveat.index') . '?url=survey', request()->routeIs('caveat.index')],
          ['Mortgage', 'landmark', route('mortgages.index') . '?url=survey', request()->routeIs('mortgages.index')],
          ['Surrender & Release', 'key-round', route('surrender-release.index', ['url' => 'survey']), request()->routeIs('surrender-release.index')],
          ['Lien', 'lock', null, false],
      ]],
  ];
  // One colour per tab (user, 2026-10-30): [text/icon, light tint].
  $fsColors = [
      'Search' => ['#be185d', '#fdf2f8'],
      'RofO' => ['#be123c', '#fff1f2'],
      'CofO' => ['#b45309', '#fffbeb'],
      'Decommissioned' => ['#475569', '#f1f5f9'],
      'Duplicates' => ['#6d28d9', '#f5f3ff'],
      'Regrant Files' => ['#0f766e', '#f0fdfa'],
      'Title Status' => ['#047857', '#ecfdf5'],
      'DCIV' => ['#1d4ed8', '#eff6ff'],
      'Encumbrance' => ['#c2410c', '#fff7ed'],
  ];
  $fsStyle = fn (string $label) => '--c:' . ($fsColors[$label][0] ?? '#be185d') . ';--bg:' . ($fsColors[$label][1] ?? '#fdf2f8');
@endphp
<style>
  .sv-fs-tabs { display: flex; flex-wrap: wrap; gap: 0; padding: 0 .75rem; background: #fff; border-bottom: 1px solid #e5e7eb; position: relative; z-index: 40; }
  .sv-fs-tab { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; padding: .7rem .45rem; font-size: .8rem; font-weight: 500; color: var(--c, #4b5563); background: none; border: 0; border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; cursor: pointer; font-family: inherit; }
  .sv-fs-tab:hover { background: var(--bg, #fdf2f8); }
  .sv-fs-tab:focus-visible, .sv-fs-item:focus-visible { outline: 2px solid var(--c, #ec4899); outline-offset: -2px; }
  .sv-fs-tab.is-active { background: var(--bg, #fdf2f8); border-bottom-color: var(--c, #db2777); font-weight: 700; }
  .sv-fs-tab svg, .sv-fs-tab i, .sv-fs-item svg, .sv-fs-item i { width: 15px; height: 15px; flex-shrink: 0; }
  .sv-fs-tab .sv-fs-sub { font-weight: 500; opacity: .85; }
  .sv-fs-tab .sv-fs-caret { width: 13px; height: 13px; transition: transform .15s; }
  .sv-fs-drop { position: relative; }
  .sv-fs-drop.is-open .sv-fs-caret { transform: rotate(180deg); }
  .sv-fs-menu { display: none; position: absolute; left: 0; top: 100%; min-width: 13rem; margin-top: 2px; padding: .25rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; box-shadow: 0 10px 25px rgba(0,0,0,.12); }
  .sv-fs-drop.is-open .sv-fs-menu { display: block; }
  .sv-fs-item { display: flex; align-items: center; gap: .5rem; padding: .5rem .65rem; border-radius: .375rem; font-size: .8125rem; color: #374151; text-decoration: none; white-space: nowrap; }
  .sv-fs-item svg, .sv-fs-item i { color: var(--c, #be185d); }
  .sv-fs-item:hover { background: var(--bg, #fdf2f8); color: var(--c, #be185d); }
  .sv-fs-item.is-active { background: var(--bg, #fce7f3); color: var(--c, #be185d); font-weight: 600; box-shadow: inset 3px 0 0 var(--c, #be185d); }
  .sv-fs-item.is-disabled, .sv-fs-item.is-disabled svg, .sv-fs-item.is-disabled i { color: #9ca3af; cursor: default; }
  .sv-fs-item.is-disabled:hover { background: transparent; color: #9ca3af; }
</style>
<nav class="sv-fs-tabs" aria-label="File Status">
  @foreach ($fsTabs as $tab)
    @if (is_array($tab[2]))
      @php
        [$label, $icon, $items] = $tab;
        $current = collect($items)->first(fn ($it) => $it[3]);
      @endphp
      <div class="sv-fs-drop" data-sv-fs-drop style="{{ $fsStyle($label) }}">
        <button type="button" class="sv-fs-tab {{ $current ? 'is-active' : '' }}" aria-haspopup="true" aria-expanded="false">
          <i data-lucide="{{ $icon }}"></i><span>{{ $label }}</span>
          @if ($current)<span class="sv-fs-sub">· {{ $current[0] }}</span>@endif
          <i data-lucide="chevron-down" class="sv-fs-caret"></i>
        </button>
        <div class="sv-fs-menu" role="menu">
          @foreach ($items as [$itLabel, $itIcon, $itHref, $itOn])
            @if ($itHref)
              <a href="{{ $itHref }}" role="menuitem" class="sv-fs-item {{ $itOn ? 'is-active' : '' }}" @if ($itOn) aria-current="page" @endif>
                <i data-lucide="{{ $itIcon }}"></i><span>{{ $itLabel }}</span>
              </a>
            @else
              <span role="menuitem" class="sv-fs-item is-disabled" aria-disabled="true" title="No page yet">
                <i data-lucide="{{ $itIcon }}"></i><span>{{ $itLabel }}</span>
              </span>
            @endif
          @endforeach
        </div>
      </div>
    @else
      @php [$label, $icon, $href, $on] = $tab; @endphp
      <a href="{{ $href }}" style="{{ $fsStyle($label) }}" class="sv-fs-tab {{ $on ? 'is-active' : '' }}" @if ($on) aria-current="page" @endif>
        <i data-lucide="{{ $icon }}"></i><span>{{ $label }}</span>
      </a>
    @endif
  @endforeach
</nav>
<script>
  (function () {
    var drops = document.querySelectorAll('[data-sv-fs-drop]');
    function closeAll(except) {
      drops.forEach(function (d) {
        if (d === except) return;
        d.classList.remove('is-open');
        d.querySelector('button').setAttribute('aria-expanded', 'false');
      });
    }
    drops.forEach(function (d) {
      var btn = d.querySelector('button');
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !d.classList.contains('is-open');
        closeAll(d);
        d.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
          // Keep the menu on screen when the tab sits near the right edge.
          var menu = d.querySelector('.sv-fs-menu');
          menu.style.left = '0'; menu.style.right = 'auto';
          if (menu.getBoundingClientRect().right > window.innerWidth - 8) { menu.style.left = 'auto'; menu.style.right = '0'; }
        }
      });
    });
    document.addEventListener('click', function () { closeAll(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(null); });
  })();

  // Opened from a File Status search result (?fs=<file no.>): the list opens filtered to
  // that file. These pages search in the browser (DataTables, or their own search box),
  // so the number is typed in for the user once the page has set itself up.
  (function () {
    var fs = new URLSearchParams(window.location.search).get('fs');
    if (!fs) return;
    function type(el) {
      el.value = fs;
      ['input', 'keyup', 'change'].forEach(function (t) { el.dispatchEvent(new Event(t, { bubbles: true })); });
    }
    function run() {
      // A page's own search box, shown filled in (Duplicates, CofO, Decommissioned, Caveat log).
      ['file-search-keyword', 'cofo-search', 'searchDecommissioned', 'log-search-input'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) type(el);
      });
      var runBtn = document.getElementById('file-search-run');
      if (runBtn) runBtn.click();
      // DataTables lists (DCIV, Mortgage, Surrender & Release, CofO, Decommissioned).
      if (window.jQuery && jQuery.fn.dataTable) {
        jQuery.fn.dataTable.tables({ visible: false, api: true }).search(fs).draw();
        jQuery('.dataTables_filter input').val(fs);
      }
    }
    window.addEventListener('load', function () { setTimeout(run, 400); });
  })();
</script>
