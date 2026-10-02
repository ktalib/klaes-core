<?php

namespace App\Services;

use App\Models\DcivFileNo;
use App\Models\FileIndexing;
use App\Models\GknFileNo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CommissioningMirrorService
{
    /**
     * Mirror a File Indexing record into the downstream commissioning modules.
     */
    public function mirror(FileIndexing $fileIndexing, array $validated = [], array $relatedFileNos = []): void
    {
        $registryCandidate = $fileIndexing->general_registry
            ?? ($validated['general_registry'] ?? null)
            ?? $fileIndexing->registry
            ?? ($validated['registry'] ?? null);

        if (!$registryCandidate) {
            return;
        }

        try {
            if ($this->registryMatchesDciv($registryCandidate)) {
                $this->syncDcivCommissionRecord($fileIndexing, $validated, $relatedFileNos);
                return;
            }

            if ($this->registryMatchesSurvey($registryCandidate)) {
                $this->syncGknCommissionRecord($fileIndexing, $validated);
            }
        } catch (\Throwable $exception) {
            Log::warning('CommissioningMirrorService::mirror - sync failed', [
                'file_indexing_id' => $fileIndexing->id,
                'registry' => $registryCandidate,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function syncDcivCommissionRecord(FileIndexing $fileIndexing, array $validated, array $relatedFileNos): void
    {
        $fileNumber = $fileIndexing->file_number;
        if (empty($fileNumber)) {
            return;
        }

        if (DcivFileNo::where('full_file_number', $fileNumber)->exists()) {
            return;
        }

        $components = $this->extractDcivComponents($fileNumber);
        if ($components === null) {
            Log::warning('CommissioningMirrorService::syncDcivCommissionRecord - pattern mismatch', [
                'file_indexing_id' => $fileIndexing->id,
                'file_number' => $fileNumber,
            ]);
            return;
        }

        $timestamp = now();

        $payload = [
            'batch_no' => null,
            'prefix' => $components['prefix'],
            'year' => $components['year'],
            'serial_number' => $components['serial'],
            'full_file_number' => $fileNumber,
            'file_name' => $fileIndexing->file_title,
            'plot_no' => $fileIndexing->plot_number,
            'tp_no' => $fileIndexing->tp_no,
            'location' => $fileIndexing->location,
            'lga' => $fileIndexing->lga,
            'tracking_id' => $fileIndexing->tracking_id,
            'created_by' => Auth::id() ?? 0,
            'commissioning_date' => $timestamp,
            'commissioning_time' => $timestamp->format('H:i:s'),
            'land_use_id' => $validated['land_use_id'] ?? null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'related_fileno' => $this->encodeRelatedNumbers($relatedFileNos, $fileIndexing->related_fileno),
            'dciv_reason' => $validated['dciv_reason'] ?? $fileIndexing->dciv_reason,
        ];

        DcivFileNo::create($payload);

        Log::info('CommissioningMirrorService::syncDcivCommissionRecord - mirrored record', [
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $fileNumber,
        ]);
    }

    protected function syncGknCommissionRecord(FileIndexing $fileIndexing, array $validated): void
    {
        $fileNumber = $fileIndexing->file_number;
        if (empty($fileNumber)) {
            return;
        }

        if (GknFileNo::where('full_file_number', $fileNumber)->exists()) {
            return;
        }

        $components = $this->extractGknComponents($fileNumber);
        $timestamp = now();

        $payload = [
            'batch_no' => null,
            'year' => $components['year'],
            'serial_number' => $components['serial'],
            'full_file_number' => $fileNumber,
            'file_name' => $fileIndexing->file_title,
            'plot_no' => $fileIndexing->plot_number,
            'tp_no' => $fileIndexing->tp_no,
            'location' => $fileIndexing->location,
            'lga' => $fileIndexing->lga,
            'tracking_id' => $fileIndexing->tracking_id,
            'created_by' => Auth::id() ?? 0,
            'commissioning_date' => $timestamp,
            'commissioning_time' => $timestamp->format('H:i:s'),
            'land_use_id' => $validated['land_use_id'] ?? null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'type' => $components['type'],
            'customer_type' => $validated['customer_type'] ?? 'Individual',
        ];

        GknFileNo::create($payload);

        Log::info('CommissioningMirrorService::syncGknCommissionRecord - mirrored record', [
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $fileNumber,
        ]);
    }

    protected function registryMatchesSurvey(?string $registry): bool
    {
        if ($registry === null) {
            return false;
        }

        return str_contains(strtoupper($registry), 'SURVEY');
    }

    protected function registryMatchesDciv(?string $registry): bool
    {
        if ($registry === null) {
            return false;
        }

        $normalized = strtoupper($registry);

        return str_contains($normalized, 'DCIV');
    }

    protected function extractDcivComponents(string $fileNumber): ?array
    {
        $normalized = strtoupper(trim($fileNumber));

        if (preg_match('/^(?<prefix>[A-Z]+)-(?<year>\d{4})-(?<serial>\d+)/', $normalized, $matches)) {
            return [
                'prefix' => $matches['prefix'],
                'year' => (int) $matches['year'],
                'serial' => (int) $matches['serial'],
            ];
        }

        return null;
    }

    protected function extractGknComponents(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));

        $type = 'GKN';
        if (str_starts_with($normalized, 'LPKN')) {
            $type = 'LPKN';
        } elseif (str_starts_with($normalized, 'MISCS')) {
            $type = 'MISCS';
        } elseif (str_starts_with($normalized, 'MISC')) {
            $type = 'MISCS';
        }

        $year = null;
        if (preg_match('/\b(19|20)\d{2}\b/', $normalized, $matches)) {
            $year = (int) $matches[0];
        }
        if ($year === null) {
            $year = (int) date('Y');
        }

        $serial = null;
        if (preg_match('/(\d+)(?!.*\d)/', $normalized, $matches)) {
            $serial = (int) $matches[1];
        }

        return [
            'type' => $type,
            'year' => $year,
            'serial' => $serial,
        ];
    }

    protected function encodeRelatedNumbers(array $numbers, $fallback = null): ?string
    {
        $filtered = array_values(array_filter($numbers, static fn($value) => !empty($value)));
        if (!empty($filtered)) {
            return json_encode(array_values(array_unique($filtered)));
        }

        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        return null;
    }
}
