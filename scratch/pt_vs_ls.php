<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ctl = app(\App\Http\Controllers\PropertySearchController::class);
$ls  = app(\App\Services\LegalSearchService::class);
$files = ['RES-1985-1547','CON-RES-1985-523','RES-1981-1','RES-2026-1558','KNML 8080','IND-2026-1010'];
foreach ($files as $fn) {
  try {
    $rep = $ls->buildPrintReport(['file_number'=>$fn]);
    $lsRows = $rep['payload']['data']['rows'] ?? [];
    $lsSig = array_map(fn($r)=>trim(($r['instrument_type']??'').'|'.($r['grantor']??'').'|'.($r['grantee']??'')), $lsRows);

    $req = \Illuminate\Http\Request::create('/property-search/timeline','GET',['file_number'=>$fn,'mode'=>'partial']);
    $p = $ctl->timeline($req)->getData()['historyPayload'];
    $ptSig = array_map(fn($r)=>trim(($r['transaction_type']??'').'|'.($r['party_1']??'').'|'.($r['party_2']??'')), $p['weighted']);

    printf("%-18s LS=%-2d PT=%-2d %s\n",$fn,count($lsSig),count($ptSig), $lsSig===$ptSig ? 'MATCH' : 'DIFFER');
    if ($lsSig!==$ptSig) { foreach(array_diff($lsSig,$ptSig) as $d) echo "     LS-only: $d\n";
                           foreach(array_diff($ptSig,$lsSig) as $d) echo "     PT-only: $d\n"; }
  } catch(\Throwable $e){ printf("%-18s ERROR %s\n",$fn,substr($e->getMessage(),0,70)); }
}
