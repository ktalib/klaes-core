<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function bench($label, $sql) {
    $start = microtime(true);
    DB::connection('sqlsrv')->select($sql);
    $duration = microtime(true) - $start;
    echo sprintf("%s: %.3f seconds\n", $label, $duration);
}

bench('Order by id', "SELECT TOP 400 landuse, mapping FROM grouping WITH (NOLOCK) WHERE landuse IS NOT NULL ORDER BY id DESC");
bench('Order by created_at', "SELECT TOP 400 landuse, mapping FROM grouping WITH (NOLOCK) WHERE landuse IS NOT NULL ORDER BY created_at DESC");
bench('Order by updated_at', "SELECT TOP 400 landuse, mapping FROM grouping WITH (NOLOCK) WHERE landuse IS NOT NULL ORDER BY updated_at DESC");
