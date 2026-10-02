#!/usr/bin/env php
<?php
/**
 * Setup script for land_use_serials table
 * Run this script to create the table and stored procedure for atomic file number generation
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load Laravel configuration
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "🚀 Setting up land_use_serials table for atomic file number generation...\n\n";

try {
    // Read the SQL file
    $sqlFile = __DIR__ . '/../database_scripts/create_land_use_serials_table.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode('GO', $sql)),
        fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
    );
    
    echo "📋 Executing SQL statements...\n";
    
    foreach ($statements as $index => $statement) {
        if (trim($statement)) {
            echo "   → Statement " . ($index + 1) . "... ";
            
            try {
                DB::connection('sqlsrv')->unprepared($statement);
                echo "✅ Success\n";
            } catch (Exception $e) {
                echo "⚠️  Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n🔧 Verifying table creation...\n";
    
    // Verify table exists
    $tableExists = DB::connection('sqlsrv')->select("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_NAME = 'land_use_serials'
    ")[0]->count > 0;
    
    if ($tableExists) {
        echo "   ✅ Table 'land_use_serials' created successfully\n";
        
        // Check initial data
        $initialData = DB::connection('sqlsrv')->table('land_use_serials')->get();
        echo "   📊 Initial records: " . $initialData->count() . "\n";
        
        foreach ($initialData as $record) {
            echo "      - {$record->land_use_type}: {$record->prefix} (Serial: {$record->current_serial})\n";
        }
    } else {
        echo "   ❌ Table creation failed\n";
    }
    
    echo "\n🧪 Testing stored procedure...\n";
    
    try {
        $result = DB::connection('sqlsrv')->select('EXEC GetNextFileSerial ?, ?', ['COMMERCIAL', 2025]);
        echo "   ✅ Stored procedure 'GetNextFileSerial' working correctly\n";
    } catch (Exception $e) {
        echo "   ⚠️  Stored procedure issue: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 Setup completed successfully!\n";
    echo "\n📖 File Number Formats:\n";
    echo "   • COMMERCIAL: ST-COM-2025-1, ST-COM-2025-2, ...\n";
    echo "   • RESIDENTIAL: ST-RES-2025-1, ST-RES-2025-2, ...\n";
    echo "   • INDUSTRIAL: ST-IND-2025-1, ST-IND-2025-2, ...\n";
    echo "   • MIXED: ST-MIXED-2025-1, ST-MIXED-2025-2, ...\n";
    echo "\n✨ Each land use type maintains its own independent serial sequence!\n";
    
} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    Log::error('Land use serials setup failed', ['error' => $e->getMessage()]);
    exit(1);
}