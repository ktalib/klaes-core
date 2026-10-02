<?php

namespace App\Services\CsvImporter\FileIndexing;

class FileIndexingRecord
{
    public const STATUS_READY = 'ready';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DUPLICATE = 'duplicate_upload';
    public const STATUS_EXISTING = 'existing';

    public function __construct(
        public int $rowIndex,
        public ?string $fileNumber,
        public ?string $fileTitle,
        public ?string $landUseType,
        public ?string $plotNumber,
        public ?string $tpNo,
        public ?string $lpknNo,
        public ?string $district,
        public ?string $lga,
        public ?string $location,
        public ?string $shelfLocation,
        public ?string $registry,
        public ?string $batchNo,
        public ?string $registryBatchNo,
        public ?string $cofoDate,
        public ?string $serialNo,
        public ?string $pageNo,
        public ?string $volNo,
        public ?string $deedsTime,
        public ?string $deedsDate,
        public array $issues = [],
        public string $status = self::STATUS_READY,
        public array $grouping = [],
        public bool $suppressed = false,
        public array $metadata = []
    ) {
    }

    public function markInvalid(string $issue): void
    {
        $this->status = self::STATUS_INVALID;
        $this->issues[] = $issue;
    }

    public function markDuplicateUpload(): void
    {
        $this->status = self::STATUS_DUPLICATE;
    }

    public function markExisting(array $sources): void
    {
        $this->status = self::STATUS_EXISTING;
        $this->suppressed = true;
        $this->issues[] = 'Existing in ' . implode(', ', $sources);
    }

    public function assignGrouping(array $grouping): void
    {
        $this->grouping = $grouping;
    }

    public function isImportable(): bool
    {
        return !$this->suppressed
            && $this->status !== self::STATUS_INVALID
            && $this->status !== self::STATUS_DUPLICATE;
    }

    public function toPreviewRow(): array
    {
        return [
            'index' => $this->rowIndex + 1,
            'file_number' => $this->fileNumber,
            'file_title' => $this->fileTitle,
            'land_use_type' => $this->landUseType,
            'plot_number' => $this->plotNumber,
            'tp_no' => $this->tpNo,
            'lpkn_no' => $this->lpknNo,
            'district' => $this->district,
            'lga' => $this->lga,
            'location' => $this->location,
            'registry' => $this->registry,
            'batch_no' => $this->batchNo,
            'shelf_location' => $this->shelfLocation,
            'status' => $this->status,
            'issues' => $this->issues,
            'grouping' => $this->grouping,
        ];
    }

    public function toSessionPayload(): array
    {
        return [
            'row_index' => $this->rowIndex,
            'file_number' => $this->fileNumber,
            'file_title' => $this->fileTitle,
            'land_use_type' => $this->landUseType,
            'plot_number' => $this->plotNumber,
            'tp_no' => $this->tpNo,
            'lpkn_no' => $this->lpknNo,
            'district' => $this->district,
            'lga' => $this->lga,
            'location' => $this->location,
            'shelf_location' => $this->shelfLocation,
            'registry' => $this->registry,
            'batch_no' => $this->batchNo,
            'registry_batch_no' => $this->registryBatchNo,
            'cofo_date' => $this->cofoDate,
            'serial_no' => $this->serialNo,
            'page_no' => $this->pageNo,
            'vol_no' => $this->volNo,
            'deeds_time' => $this->deedsTime,
            'deeds_date' => $this->deedsDate,
            'issues' => $this->issues,
            'status' => $this->status,
            'grouping' => $this->grouping,
            'suppressed' => $this->suppressed,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromSessionPayload(array $payload): self
    {
        return new self(
            rowIndex: (int) ($payload['row_index'] ?? 0),
            fileNumber: $payload['file_number'] ?? null,
            fileTitle: $payload['file_title'] ?? null,
            landUseType: $payload['land_use_type'] ?? null,
            plotNumber: $payload['plot_number'] ?? null,
            tpNo: $payload['tp_no'] ?? null,
            lpknNo: $payload['lpkn_no'] ?? null,
            district: $payload['district'] ?? null,
            lga: $payload['lga'] ?? null,
            location: $payload['location'] ?? null,
            shelfLocation: $payload['shelf_location'] ?? null,
            registry: $payload['registry'] ?? null,
            batchNo: $payload['batch_no'] ?? null,
            registryBatchNo: $payload['registry_batch_no'] ?? null,
            cofoDate: $payload['cofo_date'] ?? null,
            serialNo: $payload['serial_no'] ?? null,
            pageNo: $payload['page_no'] ?? null,
            volNo: $payload['vol_no'] ?? null,
            deedsTime: $payload['deeds_time'] ?? null,
            deedsDate: $payload['deeds_date'] ?? null,
            issues: $payload['issues'] ?? [],
            status: $payload['status'] ?? self::STATUS_READY,
            grouping: $payload['grouping'] ?? [],
            suppressed: (bool) ($payload['suppressed'] ?? false),
            metadata: $payload['metadata'] ?? []
        );
    }
}
