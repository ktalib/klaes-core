<?php

namespace App\Console\Commands;

use App\Models\TitleStatusApplication;
use App\Services\TitleStatusParcelRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Move parcel-update-type rows out of `title_status_applications` and into the
 * dedicated Parcel Update tables.
 *
 * Types Subdivision / Merger / Change of Purpose / Extension / Separation were being
 * captured as classification ticks in the File Indexing "Create Indexing" dialog,
 * which POSTs to the shared title-status backend — so they piled up in
 * `title_status_applications` and showed on the Title Status page instead of their
 * own Parcel Update tables.
 *
 * For each such row this command:
 *   1. Inserts a matching row in the correct Parcel Update table, marked hidden
 *      (status = TitleStatusParcelRouter::HIDDEN_STATUS) so it stays off the Parcel
 *      Update frontend until it is processed through the system.
 *   2. Soft-deletes the original title_status row (is_deleted = 1) so it drops off
 *      the Title Status page. Nothing is hard-deleted.
 *
 * Idempotent: re-running skips rows already migrated (tracked by a marker in the
 * destination row's remarks, and by the source row's is_deleted flag).
 */
class MigrateTitleStatusParcelUpdates extends Command
{
    protected $signature = 'title-status:migrate-parcel-updates
                            {--dry-run : Report what would change without writing anything}
                            {--type=* : Limit to specific title types (e.g. --type=Subdivision --type=Merger)}';

    protected $description = 'Move Subdivision/Merger/Change of Purpose/Extension/Separation rows from title_status_applications into their Parcel Update tables (hidden) and soft-delete the originals.';

    /** All parcel-update title types held in title_status_applications. */
    private const PARCEL_TYPES = [
        TitleStatusApplication::TYPE_SUBDIVISION,
        TitleStatusApplication::TYPE_MERGER,
        TitleStatusApplication::TYPE_PURPOSE,
        TitleStatusApplication::TYPE_EXTENSION,
        TitleStatusApplication::TYPE_SEPARATION,
        'Plot Extension',
        'File Extension',
    ];

    public function handle(TitleStatusParcelRouter $router): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $types  = $this->option('type') ?: self::PARCEL_TYPES;

        // Reject unknown types early so a typo doesn't silently migrate nothing.
        $unknown = array_diff($types, self::PARCEL_TYPES);
        if ($unknown) {
            $this->error('Unknown --type value(s): ' . implode(', ', $unknown));
            $this->line('Valid types: ' . implode(', ', self::PARCEL_TYPES));
            return self::FAILURE;
        }

        $query = TitleStatusApplication::query()
            ->whereIn('title_type', $types)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to migrate — no active parcel-update rows found in title_status_applications.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$total} parcel-update row(s) to migrate.");
        $this->newLine();

        $actorId  = Auth::id();
        $migrated = 0;
        $skipped  = 0;
        $failed   = 0;
        $perType  = [];

        $query->chunkById(200, function ($rows) use ($router, $dryRun, $actorId, &$migrated, &$skipped, &$failed, &$perType) {
            foreach ($rows as $row) {
                $table = $router->tableFor($row->title_type);
                if ($table === null) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $migrated++;
                    $perType[$row->title_type] = ($perType[$row->title_type] ?? 0) + 1;
                    $this->line(sprintf('  #%-6s %-18s %-28s -> %s', $row->id, $row->title_type, $row->file_no, $table));
                    continue;
                }

                DB::connection('sqlsrv')->beginTransaction();
                try {
                    $router->route($row->title_type, [
                        'file_no'        => $row->file_no,
                        'file_title'     => $row->file_title,
                        'applicant_name' => $row->applicant_name,
                        'plot_no'        => $row->plot_no,
                        'plan_no'        => $row->plan_no ?? null,
                        'house_no'       => $row->house_no,
                        'street_name'    => $row->street_name,
                        'district'       => $row->district,
                        'lga'            => $row->lga,
                        'state'          => $row->state,
                        'location'       => $row->location,
                        'land_use'       => $row->land_use,
                        'remark'         => $row->remark,
                        'captured_by'    => $row->captured_by,
                        'created_at'     => $row->created_at,
                        'updated_at'     => $row->updated_at,
                    ], (int) $row->id);

                    // Soft-delete the source row so it leaves the Title Status page.
                    DB::connection('sqlsrv')->table('title_status_applications')
                        ->where('id', $row->id)
                        ->update([
                            'is_deleted' => 1,
                            'deleted_by' => $actorId,
                            'deleted_at' => now(),
                            'updated_at' => now(),
                        ]);

                    DB::connection('sqlsrv')->commit();
                    $migrated++;
                    $perType[$row->title_type] = ($perType[$row->title_type] ?? 0) + 1;
                } catch (\Throwable $e) {
                    DB::connection('sqlsrv')->rollBack();
                    $failed++;
                    $this->error("  #{$row->id} ({$row->title_type} / {$row->file_no}) failed: " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] would migrate' : 'Migrated') . ": {$migrated}");
        foreach ($perType as $type => $count) {
            $this->line("    {$type}: {$count}");
        }
        if ($skipped) {
            $this->warn("Skipped (unmapped type): {$skipped}");
        }
        if ($failed) {
            $this->error("Failed: {$failed}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
