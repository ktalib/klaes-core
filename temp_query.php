<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('sqlsrv');
$tables = ['temp_fileno', 'file_history', 'pra', 'cofo', 'fh', 'deed_registrations', 'PropID_Master'];
$files = ['MLKN 2455','CON-AG-2014-35','CON-AG-2026-108','CON-COM-2026-430','CON-AG-2026-109','CON-COM-2026-431','CON-AG-2026-110'];

foreach ($tables as $table) {
    $cols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$table]);
    echo "TABLE $table\n";
    foreach ($cols as $c) {
        echo '- ' . $c->COLUMN_NAME . "\n";
    }
    echo "\n";
}

foreach ($files as $file) {
    echo "FILE: $file\n";
    foreach ($tables as $table) {
        $cols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$table]);
        $names = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);
        $candidateCols = [];
        foreach ($names as $name) {
            $lower = strtolower($name);
            if (strpos($lower, 'fileno') !== false || strpos($lower, 'file') !== false || strpos($lower, 'mls') !== false || strpos($lower, 'prop') !== false || strpos($lower, 'temp') !== false) {
                $candidateCols[] = $name;
            }
        }
        if (!$candidateCols) {
            continue;
        }
        $sql = "SELECT TOP 10 * FROM [$table] WHERE 1=0";
        foreach ($candidateCols as $col) {
            $sql .= " OR [$col] = ?";
        }
        try {
            $rows = $conn->select($sql, array_fill(0, count($candidateCols), $file));
            if ($rows) {
                echo " - $table\n";
                foreach ($rows as $row) {
                    echo '   ' . json_encode((array) $row, JSON_UNESCAPED_SLASHES) . "\n";
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    echo "\n";
}
