<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$svc = app(\App\Services\LegalSearchService::class);
$c = DB::connection('sqlsrv');
// mix: divergent-prop_id files, plus OP/TOT shared-prop_id files (legit sharing)
$div = $c->select("SELECT TOP 12 fi.file_number FROM file_indexings fi
  JOIN PropID_Master pm ON pm.primary_file_number = fi.file_number
  WHERE fi.prop_id IS NOT NULL AND fi.prop_id<>'' AND fi.prop_id<>pm.prop_id
    AND (fi.is_deleted IS NULL OR fi.is_deleted=0)
    AND EXISTS (SELECT 1 FROM pra WHERE pra.fileno = fi.file_number)
  ORDER BY NEWID()");
$op = $c->select("SELECT TOP 8 fileno FROM pra WHERE op_type IS NOT NULL AND fileno IS NOT NULL AND fileno<>'' ORDER BY NEWID()");
$files = array_merge(array_column($div,'file_number'), array_column($op,'fileno'));
$out=[];
foreach ($files as $fn) {
  try { $r=$svc->search(['query'=>$fn]); $out[$fn]=count($r['transactions'] ?? $r['records'] ?? []); }
  catch(\Throwable $e){ $out[$fn]='ERR: '.substr($e->getMessage(),0,60); }
}
file_put_contents(__DIR__.'/smoke_'.($argv[1]??'x').'.json', json_encode($out, JSON_PRETTY_PRINT));
foreach($out as $k=>$v) printf("  %-24s %s\n",$k,$v);
