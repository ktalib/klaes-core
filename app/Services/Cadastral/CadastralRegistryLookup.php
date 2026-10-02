<?php

namespace App\Services\Cadastral;

use App\Http\Controllers\Survey\LookupController;
use App\Services\ShelfRackLocator;
use App\Support\FileNumberLandUse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-through to the KLAES records the Cadastral registry needs but does not own.
 *
 * NOTHING HERE WRITES. file_indexings (170,315 rows), file_tracker (52,054) and
 * duplicate_fileno belong to other parts of KLAES and are maintained by them.
 * (sourceFile() can take a row lock for the caller's transaction; a lock is
 * not a write.)
 * The Cadastral Module reads them so its screens show the live truth instead of
 * a second, staler copy — which is also why the index card has no movement
 * column. (Commissioning a correspondence file on registration writes, and
 * lives in CorrespondenceFiles, not here.)
 *
 * Every query filters on an indexed file_number and takes a bounded number of
 * rows. An unbounded read of either of those tables would be felt across the
 * whole ministry.
 */
class CadastralRegistryLookup
{
    private const CONN = 'sqlsrv';

    public function __construct(private ?ShelfRackLocator $shelves = null) {}

    private function conn()
    {
        return DB::connection(self::CONN);
    }

    /**
     * The indexed file behind a file number, if KLAES holds one.
     *
     * Matched on the normalised spelling as well as the raw one, because the
     * same file is written "RES-1981-1" and "RES/1981/1" by different desks.
     */
    public function indexedFile(?string $fileNumber): ?object
    {
        $normalised = FileNumberFormat::normalise($fileNumber);

        if (! $normalised) {
            return null;
        }

        return $this->conn()->table('file_indexings')
            ->select([
                'id', 'file_number', 'file_title', 'plot_number', 'district', 'lga',
                'location', 'plot_size', 'registry', 'shelf_location', 'prop_id',
                'is_corresponding_file', 'corresponding_fileno', 'mls_file_no',
                'kangis_file_no', 'title_status', 'current_location',
            ])
            ->where(function ($q) use ($fileNumber, $normalised) {
                $q->where('file_number', $normalised)
                  ->orWhere('file_number', trim((string) $fileNumber));
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether a correspondence (cadastral copy) file already exists.
     *
     * Reads the index flag that MlsFileNoMatchingController (and, on
     * registration, CorrespondenceFiles) sets — about 48k rows. It is what the
     * intake form shows before registration; registration itself decides
     * created vs matched (CorrespondenceFiles::ensureFor).
     *
     * @return array{exists: bool, corresponding_fileno: ?string, status: string, indexed: ?object}
     */
    public function correspondence(?string $fileNumber): array
    {
        $indexed = $this->indexedFile($fileNumber);

        if (! $indexed) {
            return [
                'exists'               => false,
                'corresponding_fileno' => null,
                'status'               => 'pending',
                'indexed'              => null,
            ];
        }

        $has = (bool) $indexed->is_corresponding_file || ! empty($indexed->corresponding_fileno);

        return [
            'exists'               => $has,
            'corresponding_fileno' => $indexed->corresponding_fileno,
            'status'               => $has ? 'matched' : 'pending',
            'indexed'              => $indexed,
        ];
    }

    /**
     * Known duplicates of a file number.
     *
     * Rule-based, not "AI-powered" as the concept note puts it: exact match on
     * the number as written and on its normalised spelling, against the
     * duplicate_fileno register other parts of KLAES already maintain.
     *
     * @return Collection<int, object>
     */
    public function duplicates(?string $fileNumber, int $limit = 25): Collection
    {
        $normalised = FileNumberFormat::normalise($fileNumber);

        if (! $normalised) {
            return collect();
        }

        return collect($this->conn()->table('duplicate_fileno')
            ->select(['id', 'registry', 'file_number', 'file_title', 'plot_number', 'location', 'category', 'source', 'comment'])
            ->where(function ($q) use ($fileNumber, $normalised) {
                $q->where('file_number', $normalised)
                  ->orWhere('file_number', trim((string) $fileNumber));
            })
            ->limit($limit)
            ->get());
    }

    /**
     * Other indexed files sharing this one's plot number and district — the
     * double-allocation case the concept note asks about.
     *
     * Identity matching, not geometry: KLAES stores no parcel boundaries, so
     * two allocations can only be compared by what they are labelled.
     *
     * @return Collection<int, object>
     */
    public function possibleDoubleAllocations(?string $fileNumber, int $limit = 25): Collection
    {
        $indexed = $this->indexedFile($fileNumber);

        if (! $indexed || trim((string) $indexed->plot_number) === '') {
            return collect();
        }

        return collect($this->conn()->table('file_indexings')
            ->select(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'registry'])
            ->where('plot_number', $indexed->plot_number)
            ->where('file_number', '!=', $indexed->file_number)
            ->when($indexed->district, fn ($q) => $q->where('district', $indexed->district))
            ->limit($limit)
            ->get());
    }

    /**
     * The file's movement history, from the tracker the rest of KLAES uses.
     *
     * This is what "real-time sync with file movements" means here: the index
     * card reads the live log rather than keeping a copy that would fall behind.
     *
     * @return array{tracker: ?object, movements: array<int, array<string, mixed>>}
     */
    public function movements(?string $fileNumber, int $limit = 50): array
    {
        $normalised = FileNumberFormat::normalise($fileNumber);

        if (! $normalised) {
            return ['tracker' => null, 'movements' => []];
        }

        $tracker = $this->conn()->table('file_tracker')
            ->select([
                'id', 'tracking_id', 'file_number', 'file_title', 'status', 'module',
                'current_office_name', 'current_office_code', 'current_holder',
                'date_created', 'movement_log',
            ])
            ->where(function ($q) use ($fileNumber, $normalised) {
                $q->where('file_number', $normalised)
                  ->orWhere('file_number', trim((string) $fileNumber));
            })
            ->orderByDesc('id')
            ->first();

        if (! $tracker) {
            return ['tracker' => null, 'movements' => []];
        }

        $log = json_decode((string) $tracker->movement_log, true);

        if (! is_array($log)) {
            $log = [];
        }

        // Newest first, bounded — some files have been moving for forty years.
        $log = array_reverse($log);

        return [
            'tracker'   => $tracker,
            'movements' => array_slice($log, 0, $limit),
        ];
    }

    /** The rack/shelf the workbooks place this file on, or null. */
    public function shelfLocation(?string $fileNumber, ?string $registry = 'Cadastral'): ?string
    {
        try {
            $locator = $this->shelves ?: app(ShelfRackLocator::class);

            return $locator->resolve($fileNumber, $registry);
        } catch (\Throwable) {
            // The workbook ranges are optional reference data; a missing sheet
            // must not stop a clerk logging a file in.
            return null;
        }
    }

    /* ------------------------- intake from source (Phase 2) ------------------------- */

    /**
     * The source departments a file can be logged in from, and the
     * file_indexings.registry values that make up each one.
     *
     * Deeds is deliberately absent (open question Q4: nobody has said where
     * Deeds files are indexed). It stays in config's source_registries so old
     * receipts and the list filter still recognise it; it just cannot be picked.
     *
     * '1', '2' and '3' are the three Lands physical registries.
     */
    public const INTAKE_SOURCES = [
        'Land'   => ['1', '2', '3'],
        'SLTR'   => ['SLTR'],
        'ST'     => ['ST Registry'],
        'KANGIS' => ['KANGIS'],
        'DCIV'   => ['DCIV'],
    ];

    /**
     * Number columns searched besides file_number, per source.
     *
     * A file can carry its number in more than one column, so a match is taken
     * from any of them, never positionally. ST numbers live in st_fillno here
     * (file_indexings has no st_file_no column); KANGIS keeps a second spelling
     * in kangis_file_no.
     */
    private const INTAKE_NUMBER_COLUMNS = [
        'ST'     => ['st_fillno'],
        'KANGIS' => ['kangis_file_no'],
    ];

    /** A receipt in one of these states no longer holds the file. */
    public const CLOSED_RECEIPT_STATUSES = ['Archived', 'Returned', 'Rejected'];

    private const SOURCE_FILE_COLUMNS = [
        'id', 'file_number', 'st_fillno', 'kangis_file_no', 'file_title', 'plot_number',
        'street_name', 'district', 'lga', 'land_use_type', 'registry', 'is_decommissioned',
        'successor_file_no',
    ];

    /** Lower-cased lookup name => stored spelling, per address list. */
    private array $optionIndex = [];

    /**
     * Files in one source department whose number starts with the term.
     *
     * Prefix LIKE first, on file_number (indexed) and the source's other number
     * columns, inside the source's registry values — a bounded seek. Only when
     * that finds nothing does it fall back to a contains search, so a clerk who
     * types "1991-293" without the RES still finds the file; that fallback is
     * still capped at $limit rows and confined to the one source.
     *
     * @return array<int, array<string, mixed>>  Select2 items (see formatSourceFile)
     */
    public function sourceFiles(string $source, string $term, int $limit = 25): array
    {
        $term = trim($term);

        if (! isset(self::INTAKE_SOURCES[$source]) || mb_strlen($term) < 2) {
            return [];
        }

        // "RES/1991/293" and "res-1991-293" are the same number.
        $spellings = array_values(array_unique(array_filter([
            $term, FileNumberFormat::normalise($term),
        ])));

        $rows = $this->searchSource($source, $spellings, $limit, false);

        if ($rows->isEmpty() && mb_strlen($term) >= 3) {
            $rows = $this->searchSource($source, $spellings, $limit, true);
        }

        $open = $this->openReceipts($rows->pluck('id')->all());

        return $rows
            ->map(fn ($row) => $this->formatSourceFile($row, $source, $open[(int) $row->id] ?? null))
            ->all();
    }

    private function searchSource(string $source, array $spellings, int $limit, bool $contains): Collection
    {
        $columns = array_merge(['file_number'], self::INTAKE_NUMBER_COLUMNS[$source] ?? []);

        $q = $this->conn()->table('file_indexings')
            ->select(self::SOURCE_FILE_COLUMNS)
            ->whereIn('registry', self::INTAKE_SOURCES[$source])
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(function ($w) use ($columns, $spellings, $contains) {
                foreach ($columns as $col) {
                    foreach ($spellings as $s) {
                        $w->orWhere($col, 'like', ($contains ? '%' : '') . self::escapeLike($s) . '%');
                    }
                }
            });

        return collect($q->orderBy('file_number')->limit($limit)->get());
    }

    /** sqlsrv LIKE treats [ % _ as wildcards; a file number is literal. */
    private static function escapeLike(string $s): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $s);
    }

    /**
     * One indexed file, read fresh — the server's copy of what the clerk picked.
     *
     * With $source, the row must belong to that department or null comes back,
     * so a file id posted against the wrong source is refused. With $lock the
     * read takes an update lock on that one file_indexings row for the rest of
     * the caller's transaction: two clerks logging the same file at once then
     * queue behind each other instead of both passing the open-receipt check.
     * It is a lock, not a write — nothing here changes the row.
     *
     * @return array<string, mixed>|null  formatSourceFile shape
     */
    public function sourceFile(?string $source, ?int $id, bool $lock = false): ?array
    {
        if (! $id || ($source !== null && ! isset(self::INTAKE_SOURCES[$source]))) {
            return null;
        }

        $q = $this->conn()->table('file_indexings')
            ->select(self::SOURCE_FILE_COLUMNS)
            ->where('id', $id)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));

        if ($source !== null) {
            $q->whereIn('registry', self::INTAKE_SOURCES[$source]);
        }

        if ($lock) {
            $q->lockForUpdate();
        }

        $row = $q->first();

        if (! $row) {
            return null;
        }

        $source ??= $this->sourceOfRegistry($row->registry);

        return $this->formatSourceFile($row, (string) $source, $this->openReceipts([(int) $row->id])[(int) $row->id] ?? null);
    }

    /** The intake source a registry value belongs to, or null (Survey, NULL …). */
    public function sourceOfRegistry(?string $registry): ?string
    {
        foreach (self::INTAKE_SOURCES as $source => $values) {
            if (in_array((string) $registry, $values, true)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Open cadastral receipts for these file_indexings ids, keyed by id.
     *
     * "Open" is any status not in CLOSED_RECEIPT_STATUSES — Received and
     * Registered today. Soft-deleted receipts do not count.
     *
     * @param  int[]  $ids
     * @param  int|null  $exceptReceiptId  the receipt being edited
     * @return array<int, string>  file_indexing_id => receipt_ref
     */
    public function openReceipts(array $ids, ?int $exceptReceiptId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        return $this->conn()->table('cadastral_file_receipts')
            ->whereIn('file_indexing_id', $ids)
            ->whereNotIn('status', self::CLOSED_RECEIPT_STATUSES)
            ->whereNull('deleted_at')
            ->when($exceptReceiptId, fn ($q) => $q->where('id', '!=', $exceptReceiptId))
            ->orderByDesc('id')
            ->pluck('receipt_ref', 'file_indexing_id')
            ->mapWithKeys(fn ($ref, $id) => [(int) $id => (string) $ref])
            ->all();
    }

    /**
     * One file_indexings row as the intake form uses it.
     *
     * 'address' is the prop_* group the address builder takes, already mapped
     * onto the dropdown lists (see sourceAddress); 'location' is the composed
     * "District, LGA, State" — never the plot.
     */
    public function formatSourceFile(object $row, string $source, ?string $openReceipt = null): array
    {
        $number  = trim((string) $row->file_number);
        foreach (self::INTAKE_NUMBER_COLUMNS[$source] ?? [] as $col) {
            if ($number === '') $number = trim((string) ($row->{$col} ?? ''));
        }

        $title   = trim((string) $row->file_title);
        $class   = FileNumberFormat::classify($number);
        $address = $this->sourceAddress($row);
        $retired = (bool) $row->is_decommissioned;

        $text = $number . ($title !== '' ? ' — ' . $title : '');
        if ($retired)     $text .= ' (decommissioned)';
        if ($openReceipt) $text .= " (already in the queue as {$openReceipt})";

        return [
            'id'             => (int) $row->id,
            'text'           => $text,
            'file_number'    => $number,
            'other_numbers'  => array_values(array_filter(array_map(
                fn ($c) => ($v = trim((string) ($row->{$c} ?? ''))) !== '' && $v !== $number ? $v : null,
                ['st_fillno', 'kangis_file_no']
            ))),
            'source'         => $source,
            'registry'       => $row->registry,
            'owner'          => $title,
            'plot'           => trim((string) $row->plot_number),
            'district'       => trim((string) $row->district),
            'lga'            => trim((string) $row->lga),
            'land_use'       => trim((string) $row->land_use_type) ?: FileNumberFormat::landUseLabel($number),
            'file_class'     => $class,
            'type'           => self::typeLabel($number, $source, $class, $row->land_use_type),
            'address'        => $address,
            'location'       => CadastralAddress::propertyLocation($address),
            'decommissioned' => $retired,
            'successor'      => $retired ? (trim((string) $row->successor_file_no) ?: null) : null,
            'open_receipt'   => $openReceipt,
            // Select2 greys these out; store() refuses them regardless.
            'disabled'       => $retired || $openReceipt !== null,
        ];
    }

    /**
     * The brief's Type column: "Direct RES", "Conversion COM", "ST RES".
     *
     * Display only. What drives the workflow is file_class (direct|conversion).
     */
    public static function typeLabel(?string $fileNumber, ?string $source, ?string $class = null, ?string $landUse = null): string
    {
        $class ??= FileNumberFormat::classify($fileNumber);
        $code    = FileNumberFormat::landUseCode($fileNumber);

        // SLTR, KANGIS and DCIV numbers carry no land-use segment ("DCIV-2026-147"
        // would read as code DCIV), so fall back to the indexed land use.
        if (! isset(FileNumberLandUse::LABELS[$code ?? ''])) {
            $code = array_search(ucfirst(strtolower(trim((string) $landUse))), FileNumberLandUse::LABELS, true) ?: null;
        }

        $kind = match (true) {
            $source === 'ST' || (FileNumberFormat::segments($fileNumber)[0] ?? null) === 'ST' => 'ST',
            $class === 'conversion' => 'Conversion',
            default => 'Direct',
        };

        return trim($kind . ' ' . ($code ?? ''));
    }

    /**
     * A file_indexings row's location as the address builder's prop_* group.
     *
     * The builder's district and street are dropdowns over the districts and
     * street_names lists. A source value that matches a list entry (ignoring
     * case and spacing) takes the list's spelling; one that does not becomes
     * "Other" with the source text in *_other — the builder's own convention —
     * rather than being dropped. LGA has no Other, so an unlisted LGA is kept
     * as written. State is Kano: every file in this registry is.
     *
     * A segment the source leaves blank stays null, and the form leaves that
     * field for the clerk; see FileReceiptController::applySourceFile.
     */
    public function sourceAddress(object $row): array
    {
        $out = [
            'prop_house'          => null,
            'prop_plot'           => trim((string) $row->plot_number) ?: null,
            'prop_street'         => null,
            'prop_street_other'   => null,
            'prop_district'       => null,
            'prop_district_other' => null,
            'prop_lga'            => null,
            'prop_state'          => 'Kano',
        ];

        foreach (['district' => 'districts', 'street' => 'streets'] as $kind => $list) {
            $value = trim((string) ($kind === 'street' ? ($row->street_name ?? '') : $row->district));

            if ($value === '') {
                continue;
            }

            $listed = $this->listedOption($list, $value);
            $out['prop_' . $kind]            = $listed ?? 'Other';
            $out['prop_' . $kind . '_other'] = $listed === null ? $value : null;
        }

        $lga = trim((string) $row->lga);
        if ($lga !== '') {
            $out['prop_lga'] = $this->listedOption('lgas', $lga) ?? $lga;
        }

        return $out;
    }

    private function listedOption(string $list, string $value): ?string
    {
        if (! isset($this->optionIndex[$list])) {
            $this->optionIndex[$list] = [];
            foreach (LookupController::options($list) as $name) {
                $this->optionIndex[$list][self::optionKey($name)] = $name;
            }
        }

        return $this->optionIndex[$list][self::optionKey($value)] ?? null;
    }

    private static function optionKey(string $s): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($s)));
    }

    /**
     * Everything the reception screen wants about one file number, in one call.
     */
    public function summarise(?string $fileNumber): array
    {
        $correspondence = $this->correspondence($fileNumber);
        $duplicates     = $this->duplicates($fileNumber);
        $doubles        = $this->possibleDoubleAllocations($fileNumber);

        return [
            'format'         => FileNumberFormat::describe($fileNumber),
            'indexed'        => $correspondence['indexed'],
            'correspondence' => $correspondence,
            'duplicates'     => $duplicates,
            'doubles'        => $doubles,
            'shelf_location' => $this->shelfLocation($fileNumber),
            'has_warning'    => $duplicates->isNotEmpty() || $doubles->isNotEmpty(),
        ];
    }
}
