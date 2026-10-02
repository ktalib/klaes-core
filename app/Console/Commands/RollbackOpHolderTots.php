<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Undo one `op-match:backfill` run.
 *
 * Every row that command writes carries its run id in pra.remarks, so a run can be
 * lifted back out without touching transfers written by a DIFFERENT run, or by an
 * officer pressing Match on the capture form. Pass no run id to list the runs found.
 *
 * SOFT, NOT HARD. The rows are flagged is_deleted = 1, never DELETEd. Nothing is
 * removed from the deeds register in this application — a row that turned out to be
 * wrong is still a record of what the register said, and every consumer already
 * reads through the is_deleted flag.
 *
 *     php artisan op-match:rollback                       # list the runs
 *     php artisan op-match:rollback OPMB-20260830-141500 --dry-run
 *     php artisan op-match:rollback OPMB-20260830-141500
 */
class RollbackOpHolderTots extends Command
{
    protected $signature = 'op-match:rollback
        {run? : The run id stamped on the rows, e.g. OPMB-20260830-141500}
        {--dry-run : Show what would be reversed, change nothing}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Reverse one op-match:backfill run (soft-deletes the transfers it wrote)';

    public function handle(): int
    {
        $run = trim((string) $this->argument('run'));

        if ($run === '') {
            return $this->listRuns();
        }

        if (! preg_match('/^OPMB-\d{8}-\d{6}$/', $run)) {
            $this->error('That does not look like a run id. Expected OPMB-YYYYMMDD-HHMMSS.');
            return self::FAILURE;
        }

        $db = DB::connection('sqlsrv');

        $rows = $db->table('pra')
            ->where('system_source', 'OPHOLDERMATCH')
            // "[[]" is a LITERAL "[" in T-SQL LIKE. Written as '%[OPMB-…]%' the
            // brackets opened a character CLASS instead, so the pattern matched any
            // remarks containing a single character from that set — which is nearly
            // every Match OP transfer, from every run and from the Match button. This
            // command soft-deletes what it selects, so a rollback of one run was
            // selecting other people's transfers along with it.
            ->where('remarks', 'LIKE', '%[[]' . $run . ']%')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('id')
            ->get(['id', 'mlsFNo', 'fileno', 'party_1', 'party_2', 'created_at']);

        if ($rows->isEmpty()) {
            $this->warn('Nothing live to reverse for ' . $run . '. (Already rolled back, or the id is wrong — run with no argument to list the runs.)');
            return self::SUCCESS;
        }

        $this->info($rows->count() . ' transfer(s) written by ' . $run . ':');
        $this->table(
            ['pra id', 'file', 'from', 'to', 'written'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->mlsFNo ?: $r->fileno,
                mb_strimwidth((string) $r->party_1, 0, 28, '…'),
                mb_strimwidth((string) $r->party_2, 0, 28, '…'),
                $r->created_at,
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — nothing changed.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Flag these ' . $rows->count() . ' row(s) as deleted?', false)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        $ids = $rows->pluck('id')->all();
        $now = now()->toDateTimeString();

        // Chunked: SQL Server caps a statement at 2100 bind parameters.
        $reversed = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $reversed += $db->table('pra')->whereIn('id', $chunk)->update([
                'is_deleted' => 1,
                'deleted_at' => $now,
                'remarks'    => DB::raw("CONCAT(ISNULL(remarks, ''), ' [rolled back " . $now . "]')"),
            ]);
        }

        $this->info('Reversed ' . $reversed . ' row(s). They are flagged is_deleted = 1, not removed.');
        Log::warning('op-match:rollback completed', ['run' => $run, 'reversed' => $reversed, 'ids' => $ids]);

        return self::SUCCESS;
    }

    /**
     * The runs on record, read off the stamps rather than a log table — the rows
     * themselves are the only thing that cannot drift out of step with reality.
     */
    private function listRuns(): int
    {
        $rows = DB::connection('sqlsrv')
            ->table('pra')
            ->where('system_source', 'OPHOLDERMATCH')
            ->whereNotNull('remarks')
            ->get(['id', 'remarks', 'is_deleted']);

        $runs = [];
        foreach ($rows as $row) {
            if (! preg_match('/\[(OPMB-\d{8}-\d{6})\]/', (string) $row->remarks, $m)) {
                continue;
            }
            $runs[$m[1]]['live'] = ($runs[$m[1]]['live'] ?? 0) + (empty($row->is_deleted) ? 1 : 0);
            $runs[$m[1]]['reversed'] = ($runs[$m[1]]['reversed'] ?? 0) + (empty($row->is_deleted) ? 0 : 1);
        }

        if (! $runs) {
            $this->info('No op-match:backfill run has written anything yet.');

            // Transfers from the Match button carry no run id and are not reversible
            // here; say so rather than letting the empty list read as "none exist".
            $fromForm = DB::connection('sqlsrv')->table('pra')
                ->where('system_source', 'OPHOLDERMATCH')
                ->count();

            if ($fromForm > 0) {
                $this->line($fromForm . ' transfer(s) carry system_source = OPHOLDERMATCH but no run id — those came from the Match button on the capture form, one file at a time.');
            }

            return self::SUCCESS;
        }

        ksort($runs);
        $this->table(
            ['run id', 'live rows', 'already reversed'],
            collect($runs)->map(fn ($counts, $run) => [$run, $counts['live'] ?? 0, $counts['reversed'] ?? 0])->values()->all()
        );

        return self::SUCCESS;
    }
}
