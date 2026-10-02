<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(\App\Services\LegalSearchService::class);
foreach (['CON-RES-1985-523','CON-RES-2018-489'] as $fn) {
  echo "=== search $fn ===\n";
  $r = $svc->search(['query'=>$fn]);
  $rows = $r['transactions'] ?? $r['records'] ?? [];
  if (!$rows) { echo "  keys: ".implode(', ',array_keys($r))."\n"; }
  foreach ($rows as $t) {
    printf("  %-18s %-8s %-26s p1=%-28s p2=%s\n",
      $t['fileno'] ?? $t['file_number'] ?? $t['mlsFNo'] ?? '-',
      $t['prop_id'] ?? '-',
      substr($t['instrument_type'] ?? $t['transaction_type'] ?? '-',0,26),
      substr($t['party_1'] ?? '-',0,28), substr($t['party_2'] ?? '-',0,24));
  }
  echo "  total: ".count($rows)."\n\n";
}
