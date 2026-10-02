<?php
// Simple database test for ST file numbers
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct Database ST File Numbers Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .highlight { background-color: #fffacd; }
        .error { color: red; padding: 10px; background: #ffebee; border-radius: 4px; }
        .success { color: green; padding: 10px; background: #e8f5e8; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Direct Database ST File Numbers Test</h1>
    
    <?php
    try {
        // Load Laravel
        require_once __DIR__ . '/vendor/autoload.php';
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        echo "<div class='success'>✓ Laravel loaded successfully</div>";
        
        // Test database connection
        $pdo = DB::connection('sqlsrv')->getPdo();
        echo "<div class='success'>✓ SQL Server connection established</div>";
        
        echo "<h2>1. All records with ST file numbers (st_file_no NOT NULL)</h2>";
        $stRecords = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereNotNull('st_file_no')
            ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted', 'FileName')
            ->get();
            
        echo "<p><strong>Found " . count($stRecords) . " records with ST file numbers</strong></p>";
        
        if (count($stRecords) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>MLS File No</th><th>ST File No</th><th>KANGIS</th><th>New KANGIS</th><th>SOURCE</th><th>Deleted</th><th>File Name</th></tr>";
            
            foreach ($stRecords as $record) {
                $isDeleted = $record->is_deleted ? 'YES' : 'NO';
                $rowClass = ($record->SOURCE == 'ST Dept') ? 'highlight' : '';
                
                echo "<tr class='$rowClass'>";
                echo "<td>" . htmlspecialchars($record->id) . "</td>";
                echo "<td>" . htmlspecialchars($record->mlsfNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->st_file_no ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->kangisFileNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->NewKANGISFileNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->SOURCE ?: 'NULL') . "</td>";
                echo "<td>" . $isDeleted . "</td>";
                echo "<td>" . htmlspecialchars($record->FileName ?: 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<h2>2. Records with SOURCE = 'ST Dept'</h2>";
        $stDeptRecords = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('SOURCE', 'ST Dept')
            ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted', 'FileName')
            ->get();
            
        echo "<p><strong>Found " . count($stDeptRecords) . " records with SOURCE = 'ST Dept'</strong></p>";
        
        if (count($stDeptRecords) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>MLS File No</th><th>ST File No</th><th>KANGIS</th><th>New KANGIS</th><th>SOURCE</th><th>Deleted</th><th>File Name</th></tr>";
            
            foreach ($stDeptRecords as $record) {
                $isDeleted = $record->is_deleted ? 'YES' : 'NO';
                
                echo "<tr>";
                echo "<td>" . htmlspecialchars($record->id) . "</td>";
                echo "<td>" . htmlspecialchars($record->mlsfNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->st_file_no ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->kangisFileNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->NewKANGISFileNo ?: 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($record->SOURCE ?: 'NULL') . "</td>";
                echo "<td>" . $isDeleted . "</td>";
                echo "<td>" . htmlspecialchars($record->FileName ?: 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<h2>3. Search for specific ST file numbers</h2>";
        $searchNumbers = [
            'ST-RES-2025-1',
            'ST-COM-2025-1-001',
            'ST-COM-2025-2-001', 
            'ST-COM-2025-3-001',
            'ST-COM-2025-4',
            'ST-MIXED-2025-1'
        ];
        
        foreach ($searchNumbers as $stNumber) {
            echo "<h3>Searching for: $stNumber</h3>";
            
            $found = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function($query) use ($stNumber) {
                    $query->where('st_file_no', 'LIKE', "%{$stNumber}%")
                          ->orWhere('mlsfNo', 'LIKE', "%{$stNumber}%")
                          ->orWhere('kangisFileNo', 'LIKE', "%{$stNumber}%")
                          ->orWhere('NewKANGISFileNo', 'LIKE', "%{$stNumber}%");
                })
                ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted')
                ->get();
            
            if ($found->count() > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Found In Field</th><th>Value</th><th>SOURCE</th><th>Deleted</th></tr>";
                
                foreach ($found as $record) {
                    $foundIn = [];
                    if (stripos($record->st_file_no, $stNumber) !== false) $foundIn[] = "st_file_no: " . $record->st_file_no;
                    if (stripos($record->mlsfNo, $stNumber) !== false) $foundIn[] = "mlsfNo: " . $record->mlsfNo;
                    if (stripos($record->kangisFileNo, $stNumber) !== false) $foundIn[] = "kangisFileNo: " . $record->kangisFileNo;
                    if (stripos($record->NewKANGISFileNo, $stNumber) !== false) $foundIn[] = "NewKANGISFileNo: " . $record->NewKANGISFileNo;
                    
                    $isDeleted = $record->is_deleted ? 'YES' : 'NO';
                    
                    echo "<tr>";
                    echo "<td>" . $record->id . "</td>";
                    echo "<td>" . implode('<br>', $foundIn) . "</td>";
                    echo "<td>" . htmlspecialchars($stNumber) . "</td>";
                    echo "<td>" . htmlspecialchars($record->SOURCE ?: 'NULL') . "</td>";
                    echo "<td>" . $isDeleted . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p><em>Not found in database</em></p>";
            }
        }
        
        echo "<h2>4. Test Controller Query</h2>";
        echo "<p>Testing the same query that the controller uses...</p>";
        
        $controllerQuery = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->select([
                'fileNumber.id',
                'fileNumber.mlsfNo',
                'fileNumber.kangisFileNo', 
                'fileNumber.NewKANGISFileNo',
                'fileNumber.st_file_no',
                'fileNumber.SOURCE',
                'fileNumber.is_deleted'
            ])
            ->where(function($q) {
                // Include records with MLS file numbers OR ST file numbers
                $q->whereNotNull('fileNumber.mlsfNo')
                  ->orWhereNotNull('fileNumber.st_file_no')
                  ->orWhereNotNull('fileNumber.kangisFileNo')
                  ->orWhereNotNull('fileNumber.NewKANGISFileNo');
            })
            ->where(function($q) {
                $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
            })
            ->get();
            
        echo "<p><strong>Controller query returns " . count($controllerQuery) . " records</strong></p>";
        
        $stCount = 0;
        $stDeptCount = 0;
        foreach ($controllerQuery as $record) {
            if (!empty($record->st_file_no)) $stCount++;
            if ($record->SOURCE == 'ST Dept') $stDeptCount++;
        }
        
        echo "<p>Records with ST file numbers: $stCount</p>";
        echo "<p>Records from ST Dept: $stDeptCount</p>";
        
    } catch (Exception $e) {
        echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='error'>File: " . htmlspecialchars($e->getFile()) . "</div>";
        echo "<div class='error'>Line: " . htmlspecialchars($e->getLine()) . "</div>";
    }
    ?>
    
    <p><strong>Test completed at:</strong> <?= date('Y-m-d H:i:s') ?></p>
</body>
</html>