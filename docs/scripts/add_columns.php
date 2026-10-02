<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Adding tp_no and location columns to file_indexings table...\n";
    
    // Add tp_no column if it doesn't exist
    try {
        DB::connection('sqlsrv')->statement("
            IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'tp_no')
            BEGIN
                ALTER TABLE file_indexings ADD tp_no NVARCHAR(255) NULL
                PRINT 'Added tp_no column'
            END
            ELSE
            BEGIN
                PRINT 'tp_no column already exists'
            END
        ");
        echo "✓ tp_no column handled\n";
    } catch (Exception $e) {
        echo "Error adding tp_no: " . $e->getMessage() . "\n";
    }
    
    // Add location column if it doesn't exist
    try {
        DB::connection('sqlsrv')->statement("
            IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'location')
            BEGIN
                ALTER TABLE file_indexings ADD location NVARCHAR(255) NULL
                PRINT 'Added location column'
            END
            ELSE
            BEGIN
                PRINT 'location column already exists'
            END
        ");
        echo "✓ location column handled\n";
    } catch (Exception $e) {
        echo "Error adding location: " . $e->getMessage() . "\n";
    }
    
    echo "\nDone! The columns should now be available.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
