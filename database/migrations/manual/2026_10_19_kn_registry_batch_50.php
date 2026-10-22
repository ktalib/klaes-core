<?php
/**
 * KN registry batches: 100 files per batch -> 50, matching the other KANGIS prefixes
 * (kangis_grouping: registry_batch_no = ceil(number / 50)).
 *
 * - kn_grouping.registry_batch_no         = (number - 1) / 50 + 1
 * - file_indexings.registry_batch_no (KN) = same rule, only where it still holds the
 *   100-based value copied from kn_grouping.
 * sys_batch_no / [group] stay 100-based, as they did for the other prefixes.
 *
 * Old values are written to *_BEFORE.csv next to this file before any update.
 * Usage: php database/migrations/manual/2026_10_19_kn_registry_batch_50.php [--apply]
 */
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$db    = DB::connection('sqlsrv');
$dir   = __DIR__;

$knWhere = "registry_batch_no IS NULL OR registry_batch_no <> CAST(([number] - 1) / 50 + 1 AS NVARCHAR(20))";
$fiWhere = "file_number LIKE 'KN[0-9]%' AND TRY_CAST(SUBSTRING(file_number, 3, 20) AS INT) IS NOT NULL
            AND TRY_CAST(registry_batch_no AS INT) = (TRY_CAST(SUBSTRING(file_number, 3, 20) AS INT) - 1) / 100 + 1
            AND TRY_CAST(registry_batch_no AS INT) <> (TRY_CAST(SUBSTRING(file_number, 3, 20) AS INT) - 1) / 50 + 1";

$knCount = $db->selectOne("SELECT COUNT(*) n FROM kn_grouping WHERE $knWhere")->n;
$fiCount = $db->selectOne("SELECT COUNT(*) n FROM file_indexings WHERE $fiWhere")->n;
echo "kn_grouping rows to change:    $knCount\n";
echo "file_indexings rows to change: $fiCount\n";

if (!$apply) {
    echo "Dry run. Re-run with --apply to back up and update.\n";
    exit(0);
}

// 1. Back up the old values.
$fh = fopen("$dir/2026_10_19_kn_grouping_registry_batch_BEFORE.csv", 'w');
fputcsv($fh, ['id', 'kn_awaiting_fileno', 'number', 'registry_batch_no']);
$db->table('kn_grouping')->whereRaw($knWhere)->select('id', 'kn_awaiting_fileno', 'number', 'registry_batch_no')
    ->orderBy('id')->chunk(20000, function ($rows) use ($fh) {
        foreach ($rows as $r) fputcsv($fh, [$r->id, $r->kn_awaiting_fileno, $r->number, $r->registry_batch_no]);
    });
fclose($fh);

$fh = fopen("$dir/2026_10_19_file_indexings_kn_registry_batch_BEFORE.csv", 'w');
fputcsv($fh, ['id', 'file_number', 'registry_batch_no']);
foreach ($db->table('file_indexings')->whereRaw($fiWhere)->select('id', 'file_number', 'registry_batch_no')->orderBy('id')->get() as $r) {
    fputcsv($fh, [$r->id, $r->file_number, $r->registry_batch_no]);
}
fclose($fh);
echo "Backups written.\n";

// 2. Update kn_grouping in short id-range chunks so live reads are never blocked for long.
$maxId = (int) $db->selectOne('SELECT MAX(id) m FROM kn_grouping')->m;
$done  = 0;
for ($from = 1; $from <= $maxId; $from += 5000) {
    $to = $from + 4999;
    $done += $db->update(
        "UPDATE kn_grouping SET registry_batch_no = CAST(([number] - 1) / 50 + 1 AS NVARCHAR(20))
         WHERE id BETWEEN ? AND ? AND ($knWhere)",
        [$from, $to]
    );
}
echo "kn_grouping updated: $done\n";

// 3. file_indexings (small).
$fiDone = $db->update(
    "UPDATE file_indexings SET registry_batch_no = CAST((TRY_CAST(SUBSTRING(file_number, 3, 20) AS INT) - 1) / 50 + 1 AS NVARCHAR(20))
     WHERE $fiWhere"
);
echo "file_indexings updated: $fiDone\n";

// 4. Drop the print-label page's cached KN tally so the new batch counts show at once.
Cache::forget('kangis_printlabel.tally.kn_grouping.KN');
echo "Cache cleared.\n";
