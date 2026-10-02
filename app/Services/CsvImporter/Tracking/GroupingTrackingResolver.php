<?php

namespace App\Services\CsvImporter\Tracking;

use App\Services\CsvImporter\Support\CsvValueNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GroupingTrackingResolver
{
    private array $columnCache = [];

    public function __construct(
        private readonly CsvValueNormalizer $normalizer,
    ) {
    }

    public function resolve(?string $fileNumber): array
    {
        $standardized = $this->normalizer->standardizeFileNumber($fileNumber);
        if ($standardized === null) {
            return [
                'status' => 'missing',
                'message' => 'File number is blank',
            ];
        }

        $normalizedKey = $this->normalizer->normalizeFileNumberForMatch($fileNumber);
        if ($normalizedKey === null) {
            return [
                'status' => 'missing',
                'file_number' => $standardized,
                'message' => 'Unable to normalize file number for grouping lookup',
            ];
        }

        $connectionName = config('csv_importer.grouping_connection', 'sqlsrv');
        $builder = DB::connection($connectionName)->table('grouping');
        $columns = $this->selectableColumns($connectionName);

        try {
            $record = $builder
                ->select($columns)
                ->where(function ($query) use ($standardized, $normalizedKey) {
                    $query->where('awaiting_fileno', $standardized)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(awaiting_fileno), '-', ''), '/', ''), ' ', ''), '\\\\', ''), '.', '') = ?", [$normalizedKey]);
                })
                ->orderByDesc('updated_at')
                ->first();
        } catch (\Throwable $exception) {
            Log::warning('GroupingTrackingResolver: lookup failed', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'file_number' => $standardized,
                'message' => 'Grouping lookup failed',
            ];
        }

        if (!$record) {
            return [
                'status' => 'missing',
                'file_number' => $standardized,
                'message' => 'Awaiting file number not found in grouping table',
            ];
        }

        return [
            'status' => 'matched',
            'file_number' => $standardized,
            'tracking_id' => $record->tracking_id ?? null,
            'registry' => $record->registry ?? null,
            'group' => $record->group ?? null,
            'awaiting_fileno' => $record->awaiting_fileno ?? null,
            'awaiting_normalized' => $normalizedKey,
            'row_id' => (int) ($record->id ?? 0),
            'metadata' => [
                'shelf_rack' => $record->shelf_rack ?? null,
                'year' => $record->year ?? null,
                'mapping' => $record->mapping ?? null,
                'sys_batch_no' => $record->sys_batch_no ?? null,
                'mdc_batch_no' => $record->mdc_batch_no ?? null,
            ],
        ];
    }

    private function selectableColumns(string $connection): array
    {
        if (isset($this->columnCache[$connection])) {
            return $this->columnCache[$connection];
        }

        $defaults = [
            'id',
            'awaiting_fileno',
            'tracking_id',
            'mapping',
            'registry',
            'group',
            'year',
            'shelf_rack',
            'sys_batch_no',
            'mdc_batch_no',
            'updated_at',
        ];

        try {
            $available = array_map('strtolower', Schema::connection($connection)->getColumnListing('grouping'));
        } catch (\Throwable $exception) {
            Log::warning('GroupingTrackingResolver: unable to list grouping columns', [
                'error' => $exception->getMessage(),
            ]);
            $available = [];
        }

        $columns = array_values(array_filter($defaults, function ($column) use ($available) {
            return in_array(strtolower($column), $available, true);
        }));

        if (!in_array('id', $columns, true)) {
            $columns[] = 'id';
        }

        if (!in_array('tracking_id', $columns, true)) {
            $columns[] = 'tracking_id';
        }

        return $this->columnCache[$connection] = $columns;
    }
}
