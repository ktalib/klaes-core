<?php

namespace App\Http\Controllers\Api\CsvImporter;

use App\Http\Controllers\Controller;
use App\Services\CsvImporter\FileNumber\FileNumberImportService;
use App\Services\CsvImporter\Security\ImportAccessGuard;
use App\Services\CsvImporter\Sessions\ImportSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FileNumberImportController extends Controller
{
    public function __construct(
        private readonly FileNumberImportService $importService,
        private readonly ImportSessionManager $sessions,
        private readonly ImportAccessGuard $accessGuard,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'test_control' => ['required', Rule::in(['TEST', 'PRODUCTION'])],
        ]);

        $file = $validated['file'];
        $testControl = strtoupper($validated['test_control']);

        try {
            $preview = $this->importService->buildPreview($file, $testControl);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $sessionId = $this->sessions->create([
            'filename' => $file->getClientOriginalName(),
            'test_control' => $testControl,
            'records' => array_map(
                fn ($record) => $record->toSessionPayload(),
                $preview['records']
            ),
            'summary' => $preview['summary'],
            'preview_rows' => $preview['preview_rows'],
            'qc_findings' => $preview['qc_findings'],
        ]);

        return response()->json([
            'session_id' => $sessionId,
            'filename' => $file->getClientOriginalName(),
            'test_control' => $testControl,
            'summary' => $preview['summary'],
            'preview_rows' => $preview['preview_rows'],
            'qc_findings' => $preview['qc_findings'],
        ]);
    }

    public function preview(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $session = $this->sessions->require($sessionId);

        return response()->json([
            'filename' => $session['filename'],
            'test_control' => $session['test_control'],
            'summary' => $session['summary'],
            'preview_rows' => $session['preview_rows'],
            'qc_findings' => $session['qc_findings'] ?? [],
        ]);
    }

    public function import(Request $request, string $sessionId): JsonResponse
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        $session = $this->sessions->require($sessionId);
        $records = $this->importService->recordsFromSession($session['records'] ?? []);

        $summary = $this->importService->import(
            $records,
            $session['test_control'],
            $session['filename'] ?? 'upload.csv'
        );

        $this->sessions->delete($sessionId);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }
}
