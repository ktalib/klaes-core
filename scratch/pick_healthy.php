<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c = DB::connection('sqlsrv');
// healthy: file_indexings.prop_id agrees with PropID_Master
$h = $c->select("SELECT TOP 6 fi.file_number FROM file_indexings fi
  JOIN PropID_Master pm ON pm.primary_file_number = fi.file_number
  WHERE fi.prop_id IS NOT NULL AND fi.prop_id<>'' AND fi.prop_id = pm.prop_id
    AND (fi.is_deleted IS NULL OR fi.is_deleted=0)
  ORDER BY fi.id DESC");
// KANGIS alias files (must keep alias expansion)
$k = $c->select("SELECT TOP 4 primary_file_number FROM PropID_Master
  WHERE kangisFileNo IS NOT NULL AND kangisFileNo<>'' AND primary_file_number IS NOT NULL ORDER BY id DESC");
$files = array_merge(array_column($h,'file_number'), array_column($k,'primary_file_number'));
file_put_contents(__DIR__.'/healthy_files.json', json_encode(array_values(array_unique($files))));
print_r($files);
