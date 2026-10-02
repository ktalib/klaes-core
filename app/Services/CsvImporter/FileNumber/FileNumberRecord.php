<?php

namespace App\Services\CsvImporter\FileNumber;

class FileNumberRecord
{
    public const STATUS_READY = 'ready';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DUPLICATE_UPLOAD = 'duplicate_upload';
    public const STATUS_DUPLICATE_EXISTING = 'duplicate_existing';
    public const STATUS_INSERT = 'insert';
    public const STATUS_UPDATE = 'update';

    public function __construct(
        public int $rowIndex,
        public ?string $fileNumber,
        public ?string $cleanNumber,
        public ?string $canonical,
        public ?string $fileName,
        public ?string $location,
        public ?string $plotNo,
        public ?string $tpNo,
        public ?string $kangisFileNo,
        public ?string $layoutName,
        public ?string $districtName,
        public ?string $lgaName,
        public string $status,
        public string $statusLabel,
        public array $issues = [],
        public ?int $existingId = null,
        public ?int $groupingId = null,
        public ?string $groupingTrackingId = null,
        public string $groupingStatus = 'missing',
        public array $metadata = []
    ) {
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    public function isDuplicate(): bool
    {
        return in_array($this->status, [self::STATUS_DUPLICATE_UPLOAD, self::STATUS_DUPLICATE_EXISTING], true);
    }

    public function markDuplicateUpload(): void
    {
        $this->status = self::STATUS_DUPLICATE_UPLOAD;
        $this->statusLabel = 'Duplicate in upload';
    }

    public function markDuplicateExisting(int $existingId): void
    {
        $this->existingId = $existingId;
        $this->status = self::STATUS_DUPLICATE_EXISTING;
        $this->statusLabel = 'Already exists in FileNumber';
    }

    public function markInsert(): void
    {
        $this->status = self::STATUS_INSERT;
        $this->statusLabel = 'Will insert';
    }

    public function toPreviewRow(): array
    {
        return [
            'index' => $this->rowIndex + 1,
            'file_number' => $this->fileNumber ?? '',
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'file_name' => $this->fileName ?? '',
            'location' => $this->location ?? '',
            'plot_no' => $this->plotNo ?? '',
            'tp_no' => $this->tpNo ?? '',
            'kangis_file_no' => $this->kangisFileNo ?? '',
            'grouping_status' => $this->groupingStatus,
            'issues' => $this->issues,
        ];
    }

    public function toSessionPayload(): array
    {
        return [
            'row_index' => $this->rowIndex,
            'file_number' => $this->fileNumber,
            'clean_number' => $this->cleanNumber,
            'canonical' => $this->canonical,
            'file_name' => $this->fileName,
            'location' => $this->location,
            'plot_no' => $this->plotNo,
            'tp_no' => $this->tpNo,
            'kangis_file_no' => $this->kangisFileNo,
            'layout_name' => $this->layoutName,
            'district_name' => $this->districtName,
            'lga_name' => $this->lgaName,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'issues' => $this->issues,
            'existing_id' => $this->existingId,
            'grouping_id' => $this->groupingId,
            'grouping_tracking_id' => $this->groupingTrackingId,
            'grouping_status' => $this->groupingStatus,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromSessionPayload(array $payload): self
    {
        return new self(
            rowIndex: (int) ($payload['row_index'] ?? 0),
            fileNumber: $payload['file_number'] ?? null,
            cleanNumber: $payload['clean_number'] ?? null,
            canonical: $payload['canonical'] ?? null,
            fileName: $payload['file_name'] ?? null,
            location: $payload['location'] ?? null,
            plotNo: $payload['plot_no'] ?? null,
            tpNo: $payload['tp_no'] ?? null,
            kangisFileNo: $payload['kangis_file_no'] ?? null,
            layoutName: $payload['layout_name'] ?? null,
            districtName: $payload['district_name'] ?? null,
            lgaName: $payload['lga_name'] ?? null,
            status: $payload['status'] ?? self::STATUS_READY,
            statusLabel: $payload['status_label'] ?? 'Ready',
            issues: $payload['issues'] ?? [],
            existingId: $payload['existing_id'] ?? null,
            groupingId: $payload['grouping_id'] ?? null,
            groupingTrackingId: $payload['grouping_tracking_id'] ?? null,
            groupingStatus: $payload['grouping_status'] ?? 'missing',
            metadata: $payload['metadata'] ?? []
        );
    }
}
