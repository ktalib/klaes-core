<?php
/**
 * Compact KANGIS recertification probe — READ-ONLY, safe on production.
 *
 *   php scratch/kangis_probe.php "KNML 1200"
 *
 * Answers the only two questions that matter:
 *   A. does a related_file_number link row exist for either number?
 *   B. does the foreign-prop_id guard reject this file's indexing prop_id?
 * ...then shows what search() actually returns.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$typed = trim((string) ($argv[1] ?? 'KNML 1200'));
$c   = DB::connection('sqlsrv');
$svc = app(App\Services\LegalSearchService::class);
$inv = function ($name, ...$a) use ($svc) {
    $m = new ReflectionMethod($svc, $name); $m->setAccessible(true); return $m->invoke($svc, ...$a);
};

$canon = $inv('resolveKangisCanonical', $c, $typed) ?: $typed;
echo "\nTYPED     : {$typed}\nRESOLVES  : {$canon}\n\n";

// A. the link rows
echo "A. related_file_number rows\n";
if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
    echo "   TABLE MISSING\n";
} else {
    $cols = array_values(array_filter(
        Schema::connection('sqlsrv')->getColumnListing('related_file_number'),
        fn ($x) => stripos($x, 'file_number') !== false || stripos($x, 'fileno') !== false
    ));
    $rows = $c->table('related_file_number')->where(function ($w) use ($cols, $typed, $canon) {
        foreach ($cols as $col) { $w->orWhere($col, $typed)->orWhere($col, $canon); }
    })->limit(20)->get();
    printf("   %d row(s)\n", count($rows));
    foreach ($rows as $r) {
        $a = (array) $r; $b = [];
        foreach ($cols as $col) { if (!empty($a[$col])) $b[] = "{$col}={$a[$col]}"; }
        echo "     " . implode('  ', $b) . "\n";
    }
    if (!count($rows)) echo "   => DATA GAP: nothing links these numbers.\n";
}

// B. the guard
echo "\nB. foreign-prop_id guard\n";
$vars = $inv('fileNumberVariants', $canon);
$fi = $c->table('file_indexings')->whereIn('file_number', $vars)->whereNull('deleted_at')->first(['prop_id']);
if (!$fi || !$fi->prop_id) {
    echo "   no indexing prop_id — guard not involved\n";
} else {
    $f = $inv('isForeignIndexingPropId', $c, $vars, (string) $fi->prop_id);
    printf("   prop_id %s -> %s\n", $fi->prop_id, $f ? 'REJECTED (this is the cause)' : 'accepted');
}

// C. the actual result
echo "\nC. search() result\n";
$tx = $svc->search(['query' => $typed])['transactions'] ?? [];
$n = 0;
foreach ($tx as $t) {
    if (stripos((string) ($t['transaction_type'] ?? ''), 'recert') !== false
        || ($t['source_table'] ?? '') === 'Related Fileno') {
        $n++;
        printf("   RECERT: %s\n", $t['transaction_type'] ?? '-');
    }
}
printf("   %d transactions, %d recertification row(s)\n\n", count($tx), $n);
