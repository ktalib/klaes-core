<?php

namespace App\Console\Commands;

use App\Services\PropIdLineageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills `ancestral_prop_id` — the top level of the parcel lineage cascade:
 *
 *     Ancestral PropID -> Parent PropID -> PropID
 *
 * Ancestral is the OLDEST generation reachable by climbing parent_prop_id, so a
 * merged file that is three generations deep resolves past its immediate parent
 * to the original parcel. Rows with no parent at all are roots and are written as
 * NULL.
 *
 * Idempotent and safe to re-run: it recomputes from the current lineage links
 * every time and never touches prop_id or parent_prop_id.
 */
class BackfillAncestralPropIds extends Command
{
    protected $signature = 'propid:backfill-ancestral
        {--dry-run : Report what would change without writing}
        {--table= : Limit to one table (file_indexings, fileNumber, pra, CofO_staging)}
        {--chunk=1000 : Rows to scan per chunk}';

    protected $description = 'Backfill ancestral_prop_id (root of the parent_prop_id chain) on the lineage tables. Idempotent.';

    public function __construct(private PropIdLineageService $lineage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(100, (int) $this->option('chunk'));
        $only = trim((string) $this->option('table'));

        $tables = array_keys(PropIdLineageService::LINEAGE_TABLES);

        if ($only !== '') {
            if (!in_array($only, $tables, true)) {
                $this->error("Unknown table '{$only}'. Expected one of: " . implode(', ', $tables));
                return self::FAILURE;
            }
            $tables = [$only];
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be written.');
        }

        $conn = DB::connection('sqlsrv');
        $grandTotal = 0;

        // One query for the whole lineage graph — turns the per-row chain walk
        // into a hash lookup, which is what makes a full sweep tractable.
        $this->line('Loading lineage links...');
        $this->lineage->warmParentMap();

        foreach ($tables as $table) {
            if (!$this->lineage->tableIsReady($table)) {
                $this->warn("Skipping {$table} — table or ancestral_prop_id/parent_prop_id column missing (run migrations first).");
                continue;
            }

            $key = $this->lineage->keyColumn($table);
            $hasPropId = Schema::connection('sqlsrv')->hasColumn($table, 'prop_id');
            $columns = array_values(array_filter([
                $key,
                $hasPropId ? 'prop_id' : null,
                'parent_prop_id',
                'ancestral_prop_id',
            ]));

            $scanned = 0;
            $changed = 0;

            $this->line("Scanning {$table}...");

            // Keyset ("chunk by id") paging — plain chunk() uses OFFSET, which
            // degrades badly on the six-figure row counts these tables carry.
            $conn->table($table)
                ->select($columns)
                ->orderBy($key)
                ->chunkById($chunk, function ($rows) use (&$scanned, &$changed, $table, $key, $hasPropId, $dryRun, $conn) {
                    // Rows sharing a resolved value are updated as one statement.
                    $pending = [];

                    foreach ($rows as $row) {
                        $scanned++;

                        $resolved = $this->lineage->resolveAncestralForRow(
                            $hasPropId ? ($row->prop_id ?? null) : null,
                            $row->parent_prop_id ?? null
                        );

                        $current = $row->ancestral_prop_id ?? null;
                        $current = ($current === null || trim((string) $current) === '') ? null : trim((string) $current);

                        if ($resolved === $current) {
                            continue;
                        }

                        $changed++;
                        $pending[$resolved ?? ''][] = $row->{$key};
                    }

                    if (!$dryRun) {
                        foreach ($pending as $value => $ids) {
                            $conn->table($table)
                                ->whereIn($key, $ids)
                                ->update(['ancestral_prop_id' => $value === '' ? null : (string) $value]);
                        }
                    }
                }, $key);

            $verb = $dryRun ? 'would change' : 'updated';
            $this->info("  {$table}: {$scanned} scanned, {$changed} {$verb}.");
            $grandTotal += $changed;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry run complete. ' : 'Backfill complete. ') . "{$grandTotal} row(s) " . ($dryRun ? 'would be updated.' : 'updated.'));

        return self::SUCCESS;
    }
}
