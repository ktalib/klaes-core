<?php
/**
 * Verification Script for Deed of Gift Implementation
 * Run this script to verify all components are properly implemented
 */

echo "=== DEED OF GIFT IMPLEMENTATION VERIFICATION ===\n\n";

// Check if files exist
$files_to_check = [
    'resources/views/instruments/create.blade.php' => 'Main form file',
    'resources/views/instruments/create/js.blade.php' => 'JavaScript logic file',
    'app/Http/Controllers/InstrumentController.php' => 'Backend controller',
    'database/deed_of_gift_columns.sql' => 'Database schema script',
    'DEED_OF_GIFT_IMPLEMENTATION.md' => 'Implementation documentation'
];

echo "1. CHECKING FILES:\n";
foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "✓ {$description}: {$file}\n";
    } else {
        echo "✗ MISSING {$description}: {$file}\n";
    }
}

echo "\n2. CHECKING FORM IMPLEMENTATION:\n";

// Check if create.blade.php contains deed-of-gift button
$create_file = 'resources/views/instruments/create.blade.php';
if (file_exists($create_file)) {
    $content = file_get_contents($create_file);
    if (strpos($content, 'data-type="deed-of-gift"') !== false) {
        echo "✓ Deed of Gift button found in form\n";
    } else {
        echo "✗ Deed of Gift button NOT found in form\n";
    }
    
    if (strpos($content, 'Deed of Gift') !== false) {
        echo "✓ Deed of Gift text found in form\n";
    } else {
        echo "✗ Deed of Gift text NOT found in form\n";
    }
}

echo "\n3. CHECKING JAVASCRIPT IMPLEMENTATION:\n";

// Check if js.blade.php contains deed-of-gift logic
$js_file = 'resources/views/instruments/create/js.blade.php';
if (file_exists($js_file)) {
    $content = file_get_contents($js_file);
    if (strpos($content, "'deed-of-gift'") !== false) {
        echo "✓ Deed of Gift JavaScript definition found\n";
    } else {
        echo "✗ Deed of Gift JavaScript definition NOT found\n";
    }
    
    if (strpos($content, 'Donor') !== false && strpos($content, 'Donee') !== false) {
        echo "✓ Donor/Donee party labels found\n";
    } else {
        echo "✗ Donor/Donee party labels NOT found\n";
    }
    
    if (strpos($content, 'Section A - Instrument Metadata') !== false) {
        echo "✓ Section A fields found\n";
    } else {
        echo "✗ Section A fields NOT found\n";
    }
    
    if (strpos($content, 'donorPhone') !== false) {
        echo "✓ Donor details fields found\n";
    } else {
        echo "✗ Donor details fields NOT found\n";
    }
    
    if (strpos($content, 'doneePhone') !== false) {
        echo "✓ Donee details fields found\n";
    } else {
        echo "✗ Donee details fields NOT found\n";
    }
}

echo "\n4. CHECKING CONTROLLER IMPLEMENTATION:\n";

// Check if controller handles deed of gift fields
$controller_file = 'app/Http/Controllers/InstrumentController.php';
if (file_exists($controller_file)) {
    $content = file_get_contents($controller_file);
    if (strpos($content, "'Deed of Gift'") !== false) {
        echo "✓ Deed of Gift condition found in controller\n";
    } else {
        echo "✗ Deed of Gift condition NOT found in controller\n";
    }
    
    if (strpos($content, 'donorPhone') !== false) {
        echo "✓ Donor fields handling found in controller\n";
    } else {
        echo "✗ Donor fields handling NOT found in controller\n";
    }
    
    if (strpos($content, 'doneePhone') !== false) {
        echo "✓ Donee fields handling found in controller\n";
    } else {
        echo "✗ Donee fields handling NOT found in controller\n";
    }
}

echo "\n5. CHECKING DATABASE SCRIPT:\n";

$sql_file = 'database/deed_of_gift_columns.sql';
if (file_exists($sql_file)) {
    $content = file_get_contents($sql_file);
    if (strpos($content, '[klas].[dbo].[instrument_registration]') !== false) {
        echo "✓ Correct table reference found in SQL script\n";
    } else {
        echo "✗ Correct table reference NOT found in SQL script\n";
    }
    
    if (strpos($content, 'donorPhone') !== false) {
        echo "✓ Donor columns found in SQL script\n";
    } else {
        echo "✗ Donor columns NOT found in SQL script\n";
    }
    
    if (strpos($content, 'doneePhone') !== false) {
        echo "✓ Donee columns found in SQL script\n";
    } else {
        echo "✗ Donee columns NOT found in SQL script\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
echo "\nNEXT STEPS:\n";
echo "1. Run the database script: database/deed_of_gift_columns.sql\n";
echo "2. Test the form by navigating to Instruments → Create\n";
echo "3. Click on the 'Deed of Gift' button (emerald colored)\n";
echo "4. Fill out the form and submit to test functionality\n";
echo "5. Check the database to verify data is saved correctly\n";
echo "\nFor detailed information, see: DEED_OF_GIFT_IMPLEMENTATION.md\n";
?>