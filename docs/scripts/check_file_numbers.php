<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking land_use_serials table...\n\n";

$records = \DB::connection('sqlsrv')->table('land_use_serials')->get();

foreach($records as $record) {
    echo "Land Use: {$record->land_use_type}\n";
    echo "Prefix: {$record->prefix}\n";  
    echo "Current Serial: {$record->current_serial}\n";
    echo "Next File Number: {$record->prefix}-2025-" . ($record->current_serial + 1) . "\n";
    echo "---\n";
}

echo "\n🧪 Testing controller logic...\n\n";

$landUseTypes = ['COMMERCIAL', 'RESIDENTIAL', 'INDUSTRIAL', 'MIXED'];

foreach($landUseTypes as $landUse) {
    $landUseCode = match(strtoupper($landUse)) {
        'COMMERCIAL' => 'COM',
        'INDUSTRIAL' => 'IND', 
        'RESIDENTIAL' => 'RES',
        'MIXED' => 'MIXED',
        default => 'COM'
    };
    
    $currentYear = date('Y');
    $current = \DB::connection('sqlsrv')
        ->table('land_use_serials')
        ->where('land_use_type', $landUse)
        ->where('year', $currentYear)
        ->first();
    
    if ($current) {
        $nextSerial = $current->current_serial + 1;
        $fileNo = "ST-{$landUseCode}-{$currentYear}-{$nextSerial}";
        
        echo "✅ {$landUse}:\n";
        echo "   Code: {$landUseCode}\n";
        echo "   File Number: {$fileNo}\n\n";
    } else {
        echo "❌ {$landUse}: No record found!\n\n";
    }
}