<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OP → File Property ID Matching: move Occupancy Permits onto the file's parcel id.
 *
 * THE PROBLEM
 * An Occupancy Permit captured before its file was confirmed gets a prop_id of its
 * own — one parcel id per permit, because at capture time there was nothing to tie it
 * to. Later the file is confirmed and carries the real, registered parcel id. The
 * permits are then scattered across as many prop_ids as there are permits, and every
 * reader that groups by parcel — Legal Search, the property timeline, the parcel
 * summary — sees them as separate properties.
 *
 * THE RULE
 * The FILE is the control record. Its prop_id is the target and never moves; the OPs
 * move to it. That direction is not negotiable: the file's id is the one registered in
 * PropID_Master and referenced by everything else on the file, while a permit's
 * capture-time id is referenced by nothing but the permit.
 *
 * COMPANIONS
 * An OP and the Transfer of Title written against it are one parcel, and this schema
 * pairs them in two disagreeing ways — an explicit source_op_id pointer, and a shared
 * prop_id (see OpResettlementApplicationController::applyUnlinkedOpFilter, where the
 * same two representations are checked for the same reason). Moving the OP alone would
 * strand its ToT on the abandoned id, which is worse than the state we started in: the
 * grant and the transfer would then read as two unrelated properties. So companions
 * move with the OP by default, and both pairings are followed.
 *
 * EVERY WRITE IS RECORDED in op_propid_matches — the old prop_id is overwritten in
 * place and nothing else remembers it. That table is also what Undo reads.
 *
 * @see \App\Http\Controllers\OpPropIdMatchController  the page and its endpoints
 */
class OpPropIdMatchService
{
    private const CONNECTION = 'sqlsrv';

    /** The two tables an Occupancy Permit can live in. */
    public const OP_TABLES = ['pra', 'instrument_capture'];

    /** Occupancy Permits in `pra` carry either spelling of the type. */
    public const PRA_OP_TYPE_LIKE = '%Occupancy Permit%';

    /** `instrument_capture` uses exactly one. */
    public const IC_OP_TYPE = 'Occupancy Permit (OP)';

    /**
     * What every batch from this page is recorded as.
     *
     * There was briefly a second kind — Change of Ownership — chosen in a modal before
     * any permit could be ticked. It is gone: this page only ever consolidates permits
     * onto the parcel id of the file they already belong to, and asserts nothing about
     * who owns anything. Asking a question with one true answer only invited the wrong
     * one to be picked.
     *
     * The value is still WRITTEN to op_propid_matches.match_mode, so the audit trail
     * says what each batch was. It is set here, not sent by the browser.
     *
     * NOTE: the chk_opm_match_mode CHECK constraint on that column still permits
     * 'change_of_ownership'. Deliberately left alone — the constraint is permissive, no
     * row ever carried that value, and narrowing it would be a migration for nothing.
     */
    public const MODE_NO_CHANGE_OF_OWNERSHIP = 'no_change_of_ownership';

    public const MATCH_MODE_LABEL = 'Match OP - No Change of Ownership';

    /**
     * Move the given OPs onto the target prop_id.
     *
     * @param  array<int,array{source_table:string,op_id:int}>  $selections
     * @return array{ok:bool,message:string,batch_ref:?string,moved:int,companions:int,skipped:array,errors:array}
     */
    public function batchMatch(
        int $targetPropId,
        ?string $targetFileNumber,
        array $selections,
        bool $moveCompanions = true
    ): array {
        $matchMode = self::MODE_NO_CHANGE_OF_OWNERSHIP;

        if ($targetPropId <= 0) {
            return $this->refuse('No target Property ID — select a confirmed file first.');
        }

        $selections = $this->normalizeSelections($selections);

        if (empty($selections)) {
            return $this->refuse('No OP records were selected.');
        }

        $conn = DB::connection(self::CONNECTION);
        $logged = Schema::connection(self::CONNECTION)->hasTable('op_propid_matches');
        $batchRef = 'OPM-' . now()->format('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $userId = Auth::id();
        $now = now();

        $moved = 0;
        $companionCount = 0;
        $skipped = [];
        $errors = [];
        $ledger = [];

        try {
            $conn->transaction(function () use (
                $conn, $selections, $targetPropId, $targetFileNumber, $moveCompanions,
                $batchRef, $userId, $now, $matchMode,
                &$moved, &$companionCount, &$skipped, &$errors, &$ledger
            ) {
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

                    $oldPropId = $this->readPropId($op);

                    if ($oldPropId === (string) $targetPropId) {
                        $skipped[] = [
                            'source_table' => $table,
                            'op_id' => $opId,
                            'reason' => 'Already on this Property ID.',
                        ];
                        continue;
                    }

                    $this->writePropId($conn, $table, $opId, $targetPropId, $now);
                    $moved++;

                    $ledger[] = $this->ledgerRow(
                        $batchRef, $targetFileNumber, $targetPropId, $table, $opId, 'op',
                        $this->readSerial($op), $this->readFileNumber($table, $op),
                        $oldPropId, $userId, $now, $matchMode
                    );

                    if (! $moveCompanions) {
                        continue;
                    }

                    foreach ($this->findCompanions($conn, $table, $opId, $oldPropId) as $companion) {
                        $companionOld = $this->readPropId($companion);

                        if ($companionOld === (string) $targetPropId) {
                            continue;
                        }

                        $this->writePropId($conn, 'pra', (int) $companion->id, $targetPropId, $now);
                        $companionCount++;

                        $ledger[] = $this->ledgerRow(
                            $batchRef, $targetFileNumber, $targetPropId, 'pra', (int) $companion->id, 'companion',
                            $this->readSerial($companion), $this->readFileNumber('pra', $companion),
                            $companionOld, $userId, $now, $matchMode
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP prop_id batch match failed', [
                'user' => $userId,
                'target_prop_id' => $targetPropId,
                'target_file_number' => $targetFileNumber,
                'selections' => count($selections),
                'error' => $e->getMessage(),
            ]);

            return $this->refuse('The match could not be completed and nothing was changed: ' . $e->getMessage());
        }

        // Outside the transaction: the ledger is the record of what happened, and a
        // failure to write it must not roll back work that has already landed. It is
        // reported instead, because a batch with no ledger row cannot be undone.
        $ledgerWritten = true;
        if ($logged && ! empty($ledger)) {
            try {
                foreach (array_chunk($ledger, 200) as $chunk) {
                    $conn->table('op_propid_matches')->insert($chunk);
                }
            } catch (\Throwable $e) {
                $ledgerWritten = false;
                Log::channel('op_batch')->error('OP prop_id match ledger write failed', [
                    'batch_ref' => $batchRef,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('op_batch')->info('OP prop_id batch match', [
            'user' => $userId,
            'batch_ref' => $batchRef,
            'target_prop_id' => $targetPropId,
            'target_file_number' => $targetFileNumber,
            'moved' => $moved,
            'companions' => $companionCount,
            'skipped' => count($skipped),
            'errors' => $errors,
            'ledger_written' => $ledgerWritten,
        ]);

        return [
            'ok' => true,
            'message' => $this->summarize($moved, $companionCount, $skipped, $errors, $targetPropId, $ledgerWritten),
            'batch_ref' => $moved > 0 && $ledgerWritten ? $batchRef : null,
            'moved' => $moved,
            'companions' => $companionCount,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Put a batch back the way it was.
     *
     * A record is restored only if it still carries the prop_id this batch gave it.
     * Anything moved again since is left alone and reported — the officer who moved it
     * last had a reason, and silently overwriting that with an older value would make
     * Undo the more destructive of the two operations.
     */
    public function undo(string $batchRef): array
    {
        if (! Schema::connection(self::CONNECTION)->hasTable('op_propid_matches')) {
            return ['ok' => false, 'message' => 'There is no match history on this database to undo.'];
        }

        $conn = DB::connection(self::CONNECTION);

        $rows = $conn->table('op_propid_matches')
            ->where('batch_ref', $batchRef)
            ->whereNull('reverted_at')
            ->get();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'message' => 'That batch has already been undone, or no longer exists.'];
        }

        $restored = 0;
        $left = [];
        $now = now();
        $userId = Auth::id();

        try {
            $conn->transaction(function () use ($conn, $rows, $now, $userId, &$restored, &$left) {
                foreach ($rows as $row) {
                    if (! in_array($row->source_table, self::OP_TABLES, true)) {
                        continue;
                    }

                    $current = $conn->table($row->source_table)->where('id', $row->record_id)->first();

                    if (! $current) {
                        $left[] = "{$row->source_table} #{$row->record_id} no longer exists.";
                        continue;
                    }

                    if ($this->readPropId($current) !== (string) $row->new_prop_id) {
                        $left[] = "{$row->source_table} #{$row->record_id} has been moved again since — left as it is.";
                        continue;
                    }

                    $this->restorePropId($conn, $row->source_table, (int) $row->record_id, $row->previous_prop_id, $now);
                    $restored++;

                    $conn->table('op_propid_matches')->where('id', $row->id)->update([
                        'reverted_at' => $now,
                        'reverted_by' => $userId,
                        'updated_at' => $now,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP prop_id match undo failed', [
                'batch_ref' => $batchRef,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'The undo could not be completed and nothing was changed: ' . $e->getMessage()];
        }

        Log::channel('op_batch')->warning('OP prop_id batch match undone', [
            'user' => $userId,
            'batch_ref' => $batchRef,
            'restored' => $restored,
            'left' => $left,
        ]);

        $message = $restored . ' record(s) put back.';
        if (! empty($left)) {
            $message .= ' ' . count($left) . ' left alone: ' . implode(' ', $left);
        }

        return ['ok' => true, 'message' => $message, 'restored' => $restored, 'left' => $left];
    }

    /**
     * The Transfer of Title rows that belong with this OP.
     *
     * Both pairings, because the schema disagrees with itself about which is the real
     * one: an explicit source_op_id pointer, and a shared prop_id. `pra` only — a ToT
     * is always written there, never into instrument_capture.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function findCompanions($conn, string $opTable, int $opId, string $oldPropId)
    {
        $query = $conn->table('pra')
            ->where(function ($q) use ($opTable, $opId, $oldPropId) {
                $q->where(function ($pointer) use ($opTable, $opId) {
                    $pointer->where('source_op_table', $opTable)->where('source_op_id', $opId);
                });

                // Only when there IS an old id. Matching on '' or NULL would drag in every
                // unrelated row that has never been given one.
                if ($oldPropId !== '') {
                    $q->orWhere(function ($shared) use ($oldPropId) {
                        $shared->where('prop_id', $oldPropId)
                            ->where('instrument_type', 'LIKE', '%Transfer of Title%');
                    });
                }
            })
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));

        // The OP itself is never its own companion — it lives in `pra` too and would
        // otherwise be matched by the shared-prop_id arm and double-logged.
        if ($opTable === 'pra') {
            $query->where('id', '<>', $opId);
        }

        return $query->get();
    }

    /** Is this row really an Occupancy Permit? Guards against a stale id from the browser. */
    private function isOccupancyPermit(string $table, object $row): bool
    {
        $type = (string) ($row->instrument_type ?? '');

        if ((int) ($row->is_deleted ?? 0) === 1) {
            return false;
        }

        return $table === 'pra'
            ? stripos($type, 'Occupancy Permit') !== false
            : $type === self::IC_OP_TYPE;
    }

    /** prop_id as a comparable string. nvarchar in `pra`, bigint in instrument_capture. */
    private function readPropId(object $row): string
    {
        return trim((string) ($row->prop_id ?? ''));
    }

    private function readSerial(object $row): ?string
    {
        $serial = trim((string) ($row->op_serial_number ?? ''));

        return $serial !== '' ? mb_substr($serial, 0, 100) : null;
    }

    private function readFileNumber(string $table, object $row): ?string
    {
        $candidates = $table === 'pra'
            ? [$row->mlsFNo ?? null, $row->fileno ?? null, $row->temp_fileno ?? null]
            : [$row->mlsFNo ?? null, $row->temp_fileno ?? null];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return mb_substr($value, 0, 100);
            }
        }

        return null;
    }

    /** instrument_capture.prop_id is bigint; `pra`'s is nvarchar. Write each its own way. */
    private function writePropId($conn, string $table, int $id, int $propId, $now): void
    {
        $conn->table($table)->where('id', $id)->update([
            'prop_id' => $table === 'instrument_capture' ? $propId : (string) $propId,
            'updated_at' => $now,
        ]);
    }

    /** Restore an old value verbatim — including a blank, which is what NULL was stored as. */
    private function restorePropId($conn, string $table, int $id, $previous, $now): void
    {
        $value = $previous === null || trim((string) $previous) === '' ? null : trim((string) $previous);

        if ($table === 'instrument_capture') {
            $value = ($value === null || ! ctype_digit($value)) ? null : (int) $value;
        }

        $conn->table($table)->where('id', $id)->update([
            'prop_id' => $value,
            'updated_at' => $now,
        ]);
    }

    private function ledgerRow(
        string $batchRef,
        ?string $targetFileNumber,
        int $targetPropId,
        string $table,
        int $recordId,
        string $kind,
        ?string $serial,
        ?string $fileNumber,
        string $oldPropId,
        $userId,
        $now,
        ?string $matchMode = null
    ): array {
        return [
            'batch_ref' => $batchRef,
            'target_file_number' => $targetFileNumber !== null ? mb_substr($targetFileNumber, 0, 100) : null,
            'target_prop_id' => $targetPropId,
            'source_table' => $table,
            'record_id' => $recordId,
            'record_kind' => $kind,
            'op_serial_number' => $serial,
            'record_file_number' => $fileNumber,
            'previous_prop_id' => $oldPropId !== '' ? $oldPropId : null,
            'new_prop_id' => (string) $targetPropId,
            'matched_by' => $userId,
            // The basis the officer declared for this batch, on every row of it.
            'match_mode' => $matchMode,
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

            if (! in_array($table, self::OP_TABLES, true) || $id <= 0) {
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

    private function summarize(int $moved, int $companions, array $skipped, array $errors, int $propId, bool $ledgerWritten): string
    {
        if ($moved === 0 && empty($errors)) {
            return 'Nothing to do — every selected OP is already on Property ID ' . $propId . '.';
        }

        $parts = [$moved . ' OP record(s) moved to Property ID ' . $propId . '.'];

        if ($companions > 0) {
            $parts[] = $companions . ' linked Transfer of Title row(s) moved with them.';
        }

        if (! empty($skipped)) {
            $parts[] = count($skipped) . ' already on it.';
        }

        if (! empty($errors)) {
            $parts[] = count($errors) . ' could not be moved: ' . implode(' ', $errors);
        }

        if (! $ledgerWritten) {
            $parts[] = 'The change is done, but it could not be written to the match history — this batch cannot be undone from the page.';
        }

        return implode(' ', $parts);
    }

    private function refuse(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'batch_ref' => null,
            'moved' => 0,
            'companions' => 0,
            'skipped' => [],
            'errors' => [],
        ];
    }
}
