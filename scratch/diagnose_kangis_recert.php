<?php
/**
 * Why is the KANGIS recertification row not showing for a searched file?
 *
 * READ-ONLY. Safe to run on production. Writes nothing, changes nothing.
 *
 *   php scratch/diagnose_kangis_recert.php "KNML 1200"
 *
 * Walks the same path search() takes and reports, at each gate, whether the
 * recertification link is still reachable — so the step that drops it is named
 * rather than guessed at.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$typed = trim((string) ($argv[1] ?? 'KNML 1200'));
$conn  = DB::connection('sqlsrv');
$svc   = app(App\Services\LegalSearchService::class);

$call = function (string $method, ...$args) use ($svc) {
    $m = new ReflectionMethod($svc, $method);
    $m->setAccessible(true);
    return $m->invoke($svc, ...$args);
};

$line = fn () => print(str_repeat('-', 78) . "\n");

echo "\nDIAGNOSING: {$typed}\n";
$line();

// ---------------------------------------------------------------- 1. canonicalisation
$canonical = null;
try {
    $canonical = $call('resolveKangisCanonical', $conn, $typed);
} catch (\Throwable $e) {
    echo "  resolveKangisCanonical threw: " . $e->getMessage() . "\n";
}
$effective = $canonical ?: $typed;

echo "1. CANONICALISATION\n";
printf("   typed              : %s\n", $typed);
printf("   resolves to        : %s\n", $canonical ?: '(not rewritten)');
printf("   search runs against: %s\n", $effective);
if ($canonical && strcasecmp($canonical, $typed) !== 0) {
    echo "   NOTE: the typed number is rewritten. Anything keyed on the TYPED number\n";
    echo "         must be matched explicitly or it is invisible from here on.\n";
}
echo "\n";

// ---------------------------------------------------------------- 2. the link table
echo "2. related_file_number ROWS\n";
if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
    echo "   TABLE MISSING — recertification rows cannot be produced at all.\n\n";
} else {
    $cols = Schema::connection('sqlsrv')->getColumnListing('related_file_number');
    $numberCols = array_values(array_filter($cols, fn ($c) =>
        stripos($c, 'file_number') !== false || stripos($c, 'fileno') !== false));

    $q = $conn->table('related_file_number');
    $q->where(function ($w) use ($numberCols, $typed, $effective) {
        foreach ($numberCols as $col) {
            $w->orWhere($col, $typed)->orWhere($col, $effective);
        }
    });
    $rows = $q->limit(20)->get();

    printf("   number columns    : %s\n", implode(', ', $numberCols));
    printf("   rows found        : %d\n", count($rows));
    foreach ($rows as $r) {
        $a = (array) $r;
        $bits = [];
        foreach ($numberCols as $col) {
            if (!empty($a[$col])) $bits[] = $col . '=' . $a[$col];
        }
        printf("     id=%-6s %-60s type=%s\n",
            $a['id'] ?? '-', implode(' ', $bits), $a['relationship_type'] ?? ($a['type'] ?? '-'));
    }
    if (!count($rows)) {
        echo "   => No link row references either number. The recertification row has no\n";
        echo "      source; this is a DATA gap, not a filtering one.\n";
    }
    echo "\n";
}

// ---------------------------------------------------------------- 3. prop_id picture
echo "3. PROP_ID\n";
foreach (array_unique([$typed, $effective]) as $fn) {
    $master = $conn->table('PropID_Master')
        ->where('primary_file_number', $fn)->orWhere('mlsFNo', $fn)
        ->orWhere('kangisFileNo', $fn)->orWhere('NewKANGISFileno', $fn)
        ->get(['prop_id', 'primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno']);
    $fi = $conn->table('file_indexings')->where('file_number', $fn)
        ->get(['id', 'prop_id', 'registry', 'related_fileno']);

    printf("   %s\n", $fn);
    printf("     PropID_Master rows : %d\n", count($master));
    foreach ($master as $r) {
        printf("       pid=%-9s primary=%-22s mls=%-22s kangis=%s\n",
            $r->prop_id, $r->primary_file_number, $r->mlsFNo ?: '-', $r->kangisFileNo ?: '-');
    }
    printf("     file_indexings rows: %d\n", count($fi));
    foreach ($fi as $r) {
        printf("       id=%-7s prop_id=%-9s registry=%-10s related_fileno=%s\n",
            $r->id, $r->prop_id ?: '-', $r->registry ?: '-',
            $r->related_fileno ? substr((string) $r->related_fileno, 0, 60) : '-');
    }
}
echo "\n";

// ---------------------------------------------------------------- 4. the guard
echo "4. FOREIGN-PROP_ID GUARD (added 2026-08-26)\n";
$variants = $call('fileNumberVariants', $effective);
$fi = $conn->table('file_indexings')
    ->whereIn('file_number', $variants)->whereNull('deleted_at')->first(['prop_id']);
if (!$fi || !$fi->prop_id) {
    echo "   no indexing prop_id for this file — the guard cannot be involved.\n";
} else {
    $foreign = $call('isForeignIndexingPropId', $conn, $variants, (string) $fi->prop_id);
    printf("   file_indexings.prop_id = %s -> %s\n", $fi->prop_id,
        $foreign ? 'REJECTED as foreign' : 'accepted');
    if ($foreign) {
        echo "   The guard dropped this id from the expansion set. If the recertification\n";
        echo "   row is keyed on it, THIS is the cause.\n";
    }
}
echo "\n";

// ---------------------------------------------------------------- 5. the live result
echo "5. WHAT search() ACTUALLY RETURNS\n";
try {
    $res = $svc->search(['query' => $typed]);
    $tx = $res['transactions'] ?? [];
    printf("   transactions: %d\n", count($tx));
    $recert = 0;
    foreach ($tx as $t) {
        $type = strtolower((string) ($t['transaction_type'] ?? ''));
        $src  = (string) ($t['source_table'] ?? '');
        if (str_contains($type, 'recert') || $src === 'Related Fileno') {
            $recert++;
            printf("     RECERT ROW: %-34s src=%s\n", $t['transaction_type'] ?? '-', $src);
        }
    }
    printf("   recertification rows present: %d\n", $recert);
    if ($recert === 0) {
        echo "   => Not produced. Read sections 2 and 4 above: section 2 empty means a data\n";
        echo "      gap; section 4 'REJECTED' means the guard; neither means the row is\n";
        echo "      dropped later, by the cross-property filter.\n";
    }
} catch (\Throwable $e) {
    echo "   search() threw: " . $e->getMessage() . "\n";
}

$line();
echo "Nothing was modified.\n";
