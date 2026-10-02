<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class DirectFileNumberGenerator {
    
    private $connection;
    private $stats;
    
    // Define all the file formats (matching your JavaScript)
    private $fileFormats = [
        ['prefix' => 'RES', 'name' => 'RES', 'landuse' => 'RESIDENTIAL'],
        ['prefix' => 'COM', 'name' => 'COM', 'landuse' => 'COMMERCIAL'],
        ['prefix' => 'AG', 'name' => 'AG', 'landuse' => 'AGRICULTURE'],
        ['prefix' => 'RES-RC', 'name' => 'RES-RC', 'landuse' => 'RESIDENTIAL'],
        ['prefix' => 'COM-RC', 'name' => 'COM-RC', 'landuse' => 'COMMERCIAL'],
        ['prefix' => 'AG-RC', 'name' => 'AG-RC', 'landuse' => 'AGRICULTURE'],
        ['prefix' => 'CON-RES', 'name' => 'CON-RES', 'landuse' => 'RESIDENTIAL'],
        ['prefix' => 'CON-COM', 'name' => 'CON-COM', 'landuse' => 'COMMERCIAL'],
        ['prefix' => 'CON-AG', 'name' => 'CON-AG', 'landuse' => 'AGRICULTURE'],
        ['prefix' => 'CON-RES-RC', 'name' => 'CON-RES-RC', 'landuse' => 'RESIDENTIAL'],
        ['prefix' => 'CON-COM-RC', 'name' => 'CON-COM-RC', 'landuse' => 'COMMERCIAL'],
        ['prefix' => 'CON-AG-RC', 'name' => 'CON-AG-RC', 'landuse' => 'AGRICULTURE']
    ];
    
    public function __construct() {
        $this->connection = DB::connection('sqlsrv');
        $this->stats = [
            'total_generated' => 0,
            'total_inserted' => 0,
            'sheets_completed' => 0,
            'start_time' => time()
        ];
    }
    
    public function generateAndInsert() {
        echo "🚀 DIRECT FILE NUMBER GENERATION & DATABASE INSERTION\n";
        echo "=" . str_repeat("=", 60) . "\n";
        echo "Target: " . count($this->fileFormats) . " sheets × 225,000 records = 2,700,000 total records\n";
        echo "Years: 1981-2025 (45 years)\n";
        echo "Numbers per year: 5,000\n";
        echo "Database: SQL Server (klas.grouping)\n\n";
        
        $batchNo = 'DIRECT_GEN_' . date('Ymd_His');
        
        try {
            foreach ($this->fileFormats as $format) {
                $this->processFormat($format, $batchNo);
                $this->stats['sheets_completed']++;
                
                // Memory cleanup after each sheet
                gc_collect_cycles();
            }
            
            $this->printFinalReport();
            
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function processFormat($format, $batchNo) {
        echo "📋 Processing: {$format['name']} → {$format['landuse']}\n";
        
        $sheetStats = [
            'generated' => 0,
            'inserted' => 0,
            'start_time' => time()
        ];
        
        // Generate years array (1981-2025)
        $years = range(1981, 2025); // 45 years
        $numbersPerYear = 5000;
        
        $batch = [];
        $batchSize = 1000; // Insert in batches of 1000
        
        foreach ($years as $year) {
            for ($number = 1; $number <= $numbersPerYear; $number++) {
                $fileNo = "{$format['prefix']}-{$year}-{$number}";
                
                $record = [
                    'awaiting_fileno' => $fileNo,
                    'number' => (string)$number,
                    'year' => $year,
                    'landuse' => $format['landuse'],
                    'mls_fileno' => null,
                    'mapping' => 0,
                    'group' => null,
                    'batch_no' => $batchNo,
                    'mdc_batch_no' => null,
                    'sys_batch_no' => null,
                    'shelf_rack' => null,
                    'date' => null,
                    'created_by' => 'direct_generator',
                    'indexed_by' => null,
                    'date_index' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'updated_by' => 'direct_generator',
                    'deleted_at' => null,
                    'deleted_by' => null
                ];
                
                $batch[] = $record;
                $sheetStats['generated']++;
                $this->stats['total_generated']++;
                
                // Insert batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    $inserted = $this->insertBatch($batch);
                    $sheetStats['inserted'] += $inserted;
                    $this->stats['total_inserted'] += $inserted;
                    $batch = [];
                    
                    // Progress indicator
                    if ($sheetStats['generated'] % 25000 == 0) {
                        $progress = ($sheetStats['generated'] / 225000) * 100;
                        echo "  Progress: " . number_format($sheetStats['generated']) . " / 225,000 ({$progress:.1f}%)\n";
                    }
                }
            }
        }
        
        // Insert remaining records
        if (!empty($batch)) {
            $inserted = $this->insertBatch($batch);
            $sheetStats['inserted'] += $inserted;
            $this->stats['total_inserted'] += $inserted;
        }
        
        $duration = time() - $sheetStats['start_time'];
        echo "  ✅ {$format['name']}: " . number_format($sheetStats['inserted']) . " records inserted in {$duration}s\n\n";
    }
    
    private function insertBatch($records) {
        try {
            $this->connection->beginTransaction();
            
            $sql = "INSERT INTO grouping (
                awaiting_fileno, number, year, landuse, mls_fileno, mapping,
                [group], batch_no, mdc_batch_no, sys_batch_no, shelf_rack,
                date, created_by, indexed_by, date_index,
                created_at, updated_at, updated_by, deleted_at, deleted_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->connection->getPdo()->prepare($sql);
            
            foreach ($records as $record) {
                $stmt->execute([
                    $record['awaiting_fileno'],
                    $record['number'],
                    $record['year'],
                    $record['landuse'],
                    $record['mls_fileno'],
                    $record['mapping'],
                    $record['group'],
                    $record['batch_no'],
                    $record['mdc_batch_no'],
                    $record['sys_batch_no'],
                    $record['shelf_rack'],
                    $record['date'],
                    $record['created_by'],
                    $record['indexed_by'],
                    $record['date_index'],
                    $record['created_at'],
                    $record['updated_at'],
                    $record['updated_by'],
                    $record['deleted_at'],
                    $record['deleted_by']
                ]);
            }
            
            $this->connection->commit();
            return count($records);
            
        } catch (Exception $e) {
            $this->connection->rollBack();
            echo "❌ Batch insert failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function printFinalReport() {
        $totalDuration = time() - $this->stats['start_time'];
        $recordsPerSecond = $totalDuration > 0 ? $this->stats['total_inserted'] / $totalDuration : 0;
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🎉 DIRECT GENERATION COMPLETE!\n";
        echo str_repeat("=", 60) . "\n";
        echo "📊 FINAL STATISTICS:\n";
        echo "  Total Records Generated: " . number_format($this->stats['total_generated']) . "\n";
        echo "  Total Records Inserted:  " . number_format($this->stats['total_inserted']) . "\n";
        echo "  Sheets Completed:        " . $this->stats['sheets_completed'] . " / " . count($this->fileFormats) . "\n";
        echo "  Total Duration:          {$totalDuration} seconds\n";
        echo "  Processing Speed:        " . number_format($recordsPerSecond) . " records/second\n";
        echo "  Success Rate:            " . number_format(($this->stats['total_inserted'] / $this->stats['total_generated']) * 100, 2) . "%\n";
        
        echo "\n📋 SHEET BREAKDOWN:\n";
        foreach ($this->fileFormats as $format) {
            echo "  {$format['name']}: 225,000 records → {$format['landuse']}\n";
        }
        
        echo "\n🔍 VERIFICATION QUERIES:\n";
        echo "  Total count: SELECT COUNT(*) FROM grouping;\n";
        echo "  By land use: SELECT landuse, COUNT(*) FROM grouping GROUP BY landuse;\n";
        echo "  By prefix:   SELECT LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1) as prefix, COUNT(*) FROM grouping GROUP BY LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1);\n";
        
        echo "\n✅ Database ready with 2.7M file number records!\n";
    }
}

// Execute the generator
echo "Direct File Number Generator\n";
echo "This will generate and insert 2.7M records directly into the database\n";
echo "Continue? (Press Enter to continue, Ctrl+C to cancel)\n";
// readline(); // Uncomment if you want user confirmation

$generator = new DirectFileNumberGenerator();
$success = $generator->generateAndInsert();

if ($success) {
    echo "\n🎯 SUCCESS: All file numbers generated and inserted into database!\n";
} else {
    echo "\n❌ FAILED: Generation process encountered errors.\n";
}
?>