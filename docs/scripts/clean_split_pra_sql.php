<?php
// Clean and split PRA SQL insert file by 1,000 records per file, normalizing transaction_date.
// Usage: php scripts/clean_split_pra_sql.php

$input = __DIR__ . '/../database/sql/pra_inserts.sql';
$outputDir = __DIR__ . '/../database/pra';
$baseName = 'pra_inserts_cleaned';

if (!file_exists($input)) die("Input SQL file not found\n");
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

function normalizeDate($date) {
    if (!$date || strtolower($date) === 'null') return 'NULL';
    $date = strtoupper(trim($date));
    $date = preg_replace('/\b(ST|ND|RD|TH)\b/', '', $date); // Remove ordinal suffixes
    $date = preg_replace('/\s+/', ' ', $date); // Collapse spaces
    $date = str_replace([',', '.'], '', $date);
    $months = [
        'JANUAARY'=>'JANUARY','JANUARY'=>'JANUARY','JAN'=>'JANUARY',
        'FEBRAURY'=>'FEBRUARY','FEBUARY'=>'FEBRUARY','FEBRUARY'=>'FEBRUARY','FEB'=>'FEBRUARY',
        'MARCH'=>'MARCH','MAR'=>'MARCH',
        'APRIL'=>'APRIL','APR'=>'APRIL',
        'MAY'=>'MAY',
        'JUNE'=>'JUNE','JUN'=>'JUNE',
        'JULY'=>'JULY','JUL'=>'JULY',
        'AUGUST'=>'AUGUST','AUG'=>'AUGUST',
        'SEPTEMBER'=>'SEPTEMBER','SEPT'=>'SEPTEMBER','SEP'=>'SEPTEMBER',
        'OCTOBER'=>'OCTOBER','OCT'=>'OCTOBER',
        'NOVEMBER'=>'NOVEMBER','NOV'=>'NOVEMBER',
        'DECEMBER'=>'DECEMBER','DEC'=>'DECEMBER',
    ];
    // Fix month typos
    foreach ($months as $wrong=>$right) {
        $date = preg_replace("/\b$wrong\b/", $right, $date);
    }
    // Try to parse
    if (preg_match('/(\d{1,2}) ([A-Z]+) (\d{2,4})/', $date, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = $m[2];
        $y = strlen($m[3]) === 2 ? ('19'.$m[3]) : $m[3];
        $monthsNum = [
            'JANUARY'=>1,'FEBRUARY'=>2,'MARCH'=>3,'APRIL'=>4,'MAY'=>5,'JUNE'=>6,'JULY'=>7,'AUGUST'=>8,'SEPTEMBER'=>9,'OCTOBER'=>10,'NOVEMBER'=>11,'DECEMBER'=>12
        ];
        $mNum = isset($monthsNum[$month]) ? str_pad($monthsNum[$month],2,'0',STR_PAD_LEFT) : '01';
        return "'$y-$mNum-$d'";
    }
    if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{2,4})/', $date, $m)) {
        $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
        $mNum = str_pad($m[2],2,'0',STR_PAD_LEFT);
        $y = strlen($m[3]) === 2 ? ('19'.$m[3]) : $m[3];
        return "'$y-$mNum-$d'";
    }
    if (preg_match('/(\d{1,2}) ([A-Z]+) (\d{2})/', $date, $m)) {
        $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
        $month = $m[2];
        $y = '19'.$m[3];
        $monthsNum = [
            'JANUARY'=>1,'FEBRUARY'=>2,'MARCH'=>3,'APRIL'=>4,'MAY'=>5,'JUNE'=>6,'JULY'=>7,'AUGUST'=>8,'SEPTEMBER'=>9,'OCTOBER'=>10,'NOVEMBER'=>11,'DECEMBER'=>12
        ];
        $mNum = isset($monthsNum[$month]) ? str_pad($monthsNum[$month],2,'0',STR_PAD_LEFT) : '01';
        return "'$y-$mNum-$d'";
    }
    // If year only
    if (preg_match('/(\d{4})/', $date, $m)) {
        return "'$m[1]-01-01'";
    }
    return 'NULL';
}

$lines = file($input, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
$cleaned = [];
foreach ($lines as $line) {
    if (!preg_match('/^INSERT INTO pra/i', $line)) continue;
    // Find transaction_date value (4th value in VALUES)
    if (preg_match('/VALUES \((.+)\);$/', $line, $m)) {
        $parts = str_getcsv($m[1], ",", "'", "\\");
        if (count($parts) >= 4) {
            $dateRaw = trim($parts[3], " '");
            $parts[3] = normalizeDate($dateRaw);
            $newVals = implode(', ', $parts);
            $line = preg_replace('/VALUES \(.+\);$/', "VALUES ($newVals);", $line);
        }
    }
    $cleaned[] = $line;
}

// Write cleaned file
file_put_contents("$outputDir/{$baseName}.sql", implode("\n", $cleaned));

// Split into 1k per file
$chunks = array_chunk($cleaned, 1000);
foreach ($chunks as $i => $chunk) {
    $fname = sprintf("%s/%s_part%02d.sql", $outputDir, $baseName, $i+1);
    file_put_contents($fname, implode("\n", $chunk));
}
echo "Done. Cleaned and split into ".count($chunks)." files.\n";
