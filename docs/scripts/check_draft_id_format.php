<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Checking draft_id format...\n";

$draft = DB::connection('sqlsrv')->table('mother_application_draft')->first(['draft_id']);

if ($draft) {
    echo "Sample draft_id: {$draft->draft_id}\n";
    echo "Length: " . strlen($draft->draft_id) . "\n";
    echo "Format: " . (Str::isUuid($draft->draft_id) ? 'Valid UUID' : 'Not a UUID') . "\n";
} else {
    echo "No drafts found\n";
}

echo "\nTesting with valid UUID...\n";
$testUuid = Str::uuid()->toString();
echo "Generated UUID: {$testUuid}\n";