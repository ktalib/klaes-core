<?php
/**
 * Delete Duplicate TOT Entry
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Deleting duplicate TOT for RES-2023-809...\n";

$deleted = DB::connection('sqlsrv')
    ->table('pra')
    ->where('mlsFNo', 'RES-2023-809')
    ->where('instrument_type', 'Transfer of Title (OP)')
    ->where('temp_fileno', 'TEMP-35326')
    ->delete();

echo "Deleted $deleted record(s).\n";

// Relink the remaining TOT with the correct OP prop_id
echo "Relinking remaining TOT with correct OP prop_id...\n";

$updated = DB::connection('sqlsrv')
    ->table('pra')
    ->where('mlsFNo', 'RES-2023-809')
    ->where('instrument_type', 'Transfer of Title (OP)')
    ->update([
        'prop_id' => DB::raw("(SELECT TOP 1 prop_id FROM pra WHERE mlsFNo = 'RES-2023-809' AND instrument_type = 'Occupancy Permit (OP)')")
    ]);

echo "Updated $updated record(s).\n";
echo "Cleanup complete!\n";
