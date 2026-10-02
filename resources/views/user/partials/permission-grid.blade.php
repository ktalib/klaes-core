{{--
 | Module × action permission grid. Shared by the Create User and Edit User modals.
 |
 | Replaces two things that used to sit in both files, duplicated line for line:
 |
 |   - "Step 4: Select Available Roles", a flat grid of 174 checkboxes answering only
 |     "may this user see this module".
 |   - "User Actions (user_actions)", four checkboxes — create / view / update / delete — that
 |     applied to the WHOLE SYSTEM rather than to a module, and that nothing ever read. One
 |     user out of 597 had a value in it.
 |
 | Now one row per module with a grant per action, so "may edit Survey Records but not delete
 | them, and may print in Deeds but not create" is expressible for the first time.
 |
 | Posts:
 |   user_role[]                         module names — unchanged, still written to assign_role
 |   module_perm[<module name>][]        actions granted on that module
 |
 | Expects:
 |   $userRoles      UserRole rows (id, name, department_id, level, user_type)
 |   $departments    id => name, for the department filter
 |   $selectedRoles  array of module names already held (edit) or [] (create)
 |   $grants         [module name => [action => bool]] already held, or []
 |   $canGrantDelete whether the signed-in editor may hand out delete at all
 --}}

@php
    $actions = \App\Models\ModulePermission::ACTIONS;

    $actionMeta = [
        'view' => ['label' => 'View', 'icon' => 'eye', 'hint' => 'See the module and open its records'],
        'create' => ['label' => 'Create', 'icon' => 'plus', 'hint' => 'Add new records'],
        'edit' => ['label' => 'Edit', 'icon' => 'pencil', 'hint' => 'Change existing records'],
        'approve' => ['label' => 'Approve', 'icon' => 'check-circle', 'hint' => 'Approve or reject — offered only on modules that have an approval step'],
        'delete' => ['label' => 'Delete', 'icon' => 'trash-2', 'hint' => 'Remove records — grant sparingly'],
        'print' => ['label' => 'Print', 'icon' => 'printer', 'hint' => 'Print labels, sheets and certificates'],
        'export' => ['label' => 'Export', 'icon' => 'download', 'hint' => 'Download CSV, Excel and PDF'],
    ];

    $selectedRoles = collect($selectedRoles ?? [])
        ->map(fn ($r) => \App\Support\Permissions\ModuleName::normalize($r))
        ->filter()
        ->all();

    $grants = collect($grants ?? [])
        ->mapWithKeys(fn ($v, $k) => [\App\Support\Permissions\ModuleName::normalize($k) => $v])
        ->all();

    $canGrantDelete = $canGrantDelete ?? false;

    $deptNames = collect($departments ?? [])->toArray();

    /*
     | Rows for the grid. Built from the registry, then extended with anything the user holds
     | that the registry has lost track of.
     |
     | That second step is a bug fix, not a nicety. 22 granted module names have no user_roles
     | row — 8 of them still checked by the sidebar, across 38 users. Because the old grid was
     | rendered purely from user_roles, those modules had no checkbox, so simply OPENING a
     | user and pressing Save silently stripped their access. Rendering them as ticked,
     | unregistered rows means a save round-trips instead of destroying.
     */
    $rows = [];

    foreach ($userRoles as $role) {
        $key = \App\Support\Permissions\ModuleName::normalize($role->name);

        if ($key === '' || isset($rows[$key])) {
            continue;
        }

        $rows[$key] = [
            'name' => $role->name,
            'department_id' => $role->department_id,
            'department' => $deptNames[$role->department_id] ?? null,
            'user_type' => $role->user_type,
            'level' => $role->level,
            'registered' => true,
        ];
    }

    foreach ($selectedRoles as $key) {
        if (isset($rows[$key]) || \App\Support\Permissions\ModuleName::isSuperAdminGrant($key)) {
            continue;
        }

        $rows[$key] = [
            'name' => $key,
            'department_id' => null,
            'department' => null,
            'user_type' => null,
            'level' => null,
            'registered' => false,
        ];
    }

    // Shape for Alpine: filtering, counting and the bulk buttons all run client-side.
    $gridRows = [];

    foreach ($rows as $key => $row) {
        $held = in_array($key, $selectedRoles, true);
        $rowGrants = $grants[$key] ?? [];

        $gridRows[] = [
            'key' => $key,
            'name' => $row['name'],
            'deptId' => (string) ($row['department_id'] ?? ''),
            'dept' => $row['department'] ?? ($row['registered'] ? 'No department' : 'Unregistered'),
            'userType' => $row['user_type'] ?? '',
            'level' => $row['level'] ?? '',
            'registered' => $row['registered'],
            'held' => $held,
            // Approving is meaningless on most modules, so the column is live only where the
            // module actually has an approve/reject route (or says so in its name).
            'approvable' => \App\Support\Permissions\ModuleRoutes::isApprovable($row['name']),
            // On create nothing is held, so nothing is pre-ticked. On edit, a held module with
            // no stored grant row predates the backfill; show it the way the gate reads it.
            'actions' => collect($actions)->mapWithKeys(fn ($a) => [
                $a => $a === 'view'
                    ? $held
                    : (bool) ($rowGrants[$a] ?? ($held && $a !== 'delete')),
            ])->all(),
            // Kept out of 'actions' so the checkbox state stays a plain boolean the bulk
            // helpers can set without having to know which columns apply to which row.
            'lockedActions' => \App\Support\Permissions\ModuleRoutes::isApprovable($row['name'])
                ? []
                : ['approve'],
        ];
    }

    /*
     | Department options come from the FULL department list, not from the modules on screen.
     |
     | Step 1 above offers 21 departments; only 16 have any module. Deriving the options from
     | the rows meant picking one of the other 5 in Step 1 left this select with no matching
     | option, so it rendered blank while the filter was actually applied — the control said
     | one thing and the grid did another.
     */
    $departmentOptions = collect($departments ?? [])
        ->map(fn ($name, $id) => ['id' => (string) $id, 'label' => (string) $name])
        ->values()
        ->all();

    // Buckets the department list has no id for: registry rows with no department, and
    // modules a user holds that were never registered at all.
    foreach ($gridRows as $row) {
        if ($row['deptId'] === '' && !collect($departmentOptions)->contains(fn ($d) => $d['label'] === $row['dept'])) {
            $departmentOptions[] = ['id' => '', 'label' => $row['dept']];
        }
    }

    usort($departmentOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

    usort($gridRows, function ($a, $b) {
        return [$a['dept'], $a['name']] <=> [$b['dept'], $b['name']];
    });
@endphp

{{-- x-effect keeps this grid's department filter in step with "Step 1: Select Department"
     above it. Alpine chains scopes, so `selectedDept` here resolves to the parent modal's
     property; the typeof guard is what lets the partial still stand on its own, since a bare
     undefined identifier in an Alpine expression throws. --}}
<div class="pg" x-data="permissionGrid(@js($gridRows), @js($canGrantDelete), @js($departmentOptions))"
     x-effect="if (typeof selectedDept !== 'undefined' && String(selectedDept ?? '') !== deptFilter) { deptFilter = String(selectedDept ?? ''); }">

    {{-- Header: what this is, and how much is granted --}}
    <div class="pg-head">
        <div>
            <h4 class="pg-title">
                <i data-lucide="shield-check" class="pg-title-icon"></i>
                {{ __('Module Permissions') }}
            </h4>
            <p class="pg-sub">
                {{ __('Tick a module to grant access, then choose what the user may do in it.') }}
            </p>
        </div>
        <div class="pg-counter" :class="{ 'is-empty': grantedCount === 0 }">
            <span x-text="grantedCount"></span>
            <small x-text="grantedCount === 1 ? 'module' : 'modules'"></small>
        </div>
    </div>

    {{-- Filters. Department first, because that is how the ministry is organised and how an
         administrator thinks about who needs what. --}}
    <div class="pg-filters">
        <div class="pg-field">
            <label class="pg-label">{{ __('Department') }}</label>
            {{-- Changing it here changes Step 1 too — one department, chosen in either place.
                 Guarded so the grid still works with no parent to write back to. --}}
            <select x-model="deptFilter" class="pg-input"
                    @change="if (typeof selectedDept !== 'undefined') {
                                 selectedDept = deptFilter;
                                 selectedDeptName = $event.target.selectedOptions[0].text;
                                 showAll = !deptFilter;
                             }">
                <option value="">{{ __('All departments') }}</option>
                <template x-for="d in departmentOptions" :key="d.id">
                    <option :value="d.id" x-text="d.label"></option>
                </template>
            </select>
        </div>

        <div class="pg-field">
            <label class="pg-label">{{ __('Search module') }}</label>
            <div class="pg-search">
                <i data-lucide="search" class="pg-search-icon"></i>
                <input type="text" x-model="query" class="pg-input pg-input-search"
                       placeholder="{{ __('e.g. Survey, Billing, Print…') }}">
            </div>
        </div>

        <div class="pg-field pg-field-narrow">
            <label class="pg-label">{{ __('Show') }}</label>
            <select x-model="showFilter" class="pg-input">
                <option value="all">{{ __('All modules') }}</option>
                <option value="granted">{{ __('Granted only') }}</option>
                <option value="ungranted">{{ __('Not granted') }}</option>
            </select>
        </div>
    </div>

    {{-- Bulk actions. Every one of them applies to the VISIBLE rows only, so a department
         filter plus "grant view+create+edit+print" is a two-click setup for a new officer. --}}
    <div class="pg-bulk">
        <span class="pg-bulk-label">
            <span x-text="visibleRows.length"></span> {{ __('shown') }}
        </span>
        <div class="pg-bulk-actions">
            <button type="button" class="pg-btn pg-btn-grant" @click="grantVisible(['view'])">
                <i data-lucide="check" class="pg-btn-icon"></i>{{ __('Grant view') }}
            </button>
            <button type="button" class="pg-btn pg-btn-grant" @click="grantVisible(['view','create','edit','print','export'])">
                <i data-lucide="check-check" class="pg-btn-icon"></i>{{ __('Grant all but delete') }}
            </button>
            <button type="button" class="pg-btn pg-btn-clear" @click="clearVisible()">
                <i data-lucide="x" class="pg-btn-icon"></i>{{ __('Clear shown') }}
            </button>
        </div>
    </div>

    {{-- The grid --}}
    <div class="pg-table-wrap">
        <table class="pg-table">
            <thead>
                <tr>
                    <th class="pg-th-module">{{ __('Module') }}</th>
                    @foreach ($actions as $action)
                        <th class="pg-th-action" title="{{ $actionMeta[$action]['hint'] }}">
                            <span class="pg-th-action-inner">
                                <i data-lucide="{{ $actionMeta[$action]['icon'] }}" class="pg-th-icon"></i>
                                {{ __($actionMeta[$action]['label']) }}
                            </span>
                            <button type="button" class="pg-col-toggle"
                                    @click="toggleColumn('{{ $action }}')"
                                    title="{{ __('Toggle this column for every shown module') }}">
                                {{ __('all') }}
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <template x-for="row in visibleRows" :key="row.key">
                    <tr :class="{ 'pg-row-granted': row.actions.view, 'pg-row-unregistered': !row.registered }">
                        <td class="pg-td-module">
                            <label class="pg-module">
                                <span class="pg-module-name" x-text="row.name"></span>
                                <span class="pg-module-meta">
                                    <span x-text="row.dept"></span>
                                    <template x-if="row.userType">
                                        <span class="pg-chip" x-text="row.userType"></span>
                                    </template>
                                    <template x-if="!row.registered">
                                        <span class="pg-chip pg-chip-warn"
                                              title="{{ __('Held by this user but missing from the User Roles registry. Kept so saving does not remove it.') }}">
                                            {{ __('unregistered') }}
                                        </span>
                                    </template>
                                </span>
                            </label>

                            {{-- assign_role is still the source of sidebar visibility, so a
                                 granted module keeps posting its name exactly as before. --}}
                            <template x-if="row.actions.view">
                                <input type="hidden" name="user_role[]" :value="row.name">
                            </template>
                        </td>

                        @foreach ($actions as $action)
                            <td class="pg-td-action">
                                {{-- A column that does not apply to this module shows a dash rather
                                     than a dead checkbox: an unticked box reads as "not granted",
                                     which is a different statement from "there is nothing here to
                                     approve". Keeps the table rectangular either way. --}}
                                <template x-if="locked(row, '{{ $action }}')">
                                    <span class="pg-na" title="{{ __('This module has no :action step.', ['action' => strtolower($actionMeta[$action]['label'])]) }}">&mdash;</span>
                                </template>

                                <template x-if="!locked(row, '{{ $action }}')">
                                    <label class="pg-check"
                                           :class="{
                                               'is-locked': {{ $action === 'delete' ? 'true' : 'false' }} && !canGrantDelete,
                                               'is-disabled': '{{ $action }}' !== 'view' && !row.actions.view
                                           }">
                                        <input type="checkbox"
                                               :name="'module_perm[' + row.name + '][]'"
                                               value="{{ $action }}"
                                               x-model="row.actions.{{ $action }}"
                                               @change="onToggle(row, '{{ $action }}')"
                                               :disabled="('{{ $action }}' !== 'view' && !row.actions.view)
                                                          || ({{ $action === 'delete' ? 'true' : 'false' }} && !canGrantDelete)">
                                        <span class="pg-box pg-box-{{ $action }}"></span>
                                    </label>
                                </template>
                            </td>
                        @endforeach
                    </tr>
                </template>

                <tr x-show="visibleRows.length === 0">
                    <td colspan="{{ count($actions) + 1 }}" class="pg-empty">
                        <span x-show="deptFilter !== '' && query === '' && showFilter === 'all'">
                            {{ __('No modules are registered for this department.') }}
                        </span>
                        <span x-show="!(deptFilter !== '' && query === '' && showFilter === 'all')">
                            {{ __('No module matches these filters.') }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- An emptied grid has to be saveable: with no checkbox ticked the browser posts no
         user_role key at all, which the controller would read as "unchanged" rather than
         "none". The blank value makes "no modules" an explicit instruction. --}}
    <input type="hidden" name="user_role[]" value="">

    @unless ($canGrantDelete)
        <p class="pg-note">
            <i data-lucide="lock" class="pg-note-icon"></i>
            {{ __('Only a Super Admin can grant Delete. Without that, an account able to edit users could grant itself deletion of every record in the ministry.') }}
        </p>
    @endunless
</div>

{{-- Inline, not @push: these modals are fetched by customModal and injected as bare
     fragments with no layout, so a pushed stack would never be rendered. Re-injecting on
     each open is harmless — the function definition simply replaces itself. --}}
<script>
            function permissionGrid(rows, canGrantDelete, departments) {
                return {
                    rows: rows,
                    canGrantDelete: canGrantDelete,
                    // Supplied by the blade from the full department list. Left optional so the
                    // component still stands up when driven directly, as the tests do.
                    departments: departments || null,
                    deptFilter: '',
                    query: '',
                    showFilter: 'all',

                    get departmentOptions() {
                        if (this.departments && this.departments.length) {
                            return this.departments;
                        }

                        const seen = new Map();
                        this.rows.forEach(r => {
                            if (!seen.has(r.deptId)) {
                                seen.set(r.deptId, { id: r.deptId, label: r.dept });
                            }
                        });
                        return [...seen.values()].sort((a, b) => a.label.localeCompare(b.label));
                    },

                    get visibleRows() {
                        const q = this.query.trim().toLowerCase();

                        return this.rows.filter(row => {
                            if (this.deptFilter !== '' && row.deptId !== this.deptFilter) return false;
                            if (q && !row.name.toLowerCase().includes(q)) return false;
                            if (this.showFilter === 'granted' && !row.actions.view) return false;
                            if (this.showFilter === 'ungranted' && row.actions.view) return false;
                            return true;
                        });
                    },

                    get grantedCount() {
                        return this.rows.filter(r => r.actions.view).length;
                    },

                    /**
                     * Does this column simply not apply to this module?
                     *
                     * Distinct from "not granted": Approve is only meaningful where the module
                     * has an approval step, and the server drops any approve posted for a
                     * module that has none, so the grid must not pretend otherwise.
                     */
                    locked(row, action) {
                        return (row.lockedActions || []).includes(action);
                    },

                    /*
                     | An action on a module the user cannot see is meaningless, and the gate
                     | refuses it anyway (ModulePermissions::allows returns false when view is
                     | absent). So untick view and the rest go with it, rather than leaving
                     | grants stored that silently never apply.
                     */
                    onToggle(row, action) {
                        if (action === 'view' && !row.actions.view) {
                            Object.keys(row.actions).forEach(a => { row.actions[a] = false; });
                            return;
                        }

                        if (action !== 'view' && row.actions[action]) {
                            row.actions.view = true;
                        }
                    },

                    grantVisible(actions) {
                        this.visibleRows.forEach(row => {
                            actions.forEach(a => {
                                if (a === 'delete' && !this.canGrantDelete) return;
                                if (this.locked(row, a)) return;
                                row.actions[a] = true;
                            });
                            if (actions.length) row.actions.view = true;
                        });
                    },

                    clearVisible() {
                        this.visibleRows.forEach(row => {
                            Object.keys(row.actions).forEach(a => { row.actions[a] = false; });
                        });
                    },

                    toggleColumn(action) {
                        if (action === 'delete' && !this.canGrantDelete) return;

                        const visible = this.visibleRows;
                        // Only rows the column can actually apply to.
                        const eligible = (action === 'view' ? visible : visible.filter(r => r.actions.view))
                            .filter(r => !this.locked(r, action));
                        const turnOn = eligible.some(r => !r.actions[action]);

                        eligible.forEach(row => {
                            row.actions[action] = turnOn;
                            if (turnOn && action !== 'view') row.actions.view = true;
                        });
                    },
                };
            }
</script>

<style>
            .pg { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
            .pg-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
                       padding: 16px 18px; background: linear-gradient(180deg, #f8fafc, #fff); border-bottom: 1px solid #e2e8f0; }
            .pg-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; }
            .pg-title-icon { width: 18px; height: 18px; color: #4f46e5; }
            .pg-sub { margin: 4px 0 0; font-size: 12.5px; color: #64748b; }
            .pg-counter { flex-shrink: 0; text-align: center; min-width: 74px; padding: 8px 12px; border-radius: 10px;
                          background: #eef2ff; border: 1px solid #c7d2fe; }
            .pg-counter span { display: block; font-size: 20px; font-weight: 700; line-height: 1; color: #4338ca; }
            .pg-counter small { font-size: 11px; color: #6366f1; }
            .pg-counter.is-empty { background: #f8fafc; border-color: #e2e8f0; }
            .pg-counter.is-empty span { color: #94a3b8; }
            .pg-counter.is-empty small { color: #94a3b8; }

            .pg-filters { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(200px, 1.4fr) minmax(150px, .8fr);
                          gap: 12px; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
            .pg-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase;
                        letter-spacing: .04em; color: #64748b; margin-bottom: 5px; }
            .pg-input { width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #d1d9e2;
                        border-radius: 9px; background: #fff; color: #0f172a; }
            .pg-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
            .pg-search { position: relative; }
            .pg-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
                              width: 15px; height: 15px; color: #94a3b8; pointer-events: none; }
            .pg-input-search { padding-left: 32px; }

            .pg-bulk { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
                       padding: 10px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
            .pg-bulk-label { font-size: 12px; font-weight: 600; color: #64748b; }
            .pg-bulk-actions { display: flex; flex-wrap: wrap; gap: 7px; }
            .pg-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; font-size: 12px;
                      font-weight: 600; border-radius: 8px; border: 1px solid transparent; cursor: pointer; }
            .pg-btn-icon { width: 13px; height: 13px; }
            .pg-btn-grant { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
            .pg-btn-grant:hover { background: #d1fae5; }
            .pg-btn-clear { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
            .pg-btn-clear:hover { background: #fee2e2; }

            .pg-table-wrap { max-height: 46vh; overflow: auto; }
            .pg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
            .pg-table thead th { position: sticky; top: 0; z-index: 2; background: #f1f5f9; padding: 9px 10px;
                                 font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
                                 color: #475569; border-bottom: 1px solid #cbd5e1; white-space: nowrap; }
            .pg-th-module { text-align: left; min-width: 260px; }
            .pg-th-action { text-align: center; width: 78px; }
            .pg-th-action-inner { display: flex; align-items: center; justify-content: center; gap: 4px; }
            .pg-th-icon { width: 12px; height: 12px; }
            .pg-col-toggle { display: block; margin: 3px auto 0; padding: 1px 6px; font-size: 9.5px; font-weight: 700;
                             text-transform: uppercase; color: #6366f1; background: #fff; border: 1px solid #c7d2fe;
                             border-radius: 5px; cursor: pointer; }
            .pg-col-toggle:hover { background: #eef2ff; }

            .pg-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
            .pg-td-module { text-align: left; }
            .pg-td-action { text-align: center; }
            .pg-row-granted { background: #f8fdfb; }
            .pg-row-unregistered .pg-module-name { color: #92400e; }

            .pg-module { display: block; cursor: default; }
            .pg-module-name { display: block; font-weight: 600; color: #0f172a; line-height: 1.3; }
            .pg-module-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; margin-top: 2px;
                              font-size: 11px; color: #94a3b8; }
            .pg-chip { padding: 1px 6px; font-size: 10px; font-weight: 600; border-radius: 99px;
                       background: #eff6ff; color: #1d4ed8; }
            .pg-chip-warn { background: #fef3c7; color: #92400e; cursor: help; }

            .pg-check { display: inline-flex; cursor: pointer; }
            .pg-check input { position: absolute; opacity: 0; width: 0; height: 0; }
            .pg-box { display: block; width: 18px; height: 18px; border-radius: 5px; border: 1.5px solid #cbd5e1;
                      background: #fff; position: relative; transition: background .12s, border-color .12s; }
            .pg-box::after { content: ''; position: absolute; left: 5px; top: 1px; width: 5px; height: 10px;
                             border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); opacity: 0; }
            .pg-check input:checked + .pg-box { background: #4f46e5; border-color: #4f46e5; }
            .pg-check input:checked + .pg-box::after { opacity: 1; }
            .pg-check input:checked + .pg-box-delete { background: #dc2626; border-color: #dc2626; }
            .pg-check input:checked + .pg-box-print { background: #0f766e; border-color: #0f766e; }
            .pg-check input:checked + .pg-box-export { background: #b45309; border-color: #b45309; }
            .pg-check input:focus-visible + .pg-box { box-shadow: 0 0 0 3px rgba(99, 102, 241, .25); }
            .pg-check.is-disabled, .pg-check.is-locked { cursor: not-allowed; }
            .pg-check.is-disabled .pg-box, .pg-check.is-locked .pg-box { background: #f1f5f9; border-color: #e2e8f0; }

            .pg-na { color: #cbd5e1; font-size: 13px; cursor: help; }
            .pg-empty { padding: 28px; text-align: center; color: #94a3b8; font-size: 13px; }
            .pg-note { display: flex; align-items: center; gap: 7px; margin: 0; padding: 10px 18px;
                       font-size: 12px; color: #92400e; background: #fffbeb; border-top: 1px solid #fde68a; }
            .pg-note-icon { width: 14px; height: 14px; flex-shrink: 0; }

            @media (max-width: 900px) {
                .pg-filters { grid-template-columns: 1fr; }
                .pg-th-action { width: 56px; }
            }
</style>
