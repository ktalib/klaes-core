<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$files = ['RES-2025-48', 'RES-2025-139', 'RES-2025-142'];

$rows = Illuminate\Support\Facades\DB::connection('sqlsrv')
    ->table('pra')
    ->whereIn('mlsFNo', $files)
    ->orWhereIn('fileno', $files)
    ->orderBy('mlsFNo')
    ->orderBy('id')
    ->get([
        'id',
        'mlsFNo',
        'fileno',
        'temp_fileno',
        'instrument_type',
        'transaction_type',
        'prop_id',
        'parent_prop_id',
        'source_op_table',
        'source_op_id',
        'Grantor',
        'Grantee',
        'party_1',
        'party_2',
        'created_at',
        'updated_at',
    ]);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
