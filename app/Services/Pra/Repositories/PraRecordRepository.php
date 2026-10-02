<?php

namespace App\Services\Pra\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PraRecordRepository
{
    private const TABLE = 'pra';
    private const MASTER_TABLE = 'PropID_Master';

    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    private ?bool $masterTableExists = null;

    /** @var array<string, string> */
    private array $logicalColumnCache = [];

    private ?string $masterOrderColumn = null;

    private ?array $tableColumns = null;

    public function search(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $connection = DB::connection('sqlsrv');
        $query = $connection->table(self::TABLE);

        $this->applyFilters($query, $filters);

        $countQuery = clone $query;
        $total = $countQuery->count();

        [$sortColumn, $sortDirection] = $this->resolveSort($filters['sort'] ?? null, $filters['order'] ?? null);

        $records = $query
            ->orderBy($sortColumn, $sortDirection)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($row) => (array) $row);

        $enriched = $this->withMasterIdentifiers($records);

        return new Paginator(
            $enriched->values()->all(),
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function findByPropId(string $propId): ?array
    {
        return $this->latestRowForPropId($propId);
    }

    public function findByFileNumber(string $fileNumber): ?array
    {
        $records = $this->findAllByFileNumber($fileNumber);

        return $records->first();
    }

    public function findAllByFileNumber(string $fileNumber): Collection
    {
        $normalized = $this->normalizeFileNumber($fileNumber);
        if ($normalized === '') {
            return collect();
        }

        $connection = DB::connection('sqlsrv');
        $grammar = $connection->getQueryGrammar();
        $fileColumns = $this->fileNumberColumns();
        $results = collect();

        if (!empty($fileColumns)) {
            $query = $connection
                ->table(self::TABLE)
                ->where(function ($builder) use ($fileColumns, $normalized, $grammar) {
                    foreach ($fileColumns as $column) {
                        $builder->orWhereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$normalized]);
                    }
                });

            $this->applyLatestOrdering($query);

            $rows = $query->get();
            foreach ($rows as $row) {
                $results->push((array) $row);
            }
        }

        if ($this->masterTableExists()) {
            $masterRows = $connection
                ->table(self::MASTER_TABLE)
                ->select('prop_id')
                ->where(function ($builder) use ($normalized, $grammar) {
                    foreach ($this->masterFileNumberColumns() as $column) {
                        $builder->orWhereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$normalized]);
                    }
                })
                ->orderByDesc($this->masterOrderColumn())
                ->get();

            foreach ($masterRows as $masterRow) {
                if ($masterRow->prop_id) {
                    // Check if we already have this prop_id in results to avoid duplicates
                    $exists = $results->contains(fn($r) => (string) ($r['prop_id'] ?? '') === (string) $masterRow->prop_id);
                    if (!$exists) {
                        $record = $this->latestRowForPropId((string) $masterRow->prop_id);
                        if ($record) {
                            $results->push($record);
                        }
                    }
                }
            }
        }

        return $results;
    }

    public function create(array $attributes): ?array
    {
        $connection = DB::connection('sqlsrv');

        return $connection->transaction(function () use ($connection, $attributes) {
            $payload = $this->filterColumns($attributes);

            $now = now();

            if ($this->columnExists('created_at') && !array_key_exists('created_at', $payload)) {
                $payload['created_at'] = $now;
            }

            if ($this->columnExists('updated_at') && !array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = $now;
            }

            if ($this->columnExists('id')) {
                $id = $connection->table(self::TABLE)->insertGetId($payload);

                return $this->findById((int) $id);
            }

            $connection->table(self::TABLE)->insert($payload);

            if (isset($payload['prop_id'])) {
                $record = $this->findByPropId((string) $payload['prop_id']);
                if ($record !== null) {
                    return $record;
                }
            }

            foreach ($this->fileNumberColumns() as $column) {
                if (!empty($payload[$column])) {
                    $record = $this->findByFileNumber((string) $payload[$column]);
                    if ($record !== null) {
                        return $record;
                    }
                }
            }

            return $payload;
        });
    }

    public function updateByPropId(string $propId, array $attributes): ?array
    {
        return DB::connection('sqlsrv')->transaction(function () use ($propId, $attributes) {
            $target = $this->latestRowForPropId($propId);

            if ($target === null) {
                return null;
            }

            $payload = $this->filterColumns($attributes);

            if ($payload === []) {
                return $target;
            }

            if ($this->columnExists('updated_at') && !array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = now();
            }

            $query = DB::connection('sqlsrv')->table(self::TABLE);

            if (isset($target['id']) && $this->columnExists('id')) {
                $query->where('id', $target['id']);
            } else {
                $query->where('prop_id', $propId);
            }

            $query->update($payload);

            $updatedPropId = $payload['prop_id'] ?? $target['prop_id'] ?? $propId;

            $record = $this->latestRowForPropId((string) $updatedPropId);
            if ($record !== null) {
                return $record;
            }

            return $this->latestRowForPropId($propId);
        });
    }

    /**
     * Update shared property-detail columns on ALL rows matching a prop_id.
     *
     * Only the property-level fields that are common across transaction types
     * (OP, Transfer of Title, etc.) are touched; transaction-specific columns
     * like parties, dates, registration details are left untouched.
     */
    public function updatePropertyDetailsByPropId(string $propId, array $propertyFields): int
    {
        $sharedColumns = [
            'plot_no', 'tp_no', 'lpkn_no', 'plot_size',
            'house_no', 'lgsaOrCity', 'location',
            'property_description', 'land_use', 'purpose',
        ];

        $payload = [];
        foreach ($sharedColumns as $col) {
            if (array_key_exists($col, $propertyFields)) {
                $payload[$col] = $propertyFields[$col];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload = $this->filterColumns($payload);

        if (empty($payload)) {
            return 0;
        }

        if ($this->columnExists('updated_at')) {
            $payload['updated_at'] = now();
        }

        return DB::connection('sqlsrv')
            ->table(self::TABLE)
            ->where('prop_id', $propId)
            ->update($payload);
    }

    public function appendMasterIdentifiers(array $record): array
    {
        $propId = $record['prop_id'] ?? null;
        if ($propId === null || !$this->masterTableExists()) {
            return $record;
        }

        $masterMap = $this->fetchMasterRows([(string) $propId]);

        if (!empty($masterMap)) {
            $key = (string) $propId;
            if (isset($masterMap[$key])) {
                $record['master'] = $masterMap[$key];
            }
        }

        return $record;
    }

    public function findDuplicates(string $propId): array
    {
        $source = $this->findByPropId($propId);
        if ($source === null) {
            return [];
        }

        $connection = DB::connection('sqlsrv');
        $grammar = $connection->getQueryGrammar();

        $bucket = [];

        $regDateColumn = $this->resolveColumn('reg_date');
        $registrarKeys = [
            'serialNo' => $source['serialNo'] ?? null,
            'pageNo' => $source['pageNo'] ?? null,
            'volumeNo' => $source['volumeNo'] ?? null,
        ];

        if ($registrarKeys['serialNo'] && $registrarKeys['pageNo'] && $registrarKeys['volumeNo']) {
            $registrarQuery = $connection
                ->table(self::TABLE)
                ->where('prop_id', '!=', $propId)
                ->where('serialNo', $registrarKeys['serialNo'])
                ->where('pageNo', $registrarKeys['pageNo'])
                ->where('volumeNo', $registrarKeys['volumeNo']);

            $regDateValue = $source[$regDateColumn] ?? null;
            if ($regDateValue) {
                $registrarQuery->where($regDateColumn, $regDateValue);
            }

            $this->applyLatestOrdering($registrarQuery);

            $matches = $registrarQuery
                ->limit(25)
                ->get();

            foreach ($matches as $row) {
                $this->addDuplicate($bucket, (array) $row, 'registrar_match', 0.9);
            }
        }

        foreach ($this->fileNumberColumns() as $column) {
            $value = $source[$column] ?? null;
            if (!$value) {
                continue;
            }

            $normalized = $this->normalizeFileNumber($value);
            if ($normalized === '') {
                continue;
            }

            $query = $connection
                ->table(self::TABLE)
                ->where('prop_id', '!=', $propId)
                ->whereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$normalized]);

            $this->applyLatestOrdering($query);

            $matches = $query
                ->limit(25)
                ->get();

            foreach ($matches as $row) {
                $this->addDuplicate($bucket, (array) $row, 'file_number_collision', 0.8);
            }
        }

        if (empty($bucket)) {
            return [];
        }

        $records = collect($bucket)->map(fn($entry) => $entry['record']);
        $enrichedRecords = $this->withMasterIdentifiers($records);

        foreach ($bucket as $key => &$entry) {
            $entry['record'] = $enrichedRecords->get($key, $entry['record']);
            $entry['reasons'] = array_values(array_unique($entry['reasons']));
        }
        unset($entry);

        return array_values($bucket);
    }

    private function latestRowForPropId(string $propId): ?array
    {
        $query = DB::connection('sqlsrv')
            ->table(self::TABLE)
            ->where('prop_id', $propId);

        $this->applyLatestOrdering($query);

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    private function findById(int $id): ?array
    {
        $row = DB::connection('sqlsrv')
            ->table(self::TABLE)
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }

    private function filterColumns(array $attributes): array
    {
        $columns = $this->tableColumns();
        if (empty($columns)) {
            return $attributes;
        }

        $allowed = array_flip($columns);
        // SQL Server column names can be returned in a different case by
        // Schema::getColumnListing(). Normalize matching so payload keys like
        // mlsFNo still map to table columns like mlsfNo.
        $allowedLowerToActual = [];
        foreach ($columns as $columnName) {
            $allowedLowerToActual[strtolower((string) $columnName)] = $columnName;
        }

        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (isset($allowed[$key])) {
                $filtered[$key] = $value;
                continue;
            }

            $lowerKey = strtolower((string) $key);
            if (isset($allowedLowerToActual[$lowerKey])) {
                $actualColumn = $allowedLowerToActual[$lowerKey];
                $filtered[$actualColumn] = $value;
            }
        }

        return $filtered;
    }

    private function applyFilters($query, array $filters): void
    {
        $connection = DB::connection('sqlsrv');
        $grammar = $connection->getQueryGrammar();

        $propIds = collect($filters['prop_ids'] ?? [])
            ->filter(fn($value) => $value !== null && $value !== '')
            ->map(fn($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        if (!empty($propIds)) {
            $query->whereIn('prop_id', $propIds);
        }

        $fileNumbers = collect($filters['file_numbers'] ?? [])
            ->map(fn($value) => $this->normalizeFileNumber($value))
            ->filter(fn($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if (!empty($fileNumbers)) {
            $fileColumns = $this->fileNumberColumns();

            if (!empty($fileColumns)) {
                $query->where(function ($builder) use ($fileColumns, $fileNumbers, $grammar) {
                    foreach ($fileNumbers as $number) {
                        $builder->orWhere(function ($subQuery) use ($fileColumns, $number, $grammar) {
                            foreach ($fileColumns as $column) {
                                $subQuery->orWhereRaw('UPPER(' . $grammar->wrap($column) . ') = ?', [$number]);
                            }
                        });
                    }
                });
            }
        }

        if (!empty($filters['party'])) {
            $partyTerm = '%' . $this->escapeLike((string) $filters['party']) . '%';
            $partyColumns = array_filter($this->partyColumns(), fn($column) => $this->columnExists($column));

            if (!empty($partyColumns)) {
                $query->where(function ($builder) use ($partyColumns, $partyTerm) {
                    foreach ($partyColumns as $column) {
                        $builder->orWhere($column, 'LIKE', $partyTerm);
                    }
                });
            }
        }

        if (!empty($filters['location'])) {
            $locationTerm = '%' . $this->escapeLike((string) $filters['location']) . '%';
            $locationColumns = array_filter(
                ['property_description', 'location', 'plot_no', 'lgsaOrCity'],
                fn($column) => $this->columnExists($column)
            );

            if (!empty($locationColumns)) {
                $query->where(function ($builder) use ($locationColumns, $locationTerm) {
                    foreach ($locationColumns as $column) {
                        $builder->orWhere($column, 'LIKE', $locationTerm);
                    }
                });
            }
        }

        if (!empty($filters['land_use'])) {
            $query->where($this->resolveColumn('land_use'), $filters['land_use']);
        }

        if (!empty($filters['instrument_type'])) {
            $query->where($this->resolveColumn('instrument_type'), 'LIKE', '%' . $this->escapeLike((string) $filters['instrument_type']) . '%');
        }

        if (!empty($filters['registration_date_from'])) {
            $query->where($this->resolveColumn('reg_date'), '>=', $filters['registration_date_from']);
        }

        if (!empty($filters['registration_date_to'])) {
            $query->where($this->resolveColumn('reg_date'), '<=', $filters['registration_date_to']);
        }

        if (!empty($filters['transaction_date_from'])) {
            $query->where($this->resolveColumn('transaction_date'), '>=', $filters['transaction_date_from']);
        }

        if (!empty($filters['transaction_date_to'])) {
            $query->where($this->resolveColumn('transaction_date'), '<=', $filters['transaction_date_to']);
        }
    }

    private function withMasterIdentifiers(Collection $records): Collection
    {
        if ($records->isEmpty() || !$this->masterTableExists()) {
            return $records;
        }

        $propIds = $records
            ->pluck('prop_id')
            ->filter(fn($value) => $value !== null && $value !== '')
            ->map(fn($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        $masterMap = $this->fetchMasterRows($propIds);

        return $records->map(function (array $record) use ($masterMap) {
            $propId = $record['prop_id'] ?? null;
            if ($propId !== null) {
                $key = (string) $propId;
                if (isset($masterMap[$key])) {
                    $record['master'] = $masterMap[$key];
                }
            }

            return $record;
        });
    }

    private function fetchMasterRows(array $propIds): array
    {
        if (empty($propIds) || !$this->masterTableExists()) {
            return [];
        }

        $rows = DB::connection('sqlsrv')
            ->table(self::MASTER_TABLE)
            ->select(
                'prop_id',
                'primary_file_number',
                'mlsFNo',
                'kangisFileNo',
                'NewKANGISFileno',
                'temp_fileno'
            )
            ->whereIn('prop_id', $propIds)
            ->orderByDesc($this->masterOrderColumn())
            ->get();

        $masterMap = [];

        foreach ($rows as $row) {
            $key = (string) ($row->prop_id ?? '');
            if ($key === '' || isset($masterMap[$key])) {
                continue;
            }

            $masterMap[$key] = [
                'primary_file_number' => $row->primary_file_number ?? null,
                'mlsFNo' => $row->mlsFNo ?? null,
                'kangisFileNo' => $row->kangisFileNo ?? null,
                'NewKANGISFileno' => $row->NewKANGISFileno ?? null,
                'temp_fileno' => $row->temp_fileno ?? null,
            ];
        }

        return $masterMap;
    }

    private function applyLatestOrdering($query): void
    {
        foreach (['updated_at', 'created_at'] as $logicalColumn) {
            $column = $this->resolveColumn($logicalColumn);
            if ($this->columnExists($column)) {
                $query->orderByDesc($column);

                return;
            }
        }

        if ($this->columnExists('id')) {
            $query->orderByDesc('id');
        }
    }

    private function addDuplicate(array &$bucket, array $row, string $reason, float $score): void
    {
        $key = $row['prop_id'] ?? null;
        $bucketKey = $key !== null ? (string) $key : spl_object_hash((object) $row);

        if (!isset($bucket[$bucketKey])) {
            $bucket[$bucketKey] = [
                'prop_id' => $row['prop_id'] ?? null,
                'score' => $score,
                'reasons' => [$reason],
                'record' => $row,
            ];

            return;
        }

        $bucket[$bucketKey]['score'] = max($bucket[$bucketKey]['score'], $score);
        $bucket[$bucketKey]['reasons'][] = $reason;
    }

    private function resolveSort(?string $sort, ?string $order): array
    {
        $sortKey = strtolower($sort ?? 'created_at');
        $allowed = [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'reg_date' => 'reg_date',
            'transaction_date' => 'transaction_date',
            'mlsfno' => 'mlsFNo',
            'prop_id' => 'prop_id',
        ];

        if (!array_key_exists($sortKey, $allowed)) {
            $sortKey = 'created_at';
        }

        $column = $allowed[$sortKey];
        $direction = strtolower($order ?? 'desc');
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $resolved = $this->resolveColumn($column);
        if (!$this->columnExists($resolved)) {
            if ($this->columnExists('prop_id')) {
                $resolved = 'prop_id';
            } elseif ($this->columnExists('id')) {
                $resolved = 'id';
            }
        }

        return [$resolved, $direction];
    }

    private function normalizeFileNumber(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper(trim((string) $value));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_[]');
    }

    private function resolveColumn(string $column): string
    {
        if (isset($this->logicalColumnCache[$column])) {
            return $this->logicalColumnCache[$column];
        }

        $candidates = match ($column) {
            'transaction_type' => ['transaction_type', 'transactionType'],
            'transaction_date' => ['transaction_date', 'transactionDate'],
            'instrument_type' => ['instrument_type', 'instrumentType'],
            'reg_date' => ['reg_date', 'regDate'],
            'reg_time' => ['reg_time', 'regTime'],
            'land_use' => ['land_use', 'landUse'],
            'created_at' => ['created_at', 'createdAt'],
            'updated_at' => ['updated_at', 'updatedAt'],
            default => [$column],
        };

        foreach ($candidates as $candidate) {
            if ($this->columnExists($candidate)) {
                return $this->logicalColumnCache[$column] = $candidate;
            }
        }

        return $this->logicalColumnCache[$column] = $candidates[0];
    }

    private function fileNumberColumns(): array
    {
        $columns = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'np_fileno', 'temp_fileno'];

        return array_values(array_filter($columns, fn($column) => $this->columnExists($column)));
    }

    private function masterFileNumberColumns(): array
    {
        return ['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'];
    }

    private function partyColumns(): array
    {
        return [
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'Grantor',
            'Grantee',
            'MortgagorName',
            'MortgageeName',
        ];
    }

    private function masterOrderColumn(): string
    {
        if ($this->masterOrderColumn !== null) {
            return $this->masterOrderColumn;
        }

        $candidates = ['updated_at', 'created_at', 'id'];

        foreach ($candidates as $candidate) {
            try {
                if (Schema::connection('sqlsrv')->hasColumn(self::MASTER_TABLE, $candidate)) {
                    return $this->masterOrderColumn = $candidate;
                }
            } catch (\Throwable $exception) {
                // Ignore and try next candidate
            }
        }

        return $this->masterOrderColumn = 'prop_id';
    }

    private function tableColumns(): array
    {
        if ($this->tableColumns !== null) {
            return $this->tableColumns;
        }

        try {
            $this->tableColumns = Schema::connection('sqlsrv')->getColumnListing(self::TABLE);
        } catch (\Throwable $exception) {
            $this->tableColumns = [];
        }

        return $this->tableColumns;
    }

    private function columnExists(string $column): bool
    {
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }

        if (in_array($column, $this->tableColumns() ?? [], true)) {
            return $this->columnExistsCache[$column] = true;
        }

        try {
            return $this->columnExistsCache[$column] = Schema::connection('sqlsrv')->hasColumn(self::TABLE, $column);
        } catch (\Throwable $exception) {
            $this->columnExistsCache[$column] = false;

            return false;
        }
    }

    private function masterTableExists(): bool
    {
        if ($this->masterTableExists !== null) {
            return $this->masterTableExists;
        }

        try {
            $this->masterTableExists = Schema::connection('sqlsrv')->hasTable(self::MASTER_TABLE);
        } catch (\Throwable $exception) {
            $this->masterTableExists = false;
        }

        return $this->masterTableExists;
    }
}
