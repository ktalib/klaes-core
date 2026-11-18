{{-- Configurable Entries tab bar, grouped by department (ConfigurableEntriesController::TAB_GROUPS).
     Used by index.blade.php and farm-features.blade.php. Expects $tabGroups and $activeTab. --}}
<nav class="ce-tab-groups" aria-label="Configurable entries">
    @foreach($tabGroups as $groupKey => $group)
        <div class="ce-tab-group {{ array_key_exists($activeTab, $group['tabs']) ? 'current' : '' }}">
            <div class="ce-group-label">
                <i data-lucide="{{ $group['icon'] }}" class="h-4 w-4"></i>
                <span>{{ $group['label'] }}</span>
            </div>
            <div class="ce-tabs">
                @foreach($group['tabs'] as $key => $meta)
                    <a href="{{ isset($meta['route']) ? route($meta['route']) : route('configurable-entries.index', ['tab' => $key]) }}"
                        class="ce-tab {{ $activeTab === $key ? 'active' : '' }}"
                        style="--ce-accent: {{ $meta['accent'] }}; --ce-tint: {{ $meta['tint'] }}">
                        <i data-lucide="{{ $meta['icon'] }}" class="h-4 w-4"></i>
                        <span>{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>

<style>
    /* Each tab carries its own --ce-accent / --ce-tint (ConfigurableEntriesController::TABS).
       The icon is a Lucide stroke, which follows `color`, so it takes the accent without a rule of its own. */
    .ce-tab-groups { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 4px 6px; }
    .ce-tab-group { display: flex; align-items: center; gap: 12px; padding: 6px 4px; }
    .ce-tab-group + .ce-tab-group { border-top: 1px solid #f1f5f9; }
    .ce-group-label { flex: 0 0 170px; display: inline-flex; align-items: center; gap: 7px; padding-left: 6px;
                      font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
    .ce-tab-group.current .ce-group-label { color: #0f172a; }
    .ce-tabs { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; min-width: 0; }
    @media (max-width: 640px) {
        .ce-tab-group { flex-direction: column; align-items: stretch; gap: 6px; }
        .ce-group-label { flex-basis: auto; }
    }
    .ce-tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 10px; font-size: 14px; font-weight: 600;
              color: var(--ce-accent, #4b5563); background: var(--ce-tint, transparent);
              border: 1px solid transparent; transition: background .15s, border-color .15s, box-shadow .15s; }
    .ce-tab:hover { border-color: var(--ce-accent, #d1d5db); }
    .ce-tab.active { background: var(--ce-accent, #2563eb); border-color: var(--ce-accent, #2563eb); color: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .25); }
</style>
