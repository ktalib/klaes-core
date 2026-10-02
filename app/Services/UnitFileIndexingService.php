<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UnitFileIndexingService
{
    /**
     * Ensure an EDMS file indexing row exists for the given unit/SUA payload.
     */
    public function ensure(array $input): FileIndexing
    {
        $payload = $this->normalize($input);

        if (empty($payload['subapplication_id'])) {
            throw new InvalidArgumentException('subapplication_id is required for EDMS indexing');
        }

        $existing = FileIndexing::where('subapplication_id', $payload['subapplication_id'])
            ->where('file_number', $payload['file_number'])
            ->where(function ($query) {
                $query->whereNull('is_deleted')->orWhere('is_deleted', false);
            })
            ->first();

        if ($existing) {
            $updates = [];

            if (empty($existing->file_title) && !empty($payload['file_title'])) {
                $updates['file_title'] = $payload['file_title'];
            }

            if (!is_null($payload['st_batch_no']) && $existing->st_batch_no !== $payload['st_batch_no']) {
                $updates['st_batch_no'] = $payload['st_batch_no'];
            }

            if (empty($existing->tracking_id)) {
                $updates['tracking_id'] = $payload['tracking_id'] ?? $this->generateUniqueTrackingId();
            }

            if ($updates) {
                $existing->fill($updates);
                $existing->save();
            }

            return $existing;
        }

        return DB::connection('sqlsrv')->transaction(function () use ($payload) {
            if (empty($payload['tracking_id'])) {
                $payload['tracking_id'] = $this->generateUniqueTrackingId();
            }

            return FileIndexing::create($payload);
        });
    }

    private function normalize(array $input): array
    {
        $fileTitle = $this->buildFileTitle($input);

        return [
            'main_application_id' => $input['main_application_id'] ?? null,
            'subapplication_id' => $input['subapplication_id'] ?? null,
            'file_number' => $input['file_number'] ?? null,
            'file_title' => $fileTitle,
            'land_use_type' => $input['land_use_type'] ?? null,
            'plot_number' => $input['plot_number'] ?? null,
            'tp_no' => $input['tp_no'] ?? null,
            'lpkn_no' => $input['lpkn_no'] ?? null,
            'location' => $input['location'] ?? null,
            'district' => $input['district'] ?? null,
            'lga' => $input['lga'] ?? null,
            'st_batch_no' => $this->nullIfEmpty($input['st_batch_no'] ?? null),
            'tracking_id' => $this->nullIfEmpty($input['tracking_id'] ?? null),
            'registry' => 'ST Registry',
            'physical_registry' => null,
            'has_cofo' => $input['has_cofo'] ?? false,
            'has_transaction' => $input['has_transaction'] ?? false,
            'is_problematic' => $input['is_problematic'] ?? false,
            'is_co_owned_plot' => $input['is_co_owned_plot'] ?? false,
            'is_merged' => $input['is_merged'] ?? false,
            'is_deleted' => false,
            'workflow_status' => 'indexed',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ];
    }

    private function buildFileTitle(array $input): ?string
    {
        $applicantType = $input['applicant_type'] ?? null;

        if ($applicantType === 'corporate' && !empty($input['corporate_name'])) {
            return trim($input['corporate_name']);
        }

        if (!empty($input['first_owner_name'])) {
            return trim($input['first_owner_name']);
        }

        $parts = array_filter([
            $input['first_name'] ?? null,
            $input['middle_name'] ?? null,
            $input['surname'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (!empty($parts)) {
            return trim(implode(' ', $parts));
        }

        $fallback = Arr::get($input, 'file_title');

        return $fallback ? trim($fallback) : null;
    }

    private function generateUniqueTrackingId(): string
    {
        $attempts = 0;

        do {
            $candidate = $this->generateTrackingId();
            $exists = FileIndexing::on('sqlsrv')->where('tracking_id', $candidate)->exists();
            $attempts++;
        } while ($exists && $attempts < 5);

        if ($exists) {
            throw new RuntimeException('Unable to generate unique tracking ID');
        }

        return $candidate;
    }

    private function generateTrackingId(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $part1 = '';
        for ($i = 0; $i < 4; $i++) {
            $part1 .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $part2 = '';
        for ($i = 0; $i < 8; $i++) {
            $part2 .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return "TRK-{$part1}-{$part2}";
    }

    private function nullIfEmpty($value)
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }
}
