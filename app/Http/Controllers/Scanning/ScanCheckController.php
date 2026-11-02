<?php

namespace App\Http\Controllers\Scanning;

use App\Http\Controllers\Controller;
use App\Services\Scanning\ScanPresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Has this file been scanned?" for Create File Index and Legal Search, the scanned-page
 * browser (user, 2026-11-01; config fileindexing.scan_check).
 */
class ScanCheckController extends Controller
{
    public function __construct(private ScanPresenceService $scans)
    {
    }

    private function payload(array $result): array
    {
        return $result + [
            'block_create' => (bool) config('fileindexing.scan_check.block_create', true),
        ];
    }

    /** GET ?file_number= — the panel on Create File Index. */
    public function check(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number'));
        if ($fileNumber === '') {
            return response()->json(['success' => false, 'message' => 'Enter a file number.'], 422);
        }

        return response()->json(['success' => true] + $this->payload($this->scans->check($fileNumber, $request->boolean('fresh'))));
    }

    /**
     * Used by FileIndexingController::store(): the 422 response that refuses a new index for
     * a file that has not been scanned, or null when it may go ahead.
     */
    public function refusal(?string $fileNumber): ?\Illuminate\Http\JsonResponse
    {
        if (!config('fileindexing.scan_check.block_create', true)) {
            return null;
        }
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' || !ScanPresenceService::candidates($fileNumber)) {
            return null;
        }

        $result = $this->scans->check($fileNumber, true);
        if ($result['scanned']) {
            return null;
        }

        Log::info('Create File Index refused: file not scanned', ['file_number' => $fileNumber, 'by' => Auth::id()]);

        return response()->json([
            'success' => false,
            'error_type' => 'not_scanned',
            'message' => $result['message'] . ' This file cannot be indexed until its scan has been uploaded in Blind Scan.',
            'scan_check' => $this->payload($result),
        ], 422);
    }

    /** GET ?t= — the pages of one scanned folder, for the page browser. */
    public function pages(Request $request)
    {
        $resolved = $this->scans->resolveToken((string) $request->query('t'));
        if (!$resolved) {
            return response()->json(['success' => false, 'message' => 'That folder link is no longer valid. Check the file again.'], 404);
        }
        [$source, $path, $relative] = $resolved;
        $files = $this->scans->pageFiles($path, 2000);

        return response()->json([
            'success' => true,
            'label' => ScanPresenceService::SOURCE_LABELS[$source] ?? $source,
            'folder' => $relative,
            'count' => count($files),
            'pages' => array_map(function ($f) use ($request) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));

                return [
                    'name' => $f,
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'other'),
                    'url' => route('scan-check.page', ['t' => $request->query('t'), 'f' => $f]),
                ];
            }, $files),
        ]);
    }

    /** GET ?t=&f= — one page file. */
    public function page(Request $request)
    {
        $resolved = $this->scans->resolveToken((string) $request->query('t'));
        $f = str_replace('\\', '/', (string) $request->query('f'));
        abort_if(!$resolved || $f === '' || str_contains($f, '..') || str_contains($f, ':') || str_starts_with($f, '/'), 404);
        [, $path] = $resolved;

        // A single-PDF entry on the records server: the "folder" is the file itself.
        $file = @is_file($path) ? $path : $path . '/' . $f;
        abort_unless(@is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'private, max-age=600']);
    }
}
