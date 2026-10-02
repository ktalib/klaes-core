<?php
// CLI helper: convert PRA CSV files into SQL Server insert statements for the `pra` table.
// Usage: php scripts/pra_csv_to_sql.php

$baseDir = realpath(__DIR__ . '/..');
$csvDir = $baseDir . '/database/csv/PRA All';
$outputSql = $baseDir . '/database/sql/pra_inserts.sql';
$configPath = $baseDir . '/config/correct_fileno.json';

// Column mapping from PRA CSV header to pra table columns.
$columnMap = [
    'mlsFNo' => 'mlsFNo',
    'Related FileNo' => 'related_fileno',
    'transaction_type' => 'transaction_type',
    'transaction_date' => 'transaction_date',
    'SerialNo' => 'serialNo',
    'pageNo' => 'pageNo',
    'volumeNo' => 'volumeNo',
    'Grantor/Assignor' => 'grantor',
    'Grantee/Assignee' => 'grantee',
    'streetName' => 'streetName',
    'house_no' => 'house_no',
    'districtName' => 'districtName',
    'plot_no' => 'plot_no',
    'LGA' => 'lga',
    'plot_size' => 'plot_size',
    'CreatedBy' => 'created_by',
    'DateCreated' => 'created_at',
];

function readJsonConfig(string $path): array
{
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    return json_decode($json, true) ?: [];
}

function correctMlsFileNo($mlsFNo, $config)
{
    // Add more sophisticated correction logic as needed using $config
    if (!$mlsFNo) return $mlsFNo;
    $mlsFNo = trim($mlsFNo);
    // Example: Remove spaces, unify dashes, uppercase
    $mlsFNo = strtoupper(str_replace([' ', '.'], ['',''], $mlsFNo));
    // TODO: Use $config for more advanced corrections if needed
    return $mlsFNo;
}

function csvToSqlInsert($csvPath, $columnMap, $config)
{
    $sql = [];
    if (($handle = fopen($csvPath, 'r')) !== false) {
        $header = fgetcsv($handle);
        if (!$header) return '';
        $header = array_map('trim', $header);
        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            foreach ($columnMap as $csvCol => $dbCol) {
                $idx = array_search($csvCol, $header);
                $val = $idx !== false ? $row[$idx] : null;
                if ($dbCol === 'mlsFNo') {
                    $val = correctMlsFileNo($val, $config);
                }
                $data[$dbCol] = $val !== null ? addslashes($val) : null;
            }
            $columns = implode(', ', array_keys($data));
            $values = implode(', ', array_map(function($v) {
                return $v === null || $v === '' ? 'NULL' : "'{$v}'";
            }, $data));
            $sql[] = "INSERT INTO pra ($columns) VALUES ($values);";
        }
        fclose($handle);
    }
    return implode("\n", $sql);
}

$config = readJsonConfig($configPath);
$sqlOut = [];
foreach (glob($csvDir . '/*.csv') as $csvFile) {
    echo "Processing $csvFile\n";
    $sqlOut[] = csvToSqlInsert($csvFile, $columnMap, $config);
}
file_put_contents($outputSql, implode("\n", $sqlOut));
echo "Done. SQL written to $outputSql\n";
