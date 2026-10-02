<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildPropIds extends Command
{
    /**
     * The Artisan command name and options.
     *
     * @var string
     */
    protected $signature = 'propid:rebuild
                            {--connection=sqlsrv : Database connection to run against}
                            {--verify : Run integrity checks after rebuilding prop_id values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild prop_id values so every MLS file number has one shared prop_id across staging tables.';

    /**
     * Tables that share the prop_id mapping keyed by their MLS column.
     *
     * @var array<string,string>
     */
    private array $targetTables = [
        'file_history_staging' => 'mlsFNo',
        'CofO_staging' => 'mlsFNo',
        'pic' => 'mlsFNo',
    ];

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $connection = DB::connection($connectionName);

        $existingTables = $this->resolveExistingTables($connection);
        if (!isset($existingTables['file_history_staging'])) {
            $this->error('The file_history_staging table must exist to rebuild prop_id values.');

            return self::FAILURE;
        }

        $this->info("Rebuilding prop_id values on connection [{$connectionName}]...");

        try {
            $connection->transaction(function () use ($connection, $existingTables) {
                $connection->unprepared($this->buildPropIdRemapBatch($existingTables));
            });
        } catch (\Throwable $exception) {
            Log::error('propid:rebuild failed', [
                'connection' => $connectionName,
                'error' => $exception->getMessage(),
            ]);

            $this->error('Failed to rebuild prop_id values: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info('prop_id columns reset and remapped successfully.');

        if ($this->option('verify')) {
            $this->runVerification($connection, array_keys($existingTables));
        } else {
            $this->comment('Pass --verify to run duplicate and mismatch checks after rebuilding.');
        }

        return self::SUCCESS;
    }

    /**
     * Limit the target table list to ones that exist on the connection.
     */
    private function resolveExistingTables(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();

        return array_filter(
            $this->targetTables,
            static fn (string $mlsColumn, string $table) => $schema->hasTable($table),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Build the SQL batch that clears and rebuilds the prop_id mapping.
     */
    private function buildPropIdRemapBatch(array $tables): string
    {
        $resets = [];
        $updates = [];
        $unions = [];

        foreach ($tables as $table => $mlsColumn) {
            $resets[] = "UPDATE {$table} SET prop_id = NULL;";
            $updates[] = <<<SQL
UPDATE tgt
SET tgt.prop_id = map.prop_id
FROM {$table} AS tgt
JOIN #PropIDMap AS map ON map.mlsFNo = tgt.{$mlsColumn};
SQL;
            $unions[] = "SELECT DISTINCT {$mlsColumn} AS mlsFNo FROM {$table} WHERE {$mlsColumn} IS NOT NULL";
        }

        $resetSql = implode(PHP_EOL, $resets);
        $updateSql = implode(PHP_EOL, $updates);
        $unionSql = implode(PHP_EOL . 'UNION' . PHP_EOL, $unions);

        return <<<SQL
SET NOCOUNT ON;

{$resetSql}

IF OBJECT_ID('tempdb..#PropIDMap', 'U') IS NOT NULL
    DROP TABLE #PropIDMap;

SELECT 
    mlsFNo,
    ROW_NUMBER() OVER (ORDER BY mlsFNo) AS prop_id
INTO #PropIDMap
FROM (
    {$unionSql}
) AS AllMLS;

{$updateSql}
SQL;
    }

    /**
     * Run duplicate + cross-table checks so issues are surfaced immediately.
     */
    private function runVerification(Connection $connection, array $tables): void
    {
        $this->info('Running verification checks...');

        foreach ($tables as $table) {
            $duplicates = $connection->select(<<<SQL
SELECT TOP (5) mlsFNo, COUNT(DISTINCT prop_id) AS propIdCount
FROM {$table}
WHERE mlsFNo IS NOT NULL
GROUP BY mlsFNo
HAVING COUNT(DISTINCT prop_id) > 1
ORDER BY propIdCount DESC, mlsFNo;
SQL);

            if (!empty($duplicates)) {
                $this->warn("{$table}: MLS numbers with multiple prop_id values detected.");
                $this->table(['mlsFNo', 'propIdCount'], $duplicates);
            } else {
                $this->info("{$table}: ✅ each mlsFNo maps to exactly one prop_id.");
            }

            $propCollisions = $connection->select(<<<SQL
SELECT TOP (5) prop_id, COUNT(DISTINCT mlsFNo) AS mlsCount
FROM {$table}
WHERE prop_id IS NOT NULL
GROUP BY prop_id
HAVING COUNT(DISTINCT mlsFNo) > 1
ORDER BY mlsCount DESC, prop_id;
SQL);

            if (!empty($propCollisions)) {
                $this->warn("{$table}: prop_id values shared across multiple mlsFNo entries.");
                $this->table(['prop_id', 'mlsCount'], $propCollisions);
            } else {
                $this->info("{$table}: ✅ prop_id values are unique per mlsFNo.");
            }
        }

        $mismatches = $connection->select(<<<'SQL'
WITH AllMLS AS (
    SELECT DISTINCT mlsFNo FROM file_history_staging WHERE mlsFNo IS NOT NULL
    UNION
    SELECT DISTINCT mlsFNo FROM CofO_staging WHERE mlsFNo IS NOT NULL
    UNION
    SELECT DISTINCT mlsFNo FROM pic WHERE mlsFNo IS NOT NULL
),
FH AS (
    SELECT mlsFNo, MAX(prop_id) AS prop_id
    FROM file_history_staging
    WHERE mlsFNo IS NOT NULL
    GROUP BY mlsFNo
),
COFO AS (
    SELECT mlsFNo, MAX(prop_id) AS prop_id
    FROM CofO_staging
    WHERE mlsFNo IS NOT NULL
    GROUP BY mlsFNo
),
PIC AS (
    SELECT mlsFNo, MAX(prop_id) AS prop_id
    FROM pic
    WHERE mlsFNo IS NOT NULL
    GROUP BY mlsFNo
)
SELECT TOP (10)
    AllMLS.mlsFNo,
    FH.prop_id AS file_history_prop,
    COFO.prop_id AS cofo_prop,
    PIC.prop_id AS pic_prop
FROM AllMLS
LEFT JOIN FH ON FH.mlsFNo = AllMLS.mlsFNo
LEFT JOIN COFO ON COFO.mlsFNo = AllMLS.mlsFNo
LEFT JOIN PIC ON PIC.mlsFNo = AllMLS.mlsFNo
WHERE
    (FH.prop_id IS NOT NULL AND COFO.prop_id IS NOT NULL AND FH.prop_id <> COFO.prop_id)
    OR (FH.prop_id IS NOT NULL AND PIC.prop_id IS NOT NULL AND FH.prop_id <> PIC.prop_id)
    OR (COFO.prop_id IS NOT NULL AND PIC.prop_id IS NOT NULL AND COFO.prop_id <> PIC.prop_id);
SQL);

        if (!empty($mismatches)) {
            $this->warn('Cross-table prop_id mismatches detected (showing up to 10).');
            $this->table(['mlsFNo', 'file_history', 'cofo', 'pic'], $mismatches);
        } else {
            $this->info('All tables share the same prop_id mapping.');
        }
    }
}
