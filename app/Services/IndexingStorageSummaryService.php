<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Where did this record actually go?" — built right after a file indexing save
 * so the confirmation card can spell out every table the submission wrote to.
 *
 * Indexing one file fans out across a dozen tables (registry, parties, CofO/PRA
 * transactions, commissioning mirrors, bills, prop_id lineage), all of it done
 * quietly by FileIndexingController::store(). Operators had no way to tell which
 * of those actually happened for a given submission, so this counts the rows
 * that carry the saved file's numbers/id and reports them grouped.
 *
 * This is a read-only, best-effort report: it runs AFTER the save, and any
 * failure is swallowed (the record is already persisted — a broken summary must
 * never turn a successful save into an error). Counts are "rows that now exist"
 * rather than "rows this request inserted", which is what the operator is
 * actually asking about — an update that reused an existing CofO row still means
 * "the CofO is on this file".
 *
 * Mirrors the shape of IndexingDuplicateService::preview() so the front end can
 * render both with the same table renderer.
 */
class IndexingStorageSummaryService
{
    /** fileNumber registry columns that can hold this file's number. */
    private const FILE_NUMBER_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'];

    /** The usual file-number columns on the record/transaction tables. */
    private const RECORD_COLUMNS = ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'];

    /**
     * Memoised schema probes. Roughly twenty tables are checked per summary and
     * several share columns, so without this the INFORMATION_SCHEMA round trips
     * dominate the cost of the whole report.
     *
     * @var array<string,bool>
     */
    private array $schemaCache = [];

    /**
     * Summary of everything the just-saved indexing record now touches.
     *
     * @param  array  $context  Extra facts the controller already knows and that
     *                          cannot be read back off the tables — currently
     *                          'parent_prop_id', 'kangis_record' and 'is_update'.
     * @return array{file_number:string,groups:array,notes:array}|null null when the summary could not be built
     */
    public function summarize(FileIndexing $record, array $context = []): ?array
    {
        try {
            return $this->build($record, $context);
        } catch (\Throwable $e) {
            Log::warning('IndexingStorageSummaryService::summarize - failed', [
                'file_indexing_id' => $record->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function build(FileIndexing $record, array $context): array
    {
        $id = (int) $record->id;
        $numbers = $this->collectNumbers($record);
        $propId = $record->prop_id !== null ? (int) $record->prop_id : null;

        $registry = [];
        // Normally the caller hands us the row it just saved, so the count is 1 by
        // definition. Callers may also pass an UNSAVED stand-in carrying only a file
        // number (ST unit commissioning, where no file_indexings row is created) —
        // there the table must actually be counted, or the card would claim an
        // indexed record that does not exist.
        $this->push(
            $registry,
            'file_indexings',
            $record->exists ? 1 : $this->countIn('file_indexings', ['file_number', 'temp_file_no'], $numbers),
            'Indexed file record',
            $record->file_number ?: $record->temp_file_no
        );
        $this->push(
            $registry,
            'fileNumber',
            $this->countIn('fileNumber', self::FILE_NUMBER_COLUMNS, $numbers),
            'File number'
        );
        if ($propId !== null) {
            $this->push(
                $registry,
                'PropID_Master',
                $this->countWhere('PropID_Master', 'prop_id', $propId),
                'Property ID (prop_id)',
                (string) $propId
            );
        }
        // ST files are allocated in st_file_numbers before/alongside indexing, so an
        // ST primary or unit shows its allocation row here too. Non-ST files simply
        // match nothing and the row is omitted.
        $this->push(
            $registry,
            'st_file_numbers',
            $this->countIn('st_file_numbers', ['np_fileno', 'fileno', 'mls_fileno'], $numbers),
            'ST file number allocation'
        );
        $this->push(
            $registry,
            'file_indexing_links',
            $this->countWhere('file_indexing_links', 'file_indexing_id', (string) $id),
            'Related / block file link(s)'
        );
        $this->push(
            $registry,
            'master_dciv_links',
            $this->countIn('master_dciv_links', ['dciv_file_number', 'related_file_number'], $numbers),
            'DCIV master link(s)'
        );

        $parties = [];
        $this->push(
            $parties,
            'customers_staging',
            $this->countIn('customers_staging', ['file_number'], $numbers),
            'Customer record(s)'
        );
        $this->push(
            $parties,
            'entities_staging',
            $this->countIn('entities_staging', ['file_number'], $numbers),
            'Entity record(s)'
        );

        $transactions = [];
        $this->push(
            $transactions,
            'CofO_staging',
            $this->countIn('CofO_staging', self::RECORD_COLUMNS, $numbers),
            'Certificate(s) of Occupancy'
        );
        $this->push(
            $transactions,
            'pra',
            $this->countIn('pra', self::RECORD_COLUMNS, $numbers),
            'Property records (PRA / RoFO / OP)'
        );
        $this->push(
            $transactions,
            'file_history_staging',
            $this->countIn('file_history_staging', self::RECORD_COLUMNS, $numbers),
            'File history entries'
        );

        $onward = [];
        $this->push(
            $onward,
            'mls_file_no',
            $this->countIn('mls_file_no', ['full_file_number'], $numbers),
            'Commissioning register'
        );
        $this->push(
            $onward,
            'oss_applications',
            $this->countOssNoChangeApplications($numbers),
            'OSS application (No Change of Ownership)'
        );
        $this->push(
            $onward,
            'dciv_file_no',
            $this->countIn('dciv_file_no', ['full_file_number'], $numbers),
            'DCIV commissioning register'
        );
        $this->push(
            $onward,
            'gkn_file_no',
            $this->countIn('gkn_file_no', ['full_file_number'], $numbers),
            'Survey (GKN) commissioning register'
        );
        $this->pushTrackingLines($onward, $numbers);
        $this->push(
            $onward,
            'indexed_file_trackers',
            $this->countWhere('indexed_file_trackers', 'file_indexing_id', (string) $id),
            'File tracker record(s)'
        );
        $this->push(
            $onward,
            'file_indexing_bills',
            $this->countWhere('file_indexing_bills', 'file_indexing_id', $id),
            'Bill(s) — balance / grant rent'
        );

        $notes = $this->buildNotes($record, $context, $numbers, $propId);

        $groups = array_values(array_filter([
            $this->group('Registry & identity', 'primary', $registry),
            $this->group('Customer / Entity', 'parties', $parties),
            $this->group('Records & transactions', 'records', $transactions),
            $this->group('Onward / derived', 'onward', $onward),
        ]));

        $total = 0;
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $total += $row['count'];
            }
        }

        return [
            'is_update'      => (bool) ($context['is_update'] ?? false),
            'file_indexing_id' => $id,
            'file_number'    => (string) ($record->file_number ?: $record->temp_file_no ?: ''),
            'file_title'     => $record->file_title ?: null,
            'tracking_id'    => $record->tracking_id ?: null,
            'registry'       => $record->general_registry ?: ($record->registry ?: null),
            'prop_id'        => $propId,
            'parent_prop_id' => isset($context['parent_prop_id']) && $context['parent_prop_id'] !== null
                ? (int) $context['parent_prop_id']
                : null,
            'matched_numbers' => $numbers,
            'groups'         => $groups,
            'notes'          => $notes,
            'total_rows'     => $total,
        ];
    }

    /**
     * The one-line explanations that a row count cannot carry on its own — the
     * separate New KANGIS record, the prop_id lineage, and the shelf/batch the
     * physical file now sits on.
     *
     * @return array<int,array{tone:string,text:string}>
     */
    private function buildNotes(FileIndexing $record, array $context, array $numbers, ?int $propId): array
    {
        $notes = [];

        $kangis = $context['kangis_record'] ?? null;
        if ($kangis) {
            $notes[] = [
                'tone' => 'info',
                'text' => sprintf(
                    'A separate indexing record was created for New KANGIS number %s (prop_id %s). '
                    . 'Transactions captured on this form were saved against THAT record, not this file.',
                    $kangis->file_number ?? '—',
                    $kangis->prop_id ?? '—'
                ),
            ];
        }

        // Where the registry range (config/file_ranges.php) placed the file, or why
        // it could not be placed — the operator's main "so where IS this file now?"
        // question, which no row count answers on its own.
        $range = $context['range_tracking'] ?? null;
        if (is_array($range)) {
            $notes[] = match ($range['reason']) {
                'created' => [
                    'tone' => 'info',
                    // Just where the file was logged. The zone/year-range reasoning
                    // behind that placement is config/file_ranges.php's business, not
                    // something the operator needs read back to them every save.
                    'text' => 'File Logged: ' . $range['location'],
                ],
                'already_tracked' => [
                    'tone' => 'muted',
                    'text' => 'This file already has file tracker history, so its existing location was left alone.',
                ],
                'commissioned' => [
                    'tone' => 'info',
                    'text' => 'Commissioned in KLAES — the file is still in process at the File Commissioning Office, '
                        . 'so no archive/pool tracking line was opened for it.',
                ],
                'no_range_match' => [
                    'tone' => 'muted',
                    'text' => 'This file number falls outside every configured registry range, so no location could be '
                        . 'assigned — searches will refer it to the original registry.',
                ],
                default => [
                    'tone' => 'muted',
                    'text' => 'No tracking line was opened for this file.',
                ],
            };
        }

        // The scan folder is not a table, so it has no row above — but it is the one
        // thing the operator physically goes looking for next, so it gets its own note.
        $scanFolder = $context['scan_folder'] ?? null;
        if (is_array($scanFolder) && !empty($scanFolder['path'])) {
            $notes[] = match ($scanFolder['reason']) {
                'created' => [
                    'tone' => 'info',
                    'text' => 'Scan folder created — drop scans for this file in ' . $scanFolder['path'] . '.',
                ],
                'already_exists' => [
                    'tone' => 'muted',
                    'text' => 'Scan folder already existed at ' . $scanFolder['path'] . '.',
                ],
                default => [
                    'tone' => 'muted',
                    'text' => 'The scan folder ' . $scanFolder['path'] . ' could not be created — '
                        . 'it will be made on the first upload.',
                ],
            };
        }

        // The counterpart folios: the same file number's folders in the registries
        // the file also physically sits in. Named individually rather than summed,
        // because the operator walking to the Cadastral registry needs that one
        // path — "2 folios created" tells them nothing they can act on.
        $folios = is_array($scanFolder) ? ($scanFolder['folios'] ?? []) : [];
        foreach ($folios as $folioRegistry => $folio) {
            if (!is_array($folio) || empty($folio['path'])) {
                continue;
            }

            $notes[] = match ($folio['reason']) {
                'created' => [
                    'tone' => 'info',
                    'text' => $folioRegistry . ' folio created at ' . $folio['path'] . '.',
                ],
                'already_exists' => [
                    'tone' => 'muted',
                    'text' => $folioRegistry . ' folio already existed at ' . $folio['path'] . '.',
                ],
                default => [
                    'tone' => 'muted',
                    'text' => 'The ' . $folioRegistry . ' folio ' . $folio['path'] . ' could not be created — '
                        . 'it will be made on the first upload.',
                ],
            };
        }

        $parentPropId = $context['parent_prop_id'] ?? null;
        if ($propId !== null && $parentPropId !== null && (int) $parentPropId !== $propId) {
            $notes[] = [
                'tone' => 'info',
                'text' => sprintf(
                    'This file hangs off parent property %d — Legal Search will show it under that parcel\'s lineage.',
                    (int) $parentPropId
                ),
            ];
        }

        if ($record->batch_no || $record->shelf_location) {
            $notes[] = [
                'tone' => 'info',
                'text' => sprintf(
                    'Physical file filed at batch %s, shelf %s.',
                    $record->batch_no ?: '—',
                    $record->shelf_location ?: '—'
                ),
            ];
        }

        if (count($numbers) > 1) {
            $notes[] = [
                'tone' => 'muted',
                'text' => 'Counted against file number(s): ' . implode(', ', $numbers) . '.',
            ];
        }

        return $notes;
    }

    /**
     * @param  array<string,array{table:string,label:string,count:int,detail:?string}>  $rows
     * @return array{title:string,tone:string,rows:array}|null null when nothing landed in the group
     */
    private function group(string $title, string $tone, array $rows): ?array
    {
        if (empty($rows)) {
            return null;
        }

        return ['title' => $title, 'tone' => $tone, 'rows' => array_values($rows)];
    }

    /**
     * The file's tracking lines, one row each rather than a single "1 tracker row"
     * count.
     *
     * A file_tracker row carries its whole history in one movement_log, so the
     * count of ROWS says nothing useful — a freshly commissioned file is one row
     * holding TWO lines: the File Commissioning line (DIIT) opened at the File
     * Commissioning Office, and the onward Log-out to wherever it was sent. Those
     * are what the operator wants to see, so each becomes its own row here.
     *
     * A commissioned file that was never given a destination has no stored tracker
     * at all — its commissioning line is derived at read time by
     * FileCommissioningTrackingService — so that case is reported as the derived
     * line rather than as nothing.
     *
     * @param array<string,array> $bucket
     * @param array<int,string>   $numbers
     */
    private function pushTrackingLines(array &$bucket, array $numbers): void
    {
        if (empty($numbers) || !$this->hasTable('file_tracker')) {
            return;
        }

        try {
            $tracker = DB::connection('sqlsrv')->table('file_tracker')
                ->whereIn('file_number', $numbers)
                ->orderByDesc('id')
                ->first(['id', 'movement_log', 'current_office_name']);
        } catch (\Throwable $e) {
            return;
        }

        if (!$tracker) {
            // No stored tracker. A KLAES-commissioned file still shows the derived
            // File Commissioning line, which is how it reads on the File Log Table.
            if ($this->countIn('mls_file_no', ['full_file_number'], $numbers) > 0) {
                $this->push(
                    $bucket,
                    'file_tracker',
                    1,
                    'File Commissioning (DIIT)',
                    'Derived — File Commissioning Office',
                    'file_tracker#diit'
                );
            }

            return;
        }

        $log = $tracker->movement_log;
        if (is_string($log)) {
            $log = json_decode($log, true);
        }

        if (!is_array($log) || empty($log)) {
            $this->push($bucket, 'file_tracker', 1, 'File tracker line(s)', $tracker->current_office_name ?: null);
            return;
        }

        foreach (array_values($log) as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $this->push(
                $bucket,
                'file_tracker',
                1,
                $this->trackingLineLabel($entry),
                trim((string) ($entry['office_name'] ?? $entry['receiving_office_name'] ?? '')) ?: null,
                'file_tracker#' . $index
            );
        }
    }

    /**
     * Display label for one movement entry.
     *
     * status_label is set explicitly by the lines that need naming — "File
     * Commissioning" (DIIT), "In Archive" / "In Pool Office" (the range-derived
     * home row). Everything else is an ordinary movement, named by whether the
     * file has left (Log-out) or come back (Log-in), matching the File Log Table.
     *
     * @param array<string,mixed> $entry
     */
    private function trackingLineLabel(array $entry): string
    {
        $label = trim((string) ($entry['status_label'] ?? ''));
        if ($label !== '') {
            // Mark the derived commissioning line so it is not mistaken for a
            // movement somebody actually logged.
            return !empty($entry['_diit']) ? $label . ' (DIIT)' : $label;
        }

        $status = strtolower(trim((string) ($entry['status'] ?? '')));

        if ($status === 'pending_acceptance') {
            return 'In-Transit (awaiting acceptance)';
        }

        // A returned file carries a log-in; anything still out shows its log-out.
        if (!empty($entry['log_in_date']) && empty($entry['log_out_date'])) {
            return 'Log-in';
        }

        return 'Log-out';
    }

    /**
     * @param array<string,array> $bucket
     * @param string|null $key Bucket key, when several rows share one table name
     *                         (the file_tracker movement lines). Defaults to the
     *                         table, so one row per table is the normal case.
     */
    private function push(array &$bucket, string $table, int $count, string $label, ?string $detail = null, ?string $key = null): void
    {
        if ($count <= 0) {
            return;
        }

        $bucket[$key ?? $table] = [
            'table'  => $table,
            'label'  => $label,
            'count'  => $count,
            'detail' => $detail !== null && trim($detail) !== '' ? trim($detail) : null,
        ];
    }

    /** Rows whose $column equals $value, 0 when the table/column is absent. */
    private function countWhere(string $table, string $column, $value): int
    {
        if (!$this->hasTable($table) || !$this->hasColumn($table, $column)) {
            return 0;
        }

        return DB::connection('sqlsrv')->table($table)->where($column, $value)->count();
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaCache['t:' . $table]
            ??= Schema::connection('sqlsrv')->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->schemaCache['c:' . $table . '.' . $column]
            ??= Schema::connection('sqlsrv')->hasColumn($table, $column);
    }

    /**
     * Rows carrying any of this file's numbers in any of $columns. Columns the
     * install does not have are skipped — these staging tables differ between
     * deployments, and a missing column must not blow up the confirmation card.
     */
    private function countIn(string $table, array $columns, array $numbers): int
    {
        if (empty($numbers) || !$this->hasTable($table)) {
            return 0;
        }

        $columns = array_values(array_filter(
            $columns,
            fn ($column) => $this->hasColumn($table, $column)
        ));

        if (empty($columns)) {
            return 0;
        }

        $query = DB::connection('sqlsrv')->table($table)
            ->where(function ($q) use ($columns, $numbers) {
                foreach ($columns as $column) {
                    $q->orWhereIn($column, $numbers);
                }
            });

        if ($this->hasColumn($table, 'is_deleted')) {
            $query->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
        }

        return $query->count();
    }

    /** Count only the OSS side that the no-change page displays. */
    private function countOssNoChangeApplications(array $numbers): int
    {
        if (empty($numbers) || !$this->hasTable('oss_applications') || !$this->hasColumn('oss_applications', 'file_no')) {
            return 0;
        }

        $query = DB::connection('sqlsrv')->table('oss_applications')->whereIn('file_no', $numbers);
        if ($this->hasColumn('oss_applications', 'system_source')) {
            $query->where(function ($q) {
                $q->whereNull('system_source')->orWhere('system_source', '<>', 'OSSOPCHANGEOFNAME');
            });
        }
        if ($this->hasColumn('oss_applications', 'is_deleted')) {
            $query->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });
        }

        return $query->count();
    }

    /**
     * Every number this record answers to. "-" is a placeholder used across the
     * staging tables for "no value" and would match unrelated rows.
     *
     * @return array<int,string>
     */
    private function collectNumbers(FileIndexing $record): array
    {
        $numbers = [];

        foreach (['file_number', 'temp_file_no', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no'] as $column) {
            $value = trim((string) ($record->{$column} ?? ''));
            if ($value !== '' && $value !== '-') {
                $numbers[$value] = $value;
            }
        }

        return array_values($numbers);
    }
}
