<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * MASTER DELETE for a captured / registered instrument (Instrument Capture and
 * Instrument Registration screens).
 *
 * One capture and the registration it produced are one record to the officer,
 * so they go together: the instrument_capture row, every deed_registrations row
 * pointing at it, the RDS tracking rows for those registrations, and - for a
 * CofO - the CofO_staging mirror the capture wrote.
 *
 * HARD delete, on purpose. The registration number is freed by the rows being
 * gone: MissingRegistrationParticularsService surveys deed_registrations and
 * checks instrument_capture without looking at is_deleted, so a soft-deleted
 * row would keep holding its number and the hole could never be backfilled.
 * Once deleted, the number shows in the capture screen's missing-number list
 * (including the vault's own latest number - the survey runs up to and
 * including current_serial) and the next capture can take it.
 *
 * The vault is NOT rewound: it may have moved on since, and a rewind would hand
 * out numbers that are still held. The gap list is the one way back.
 *
 * Refused while another module still points at the capture (PRA, OSS, LAAS,
 * SLTR CofO TDP, MLS commissioning): erasing it would leave those records
 * referring to an instrument that no longer exists. Unlink those first.
 */
class InstrumentRegistrationPurgeService
{
    /** Registers this delete never touches: generated from ST applications, or kept by Land Registration. */
    public const EXCLUDED_TYPES = [
        'ST Assignment (Transfer of Title)',
        'Sectional Titling CofO',
        'ST Fragmentation',
    ];

    /** Tables (and their link column) that make a capture undeletable while they point at it. */
    private const BLOCKING_LINKS = [
        'pra' => 'instrument_capture_id',
        'oss_applications' => 'instrument_capture_id',
        'oss_acknowledgements' => 'instrument_capture_id',
        'oss_verifications' => 'instrument_capture_id',
        'oss_change_of_ownership' => 'instrument_capture_id',
        'laas_transfer_candidates' => 'instrument_capture_id',
        'sltr_cofo_tdp' => 'instrument_capture_id',
        'mls_file_no' => 'source_instrument_capture_id',
    ];

    private array $columnCache = [];

    /**
     * The capture + registrations a row reference stands for.
     *
     * Accepts the ids the two screens use: `deed_reg_{id}` and `instr_capture_{id}`
     * (Instrument Registration) and a bare capture id (Instrument Capture).
     *
     * @return array{capture:?object, deeds:Collection, reference:string, label:string}
     */
    public function resolve(string $ref): array
    {
        $db = DB::connection('sqlsrv');
        $ref = trim($ref);
        $capture = null;
        $deeds = collect();

        if (preg_match('/^deed_reg_(\d+)$/', $ref, $m)) {
            $deed = $db->table('deed_registrations')->where('id', (int) $m[1])->first();
            if (!$deed) {
                throw new RuntimeException('Registration not found.');
            }
            if (!empty($deed->instrument_capture_id)) {
                $capture = $db->table('instrument_capture')->where('id', $deed->instrument_capture_id)->first();
            }
            $deeds = $capture
                ? $db->table('deed_registrations')->where('instrument_capture_id', $capture->id)->get()
                : collect([$deed]);
        } elseif (preg_match('/^(?:instr_capture_)?(\d+)$/', $ref, $m)) {
            $capture = $db->table('instrument_capture')->where('id', (int) $m[1])->first();
            if (!$capture) {
                throw new RuntimeException('Captured instrument not found.');
            }
            $deeds = $db->table('deed_registrations')->where('instrument_capture_id', $capture->id)->get();
        } else {
            throw new RuntimeException('Unrecognised record reference.');
        }

        $type = $capture->instrument_type ?? ($deeds->first()->instrument_type ?? '');
        if ($this->isExcludedType((string) $type)) {
            throw new RuntimeException("Master Delete is not available for {$type} records on this screen.");
        }

        $regNo = trim((string) ($deeds->first()->registration_number ?? ($capture->registration_number ?? '')));
        $fileNo = $capture
            ? ($capture->mlsFNo ?: $capture->kangisFileNo ?: $capture->NewKANGISFileno ?: $capture->temp_fileno)
            : ($deeds->first()->fileno ?? null);

        return [
            'capture' => $capture,
            'deeds' => $deeds,
            // What the operator types back: the registration number when there is
            // one (unique within its register), otherwise the file number.
            'reference' => $regNo !== '' ? $regNo : trim((string) $fileNo),
            'label' => trim($type . ' ' . ($regNo !== '' ? $regNo : '') . ($fileNo ? " ({$fileNo})" : '')),
        ];
    }

    public function isExcludedType(string $type): bool
    {
        $type = trim($type);
        if (strcasecmp($type, (string) config('land_registration.instrument_type')) === 0) {
            return true;
        }
        foreach (self::EXCLUDED_TYPES as $excluded) {
            if (strcasecmp($type, $excluded) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * What a delete would remove and what stops it. Read-only.
     *
     * @return array{counts:array<string,int>, blockers:array<string,int>}
     */
    public function preview(array $unit): array
    {
        $db = DB::connection('sqlsrv');
        $deedIds = $unit['deeds']->pluck('id')->all();

        $counts = [
            'instrument_capture' => $unit['capture'] ? 1 : 0,
            'deed_registrations' => count($deedIds),
            'rds_tracking' => $deedIds
                ? $db->table('rds_tracking')->whereIn('instrument_id', $this->rdsKeys($deedIds))->count()
                : 0,
            'CofO_staging' => $this->cofoMirrorForCapture($unit['capture'])?->count() ?? 0,
        ];

        $blockers = [];
        if ($unit['capture']) {
            foreach (self::BLOCKING_LINKS as $table => $column) {
                if (!$this->hasColumn($table, $column)) {
                    continue;
                }
                $n = $db->table($table)->where($column, $unit['capture']->id)->count();
                if ($n > 0) {
                    $blockers[$table] = $n;
                }
            }
        }

        return ['counts' => array_filter($counts), 'blockers' => $blockers];
    }

    /**
     * Erase the unit. Must run inside the caller's sqlsrv transaction.
     *
     * @return array<string,int> rows removed per table
     */
    public function purge(array $unit): array
    {
        $preview = $this->preview($unit);
        if ($preview['blockers']) {
            throw new RuntimeException('Still linked from ' . $this->describe($preview['blockers'])
                . '. Unlink those records before a Master Delete.');
        }

        $this->backup($unit);

        $db = DB::connection('sqlsrv');
        $deedIds = $unit['deeds']->pluck('id')->all();
        $counts = [];

        // Before the deeds go: the mirror is found through the capture's own
        // registration number, which the delete is about to free.
        if ($mirror = $this->cofoMirrorForCapture($unit['capture'])) {
            $counts['CofO_staging'] = $mirror->delete();
        }

        if ($deedIds) {
            $counts['rds_tracking'] = $db->table('rds_tracking')->whereIn('instrument_id', $this->rdsKeys($deedIds))->delete();
            $counts['deed_registrations'] = $db->table('deed_registrations')->whereIn('id', $deedIds)->delete();
        }

        if ($unit['capture']) {
            $counts['instrument_capture'] = $db->table('instrument_capture')->where('id', $unit['capture']->id)->delete();
        }

        return array_filter($counts);
    }

    public function describe(array $counts): string
    {
        return implode(', ', array_map(
            fn ($table, $n) => "{$table} ({$n})",
            array_keys($counts),
            array_values($counts)
        ));
    }

    /** rds_tracking keys a registration as "deed_reg_{id}"; a few early rows carry the bare id. */
    private function rdsKeys(array $deedIds): array
    {
        $keys = [];
        foreach ($deedIds as $id) {
            $keys[] = 'deed_reg_' . $id;
            $keys[] = (string) $id;
        }

        return $keys;
    }

    /**
     * The CofO_staging row a CofO capture mirrored itself into
     * (InstrumentCaptureService::syncCofoStaging): same type, same registration
     * number, same file. Null when there is nothing to match on.
     * Used by the Master Delete to remove a CofO capture together with its mirror.
     */
    public function cofoMirrorForCapture(?object $capture)
    {
        if (!$capture || stripos((string) $capture->instrument_type, 'Certificate of Occupancy') === false) {
            return null;
        }

        $regNo = trim((string) ($capture->registration_number ?? ''));
        $files = array_values(array_filter([
            $capture->mlsFNo ?? null,
            $capture->kangisFileNo ?? null,
            $capture->NewKANGISFileno ?? null,
            $capture->temp_fileno ?? null,
        ]));

        if ($regNo === '' || !$files) {
            return null;
        }

        return DB::connection('sqlsrv')->table('CofO_staging')
            ->where('regNo', $regNo)
            ->where('instrument_type', $capture->instrument_type)
            ->where(function ($q) use ($files) {
                $q->whereIn('fileno', $files)
                    ->orWhereIn('mlsFNo', $files)
                    ->orWhereIn('kangisFileNo', $files)
                    ->orWhereIn('NewKANGISFileno', $files)
                    ->orWhereIn('temp_fileno', $files);
            });
    }

    /**
     * Every row about to go, appended to a JSONL file before anything is
     * deleted. The audit log carries the same snapshot; this copy is the one
     * that survives a broken audit table.
     */
    private function backup(array $unit): void
    {
        $db = DB::connection('sqlsrv');
        $deedIds = $unit['deeds']->pluck('id')->all();

        $record = [
            'at' => now()->toDateTimeString(),
            'user_id' => Auth::id(),
            'reference' => $unit['reference'],
            'instrument_capture' => $unit['capture'],
            'deed_registrations' => $unit['deeds']->values(),
            'rds_tracking' => $deedIds
                ? $db->table('rds_tracking')->whereIn('instrument_id', $this->rdsKeys($deedIds))->get()
                : [],
            'CofO_staging' => $this->cofoMirrorForCapture($unit['capture'])?->get() ?? [],
        ];

        $dir = storage_path('app/deleted_records');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // A failed backup stops the delete: the rows have no other copy.
        $written = file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'instrument_master_delete_' . now()->format('Ymd') . '.jsonl',
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        if ($written === false) {
            throw new RuntimeException('Could not write the pre-delete backup; nothing was deleted.');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columnCache["{$table}.{$column}"]
            ??= Schema::connection('sqlsrv')->hasColumn($table, $column);
    }
}
