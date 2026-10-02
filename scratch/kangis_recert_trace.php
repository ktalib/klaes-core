<?php
/**
 * Trace the KANGIS recertification row through the two stages that can drop it.
 *
 * READ-ONLY. Safe on production.
 *
 *   php scratch/kangis_recert_trace.php "KNML 1200"
 *
 * Stage 1  fetchRelatedRecertificationRows() — is the row BUILT at all?
 * Stage 2  dropMergedRecertRows()            — is it built and then FOLDED AWAY?
 *
 * Whichever stage loses it is the one to fix.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$typed = trim((string) ($argv[1] ?? 'KNML 1200'));
$conn  = DB::connection('sqlsrv');
$svc   = app(App\Services\LegalSearchService::class);

$inv = function (string $name, ...$args) use ($svc) {
    $m = new ReflectionMethod($svc, $name);
    $m->setAccessible(true);
    return $m->invoke($svc, ...$args);
};

$canon = $inv('resolveKangisCanonical', $conn, $typed) ?: $typed;
echo "\nTYPED    : {$typed}\nCANONICAL: {$canon}\n";
echo str_repeat('-', 74) . "\n";

// ---------------------------------------------------------- the register rows
echo "REGISTER ROWS (related_file_number)\n";
$reg = $conn->table('related_file_number')
    ->where('file_number', $typed)->orWhere('related_fileno', $typed)
    ->orWhere('file_number', $canon)->orWhere('related_fileno', $canon)
    ->get();
printf("  %d row(s)\n", count($reg));
foreach ($reg as $r) {
    $a = (array) $r;
    printf("    id=%-6s file_number=%-24s related_fileno=%-24s type=%s\n",
        $a['id'] ?? '-', $a['file_number'] ?? '-', $a['related_fileno'] ?? '-',
        $a['transaction_type'] ?? ($a['relationship_type'] ?? '-'));
}

// ---------------------------------------------------- stage 1: is it BUILT?
echo "\nSTAGE 1 — fetchRelatedRecertificationRows()\n";
$res = $svc->search(['query' => $typed]);
$all = $res['transactions'] ?? [];
printf("  search() returned %d transactions to feed it\n", count($all));

$m = new ReflectionMethod($svc, 'fetchRelatedRecertificationRows');
$m->setAccessible(true);
$argCount = $m->getNumberOfParameters();
printf("  this build's signature takes %d parameter(s)%s\n", $argCount,
    $argCount < 4 ? '  (older build: the typed number is NOT passed through)' : '');

$built = $argCount >= 4
    ? $m->invoke($svc, $conn, $canon, $all, $typed)
    : $m->invoke($svc, $conn, $canon, $all);

printf("  rows BUILT: %d\n", count($built));
foreach ($built as $b) {
    printf("    %-42s file_no=%-22s src=%s\n",
        $b['transaction_type'] ?? ($b['instrument_type'] ?? '-'),
        $b['file_no'] ?? ($b['fileno'] ?? '-'),
        $b['source_table'] ?? '-');
}
if (!count($built)) {
    echo "  => NOT BUILT. The link exists but the row is never created — the skip is\n";
    echo "     inside fetchRelatedRecertificationRows (endpoint choice / own-set test).\n";
}

// ------------------------------------------- stage 2: is it then FOLDED AWAY?
echo "\nSTAGE 2 — dropMergedRecertRows()\n";
if (!count($built)) {
    echo "  (nothing to fold — stage 1 produced no rows)\n";
} else {
    $kept = $inv('dropMergedRecertRows', $built);
    printf("  rows IN: %d   rows KEPT: %d\n", count($built), count($kept));

    if (count($kept) < count($built)) {
        echo "  => FOLDED AWAY here. The row is built, then dropped because the land file\n";
        echo "     is an -RC- file whose commissioning row already reads\n";
        echo "     \"File Commissioning & Recertification\".\n";
        foreach ($built as $b) {
            $type = $b['transaction_type'] ?? ($b['instrument_type'] ?? '');
            $own  = $b['file_no'] ?? ($b['fileno'] ?? '');
            printf("     dropped? %-3s  %-42s (own no: %s)\n",
                $inv('isRecertLandFile', $own) && strcasecmp($type, 'Land Recertification (File Commissioning)') === 0 ? 'YES' : 'no',
                $type, $own);
        }
    } else {
        echo "  => Survives the fold. If it still is not on screen, it is dropped later\n";
        echo "     (cross-property guard) or not rendered by the front end.\n";
    }
}

// ------------------------------------------------------------ what shipped
echo "\nFINAL — what search() returned\n";
$n = 0;
foreach ($all as $t) {
    $type = (string) ($t['transaction_type'] ?? '');
    if (stripos($type, 'recert') !== false || ($t['source_table'] ?? '') === 'Related Fileno') {
        $n++;
        printf("    %s\n", $type);
    }
}
printf("  recertification rows in the final result: %d\n", $n);
echo str_repeat('-', 74) . "\nNothing was modified.\n";
