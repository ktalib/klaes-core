<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function timeQuery($label, $sql) {
    $start = microtime(true);
    try {
        $result = DB::connection('sqlsrv')->select($sql);
        $duration = microtime(true) - $start;
        echo sprintf("%s: %.3f seconds\n", $label, $duration);
    } catch (Throwable $e) {
        $duration = microtime(true) - $start;
        echo sprintf("%s failed after %.3f seconds: %s\n", $label, $duration, $e->getMessage());
    }
}

timeQuery('Aggregated quick stats', "
    SELECT 
        COUNT(*) as sample_total,
        SUM(CASE WHEN mapping = 1 THEN 1 ELSE 0 END) as sample_matched,
        AVG(CASE WHEN mapping = 1 THEN 1.0 ELSE 0.0 END) * 100 as match_rate
    FROM (
        SELECT TOP 10000 mapping, created_at
        FROM grouping WITH (NOLOCK)
        ORDER BY id DESC
    ) recent_sample
");

timeQuery('Land use aggregate', "
    SELECT 
        COALESCE(landuse, 'Unknown') as landuse,
        COUNT(*) * 210 as total_files,
        SUM(CASE WHEN mapping = 1 THEN 1 ELSE 0 END) * 210 as matched_files,
        CAST(AVG(CASE WHEN mapping = 1 THEN 100.0 ELSE 0.0 END) AS DECIMAL(5,2)) as match_percentage
    FROM (
        SELECT TOP 1000 landuse, mapping
        FROM grouping WITH (NOLOCK)
        WHERE landuse IS NOT NULL
        ORDER BY id DESC
    ) sample
    GROUP BY landuse
    ORDER BY COUNT(*) DESC
");

timeQuery('Activity aggregate', "
    SELECT TOP 20
        awaiting_fileno,
        mls_fileno,
        landuse,
        year,
        CEILING(CAST(COALESCE(number, 1) AS FLOAT) / 100.0) as group_number,
        1 as position_in_group,
        COALESCE(updated_at, created_at) as matched_at
    FROM (
        SELECT TOP 2000 *
        FROM grouping WITH (NOLOCK)
        WHERE mapping = 1 
        ORDER BY id DESC
    ) recent
    ORDER BY COALESCE(updated_at, created_at) DESC
");
