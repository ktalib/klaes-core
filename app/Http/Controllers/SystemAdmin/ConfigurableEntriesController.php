<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentTypeController;
use App\Services\ConfigurableEntries\InstrumentTypes as ApplicationController;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\Department;
use App\Models\InstrumentWorkflow\InstrumentFeeMapping;
use App\Models\InstrumentWorkflow\WorkflowActionRole;
use App\Models\NewInstrumentType;
use App\Models\RevenueItem;
use App\Models\UserActivityLog;
use App\Models\UserRole;
use App\Support\Permissions\ModuleName;
use App\Services\InstrumentRegistrationService;
use App\Services\InstrumentWorkflow\InstrumentAccessGuard;
use App\Services\InstrumentWorkflow\InstrumentWorkflowService;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use App\Services\InstrumentWorkflow\WorkflowPipeline;
use App\Services\ScheduleFileNumberService;
use App\Support\AlaesLogos;
use App\Support\DeedsPipelineGates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * System Admin → Configurable Entries.
 *
 *   Revenue Items          revenue_items + the per-instrument-type fee mapping
 *   FileNo Prefix/SerialNo file_schedules / schedule_file_formats (ScheduleFileNumberService) -- tab withdrawn, see HIDDEN_TABS
 *   Serial Initialization  the one-time, then locked, starting serial for each file-number
 *                          generator: mls_serial_control (Land, per prefix and year),
 *                          dciv_serial_control (Deeds, per prefix and year) and
 *                          gkn_serial_control (Survey, per prefix) — see SERIAL_REGISTERS
 *   Instrument Volume      instrument_number_vaults: per register (counters, page_limit, Unified Register flag), and per type
 *                          through InstrumentTypeController as the Instrument Types pop-up does (closed while shared mode is live)
 *   Workflow Pipeline      instrument_workflow_steps + instrument_workflow_action_roles -- tab withdrawn, see HIDDEN_TABS
 *   Valuation - Consent -  how hard each hand-off of the Deeds pipeline is enforced
 *   Registration           (App\Support\DeedsPipelineGates, read by DeedsPipelineStatus)
 *   Bill Formulas          billing_types + billing_type_items (formulas with + - and brackets)
 *   Land Rates             land-use rates, district rates with their Tax Zone, the Tax Zones
 *   Logos                  every logo the system shows or prints (config/alaes_logos.php and
 *                          the alaes_logos overrides), resolved through App\Support\AlaesLogos
 *   Map Types              sdi_map_types (App\Http\Controllers\Sdi\MapTypeController), linked as a tab
 *   Departments, User Roles the existing pages (departments.index, user-roles.index), linked as tabs
 *   Cadastral              the official fee sheet's rates, the S.L.N. No. 3 of 1983 area schedule,
 *                          transport bands, job/ITS number formats, file prefixes, source
 *                          registries (cadastral_settings & co., read through
 *                          App\Services\Cadastral\CadastralSettings) and who holds each job post
 *                          (cadastral_officers)
 *
 * Forms submit by AJAX (instrument_workflow partials/ajax) and answer through
 * RunsWorkflowActions. Access: the 'System Settings' role, or Super Admin.
 */
class ConfigurableEntriesController extends Controller
{
    use RunsWorkflowActions;

    /**
     * The tab bar, in order.
     *
     * `accent` / `tint` give each tab its own colour: the tint is the resting
     * background, the accent the label, icon and the solid fill once the tab is
     * selected. They walk the colour wheel in tab order so no two neighbours
     * read as the same hue, and every accent is a -700 shade so the label
     * clears 4.5:1 both on its tint and reversed out in white when active.
     *
     * Raw hex, not Tailwind classes: layouts/app.blade.php serves Tailwind
     * 2.2 from a CDN, whose default palette has no orange, amber, lime, teal,
     * cyan, violet, fuchsia or rose to name.
     */
    public const TABS = [
        'revenue' => ['label' => 'Revenue Items', 'icon' => 'banknote', 'accent' => '#1d4ed8', 'tint' => '#eff6ff'],
        'billing' => ['label' => 'Bill Formulas', 'icon' => 'sigma', 'accent' => '#6d28d9', 'tint' => '#f5f3ff'],
        'departments' => ['label' => 'Departments', 'icon' => 'building', 'accent' => '#a21caf', 'tint' => '#fdf4ff'],
        'units' => ['label' => 'Units', 'icon' => 'network', 'accent' => '#7e22ce', 'tint' => '#faf5ff'],
        'user-roles' => ['label' => 'User Roles', 'icon' => 'shield', 'accent' => '#be123c', 'tint' => '#fff1f2'],
        'module-permissions' => ['label' => 'Module Permissions', 'icon' => 'shield-check', 'accent' => '#4338ca', 'tint' => '#eef2ff'],
        'fileno' => ['label' => 'FileNo Prefix & SerialNo', 'icon' => 'hash', 'accent' => '#c2410c', 'tint' => '#fff7ed'],
        'serials' => ['label' => 'Serial Initialization', 'icon' => 'list-ordered', 'accent' => '#047857', 'tint' => '#ecfdf5'],
        'volume' => ['label' => 'Deeds Instrument Volume', 'icon' => 'book-marked', 'accent' => '#b45309', 'tint' => '#fffbeb'],
        'pipeline' => ['label' => 'Instrument Registration Pipeline', 'icon' => 'git-commit-horizontal', 'accent' => '#4d7c0f', 'tint' => '#f7fee7'],
        'deeds-workflow' => ['label' => 'Valuation - Consent - Registration', 'icon' => 'workflow', 'accent' => '#4d7c0f', 'tint' => '#f7fee7'],
        'land-rates' => ['label' => 'Land Rates (G.Rent & LUC)', 'icon' => 'map', 'accent' => '#0f766e', 'tint' => '#f0fdfa'],
        'logos' => ['label' => 'Logos', 'icon' => 'image', 'accent' => '#4338ca', 'tint' => '#eef2ff'],
        'signatories' => ['label' => 'Signatories', 'icon' => 'pen-line', 'accent' => '#9d174d', 'tint' => '#fdf2f8'],
        // Green like the fee sheet it configures (green-700 on green-50).
        'cadastral' => ['label' => 'Cadastral', 'icon' => 'compass', 'accent' => '#15803d', 'tint' => '#f0fdf4'],
        'map-types' => ['label' => 'Map Types', 'icon' => 'map-pin', 'accent' => '#0e7490', 'tint' => '#ecfeff', 'route' => 'sdi.admin.map-types.index'],
    ];

    /**
     * Tabs that still exist but are no longer offered.
     *
     * Their data method, their routes and their partial are all untouched: a tab
     * comes back by taking its key out of this list, and nothing that depends on
     * what it edits -- ScheduleFileNumberService, the instrument workflow -- is
     * affected either way. They are listed here rather than deleted because which
     * screens the department is using is not the same question as whether the code
     * behind them is still right.
     *
     *   fileno    withdrawn on request.
     *   pipeline  withdrawn in favour of 'deeds-workflow' below, which configures
     *             Valuation -> Consent -> Registration, the pipeline Deeds runs.
     */
    public const HIDDEN_TABS = ['pipeline', 'fileno'];

    /**
     * Document signatories the Signatories tab always offers.
     *
     * Created on first view so their name and signature fields are simply present to be
     * filled in, and refused deletion so the ones the printed documents expect cannot be
     * removed by accident. Further signatories -- a Surveyor-General, a
     * Director of Lands -- are added from the tab and may be removed freely.
     */
    public const DEFAULT_SIGNATORIES = ['Honourable Commissioner', 'Executive Governor', 'Permanent Secretary'];

    /**
     * The hand-offs of Valuation -> Consent -> Print -> Registration, in order.
     *
     * `guards` is the action the gate refuses, `where` the screen it is refused
     * on, so the tab names a real screen rather than an internal gate key.
     * The modes themselves, and where a chosen mode is stored, are
     * App\Support\DeedsPipelineGates.
     */
    public const DEEDS_GATES = [
        'valuation_before_consent' => [
            'label' => 'Valuation before Consent',
            'guards' => 'Capturing a Consent application against a file that has no Valuation Report',
            'where' => 'Deeds -> Consent Applications',
        ],
        'consent_before_registration' => [
            'label' => 'Consent before Registration',
            'guards' => 'Registering a consent-gated instrument with no consent on the file',
            'where' => 'Deeds -> Instrument Capture',
        ],
        'consent_print_before_registration' => [
            'label' => 'Printed Consent before Registration',
            'guards' => 'Registering against a consent that was captured but never printed',
            'where' => 'Deeds -> Instrument Capture',
        ],
    ];

    /**
     * The counters that are set once by hand and then locked — the serial the
     * paper register had reached before KLAES started numbering, so the first
     * file number the generator mints continues after it instead of re-issuing
     * a number that already exists on a file in the registry.
     *
     * Land keeps one counter per commissioning prefix (the `prefix` table) and
     * per year; Deeds and Survey keep one per fixed prefix, Deeds per year and
     * Survey once for good. `keys` null means "read them from the prefix table".
     * One table and one action serve all three because the rule is identical:
     * initialize once, lock immediately, never edit through a screen again.
     *
     * @var array<string, array>
     */
    public const SERIAL_REGISTERS = [
        'land' => [
            'label' => 'Land — MLS file numbers',
            'where' => 'Lands → Generate New FileNo (MLSFileNo)',
            'model' => \App\Models\MlsSerialControl::class,
            'table' => 'mls_serial_control',
            'key' => 'land_use',
            'key_label' => 'Land use',
            'keys' => null,
            'yearly' => true,
            'by' => 'name',
            'icon' => 'map',
        ],
        'deeds' => [
            'label' => 'Deeds — DCIV file numbers',
            'where' => 'DCIV Management → Generate New FileNo (DCIVFileNo)',
            'model' => \App\Models\DcivSerialControl::class,
            'table' => 'dciv_serial_control',
            'key' => 'prefix',
            'key_label' => 'Prefix',
            'keys' => ['DCIV', 'LPCC'],
            'yearly' => true,
            'by' => 'id',
            'icon' => 'book-marked',
        ],
        'survey' => [
            'label' => 'Survey — GKN file numbers',
            'where' => 'Survey → Generate New FileNo (GKNFileNo)',
            'model' => \App\Models\GknSerialControl::class,
            'table' => 'gkn_serial_control',
            'key' => 'prefix',
            'key_label' => 'Prefix',
            'keys' => ['GKN', 'LPKN', 'MISCS'],
            'yearly' => false,
            'by' => 'id',
            'icon' => 'ruler',
        ],
    ];

    /** Stored prefixes whose name on the generator differs from the stored one. */
    public const SERIAL_KEY_LABELS = ['MISCS' => 'MISC KN'];

    public const ACTION_LABELS = [
        'application.manage' => ['Raise applications', 'Land → Instrument Registration Applications'],
        'payment.capture' => ['Take application fee payment', 'KLAES REV-M → Instrument Application Fees'],
        'payment.validate' => ['Validate payment (Accounts)', 'KLAES REV-M → Instrument Application Fees'],
        'receipt.issue' => ['Issue counter-signed receipt', 'KLAES REV-M → Instrument Application Fees'],
        'check.lands' => [null, null],
        'check.survey' => [null, null],
        'check.planning' => [null, null],
        'codes.verify' => ['Verify TIN', 'Application page'],
        'registration_fee.manage' => ['Generate the registration fee bill / confirm payment', 'Application page'],
        'bir.send' => ['Send to BIR', 'Deeds → Instrument Capture / Deeds to BIR'],
        'bir.review' => ['Review instruments', 'BIR → BIR to Deeds'],
        'cor.print' => ['Print CoR sticker', 'Application page'],
        'final.sign' => ['Final signing', 'Application page'],
    ];

    /**
     * Tabs whose module is not installed on this deployment.
     *
     * KLAES is being brought up module by module, and this page is the one
     * screen that spans all of them. A tab is listed only when the thing it
     * edits actually exists, so the page never offers a screen that would fail
     * on the first query. Each entry lights up on its own as its module lands -
     * nothing here needs editing to enable one.
     *
     *   land-rates    needs the land charge tables (land_use_rates, luc_parameters,
     *                 and the rate columns on districts)
     *   logos         needs alaes_logos + App\Support\AlaesLogos
     *
     * @return array<string, array>
     */
    private function availableTabs(): array
    {
        $schema = Schema::connection('sqlsrv');

        $requires = [
            'land-rates' => fn () => $schema->hasTable('land_use_rates') && $schema->hasColumn('districts', 'rate_multiplier'),
            'logos' => fn () => $schema->hasTable('alaes_logos') && class_exists(\App\Support\AlaesLogos::class),
            'fileno' => fn () => $schema->hasTable('schedule_file_formats'),
            // Whichever of the three counter tables exist; the tab lists only those.
            'serials' => fn () => (bool) array_filter(
                self::SERIAL_REGISTERS,
                fn (array $meta) => $schema->hasTable($meta['table'])
            ),
            // The tab redirects into the SDI, so it needs the ROUTE, not just the
            // table: the SDI tables survive while its routes are commented out.
            'map-types' => fn () => \Illuminate\Support\Facades\Route::has('sdi.admin.map-types.index'),
            // Shows rows from module_permissions, so it waits for the migration.
            'module-permissions' => fn () => $schema->hasTable('module_permissions'),
            // Reuses signing_officers -- no table of its own.
            'signatories' => fn () => $schema->hasTable('signing_officers'),
            // Waits for 2026_10_01_100000_create_cadastral_settings_tables. Until it
            // runs, CadastralSettings serves config/cadastral_module.php and there is
            // nowhere to save an edit, so a tab would only offer forms that fail.
            'cadastral' => fn () => $schema->hasTable('cadastral_settings'),
        ];

        return array_filter(
            self::TABS,
            fn ($key) => !in_array($key, self::HIDDEN_TABS, true)
                && (!isset($requires[$key]) || ($requires[$key])()),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function index(Request $request, WorkflowPipeline $pipeline, ScheduleFileNumberService $schedules, InstrumentRegistrationService $registration)
    {
        $this->authorizeAdmin();

        $tabs = $this->availableTabs();

        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'revenue';
        if ($route = $tabs[$tab]['route'] ?? null) {
            return redirect()->route($route);
        }
        $data = ['tab' => $tab, 'tabs' => $tabs];

        return view('system_admin.configurable_entries.index', $data + match ($tab) {
            'revenue' => $this->revenueData($request),
            'billing' => $this->billingData(),
            'departments' => $this->departmentsData(),
            'units' => $this->unitsData(),
            'user-roles' => $this->userRolesData(),
            'module-permissions' => $this->modulePermissionsData($request),
            'fileno' => $this->fileNoData($schedules),
            'serials' => $this->serialsData(),
            'volume' => $this->volumeData($registration),
            'pipeline' => $this->pipelineData($pipeline),
            'deeds-workflow' => $this->deedsWorkflowData(),
            'land-rates' => $this->landRatesData($request),
            'logos' => $this->logosData($request),
            'signatories' => $this->signatoriesData(),
            'cadastral' => $this->cadastralData(),
        });
    }

    // ---- Signatories --------------------------------------------------------

    /**
     * The office holders whose name and signature are printed on issued documents.
     *
     * Stored in signing_officers, the table that already holds a name and a signature
     * file, rather than a table of its own. These rows carry NO user_id, which is what
     * separates them from the staff officers on the Land Officer Settings screen -- see
     * App\Models\LandOfficer::scopeDocumentSignatories().
     *
     * The Commissioner, the Governor and the Permanent Secretary are created on first view
     * so their name and signature fields are simply there to fill in; anything beyond them
     * is added from the tab.
     */
    private function signatoriesData(): array
    {
        foreach (self::DEFAULT_SIGNATORIES as $rank) {
            $exists = \App\Models\LandOfficer::documentSignatories()->where('rank', $rank)->exists();

            if (!$exists) {
                \App\Models\LandOfficer::create(['name' => '', 'rank' => $rank, 'user_id' => null]);
            }
        }

        return [
            'signatories' => \App\Models\LandOfficer::documentSignatories()
                ->orderBy('id')
                ->get(['id', 'name', 'rank', 'signature_file']),
            'defaultSignatories' => self::DEFAULT_SIGNATORIES,
        ];
    }

    /** Create or update one document signatory. */
    public function saveSignatory(Request $request, ?int $signatory = null)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'rank' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ], [
            'rank.required' => 'Give the signatory a title, for example "Honourable Commissioner".',
            'signature_file.image' => 'The signature must be an image (PNG or JPG).',
            'signature_file.max' => 'The signature image must be 2 MB or smaller.',
        ]);

        $record = $signatory
            ? \App\Models\LandOfficer::documentSignatories()->findOrFail($signatory)
            : new \App\Models\LandOfficer();

        $record->rank = trim($validated['rank']);
        $record->name = trim((string) ($validated['name'] ?? ''));
        // Never let one of these acquire a user: user_id is what keeps it out of the
        // staff signatory lists.
        $record->user_id = null;

        if ($request->hasFile('signature_file')) {
            $record->signature_file = $request->file('signature_file')
                ->store('document_signatory_signatures', 'public');
        }

        $record->save();

        return back()->with('success', sprintf('%s saved.', $record->rank));
    }

    /** Remove a signatory. The two defaults are kept, since the tab recreates them anyway. */
    public function deleteSignatory(int $signatory)
    {
        $this->authorizeAdmin();

        $record = \App\Models\LandOfficer::documentSignatories()->findOrFail($signatory);

        if (in_array($record->rank, self::DEFAULT_SIGNATORIES, true)) {
            return back()->with('error', sprintf('%s is a standard signatory and cannot be removed. Clear its name and signature instead.', $record->rank));
        }

        $record->delete();

        return back()->with('success', 'Signatory removed.');
    }

    // ---- Departments --------------------------------------------------------

    private function departmentsData(): array
    {
        $all = DB::connection('sqlsrv')->table('departments')->orderBy('name', 'asc')->get();

        return [
            'departments' => $all->whereNull('parent_id')->values(),
            'unitCounts' => $all->whereNotNull('parent_id')->countBy('parent_id'),
        ];
    }

    // ---- Units ----------------------------------------------------------------

    /**
     * A unit is a `departments` row with parent_id set (Registry under Lands,
     * GIS under KANGIS). Listed grouped by department; a unit whose parent is
     * gone sorts last as "No department" so it can be fixed.
     */
    private function unitsData(): array
    {
        $all = DB::connection('sqlsrv')->table('departments')->orderBy('name', 'asc')->get();
        $parents = $all->whereNull('parent_id')->keyBy('id');

        $units = $all->whereNotNull('parent_id')
            ->each(fn ($u) => $u->parent_name = $parents[$u->parent_id]->name ?? null)
            ->sortBy(fn ($u) => ($u->parent_name ?? '~') . '|' . $u->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'units' => $units,
            'departmentCount' => $parents->count(),
        ];
    }

    // ---- User Roles ---------------------------------------------------------

    private function userRolesData(): array
    {
        $userRoles = UserRole::with('department')->orderBy('name', 'asc')->get();

        return [
            'userRoles' => $userRoles,
        ];
    }

    // ---- Module Permissions ---------------------------------------------------

    /**
     * Read-only overview of who holds what, per module.
     *
     * Grants are edited on the user, not here -- one user's whole grid is a coherent decision,
     * whereas a module's column across 537 users is not something anyone edits in one sitting.
     * What this tab is for is the question the user screens cannot answer: which modules carry
     * dangerous permissions, and which are granted to nobody at all.
     *
     * Also surfaces the registry drift the permission work uncovered: module names that users
     * hold but user_roles has no row for. Those are real access -- the sidebar still honours
     * several of them -- so they are listed to be reconciled, not quietly dropped.
     */
    private function modulePermissionsData(Request $request): array
    {
        $conn = DB::connection('sqlsrv');

        $search = trim((string) $request->query('q', ''));
        $departmentId = (string) $request->query('department', '');

        $registry = UserRole::with('department')->orderBy('name')->get();

        // One row per module: how many users hold it, and how many hold each action.
        $counts = $conn->table('module_permissions')
            ->selectRaw('module_name,
                COUNT(*) AS users_total,
                SUM(CAST(can_create AS INT)) AS n_create,
                SUM(CAST(can_edit AS INT))   AS n_edit,
                SUM(CAST(can_delete AS INT)) AS n_delete,
                SUM(CAST(can_print AS INT))  AS n_print,
                SUM(CAST(can_export AS INT)) AS n_export')
            ->groupBy('module_name')
            ->get()
            ->keyBy(fn ($row) => ModuleName::normalize($row->module_name));

        $rows = [];

        foreach ($registry as $role) {
            $key = ModuleName::normalize($role->name);

            if (isset($rows[$key])) {
                continue;
            }

            $rows[$key] = $this->modulePermissionRow($role->name, $counts->get($key), [
                'department' => $role->department?->name,
                'department_id' => (string) ($role->department_id ?? ''),
                'user_type' => $role->user_type,
                'level' => $role->level,
                'is_active' => (bool) $role->is_active,
                'registered' => true,
            ]);
        }

        // Anything granted that the registry does not know about.
        foreach ($counts as $key => $row) {
            if (isset($rows[$key])) {
                continue;
            }

            $rows[$key] = $this->modulePermissionRow($row->module_name, $row, [
                'department' => null,
                'department_id' => '',
                'user_type' => null,
                'level' => null,
                'is_active' => true,
                'registered' => false,
            ]);
        }

        $all = collect($rows)->values();

        $filtered = $all
            ->when($search !== '', fn ($c) => $c->filter(
                fn ($r) => str_contains(mb_strtolower($r['name']), mb_strtolower($search))
            ))
            ->when($departmentId !== '', fn ($c) => $c->filter(
                fn ($r) => $r['department_id'] === $departmentId
            ))
            ->sortBy([['department', SORT_ASC], ['name', SORT_ASC]])
            ->values();

        return [
            'modules' => $filtered,
            'moduleSearch' => $search,
            'moduleDepartment' => $departmentId,
            'moduleDepartments' => $registry
                ->filter(fn ($r) => $r->department)
                ->mapWithKeys(fn ($r) => [(string) $r->department_id => $r->department->name])
                ->sort()
                ->all(),
            'moduleTotals' => [
                'modules' => $all->count(),
                'granted' => $all->where('users_total', '>', 0)->count(),
                'unused' => $all->where('users_total', 0)->count(),
                'unregistered' => $all->where('registered', false)->count(),
                'delete_grants' => $all->sum('n_delete'),
                'grant_rows' => $conn->table('module_permissions')->count(),
            ],
            'moduleStrict' => [
                'routes' => (bool) config('module_permissions.strict_routes'),
                'actions' => (bool) config('module_permissions.strict_actions'),
                'enabled' => (bool) config('module_permissions.enabled', true),
            ],
        ];
    }

    /**
     * @param  object|null  $counts
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function modulePermissionRow(string $name, $counts, array $meta): array
    {
        return array_merge($meta, [
            'name' => $name,
            'users_total' => (int) ($counts->users_total ?? 0),
            'n_create' => (int) ($counts->n_create ?? 0),
            'n_edit' => (int) ($counts->n_edit ?? 0),
            'n_delete' => (int) ($counts->n_delete ?? 0),
            'n_print' => (int) ($counts->n_print ?? 0),
            'n_export' => (int) ($counts->n_export ?? 0),
        ]);
    }

    // ---- Land Rates (Ground Rent & LUC) ---------------------------------------

    private function landRatesData(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $lga = (string) $request->query('lga', '');
        $category = (string) $request->query('category', '');
        $zone = (string) $request->query('zone', '');

        $districts = \App\Models\District::query()
            ->with('taxZone')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('cad_zone', 'like', "%{$search}%")->orWhere('cad_name', 'like', "%{$search}%")))
            ->when($lga !== '', fn ($q) => $q->where('lga', $lga))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($zone === 'none', fn ($q) => $q->whereNull('tax_zone_id'))
            ->when($zone !== '' && $zone !== 'none', fn ($q) => $q->where('tax_zone_id', (int) $zone))
            ->orderBy('lga')->orderBy('name')
            ->paginate(40)->withQueryString();

        $all = \App\Models\District::query()->get(['lga', 'category', 'rate_multiplier', 'luc_land_value', 'luc_building_value', 'tax_zone_id']);
        $taxZones = \App\Models\TaxZone::query()->withCount('districts')->orderBy('sort_order')->orderBy('lga')->orderBy('code')->get();

        return [
            'landUses' => \App\Models\LandUseRate::query()->orderBy('sort_order')->orderBy('name')->get(),
            'districts' => $districts,
            'districtLgas' => $all->pluck('lga')->filter()->unique()->sort()->values(),
            'districtCategories' => $all->pluck('category')->filter()->unique()->sort()->values(),
            'districtStats' => [
                'total' => $all->count(),
                'custom_rate' => $all->filter(fn ($d) => round((float) $d->rate_multiplier, 4) !== 1.0)->count(),
                'with_lv' => $all->whereNotNull('luc_land_value')->count(),
                'with_bv' => $all->whereNotNull('luc_building_value')->count(),
                'with_zone' => $all->whereNotNull('tax_zone_id')->count(),
            ],
            'taxZones' => $taxZones,
            'zone' => $zone,
            'occupancy' => \App\Models\LucParameter::query()->where('kind', \App\Models\LucParameter::KIND_OCCUPANCY)->orderBy('sort_order')->orderBy('label')->get(),
            'propertyCodes' => \App\Models\LucParameter::query()->where('kind', \App\Models\LucParameter::KIND_PROPERTY_CODE)->orderBy('sort_order')->orderBy('label')->get(),
            'search' => $search,
            'lga' => $lga,
            'category' => $category,
        ];
    }

    public function saveLandUse(Request $request, ?int $landUse = null)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('sqlsrv.land_use_rates', 'name')->ignore($landUse)],
            'ground_rent_rate' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->attempt(function () use ($data, $landUse) {
            $row = $landUse ? \App\Models\LandUseRate::query()->findOrFail($landUse) : new \App\Models\LandUseRate();
            $row->fill([
                'name' => $data['name'],
                'ground_rent_rate' => $data['ground_rent_rate'],
                'sort_order' => (int) ($data['sort_order'] ?? ($row->sort_order ?: (int) \App\Models\LandUseRate::query()->max('sort_order') + 1)),
                'is_active' => $landUse ? (bool) ($data['is_active'] ?? false) : true,
                'updated_by_name' => Auth::user()?->name,
            ])->save();

            return "Land use {$row->name} saved at ₦" . number_format((float) $row->ground_rent_rate, 2) . ' per m².';
        }, 'Land use saved.');
    }

    public function saveDistrictRates(Request $request, int $district)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'rate_multiplier' => 'required|numeric|min:0|max:1000',
            'luc_land_value' => 'nullable|numeric|min:0',
            'luc_building_value' => 'nullable|numeric|min:0',
            'tax_zone_id' => 'nullable|integer',
        ]);

        return $this->attempt(function () use ($data, $district) {
            $row = \App\Models\District::query()->findOrFail($district);

            // A Tax Zone is chosen from the zones of the district's own LGA - never typed.
            $zone = !empty($data['tax_zone_id']) ? \App\Models\TaxZone::query()->find($data['tax_zone_id']) : null;
            if (!empty($data['tax_zone_id']) && (!$zone || strcasecmp((string) $zone->lga, (string) $row->lga) !== 0)) {
                throw WorkflowGuardException::because("Choose a Tax Zone of {$row->lga} for {$row->name}.");
            }

            $before = $row->only(['rate_multiplier', 'luc_land_value', 'luc_building_value', 'tax_zone_id']);
            $row->fill([
                'rate_multiplier' => $data['rate_multiplier'],
                'luc_land_value' => $data['luc_land_value'] ?? null,
                'luc_building_value' => $data['luc_building_value'] ?? null,
                'tax_zone_id' => $zone?->id,
                'rates_updated_by' => Auth::user()?->name,
            ]);
            $changed = array_diff_key($row->getDirty(), ['rates_updated_by' => 1, 'updated_at' => 1]);
            $row->save();

            if ($changed) {
                app(\App\Services\AuditService::class)->logAction('District Rates Updated', 'District', $row->id, $before,
                    $row->only(['rate_multiplier', 'luc_land_value', 'luc_building_value', 'tax_zone_id']),
                    "District {$row->name} ({$row->lga}) rates / Tax Zone updated" . ($zone ? " · zone {$zone->code}" : ''));
            }

            return "Rates for {$row->name} saved" . ($zone ? " · Tax Zone {$zone->code}." : '.');
        }, 'District rates saved.');
    }

    public function saveLucParameter(Request $request, ?int $parameter = null)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'kind' => ['required', Rule::in([\App\Models\LucParameter::KIND_OCCUPANCY, \App\Models\LucParameter::KIND_PROPERTY_CODE])],
            'label' => 'required|string|max:200',
            'rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->attempt(function () use ($data, $parameter) {
            $row = $parameter ? \App\Models\LucParameter::query()->findOrFail($parameter) : new \App\Models\LucParameter();
            if (!$parameter) {
                $code = \Illuminate\Support\Str::slug($data['label'], '_') ?: 'item';
                $base = $code;
                $n = 1;
                while (\App\Models\LucParameter::query()->where('kind', $data['kind'])->where('code', $code)->exists()) {
                    $code = $base . '_' . (++$n);
                }
                $row->fill(['kind' => $data['kind'], 'code' => $code, 'sort_order' => (int) \App\Models\LucParameter::query()->where('kind', $data['kind'])->max('sort_order') + 1]);
            }
            $row->fill([
                'label' => $data['label'],
                'rate' => $data['rate'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $parameter ? (bool) ($data['is_active'] ?? false) : true,
                'updated_by_name' => Auth::user()?->name,
            ])->save();

            return ($row->kind === \App\Models\LucParameter::KIND_OCCUPANCY ? 'Charge rate (M) ' : 'Property Code Rate (PCR) ') . "“{$row->label}” saved.";
        }, 'Saved.');
    }

    // ---- Bill Formulas --------------------------------------------------------

    private function billingData(): array
    {
        return [
            'billingTypes' => \App\Models\BillingType::query()->with('items.revenueItem')->orderBy('sort_order')->orderBy('name')->get(),
            'billingRevenueItems' => RevenueItem::query()->where('is_active', true)->orderBy('revenue_code')->get(['id', 'revenue_code', 'name', 'base_rate']),
        ];
    }

    public function storeBillingType(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('sqlsrv.billing_types', 'code')],
            'name' => 'required|string|max:200',
            'module' => 'nullable|string|max:60',
        ]);

        return $this->attempt(function () use ($data) {
            \App\Models\BillingType::query()->create($data + [
                'formula' => '', 'is_active' => false,
                'updated_by_name' => Auth::user()?->name,
            ]);
        }, 'Bill type created inactive. Add its items and formula before enabling it.');
    }

    public function saveBillingType(Request $request, int $type)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'formula' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        return $this->attempt(function () use ($data, $type) {
            $row = \App\Models\BillingType::query()->with('items')->findOrFail($type);
            $formula = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($data['formula'] ?? ''))));

            $problems = \App\Services\Billing\BillFormula::problems($formula, $row->items->pluck('variable')->all());
            if ($problems) {
                throw WorkflowGuardException::because('The formula is not valid: ' . implode(' ', $problems));
            }

            $before = $row->only(['name', 'formula', 'is_active', 'effective_from', 'effective_to']);
            $row->fill([
                'name' => $data['name'],
                'formula' => $formula,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'updated_by_name' => Auth::user()?->name,
            ])->save();

            app(\App\Services\AuditService::class)->logAction('Bill Formula Updated', 'BillingType', $row->id, $before,
                $row->only(['name', 'formula', 'is_active', 'effective_from', 'effective_to']),
                "Bill formula for {$row->code} set to “{$row->formula}”");

            return "{$row->name}: formula saved as " . ($formula !== '' ? $formula : 'the sum of its items') . '. New bills use it; issued bills keep their amounts.';
        }, 'Bill formula saved.');
    }

    public function saveBillingItem(Request $request, int $type, ?int $item = null)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'variable' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'label' => 'required|string|max:200',
            'source' => ['required', Rule::in(array_keys(\App\Models\BillingTypeItem::SOURCE_LABELS))],
            'revenue_item_id' => 'nullable|required_if:source,revenue_item|integer|exists:sqlsrv.revenue_items,id',
            'amount' => 'nullable|required_if:source,amount|numeric|min:0',
            'sequence' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ], [
            'variable.regex' => 'A variable is letters, digits and underscores, starting with a letter (e.g. LS_FEE).',
            'revenue_item_id.required_if' => 'Choose the revenue item whose rate this variable uses.',
            'amount.required_if' => 'Enter the fixed amount.',
        ]);

        return $this->attempt(function () use ($data, $type, $item) {
            $billingType = \App\Models\BillingType::query()->findOrFail($type);
            $row = $item
                ? \App\Models\BillingTypeItem::query()->where('billing_type_id', $type)->findOrFail($item)
                : new \App\Models\BillingTypeItem(['billing_type_id' => $type]);
            $variable = strtoupper($data['variable']);

            $taken = \App\Models\BillingTypeItem::query()->where('billing_type_id', $type)->where('variable', $variable)
                ->when($item, fn ($q) => $q->where('id', '!=', $item))->exists();
            if ($taken) {
                throw WorkflowGuardException::because("{$variable} is already a variable of {$billingType->name}.");
            }
            // Renaming a variable the formula still uses would break the formula.
            if ($row->exists && $row->variable !== $variable && in_array($row->variable, \App\Services\Billing\BillFormula::variables((string) $billingType->formula), true)) {
                throw WorkflowGuardException::because("The formula uses {$row->variable}. Change the formula first, then rename the variable.");
            }

            $before = $row->exists ? $row->only(['variable', 'label', 'source', 'revenue_item_id', 'amount', 'is_active']) : null;
            $row->fill([
                'variable' => $variable,
                'label' => $data['label'],
                'source' => $data['source'],
                'revenue_item_id' => $data['source'] === \App\Models\BillingTypeItem::SOURCE_REVENUE_ITEM ? $data['revenue_item_id'] : null,
                'amount' => $data['source'] === \App\Models\BillingTypeItem::SOURCE_AMOUNT ? $data['amount'] : null,
                'sequence' => (int) ($data['sequence'] ?? ($row->sequence ?: (int) \App\Models\BillingTypeItem::query()->where('billing_type_id', $type)->max('sequence') + 1)),
                'is_active' => $row->exists ? (bool) ($data['is_active'] ?? false) : true,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
                'updated_by_name' => Auth::user()?->name,
            ])->save();

            app(\App\Services\AuditService::class)->logAction($before ? 'Bill Item Updated' : 'Bill Item Added', 'BillingTypeItem', $row->id, $before,
                $row->only(['variable', 'label', 'source', 'revenue_item_id', 'amount', 'is_active']), "{$billingType->code}: {$row->variable} ({$row->label})");

            return "{$billingType->name}: {$row->variable} saved.";
        }, 'Item saved.');
    }

    // ---- Revenue Items ------------------------------------------------------

    private function revenueData(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        $status = (string) $request->query('status', '');

        $all = RevenueItem::query()->orderBy('revenue_code')->get();
        $categories = $all->map->category()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        $filtered = $all
            ->when($search !== '', fn ($c) => $c->filter(fn (RevenueItem $i) => stripos($i->name, $search) !== false || str_contains($i->revenue_code, $search)))
            ->when($category !== '', fn ($c) => $c->filter(fn (RevenueItem $i) => $i->category() === $category))
            ->when($status === 'active', fn ($c) => $c->where('is_active', true))
            ->when($status === 'inactive', fn ($c) => $c->where('is_active', false))
            ->when($status === 'unrated', fn ($c) => $c->filter(fn (RevenueItem $i) => (float) $i->base_rate <= 0))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $items = new \Illuminate\Pagination\LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $active = $all->where('is_active', true);
        $byHead = fn (string $head) => $active->filter(fn (RevenueItem $i) => $i->category() === $head)->values();
        $mappings = InstrumentFeeMapping::query()->with(['applicationItem', 'registrationItem', 'approvalItem'])->get()->keyBy('instrument_type');

        return [
            'items' => $items,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'categories' => $categories,
            'stats' => [
                'total' => $all->count(),
                'active' => $active->count(),
                'rated' => $all->filter(fn ($i) => (float) $i->base_rate > 0)->count(),
                'categories' => $categories->count(),
            ],
            'instrumentTypes' => collect(ApplicationController::intakeInstrumentTypes())->merge($mappings->keys())->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'mappings' => $mappings,
            'slotOptions' => [
                'application' => $byHead('Application Fee'),
                'registration' => $byHead('Registration Fee'),
                'approval' => $byHead('Approval Fee'),
            ],
            'fallback' => Schema::connection('sqlsrv')->hasTable('instrument_fee_items')
                ? \App\Models\InstrumentWorkflow\InstrumentFeeItem::query()->where('is_active', true)->orderBy('purpose')->orderBy('sort_order')->get()
                : collect(),
        ];
    }

    public function storeRevenueItem(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'revenue_code' => ['required', 'string', 'max:20', 'regex:/^\d+$/', Rule::unique('sqlsrv.revenue_items', 'revenue_code')],
            'name' => 'required|string|max:300',
            'base_rate' => 'required|numeric|min:0',
        ], ['revenue_code.regex' => 'The Revenue Code is digits only.', 'revenue_code.unique' => 'That Revenue Code is already in the register.']);

        return $this->attempt(function () use ($data) {
            $name = preg_match('/^Rate of\s/i', $data['name']) ? $data['name'] : 'Rate of ' . $data['name'];
            $item = RevenueItem::query()->create([
                'revenue_code' => $data['revenue_code'],
                'name' => $name,
                'base_rate' => $data['base_rate'],
                'is_active' => true,
                'rate_id' => (int) RevenueItem::query()->max('rate_id') + 1,
                'updated_by_name' => Auth::user()?->name,
            ]);

            return "Revenue item {$item->revenue_code} added.";
        }, 'Revenue item added.');
    }

    public function updateRevenueItem(Request $request, RevenueItem $item)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:300',
            'base_rate' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->attempt(function () use ($item, $data) {
            $old = (float) $item->base_rate;
            $item->fill([
                'name' => $data['name'],
                'base_rate' => $data['base_rate'],
                'is_active' => (bool) ($data['is_active'] ?? false),
                'updated_by_name' => Auth::user()?->name,
            ])->save();

            $rateNote = round($old, 2) !== round((float) $data['base_rate'], 2)
                ? sprintf(' Rate ₦%s → ₦%s; bills already issued keep their amounts.', number_format($old, 2), number_format((float) $data['base_rate'], 2))
                : '';

            return "Revenue item {$item->revenue_code} saved.{$rateNote}";
        }, 'Revenue item saved.');
    }

    public function saveFeeMapping(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'instrument_type' => 'required|string|max:150',
            'application_revenue_item_id' => 'nullable|integer|exists:sqlsrv.revenue_items,id',
            'registration_revenue_item_id' => 'nullable|integer|exists:sqlsrv.revenue_items,id',
            'approval_revenue_item_id' => 'nullable|integer|exists:sqlsrv.revenue_items,id',
        ]);

        return $this->attempt(function () use ($data) {
            InstrumentFeeMapping::query()->updateOrCreate(['instrument_type' => $data['instrument_type']], [
                'application_revenue_item_id' => $data['application_revenue_item_id'] ?? null,
                'registration_revenue_item_id' => $data['registration_revenue_item_id'] ?? null,
                'approval_revenue_item_id' => $data['approval_revenue_item_id'] ?? null,
                'updated_by_name' => Auth::user()?->name,
            ]);

            return "Fees for {$data['instrument_type']} saved. New bills use them; issued bills are unchanged.";
        }, 'Fees saved.');
    }

    // ---- FileNo Prefix & SerialNo -------------------------------------------

    private function fileNoData(ScheduleFileNumberService $service): array
    {
        $db = $service->db();
        $generated = $db->table('grouping')->whereNotNull('file_format')->whereNull('deleted_at')
            ->select('file_format', DB::raw('COUNT(*) as total'), DB::raw('MIN(serial_no) as first_serial'), DB::raw('MAX(serial_no) as max_serial'))
            ->groupBy('file_format')->get()->keyBy('file_format');
        $formats = $db->table('schedule_file_formats')->orderBy('sort_order')->orderBy('id')->get()->groupBy('schedule_id');

        return [
            'schedules' => $db->table('file_schedules')->orderBy('sort_order')->orderBy('id')->get()->map(function ($schedule) use ($formats, $generated) {
                $schedule->formats = ($formats[$schedule->id] ?? collect())->map(function ($format) use ($generated) {
                    $stats = $generated[$format->pattern] ?? null;
                    $format->generated = (int) ($stats->total ?? 0);
                    $format->first_serial = $stats->first_serial ?? null;
                    $format->max_serial = $stats->max_serial ?? null;
                    // The number File Commissioning would give the next new file.
                    $next = app(ScheduleFileNumberService::class)->nextCommissioningSerial((int) $format->id);
                    $format->example = $next['file_number'] ?? '—';
                    $format->last_commissioned = (int) ($next['last_commissioned'] ?? 0);
                    return $format;
                })->values();
                return $schedule;
            }),
        ];
    }

    public function storeSchedule(Request $request, ScheduleFileNumberService $service)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('sqlsrv.file_schedules', 'code')],
            'name' => 'required|string|max:100',
        ]);

        return $this->attempt(function () use ($data, $service) {
            $service->db()->table('file_schedules')->insert([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'sort_order' => (int) $service->db()->table('file_schedules')->max('sort_order') + 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return "Schedule {$data['name']} added. Add its file number formats next.";
        }, 'Schedule added.');
    }

    public function updateSchedule(Request $request, int $schedule, ScheduleFileNumberService $service)
    {
        $this->authorizeAdmin();

        $data = $request->validate(['name' => 'required|string|max:100', 'sort_order' => 'nullable|integer|min:0', 'is_active' => 'nullable|boolean']);

        return $this->attempt(function () use ($data, $schedule, $service) {
            $updated = $service->db()->table('file_schedules')->where('id', $schedule)->update([
                'name' => $data['name'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? false) ? 1 : 0,
                'updated_at' => now(),
            ]);
            if (!$updated) {
                throw WorkflowGuardException::because('The schedule was not found.');
            }

            return "Schedule {$data['name']} saved.";
        }, 'Schedule saved.');
    }

    public function storeFormat(Request $request, ScheduleFileNumberService $service)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'schedule_id' => 'required|integer|exists:sqlsrv.file_schedules,id',
            'file_prefix' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\/\-]+$/'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'label' => 'nullable|string|max:150',
            'last_serial' => 'nullable|integer|min:0',
        ], ['file_prefix.regex' => 'The prefix may contain letters, digits, "/" and "-".']);

        return $this->attempt(function () use ($data, $service) {
            $prefix = strtoupper(trim($data['file_prefix'], '/'));
            $suffix = !empty($data['suffix']) ? strtoupper(trim($data['suffix'], '/')) : null;
            $pattern = $prefix . '/' . ScheduleFileNumberService::SERIAL_TOKEN . ($suffix ? '/' . $suffix : '');

            if ($service->db()->table('schedule_file_formats')->where('pattern', $pattern)->exists()) {
                throw WorkflowGuardException::because("The format {$pattern} already exists.");
            }

            $schedule = $service->db()->table('file_schedules')->where('id', $data['schedule_id'])->first();
            $service->db()->table('schedule_file_formats')->insert([
                'schedule_id' => $data['schedule_id'],
                'file_prefix' => $prefix,
                'suffix' => $suffix,
                'pattern' => $pattern,
                'label' => !empty($data['label']) ? $data['label'] : "{$prefix} — {$schedule->name}",
                'sort_order' => (int) $service->db()->table('schedule_file_formats')->where('schedule_id', $data['schedule_id'])->max('sort_order') + 1,
                'last_serial' => (int) ($data['last_serial'] ?? 0),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return "Format {$pattern} added to {$schedule->name}.";
        }, 'Format added.');
    }

    public function updateFormat(Request $request, int $format, ScheduleFileNumberService $service)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'label' => 'required|string|max:150',
            'sort_order' => 'nullable|integer|min:0',
            'commissioning_start_after' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'file_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\/\-]+$/'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);

        return $this->attempt(function () use ($data, $format, $service) {
            $row = $service->format($format);
            if (!$row) {
                throw WorkflowGuardException::because('The format was not found.');
            }

            $update = [
                'label' => $data['label'],
                'sort_order' => (int) ($data['sort_order'] ?? $row->sort_order),
                'commissioning_start_after' => (int) ($data['commissioning_start_after'] ?? ($row->commissioning_start_after ?? 0)),
                'is_active' => (bool) ($data['is_active'] ?? false) ? 1 : 0,
                'updated_at' => now(),
            ];

            // Generated numbers are stored against the pattern, so it is fixed once any exist.
            $generated = $service->db()->table('grouping')->where('file_format', $row->pattern)->exists();
            if (!$generated && !empty($data['file_prefix'])) {
                $prefix = strtoupper(trim($data['file_prefix'], '/'));
                $suffix = !empty($data['suffix']) ? strtoupper(trim($data['suffix'], '/')) : null;
                $pattern = $prefix . '/' . ScheduleFileNumberService::SERIAL_TOKEN . ($suffix ? '/' . $suffix : '');
                if ($pattern !== $row->pattern && $service->db()->table('schedule_file_formats')->where('pattern', $pattern)->exists()) {
                    throw WorkflowGuardException::because("The format {$pattern} already exists.");
                }
                $update += ['file_prefix' => $prefix, 'suffix' => $suffix, 'pattern' => $pattern];
            }

            $service->db()->table('schedule_file_formats')->where('id', $format)->update($update);

            return "Format {$data['label']} saved." . ($generated && !empty($data['file_prefix']) && strtoupper(trim($data['file_prefix'], '/')) !== $row->file_prefix ? ' The prefix was not changed: file numbers have already been generated with it.' : '');
        }, 'Format saved.');
    }

    public function generateSerials(Request $request, int $format, ScheduleFileNumberService $service)
    {
        $this->authorizeAdmin();

        $data = $request->validate(['count' => 'required|integer|min:1|max:5000']);

        return $this->attempt(function () use ($data, $format, $service) {
            $row = $service->format($format);
            if (!$row) {
                throw WorkflowGuardException::because('The format was not found.');
            }

            $start = (int) $row->last_serial + 1;
            ['inserted' => $inserted, 'skipped' => $skipped] = $service->generate($format, $start, (int) $data['count'], Auth::user()?->name ?? 'System');
            $end = $start + (int) $data['count'] - 1;

            return sprintf('%s serials %d–%d: %d generated%s.', $row->pattern, $start, $end, $inserted, $skipped ? ", {$skipped} already existed" : '');
        }, 'Serials generated.');
    }

    // ---- Serial Initialization -----------------------------------------------

    /**
     * One group per register that exists on this deployment, each listing every
     * counter it can hold with the row behind it (if any). A counter with no row
     * is Pending and can be initialized; one that is locked is shown read-only,
     * because that is the whole point of locking it.
     */
    private function serialsData(): array
    {
        $schema = Schema::connection('sqlsrv');
        $year = (int) date('Y');

        $registers = [];

        foreach (self::SERIAL_REGISTERS as $key => $meta) {
            if (!$schema->hasTable($meta['table'])) {
                continue;
            }

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $meta['model'];
            $rows = $model::query()->when($meta['yearly'], fn ($q) => $q->where('year', $year))->get();

            $clean = fn ($value) => trim((string) $value);

            // Land reads its counters from the commissioning prefixes; the other
            // two have a fixed list. Either way a stored key that is no longer
            // offered is merged in, so a lock nobody expected is still visible.
            $names = collect($meta['keys'] ?? \App\Models\Prefix::query()->pluck('prefix')->all())
                ->merge($rows->pluck($meta['key']))
                ->map($clean)
                ->filter()
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            // Land stores the officer's name in initialized_by; Deeds and Survey store a user id.
            $userNames = $meta['by'] === 'id'
                ? \App\Models\User::query()
                    ->whereIn('id', $rows->pluck('initialized_by')->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique()->all())
                    ->get()->pluck('name', 'id')
                : collect();

            $counters = $names->map(function ($name) use ($rows, $meta, $clean, $userNames, $year) {
                $row = $rows->first(fn ($r) => $clean($r->{$meta['key']}) === $name);
                $by = $row?->initialized_by;

                return (object) [
                    'name' => $name,
                    'label' => self::SERIAL_KEY_LABELS[$name] ?? $name,
                    'year' => $meta['yearly'] ? $year : null,
                    // A row can exist unlocked and still be live: getNextSerial()
                    // creates one and counts up whether or not it was initialized,
                    // so its last_serial is a real number already issued, not a blank.
                    'exists' => $row !== null,
                    'last_serial' => (int) ($row->last_serial ?? 0),
                    'initialized' => (bool) ($row->is_initialized ?? false),
                    'locked' => (bool) ($row->is_locked ?? false),
                    'initialized_at' => $row?->initialized_at ?? $row?->created_at,
                    'initialized_by' => is_numeric($by) ? ($userNames[(int) $by] ?? "User #{$by}") : ($by ?: null),
                ];
            });

            $registers[$key] = (object) [
                'key' => $key,
                'label' => $meta['label'],
                'where' => $meta['where'],
                'key_label' => $meta['key_label'],
                'icon' => $meta['icon'],
                'yearly' => $meta['yearly'],
                'year' => $meta['yearly'] ? $year : null,
                'counters' => $counters,
                'locked_count' => $counters->where('locked', true)->count(),
                'pending_count' => $counters->where('locked', false)->count(),
            ];
        }

        return ['registers' => collect($registers)];
    }

    /**
     * Set a counter's last serial and lock it. One-time by design: a locked
     * counter is refused here, so the only way past it is the database — which
     * is what keeps the generator from minting a file number twice.
     */
    public function initializeSerial(Request $request, string $register)
    {
        $this->authorizeAdmin();

        $meta = self::SERIAL_REGISTERS[$register] ?? abort(404, 'No such serial register.');
        abort_unless(Schema::connection('sqlsrv')->hasTable($meta['table']), 404, 'No such serial register.');

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'last_serial' => 'required|integer|min:0',
        ]);

        return $this->attempt(function () use ($data, $meta, $register) {
            $name = trim($data['name']);
            $lastSerial = (int) $data['last_serial'];
            $year = (int) date('Y');

            $allowed = $meta['keys'] ?? \App\Models\Prefix::query()->pluck('prefix')->map(fn ($p) => trim((string) $p))->all();
            if (!in_array($name, $allowed, true)) {
                throw WorkflowGuardException::because("{$meta['key_label']} {$name} is not one this register numbers.");
            }

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $meta['model'];
            $keys = [$meta['key'] => $name] + ($meta['yearly'] ? ['year' => $year] : []);

            // The lock is the gate, not the initialized flag: the Land register was
            // seeded with rows marked initialized but left unlocked so the real
            // starting serial could still be set. That is the gate the old screen
            // used and the one the generator itself checks.
            $existing = $model::query()->where($keys)->first();
            if ($existing && $existing->is_locked) {
                throw WorkflowGuardException::because(sprintf(
                    '%s %s is already initialized at %d%s and locked. Only the database can change it now.',
                    $meta['key_label'],
                    self::SERIAL_KEY_LABELS[$name] ?? $name,
                    (int) $existing->last_serial,
                    $meta['yearly'] ? " for {$year}" : ''
                ));
            }

            $row = $model::query()->updateOrCreate($keys, [
                'last_serial' => $lastSerial,
                'is_initialized' => true,
                'is_locked' => true,
                'initialized_at' => now(),
                // Land stores a name, Deeds and Survey a user id — as each generator reads it back.
                'initialized_by' => $meta['by'] === 'id' ? Auth::id() : (Auth::user()?->name ?? 'System'),
            ]);

            $label = self::SERIAL_KEY_LABELS[$name] ?? $name;
            // An unlocked row is still live — the generator has been counting up on it.
            // Setting the counter below where it stands re-issues numbers already given out.
            $was = $existing ? (int) $existing->last_serial : null;
            $backwards = $was !== null && $lastSerial < $was;

            app(\App\Services\AuditService::class)->logAction(
                'Serial Initialized',
                class_basename($meta['model']),
                $row->getKey(),
                $existing ? ['last_serial' => $was] : null,
                ['register' => $register, $meta['key'] => $name, 'year' => $meta['yearly'] ? $year : null, 'last_serial' => $lastSerial],
                "{$meta['label']}: {$label} initialized at {$lastSerial} and locked."
                    . ($backwards ? " The counter was moved BACK from {$was}." : '')
            );

            return sprintf(
                '%s %s is initialized at %d%s and locked. The next file number under it will be serial %d.',
                $meta['key_label'],
                $label,
                $lastSerial,
                $meta['yearly'] ? " for {$year}" : '',
                $lastSerial + 1
            ) . ($backwards
                ? " The counter was moved BACK from {$was}: serials " . ($lastSerial + 1) . "–{$was} will be issued a second time."
                : '');
        }, 'Serial initialized.');
    }

    // ---- Deeds Instrument Volume --------------------------------------------

    private function volumeData(InstrumentRegistrationService $registration): array
    {
        $vaultRows = DB::connection('sqlsrv')->table('instrument_number_vaults')->orderBy('instrument_type')->get();
        $vaults = $vaultRows->keyBy('instrument_type');
        $liveShared = $registration->sharedVaultName();
        $clean = fn ($text) => trim(preg_replace('/\s+/', ' ', (string) $text));

        $types = NewInstrumentType::query()->orderBy('name')->get()->map(function ($type) use ($vaults, $registration, $clean, $liveShared) {
            $vaultName = $registration->resolveVaultName($type->name);
            $ownVault = $registration->baseVaultName($type->name);
            $vault = $vaults->get($vaultName);

            try {
                $next = $registration->peekRegistrationNumber($type->name);
            } catch (\Throwable $e) {
                $next = ['configured' => false];
            }

            return (object) [
                'id' => $type->id,
                'name' => $clean($type->name),
                'raw_name' => $type->name,
                'vault' => $clean($vaultName),
                'own_vault' => $clean($ownVault),
                // Numbering from the Unified Register rather than its own vault.
                'unified' => $liveShared !== null && $vaultName === $liveShared,
                // Some stored names carry pasted line breaks; compare them cleaned.
                'shared' => $clean($type->name) !== $clean($vaultName),
                'volume' => $vault->current_volume ?? null,
                'page' => $vault->current_page ?? null,
                'serial' => $vault->current_serial ?? null,
                'start_volume' => $vault->start_volume ?? null,
                'start_serial' => $vault->start_serial ?? null,
                'next' => !empty($next['configured']) ? ($next['formatted'] ?? null) : null,
                'updated_at' => $vault->updated_at ?? null,
            ];
        });

        $hasUnifiedColumns = $registration->vaultHasColumn('page_limit') && $registration->vaultHasColumn('is_shared');

        return [
            'types' => $types,
            'unifiedColumns' => $hasUnifiedColumns,
            'unifiedEnabled' => filter_var(config('instrument_workflow.registration.unified_vault.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'unifiedName' => (string) config('instrument_workflow.registration.unified_vault.name', 'Unified Register'),
            'liveShared' => $liveShared,
            'landVault' => (string) config('land_registration.instrument_type'),
            'vaults' => $vaultRows->map(function ($vault) use ($registration, $types, $liveShared) {
                $name = (string) $vault->instrument_type;
                $isLive = $liveShared === null ? $types->contains(fn ($t) => $t->vault === trim(preg_replace('/\s+/', ' ', $name))) : $liveShared === $name;

                return (object) [
                    'id' => (int) $vault->id,
                    'name' => $name,
                    'serial' => (int) $vault->current_serial,
                    'page' => (int) $vault->current_page,
                    'volume' => (int) $vault->current_volume,
                    'page_limit' => $registration->pageLimitFor($vault),
                    'is_shared' => (bool) ($vault->is_shared ?? false),
                    'start_volume' => $vault->start_volume ?? null,
                    'start_serial' => $vault->start_serial ?? null,
                    // In use: the live shared vault, or (shared mode off) a vault some type numbers from.
                    'live' => $isLive,
                    'types' => $types->filter(fn ($t) => $t->own_vault === trim(preg_replace('/\s+/', ' ', $name)))->pluck('name')->values()->all(),
                    'next' => $registration->nextNumberForVault($vault)['formatted'] ?? null,
                    'updated_at' => $vault->updated_at ?? null,
                ];
            }),
        ];
    }

    /**
     * Edit one register (vault) directly: counters, page limit, shared flag.
     *
     * The counters are the LAST number issued; the next registration continues
     * from them, so lowering them re-issues numbers. Switching a vault's Shared
     * flag on makes it the Single Unified Register for every instrument type
     * (others are switched off first - one shared vault at most).
     */
    public function updateVault(Request $request, int $vault, InstrumentRegistrationService $registration)
    {
        $this->authorizeAdmin();

        if (!$registration->vaultHasColumn('page_limit') || !$registration->vaultHasColumn('is_shared')) {
            return $this->actionFailed('Run the 2026_09_15_200000 migration first: the register table has no page limit or shared flag yet.');
        }

        $data = $request->validate([
            'last_volume' => 'required|integer|min:1',
            'last_page' => 'required|integer|min:0',
            'last_serial' => 'required|integer|min:0',
            'page_limit' => 'required|integer|min:1|max:100000',
            'is_shared' => 'nullable|boolean',
            'start_volume' => 'nullable|integer|min:1',
            'start_serial' => 'nullable|integer|min:1',
        ]);

        return $this->attempt(function () use ($data, $vault, $registration) {
            $db = DB::connection('sqlsrv');

            $message = $db->transaction(function () use ($db, $data, $vault, $registration) {
                $row = $db->table('instrument_number_vaults')->where('id', $vault)->lockForUpdate()->first();
                if (!$row) {
                    throw WorkflowGuardException::because('The register was not found.');
                }

                $shared = (bool) ($data['is_shared'] ?? false);
                if ($shared && $row->instrument_type === config('land_registration.instrument_type')) {
                    throw WorkflowGuardException::because("{$row->instrument_type} numbers on the Land Registry's own rules and cannot be the shared register.");
                }
                if ($shared && (int) $data['last_serial'] > (int) $data['page_limit']) {
                    throw WorkflowGuardException::because("The last serial ({$data['last_serial']}) is above the page limit ({$data['page_limit']}).");
                }

                $update = [
                    'current_volume' => (int) $data['last_volume'],
                    'current_page' => (int) $data['last_page'],
                    'current_serial' => (int) $data['last_serial'],
                    'page_limit' => (int) $data['page_limit'],
                    'updated_at' => now(),
                ];
                if ($registration->vaultHasColumn('start_volume') && $registration->vaultHasColumn('start_serial')) {
                    $update['start_volume'] = !empty($data['start_volume']) ? (int) $data['start_volume'] : null;
                    $update['start_serial'] = !empty($data['start_serial']) ? (int) $data['start_serial'] : null;
                }

                if ($shared) {
                    // Clear first: a filtered unique index allows one shared row.
                    $db->table('instrument_number_vaults')->where('id', '<>', $row->id)->where('is_shared', 1)->update(['is_shared' => 0, 'updated_at' => now()]);
                }
                $update['is_shared'] = $shared ? 1 : 0;

                $db->table('instrument_number_vaults')->where('id', $row->id)->update($update);

                $backwards = [(int) $data['last_volume'], (int) $data['last_serial']] < [(int) $row->current_volume, (int) $row->current_serial];
                $fresh = $db->table('instrument_number_vaults')->where('id', $row->id)->first();
                $next = $registration->nextNumberForVault($fresh)['formatted'] ?? null;

                \Illuminate\Support\Facades\Log::warning('Registration vault edited on Configurable Entries', [
                    'vault' => $row->instrument_type,
                    'before' => "{$row->current_serial}/{$row->current_page}/{$row->current_volume} limit " . $registration->pageLimitFor($row) . ' shared ' . (int) ($row->is_shared ?? 0),
                    'after' => "{$update['current_serial']}/{$update['current_page']}/{$update['current_volume']} limit {$update['page_limit']} shared {$update['is_shared']}",
                    'user' => Auth::id(),
                ]);

                return "Register {$row->instrument_type} saved. Its next number is {$next}."
                    . ($backwards ? ' The counters were moved BACK: numbers already issued in between will be issued again.' : '')
                    . ($shared && !filter_var(config('instrument_workflow.registration.unified_vault.enabled', false), FILTER_VALIDATE_BOOLEAN) ? ' Shared mode is switched off in configuration (INSTRUMENT_UNIFIED_VAULT), so types still use their own registers.' : '')
                    . (!$shared && (int) ($row->is_shared ?? 0) === 1 ? ' Shared mode is now off: every type numbers from its own register again.' : '');
            });

            return $message;
        }, 'Register saved.');
    }

    public function updateVolume(Request $request, int $type)
    {
        $this->authorizeAdmin();

        // While the Unified Register is live every type resolves to it, so a
        // per-type edit would silently move the shared counters. Those are
        // edited on the register itself, where the warning is.
        if (($shared = app(InstrumentRegistrationService::class)->sharedVaultName()) !== null) {
            return $this->actionFailed("Every instrument type numbers from the {$shared} while it is live. Edit that register's counters in the Registers table instead.");
        }

        $row = NewInstrumentType::query()->findOrFail($type);
        $request->validate([
            'last_volume' => 'required|integer|min:0',
            'last_page' => 'required|integer|min:0',
            'last_serial' => 'required|integer|min:0',
            'start_volume' => 'nullable|integer|min:1',
            'start_serial' => 'nullable|integer|min:1',
        ]);

        return $this->attempt(function () use ($request, $row) {
            // The same write the Instrument Types pop-up makes, so the vault rules stay in one place.
            $request->merge(['name' => $row->name, 'description' => $row->description]);
            $response = app(InstrumentTypeController::class)->update($request, $row->id);
            if ($response->getStatusCode() >= 400) {
                throw WorkflowGuardException::because((string) ($response->getData(true)['message'] ?? 'The volume could not be saved.'));
            }

            $next = app(InstrumentRegistrationService::class)->peekRegistrationNumber($row->name);

            return "Volume for {$row->name} saved." . (!empty($next['formatted']) ? " The next registration will be {$next['formatted']}." : '');
        }, 'Volume saved.');
    }

    // ---- Instrument Registration Pipeline -----------------------------------

    private function pipelineData(WorkflowPipeline $pipeline): array
    {
        $roles = DB::connection('sqlsrv')->table('user_roles')->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))->orderBy('name')->pluck('name')->unique()->values();
        $overrides = WorkflowActionRole::query()->pluck('roles', 'action');

        $actions = [];
        foreach (self::ACTION_LABELS as $action => [$label, $where]) {
            if (str_starts_with($action, 'check.')) {
                $key = substr($action, 6);
                $label = $pipeline->label($key);
                $where = WorkflowPipeline::SCREENS[$key];
            }
            $actions[$action] = [
                'label' => $label,
                'where' => $where,
                'roles' => InstrumentAccessGuard::rolesFor($action),
                'custom' => $overrides->has($action),
            ];
        }

        return [
            'steps' => array_values($pipeline->steps()),
            'actions' => $actions,
            'roleNames' => $roles,
            'workflowInstalled' => Schema::connection('sqlsrv')->hasTable('instrument_applications'),
            'waiting' => Schema::connection('sqlsrv')->hasTable('instrument_applications') ? \App\Models\InstrumentWorkflow\InstrumentApplication::query()
                ->whereIn('stage', array_values(WorkflowPipeline::STAGES))
                ->select('stage', DB::raw('COUNT(*) as total'))->groupBy('stage')->pluck('total', 'stage') : collect(),
        ];
    }

    public function savePipeline(Request $request, WorkflowPipeline $pipeline, InstrumentWorkflowService $workflow)
    {
        $this->authorizeAdmin();

        $keys = array_keys(WorkflowPipeline::DEFAULTS);
        $data = $request->validate([
            'steps' => 'required|array',
            'steps.*.label' => 'required|string|max:150',
            'steps.*.department' => 'required|string|max:150',
            'steps.*.sort_order' => 'required|integer|min:0|max:99',
            'steps.*.enabled' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'nullable|array',
            'roles.*.*' => 'nullable|string|max:255',
        ]);

        // Only a transition from on to off is alertable. Re-saving an
        // already-disabled step must not create duplicate activity entries.
        $before = collect($pipeline->steps())->mapWithKeys(fn (array $step) => [$step['key'] => $step]);

        return $this->attempt(function () use ($data, $keys, $pipeline, $workflow, $before, $request) {
            $steps = array_intersect_key($data['steps'], array_flip($keys));
            $pipeline->save($steps, Auth::user()?->name);

            $known = array_keys(self::ACTION_LABELS);
            foreach ((array) ($data['roles'] ?? []) as $action => $roles) {
                if (!in_array($action, $known, true)) {
                    continue;
                }
                WorkflowActionRole::query()->updateOrCreate(['action' => $action], [
                    'roles' => array_values(array_unique(array_filter(array_map('trim', (array) $roles)))),
                    'updated_by_name' => Auth::user()?->name,
                ]);
            }
            InstrumentAccessGuard::flushRoles();

            $moved = Schema::connection('sqlsrv')->hasTable('instrument_applications')
                ? $workflow->releaseTurnedOffChecks() : 0;

            $disabled = collect($pipeline->steps())
                ->filter(fn (array $step) => !$step['enabled'] && (($before[$step['key']]['enabled'] ?? true) === true))
                ->values();
            $enabled = collect($pipeline->steps())
                ->filter(fn (array $step) => $step['enabled'] && (($before[$step['key']]['enabled'] ?? false) === false))
                ->values();

            $events = [
                [
                    'steps' => $disabled,
                    'action' => 'Instrument Workflow Step Disabled',
                    'activity_type' => 'workflow_step_disabled',
                    'description' => fn (string $names) => "Instrument Registration Pipeline step disabled: {$names}. Disabled checks are skipped; affected applications move to the next enabled check or LIC.",
                    'alert' => fn (string $names) => "Disabled pipeline step(s): {$names}.",
                ],
                [
                    'steps' => $enabled,
                    'action' => 'Instrument Workflow Step Enabled',
                    'activity_type' => 'workflow_step_enabled',
                    'description' => fn (string $names) => "Instrument Registration Pipeline step enabled: {$names}. New applications will be routed through this check.",
                    'alert' => fn (string $names) => "Enabled pipeline step(s): {$names}.",
                ],
            ];

            $alerts = [];
            foreach ($events as $event) {
                if ($event['steps']->isEmpty()) {
                    continue;
                }

                $names = $event['steps']->pluck('label')->implode(', ');
                $description = $event['description']($names);

                app(\App\Services\AuditService::class)->logAction(
                    $event['action'],
                    'InstrumentWorkflowStep',
                    null,
                    $event['steps']->mapWithKeys(fn (array $step) => [$step['key'] => $before[$step['key']] ?? null])->all(),
                    $event['steps']->mapWithKeys(fn (array $step) => [$step['key'] => $step])->all(),
                    $description
                );

                // This is an activity event rather than a browser session. It
                // therefore appears on /user-activity-logs without changing
                // the online-user count or ending the administrator's session.
                UserActivityLog::create([
                    'user_id' => Auth::id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'activity_type' => $event['activity_type'],
                    'activity_description' => $description,
                    'status' => 'Offline',
                    'is_online' => false,
                    'session_id' => $request->session()->getId(),
                    'test_control' => app()->environment('production') ? 'PRO' : 'TEST',
                ]);

                $alerts[] = $event['alert']($names);
            }

            if ($alerts) {
                $alert = 'Alert: ' . implode(' ', $alerts) . ' The change has been logged in User Activity Logs.';
                session()->flash('warning', $alert);

                return $alert . ($moved ? " {$moved} waiting " . \Illuminate\Support\Str::plural('application', $moved) . ' moved past checks that are now off.' : '');
            }

            $order = collect($pipeline->steps())->map(fn ($s) => $s['department'] . ($s['enabled'] ? '' : ' (off)'))->implode(' → ');

            return "Pipeline saved: {$order} → LIC." . ($moved ? " {$moved} waiting " . \Illuminate\Support\Str::plural('application', $moved) . ' moved past checks that are now off.' : '');
        }, 'Pipeline saved.');
    }

    // ---- Valuation - Consent - Registration ---------------------------------

    /**
     * The Deeds workflow tab: the three hand-offs of Valuation -> Consent ->
     * Print -> Registration, and the mapping that decides which instruments are
     * gated at all.
     *
     * Only the gates are editable. The instrument and consent-type maps are shown
     * read-only because they are the definition of the dealings themselves --
     * which deed a "Consent to Mortgage" authorises is law, not a setting -- and
     * config/deeds_pipeline.php stays their single owner. They are shown at all
     * because a gate set to 'block' means nothing without knowing what it blocks.
     */
    private function deedsWorkflowData(): array
    {
        $overrides = DeedsPipelineGates::overrides();

        $gates = [];
        foreach (self::DEEDS_GATES as $gate => $meta) {
            $gates[$gate] = $meta + [
                'key' => $gate,
                'mode' => DeedsPipelineGates::mode($gate),
                'default' => DeedsPipelineGates::default($gate),
                'custom' => array_key_exists($gate, $overrides),
            ];
        }

        return [
            'gates' => $gates,
            'gateModes' => DeedsPipelineGates::MODES,
            'consentInstruments' => (array) config('deeds_pipeline.consent_instruments', []),
            'consentGroups' => (array) config('deeds_pipeline.consent_groups', []),
        ];
    }

    /**
     * Save the gate modes.
     *
     * Loosening a gate is the change worth recording: it is what lets work past a
     * stage that was previously required, so every change is written to the audit
     * trail and to User Activity Logs with the wording of what it now permits.
     * Nothing already captured is touched -- a gate is only ever consulted at the
     * moment an officer tries to save the next stage.
     */
    public function saveDeedsWorkflow(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'gates' => 'required|array',
            'gates.*' => ['required', 'string', Rule::in(DeedsPipelineGates::MODES)],
        ]);

        $before = [];
        foreach (array_keys(self::DEEDS_GATES) as $gate) {
            $before[$gate] = DeedsPipelineGates::mode($gate);
        }

        return $this->attempt(function () use ($data, $before, $request) {
            $changed = [];

            foreach (array_keys(self::DEEDS_GATES) as $gate) {
                $mode = $data['gates'][$gate] ?? null;

                if ($mode === null || $mode === $before[$gate]) {
                    continue;
                }

                DeedsPipelineGates::set($gate, $mode);
                $changed[$gate] = $mode;
            }

            if ($changed === []) {
                return 'No change - the workflow is already set that way.';
            }

            $names = collect($changed)
                ->map(fn (string $mode, string $gate) => self::DEEDS_GATES[$gate]['label']
                    . ': ' . strtoupper($before[$gate]) . ' -> ' . strtoupper($mode))
                ->implode('; ');

            $description = "Valuation - Consent - Registration workflow changed. {$names}. "
                . "Block refuses the action until the earlier stage exists, warn allows it "
                . "with a warning, off does not check at all.";

            app(\App\Services\AuditService::class)->logAction(
                'Deeds Workflow Gate Changed',
                'DeedsPipelineGate',
                null,
                array_intersect_key($before, $changed),
                $changed,
                $description
            );

            // An activity event, not a browser session: it shows on
            // /user-activity-logs without changing the online-user count or
            // ending the administrator's own session.
            UserActivityLog::create([
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'activity_type' => 'deeds_workflow_gate_changed',
                'activity_description' => $description,
                'status' => 'Offline',
                'is_online' => false,
                'session_id' => $request->session()->getId(),
                'test_control' => app()->environment('production') ? 'PRO' : 'TEST',
            ]);

            $loosened = collect($changed)->filter(
                fn (string $mode, string $gate) => $before[$gate] === 'block' && $mode !== 'block'
            );

            if ($loosened->isNotEmpty()) {
                $labels = $loosened->keys()->map(fn ($gate) => self::DEEDS_GATES[$gate]['label'])->implode(', ');
                $alert = "Alert: {$labels} no longer blocks. Officers can now save past that stage. "
                    . 'The change has been logged in User Activity Logs.';
                session()->flash('warning', $alert);

                return $alert;
            }

            return "Workflow saved. {$names}.";
        }, 'Workflow saved.');
    }

    // ---- CofO Process --------------------------------------------------------



    // -------------------------------------------------------------------------

    // ---- Logos ----------------------------------------------------------------

    /**
     * Every logo slot, grouped as config/alaes_logos.php groups them, with what each
     * one resolves to today. `missing` marks a slot whose image is not on disk — those
     * screens print a gap right now, and an upload here is what fixes them.
     */
    private function logosData(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $group = (string) $request->query('group', '');
        $only = (string) $request->query('only', '');

        $rows = [];
        foreach (AlaesLogos::byGroup() as $groupKey => $slots) {
            foreach ($slots as $slot => $definition) {
                $relative = AlaesLogos::relative($slot);
                $rows[$groupKey][$slot] = $definition + [
                    'slot' => $slot,
                    'url' => AlaesLogos::url($slot),
                    'relative' => $relative,
                    'custom' => AlaesLogos::isCustom($slot),
                    'missing' => $relative === '' || !is_file(public_path($relative)),
                ];
            }
        }

        // Counts for the whole catalogue, and per area for the index cards. 250-odd
        // slots is too many to put on one page, so the tab opens on the areas and
        // only lists slots once an area is chosen or something is searched for.
        $counts = ['slots' => 0, 'custom' => 0, 'missing' => 0, 'groups' => count($rows)];
        $groupCounts = [];
        foreach ($rows as $groupKey => $slots) {
            $groupCounts[$groupKey] = ['slots' => 0, 'custom' => 0, 'missing' => 0, 'preview' => []];
            foreach ($slots as $row) {
                $counts['slots']++;
                $counts['custom'] += $row['custom'] ? 1 : 0;
                $counts['missing'] += $row['missing'] ? 1 : 0;
                $groupCounts[$groupKey]['slots']++;
                $groupCounts[$groupKey]['custom'] += $row['custom'] ? 1 : 0;
                $groupCounts[$groupKey]['missing'] += $row['missing'] ? 1 : 0;
                if (!$row['missing'] && count($groupCounts[$groupKey]['preview']) < 4) {
                    $groupCounts[$groupKey]['preview'][] = $row['url'];
                }
            }
        }

        // Filters narrow what is listed, never what is counted: the tiles describe the
        // whole catalogue, the list answers the question being asked of it.
        $filtered = [];
        foreach ($rows as $groupKey => $slots) {
            if ($group !== '' && $group !== $groupKey) {
                continue;
            }

            $kept = array_filter($slots, function (array $row) use ($search, $only) {
                if ($only === 'custom' && !$row['custom']) {
                    return false;
                }
                if ($only === 'missing' && !$row['missing']) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }

                return stripos($row['label'], $search) !== false
                    || stripos($row['slot'], $search) !== false
                    || stripos((string) ($row['where'] ?? ''), $search) !== false
                    || stripos((string) $row['default'], $search) !== false;
            });

            if ($kept !== []) {
                $filtered[$groupKey] = $kept;
            }
        }

        return [
            'logoGroups' => (array) config('alaes_logos.groups', []),
            'logoRows' => $filtered,
            'logoCounts' => $counts,
            'logoGroupCounts' => $groupCounts,
            'logoSearch' => $search,
            'logoGroup' => $group,
            'logoOnly' => $only,
            'logoMaxKb' => (int) config('alaes_logos.max_kilobytes', 4096),
            'logoExtensions' => (array) config('alaes_logos.allowed_extensions', []),
        ];
    }

    /** Replace one slot's image. The upload is kept beside the shipped logos, named for its slot. */
    public function saveLogo(Request $request, string $slot)
    {
        $this->authorizeAdmin();

        abort_unless(array_key_exists($slot, AlaesLogos::slots()), 404, 'No such logo.');

        $extensions = (array) config('alaes_logos.allowed_extensions', ['png', 'jpg', 'jpeg']);
        $maxKb = (int) config('alaes_logos.max_kilobytes', 4096);

        $data = $request->validate([
            'image' => ['required', 'file', 'mimes:' . implode(',', $extensions), 'max:' . $maxKb],
        ], [
            'image.required' => 'Choose the image to use.',
            'image.mimes' => 'Use a ' . strtoupper(implode(', ', $extensions)) . ' image.',
            'image.max' => 'That image is larger than ' . $maxKb . 'KB.',
        ]);

        return $this->attempt(function () use ($slot, $data) {
            $definition = AlaesLogos::slots()[$slot];
            $directory = trim((string) config('alaes_logos.upload_directory', 'assets/images/logos/custom'), '/');
            $target = public_path($directory);

            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                throw WorkflowGuardException::because('The logo folder could not be created: ' . $directory);
            }

            $file = $data['image'];
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $name = $slot . '-' . now()->format('YmdHis') . '.' . $extension;
            $file->move($target, $name);

            $previous = DB::connection('sqlsrv')->table('alaes_logos')->where('slot', $slot)->first();
            $path = $directory . '/' . $name;

            DB::connection('sqlsrv')->table('alaes_logos')->updateOrInsert(['slot' => $slot], [
                'path' => $path,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'updated_by' => Auth::id(),
                'updated_by_name' => Auth::user()?->name,
                'updated_at' => now(),
                'created_at' => $previous->created_at ?? now(),
            ]);

            // The file this one replaces is ours to remove — but only ours. A slot still
            // standing on the shipped default has no uploaded file to delete.
            if ($previous && $previous->path && $previous->path !== $path && is_file(public_path($previous->path))) {
                @unlink(public_path($previous->path));
            }

            AlaesLogos::flush();

            app(\App\Services\AuditService::class)->logAction('Logo Changed', 'AlaesLogo', null,
                ['slot' => $slot, 'path' => $previous->path ?? $definition['default']],
                ['slot' => $slot, 'path' => $path],
                "{$definition['label']} now uses {$name}");

            return "{$definition['label']} updated. Every screen and document that shows it changes with it.";
        }, 'Logo updated.');
    }

    /** Put one slot back to the image KLAES ships with. */
    public function resetLogo(Request $request, string $slot)
    {
        $this->authorizeAdmin();

        abort_unless(array_key_exists($slot, AlaesLogos::slots()), 404, 'No such logo.');

        return $this->attempt(function () use ($slot) {
            $definition = AlaesLogos::slots()[$slot];
            $row = DB::connection('sqlsrv')->table('alaes_logos')->where('slot', $slot)->first();

            if (!$row) {
                throw WorkflowGuardException::because("{$definition['label']} already uses the default image.");
            }

            DB::connection('sqlsrv')->table('alaes_logos')->where('slot', $slot)->delete();

            if ($row->path && is_file(public_path($row->path))) {
                @unlink(public_path($row->path));
            }

            AlaesLogos::flush();

            app(\App\Services\AuditService::class)->logAction('Logo Restored', 'AlaesLogo', null,
                ['slot' => $slot, 'path' => $row->path],
                ['slot' => $slot, 'path' => $definition['default']],
                "{$definition['label']} restored to the default image");

            return "{$definition['label']} is back to the image KLAES ships with.";
        }, 'Default logo restored.');
    }

    // ---- Cadastral -------------------------------------------------------------

    /**
     * Sections of the Cadastral tab that save through saveCadastralSettings(),
     * each owning the CadastralSettings::DEFINITIONS rows of the same group.
     */
    public const CADASTRAL_SETTING_GROUPS = [
        'fees' => 'Fee sheet rates',
        'area' => 'Area fee rules',
        'transport' => 'Transport bands',
        'numbering' => 'Numbering',
        'file_numbers' => 'File-number prefixes & source registries',
    ];

    /**
     * Everything the Cadastral tab shows.
     *
     * Values come through CadastralSettings, the same reader the module will
     * bill from, so what this screen shows is what a bill would use -- including
     * the config fallback for a key nobody has saved, marked as such.
     *
     * The schedule and the bands are shown from config, read-only, while their
     * table is empty: editing one row of an empty table would leave a one-row
     * schedule that replaces the whole config list. The admin copies the
     * defaults in first (importCadastralDefaults), then edits.
     */
    private function cadastralData(): array
    {
        $settings = new \App\Services\Cadastral\CadastralSettings();
        $db = DB::connection('sqlsrv');

        $fields = [];
        foreach (\App\Services\Cadastral\CadastralSettings::DEFINITIONS as $key => $meta) {
            $value = $settings->get($key);
            $row = $settings->row($key);
            $fields[$meta['group']][$key] = $meta + [
                'key' => $key,
                'name' => $this->cadastralFieldName($key),
                'value' => $value,
                'text' => $meta['type'] === 'list' ? implode(', ', (array) $value) : $value,
                'source' => $settings->source($key),
                'updated_by' => $row->updated_by ?? null,
                'updated_at' => $row->updated_at ?? null,
            ];
        }

        // Officers & posts. Only these columns: name and rank are what the Land 12
        // dropdown (SurveyReportController) prints, and are left alone on rows that
        // were there before the module.
        $officers = $db->table('cadastral_officers')->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'rank', 'user_id', 'post_code', 'department', 'is_active']);

        $users = $db->table('users')->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'is_active'])
            ->map(function ($u) {
                $u->display = trim($u->first_name . ' ' . $u->last_name) ?: ($u->email ?: 'User #' . $u->id);

                return $u;
            });
        $userNames = $users->pluck('display', 'id');

        // Which report step needs each post, so the screen can say what an
        // unfilled post blocks rather than just that it is empty.
        $neededBy = [];
        foreach ((array) config('cadastral_module.stage_chains', []) as $chain => $steps) {
            foreach ($steps as $step) {
                if (!empty($step['post'])) {
                    $neededBy[$step['post']][] = ucfirst($chain) . ' → ' . $step['name'];
                }
            }
        }

        $scheduleRows = $settings->areaSchedule(false);
        $examples = [];
        foreach ([0.02, 0.45, 1.5, (float) ($settings->maxScheduleHectares() ?? 9) + 0.5] as $ha) {
            $examples[] = ['ha' => $ha, 'fee' => $settings->areaFee($ha)];
        }

        return [
            'cadFields' => $fields,
            'cadGroups' => self::CADASTRAL_SETTING_GROUPS,
            'cadSchedule' => $scheduleRows,
            'cadScheduleSource' => $settings->areaScheduleSource(),
            'cadScheduleColumn' => $settings->areaScheduleColumn(),
            'cadScheduleColumns' => \App\Services\Cadastral\CadastralSettings::SCHEDULE_COLUMNS,
            'cadBetweenRule' => $settings->betweenRowsRule(),
            'cadAboveMax' => $settings->aboveMaxArea(),
            'cadExamples' => $examples,
            'cadBands' => $settings->transportBands(false),
            'cadBandsSource' => $settings->transportBandsSource(),
            'cadPosts' => (array) config('cadastral_module.posts', []),
            'cadNeededBy' => $neededBy,
            'cadOfficers' => $officers,
            'cadUsers' => $users,
            'cadUserNames' => $userNames,
        ];
    }

    /** Form field name for a dotted settings key: dots would read as nesting to the validator. */
    private function cadastralFieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** Refuse a cadastral save before the migration, rather than fail on a missing table. */
    private function requireCadastralTables(): void
    {
        if (!(new \App\Services\Cadastral\CadastralSettings())->isInstalled()) {
            throw WorkflowGuardException::because('The Cadastral settings tables have not been created yet. Run the 2026_10_01_100000 migration first.');
        }
    }

    /**
     * Save one section's settings (fees, area, transport, numbering, file_numbers).
     *
     * Only keys whose value actually changed are written, so `source` stays
     * "config" for the ones an admin never touched and a later change to the
     * shipped default still reaches them. Every write is audited with the old and
     * new values side by side.
     */
    public function saveCadastralSettings(Request $request, string $group)
    {
        $this->authorizeAdmin();

        $definitions = array_filter(
            \App\Services\Cadastral\CadastralSettings::DEFINITIONS,
            fn ($meta) => $meta['group'] === $group
        );
        abort_if($definitions === [], 404);

        $rules = [];
        $names = [];
        foreach ($definitions as $key => $meta) {
            $field = 'settings.' . $this->cadastralFieldName($key);
            $names[$field] = $meta['label'];
            $rules[$field] = match ($meta['type']) {
                'money' => 'required|numeric|min:0|max:100000000',
                'decimal' => 'required|numeric|gt:0|max:1000000',
                'int' => 'required|integer|min:1|max:12',
                'bool' => 'nullable|boolean',
                'choice' => ['required', Rule::in(array_keys($meta['options'] ?? []))],
                'list' => 'nullable|string|max:2000',
                default => 'required|string|max:100',
            };
        }

        $data = $request->validate($rules, [], $names);
        $input = $data['settings'] ?? [];

        return $this->attempt(function () use ($definitions, $input, $group) {
            $this->requireCadastralTables();
            $settings = new \App\Services\Cadastral\CadastralSettings();

            // Normalise what was posted into the value CadastralSettings returns.
            $new = [];
            foreach ($definitions as $key => $meta) {
                $raw = $input[$this->cadastralFieldName($key)] ?? null;
                $new[$key] = match ($meta['type']) {
                    'money', 'decimal' => round((float) $raw, 2),
                    'int' => (int) $raw,
                    'bool' => (bool) $raw,
                    'list' => array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) $raw)), 'strlen'))),
                    default => trim((string) $raw),
                };
            }

            $this->guardCadastralSettings($group, $new);

            $before = [];
            $after = [];
            foreach ($new as $key => $value) {
                $type = $definitions[$key]['type'];
                $old = $settings->get($key);
                // Compare encoded, so 4000 and "4000.00" are the same and a list
                // re-saved in the same order is not a change.
                $oldEncoded = \App\Services\Cadastral\CadastralSettings::encode(
                    in_array($type, ['money', 'decimal'], true) ? round((float) $old, 2) : $old,
                    $type
                );
                if ($oldEncoded === \App\Services\Cadastral\CadastralSettings::encode($value, $type)) {
                    continue;
                }
                $before[$key] = $old;
                $after[$key] = $value;
            }

            if ($after === []) {
                return 'No change - those values are already saved.';
            }

            $db = DB::connection('sqlsrv');
            $db->transaction(function () use ($db, $after, $definitions) {
                foreach ($after as $key => $value) {
                    $meta = $definitions[$key];
                    $values = [
                        'value' => \App\Services\Cadastral\CadastralSettings::encode($value, $meta['type']),
                        'value_type' => $meta['type'],
                        'label' => $meta['label'],
                        'group' => $meta['group'],
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ];

                    // Update-then-insert: sqlsrv has no insertOrIgnore, and the
                    // unique index on key rejects a racing second insert anyway.
                    $updated = $db->table('cadastral_settings')->where('key', $key)->update($values);
                    if (!$updated) {
                        $db->table('cadastral_settings')->insert($values + ['key' => $key, 'created_at' => now()]);
                    }
                }
            });
            \App\Services\Cadastral\CadastralSettings::forget();

            $labels = collect($after)->keys()->map(fn ($k) => $definitions[$k]['label'])->implode(', ');
            app(\App\Services\AuditService::class)->logAction('Cadastral Settings Updated', 'CadastralSetting', null,
                $before, $after, self::CADASTRAL_SETTING_GROUPS[$group] . " changed: {$labels}");

            return self::CADASTRAL_SETTING_GROUPS[$group] . " saved ({$labels}). Bills already issued keep the rates they were issued at.";
        }, 'Cadastral settings saved.');
    }

    /** The cross-field rules the validator cannot express. */
    private function guardCadastralSettings(string $group, array $new): void
    {
        if ($group === 'numbering') {
            foreach (['job_number.format' => 'survey job', 'its_number.format' => 'ITS'] as $key => $what) {
                if (!str_contains($new[$key], '{serial}')) {
                    throw WorkflowGuardException::because("The {$what} number format must contain {serial}, or every number it issues would be the same.");
                }
                // Serials restart each year in SurveyJobNumberGenerator, so a format
                // without {year} would repeat numbers from the second January on.
                if (!str_contains($new[$key], '{year}')) {
                    throw WorkflowGuardException::because("The {$what} number format must contain {year}: serials restart every year, so without it numbers would repeat.");
                }
            }
        }

        if ($group === 'file_numbers') {
            foreach (['file_numbers.direct_prefixes', 'file_numbers.conversion_prefixes'] as $key) {
                foreach ($new[$key] as $prefix) {
                    if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $prefix)) {
                        throw WorkflowGuardException::because("\"{$prefix}\" is not a file-number prefix: letters and digits only, up to 10, e.g. RES or CON.");
                    }
                }
            }

            $overlap = array_intersect(
                array_map('strtoupper', $new['file_numbers.direct_prefixes']),
                array_map('strtoupper', $new['file_numbers.conversion_prefixes'])
            );
            if ($overlap) {
                throw WorkflowGuardException::because('A prefix cannot be both direct and conversion: ' . implode(', ', $overlap) . '. A conversion file skips charting, a direct one does not.');
            }

            if ($new['file_numbers.conversion_prefixes'] === []) {
                throw WorkflowGuardException::because('Keep at least one conversion prefix (CON): without one, no file is ever recognised as a conversion.');
            }

            if ($new['file_numbers.source_registries'] === []) {
                throw WorkflowGuardException::because('Keep at least one source registry, or intake has nothing to offer.');
            }
        }
    }

    /**
     * Copy the shipped area schedule or transport bands into their empty table,
     * so they can be edited. Refused once the table has any row: it is never a
     * "reset", because that would overwrite what an admin has changed.
     */
    public function importCadastralDefaults(Request $request, string $what)
    {
        $this->authorizeAdmin();

        $map = [
            'area-schedule' => ['table' => 'cadastral_area_fee_schedule', 'label' => 'area fee schedule', 'rows' => fn () => \App\Services\Cadastral\CadastralSettings::defaultScheduleRows()],
            'transport-bands' => ['table' => 'cadastral_transport_bands', 'label' => 'transport bands', 'rows' => fn () => \App\Services\Cadastral\CadastralSettings::defaultBandRows()],
        ];
        abort_unless(isset($map[$what]), 404);
        $meta = $map[$what];

        return $this->attempt(function () use ($meta) {
            $this->requireCadastralTables();
            $db = DB::connection('sqlsrv');

            $rows = ($meta['rows'])();
            $db->transaction(function () use ($db, $meta, $rows) {
                // Checked inside the transaction, under a lock, so two admins
                // clicking at once cannot both copy the list in.
                $present = $db->table($meta['table'])->lock('WITH (UPDLOCK, HOLDLOCK)')->count();
                if ($present > 0) {
                    throw WorkflowGuardException::because("The {$meta['label']} already has {$present} row(s); edit those instead.");
                }
                foreach ($rows as $row) {
                    $db->table($meta['table'])->insert($row + ['updated_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
                }
            });
            \App\Services\Cadastral\CadastralSettings::forget();

            app(\App\Services\AuditService::class)->logAction('Cadastral Defaults Copied', 'CadastralSetting', null,
                null, ['table' => $meta['table'], 'rows' => $rows], "Shipped {$meta['label']} copied into {$meta['table']} for editing");

            return 'The official ' . $meta['label'] . ' (' . count($rows) . ' rows) is now editable.';
        }, 'Defaults copied.');
    }

    /**
     * Add or edit one row of the area-fee schedule. Rows are switched off, never
     * deleted: a bill keeps its own copy of the rate, but the row is still the
     * record of what the schedule said.
     */
    public function saveCadastralScheduleRow(Request $request, ?int $row = null)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'hectares' => 'required|numeric|min:0.01|max:99999999',
            'current_fee' => 'nullable|numeric|min:0|max:100000000',
            'proposed_fee' => 'nullable|numeric|min:0|max:100000000',
            'additional_note' => 'nullable|string|max:255',
            'proposed_additional_note' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:10000',
            'is_active' => 'nullable|boolean',
        ], [], ['hectares' => 'Hectares', 'current_fee' => 'Current fee', 'proposed_fee' => 'Proposed fee']);

        return $this->attempt(function () use ($data, $row) {
            $this->requireCadastralTables();
            $db = DB::connection('sqlsrv');
            $table = $db->table('cadastral_area_fee_schedule');

            if (($data['current_fee'] ?? null) === null && ($data['proposed_fee'] ?? null) === null) {
                throw WorkflowGuardException::because('Give the row at least one fee, current or proposed.');
            }

            if (!$row && !$db->table('cadastral_area_fee_schedule')->exists()) {
                throw WorkflowGuardException::because('Copy the official schedule in first. A single row saved into the empty table would replace the whole schedule.');
            }

            $hectares = round((float) $data['hectares'], 2);
            $clash = $db->table('cadastral_area_fee_schedule')->where('hectares', $hectares)
                ->when($row, fn ($q) => $q->where('id', '!=', $row))->exists();
            if ($clash) {
                throw WorkflowGuardException::because('The schedule already has a ' . number_format($hectares, 2) . ' Ha row. Edit that one instead.');
            }

            $before = $row ? (array) ($db->table('cadastral_area_fee_schedule')->where('id', $row)->first() ?? abort(404)) : null;

            $values = [
                'hectares' => $hectares,
                'current_fee' => $data['current_fee'] ?? null,
                'proposed_fee' => $data['proposed_fee'] ?? null,
                'additional_note' => $data['additional_note'] ?? null,
                'proposed_additional_note' => $data['proposed_additional_note'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? ($before['sort_order'] ?? ((int) $db->table('cadastral_area_fee_schedule')->max('sort_order') + 1))),
                // A new row is active; an edited one is what the switch says.
                'is_active' => $row ? (bool) ($data['is_active'] ?? false) : true,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ];

            if ($row) {
                $table->where('id', $row)->update($values);
                $id = $row;
            } else {
                $id = $table->insertGetId($values + ['created_at' => now()]);
            }
            \App\Services\Cadastral\CadastralSettings::forget();

            app(\App\Services\AuditService::class)->logAction($row ? 'Cadastral Area Fee Row Updated' : 'Cadastral Area Fee Row Added',
                'CadastralAreaFeeSchedule', $id, $before ? array_intersect_key($before, $values) : null, $values,
                'Area fee schedule ' . number_format($hectares, 2) . ' Ha ' . ($row ? 'updated' : 'added'));

            return 'Area fee row ' . number_format($hectares, 2) . ' Ha saved' . ($values['is_active'] ? '.' : ' (switched off: bills skip it).');
        }, 'Area fee row saved.');
    }

    /** Add or edit one transport band. Switched off, never deleted. */
    public function saveCadastralBand(Request $request, ?int $band = null)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'label' => 'required|string|max:150',
            'min_km' => 'required|numeric|min:0|max:100000',
            'max_km' => 'nullable|numeric|gte:min_km|max:100000',
            'fee' => 'required|numeric|min:0|max:100000000',
            'sort_order' => 'nullable|integer|min:0|max:10000',
            'is_active' => 'nullable|boolean',
        ], ['max_km.gte' => 'The "to" distance must not be less than the "from" distance.'],
            ['label' => 'Label', 'min_km' => 'From (km)', 'max_km' => 'To (km)', 'fee' => 'Fee']);

        return $this->attempt(function () use ($data, $band) {
            $this->requireCadastralTables();
            $db = DB::connection('sqlsrv');

            if (!$band && !$db->table('cadastral_transport_bands')->exists()) {
                throw WorkflowGuardException::because('Copy the official bands in first. A single band saved into the empty table would replace all three.');
            }

            $before = $band ? (array) ($db->table('cadastral_transport_bands')->where('id', $band)->first() ?? abort(404)) : null;
            $isActive = $band ? (bool) ($data['is_active'] ?? false) : true;

            // Two active bands covering the same kilometre would make the band for
            // a distance depend on row order. Ranges are whole km, inclusive.
            if ($isActive) {
                $min = (float) $data['min_km'];
                $max = ($data['max_km'] ?? null) === null ? INF : (float) $data['max_km'];
                $overlap = $db->table('cadastral_transport_bands')->where('is_active', true)
                    ->when($band, fn ($q) => $q->where('id', '!=', $band))->get()
                    ->first(fn ($b) => $min <= ($b->max_km === null ? INF : (float) $b->max_km) && (float) $b->min_km <= $max);
                if ($overlap) {
                    throw WorkflowGuardException::because("That range overlaps the active band \"{$overlap->label}\". Adjust or switch that one off first.");
                }
            }

            $values = [
                'label' => trim($data['label']),
                'min_km' => $data['min_km'],
                'max_km' => $data['max_km'] ?? null,
                'fee' => $data['fee'],
                'sort_order' => (int) ($data['sort_order'] ?? ($before['sort_order'] ?? ((int) $db->table('cadastral_transport_bands')->max('sort_order') + 1))),
                'is_active' => $isActive,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ];

            if ($band) {
                $db->table('cadastral_transport_bands')->where('id', $band)->update($values);
                $id = $band;
            } else {
                $id = $db->table('cadastral_transport_bands')->insertGetId($values + ['created_at' => now()]);
            }
            \App\Services\Cadastral\CadastralSettings::forget();

            app(\App\Services\AuditService::class)->logAction($band ? 'Cadastral Transport Band Updated' : 'Cadastral Transport Band Added',
                'CadastralTransportBand', $id, $before ? array_intersect_key($before, $values) : null, $values,
                "Transport band {$values['label']} " . ($band ? 'updated' : 'added'));

            return "Transport band \"{$values['label']}\" saved.";
        }, 'Transport band saved.');
    }

    /**
     * Assign a KLAES user to a job post, or change / switch off an assignment.
     *
     * cadastral_officers is the directory the report workflow routes on
     * (CadastralOfficer::postsFor) AND the Land 12 officer dropdown reads
     * (SurveyReportController). One row is one person in one post; a person in
     * two posts has two rows. Rows are never deleted -- switching one off ends the
     * post without losing who held it. On an existing row only user_id, post_code
     * and is_active change; name and rank are what Land 12 prints and stay as
     * they were. A new row takes its name from the user and its rank from the post.
     */
    public function saveCadastralOfficer(Request $request, ?int $officer = null)
    {
        $this->authorizeAdmin();

        $posts = array_keys((array) config('cadastral_module.posts', []));

        $data = $request->validate([
            'user_id' => [$officer ? 'nullable' : 'required', 'integer', Rule::exists('sqlsrv.users', 'id')],
            'post_code' => [$officer ? 'nullable' : 'required', 'string', Rule::in($posts)],
            'is_active' => 'nullable|boolean',
        ], ['user_id.required' => 'Choose the KLAES user who holds the post.', 'post_code.required' => 'Choose the post.'],
            ['user_id' => 'User', 'post_code' => 'Post']);

        return $this->attempt(function () use ($data, $officer) {
            $db = DB::connection('sqlsrv');
            $postLabels = (array) config('cadastral_module.posts', []);

            $before = $officer
                ? (array) ($db->table('cadastral_officers')->where('id', $officer)->first(['id', 'name', 'rank', 'user_id', 'post_code', 'is_active']) ?? abort(404))
                : null;
            $isActive = $officer ? (bool) ($data['is_active'] ?? false) : true;
            $userId = $data['user_id'] ?? null;
            $post = $data['post_code'] ?? null;

            if ($isActive && $userId && $post) {
                $duplicate = $db->table('cadastral_officers')
                    ->where('user_id', $userId)->where('post_code', $post)->where('is_active', true)
                    ->when($officer, fn ($q) => $q->where('id', '!=', $officer))->exists();
                if ($duplicate) {
                    throw WorkflowGuardException::because('That user already holds ' . ($postLabels[$post] ?? $post) . '.');
                }
            }

            $user = $userId ? $db->table('users')->where('id', $userId)->first(['id', 'first_name', 'last_name', 'email']) : null;
            $userName = $user ? (trim($user->first_name . ' ' . $user->last_name) ?: (string) $user->email) : null;

            $values = ['user_id' => $userId, 'post_code' => $post, 'is_active' => $isActive, 'updated_at' => now()];

            if ($officer) {
                $db->table('cadastral_officers')->where('id', $officer)->update($values);
                $id = $officer;
            } else {
                // name and rank are NOT NULL on this table.
                $values += ['name' => $userName ?: 'User #' . $userId, 'rank' => $postLabels[$post] ?? $post, 'created_at' => now()];
                $id = $db->table('cadastral_officers')->insertGetId($values);
            }

            app(\App\Services\AuditService::class)->logAction($officer ? 'Cadastral Officer Updated' : 'Cadastral Officer Added',
                'CadastralOfficer', $id, $before, $values + ['user_name' => $userName],
                ($userName ?: 'No user') . ' · ' . ($post ? ($postLabels[$post] ?? $post) : 'no post') . ($isActive ? '' : ' (inactive)'));

            return $post
                ? ($userName ?: 'Nobody') . ' ' . ($isActive ? 'now holds ' : 'no longer holds ') . ($postLabels[$post] ?? $post) . '.'
                : 'Officer saved.';
        }, 'Officer saved.');
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        $roles = array_map(fn ($r) => strtolower(trim((string) $r)), $user?->assignedRoleNames() ?? []);

        abort_unless($user && ($user->isSuperAdmin() || in_array('system settings', $roles, true)), 403, 'Configurable Entries needs the System Settings role.');
    }
}
