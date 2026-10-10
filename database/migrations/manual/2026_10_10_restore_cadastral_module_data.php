<?php

/*
| Restore the Cadastral Module data dropped on 2026-10-10.
|
| Reloads the CSV exports in storage/app/cadastral_module_removed_2026-10-10/
| into the recreated tables, keeping the original ids (IDENTITY_INSERT):
| cadastral_settings (20), cadastral_area_fee_schedule (26),
| cadastral_transport_bands (3), cadastral_file_receipts (4).
| cadastral_officers and cadastral_shadow_files were never dropped, so they are skipped.
|
| Run AFTER the six cadastral module migrations have recreated the tables.
|   C:\xampp\php\php.exe database/migrations/manual/2026_10_10_restore_cadastral_module_data.php            (dry run: inserts, then rolls back)
|   C:\xampp\php\php.exe database/migrations/manual/2026_10_10_restore_cadastral_module_data.php --commit   (keeps it)
|
| Refuses a table that already has rows. One transaction; any error rolls back everything.
| An empty CSV field is loaded as NULL (fputcsv wrote NULLs as empty fields).
*/

require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$commit = in_array('--commit', $argv, true);
$dir = storage_path('app/cadastral_module_removed_2026-10-10');
$tables = ['cadastral_settings', 'cadastral_area_fee_schedule', 'cadastral_transport_bands', 'cadastral_file_receipts'];
$db = DB::connection('sqlsrv');

if ($db->getDatabaseName() !== 'klas') {
    exit("Refusing: sqlsrv database is {$db->getDatabaseName()}, expected klas.\n");
}

$db->beginTransaction();
try {
    foreach ($tables as $table) {
        if (! $db->getSchemaBuilder()->hasTable($table)) {
            throw new RuntimeException("$table does not exist. Run the cadastral module migrations first.");
        }
        if ($db->table($table)->count() > 0) {
            throw new RuntimeException("$table already has rows; not touching it.");
        }

        $fh = fopen("$dir/$table.csv", 'r');
        $header = fgetcsv($fh);
        $columns = $db->getSchemaBuilder()->getColumnListing($table);
        $missing = array_diff($header, $columns);
        if ($missing) {
            throw new RuntimeException("$table is missing columns: " . implode(', ', $missing));
        }

        $db->unprepared("SET IDENTITY_INSERT [dbo].[$table] ON");
        $n = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $values = array_map(fn ($v) => $v === '' ? null : $v, array_combine($header, $row));
            $db->table($table)->insert($values);
            $n++;
        }
        $db->unprepared("SET IDENTITY_INSERT [dbo].[$table] OFF");
        fclose($fh);

        echo str_pad($table, 32) . "$n rows\n";
    }

    if ($commit) {
        $db->commit();
        echo "Committed.\n";
    } else {
        $db->rollBack();
        echo "Dry run: rolled back. Re-run with --commit to keep it.\n";
    }
} catch (Throwable $e) {
    $db->rollBack();
    echo 'Rolled back: ' . $e->getMessage() . "\n";
    exit(1);
}
