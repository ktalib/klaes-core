<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the lineage of files retired by a subdivision/merger before the mother was
 * guaranteed a prop_id.
 *
 * A subdivision takes its children's parent_prop_id from the mother's file_indexings
 * row. When the mother was never indexed (or its row carried no prop_id) that value was
 * null, so two things broke at once:
 *
 *   - the mother has no PropID_Master entry, so the Decommissioned Files list shows "-"
 *     in its PropID column (the parcel lost its identity the moment the file was retired);
 *   - every child's parent_prop_id is null, so nothing points back up to the mother.
 *
 * This command mints/reuses the mother's prop_id via PropertyIdAllocationService (which
 * writes the PropID_Master row) and stamps it onto the children named in the archive's
 * successor_file_no. Forward-only and idempotent: an existing parent_prop_id is never
 * overwritten, and a mother that already resolves to a prop_id is reused, not re-minted.
 */
class BackfillDecommissionedParentPropIds extends Command
{
    protected $signature = 'decommissioning:backfill-parent-propids
        {--file= : Only process this decommissioned (mother) file number}
        {--all : Process every decommissioned file that has successors}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Give decommissioned mother files a prop_id and link their successor children via parent_prop_id.';

    public function __construct(private PropertyIdAllocationService $allocator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = trim((string) $this->option('file'));
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if ($file === '' && !$all) {
            $this->error('Pass --file=<FILE-NO> for a single mother, or --all to sweep every decommissioned file.');

            return self::FAILURE;
        }

        $conn = DB::connection('sqlsrv');
        $hasFileNumberParentCol = Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id');

        $mothers = $conn->table('decommissioned_files')
            ->where(function ($q) {
                $q->where('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning');
            })
            ->whereNotNull('successor_file_no')
            ->where('successor_file_no', '<>', '')
            ->when($file !== '', function ($q) use ($file) {
                $q->where(function ($w) use ($file) {
                    $w->where('file_no', $file)
                        ->orWhere('mls_file_no', $file)
                        ->orWhere('kangis_file_no', $file)
                        ->orWhere('new_kangis_file_no', $file);
                });
            })
            ->orderBy('id')
            ->get();

        if ($mothers->isEmpty()) {
            $this->warn($file !== ''
                ? "No decommissioned file with successors found for {$file}."
                : 'No decommissioned files with successors found.');

            return self::SUCCESS;
        }

        $mintedParents = 0;
        $linkedChildren = 0;

        foreach ($mothers as $mother) {
            $motherNo = trim((string) ($mother->mls_file_no ?: $mother->file_no));
            if ($motherNo === '') {
                continue;
            }

            $children = $this->splitFileNumbers($mother->successor_file_no);
            if (empty($children)) {
                continue;
            }

            // A child's prop_id can never be the mother's: they are separate parcels, and a
            // mother that "resolves" to one of its own children means a lookup went sideways.
            $childPropIds = $conn->table('PropID_Master')
                ->whereIn('primary_file_number', $children)
                ->pluck('prop_id')
                ->map(fn ($p) => (int) $p)
                ->all();

            $existing = $this->findPropId($conn, $mother, $childPropIds);
            $propId = $existing;

            if ($propId === null) {
                if ($dryRun) {
                    $this->line("  [dry-run] {$motherNo}: would mint a prop_id (none found in PropID_Master)");
                } else {
                    try {
                        // skip_lookup: mint a brand-new prop_id and its master row outright.
                        // Without it the allocator also matches temp_fileno, and a subdivision
                        // stamps the MOTHER's number into each child's temp_fileno — so the
                        // mother would come back holding one of its own children's prop_ids.
                        $propId = $this->allocator->allocateOrRetrievePropId(
                            $motherNo,
                            trim((string) $mother->mls_file_no) ?: null,
                            trim((string) $mother->kangis_file_no) ?: null,
                            trim((string) $mother->new_kangis_file_no) ?: null,
                            ['skip_lookup' => true]
                        );
                        $mintedParents++;
                        $this->info("  {$motherNo}: minted prop_id {$propId}");
                    } catch (\Throwable $e) {
                        $this->error("  {$motherNo}: could not allocate a prop_id — " . $e->getMessage());
                        continue;
                    }
                }
            }

            if ($propId !== null && in_array((int) $propId, $childPropIds, true)) {
                $this->error("  {$motherNo}: resolved prop_id {$propId} belongs to one of its own children — skipped.");
                continue;
            }

            if ($propId === null) {
                continue; // dry-run with nothing allocated yet; children counts below would be guesses
            }

            // Only fill gaps — a child that already points somewhere keeps its link.
            $pendingIndexing = $conn->table('file_indexings')
                ->whereIn('file_number', $children)
                ->whereNull('parent_prop_id')
                ->where(function ($q) use ($propId) {
                    $q->whereNull('prop_id')->orWhere('prop_id', '<>', $propId);
                })
                ->count();

            if ($dryRun) {
                $this->line("  [dry-run] {$motherNo} (prop_id " . ($existing ?? '?') . "): would link {$pendingIndexing} of "
                    . count($children) . ' children');
                continue;
            }

            $updated = $conn->table('file_indexings')
                ->whereIn('file_number', $children)
                ->whereNull('parent_prop_id')
                ->where(function ($q) use ($propId) {
                    $q->whereNull('prop_id')->orWhere('prop_id', '<>', $propId);
                })
                ->update(['parent_prop_id' => $propId, 'updated_at' => now()]);

            if ($hasFileNumberParentCol) {
                $conn->table('fileNumber')
                    ->whereIn('mlsfNo', $children)
                    ->whereNull('parent_prop_id')
                    ->update(['parent_prop_id' => $propId]);
            }

            $linkedChildren += $updated;
            $this->info("  {$motherNo} (prop_id {$propId}): linked {$updated} of " . count($children) . ' children');
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run complete — nothing written.'
            : "Done. Parents given a prop_id: {$mintedParents}. Children linked: {$linkedChildren}.");

        return self::SUCCESS;
    }

    /**
     * The mother's prop_id if anything already knows it: PropID_Master first (the
     * authority), then the archived indexing row the decommission left behind, then the
     * Legal Search staging tables.
     *
     * Only identity columns are consulted — never temp_fileno / provenance columns, which
     * hold the file a record came FROM rather than what the record IS.
     */
    private function findPropId($conn, $mother, array $childPropIds = []): ?int
    {
        $numbers = array_values(array_filter([
            trim((string) $mother->mls_file_no),
            trim((string) $mother->file_no),
            trim((string) $mother->kangis_file_no),
            trim((string) $mother->new_kangis_file_no),
        ]));

        if (empty($numbers)) {
            return null;
        }

        $sources = [
            ['PropID_Master', ['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno']],
            ['deprecated_records', ['file_number']],
            ['file_history_staging', ['mlsfNo', 'fileno']],
            ['pra', ['mlsFNo', 'fileno']],
            ['CofO_staging', ['mlsFNo', 'kangisFileNo', 'fileno']],
        ];

        foreach ($sources as [$table, $columns]) {
            try {
                $found = $conn->table($table)
                    ->where(function ($q) use ($columns, $numbers) {
                        foreach ($columns as $col) {
                            $q->orWhereIn($col, $numbers);
                        }
                    })
                    ->whereNotNull('prop_id')
                    ->pluck('prop_id');
            } catch (\Throwable $e) {
                continue; // table/column absent on this environment
            }

            foreach ($found as $candidate) {
                $candidate = (int) $candidate;
                if ($candidate > 0 && !in_array($candidate, $childPropIds, true)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * successor_file_no is a CSV of the children a batch subdivision produced.
     */
    private function splitFileNumbers(?string $csv): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $csv)),
            fn ($n) => $n !== ''
        )));
    }
}
