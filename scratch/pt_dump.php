<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fn = $argv[1] ?? 'RES-1985-1547';
$ctl = app(\App\Http\Controllers\PropertySearchController::class);
$req = \Illuminate\Http\Request::create('/property-search/timeline','GET',['file_number'=>$fn,'mode'=>'partial']);
$view = $ctl->timeline($req);
$p = $view->getData()['historyPayload'];
echo "propId={$p['propId']} total={$p['totalTransactions']} weighted={$p['weightedCount']} omitted={$p['omittedCount']}\n";
foreach(['weighted','omitted'] as $k){
  echo "-- $k --\n";
  foreach($p[$k] as $i=>$t) printf("  %d. %-12s w=%-5s %-26s %-26s -> %s\n",$i+1,
    substr($t['source_table']??'-',0,12), $t['timeline_weight']??'-',
    substr($t['transaction_type'] ?? $t['instrument_type'] ?? '-',0,26),
    substr($t['party_1']??'-',0,26), substr($t['party_2']??'-',0,24));
}
