<?php

namespace App\Services;

use App\Support\OssOpCommissionFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves a commissioned file between the two commissioning registries.
 *
 * A file is commissioned either in MLS File Commissioning ("Land") or in the Lands
 * One Stop Shop. Both post to the same endpoint and write otherwise identical rows,
 * so the origin is a stamp rather than a shape: mls_file_no.system_sub_type, which
 * OssOpCommissionFilter calls the test "and nothing else". Choosing the wrong entry
 * point therefore does not corrupt the file — it files it under the wrong registry,
 * and the repair is to restamp it.
 *
 * Two rows carry the origin and both have to move together, or the file appears on
 * one module's list while its application still belongs to the other:
 *
 *   mls_file_no.system_sub_type          MLS | OSS   — which module lists the file
 *   oss_applications.system_source       + remarks   — which application list it feeds
 *
 * Deliberately NOT touched:
 *  - fileNumber.SOURCE. FileNumberController lists commissioned files with
 *    SOURCE IN ('MLS_Commissioned','MLS_Commissioned_Batch'); rewriting it to an OSS
 *    value would drop the file out of those listings altogether.
 *  - Whether an mls_file_no row exists. That is the separate fc/fefr axis; a file
 *    with such a row is "commissioned" on either side, and moving registries must
 *    never un-commission a file.
 *  - pra / instrument_capture. A move re-files the commissioning; it does not touch
 *    the instruments registered against the land.
 */
class CommissioningRegistryMoveService
{
    public const TARGET_LAND = OssOpCommissionFilter::MLS;
    public const TARGET_OSS = OssOpCommissionFilter::OSS;

    /** oss_applications.system_source per registry. */
    private const OSS_SYSTEM_SOURCE = MlsCommissioningOssApplicationService::CHANGE_OF_NAME_SOURCE;
    private const LAND_SYSTEM_SOURCE = MlsCommissioningOssApplicationService::SYSTEM_SOURCE;

    /** The remarks each registry's mirror writes, kept in step with the source. */
    private const OSS_REMARKS = MlsCommissioningOssApplicationService::OSS_REMARKS;
    private const LAND_REMARKS = MlsCommissioningOssApplicationService::MLS_REMARKS;

    /**
     * @return array<string,mixed>
     */
    public function move(string $fileNumber, string $target, ?int $userId = null): array
    {
        $fileNumber = trim($fileNumber);
        $target = strtoupper(trim($target));

        if ($fileNumber === '') {
            throw new \InvalidArgumentException('A file number is required.');
        }
        if (!in_array($target, [self::TARGET_LAND, self::TARGET_OSS], true)) {
            throw new \InvalidArgumentException('Target registry must be MLS or OSS.');
        }

        $db = DB::connection('sqlsrv');

        // Live rows only. A deleted commissioning has no place on either list, and
        // restamping one would quietly resurrect it.
        $mls = $db->table('mls_file_no')
            ->where('full_file_number', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first();

        if (!$mls) {
            return $this->result('not_found', $fileNumber, null, $target, 0, 0,
                $fileNumber . ' has no live commissioning record, so there is nothing to move.');
        }

        $from = strtoupper(trim((string) ($mls->system_sub_type ?? ''))) ?: null;

        // An unstamped row is MLS by OssOpCommissionFilter's own rule ("rows with no
        // stamp are treated as MLS-commissioned"), so moving one to Land is still a
        // real write: it makes the implicit stamp explicit.
        if ($from === $target) {
            return $this->result('unchanged', $fileNumber, $from, $target, 0, 0,
                $fileNumber . ' is already commissioned in ' . $this->label($target) . '.');
        }

        $isOss = $target === self::TARGET_OSS;
        $systemSource = $isOss ? self::OSS_SYSTEM_SOURCE : self::LAND_SYSTEM_SOURCE;
        $remarks = $isOss ? self::OSS_REMARKS : self::LAND_REMARKS;

        $ossBefore = $db->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->get(['id', 'system_source', 'remarks']);

        $counts = $db->transaction(function () use ($db, $fileNumber, $mls, $target, $systemSource, $remarks, $userId) {
            $m = $db->table('mls_file_no')
                ->where('id', $mls->id)
                ->update([
                    'system_sub_type' => $target,
                    'updated_at' => now(),
                ]);

            // The mirror row is keyed on file_no rather than on the mls_file_no id,
            // and a file can legitimately carry more than one. Every live row moves,
            // or the file stays split across the two lists.
            $o = $db->table('oss_applications')
                ->where('file_no', $fileNumber)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->update([
                    'system_source' => $systemSource,
                    'remarks' => $remarks,
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

            return [$m, $o];
        });

        [$mlsUpdated, $ossUpdated] = $counts;

        $this->audit($fileNumber, (int) $mls->id, [
            'system_sub_type' => $mls->system_sub_type ?? null,
            'oss_applications' => $ossBefore->toArray(),
        ], [
            'system_sub_type' => $target,
            'system_source' => $systemSource,
            'remarks' => $remarks,
        ]);

        Log::info('Commissioning registry moved', [
            'file_number' => $fileNumber,
            'from' => $from,
            'to' => $target,
            'mls_file_no_updated' => $mlsUpdated,
            'oss_applications_updated' => $ossUpdated,
            'user_id' => $userId,
        ]);

        return $this->result('moved', $fileNumber, $from, $target, $mlsUpdated, $ossUpdated,
            $fileNumber . ' moved to ' . $this->label($target) . '.'
            . ($ossUpdated === 0 ? ' It has no OSS application row to move.' : ''));
    }

    /** Which registry a file currently sits in, so a UI can label its own action. */
    public function currentRegistry(string $fileNumber): ?string
    {
        $row = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('full_file_number', trim($fileNumber))
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return null;
        }

        return strtoupper(trim((string) ($row->system_sub_type ?? ''))) ?: self::TARGET_LAND;
    }

    private function label(string $target): string
    {
        return $target === self::TARGET_OSS
            ? 'OSS File Commissioning'
            : 'Land (MLS File Commissioning)';
    }

    private function audit(string $fileNumber, int $id, array $old, array $new): void
    {
        try {
            app(AuditService::class)->logAction(
                'REGISTRY_MOVED',
                'MlsFileNo',
                $id,
                $old,
                $new,
                'Commissioning registry moved for ' . $fileNumber
            );
        } catch (\Throwable $e) {
            // The move has already committed; an unavailable audit table must not
            // report a successful move as a failure.
            Log::warning('Could not audit commissioning registry move', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function result(string $status, string $fileNumber, ?string $from, string $to, int $m, int $o, string $message): array
    {
        return [
            'status' => $status,
            'file_number' => $fileNumber,
            'from' => $from,
            'to' => $to,
            'mls_file_no_updated' => $m,
            'oss_applications_updated' => $o,
            'message' => $message,
        ];
    }
}
