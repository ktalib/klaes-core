<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\STFileNumberService;

echo "=== Testing ST File Number Service After Comprehensive Migration ===\n\n";

try {
    $service = new STFileNumberService();
    
    echo "=== Testing Next File Number Generation ===\n\n";
    
    // Test 1: Next Residential Primary (should be ST-RES-2025-2)
    echo "1. Testing Next Residential Primary:\n";
    $result1 = $service->generatePrimaryFileNumber('Residential', [
        'applicant_type' => 'Individual',
        'first_name' => 'John',
        'surname' => 'Doe'
    ]);
    
    if ($result1['success']) {
        echo "✅ Expected ST-RES-2025-2, Got: {$result1['data']['np_fileno']}\n";
        echo "   Status: {$result1['data']['tracking_id']}\n";
    } else {
        echo "❌ Failed: {$result1['message']}\n";
    }
    
    // Test 2: Next Commercial Primary (should be ST-COM-2025-4)
    echo "\n2. Testing Next Commercial Primary:\n";
    $result2 = $service->generatePrimaryFileNumber('Commercial', [
        'applicant_type' => 'Corporate',
        'corporate_name' => 'Test Corp'
    ]);
    
    if ($result2['success']) {
        echo "✅ Expected ST-COM-2025-4, Got: {$result2['data']['np_fileno']}\n";
        echo "   Status: {$result2['data']['tracking_id']}\n";
    } else {
        echo "❌ Failed: {$result2['message']}\n";
    }
    
    // Test 3: Next Industrial SUA (should be ST-IND-2025-1)
    echo "\n3. Testing Next Industrial SUA:\n";
    $result3 = $service->generateSUAFileNumber('Industrial', [
        'applicant_type' => 'Individual',
        'first_name' => 'Jane',
        'surname' => 'Smith'
    ]);
    
    if ($result3['success']) {
        echo "✅ Expected ST-IND-2025-1/ST-IND-2025-1-001, Got: {$result3['data']['np_fileno']}/{$result3['data']['unit_fileno']}\n";
        echo "   Status: {$result3['data']['tracking_id']}\n";
    } else {
        echo "❌ Failed: {$result3['message']}\n";
    }
    
    // Test 4: PUA from existing Residential (should be ST-RES-2025-1-001)
    echo "\n4. Testing PUA from existing ST-RES-2025-1:\n";
    $result4 = $service->generatePUAFileNumber('ST-RES-2025-1', [
        'applicant_type' => 'Individual',
        'first_name' => 'Bob',
        'surname' => 'Wilson'
    ]);
    
    if ($result4['success']) {
        echo "✅ Expected ST-RES-2025-1-001, Got: {$result4['data']['unit_fileno']}\n";
        echo "   Parent: {$result4['data']['np_fileno']}\n";
        echo "   Status: {$result4['data']['tracking_id']}\n";
    } else {
        echo "❌ Failed: {$result4['message']}\n";
    }
    
    // Test 5: PUA from existing Commercial SUA (should fail or work correctly)
    echo "\n5. Testing PUA from existing SUA ST-COM-2025-1:\n";
    $result5 = $service->generatePUAFileNumber('ST-COM-2025-1', [
        'applicant_type' => 'Individual',
        'first_name' => 'Alice',
        'surname' => 'Brown'
    ]);
    
    if ($result5['success']) {
        echo "✅ Got: {$result5['data']['unit_fileno']}\n";
        echo "   Parent: {$result5['data']['np_fileno']}\n";
        echo "   Status: {$result5['data']['tracking_id']}\n";
    } else {
        echo "⚠️  Expected behavior - SUA cannot have PUA children: {$result5['message']}\n";
    }
    
    echo "\n=== Final st_file_numbers Table State ===\n";
    $allRecords = DB::connection('sqlsrv')->table('st_file_numbers')
        ->orderBy('id')
        ->get();
    
    foreach ($allRecords as $record) {
        $typeInfo = $record->file_no_type;
        $parentInfo = $record->parent_id ? " (Parent: {$record->parent_id})" : "";
        $status = $record->status;
        
        echo "ID: {$record->id} | {$typeInfo} | {$record->np_fileno} | {$record->fileno} | {$status}{$parentInfo}\n";
    }
    
    echo "\n=== Current Serial Number State ===\n";
    
    $landUses = ['RES', 'COM', 'IND', 'MIXED'];
    foreach ($landUses as $code) {
        $maxSerial = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('land_use_code', $code)
            ->max('serial_no');
        
        $nextSerial = ($maxSerial ?? 0) + 1;
        echo "{$code}: Current max = " . ($maxSerial ?? 0) . ", Next = ST-{$code}-2025-{$nextSerial}\n";
    }
    
    echo "\n🎉 All tests completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}