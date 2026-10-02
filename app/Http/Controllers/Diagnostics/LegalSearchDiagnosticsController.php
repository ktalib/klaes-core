<?php

namespace App\Http\Controllers\Diagnostics;

use App\Http\Controllers\Controller;
use App\Services\LegalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TEMPORARY read-only diagnostic for "a subdivided mother returns no results".
 *
 * Answers, for one file number, everything needed to tell the possible causes
 * apart: the fix is not deployed, the fix is deployed but its scope guard does
 * not match this row, or the data is shaped differently than expected.
 *
 * Read-only: it runs SELECTs and the search service, and never writes.
 * Returns counts, identifiers and booleans only - no party names or addresses.
 *
 * Protected by a fixed key in the URL (?key=...). Throwaway endpoint - DELETE
 * the route and this file once the question is answered.
 */
class LegalSearchDiagnosticsController extends Controller
{
    /** Bump when the diagnostic itself changes, so a stale deploy is obvious. */
    private const DIAG_VERSION = 'diag-1';

    /** Present only in the build that carries the downward-expansion fix. */
    private const FIX_METHOD = 'resolveSplitMotherPropId';

    private const KEY = 'klaes-ls-diag-8f3b21c7d94e';

    public function __invoke(Request $request, LegalSearchService $service): JsonResponse
    {
        if (!hash_equals(self::KEY, (string) $request->query('key'))) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $file = trim((string) $request->query('file', ''));
        if ($file === '') {
            return response()->json(['error' => 'pass ?file=<file number>'], 422);
        }

        $conn = DB::connection('sqlsrv');
        $out = [
            'diag_version' => self::DIAG_VERSION,
            'file'         => $file,
            'app_env'      => app()->environment(),
            // THE key question: is the fixed build actually running?
            'fix_deployed' => method_exists($service, self::FIX_METHOD),
        ];

        // --- decommission row (the scope guard's first test) ---
        try {
            $decom = $conn->table('decommissioned_files')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [strtoupper($file)])
                ->orderByDesc('id')
                ->first();
            $reason = (string) ($decom->decommissioning_reason ?? '');
            $out['decommission'] = $decom ? [
                'found'                 => true,
                'reason'                => $reason,
                'reason_matches_split'  => (bool) preg_match('/subdivision|merg|separation|fragment/i', $reason),
                'false_decommissioning' => $decom->false_decommissioning ?? null,
                'successors_listed'     => $decom->successor_file_no
                    ? count(array_filter(array_map('trim', explode(',', (string) $decom->successor_file_no))))
                    : 0,
                'event_type'            => $decom->event_type ?? null,
            ] : ['found' => false];
        } catch (\Throwable $e) {
            $out['decommission'] = ['error' => $e->getMessage()];
        }

        // --- prop_id resolution (the scope guard's second test) ---
        try {
            $master = $conn->table('PropID_Master')
                ->where(function ($q) use ($file) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(primary_file_number))) = ?', [strtoupper($file)])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [strtoupper($file)]);
                })
                ->whereNotNull('prop_id')
                ->orderBy('id')
                ->get(['id', 'prop_id', 'primary_file_number', 'mlsFNo']);
            $out['propid_master'] = $master->toArray();
        } catch (\Throwable $e) {
            $out['propid_master'] = ['error' => $e->getMessage()];
        }

        // --- rows keyed to the file's own number (must be 0 for the fix to fire) ---
        $own = [];
        foreach ([
            'pra'                  => ['mlsFNo', 'fileno', 'temp_fileno'],
            'file_history_staging' => ['mlsfNo', 'fileno'],
            'CofO_staging'         => ['mlsFNo', 'fileno'],
            'deed_registrations'   => ['fileno'],
        ] as $table => $cols) {
            try {
                $q = $conn->table($table)->where(function ($sub) use ($cols, $file) {
                    foreach ($cols as $c) {
                        $sub->orWhereRaw("UPPER(LTRIM(RTRIM({$c}))) = ?", [strtoupper($file)]);
                    }
                });
                $own[$table] = (int) $q->count();
            } catch (\Throwable $e) {
                $own[$table] = 'err: ' . $e->getMessage();
            }
        }
        $out['own_number_rows'] = $own;

        // --- what the scope guard actually returns, and the children it should reach ---
        try {
            $ref = new \ReflectionClass($service);
            if ($ref->hasMethod(self::FIX_METHOD)) {
                $m = $ref->getMethod(self::FIX_METHOD);
                $m->setAccessible(true);
                $prop = $m->invoke($service, $conn, $file);
                $out['split_mother_prop_id'] = $prop;
                $out['pra_children_for_that_prop'] = $prop !== null
                    ? (int) $conn->table('pra')->where('parent_prop_id', (string) $prop)->count()
                    : null;
            } else {
                $out['split_mother_prop_id'] = 'METHOD ABSENT - old build is running';
            }
        } catch (\Throwable $e) {
            $out['split_mother_prop_id'] = 'err: ' . $e->getMessage();
        }

        // --- children by the prop_id the master table reports, regardless of the guard ---
        try {
            $masterProp = $conn->table('PropID_Master')
                ->where(function ($q) use ($file) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(primary_file_number))) = ?', [strtoupper($file)])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [strtoupper($file)]);
                })
                ->value('prop_id');
            $out['children_by_master_prop'] = $masterProp !== null
                ? [
                    'prop_id'          => (string) $masterProp,
                    'pra_children'     => (int) $conn->table('pra')->where('parent_prop_id', (string) $masterProp)->count(),
                    'indexed_children' => Schema::connection('sqlsrv')->hasColumn('file_indexings', 'parent_prop_id')
                        ? (int) $conn->table('file_indexings')->where('parent_prop_id', (string) $masterProp)->count()
                        : null,
                ]
                : null;
        } catch (\Throwable $e) {
            $out['children_by_master_prop'] = ['error' => $e->getMessage()];
        }

        // --- the real search, timed ---
        try {
            $t0 = microtime(true);
            $result = $service->search(['query' => $file]);
            $out['search'] = [
                'ms'           => (int) round((microtime(true) - $t0) * 1000),
                'transactions' => count($result['transactions'] ?? []),
                'pra_count'    => $result['pra_count'] ?? null,
                'total_count'  => $result['total_count'] ?? null,
                'sample'       => collect($result['transactions'] ?? [])->take(5)->map(function ($t) {
                    $t = (array) $t;
                    return [
                        'file'   => $t['file_number'] ?? $t['mlsFNo'] ?? null,
                        'type'   => $t['transaction_type'] ?? null,
                        'prop'   => $t['prop_id'] ?? null,
                        'parent' => $t['parent_prop_id'] ?? null,
                    ];
                })->values()->all(),
            ];
        } catch (\Throwable $e) {
            $out['search'] = ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()];
        }

        return response()->json($out, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
