<?php
/**
 * Snapshot a Cadastral Dept case (and its file's records) before a live test, then put it back.
 *
 *   php case_snapshot.php snap <case_id>                 writes <case_ref>_<file>_<timestamp>.json here
 *   php case_snapshot.php restore <snapshot.json>        dry run: lists what would change
 *   php case_snapshot.php restore <snapshot.json> --apply
 *
 * Covers (all sqlsrv): the cadastral_cases row; every row of every table with a cadastral_case_id
 * for the case; their children (charting beacons, FMC logs, survey_register_files by movement);
 * the file_indexings, fileNumber and file_tracker rows of the file. Restore puts snapshot rows back
 * as they were, deletes rows made since, and deletes bell notifications made since that name the
 * case or the file. audit_logs are kept: they are the history of the test.
 */
require 'C:/xampp/htdocs/klas/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/klas/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$d = DB::connection('sqlsrv');
$d->getPdo()->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 30);   // fail, never hang on another session's lock
$mode = $argv[1] ?? '';

/** child table => [child fk column, parent table] */
$children = [
    'cadastral_charting_beacons' => ['cadastral_charting_id', 'cadastral_chartings'],
    'cadastral_fmc_logs' => ['cadastral_fmc_id', 'cadastral_fmcs'],
    'survey_register_files' => ['cadastral_movement_id', 'cadastral_case_movements'],
];

$caseTables = fn () => array_values(array_filter(array_column(
    $d->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'cadastral_case_id' ORDER BY TABLE_NAME"), 'TABLE_NAME'
), fn ($t) => !preg_match('/backup|_bak|_old/i', $t)));

$rows = fn ($q) => array_map(fn ($r) => (array) $r, $q->get()->all());

if ($mode === 'snap') {
    $case = $d->table('cadastral_cases')->where('id', (int) ($argv[2] ?? 0))->first();
    if (!$case) {
        exit("No such case.\n");
    }
    $snap = ['taken_at' => now()->toDateTimeString(), 'case_id' => (int) $case->id, 'case_ref' => $case->case_ref,
        'file_number' => $case->file_number, 'tables' => [], 'children' => [], 'base' => [], 'high_water' => []];

    foreach ($caseTables() as $t) {
        $snap['tables'][$t] = $rows($d->table($t)->where('cadastral_case_id', $case->id));
    }
    $snap['tables']['cadastral_cases'] = $rows($d->table('cadastral_cases')->where('id', $case->id));
    foreach ($children as $t => [$fk, $parent]) {
        $ids = array_column($snap['tables'][$parent] ?? [], 'id');
        $snap['children'][$t] = $ids ? $rows($d->table($t)->whereIn($fk, $ids)) : [];
    }
    foreach (['file_indexings' => $case->file_indexing_id, 'file_tracker' => $case->file_tracker_id] as $t => $id) {
        $snap['base'][$t] = $id ? $rows($d->table($t)->where('id', $id)) : [];
    }
    // Ids by a NOLOCK scan (a plain scan waits on other sessions' row locks), then the rows by id.
    $cols = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'gkn_fileno', 'dciv_fileno'];
    $fnIds = array_column($d->select('SELECT id FROM fileNumber WITH (NOLOCK) WHERE ' . implode(' OR ', array_map(fn ($c) => "$c = ?", $cols)),
        array_fill(0, count($cols), $case->file_number)), 'id');
    $snap['base']['fileNumber'] = $fnIds ? $rows($d->table('fileNumber')->whereIn('id', $fnIds)) : [];
    foreach (['notifications', 'audit_logs'] as $t) {
        $snap['high_water'][$t] = (int) $d->table($t)->max('id');
    }

    $name = __DIR__ . '/' . $case->case_ref . '_' . preg_replace('/[^A-Za-z0-9-]/', '-', $case->file_number) . '_' . now()->format('Ymd-His') . '.json';
    file_put_contents($name, json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Snapshot: $name\n";
    foreach ($snap['tables'] + $snap['children'] + $snap['base'] as $t => $r) {
        if ($r) {
            echo '  ' . str_pad($t, 40) . count($r) . " row(s)\n";
        }
    }
    exit(0);
}

if ($mode !== 'restore' || !is_file($argv[2] ?? '')) {
    exit("Usage: snap <case_id> | restore <snapshot.json> [--apply]\n");
}

$snap = json_decode(file_get_contents($argv[2]), true);
$apply = in_array('--apply', $argv, true);
$caseId = (int) $snap['case_id'];
$log = [];

/** Put one snapshot row back: update it, or re-insert it with its old id. */
$put = function (string $t, array $row) use ($d, &$log) {
    $id = $row['id'];
    $now = $d->table($t)->where('id', $id)->first();
    $vals = $row;
    unset($vals['id']);
    if ($now) {
        $diff = array_keys(array_filter($vals, fn ($v, $c) => (string) $v !== (string) ($now->{$c} ?? null), ARRAY_FILTER_USE_BOTH));
        if ($diff) {
            $log[] = "update $t#$id: " . implode(', ', $diff);
            $d->table($t)->where('id', $id)->update(array_intersect_key($vals, array_flip($diff)));
        }
    } else {
        $log[] = "re-insert $t#$id";
        $d->unprepared("SET IDENTITY_INSERT [$t] ON");
        $d->table($t)->insert($row);
        $d->unprepared("SET IDENTITY_INSERT [$t] OFF");
    }
};

$d->beginTransaction();
try {
    // Rows made since the snapshot: children first, then the case's own rows.
    foreach ($children as $t => [$fk, $parent]) {
        $parentIds = $parent === 'cadastral_cases' ? [$caseId] : $d->table($parent)->where('cadastral_case_id', $caseId)->pluck('id')->all();
        $keep = array_column($snap['children'][$t] ?? [], 'id');
        $q = $d->table($t)->whereIn($fk, $parentIds ?: [0])->whereNotIn('id', $keep ?: [0]);
        if ($n = (clone $q)->count()) {
            $log[] = "delete $n new row(s) from $t";
            $q->delete();
        }
    }
    foreach ($snap['tables'] as $t => $snapRows) {
        if ($t === 'cadastral_cases') {
            continue;
        }
        $q = $d->table($t)->where('cadastral_case_id', $caseId)->whereNotIn('id', array_column($snapRows, 'id') ?: [0]);
        if ($n = (clone $q)->count()) {
            $log[] = "delete $n new row(s) from $t";
            $q->delete();
        }
    }
    // Tables that gained a cadastral_case_id table since the snapshot are not touched; say so.
    foreach (array_diff($caseTables(), array_keys($snap['tables'])) as $t) {
        if ($d->table($t)->where('cadastral_case_id', $caseId)->exists()) {
            $log[] = "NOTE: $t has rows for the case but was not in the snapshot; left alone";
        }
    }

    // Snapshot rows back as they were.
    foreach ($snap['tables'] as $t => $snapRows) {
        foreach ($snapRows as $r) {
            $put($t, $r);
        }
    }
    foreach ($snap['children'] as $t => $snapRows) {
        foreach ($snapRows as $r) {
            $put($t, $r);
        }
    }
    foreach ($snap['base'] as $t => $snapRows) {
        foreach ($snapRows as $r) {
            $put($t, $r);
        }
    }

    // Bell notifications made since that name the case or the file.
    $q = $d->table('notifications')->where('id', '>', $snap['high_water']['notifications'])
        ->where(function ($w) use ($snap) {
            foreach (['title', 'subject', 'message', 'body', 'data'] as $c) {
                $w->orWhere($c, 'like', '%' . $snap['case_ref'] . '%')->orWhere($c, 'like', '%' . $snap['file_number'] . '%');
            }
        });
    if ($n = (clone $q)->count()) {
        $log[] = "delete $n notification(s)";
        $q->delete();
    }

    echo ($log ? implode("\n", $log) : 'Nothing differs from the snapshot.') . "\n";
    if ($apply) {
        $d->commit();
        echo "APPLIED.\n";
    } else {
        $d->rollBack();
        echo "Dry run: nothing written. Add --apply to restore.\n";
    }
} catch (Throwable $e) {
    $d->rollBack();
    echo 'FAILED, nothing written: ' . $e->getMessage() . "\n";
    exit(1);
}
