<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RegistryDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight lookup backing the File Indexing "Registry" auto-fill —
 * lets the create-indexing dialog derive the Registry 1/2/3 number from
 * the file number without duplicating config/file_ranges.php's prefix+year
 * rules in JavaScript.
 */
class RegistryDetectionApiController extends Controller
{
    public function detect(Request $request, RegistryDetector $detector): JsonResponse
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        $result = $detector->detect($fileNumber);

        return response()->json([
            'success' => true,
            'file_number' => $fileNumber,
            'registry' => $result['registry'],
            'zone' => $result['zone'],
            'reason' => $result['reason'],
        ]);
    }
}
