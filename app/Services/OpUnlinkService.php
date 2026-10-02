<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OP Unlink: take Occupancy Permits off a file's parcel id and give them their own.
 *
 * The reverse of OpPropIdMatchService. A permit matched to the wrong file — or a
 * permit that turns out to belong to a parcel of its own — is detached here: it stops
 * sharing the file's prop_id and is given a fresh one, so the file's records stop
 * reading as though the permit belongs to them.
 *
 * TWO WAYS TO DETACH, and the difference matters:
 *
 *   INDIVIDUAL   every selected permit gets its OWN new prop_id. Use when the permits
 *                have nothing to do with each other and each is its own parcel.
 *
 *   GROUP        all selected permits get ONE shared new prop_id — a "Group OP". Use
 *                when the permits belong together (one parcel, several permits) but
 *                not to the file they were on. Meaningless for a single permit, and
 *                refused there rather than silently treated as individual.
 *
 * COMPANIONS ARE FOLLOWED BY POINTER ONLY.
 * Matching carries a permit's Transfer of Title along by either an explicit
 * source_op_id pointer OR a shared prop_id. Unlinking must NOT use the shared-prop_id
 * arm: on a file, that arm matches the FILE'S OWN transfers, and dragging those off
 * with the permit would strip the file of its history. Only the explicit pointer says
 * "this transfer was written against this permit", so only that is followed.
 *
 * EVERY WRITE IS RECORDED in op_propid_matches, the same ledger the match page uses,
 * so an unlink is undone exactly the way a match is.
 *
 * @see \App\Services\OpPropIdMatchService  the matching direction
 */
class OpUnlinkService
{
    private const CONNECTION = 'sqlsrv';

    public const MODE_INDIVIDUAL = 'individual';
    public const MODE_GROUP = 'group';

    /** What each mode is called on screen and in the ledger. */
    public const MODES = [
        self::MODE_INDIVIDUAL => 'Unmatch and Issue Individual IDs',
        self::MODE_GROUP => 'Unmatch / Create as Group OPs',
    ];

    /** Ledger record_kind values. Distinct from the match kinds so the two are separable. */
    private const KIND_INDIVIDUAL = 'unlink_individual';
    private const KIND_GROUP = 'unlink_group';
    private const KIND_COMPANION = 'unlink_companion';

    public function __construct(private OpPropIdMatchService $matcher)
    {
    }

    /**
     * Detach the given permits from whatever prop_id they currently hold.
     *
     * @param  array<int,array{source_table:string,op_id:int}>  $selections
     * @return array{ok:bool,message:string,batch_ref:?string,unlinked:int,companions:int,group_prop_id:?int,assignments:array,errors:array}
     */
    public function unlink(array $selections, string $mode, bool $moveCompanions = true): array
    {
        if (! array_key_exists($mode, self::MODES)) {
            return $this->refuse('Choose how the permits should be separated.');
        }

        $selections = $this->normalizeSelections($selections);

        if (empty($selections)) {
            return $this->refuse('No OP records were selected.');
        }

        // A group of one is not a group. Refusing is kinder than quietly doing something
        // else: the officer asked for a shared parcel and would not be told they got a
        // private one.
        if ($mode === self::MODE_GROUP && count($selections) < 2) {
            return $this->refuse('A Group OP needs at least two permits. Use "Issue Individual IDs" for a single permit.');
        }

        $conn = DB::connection(self::CONNECTION);
        $logged = Schema::connection(self::CONNECTION)->hasTable('op_propid_matches');
        $batchRef = 'OPU-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $userId = Auth::id();
        $now = now();

        $unlinked = 0;
        $companionCount = 0;
        $errors = [];
        $assignments = [];
        $ledger = [];
        $groupPropId = null;

        try {
            $conn->transaction(function () use (
                $conn, $selections, $mode, $moveCompanions, $batchRef, $userId, $now,
                &$unlinked, &$companionCount, &$errors, &$assignments, &$ledger, &$groupPropId
            ) {
                // One number for the whole group, taken once and under the same lock the
                // allocator uses, so a concurrent unlink cannot be handed the same one.
                if ($mode === self::MODE_GROUP) {
                    $groupPropId = $this->reservePropId($conn, 'GROUP-OP', $now);
                }

                foreach ($selections as $selection) {
                    $table = $selection['source_table'];
                    $opId = $selection['op_id'];

                    $op = $conn->table($table)->where('id', $opId)->first();

                    if (! $op) {
                        $errors[] = "{$table} #{$opId} no longer exists.";
                        continue;
                    }

                    if (! $this->isOccupancyPermit($table, $op)) {
                        $errors[] = "{$table} #{$opId} is not an Occupancy Permit.";
                        continue;
                    }

                    $oldPropId = trim((string) ($op->prop_id ?? ''));

                    $newPropId = $mode === self::MODE_GROUP
                        ? $groupPropId
                        : $this->reservePropId($conn, $this->firstFileNo($op) ?: 'UNLINK', $now);

                    if ($oldPropId === (string) $newPropId) {
                        $errors[] = "{$table} #{$opId} is already on that Property ID.";
                        continue;
                    }

                    $this->writePropId($conn, $table, $opId, $newPropId, $now);
                    $unlinked++;

                    $assignments[] = [
                        'source_table' => $table,
                        'op_id' => $opId,
                        'previous_prop_id' => $oldPropId !== '' ? $oldPropId : null,
                        'prop_id' => $newPropId,
                    ];

                    $ledger[] = $this->ledgerRow(
                        $batchRef, $newPropId, $table, $opId,
                        $mode === self::MODE_GROUP ? self::KIND_GROUP : self::KIND_INDIVIDUAL,
                        $op, $oldPropId, $userId, $now
                    );

                    if (! $moveCompanions) {
                        continue;
                    }

                    // Pointer only — see the class docblock. The shared-prop_id arm would
                    // take the file's own transfers with it.
                    foreach ($this->pointerCompanions($conn, $table, $opId) as $companion) {
                        $companionOld = trim((string) ($companion->prop_id ?? ''));

                        if ($companionOld === (string) $newPropId) {
                            continue;
                        }

                        $this->writePropId($conn, 'pra', (int) $companion->id, $newPropId, $now);
                        $companionCount++;

                        $ledger[] = $this->ledgerRow(
                            $batchRef, $newPropId, 'pra', (int) $companion->id, self::KIND_COMPANION,
                            $companion, $companionOld, $userId, $now
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP unlink failed', [
                'user' => $userId,
                'mode' => $mode,
                'selections' => count($selections),
                'error' => $e->getMessage(),
            ]);

            return $this->refuse('The unlink could not be completed and nothing was changed: ' . $e->getMessage());
        }

        // Outside the transaction, for the same reason the match ledger is: a failure to
        // record what happened must not roll back work that has already landed.
        $ledgerWritten = true;
        if ($logged && ! empty($ledger)) {
            try {
                foreach (array_chunk($ledger, 200) as $chunk) {
                    $conn->table('op_propid_matches')->insert($chunk);
                }
            } catch (\Throwable $e) {
                $ledgerWritten = false;
                Log::channel('op_batch')->error('OP unlink ledger write failed', [
                    'batch_ref' => $batchRef,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('op_batch')->warning('OP unlink', [
            'user' => $userId,
            'batch_ref' => $batchRef,
            'mode' => $mode,
            'unlinked' => $unlinked,
            'companions' => $companionCount,
            'group_prop_id' => $groupPropId,
            'errors' => $errors,
            'ledger_written' => $ledgerWritten,
        ]);

        return [
            'ok' => true,
            'message' => $this->summarize($mode, $unlinked, $companionCount, $groupPropId, $errors, $ledgerWritten),
            'batch_ref' => $unlinked > 0 && $ledgerWritten ? $batchRef : null,
            'unlinked' => $unlinked,
            'companions' => $companionCount,
            'group_prop_id' => $groupPropId,
            'assignments' => $assignments,
            'errors' => $errors,
        ];
    }

    /**
     * Take the next prop_id and reserve it in PropID_Master.
     *
     * Reserving is the point: an unlinked permit's new id must be a real registered
     * parcel, or the next allocation anywhere in the app hands the same number to
     * somebody else. The synthetic primary_file_number keeps the unique index happy
     * without claiming the permit's real file number, which still belongs to the group
     * it was detached from — the same approach PropIdMasterController::dropReallocate
     * takes for exactly this reason.
     */
    private function reservePropId($conn, string $hint, $now): int
    {
        $masterExists = Schema::connection(self::CONNECTION)->hasTable('PropID_Master');

        if (! $masterExists) {
            throw new \RuntimeException('PropID_Master is unavailable, so no new Property ID can be issued.');
        }

        $next = ((int) $conn->table('PropID_Master')
            ->lockForUpdate()
            ->where('prop_id', '<', 2147483647)
            ->max('prop_id')) + 1;

        $row = [
            'prop_id' => $next,
            'primary_file_number' => mb_substr(($hint !== '' ? $hint : 'UNLINK') . '-UNLINK-' . $next, 0, 90),
            'status' => 'unlinked',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection(self::CONNECTION)->hasColumn('PropID_Master', 'source_table')) {
            $row['source_table'] = 'pra';
        }

        $conn->table('PropID_Master')->insert($row);

        return $next;
    }

    /**
     * Transfers written against THIS permit, by explicit pointer.
     *
     * No shared-prop_id arm, deliberately: on a file that would match the file's own
     * transfers and carry them off with the permit.
     */
    private function pointerCompanions($conn, string $opTable, int $opId)
    {
        return $conn->table('pra')
            ->where('source_op_table', $opTable)
            ->where('source_op_id', $opId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->when($opTable === 'pra', fn ($q) => $q->where('id', '<>', $opId))
            ->get();
    }

    private function isOccupancyPermit(string $table, object $row): bool
    {
        $type = (string) ($row->instrument_type ?? '');

        if ((int) ($row->is_deleted ?? 0) === 1) {
            return false;
        }

        return $table === 'pra'
            ? stripos($type, 'Occupancy Permit') !== false
            : $type === OpPropIdMatchService::IC_OP_TYPE;
    }

    /** instrument_capture.prop_id is bigint; `pra`'s is nvarchar. */
    private function writePropId($conn, string $table, int $id, int $propId, $now): void
    {
        $conn->table($table)->where('id', $id)->update([
            'prop_id' => $table === 'instrument_capture' ? $propId : (string) $propId,
            'updated_at' => $now,
        ]);
    }

    private function firstFileNo(object $record): string
    {
        foreach (['mlsFNo', 'fileno', 'temp_fileno', 'kangisFileNo', 'NewKANGISFileno'] as $column) {
            $value = trim((string) ($record->{$column} ?? ''));
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '';
    }

    private function ledgerRow(string $batchRef, int $newPropId, string $table, int $recordId, string $kind, object $record, string $oldPropId, $userId, $now): array
    {
        $serial = trim((string) ($record->op_serial_number ?? ''));
        $fileNo = $this->firstFileNo($record);

        return [
            'batch_ref' => $batchRef,
            // An unlink has no destination file — that is what makes it an unlink. A
            // later reassignment is its own batch with its own row.
            'target_file_number' => null,
            'target_prop_id' => $newPropId,
            'source_table' => $table,
            'record_id' => $recordId,
            'record_kind' => $kind,
            'op_serial_number' => $serial !== '' ? mb_substr($serial, 0, 100) : null,
            'record_file_number' => $fileNo !== '' ? mb_substr($fileNo, 0, 100) : null,
            'previous_prop_id' => $oldPropId !== '' ? $oldPropId : null,
            'new_prop_id' => (string) $newPropId,
            'matched_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return array<int,array{source_table:string,op_id:int}> */
    private function normalizeSelections(array $selections): array
    {
        $seen = [];
        $out = [];

        foreach ($selections as $selection) {
            $table = is_array($selection) ? (string) ($selection['source_table'] ?? '') : '';
            $id = (int) (is_array($selection) ? ($selection['op_id'] ?? 0) : 0);

            if (! in_array($table, OpPropIdMatchService::OP_TABLES, true) || $id <= 0) {
                continue;
            }

            $key = $table . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = ['source_table' => $table, 'op_id' => $id];
        }

        return $out;
    }

    private function summarize(string $mode, int $unlinked, int $companions, ?int $groupPropId, array $errors, bool $ledgerWritten): string
    {
        if ($unlinked === 0) {
            return empty($errors)
                ? 'Nothing was unlinked.'
                : 'Nothing was unlinked. ' . implode(' ', $errors);
        }

        $parts = [];

        $parts[] = $mode === self::MODE_GROUP
            ? $unlinked . ' permit(s) unlinked and grouped on one new Property ID, ' . $groupPropId . '.'
            : $unlinked . ' permit(s) unlinked, each with its own new Property ID.';

        if ($companions > 0) {
            $parts[] = $companions . ' linked Transfer of Title row(s) went with them.';
        }

        if (! empty($errors)) {
            $parts[] = count($errors) . ' could not be unlinked: ' . implode(' ', $errors);
        }

        $parts[] = 'They are now available below for reassignment — which is optional.';

        if (! $ledgerWritten) {
            $parts[] = 'The change is done, but it could not be written to the history, so this batch cannot be undone from the page.';
        }

        return implode(' ', $parts);
    }

    private function refuse(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'batch_ref' => null,
            'unlinked' => 0,
            'companions' => 0,
            'group_prop_id' => null,
            'assignments' => [],
            'errors' => [],
        ];
    }
}
