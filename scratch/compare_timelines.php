<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fn = $argv[1] ?? 'RES-1985-1547';
$ls = app(\App\Services\LegalSearchService::class);

echo "########## CORE LS search() : $fn ##########\n";
$r = $ls->search(['query'=>$fn]);
$rows = $r['transactions'] ?? $r['records'] ?? [];
foreach($rows as $i=>$t) printf(" %d. %-10s w=%-4s %-28s %-28s -> %s\n",$i+1,
  substr($t['source_table']??'-',0,10), $t['timeline_weight'] ?? $t['weight'] ?? '-',
  substr($t['instrument_type'] ?? $t['transaction_type'] ?? '-',0,28),
  substr($t['party_1']??'-',0,28), substr($t['party_2']??'-',0,26));
echo " total: ".count($rows)."\n\n";

echo "########## buildPrintReport() : $fn ##########\n";
$rep = $ls->buildPrintReport(['file_number'=>$fn]);
$d = $rep['payload']['data'] ?? [];
foreach(($d['rows'] ?? []) as $i=>$t) printf(" %d. %-10s %-28s %-28s -> %s\n",$i+1,
  substr($t['source_table']??'-',0,10), substr($t['instrument_type'] ?? '-',0,28),
  substr($t['grantor'] ?? $t['party_1'] ?? '-',0,28), substr($t['grantee'] ?? $t['party_2'] ?? '-',0,26));
echo " total: ".count($d['rows'] ?? [])."\n";
