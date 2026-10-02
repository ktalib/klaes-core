<?php

namespace App\Services;

use App\Models\MlsFileNo;
use App\Support\OssOpCommissionFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors MLS File Number Generator commissions into the OSS no-change list.
 *
 * The change-of-name list is deliberately isolated by SYSTEM_SOURCE. Never update
 * or reclassify one of those rows, even if it happens to carry the same file number.
 */
class MlsCommissioningOssApplicationService
{
    public const SYSTEM_SOURCE = 'MLS_FILE_NUMBER_GENERATOR';
    public const CHANGE_OF_NAME_SOURCE = 'OSSOPCHANGEOFNAME';

    /** Marks the change-of-name rows this service raised, as opposed to ones typed in OSS. */
    public const OSS_REMARKS = 'Auto-created from OSS File Commissioning.';
    public const MLS_REMARKS = 'Auto-created from MLPP File Number Generator commissioning.';

    private ?array $ossColumns = null;

    /**
     * @param MlsFileNo|array<string,mixed>|object $source
     * @return array{action:string,id:?int,file_number:string}
     */
    public function sync($source): array
    {
        $row = $source instanceof MlsFileNo ? $source->getAttributes() : (array) $source;
        $fileNumber = trim((string) ($row['full_file_number'] ?? $row['file_number'] ?? ''));

        if ($fileNumber === '') {
            throw new \InvalidArgumentException('An MLS full file number is required for the OSS mirror.');
        }

        // OSS commissioning of an OP-backed file belongs on the Change of Ownership
        // (change-of-name) list, not the no-change one. Anything else raised through an
        // OSS entry point still has no application of its own and is left alone.
        $isOss = strtoupper(trim((string) ($row['system_sub_type'] ?? ''))) === OssOpCommissionFilter::OSS;
        if ($isOss && !$this->isOpBackedCommissioning($row)) {
            return $this->result('skipped_oss', null, $fileNumber);
        }

        $targetSource = $isOss ? self::CHANGE_OF_NAME_SOURCE : self::SYSTEM_SOURCE;

        $db = DB::connection('sqlsrv');
        $existingRows = $db->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->orderByDesc('id')
            ->get();

        // A Change-of-Name application is authoritative for this file. In particular,
        // do not rewrite its source and accidentally move it onto the no-change page.
        // (An OSS commissioning is itself a change-of-name row, so it fills that row
        // instead of bailing out.)
        if (!$isOss && $existingRows->contains(function ($existing) {
            return $this->isActive($existing)
                && strtoupper(trim((string) ($existing->system_source ?? ''))) === self::CHANGE_OF_NAME_SOURCE;
        })) {
            return $this->result('skipped_change_of_name', null, $fileNumber);
        }

        $existing = $existingRows->first(function ($candidate) use ($isOss) {
            if (!$this->isActive($candidate)) {
                return false;
            }
            // Fill the row that already speaks for this file on the page we are writing for.
            return !$isOss
                || strtoupper(trim((string) ($candidate->system_source ?? ''))) === self::CHANGE_OF_NAME_SOURCE;
        });
        $payload = $this->payload($row, $fileNumber, $targetSource);

        if ($existing) {
            // Respect data entered through OSS. Only fill fields that are currently blank.
            $changes = [];
            foreach ($payload as $column => $value) {
                if (in_array($column, ['created_at', 'system_source', 'status', 'remarks'], true)) {
                    continue;
                }

                $current = $existing->{$column} ?? null;
                if (($current === null || trim((string) $current) === '') && $value !== null && $value !== '') {
                    $changes[$column] = $value;
                }
            }

            if (!$changes) {
                return $this->result('unchanged', (int) $existing->id, $fileNumber);
            }

            $changes['updated_at'] = now();
            $db->table('oss_applications')->where('id', $existing->id)->update($changes);
            $this->audit('UPDATED', (int) $existing->id, (array) $existing, $changes, $fileNumber);

            return $this->result('updated', (int) $existing->id, $fileNumber);
        }

        $id = (int) $db->table('oss_applications')->insertGetId($payload);
        $this->audit('CREATED', $id, null, $payload, $fileNumber);

        return $this->result('created', $id, $fileNumber);
    }

    /**
     * Did this commissioning come from an Occupancy Permit capture? Only those belong on
     * the Change of Ownership list — the OP is what the application is about. The OSS
     * stamps sub_source at commissioning time ("OP Change of Ownership" / the older
     * "OP Change of Name"); the source ids cover a hand-off that lost the sub_source.
     *
     * @param array<string,mixed> $row
     */
    private function isOpBackedCommissioning(array $row): bool
    {
        $subSource = strtoupper(trim((string) ($row['sub_source'] ?? '')));
        if (str_starts_with($subSource, 'OP ')) {
            return true;
        }

        foreach (['source_instrument_capture_id', 'source_pra_id'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $row */
    private function payload(array $row, string $fileNumber, string $systemSource = self::SYSTEM_SOURCE): array
    {
        $commissionedAt = $row['commissioning_date'] ?? $row['created_at'] ?? now();
        try {
            $commissionedAt = Carbon::parse($commissionedAt);
        } catch (\Throwable $e) {
            $commissionedAt = now();
        }

        $payload = [
            'application_type' => $this->resolveApplicationType($row['land_use'] ?? null, $fileNumber),
            'applicant_name' => $this->nullable($row['file_name'] ?? null),
            'file_no' => $fileNumber,
            'plot_no' => $this->nullable($row['plot_no'] ?? null),
            'plan_no' => $this->nullable($row['tp_no'] ?? null),
            'location' => $this->nullable($row['location'] ?? null),
            'district' => $this->nullable($row['district'] ?? null),
            'lga' => $this->nullable($row['lga'] ?? null),
            'land_use' => $this->nullable($row['land_use'] ?? null),
            'passport_photo' => $this->findPassportPath($fileNumber),
            'status' => 'approved',
            'remarks' => $systemSource === self::CHANGE_OF_NAME_SOURCE ? self::OSS_REMARKS : self::MLS_REMARKS,
            'system_source' => $systemSource,
            'is_deleted' => 0,
            'created_at' => $commissionedAt,
            'updated_at' => $commissionedAt,
        ];

        $columns = $this->ossColumns();

        return array_filter($payload, static function ($value, $column) use ($columns) {
            return isset($columns[strtolower((string) $column)]);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Attach the passport saved after the main commissioning transaction commits.
     * Change-of-name applications typed into OSS carry their own passport and are left
     * alone; the ones this service raised for an OSS commissioning are ours to fill, and
     * are told apart by the remarks stamp.
     */
    public function attachPassport(string $fileNumber, ?string $path): bool
    {
        $fileNumber = trim($fileNumber);
        $path = $this->nullable($path);
        if ($fileNumber === '' || $path === null || !isset($this->ossColumns()['passport_photo'])) {
            return false;
        }

        $query = DB::connection('sqlsrv')->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('system_source')
                    ->orWhere('system_source', '<>', self::CHANGE_OF_NAME_SOURCE)
                    ->orWhere('remarks', self::OSS_REMARKS);
            });

        if (isset($this->ossColumns()['is_deleted'])) {
            $query->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });
        }

        $existing = $query->orderByDesc('id')->first();
        if (!$existing || trim((string) ($existing->passport_photo ?? '')) === $path) {
            return (bool) $existing;
        }

        $changes = ['passport_photo' => $path];
        if (isset($this->ossColumns()['updated_at'])) {
            $changes['updated_at'] = now();
        }

        DB::connection('sqlsrv')->table('oss_applications')->where('id', $existing->id)->update($changes);
        $this->audit('UPDATED', (int) $existing->id, (array) $existing, $changes, $fileNumber);

        return true;
    }

    public function resolveApplicationType($landUse, string $fileNumber = ''): string
    {
        $value = strtoupper(trim((string) $landUse) . ' ' . trim($fileNumber));

        if (preg_match('/\b(AGR|AGRICULTURAL|AGRICULTURE)\b/', $value)) {
            return 'agricultural';
        }
        if (preg_match('/\b(IND|INDUSTRIAL|INDUSTRY)\b/', $value)) {
            return 'industrial';
        }
        if (preg_match('/\b(COM|CON|COMMERCIAL|COMMERCIAL CONCESSION)\b/', $value)) {
            return 'commercial';
        }

        return 'residential';
    }

    /** @return array<string,string> */
    private function ossColumns(): array
    {
        if ($this->ossColumns !== null) {
            return $this->ossColumns;
        }

        $this->ossColumns = [];
        foreach (Schema::connection('sqlsrv')->getColumnListing('oss_applications') as $column) {
            $this->ossColumns[strtolower($column)] = $column;
        }

        return $this->ossColumns;
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** Recover a passport already filed by MLS commissioning for backfills/re-runs. */
    private function findPassportPath(string $fileNumber): ?string
    {
        if (!isset($this->ossColumns()['passport_photo'])
            || !Schema::connection('sqlsrv')->hasTable('file_indexings')
            || !Schema::connection('sqlsrv')->hasTable('scannings')) {
            return null;
        }

        try {
            $path = DB::connection('sqlsrv')->table('scannings as s')
                ->join('file_indexings as fi', 'fi.id', '=', 's.file_indexing_id')
                ->where('fi.file_number', $fileNumber)
                ->where(function ($q) {
                    $q->where('s.document_type', 'Passport Photograph')
                        ->orWhere('s.original_filename', 'LIKE', 'passport_%')
                        ->orWhere('s.document_path', 'LIKE', '%/passport_%');
                })
                ->orderByDesc('s.id')
                ->value('s.document_path');

            return $this->nullable($path);
        } catch (\Throwable $e) {
            Log::warning('Could not resolve MLS commissioning passport for OSS mirror', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isActive(object $row): bool
    {
        return !isset($row->is_deleted) || $row->is_deleted === null || (int) $row->is_deleted === 0;
    }

    private function audit(string $action, int $id, ?array $old, array $new, string $fileNumber): void
    {
        try {
            app(AuditService::class)->logAction(
                $action,
                'LandsOneStopShopApplication',
                $id,
                $old,
                $new,
                "MLS commissioning mirror for {$fileNumber}"
            );
        } catch (\Throwable $e) {
            // The mirror is part of the commissioning transaction; audit storage is
            // best-effort so an unavailable audit table does not strand a valid file.
            Log::warning('Could not audit MLS-to-OSS application mirror', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function result(string $action, ?int $id, string $fileNumber): array
    {
        return ['action' => $action, 'id' => $id, 'file_number' => $fileNumber];
    }
}
