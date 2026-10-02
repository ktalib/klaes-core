<?php
// insert_pic_data_to_temp.php
// Reads all SQL files from database/pic and creates insert statements for pic_temp table

$picDir = __DIR__ . '/../database/pic/';
$outputFile = __DIR__ . '/../database_scripts/insert_all_pic_data_to_temp.sql';

if (!is_dir($picDir)) {
    exit("PIC directory not found: $picDir\n");
}

$sqlFiles = glob($picDir . 'pic_inserts_*.sql');
sort($sqlFiles); // Process files in order

if (empty($sqlFiles)) {
    exit("No PIC SQL files found in $picDir\n");
}

$combinedSql = "-- Insert all PIC data into pic_temp table\n";
$combinedSql .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";

$totalRecords = 0;
foreach ($sqlFiles as $file) {
    echo "Processing: " . basename($file) . "\n";
    
    $content = file_get_contents($file);
    if (empty($content)) {
        echo "Warning: Empty file - " . basename($file) . "\n";
        continue;
    }
    
    // Replace INSERT INTO [pic] with INSERT INTO [pic_temp]
    $content = str_replace('INSERT INTO [pic]', 'INSERT INTO [pic_temp]', $content);
    
    // Count records in this file (count opening parentheses in VALUES section)
    preg_match_all('/\(\s*N?\'/', $content, $matches);
    $recordCount = count($matches[0]);
    $totalRecords += $recordCount;
    
    $combinedSql .= "-- File: " . basename($file) . " ($recordCount records)\n";
    $combinedSql .= $content . "\n";
    
    echo "  Records: $recordCount\n";
}

// Write combined SQL to output file
file_put_contents($outputFile, $combinedSql);

echo "\nSummary:\n";
echo "- Files processed: " . count($sqlFiles) . "\n";
echo "- Total records: $totalRecords\n";
echo "- Output file: $outputFile\n";
echo "\nTo execute the inserts, run this SQL file in your database.\n";
echo "Or use: php artisan db:seed --class=ExecutePicTempInserts\n";