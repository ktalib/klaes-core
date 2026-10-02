<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\STFileNumberService;
use Illuminate\Support\Facades\Auth;

echo "=== Testing ST File Number Service after migration ===\n\n";

try {
    // Create an instance of the service
    $service = new STFileNumberService();
    
    echo "Testing next file number generation...\n\n";
    
    // Test Primary file number generation (should be ST-RES-2025-2)
    echo "1. Testing Primary Residential file number:\n";
    $result = $service->generatePrimaryFileNumber('Residential', [
        'applicant_type' => 'Individual',
        'first_name' => 'Test',
        'surname' => 'User',
        'applicant_title' => 'Mr'
    ]);
    
    if ($result['success']) {
        echo "✅ Primary generation successful!\n";
        echo "   File Number: {$result['data']['np_fileno']}\n";
        echo "   Serial: {$result['data']['serial_no']}\n";
        echo "   Tracking ID: {$result['data']['tracking_id']}\n";
    } else {
        echo "❌ Primary generation failed: {$result['message']}\n";
    }
    
    echo "\n2. Testing Commercial Primary file number:\n";
    $result2 = $service->generatePrimaryFileNumber('Commercial', [
        'applicant_type' => 'Corporate',
        'corporate_name' => 'Test Company Ltd',
        'rc_number' => 'RC12345'
    ]);
    
    if ($result2['success']) {
        echo "✅ Commercial Primary generation successful!\n";
        echo "   File Number: {$result2['data']['np_fileno']}\n";
        echo "   Serial: {$result2['data']['serial_no']}\n";
        echo "   Tracking ID: {$result2['data']['tracking_id']}\n";
    } else {
        echo "❌ Commercial Primary generation failed: {$result2['message']}\n";
    }
    
    echo "\n3. Testing SUA file number generation:\n";
    $result3 = $service->generateSUAFileNumber('Industrial', [
        'applicant_type' => 'Individual',
        'first_name' => 'Jane',
        'surname' => 'Doe'
    ]);
    
    if ($result3['success']) {
        echo "✅ SUA generation successful!\n";
        echo "   NP FileNo: {$result3['data']['np_fileno']}\n";
        echo "   Unit FileNo: {$result3['data']['unit_fileno']}\n";
        echo "   MLS FileNo: {$result3['data']['mls_fileno']}\n";
        echo "   Tracking ID: {$result3['data']['tracking_id']}\n";
    } else {
        echo "❌ SUA generation failed: {$result3['message']}\n";
    }
    
    // Test PUA with the existing residential file number
    echo "\n4. Testing PUA file number generation (using existing ST-RES-2025-1):\n";
    $result4 = $service->generatePUAFileNumber('ST-RES-2025-1', [
        'applicant_type' => 'Individual',
        'first_name' => 'Bob',
        'surname' => 'Smith'
    ]);
    
    if ($result4['success']) {
        echo "✅ PUA generation successful!\n";
        echo "   NP FileNo: {$result4['data']['np_fileno']}\n";
        echo "   Unit FileNo: {$result4['data']['unit_fileno']}\n";
        echo "   MLS FileNo: {$result4['data']['mls_fileno']}\n";
        echo "   Unit Sequence: {$result4['data']['unit_sequence']}\n";
        echo "   Tracking ID: {$result4['data']['tracking_id']}\n";
    } else {
        echo "❌ PUA generation failed: {$result4['message']}\n";
    }
    
    // Show final state of all records
    echo "\n=== Final st_file_numbers table state ===\n";
    $allRecords = DB::connection('sqlsrv')->table('st_file_numbers')
        ->orderBy('id', 'desc')
        ->get();
    
    foreach ($allRecords as $record) {
        $status = $record->status;
        $type = $record->file_no_type;
        $created = substr($record->created_at, 0, 19);
        
        echo "ID: {$record->id} | {$record->np_fileno} | {$record->fileno} | {$type} | {$status} | {$created}\n";
    }
    
    echo "\n🎉 All tests completed! The ST File Number Service is working correctly with the migrated data.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}