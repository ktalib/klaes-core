<?php
// CLI helper: convert Index Card CSV files into SQL Server insert statements for the `pic` table.
// Usage: php scripts/pic_csv_to_sql.php

$baseDir = realpath(__DIR__ . '/..');
$csvDir = $baseDir . '/database/csv/Index Card_All';
$outputSql = $baseDir . '/database/sql/pic_inserts.sql';
$reportPath = $baseDir . '/database/sql/pic_import_report.json';
$configPath = $baseDir . '/config/correct_fileno.json';

// Column mapping from CSV header to pic table columns.
$columnMap = [
    'MLSFileNo' => 'mlsFNo',
    'OldKNNo' => 'kangisFileNo',
    'transaction_type' => 'transaction_type',
    'serialNo' => 'serialNo',
    'pageNo' => 'pageNo',
    'volumeNo' => 'volumeNo',
    'regNo' => 'regNo',
    'period' => 'period',
    'period_unit' => 'period_unit',
    'Grantor' => 'Grantor',
    'Grantee' => 'Grantee',
    'Assignee' => 'Assignee',
    'property_description' => 'property_description',
    'location' => 'location',
    'Assignment Date' => 'transaction_date',
    'streetName' => 'streetName',
    'house_no' => 'house_no',
    'districtName' => 'districtName',
    'plot_no' => 'plot_no',
    'LGA' => 'lga',
    'layout' => 'layout',
    'source' => 'source',
    'tp_no' => 'tp_no',
    'lpkn_no' => 'lpkn_no',
    'approved_plan_no' => 'approved_plan_no',
    'plot_size' => 'plot_size',
    'date_recommended' => 'date_recommended',
    'date_approved' => 'date_approved',
    'lease_begins' => 'lease_begins',
    'lease_expires' => 'lease_expires',
    'Surrender Date' => 'surrender_date',
    'Revoked date' => 'revoked_date',
    'metric_sheet' => 'metric_sheet',
    'regranted from' => 'regranted_from',
    'Date Expired' => 'date_expired',
    'Remarks' => 'remarks',
    'CreatedBy' => 'created_by',
    'DateCreated' => 'created_at',
];

function readJsonConfig(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function clean(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function normalizeFileNumber(?string $value, array $yearRange, array &$warnings, string $file, int $line): ?string
{
    $original = clean($value);
    if ($original === null) {
        return null;
    }

    $normalized = strtoupper($original);
    $normalized = str_replace(['/', '_', '.'], '-', $normalized);
    $normalized = preg_replace('/\s+/', '', $normalized);
    $normalized = str_replace('C0N', 'CON', $normalized);
    $normalized = str_replace('C0M', 'COM', $normalized);
    $normalized = str_replace('RRES', 'RES', $normalized);
    $normalized = preg_replace('/\bCM-/', 'COM-', $normalized);
    $normalized = preg_replace('/\bCN-/', 'CON-', $normalized);
    $normalized = str_replace('AV-RC', 'AG-RC', $normalized);
    $normalized = str_replace('AV-', 'AG-', $normalized);
    $normalized = str_replace('OPCON-', 'CON-', $normalized);

    $parts = explode('-', $normalized);
    $yearStart = $yearRange['start'] ?? 1981;
    $yearEnd = $yearRange['end'] ?? 2025;

    foreach ($parts as $i => $token) {
        if (preg_match('/^\d{2}$/', $token)) {
            $year = (int) $token;
            if ($year <= ($yearEnd % 100)) {
                $parts[$i] = (string) (2000 + $year);
            } elseif ($year >= ($yearStart % 100)) {
                $parts[$i] = (string) (1900 + $year);
            } else {
                $warnings['ambiguous_year'][] = [
                    'file' => $file,
                    'line' => $line,
                    'original' => $original,
                    'token' => $token,
                ];
            }
        } elseif (preg_match('/^\d{1}$/', $token)) {
            // Single-digit year; assume 2000-series but flag it.
            $parts[$i] = '200' . $token;
            $warnings['ambiguous_year'][] = [
                'file' => $file,
                'line' => $line,
                'original' => $original,
                'token' => $token,
            ];
        }
    }

    // Remove leading zeros from the final numeric token (sequence number)
    $lastIndex = count($parts) - 1;
    if ($lastIndex >= 0 && preg_match('/^\d+$/', $parts[$lastIndex])) {
        $parts[$lastIndex] = ltrim($parts[$lastIndex], '0');
        if ($parts[$lastIndex] === '') {
            $parts[$lastIndex] = '0';
        }
    }

    $normalized = implode('-', $parts);

    if ($normalized !== $original) {
        $warnings['normalized'][] = [
            'file' => $file,
            'line' => $line,
            'original' => $original,
            'normalized' => $normalized,
        ];
    }

    return $normalized;
}

function escapeSqlValue(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "N'" . str_replace("'", "''", $value) . "'";
}

function deriveLandUseFromFileNumber(?string $fileNumber): ?string
{
    if ($fileNumber === null) {
        return null;
    }

    $tokens = preg_split('/[^A-Z]/', strtoupper($fileNumber));

    $map = [
        'RES' => 'RESIDENTIAL',
        'COM' => 'COMMERCIAL',
        'IND' => 'INDUSTRIAL',
        'AG' => 'AGRICULTURAL',
        'MIX' => 'MIXED USE',
    ];

    foreach ($tokens as $token) {
        if ($token !== '' && isset($map[$token])) {
            return $map[$token];
        }
    }

    return null;
}

// Load config for year span guidance.
$config = readJsonConfig($configPath);
$yearSpan = $config['file_number_system']['year_span'] ?? '1981-2025';
[$startYear, $endYear] = array_map('intval', explode('-', $yearSpan));
$yearRange = ['start' => $startYear, 'end' => $endYear];

// Collect CSV files.
$csvFiles = glob($csvDir . '/*.csv');
sort($csvFiles);

if (empty($csvFiles)) {
    fwrite(STDERR, "No CSV files found in {$csvDir}\n");
    exit(1);
}

$rows = [];
$warnings = [
    'missing_file_number' => [],
    'normalized' => [],
    'ambiguous_year' => [],
];

foreach ($csvFiles as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "Cannot read {$file}\n");
        continue;
    }

    if (($handle = fopen($file, 'r')) === false) {
        fwrite(STDERR, "Failed to open {$file}\n");
        continue;
    }

    $lineNumber = 0;
    $header = [];

    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        if ($lineNumber === 1) {
            $header = array_map('trim', $data);
            continue;
        }

        $hasContent = array_filter($data, fn($value) => clean($value) !== null);
        if (empty($hasContent)) {
            continue;
        }

        $record = [];
        foreach ($header as $index => $columnName) {
            if ($columnName === '' || !array_key_exists($index, $data)) {
                continue;
            }
            $record[$columnName] = clean($data[$index]);
        }

        $normalizedMls = normalizeFileNumber($record['MLSFileNo'] ?? null, $yearRange, $warnings, basename($file), $lineNumber);
        if ($normalizedMls === null) {
            $warnings['missing_file_number'][] = [
                'file' => basename($file),
                'line' => $lineNumber,
            ];
        }

        $row = [];
        foreach ($columnMap as $csvCol => $dbCol) {
            $value = $record[$csvCol] ?? null;
            if ($csvCol === 'MLSFileNo') {
                $value = $normalizedMls;
            }
            $row[$dbCol] = $value;
        }

        // Derive optional land_use from file number when present.
        $row['land_use'] = deriveLandUseFromFileNumber($normalizedMls ?? ($record['MLSFileNo'] ?? null));

        $rows[] = $row;
    }

    fclose($handle);
}

if (empty($rows)) {
    fwrite(STDERR, "No data rows were parsed; aborting.\n");
    exit(1);
}

// Normalize column order.
$dbColumns = array_values(array_unique(array_merge(array_values($columnMap), ['land_use'])));

$sqlLines = [];
$sqlLines[] = '-- Auto-generated PIC inserts from Index Card CSVs';
$sqlLines[] = '-- Run against SQL Server database (table: pic)';
$sqlLines[] = 'SET NOCOUNT ON;';
$sqlLines[] = '';

$chunkSize = 200;
$chunks = array_chunk($rows, $chunkSize);

foreach ($chunks as $chunk) {
    $sqlLines[] = 'INSERT INTO [pic] (' . implode(', ', array_map(fn($col) => '[' . $col . ']', $dbColumns)) . ') VALUES';

    $valueLines = [];
    foreach ($chunk as $row) {
        $values = [];
        foreach ($dbColumns as $col) {
            $values[] = escapeSqlValue($row[$col] ?? null);
        }
        $valueLines[] = '    (' . implode(', ', $values) . ')';
    }

    $lastIndex = count($valueLines) - 1;
    foreach ($valueLines as $i => $line) {
        $terminator = $i === $lastIndex ? ';' : ',';
        $sqlLines[] = $line . $terminator;
    }

    $sqlLines[] = '';
}

file_put_contents($outputSql, implode(PHP_EOL, $sqlLines));

$report = [
    'generated_at' => date('c'),
    'csv_directory' => $csvDir,
    'output_sql' => $outputSql,
    'total_rows' => count($rows),
    'total_files' => count($csvFiles),
    'column_order' => $dbColumns,
    'warnings' => [
        'missing_file_number_count' => count($warnings['missing_file_number']),
        'normalized_count' => count($warnings['normalized']),
        'ambiguous_year_count' => count($warnings['ambiguous_year']),
        'samples' => [
            'missing_file_number' => array_slice($warnings['missing_file_number'], 0, 25),
            'normalized' => array_slice($warnings['normalized'], 0, 25),
            'ambiguous_year' => array_slice($warnings['ambiguous_year'], 0, 25),
        ],
    ],
];

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Generated SQL: {$outputSql}\n");
fwrite(STDOUT, "Report: {$reportPath}\n");
