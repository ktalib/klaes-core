<?php

namespace App\Services\CsvImporter\FileIndexing;

use App\Models\FileIndexing;
use App\Models\FileNumber;
use App\Models\Grouping;
use App\Services\CsvImporter\Qc\FileNumberQcService;
use App\Services\CsvImporter\Support\CsvValueNormalizer;
use App\Services\CsvImporter\Tracking\GroupingTrackingResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use League\Csv\Statement;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use SplFileObject;

class FileIndexingImportService
{
    private const NUMERIC_FIELDS = [
        'registry',
        'batch_no',
        'registry_batch_no',
        'lpkn_no',
        'serial_no',
        'page_no',
        'vol_no',
    ];

    private const COLUMN_MAPPINGS = [
        'registry' => ['registry'],
        'batch_no' => ['batch no', 'batch number', 'batchno'],
        'registry_batch_no' => ['registry batch no', 'registrybatchno'],
        'file_number' => ['file number', 'filenumber', 'mls file no', 'mlsfno', 'st file no'],
        'file_title' => ['file title', 'title', 'file name', 'filename'],
        'land_use_type' => ['land use type', 'landuse', 'land use'],
        'plot_number' => ['plot number', 'plotno', 'plot num'],
        'lpkn_no' => ['lpkn no', 'lpkn'],
        'tp_no' => ['tp no', 'tp plan no', 'tp number'],
        'district' => ['district'],
        'lga' => ['lga'],
        'location' => ['location'],
        'shelf_location' => ['shelf location', 'shelf_location'],
        'cofo_date' => ['cofo date', 'cofo_date'],
        'serial_no' => ['serial no', 'serial number'],
        'page_no' => ['page no', 'page number'],
        'vol_no' => ['vol no', 'volume no'],
        'deeds_time' => ['deeds time'],
        'deeds_date' => ['deeds date'],
    ];

    private const GROUPING_PREVIEW_LIMIT = 1000;

    public function __construct(
        private readonly CsvValueNormalizer $normalizer,
        private readonly FileNumberQcService $qcService,
        private readonly GroupingTrackingResolver $groupingResolver,
    ) {
    }

    public function buildPreview(UploadedFile $file, string $testControl): array
    {
        $rows = $this->standardizeRows($this->readRows($file));
        $records = $this->buildRecords($rows);

        return $this->analyzeRecords($records, $testControl);
    }

    /**
     * @return FileIndexingRecord[]
     */
    public function recordsFromSession(array $payload): array
    {
        return array_map(fn ($row) => FileIndexingRecord::fromSessionPayload($row), $payload);
    }

    public function analyzeFromPath(string $path, string $testControl, ?callable $progressCallback = null): array
    {
        $extension = $this->extensionFromPath($path);
        $records = [];
        $processed = 0;

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $rows = $this->standardizeRows($this->readRowsFromPath($path));
            $records = $this->buildRecords($rows);
            $processed = count($rows);
            if ($progressCallback) {
                $progressCallback($processed);
            }
        } else {
            $chunkSize = 1000;
            $offset = 0;

            while (true) {
                $rows = $this->standardizeRows($this->readRowsFromPath($path, $offset, $chunkSize));
                if (empty($rows)) {
                    break;
                }

                $chunkRecords = $this->buildRecords($rows, $offset);
                if (!empty($chunkRecords)) {
                    array_push($records, ...$chunkRecords);
                }

                $processed += count($rows);
                if ($progressCallback) {
                    $progressCallback($processed);
                }

                if (count($rows) < $chunkSize) {
                    break;
                }

                $offset += $chunkSize;
            }
        }

        if (empty($records)) {
            return [
                'records' => [],
                'suppressed' => [],
                'multiple_occurrences' => [],
                'grouping_preview' => [
                    'summary' => ['matched' => 0, 'missing' => 0, 'skipped' => 0],
                    'rows' => [],
                    'note' => 'No records to analyze.',
                ],
                'qc_findings' => [],
                'summary' => [
                    'total_rows' => 0,
                    'preview_rows' => 0,
                    'ready_for_import' => 0,
                    'suppressed_existing_count' => 0,
                    'duplicate_rows' => 0,
                    'invalid_rows' => 0,
                ],
            ];
        }

        return $this->analyzeRecords($records, $testControl);
    }

    public function paginateFromPath(string $path, int $page, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->standardizeRows($this->readRowsFromPath($path, $offset, $perPage));
        $records = $this->buildRecords($rows, $offset);

        return array_map(
            fn (FileIndexingRecord $record) => $record->toPreviewRow(),
            $records
        );
    }

    public function parseAllFromPath(string $path): array
    {
        $rows = $this->standardizeRows($this->readRowsFromPath($path));

        return $this->buildRecords($rows);
    }

    public function countRowsFromPath(string $path): int
    {
        $extension = $this->extensionFromPath($path);

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            if (!$sheet instanceof Worksheet) {
                return 0;
            }

            $highestRow = (int) $sheet->getHighestRow();

            return max(0, $highestRow - 1);
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV);
        $file->seek(PHP_INT_MAX);

        $totalLines = $file->key(); // zero-based index of last line

        return max(0, $totalLines);
    }

    /**
     * @param  FileIndexingRecord[]  $records
     */
    public function import(
        array $records,
        string $testControl,
        string $filename,
        ?callable $afterRecord = null
    ): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $groupingUpdates = 0;
        $now = now();

        foreach ($records as $record) {
            if (!$record->isImportable()) {
                $skipped++;
                if ($afterRecord) {
                    $afterRecord($record);
                }
                continue;
            }

            $fileNumber = $record->fileNumber;
            if (!$fileNumber) {
                $skipped++;
                if ($afterRecord) {
                    $afterRecord($record);
                }
                continue;
            }

            $payload = [
                'file_title' => $record->fileTitle,
                'land_use_type' => $record->landUseType,
                'plot_number' => $record->plotNumber,
                'tp_no' => $record->tpNo,
                'lpkn_no' => $record->lpknNo,
                'district' => $record->district,
                'lga' => $record->lga,
                'location' => $record->location,
                'shelf_location' => $record->shelfLocation,
                'registry' => $record->registry,
                'batch_no' => $record->batchNo,
                'registry_batch_no' => $record->registryBatchNo,
                'serial_no' => $record->serialNo,
                'page_no' => $record->pageNo,
                'vol_no' => $record->volNo,
                'test_control' => $testControl,
                'source' => 'CSV Importer',
                'updated_at' => $now,
                'updated_by' => 'CSV Importer',
            ];

            $existing = FileIndexing::on('sqlsrv')->where('file_number', $fileNumber)->first();
            if ($existing) {
                $existing->fill($payload);
                $existing->save();
                $updated++;
            } else {
                $payload = array_merge($payload, [
                    'file_number' => $fileNumber,
                    'created_at' => $now,
                    'created_by' => 'CSV Importer',
                ]);
                FileIndexing::on('sqlsrv')->create($payload);
                $inserted++;
            }

            $groupingRowId = Arr::get($record->grouping, 'row_id');
            if ($groupingRowId) {
                Grouping::on('sqlsrv')
                    ->where('id', $groupingRowId)
                    ->update([
                        'indexing_mapping' => 1,
                        'indexing_mls_fileno' => $fileNumber,
                        'tracking_id' => Arr::get($record->grouping, 'tracking_id'),
                        'updated_at' => $now,
                    ]);
                $groupingUpdates++;
            }

            if ($afterRecord) {
                $afterRecord($record);
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'grouping_updates' => $groupingUpdates,
            'source' => $filename,
        ];
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return match ($extension) {
            'xlsx', 'xls' => $this->readSpreadsheetRows($file),
            default => $this->readCsvRows($file),
        };
    }

    private function readRowsFromPath(string $path, ?int $offset = null, ?int $limit = null): array
    {
        $extension = $this->extensionFromPath($path);
        return match ($extension) {
            'xlsx', 'xls' => $this->readSpreadsheetRowsFromPath($path, $offset, $limit),
            default => $this->readCsvRowsFromPath($path, $offset, $limit),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSpreadsheetRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        if (!$sheet instanceof Worksheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) {
            return [];
        }

        $headers = array_shift($rows);
        $normalizedHeaders = array_map(function ($header) {
            return is_string($header) ? trim($header) : (string) $header;
        }, $headers);

        $records = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($normalizedHeaders as $columnIndex => $header) {
                if ($header === null || $header === '') {
                    continue;
                }
                $assoc[$header] = $row[$columnIndex] ?? null;
            }

            if ($this->rowIsEmpty($assoc)) {
                continue;
            }

            $records[] = $assoc;
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSpreadsheetRowsFromPath(string $path, ?int $offset, ?int $limit): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        if (!$sheet instanceof Worksheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) {
            return [];
        }

        $headers = array_shift($rows);
        $normalizedHeaders = array_map(function ($header) {
            return is_string($header) ? trim($header) : (string) $header;
        }, $headers);

        if ($offset !== null || $limit !== null) {
            $rows = array_slice(
                $rows,
                $offset ?? 0,
                $limit ?? null
            );
        }

        $records = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($normalizedHeaders as $columnIndex => $header) {
                if ($header === null || $header === '') {
                    continue;
                }
                $assoc[$header] = $row[$columnIndex] ?? null;
            }

            if ($this->rowIsEmpty($assoc)) {
                continue;
            }

            $records[] = $assoc;
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        try {
            $reader = Reader::createFromPath($file->getRealPath(), 'r');
            $reader->setHeaderOffset(0);

            $records = [];
            foreach ($reader->getRecords() as $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }
                $records[] = $row;
            }

            return $records;
        } catch (CsvException $exception) {
            throw new \InvalidArgumentException('Unable to read uploaded file: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsvRowsFromPath(string $path, ?int $offset, ?int $limit): array
    {
        try {
            $reader = Reader::createFromPath($path, 'r');
            $reader->setHeaderOffset(0);
            $statement = (new Statement())->offset($offset ?? 0);
            if ($limit !== null) {
                $statement = $statement->limit($limit);
            }

            $rows = [];
            foreach ($statement->process($reader)->getRecords() as $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }
                $rows[] = $row;
            }

            return $rows;
        } catch (CsvException $exception) {
            throw new \InvalidArgumentException('Unable to read uploaded file: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function rowIsEmpty(?array $row): bool
    {
        if (!$row) {
            return true;
        }

        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function standardizeRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $sanitizedRow = [];
            foreach ($row as $column => $value) {
                $key = Str::of((string) $column)->lower()->replace(' ', '')->replace('_', '')->toString();
                $sanitizedRow[$key] = $value;
            }

            $standardRow = [];
            foreach (self::COLUMN_MAPPINGS as $field => $aliases) {
                $value = null;
                foreach ($aliases as $alias) {
                    $aliasKey = Str::of($alias)->lower()->replace(' ', '')->replace('_', '')->toString();
                    if (array_key_exists($aliasKey, $sanitizedRow)) {
                        $value = $sanitizedRow[$aliasKey];
                        break;
                    }
                }

                $formatted = $this->normalizer->formatValue(
                    $value,
                    in_array($field, self::NUMERIC_FIELDS, true)
                );
                $standardRow[$field] = $formatted !== '' ? $formatted : null;
            }

            if ($this->rowIsEmpty($standardRow)) {
                continue;
            }

            if (!empty($standardRow['file_number'])) {
                $standardRow['file_number'] = Str::upper($standardRow['file_number']);
            }

            if (!empty($standardRow['cofo_date'])) {
                $standardRow['cofo_date'] = $this->normalizer->formatDateForUi($standardRow['cofo_date']);
            }

            if (!empty($standardRow['deeds_date'])) {
                $standardRow['deeds_date'] = $this->normalizer->formatDateForUi($standardRow['deeds_date']);
            }

            if (!empty($standardRow['deeds_time'])) {
                $standardRow['deeds_time'] = $this->normalizer->formatTimeForUi($standardRow['deeds_time']);
            }

            $normalized[] = $standardRow;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return FileIndexingRecord[]
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildRecords(array $rows, int $startIndex = 0): array
    {
        $records = [];
        foreach ($rows as $index => $row) {
            $fileNumber = $this->normalizer->normalizeString($row['file_number'] ?? null);
            $fileTitle = $this->normalizer->normalizeString($row['file_title'] ?? null);

            $record = new FileIndexingRecord(
                rowIndex: $startIndex + $index,
                fileNumber: $fileNumber ? Str::upper($fileNumber) : null,
                fileTitle: $fileTitle,
                landUseType: $this->normalizer->normalizeString($row['land_use_type'] ?? null),
                plotNumber: $this->normalizer->normalizeString($row['plot_number'] ?? null),
                tpNo: $this->normalizer->normalizeString($row['tp_no'] ?? null),
                lpknNo: $this->normalizer->normalizeString($row['lpkn_no'] ?? null),
                district: $this->normalizer->normalizeString($row['district'] ?? null),
                lga: $this->normalizer->normalizeString($row['lga'] ?? null),
                location: $this->normalizer->normalizeString($row['location'] ?? null),
                shelfLocation: $this->normalizer->normalizeString($row['shelf_location'] ?? null),
                registry: $this->normalizer->normalizeString($row['registry'] ?? null),
                batchNo: $this->normalizer->normalizeString($row['batch_no'] ?? null),
                registryBatchNo: $this->normalizer->normalizeString($row['registry_batch_no'] ?? null),
                cofoDate: $row['cofo_date'] ?? null,
                serialNo: $this->normalizer->normalizeString($row['serial_no'] ?? null),
                pageNo: $this->normalizer->normalizeString($row['page_no'] ?? null),
                volNo: $this->normalizer->normalizeString($row['vol_no'] ?? null),
                deedsTime: $row['deeds_time'] ?? null,
                deedsDate: $row['deeds_date'] ?? null,
            );

            if (!$record->fileNumber) {
                $record->markInvalid('Missing file number');
            }

            if (!$record->fileTitle) {
                $record->markInvalid('Missing file title');
            }

            $record->rowIndex = $startIndex + $index;
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  FileIndexingRecord[]  $records
     * @return array{0: FileIndexingRecord[], 1: array<int, array<string, mixed>>}
     */
    private function filterExistingRecords(array $records, string $testControl): array
    {
        $normalizedMap = [];
        foreach ($records as $index => $record) {
            $normalized = $record->fileNumber ? Str::upper($record->fileNumber) : null;
            if ($normalized) {
                $normalizedMap[$index] = $normalized;
            }
        }

        $existingSources = $this->lookupExistingSources(array_values($normalizedMap), $testControl);
        if (empty($existingSources)) {
            return [$records, []];
        }

        $suppressed = [];
        foreach ($records as $index => $record) {
            $canonical = $normalizedMap[$index] ?? null;
            if ($canonical && isset($existingSources[$canonical])) {
                $record->markExisting($existingSources[$canonical]);
                $suppressed[] = [
                    'row_index' => $record->rowIndex + 1,
                    'file_number' => $record->fileNumber,
                    'sources' => $existingSources[$canonical],
                ];
            }
        }

        $filtered = array_values(array_filter($records, fn (FileIndexingRecord $record) => !$record->suppressed));

        return [$filtered, $suppressed];
    }

    /**
     * @param  string[]  $numbers
     */
    private function lookupExistingSources(array $numbers, string $testControl): array
    {
        if (empty($numbers)) {
            return [];
        }

        $unique = array_values(array_unique(array_filter($numbers)));
        if (empty($unique)) {
            return [];
        }

        $detected = [];
        foreach (array_chunk($unique, 500) as $chunk) {
            $fileIndexingRows = FileIndexing::on('sqlsrv')
                ->select('file_number')
                ->whereIn(DB::raw('UPPER(file_number)'), $chunk)
                ->get();
            foreach ($fileIndexingRows as $row) {
                $key = Str::upper($row->file_number);
                $detected[$key][] = 'File Indexing';
            }

            $fileNumberRows = FileNumber::on('sqlsrv')
                ->select('mlsfNo')
                ->whereIn(DB::raw('UPPER(mlsfNo)'), $chunk)
                ->where('test_control', strtoupper($testControl))
                ->get();
            foreach ($fileNumberRows as $row) {
                $key = Str::upper($row->mlsfNo);
                $detected[$key][] = 'File Number';
            }
        }

        return array_map(
            fn ($sources) => array_values(array_unique($sources)),
            $detected
        );
    }

    /**
     * @param  FileIndexingRecord[]  $records
     */
    private function detectUploadDuplicates(array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            if (!$record->fileNumber) {
                continue;
            }
            $counts[$record->fileNumber] = ($counts[$record->fileNumber] ?? 0) + 1;
        }

        $duplicates = [];
        foreach ($records as $record) {
            if (!$record->fileNumber) {
                continue;
            }

            if (($counts[$record->fileNumber] ?? 0) > 1) {
                $record->markDuplicateUpload();
                $duplicates[$record->fileNumber] = $counts[$record->fileNumber];
            }
        }

        ksort($duplicates);

        return $duplicates;
    }

    /**
     * @param  FileIndexingRecord[]  $records
     */
    private function buildGroupingPreview(array $records): array
    {
        $limit = self::GROUPING_PREVIEW_LIMIT;
        $total = count($records);

        if ($total === 0) {
            return [
                'summary' => ['matched' => 0, 'missing' => 0, 'skipped' => 0],
                'rows' => [],
                'note' => 'No records to analyze.',
            ];
        }

        if ($total > $limit) {
            return [
                'summary' => ['matched' => 0, 'missing' => 0, 'skipped' => $total],
                'rows' => [],
                'note' => sprintf(
                    'Grouping preview skipped because upload contains %d rows; limit is %d.',
                    $total,
                    $limit
                ),
            ];
        }

        $matched = 0;
        $missing = 0;
        $rows = [];

        foreach ($records as $record) {
            if (!$record->fileNumber) {
                $missing++;
                continue;
            }

            $result = $this->groupingResolver->resolve($record->fileNumber);
            $record->assignGrouping($result);

            if (($result['status'] ?? 'missing') === 'matched') {
                $matched++;
            } else {
                $missing++;
            }

            $rows[] = [
                'file_number' => $record->fileNumber,
                'status' => $result['status'] ?? 'missing',
                'tracking_id' => $result['tracking_id'] ?? null,
                'registry' => $result['registry'] ?? null,
                'group' => $result['group'] ?? null,
            ];
        }

        return [
            'summary' => [
                'matched' => $matched,
                'missing' => $missing,
                'skipped' => 0,
            ],
            'rows' => $rows,
        ];
    }

    private function analyzeRecords(array $records, string $testControl): array
    {
        [$filteredRecords, $suppressed] = $this->filterExistingRecords($records, $testControl);
        $duplicateMap = $this->detectUploadDuplicates($filteredRecords);
        $groupingPreview = $this->buildGroupingPreview($filteredRecords);
        $qcFindings = $this->qcService->analyze(
            array_map(fn (FileIndexingRecord $record) => ['file_number' => $record->fileNumber], $filteredRecords)
        );

        $summary = $this->buildSummary($records, $filteredRecords, $suppressed, $duplicateMap);

        return [
            'records' => $filteredRecords,
            'suppressed' => $suppressed,
            'multiple_occurrences' => $duplicateMap,
            'grouping_preview' => $groupingPreview,
            'qc_findings' => $qcFindings,
            'summary' => $summary,
        ];
    }

    private function buildSummary(
        array $allRecords,
        array $filteredRecords,
        array $suppressed,
        array $duplicates
    ): array {
        $invalid = count(array_filter(
            $filteredRecords,
            fn (FileIndexingRecord $record) => $record->status === FileIndexingRecord::STATUS_INVALID
        ));

        $ready = count(array_filter(
            $filteredRecords,
            fn (FileIndexingRecord $record) => $record->isImportable()
        ));

        return [
            'total_rows' => count($allRecords),
            'preview_rows' => count($filteredRecords),
            'ready_for_import' => $ready,
            'suppressed_existing_count' => count($suppressed),
            'duplicate_rows' => count($duplicates),
            'invalid_rows' => $invalid,
        ];
    }

    private function extensionFromPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'csv');
    }
}
