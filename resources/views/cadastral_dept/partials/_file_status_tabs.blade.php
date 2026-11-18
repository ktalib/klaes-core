{{-- Cadastral > Verification > File Status as tabs (user, 2026-11-17: "use the same style we used for survey"):
     a copy of survey_module/partials/_file_status_tabs. The sidebar shows File Status once (a tab card) and each list
     is a tab here, the existing page opened with ?url=cadastral (view only). Drawn under the page header by
     admin.header on the Search page and on these lists when ?url=cadastral. The ?fs= prefill stays in
     cadastral_dept/partials/_fs_prefill. --}}
@php
  // Back to the search with the file this list was opened for, if any.
  $cdFsBack = request('fs') ?: (is_string(request('search')) ? request('search') : null);
  $cdFsTabs = [
      // The search across every list (CR 30102026 item 29, 2026-10-31): where File Status opens.
      ['Search', 'search', route('cadastral-dept.file-status', $cdFsBack ? ['by' => 'file', 'q' => $cdFsBack] : []), request()->routeIs('cadastral-dept.file-status')],
      ['RofO', 'file-check', route('land-rofos.index', ['url' => 'cadastral']), request()->routeIs('land-rofos.index')],
      ['CofO', 'award', route('propertycard.cofo', ['url' => 'cadastral']), request()->routeIs('propertycard.cofo')],
      ['Decommissioned', 'archive', route('file-decommissioning.decommissioned', ['url' => 'cadastral']), request()->routeIs('file-decommissioning.decommissioned')],
      ['Duplicates', 'copy', route('file-search-db.index', ['url' => 'cadastral']), request()->routeIs('file-search-db.index')],
      ['Regrant Files', 'refresh-cw', [
          ['Regrant Register', 'list', route('regrant.index', ['tab' => 'register', 'url' => 'cadastral']), request()->routeIs('regrant.index') && request('tab') !== 'due'],
          ['To be Regranted', 'alarm-clock', route('regrant.index', ['tab' => 'due', 'url' => 'cadastral']), request()->routeIs('regrant.index') && request('tab') === 'due'],
      ]],
      ['Title Status', 'badge-check', route('title-status.index') . '?url=cadastral', request()->routeIs('title-status.index')],
      ['DCIV', 'folder-kanban', route('dciv-generation.index', ['url' => 'cadastral']), request()->routeIs('dciv-generation.index')],
      // ST Files, its own screen (user, 2026-11-18).
      ['ST Files', 'building-2', route('st-file-status.index', ['url' => 'cadastral']), request()->routeIs('st-file-status.index')],
      ['Encumbrance', 'shield', [
          ['Caveat', 'shield-alert', route('caveat.index') . '?url=cadastral', request()->routeIs('caveat.index')],
          ['Mortgage', 'landmark', route('mortgages.index') . '?url=cadastral', request()->routeIs('mortgages.index')],
          ['Surrender & Release', 'key-round', route('surrender-release.index', ['url' => 'cadastral']), request()->routeIs('surrender-release.index')],
          ['Lien', 'lock', null, false],
      ]],
  ];
  // One colour per tab (user, 2026-10-30): [text/icon, light tint].
  $cdFsColors = [
      'Search' => ['#be185d', '#fdf2f8'],
      'RofO' => ['#be123c', '#fff1f2'],
      'CofO' => ['#b45309', '#fffbeb'],
      'Decommissioned' => ['#475569', '#f1f5f9'],
      'Duplicates' => ['#6d28d9', '#f5f3ff'],
      'Regrant Files' => ['#0f766e', '#f0fdfa'],
      'Title Status' => ['#047857', '#ecfdf5'],
      'DCIV' => ['#1d4ed8', '#eff6ff'],
      'ST Files' => ['#7e22ce', '#faf5ff'],
      'Encumbrance' => ['#c2410c', '#fff7ed'],
  ];
  $cdFsStyle = fn (string $label) => '--c:' . ($cdFsColors[$label][0] ?? '#be185d') . ';--bg:' . ($cdFsColors[$label][1] ?? '#fdf2f8');
@endphp
<style>
  .cd-fs-tabs { display: flex; flex-wrap: wrap; gap: 0; padding: 0 .75rem; background: #fff; border-bottom: 1px solid #e5e7eb; position: relative; z-index: 40; }
  .cd-fs-tab { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; padding: .7rem .45rem; font-size: .8rem; font-weight: 500; color: var(--c, #4b5563); background: none; border: 0; border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; cursor: pointer; font-family: inherit; }
  .cd-fs-tab:hover { background: var(--bg, #fdf2f8); }
  .cd-fs-tab:focus-visible, .cd-fs-item:focus-visible { outline: 2px solid var(--c, #ec4899); outline-offset: -2px; }
  .cd-fs-tab.is-active { background: var(--bg, #fdf2f8); border-bottom-color: var(--c, #db2777); font-weight: 700; }
  .cd-fs-tab svg, .cd-fs-tab i, .cd-fs-item svg, .cd-fs-item i { width: 15px; height: 15px; flex-shrink: 0; }
  .cd-fs-tab .cd-fs-sub { font-weight: 500; opacity: .85; }
  .cd-fs-tab .cd-fs-caret { width: 13px; height: 13px; transition: transform .15s; }
  .cd-fs-drop { position: relative; }
  .cd-fs-drop.is-open .cd-fs-caret { transform: rotate(180deg); }
  .cd-fs-menu { display: none; position: absolute; left: 0; top: 100%; min-width: 13rem; margin-top: 2px; padding: .25rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; box-shadow: 0 10px 25px rgba(0,0,0,.12); }
  .cd-fs-drop.is-open .cd-fs-menu { display: block; }
  .cd-fs-item { display: flex; align-items: center; gap: .5rem; padding: .5rem .65rem; border-radius: .375rem; font-size: .8125rem; color: #374151; text-decoration: none; white-space: nowrap; }
  .cd-fs-item svg, .cd-fs-item i { color: var(--c, #be185d); }
  .cd-fs-item:hover { background: var(--bg, #fdf2f8); color: var(--c, #be185d); }
  .cd-fs-item.is-active { background: var(--bg, #fce7f3); color: var(--c, #be185d); font-weight: 600; box-shadow: inset 3px 0 0 var(--c, #be185d); }
  .cd-fs-item.is-disabled, .cd-fs-item.is-disabled svg, .cd-fs-item.is-disabled i { color: #9ca3af; cursor: default; }
  .cd-fs-item.is-disabled:hover { background: transparent; color: #9ca3af; }
</style>
<nav class="cd-fs-tabs" aria-label="File Status">
  @foreach ($cdFsTabs as $tab)
    @if (is_array($tab[2]))
      @php
        [$label, $icon, $items] = $tab;
        $current = collect($items)->first(fn ($it) => $it[3]);
      @endphp
      <div class="cd-fs-drop" data-cd-fs-drop style="{{ $cdFsStyle($label) }}">
        <button type="button" class="cd-fs-tab {{ $current ? 'is-active' : '' }}" aria-haspopup="true" aria-expanded="false">
          <i data-lucide="{{ $icon }}"></i><span>{{ $label }}</span>
          @if ($current)<span class="cd-fs-sub">· {{ $current[0] }}</span>@endif
          <i data-lucide="chevron-down" class="cd-fs-caret"></i>
        </button>
        <div class="cd-fs-menu" role="menu">
          @foreach ($items as [$itLabel, $itIcon, $itHref, $itOn])
            @if ($itHref)
              <a href="{{ $itHref }}" role="menuitem" class="cd-fs-item {{ $itOn ? 'is-active' : '' }}" @if ($itOn) aria-current="page" @endif>
                <i data-lucide="{{ $itIcon }}"></i><span>{{ $itLabel }}</span>
              </a>
            @else
              <span role="menuitem" class="cd-fs-item is-disabled" aria-disabled="true" title="No page yet">
                <i data-lucide="{{ $itIcon }}"></i><span>{{ $itLabel }}</span>
              </span>
            @endif
          @endforeach
        </div>
      </div>
    @else
      @php [$label, $icon, $href, $on] = $tab; @endphp
      <a href="{{ $href }}" style="{{ $cdFsStyle($label) }}" class="cd-fs-tab {{ $on ? 'is-active' : '' }}" @if ($on) aria-current="page" @endif>
        <i data-lucide="{{ $icon }}"></i><span>{{ $label }}</span>
      </a>
    @endif
  @endforeach
</nav>
<script>
  (function () {
    var drops = document.querySelectorAll('[data-cd-fs-drop]');
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
          var menu = d.querySelector('.cd-fs-menu');
          menu.style.left = '0'; menu.style.right = 'auto';
          if (menu.getBoundingClientRect().right > window.innerWidth - 8) { menu.style.left = 'auto'; menu.style.right = '0'; }
        }
      });
    });
    document.addEventListener('click', function () { closeAll(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(null); });
  })();

</script>
