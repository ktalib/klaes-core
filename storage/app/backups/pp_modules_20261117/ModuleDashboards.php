<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The module Dashboards added on the KLAES sidebar outline (KLAES Land Admin Enterprise
 * System (11).docx, 2026-11-10): a "Dashboard" first under CRM, Digital File Archive,
 * Programmes, Information Products, KANGIS LIS, Deeds, Legal Search, Land, DCIV, Physical
 * Planning, PRS, Sectional Titling, SLTR and System Admin. Special Assignment, Survey and
 * Cadastral already had their own and keep them.
 *
 * Each module is defined here once: who may open it (the same module names that show the
 * module on the sidebar), four live figure cards and its quick links. Every figure is a
 * count on the module's own tables, with how many of them were added this month; a table
 * that is missing shows a dash rather than failing the page. Figures are cached for five
 * minutes, the same for every viewer, since they hold no per-user data.
 */
class ModuleDashboards
{
    private const CONN = 'sqlsrv';
    private const CACHE_SECONDS = 300;
    private const OP = 'Occupancy Permit (OP)';

    /** Module keys, in sidebar order. */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /** Whether the user may open the module's dashboard: any module name its sidebar block checks. */
    public function canView(?User $user, string $key): bool
    {
        if (!$user || !self::exists($key)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        foreach (self::definitions()[$key]['roles'] as $role) {
            if ($user->canDo($role, 'view')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page's data: title, description, cards (with their figures) and the links this
     * user may open.
     */
    public function build(string $key, User $user): array
    {
        $def = self::definitions()[$key];
        $figures = Cache::remember("module_dashboard.{$key}", self::CACHE_SECONDS, fn () => array_map(fn ($c) => $this->figure($c), $def['cards']));
        $may = fn (array $item) => empty($item['roles']) || $user->isSuperAdmin()
            || collect($item['roles'])->contains(fn ($r) => $user->canDo($r, 'view'));

        $cards = [];
        foreach ($def['cards'] as $i => $card) {
            $cards[] = [
                'label' => $card['label'],
                'hint' => $card['hint'],
                'icon' => $card['icon'],
                'colour' => $card['colour'],
                'total' => $figures[$i]['total'] ?? null,
                'month' => $figures[$i]['month'] ?? null,
                'url' => $may($card) ? $this->url($card['link'] ?? null) : null,
            ];
        }
        $links = [];
        foreach ($def['links'] as $link) {
            if ($may($link) && ($url = $this->url($link['link']))) {
                $links[] = ['label' => $link['label'], 'icon' => $link['icon'], 'url' => $url];
            }
        }

        return ['key' => $key, 'title' => $def['title'], 'description' => $def['description'], 'icon' => $def['icon'],
            'cards' => $cards, 'links' => $links, 'cachedFor' => (int) (self::CACHE_SECONDS / 60)];
    }

    /** One card's figures: the total and how many were added this month, or nulls when the table is missing. */
    private function figure(array $card): array
    {
        try {
            if (!Schema::connection(self::CONN)->hasTable($card['table'])) {
                return ['total' => null, 'month' => null];
            }
            $q = DB::connection(self::CONN)->table($card['table']);
            if (isset($card['where'])) {
                ($card['where'])($q);
            }
            $date = array_key_exists('date', $card) ? $card['date'] : 'created_at';
            if ($date === null) {
                return ['total' => (int) $q->count(), 'month' => null];
            }
            $row = $q->selectRaw("COUNT(*) AS total, SUM(CASE WHEN [{$date}] >= ? THEN 1 ELSE 0 END) AS month", [now()->startOfMonth()])->first();

            return ['total' => (int) $row->total, 'month' => (int) $row->month];
        } catch (\Throwable $e) {
            report($e);

            return ['total' => null, 'month' => null];
        }
    }

    /** [route name, params, query string] or a path, as a URL; null when the route is not there. */
    private function url($link): ?string
    {
        if ($link === null) {
            return null;
        }
        if (is_string($link)) {
            return url($link);
        }
        [$name, $params, $query] = $link + [1 => [], 2 => ''];
        if (!Route::has($name)) {
            return null;
        }

        return route($name, $params) . ($query !== '' ? '?' . $query : '');
    }

    /* ------------------------------------------------------------------ definitions */

    private static function notDeleted(string $col = 'is_deleted'): \Closure
    {
        return fn (Builder $q) => $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, 0));
    }

    private static function card(string $label, string $table, string $hint, string $icon, string $colour, $link = null, array $roles = [], ?\Closure $where = null, ?string $date = 'created_at'): array
    {
        return compact('label', 'table', 'hint', 'icon', 'colour', 'link', 'roles', 'where', 'date');
    }

    private static function link(string $label, $link, string $icon = 'arrow-right', array $roles = []): array
    {
        return compact('label', 'link', 'icon', 'roles');
    }

    /**
     * Module => definition. 'roles' copies the condition that shows the module on the sidebar
     * (resources/views/admin/menu/partials/modules/<module>.blade.php). A card or link with
     * roles opens only for those, as its sidebar entry does.
     */
    public static function definitions(): array
    {
        $nd = self::notDeleted();

        return [
            'crm' => [
                'title' => 'Customer Relationship Management',
                'description' => 'Persons, corporate bodies and multiple owners on the customer register',
                'icon' => 'users',
                'roles' => ['CRM - Person', 'CRM - Corporate', 'CRM - Customer Manager'],
                'cards' => [
                    self::card('Individuals', 'entities_staging', 'persons on the register', 'user', 'blue', ['entities.index', ['entity_type' => 'Individual']], ['CRM - Person'],
                        fn ($q) => $q->where('entity_type', 'Individual')),
                    self::card('Corporate', 'entities_staging', 'companies and corporate bodies', 'building-2', 'green', ['entities.index', ['entity_type' => 'Corporate']], ['CRM - Corporate'],
                        fn ($q) => $q->whereIn('entity_type', ['Corporate', 'COMPANY'])),
                    self::card('Multiple Owners', 'entities_staging', 'jointly held titles', 'users-round', 'purple', ['entities.index', ['entity_type' => 'Multiple Owners']], ['CRM - Person'],
                        fn ($q) => $q->where('entity_type', 'Multiple')),
                    self::card('Government & Others', 'entities_staging', 'government, emirate councils, religious groups', 'landmark', 'amber', null, [],
                        fn ($q) => $q->where(fn ($w) => $w->whereNotIn('entity_type', ['Individual', 'Corporate', 'COMPANY', 'Multiple'])->orWhereNull('entity_type'))),
                ],
                'links' => [
                    self::link('Individual', ['entities.index', ['entity_type' => 'Individual']], 'user', ['CRM - Person']),
                    self::link('Group', ['entities.index', ['entity_type' => 'Group']], 'users', ['CRM - Person']),
                    self::link('Multiple Owners', ['entities.index', ['entity_type' => 'Multiple Owners']], 'users-round', ['CRM - Person']),
                    self::link('Corporate', ['entities.index', ['entity_type' => 'Corporate']], 'building-2', ['CRM - Corporate']),
                    self::link('Appointment', '/appointment', 'calendar-plus', ['Appointment']),
                    self::link('Appointment Calendar', '/appointment/calendar', 'calendar-days', ['Appointment Calendar']),
                ],
            ],

            'digital_archive' => [
                'title' => 'Digital File Archive',
                'description' => 'Indexed, scanned and page-typed files, and where the paper files are',
                'icon' => 'archive',
                'roles' => ['File Digital Library - Doc-WARE', 'EDMS Update'],
                'cards' => [
                    self::card('Indexed Files', 'file_indexings', 'files on the index', 'folder-open', 'blue', ['filearchive.index'], ['File Digital Library - Doc-WARE'], $nd),
                    self::card('Scanned Documents', 'scannings', 'documents uploaded from the scanners', 'scan', 'green', ['filearchive.index'], ['File Digital Library - Doc-WARE']),
                    self::card('Page-Typed Pages', 'pagetypings', 'pages typed and classified', 'files', 'purple', '/pagetyping?url=ptmore', ['EDMS Update']),
                    self::card('Files Tracked', 'file_tracker', 'paper files with a tracking record', 'map-pin', 'amber'),
                ],
                'links' => [
                    self::link('File Digital Library – Doc-WARE', ['filearchive.index'], 'library', ['File Digital Library - Doc-WARE']),
                    self::link('Scan More', '/scanning?url=scmore', 'scan', ['EDMS Update']),
                    self::link('Type More', '/pagetyping?url=ptmore', 'files', ['EDMS Update']),
                ],
            ],

            'programmes' => [
                'title' => 'Programmes',
                'description' => 'Allocation, resettlement, recertification, conversion and enumeration',
                'icon' => 'layers',
                'roles' => ['Allocation', 'Compensation/Resettlement', 'Recertification - Application', 'Recertification - Bills & Payments',
                    'Recertification - Migrate Data', 'Recertification - Verification Sheet', 'GIS - Data Capture', 'Recertification - Vetting Sheet',
                    'Recertification - EDMS', 'Recertification - Certification', "Recertification - DG's List", 'Recertification - Governors List',
                    'Conversion/Regularization', 'Land Property Enumeration - Data Repository', 'Land Property Enumeration - Migrate Data'],
                'cards' => [
                    self::card('Allocation List', 'allocation_list_stage', 'allottees on the allocation lists', 'list-checks', 'blue', '/programmes/allocation/governors-list', ['Allocation']),
                    self::card('Recertification', 'recertification_applications', 'recertification applications', 'refresh-ccw', 'green', ['recertification.index'], ['Recertification - Application']),
                    self::card('Conversion/Regularization', 'conversion_applications', 'conversion applications', 'repeat', 'purple', '/programmes/regularization', ['Conversion/Regularization']),
                    // is_allocated = taken up by MLS file-number generation, not "land allocated".
                    self::card('Allottees with a File No.', 'allocation_list_stage', 'allocation list entries taken up for an MLS file number', 'hash', 'amber',
                        '/programmes/allocation/governors-list', ['Allocation'], fn ($q) => $q->where('is_allocated', 1)),
                ],
                'links' => [
                    self::link('Allocation – Governors List', '/programmes/allocation/governors-list', 'list', ['Allocation']),
                    self::link('Allocation – Commissioners List', '/programmes/allocation/commissioners-list', 'list', ['Allocation']),
                    self::link('Resettlement – Governors List', '/programmes/resettlement/governors-list', 'home', ['Compensation/Resettlement']),
                    self::link('Recertification', ['recertification.index'], 'refresh-ccw', ['Recertification - Application']),
                    self::link('Conversion/Regularization', '/programmes/regularization', 'repeat', ['Conversion/Regularization']),
                    self::link('Enumeration – Data Repository', '/programmes/enumeration/data-repository', 'database', ['Land Property Enumeration - Data Repository']),
                ],
            ],

            'information_products' => [
                'title' => 'Information Products',
                'description' => 'Letters of Grant / RofO, Occupancy Permits and Certificates of Occupancy',
                'icon' => 'file-badge',
                'roles' => ['Letter of Grant/RofO', 'Occupancy Permit (OP)', 'Site Plan/Parcel Plan', 'Certificate of Occupancy'],
                'cards' => [
                    self::card('RofO Generated', 'land_recommendations', 'Letters of Grant / RofO produced', 'file-check', 'green', ['rofo-staging.index'], ['Letter of Grant/RofO'],
                        fn ($q) => $q->where('rofo_status', 'generated'), 'rofo_generated_at'),
                    self::card('RofO Pending', 'land_recommendations', 'recommendations awaiting their RofO', 'file-clock', 'amber', ['rofo-staging.index'], ['Letter of Grant/RofO'],
                        fn ($q) => $q->where(fn ($w) => $w->where('rofo_status', 'pending')->orWhereNull('rofo_status'))),
                    self::card('Occupancy Permits', 'instrument_capture', 'OPs captured in Deeds', 'file-warning', 'blue', ['ops-dashboard.index'], ['Occupancy Permit (OP)'],
                        fn ($q) => $q->where('instrument_type', self::OP)->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Certificates of Occupancy', 'CofO', 'CofOs on record', 'award', 'purple', '/propertycard/cofo', ['Certificate of Occupancy']),
                ],
                'links' => [
                    self::link('Letter of Grant/RofO', ['rofo-staging.index'], 'file-plus', ['Letter of Grant/RofO']),
                    self::link('Occupancy Permit (OP)', ['ops-dashboard.index'], 'file-warning', ['Occupancy Permit (OP)']),
                    self::link('Site Plan/Parcel Plan', '/documents/site-plan', 'file-text', ['Site Plan/Parcel Plan']),
                    self::link('Certificate of Occupancy', '/propertycard/cofo', 'award', ['Certificate of Occupancy']),
                ],
            ],

            'kangis' => [
                'title' => 'KANGIS LIS',
                'description' => 'KANGIS indexing, recertification and the KANGIS digital archive',
                'icon' => 'map',
                'roles' => ['KANGIS', 'Kangis-Indexing', 'Recertification - Application', 'Recertification - Bills & Payments', 'Recertification - EDMS',
                    'Recertification - Certification', "Recertification - DG's List", 'Recertification - Governors List', 'GIS - e-Registry',
                    'KANGIS - Indexing', 'KANGIS - Recertification', 'KANGIS - File Digital Library', 'KANGIS - Digital Archive'],
                'cards' => [
                    self::card('KANGIS Files Indexed', 'file_indexings', 'indexed files carrying a KANGIS number', 'folder-open', 'blue', ['kangis.indexed-files'], ['KANGIS - Indexing', 'KANGIS', 'Kangis-Indexing'],
                        fn ($q) => $q->where(fn ($w) => $w->whereNotNull('kangis_file_no')->where('kangis_file_no', '!=', '')
                            ->orWhere(fn ($n) => $n->whereNotNull('new_kangis_file_no')->where('new_kangis_file_no', '!=', '')))
                            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Labels Printed', 'kangis_print_label_batch_items', 'KANGIS file labels printed', 'printer', 'green', ['kangis-printlabel.index'], ['KANGIS - Indexing', 'KANGIS', 'Kangis-Indexing']),
                    self::card('Recertification', 'recertification_applications', 'recertification applications', 'refresh-ccw', 'purple', ['recertification.index', ['url' => 'kangis']],
                        ['KANGIS - Recertification', 'Recertification - Application']),
                    self::card('Checkout Approvals', 'kangis_checkout_approvals', 'KANGIS file checkouts approved', 'log-out', 'amber'),
                ],
                'links' => [
                    self::link('File Indexing Assistant', ['kangis.indexed-files'], 'folder-open', ['KANGIS - Indexing', 'KANGIS', 'Kangis-Indexing']),
                    self::link('File History View', ['file-index-view.index', ['url' => 'kangis']], 'history', ['KANGIS - Indexing', 'KANGIS', 'Kangis-Indexing']),
                    self::link('Print Files Label', ['kangis-printlabel.index'], 'printer', ['KANGIS - Indexing', 'KANGIS', 'Kangis-Indexing']),
                    self::link('Recertification', ['recertification.index', ['url' => 'kangis']], 'refresh-ccw', ['KANGIS - Recertification', 'Recertification - Application']),
                    self::link('File Tracker Dashboard', ['file-tracker.dashboard', ['url' => 'kangis']], 'bar-chart-2', ['KANGIS - Digital Archive']),
                    self::link('Log a File', ['create-file-tracker.index', ['url' => 'kangis']], 'file-plus', ['KANGIS - Digital Archive']),
                ],
            ],

            'deeds' => [
                'title' => 'Deeds',
                'description' => 'Property records, instruments, registrations, consent and encumbrances',
                'icon' => 'scroll-text',
                'roles' => ['PRA', 'Bill Balance', 'Valuation', 'Deeds - Consent', 'Deeds Registration', 'Deeds - Encumbrance', 'Deeds - Surrender',
                    'Surrender & Release', 'Parcel/Title Management-Deeds', 'Duplex Parcel Update-Deeds', 'Activity Monitoring'],
                'cards' => [
                    self::card('Property Records (PRA)', 'pra', 'legacy instruments keyed in', 'book-open', 'blue', ['propertycard.index'], ['PRA'], $nd),
                    self::card('Instruments Captured', 'instrument_capture', 'new records captured', 'file-input', 'green', ['instruments.index'], ['Deeds Registration'], $nd),
                    self::card('Registrations', 'deed_registrations', 'instruments registered', 'stamp', 'purple', ['instrument_registration.index'], ['Deeds Registration'], $nd),
                    self::card('Consent Applications', 'consent_applications', 'applications for consent', 'file-signature', 'amber', ['deeds-applications.index'], ['Deeds - Consent']),
                ],
                'links' => [
                    self::link('PRA & PIC User Output Tracking', ['pra-pic-audit.index'], 'activity', ['PRA']),
                    self::link('Property Records Assistant', ['propertycard.index'], 'book-open', ['PRA']),
                    self::link('Instrument Capture', ['instruments.index'], 'file-input', ['Deeds Registration']),
                    self::link('Instrument Registration', ['instrument_registration.index'], 'stamp', ['Deeds Registration']),
                    self::link('Consent Applications', ['deeds-applications.index'], 'file-signature', ['Deeds - Consent']),
                    self::link('Caveat', ['caveat.index'], 'shield-alert', ['Deeds - Encumbrance']),
                    self::link('Bill Balance', ['bill-balance.index'], 'wallet', ['Bill Balance']),
                    self::link('Valuation for Assignment', ['valuation-reports.index'], 'calculator', ['Valuation']),
                ],
            ],

            'legal_search' => [
                'title' => 'Legal Search',
                'description' => 'Official and on-premise searches, online legal search and the PHS portal',
                'icon' => 'search',
                'roles' => ['Deeds - Official (for filing purpose)', 'Deeds - On-Premise (Pay-Per-Search)', 'Deeds - Legal Search Reports'],
                'cards' => [
                    self::card('Searches Made', 'legal_search_logs', 'searches run on-premise', 'search', 'blue', ['legalsearchreports.index'], ['Deeds - Legal Search Reports']),
                    self::card('Search Reports Printed', 'legal_search_logs', 'reports printed for applicants', 'printer', 'green', ['legalsearchreports.index'], ['Deeds - Legal Search Reports'],
                        fn ($q) => $q->where('search_source', 'like', 'legal_search.print%')),
                    self::card('Online Requests', 'legal_search_online_requests', 'online legal search requests', 'globe', 'purple', ['legal-search-online.admin.requests']),
                    self::card('PHS Institutions', 'phs_institutions', 'institutions on the PHS portal', 'landmark', 'amber', ['system-admin.phs.index']),
                ],
                'links' => [
                    self::link('Property Records', ['property-search.index'], 'database'),
                    self::link('Official (for filing purpose)', ['legal_search.index'], 'file-search', ['Deeds - Official (for filing purpose)']),
                    self::link('On-Premise', ['onpremise.index'], 'search', ['Deeds - On-Premise (Pay-Per-Search)']),
                    self::link('Legal Search Reports', ['legalsearchreports.index'], 'file-bar-chart', ['Deeds - Legal Search Reports']),
                    self::link('Online Legal Search Admin', ['legal-search-online.admin.index'], 'globe'),
                    self::link('PHS-P Dashboard', ['system-admin.phs.index'], 'layout-dashboard'),
                ],
            ],

            'land' => [
                'title' => 'Land',
                'description' => 'File numbers, OSS applications, Letters of Grant and file history',
                'icon' => 'map-pinned',
                'roles' => ['Land', 'Allocation List', 'Land One Stop Shop', 'File History View', 'File Search', 'Letter of Grant (RofO)', 'Land to Cad/Survey',
                    'Parcel/Title Management-Land', 'Generate New FileNo (MLSFileNo)', 'EDMS Update', 'Regrant', 'Land Registration'],
                'cards' => [
                    self::card('MLS File Numbers', 'fileNumber', 'file numbers generated and captured', 'hash', 'blue', ['file-numbers.index'], ['Land'],
                        fn ($q) => $q->whereNotNull('mlsfNo')->where('mlsfNo', '!=', '')->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('OSS Applications Pending', 'oss_applications', 'One Stop Shop applications not yet approved', 'inbox', 'amber',
                        ['lands-one-stop-shop.all-applications.index', ['type' => 'change-of-name']], ['Land One Stop Shop'], fn ($q) => $q->where('status', 'pending')),
                    self::card('Land Recommendations', 'land_recommendations', 'recommendations for a Letter of Grant', 'file-check', 'green',
                        ['land-recommendations.index', ['type' => 'ROFO']], ['Letter of Grant (RofO)', 'Regrant']),
                    self::card('Decommissioned Files', 'decommissioned_files', 'files retired from use', 'archive-x', 'purple', ['file-decommissioning.decommissioned'], ['Land']),
                ],
                'links' => [
                    self::link('Allocation List', ['allocation-list.index'], 'list', ['Allocation List']),
                    self::link('Generate New FileNo (MLSFileNo)', ['file-numbers.index'], 'hash', ['Land']),
                    self::link('Land Applications', ['lands-one-stop-shop.all-applications.index', ['type' => 'no-change-of-name']], 'inbox', ['Land']),
                    self::link('OSS Applications', ['lands-one-stop-shop.all-applications.index', ['type' => 'change-of-name']], 'inbox', ['Land One Stop Shop']),
                    self::link('Land Recommendation', ['land-recommendations.index', ['type' => 'ROFO']], 'file-check', ['Letter of Grant (RofO)', 'Regrant']),
                    self::link('RofO', ['land-rofos.index'], 'file-badge', ['Letter of Grant (RofO)', 'Regrant']),
                    self::link('History View', ['file-index-view.index', ['url' => 'land']], 'history', ['File History View', 'File Search']),
                    self::link('File Tracker Dashboard', ['file-tracker.dashboard', ['url' => 'land']], 'bar-chart-2'),
                ],
            ],

            'dciv' => [
                'title' => 'DCIV Management',
                'description' => 'DCIV file numbers, call-ups and title status',
                'icon' => 'folder-key',
                'roles' => ['Generate New FileNo (DCIVFileNo)', 'DCIV - Records', 'DCIV - Digital Archive'],
                'cards' => [
                    self::card('DCIV File Numbers', 'dciv_file_no', 'DCIV file numbers generated', 'hash', 'blue', ['dciv-generation.index'], ['Generate New FileNo (DCIVFileNo)'], $nd),
                    self::card('DCIV Call-ups', 'master_dciv_links', 'files called up to DCIV', 'link', 'green', ['master-dciv-links.index']),
                    self::card('Title Status Pending', 'title_status_applications', 'DCIV title status updates awaiting approval', 'clipboard-list', 'amber', ['title-status.index', [], 'url=dciv'], [],
                        fn ($q) => $q->where('url', 'dciv')->where('status', 'pending')->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Shelf Labels', 'dciv_rack_shelf_labels', 'rack and shelf labels', 'tag', 'purple'),
                ],
                'links' => [
                    self::link('Generate New FileNo (DCIVFileNo)', ['dciv-generation.index'], 'hash', ['Generate New FileNo (DCIVFileNo)']),
                    self::link('DCIV Call-up', ['master-dciv-links.index'], 'link'),
                    self::link('Title Status Update', ['title-status.index', [], 'url=dciv'], 'clipboard-list'),
                    self::link('Quick Search', ['create-file-tracker.quick-search', ['url' => 'dciv']], 'search', ['DCIV - Digital Archive']),
                    self::link('File Digital Library – Doc-WARE', ['filearchive.index', ['url' => 'dciv']], 'library', ['DCIV - Digital Archive']),
                ],
            ],

            'physical_planning' => [
                'title' => 'Physical Planning',
                'description' => 'Conversion, Sectional Titling and SLTR planning work, and the Master JSI',
                'icon' => 'drafting-compass',
                'roles' => ['PHYSICAL PLANNING', 'Shadow File Commissioning', 'PP - Conversion Applications', 'Planning Recommendation - Conversion',
                    'Lands eRegistry', 'Conversion Applications for Memo', 'Bills & Payments - Conversion', 'Planning Recommendation',
                    'ST Applications for Memo', 'ST eRegistry', 'Bills & Payments', 'PP - SLTR Applications', 'PP Director', 'PP Reports',
                    'Master JSI', 'Parcel/Title Management-PP'],
                'cards' => [
                    self::card('Conversion Applications', 'conversion_applications', 'applications for planning recommendation', 'repeat', 'blue',
                        ['physical-planning.regular.planning-recommendation'], ['Planning Recommendation - Conversion']),
                    self::card('ST Primary Applications', 'mother_applications', 'Sectional Titling primary applications', 'building', 'green',
                        ['programmes.approvals.planning_recomm', [], 'url=view'], ['Planning Recommendation']),
                    self::card('SLTR Recommendations', 'sltr_recommendations', 'SLTR planning recommendations', 'file-check', 'purple',
                        ['physical-planning.sltr.planning-recommendation'], ['Planning Recommendation']),
                    self::card('Master JSI Reports', 'master_jsi_reports', 'joint site inspection reports', 'clipboard-check', 'amber', ['master-jsi.index'], ['Master JSI', 'Parcel/Title Management-PP']),
                ],
                'links' => [
                    self::link('Title Status Update', ['title-status.index', [], 'url=pp'], 'clipboard-list'),
                    self::link('Shadow File Commissioning', ['lands-file-no-matching.index'], 'copy', ['Shadow File Commissioning']),
                    self::link('Conversion – Planning Recommendation', ['physical-planning.regular.planning-recommendation'], 'file-check', ['Planning Recommendation - Conversion']),
                    self::link('ST – Planning Recommendation', ['programmes.approvals.planning_recomm', [], 'url=view'], 'building', ['Planning Recommendation']),
                    self::link('SLTR – Planning Recommendation', ['physical-planning.sltr.planning-recommendation'], 'file-check', ['Planning Recommendation']),
                    self::link('Master JSI', ['master-jsi.index'], 'clipboard-check', ['Master JSI', 'Parcel/Title Management-PP']),
                    self::link('Master JSI Approval', ['master-jsi.approvals'], 'check-circle', ['PP Director']),
                    self::link('PP Reports', '/physical-planning/reports', 'bar-chart-3', ['PP Reports']),
                ],
            ],

            'prs' => [
                'title' => 'PRS Management',
                'description' => 'Deeds registrations behind the PRS annual report',
                'icon' => 'bar-chart-3',
                'roles' => ['prs'],
                'cards' => [
                    self::card('Registrations', 'deed_registrations', 'instruments registered, all years', 'stamp', 'blue', ['prs-report.index'], [], $nd),
                    self::card('This Year', 'deed_registrations', 'registered since 1 January', 'calendar', 'green', ['prs-report.index'], [],
                        fn ($q) => $q->where('deeds_date', '>=', now()->startOfYear()->toDateString())->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Certificates of Occupancy', 'deed_registrations', 'CofOs registered', 'award', 'purple', ['prs-report.index'], [],
                        fn ($q) => $q->where('instrument_type', 'like', '%Certificate of Occupancy%')->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Assignments', 'deed_registrations', 'deeds of assignment registered', 'arrow-right-left', 'amber', ['prs-report.index'], [],
                        fn ($q) => $q->where('instrument_type', 'like', '%Assignment%')->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                ],
                'links' => [
                    self::link('PRS Annual Report', ['prs-report.index'], 'bar-chart-3'),
                ],
            ],

            'sectional_titling' => [
                'title' => 'Sectional Titling',
                'description' => 'ST file numbers, primary and unit applications and certificates',
                'icon' => 'building-2',
                'roles' => ['ST - Overview', 'ST - Primary Application', 'ST - Unit Application', 'ST - Applications', 'ST - Field Data Integration',
                    'ST - Bills & Payments', 'ST - Approvals', 'ST - Approvals (Other Departments)', 'ST - ST Memo', 'ST - Certificate',
                    'ST - e-Registry', 'ST - GIS', 'ST - Survey', 'ST - Reports', 'ST - Sectional Titling BaseMap'],
                'cards' => [
                    self::card('ST File Numbers', 'st_file_numbers', 'ST file numbers generated', 'hash', 'blue', ['st-file-numbers.index'], ['New ST FileNo']),
                    self::card('Primary Applications', 'mother_applications', 'primary (scheme) applications', 'building', 'green', ['sectionaltitling.primary', [], 'url=infopro'],
                        ['ST - Primary Application', 'ST - Applications']),
                    self::card('Unit Applications', 'subapplications', 'unit applications', 'door-open', 'purple', ['sectionaltitling.units'], ['ST - Unit Application', 'ST - Applications']),
                    self::card('ST CofOs', 'st_cofo', 'Sectional Titling CofOs', 'award', 'amber', ['programmes.cofo_complete'], ['ST - Certificate']),
                ],
                'links' => [
                    self::link('Overview', ['sectionaltitling.index'], 'layout-grid', ['ST - Overview']),
                    self::link('New ST FileNo', ['st-file-numbers.index'], 'hash', ['New ST FileNo']),
                    self::link('Primary Applications', ['sectionaltitling.primary', [], 'url=infopro'], 'building', ['ST - Primary Application', 'ST - Applications']),
                    self::link('Parented Units', ['sectionaltitling.units'], 'door-open', ['ST - Unit Application', 'ST - Applications']),
                    self::link('Bills', ['programmes.bills'], 'receipt', ['ST - Bills & Payments']),
                    self::link('CofO', ['programmes.cofo_complete'], 'award', ['ST - Certificate']),
                    self::link('File Tracker Dashboard', ['file-tracker.dashboard', ['url' => 'st']], 'bar-chart-2', ['ST - e-Registry']),
                ],
            ],

            'sltr' => [
                'title' => 'SLTR/First Registration',
                'description' => 'Systematic Land Titling and Registration: indexing, applications and certificates',
                'icon' => 'map',
                'roles' => ['SLTR - Overview', 'SLTR - Indexing', 'SLTR - Form', 'SLTR - Application', 'SLTR - Claimants', 'SLTR - Legacy Data',
                    'SLTR - Field Data', 'SLTR - Payments', 'SLTR - Approvals', 'SLTR - Other Departments', 'SLTR - Memo', 'SLTR - Certificate',
                    'SLTR - Digital Archive', 'SLTR - GIS', 'SLTR - Survey', 'SLTR - Reports', 'SLTR - CofO Front Page', 'SLTR - CofO'],
                'cards' => [
                    self::card('SLTR Files Indexed', 'file_indexings', 'indexed files with an SLTR number', 'folder-open', 'blue', ['sltr.indexed-files'], ['SLTR - Indexing'],
                        fn ($q) => $q->where('file_number', 'like', 'SLTR-%')->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))),
                    self::card('Labels Printed', 'sltr_print_label_batch_items', 'SLTR file labels printed', 'printer', 'green', ['sltr-printlabel.index'], ['SLTR - Indexing']),
                    self::card('Recommendations', 'sltr_recommendations', 'SLTR recommendations', 'file-check', 'purple', ['sltr-recommendations.index'],
                        ['SLTR - Certificate', 'SLTR - GIS', 'SLTR - Survey', 'SLTR - Reports']),
                    self::card('Shelf Labels', 'sltr_rack_shelf_labels', 'SLTR rack and shelf labels', 'tag', 'amber', ['sltr-printlabel.index'], ['SLTR - Indexing']),
                ],
                'links' => [
                    self::link('Overview', ['sltroverview.index'], 'layout-grid', ['SLTR - Overview']),
                    self::link('File Indexing Assistant', ['sltr.indexed-files'], 'folder-open', ['SLTR - Indexing']),
                    self::link('Print Files Label', ['sltr-printlabel.index'], 'printer', ['SLTR - Indexing']),
                    self::link('Application', ['sltrapplication.index'], 'file-plus', ['SLTR - Application']),
                    self::link('Field Data', ['sltr_field_data.index'], 'map-pin', ['SLTR - Field Data']),
                    self::link('Recommendation', ['sltr-recommendations.index'], 'file-check', ['SLTR - Certificate', 'SLTR - GIS', 'SLTR - Survey', 'SLTR - Reports']),
                    self::link('CofO', ['sltr-cofo.index'], 'award', ['SLTR - CofO']),
                ],
            ],

            'system_admin' => [
                'title' => 'System Admin',
                'description' => 'User accounts, sign-ins and the audit trail',
                'icon' => 'settings',
                'roles' => ['User Account', 'Departments', 'User Roles', 'Land Officers', 'Digital Signature Control', 'System Settings'],
                'cards' => [
                    self::card('User Accounts', 'users', 'accounts on KLAES', 'users', 'blue', ['users.index'], ['User Account']),
                    self::card('Active Accounts', 'users', 'accounts allowed to sign in', 'user-check', 'green', ['users.index'], ['User Account'],
                        fn ($q) => $q->where('is_active', 1)),
                    self::card('Activity Log Entries', 'user_activity_logs', 'user activity recorded', 'activity', 'purple', ['user-activity-logs.index', [], 'url=admin'], ['User Account']),
                    self::card('Audit Entries', 'audit_logs', 'changes recorded in the audit trail', 'shield-check', 'amber', ['activity-monitoring.index', [], 'url=admin'], ['Activity Monitoring']),
                ],
                'links' => [
                    self::link('User Account', ['users.index'], 'users', ['User Account']),
                    self::link('Activity Logs', ['user-activity-logs.index', [], 'url=admin'], 'activity', ['User Account']),
                    self::link('Configurable Entries', ['configurable-entries.index'], 'sliders-horizontal', ['System Settings']),
                    self::link('Digital Signature Control', ['digital-signatures.index'], 'pen-tool', ['Land Officers', 'Digital Signature Control']),
                    self::link('System Settings', ['setting.index'], 'settings', ['System Settings']),
                    self::link('KLAES Documentation', ['system-admin.klaes-documentation'], 'book-open'),
                ],
            ],
        ];
    }
}
