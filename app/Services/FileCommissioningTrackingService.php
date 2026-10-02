<?php

namespace App\Services;

use App\Models\FileTracker;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default In-process In-transit Tracking (DIIT).
 *
 * A file commissioned through KLAES is a NEW file: it is created at the File
 * Commissioning Office and then travels from office to office for processing
 * and documentation. It is not in the registry/archive yet, and until someone
 * logs it out it has no file_tracker row at all — so before DIIT such a file
 * was invisible to the File Log Table and reported "In Archive" by the location
 * resolver.
 *
 * DIIT gives every KLAES-commissioned file a default first tracking line:
 *
 *      File Commissioning Office (FCO) — "File Commissioning" — commissioning date
 *
 * The line is DERIVED from mls_file_no rather than stored, so every file already
 * commissioned is covered the moment this ships and every future commissioning
 * is covered automatically — there is nothing to backfill and nothing to keep in
 * sync. mls_file_no.full_file_number is the authoritative record of a KLAES
 * commissioning (the same authority LegalSearchService::resolveCommissioningInfo()
 * uses for the Legal Search "File Commissioning" row).
 *
 * The DIIT state ends at the file's FIRST REAL tracking record: once a
 * file_tracker row exists, real tracking governs the file's location and the
 * commissioning line becomes the opening entry of its movement history.
 */
class FileCommissioningTrackingService
{
    /** The office a file is commissioned at (offices.office_code = 'FCO'). */
    public const OFFICE_CODE = 'FCO';
    public const OFFICE_NAME = 'File Commissioning Office';
    public const OFFICE_DEPARTMENT = 'Land';

    /** Badge text rendered for the default line (movement entry `status_label`). */
    public const LABEL = 'File Commissioning';

    /**
     * KLAES commissioning went live in 2026; the handful of older mls_file_no
     * rows carry a legacy year and must not sprout a commissioning line.
     */
    public const MIN_YEAR = 2026;

    protected string $connection = 'sqlsrv';

    /**
     * Per-request map of normalized (UPPER+trim) file number => commissioning
     * info array, or false when the file is not KLAES-commissioned. Primed one
     * page at a time so list screens run a single query instead of one per row.
     *
     * @var array<string, array<string,mixed>|false>
     */
    protected array $cache = [];

    /**
     * Base scope over the commissioning register: live rows only, from the year
     * KLAES commissioning started. Callers add their own filters/projection.
     */
    public function baseQuery(): Builder
    {
        return DB::connection($this->connection)
            ->table('mls_file_no')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) {
                $q->where('year', '>=', self::MIN_YEAR)
                    ->orWhereYear('commissioning_date', '>=', self::MIN_YEAR);
            });
    }

    /**
     * Rows in the commissioning register that have NO file_tracker record yet —
     * the files whose only tracking line is the DIIT one. Once a file is logged
     * out for real it drops out of here and its real tracker takes over.
     */
    public function untrackedQuery(): Builder
    {
        return $this->baseQuery()->whereNotExists(function ($sub) {
            // Plain equality (no UPPER/TRIM): the SQL_Latin1_General_CP1_CI_AS
            // collation already matches case-insensitively and ignores trailing
            // spaces, so this can seek the file_number index.
            $sub->selectRaw('1')
                ->from('file_tracker')
                ->whereColumn('file_tracker.file_number', 'mls_file_no.full_file_number');
        });
    }

    /**
     * Bulk-load the commissioning info for a whole page of file numbers in one
     * query. Mirrors the priming pattern the File Log Table already uses for
     * titles/locations — without it, infoFor() costs one query per row.
     */
    public function prime(iterable $fileNumbers): void
    {
        $wanted = [];
        foreach ($fileNumbers as $number) {
            $key = $this->key($number);
            if ($key !== '' && !array_key_exists($key, $this->cache)) {
                $wanted[$key] = trim((string) $number);
            }
        }

        if (empty($wanted)) {
            return;
        }

        try {
            // SQL Server caps a statement at 2,100 parameters.
            foreach (array_chunk($wanted, 1000, true) as $chunk) {
                $rows = $this->baseQuery()
                    ->whereIn('full_file_number', array_values($chunk))
                    ->orderBy('id')
                    ->get(['full_file_number', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source', 'file_name']);

                $found = [];
                foreach ($rows as $row) {
                    $found[$this->key($row->full_file_number)] = $row;
                }

                foreach (array_keys($chunk) as $key) {
                    $this->cache[$key] = isset($found[$key]) ? $this->hydrate($found[$key]) : false;
                }
            }
        } catch (\Throwable $e) {
            // A transient failure must not break the screen — leave the cache
            // alone so infoFor() falls back to per-row lookups.
            Log::warning('Unable to prime commissioning (DIIT) cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Commissioning info for a single file, or null when the file was not
     * commissioned through KLAES.
     *
     * @return array{file_number:string, commissioned_at:Carbon, commissioned_by:?string, tracking_id:?string, source:?string}|null
     */
    public function infoFor(?string $fileNumber): ?array
    {
        $key = $this->key($fileNumber);
        if ($key === '') {
            return null;
        }

        if (!array_key_exists($key, $this->cache)) {
            try {
                $row = $this->baseQuery()
                    ->where('full_file_number', trim((string) $fileNumber))
                    ->orderBy('id')
                    ->first(['full_file_number', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source', 'file_name']);

                $this->cache[$key] = $row ? $this->hydrate($row) : false;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $this->cache[$key] ?: null;
    }

    public function isCommissioned(?string $fileNumber): bool
    {
        return $this->infoFor($fileNumber) !== null;
    }

    /**
     * Resolve whatever identifier a tracking screen carries for a commissioned
     * file back to its commissioning info.
     *
     * A file still in the DIIT state has no file_tracker row, so the value an
     * officer scans or types is never a real tracking id. It is one of:
     *
     *   1. "DIIT-<file number>" — the synthetic id syntheticTracker() mints when
     *      the commissioning register carries no tracking id of its own;
     *   2. the file number itself;
     *   3. mls_file_no.tracking_id — the register's own tracking id.
     *
     * Returns null when the identifier is not a KLAES-commissioned file, which
     * lets callers fall through to their normal "not found" answer.
     *
     * @return array<string,mixed>|null
     */
    public function resolveIdentifier(?string $identifier): ?array
    {
        $value = trim((string) $identifier);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith(Str::upper($value), 'DIIT-')) {
            if ($info = $this->infoFor(substr($value, 5))) {
                return $info;
            }
        }

        if ($info = $this->infoFor($value)) {
            return $info;
        }

        try {
            $row = $this->baseQuery()
                ->where('tracking_id', $value)
                ->orderBy('id')
                ->first(['full_file_number', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source', 'file_name']);
        } catch (\Throwable $e) {
            Log::warning('Unable to resolve commissioning (DIIT) identifier', [
                'identifier' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$row) {
            return null;
        }

        $info = $this->hydrate($row);
        $this->cache[$this->key($info['file_number'])] = $info;

        return $info;
    }

    /**
     * Turn a commissioning-register row into the info array used everywhere else.
     *
     * @param object $row
     * @return array<string,mixed>
     */
    public function hydrate($row): array
    {
        return [
            'file_number'     => trim((string) $row->full_file_number),
            'file_title'      => isset($row->file_name) ? trim((string) $row->file_name) : null,
            'commissioned_at' => $this->commissionedAt($row),
            'commissioned_by' => trim((string) ($row->created_by ?? '')) ?: null,
            'tracking_id'     => trim((string) ($row->tracking_id ?? '')) ?: null,
            'source'          => trim((string) ($row->source ?? '')) ?: null,
        ];
    }

    /**
     * The moment the file was commissioned: the hand-entered commissioning date
     * (plus time when recorded), falling back to the register row's created_at.
     */
    protected function commissionedAt($row): Carbon
    {
        $date = trim((string) ($row->commissioning_date ?? ''));
        $time = trim((string) ($row->commissioning_time ?? ''));

        if ($date !== '') {
            $stamp = substr($date, 0, 10) . ($time !== '' ? ' ' . substr($time, 0, 8) : ' 00:00:00');
            $parsed = rescue(fn () => Carbon::parse($stamp), null, false);
            if ($parsed) {
                return $parsed;
            }
        }

        return rescue(fn () => Carbon::parse($row->created_at), Carbon::now(), false);
    }

    /**
     * The default line itself, in the movement_log shape the tracking sheet and
     * the File Log Table already render (see convertMovementLogEntry() in
     * create_file_tracker_page/partials/js.blade.php). `status_label` drives the
     * badge text, so the row reads "File Commissioning" rather than "Log-out".
     *
     * @param array<string,mixed> $info
     * @param Carbon|null $closedAt When the file left the commissioning office —
     *        the first real movement's log-in. Left open (still in transit at
     *        FCO) when the file has no real tracking yet.
     * @return array<string,mixed>
     */
    public function movementEntry(array $info, ?Carbon $closedAt = null): array
    {
        /** @var Carbon $at */
        $at = $info['commissioned_at'];
        $holder = $info['commissioned_by'] ?: 'File Commissioning Office';

        // A legacy file recommissioned into KLAES can carry movements older than its
        // commissioning date; the line still closes (the file did leave the office),
        // just never before it opened.
        if ($closedAt !== null && $closedAt->lessThan($at)) {
            $closedAt = $at->copy();
        }

        return [
            'log_id'                 => 'DIIT-' . strtoupper(substr(sha1('diit|' . $info['file_number']), 0, 10)),
            'office_code'            => self::OFFICE_CODE,
            'office_name'            => self::OFFICE_NAME,
            'log_in_date'            => $at->format('Y-m-d'),
            'log_in_time'            => $at->format('H:i'),
            'log_out_date'           => $closedAt ? $closedAt->format('Y-m-d') : null,
            'log_out_time'           => $closedAt ? $closedAt->format('H:i') : null,
            'status'                 => $closedAt ? 'completed' : 'active',
            'status_label'           => self::LABEL,
            'notes'                  => 'File commissioned in KLAES — in process at the File Commissioning Office.',
            'timestamp'              => $at->toIso8601String(),
            'user_name'              => $info['commissioned_by'] ?: 'System',
            'receiving_office_code'  => self::OFFICE_CODE,
            'receiving_office_name'  => self::OFFICE_NAME,
            'receiving_officer_id'   => null,
            'receiving_officer_name' => $holder,
            'origin_office_code'     => self::OFFICE_CODE,
            'origin_office_name'     => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            // Marks the row as the derived commissioning line rather than a real
            // logged movement, so the UI can render it read-only.
            '_diit'                  => true,
        ];
    }

    /**
     * True when a movement log already carries the commissioning line.
     *
     * A file commissioned with a "next destination" has the line STORED as the
     * first entry of its real tracker (see startTracking()), so callers must not
     * prepend the derived one on top of it.
     *
     * @param iterable<mixed> ...$logs
     */
    public function logHasCommissioningLine(iterable ...$logs): bool
    {
        foreach ($logs as $log) {
            foreach ($log as $entry) {
                if (is_array($entry) && !empty($entry['_diit'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * An UNSAVED FileTracker carrying nothing but the commissioning line, so a
     * commissioned-but-never-logged-out file can be listed by the File Log Table
     * through exactly the same decoration/rendering path as a real tracker.
     *
     * @param array<string,mixed> $info
     */
    public function syntheticTracker(array $info): FileTracker
    {
        /** @var Carbon $at */
        $at = $info['commissioned_at'];

        $tracker = new FileTracker();
        $tracker->forceFill([
            'id'                    => null,
            'tracking_id'           => $info['tracking_id'] ?: ('DIIT-' . $info['file_number']),
            'file_number'           => $info['file_number'],
            'file_title'            => $info['file_title'],
            'file_type'             => 'File',
            'priority'              => FileTracker::PRIORITY_MEDIUM,
            'status'                => FileTracker::STATUS_ACTIVE,
            'department'            => self::OFFICE_DEPARTMENT,
            'created_by_name'       => $info['commissioned_by'],
            'description'           => 'Default In-process In-transit Tracking — file commissioned in KLAES.',
            'movement_log'          => [$this->movementEntry($info)],
            'current_office_code'   => self::OFFICE_CODE,
            'current_office_name'   => self::OFFICE_NAME,
            'origin_office_code'    => self::OFFICE_CODE,
            'origin_office_name'    => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            'receiving_office_code' => self::OFFICE_CODE,
            'receiving_office_name' => self::OFFICE_NAME,
            'receiving_officer_name' => $info['commissioned_by'],
            'total_offices'         => 1,
            'completed_offices'     => 1,
            'date_created'          => $at,
            'created_at'            => $at,
            'updated_at'            => $at,
        ]);

        // Not a database row: never let a stray save() write it, and let the API
        // payload/front end tell it apart from a real tracker.
        $tracker->exists = false;
        $tracker->setAttribute('is_diit', true);

        return $tracker;
    }

    /**
     * Open REAL tracking for a file that is still in the DIIT state, without an
     * onward destination.
     *
     * syntheticTracker() is only a rendering shim — it is never saved — so any
     * tracking action taken on a commissioned-but-never-logged-out file has
     * nothing to act on. "Log File Back to Registry" is the first such action:
     * a commissioned file does come back to its registry, and until it has a
     * tracker there is no record to log that against.
     *
     * This writes the tracker the action needs, carrying the commissioning line
     * closed at $closedAt as the opening entry of its movement history — the
     * same shape startTracking() leaves behind, minus the onward movement. From
     * here the file is tracked normally and the DIIT state is over.
     *
     * A tracker created in the meantime (the file was logged out between the
     * lookup and the action) wins: it is returned untouched rather than
     * duplicated.
     *
     * @param array<string,mixed> $info Commissioning info (see hydrate()).
     * @param Carbon|null $closedAt When the file left the commissioning office.
     */
    public function materialize(array $info, ?Carbon $closedAt = null): FileTracker
    {
        $existing = FileTracker::where('file_number', $info['file_number'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @var Carbon $commissionedAt */
        $commissionedAt = $info['commissioned_at'];

        $closedAt = $closedAt ?: Carbon::now();
        if ($closedAt->lessThan($commissionedAt)) {
            $closedAt = $commissionedAt->copy();
        }

        $tracker = new FileTracker();
        $tracker->fill([
            'tracking_id'           => FileTracker::generateTrackingId(),
            'file_number'           => $info['file_number'],
            'file_title'            => $info['file_title'],
            'file_type'             => 'File',
            'priority'              => FileTracker::PRIORITY_MEDIUM,
            'status'                => FileTracker::STATUS_ACTIVE,
            'department'            => self::OFFICE_DEPARTMENT,
            'created_by'            => auth()->id(),
            'created_by_name'       => auth()->user()->name ?? ($info['commissioned_by'] ?: 'System'),
            'description'           => 'File commissioned in KLAES — tracking opened from the default in-process line.',
            'file_request_type'     => 'MANUAL',
            'date_created'          => $commissionedAt,
            'date_requested'        => $commissionedAt,
            'movement_log'          => [$this->movementEntry($info, $closedAt)],
            'current_office_code'   => self::OFFICE_CODE,
            'current_office_name'   => self::OFFICE_NAME,
            'origin_office_code'    => self::OFFICE_CODE,
            'origin_office_name'    => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            'receiving_office_code' => self::OFFICE_CODE,
            'receiving_office_name' => self::OFFICE_NAME,
            'receiving_officer_name' => $info['commissioned_by'],
            'total_offices'         => 1,
            'completed_offices'     => 1,
        ]);

        $tracker->save();

        // The file now has a real tracker, so any cached "commissioned, untracked"
        // answer for it in this request is stale.
        unset($this->cache[$this->key($info['file_number'])]);

        return $tracker;
    }

    /**
     * Open real tracking for a file at the moment it is commissioned.
     *
     * The commissioning screen asks where the file goes next, so a newly
     * commissioned file starts with TWO lines rather than one:
     *
     *   1. File Commissioning — at the File Commissioning Office, opened at the
     *      commissioning moment and closed as the file is handed on;
     *   2. the onward movement — in transit to the chosen department/unit office,
     *      awaiting acceptance there.
     *
     * This writes a real file_tracker row, which is exactly what ends the derived
     * DIIT state: from here the file is tracked normally and the commissioning
     * line lives on as the first entry of its movement history.
     *
     * @param array<string,mixed> $info        Commissioning info (see hydrate()).
     * @param array<string,mixed> $destination department, office_code, office_name.
     * @return FileTracker|null Null when no destination was chosen — the file then
     *         keeps the derived commissioning line until it is first logged out.
     */
    public function startTracking(array $info, array $destination): ?FileTracker
    {
        $officeCode = trim((string) ($destination['office_code'] ?? ''));
        $officeName = trim((string) ($destination['office_name'] ?? ''));
        $department = trim((string) ($destination['department'] ?? ''));
        // Optional — see destinationFromRequest(). Blank leaves the Movement History's
        // Receiving Officer reading N/A, as every file commissioned before this field
        // existed still does.
        $officerName = trim((string) ($destination['officer_name'] ?? '')) ?: null;
        $officerId = $destination['officer_id'] ?? null;

        if ($officeCode === '' && $officeName === '') {
            return null;
        }

        /** @var Carbon $commissionedAt */
        $commissionedAt = $info['commissioned_at'];
        // The handover happens as the file is commissioned, so the two lines meet:
        // the commissioning line closes exactly where the onward movement opens.
        $handoverAt = Carbon::now()->lessThan($commissionedAt) ? $commissionedAt->copy() : Carbon::now();

        $onward = [
            'log_id'                 => 'LOG-' . $handoverAt->format('YmdHis') . '-' . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'office_code'            => $officeCode ?: $officeName,
            'office_name'            => $officeName ?: $officeCode,
            // A departure row records WHEN THE FILE WAS LOGGED OUT to this office in
            // both fields, exactly as the File Details form writes a manual log-out
            // (see CreateFileTrackerController::store()). The Log Out column reads
            // log_out_*; log_in_* is only surfaced once the file comes back.
            'log_in_date'            => $handoverAt->format('Y-m-d'),
            'log_in_time'            => $handoverAt->format('H:i'),
            'log_out_date'           => $handoverAt->format('Y-m-d'),
            'log_out_time'           => $handoverAt->format('H:i'),
            // In transit to the office. Naming the receiving officer records who is
            // expected to take it; it does not make the row pending their acceptance,
            // which is a separate workflow.
            'status'                 => 'active',
            'notes'                  => 'File dispatched from the File Commissioning Office at commissioning.',
            'timestamp'              => $handoverAt->toIso8601String(),
            'user_id'                => auth()->id(),
            'user_name'              => auth()->user()->name ?? ($info['commissioned_by'] ?: 'System'),
            'receiving_office_code'  => $officeCode ?: $officeName,
            'receiving_office_name'  => $officeName ?: $officeCode,
            'receiving_officer_id'   => $officerId,
            'receiving_officer_name' => $officerName,
            'origin_office_code'     => self::OFFICE_CODE,
            'origin_office_name'     => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
        ];

        $tracker = new FileTracker();
        $tracker->fill([
            'tracking_id'           => FileTracker::generateTrackingId(),
            'file_number'           => $info['file_number'],
            'file_title'            => $info['file_title'],
            'file_type'             => 'File',
            'priority'              => FileTracker::PRIORITY_MEDIUM,
            'status'                => FileTracker::STATUS_ACTIVE,
            'department'            => $department ?: self::OFFICE_DEPARTMENT,
            'destination'           => $officeName ?: $officeCode,
            'created_by'            => auth()->id(),
            'created_by_name'       => auth()->user()->name ?? ($info['commissioned_by'] ?: 'System'),
            'description'           => 'File commissioned in KLAES and dispatched for processing.',
            'file_request_type'     => 'MANUAL',
            'date_created'          => $commissionedAt,
            'date_requested'        => $commissionedAt,
            'movement_log'          => [$this->movementEntry($info, $handoverAt), $onward],
            'current_office_code'   => $officeCode ?: $officeName,
            'current_office_name'   => $officeName ?: $officeCode,
            'origin_office_code'    => self::OFFICE_CODE,
            'origin_office_name'    => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            'receiving_office_code' => $officeCode ?: $officeName,
            'receiving_office_name' => $officeName ?: $officeCode,
            // transformApiTracker() maps receivingOfficerName straight off this column,
            // which is what both the sheet's "Receiving Officer (Holder)" line and the
            // Movement History fall back to. current_handler is not fillable, so it is
            // deliberately not set here — fill() would have dropped it in silence.
            'receiving_officer_id'  => $officerId,
            'receiving_officer_name' => $officerName,
            'total_offices'         => 2,
            'completed_offices'     => 2,
        ]);

        $tracker->save();

        // The file now has a real tracker, so any cached "commissioned, untracked"
        // answer for it in this request is stale.
        unset($this->cache[$this->key($info['file_number'])]);

        return $tracker;
    }

    /**
     * Resolve the "where does this file go next" selection posted by the
     * commissioning screen into the shape startTracking() expects. Returns an
     * empty array when nothing was chosen.
     *
     * @return array<string,string>
     */
    public function destinationFromRequest(\Illuminate\Http\Request $request): array
    {
        $officeCode = trim((string) $request->input('destination_office_code', ''));
        $officeName = trim((string) $request->input('destination_office_name', ''));
        $department = trim((string) $request->input('destination_department', ''));

        if ($officeCode === '' && $officeName === '') {
            return [];
        }

        // Trust the office table over the posted label.
        if ($officeCode !== '') {
            $office = DB::connection($this->connection)
                ->table('offices')
                ->where('office_code', $officeCode)
                ->first(['office_name', 'department']);

            if ($office) {
                $officeName = trim((string) $office->office_name);
                $department = $department ?: trim((string) $office->department);
            }
        }

        // Who receives the file at that office. Optional: the picker leaves it blank
        // when the directory is unavailable, and a missing name must never stop a file
        // number being issued. `ro_12` ids come from other_receiving_officers, which is
        // not the users table — only a plain integer is kept as a user id.
        $officerName = trim((string) $request->input('destination_receiving_officer_name', ''));
        $officerId = trim((string) $request->input('destination_receiving_officer_id', ''));

        return [
            'office_code'   => $officeCode,
            'office_name'   => $officeName,
            'department'    => $department,
            'officer_name'  => $officerName ?: null,
            'officer_id'    => ctype_digit($officerId) ? (int) $officerId : null,
        ];
    }

    /** Normalized cache key for a file number. */
    protected function key(?string $fileNumber): string
    {
        return mb_strtoupper(trim((string) $fileNumber));
    }
}
