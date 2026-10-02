<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Migrating Shared Areas to shared_utilities Table ===\n";

try {
    // First, let's check what data we have
    echo "\n1. Checking existing data in mother_applications...\n";
    $motherApps = DB::connection('sqlsrv')
        ->table('mother_applications')
        ->whereNotNull('shared_areas')
        ->where('shared_areas', '!=', '')
        ->select('id', 'shared_areas')
        ->get();

    echo "Found " . $motherApps->count() . " mother applications with shared_areas data\n";

    echo "\n2. Checking existing data in subapplications...\n";
    $subApps = DB::connection('sqlsrv')
        ->table('subapplications')
        ->whereNotNull('shared_areas')
        ->where('shared_areas', '!=', '')
        ->select('id', 'main_application_id', 'shared_areas')
        ->get();

    echo "Found " . $subApps->count() . " sub applications with shared_areas data\n";

    // Function to parse shared areas data
    function parseSharedAreas($sharedAreasRaw) {
        if (empty($sharedAreasRaw)) {
            return [];
        }

        echo "  Raw data: {$sharedAreasRaw}\n";
        
        $utilities = [];
        
        // Try to decode as JSON first
        $sharedAreasData = is_string($sharedAreasRaw) ? json_decode($sharedAreasRaw, true) : $sharedAreasRaw;
        
        // If JSON decode failed, try to handle malformed JSON like {"ACCSESS"}
        if ($sharedAreasData === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "  JSON decode failed, trying to parse malformed JSON\n";
            
            // Handle malformed JSON like {"ACCSESS"} by extracting the content
            if (preg_match('/\{([^}]+)\}/', $sharedAreasRaw, $matches)) {
                $content = trim($matches[1], '"');
                if (!empty($content)) {
                    $utilities = [$content];
                }
            }
        } else {
            echo "  Decoded data: " . print_r($sharedAreasData, true) . "\n";
            
            if (is_array($sharedAreasData) && !empty($sharedAreasData)) {
                // Check if it's an indexed array (like ["hallways", "gardens"])
                $keys = array_keys($sharedAreasData);
                if ($keys === range(0, count($sharedAreasData) - 1)) {
                    // Indexed array - use values
                    $utilities = array_values($sharedAreasData);
                } else {
                    // Associative array format like {"ACCSESS": null} - use keys
                    $utilities = array_keys($sharedAreasData);
                }
            } elseif (is_object($sharedAreasData)) {
                // Handle object format
                $utilities = array_keys((array)$sharedAreasData);
            }
        }

        echo "  Parsed utilities: " . print_r($utilities, true) . "\n";
        return array_filter($utilities); // Remove empty values
    }

    echo "\n3. Processing mother_applications data...\n";
    $processedMother = 0;
    foreach ($motherApps as $app) {
        $utilities = parseSharedAreas($app->shared_areas);
        echo "App ID {$app->id}: " . json_encode($utilities) . "\n";

        foreach ($utilities as $index => $utility) {
            DB::connection('sqlsrv')->table('shared_utilities')->insert([
                'application_id' => $app->id,
                'sub_application_id' => null,
                'utility_type' => trim($utility),
                'dimension' => null, // Will be filled during JSI
                'count' => null,
                'order' => $index + 1,
                'created_by' => 1, // Default system user
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        $processedMother++;
    }

    echo "\n4. Processing subapplications data...\n";
    $processedSub = 0;
    foreach ($subApps as $app) {
        $utilities = parseSharedAreas($app->shared_areas);
        echo "Sub App ID {$app->id} (Parent: {$app->main_application_id}): " . json_encode($utilities) . "\n";

        foreach ($utilities as $index => $utility) {
            DB::connection('sqlsrv')->table('shared_utilities')->insert([
                'application_id' => $app->main_application_id,
                'sub_application_id' => $app->id,
                'utility_type' => trim($utility),
                'dimension' => null, // Will be filled during JSI
                'count' => null,
                'order' => $index + 1,
                'created_by' => 1, // Default system user
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        $processedSub++;
    }

    echo "\n=== Migration Summary ===\n";
    echo "Processed {$processedMother} mother applications\n";
    echo "Processed {$processedSub} sub applications\n";

    // Verify the migration
    $totalSharedUtilities = DB::connection('sqlsrv')->table('shared_utilities')->count();
    echo "Total records in shared_utilities table: {$totalSharedUtilities}\n";

    echo "\n=== Sample Data in shared_utilities Table ===\n";
    $sampleData = DB::connection('sqlsrv')
        ->table('shared_utilities')
        ->select('application_id', 'sub_application_id', 'utility_type', 'order')
        ->limit(10)
        ->get();

    foreach ($sampleData as $record) {
        $subId = $record->sub_application_id ? "Sub:{$record->sub_application_id}" : "Primary";
        echo "App:{$record->application_id} | {$subId} | {$record->utility_type} | Order:{$record->order}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}