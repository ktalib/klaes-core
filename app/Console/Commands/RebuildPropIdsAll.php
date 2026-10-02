<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RebuildPropIdsAll extends Command
{
    protected $signature = 'propid:rebuild-all
                            {--connection=sqlsrv : Database connection to run against}
                            {--dry-run : Print counts and lineage preview without writing}
                            {--no-followers : Skip caveats / manual_file_linkages / deprecated_records remap}
                            {--verify : Print summary after rebuild}';

    protected $description = 'Rebuild prop_id across PRA, PIC, IC, FH, CofO_staging (+ follower tables) using mlsFNo with temp_fileno fallback. Snapshots OP/ToT lineage first.';

    private array $primaryTables = [
        'pra',
        'pic',
        'instrument_capture',
        'file_history_staging',
        'CofO_staging',
    ];

    public function handle(): int
    {
        $connection = DB::connection($this->option('connection'));
        $isDry = (bool) $this->option('dry-run');

        $this->info('Pre-flight identifier counts:');
        $this->renderPreflight($connection);

        if ($isDry) {
            $this->warn('--dry-run set: no writes will be performed.');
            $this->previewLineage($connection);
            return self::SUCCESS;
        }

        if (! $this->confirm('Proceed with prop_id rebuild on connection [' . $this->option('connection') . ']?', false)) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        try {
            $connection->transaction(function () use ($connection) {
                $this->ensureLineageTable($connection);
                $this->snapshotLineage($connection);
                $this->buildMaps($connection);
                $this->clearPropIds($connection);
                $this->applyNewPropIds($connection);
                $this->backfillSnapshotNewPropId($connection);
                $this->remapPraParentPropId($connection);

                if (! $this->option('no-followers')) {
                    $this->remapFollowerTables($connection);
                }

                $this->rebuildPropIdMaster($connection);

                $this->dropMaps($connection);
            }, 1);
        } catch (\Throwable $e) {
            Log::error('propid:rebuild-all failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Rebuild complete.');

        if ($this->option('verify')) {
            $this->renderPreflight($connection);
        }

        $this->comment('Snapshot table: propid_rebuild_lineage  (filter by MAX(batch_run_at) for the latest run)');
        $this->comment('PropID_Master cleared and repopulated. Allocator will continue from MAX(prop_id)+1.');

        return self::SUCCESS;
    }

    private function renderPreflight(Connection $conn): void
    {
        $rows = [];
        foreach ($this->primaryTables as $t) {
            if (! Schema::connection($conn->getName())->hasTable($t)) {
                continue;
            }
            $total       = $conn->table($t)->count();
            $hasMls      = $conn->table($t)->whereNotNull('mlsFNo')->whereRaw("LTRIM(RTRIM(mlsFNo)) <> ''")->count();
            $tempOnly    = $conn->table($t)
                ->whereNotNull('temp_fileno')->whereRaw("LTRIM(RTRIM(temp_fileno)) <> ''")
                ->whereRaw("(mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')")
                ->count();
            $neither     = $conn->table($t)
                ->whereRaw("(mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')")
                ->whereRaw("(temp_fileno IS NULL OR LTRIM(RTRIM(temp_fileno)) = '')")
                ->count();
            $withPropId  = $conn->table($t)->whereNotNull('prop_id')->count();
            $rows[] = [$t, $total, $hasMls, $tempOnly, $neither, $withPropId];
        }
        $this->table(
            ['table', 'total', 'has_mls', 'temp_only', 'neither', 'with_prop_id'],
            $rows
        );
    }

    private function previewLineage(Connection $conn): void
    {
        $tots = $conn->table('pra')->whereNotNull('source_op_id')->count();
        $ops  = $conn->table('pra')->whereNotNull('parent_prop_id')->distinct()->count('parent_prop_id');
        $this->line("Lineage preview: <comment>{$tots}</comment> ToT rows referencing <comment>{$ops}</comment> distinct parent OP prop_ids.");
    }

    private function ensureLineageTable(Connection $conn): void
    {
        $schema = Schema::connection($conn->getName());
        if ($schema->hasTable('propid_rebuild_lineage')) {
            return;
        }

        $conn->statement(<<<'SQL'
CREATE TABLE [dbo].[propid_rebuild_lineage] (
    [id]                  BIGINT IDENTITY(1,1) NOT NULL,
    [batch_run_at]        DATETIME2(0)        NOT NULL,
    [tracking_no]         NVARCHAR(80)        NOT NULL,
    [lineage_group]       BIGINT              NULL,
    [role]                NVARCHAR(10)        NOT NULL,
    [source_table]        NVARCHAR(50)        NOT NULL,
    [source_row_id]       BIGINT              NOT NULL,
    [mlsFNo]              NVARCHAR(255)       NULL,
    [temp_fileno]         NVARCHAR(255)       NULL,
    [old_prop_id]         BIGINT              NULL,
    [old_parent_prop_id]  NVARCHAR(50)        NULL,
    [old_source_op_id]    NVARCHAR(50)        NULL,
    [old_source_op_table] NVARCHAR(50)        NULL,
    [new_prop_id]         BIGINT              NULL,
    CONSTRAINT [PK_propid_rebuild_lineage] PRIMARY KEY CLUSTERED ([id])
)
SQL);
        $conn->statement('CREATE NONCLUSTERED INDEX [IX_prl_tracking_no]    ON [propid_rebuild_lineage]([tracking_no])');
        $conn->statement('CREATE NONCLUSTERED INDEX [IX_prl_lineage_group] ON [propid_rebuild_lineage]([lineage_group])');
        $conn->statement('CREATE NONCLUSTERED INDEX [IX_prl_source]         ON [propid_rebuild_lineage]([source_table],[source_row_id])');
        $conn->statement('CREATE NONCLUSTERED INDEX [IX_prl_old_prop_id]    ON [propid_rebuild_lineage]([old_prop_id])');

        $this->info('Created propid_rebuild_lineage.');
    }

    private function snapshotLineage(Connection $conn): void
    {
        $conn->statement(<<<'SQL'
;WITH PraSnap AS (
    SELECT
        p.id, p.prop_id, p.mlsFNo, p.temp_fileno,
        p.parent_prop_id, p.source_op_id, p.source_op_table,
        CASE
            WHEN p.parent_prop_id IS NOT NULL
                 AND LTRIM(RTRIM(p.parent_prop_id)) <> ''
                 AND p.parent_prop_id NOT LIKE '%[^0-9]%'
                THEN CAST(p.parent_prop_id AS BIGINT)
            ELSE CAST(p.prop_id AS BIGINT)
        END AS lineage_group_val
    FROM [dbo].[pra] p
    WHERE p.prop_id IS NOT NULL
       OR p.source_op_id IS NOT NULL
       OR p.parent_prop_id IS NOT NULL
)
INSERT INTO [dbo].[propid_rebuild_lineage]
    (batch_run_at, tracking_no, lineage_group, role,
     source_table, source_row_id, mlsFNo, temp_fileno,
     old_prop_id, old_parent_prop_id, old_source_op_id, old_source_op_table,
     new_prop_id)
SELECT
    SYSUTCDATETIME(),
    CONCAT('LIN-', s.lineage_group_val, '-',
           CASE WHEN s.source_op_id IS NOT NULL THEN 'TOT' ELSE 'OP' END,
           '-', s.id),
    s.lineage_group_val,
    CASE WHEN s.source_op_id IS NOT NULL THEN 'TOT' ELSE 'OP' END,
    'pra',
    s.id,
    s.mlsFNo,
    s.temp_fileno,
    s.prop_id,
    s.parent_prop_id,
    s.source_op_id,
    s.source_op_table,
    NULL
FROM PraSnap s
SQL);
        $this->info('Lineage snapshot written.');
    }

    private function buildMaps(Connection $conn): void
    {
        $conn->statement("IF OBJECT_ID('tempdb..#PropIDMap', 'U') IS NOT NULL DROP TABLE #PropIDMap");
        $conn->statement(<<<'SQL'
;WITH AllKeys AS (
    SELECT DISTINCT CAST('M:' + UPPER(LTRIM(RTRIM(mlsFNo))) AS NVARCHAR(450)) AS key_val FROM (
        SELECT mlsFNo FROM [dbo].[pra]                   WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[pic]             WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[instrument_capture] WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[file_history_staging] WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[CofO_staging]    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    ) m
    UNION
    SELECT DISTINCT CAST('T:' + UPPER(LTRIM(RTRIM(temp_fileno))) AS NVARCHAR(450)) FROM (
        SELECT temp_fileno FROM [dbo].[pra]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[pic]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[instrument_capture]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[CofO_staging]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
    ) t
)
SELECT key_val, ROW_NUMBER() OVER (ORDER BY key_val) AS new_prop_id
INTO #PropIDMap FROM AllKeys
SQL);
        $conn->statement('CREATE UNIQUE CLUSTERED INDEX IX_PropIDMap_key ON #PropIDMap(key_val)');
        $conn->statement('CREATE NONCLUSTERED INDEX IX_PropIDMap_propid  ON #PropIDMap(new_prop_id)');

        $conn->statement("IF OBJECT_ID('tempdb..#PropIDTranslation', 'U') IS NOT NULL DROP TABLE #PropIDTranslation");
        $conn->statement(<<<'SQL'
;WITH SourceRows AS (
    SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[pra]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[pic]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[instrument_capture] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[file_history_staging] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[CofO_staging]       WHERE prop_id IS NOT NULL
), Keyed AS (
    SELECT s.prop_id AS old_prop_id,
        CASE
            WHEN s.mlsFNo IS NOT NULL AND LTRIM(RTRIM(s.mlsFNo)) <> ''
                THEN 'M:' + UPPER(LTRIM(RTRIM(s.mlsFNo)))
            WHEN s.temp_fileno IS NOT NULL AND LTRIM(RTRIM(s.temp_fileno)) <> ''
                THEN 'T:' + UPPER(LTRIM(RTRIM(s.temp_fileno)))
            ELSE NULL
        END AS key_val
    FROM SourceRows s
)
SELECT old_prop_id, new_prop_id INTO #PropIDTranslation
FROM (
    SELECT k.old_prop_id, m.new_prop_id,
           ROW_NUMBER() OVER (PARTITION BY k.old_prop_id ORDER BY m.new_prop_id) AS rn
    FROM Keyed k
    JOIN #PropIDMap m ON m.key_val = k.key_val
    WHERE k.key_val IS NOT NULL
) d
WHERE rn = 1
SQL);
        $conn->statement('CREATE UNIQUE CLUSTERED INDEX IX_PropIDTr_old ON #PropIDTranslation(old_prop_id)');

        $mapCount = (int) $conn->scalar('SELECT COUNT(*) FROM #PropIDMap');
        $trCount  = (int) $conn->scalar('SELECT COUNT(*) FROM #PropIDTranslation');
        $this->info("PropIDMap: {$mapCount} distinct keys.  Translation: {$trCount} old->new pairs.");
    }

    private function clearPropIds(Connection $conn): void
    {
        foreach ($this->primaryTables as $t) {
            $rows = $conn->table($t)->whereNotNull('prop_id')->update(['prop_id' => null]);
            $this->line("  cleared {$t}: {$rows}");
        }
    }

    private function applyNewPropIds(Connection $conn): void
    {
        foreach ($this->primaryTables as $t) {
            $conn->statement(<<<SQL
UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[{$t}] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END
SQL);
            $written = $conn->table($t)->whereNotNull('prop_id')->count();
            $this->line("  wrote {$t}.prop_id (now non-null): {$written}");
        }
    }

    private function backfillSnapshotNewPropId(Connection $conn): void
    {
        $conn->statement(<<<'SQL'
UPDATE l SET l.new_prop_id = p.prop_id
FROM [dbo].[propid_rebuild_lineage] l
JOIN [dbo].[pra] p ON p.id = l.source_row_id
WHERE l.source_table = 'pra' AND l.new_prop_id IS NULL
SQL);
    }

    private function remapPraParentPropId(Connection $conn): void
    {
        $conn->statement(<<<'SQL'
UPDATE t SET t.parent_prop_id = CAST(tr.new_prop_id AS NVARCHAR(50))
FROM [dbo].[pra] t
JOIN #PropIDTranslation tr ON TRY_CAST(t.parent_prop_id AS BIGINT) = tr.old_prop_id
WHERE t.parent_prop_id IS NOT NULL
SQL);
        $this->line('  remapped pra.parent_prop_id');
    }

    private function remapFollowerTables(Connection $conn): void
    {
        $schema = Schema::connection($conn->getName());

        if ($schema->hasTable('caveats')) {
            $conn->statement(<<<'SQL'
UPDATE t SET t.prop_id = tr.new_prop_id
FROM [dbo].[caveats] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = t.prop_id
SQL);
            $this->line('  remapped caveats.prop_id');
        }

        if ($schema->hasTable('manual_file_linkages')) {
            $conn->statement(<<<'SQL'
UPDATE t SET t.prop_id = CAST(tr.new_prop_id AS NVARCHAR(50))
FROM [dbo].[manual_file_linkages] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.prop_id AS BIGINT)
SQL);
            $this->line('  remapped manual_file_linkages.prop_id');
        }

        if ($schema->hasTable('deprecated_records')) {
            $conn->statement(<<<'SQL'
UPDATE t SET t.prop_id = CAST(tr.new_prop_id AS NVARCHAR(100))
FROM [dbo].[deprecated_records] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.prop_id AS BIGINT)
SQL);
            $conn->statement(<<<'SQL'
UPDATE t SET t.parent_prop_id = CAST(tr.new_prop_id AS NVARCHAR(255))
FROM [dbo].[deprecated_records] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.parent_prop_id AS BIGINT)
SQL);
            $this->line('  remapped deprecated_records.prop_id + parent_prop_id');
        }
    }

    private function rebuildPropIdMaster(Connection $conn): void
    {
        if (! Schema::connection($conn->getName())->hasTable('PropID_Master')) {
            $this->warn('  PropID_Master not found; skipping master rebuild.');
            return;
        }

        $deleted = $conn->table('PropID_Master')->delete();
        $this->line("  PropID_Master rows deleted: {$deleted}");

        $conn->statement("DBCC CHECKIDENT ('[dbo].[PropID_Master]', RESEED, 0) WITH NO_INFOMSGS");

        $conn->statement("IF OBJECT_ID('tempdb..#MasterRebuild', 'U') IS NOT NULL DROP TABLE #MasterRebuild");
        $conn->statement(<<<'SQL'
;WITH AllSource AS (
    SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'pra' AS src FROM [dbo].[pra]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'pic'                FROM [dbo].[pic]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'instrument_capture' FROM [dbo].[instrument_capture] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'file_history_staging' FROM [dbo].[file_history_staging] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'CofO_staging'       FROM [dbo].[CofO_staging]       WHERE prop_id IS NOT NULL
), Consolidated AS (
    SELECT
        prop_id,
        MAX(NULLIF(LTRIM(RTRIM(mlsFNo)),           '')) AS mlsFNo,
        MAX(NULLIF(LTRIM(RTRIM(kangisFileNo)),     '')) AS kangisFileNo,
        MAX(NULLIF(LTRIM(RTRIM(NewKANGISFileno)),  '')) AS NewKANGISFileno,
        MAX(NULLIF(LTRIM(RTRIM(temp_fileno)),      '')) AS temp_fileno,
        MAX(src)                                         AS source_table
    FROM AllSource
    GROUP BY prop_id
), Deduped AS (
    SELECT
        prop_id,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(mlsFNo)          ORDER BY prop_id) = 1 OR mlsFNo IS NULL          THEN mlsFNo          END AS mlsFNo,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(kangisFileNo)    ORDER BY prop_id) = 1 OR kangisFileNo IS NULL    THEN kangisFileNo    END AS kangisFileNo,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(NewKANGISFileno) ORDER BY prop_id) = 1 OR NewKANGISFileno IS NULL THEN NewKANGISFileno END AS NewKANGISFileno,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(temp_fileno)     ORDER BY prop_id) = 1 OR temp_fileno IS NULL     THEN temp_fileno     END AS temp_fileno,
        source_table
    FROM Consolidated
)
SELECT * INTO #MasterRebuild FROM Deduped
SQL);
        $conn->statement('CREATE UNIQUE CLUSTERED INDEX IX_MasterRebuild_propid ON #MasterRebuild(prop_id)');

        $conn->statement(<<<'SQL'
;WITH Candidates AS (
    SELECT
        prop_id,
        COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno,
                 CONCAT('AUTO-REF-', prop_id)) AS candidate,
        mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, source_table
    FROM #MasterRebuild
), Ranked AS (
    SELECT
        prop_id, candidate, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, source_table,
        ROW_NUMBER() OVER (PARTITION BY candidate ORDER BY prop_id) AS rn
    FROM Candidates
)
INSERT INTO [dbo].[PropID_Master]
    (prop_id, primary_file_number, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno,
     source_table, status, created_at, updated_at)
SELECT
    prop_id,
    CASE WHEN rn = 1 THEN candidate
         ELSE CONCAT(candidate, '-REF-', prop_id)
    END,
    mlsFNo,
    kangisFileNo,
    NewKANGISFileno,
    temp_fileno,
    source_table,
    'active',
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM Ranked
SQL);
        $conn->statement('DROP TABLE #MasterRebuild');

        $inserted = (int) $conn->scalar('SELECT COUNT(*) FROM PropID_Master');
        $maxProp  = (int) $conn->scalar('SELECT COALESCE(MAX(prop_id), 0) FROM PropID_Master');
        $this->line("  PropID_Master repopulated: {$inserted} rows (max prop_id = {$maxProp}).");
    }

    private function dropMaps(Connection $conn): void
    {
        $conn->statement("IF OBJECT_ID('tempdb..#PropIDMap', 'U') IS NOT NULL DROP TABLE #PropIDMap");
        $conn->statement("IF OBJECT_ID('tempdb..#PropIDTranslation', 'U') IS NOT NULL DROP TABLE #PropIDTranslation");
    }
}
