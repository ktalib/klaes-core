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

class FileIndexingImportJob implements ShouldQueue
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

        if (($session['status'] ?? null) !== 'import_queued' && ($session['status'] ?? null) !== 'import_processing') {
            // Session may have been retried; ensure we start from import phase.
            $sessions->markStatus($this->sessionId, 'import_processing', [
                'phase' => 'import',
            ]);
        } else {
            $sessions->markStatus($this->sessionId, 'import_processing', [
                'phase' => 'import',
            ]);
        }

        if ($upload) {
            $upload->update(['status' => 'import_processing']);
        }

        $session = $sessions->require($this->sessionId);
        $recordsPayload = $sessions->loadRecords($this->sessionId);
        $records = [];

        if (!empty($recordsPayload)) {
            $records = $importService->recordsFromSession($recordsPayload);
        } else {
            $filePath = $session['file_path'] ?? null;
            if (!$filePath || !Storage::disk('local')->exists($filePath)) {
                throw new \RuntimeException('Uploaded file missing for this session.');
            }

            $analysis = $importService->analyzeFromPath(
                Storage::disk('local')->path($filePath),
                $session['test_control'],
                null
            );
            $records = $analysis['records'];
        }

        $total = count($records);

        if ($total === 0) {
            $sessions->update($this->sessionId, [
                'status' => 'import_completed',
                'phase' => 'import',
                'import_summary' => [
                    'inserted' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'grouping_updates' => 0,
                    'source' => $session['filename'] ?? 'upload.csv',
                ],
                'progress' => [
                    'processed' => 0,
                    'total' => 0,
                    'percent' => 100,
                    'message' => 'No importable records.',
                ],
            ]);

            if ($upload) {
                $upload->update([
                    'status' => 'import_completed',
                    'meta' => array_merge($upload->meta ?? [], [
                        'import_summary' => [
                            'inserted' => 0,
                            'updated' => 0,
                            'skipped' => 0,
                            'grouping_updates' => 0,
                            'source' => $session['filename'] ?? 'upload.csv',
                        ],
                    ]),
                ]);
            }

            return;
        }

        $processed = 0;
        $sessions->markProgress($this->sessionId, 0, $total, 'Importing records');

        try {
            $summary = $importService->import(
                $records,
                $session['test_control'],
                $session['filename'] ?? 'upload.csv',
                function () use (&$processed, $total, $sessions) {
                    $processed++;
                    if ($processed === $total || $processed % 25 === 0) {
                        $sessions->markProgress(
                            $this->sessionId,
                            $processed,
                            $total,
                            'Importing records'
                        );
                    }
                }
            );
        } catch (\Throwable $exception) {
            Log::error('FileIndexingImportJob failed', [
                'session_id' => $this->sessionId,
                'message' => $exception->getMessage(),
            ]);
            $sessions->markFailed($this->sessionId, $exception->getMessage());
            if ($upload) {
                $upload->update([
                    'status' => 'failed',
                    'meta' => array_merge($upload->meta ?? [], [
                        'error' => $exception->getMessage(),
                    ]),
                ]);
            }

            throw $exception;
        }

        $sessions->update($this->sessionId, [
            'status' => 'import_completed',
            'phase' => 'import',
            'import_summary' => $summary,
            'progress' => [
                'processed' => $total,
                'total' => $total,
                'percent' => 100,
                'message' => 'Import complete',
            ],
        ]);

        if ($upload) {
            $upload->update([
                'status' => 'import_completed',
                'meta' => array_merge($upload->meta ?? [], [
                    'import_summary' => $summary,
                ]),
            ]);
        }
    }
}
