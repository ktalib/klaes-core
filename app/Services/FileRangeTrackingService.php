<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\FileTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Opening tracking line for a newly indexed file, placed by its registry range.
 *
 * config/file_ranges.php is the authority on where a file physically lives: its
 * prefix + year resolve to a registry ("Registry 1/2/3") and a zone —
 *   - archive : the Digital Archive, on a rack/shelf, with us;
 *   - pool    : the Pool Office, with us but not yet processed.
 * Until now that answer was only ever DERIVED at search time by
 * FileLocationResolver, so an indexed file had no file_tracker row and did not
 * appear in the File Log Table until somebody logged it out for the first time.
 *
 * This writes that opening row at indexing time, so every indexed file starts
 * with a real tracking line saying where it is:
 *
 *      Registry 2 — Pool Office — "Filed in Pool Office" — indexing date
 *
 * The row is written as COMPLETED (FileTracker::STATUS_COMPLETED), i.e. "with
 * us, at rest", NOT as an active movement — the file has not been logged out to
 * anybody. FileLocationResolver treats a COMPLETED tracker as terminal, so the
 * file still reports IN_ARCHIVE / IN_POOL_OFFICE rather than IN_TRANSIT.
 *
 * Two cases deliberately get NO row:
 *
 *   1. No range match — the file was never transferred to us
 *      (REFER_TO_ORIGINAL_REGISTRY). We cannot claim to hold it.
 *   2. KLAES-commissioned files (present in mls_file_no) — a commissioned file
 *      is NEW: it was created at the File Commissioning Office and is still
 *      moving from office to office, so it is not in the archive at all. Its
 *      opening line belongs to FileCommissioningTrackingService (DIIT), and
 *      writing an "at rest in Registry N" row would silently replace it.
 *
 * Idempotent: a file that already has any file_tracker row keeps it — real
 * tracking always wins over this default line.
 */
class FileRangeTrackingService
{
    /** Offices this service files a file into (no rows in `offices`; see class doc). */
    public const ARCHIVE_OFFICE_CODE = 'ARC';
    public const POOL_OFFICE_CODE    = 'POOL';
    public const OFFICE_DEPARTMENT   = 'Land';

    /**
     * Badge text rendered for the opening line (movement entry `status_label`).
     * This row REPLACES the File Log Table's hard-coded "In Archive" home row (see
     * the `_range_home` marker below), so it uses the same wording — the zone is
     * the only difference.
     */
    public const LABEL_ARCHIVE = 'In Archive';
    public const LABEL_POOL    = 'In Pool Office';

    public function __construct(
        private FileLocationResolver $locationResolver,
        private FileCommissioningTrackingService $commissioningService,
    ) {
    }

    /**
     * Create the opening tracking line for a freshly indexed file.
     *
     * Best-effort: the file is already indexed by the time this runs, so a
     * failure here is logged and reported, never thrown.
     *
     * @return array{created:bool, reason:string, registry:?string, zone:?string, location:?string, tracker_id:?int}
     */
    public function openForIndexing(FileIndexing $record): array
    {
        $fileNumber = trim((string) ($record->file_number ?: $record->temp_file_no ?: ''));

        if ($fileNumber === '') {
            return $this->outcome(false, 'no_file_number');
        }

        try {
            if ($this->hasTracker($fileNumber)) {
                return $this->outcome(false, 'already_tracked');
            }

            // A commissioned file is not in the registry yet — DIIT owns its first line.
            if ($this->commissioningService->isCommissioned($fileNumber)) {
                return $this->outcome(false, 'commissioned');
            }

            $range = $this->locationResolver->matchRange($fileNumber);
            if ($range === null) {
                return $this->outcome(false, 'no_range_match');
            }

            $isArchive  = ($range['zone'] ?? '') === 'archive';
            $registry   = trim((string) ($range['registry'] ?? '')) ?: 'Registry';
            $officeCode = $isArchive ? self::ARCHIVE_OFFICE_CODE : self::POOL_OFFICE_CODE;
            $officeName = $registry . ' — ' . ($isArchive ? 'Digital Archive' : 'Pool Office');
            $label      = $isArchive ? self::LABEL_ARCHIVE : self::LABEL_POOL;

            $rackShelf = $isArchive
                ? ($record->shelf_location ?: $this->locationResolver->rackShelfFor($fileNumber))
                : null;

            $filedAt = $this->filedAt($record);
            $userName = Auth::user()->name ?? 'System';

            // The OFFICE column of the File Log Table reads the movement entry's own
            // office_name, and this row replaces the home row that used to carry the
            // shelf/rack — so keep that detail here when the file has one.
            $movementOfficeName = $officeName . ($rackShelf ? ' — Shelf/Rack ' . $rackShelf : '');

            $movement = [
                'log_id'      => 'RNG-' . strtoupper(substr(sha1('range|' . $fileNumber), 0, 10)),
                'office_code' => $officeCode,
                'office_name' => $movementOfficeName,
                // The file is sitting in its home registry, so there is no arrival to
                // record — Log In stays blank. Log Out carries the indexing date: the
                // moment the file was released from indexing into this registry.
                'log_in_date'  => null,
                'log_in_time'  => null,
                'log_out_date' => $filedAt->format('Y-m-d'),
                'log_out_time' => $filedAt->format('H:i'),
                'status'       => 'completed',
                'status_label' => $label,
                'notes'        => sprintf(
                    'File indexed and filed in %s%s.',
                    $officeName,
                    $rackShelf ? ' — Rack/Shelf ' . $rackShelf : ''
                ),
                'timestamp'  => $filedAt->toIso8601String(),
                'user_id'    => Auth::id(),
                'user_name'  => $userName,
                'receiving_office_code'    => $officeCode,
                'receiving_office_name'    => $movementOfficeName,
                'receiving_officer_id'     => null,
                'receiving_officer_name'   => $isArchive ? 'Archive' : 'Pool Office',
                'origin_office_code'       => $officeCode,
                'origin_office_name'       => $officeName,
                'origin_office_department' => self::OFFICE_DEPARTMENT,
                'in_digital_archive'       => $isArchive,
                // Marks the row as the range-derived opening line rather than a real
                // logged movement, so the UI can render it read-only.
                '_range_home'              => true,
            ];

            $tracker = new FileTracker();
            $tracker->fill([
                'tracking_id'   => $record->tracking_id ?: FileTracker::generateTrackingId(),
                'file_number'   => $fileNumber,
                'file_title'    => $record->file_title,
                'file_type'     => 'File',
                'priority'      => FileTracker::PRIORITY_MEDIUM,
                // ACTIVE so the File Log Table lists the file under Active Files rather
                // than Completed (a file sitting in its registry was never "completed" —
                // nothing was ever requested). This is a LISTING choice only: the file is
                // not logged out to anyone, so FileLocationResolver specifically excludes
                // SYSTEM trackers from its in-transit test and answers from the range
                // instead. Changing this back to COMPLETED moves the card to the
                // Completed tab; it does not change where the file is reported to be.
                'status'        => FileTracker::STATUS_ACTIVE,
                // The column defaults to PENDING, which badges the card "Pending
                // acceptance". Nobody has to accept a file that never left its own
                // registry, so settle it up front.
                'assignment_status' => FileTracker::ASSIGNMENT_ACCEPTED,
                'department'    => self::OFFICE_DEPARTMENT,
                'destination'   => $officeName,
                'created_by'    => Auth::id(),
                'created_by_name' => $userName,
                'description'   => 'Opening tracking line created at indexing from the registry range.',
                'file_request_type' => 'SYSTEM',
                'date_created'  => $filedAt,
                'date_requested' => $filedAt,
                'movement_log'  => [$movement],
                'current_office_code' => $officeCode,
                'current_office_name' => $officeName,
                'origin_office_code'  => $officeCode,
                'origin_office_name'  => $officeName,
                'origin_office_department' => self::OFFICE_DEPARTMENT,
                'receiving_office_code' => $officeCode,
                'receiving_office_name' => $officeName,
                'total_offices'     => 1,
                'completed_offices' => 1,
                'module'            => 'file_indexing',
                'in_digital_archive' => $isArchive,
            ]);

            $tracker->save();

            Log::info('FileRangeTrackingService - opened range tracking line', [
                'file_indexing_id' => $record->id,
                'file_number'      => $fileNumber,
                'registry'         => $registry,
                'zone'             => $range['zone'],
                'tracker_id'       => $tracker->id,
            ]);

            return [
                'created'    => true,
                'reason'     => 'created',
                'registry'   => $registry,
                'zone'       => $range['zone'],
                'location'   => $officeName . ($rackShelf ? ' — Rack/Shelf ' . $rackShelf : ''),
                'tracker_id' => (int) $tracker->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('FileRangeTrackingService - failed to open range tracking line', [
                'file_indexing_id' => $record->id ?? null,
                'file_number'      => $fileNumber,
                'error'            => $e->getMessage(),
            ]);

            return $this->outcome(false, 'error');
        }
    }

    /**
     * When the file was filed. The indexing row's own creation time is the moment
     * it entered the registry as far as KLAES is concerned; a backdated import
     * that carries no timestamp falls back to now.
     */
    private function filedAt(FileIndexing $record): Carbon
    {
        try {
            return $record->created_at ? Carbon::parse($record->created_at) : Carbon::now();
        } catch (\Throwable $e) {
            return Carbon::now();
        }
    }

    /**
     * Plain equality, no UPPER/TRIM: the SQL_Latin1_General_CP1_CI_AS collation
     * already matches case-insensitively and ignores trailing spaces, so this
     * seeks the file_number index instead of scanning.
     */
    private function hasTracker(string $fileNumber): bool
    {
        return DB::connection('sqlsrv')
            ->table('file_tracker')
            ->where('file_number', $fileNumber)
            ->exists();
    }

    /** @return array{created:bool, reason:string, registry:null, zone:null, location:null, tracker_id:null} */
    private function outcome(bool $created, string $reason): array
    {
        return [
            'created'    => $created,
            'reason'     => $reason,
            'registry'   => null,
            'zone'       => null,
            'location'   => null,
            'tracker_id' => null,
        ];
    }
}
