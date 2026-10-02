#!/usr/bin/env php
<?php
/**
 * Direct migration execution script for enhance_activity_logs_tables migration
 * This bypasses the class duplication issue by executing the migration directly
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

// Set database connection
\Illuminate\Support\Facades\DB::setDefaultConnection('sqlsrv');

$migration_file = __DIR__.'/database/migrations/2025_11_10_000001_enhance_activity_logs_tables.php';

if (!file_exists($migration_file)) {
    echo "❌ Migration file not found: {$migration_file}\n";
    exit(1);
}

try {
    // Create a PDO connection to SQL Server
    $connection = \Illuminate\Support\Facades\DB::connection('sqlsrv');
    
    echo "✅ Connected to SQL Server\n";
    
    // Get the migration batch number
    $batches = \Illuminate\Support\Facades\DB::connection('sqlsrv')
        ->table('migrations')
        ->max('batch') ?? 0;
    
    $batch = $batches + 1;
    echo "📊 Current batch: {$batch}\n";
    
    // Load and execute the migration
    $class = require $migration_file;
    
    // Check if it's an anonymous class
    if ($class instanceof \Illuminate\Database\Migrations\Migration) {
        echo "🚀 Running migration...\n";
        $class->up();
        
        // Record the migration
        \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('migrations')
            ->insert([
                'migration' => '2025_11_10_000001_enhance_activity_logs_tables',
                'batch' => $batch,
            ]);
        
        echo "✅ Migration executed successfully!\n";
        
        // Verify the columns were created
        $columns = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->getSchemaBuilder()
            ->getColumnListing('user_activity_logs');
        
        $new_columns = ['duration_minutes', 'status', 'last_seen_at', 'related_file_number', 'test_control', 'indexed_at'];
        $missing = array_diff($new_columns, $columns);
        
        if (empty($missing)) {
            echo "✅ All new columns verified in user_activity_logs table\n";
        } else {
            echo "⚠️  Missing columns: " . implode(', ', $missing) . "\n";
        }
        
    } else {
        echo "❌ Migration class is not valid\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
