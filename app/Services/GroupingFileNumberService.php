<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupingFileNumberService
{
    public function linkAwaitingToMls(string $fileNumber, ?string $awaitingFileNumber = null, string $registryType = 'Lands Registry'): array
    {
        $fileNumber = trim($fileNumber);
        $awaitingFileNumber = $awaitingFileNumber ? trim($awaitingFileNumber) : $fileNumber;
        
        $res = $this->bulkLinkAwaitingToMls([$awaitingFileNumber], true, $registryType, $fileNumber);
        
        return $res[$awaitingFileNumber] ?? [
            'status' => 'error',
            'record' => null,
            'meta' => ['message' => 'Processing failed for ' . $awaitingFileNumber]
        ];
    }

    /**
     * Bulk link multiple file numbers to grouping records
     * 
     * @param array $fileNumbers
     * @param bool $returnFullRecords If false, skips fetching the updated record to save performance
     */
    public function bulkLinkAwaitingToMls(array $fileNumbers, bool $returnFullRecords = true, string $registryType = 'Lands Registry', ?string $overrideMls = null): array
    {
        $fileNumbers = array_unique(array_filter(array_map('trim', $fileNumbers)));
        if (empty($fileNumbers)) {
            return [];
        }

        $results = [];
        $connection = DB::connection('sqlsrv');

        // Auto-detect table based on the first file number to support bulk multi-table 
        // while assuming consistent batch registry (standard for batch ops)
        $firstFile = reset($fileNumbers);
        $table = $this->getTableName($registryType, $firstFile);
        $fileNoColumn = $this->getColumnNameByTable($table);
        $mlsColumn = $this->getMlsColumnName($table);

        // 1. Bulk Fetch all matching records using simple WHERE IN (Indexed and Fast)
        $records = $connection->table($table)
            ->whereIn($fileNoColumn, $fileNumbers)
            ->select($this->selectColumns($table, $fileNoColumn))
            ->get()
            ->groupBy($fileNoColumn);

        $now = Carbon::now();
        $idsToUpdate = [];
        $updateData = [];

        foreach ($fileNumbers as $fullFileNumber) {
            $matchingRecords = $records->get($fullFileNumber);

            if (!$matchingRecords || $matchingRecords->isEmpty()) {
                $results[$fullFileNumber] = [
                    'status' => 'not_found',
                    'record' => null,
                    'meta' => ['search_value' => $fullFileNumber]
                ];
                continue;
            }

            // If multiple matches, take the latest one
            $record = $matchingRecords->sortByDesc('updated_at')->first();

            $currentMapping = $record->mapping !== null ? (int) $record->mapping : null;
            $currentMls = $record->mls_fileno ?? null;

            // Conflict check - REMOVED per user request to allow re-mapping
            /*
            if ($currentMapping === 1 && !$this->stringsEqual($currentMls, $fullFileNumber) && !empty($currentMls)) {
                $results[$fullFileNumber] = [
                    'status' => 'conflict',
                    'record' => $this->formatRecord($record),
                    'meta' => [
                        'search_value' => $fullFileNumber,
                        'existing_mls_fileno' => $currentMls
                    ]
                ];
                continue;
            }
            */

            if (!$this->stringsEqual($currentMls, $fullFileNumber) || $currentMapping !== 1) {
                $entry = [
                    'id' => $record->id,
                    'mls_fileno' => $overrideMls ?? $fullFileNumber,
                ];
                // Auto-generate tracking_id if missing
                $hasTrackingId = !empty($record->tracking_id ?? null);
                if (!$hasTrackingId) {
                    $entry['tracking_id'] = 'TRK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8)) . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
                }
                // Queue for bulk update
                $updateData[] = $entry;
                $results[$fullFileNumber] = [
                    'status' => 'updated',
                    'record' => $this->formatRecord($record),
                    'meta' => ['search_value' => $fullFileNumber]
                ];
            } else {
                // Even if unchanged, auto-generate tracking_id if missing
                $hasTrackingId = !empty($record->tracking_id ?? null);
                if (!$hasTrackingId) {
                    $entry = [
                        'id' => $record->id,
                        'mls_fileno' => $currentMls ?? $fullFileNumber,
                        'tracking_id' => 'TRK-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8)) . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5)),
                    ];
                    $updateData[] = $entry;
                }
                $results[$fullFileNumber] = [
                    'status' => 'unchanged',
                    'record' => $this->formatRecord($record),
                    'meta' => ['search_value' => $fullFileNumber]
                ];
            }
        }

        // 2. Perform Single High-Speed Bulk Update if needed
        if (!empty($updateData)) {
            $ids = array_column($updateData, 'id');
            $caseMls = "CASE id ";
            foreach ($updateData as $row) {
                $quotedMls = $connection->getPdo()->quote($row['mls_fileno']);
                $caseMls .= "WHEN {$row['id']} THEN {$quotedMls} ";
            }
            $caseMls .= "ELSE [{$mlsColumn}] END";

            // Build tracking_id CASE for rows that need one auto-generated
            $trackingUpdates = array_filter($updateData, fn($r) => isset($r['tracking_id']));
            $updatePayload = [
                $mlsColumn => DB::raw($caseMls),
                'mapping' => 1,
                'updated_at' => $now,
            ];
            if (!empty($trackingUpdates)) {
                $caseTrk = "CASE id ";
                foreach ($trackingUpdates as $row) {
                    $quotedTrk = $connection->getPdo()->quote($row['tracking_id']);
                    $caseTrk .= "WHEN {$row['id']} THEN {$quotedTrk} ";
                }
                $caseTrk .= "ELSE [tracking_id] END";
                $updatePayload['tracking_id'] = DB::raw($caseTrk);
            }

            $connection->table($table)
                ->whereIn('id', $ids)
                ->update($updatePayload);

            // If user needs the updated records in the response, we must refresh them
            if ($returnFullRecords) {
                $refreshedRecords = $connection->table($table)
                    ->whereIn('id', $ids)
                    ->select($this->selectColumns($table, $fileNoColumn))
                    ->get();

                foreach ($refreshedRecords as $refreshed) {
                    $fn = $refreshed->$fileNoColumn;
                    if (isset($results[$fn])) {
                        $results[$fn]['record'] = $this->formatRecord($refreshed);
                    }
                }
            }
        }

        // Clean up response if returnFullRecords is false
        if (!$returnFullRecords) {
            foreach ($results as $fn => $res) {
                $results[$fn] = ['status' => $res['status']];
            }
        }

        return $results;
    }


    public function findGroupingRecord($connection, string $original, string $normalized, string $registryType = 'Lands Registry'): array
    {
        // Pass original file number to help detect table if registry is generic
        $table = $this->getTableName($registryType, $original);

        // Derive column name from the resolved table
        $fileNoColumn = $this->getColumnNameByTable($table);

        $columns = $this->selectColumns($table, $fileNoColumn);

        $record = $connection->table($table)
            ->select($columns)
            ->where($fileNoColumn, $original)
            ->orderByDesc('updated_at')
            ->first();

        if ($record) {
            return ['record' => $record, 'match_type' => 'exact', 'table' => $table];
        }

        $record = $connection->table($table)
            ->select($columns)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$fileNoColumn}), '-', ''), '/', ''), ' ', ''), '\\', ''), '.', '') = ?", [$normalized])
            ->orderByDesc('updated_at')
            ->first();

        if ($record) {
            return ['record' => $record, 'match_type' => 'normalized', 'table' => $table];
        }

        return ['record' => null, 'match_type' => 'none', 'table' => $table];
    }

    public function getTableName(string $registryType, ?string $fileNumber = null): string
    {
        $type = strtoupper($registryType);
        $f = $fileNumber ? strtoupper($fileNumber) : '';

        // Auto-detect based on file number prefix first (High Priority)
        if ($f) {
            $f = strtoupper($f);
            if (str_contains($f, 'GKN')) return 'gkn_grouping';
            if (str_contains($f, 'LPKN')) return 'lpkn_grouping';
            if (str_contains($f, 'MISC')) return 'miscs_kn_grouping';
            if (str_contains($f, 'SLTR')) return 'sltr_grouping';
            if (str_contains($f, 'SIT')) return 'sit_grouping';
            if (str_contains($f, 'DCIV')) return 'dciv_grouping';
            
            // KANGIS variations
            if (str_contains($f, 'KNML') || str_contains($f, 'MLKN') || str_contains($f, 'MNKL') || str_contains($f, 'KNGP') || str_contains($f, 'EGS')) {
                return 'kangis_grouping';
            }
            
            // KN-series awaiting file numbers (KN1, KN2 … KN9999)
            if (preg_match('/^KN\d+$/i', $f)) return 'kn_grouping';
        }

        // Check for Survey Registry
        if (str_contains($type, 'SURVEY')) {
            return 'gkn_grouping';
        }

        // Check for SLTR Registry
        if (str_contains($type, 'SLTR')) {
            return 'sltr_grouping';
        }

        if (str_contains($type, 'SIT')) {
            return 'sit_grouping';
        }
        if (str_contains($type, 'DCIV')) {
            return 'dciv_grouping';
        }
        if (str_contains($type, 'KANGIS')) {
            return 'kangis_grouping';
        }

        return 'grouping';
    }

    public function getColumnNameByTable(string $table): string
    {
        switch ($table) {
            case 'gkn_grouping':
                return 'gkn_awaiting_fileno';
            case 'lpkn_grouping':
                return 'lpkn_awaiting_fileno';
            case 'miscs_kn_grouping':
                return 'miscs_kn_awaiting_fileno';
            case 'sltr_grouping':
                return 'sltr_awaiting_fileno';
            case 'sit_grouping':
                return 'sit_awaiting_fileno';
            case 'dciv_grouping':
                return 'dciv_awaiting_fileno';
            case 'kangis_grouping':
                return 'kangis_awaiting_fileno';
            case 'kn_grouping':
                return 'kn_awaiting_fileno';
            default:
                return 'awaiting_fileno';
        }
    }

    public function getFileNoColumnName(string $registryType): string
    {
        // This method is now secondary to table-based detection
        $table = $this->getTableName($registryType);
        return $this->getColumnNameByTable($table);
    }

    public function getMlsColumnName(string $table): string
    {
        $map = [
            'gkn_grouping' => 'gkn_fileno',
            'lpkn_grouping' => 'lpkn_fileno',
            'miscs_kn_grouping' => 'miscs_kn_fileno',
            'sltr_grouping' => 'sltr_fileno',
            'sit_grouping' => 'sit_fileno',
            'dciv_grouping' => 'dciv_fileno',
            'kangis_grouping' => 'kangis_fileno',
            'kn_grouping'     => 'kn_fileno',
        ];

        return $map[$table] ?? 'mls_fileno';
    }

    public function findById(string $registryType, int $id, ?string $fileNumber = null): ?object
    {
        // Pass fileNumber so prefix auto-detection (e.g. KN1 → kn_grouping) takes priority
        // over the registry short-code (e.g. 'KN') which is not always recognised.
        $tableName = $this->getTableName($registryType, $fileNumber);
        $fileNoColumn = $this->getColumnNameByTable($tableName);
        $columns = $this->selectColumns($tableName, $fileNoColumn);

        return DB::connection('sqlsrv')->table($tableName)
            ->select($columns)
            ->where('id', $id)
            ->first();
    }

    private function normalizeValue(string $value): string
    {
        $upper = strtoupper($value);
        return str_replace(['-', '/', ' ', '\\', '.', ','], '', $upper);
    }

    private function stringsEqual(?string $first, ?string $second): bool
    {
        return strtoupper(trim((string) $first)) === strtoupper(trim((string) $second));
    }

    private function selectColumns(string $table = 'grouping', string $fileNoColumn = 'awaiting_fileno'): array
    {
        // 1. Get available columns first to avoid SQL errors on missing columns
        try {
            $availableList = array_map('strtolower', Schema::connection('sqlsrv')->getColumnListing($table));
            $availableMap = array_flip($availableList);
        } catch (\Throwable $e) {
            $availableMap = [];
        }

        // Start with ID which is common
        $columns = ['id'];

        // Add file number with alias if needed
        if ($fileNoColumn !== 'awaiting_fileno' && isset($availableMap[strtolower($fileNoColumn)])) {
            $columns[] = "{$fileNoColumn} as awaiting_fileno";
            $columns[] = $fileNoColumn;
        } elseif (isset($availableMap['awaiting_fileno'])) {
            $columns[] = 'awaiting_fileno';
        }

        // Handle MLS/Final File Number column based on table
        $mlsColumnCandidates = ['mls_fileno'];
        if ($table === 'gkn_grouping') array_unshift($mlsColumnCandidates, 'gkn_fileno');
        elseif ($table === 'sltr_grouping') array_unshift($mlsColumnCandidates, 'sltr_fileno');
        elseif ($table === 'sit_grouping') array_unshift($mlsColumnCandidates, 'sit_fileno');
        elseif ($table === 'dciv_grouping') array_unshift($mlsColumnCandidates, 'dciv_fileno');
        elseif ($table === 'lpkn_grouping') array_unshift($mlsColumnCandidates, 'lpkn_fileno');
        elseif ($table === 'miscs_kn_grouping') array_unshift($mlsColumnCandidates, 'miscs_kn_fileno');
        elseif ($table === 'kangis_grouping') array_unshift($mlsColumnCandidates, 'kangis_fileno');
        elseif ($table === 'kn_grouping') array_unshift($mlsColumnCandidates, 'kn_fileno');

        $chosenMlsColumn = 'mls_fileno';
        foreach ($mlsColumnCandidates as $candidate) {
            if (isset($availableMap[strtolower($candidate)])) {
                $chosenMlsColumn = $candidate;
                break;
            }
        }

        if ($chosenMlsColumn !== 'mls_fileno') {
            $columns[] = "{$chosenMlsColumn} as mls_fileno";
            $columns[] = $chosenMlsColumn; // Also select original
        } elseif (isset($availableMap['mls_fileno'])) {
            $columns[] = 'mls_fileno';
        }

        // Expanded list of potential columns for comprehensive data population
        $potentialColumns = [
            'tracking_id', 'mapping', 'landuse', 'year', 'registry', 'shelf_rack', 
            'updated_at', 'created_at', 'sys_batch_no', 'mdc_batch_no', 'batch_no',
            'registry_batch_no', 'group_no', 'group_no as group',
            
            // Property Identifiers
            'plot_no', 'plot_number', 'tp_no', 'tp_number', 'lpkn_no', 'location', 
            'property_location', 'district', 'lga', 'lgsaOrCity', 'land_use', 
            'land_use_type', 'plot_size',
            
            // Personal/Entity Details
            'grantor', 'grantee', 'first_party', 'second_party', 
            'entity_name', 'customer_name', 'entity_type', 'customer_type',
            'entity_physical_address', 'property_address', 'residence_address',
            'customer_account_no', 'customer_code',
            
            // Contact & ID
            'phone', 'phone_number', 'email', 'rc_no', 'nin', 'tin', 'dob',
            'country_code', 'address'
        ];

        foreach ($potentialColumns as $colDef) {
            // Handle aliasing logic simply
            if (str_contains($colDef, ' as ')) {
                [$actualCol, $alias] = explode(' as ', $colDef);
                if (isset($availableMap[strtolower(trim($actualCol))])) {
                    $columns[] = $colDef;
                }
            } else {
                if (isset($availableMap[strtolower($colDef)])) {
                    $columns[] = $colDef; 
                }
            }
        }

        return $columns;
    }

    private function formatRecord($record): array
    {
        if (!$record) {
            return [];
        }

        // Return all fields present on the record object dynamically
        // Ensure core fields are type-cast correctly if needed, but array cast captures everything selected
        $data = (array) $record;
        
        // Ensure ID is integer
        if (isset($data['id'])) {
            $data['id'] = (int) $data['id'];
        }

        return $data;
    }
}
