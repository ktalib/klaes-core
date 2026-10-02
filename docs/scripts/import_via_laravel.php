<?php
/**
 * Laravel-based CSV Import Script for Grouping Table
 * 
 * This script uses Laravel's Eloquent ORM to import CSV data
 * into the grouping table with proper error handling.
 */

// Bootstrap Laravel
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Grouping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CSVImporter
{
    private $landUseMapping = [
        'AG' => 'AGRICULTURE',
        'COM' => 'COMMERCIAL',
        'CON' => 'CONSTRUCTION', 
        'RES' => 'RESIDENTIAL',
        'IND' => 'INDUSTRIAL',
        'MIX' => 'MIXED',
        'AG-RC' => 'AGRICULTURE',
        'COM-RC' => 'COMMERCIAL',
        'CON-COM' => 'CONSTRUCTION_COMMERCIAL',
        'CON-RES' => 'CONSTRUCTION_RESIDENTIAL',
        'RES-RC' => 'RESIDENTIAL'
    ];

    private $stats = [
        'files_processed' => 0,
        'total_records' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    public function getLandUseFromFileNo($fileNo)
    {
        if (empty($fileNo)) {
            return 'OTHER';
        }

        $fileNo = trim(str_replace('"', '', $fileNo));
        
        // Try compound prefixes first
        foreach ($this->landUseMapping as $prefix => $landUse) {
            if (stripos($fileNo, $prefix . '-') === 0) {
                return $landUse;
            }
        }

        // Fallback to simple prefix
        $parts = explode('-', $fileNo);
        if (!empty($parts)) {
            $prefix = strtoupper($parts[0]);
            return $this->landUseMapping[$prefix] ?? 'OTHER';
        }

        return 'OTHER';
    }

    public function processCSVFile($filePath)
    {
        $filename = basename($filePath);
        echo "📁 Processing: {$filename}\n";

        if (!file_exists($filePath)) {
            echo "   ✗ File not found: {$filePath}\n";
            return false;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            echo "   ✗ Cannot open file: {$filePath}\n";
            return false;
        }

        // Skip header row
        fgetcsv($handle);

        $batchSize = 500;
        $batch = [];
        $lineNumber = 1;
        $fileInserted = 0;
        $fileSkipped = 0;
        $fileErrors = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            try {
                if (count($data) < 3) {
                    echo "   ⚠️  Skipping line {$lineNumber}: insufficient columns\n";
                    $fileErrors++;
                    continue;
                }

                $year = (int) $data[0];
                $number = (int) $data[1];
                $fileNo = trim(str_replace('"', '', $data[2]));
                $landUse = $this->getLandUseFromFileNo($fileNo);

                // Check if record already exists
                if (Grouping::where('awaiting_fileno', $fileNo)->exists()) {
                    $fileSkipped++;
                    continue;
                }

                $batch[] = [
                    'awaiting_fileno' => $fileNo,
                    'mls_fileno' => null,
                    'mapping' => 0,
                    'group' => null,
                    'batch_no' => null,
                    'mdc_batch_no' => null,
                    'sys_batch_no' => null,
                    'shelf_rack' => null,
                    'date' => null,
                    'created_by' => 'CSV Import',
                    'indexed_by' => null,
                    'date_index' => null,
                    'year' => $year,
                    'landuse' => $landUse,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $inserted = $this->insertBatch($batch);
                    $fileInserted += $inserted;
                    $batch = [];
                    echo "   ✓ Processed batch: {$inserted} records inserted\n";
                }

            } catch (Exception $e) {
                echo "   ✗ Error on line {$lineNumber}: " . $e->getMessage() . "\n";
                $fileErrors++;
            }
        }

        // Process remaining records in batch
        if (!empty($batch)) {
            $inserted = $this->insertBatch($batch);
            $fileInserted += $inserted;
            echo "   ✓ Final batch: {$inserted} records inserted\n";
        }

        fclose($handle);

        echo "   📊 File Summary - Inserted: {$fileInserted}, Skipped: {$fileSkipped}, Errors: {$fileErrors}\n";

        $this->stats['inserted'] += $fileInserted;
        $this->stats['skipped'] += $fileSkipped;
        $this->stats['errors'] += $fileErrors;
        $this->stats['files_processed']++;

        return true;
    }

    private function insertBatch($batch)
    {
        try {
            DB::connection('sqlsrv')->table('grouping')->insert($batch);
            return count($batch);
        } catch (Exception $e) {
            echo "   ✗ Batch insert error: " . $e->getMessage() . "\n";
            
            // Try inserting one by one as fallback
            $inserted = 0;
            foreach ($batch as $record) {
                try {
                    DB::connection('sqlsrv')->table('grouping')->insert($record);
                    $inserted++;
                } catch (Exception $e2) {
                    echo "   ✗ Individual insert error for {$record['awaiting_fileno']}: " . $e2->getMessage() . "\n";
                }
            }
            return $inserted;
        }
    }

    public function importAllFiles()
    {
        echo "🚀 Starting CSV Import to Grouping Table\n";
        echo str_repeat("=", 50) . "\n";

        $importDir = public_path('import');
        $csvFiles = glob($importDir . '/*.csv');

        if (empty($csvFiles)) {
            echo "✗ No CSV files found in {$importDir}\n";
            return;
        }

        echo "📂 Found " . count($csvFiles) . " CSV files:\n";
        foreach ($csvFiles as $file) {
            echo "   - " . basename($file) . "\n";
        }
        echo "\n";

        // Process each file
        foreach ($csvFiles as $csvFile) {
            $this->processCSVFile($csvFile);
            echo "\n";
        }

        // Final summary
        echo str_repeat("=", 50) . "\n";
        echo "📊 FINAL SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Files Processed: {$this->stats['files_processed']}\n";
        echo "✅ Total Records Inserted: " . number_format($this->stats['inserted']) . "\n";
        echo "⏭️  Total Records Skipped: " . number_format($this->stats['skipped']) . "\n";
        echo "❌ Total Errors: " . number_format($this->stats['errors']) . "\n";

        // Show land use statistics
        $this->showLandUseStats();

        echo "\n🎉 Import completed!\n";
    }

    private function showLandUseStats()
    {
        echo "\n📈 Records by Land Use:\n";
        
        $stats = DB::connection('sqlsrv')
            ->table('grouping')
            ->select('landuse', DB::raw('COUNT(*) as count'))
            ->where('created_by', 'CSV Import')
            ->groupBy('landuse')
            ->orderBy('count', 'desc')
            ->get();

        foreach ($stats as $stat) {
            echo "   {$stat->landuse}: " . number_format($stat->count) . "\n";
        }
    }
}

// Run the import
try {
    $importer = new CSVImporter();
    $importer->importAllFiles();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}