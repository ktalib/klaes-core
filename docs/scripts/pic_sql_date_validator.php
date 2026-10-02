<?php
// pic_sql_date_validator.php
// Validates and corrects date fields in SQL insert statements, then splits into 1,000-record files.

$inputFile = __DIR__ . '/../database/sql/pic_inserts.sql';
$outputDir = __DIR__ . '/../database/pic/';

if (!file_exists($inputFile)) {
    exit("Input SQL file not found!\n");
}
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$sql = file_get_contents($inputFile);

// Split into individual INSERT blocks (each block may contain many VALUES)
$insertBlocks = preg_split('/\bINSERT INTO /i', $sql, -1, PREG_SPLIT_NO_EMPTY);
$allRecords = [];
$header = '';
foreach ($insertBlocks as $block) {
    $block = "INSERT INTO " . $block;
    if (preg_match('/^INSERT INTO \[pic\] .*?VALUES/i', $block, $m)) {
        $header = $m[0];
        $values = trim(substr($block, strlen($header)));
        // Remove trailing semicolon if present
        $values = rtrim($values, ";\n ");
        // Split on '),\n' but keep the closing parenthesis
        $records = preg_split("/\),\s*\n/", $values);
        foreach ($records as $i => $rec) {
            $rec = trim($rec);
            if (substr($rec, -1) !== ')') $rec .= ')';
            $allRecords[] = $rec;
        }
    }
}

function fix_date($str) {
    // Accepts N'...date...' or NULL
    if (preg_match("/^N'(.*?)'$/", $str, $m)) {
        $date = $m[1];
        // Acceptable formats: d-m-Y, d/m/Y, d.m.Y, d-m-y, d/m/y, d.m.y
        $date = str_replace(['/', '.'], '-', $date);
        if (preg_match('/^\d{1,2}-\d{1,2}-\d{2,4}$/', $date)) {
            $parts = explode('-', $date);
            if (strlen($parts[2]) === 2) {
                // Assume 19xx for years > 30, else 20xx
                $year = (int)$parts[2];
                $parts[2] = $year > 30 ? (1900 + $year) : (2000 + $year);
            }
            // Validate date
            if (checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) {
                return 
                    
                    "N'" . sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]) . "'";
            }
        }
        // If not valid, return NULL
        return 'NULL';
    }
    return $str;
}

// Find all date fields in each record and fix them
$correctedRecords = [];
foreach ($allRecords as $rec) {
    // Split by comma, but not inside parentheses
    $fields = preg_split('/,(?![^\(]*\))/', $rec);
    // Date fields are usually at fixed positions (find all N'...date...')
    foreach ($fields as $i => $f) {
        if (preg_match("/^N'\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4}'$/", trim($f))) {
            $fields[$i] = fix_date(trim($f));
        }
    }
    $correctedRecords[] = implode(', ', $fields);
}

// Split into 1,000-record files
$total = count($correctedRecords);
$numFiles = ceil($total / 1000);
for ($i = 0; $i < $numFiles; $i++) {
    $start = $i * 1000;
    $end = min($start + 1000, $total);
    $out = $header . "\n";
    $out .= implode(",\n", array_slice($correctedRecords, $start, $end - $start));
    $out .= ";\n";
    $fname = $outputDir . 'pic_inserts_' . ($i + 1) . '.sql';
    file_put_contents($fname, $out);
}

echo "Done. Split into $numFiles files in $outputDir\n";
