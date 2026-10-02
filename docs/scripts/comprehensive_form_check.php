<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== COMPREHENSIVE PRIMARY FORM SUBMISSION CHECK ===\n\n";

// 1. Check mother_applications table structure
echo "1. MOTHER_APPLICATIONS TABLE CHECK\n";
echo str_repeat("=", 50) . "\n";

try {
    $columns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_applications' 
        ORDER BY ORDINAL_POSITION
    ");
    
    $requiredFields = [
        'np_fileno', 'fileno', 'land_use', 'applicant_type',
        'first_name', 'surname', 'corporate_name', 'rc_number',
        'address_house_no', 'address_street_name', 'address_lga', 'address_state',
        'scheme_no', 'property_street_name', 'property_lga', 'property_state',
        'units_count', 'blocks_count', 'sections_count', 'NoOfUnits', 'NoOfBlocks', 'NoOfSections',
        'phone_number', 'email', 'selected_file_data', 'tracking_id', 'primary_file_id'
    ];
    
    $foundFields = [];
    foreach ($columns as $col) {
        $foundFields[] = $col->COLUMN_NAME;
    }
    
    echo "✓ Total columns: " . count($foundFields) . "\n";
    
    $missingFields = array_diff($requiredFields, $foundFields);
    if (empty($missingFields)) {
        echo "✓ All required fields exist\n";
    } else {
        echo "✗ Missing fields: " . implode(', ', $missingFields) . "\n";
    }
    
    // Check for field name mismatches
    $fieldMappings = [
        'units_count' => 'NoOfUnits',
        'blocks_count' => 'NoOfBlocks',
        'sections_count' => 'NoOfSections'
    ];
    
    echo "\nField Mapping Check:\n";
    foreach ($fieldMappings as $formField => $dbField) {
        $hasFormField = in_array($formField, $foundFields);
        $hasDbField = in_array($dbField, $foundFields);
        echo "  $formField => $dbField: ";
        if ($hasDbField) {
            echo "✓ DB field exists\n";
        } elseif ($hasFormField) {
            echo "⚠ Only form field exists\n";
        } else {
            echo "✗ Neither exists\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// 2. Check buyer_list table
echo "\n2. BUYER_LIST TABLE CHECK\n";
echo str_repeat("=", 50) . "\n";

try {
    $buyerColumns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'buyer_list' 
        ORDER BY ORDINAL_POSITION
    ");
    
    $buyerFields = [];
    foreach ($buyerColumns as $col) {
        $buyerFields[] = $col->COLUMN_NAME;
        echo "  {$col->COLUMN_NAME} ({$col->DATA_TYPE})\n";
    }
    
    $requiredBuyerFields = ['application_id', 'buyer_name', 'unit_no', 'land_use', 'buyer_title'];
    $missingBuyerFields = array_diff($requiredBuyerFields, $buyerFields);
    
    if (empty($missingBuyerFields)) {
        echo "✓ All required buyer fields exist\n";
    } else {
        echo "✗ Missing buyer fields: " . implode(', ', $missingBuyerFields) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// 3. Check file_indexings table
echo "\n3. FILE_INDEXINGS TABLE CHECK\n";
echo str_repeat("=", 50) . "\n";

try {
    $indexingColumns = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'file_indexings' 
        ORDER BY ORDINAL_POSITION
    ");
    
    $indexingFields = [];
    foreach ($indexingColumns as $col) {
        $indexingFields[] = $col->COLUMN_NAME;
    }
    
    echo "✓ Total columns: " . count($indexingFields) . "\n";
    
    $requiredIndexingFields = ['main_application_id', 'file_number', 'land_use_type', 'tracking_id'];
    $missingIndexingFields = array_diff($requiredIndexingFields, $indexingFields);
    
    if (empty($missingIndexingFields)) {
        echo "✓ All required indexing fields exist\n";
    } else {
        echo "✗ Missing indexing fields: " . implode(', ', $missingIndexingFields) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// 4. Test recent submission
echo "\n4. RECENT SUBMISSION CHECK\n";
echo str_repeat("=", 50) . "\n";

try {
    $recentApp = DB::connection('sqlsrv')
        ->table('mother_applications')
        ->orderBy('id', 'desc')
        ->first();
    
    if ($recentApp) {
        echo "Most Recent Application (ID: {$recentApp->id}):\n";
        echo "  NP File No: " . ($recentApp->np_fileno ?? 'NULL') . "\n";
        echo "  File No: " . ($recentApp->fileno ?? 'NULL') . "\n";
        echo "  Land Use: " . ($recentApp->land_use ?? 'NULL') . "\n";
        echo "  Applicant Type: " . ($recentApp->applicant_type ?? 'NULL') . "\n";
        echo "  First Name: " . ($recentApp->first_name ?? 'NULL') . "\n";
        echo "  Surname: " . ($recentApp->surname ?? 'NULL') . "\n";
        echo "  Corporate Name: " . ($recentApp->corporate_name ?? 'NULL') . "\n";
        echo "  Scheme No: " . ($recentApp->scheme_no ?? 'NULL') . "\n";
        echo "  Property Street: " . ($recentApp->property_street_name ?? 'NULL') . "\n";
        echo "  Property LGA: " . ($recentApp->property_lga ?? 'NULL') . "\n";
        echo "  Units Count: " . ($recentApp->units_count ?? 'NULL') . "\n";
        echo "  NoOfUnits: " . ($recentApp->NoOfUnits ?? 'NULL') . "\n";
        echo "  Tracking ID: " . ($recentApp->tracking_id ?? 'NULL') . "\n";
        echo "  Created At: " . ($recentApp->created_at ?? 'NULL') . "\n";
        
        // Check for buyers
        $buyerCount = DB::connection('sqlsrv')
            ->table('buyer_list')
            ->where('application_id', $recentApp->id)
            ->count();
        
        echo "\n  Associated Buyers: $buyerCount\n";
        
        if ($buyerCount > 0) {
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->where('application_id', $recentApp->id)
                ->get();
            
            foreach ($buyers as $buyer) {
                echo "    - {$buyer->buyer_name} (Unit: {$buyer->unit_no}, Land Use: " . ($buyer->land_use ?? 'NULL') . ")\n";
            }
        }
        
        // Check for file indexing
        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('main_application_id', $recentApp->id)
            ->first();
        
        if ($indexing) {
            echo "\n  File Indexing: ✓ EXISTS\n";
            echo "    File Number: " . ($indexing->file_number ?? 'NULL') . "\n";
            echo "    Tracking ID: " . ($indexing->tracking_id ?? 'NULL') . "\n";
        } else {
            echo "\n  File Indexing: ✗ NOT FOUND\n";
        }
        
    } else {
        echo "✗ No applications found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// 5. Check controller field mapping
echo "\n5. CONTROLLER FIELD MAPPING CHECK\n";
echo str_repeat("=", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/PrimaryApplicationController.php';
if (file_exists($controllerPath)) {
    $controllerContent = file_get_contents($controllerPath);
    
    // Check if units_count is mapped to NoOfUnits
    if (strpos($controllerContent, "'NoOfUnits' => \$validated['units_count']") !== false) {
        echo "✓ units_count => NoOfUnits mapping exists\n";
    } else {
        echo "✗ units_count => NoOfUnits mapping NOT FOUND\n";
    }
    
    if (strpos($controllerContent, "'NoOfBlocks' => \$validated['blocks_count']") !== false) {
        echo "✓ blocks_count => NoOfBlocks mapping exists\n";
    } else {
        echo "✗ blocks_count => NoOfBlocks mapping NOT FOUND\n";
    }
    
    if (strpos($controllerContent, "'NoOfSections' => \$validated['sections_count']") !== false) {
        echo "✓ sections_count => NoOfSections mapping exists\n";
    } else {
        echo "✗ sections_count => NoOfSections mapping NOT FOUND\n";
    }
    
    // Check if tracking_id is being saved
    if (strpos($controllerContent, "tracking_id") !== false) {
        echo "✓ tracking_id field is referenced\n";
    } else {
        echo "✗ tracking_id field NOT referenced\n";
    }
    
} else {
    echo "✗ Controller file not found\n";
}

// 6. Summary and Recommendations
echo "\n6. SUMMARY AND RECOMMENDATIONS\n";
echo str_repeat("=", 50) . "\n";

echo "\nCritical Issues to Fix:\n";
echo "1. Ensure all hidden fields in form are properly populated by JavaScript\n";
echo "2. Verify tracking_id is being captured from ST API selection\n";
echo "3. Confirm buyer records are being submitted with land_use field\n";
echo "4. Check that file_indexings entry is created after application submission\n";
echo "5. Verify address fields are being captured correctly\n";

echo "\nNext Steps:\n";
echo "1. Test form submission with browser console open\n";
echo "2. Check Laravel logs for any validation errors\n";
echo "3. Verify JavaScript is populating all hidden fields\n";
echo "4. Ensure SectionalTitleHelper::insertBuyers is being called\n";

echo "\n=== CHECK COMPLETE ===\n";
