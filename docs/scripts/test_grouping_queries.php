<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function timeQuery($label, $callback) {
    $start = microtime(true);
    try {
        $result = $callback();
        $duration = microtime(true) - $start;
        echo sprintf("%s: %.3f seconds\n", $label, $duration);
        if (is_array($result)) {
            echo sprintf("  rows: %d\n", count($result));
        }
    } catch (Throwable $e) {
        $duration = microtime(true) - $start;
        echo sprintf("%s failed after %.3f seconds: %s\n", $label, $duration, $e->getMessage());
    }
}

timeQuery('getQuickStats', function () {
    return DB::connection('sqlsrv')->select("SELECT TOP 10000 mapping, created_at FROM grouping WITH (NOLOCK) ORDER BY id DESC");
});

timeQuery('getQuickLandUse', function () {
    return DB::connection('sqlsrv')->select("SELECT TOP 1000 landuse, mapping FROM grouping WITH (NOLOCK) WHERE landuse IS NOT NULL ORDER BY id DESC");
});

timeQuery('getQuickActivity', function () {
    return DB::connection('sqlsrv')->select("SELECT TOP 2000 * FROM grouping WITH (NOLOCK) WHERE mapping = 1 ORDER BY id DESC");
});
