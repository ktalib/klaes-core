<?php

namespace App\Services\RecordVersions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Record State Versions for the Indexed Files table, read from file_snapshots.
 *
 * FileSnapshotService already writes a full version of the file on every indexing
 * save (since 2026-08-27). The table only shows a version for an EDIT that changed
 * something the table prints: snapshots written by transaction capture, and edit
 * saves that only moved the timestamps, are skipped.
 *
 * The first snapshot is the baseline (V1). For a file indexed after snapshots
 * began it is the file as indexed; for an older file it is the state saved by its
 * first edit since then, because nothing earlier was ever kept.
 */
class IndexingVersionHistory
{
    /** Snapshot payload fields => file_indexings columns the table's cells are drawn from. */
    public const FIELDS = [
        'identity.file_number' => 'file_number',
        'identity.file_title' => 'file_title',
        'identity.temp_file_no' => 'temp_file_no',
        'identity.mls_file_no' => 'mls_file_no',
        'identity.kangis_file_no' => 'kangis_file_no',
        'identity.new_kangis_file_no' => 'new_kangis_file_no',
        'identity.kangis_fileno_placeholder' => 'kangis_fileno_placeholder',
        'identity.registry' => 'registry',
        'identity.general_registry' => 'general_registry',
        'identity.physical_registry' => 'physical_registry',
        'property.land_use_type' => 'land_use_type',
        'property.plot_number' => 'plot_number',
        'property.tp_no' => 'tp_no',
        'property.lpkn_no' => 'lpkn_no',
        'property.location' => 'location',
        'property.district' => 'district',
        'property.lga' => 'lga',
        'property.latitude' => 'latitude',
        'property.longitude' => 'longitude',
        'indexing.shelf_location' => 'shelf_location',
        'indexing.registry_batch_no' => 'registry_batch_no',
        'indexing.sys_batch_no' => 'sys_batch_no',
        'indexing.batch_no' => 'batch_no',
    ];

    /** Carried onto each version so its Updated By / Indexed By cells read as they did then. */
    private const STAMPS = [
        'indexing.created_at' => 'created_at',
        'indexing.created_by' => 'created_by',
        'indexing.updated_at' => 'updated_at',
        'indexing.updated_by' => 'updated_by',
    ];

    private const EDIT_SOURCE = 'file_indexing.update';

    private ?bool $tableExists = null;

    public function available(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::connection('sqlsrv')->hasTable('file_snapshots');
            } catch (\Throwable $e) {
                $this->tableExists = false;
            }
        }

        return $this->tableExists;
    }

    /**
     * Current version number per file for one page of the list (1 when never edited).
     *
     * @param  int[] $ids
     * @return array<int, int>
     */
    public function counts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || !$this->available()) {
            return [];
        }

        try {
            return DB::connection('sqlsrv')->table('file_snapshots')
                ->whereIn('file_indexing_id', $ids)
                ->orderBy('file_indexing_id')->orderBy('version')
                ->get(['file_indexing_id', 'version', 'source', 'changes'])
                ->groupBy('file_indexing_id')
                ->map(fn (Collection $rows) => $this->shown($rows)->count())
                ->all();
        } catch (\Throwable $e) {
            Log::warning('IndexingVersionHistory::counts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * The versions the table shows for one file, oldest first: each with the column
     * values to draw it from and who/when.
     */
    public function versions(int $id): array
    {
        if (!$this->available()) {
            return [];
        }

        $rows = DB::connection('sqlsrv')->table('file_snapshots')
            ->where('file_indexing_id', $id)
            ->orderBy('version')
            ->get(['id', 'version', 'source', 'changes', 'payload', 'performed_by_name', 'performed_at']);

        $shown = $this->shown($rows)->values();

        return $shown->map(function ($snap, int $i) {
            $payload = json_decode((string) $snap->payload, true) ?: [];
            $columns = [];
            foreach (self::FIELDS + self::STAMPS as $path => $column) {
                [$section, $key] = explode('.', $path, 2);
                // The payload leaves empty values out, so a missing field was blank.
                $columns[$column] = $payload[$section][$key] ?? null;
            }

            return [
                'version' => $i + 1,
                'kind' => $i > 0 ? 'edit' : ($snap->source === 'file_indexing.store' ? 'indexed' : 'earliest'),
                'by' => $snap->performed_by_name ?: null,
                'at' => $snap->performed_at ? substr((string) $snap->performed_at, 0, 16) : null,
                'columns' => $columns,
            ];
        })->all();
    }

    /** The baseline, then every edit save that changed a field the table prints. */
    private function shown(Collection $rows): Collection
    {
        $visible = array_keys(self::FIELDS);

        return $rows->values()->filter(function ($snap, int $i) use ($visible) {
            if ($i === 0) {
                return true;
            }
            if ($snap->source !== self::EDIT_SOURCE) {
                return false;
            }
            foreach (json_decode((string) $snap->changes, true) ?: [] as $change) {
                if (in_array($change['path'] ?? '', $visible, true)) {
                    return true;
                }
            }

            return false;
        });
    }
}
