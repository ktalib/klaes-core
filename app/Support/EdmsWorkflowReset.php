<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Preserved documents remain readable without carrying old progress into a reset. */
class EdmsWorkflowReset
{
    public static function baseline(): ?array
    {
        $path = storage_path('app/edms-workflow-reset.json');
        if (!is_file($path)) {
            return null;
        }

        $value = json_decode(file_get_contents($path), true);

        return is_array($value) && isset($value['reset_at'], $value['scannings_max_id'], $value['pagetypings_max_id'])
            ? $value : null;
    }

    public static function sinceReset($query, string $table, ?array $baseline = null)
    {
        $baseline ??= self::baseline();
        if (!$baseline) {
            return $query;
        }

        return $query->where(function ($q) use ($table, $baseline) {
            $q->where($table . '.id', '>', (int) $baseline[$table . '_max_id'])
                ->orWhere($table . '.updated_at', '>', $baseline['reset_at']);
        });
    }

    public static function counts(string $table, $fileIds, array $baseline)
    {
        if ($fileIds->isEmpty()) {
            return collect();
        }

        return self::sinceReset(DB::connection('sqlsrv')->table($table), $table, $baseline)
            ->whereIn('file_indexing_id', $fileIds)
            ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
            ->groupBy('file_indexing_id')->pluck('total', 'file_indexing_id');
    }
}
