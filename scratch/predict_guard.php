<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$svc = app(\App\Services\LegalSearchService::class);
$c = DB::connection('sqlsrv');
$m = new ReflectionMethod($svc,'isForeignIndexingPropId'); $m->setAccessible(true);
$v = new ReflectionMethod($svc,'fileNumberVariants'); $v->setAccessible(true);
foreach (json_decode(file_get_contents(__DIR__.'/smoke_files.json'),true) as $fn) {
  $fi = $c->table('file_indexings')->where('file_number',$fn)->whereNull('deleted_at')->first(['prop_id']);
  $pm = $c->table('PropID_Master')->where('primary_file_number',$fn)->value('prop_id');
  $pid = (string)($fi->prop_id ?? '');
  $rej = $pid!=='' ? $m->invoke($svc,$c,$v->invoke($svc,$fn),$pid) : null;
  printf("  %-16s fi.prop_id=%-8s master=%-8s => %s\n",$fn,$pid?:'-',$pm?:'-',
    $rej===null?'no indexing prop_id':($rej?'REJECTED (was contaminating)':'trusted (unchanged)'));
}
