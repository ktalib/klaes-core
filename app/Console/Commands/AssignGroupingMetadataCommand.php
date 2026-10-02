<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssignGroupingMetadataCommand extends Command
{
    /**
     * Size of each group/sys batch block.
     */
    private const GROUP_SIZE = 100;

    /**
     * Default batch size for incremental updates.
     */
    private const DEFAULT_BATCH_SIZE = 25000;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'grouping:assign-metadata
        {--dry-run : Analyse dataset and label capacity without writing changes}
        {--batch-size=0 : Optional chunk size for update loop (0 = single UPDATE)}';

    /**
     * The console command description.
     */
    protected $description = 'Populate [group], sys_batch_no and shelf_rack for grouping records using 100-file batches.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $connection = DB::connection('sqlsrv');

        try {
            $this->info(($dryRun ? 'Analysing' : 'Assigning') . ' grouping metadata…');
            $this->buildStagingTable($connection);

            $stats = $connection->selectOne(<<<'SQL'
                SELECT
                    COUNT_BIG(*) AS total_rows,
                    ISNULL(MAX(group_no), 0) AS total_groups
                FROM #GroupingAssignments
            SQL);

            $totalRows = (int) ($stats->total_rows ?? 0);
            $totalGroups = (int) ($stats->total_groups ?? 0);

            if ($totalRows === 0) {
                $this->warn('No grouping rows detected. Aborting.');
                $this->cleanup();
                return self::SUCCESS;
            }

            $this->line('Rows discovered: ' . number_format($totalRows));
            $this->line('Groups required (@' . self::GROUP_SIZE . '/group): ' . number_format($totalGroups));

            if ($dryRun) {
                $availableLabels = $this->countAvailableLabels();
                $deficit = max(0, $totalGroups - $availableLabels);
                $this->line('Rack_Shelf_Labels available: ' . number_format($availableLabels));
                $this->line('Additional labels required: ' . number_format($deficit));
                $this->cleanup();
                $this->info('Dry-run complete. No data changed.');
                return self::SUCCESS;
            }

            $this->ensureLabelCapacity($totalGroups);
            $this->assignLabelsToStaging($totalGroups);
            $affected = $this->updateGroupingTable($batchSize);
            $this->markLabelsUsed();
            $this->cleanup();

            $this->info('Grouping metadata assignment complete. Updated rows: ' . number_format($affected));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->cleanup();
            $this->error('Failed to assign grouping metadata: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function buildStagingTable($connection): void
    {
        $this->line('Creating staging dataset…');

        $connection->statement(<<<'SQL'
            IF OBJECT_ID('tempdb..#GroupingAssignments') IS NOT NULL
                DROP TABLE #GroupingAssignments;
        SQL);

        // Build row numbers inside each landuse/year partition, using any available serial for ordering.
        $connection->statement(<<<'SQL'
            SELECT
                g.id AS grouping_id,
                ISNULL(NULLIF(g.landuse, ''), 'UNKNOWN') AS landuse_key,
                ISNULL(CONVERT(VARCHAR(4), g.year), '0000') AS year_key,
                ROW_NUMBER() OVER (
                    PARTITION BY ISNULL(NULLIF(g.landuse, ''), 'UNKNOWN'),
                                 ISNULL(CONVERT(VARCHAR(4), g.year), '0000')
                    ORDER BY
                        CASE
                            WHEN TRY_CONVERT(BIGINT, g.number) IS NOT NULL THEN TRY_CONVERT(BIGINT, g.number)
                            WHEN TRY_CONVERT(BIGINT, PARSENAME(REPLACE(g.awaiting_fileno, '-', '.'), 1)) IS NOT NULL THEN TRY_CONVERT(BIGINT, PARSENAME(REPLACE(g.awaiting_fileno, '-', '.'), 1))
                            WHEN TRY_CONVERT(BIGINT, PARSENAME(REPLACE(g.mls_fileno, '-', '.'), 1)) IS NOT NULL THEN TRY_CONVERT(BIGINT, PARSENAME(REPLACE(g.mls_fileno, '-', '.'), 1))
                            ELSE g.id
                        END,
                        g.id
                ) AS row_num
            INTO #GroupingAssignments
            FROM grouping g WITH (READPAST)
            WHERE g.deleted_at IS NULL;
        SQL);

        // Pre-compute group/sys batch numbers.
        $connection->statement(<<<'SQL'
            ALTER TABLE #GroupingAssignments
            ADD
                group_no INT,
                sys_batch_no INT,
                label_id INT NULL,
                shelf_rack NVARCHAR(32) NULL;
        SQL);

        $connection->statement(<<<'SQL'
            UPDATE #GroupingAssignments
            SET
                group_no = ((row_num - 1) / 100) + 1,
                sys_batch_no = ((row_num - 1) / 100) + 1;
        SQL);
    }

    private function countAvailableLabels(): int
    {
        $row = DB::connection('sqlsrv')->selectOne(<<<'SQL'
            SELECT COUNT(*) AS available
            FROM Rack_Shelf_Labels WITH (NOLOCK)
            WHERE ISNULL(is_used, 0) = 0
        SQL);

        return (int) ($row->available ?? 0);
    }

    private function ensureLabelCapacity(int $requiredGroups): void
    {
        if ($requiredGroups <= 0) {
            return;
        }

        $connection = DB::connection('sqlsrv');
        $available = $this->countAvailableLabels();

        if ($available >= $requiredGroups) {
            $this->line('Rack/Shelf label pool sufficient (' . number_format($available) . ').');
            return;
        }

        $needed = $requiredGroups - $available;
        $this->warn('Generating ' . number_format($needed) . ' additional rack/shelf labels…');

        $existing = $connection->table('Rack_Shelf_Labels')
            ->pluck('full_label')
            ->map(fn ($label) => strtoupper((string) $label))
            ->flip();

        $newLabels = [];
        foreach ($this->generateRackCodes() as $rack) {
            for ($shelf = 1; $shelf <= 999; $shelf++) {
                $full = strtoupper($rack . $shelf);
                if (isset($existing[$full])) {
                    continue;
                }

                $newLabels[] = [
                    'rack' => $rack,
                    'shelf' => (string) $shelf,
                    'full_label' => $full,
                    'is_used' => 0,
                ];

                if (count($newLabels) >= $needed) {
                    break 2;
                }
            }
        }

        if (count($newLabels) < $needed) {
            throw new \RuntimeException('Unable to generate enough unique rack/shelf labels.');
        }

        foreach (array_chunk($newLabels, 1000) as $chunk) {
            $connection->table('Rack_Shelf_Labels')->insert($chunk);
        }

        $this->line('Added labels: ' . number_format(count($newLabels)));
    }

    private function assignLabelsToStaging(int $totalGroups): void
    {
        if ($totalGroups <= 0) {
            return;
        }

        $this->line('Mapping labels to staging groups…');

        DB::connection('sqlsrv')->statement(
            sprintf(
                <<<'SQL'
                    WITH LabelSource AS (
                        SELECT TOP (%d)
                            id,
                            full_label,
                            ROW_NUMBER() OVER (ORDER BY id) AS rn
                        FROM Rack_Shelf_Labels WITH (UPDLOCK, READPAST)
                        WHERE ISNULL(is_used, 0) = 0
                        ORDER BY id
                    )
                    UPDATE g
                    SET
                        g.label_id = l.id,
                        g.shelf_rack = l.full_label
                    FROM #GroupingAssignments g
                    JOIN LabelSource l ON l.rn = g.group_no;
                SQL,
                max($totalGroups, 0)
            )
        );
    }

    private function updateGroupingTable(int $batchSize): int
    {
        $connection = DB::connection('sqlsrv');

        if ($batchSize <= 0) {
            $this->line('Applying assignments to grouping table…');

            return $connection->update(<<<'SQL'
                UPDATE g
                SET
                    [group] = CAST(a.group_no AS VARCHAR(20)),
                    sys_batch_no = CAST(a.sys_batch_no AS VARCHAR(20)),
                    shelf_rack = a.shelf_rack
                FROM grouping g
                JOIN #GroupingAssignments a ON g.id = a.grouping_id
                WHERE
                    ISNULL(g.[group], '') <> CAST(a.group_no AS VARCHAR(20))
                    OR ISNULL(g.sys_batch_no, '') <> CAST(a.sys_batch_no AS VARCHAR(20))
                    OR ISNULL(g.shelf_rack, '') <> ISNULL(a.shelf_rack, '');
            SQL);
        }

        $total = 0;
        $this->line('Applying assignments in batches of ' . number_format($batchSize) . '…');

        while (true) {
            $updated = $connection->update(
                sprintf(
                    <<<'SQL'
                        WITH batch AS (
                            SELECT TOP (%d)
                                g.id,
                                a.group_no,
                                a.sys_batch_no,
                                a.shelf_rack
                            FROM grouping g WITH (UPDLOCK, READPAST)
                            JOIN #GroupingAssignments a ON g.id = a.grouping_id
                            WHERE
                                ISNULL(g.[group], '') <> CAST(a.group_no AS VARCHAR(20))
                                OR ISNULL(g.sys_batch_no, '') <> CAST(a.sys_batch_no AS VARCHAR(20))
                                OR ISNULL(g.shelf_rack, '') <> ISNULL(a.shelf_rack, '')
                            ORDER BY g.id
                        )
                        UPDATE g
                        SET
                            [group] = CAST(b.group_no AS VARCHAR(20)),
                            sys_batch_no = CAST(b.sys_batch_no AS VARCHAR(20)),
                            shelf_rack = b.shelf_rack
                        FROM grouping g
                        JOIN batch b ON g.id = b.id;
                    SQL,
                    max($batchSize, 1)
                )
            );

            if ($updated === 0) {
                break;
            }

            $total += $updated;
        }

        return $total;
    }

    private function markLabelsUsed(): void
    {
        DB::connection('sqlsrv')->statement(<<<'SQL'
            UPDATE l
            SET is_used = 1
            FROM Rack_Shelf_Labels l
            JOIN (
                SELECT DISTINCT label_id
                FROM #GroupingAssignments
                WHERE label_id IS NOT NULL
            ) assigned ON assigned.label_id = l.id;
        SQL);
    }

    private function cleanup(): void
    {
        DB::connection('sqlsrv')->statement(<<<'SQL'
            IF OBJECT_ID('tempdb..#GroupingAssignments') IS NOT NULL
                DROP TABLE #GroupingAssignments;
        SQL);
    }

    private function generateRackCodes(): Collection
    {
        $codes = [];
        $letters = range('A', 'Z');

        foreach ($letters as $letter) {
            $codes[] = $letter;
        }

        foreach ($letters as $first) {
            foreach ($letters as $second) {
                $codes[] = $first . $second;
            }
        }

        return collect($codes);
    }
}
