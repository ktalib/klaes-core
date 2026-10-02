<?php

namespace App\Services\CsvImporter\Sessions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportSessionManager
{
    private const CACHE_PREFIX = 'csv_importer_session_';
    private const TTL_SECONDS = 7200;
    private const RECORDS_DIR = 'csv-importer/sessions';

    public function create(array $payload): string
    {
        $sessionId = (string) Str::uuid();
        $defaults = [
            'id' => $sessionId,
            'status' => 'queued',
            'phase' => 'preview',
            'progress' => [
                'processed' => 0,
                'total' => 0,
                'percent' => 0,
                'message' => 'Waiting in queue',
            ],
            'created_at' => now()->toIso8601String(),
        ];

        $this->put($sessionId, array_merge($defaults, $payload));

        return $sessionId;
    }

    public function require(string $sessionId): array
    {
        $payload = $this->get($sessionId);
        if (!$payload) {
            throw new \RuntimeException('Session expired or not found.');
        }

        return $payload;
    }

    public function get(string $sessionId): ?array
    {
        /** @var array|null $payload */
        $payload = Cache::get($this->cacheKey($sessionId));

        return $payload;
    }

    public function update(string $sessionId, array $attributes): array
    {
        $session = $this->require($sessionId);
        $updated = array_merge(
            $session,
            $attributes,
            ['updated_at' => now()->toIso8601String()]
        );

        $this->put($sessionId, $updated);

        return $updated;
    }

    public function markStatus(string $sessionId, string $status, array $extra = []): array
    {
        return $this->update($sessionId, array_merge([
            'status' => $status,
        ], $extra));
    }

    public function markProgress(string $sessionId, int $processed, int $total, ?string $message = null): void
    {
        $percent = $total > 0 ? round(($processed / $total) * 100, 2) : 0;

        $this->update($sessionId, [
            'progress' => [
                'processed' => $processed,
                'total' => $total,
                'percent' => $percent,
                'message' => $message ?? Arr::get($this->require($sessionId), 'progress.message', 'Processing'),
            ],
        ]);
    }

    public function markFailed(string $sessionId, string $message): void
    {
        $this->update($sessionId, [
            'status' => 'failed',
            'error' => $message,
            'progress' => [
                'processed' => 0,
                'total' => 0,
                'percent' => 0,
                'message' => $message,
            ],
        ]);
    }

    public function storeRecords(string $sessionId, array $records): void
    {
        Storage::disk('local')->makeDirectory(self::RECORDS_DIR);
        Storage::disk('local')->put(
            $this->recordsPath($sessionId),
            json_encode($records, JSON_THROW_ON_ERROR)
        );
    }

    public function loadRecords(string $sessionId): array
    {
        $path = $this->recordsPath($sessionId);
        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        $contents = Storage::disk('local')->get($path);

        return $contents
            ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    public function delete(string $sessionId, bool $deleteUpload = true): void
    {
        $session = $this->get($sessionId);
        Cache::forget($this->cacheKey($sessionId));
        Storage::disk('local')->delete($this->recordsPath($sessionId));

        if ($deleteUpload && $session && !empty($session['file_path'])) {
            Storage::disk('local')->delete($session['file_path']);
        }
    }

    private function put(string $sessionId, array $payload): void
    {
        Cache::put(
            $this->cacheKey($sessionId),
            $payload,
            now()->addSeconds(self::TTL_SECONDS)
        );
    }

    private function cacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX . $sessionId;
    }

    private function recordsPath(string $sessionId): string
    {
        return self::RECORDS_DIR . '/' . $sessionId . '.json';
    }
}
