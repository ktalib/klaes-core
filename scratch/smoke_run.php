<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(\App\Services\LegalSearchService::class);
$files = json_decode(file_get_contents(__DIR__.'/smoke_files.json'), true);
$out=[];
foreach ($files as $fn) {
  try {
    $r=$svc->search(['query'=>$fn]);
    $rows = $r['transactions'] ?? $r['records'] ?? [];
    $sig=[];
    foreach($rows as $t){
      $sig[] = ($t['fileno'] ?? $t['file_number'] ?? $t['mlsFNo'] ?? '-').'|'.($t['prop_id'] ?? '-').'|'.substr($t['instrument_type'] ?? $t['transaction_type'] ?? '-',0,24);
    }
    sort($sig);
    $out[$fn]=$sig;
  } catch(\Throwable $e){ $out[$fn]=['ERR: '.substr($e->getMessage(),0,80)]; }
}
file_put_contents(__DIR__.'/smoke_'.$argv[1].'.json', json_encode($out, JSON_PRETTY_PRINT));
echo "done ".$argv[1]."\n";
