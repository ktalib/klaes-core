<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ls = app(\App\Services\LegalSearchService::class);
$rep = $ls->buildPrintReport(['file_number'=>$argv[1] ?? 'RES-1985-1547']);
$d = $rep['payload']['data'] ?? [];
echo "data keys: ".implode(', ',array_keys($d))."\n\n";
echo "row keys: ".implode(', ',array_keys($d['rows'][0] ?? []))."\n\n";
foreach(['rows','excluded_rows','omitted','excluded'] as $k) if(isset($d[$k])) echo "$k count=".count($d[$k])."\n";
echo "\nfirst row: ".json_encode($d['rows'][0] ?? [], JSON_PRETTY_PRINT)."\n";
