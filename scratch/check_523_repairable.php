<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$c = DB::connection('sqlsrv');
$pid = '16669';
foreach (['pra','file_indexings','fileNumber','deprecated_records'] as $t) {
  try { $n = $c->table($t)->where('parent_prop_id',$pid)->count(); echo "$t.parent_prop_id=$pid : $n\n"; }
  catch (\Throwable $e) { echo "$t : n/a\n"; }
}
echo "backup table exists: ".(\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('file_indexings_propid_backup')?'yes':'no')."\n";
// OP/TOT sharing check
$n = $c->table('pra')->where('prop_id',$pid)->count();
echo "pra rows on prop_id $pid: $n\n";
