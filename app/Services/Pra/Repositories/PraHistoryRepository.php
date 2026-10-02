<?php

namespace App\Services\Pra\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PraHistoryRepository
{
    private const PRA_TABLE = 'pra';
    private const HISTORY_TABLE = 'file_history_staging';

    public function timelineForPropId(string $propId): array
    {
        $history = DB::connection('sqlsrv')
            ->table(self::HISTORY_TABLE)
            ->where('prop_id', $propId)
            ->orderByDesc('reg_date')
            ->orderByDesc('reg_time')
            ->limit(100)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        if (!empty($history)) {
            return $history;
        }

        $query = DB::connection('sqlsrv')
            ->table(self::PRA_TABLE)
            ->where('prop_id', $propId)
            ->limit(100);

        if (Schema::connection('sqlsrv')->hasColumn(self::PRA_TABLE, 'created_at')) {
            $query->orderByDesc('created_at');
        } elseif (Schema::connection('sqlsrv')->hasColumn(self::PRA_TABLE, 'updated_at')) {
            $query->orderByDesc('updated_at');
        } elseif (Schema::connection('sqlsrv')->hasColumn(self::PRA_TABLE, 'id')) {
            $query->orderByDesc('id');
        }

        return $query
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }
}
