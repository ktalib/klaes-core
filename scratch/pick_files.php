<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c = DB::connection('sqlsrv');
$div = $c->select("SELECT TOP 5 fi.file_number FROM file_indexings fi
  JOIN PropID_Master pm ON pm.primary_file_number = fi.file_number
  WHERE fi.prop_id IS NOT NULL AND fi.prop_id<>'' AND fi.prop_id<>pm.prop_id
    AND (fi.is_deleted IS NULL OR fi.is_deleted=0)
    AND EXISTS (SELECT 1 FROM pra WHERE pra.fileno = fi.file_number)
  ORDER BY fi.id");
$op = $c->select("SELECT TOP 3 fileno FROM pra WHERE op_type IS NOT NULL AND fileno IS NOT NULL AND fileno<>'' ORDER BY id");
$files = array_merge(array_column($div,'file_number'), array_column($op,'fileno'));
file_put_contents(__DIR__.'/smoke_files.json', json_encode($files));
print_r($files);
