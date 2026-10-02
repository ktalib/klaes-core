<?php

namespace App\Http\Controllers;

use App\Services\FileIndexingCoordinateBackfillService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FileIndexingCoordinateBackfillController extends Controller
{
    public function index(FileIndexingCoordinateBackfillService $service)
    {
        return view('fileindexing.backfill-coordinates', [
            'remaining' => $service->remainingCount(),
        ]);
    }

    public function run(Request $request, FileIndexingCoordinateBackfillService $service): JsonResponse
    {
        // Nominatim allows ~1 request/second, and one row can cost several of them
        // (street, then district, then LGA), so a web request needs a small batch and
        // generous headroom above PHP's default 60s execution limit. The CLI command
        // has no time limit and is the better tool for a large run.
        set_time_limit(600);

        $limit = (int) $request->input('limit', 10);
        $limit = max(1, min(20, $limit));
        $afterId = $request->input('after_id');
        $afterId = $afterId !== null ? (int) $afterId : null;

        $service->skipLgaTier($request->boolean('skip_lga_only'));

        $result = $service->runBatch($limit, false, false, $afterId);

        // The whole batch failed at the transport layer — say why, rather than
        // reporting a batch of silent zeroes.
        $errors = $result['counts']['ERROR'] ?? 0;
        if ($errors > 0 && $errors === $result['processed']) {
            return response()->json([
                'error' => 'Could not reach nominatim.openstreetmap.org: ' . ($result['last_error'] ?? 'unknown error'),
            ], 502);
        }

        return response()->json($result);
    }
}
