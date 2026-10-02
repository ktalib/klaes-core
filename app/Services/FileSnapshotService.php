<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\FileSnapshot;
use App\Services\EdmsScanUploadFolderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "What did this file look like, and who changed it?" — the append-only history
 * behind the File Snapshot card.
 *
 * file_indexings is edited IN PLACE. Every correction, re-index, newly linked
 * sibling and newly captured transaction overwrites what was there before, so the
 * only record of a file's past was whatever the operator remembered. This service
 * reads the file's whole readable state — identity, property, indexing, tracking,
 * EDMS, entity/customer, transactions, links, bills — and writes it as a NEW
 * version of file_snapshots, together with a diff against the previous version.
 *
 * Three rules this service is built around:
 *
 *   APPEND ONLY   Nothing is ever updated or deleted. A change is a new version.
 *                 The one exception to writing is a no-op: an identical payload
 *                 hash is skipped, because a save that changed nothing is not a
 *                 version of anything.
 *
 *   BEST EFFORT   Every entry point runs AFTER the save has committed. A failure
 *                 here must never turn a successful save into an error, so
 *                 capture() swallows everything and returns null. The visible
 *                 consequence of a broken snapshot is a missing card, not a 500.
 *
 *   DIRECT READS  Deliberately NOT LegalSearchService::buildPrintReport, which is
 *                 the richest history in the system but costs ~3-5s per file.
 *                 This runs on every save, edit and transaction capture, so it
 *                 reads the tables directly and stays in the low hundreds of ms.
 *
 * Mirrors the defensive posture of IndexingStorageSummaryService: memoised schema
 * probes, tables that may not exist on every environment are guarded, and a
 * section that cannot be read is omitted rather than faked.
 */
class FileSnapshotService
{
    /** The three tables a file's transactions live across. Same set as FileIndexingActivityLogController. */
    private const TXN_TABLES = ['file_history_staging', 'pra', 'CofO_staging'];

    /** File-number columns shared by those three tables. */
    private const TXN_FILE_COLUMNS = ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'];

    /**
     * Rows kept per collection in the payload. A file with hundreds of
     * transactions would otherwise write a multi-hundred-KB row on every edit,
     * and the card cannot usefully display them either. Overflow is reported as
     * a count so the trail never silently pretends the extras do not exist.
     */
    private const MAX_ROWS = 200;

    /** Memoised hasTable/hasColumn probes — a dozen tables are checked per snapshot. */
    private array $schemaCache = [];

    /** Normalised created_by key (id / full name / username / email) => display name. */
    private ?array $userIndex = null;

    /**
     * Write a new snapshot version for a file.
     *
     * @param  string $eventType  one of FileSnapshot::EVENT_*, or 'auto' to derive
     *                            it from what actually changed since the last version.
     * @param  array  $context    facts the caller already holds and that cannot be read
     *                            back off the tables: 'source', 'scan_folder',
     *                            'storage_summary', 'parent_prop_id', 'event_label'.
     */
    public function capture(FileIndexing $record, string $eventType, array $context = []): ?FileSnapshot
    {
        try {
            if (empty($record->id)) {
                return null;
            }

            return $this->write($record, $eventType, $context);
        } catch (\Throwable $e) {
            // The file is already saved. A snapshot that cannot be written is a
            // gap in the audit trail, which is worth a log line and nothing more.
            Log::warning('FileSnapshotService::capture - failed', [
                'file_indexing_id' => $record->id ?? null,
                'file_number' => $record->file_number ?? null,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Capture for a file identified only by its number — the transaction-capture
     * path, which knows the file number but not necessarily the indexing row.
     */
    public function captureByFileNumber(?string $fileNumber, string $eventType, array $context = []): ?FileSnapshot
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        try {
            $record = FileIndexing::on('sqlsrv')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber])
                ->first();

            if (!$record) {
                // Also try the temp-number column: "X(T)" and "X" are the same
                // physical file stored in different columns, and a literal lookup
                // on file_number misses the temp-only rows silently.
                $record = FileIndexing::on('sqlsrv')
                    ->whereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$fileNumber])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$fileNumber . '(T)'])
                    ->first();
            }

            return $record ? $this->capture($record, $eventType, $context) : null;
        } catch (\Throwable $e) {
            Log::warning('FileSnapshotService::captureByFileNumber - failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** The newest version for a file, or null when it has never been snapshotted. */
    public function latestFor(int $fileIndexingId): ?FileSnapshot
    {
        try {
            return FileSnapshot::on('sqlsrv')
                ->where('file_indexing_id', $fileIndexingId)
                ->orderByDesc('version')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('FileSnapshotService::latestFor - failed', [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ---------------------------------------------------------------- writing

    private function write(FileIndexing $record, string $eventType, array $context): ?FileSnapshot
    {
        $payload = $this->buildPayload($record, $context);
        $previous = $this->latestFor((int) $record->id);

        // The scan-folder outcome is handed in by the caller and only the CREATE
        // path has one — an edit never re-runs the folder creation. Without this,
        // v2 of every edited file would report its EDMS folders as removed, which
        // is a fabricated change: the folders are still there, we simply had no
        // context to read them. Carry the last known state forward instead.
        $payload = $this->carryForwardEdms($payload, $previous);

        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // A save that changed nothing is not a version of anything. Return the
        // existing row so the card still has something to show.
        if ($previous && $previous->payload_hash === $hash) {
            return $previous;
        }

        $changes = $previous ? $this->diff($previous->payload ?: [], $payload) : [];

        // 'auto' lets the caller say "something changed, work out what kind" —
        // the update path cannot know whether the operator edited a field, added a
        // related file, or both.
        if ($eventType === 'auto') {
            $eventType = $previous
                ? $this->deriveEventType($changes)
                : FileSnapshot::EVENT_INDEXED;
        }

        // Version 1 is always 'indexed' regardless of which entry point got there
        // first: the first snapshot of a file IS the indexing of that file.
        if (!$previous) {
            $eventType = FileSnapshot::EVENT_INDEXED;
            $changes = [];
        }

        $user = Auth::user();
        $performedAt = Carbon::now();

        return FileSnapshot::on('sqlsrv')->create([
            'file_indexing_id' => (int) $record->id,
            'file_number' => $this->clip($record->file_number, 255),
            'temp_file_no' => $this->clip($record->temp_file_no, 255),
            'tracking_id' => $this->clip($record->tracking_id, 255),
            'prop_id' => is_numeric($record->prop_id) ? (int) $record->prop_id : null,
            'parent_prop_id' => is_numeric($record->parent_prop_id) ? (int) $record->parent_prop_id : null,
            'version' => $previous ? ((int) $previous->version + 1) : 1,
            'event_type' => $eventType,
            'event_label' => $this->clip(
                $context['event_label'] ?? $this->labelFor($eventType, $changes),
                255
            ),
            'payload' => $payload,
            'changes' => $changes ?: null,
            'changed_field_count' => count($changes),
            'payload_hash' => $hash,
            'performed_by' => $user && is_numeric($user->id) ? (int) $user->id : null,
            'performed_by_name' => $this->clip($this->displayNameFor($user), 255),
            'performed_at' => $performedAt,
            'source' => $this->clip($context['source'] ?? null, 60),
            'ip_address' => $this->clip(request()?->ip(), 45),
            'user_agent' => $this->clip(request()?->userAgent(), 512),
        ]);
    }

    /**
     * Keep the previous version's EDMS folder list when this capture had no
     * scan-folder context to build one from.
     *
     * "Not supplied" and "no longer there" are different facts, and only the
     * create path ever supplies it. Reporting the second when we mean the first
     * would put a fabricated "removed" row in the audit trail.
     */
    private function carryForwardEdms(array $payload, ?FileSnapshot $previous): array
    {
        if (!empty($payload['edms']['folders']) || !$previous) {
            return $payload;
        }

        $inherited = $previous->payload['edms']['folders'] ?? null;
        if (empty($inherited)) {
            return $payload;
        }

        $payload['edms'] = (array) ($payload['edms'] ?? []);
        $payload['edms']['folders'] = $inherited;
        $payload['counts']['edms_folders'] = count($inherited);

        return $payload;
    }

    /**
     * Which kind of change this was. Checked most-specific first: a save that
     * touched only links is a linking event even though it also went through the
     * ordinary update path.
     *
     * @param array<int,array<string,mixed>> $changes
     */
    private function deriveEventType(array $changes): string
    {
        $sections = array_unique(array_map(
            static fn ($change) => explode('.', (string) ($change['path'] ?? ''))[0],
            $changes
        ));
        $sections = array_values(array_filter($sections));

        if ($sections === ['transactions']) {
            return FileSnapshot::EVENT_TRANSACTION_ADDED;
        }

        if ($sections === ['links']) {
            return FileSnapshot::EVENT_LINKED;
        }

        return FileSnapshot::EVENT_EDITED;
    }

    /** @param array<int,array<string,mixed>> $changes */
    private function labelFor(string $eventType, array $changes): string
    {
        $count = count($changes);
        $fields = $count === 1 ? '1 change' : ($count . ' changes');

        return match ($eventType) {
            FileSnapshot::EVENT_INDEXED => 'File created and indexed',
            FileSnapshot::EVENT_LINKED => 'Related / linked files updated (' . $fields . ')',
            FileSnapshot::EVENT_TRANSACTION_ADDED => 'Transaction captured (' . $fields . ')',
            default => 'File information edited (' . $fields . ')',
        };
    }

    // ---------------------------------------------------------------- payload

    /**
     * The file's whole readable state, section by section. Every section is
     * independently guarded: one unreadable table costs that section, not the
     * snapshot.
     *
     * @return array<string,mixed>
     */
    public function buildPayload(FileIndexing $record, array $context = []): array
    {
        $fileNumbers = $this->collectNumbers($record);

        $payload = [
            'identity' => $this->section(fn () => $this->identitySection($record)),
            'property' => $this->section(fn () => $this->propertySection($record)),
            'indexing' => $this->section(fn () => $this->indexingSection($record)),
            'tracking' => $this->section(fn () => $this->trackingSection($record)),
            'edms' => $this->section(fn () => $this->edmsSection($record, $context)),
            'parties' => $this->section(fn () => $this->partiesSection($record)),
            'entity' => $this->section(fn () => $this->entitySection($record)),
            'customer' => $this->section(fn () => $this->customerSection($record)),
            'transactions' => $this->section(fn () => $this->transactionsSection($fileNumbers), []),
            'links' => $this->section(fn () => $this->linksSection($record, $fileNumbers), []),
            'bills' => $this->section(fn () => $this->billsSection($record)),
        ];

        $payload['counts'] = [
            'transactions' => count($payload['transactions'] ?? []),
            'links' => count($payload['links'] ?? []),
            'tracking_lines' => count($payload['tracking']['trackers'] ?? []),
            'tracking_movements' => count($payload['tracking']['movements'] ?? []),
            'edms_folders' => count($payload['edms']['folders'] ?? []),
        ];

        // The destinations card content, when the caller has it. Stored so the
        // snapshot can be re-read later without recomputing the fan-out counts.
        if (!empty($context['storage_summary'])) {
            $payload['storage_summary'] = $context['storage_summary'];
        }

        return $payload;
    }

    /** Run one section, returning $fallback if it throws. */
    private function section(callable $builder, $fallback = null)
    {
        try {
            return $builder();
        } catch (\Throwable $e) {
            Log::debug('FileSnapshotService - section failed: ' . $e->getMessage());

            return $fallback;
        }
    }

    private function identitySection(FileIndexing $r): array
    {
        return $this->compact([
            'file_number' => $r->file_number,
            'file_title' => $r->file_title,
            'temp_file_no' => $r->temp_file_no,
            'has_temp_file' => $r->has_temp_file,
            'old_fileno' => $r->old_fileno,
            'mls_file_no' => $r->mls_file_no,
            'kangis_file_no' => $r->kangis_file_no,
            'new_kangis_file_no' => $r->new_kangis_file_no,
            'kangis_fileno_placeholder' => $r->kangis_fileno_placeholder,
            'kangis_fileno_resolved' => $r->kangis_fileno_resolved,
            'st_fillno' => $r->st_fillno,
            'registry' => $r->registry,
            'general_registry' => $r->general_registry,
            'physical_registry' => $r->physical_registry,
            'file_type' => $r->file_type,
            'indexing_type' => $r->indexing_type,
            'file_classification' => $r->file_classification,
            'prop_id' => $r->prop_id,
            'parent_prop_id' => $r->parent_prop_id,
            'ancestral_prop_id' => $r->ancestral_prop_id,
        ]);
    }

    private function propertySection(FileIndexing $r): array
    {
        return $this->compact([
            'land_use_type' => $r->land_use_type,
            'plot_number' => $r->plot_number,
            'plot_size' => $r->plot_size,
            'tp_no' => $r->tp_no,
            'lpkn_no' => $r->lpkn_no,
            'location' => $r->location,
            'street_name' => $r->street_name,
            'district' => $r->district,
            'lga' => $r->lga,
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'property_description' => $r->property_description,
            'term' => $r->term,
            'has_cofo' => $r->has_cofo,
            'has_rofo' => $r->has_rofo,
        ]);
    }

    private function indexingSection(FileIndexing $r): array
    {
        return $this->compact([
            'created_at' => $this->stamp($r->created_at),
            'created_by' => $r->created_by,
            'created_by_name' => $this->resolvedNameUnless($r->created_by),
            'updated_at' => $this->stamp($r->updated_at),
            'updated_by' => $r->updated_by,
            'updated_by_name' => $this->resolvedNameUnless($r->updated_by),
            'batch_no' => $r->batch_no,
            'st_batch_no' => $r->st_batch_no,
            'sys_batch_no' => $r->sys_batch_no,
            'registry_batch_no' => $r->registry_batch_no,
            'serial_no' => $r->serial_no,
            'group_no' => $r->group_no,
            'shelf_location' => $r->shelf_location,
            'shelf_label_id' => $r->shelf_label_id,
            'workflow_status' => $r->workflow_status,
            'is_updated' => $r->is_updated,
            'has_qc_issues' => $r->has_qc_issues,
            'is_merged' => $r->is_merged,
            'has_transaction' => $r->has_transaction,
            'is_problematic' => $r->is_problematic,
            'test_control' => $r->test_control,
        ]);
    }

    private function trackingSection(FileIndexing $r): array
    {
        $section = $this->compact([
            'tracking_id' => $r->tracking_id,
            'tracking_status' => $r->tracking_status,
            'current_location' => $r->current_location,
            'file_tracker_id' => $r->file_tracker_id,
            'location_status_manual' => $r->location_status_manual,
        ]);

        // The OPENING tracking line lives in `file_tracker` (singular) — the row
        // FileRangeTrackingService::openForIndexing writes so a file has a real
        // recorded location the moment it is indexed. `file_trackings` (plural) is
        // a DIFFERENT table, holding later movement/assignment rows. Reading only
        // the second is why a freshly indexed file looked untracked here.
        $section['trackers'] = $this->trackerLines($r);
        $section['movements'] = [];

        if (!$this->hasTable('file_trackings')) {
            return $section;
        }

        $rows = DB::connection('sqlsrv')->table('file_trackings')
            ->where('file_indexing_id', (int) $r->id)
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        $section['movements'] = $rows->map(fn ($row) => $this->compact([
            '_key' => 'file_trackings:' . ($row->id ?? '0'),
            'id' => $row->id ?? null,
            'status' => $row->status ?? null,
            'current_location' => $row->current_location ?? null,
            'current_holder' => $row->current_holder ?? null,
            'assigned_office' => $row->assigned_office ?? null,
            'assigned_to' => $row->assigned_to ?? null,
            'assigned_to_name' => $this->resolveUserName($row->assigned_to ?? null),
            'assigned_by' => $row->assigned_by ?? null,
            'assigned_by_name' => $this->resolveUserName($row->assigned_by ?? null),
            'assignment_status' => $row->assignment_status ?? null,
            'date_received' => $this->stamp($row->date_received ?? null),
            'due_date' => $this->stamp($row->due_date ?? null),
            'created_at' => $this->stamp($row->created_at ?? null),
        ], ['_key']))->values()->all();

        return $section;
    }

    /**
     * The file_tracker request/route lines for this file, newest first.
     *
     * Matched on file number rather than on file_indexing_id: file_tracker has no
     * such column — it is keyed by the number, because a tracking line can be
     * opened for a file before it is ever indexed.
     *
     * @return array<int,array<string,mixed>>
     */
    private function trackerLines(FileIndexing $r): array
    {
        if (!$this->hasTable('file_tracker')) {
            return [];
        }

        $numbers = $this->collectNumbers($r);
        if (empty($numbers)) {
            return [];
        }

        $rows = DB::connection('sqlsrv')->table('file_tracker')
            ->where(function ($builder) use ($numbers) {
                foreach ($numbers as $number) {
                    $builder->orWhereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$number]);
                }
            })
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        return $rows->map(fn ($row) => $this->compact([
            '_key' => 'file_tracker:' . ($row->id ?? '0'),
            'tracking_id' => $row->tracking_id ?? null,
            'status' => $row->status ?? null,
            'registry_code' => $row->registry_code ?? null,
            'department' => $row->department ?? null,
            'destination' => $row->destination ?? null,
            'current_office' => $row->current_office_name ?? null,
            'origin_office' => $row->origin_office_name ?? null,
            'purpose' => $row->request_purpose_name ?? null,
            'file_request_type' => $row->file_request_type ?? null,
            'in_digital_archive' => $row->in_digital_archive ?? null,
            'date_created' => $this->stamp($row->date_created ?? ($row->created_at ?? null)),
            'deadline' => $this->stamp($row->deadline ?? null),
            'created_by' => $row->created_by ?? null,
            'created_by_name' => $row->created_by_name
                ?? $this->resolveUserName($row->created_by ?? null),
        ], ['_key']))->values()->all();
    }

    /**
     * The scan folder is reported from what the caller already created — this
     * service NEVER creates one. A snapshot is a read of the world, and a read
     * that has side effects on the filesystem is not a snapshot.
     *
     * The counterpart folios (Cadastral Registry, Physical Planning Registry —
     * EdmsScanUploadFolderService::FOLIO_REGISTRIES) are emitted as a keyed
     * collection, one row per registry. They used to be flattened into a list of
     * strings, which the card could only render as "2 item(s)" — the two
     * registries the operator actually wants named were the part it dropped.
     */
    private function edmsSection(FileIndexing $r, array $context): array
    {
        $section = $this->compact([
            'edms_file_type' => $r->edms_file_type,
        ]);

        $folder = $context['scan_folder'] ?? null;
        if (!is_array($folder)) {
            return $section;
        }

        // The home folder is the first row of the `folders` collection below, so it
        // is deliberately NOT also emitted as flat home_* fields — the card would
        // show the same path twice, once as a field and once as a row.
        $folders = [[
            '_key' => 'edms:home',
            'registry' => $folder['registry'] ?? 'Home registry',
            'role' => 'Home folder',
            'path' => $folder['path'] ?? null,
            'state' => $this->folderState($folder),
        ]];

        // Every folio registry is listed even when it produced no folder, so
        // "Cadastral Registry — same as home" and "Cadastral Registry missing"
        // are distinguishable. A blank row would read as the latter either way.
        foreach (EdmsScanUploadFolderService::FOLIO_REGISTRIES as $registry) {
            $folio = $folder['folios'][$registry] ?? null;

            $folders[] = $this->compact([
                '_key' => 'edms:' . $registry,
                'registry' => $registry,
                'role' => 'Counterpart folio',
                'path' => is_array($folio) ? ($folio['path'] ?? null) : null,
                'state' => is_array($folio) ? $this->folderState($folio) : 'not created',
            ], ['_key', 'registry', 'role', 'state']);
        }

        $section['folders'] = $folders;

        return $section;
    }

    /** created | existed | same as home | the service's own reason string. */
    private function folderState(array $folder): string
    {
        if (!empty($folder['created'])) {
            return 'created';
        }

        if (!empty($folder['existed'])) {
            return 'existed';
        }

        return str_replace('_', ' ', (string) ($folder['reason'] ?? 'unknown'));
    }

    /** Party / holder names carried on the indexing row itself. */
    private function partiesSection(FileIndexing $r): array
    {
        return $this->compact([
            'original_holder' => $r->original_holder,
            'current_holder' => $r->current_holder,
            'party_3' => $r->party_3,
            'party_4' => $r->party_4,
            'party_5' => $r->party_5,
            'gender' => $r->gender,
            'dob' => $this->stamp($r->dob),
            'nin' => $r->nin,
            'tin' => $r->tin,
            'rc_no' => $r->rc_no,
            'phone' => $r->phone,
            'residence_address' => $r->residence_address,
        ]);
    }

    private function entitySection(FileIndexing $r): ?array
    {
        return $this->stagingRow('entities_staging', $r->file_number, [
            'id', 'entity_name', 'entity_type', 'file_number', 'created_at', 'updated_at',
        ]);
    }

    private function customerSection(FileIndexing $r): ?array
    {
        return $this->stagingRow('customers_staging', $r->file_number, [
            'id', 'customer_name', 'customer_type', 'customer_account_no', 'customer_code',
            'customer_status', 'property_address', 'file_number', 'created_at', 'updated_at',
        ]);
    }

    /** One row from a *_staging table keyed by file_number, whitelisted to $columns. */
    private function stagingRow(string $table, ?string $fileNumber, array $columns): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' || !$this->hasTable($table) || !$this->hasColumn($table, 'file_number')) {
            return null;
        }

        $row = DB::connection('sqlsrv')->table($table)
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber])
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return null;
        }

        $out = [];
        foreach ($columns as $column) {
            if (!$this->hasColumn($table, $column)) {
                continue;
            }
            $value = $row->{$column} ?? null;
            if ($value !== null && $value !== '') {
                $out[$column] = in_array($column, ['created_at', 'updated_at'], true)
                    ? $this->stamp($value)
                    : $value;
            }
        }

        return $out ?: null;
    }

    /**
     * Every transaction on the file across the three staging tables.
     *
     * Keyed as "{table}:{id}" so the diff can report a newly captured transaction
     * as ONE added entry rather than as a wholesale array replacement — which is
     * the difference between a readable audit trail and an unreadable one.
     *
     * @param  array<int,string> $numbers
     * @return array<int,array<string,mixed>>
     */
    private function transactionsSection(array $numbers): array
    {
        if (empty($numbers)) {
            return [];
        }

        $rows = [];

        foreach (self::TXN_TABLES as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                self::TXN_FILE_COLUMNS,
                fn ($column) => $this->hasColumn($table, $column)
            ));

            if (empty($columns)) {
                continue;
            }

            $query = DB::connection('sqlsrv')->table($table)
                ->where(function ($builder) use ($columns, $numbers) {
                    foreach ($columns as $column) {
                        foreach ($numbers as $number) {
                            $builder->orWhereRaw(
                                'UPPER(LTRIM(RTRIM(' . $column . '))) = UPPER(?)',
                                [$number]
                            );
                        }
                    }
                })
                ->orderByDesc('id')
                ->limit(self::MAX_ROWS);

            foreach ($query->get() as $row) {
                $rows[] = $this->normaliseTransaction($table, $row);
            }
        }

        // Newest dealing first — the same order the operator reads the file in.
        usort($rows, static function ($a, $b) {
            return strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
        });

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /** The three transaction tables share these columns; absent ones read as null. */
    private function normaliseTransaction(string $table, $row): array
    {
        return $this->compact([
            '_key' => $table . ':' . ($row->id ?? '0'),
            'source_table' => $table,
            'id' => $row->id ?? null,
            'transaction_type' => $row->transaction_type ?? ($row->instrument_type ?? null),
            'transaction_date' => $this->stamp($row->transaction_date ?? null),
            'reg_no' => $row->regNo ?? null,
            'reg_date' => $this->stamp($row->reg_date ?? null),
            'serial_no' => $row->serialNo ?? null,
            'page_no' => $row->pageNo ?? null,
            'volume_no' => $row->volumeNo ?? null,
            'land_use' => $row->land_use ?? null,
            'period' => $row->period ?? null,
            'period_unit' => $row->period_unit ?? null,
            'party_1' => $row->party_1 ?? ($row->Grantor ?? null),
            'party_2' => $row->party_2 ?? ($row->Grantee ?? null),
            'party_3' => $row->party_3 ?? null,
            'location' => $row->location ?? null,
            'plot_no' => $row->plot_no ?? null,
            'status' => $row->status ?? null,
            'created_at' => $this->stamp($row->created_at ?? null),
            'created_by' => $row->created_by ?? null,
            'created_by_name' => $this->resolveUserName($row->created_by ?? null),
        ], ['_key']);
    }

    /**
     * Every file linked to this one, from all three places a link can live:
     * the indexing row's own JSON list, file_indexing_links (block / related),
     * and the related_file_number register that Legal Search actually reads.
     *
     * @param  array<int,string> $numbers
     * @return array<int,array<string,mixed>>
     */
    private function linksSection(FileIndexing $r, array $numbers): array
    {
        $links = [];

        // 1. The row's own JSON list.
        foreach ($this->decodeRelatedFileno($r->related_fileno) as $number) {
            $links[] = [
                '_key' => 'related_fileno:' . strtoupper($number),
                'source' => 'file_indexings.related_fileno',
                'file_number' => $number,
            ];
        }

        // 2. Block / related link rows.
        if ($this->hasTable('file_indexing_links')) {
            $rows = DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_indexing_id', (int) $r->id)
                ->orderBy('id')
                ->limit(self::MAX_ROWS)
                ->get();

            foreach ($rows as $row) {
                $links[] = $this->compact([
                    '_key' => 'file_indexing_links:' . ($row->id ?? '0'),
                    'source' => 'file_indexing_links',
                    'file_number' => $row->file_number ?? null,
                    'file_title' => $row->file_title ?? null,
                    // mfile = 1 is a main file of a block, 0 a related file.
                    'role' => isset($row->mfile) ? ((int) $row->mfile === 1 ? 'Main file' : 'Related file') : null,
                    'indexing_type' => $row->indexing_type ?? null,
                    'created_at' => $this->stamp($row->created_at ?? null),
                    'created_by' => $row->created_by ?? null,
                    'created_by_name' => $this->resolveUserName($row->created_by ?? null),
                ], ['_key']);
            }
        }

        // 3. The register Legal Search and the Related File Numbers page read.
        if ($this->hasTable(RelatedFileNumberRegistrar::TABLE)) {
            $rows = DB::connection('sqlsrv')->table(RelatedFileNumberRegistrar::TABLE)
                ->where('source_table', 'file_indexings')
                ->where('source_id', (int) $r->id)
                ->orderBy('id')
                ->limit(self::MAX_ROWS)
                ->get();

            foreach ($rows as $row) {
                $links[] = $this->compact([
                    '_key' => 'related_file_number:' . ($row->id ?? '0'),
                    'source' => RelatedFileNumberRegistrar::TABLE,
                    'file_number' => $row->related_fileno ?? null,
                    'relationship' => $row->transaction_type ?? null,
                    'comment' => $row->comment ?? null,
                    'created_at' => $this->stamp($row->created_at ?? null),
                ], ['_key']);
            }
        }

        // 4. DCIV master links, in both directions.
        if ($this->hasTable('master_dciv_links') && !empty($numbers)) {
            $rows = DB::connection('sqlsrv')->table('master_dciv_links')
                ->where(function ($builder) use ($numbers) {
                    foreach ($numbers as $number) {
                        $builder->orWhereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = UPPER(?)', [$number])
                            ->orWhereRaw('UPPER(LTRIM(RTRIM(related_file_number))) = UPPER(?)', [$number]);
                    }
                })
                ->orderBy('id')
                ->limit(self::MAX_ROWS)
                ->get();

            foreach ($rows as $row) {
                $links[] = $this->compact([
                    '_key' => 'master_dciv_links:' . ($row->id ?? '0'),
                    'source' => 'master_dciv_links',
                    'file_number' => $row->related_file_number ?? ($row->dciv_file_number ?? null),
                    'relationship' => 'DCIV',
                    'dciv_file_number' => $row->dciv_file_number ?? null,
                    'created_at' => $this->stamp($row->created_at ?? null),
                ], ['_key']);
            }
        }

        return array_slice($links, 0, self::MAX_ROWS);
    }

    private function billsSection(FileIndexing $r): array
    {
        $section = $this->compact([
            'bill_total_amount' => $r->bill_total_amount,
            'bill_from_year' => $r->bill_from_year,
            'bill_to_year' => $r->bill_to_year,
            'bill_receipt_no' => $r->bill_receipt_no,
            'bill_receipt_date' => $this->stamp($r->bill_receipt_date),
            'ground_rent_amount' => $r->ground_rent_amount,
            'ground_rent_from_year' => $r->ground_rent_from_year,
            'ground_rent_to_year' => $r->ground_rent_to_year,
            'ground_rent_receipt_no' => $r->ground_rent_receipt_no,
            'ground_rent_receipt_date' => $this->stamp($r->ground_rent_receipt_date),
        ]);

        if ($this->hasTable('file_indexing_bills')) {
            $count = DB::connection('sqlsrv')->table('file_indexing_bills')
                ->where('file_indexing_id', (int) $r->id)
                ->count();

            if ($count > 0) {
                $section['bill_rows'] = $count;
            }
        }

        return $section;
    }

    // ------------------------------------------------------------------ diff

    /**
     * Field-by-field difference between two payloads.
     *
     * Scalars diff by path. Collections of rows (transactions, links) diff by
     * their `_key`, so adding one transaction to a file that has forty reports
     * one `added` entry instead of forty-one changes.
     *
     * @return array<int,array{path:string,label:string,from:mixed,to:mixed,kind:string}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $section) {
            $old = $before[$section] ?? null;
            $new = $after[$section] ?? null;

            if ($this->isKeyedCollection($old) || $this->isKeyedCollection($new)) {
                $changes = array_merge(
                    $changes,
                    $this->diffCollection((string) $section, is_array($old) ? $old : [], is_array($new) ? $new : [])
                );
                continue;
            }

            if (is_array($old) || is_array($new)) {
                $changes = array_merge(
                    $changes,
                    $this->diffAssoc((string) $section, is_array($old) ? $old : [], is_array($new) ? $new : [])
                );
                continue;
            }

            if ($this->differs($old, $new)) {
                $changes[] = $this->change((string) $section, (string) $section, $old, $new);
            }
        }

        return $changes;
    }

    /** @return array<int,array<string,mixed>> */
    private function diffAssoc(string $prefix, array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if (is_array($old) || is_array($new)) {
                // Nested lists inside a section (e.g. tracking.movements, edms.folios)
                // are compared whole: they are short and rarely the point of an edit.
                if ($this->differs(json_encode($old), json_encode($new))) {
                    $changes[] = $this->change(
                        $prefix . '.' . $key,
                        $this->humanise((string) $key),
                        is_array($old) ? count($old) . ' item(s)' : $old,
                        is_array($new) ? count($new) . ' item(s)' : $new
                    );
                }
                continue;
            }

            if ($this->differs($old, $new)) {
                $changes[] = $this->change($prefix . '.' . $key, $this->humanise((string) $key), $old, $new);
            }
        }

        return $changes;
    }

    /** @return array<int,array<string,mixed>> */
    private function diffCollection(string $prefix, array $before, array $after): array
    {
        $oldByKey = $this->keyRows($before);
        $newByKey = $this->keyRows($after);
        $changes = [];

        foreach ($newByKey as $key => $row) {
            if (!array_key_exists($key, $oldByKey)) {
                $changes[] = $this->change($prefix . '.' . $key, $this->rowLabel($prefix, $row), null, $this->rowSummary($row), 'added');
                continue;
            }

            if ($this->differs(json_encode($oldByKey[$key]), json_encode($row))) {
                $changes[] = $this->change(
                    $prefix . '.' . $key,
                    $this->rowLabel($prefix, $row),
                    $this->rowSummary($oldByKey[$key]),
                    $this->rowSummary($row)
                );
            }
        }

        foreach ($oldByKey as $key => $row) {
            if (!array_key_exists($key, $newByKey)) {
                $changes[] = $this->change($prefix . '.' . $key, $this->rowLabel($prefix, $row), $this->rowSummary($row), null, 'removed');
            }
        }

        return $changes;
    }

    private function change(string $path, string $label, $from, $to, string $kind = 'changed'): array
    {
        return [
            'path' => $path,
            'label' => $label,
            'from' => $this->clip(is_scalar($from) || $from === null ? $from : json_encode($from), 500),
            'to' => $this->clip(is_scalar($to) || $to === null ? $to : json_encode($to), 500),
            'kind' => $kind,
        ];
    }

    /** A list of rows each carrying a `_key`, i.e. something to diff by identity. */
    private function isKeyedCollection($value): bool
    {
        // Not array_is_list(): composer allows PHP ^8.0.2 and that landed in 8.1.
        return is_array($value)
            && $value !== []
            && array_keys($value) === range(0, count($value) - 1)
            && is_array($value[0])
            && array_key_exists('_key', $value[0]);
    }

    /** @return array<string,array<string,mixed>> */
    private function keyRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[(string) ($row['_key'] ?? $index)] = $row;
        }

        return $out;
    }

    private function rowLabel(string $prefix, array $row): string
    {
        if ($prefix === 'transactions') {
            return trim((string) ($row['transaction_type'] ?? 'Transaction'));
        }

        if ($prefix === 'links') {
            return trim((string) ($row['file_number'] ?? 'Linked file'));
        }

        return $this->humanise($prefix);
    }

    private function rowSummary(array $row): string
    {
        $parts = array_filter([
            $row['transaction_type'] ?? null,
            $row['transaction_date'] ?? null,
            $row['file_number'] ?? null,
            $row['relationship'] ?? ($row['role'] ?? null),
            $row['party_1'] ?? null,
            $row['party_2'] ?? null,
        ], static fn ($value) => $value !== null && trim((string) $value) !== '');

        return implode(' · ', array_map('strval', $parts));
    }

    /**
     * Null, empty string and "0000-00-00" style blanks all mean "not set" in this
     * schema, so a save that rewrites null as '' is not a change worth reporting.
     */
    private function differs($old, $new): bool
    {
        return $this->blank($old) !== $this->blank($new);
    }

    private function blank($value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return '1';
        }

        return trim((string) $value);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Every number this file might be filed under. Used to find its transactions
     * and DCIV links, which are stored against whichever number was current when
     * the row was written.
     *
     * @return array<int,string>
     */
    private function collectNumbers(FileIndexing $r): array
    {
        $numbers = [];

        foreach ([
            $r->file_number,
            $r->temp_file_no,
            $r->mls_file_no,
            $r->kangis_file_no,
            $r->new_kangis_file_no,
            $r->kangis_fileno_resolved,
            $r->old_fileno,
            $r->st_fillno,
        ] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $numbers[strtoupper($value)] = $value;

            // "X(T)" and "X" are the same physical file stored in different
            // columns; looking up only the literal value misses half the rows.
            $bare = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $value));
            if ($bare !== '' && $bare !== $value) {
                $numbers[strtoupper($bare)] = $bare;
            }
        }

        return array_values($numbers);
    }

    /** @return array<int,string> */
    private function decodeRelatedFileno($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $decoded = json_decode((string) $value, true);
            // Not JSON: the column has also held plain delimited text. Never split
            // on whitespace — KANGIS numbers legitimately contain spaces ("KNML 1").
            $items = is_array($decoded)
                ? $decoded
                : preg_split('/[,;\n]+/', (string) $value);
        }

        $out = [];
        foreach ((array) $items as $item) {
            $item = is_array($item)
                ? trim((string) ($item['file_no'] ?? $item['file_number'] ?? ''))
                : trim((string) $item);

            if ($item !== '') {
                $out[strtoupper($item)] = $item;
            }
        }

        return array_values($out);
    }

    /**
     * Drop empty values so the payload — and therefore the diff — is about what
     * the file actually has, not about a hundred null columns.
     *
     * @param array<int,string> $always keys kept even when empty
     */
    private function compact(array $values, array $always = []): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $always, true) || ($value !== null && $value !== '' && $value !== [])) {
                $out[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $out;
    }

    private function stamp($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function clip($value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private function humanise(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function displayNameFor($user): ?string
    {
        if (!$user) {
            return null;
        }

        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $full !== '' ? $full : ($user->username ?: ($user->email ?: ('User #' . $user->id)));
    }

    /**
     * The resolved display name, unless it is just the raw value again.
     *
     * created_by often already HOLDS the display name, and emitting both
     * "Created by: Klaes Admin" and "Created by name: Klaes Admin" makes the card
     * look like it is reporting two different facts.
     */
    private function resolvedNameUnless($value): ?string
    {
        $name = $this->resolveUserName($value);

        return ($name !== null && strcasecmp($name, trim((string) $value)) === 0) ? null : $name;
    }

    /**
     * created_by is NOT reliably a user id — as FileIndexingActivityLogController
     * documents, it may hold a name, username or email depending on when the row
     * was written. Resolve through all four keys, and return null rather than
     * inventing a name when nothing matches.
     */
    private function resolveUserName($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($this->userIndex === null) {
            $this->userIndex = [];

            try {
                $users = DB::connection('sqlsrv')->table('users')
                    ->select('id', 'first_name', 'last_name', 'username', 'email')
                    ->get();

                foreach ($users as $user) {
                    $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    $display = $full !== '' ? $full : ($user->username ?: ($user->email ?: ('User #' . $user->id)));

                    foreach ([(string) $user->id, $full, $user->username, $user->email] as $key) {
                        $key = strtoupper(trim((string) $key));
                        if ($key !== '') {
                            $this->userIndex[$key] = $display;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('FileSnapshotService - user index unavailable: ' . $e->getMessage());
            }
        }

        return $this->userIndex[strtoupper($value)] ?? null;
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaCache['t:' . $table]
            ??= (function () use ($table) {
                try {
                    return Schema::connection('sqlsrv')->hasTable($table);
                } catch (\Throwable $e) {
                    return false;
                }
            })();
    }

    /**
     * One column listing per table, not one INFORMATION_SCHEMA round trip per
     * column. A snapshot probes ~30 columns across ~6 tables, and the per-column
     * form of this made the cold path three times slower than the reads it guards.
     */
    private function hasColumn(string $table, string $column): bool
    {
        $key = 'cols:' . $table;

        if (!array_key_exists($key, $this->schemaCache)) {
            try {
                $this->schemaCache[$key] = array_flip(array_map(
                    'strtolower',
                    Schema::connection('sqlsrv')->getColumnListing($table)
                ));
            } catch (\Throwable $e) {
                $this->schemaCache[$key] = [];
            }
        }

        return isset($this->schemaCache[$key][strtolower($column)]);
    }
}
