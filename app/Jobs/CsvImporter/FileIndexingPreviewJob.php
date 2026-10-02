<?php

namespace App\Jobs\CsvImporter;

use App\Services\CsvImporter\FileIndexing\FileIndexingImportService;
use App\Services\CsvImporter\Sessions\ImportSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileIndexingPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(private readonly string $sessionId)
    {
    }

    public function handle(
        ImportSessionManager $sessions,
        FileIndexingImportService $importService
    ): void {
        $session = $sessions->require($this->sessionId);

        try {
            $sessions->markStatus($this->sessionId, 'processing', [
                'phase' => 'preview',
                'progress' => [
                    'processed' => 0,
                    'total' => 0,
                    'percent' => 0,
                    'message' => 'Parsing upload',
                ],
            ]);

            $filePath = $session['file_path'] ?? null;
            if (!$filePath || !Storage::disk('local')->exists($filePath)) {
                throw new \RuntimeException('Uploaded file not found for session.');
            }

            $uploadedFile = new UploadedFile(
                Storage::disk('local')->path($filePath),
                $session['filename'] ?? basename($filePath),
                null,
                UPLOAD_ERR_OK,
                true
            );

            $preview = $importService->buildPreview($uploadedFile, $session['test_control']);

            $sessions->storeRecords(
                $this->sessionId,
                array_map(
                    fn ($record) => $record->toSessionPayload(),
                    $preview['records']
                )
            );

            $sessions->update($this->sessionId, [
                'status' => 'preview_ready',
                'phase' => 'preview',
                'summary' => $preview['summary'],
                'suppressed' => $preview['suppressed'],
                'multiple_occurrences' => $preview['multiple_occurrences'],
                'grouping_preview' => $preview['grouping_preview'],
                'qc_findings' => $preview['qc_findings'],
                'progress' => [
                    'processed' => $preview['summary']['preview_rows'] ?? 0,
                    'total' => $preview['summary']['total_rows'] ?? 0,
                    'percent' => 100,
                    'message' => 'Preview ready',
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('FileIndexingPreviewJob failed', [
                'session_id' => $this->sessionId,
                'message' => $exception->getMessage(),
            ]);
            $sessions->markFailed($this->sessionId, $exception->getMessage());

            throw $exception;
        }
    }
}
