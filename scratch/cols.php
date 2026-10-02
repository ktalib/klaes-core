<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach (['PropID_Master','pra','file_indexings'] as $t) {
  echo "=== $t ===\n";
  $cols = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($t);
  echo implode(', ',$cols)."\n\n";
}
