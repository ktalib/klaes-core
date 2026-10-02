{{--
 | System Admin → Configurable Entries → Module Permissions.
 |
 | Read-only by design. A user's grid is edited on the user, because one person's permissions
 | are a single coherent decision; a module's column across 537 users is not something anyone
 | sits down and edits. What this tab answers is what the user screens cannot: which modules
 | carry delete, which are granted to nobody, and where the registry has drifted from what
 | people actually hold.
 --}}

<div class="space-y-4">

    {{-- Enforcement posture. Worth showing prominently: with strict mode off the gate is
         permissive by design during rollout, and someone reading only the grid below could
         otherwise assume the numbers are already being enforced to the letter. --}}
    <div class="mp-posture {{ $moduleStrict['enabled'] ? '' : 'is-off' }}">
        <div class="mp-posture-main">
            <i data-lucide="{{ $moduleStrict['enabled'] ? 'shield-check' : 'shield-off' }}" class="mp-posture-icon"></i>
            <div>
                <p class="mp-posture-title">
                    @if (!$moduleStrict['enabled'])
                        {{ __('Enforcement is switched off') }}
                    @elseif ($moduleStrict['routes'] && $moduleStrict['actions'])
                        {{ __('Enforcing strictly') }}
                    @else
                        {{ __('Enforcing in rollout mode') }}
                    @endif
                </p>
                <p class="mp-posture-sub">
                    @if (!$moduleStrict['enabled'])
                        {{ __('MODULE_PERMISSIONS_ENABLED is false — every permission below is recorded but nothing is blocked.') }}
                    @else
                        {{ __('Unmapped routes:') }}
                        <strong>{{ $moduleStrict['routes'] ? __('denied') : __('allowed and logged') }}</strong>
                        ·
                        {{ __('Module with no stored grant:') }}
                        <strong>{{ $moduleStrict['actions'] ? __('denied') : __('allowed except delete') }}</strong>
                    @endif
                </p>
            </div>
        </div>
        <code class="mp-posture-cmd">php artisan permissions:audit</code>
    </div>

    {{-- Totals --}}
    <div class="mp-stats">
        <div class="mp-stat">
            <span class="mp-stat-value">{{ number_format($moduleTotals['modules']) }}</span>
            <span class="mp-stat-label">{{ __('modules') }}</span>
        </div>
        <div class="mp-stat">
            <span class="mp-stat-value">{{ number_format($moduleTotals['granted']) }}</span>
            <span class="mp-stat-label">{{ __('granted to someone') }}</span>
        </div>
        <div class="mp-stat {{ $moduleTotals['unused'] > 0 ? 'is-muted' : '' }}">
            <span class="mp-stat-value">{{ number_format($moduleTotals['unused']) }}</span>
            <span class="mp-stat-label">{{ __('granted to nobody') }}</span>
        </div>
        <div class="mp-stat {{ $moduleTotals['delete_grants'] > 0 ? 'is-danger' : '' }}">
            <span class="mp-stat-value">{{ number_format($moduleTotals['delete_grants']) }}</span>
            <span class="mp-stat-label">{{ __('delete grants') }}</span>
        </div>
        <div class="mp-stat {{ $moduleTotals['unregistered'] > 0 ? 'is-warn' : '' }}">
            <span class="mp-stat-value">{{ number_format($moduleTotals['unregistered']) }}</span>
            <span class="mp-stat-label">{{ __('not in registry') }}</span>
        </div>
        <div class="mp-stat">
            <span class="mp-stat-value">{{ number_format($moduleTotals['grant_rows']) }}</span>
            <span class="mp-stat-label">{{ __('grant rows') }}</span>
        </div>
    </div>

    @if ($moduleTotals['unregistered'] > 0)
        <div class="mp-alert">
            <i data-lucide="alert-triangle" class="mp-alert-icon"></i>
            <div>
                <strong>{{ trans_choice('{1}:count module is granted to users but has no User Roles row.|[2,*]:count modules are granted to users but have no User Roles row.', $moduleTotals['unregistered'], ['count' => $moduleTotals['unregistered']]) }}</strong>
                <p>
                    {{ __('These are real access — the sidebar still honours several of them. They are listed below marked "not in registry" so they can be added on the') }}
                    <a href="{{ route('configurable-entries.index', ['tab' => 'user-roles']) }}">{{ __('User Roles') }}</a>
                    {{ __('tab, rather than being silently dropped.') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Filters. Department first, matching the permission grid on the user modal. --}}
    <form method="GET" class="mp-filters">
        <input type="hidden" name="tab" value="module-permissions">

        <div class="mp-filter">
            <label class="mp-filter-label">{{ __('Department') }}</label>
            <select name="department" class="mp-input" onchange="this.form.submit()">
                <option value="">{{ __('All departments') }}</option>
                @foreach ($moduleDepartments as $id => $name)
                    <option value="{{ $id }}" @selected($moduleDepartment === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mp-filter mp-filter-grow">
            <label class="mp-filter-label">{{ __('Search module') }}</label>
            <input type="text" name="q" value="{{ $moduleSearch }}" class="mp-input"
                   placeholder="{{ __('e.g. Survey, Billing, Print…') }}">
        </div>

        <div class="mp-filter-actions">
            <button type="submit" class="ce-pill">{{ __('Filter') }}</button>
            @if ($moduleSearch !== '' || $moduleDepartment !== '')
                <a href="{{ route('configurable-entries.index', ['tab' => 'module-permissions']) }}" class="mp-clear">{{ __('Clear') }}</a>
            @endif
        </div>
    </form>

    <div class="ce-table-wrapper">
        @if ($modules->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500">{{ __('No module matches these filters.') }}</p>
            </div>
        @else
            <table class="ce-table mp-table">
                <thead>
                    <tr>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Department') }}</th>
                        <th class="mp-num">{{ __('Users') }}</th>
                        <th class="mp-num">{{ __('Create') }}</th>
                        <th class="mp-num">{{ __('Edit') }}</th>
                        <th class="mp-num">{{ __('Delete') }}</th>
                        <th class="mp-num">{{ __('Print') }}</th>
                        <th class="mp-num">{{ __('Export') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $module)
                        <tr class="{{ $module['users_total'] === 0 ? 'mp-row-unused' : '' }}">
                            <td>
                                <span class="mp-name">{{ $module['name'] }}</span>
                                <span class="mp-meta">
                                    @if ($module['user_type'])
                                        <span class="ce-pill blue">{{ $module['user_type'] }}</span>
                                    @endif
                                    @if (!$module['registered'])
                                        <span class="ce-pill red" title="{{ __('Granted to users but missing from the User Roles registry') }}">
                                            {{ __('not in registry') }}
                                        </span>
                                    @elseif (!$module['is_active'])
                                        <span class="ce-pill">{{ __('inactive') }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="mp-dept">{{ $module['department'] ?? '—' }}</td>
                            <td class="mp-num">
                                @if ($module['users_total'] > 0)
                                    <strong>{{ number_format($module['users_total']) }}</strong>
                                @else
                                    <span class="mp-zero">0</span>
                                @endif
                            </td>
                            @foreach (['n_create', 'n_edit', 'n_delete', 'n_print', 'n_export'] as $key)
                                <td class="mp-num">
                                    @if ($module[$key] > 0)
                                        <span class="mp-count {{ $key === 'n_delete' ? 'is-danger' : '' }}">{{ number_format($module[$key]) }}</span>
                                    @else
                                        <span class="mp-zero">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="mp-foot">
        {{ __('Permissions are granted per user on the') }}
        <a href="{{ route('users.index') }}">{{ __('User Account') }}</a>
        {{ __('screen — open a user and use the Module Permissions grid.') }}
    </p>
</div>

<style>
    .mp-posture { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
                  padding: 12px 16px; border-radius: 12px; background: #eef2ff; border: 1px solid #c7d2fe; }
    .mp-posture.is-off { background: #fef2f2; border-color: #fecaca; }
    .mp-posture-main { display: flex; align-items: center; gap: 12px; }
    .mp-posture-icon { width: 22px; height: 22px; color: #4338ca; flex-shrink: 0; }
    .mp-posture.is-off .mp-posture-icon { color: #b91c1c; }
    .mp-posture-title { margin: 0; font-size: 14px; font-weight: 700; color: #312e81; }
    .mp-posture.is-off .mp-posture-title { color: #991b1b; }
    .mp-posture-sub { margin: 2px 0 0; font-size: 12.5px; color: #4b5563; }
    .mp-posture-cmd { font-size: 12px; background: #fff; border: 1px solid #c7d2fe; border-radius: 7px;
                      padding: 5px 9px; color: #4338ca; }

    .mp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)); gap: 10px; }
    .mp-stat { padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
    .mp-stat-value { display: block; font-size: 22px; font-weight: 700; line-height: 1.1; color: #111827; }
    .mp-stat-label { display: block; margin-top: 2px; font-size: 11.5px; color: #6b7280; }
    .mp-stat.is-danger { background: #fef2f2; border-color: #fecaca; }
    .mp-stat.is-danger .mp-stat-value { color: #b91c1c; }
    .mp-stat.is-warn { background: #fffbeb; border-color: #fde68a; }
    .mp-stat.is-warn .mp-stat-value { color: #92400e; }
    .mp-stat.is-muted .mp-stat-value { color: #9ca3af; }

    .mp-alert { display: flex; gap: 11px; padding: 12px 15px; border-radius: 12px;
                background: #fffbeb; border: 1px solid #fde68a; }
    .mp-alert-icon { width: 19px; height: 19px; color: #d97706; flex-shrink: 0; margin-top: 1px; }
    .mp-alert strong { font-size: 13.5px; color: #92400e; }
    .mp-alert p { margin: 3px 0 0; font-size: 12.5px; color: #78350f; }
    .mp-alert a { font-weight: 600; text-decoration: underline; }

    .mp-filters { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;
                  padding: 12px 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; }
    .mp-filter { min-width: 190px; }
    .mp-filter-grow { flex: 1; min-width: 220px; }
    .mp-filter-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase;
                       letter-spacing: .04em; color: #6b7280; margin-bottom: 4px; }
    .mp-input { width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #d1d5db;
                border-radius: 9px; background: #fff; }
    .mp-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .15); }
    .mp-filter-actions { display: flex; align-items: center; gap: 10px; }
    .mp-clear { font-size: 12.5px; color: #6b7280; text-decoration: underline; }

    .mp-table .mp-num { text-align: center; width: 76px; }
    .mp-name { display: block; font-weight: 600; color: #111827; }
    .mp-meta { display: inline-flex; gap: 5px; margin-top: 3px; }
    .mp-dept { color: #6b7280; font-size: 13px; }
    .mp-row-unused { background: #fcfcfd; }
    .mp-row-unused .mp-name { color: #9ca3af; }
    .mp-count { display: inline-block; min-width: 26px; padding: 2px 7px; border-radius: 99px;
                background: #eef2ff; color: #4338ca; font-size: 12px; font-weight: 600; }
    .mp-count.is-danger { background: #fee2e2; color: #b91c1c; }
    .mp-zero { color: #d1d5db; }

    .mp-foot { margin: 0; font-size: 12.5px; color: #6b7280; }
    .mp-foot a { font-weight: 600; color: #4338ca; }
</style>
