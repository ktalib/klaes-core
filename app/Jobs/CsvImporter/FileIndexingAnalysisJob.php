<?php

namespace App\Jobs\CsvImporter;

use App\Models\CsvImportUpload;
use App\Services\CsvImporter\FileIndexing\FileIndexingImportService;
use App\Services\CsvImporter\Sessions\ImportSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileIndexingAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(private readonly string $sessionId)
    {
    }

    public function handle(
        ImportSessionManager $sessions,
        FileIndexingImportService $importService
    ): void {
        $session = $sessions->require($this->sessionId);
        $upload = CsvImportUpload::where('session_id', $this->sessionId)->first();

        if (!$upload) {
            throw new \RuntimeException('Upload metadata not found for session.');
        }

        try {
            $filePath = $session['file_path'] ?? null;
            if (!$filePath || !Storage::disk('local')->exists($filePath)) {
                throw new \RuntimeException('Uploaded file not found.');
            }

            $absolutePath = Storage::disk('local')->path($filePath);
            $totalRows = $importService->countRowsFromPath($absolutePath);

            $sessions->markStatus($this->sessionId, 'processing', [
                'phase' => 'preview',
                'progress' => [
                    'processed' => 0,
                    'total' => $totalRows,
                    'percent' => 0,
                    'message' => 'Analyzing upload',
                ],
            ]);

            $upload->update([
                'status' => 'processing',
                'row_count' => $totalRows,
            ]);

            $analysis = $importService->analyzeFromPath(
                $absolutePath,
                $session['test_control'],
                function (int $processed) use ($sessions, $totalRows) {
                    $totalForProgress = $totalRows > 0 ? $totalRows : max($processed, 1);
                    $sessions->markProgress(
                        $this->sessionId,
                        min($processed, $totalForProgress),
                        $totalForProgress,
                        'Analyzing upload'
                    );
                }
            );

            $sessions->storeRecords(
                $this->sessionId,
                array_map(
                    fn ($record) => $record->toSessionPayload(),
                    $analysis['records']
                )
            );

            $sessions->update($this->sessionId, [
                'status' => 'preview_ready',
                'phase' => 'preview',
                'summary' => $analysis['summary'],
                'suppressed' => $analysis['suppressed'],
                'multiple_occurrences' => $analysis['multiple_occurrences'],
                'grouping_preview' => $analysis['grouping_preview'],
                'qc_findings' => $analysis['qc_findings'],
                'progress' => [
                    'processed' => $analysis['summary']['preview_rows'] ?? 0,
                    'total' => $analysis['summary']['total_rows'] ?? 0,
                    'percent' => 100,
                    'message' => 'Preview ready',
                ],
            ]);

            $upload->update([
                'status' => 'preview_ready',
                'row_count' => $analysis['summary']['total_rows'] ?? $upload->row_count,
                'meta' => [
                    'summary' => $analysis['summary'],
                    'suppressed' => array_slice($analysis['suppressed'], 0, 250),
                    'multiple_occurrences' => $analysis['multiple_occurrences'],
                    'grouping_preview' => $analysis['grouping_preview'],
                    'qc_findings' => $analysis['qc_findings'],
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('FileIndexingAnalysisJob failed', [
                'session_id' => $this->sessionId,
                'message' => $exception->getMessage(),
            ]);

            $sessions->markFailed($this->sessionId, $exception->getMessage());
            $upload->update([
                'status' => 'failed',
                'meta' => array_merge($upload->meta ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }
    }
}
