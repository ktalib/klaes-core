<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves qualified `pra_tot_staging` rows into `pra` as Transfer of Title (OP) records.
 *
 * This is the CLI counterpart of the ToT Dashboard (/maintenance/tot,
 * ToTStagingController::generate). The dashboard pages 25 rows at a time and needs a
 * human to tick boxes; there are 8,000+ pending rows, so the same work is unusable
 * through the screen. Same qualification rule, same staging bookkeeping — just
 * batchable, filterable and previewable.
 *
 * What a staged row means (see docs/tot-dashboard-explained.md): two pra rows share a
 * prop_id but the holder names do not line up, so the chain is missing the dealing that
 * would explain how `op_name` became `ro_name`. The generated row IS that missing
 * dealing: Party 1 = op_name (the holder on record), Party 2 = ro_name (the staged
 * holder).
 *
 * Differences from the dashboard, all deliberate:
 *  - `instrument_type` is set to 'Transfer of Title (OP)' as well as `transaction_type`.
 *    The dashboard sets only the latter, so its 24 existing rows still carry the OP's
 *    'Right of Occupancy' instrument_type and are invisible to every consumer that
 *    filters on instrument_type (oss:retire-duplicate-op-tots, the Legal Search
 *    timeline). Use --repair-existing to fix those in place.
 *  - the OP is linked through source_op_table/source_op_id, so the new row is navigable
 *    back to its source the way OSS-created ToTs are.
 *  - registration particulars are zeroed (0/0/0, 0, 0, 0) — a reconstructed bridging
 *    transfer was never registered — matching the OSS Change-of-Name convention in
 *    ApplicationController::saveFfrChangeOfName.
 *  - a duplicate guard runs before every insert, so re-running is safe even if a
 *    previous run died before it could stamp the staging row.
 *
 * prop_id is inherited from the source OP on purpose: the transfer is a dealing on the
 * same parcel, and prop_id identifies a parcel, not a transaction. (The OSS flow mints a
 * fresh prop_id instead, but only because it also rewrites PropID_Master's primary
 * identifier — a side effect that has no business firing 8,000 times in a backfill.)
 *
 * Government/agency grantees are skipped exactly as the dashboard skips them: those rows
 * are the detector mistaking a Right of Occupancy's Governor-side grantor for a holder,
 * not a real transfer.
 *
 * Idempotent. Use --dry-run to preview.
 */
class GenerateStagedTots extends Command
{
    protected $signature = 'tot:generate-from-staging
        {--dry-run : Show what would change without writing}
        {--id= : Comma-separated pra_tot_staging ids to limit the run to}
        {--file= : Comma-separated file numbers (mlsFNo) to limit the run to}
        {--limit= : Process at most this many qualifying rows}
        {--user= : User id to stamp as processed_by / created_by}
        {--include-government : Also process rows whose staged holder is a government entity}
        {--repair-existing : Instead of generating, fix instrument_type + OP linkage on rows a previous dashboard run created}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Move qualified pra_tot_staging rows into pra as Transfer of Title (OP) records';

    /**
     * Kept in step with ToTStagingController::isGovernmentEntity so the CLI and the
     * dashboard never disagree about which rows qualify.
     *
     * Matched on word boundaries, not as substrings: 'STATE' inside 'THE ESTATE OF LATE
     * ALHAJI GUDAJI HALIRU CHIROMA' is not a government body, and the substring form
     * excluded every deceased-holder estate in the staging table from ever being
     * transferred. Same for 'REAL ESTATE' / 'ESTATES'.
     */
    private const GOVERNMENT_PATTERNS = [
        'GOVERNMENT', 'JUDICIARY', 'STATE', 'FEDERAL', 'MINISTRY',
        'DEPARTMENT', 'AGENCY', 'COMMISSION', 'AUTHORITY',
    ];

    private const TOT_TYPE = 'Transfer of Title (OP)';

    /** @var array<string, int> "<scope>|<party 1>|<party 2>" => pra id */
    private array $existingTots = [];

    /**
     * Columns that must never be carried over from the source OP row: the identity of
     * the OP itself, its registration particulars, its batch/merger membership, and the
     * SQL Server computed column that cannot be inserted at all.
     */
    private const DO_NOT_COPY = [
        'id', 'resolved_fileno', 'updated_at', 'updated_by', 'deleted_at',
        'instrument_capture_id', 'op_batch', 'merger_group_id', 'is_merger_op',
        'is_subdivided', 'op_count', 'source_pra_id', 'source_op_table', 'source_op_id',
        'is_caveated', 'caveated_comment', 'caveat_id',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $db = DB::connection('sqlsrv');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        if ($this->option('repair-existing')) {
            return $this->repairExisting($db, $dryRun);
        }

        $rows = $this->fetchStagedRows($db);

        if ($rows->isEmpty()) {
            $this->info('Nothing to do — no pending staging row matches the given filters.');
            return self::SUCCESS;
        }

        $this->info("Qualifying staged rows: {$rows->count()}");

        if (! $dryRun && ! $this->option('force') && ! $this->confirm("Generate up to {$rows->count()} Transfer of Title row(s) in pra?", false)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $now = now();

        $this->loadExistingTots($db);

        $generated = 0;
        $skipped = [];
        $skipTally = [];

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $staging) {
            $bar->advance();

            $reason = null;
            $category = null;
            $newId = $this->generateOne($db, $staging, $userId, $now, $dryRun, $reason, $category);

            if ($newId === null) {
                $skipped[] = "staging #{$staging->id} ({$staging->mlsFNo}): {$reason}";
                $skipTally[$category] = ($skipTally[$category] ?? 0) + 1;
                continue;
            }

            $generated++;
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'Would generate' : 'Generated';
        $this->info("{$verb} {$generated} Transfer of Title (OP) row(s) from {$rows->count()} staged row(s).");

        if ($skipped) {
            $this->newLine();
            $this->warn('Skipped ' . count($skipped) . ' row(s):');

            arsort($skipTally);
            $this->table(['Reason', 'Rows'], collect($skipTally)->map(
                fn ($count, $reason) => [$reason, $count]
            )->values()->all());

            $this->line('First ' . min(50, count($skipped)) . ':');
            foreach (array_slice($skipped, 0, 50) as $line) {
                $this->line('  ' . $line);
            }
            if (count($skipped) > 50) {
                $this->line('  … and ' . (count($skipped) - 50) . ' more.');
            }
        }

        if (! $dryRun && $generated > 0) {
            Log::info('tot:generate-from-staging completed', [
                'generated' => $generated,
                'skipped' => count($skipped),
                'user_id' => $userId,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Builds the candidate set. Mirrors ToTStagingController::index — pending, both names
     * present — with the government exclusion applied per row rather than through the
     * dashboard's whereNotIn subquery, so a skip can be reported with its reason.
     */
    private function fetchStagedRows($db)
    {
        $ids = $this->csvOption('id');
        $files = $this->csvOption('file');

        $query = $db->table('pra_tot_staging')
            ->where('status', 'pending')
            ->where('is_processed', 0)
            ->whereNotNull('op_name')->where('op_name', '<>', '')
            ->whereNotNull('ro_name')->where('ro_name', '<>', '')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when($files, fn ($q) => $q->whereIn('mlsFNo', $files))
            ->orderBy('id');

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Returns the new pra id, or null with $reason (per row) and $category (for the
     * summary tally) set when the row is skipped.
     */
    private function generateOne($db, object $staging, ?int $userId, $now, bool $dryRun, ?string &$reason, ?string &$category = null): ?int
    {
        $grantor = trim((string) $staging->op_name);
        $grantee = trim((string) $staging->ro_name);

        if (! $this->option('include-government') && $this->isGovernmentEntity($grantee)) {
            $reason = "staged holder '{$grantee}' is a government entity";
            $category = 'Staged holder is a government entity';
            return null;
        }

        // Same name on both sides is not a transfer — the detector matched on spacing,
        // casing or punctuation noise ('MRS J.S MOUKARIM' vs 'MRS. J. S. MOUKARIM' is one
        // real staged pair). Writing it would put a self-transfer into the timeline.
        if ($this->identityKey($grantor) === $this->identityKey($grantee)) {
            $reason = 'Party 1 and Party 2 are the same holder';
            $category = 'Party 1 and Party 2 are the same holder';
            return null;
        }

        $op = $db->table('pra')->where('id', $staging->op_id)->first();

        if (! $op) {
            $reason = "source pra row #{$staging->op_id} no longer exists";
            $category = 'Source pra row is gone';
            return null;
        }

        if (! empty($op->is_deleted)) {
            $reason = "source pra row #{$staging->op_id} is soft-deleted";
            $category = 'Source pra row is soft-deleted';
            return null;
        }

        $existingId = $this->findExistingTot($op, $grantor, $grantee);

        if ($existingId !== null) {
            // Id 0 only occurs in a dry run, where earlier rows of this same run are
            // indexed without being written.
            if ($existingId === 0) {
                $reason = 'duplicate of a transfer generated earlier in this run';
                $category = 'Duplicate within this run';
                return null;
            }

            // The transfer is already on file. Close the staging row so it stops being
            // offered rather than leaving it to be retried forever.
            if (! $dryRun) {
                $this->stampStaging($db, $staging->id, $userId, $now, "Already present as pra #{$existingId}; no new row written.");
            }
            $reason = "already generated as pra #{$existingId} (staging closed)";
            $category = 'Transfer already on file';
            return null;
        }

        $payload = $this->buildTotPayload($op, $staging, $grantor, $grantee, $userId, $now);

        if ($dryRun) {
            // Index it anyway so the preview counts a repeated staging pair once, exactly
            // as a real run would.
            $this->indexTot(0, $op->prop_id ?? null, $op->mlsFNo ?? null, $op->fileno ?? null, $grantor, $grantee);

            return 0;
        }

        $newId = $db->transaction(function () use ($db, $payload, $staging, $userId, $now) {
            $id = (int) $db->table('pra')->insertGetId($payload);

            $this->stampStaging($db, $staging->id, $userId, $now, "Generated Transfer of Title as pra #{$id}.");

            return $id;
        });

        $this->indexTot($newId, $op->prop_id ?? null, $op->mlsFNo ?? null, $op->fileno ?? null, $grantor, $grantee);

        return $newId;
    }

    private function buildTotPayload(object $op, object $staging, string $grantor, string $grantee, ?int $userId, $now): array
    {
        $payload = (array) $op;

        foreach (self::DO_NOT_COPY as $column) {
            unset($payload[$column]);
        }

        $payload['transaction_type'] = self::TOT_TYPE;
        $payload['instrument_type'] = self::TOT_TYPE;

        $payload['Grantor'] = $grantor;
        $payload['party_1'] = $grantor;
        $payload['Grantee'] = $grantee;
        $payload['party_2'] = $grantee;

        // A reconstructed bridging transfer was never presented to the registry, so it
        // carries no registration particulars — the same 0/0/0 convention OSS applies to
        // its Change-of-Name transfers.
        $payload['regNo'] = '0/0/0';
        $payload['serialNo'] = '0';
        $payload['pageNo'] = '0';
        $payload['volumeNo'] = '0';

        // Lineage back to the OP this transfer was derived from.
        $payload['source_op_table'] = 'pra';
        $payload['source_op_id'] = (int) $op->id;

        $payload['is_deleted'] = 0;
        $payload['source'] = 'ToT Staging';
        $payload['system_source'] = 'TOTSTAGING';
        $payload['created_at'] = $now->toDateTimeString();
        $payload['created_by'] = $userId !== null ? (string) $userId : null;

        $payload['remarks'] = trim(
            trim((string) ($op->remarks ?? ''))
            . ' Auto-generated Transfer of Title from OP/RofO holder mismatch on '
            . $now->toDateString() . " (tot:generate-from-staging, staging ID {$staging->id})."
        );

        return $payload;
    }

    /**
     * A transfer already on file for this parcel between the same two parties. Matched on
     * the parties rather than on source_op_id so rows the dashboard wrote — which carry
     * no linkage at all — are still recognised.
     *
     * Looked up against a prebuilt index rather than queried per row: the type match is a
     * leading-wildcard LIKE, so every call would be a full scan of pra's 130k+ rows and a
     * full run would spend hours on nothing else.
     */
    private function findExistingTot(object $op, string $grantor, string $grantee): ?int
    {
        foreach ($this->totKeys($op->prop_id ?? null, $op->mlsFNo ?? null, $op->fileno ?? null) as $scope) {
            $key = $scope . '|' . $this->norm($grantor) . '|' . $this->norm($grantee);

            if (isset($this->existingTots[$key])) {
                return $this->existingTots[$key];
            }
        }

        return null;
    }

    /**
     * Loads every live Transfer of Title once, indexed by parcel/file scope plus the two
     * party names. Rows written during this run are added to the index as they go, so a
     * staging table holding the same transfer twice (it does — e.g. #15 and #16 both name
     * COM-2000-173) produces one row, not two.
     */
    private function loadExistingTots($db): void
    {
        $this->existingTots = [];

        $rows = $db->table('pra')
            ->where(function ($q) {
                $q->where('transaction_type', 'LIKE', '%Transfer of Title%')
                    ->orWhere('instrument_type', 'LIKE', '%Transfer of Title%');
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->get(['id', 'prop_id', 'mlsFNo', 'fileno', 'Grantor', 'Grantee', 'party_1', 'party_2']);

        foreach ($rows as $row) {
            $this->indexTot((int) $row->id, $row->prop_id, $row->mlsFNo, $row->fileno,
                $row->party_1 ?: $row->Grantor, $row->party_2 ?: $row->Grantee);
        }
    }

    private function indexTot(int $id, $propId, $mlsFNo, $fileNo, $grantor, $grantee): void
    {
        foreach ($this->totKeys($propId, $mlsFNo, $fileNo) as $scope) {
            $key = $scope . '|' . $this->norm($grantor) . '|' . $this->norm($grantee);
            $this->existingTots[$key] ??= $id;
        }
    }

    /** @return array<int, string> */
    private function totKeys($propId, $mlsFNo, $fileNo): array
    {
        $keys = [];

        if (trim((string) $propId) !== '') {
            $keys[] = 'p:' . trim((string) $propId);
        }
        foreach ([$mlsFNo, $fileNo] as $candidate) {
            if (trim((string) $candidate) !== '') {
                $keys[] = 'f:' . $this->norm($candidate);
            }
        }

        return array_unique($keys);
    }

    private function stampStaging($db, $stagingId, ?int $userId, $now, string $remarks): void
    {
        $db->table('pra_tot_staging')->where('id', $stagingId)->update([
            'status' => 'processed',
            'is_processed' => 1,
            'processed_at' => $now,
            'processed_by' => $userId,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Repairs rows the dashboard generated before this command existed. They were copied
     * wholesale from their OP, so they still announce themselves as the OP's instrument
     * type and carry no link back to it — which is why they do not appear in Legal Search
     * timelines or in oss:retire-duplicate-op-tots. Identified by the remark the
     * dashboard stamped on them.
     */
    private function repairExisting($db, bool $dryRun): int
    {
        $rows = $db->table('pra')
            ->where('remarks', 'LIKE', '%Auto-generated ToT from OP/RO mismatch%')
            ->get(['id', 'mlsFNo', 'prop_id', 'transaction_type', 'instrument_type', 'remarks']);

        if ($rows->isEmpty()) {
            $this->info('Nothing to repair — no dashboard-generated ToT rows found.');
            return self::SUCCESS;
        }

        $repaired = 0;

        foreach ($rows as $row) {
            // The staging id is the only thread back to the source OP: the dashboard
            // stored no linkage on the row itself.
            if (! preg_match('/Staging ID:\s*(\d+)/i', (string) $row->remarks, $m)) {
                $this->line("  #{$row->id} ({$row->mlsFNo}): no staging id in remarks — left alone");
                continue;
            }

            $staging = $db->table('pra_tot_staging')->where('id', (int) $m[1])->first(['op_id']);

            $update = ['instrument_type' => self::TOT_TYPE, 'transaction_type' => self::TOT_TYPE];

            if ($staging && $staging->op_id) {
                $update['source_op_table'] = 'pra';
                $update['source_op_id'] = (int) $staging->op_id;
            }

            $this->line("  #{$row->id} ({$row->mlsFNo}): instrument_type '{$row->instrument_type}' → '"
                . self::TOT_TYPE . "'" . (isset($update['source_op_id']) ? ", linked to OP #{$update['source_op_id']}" : ''));

            if (! $dryRun) {
                $db->table('pra')->where('id', $row->id)->update($update);
            }

            $repaired++;
        }

        $verb = $dryRun ? 'Would repair' : 'Repaired';
        $this->newLine();
        $this->info("{$verb} {$repaired} of {$rows->count()} dashboard-generated ToT row(s).");

        return self::SUCCESS;
    }

    private function isGovernmentEntity(?string $name): bool
    {
        if (! $name) {
            return false;
        }

        $name = strtoupper(trim($name));

        foreach (self::GOVERNMENT_PATTERNS as $pattern) {
            if (preg_match('/\b' . $pattern . '\b/', $name)) {
                return true;
            }
        }

        return false;
    }

    private function norm($value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
    }

    /**
     * Aggressive form used only to decide "are these two names the same person?".
     * Deliberately not used for the duplicate index, where dropping punctuation would
     * widen what counts as an already-recorded transfer.
     */
    private function identityKey($value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
    }

    /** @return array<int, string> */
    private function csvOption(string $name): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option($name))
        )));
    }
}
