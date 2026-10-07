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
     * "PIECE OF LAND" (and its misspellings) is a description, not a plot
     * number: ~45k files carry it, so it is not compared.
     *
     * @return Collection<int, object>
     */
    public function possibleDoubleAllocations(?string $fileNumber, int $limit = 25): Collection
    {
        $indexed = $this->indexedFile($fileNumber);

        if (! $indexed || trim((string) $indexed->plot_number) === ''
            || self::isPieceOfLand($indexed->plot_number)) {
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
     * True for "PIECE OF LAND" as indexed: "A PIECE OF LAND", "PIECEOF LAND",
     * "PICE OF LAND", "piece of and", "PIECE OF LAND 'B'", spacing variants.
     */
    public static function isPieceOfLand(?string $plotNumber): bool
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper((string) $plotNumber));

        return (bool) preg_match('/^A?PIE?CEOFL?AND[A-Z]?$/', $letters);
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

    /* ------------------------ the global file picker ------------------------ */

    /**
     * The global file-number selector's tabs, mapped to intake sources.
     *
     * A hint only. Which source a file belongs to is read from its own
     * file_indexings.registry (sourceOfRegistry): the tab says where the clerk
     * looked, the row says where the file is indexed. The tab orders an
     * ambiguous list, it never picks from it. 'gkn' is the Survey registry,
     * which is not an intake source; Deeds has no tab and no mapping (Q4).
     */
    public const TAB_SOURCES = [
        'mls'       => 'Land',
        'old_mls'   => 'Land',
        'kangis'    => 'KANGIS',
        'newkangis' => 'KANGIS',
        'st'        => 'ST',
        'sit'       => 'ST',
        'sltr'      => 'SLTR',
        'dciv'      => 'DCIV',
        'gkn'       => null,
    ];

    /**
     * How far along the module a file must be before a form will take it.
     *
     *   intake   indexed, in an intake source, not decommissioned, no open receipt
     *   indexed  any live indexed file
     *   receipt  a registered intake receipt that is not returned or rejected
     *   plan     a plan-description record
     *   card     a live index card
     */
    public const RESOLVE_SCOPES = ['intake', 'indexed', 'receipt', 'plan', 'card'];

    /**
     * Extra refusals a form adds on top of its scope (the server repeats them).
     *
     *   chart       a conversion file is not charted; a charted file gets a new version, not a second chart
     *   commission  a file has one live index card
     */
    public const RESOLVE_PURPOSES = ['chart', 'commission'];

    /** Every column a file number can sit in. Matched across all of them, never positionally. */
    private const RESOLVE_NUMBER_COLUMNS = ['file_number', 'st_fillno', 'kangis_file_no', 'mls_file_no', 'new_kangis_file_no'];

    /** A receipt in one of these states cannot be built on (IndexCardController::DEAD_RECEIPTS). */
    private const DEAD_RECEIPTS = ['Rejected', 'Returned'];

    /** Survey jobs in these states are not offered. */
    private const DEAD_JOBS = ['Cancelled', 'Rejected'];

    /** Form fields a file's records can supply, in fill order: a select before its "specify" box. */
    public const VALUE_FIELDS = [
        'file_title', 'plot_no',
        'prop_district', 'prop_district_other', 'prop_street', 'prop_street_other',
        'prop_lga', 'prop_state', 'prop_house', 'prop_plot',
    ];

    /**
     * Live indexed files carrying this number in any number column.
     *
     * One equality test per column over both spellings. Only file_number is
     * indexed, so this is a single scan of file_indexings (~150 ms) — fine for
     * a click, which is the only thing that calls it; it is never wired to a
     * keystroke.
     *
     * @return Collection<int, object>  rows with ->matched_on (column names)
     */
    public function matchIndexedFiles(?string $fileNumber, int $limit = 15): Collection
    {
        $spellings = $this->spellings($fileNumber);

        if ($spellings === []) {
            return collect();
        }

        $columns = array_merge(self::SOURCE_FILE_COLUMNS, ['mls_file_no', 'new_kangis_file_no']);

        $rows = collect($this->conn()->table('file_indexings')
            ->select(array_values(array_unique($columns)))
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(function ($w) use ($spellings) {
                foreach (self::RESOLVE_NUMBER_COLUMNS as $col) {
                    $w->orWhereIn($col, $spellings);
                }
            })
            ->orderBy('id')
            ->limit($limit)
            ->get());

        $upper = array_map('strtoupper', $spellings);

        return $rows->each(function ($row) use ($upper) {
            $row->matched_on = array_values(array_filter(
                self::RESOLVE_NUMBER_COLUMNS,
                fn ($c) => in_array(strtoupper(trim((string) ($row->{$c} ?? ''))), $upper, true)
            ));
        });
    }

    /**
     * What the shared Cadastral file picker needs about one file.
     *
     * $q takes the picked number (file_number + the selector's tab), or an id
     * when the file is already known: file_indexing_id (the clerk chose from an
     * ambiguous list), receipt, or card. scope and purpose say which forms
     * will take the file; see RESOLVE_SCOPES / RESOLVE_PURPOSES.
     *
     * Returns status ok | ambiguous | refused | not_found. A refused file still
     * carries its details and flags so the picker can show why. 'values' are
     * the form fields the file's records supply (blank ones stay with the
     * clerk); 'hidden' are the ids the form posts. READ ONLY.
     */
    public function resolveFile(array $q): array
    {
        $scope   = in_array($q['scope'] ?? null, self::RESOLVE_SCOPES, true) ? $q['scope'] : 'indexed';
        $purpose = in_array($q['purpose'] ?? null, self::RESOLVE_PURPOSES, true) ? $q['purpose'] : null;
        $tab     = strtolower(trim((string) ($q['tab'] ?? ''))) ?: null;
        $number  = trim((string) ($q['file_number'] ?? ''));

        $out = [
            'status'  => 'not_found',
            'message' => null,
            'scope'   => $scope,
            'purpose' => $purpose,
            'query'   => [
                'file_number' => $number !== '' ? $number : null,
                'tab'         => $tab,
                'tab_label'   => $tab ? strtoupper($tab === 'mls' ? 'MLPP' : $tab) : null,
                'tab_source'  => $tab !== null ? (self::TAB_SOURCES[$tab] ?? null) : null,
            ],
            'candidates' => [],
            'file'       => null,
            'records'    => null,
            'flags'      => [],
            'values'     => [],
            'hidden'     => [],
        ];

        $row = $receipt = $card = null;

        if ($id = (int) ($q['card'] ?? 0)) {
            $card = \App\Models\Cadastral\CadastralIndexCard::find($id);
            if (! $card) return ['message' => 'That index card no longer exists.'] + $out;
            $receipt = $card->sourceReceipt();
            $row = $this->indexedRow(($card->getAttributes()['file_indexing_id'] ?? null) ?: $receipt?->file_indexing_id);
        } elseif ($id = (int) ($q['receipt'] ?? 0)) {
            $receipt = \App\Models\Cadastral\CadastralFileReceipt::find($id);
            if (! $receipt) return ['message' => 'That intake receipt no longer exists.'] + $out;
            $row = $this->indexedRow($receipt->file_indexing_id);
        } elseif ($id = (int) ($q['file_indexing_id'] ?? 0)) {
            $row = $this->indexedRow($id);
            if (! $row) return ['message' => 'That file is no longer in the file index. Select it again.'] + $out;
        } else {
            if ($number === '') {
                return ['message' => 'Select a file number first.'] + $out;
            }

            $matches = $this->matchIndexedFiles($number);
            $live    = $matches->reject(fn ($m) => (bool) $m->is_decommissioned)->values();

            if ($live->count() > 1) {
                // Several rows carry this number. Which one is meant is the
                // clerk's call, so the list goes back rather than a guess.
                $tabSource = $out['query']['tab_source'];
                $out['status']     = 'ambiguous';
                $out['message']    = "{$number} is on {$live->count()} indexed files. Choose the one you mean.";
                $out['candidates'] = $live
                    ->map(fn ($m) => $this->candidate($m, $tabSource))
                    ->sortByDesc('tab_match')->values()->all();

                return $out;
            }

            if ($live->isEmpty() && $matches->isNotEmpty()) {
                $dead = $matches->first();
                $file = $this->formatSourceFile($dead, (string) $this->sourceOfRegistry($dead->registry));
                $out['status']  = 'refused';
                $out['file']    = $file + ['registry_label' => self::registryLabel($dead->registry)];
                $out['message'] = "{$file['file_number']} has been decommissioned"
                    . ($file['successor'] ? " and replaced by {$file['successor']}" : '')
                    . '. Select the current file instead.';
                $out['flags'][] = ['danger', 'Decommissioned' . ($file['successor'] ? ': now ' . $file['successor'] : '')];

                return $out;
            }

            $row = $live->first();

            // Not indexed at all: a receipt or card may still carry the number
            // (the row was indexed when the file came in), and the later stages
            // work from those.
            if (! $row && in_array($scope, ['receipt', 'plan', 'card'], true)) {
                $spellings = $this->spellings($number);
                $receipt = \App\Models\Cadastral\CadastralFileReceipt::query()
                    ->whereIn('file_number', $spellings)->orderByDesc('id')->first();
                $card = \App\Models\Cadastral\CadastralIndexCard::query()
                    ->whereIn('file_number', $spellings)->orderByDesc('id')->first();
            }

            if (! $row && ! $receipt && ! $card) {
                $out['message'] = "{$number} is not in the file index (file_indexings), so the Cadastral module cannot take it. "
                    . 'Index the file first, or check the number.';

                return $out;
            }
        }

        $source = $row
            ? $this->formatSourceFile($row, (string) $this->sourceOfRegistry($row->registry))
            : null;

        $fileNumber = $source['file_number'] ?? $receipt?->file_number ?? $card?->file_number;
        $records    = $this->moduleRecords($row ? (int) $row->id : null, $fileNumber, $receipt, $card);

        // The receipt the later stages build on: the one asked for if it
        // qualifies, else the file's latest registered live receipt.
        $registered = $records['registered_model'];
        $cardModel  = $records['card_model'];
        unset($records['registered_model'], $records['card_model']);

        $file = $this->filePayload($source, $registered ?? $receipt, $cardModel, $row);

        $out['file']    = $file;
        $out['records'] = $records;
        $out['flags']   = $this->flags($file, $records, $scope);
        $out['values']  = $this->fileValues(
            $source,
            $scope === 'intake' ? null : ($registered ?? $receipt),
            $scope === 'card' ? $cardModel : null
        );
        $out['hidden'] = array_filter([
            'file_indexing_id'              => $file['file_indexing_id'],
            'source_registry'               => $scope === 'intake' ? $file['source'] : null,
            'cadastral_file_receipt_id'     => $registered?->id,
            'cadastral_index_card_id'       => $records['card']['id'] ?? null,
            'cadastral_plan_description_id' => $records['plan_description']['id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $refusal = $this->scopeRefusal($scope, $purpose, $file, $records);

        $out['status']  = $refusal === null ? 'ok' : 'refused';
        $out['message'] = $refusal;

        if ($refusal !== null) {
            // A refused file posts nothing.
            $out['hidden'] = [];
        }

        return $out;
    }

    /**
     * The form values a file's records supply, best record first: the index
     * card (card-scoped forms), then the intake receipt — which holds the
     * source's values plus whatever the clerk filled in at intake — then the
     * file_indexings row. A blank in all of them stays blank, and the form
     * leaves that field to the clerk.
     *
     * The address travels as a group per kind: district with its "Other"
     * text, street with its. Location is "District, LGA, State", never the plot.
     *
     * @param  array|null  $source  formatSourceFile shape
     */
    public function fileValues(?array $source, $receipt = null, $card = null): array
    {
        $layers = [];

        if ($card) {
            $layers[] = ['file_title' => $card->file_title, 'plot' => $card->plot_no ?: $card->prop_plot] + $this->prefixed($card);
        }
        if ($receipt) {
            $layers[] = ['file_title' => $receipt->file_title, 'plot' => $receipt->prop_plot] + $this->prefixed($receipt);
        }
        if ($source) {
            $layers[] = ['file_title' => $source['owner'], 'plot' => $source['plot']] + $source['address'];
        }

        $first = function (string $key) use ($layers) {
            foreach ($layers as $layer) {
                $v = $layer[$key] ?? null;
                if ($v !== null && trim((string) $v) !== '') return trim((string) $v);
            }
            return null;
        };

        $values = [
            'file_title' => $first('file_title'),
            'plot_no'    => $first('plot'),
            'prop_plot'  => $first('plot'),
            'prop_house' => $first('prop_house'),
            'prop_lga'   => $first('prop_lga'),
            'prop_state' => $first('prop_state') ?? 'Kano',
        ];

        foreach (['district', 'street'] as $kind) {
            $values['prop_' . $kind] = null;
            $values['prop_' . $kind . '_other'] = null;

            foreach ($layers as $layer) {
                if (trim((string) ($layer['prop_' . $kind] ?? '')) !== '') {
                    $values['prop_' . $kind]            = $layer['prop_' . $kind];
                    $values['prop_' . $kind . '_other'] = $layer['prop_' . $kind . '_other'] ?? null;
                    break;
                }
            }
        }

        $values['location'] = CadastralAddress::propertyLocation($values) ?: null;

        return $values;
    }

    /**
     * The subset of $values a form must take as given, keyed by form field.
     *
     * Controllers merge this over the request BEFORE validating, so a value
     * the file supplies wins over anything posted (a disabled input posts
     * nothing anyway) and still satisfies a required rule. A blank stays out,
     * and the clerk's input for it is used. Mirrors what the picker locks.
     *
     * @param  string[]  $fields  the fields this form has
     */
    public static function lockedInput(array $values, array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            if (str_ends_with($field, '_other')) {
                continue;   // travels with its select, below
            }

            $v = $values[$field] ?? null;
            if ($v === null || trim((string) $v) === '') {
                continue;
            }

            $out[$field] = $v;

            if (in_array($field, ['prop_district', 'prop_street'], true)) {
                $out[$field . '_other'] = $values[$field . '_other'] ?? null;
            }
        }

        return $out;
    }

    /* ------------------------------ picker helpers ------------------------------ */

    /** The number as typed and in its normalised spelling. */
    private function spellings(?string $fileNumber): array
    {
        return array_values(array_unique(array_filter([
            trim((string) $fileNumber), FileNumberFormat::normalise($fileNumber),
        ])));
    }

    /** One live file_indexings row by id, or null. */
    private function indexedRow($id): ?object
    {
        if (! $id) {
            return null;
        }

        return $this->conn()->table('file_indexings')
            ->select(array_values(array_unique(array_merge(self::SOURCE_FILE_COLUMNS, ['mls_file_no', 'new_kangis_file_no']))))
            ->where('id', (int) $id)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->first();
    }

    /** "1" reads as "Lands Registry 1"; the rest are already names. */
    public static function registryLabel(?string $registry): string
    {
        $registry = trim((string) $registry);

        return match (true) {
            $registry === ''                            => 'No registry',
            in_array($registry, ['1', '2', '3'], true)  => "Lands Registry {$registry}",
            default                                     => $registry,
        };
    }

    /** A row of an ambiguous match, as the picker lists it. */
    private function candidate(object $row, ?string $tabSource): array
    {
        $source = $this->sourceOfRegistry($row->registry);
        $file   = $this->formatSourceFile($row, (string) $source);

        $labels = [
            'file_number' => 'file number', 'st_fillno' => 'ST number', 'kangis_file_no' => 'KANGIS number',
            'mls_file_no' => 'MLS number', 'new_kangis_file_no' => 'New KANGIS number',
        ];

        return [
            'id'          => $file['id'],
            'file_number' => $file['file_number'],
            'owner'       => $file['owner'],
            'plot'        => $file['plot'],
            'location'    => $file['location'],
            'registry'    => self::registryLabel($row->registry),
            'source'      => $source,
            'matched_on'  => array_map(fn ($c) => $labels[$c] ?? $c, $row->matched_on ?? []),
            'tab_match'   => $tabSource !== null && $source === $tabSource,
        ];
    }

    /** prop_* columns of a module record. */
    private function prefixed($model): array
    {
        $out = [];
        foreach (['house', 'plot', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'] as $s) {
            $out['prop_' . $s] = $model->{'prop_' . $s} ?? null;
        }
        return $out;
    }

    /**
     * The module's own records for a file: receipts, card, plan description,
     * current chart, reports and live survey jobs. By file_indexing_id where
     * the record keeps one, and by the file number either way.
     */
    private function moduleRecords(?int $indexingId, ?string $fileNumber, $askedReceipt = null, $askedCard = null): array
    {
        $numbers = $this->spellings($fileNumber);

        $receipts = \App\Models\Cadastral\CadastralFileReceipt::query()
            ->where(function ($w) use ($indexingId, $numbers) {
                $w->whereIn('file_number', $numbers ?: ['']);
                if ($indexingId) $w->orWhere('file_indexing_id', $indexingId);
            })
            ->orderByDesc('id')->limit(25)->get();

        $open = $receipts->first(fn ($r) => ! in_array($r->status, self::CLOSED_RECEIPT_STATUSES, true));

        $qualifies = fn ($r) => $r && $r->registered_at && ! in_array($r->status, self::DEAD_RECEIPTS, true);
        $registered = $qualifies($askedReceipt) ? $askedReceipt : $receipts->first($qualifies);

        $card = $askedCard ?: \App\Models\Cadastral\CadastralIndexCard::query()
            ->where(function ($w) use ($indexingId, $numbers) {
                $w->whereIn('file_number', $numbers ?: ['']);
                if ($indexingId && \App\Models\Cadastral\CadastralIndexCard::linkInstalled()) {
                    $w->orWhere('file_indexing_id', $indexingId);
                }
            })
            ->orderByDesc('id')->first();

        $plan = \App\Models\Cadastral\CadastralPlanDescription::query()
            ->whereIn('file_number', $numbers ?: [''])->orderByDesc('id')->first();

        $chart = \App\Models\Cadastral\CadastralChart::query()->current()
            ->whereIn('file_number', $numbers ?: [''])->orderByDesc('id')->first();

        $reports = \App\Models\Cadastral\CadastralReport::query()
            ->where(function ($w) use ($numbers, $receipts) {
                $w->whereIn('file_number', $numbers ?: ['']);
                if ($receipts->isNotEmpty()) $w->orWhereIn('cadastral_file_receipt_id', $receipts->pluck('id')->all());
            })
            ->orderByDesc('id')->limit(10)
            ->get(['id', 'report_ref', 'report_type', 'status']);

        $jobs = \App\Models\Cadastral\CadastralSurveyJob::query()
            ->whereIn('file_number', $numbers ?: [''])
            ->whereNotIn('status', self::DEAD_JOBS)
            ->orderByDesc('id')->limit(10)
            ->get(['id', 'job_number', 'status']);

        $receiptRow = fn ($r) => $r ? [
            'id'         => $r->id,
            'ref'        => $r->receipt_ref,
            'status'     => $r->status,
            'registered' => (bool) $r->registered_at,
            'on_hold'    => $r->isOnHold(),
            'hold_reason'=> $r->isOnHold() ? $r->hold_reason : null,
            'source'     => $r->source_registry,
        ] : null;

        return [
            'open_receipt'       => $receiptRow($open),
            'registered_receipt' => $receiptRow($registered),
            'card'               => $card ? [
                'id'          => $card->id,
                'ref'         => $card->card_ref,
                'file_status' => $card->file_status,
                'status_label'=> $card->file_status_label,
                'survey_job'  => $card->survey_job_number,
            ] : null,
            'plan_description'   => $plan ? ['id' => $plan->id, 'ref' => $plan->pd_ref] : null,
            'chart'              => $chart ? [
                'id' => $chart->id, 'ref' => $chart->chart_ref, 'status' => $chart->status,
                'charting_required' => (bool) $chart->charting_required,
            ] : null,
            'reports'            => $reports->map(fn ($r) => [
                'id' => $r->id, 'ref' => $r->report_ref, 'type' => $r->report_type, 'status' => $r->status,
            ])->all(),
            'survey_jobs'        => $jobs->map(fn ($j) => [
                'id' => $j->id, 'text' => "{$j->job_number} ({$j->status})",
            ])->all(),
            'registered_model'   => $registered,
            'card_model'         => $card,
        ];
    }

    /** The file as the picker's summary card shows it, from whichever records exist. */
    private function filePayload(?array $source, $receipt, $card, ?object $row): array
    {
        $number = $source['file_number'] ?? $receipt?->file_number ?? $card?->file_number;
        $sourceName = $source['source'] ?? null;
        $sourceName = $sourceName !== '' ? $sourceName : null;
        $class  = FileNumberFormat::classify($number);

        return [
            'file_indexing_id' => $source['id'] ?? $receipt?->file_indexing_id,
            'file_number'      => $number,
            'other_numbers'    => array_values(array_unique(array_filter(array_merge(
                $source['other_numbers'] ?? [],
                $row ? array_map(fn ($c) => trim((string) ($row->{$c} ?? '')), ['mls_file_no', 'new_kangis_file_no']) : []
            ), fn ($v) => $v !== '' && $v !== $number))),
            'registry'         => $row?->registry,
            'registry_label'   => $row ? self::registryLabel($row->registry) : null,
            'source'           => $sourceName ?? $receipt?->source_registry,
            'owner'            => ($source['owner'] ?? '') ?: ($receipt?->file_title ?: $card?->file_title),
            'land_use'         => $source['land_use'] ?? FileNumberFormat::landUseLabel($number),
            'file_class'       => $class,
            'type'             => self::typeLabel($number, $sourceName ?? $receipt?->source_registry, $class, $row?->land_use_type),
            'decommissioned'   => (bool) ($source['decommissioned'] ?? false),
            'successor'        => $source['successor'] ?? null,
            'indexed'          => $row !== null,
        ];
    }

    /** @return array<int, array{0: string, 1: string}>  [level, text]; level ok|info|warn|danger */
    private function flags(array $file, array $records, string $scope): array
    {
        $flags = [];

        if (! $file['indexed'])                 $flags[] = ['warn', 'Not in the file index'];
        if ($file['decommissioned'])            $flags[] = ['danger', 'Decommissioned' . ($file['successor'] ? ': now ' . $file['successor'] : '')];
        if ($file['file_class'] === 'conversion') $flags[] = ['info', 'Conversion file: charting not required'];

        if ($r = $records['open_receipt']) {
            $flags[] = $r['on_hold']
                ? ['danger', "On Hold ({$r['ref']})" . ($r['hold_reason'] ? ': ' . $r['hold_reason'] : '')]
                : [$scope === 'intake' ? 'danger' : 'info', "Open receipt {$r['ref']} ({$r['status']})"];
        } elseif ($records['registered_receipt']) {
            $flags[] = ['ok', "Receipt {$records['registered_receipt']['ref']}"];
        } else {
            $flags[] = ['warn', 'No intake receipt'];
        }

        $flags[] = $records['card']
            ? ['ok', "Index card {$records['card']['ref']} ({$records['card']['status_label']})"]
            : ['warn', 'No index card'];

        if ($records['chart'])            $flags[] = ['ok', "Chart {$records['chart']['ref']} ({$records['chart']['status']})"];
        if ($records['plan_description']) $flags[] = ['ok', "Plan description {$records['plan_description']['ref']}"];
        if ($records['reports'])          $flags[] = ['info', count($records['reports']) . ' report(s)'];

        return $flags;
    }

    /** Why a form at this scope cannot take the file, or null when it can. */
    private function scopeRefusal(string $scope, ?string $purpose, array $file, array $records): ?string
    {
        $n = $file['file_number'];

        if ($scope === 'intake') {
            if (! $file['indexed']) {
                return "{$n} is not in the file index, so it cannot be logged in.";
            }
            if ($file['source'] === null || ! isset(self::INTAKE_SOURCES[$file['source']])) {
                return "{$n} is indexed under " . ($file['registry_label'] ?? 'no registry') . ', which is not an intake source. '
                    . 'Files can be logged in from ' . implode(', ', array_keys(self::INTAKE_SOURCES)) . ' (Deeds is not supported yet).';
            }
            if ($file['decommissioned']) {
                return "{$n} has been decommissioned" . ($file['successor'] ? " and replaced by {$file['successor']}" : '') . '. Log the current file instead.';
            }
            if ($r = $records['open_receipt']) {
                return "{$n} is already in the queue as {$r['ref']}. Archive, return or reject that receipt before logging the file again.";
            }
        }

        if ($scope === 'indexed' && ! $file['indexed']) {
            return "{$n} is not in the file index. Only an indexed file can be used here.";
        }

        if (in_array($scope, ['receipt', 'plan'], true) && ! $records['registered_receipt']) {
            $open = $records['open_receipt'];

            return $open
                ? "{$n} is in the intake queue as {$open['ref']} but is not registered yet"
                    . ($open['on_hold'] ? ' — it is On Hold for investigation' : '') . '. Register it on the Intake Queue first.'
                : "{$n} has not been received by Cadastral. Log it on Intake (Log Incoming File) and register it first.";
        }

        if ($scope === 'plan' && ! $records['plan_description']) {
            return "{$n} has no plan-description record yet. Start one on Plans & Descriptions (Area & Pillars) first.";
        }

        if ($scope === 'card' && ! $records['card']) {
            return "{$n} has no index card. Commission one on Index Cards first.";
        }

        if ($purpose === 'chart') {
            if ($file['file_class'] === 'conversion') {
                return "{$n} is a conversion file: charting is not required. Commission its index card instead.";
            }
            if ($c = $records['chart']) {
                return "{$n} is already charted as {$c['ref']}. Open that chart and create a new version instead of a second chart.";
            }
        }

        if ($purpose === 'commission' && ($c = $records['card'])) {
            return "{$n} already has index card {$c['ref']}. Update that card instead of commissioning a second.";
        }

        return null;
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
