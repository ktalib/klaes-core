<?php

namespace App\Services\Vfs;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\TitleStatusApplication;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\LegalSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only assembler for the Virtual Folder System.
 *
 * Every method here READS. Nothing in this class writes, moves, renames or deletes
 * anything — the whole point of the VFS layer is that a logical view is assembled from
 * pointers while the physical EDMS folders stay exactly as scanned.
 *
 * This is the first cut, so it is built entirely on tables that already exist:
 *   - file_indexings            the folio identity, plus prop_id lineage columns
 *   - pagetypings + scannings   the pages, grouped into virtual folders by page_type
 *   - title_status_applications the legal status events (TSU)
 *   - EdmsDocumentPathResolver  the one place that turns a stored path into a URL
 *
 * What it deliberately CANNOT do yet, and says so in its output rather than faking:
 *   - historical page versions ("what did folio 41 look like in 2021")
 *   - enforcing a freeze; the banner is advisory until FolioTransactionGuard exists
 *   - structured litigation detail; there is no court-case table, only free text
 */
class VfsFolioPresenter
{
    /** Tiles per page. The archive viewer loads every page at once; this must not. */
    public const PER_PAGE = 60;

    /** Hard ceiling regardless of what the request asks for. */
    private const MAX_PER_PAGE = 120;

    /**
     * The register shows files that actually have scanned pages by default.
     *
     * Roughly 95% of file_indexings rows have no pagetypings at all, so an
     * unfiltered register would be almost entirely files the explorer cannot open.
     * The other scopes ("all", "unmapped", "temporary") stay one click away.
     */
    public const DEFAULT_SCOPE = 'mapped';

    /** Pseudo-state meaning "any active restriction", not one particular kind. */
    public const STATE_ANY_HOLD = 'ANY_HOLD';

    /**
     * Bindings per IN(...) / OR block when querying a family.
     *
     * SQL Server refuses a statement with more than 2,100 parameters, and a
     * subdivision mother can have hundreds of related files, so every family query
     * is chunked. 400 leaves generous headroom because the register block binds two
     * parameters per candidate.
     */
    private const QUERY_CHUNK = 400;

    /**
     * Most related files shown for one folio.
     *
     * A large subdivision legitimately produces hundreds of children; listing all of
     * them would flood the panel and the queries behind it. The UI says when the list
     * was cut rather than silently showing a partial family as if it were complete.
     */
    private const MAX_RELATED = 500;

    /** Exposed so the view can name the cap it is reporting. */
    public static function maxRelated(): int
    {
        return self::MAX_RELATED;
    }

    /**
     * Canonical legal state => how the banner presents it.
     *
     * Colours are Tailwind 2.2.19 DEFAULT palette only. The app loads Tailwind from
     * CDN, where slate-*, amber-* and emerald-* do not exist and arbitrary values
     * like bg-red-600/90 are inert. yellow-500 under white text measures ~2.2:1 and
     * fails WCAG AA, so withdrawal uses yellow-800 (~7:1) and still reads as amber.
     */
    private const STATE_PRESENTATION = [
        'LITIGATION_HOLD' => [
            // Displayed as "LITIGATION". The key keeps the _HOLD suffix because it is
            // a filter value in live URLs and a lookup key in TYPE_TO_STATE,
            // STAMP_TO_STATE and SEVERITY — renaming it would break saved links.
            'label' => 'Litigation',
            'headline' => 'LITIGATION — all transactions suspended',
            'banner' => 'bg-red-600',
            'chip' => 'bg-red-600 text-white',
            'rail' => 'bg-red-600',
            'icon' => 'lock',
            'restricting' => true,
        ],
        'CANCELLED' => [
            'label' => 'Cancelled',
            'headline' => 'CANCELLED — title void',
            'banner' => 'bg-gray-800',
            'chip' => 'bg-gray-800 text-white',
            'rail' => 'bg-gray-800',
            'icon' => 'x-circle',
            'restricting' => true,
            'watermark' => 'CANCELLED',
        ],
        'WITHDRAWN' => [
            'label' => 'Withdrawn',
            'headline' => 'WITHDRAWN',
            'banner' => 'bg-yellow-800',
            'chip' => 'bg-yellow-800 text-white',
            'rail' => 'bg-yellow-800',
            'icon' => 'alert-triangle',
            'restricting' => true,
        ],
        'REVOKED' => [
            'label' => 'Revoked',
            'headline' => 'REVOKED — title extinguished',
            'banner' => 'bg-red-700',
            'chip' => 'bg-red-700 text-white',
            'rail' => 'bg-red-700',
            'icon' => 'lock',
            'restricting' => true,
            'cascade' => true,
        ],
        'SURRENDERED' => [
            'label' => 'Surrendered',
            'headline' => 'SURRENDERED',
            'banner' => 'bg-gray-800',
            'chip' => 'bg-gray-800 text-white',
            'rail' => 'bg-gray-800',
            'icon' => 'x-circle',
            'restricting' => true,
        ],
        'CLOSED' => [
            'label' => 'Closed',
            'headline' => 'CLOSED — administrative closure',
            'banner' => 'bg-gray-500',
            'chip' => 'bg-gray-500 text-white',
            'rail' => 'bg-gray-500',
            'icon' => 'archive',
            'restricting' => true,
        ],
        'AMENDED' => [
            'label' => 'Amended',
            'headline' => 'AMENDED — see history',
            'banner' => 'bg-blue-600',
            'chip' => 'bg-blue-600 text-white',
            'rail' => 'bg-blue-600',
            'icon' => 'info',
            'restricting' => false,
        ],
    ];

    /**
     * Registry => badge colour.
     *
     * Tailwind 2.2.19 DEFAULT palette only (the CDN build has no sky/teal/cyan), and
     * -100 background with -800 text clears WCAG AA comfortably.
     *
     * Red is deliberately absent: it is reserved for legal status, and a registry
     * badge in the same colour as a litigation hold would read as an alarm.
     */
    private const REGISTRY_BADGES = [
        'Lands Registry' => 'bg-green-100 text-green-800',
        'Deeds Registry' => 'bg-indigo-100 text-indigo-800',
        'DCIV Registry' => 'bg-purple-100 text-purple-800',
        'KANGIS Registry' => 'bg-blue-100 text-blue-800',
        'SLTR Registry' => 'bg-yellow-100 text-yellow-800',
        'ST Registry' => 'bg-pink-100 text-pink-800',
        'Survey Registry' => 'bg-indigo-100 text-indigo-800',
        'Cadastral Registry' => 'bg-gray-200 text-gray-800',
        'Secret Registry' => 'bg-gray-800 text-white',
        'Physical Planning Registry' => 'bg-purple-100 text-purple-800',
        'SIT Registry' => 'bg-pink-100 text-pink-800',
    ];

    /**
     * A registry as a coloured badge.
     *
     * The column stores ids ("3"), short forms ("SLTR") and display names, so the
     * label is resolved first and the colour keyed off the canonical name — otherwise
     * the same registry would take three different colours on one screen.
     *
     * @return array{label:string,class:string}
     */
    public function registryBadge($registry): array
    {
        $label = $this->registryLabel($registry);

        return [
            'label' => $label,
            'class' => self::REGISTRY_BADGES[$label] ?? 'bg-gray-100 text-gray-700',
        ];
    }

    /**
     * Relationship type => badge colour and icon.
     *
     * Keyed on the register's own transaction_type spellings, lowercased, so the
     * colour survives the casing drift in the column. Same palette constraint as
     * REGISTRY_BADGES: Tailwind 2.2.19 DEFAULTS only, and red stays reserved for
     * legal status.
     *
     * The two kinds of link are deliberately coloured apart. A recertification is
     * the SAME parcel wearing another registry's number, so it takes that
     * registry's colour. A parcel event CHANGED the parcel, so it takes a warmer
     * one. Reading the panel should not require reading the group heading.
     */
    private const LINK_TYPE_BADGES = [
        // Same property, other registry — coloured to match the registry itself.
        'kangis recertification' => ['bg-blue-100 text-blue-800', 'copy'],
        'ministry of land & physical planning recertification' => ['bg-purple-100 text-purple-800', 'copy'],
        'recertification' => ['bg-blue-100 text-blue-800', 'copy'],
        'related file' => ['bg-gray-100 text-gray-700', 'link'],

        // Parcel events — this parcel's shape or purpose actually changed.
        'subdivision' => ['bg-yellow-100 text-yellow-800', 'scissors'],
        'merger' => ['bg-indigo-100 text-indigo-800', 'combine'],
        'change of purpose' => ['bg-purple-100 text-purple-800', 'repeat'],
        're-grant' => ['bg-green-100 text-green-800', 'award'],
        'mother file' => ['bg-pink-100 text-pink-800', 'git-branch'],
    ];

    /**
     * A relationship type as a coloured badge.
     *
     * An unrecorded type is NOT given a colour of its own — it gets the neutral
     * grey and says so in words. Inventing a confident-looking badge for a link
     * the register never classified is exactly the kind of false precision this
     * panel was rebuilt to remove.
     *
     * @return array{label:string,class:string,icon:string,known:bool}
     */
    public function linkTypeBadge(?string $type): array
    {
        $key = strtolower(trim((string) $type));
        $known = isset(self::LINK_TYPE_BADGES[$key]);

        return [
            'label' => $known ? $type : 'not recorded',
            'class' => $known ? self::LINK_TYPE_BADGES[$key][0] : 'bg-gray-100 text-gray-500',
            'icon' => $known ? self::LINK_TYPE_BADGES[$key][1] : 'help-circle',
            'known' => $known,
        ];
    }

    /**
     * Stored title_type => canonical state.
     *
     * Both spellings are live in the data: the standalone Title Status module writes
     * the TitleStatusApplication constants while the indexing form posts its own
     * shorter values. This map folds every variant, exactly as
     * FileIndexingTitleStatusLookup::TYPE_ALIASES does for the capture form. Do not
     * invent a third spelling here.
     */
    private const TYPE_TO_STATE = [
        'Litigation' => 'LITIGATION_HOLD',

        'Cancellation' => 'CANCELLED',
        'Cancellation (RofO)' => 'CANCELLED',

        'Withdrawal (Application)' => 'WITHDRAWN',
        'Withdrawal (Allocation)' => 'WITHDRAWN',
        'Withdrawal' => 'WITHDRAWN',

        'Revoke' => 'REVOKED',
        'Revoke (CofO)' => 'REVOKED',
        'Revocation' => 'REVOKED',

        'Surrender' => 'SURRENDERED',

        'Closed' => 'CLOSED',
        'Closed To' => 'CLOSED',
        'Continued From' => 'CLOSED',

        'Amendment/Reconsideration (Application/RofO/CofO)' => 'AMENDED',
        'Amendment' => 'AMENDED',
    ];

    /**
     * A THIRD vocabulary: file_indexings.title_status_type.
     *
     * The indexing form stamps this column with lowercase snake_case values, and a
     * file can carry SEVERAL of them comma-separated ("subdivision, cancelled").
     * That is neither the TitleStatusApplication constants nor the capture form's
     * option values, so it needs its own map — do not try to fold it into
     * TYPE_TO_STATE, and do not invent a fourth spelling anywhere else.
     *
     * In practice this column, not title_status_applications, is where the legal
     * status of most files actually lives.
     *
     * Values deliberately NOT mapped: subdivision, merger, separation, extension,
     * change_of_purpose, change_of_name, regranted, resettled (structural, not legal)
     * and "flagged", which is an internal marker with no defined legal meaning —
     * presenting it as a lock would assert something the data does not say.
     */
    private const STAMP_TO_STATE = [
        'litigation' => 'LITIGATION_HOLD',
        'cancelled' => 'CANCELLED',
        'canceled' => 'CANCELLED',
        'revoked' => 'REVOKED',
        'withdrawn' => 'WITHDRAWN',
        'surrendered' => 'SURRENDERED',
        'closed' => 'CLOSED',
        'amendment' => 'AMENDED',
        'amended' => 'AMENDED',
    ];

    /**
     * Most severe first. When a file carries several restrictions the banner leads
     * with the worst, and says how many others are in force rather than hiding them.
     */
    private const SEVERITY = [
        'REVOKED', 'CANCELLED', 'LITIGATION_HOLD', 'WITHDRAWN', 'SURRENDERED', 'CLOSED', 'AMENDED',
    ];

    /** Types that describe a parcel transformation (SPU/APU), not a legal restriction. */
    private const STRUCTURAL_TYPES = [
        'Subdivision', 'Merger', 'Separation', 'Extension',
        'Plot Extension', 'File Extension',
        'Change of Purpose', 'Change of Name',
        'Re-grant', 'Re-granted From', 'Re-granted To',
        'Resettlement', 'Resettled From', 'Resettled To',
    ];

    /** id => display name, loaded once per request. */
    private ?array $pageTypeNames = null;
    private ?array $pageSubTypeNames = null;

    public function __construct(private EdmsDocumentPathResolver $paths)
    {
    }

    /**
     * Resolve a stored page_type / page_subtype to something a person can read.
     *
     * pagetypings.page_type is a VARCHAR carrying two different things: the numeric
     * id of a PageType row for anything typed through the current tool, and legacy
     * free text ("APPLICATION FOR RIGHT OF OCCUPANCY") for older records. A SQL join
     * on id would throw a conversion error on the free-text rows, so the lookup runs
     * in PHP and the raw value is kept as the folder key either way.
     */
    private function typeName(?string $stored, bool $subtype = false): string
    {
        $stored = trim((string) $stored);

        if ($stored === '') {
            return '';
        }

        if (!is_numeric($stored)) {
            return $stored;
        }

        if ($subtype) {
            $this->pageSubTypeNames ??= DB::connection('sqlsrv')
                ->table('PageSubType')->pluck('PageSubType', 'id')->all();

            return $this->pageSubTypeNames[(int) $stored] ?? ('Subtype ' . $stored);
        }

        $this->pageTypeNames ??= DB::connection('sqlsrv')
            ->table('PageType')->pluck('PageType', 'id')->all();

        return $this->pageTypeNames[(int) $stored] ?? ('Type ' . $stored);
    }

    /** Canonical registry display name; the column stores ids, short forms and names. */
    public function registryLabel($registry): string
    {
        return $this->paths->registryName($registry);
    }

    // ---------------------------------------------------------------- register

    /**
     * The /vfs register: indexed files with their legal status resolved per row.
     *
     * Status is resolved for the whole page in ONE query rather than per row, so a
     * hold is visible while scanning the list without N+1 lookups.
     *
     * @param  array{q?:string,registry?:string,state?:string,scope?:string}  $filters
     */
    public function register(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->registerQuery($filters)
            ->select([
                'id', 'file_number', 'temp_file_no', 'file_title', 'registry',
                'prop_id', 'parent_prop_id', 'plot_number', 'land_use_type', 'location',
                'district', 'lga', 'street_name', 'current_holder', 'original_holder',
                'title_status', 'title_status_type', 'title_status_remark',
                'has_temp_file', 'is_decommissioned', 'is_merged', 'is_updated', 'created_at',
            ]);

        $paginator = $query->orderBy('file_number')->paginate($perPage)->withQueryString();

        $this->attachRegisterMetadata($paginator->getCollection());

        return $paginator;
    }

    /**
     * How many files the SAME search would return under a different scope.
     *
     * The register defaults to "mapped", and 95% of indexed files have never been
     * scanned. So an exact file number that exists can return an empty table, and
     * "No indexed files match this search" is then simply false — the file is
     * there, the scope is hiding it. The view uses this to say so and offer the
     * switch, instead of letting staff conclude the record does not exist.
     */
    public function countUnderScope(array $filters, string $scope): int
    {
        return $this->registerQuery(array_merge($filters, ['scope' => $scope]))->count();
    }

    /**
     * Every register filter except the select list, ordering and pagination.
     *
     * Shared verbatim by register() and countUnderScope() so the two can never
     * disagree — a count that used different rules from the table it describes
     * would be worse than no count at all.
     */
    private function registerQuery(array $filters)
    {
        $query = FileIndexing::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $like = '%' . $term . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_number', 'like', $like)
                    ->orWhere('temp_file_no', 'like', $like)
                    ->orWhere('file_title', 'like', $like)
                    ->orWhere('plot_number', 'like', $like)
                    ->orWhere('prop_id', 'like', $like)
                    ->orWhere('current_holder', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('district', 'like', $like);
            });
        }

        if ($registry = trim((string) ($filters['registry'] ?? ''))) {
            $query->where('registry', $registry);
        }

        $scope = $filters['scope'] ?? self::DEFAULT_SCOPE;

        if ($scope === 'temporary') {
            $query->where(function ($q) {
                $q->where('has_temp_file', 1)
                    ->orWhereNotNull('temp_file_no')
                    ->orWhere('file_number', 'like', '%(T)');
            });
        }

        // Most indexed files have no scanned pages at all, so an unfiltered register
        // would be mostly rows the explorer cannot open. "mapped" is the default and
        // the UI says so; "all" and "unmapped" remain one click away.
        if ($scope === 'mapped') {
            $query->whereExists(fn ($q) => $q->selectRaw(1)
                ->from('pagetypings')
                ->whereColumn('pagetypings.file_indexing_id', 'file_indexings.id'));
        } elseif ($scope === 'unmapped') {
            $query->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('pagetypings')
                ->whereColumn('pagetypings.file_indexing_id', 'file_indexings.id'));
        }

        // Legal status filters in SQL against the indexing stamp, so the result count
        // is the real total rather than whatever happened to land on one page.
        // ANY_HOLD is the whole restricted set — the same population the "under legal
        // hold" tile counts, so clicking that tile lands on exactly its own number.
        if ($state = trim((string) ($filters['state'] ?? ''))) {
            $query->where(fn ($q) => $this->applyStampFilter(
                $q,
                $state === self::STATE_ANY_HOLD ? null : $state
            ));
        }

        return $query;
    }

    /**
     * Constrain a query to files whose title_status_type stamp carries a legal state.
     *
     * A null $state matches ANY restriction. The column is comma-separated free text,
     * so each keyword is matched with LIKE and bounded by the delimiters that can
     * surround it — matching bare "%closed%" would also catch a value like
     * "foreclosed" if one is ever introduced.
     */
    private function applyStampFilter($query, ?string $state)
    {
        $keywords = array_keys(array_filter(
            self::STAMP_TO_STATE,
            fn ($mapped) => $state === null
                ? (self::STATE_PRESENTATION[$mapped]['restricting'] ?? false)
                : $mapped === $state
        ));

        foreach ($keywords as $keyword) {
            $query->orWhere('title_status_type', $keyword)
                ->orWhere('title_status_type', 'like', $keyword . ',%')
                ->orWhere('title_status_type', 'like', '%, ' . $keyword)
                ->orWhere('title_status_type', 'like', '%, ' . $keyword . ',%');
        }

        return $query;
    }

    /** Counts for the register's stat tiles. */
    public function registerStats(): array
    {
        $indexed = FileIndexing::query()
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->count();

        $temporary = FileIndexing::query()
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->where('has_temp_file', 1)->orWhereNotNull('temp_file_no'))
            ->count();

        // Counted off the indexing stamp, not title_status_applications: the standalone
        // module holds barely any approved rows, while the stamp is where the status of
        // most files actually lives.
        $onHold = FileIndexing::query()
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $this->applyStampFilter($q, null))
            ->count();

        $unmapped = $indexed - DB::connection('sqlsrv')
            ->table('pagetypings')
            ->distinct()
            ->count('file_indexing_id');

        return [
            'indexed' => $indexed,
            'on_hold' => $onHold,
            'temporary' => $temporary,
            'unmapped' => max($unmapped, 0),
        ];
    }

    /**
     * Distinct registry values, for the register's filter dropdown.
     *
     * The column holds some legacy numeric values ("1", "2", "3") alongside the real
     * names. Those are dropped from the dropdown — they would offer a filter that
     * means nothing to staff — but they are NOT excluded from the register itself,
     * so no file is hidden.
     */
    public function registries(): Collection
    {
        return FileIndexing::query()
            ->select('registry')
            ->whereNotNull('registry')
            ->where('registry', '<>', '')
            ->distinct()
            ->orderBy('registry')
            ->pluck('registry')
            ->reject(fn ($value) => is_numeric(trim((string) $value)))
            ->values();
    }

    /** Resolve status and page count for a page of register rows, without N+1. */
    private function attachRegisterMetadata(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $pageCounts = DB::connection('sqlsrv')
            ->table('pagetypings')
            ->select('file_indexing_id', DB::raw('count(*) as total'))
            ->whereIn('file_indexing_id', $rows->pluck('id')->all())
            ->groupBy('file_indexing_id')
            ->pluck('total', 'file_indexing_id');

        $fileNumbers = $rows->flatMap(fn ($row) => array_filter([$row->file_number, $row->temp_file_no]))
            ->unique()
            ->values()
            ->all();

        $statusRows = empty($fileNumbers)
            ? collect()
            : TitleStatusApplication::query()
                ->whereIn('file_no', $fileNumbers)
                ->where('status', TitleStatusApplication::STATUS_APPROVED)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->get()
                ->groupBy('file_no');

        foreach ($rows as $row) {
            $row->vfs_pages = (int) ($pageCounts[$row->id] ?? 0);

            $events = collect([$row->file_number, $row->temp_file_no])
                ->filter()
                ->flatMap(fn ($no) => $statusRows->get($no, collect()));

            // The standalone Title Status module is not the only writer: the indexing
            // form stamps its own title_status_type onto the file record. Prefer an
            // approved TSU row, fall back to the stamp, so a status captured either
            // way is visible on the register.
            $row->vfs_state = $this->activeState($events) ?: $this->stateFromStamp($row);
            $row->vfs_is_temporary = $this->isTemporary($row);
            $row->vfs_registry_label = $this->registryLabel($row->registry);
            $row->vfs_display_number = $this->displayNumber($row);
            $row->vfs_registry_badge = $this->registryBadge($row->registry);
        }
    }

    // ------------------------------------------------------------------ folio

    /** Header facts for one folio. */
    public function summary(FileIndexing $file): array
    {
        return [
            'id' => $file->id,
            'file_number' => $file->file_number,
            'temp_file_no' => $file->temp_file_no,
            'display_number' => $this->displayNumber($file),
            'file_title' => $file->file_title,
            'registry' => $this->registryLabel($file->registry),
            'registry_badge' => $this->registryBadge($file->registry),
            'prop_id' => $file->prop_id,
            'parent_prop_id' => $file->parent_prop_id,
            'plot_no' => $file->plot_number,
            'land_use' => $file->land_use_type,
            'holder' => $file->current_holder ?: $file->original_holder,
            'location' => collect([$file->street_name, $file->location ?: $file->district, $file->lga])->filter()->implode(', '),
            'is_temporary' => $this->isTemporary($file),
            'page_count' => $file->pagetypings()->count(),
            'structural_status' => $this->structuralStatus($file),
            'structural_class' => $this->structuralClass($this->structuralStatus($file)),
        ];
    }

    /** The active legal restriction, or null when the folio is unrestricted. */
    public function status(FileIndexing $file): ?array
    {
        return $this->activeState($this->statusEvents($file)) ?: $this->stateFromStamp($file);
    }

    /**
     * Status read straight off the indexing record's own title_status_type stamp.
     *
     * Used only when no approved title_status_applications row exists, because the
     * indexing form writes this column directly. It carries no authority, reference
     * or date, so the banner shows less detail and says where it came from.
     */
    private function stateFromStamp($row): ?array
    {
        $raw = trim((string) ($row->title_status_type ?? ''));

        if ($raw === '') {
            return null;
        }

        // Comma-separated and multi-valued: "subdivision, cancelled" is one file that
        // was subdivided AND cancelled. Map every part, keep only the legal ones.
        $states = collect(explode(',', $raw))
            ->map(fn ($part) => self::STAMP_TO_STATE[strtolower(trim($part))] ?? null)
            ->filter()
            ->unique()
            ->sortBy(fn ($state) => array_search($state, self::SEVERITY, true))
            ->values();

        if ($states->isEmpty()) {
            return null;
        }

        $key = $states->first();
        $presentation = self::STATE_PRESENTATION[$key];

        return array_merge($presentation, [
            'key' => $key,
            'detail' => 'recorded on the indexing record',
            'reason' => $row->title_status_remark ?? null,
            'read_only' => (bool) ($presentation['restricting'] ?? false),
            'enforcement_pending' => true,
            'reversal_tracked' => false,
            // Never let the lead badge hide a second restriction.
            'other_active' => $states->count() - 1,
            'other_states' => $states->slice(1)->values()->all(),
            'from_stamp' => true,
        ]);
    }

    /**
     * Virtual folders for the Current Documents section, grouped by page_type.
     *
     * These are PRESENTATION groups over the stored classification. Nothing is
     * renamed in the database, and unclassified pages stay visible rather than
     * being hidden into a tidy-looking list.
     */
    public function folders(FileIndexing $file): array
    {
        $groups = DB::connection('sqlsrv')
            ->table('pagetypings')
            ->select('page_type', DB::raw('count(*) as total'))
            ->where('file_indexing_id', $file->id)
            ->groupBy('page_type')
            ->orderBy('page_type')
            ->get();

        $folders = [];
        $unclassified = 0;

        foreach ($groups as $group) {
            $stored = trim((string) $group->page_type);

            if ($stored === '') {
                $unclassified += (int) $group->total;
                continue;
            }

            $folders[] = [
                // The key stays the RAW stored value so filtering still matches the
                // column; only the label is resolved for display.
                'key' => $stored,
                'label' => $this->typeName($stored),
                'count' => (int) $group->total,
                'classified' => true,
            ];
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        if ($unclassified > 0) {
            $folders[] = [
                'key' => '__unclassified',
                'label' => 'Unclassified',
                'count' => $unclassified,
                'classified' => false,
            ];
        }

        return $folders;
    }

    /**
     * Document tiles for one folder, paginated.
     *
     * Bounded on purpose: the existing archive viewer returns every page of a file in
     * one payload and renders all thumbnails eagerly, which is the known bottleneck on
     * large merged files. This caps the payload and lets the grid lazy-load.
     */
    public function documents(FileIndexing $file, ?string $folder = null, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = min(max((int) ($perPage ?: self::PER_PAGE), 1), self::MAX_PER_PAGE);

        $query = PageTyping::query()
            ->with(['scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,registry,edms_file_type,paper_size'])
            ->where('file_indexing_id', $file->id);

        if ($folder === '__unclassified') {
            $query->where(fn ($q) => $q->whereNull('page_type')->orWhere('page_type', ''));
        } elseif ($folder !== null && $folder !== '') {
            $query->where('page_type', $folder);
        }

        $paginator = $query
            ->orderByRaw('ISNULL(page_number, 0) asc')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (PageTyping $pt) => $this->tile($pt, $file))
        );

        return $paginator;
    }

    /**
     * One document tile.
     *
     * Media resolution goes through EdmsDocumentPathResolver, which walks every
     * historical EDMS layout, so a stale stored path still renders. A page whose
     * source cannot be found keeps its mapping and is reported as unavailable — it is
     * never silently dropped from the folder.
     */
    private function tile(PageTyping $pt, FileIndexing $file): array
    {
        $context = $this->paths->contextFromScanning($pt->scanning, $file);
        $rawPath = $pt->file_path ?: optional($pt->scanning)->document_path;

        $viewerUrl = $rawPath ? $this->paths->resolveUrl($rawPath, $context) : null;
        $relative = $rawPath ? $this->paths->resolveRelative($rawPath, $context) : null;
        $extension = strtolower((string) pathinfo((string) ($relative ?: $rawPath), PATHINFO_EXTENSION)) ?: null;

        $isPdf = $extension === 'pdf';
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'], true);

        return [
            'id' => $pt->id,
            'label' => $this->tileLabel($pt),
            'page_type' => $this->typeName($pt->page_type),
            'page_subtype' => $this->typeName($pt->page_subtype, true),
            'page_number' => $pt->page_number,
            'serial_number' => $pt->serial_number,
            'extension' => $extension,
            'media_type' => $isPdf ? 'pdf' : ($isImage ? 'image' : 'document'),
            'viewer_url' => $viewerUrl,
            'thumbnail_url' => $isImage ? $viewerUrl : null,
            'pdf_page_number' => $pt->isPdfPage() ? $pt->getPdfPageNumber() : null,
            'scanning_id' => $pt->scanning_id,
            'source_file_number' => $file->file_number,
            'source_relative' => $relative,
            // Distinguishes "we have no pointer" from "the pointer does not resolve".
            'is_unmapped' => $rawPath === null || $rawPath === '',
            'is_unavailable' => $rawPath !== null && $rawPath !== '' && $viewerUrl === null,
            'qc_status' => $pt->qc_status ?: 'pending',
        ];
    }

    private function tileLabel(PageTyping $pt): string
    {
        $parts = array_filter([
            $this->typeName($pt->page_type) ?: 'Unclassified',
            $this->typeName($pt->page_subtype, true),
        ]);

        return implode(' — ', $parts);
    }

    // ---------------------------------------------------------------- lineage

    /**
     * Parent and child folios, read from the prop_id cascade.
     *
     * Assembled, not stored: lineage lives across parent_prop_id (comma-separated for
     * true mergers), related_file_number and the successor pointer on
     * decommissioned_files. There is no single normalised edge table yet, so this
     * reads the prop_id columns and reports what it found.
     */
    public function lineage(FileIndexing $file): array
    {
        $family = $this->relatedFileNumbers($file);
        $links  = $this->classifyLinks($file, $family);

        $events = $this->statusEvents($file)
            ->filter(fn ($row) => in_array($row->title_type, self::STRUCTURAL_TYPES, true))
            ->sortBy(fn ($row) => $this->eventDate($row))
            ->values();

        return array_merge($links, [
            'events' => $events,
            'truncated' => $this->relatedTruncated,
            'total' => count($links['same_property']) + count($links['parcel_history'])
                + count($links['unclassified']) + count($links['unindexed']),
            // Said out loud in the UI rather than implied by an empty panel.
            'has_versioned_history' => false,
        ]);
    }

    /**
     * Every file number related to this one, from the sources Legal Search trusts.
     *
     * Deliberately NOT from prop_id. `file_indexings.prop_id` is not a safe join key:
     * a bulk import stamped thousands of rows with a row ordinal instead of the real
     * property id (see database/sql/2026_08_10_fix_file_indexings_prop_id_conflicts.sql),
     * so 2,034 prop_id values are shared by unrelated files. Joining on it drags a
     * stranger's parcel into the folio, which is exactly the bug this replaces.
     *
     * Two sources are unioned because neither is complete: the related_file_number
     * register covers ~10.1k files and carries a relationship type, while the
     * file_indexings.related_fileno JSON column covers ~13.9k and carries none. About
     * 5.2k files appear only in the JSON.
     *
     * @return array<string,array{type:?string,comment:?string}> keyed by file number
     */
    private function relatedFileNumbers(FileIndexing $file): array
    {
        $self = trim((string) $file->file_number);
        $candidates = [$self];

        // getSmeAllowedFileNos returns [] unless "SME mode" fires, and [] for ST-
        // prefixed numbers, so the register union below is not optional — it is what
        // catches a file whose only link is a register row.
        try {
            $sme = app(LegalSearchService::class)
                ->getSmeAllowedFileNos($self, DB::connection('sqlsrv'));
            $candidates = array_merge($candidates, $sme);
        } catch (\Throwable $e) {
            Log::warning('VFS: SME family lookup failed', [
                'file_number' => $self,
                'message' => $e->getMessage(),
            ]);
        }

        $candidates = array_values(array_unique(array_filter(array_map('trim', $candidates))));

        // 167 rows carry no file_number at all. With nothing to match on, an unguarded
        // where(function(){}) adds NO condition and the register query returns EVERY
        // row in the table — which is how a folio with no number ended up "related" to
        // the whole registry. Stop here instead.
        if (empty($candidates)) {
            return [];
        }

        $related = [];

        // The SME family is itself a list of related files, not merely a set of search
        // keys for the register: it already resolves the related_fileno column in BOTH
        // directions, which is the only thing that finds a counterpart whose link is
        // recorded solely on the other file's row. Seed from it first, untyped, so a
        // typed register edge below can still upgrade the entry.
        foreach ($candidates as $number) {
            if (!$this->sameFileNumber($number, $self)) {
                $related[$number] = ['type' => null, 'comment' => null];
            }
        }

        // The register, BOTH directions: 7,361 rows have no reverse row, so matching
        // only one column would miss the counterpart half the time.
        if (Schema::connection('sqlsrv')->hasTable('related_file_number')) {
            $rows = collect();

            foreach (array_chunk($candidates, self::QUERY_CHUNK) as $batch) {
                $rows = $rows->concat(
                    DB::connection('sqlsrv')->table('related_file_number')
                        ->where(function ($q) use ($batch) {
                            foreach ($batch as $candidate) {
                                $q->orWhere('file_number', $candidate)
                                    ->orWhere('related_fileno', $candidate);
                            }
                        })
                        ->get(['file_number', 'related_fileno', 'transaction_type', 'comment'])
                );
            }

            foreach ($rows as $row) {
                foreach ([$row->file_number, $row->related_fileno] as $number) {
                    $number = trim((string) $number);

                    if ($number === '' || $this->sameFileNumber($number, $self)) {
                        continue;
                    }

                    // A typed edge always beats an untyped one for the same counterpart.
                    if (!isset($related[$number]) || empty($related[$number]['type'])) {
                        $related[$number] = [
                            'type' => $row->transaction_type ?: null,
                            'comment' => $row->comment ?: null,
                        ];
                    }
                }
            }
        }

        // The JSON column, for the ~5.2k files with no register row. There is no type
        // here, so these land in the default bucket rather than being guessed at.
        foreach ($this->parseRelatedFileno($file->related_fileno) as $number) {
            if (!$this->sameFileNumber($number, $self) && !isset($related[$number])) {
                $related[$number] = ['type' => null, 'comment' => null];
            }
        }

        return $related;
    }

    /** True when the family was cut to MAX_RELATED, so the view can say so. */
    private bool $relatedTruncated = false;

    /**
     * Split the related files into the two relationship kinds the register
     * distinguishes, so the UI never presents a registry counterpart as parcel
     * ancestry, or the reverse.
     */
    private function classifyLinks(FileIndexing $file, array $related): array
    {
        // The register is not the only record of WHY two files are linked, and for
        // recent parcel work it is the weaker one: the 2026 writers stamp
        // transaction_type "Related File" or "Other", while decommissioned_files
        // states plainly "Plot Subdivision into 2 fragments". Reading the event back
        // is what turns two anonymous "Related File" cards into the subdivision they
        // actually are.
        $this->enrichFromParcelEvents(
            trim((string) ($file->file_number ?: $file->temp_file_no)),
            $related
        );

        $out = ['same_property' => [], 'parcel_history' => [], 'unclassified' => [], 'unindexed' => []];

        if (empty($related)) {
            return $out;
        }

        // A big subdivision can relate hundreds of files. Cap the list, and chunk
        // every lookup: SQL Server rejects a statement over 2,100 parameters.
        if (count($related) > self::MAX_RELATED) {
            $this->relatedTruncated = true;
            $related = array_slice($related, 0, self::MAX_RELATED, true);
        }

        $rows = collect();

        foreach (array_chunk(array_keys($related), self::QUERY_CHUNK) as $batch) {
            $rows = $rows->concat(
                FileIndexing::query()
                    ->select(['id', 'file_number', 'file_title', 'registry', 'prop_id',
                              'current_holder', 'original_holder', 'plot_number', 'district',
                              'lga', 'location', 'street_name', 'land_use_type'])
                    ->whereIn('file_number', $batch)
                    ->get()
            );
        }

        $rows = $rows->keyBy(fn ($row) => trim((string) $row->file_number));

        $pageCounts = collect();

        foreach (array_chunk($rows->pluck('id')->all(), self::QUERY_CHUNK) as $batch) {
            $pageCounts = $pageCounts->union(
                DB::connection('sqlsrv')
                    ->table('pagetypings')
                    ->select('file_indexing_id', DB::raw('count(*) as total'))
                    ->whereIn('file_indexing_id', $batch)
                    ->groupBy('file_indexing_id')
                    ->pluck('total', 'file_indexing_id')
            );
        }

        foreach ($related as $number => $meta) {
            $row = $rows->get($number);

            // A related number with no indexing record is still reported: it is a real
            // link to a file nobody has indexed, not something to quietly drop.
            if (!$row) {
                $out['unindexed'][] = ['file_number' => $number, 'type' => $meta['type']];
                continue;
            }

            $entry = [
                'id' => $row->id,
                'file_number' => $row->file_number,
                'holder' => $row->current_holder ?: $row->original_holder ?: $row->file_title,
                'registry' => $this->registryLabel($row->registry),
                'registry_badge' => $this->registryBadge($row->registry),
                'prop_id' => $row->prop_id,
                // Where the parcel IS, not just what it is for. A card showing only
                // "Commercial" tells a searcher nothing they can act on, and these
                // columns are populated unevenly, so every one that has a value
                // contributes. Duplicates are dropped because district and location
                // frequently hold the same name.
                'property' => collect([
                    $row->plot_number ? 'Plot ' . $row->plot_number : null,
                    $row->street_name,
                    $row->location,
                    $row->district,
                    $row->lga,
                ])->map(fn ($part) => trim((string) $part))
                    ->filter()
                    ->unique(fn ($part) => strtoupper($part))
                    ->implode(' · '),
                'land_use' => $row->land_use_type ?: null,
                'pages' => (int) ($pageCounts[$row->id] ?? 0),
                // Left null when nothing recorded the kind of link. The view says
                // "not recorded" rather than inventing a relationship name.
                'type' => $meta['type'] ?: null,
                'type_badge' => $this->linkTypeBadge($meta['type']),
                'comment' => $meta['comment'],
            ];

            $out[$this->linkGroup($meta['type'])][] = $entry;
        }

        foreach (['same_property', 'parcel_history', 'unclassified'] as $group) {
            usort($out[$group], fn ($a, $b) => strcmp($a['file_number'], $b['file_number']));
        }

        return $out;
    }

    /**
     * Which panel a link belongs in, from its register transaction_type.
     *
     * The register distinguishes a same-property registry counterpart (KANGIS /
     * MLPP recertification) from real parcel ancestry (subdivision, merger). That
     * distinction is the whole reason this panel can be honest — parent_prop_id
     * carries both kinds with no discriminator at all.
     */
    /**
     * Upgrade weak register types using the parcel event that actually happened.
     *
     * decommissioned_files records the event in words — "Plot Subdivision into 2
     * fragments", "Change of Purpose to CON-COM", "Merged into IND-2026-3" — along
     * with the successors it produced. Where the register only managed "Related
     * File" or nothing at all, that sentence is the better answer, so it wins.
     *
     * A type the register states explicitly is NEVER overwritten: someone chose it,
     * and a decommissioning reason is free text that should not outrank a deliberate
     * classification.
     *
     * Read in both directions. The decommissioned file names its successors, and a
     * successor should equally be able to name the file it came from.
     *
     * @param  array<string,array{type:?string,comment:?string}>  $related
     */
    private function enrichFromParcelEvents(string $self, array &$related): void
    {
        if ($self === '' || !$related) {
            return;
        }

        $numbers = array_map('strval', array_keys($related));
        $conn = DB::connection('sqlsrv');

        // Rows where THIS file was retired, plus rows where one of the counterparts
        // was — chunked, because the successor list can name hundreds of files.
        $events = collect();

        foreach (array_chunk(array_merge([$self], $numbers), self::QUERY_CHUNK) as $batch) {
            $events = $events->merge(
                $conn->table('decommissioned_files')
                    ->whereIn('file_no', $batch)
                    ->get(['file_no', 'successor_file_no', 'decommissioning_reason'])
            );
        }

        if ($events->isEmpty()) {
            return;
        }

        $selfKey = strtoupper($self);

        // UPPER(number) => the key as it appears in $related. Built once, because
        // walking every related file for every event is quadratic, and a 530-plot
        // subdivision turns that into a quarter of a million comparisons per page
        // load. Each event now touches only the files it actually names.
        $index = [];

        foreach ($related as $number => $meta) {
            $index[strtoupper((string) $number)] = $number;
        }

        // What a decommissioning row implies about a counterpart, gathered first so
        // the assignment loop below is a single pass.
        $found = [];

        foreach ($events as $event) {
            $kind = $this->parcelEventKind($event->decommissioning_reason);

            if ($kind === null) {
                continue;
            }

            $subject = strtoupper(trim((string) $event->file_no));
            $reason = trim((string) $event->decommissioning_reason) ?: null;

            $successors = array_filter(array_map(
                static fn ($n) => strtoupper(trim($n)),
                preg_split('/\s*,\s*/', (string) ($event->successor_file_no ?? '')) ?: []
            ));

            if ($subject === $selfKey) {
                // This file was retired into its successors.
                foreach ($successors as $successor) {
                    if (isset($index[$successor])) {
                        $found[$index[$successor]] ??= [$kind, $reason];
                    }
                }

                continue;
            }

            // A counterpart was retired into this file, so the same event names it.
            if (isset($index[$subject]) && in_array($selfKey, $successors, true)) {
                $found[$index[$subject]] ??= [$kind, $reason];
            }
        }

        foreach ($found as $number => [$kind, $reason]) {
            $existing = strtolower(trim((string) ($related[$number]['type'] ?? '')));

            // Only fill a gap. A type the register states explicitly stays as it is.
            if ($existing !== '' && $existing !== 'related file' && $existing !== 'other') {
                continue;
            }

            $related[$number]['type'] = $kind;
            $related[$number]['comment'] = $related[$number]['comment'] ?: $reason;
        }
    }

    /**
     * The parcel event a decommissioning reason describes, or null if it is not one.
     *
     * The reasons are free text written by several modules over time, so this matches
     * on the word that carries the meaning rather than on whole strings. Anything it
     * does not recognise is left alone — a guess here would show a confident wrong
     * badge, which is worse than the honest "not recorded".
     */
    private function parcelEventKind(?string $reason): ?string
    {
        $reason = strtolower(trim((string) $reason));

        if ($reason === '') {
            return null;
        }

        // "Title Status: subdivision" rows are status flags, not parcel lineage.
        if (str_starts_with($reason, 'title status:')) {
            return null;
        }

        foreach ([
            'subdivision' => 'Subdivision',
            'subdivided' => 'Subdivision',
            'merger' => 'Merger',
            'merged' => 'Merger',
            'change of purpose' => 'Change of Purpose',
            're-grant' => 'Re-grant',
            'regrant' => 'Re-grant',
            'separation' => 'Subdivision',
        ] as $needle => $kind) {
            if (str_contains($reason, $needle)) {
                return $kind;
            }
        }

        return null;
    }

    private function linkGroup(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        if ($type === '' || $type === 'other') {
            return 'unclassified';
        }

        if (str_contains($type, 'recertification') || $type === 'related file') {
            return 'same_property';
        }

        if (in_array($type, ['subdivision', 'plot subdivision', 'merger', 'separation',
                             'extension', 'change of purpose', 'change of name',
                             're-grant', 'resettlement', 'mother file'], true)) {
            return 'parcel_history';
        }

        return 'unclassified';
    }

    /** related_fileno holds a JSON array, and occasionally a bare CSV string. */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '' || $raw === '[]') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : explode(',', trim($raw, "[]\"' "));

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item, " \"'\t\n\r"),
            $items
        )));
    }

    /** File numbers vary in case and separators between tables. */
    private function sameFileNumber(?string $a, ?string $b): bool
    {
        $normalize = fn ($v) => preg_replace('/\s+/', '', strtoupper(
            preg_replace('/[\/=_]+/', '-', trim((string) $v))
        ));

        return $normalize($a) === $normalize($b);
    }

    // --------------------------------------------------------------- timeline

    /**
     * The interleaved status timeline: structural (SPU/APU) and legal (TSU) events
     * in one chronological feed, which is the whole point of the unified view.
     *
     * The opening ALLOTMENT row is derived from the indexing record's own creation,
     * not from an event table — the frameworks' timeline starts with an allotment but
     * their trigger_event_type enum only admits SPU, APU and TSU, so there is nowhere
     * to store one. Flagged as derived so nobody reads it as a recorded event.
     */
    public function statusTimeline(FileIndexing $file): array
    {
        $events = $this->statusEvents($file)
            ->map(function (TitleStatusApplication $row) {
                $state = self::TYPE_TO_STATE[$row->title_type] ?? null;
                $structural = in_array($row->title_type, self::STRUCTURAL_TYPES, true);

                return [
                    'kind' => $structural ? 'SPU' : 'TSU',
                    'type' => $row->title_type,
                    'state' => $state,
                    'presentation' => $state ? self::STATE_PRESENTATION[$state] : null,
                    'date' => $this->eventDate($row),
                    'authority' => $row->authority,
                    'reference' => $row->authority_reference,
                    'initiated_by' => $row->initiated_by,
                    'reason' => $row->reason,
                    'remark' => $row->remark,
                    'see_fileno' => $row->see_fileno,
                    'derived' => false,
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();

        array_unshift($events, [
            'kind' => 'ORIGIN',
            'type' => 'Allotment',
            'state' => null,
            'presentation' => null,
            'date' => $file->created_at,
            'authority' => null,
            'reference' => null,
            'initiated_by' => null,
            'reason' => 'File indexed as ' . $file->file_number,
            'remark' => null,
            'see_fileno' => null,
            'derived' => true,
        ]);

        return $events;
    }

    // -------------------------------------------------------- physical source

    /**
     * The real EDMS directories this folio draws from.
     *
     * One folio is never equal to one directory: a file born of a subdivision
     * inherits its parent's folder by pointer, and a reconciled temporary file keeps
     * its own. Derived from where the pages actually resolve.
     */
    public function physicalSources(FileIndexing $file): array
    {
        $pages = PageTyping::query()
            ->with(['scanning:id,file_indexing_id,document_path,original_filename,registry,edms_file_type,paper_size'])
            ->where('file_indexing_id', $file->id)
            ->get();

        $folders = [];

        foreach ($pages as $pt) {
            $context = $this->paths->contextFromScanning($pt->scanning, $file);
            $rawPath = $pt->file_path ?: optional($pt->scanning)->document_path;
            $relative = $rawPath ? $this->paths->resolveRelative($rawPath, $context) : null;

            if (!$relative) {
                continue;
            }

            $folder = trim((string) dirname($relative), '.');

            if ($folder === '') {
                continue;
            }

            if (!isset($folders[$folder])) {
                $folders[$folder] = [
                    'path' => $folder,
                    'label' => basename($folder),
                    'registry' => $this->registryLabel($pt->registry ?: $file->registry),
                    'pages' => 0,
                    'is_temporary' => (bool) preg_match('/\(\s*T\s*\)\s*$/i', basename($folder)),
                ];
            }

            $folders[$folder]['pages']++;
        }

        return array_values($folders);
    }

    // ---------------------------------------------------------------- helpers

    /** Approved, undeleted title-status rows for this folio's file numbers. */
    private function statusEvents(FileIndexing $file): Collection
    {
        $numbers = array_values(array_filter([$file->file_number, $file->temp_file_no]));

        if (empty($numbers)) {
            return collect();
        }

        return TitleStatusApplication::query()
            ->whereIn('file_no', $numbers)
            ->where('status', TitleStatusApplication::STATUS_APPROVED)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->get();
    }

    /**
     * The restriction currently in force.
     *
     * The latest approved restricting event wins. Reversal is NOT modelled: the
     * current table has no is_reversed / reversal_tsu_id pair, so a resolved
     * litigation cannot yet be told apart from a live one. The payload carries
     * reversal_tracked => false and the UI states it rather than implying certainty.
     */
    private function activeState(Collection $events): ?array
    {
        $restricting = $events
            ->map(function ($row) {
                $state = self::TYPE_TO_STATE[$row->title_type] ?? null;

                return $state ? ['row' => $row, 'state' => $state, 'date' => $this->eventDate($row)] : null;
            })
            ->filter()
            ->sortByDesc('date')
            ->values();

        if ($restricting->isEmpty()) {
            return null;
        }

        $active = $restricting->first();
        $presentation = self::STATE_PRESENTATION[$active['state']];

        $detail = array_filter([
            $active['row']->authority_reference,
            $active['row']->authority,
            $active['date'] ? 'effective ' . $active['date']->format('j M Y') : null,
        ]);

        return array_merge($presentation, [
            'key' => $active['state'],
            'detail' => implode(' · ', $detail),
            'reason' => $active['row']->reason,
            'read_only' => (bool) ($presentation['restricting'] ?? false),
            // Advisory, not enforced. FolioTransactionGuard is backend work; until it
            // exists the screen must not imply a lock the system is not applying.
            'enforcement_pending' => true,
            'reversal_tracked' => false,
            'other_active' => $restricting->count() - 1,
        ]);
    }

    /** approved_at is the legal effective date we have; fall back to capture time. */
    private function eventDate($row)
    {
        return $row->approved_at ?: $row->created_at;
    }

    /**
     * What to call this folio on screen.
     *
     * 167 rows carry no file_number at all — the number exists only in
     * temp_file_no, because the main file was never found and the registry
     * worked in a temporary file throughout. Showing file_number alone leaves
     * those rows blank and unidentifiable, so fall back to the temporary number
     * rather than rendering an empty cell.
     */
    private function displayNumber($file): string
    {
        $number = trim((string) ($file->file_number ?? ''));

        if ($number !== '') {
            return $number;
        }

        return trim((string) ($file->temp_file_no ?? '')) ?: '(no file number)';
    }

    private function isTemporary($file): bool
    {
        return (bool) ($file->has_temp_file ?? false)
            || !empty($file->temp_file_no)
            || (bool) preg_match('/\(\s*T\s*\)\s*$/i', (string) $file->file_number);
    }

    /**
     * Colour for a structural state.
     *
     * Every state was previously green, so a decommissioned file announced itself in
     * the colour of a healthy one. Red is NOT used: it is reserved for legal status,
     * and a retired file is not a restricted file — the two are shown side by side
     * and must not be confusable.
     */
    private function structuralClass(string $state): string
    {
        return [
            'Active' => 'text-green-700',
            'Decommissioned' => 'text-gray-500',
            'Merged' => 'text-indigo-700',
            'Updated' => 'text-blue-700',
        ][$state] ?? 'text-gray-700';
    }

    /**
     * Structural lifecycle, kept deliberately separate from legal status: a folio
     * that was subdivided years ago can still be under a live litigation hold, and
     * collapsing the two would hide one of them.
     */
    private function structuralStatus(FileIndexing $file): string
    {
        if (!empty($file->is_decommissioned)) {
            return 'Decommissioned';
        }

        if (!empty($file->is_merged)) {
            return 'Merged';
        }

        if (!empty($file->is_updated)) {
            return 'Updated';
        }

        return 'Active';
    }
}
