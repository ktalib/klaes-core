<?php

namespace App\Services\CsvImporter\FileNumber;

use App\Models\FileNumber;
use App\Models\Grouping;
use App\Services\CsvImporter\Qc\FileNumberQcService;
use App\Services\CsvImporter\Support\CsvValueNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FileNumberImportService
{
    private const REQUIRED_COLUMNS = ['mlsf_no', 'current_allottee'];

    private const COLUMN_ALIASES = [
        'mlsfno' => 'mlsf_no',
        'mlsf_no' => 'mlsf_no',
        'mlsf number' => 'mlsf_no',
        'mlsno' => 'mlsf_no',
        'mlsf' => 'mlsf_no',
        'currentallottee' => 'current_allottee',
        'current_allottee' => 'current_allottee',
        'file_name' => 'current_allottee',
        'filename' => 'current_allottee',
        'plotno' => 'plot_no',
        'plot number' => 'plot_no',
        'tpplanno' => 'tp_plan_no',
        'tp_no' => 'tp_plan_no',
        'tpplan' => 'tp_plan_no',
        'tp plan no' => 'tp_plan_no',
        'kangisfileno' => 'kangis_file_no',
        'kangis_file_no' => 'kangis_file_no',
        'layoutname' => 'layout_name',
        'districtname' => 'district_name',
        'lganame' => 'lga_name',
    ];

    public function __construct(
        private readonly CsvValueNormalizer $normalizer,
        private readonly FileNumberQcService $qcService,
    ) {
    }

    public function buildPreview(UploadedFile $file, string $testControl): array
    {
        $rows = $this->standardizeRows($this->readRows($file));
        $records = $this->buildRecords($rows);
        $this->annotateWithExisting($records);
        $this->annotateWithGrouping($records);

        $summary = $this->summarise($records);
        $previewRows = array_map(fn (FileNumberRecord $record) => $record->toPreviewRow(), $records);
        $qcFindings = $this->qcService->analyze(
            array_map(fn (FileNumberRecord $record) => ['file_number' => $record->fileNumber], $records)
        );

        return [
            'records' => $records,
            'summary' => $summary,
            'preview_rows' => $previewRows,
            'qc_findings' => $qcFindings,
        ];
    }

    /**
     * @param  FileNumberRecord[]  $records
     */
    public function import(array $records, string $testControl, string $filename): array
    {
        $now = now();
        $inserted = 0;
        $updated = 0;
        $skippedDuplicates = 0;
        $skippedInvalid = 0;
        $groupingUpdates = 0;

        DB::connection('sqlsrv')->beginTransaction();

        try {
            foreach ($records as $record) {
                if ($record->isDuplicate()) {
                    $skippedDuplicates++;
                    continue;
                }

                if ($record->isInvalid()) {
                    $skippedInvalid++;
                    continue;
                }

                if (!$record->fileNumber || !$record->fileName) {
                    $skippedInvalid++;
                    continue;
                }

                $trackingId = $record->groupingTrackingId;
                if (!$trackingId && $record->groupingId) {
                    $groupingRow = Grouping::on('sqlsrv')->find($record->groupingId);
                    if ($groupingRow && $groupingRow->tracking_id) {
                        $trackingId = $this->normalizer->normalizeString($groupingRow->tracking_id);
                    }
                }

                /** @var FileNumber|null $existing */
                $existing = $record->existingId ? FileNumber::on('sqlsrv')->find($record->existingId) : null;

                if ($existing) {
                    $existing->forceFill([
                        'FileName' => $record->fileName,
                        'location' => $record->location,
                        'plot_no' => $record->plotNo,
                        'tp_no' => $record->tpNo,
                        'kangisFileNo' => $record->kangisFileNo,
                        'tracking_id' => $trackingId ?? $existing->tracking_id,
                        'updated_by' => 'CSV Bulk Importer',
                        'type' => 'KANGIS',
                        'SOURCE' => 'KANGIS GIS',
                        'test_control' => $testControl,
                        'date_migrated' => $now,
                        'migration_source' => 'KANGIS GIS',
                        'migrated_by' => '1',
                        'is_deleted' => false,
                    ]);
                    $existing->updated_at = $now;
                    $existing->save();

                    $updated++;
                } else {
                    $entry = new FileNumber();
                    $entry->mlsfNo = $record->fileNumber;
                    $entry->forceFill([
                        'FileName' => $record->fileName,
                        'location' => $record->location,
                        'plot_no' => $record->plotNo,
                        'tp_no' => $record->tpNo,
                        'kangisFileNo' => $record->kangisFileNo,
                        'tracking_id' => $trackingId,
                        'created_by' => 'CSV Bulk Importer',
                        'updated_by' => 'CSV Bulk Importer',
                        'type' => 'KANGIS',
                        'SOURCE' => 'KANGIS GIS',
                        'test_control' => $testControl,
                        'date_migrated' => $now,
                        'migration_source' => 'KANGIS GIS',
                        'migrated_by' => '1',
                        'is_deleted' => false,
                    ]);
                    $entry->created_at = $now;
                    $entry->updated_at = $now;
                    $entry->save();

                    $record->existingId = $entry->id;
                    $inserted++;
                }

                if ($record->groupingId) {
                    Grouping::on('sqlsrv')
                        ->where('id', $record->groupingId)
                        ->update([
                            'indexing_mapping' => 1,
                            'indexing_mls_fileno' => $record->fileNumber,
                            'tracking_id' => $trackingId,
                            'test_control' => $testControl,
                            'updated_at' => $now,
                        ]);
                    $groupingUpdates++;
                }
            }

            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $exception) {
            DB::connection('sqlsrv')->rollBack();
            throw $exception;
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped_duplicates' => $skippedDuplicates,
            'skipped_invalid' => $skippedInvalid,
            'grouping_updates' => $groupingUpdates,
        ];
    }

    /**
     * @return FileNumberRecord[]
     */
    public function recordsFromSession(array $payload): array
    {
        return array_map(fn (array $row) => FileNumberRecord::fromSessionPayload($row), $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readSpreadsheetRows($file);
        }

        return $this->readCsvRows($file);
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
        if (empty($rows)) {
            throw new \InvalidArgumentException('Uploaded file is empty.');
        }

        $normalized = array_map(function (array $row) {
            $standardized = [];
            foreach ($row as $column => $value) {
                $key = Str::of((string) $column)->lower()->replace(' ', '')->replace('_', '')->toString();
                $target = self::COLUMN_ALIASES[$key] ?? Str::of((string) $column)->lower()->snake();
                $standardized[$target] = $value;
            }

            return $standardized;
        }, $rows);

        foreach (self::REQUIRED_COLUMNS as $required) {
            $present = collect($normalized)->contains(fn ($row) => array_key_exists($required, $row));
            if (!$present) {
                throw new \InvalidArgumentException("Missing required column '{$required}' in upload.");
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return FileNumberRecord[]
     */
    private function buildRecords(array $rows): array
    {
        $records = [];
        $seenCanonicals = [];

        foreach ($rows as $index => $row) {
            $fileNumber = $this->normalizer->normalizeString($row['mlsf_no'] ?? null);
            $fileName = $this->normalizer->normalizeString($row['current_allottee'] ?? null);
            $issues = [];

            if (!$fileNumber) {
                $issues[] = 'Missing mlsfNo';
            }

            if (!$fileName) {
                $issues[] = 'Missing currentAllottee';
            }

            $clean = $this->normalizer->removeFileNumberSuffixes($fileNumber) ?? $fileNumber;
            $canonical = $this->normalizer->normalizeFileNumberForMatch($fileNumber);

            $layout = $this->normalizer->normalizeString($row['layout_name'] ?? null);
            $district = $this->normalizer->normalizeString($row['district_name'] ?? null);
            $lga = $this->normalizer->normalizeString($row['lga_name'] ?? null);
            $location = $this->normalizer->combineLocation($layout, $lga, $district);

            $kangis = $this->normalizer->normalizeString($row['kangis_file_no'] ?? null);
            $plot = $this->normalizer->normalizeString($row['plot_no'] ?? null);
            $tp = $this->normalizer->normalizeString($row['tp_plan_no'] ?? null);

            $status = empty($issues) ? FileNumberRecord::STATUS_READY : FileNumberRecord::STATUS_INVALID;
            $statusLabel = empty($issues) ? 'Ready' : 'Invalid';

            if ($canonical) {
                if (isset($seenCanonicals[$canonical])) {
                    $status = FileNumberRecord::STATUS_DUPLICATE_UPLOAD;
                    $statusLabel = 'Duplicate in upload';
                } else {
                    $seenCanonicals[$canonical] = $index;
                }
            }

            $records[] = new FileNumberRecord(
                rowIndex: $index,
                fileNumber: $fileNumber,
                cleanNumber: $clean,
                canonical: $canonical,
                fileName: $fileName,
                location: $location,
                plotNo: $plot,
                tpNo: $tp,
                kangisFileNo: $kangis,
                layoutName: $layout,
                districtName: $district,
                lgaName: $lga,
                status: $status,
                statusLabel: $statusLabel,
                issues: $issues,
            );
        }

        return $records;
    }

    /**
     * @param  FileNumberRecord[]  $records
     */
    private function annotateWithExisting(array $records): void
    {
        $candidateValues = [];
        $seen = [];

        foreach ($records as $record) {
            foreach ([$record->fileNumber, $record->cleanNumber] as $candidate) {
                if (!$candidate) {
                    continue;
                }
                $key = Str::upper($candidate);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $candidateValues[] = $candidate;
            }
        }

        if (empty($candidateValues)) {
            return;
        }

        $existingMap = [];

        foreach (array_chunk($candidateValues, 900) as $chunk) {
            $rows = FileNumber::on('sqlsrv')
                ->select(['id', 'mlsfNo'])
                ->whereIn('mlsfNo', $chunk)
                ->get();

            foreach ($rows as $row) {
                $canonical = $this->normalizer->normalizeFileNumberForMatch($row->mlsfNo);
                if ($canonical) {
                    $existingMap[$canonical] = (int) $row->id;
                }
            }
        }

        foreach ($records as $record) {
            if ($record->status === FileNumberRecord::STATUS_INVALID || $record->status === FileNumberRecord::STATUS_DUPLICATE_UPLOAD) {
                continue;
            }

            if ($record->canonical && isset($existingMap[$record->canonical])) {
                $record->markDuplicateExisting($existingMap[$record->canonical]);
            } else {
                $record->markInsert();
            }
        }
    }

    /**
     * @param  FileNumberRecord[]  $records
     */
    private function annotateWithGrouping(array $records): void
    {
        $candidateValues = [];
        $seen = [];

        foreach ($records as $record) {
            foreach ([$record->fileNumber, $record->cleanNumber] as $candidate) {
                if (!$candidate) {
                    continue;
                }
                $key = Str::upper($candidate);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $candidateValues[] = $candidate;
            }
        }

        if (empty($candidateValues)) {
            return;
        }

        $groupingMap = [];

        foreach (array_chunk($candidateValues, 900) as $chunk) {
            $rows = Grouping::on('sqlsrv')
                ->select([
                    'id',
                    'awaiting_fileno',
                    'tracking_id',
                    'registry',
                    'group',
                    'shelf_rack',
                    'sys_batch_no',
                    'mdc_batch_no',
                    'mapping',
                    'year',
                ])
                ->whereIn('awaiting_fileno', $chunk)
                ->get();

            foreach ($rows as $row) {
                $canonical = $this->normalizer->normalizeFileNumberForMatch($row->awaiting_fileno);
                if ($canonical) {
                    $groupingMap[$canonical] = $row;
                }
            }
        }

        foreach ($records as $record) {
            if (!$record->canonical) {
                continue;
            }

            if (!isset($groupingMap[$record->canonical])) {
                continue;
            }

            $row = $groupingMap[$record->canonical];
            $record->groupingId = (int) $row->id;
            $record->groupingTrackingId = $this->normalizer->normalizeString($row->tracking_id);
            $record->groupingStatus = 'matched';
            $record->metadata = [
                'registry' => $row->registry,
                'group' => $row->group,
                'shelf_rack' => $row->shelf_rack,
                'sys_batch_no' => $row->sys_batch_no,
                'mdc_batch_no' => $row->mdc_batch_no,
                'mapping' => $row->mapping,
                'year' => $row->year,
            ];
        }
    }

    /**
     * @param  FileNumberRecord[]  $records
     */
    private function summarise(array $records): array
    {
        $summary = [
            'total_rows' => count($records),
            'new_records' => 0,
            'update_records' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'ready_for_import' => 0,
        ];

        foreach ($records as $record) {
            switch ($record->status) {
                case FileNumberRecord::STATUS_INSERT:
                    $summary['new_records']++;
                    break;
                case FileNumberRecord::STATUS_UPDATE:
                    $summary['update_records']++;
                    break;
                case FileNumberRecord::STATUS_DUPLICATE_UPLOAD:
                case FileNumberRecord::STATUS_DUPLICATE_EXISTING:
                    $summary['duplicates']++;
                    break;
                case FileNumberRecord::STATUS_INVALID:
                    $summary['invalid']++;
                    break;
            }
        }

        $summary['ready_for_import'] = $summary['new_records'] + $summary['update_records'];

        return $summary;
    }
}
