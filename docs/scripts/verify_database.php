<?php
/**
 * Database Verification Script
 * Check recent submissions to mother_applications table
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

try {
    echo "🔍 DATABASE VERIFICATION SCRIPT\n";
    echo str_repeat("=", 50) . "\n";
    
    // Test database connection
    echo "📡 Testing database connection...\n";
    $connection = DB::connection('sqlsrv');
    $connection->getPdo();
    echo "✅ Database connection successful\n\n";
    
    // Get recent submissions (last 5)
    echo "📋 Recent submissions to mother_applications:\n";
    echo str_repeat("-", 50) . "\n";
    
    $recentApps = DB::connection('sqlsrv')
        ->table('mother_applications')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    if ($recentApps->count() > 0) {
        foreach ($recentApps as $app) {
            echo "🆔 ID: {$app->id}\n";
            echo "📄 File Number: {$app->np_fileno}\n";
            echo "🏢 Applicant Type: {$app->applicant_type}\n";
            echo "👤 Corporate Name: " . ($app->corporate_name ?: 'NULL') . "\n";
            echo "🔢 RC Number: " . ($app->rc_number ?: 'NULL') . "\n";
            echo "👨 First Name: " . ($app->first_name ?: 'NULL') . "\n";
            echo "👨 Last Name: " . ($app->surname ?: 'NULL') . "\n";
            echo "📧 Email: " . ($app->email ?: 'NULL') . "\n";
            echo "📞 Phone: " . ($app->phone_number ?: 'NULL') . "\n";
            echo "🏠 Address: " . (substr($app->address ?: 'NULL', 0, 50)) . "\n";
            echo "🏗️ Land Use: {$app->land_use}\n";
            echo "🕒 Created: {$app->created_at}\n";
            echo str_repeat("-", 50) . "\n";
        }
    } else {
        echo "❌ No recent applications found\n";
    }
    
    // Check field mapping
    echo "\n🔍 Field Analysis:\n";
    echo str_repeat("-", 50) . "\n";
    
    $columns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('mother_applications');
    
    $keyFields = [
        'np_fileno', 'fileno', 'land_use', 'applicant_type',
        'corporate_name', 'rc_number', 'first_name', 'surname',
        'email', 'phone_number', 'address', 'scheme_no'
    ];
    
    foreach ($keyFields as $field) {
        $exists = in_array($field, $columns) ? '✅' : '❌';
        echo "{$exists} {$field}\n";
    }
    
    echo "\n📊 Total columns in mother_applications: " . count($columns) . "\n";
    
    // Check for NULL values in recent submissions
    if ($recentApps->count() > 0) {
        echo "\n🔍 NULL Field Analysis (Latest Record):\n";
        echo str_repeat("-", 30) . "\n";
        
        $latest = $recentApps->first();
        $nullFields = [];
        
        foreach ($keyFields as $field) {
            if (property_exists($latest, $field)) {
                if (is_null($latest->$field) || $latest->$field === '') {
                    $nullFields[] = $field;
                }
            }
        }
        
        if (empty($nullFields)) {
            echo "✅ All key fields have values!\n";
        } else {
            echo "❌ NULL/Empty fields found:\n";
            foreach ($nullFields as $field) {
                echo "   - {$field}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "🔗 Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Database verification complete\n";
?>