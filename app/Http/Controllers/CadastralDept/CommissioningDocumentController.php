<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Services\Laas\LaasFolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Commissioning sheet / LGA confirmation sheet for the Cadastral "Scanned files" pop-up
 * (user, 2026-11-10: "for commissioned files, the commissioning sheet, LGA confirmation if any
 * should display too, just like we did for VFS").
 *
 * Read-only. Keyed by file number, and drawn by the same LaasFolioService the VFS
 * Commissioning folder uses (VirtualFileExplorerController::systemDocument), so the two
 * always show the same sheet. Only a key the file itself lists, and only when available, renders.
 */
class CommissioningDocumentController extends Controller
{
    public function __construct(private LaasFolioService $folio)
    {
    }

    /** GET ?file_number= : the commissioning documents this file has (empty when never commissioned). */
    public function index(Request $request): JsonResponse
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        if ($fileNumber === '') {
            return response()->json(['success' => true, 'documents' => []]);
        }

        try {
            $docs = $this->folio->commissioningDocuments($fileNumber);
        } catch (\Throwable $e) {
            Log::info('Cadastral: commissioning documents could not be listed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
            $docs = [];
        }

        $documents = collect($docs)->map(fn (array $doc) => [
            'key' => $doc['key'],
            'title' => $doc['title'],
            'date' => $doc['date'] ?? null,
            'available' => (bool) $doc['available'],
            'note' => $doc['note'] ?? null,
            'url' => $doc['available']
                // Relative, so the frame follows the page's scheme (http or https).
                ? route('cadastral-dept.commissioning-docs.show', ['key' => $doc['key'], 'file_number' => $fileNumber], false)
                : null,
        ])->values();

        return response()->json(['success' => true, 'documents' => $documents]);
    }

    /** GET /{key}?file_number= : one sheet, drawn read-only for embedding. */
    public function show(Request $request, string $key)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));
        abort_if($fileNumber === '', 404);

        $listed = collect($this->folio->commissioningDocuments($fileNumber))
            ->first(fn (array $doc) => $doc['key'] === $key && $doc['available']);
        abort_unless($listed, 404);

        $html = $this->folio->renderCommissioningDocument($fileNumber, $key);
        abort_if($html === null, 404, 'This document could not be drawn.');

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
