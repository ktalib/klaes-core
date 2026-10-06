<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Send to OSS Applications" — puts a file that is missing from the Applications
 * table onto the right list, from the action menu of the module that owns it.
 *
 *  - MLPP File Commissioning → Land Applications (No Change of Ownership):
 *      system_source MLS_FILE_NUMBER_GENERATOR.
 *  - OSS FC / FEFR (the OP page) → Applications (Change of Ownership):
 *      system_source OSSOPCHANGEOFNAME, pinned through the same two flags the
 *      match/capture mirror uses (system_sub_type OSS + an "OP " sub_source).
 *
 * The row is written by MlsCommissioningOssApplicationService::sync() — the
 * commissioning engine's own mirror — so it is the same row commissioning would
 * have written. sync() stamps only the commissioning DATE and no creator, so the
 * original creator and moment are applied afterwards.
 *
 * Dates: pra.created_at is nvarchar in three shapes (".232" millis, no millis,
 * ISO "T"), mls_file_no carries 7 fractional digits, and oss_applications.created_at
 * is `datetime` (3 digits max) — every value goes through Carbon and is written as
 * Y-m-d H:i:s.v so neither the insert nor the listing's date rendering can choke.
 */
class OssApplicationSendService
{
    public const NO_CHANGE = 'no_change';
    public const CHANGE_OF_OWNERSHIP = 'change_of_ownership';

    private string $connection = 'sqlsrv';

    /** MLPP File Commissioning → No Change of Ownership. */
    public function sendNoChange(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        $mls = $this->mlsRow($fileNumber);
        if (!$mls) {
            return $this->fail("$fileNumber is not in the commissioning register, so it cannot be sent from MLPP File Commissioning.");
        }

        // Commissioned through OSS FC: that is a change of ownership, owned by the OP page.
        if (strtoupper(trim((string) ($mls->system_sub_type ?? ''))) === \App\Support\OssOpCommissionFilter::OSS) {
            return $this->fail("$fileNumber was commissioned through OSS File Commissioning (Change of Ownership). Send it from the OSS FC page instead.");
        }

        $existing = $this->activeRows($fileNumber);
        if ($coo = $existing->first(fn ($r) => $this->isChangeOfName($r))) {
            return $this->fail("$fileNumber is already on Applications (Change of Ownership) as #{$coo->id}. It was not added to No Change of Ownership.");
        }
        if ($own = $existing->first(fn ($r) => !$this->isChangeOfName($r))) {
            return $this->already($fileNumber, $own, self::NO_CHANGE);
        }

        $row = (array) $mls;
        $row['system_sub_type'] = 'MLS';   // MLPP is never a change of ownership

        $creator = $this->userId($mls->created_by);
        $at = $this->commissionedAt($mls);

        return $this->write($fileNumber, $row, self::NO_CHANGE, $creator, $at, 'mls_file_no #' . $mls->id);
    }

    /**
     * OSS FC / FEFR → Change of Ownership. Keyed on the row's Transfer of Title pra id
     * (what the OP page lists); the file number comes from that pra row.
     */
    public function sendChangeOfOwnership(int $praId): array
    {
        $db = DB::connection($this->connection);
        $tot = $db->table('pra')->where('id', $praId)->first();
        if (!$tot) {
            return $this->fail('Record not found.');
        }

        $fileNumber = trim((string) ($tot->mlsFNo ?: $tot->fileno));
        if ($fileNumber === '') {
            return $this->fail('This record has no file number yet (temporary file ' . ($tot->temp_fileno ?: '—') . '). Commission or match it first.');
        }

        $existing = $this->activeRows($fileNumber);
        if ($coo = $existing->first(fn ($r) => $this->isChangeOfName($r))) {
            return $this->already($fileNumber, $coo, self::CHANGE_OF_OWNERSHIP);
        }

        $op = $this->opRow($fileNumber);
        $mls = $this->mlsRow($fileNumber);

        // Holder: the commissioned file title when there is one, else the ToT grantee.
        $holder = trim((string) ($mls->file_name ?? '')) ?: trim((string) ($tot->Grantee ?? ''));

        $row = [
            'full_file_number' => $fileNumber,
            'file_name'        => $holder ?: null,
            'plot_no'          => $mls->plot_no ?? data_get($op, 'plot_no') ?? $tot->plot_no,
            'tp_no'            => $mls->tp_no ?? data_get($op, 'tp_no') ?? $tot->tp_no,
            'location'         => $mls->location ?? data_get($op, 'location') ?? $tot->location,
            'district'         => $mls->district ?? data_get($op, 'district'),
            'lga'              => $mls->lga ?? data_get($op, 'lga'),
            'land_use'         => $mls->land_use ?? data_get($op, 'land_use') ?? $tot->land_use,
            'op_serial_number' => data_get($op, 'op_serial_number') ?: ($tot->op_serial_number ?? null),
            'source_pra_id'    => data_get($op, 'id') ?: $praId,
            // The two halves of sync()'s change-of-name gate (see mirrorChangeOfNameApplication).
            'system_sub_type'  => \App\Support\OssOpCommissionFilter::OSS,
            'sub_source'       => str_starts_with(strtoupper(trim((string) ($mls->sub_source ?? ''))), 'OP ')
                ? $mls->sub_source : 'OP Change of Ownership',
        ];

        // Original: an FC file was raised at its commissioning; a FEFR record when its
        // Transfer of Title was captured.
        if ($mls) {
            $creator = $this->userId($mls->created_by) ?? $this->userId($tot->created_by);
            $at = $this->commissionedAt($mls);
            $origin = 'mls_file_no #' . $mls->id;
        } else {
            $creator = $this->userId($tot->created_by);
            $at = $this->parseDate($tot->created_at);
            $origin = 'pra #' . $tot->id;
        }

        $result = $this->write($fileNumber, $row, self::CHANGE_OF_OWNERSHIP, $creator, $at, $origin);

        // A no-change row for the same file stays as it is; say so rather than move it.
        if ($result['success'] && ($other = $existing->first(fn ($r) => !$this->isChangeOfName($r)))) {
            $result['note'] = "This file also has a No Change of Ownership application (#{$other->id}); it was left as it is.";
        }

        return $result;
    }

    // ---------------------------------------------------------------------------

    private function write(string $fileNumber, array $row, string $list, ?int $creator, ?Carbon $at, string $origin): array
    {
        $db = DB::connection($this->connection);
        $db->beginTransaction();
        try {
            $sync = app(MlsCommissioningOssApplicationService::class)->sync($row);
            $id = $sync['id'] ?? null;

            if (!$id || !in_array($sync['action'], ['created', 'updated', 'unchanged'], true)) {
                $db->rollBack();
                return $this->fail("The record could not be placed on the list ({$sync['action']}).");
            }

            $row = $db->table('oss_applications')->where('id', $id)->first();
            if (!$row || $this->isChangeOfName($row) !== ($list === self::CHANGE_OF_OWNERSHIP)) {
                $db->rollBack();
                return $this->fail('The application landed on the wrong list; nothing was saved.');
            }

            if ($sync['action'] === 'created') {
                $stamp = ['updated_at' => $this->sqlDate(now())];
                if ($creator) {
                    $stamp['captured_by'] = $creator;
                }
                if ($at) {
                    $stamp['created_at'] = $this->sqlDate($at);
                }
                $db->table('oss_applications')->where('id', $id)->update($stamp);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Log::warning('Send to OSS Applications failed', ['file_no' => $fileNumber, 'list' => $list, 'error' => $e->getMessage()]);
            return $this->fail('Could not send to OSS Applications: ' . $e->getMessage());
        }

        Log::info('Sent to OSS Applications from the action menu', [
            'file_no' => $fileNumber, 'list' => $list, 'oss_application_id' => $id,
            'action' => $sync['action'], 'origin' => $origin, 'captured_by' => $creator,
            'created_at' => $at ? $at->toDateTimeString() : null, 'user_id' => Auth::id(),
        ]);

        return [
            'success' => true,
            'action' => $sync['action'],
            'id' => $id,
            'file_number' => $fileNumber,
            'list' => $list,
            'message' => ($sync['action'] === 'created' ? 'Added to ' : 'Already on ') . $this->listLabel($list)
                . " as application #$id.",
        ];
    }

    private function mlsRow(string $fileNumber): ?object
    {
        return DB::connection($this->connection)->table('mls_file_no')
            ->where('full_file_number', $fileNumber)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();
    }

    private function opRow(string $fileNumber): ?object
    {
        return DB::connection($this->connection)->table('pra')
            ->whereRaw("COALESCE(NULLIF(mlsFNo,''), fileno) = ?", [$fileNumber])
            ->where(fn ($q) => $q->where('instrument_type', 'like', '%Occupancy Permit%')
                ->orWhere('transaction_type', 'like', '%Occupancy Permit%'))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderBy('id')
            ->first();
    }

    private function activeRows(string $fileNumber)
    {
        return DB::connection($this->connection)->table('oss_applications')
            ->where('file_no', $fileNumber)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['id', 'system_source']);
    }

    private function isChangeOfName(object $row): bool
    {
        return strtoupper(trim((string) ($row->system_source ?? ''))) === MlsCommissioningOssApplicationService::CHANGE_OF_NAME_SOURCE;
    }

    /** A user id from an id, a "First Last" name, or nothing. */
    private function userId($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $ids = DB::connection($this->connection)->table('users')
            ->whereRaw("LTRIM(RTRIM(CONCAT(first_name, ' ', last_name))) = ?", [$value])
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /** Same rule as FileCommissioningTrackingService::commissionedAt(). */
    private function commissionedAt(object $mls): ?Carbon
    {
        $date = trim((string) ($mls->commissioning_date ?? ''));
        $time = trim((string) ($mls->commissioning_time ?? ''));
        if ($date !== '') {
            $parsed = $this->parseDate(substr($date, 0, 10) . ' ' . ($time !== '' ? substr($time, 0, 8) : '00:00:00'));
            if ($parsed) {
                return $parsed;
            }
        }

        return $this->parseDate($mls->created_at ?? null);
    }

    /** Tolerates every date shape found in these tables; null when unparseable. */
    private function parseDate($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // Over-long fractions ("…04.4266667") trip some parsers; three digits is all we keep.
        $value = preg_replace('/(\.\d{3})\d+/', '$1', $value);
        foreach (['Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.v', 'Y-m-d'] as $format) {
            try {
                $d = Carbon::createFromFormat('!' . $format, $value);
                if ($d instanceof Carbon) {
                    return $d;
                }
            } catch (\Throwable $e) {
                // try the next shape
            }
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sqlDate(Carbon $c): string
    {
        return $c->format('Y-m-d H:i:s.v');
    }

    private function already(string $fileNumber, object $row, string $list): array
    {
        return [
            'success' => true, 'action' => 'already', 'id' => (int) $row->id, 'file_number' => $fileNumber, 'list' => $list,
            'message' => "$fileNumber is already on " . $this->listLabel($list) . " as application #{$row->id}. Nothing was changed.",
        ];
    }

    private function listLabel(string $list): string
    {
        return $list === self::CHANGE_OF_OWNERSHIP
            ? 'Applications (Change of Ownership)'
            : 'Land Applications (No Change of Ownership)';
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
