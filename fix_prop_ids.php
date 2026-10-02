<?php
/**
 * Production Data Recovery Script: Unmerge Prop IDs
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Prop ID Unmerge Process...\n";
echo "===================================\n";

function getNextPropId() {
    static $currentMax = 80000000;
    $currentMax++;
    return $currentMax;
}

$tables = [
    'pra' => 'mlsFNo',
    'instrument_capture' => 'mlsFNo',
    'file_history_staging' => 'mlsFNo',
    'pic' => 'mlsFNo',
    'CofO_staging' => 'mlsFNo'
];

// Step 0: Rescue any prop_ids > 2147483647 that might exist
echo "Step 0: Scanning for oversized prop_ids...\n";
$rescueSql = "
    SELECT DISTINCT UPPER(LTRIM(RTRIM(mlsFNo))) as file_no
    FROM pra WHERE TRY_CAST(prop_id AS BIGINT) > 2147483647 AND mlsFNo IS NOT NULL
    UNION
    SELECT DISTINCT UPPER(LTRIM(RTRIM(mlsFNo))) as file_no
    FROM instrument_capture WHERE TRY_CAST(prop_id AS BIGINT) > 2147483647 AND mlsFNo IS NOT NULL
    UNION
    SELECT DISTINCT UPPER(LTRIM(RTRIM(mlsFNo))) as file_no
    FROM CofO_staging WHERE TRY_CAST(prop_id AS BIGINT) > 2147483647 AND mlsFNo IS NOT NULL
    UNION
    SELECT DISTINCT UPPER(LTRIM(RTRIM(mlsFNo))) as file_no
    FROM file_history_staging WHERE TRY_CAST(prop_id AS BIGINT) > 2147483647 AND mlsFNo IS NOT NULL
";
$rescueFiles = DB::connection('sqlsrv')->select($rescueSql);

foreach ($rescueFiles as $rescue) {
    $fileNo = $rescue->file_no;
    $newId = getNextPropId();
    foreach ($tables as $table => $column) {
        DB::connection('sqlsrv')
            ->table($table)
            ->whereRaw("TRY_CAST(prop_id AS BIGINT) > 2147483647")
            ->whereRaw("UPPER(LTRIM(RTRIM($column))) = ?", [$fileNo])
            ->update(['prop_id' => $newId]);
    }
}
echo "Rescued " . count($rescueFiles) . " oversized files.\n\n";

// Step 1: Find conflicting prop_ids
echo "Step 1: Unmerging conflicts...\n";
$sql = "
    WITH CombinedFiles AS (
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no FROM pra WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
        UNION
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no FROM instrument_capture WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
        UNION
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no FROM file_history_staging WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
    )
    SELECT prop_id, 
           COUNT(DISTINCT file_no) as distinct_files,
           STRING_AGG(CAST(file_no AS NVARCHAR(MAX)), ', ') as file_numbers
    FROM CombinedFiles
    WHERE prop_id IS NOT NULL AND prop_id < 2147483647
    GROUP BY prop_id
    HAVING COUNT(DISTINCT file_no) > 1
";

$conflicts = DB::connection('sqlsrv')->select($sql);
echo "Found " . count($conflicts) . " conflicting prop_ids.\n\n";

$fixedCount = 0;
$newPropIdsAssigned = 0;

foreach ($conflicts as $conflict) {
    $propId = $conflict->prop_id;
    $fileNumbers = array_unique(explode(', ', $conflict->file_numbers));
    
    // Ignore invalid placeholders
    $invalidPlaceholders = ['N/A', 'NA', 'NONE', 'NULL', 'NIL', '-', '.', '0', 'TBD'];
    $validFiles = [];
    foreach ($fileNumbers as $f) {
        if (!in_array(strtoupper($f), $invalidPlaceholders)) {
            $validFiles[] = $f;
        }
    }

    if (count($validFiles) <= 1) {
        continue;
    }

    $primaryFile = array_shift($validFiles); // Keep original prop_id

    foreach ($validFiles as $fileToMigrate) {
        $newPropId = getNextPropId();
        
        foreach ($tables as $table => $column) {
            DB::connection('sqlsrv')
                ->table($table)
                ->where('prop_id', $propId)
                ->whereRaw("UPPER(LTRIM(RTRIM($column))) = ?", [$fileToMigrate])
                ->update(['prop_id' => $newPropId]);
        }
        $newPropIdsAssigned++;
    }
    $fixedCount++;
}

echo "\nUnmerging complete! Fixed $fixedCount prop_ids by assigning $newPropIdsAssigned new prop_ids.\n";

// Step 2: Empty PropID_Master
echo "\nTruncating PropID_Master to remove corrupted index...\n";
DB::connection('sqlsrv')->table('PropID_Master')->truncate();
echo "PropID_Master truncated.\n";

// Step 3: Rebuild PropID_Master from fixed tables
echo "\nRebuilding PropID_Master from cleaned data...\n";

$rebuildSql = "
    INSERT INTO PropID_Master (prop_id, primary_file_number, created_at, updated_at, status)
    SELECT prop_id, file_no, GETDATE(), GETDATE(), 'active'
    FROM (
        SELECT prop_id, file_no,
               ROW_NUMBER() OVER (PARTITION BY file_no ORDER BY prop_id DESC) as rn_file,
               ROW_NUMBER() OVER (PARTITION BY prop_id ORDER BY file_no DESC) as rn_prop
        FROM (
            SELECT TRY_CAST(prop_id AS INT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no
            FROM (
                SELECT prop_id, mlsFNo FROM pra WHERE mlsFNo IS NOT NULL AND mlsFNo != ''
                UNION ALL
                SELECT prop_id, mlsFNo FROM instrument_capture WHERE mlsFNo IS NOT NULL AND mlsFNo != ''
                UNION ALL
                SELECT prop_id, mlsFNo FROM file_history_staging WHERE mlsFNo IS NOT NULL AND mlsFNo != ''
            ) combined
            WHERE TRY_CAST(prop_id AS BIGINT) < 2147483647 
              AND UPPER(LTRIM(RTRIM(mlsFNo))) NOT IN ('N/A', 'NA', 'NONE', 'NULL', 'NIL', '-', '.', '0', 'TBD')
        ) cleaned
        WHERE prop_id IS NOT NULL AND file_no IS NOT NULL
    ) grouped
    WHERE rn_file = 1 AND rn_prop = 1
";

$inserted = DB::connection('sqlsrv')->statement($rebuildSql);

echo "Rebuild complete!\n";
echo "===================================\n";
echo "Recovery successfully finished.\n";
