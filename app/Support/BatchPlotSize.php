<?php

namespace App\Support;

use App\Models\LandRecommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchPlotSize
{
    /** Resolve inherited/indexed sizes, then validate the entire batch before any writes. */
    public static function validate(array $data, iterable $existing = []): array
    {
        $key = static fn ($number) => mb_strtoupper(trim((string) $number));
        $saved = collect($existing)->keyBy(fn ($row) => $key($row->file_number));
        $numbers = collect($data['children'])->pluck('file_number')->map($key)->unique()->values()->all();
        $indexed = [];
        // Chunk for SQL Server's parameter limit on large batches.
        foreach (array_chunk($numbers, 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $chunk)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->get(['file_number', 'plot_size']);
            foreach ($rows as $row) {
                $size = PlotSize::parse($row->plot_size);
                if ($size !== null) {
                    $indexed[$key($row->file_number)] ??= $size;
                }
            }
        }

        $errors = [];
        foreach ($data['children'] as $index => &$child) {
            $number = $key($child['file_number']);
            $record = $saved->get($number);
            $issued = $record && $record->rofo_status === LandRecommendation::ROFO_GENERATED;
            // Issued files retain their saved value unless an explicit correction
            // is posted. A blank child value never inherits another file's size.
            $size = $indexed[$number] ?? (array_key_exists('area_sqm', $child)
                ? $child['area_sqm']
                : ($issued ? $record->area_sqm : ($data['area_sqm'] ?? null)));
            if (!is_numeric($size) || !is_finite((float) $size) || (float) $size <= 0) {
                $errors["children.{$index}.area_sqm"] = "Plot Size is required for {$child['file_number']} and must be greater than 0 square metres.";
                continue;
            }
            $child['area_sqm'] = $size;
        }
        unset($child);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        return $data;
    }
}
