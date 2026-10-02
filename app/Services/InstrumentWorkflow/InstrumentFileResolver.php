<?php

namespace App\Services\InstrumentWorkflow;

use App\Models\FileIndexingRecord;
use App\Services\LegacyFileNumberNormalizer;
use Illuminate\Support\Collection;

/**
 * Resolves a typed file number to the File_Indexing record(s) it names.
 *
 * ALIS stored the same number several ways (LUAC:AB/6831/AB vs LUAC/AB/6831/AB),
 * so the typed value is normalized first and matched against the corrected
 * file_number as well as the untouched legacy_file_number / AlisFileNumber.
 *
 * Deliberately never falls back to the Kano-era file_indexings table (as
 * ScheduleFileNumberService::indexedRecordId does): workflow rows key on
 * File_Indexing.id only, so the id's table is never ambiguous. When a number
 * matches more than one record (33 legacy numbers do) every candidate is
 * returned and the officer must pick one - the workflow never guesses.
 */
class InstrumentFileResolver
{
    /**
     * @return array{typed: string, normalized: ?string, records: Collection}
     */
    public function candidates(string $typed, int $limit = 10): array
    {
        $typed = trim($typed);
        $normalized = LegacyFileNumberNormalizer::normalize($typed);

        if ($typed === '') {
            return ['typed' => $typed, 'normalized' => null, 'records' => collect()];
        }

        $values = array_values(array_unique(array_filter([$typed, strtoupper($typed), $normalized])));

        $records = FileIndexingRecord::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($values) {
                $query->whereIn('file_number', $values)
                    ->orWhereIn('legacy_file_number', $values)
                    ->orWhereIn('AlisFileNumber', $values);
            })
            ->with(['propertyDetails'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return ['typed' => $typed, 'normalized' => $normalized, 'records' => $records];
    }

    public function find(int $fileIndexingId): ?FileIndexingRecord
    {
        return FileIndexingRecord::query()
            ->whereNull('deleted_at')
            ->with(['propertyDetails'])
            ->find($fileIndexingId);
    }

    /** A compact, display-ready summary of a record for pickers and snapshots. */
    public function summarize(FileIndexingRecord $record): array
    {
        return [
            'id' => $record->id,
            'file_number' => $record->file_number,
            'legacy_file_number' => $record->legacy_file_number,
            'file_title' => $record->file_title,
            'schedule' => $record->schedule,
            'land_use_type' => $record->land_use_type,
            'source' => $record->source,
            // A provisional file opened by TempFileNumberAllocator for a property
            // that has no number yet; the screens badge it so nobody mistakes the
            // TEMP number for a permanent one.
            'is_temporary' => TempFileNumberAllocator::isTemporary($record->file_number),
            'properties' => $record->propertyDetails->map(fn ($p) => [
                'file_param_index' => $p->file_param_index,
                'lga' => $p->lga,
                'district' => $p->district,
                'street_name' => $p->street_name,
                'location' => $p->location,
                'plot_number' => $p->plot_number,
                'plot_size' => $p->plot_size,
                'tp_no' => $p->tp_no,
                'lpkn_no' => $p->lpkn_no,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ])->values()->all(),
        ];
    }
}

