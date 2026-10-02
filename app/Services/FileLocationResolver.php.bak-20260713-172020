<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\FileTracker;
use App\Models\MissingFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quick Search & File Location engine.
 *
 * Given a file number, returns a definitive location + status + next action so
 * that no search ever ends without a clear outcome. Shared by the web Quick
 * Search and the SCB Monitor mobile endpoints.
 *
 * Precedence:
 *   1. file_tracker.status = ACTIVE -> logged out -> IN_TRANSIT (ground truth:
 *      a physically logged-out file wins over a stale SCB found/not-found override).
 *   2. Manual override (location_status_manual + tracking_status on the indexing row).
 *   3. file_tracker.status = COMPLETED -> logged in -> IN_ARCHIVE
 *        - file_tracker.status = CANCELLED -> ignore, fall through
 *   4. No active tracker -> parse prefix + year, match config/file_ranges.php
 *        - zone=archive + scanned/indexed -> IN_ARCHIVE
 *        - zone=archive + not scanned      -> FILE_NOT_FOUND (archive-missing)
 *        - zone=pool                        -> IN_POOL_OFFICE
 *   3. No tracker and no range match       -> REFER_TO_ORIGINAL_REGISTRY
 */
class FileLocationResolver
{
    public const STATUS_IN_TRANSIT  = 'IN_TRANSIT';
    public const STATUS_IN_ARCHIVE  = 'IN_ARCHIVE';
    public const STATUS_IN_POOL     = 'IN_POOL_OFFICE';
    public const STATUS_NOT_FOUND   = 'FILE_NOT_FOUND';
    public const STATUS_REFER       = 'REFER_TO_ORIGINAL_REGISTRY';

    // SCB-response outcome statuses (set when an SCB Monitor replies Found / Not Found).
    public const STATUS_IN_ARCHIVE_FOUND     = 'IN_ARCHIVE_FOUND';
    public const STATUS_IN_ARCHIVE_NOT_FOUND = 'IN_ARCHIVE_NOT_FOUND';
    public const STATUS_IN_POOL_FOUND        = 'IN_POOL_OFFICE_FOUND';
    public const STATUS_IN_POOL_NOT_FOUND    = 'IN_POOL_OFFICE_NOT_FOUND';

    // File is recorded in missing_files — SCB already searched and confirmed missing.
    public const STATUS_MISSING_FILE = 'MISSING_FILE';

    // Non-indexed files: cannot be located by normal search → blind request to SCB.
    public const STATUS_PENDING_FILE       = 'PENDING_FILE';
    public const STATUS_BLIND_REQUEST_SENT = 'BLIND_REQUEST_SENT';

    // Generic holding office a KANGIS approval-workflow file parks in after DG
    // approval. It is not the file's real destination — that is stored on the
    // tracker's workflow_config (see resolveTransitLocation()).
    protected const KANGIS_QUEUE_OFFICE = 'Department Queue';

    /** Cache of shelf lookups within a single request. */
    protected array $rackShelfCache = [];

    /** Duplicate-registry flag for the file currently being resolved (set per resolve()). */
    protected ?array $currentDuplicateFlag = null;

    /**
     * Resolve the location/status for a file number.
     *
     * @return array{
     *   file_number:string, status:string, registry:?string, zone:?string,
     *   current_location:?string, rack_shelf:?string, file_tracker_id:?int,
     *   tracker:?FileTracker, indexing:?FileIndexing,
     *   next_action:string, slip_variant:?string, can_send_fr:bool
     * }
     */
    public function resolve(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        $variants   = $this->variants($fileNumber);

        // Flag if this file number is registered in duplicate_fileno (CofO collected/ready,
        // duplicate, temporary, or withdrawn/cancelled/revoked). Surfaced on every outcome.
        // Matched on the EXACT number (temp "(T)" preserved): a temporary file is its own
        // record, so it must only inherit a flag registered against the "(T)" number itself —
        // never the one on its stripped base. Otherwise a "(T)" file whose base is a Duplicate
        // would be wrongly diverted to the Director Land instead of showing the requester form.
        $this->currentDuplicateFlag = $this->duplicateFlagFor($this->duplicateVariants($fileNumber));

        $indexing = $this->findIndexing($variants);
        $tracker  = $this->findTracker($variants);

        // ── 1. A non-terminal tracker means the file is physically logged out -> IN_TRANSIT.
        //        This is the ground truth: once the file has been logged, it wins over
        //        any stale SCB found/not-found override left on the indexing row (which
        //        was set while the file was still in the archive awaiting collection).
        //        A tracker is "still out" for any status except COMPLETED (logged back
        //        in) or CANCELLED — this matches the rest of the app (see the file-tracker
        //        API's "NOT IN ('COMPLETED','CANCELLED')" logged-out test). Approval-
        //        workflow files carry statuses like submitted / recommended / approved /
        //        in_processing while they sit at another office, and those are all "out".
        if ($tracker
            && !$this->isTerminalTrackerStatus($tracker->status)
            && !empty($tracker->movement_log)   // guard against a stuck tracker whose movements were all deleted
        ) {                                     // (a movement still "pending_acceptance" at its destination is still in transit)
            $location  = $this->resolveTransitLocation($tracker);
            $heldSince = $this->holderSince($tracker);

            return $this->result($fileNumber, self::STATUS_IN_TRANSIT, [
                'registry'         => $tracker->origin_office_name,
                'current_location' => $location,
                // The file's assigned home shelf still applies while it is out — it is
                // where the file returns to — so surface it for in-transit files too.
                'rack_shelf'       => $this->getRackShelf($fileNumber, $indexing),
                'file_tracker_id'  => $tracker->id,
                'tracker'          => $tracker,
                'indexing'         => $indexing,
                'next_action'      => 'Print Tracking Confirmation Slip',
                'slip_variant'     => 'tracking_confirmation',
                // How long the file has been with the current holder.
                'held_since'           => $heldSince?->format('Y-m-d g:i A'),
                'days_with_holder'     => $this->daysWithHolder($heldSince),
                'duration_with_holder' => $this->durationLabel($heldSince),
            ]);
        }

        // ── 0. Manual override set from the File Quick Search interface ──
        if ($indexing && !empty($indexing->location_status_manual) && !empty($indexing->tracking_status)) {
            $meta = $this->actionMetaFor($indexing->tracking_status, $this->indexingRegistry($indexing), $this->isDcivFile($indexing));
            return $this->result($fileNumber, $indexing->tracking_status, array_merge([
                'current_location' => $indexing->current_location,
                'file_tracker_id'  => $indexing->file_tracker_id,
                'indexing'         => $indexing,
                'tracker'          => $tracker,
                'manual'           => true,
            ], $meta));
        }

        // ── 2. Tracker logged back in -> Archive ──
        if ($tracker && strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
            // Logged back in / with us -> Archive (must be confirmed by SCB before logging out).
            $rackShelf = $this->getRackShelf($fileNumber, $indexing);
            $range     = $this->matchRange($fileNumber);
            $registry  = $range['registry'] ?? null;

            return $this->result($fileNumber, self::STATUS_IN_ARCHIVE, array_merge([
                'registry'         => $registry,
                'zone'             => 'archive',
                'current_location' => $this->archiveLocation($registry, $rackShelf),
                'rack_shelf'       => $rackShelf,
                'file_tracker_id'  => $tracker->id,
                'tracker'          => $tracker,
                'indexing'         => $indexing,
            ], $this->actionMetaFor(self::STATUS_IN_ARCHIVE, $registry, $this->isDcivFile($indexing))));
        }

        // ── 3. Not indexed at all -> Pending File (blind request) ──
        if ($indexing === null) {
            // A file that was decommissioned/superseded by a land transaction has its indexing
            // hard-deleted, so it lands here. Annotate it with its successor instead of treating
            // it as an ordinary un-indexed file, so the requester is pointed to the live file.
            $superseded = $this->supersededInfoFor($variants);
            if ($superseded !== null) {
                return $this->result($fileNumber, self::STATUS_PENDING_FILE, array_merge([
                    'current_location'   => $superseded['successor']
                        ? 'Superseded by ' . $superseded['successor']
                        : 'Superseded / decommissioned',
                    'superseded_by'      => $superseded['successor'],
                    'superseded_reason'  => $superseded['reason'],
                ], $this->actionMetaFor(self::STATUS_PENDING_FILE)));
            }

            return $this->result($fileNumber, self::STATUS_PENDING_FILE, array_merge([
                'current_location' => 'Not indexed',
            ], $this->actionMetaFor(self::STATUS_PENDING_FILE)));
        }

        // ── 4. Indexed -> registry range lookup ──
        $range = $this->matchRange($fileNumber);

        if ($range === null) {
            // Indexed but outside any known registry range -> refer.
            $registryFallback = $this->indexingRegistry($indexing);
            return $this->result($fileNumber, self::STATUS_REFER, array_merge([
                'indexing'         => $indexing,
                'registry'         => $registryFallback,
                'current_location' => $registryFallback,
            ], $this->actionMetaFor(self::STATUS_REFER, $registryFallback, $this->isDcivFile($indexing))));
        }

        $registryName = $range['registry'] ?? $this->indexingRegistry($indexing);

        if ($range['zone'] === 'archive') {
            $rackShelf = $this->getRackShelf($fileNumber, $indexing);
            return $this->result($fileNumber, self::STATUS_IN_ARCHIVE, array_merge([
                'registry'         => $registryName,
                'zone'             => 'archive',
                'current_location' => $this->archiveLocation($registryName, $rackShelf),
                'rack_shelf'       => $rackShelf,
                'indexing'         => $indexing,
            ], $this->actionMetaFor(self::STATUS_IN_ARCHIVE, $registryName, $this->isDcivFile($indexing))));
        }

        // zone = pool
        return $this->result($fileNumber, self::STATUS_IN_POOL, array_merge([
            'registry'         => $registryName,
            'zone'             => 'pool',
            'current_location' => ($registryName ?: 'Pool Office') . ' — Pool Office',
            'indexing'         => $indexing,
        ], $this->actionMetaFor(self::STATUS_IN_POOL, $registryName, $this->isDcivFile($indexing))));
    }

    /**
     * Persist the resolved snapshot onto the matching file_indexings row.
     */
    public function persist(array $result): void
    {
        /** @var FileIndexing|null $indexing */
        $indexing = $result['indexing'] ?? null;
        if (!$indexing) {
            return;
        }

        $indexing->forceFill([
            'tracking_status'  => $result['status'],
            'current_location' => $result['current_location'],
            'file_tracker_id'  => $result['file_tracker_id'],
        ])->save();
    }

    /**
     * Parse a file number into a registry range, longest-prefix first.
     *
     * @return array{zone:string, registry:string}|null
     */
    public function matchRange(string $fileNumber): ?array
    {
        $parsed = $this->parse($fileNumber);
        if ($parsed === null) {
            return null;
        }
        [$prefix, $year] = $parsed;

        $ranges = (array) config('file_ranges.ranges', []);

        // Candidate prefixes from longest to shortest (e.g. CON-RES-RC, CON-RES, CON, ...).
        $segments = explode('-', $prefix);
        for ($take = count($segments); $take >= 1; $take--) {
            $candidate = implode('-', array_slice($segments, 0, $take));
            if (!isset($ranges[$candidate])) {
                continue;
            }
            foreach ($ranges[$candidate] as $range) {
                if ($year >= (int) $range['from'] && $year <= (int) $range['to']) {
                    return ['zone' => $range['zone'], 'registry' => $range['registry']];
                }
            }
        }

        return null;
    }

    /**
     * Split "PREFIX-YYYY-..." into [prefix, year]. Returns null if no 4-digit
     * year segment is present.
     *
     * @return array{0:string,1:int}|null
     */
    public function parse(string $fileNumber): ?array
    {
        $normalized = strtoupper(trim($fileNumber));
        // Strip a trailing temp marker like "(T)".
        $normalized = preg_replace('/\s*\(\s*T\s*\)\s*$/', '', $normalized);

        if (preg_match('/^([A-Z][A-Z\-]*?)-(\d{4})(?:\-|$)/', $normalized, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return null;
    }

    /**
     * Public rack/shelf lookup for a file number — its assigned home shelf,
     * independent of whether the file is currently in transit. Resolves the
     * indexing row itself for the fallback path.
     */
    public function rackShelfFor(string $fileNumber): ?string
    {
        $indexing = $this->findIndexing($this->variants($fileNumber));
        return $this->getRackShelf($fileNumber, $indexing);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    protected function result(string $fileNumber, string $status, array $extra): array
    {
        $base = [
            'file_number'      => $fileNumber,
            'status'           => $status,
            'registry'         => null,
            'zone'             => null,
            'current_location' => null,
            'rack_shelf'       => null,
            'file_tracker_id'  => null,
            'tracker'          => null,
            'indexing'         => null,
            'next_action'      => '',
            'slip_variant'     => null,
            'can_send_fr'      => false,
            'can_log'          => false,
            'is_blind'         => false,
            'manual'           => false,
            'duplicate_flag'   => $this->currentDuplicateFlag,
            // Temporary file — the searched number carries the "(T)" suffix. Such a
            // file is a standalone record (see variants()); the UI badges it.
            'is_temp_file'     => (bool) preg_match('/\(\s*T\s*\)\s*$/i', trim($fileNumber)),
            'is_missing_file'  => false,
            'superseded_by'    => null,
            'superseded_reason' => null,
            // Holder-duration fields — only populated on the IN_TRANSIT outcome.
            'held_since'           => null,
            'days_with_holder'     => null,
            'duration_with_holder' => null,
        ];

        $merged = array_merge($base, $extra);

        // Fill in registry/location defaults from indexing when not provided.
        if (($merged['registry'] ?? null) === null && isset($merged['indexing']) && $merged['indexing'] instanceof FileIndexing) {
            $fallback = $this->indexingRegistry($merged['indexing']);
            if ($fallback) {
                $merged['registry'] = $fallback;
                if (($merged['current_location'] ?? null) === null) {
                    $merged['current_location'] = $fallback;
                }
            }
        }

        // Always surface the file's home Rack/Shelf when one exists, on EVERY
        // outcome — not just the archive branches that resolve it inline. This is
        // the assigned shelf, independent of whether the file is currently logged
        // out (IN_TRANSIT), so the UI can show "Rack/Shelf" right after Registry
        // for every file number that has one. Cached per request, so cheap.
        if (empty($merged['rack_shelf'])) {
            $indexingRow = ($merged['indexing'] ?? null) instanceof FileIndexing ? $merged['indexing'] : null;
            $merged['rack_shelf'] = $this->getRackShelf($fileNumber, $indexingRow);
        }

        // Check if this file number exists in the missing_files table (marked as
        // missing/not-yet-found). When it does, the status is overridden to MISSING_FILE,
        // and the "Send Blind Request to SCB Monitor" button changes to
        // "Send Blind Request to the Original Registry" instead,
        // and the record saves without sending the request to the SCB.
        try {
            if (Schema::connection('sqlsrv')->hasTable('missing_files')) {
                $merged['is_missing_file'] = DB::connection('sqlsrv')
                    ->table('missing_files')
                    ->where('file_number', $fileNumber)
                    ->where('status', '!=', MissingFile::STATUS_FOUND)
                    ->exists();
            }
        } catch (\Throwable $e) {
            // Fail open — treat as not in missing_files if the table is missing.
        }

        // Override status when the file is confirmed missing — SCB already searched and
        // could not find it. This takes precedence over the resolver's inferred status
        // (IN_ARCHIVE, FILE_NOT_FOUND, etc.) so the UI shows "Missing File" consistently.
        if ($merged['is_missing_file']) {
            $merged['status'] = self::STATUS_MISSING_FILE;
            // Merge in the action meta for MISSING_FILE so next_action etc. are correct.
            $meta = $this->actionMetaFor(self::STATUS_MISSING_FILE);
            foreach ($meta as $k => $v) {
                $merged[$k] = $v;
            }
        }

        return $merged;
    }

    /**
     * If any variant of the file number was genuinely decommissioned/superseded by a land
     * transaction, return ['successor' => ?string, 'reason' => ?string]; otherwise null.
     * false_decommissioning = 1 rows are Title-Status flags, not real decommissions.
     */
    protected function supersededInfoFor(array $variants): ?array
    {
        $variants = array_values(array_filter(array_map('trim', $variants), fn($v) => $v !== ''));
        if (empty($variants)) {
            return null;
        }

        try {
            if (!Schema::connection('sqlsrv')->hasTable('decommissioned_files')) {
                return null;
            }

            $hasSuccessor = Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no');

            $query = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where(function ($q) use ($variants) {
                    $q->whereIn('file_no', $variants)->orWhereIn('mls_file_no', $variants);
                });

            if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'false_decommissioning')) {
                $query->where(function ($q) {
                    $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
                });
            }

            $row = $query->orderByDesc('id')->first();
            if (!$row) {
                return null;
            }

            $successor = $hasSuccessor ? ($row->successor_file_no ?: null) : null;
            $reason = $row->decommissioning_reason ?? null;

            // Legacy fallback: many older reasons embed the successor at the end
            // (e.g. "Plot Merger to CON-COM-2023-197" or "... → CON-COM-2023-197").
            if (!$successor && $reason && preg_match('/(?:to|→|->)\s*([A-Z0-9\/\-]+)\s*$/i', $reason, $m)) {
                $successor = $m[1];
            }

            return ['successor' => $successor, 'reason' => $reason];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Derive a registry/location label from the indexing row.
     */
    protected function indexingRegistry(?FileIndexing $indexing): ?string
    {
        if (!$indexing) {
            return null;
        }

        $candidates = [
            trim((string) ($indexing->physical_registry ?? '')),
            trim((string) ($indexing->registry ?? '')),
            trim((string) ($indexing->general_registry ?? '')),
        ];

        foreach ($candidates as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * True when the file is flagged as under DCIV investigation (dciv_status=1)
     * — i.e. it was linked as a related file on a DCIV record via
     * master_dciv_links (see DcivGenerationController::store()).
     */
    protected function isDcivFile(?FileIndexing $indexing): bool
    {
        return (bool) $indexing && (int) ($indexing->dciv_status ?? 0) === 1;
    }

    /**
     * Full "Under Investigation" payload for the Quick Search / mobile File
     * Search result card. A file is under DCIV investigation two ways:
     *   (a) it's a related file flagged dciv_status=1 on its own indexing row
     *       (linked to a DCIV master via master_dciv_links), or
     *   (b) it IS the DCIV master record itself — either its own registry is
     *       the DCIV Registry, or it has related files linked to it. The
     *       master's own indexing row is never stamped with dciv_status/
     *       dciv_reason (only the related files are), so its reason is looked
     *       up from dciv_file_no instead.
     * Shared by web Quick Search and mobile File Search so both show the same
     * red badge + reason for the same file.
     *
     * @return array{status:bool, fileno:?string, reason:?string, related_files:array}
     */
    public function dcivInfoFor(string $fileNumber, ?FileIndexing $indexing, ?string $registry): array
    {
        $relatedFiles = \App\Models\MasterDcivLink::whereRaw(
                'UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper(trim($fileNumber))]
            )
            ->orderBy('id')
            ->get(['related_file_number', 'related_file_title', 'related_file_type', 'dciv_reason']);

        $flaggedAsRelated = $this->isDcivFile($indexing);
        $isMaster = $relatedFiles->isNotEmpty() || (is_string($registry) && stripos($registry, 'DCIV') !== false);

        $reason = $indexing?->dciv_reason
            ?: optional($relatedFiles->first())?->dciv_reason
            ?: ($isMaster
                ? \App\Models\DcivFileNo::whereRaw(
                    'UPPER(LTRIM(RTRIM(full_file_number))) = ?', [strtoupper(trim($fileNumber))]
                )->where(function ($q) {
                    $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                })->value('dciv_reason')
                : null);

        return [
            'status'        => $flaggedAsRelated || $isMaster,
            'fileno'        => $indexing?->dciv_fileno,
            'reason'        => $reason,
            'related_files' => $relatedFiles
                ->map(fn ($r) => $r->only(['related_file_number', 'related_file_title', 'related_file_type']))
                ->toArray(),
        ];
    }

    /**
     * Look up the file in duplicate_fileno and, if present, return a normalized
     * badge payload (category + display label + colour). Surfaced on every Quick
     * Search / mobile File Search result so the front desk can see at a glance
     * that the file is flagged as CofO-collected/ready, a duplicate, a temporary
     * file, or withdrawn/cancelled/revoked.
     *
     * @return array{category:string,label:string,color:string,comment:?string,registry:?string}|null
     */
    protected function duplicateFlagFor(array $variants): ?array
    {
        if (empty($variants)) {
            return null;
        }

        $row = DB::connection('sqlsrv')
            ->table('duplicate_fileno')
            ->whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first(['category', 'comment', 'registry']);

        if (!$row || empty($row->category)) {
            return null;
        }

        // The comment chip is only useful when it adds information beyond the category
        // itself (the import often copies the category into the comment column verbatim).
        $comment = trim((string) ($row->comment ?? ''));
        if ($comment === '' || strtoupper($comment) === strtoupper(trim((string) $row->category))) {
            $comment = null;
        }

        return array_merge(
            $this->duplicateCategoryMeta($row->category),
            ['comment' => $comment, 'registry' => $row->registry ?: null]
        );
    }

    /**
     * Map a raw duplicate_fileno.category value to a display label + badge colour.
     *
     * `directs_to_land` controls whether a flagged file is pulled out of the normal
     * SCB search and instead re-directed to the Director Land (Land Department) to
     * resolve the duplication. Only genuine duplicates carry a duplication to
     * resolve — a Temporary File has none, so it follows the normal SCB workflow
     * (Send File Search Request) while still showing its "Temporary File" badge.
     */
    protected function duplicateCategoryMeta(string $category): array
    {
        $key = strtoupper(trim($category));

        return match ($key) {
            '[COFO_COLLECTED]' => ['category' => $category, 'label' => 'CofO Collected', 'color' => '#16a34a', 'directs_to_land' => true],
            '[COFO_READY]'     => ['category' => $category, 'label' => 'CofO Ready',     'color' => '#2563eb', 'directs_to_land' => true],
            'DUPLICATE FILES'  => ['category' => $category, 'label' => 'Duplicate File', 'color' => '#d97706', 'directs_to_land' => true],
            'TEMP FILES'       => ['category' => $category, 'label' => 'Temporary File', 'color' => '#7c3aed', 'directs_to_land' => false],
            'W/C/R FILES'      => ['category' => $category, 'label' => 'Withdrawn / Cancelled / Revoked', 'color' => '#dc2626', 'directs_to_land' => true],
            default            => ['category' => $category, 'label' => trim($category, '[]'), 'color' => '#475569', 'directs_to_land' => true],
        };
    }

    /**
     * Map a status to its next-action label, slip variant, and the available
     * actions (send FSR, log file, blind request). Used for manual/SCB-driven
     * statuses and by callers.
     *
     * A file routed to the DCIV Registry — either because that IS its own
     * registry, or because it is flagged dciv_status=1 (linked as a related
     * file on a DCIV investigation via master_dciv_links) — is searched by the
     * DCIV Director rather than the generic SCB Monitor pool, so the action
     * label reflects that instead.
     *
     * @return array{next_action:string, slip_variant:?string, can_send_fr:bool, can_log:bool, is_blind:bool}
     */
    public function actionMetaFor(string $status, ?string $registry = null, bool $isDciv = false): array
    {
        $base = ['next_action' => '', 'slip_variant' => null, 'can_send_fr' => false, 'can_log' => false, 'is_blind' => false];

        $isDciv = $isDciv || (is_string($registry) && stripos($registry, 'DCIV') !== false);
        $searcher = $isDciv ? 'DCIV Director' : 'SCB Monitor';

        return array_merge($base, match ($status) {
            self::STATUS_IN_TRANSIT =>
                ['next_action' => 'Print Tracking Confirmation Slip', 'slip_variant' => 'tracking_confirmation'],

            // Archive / Pool both require a physical confirmation before logging.
            self::STATUS_IN_ARCHIVE, self::STATUS_IN_POOL =>
                ['next_action' => "Send File Search Request to {$searcher}", 'can_send_fr' => true],

            // SCB replied FOUND -> log the file (no slip yet; not logged out).
            self::STATUS_IN_ARCHIVE_FOUND, self::STATUS_IN_POOL_FOUND =>
                ['next_action' => 'Log the file', 'can_log' => true],

            // SCB replied NOT FOUND -> refer to original registry.
            self::STATUS_IN_ARCHIVE_NOT_FOUND, self::STATUS_IN_POOL_NOT_FOUND =>
                ['next_action' => 'Refer to Original Registry', 'slip_variant' => 'refer_registry'],

            // Non-indexed file -> blind request.
            self::STATUS_PENDING_FILE =>
                ['next_action' => "Send Blind Request to {$searcher}", 'can_send_fr' => true, 'is_blind' => true],
            self::STATUS_BLIND_REQUEST_SENT =>
                ['next_action' => 'Awaiting SCB feedback', 'is_blind' => true],

            self::STATUS_NOT_FOUND =>
                ['next_action' => 'Print Missing File Confirmation Slip', 'slip_variant' => 'missing'],

            // File is recorded in missing_files — SCB already searched and confirmed
            // missing. The next step is to send a request to the Original Registry.
            self::STATUS_MISSING_FILE =>
                ['next_action' => 'Request to Original Registry', 'can_send_fr' => true, 'is_blind' => true],

            // Indexed but outside any known registry range — send it to be
            // physically searched first. Only if reported Not Found does the
            // outcome (IN_ARCHIVE_NOT_FOUND) offer the Refer-to-Original-Registry slip.
            self::STATUS_REFER =>
                ['next_action' => "Send File Search Request to {$searcher}", 'can_send_fr' => true],

            default => [],
        });
    }

    /**
     * Combine a base location status (IN_ARCHIVE / IN_POOL_OFFICE / PENDING_FILE)
     * with an SCB found/not-found result into the outcome status.
     */
    public function combineScbOutcome(?string $resolvedStatus, bool $found): string
    {
        $isPool = $resolvedStatus === self::STATUS_IN_POOL;
        if ($found) {
            return $isPool ? self::STATUS_IN_POOL_FOUND : self::STATUS_IN_ARCHIVE_FOUND;
        }
        return $isPool ? self::STATUS_IN_POOL_NOT_FOUND : self::STATUS_IN_ARCHIVE_NOT_FOUND;
    }

    protected function archiveLocation(?string $registry, ?string $rackShelf): string
    {
        $parts = array_filter([$registry, $rackShelf ? 'Rack/Shelf ' . $rackShelf : null]);
        return $parts ? implode(' — ', $parts) : ($registry ?? 'Digital Archive');
    }

    /**
     * Build file-number match variants (handles temp "(T)" suffix + casing).
     */
    protected function variants(string $fileNumber): array
    {
        // A temporary "(T)" file is a record in its own right — it has its own
        // indexing row (matched via temp_file_no), its own trackers and its own
        // shelf. Never fall back to the stripped base number: doing so made a
        // "(T)" search inherit the PARENT file's tracker/indexing, showing the
        // parent's In-Transit holder instead of acting as a standalone request.
        $candidates = [$fileNumber];

        $variants = [];
        foreach ($candidates as $candidate) {
            $variants[] = $candidate;
            $variants[] = strtoupper($candidate);
            $variants[] = strtolower($candidate);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Build EXACT match variants for the duplicate_fileno lookup — casing only, with
     * the temp "(T)" suffix preserved (never stripped to the base). A temporary file
     * is a record in its own right, so it must only pick up a duplicate_fileno flag
     * registered against the "(T)" number itself.
     */
    protected function duplicateVariants(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $fileNumber,
            strtoupper($fileNumber),
            strtolower($fileNumber),
        ])));
    }

    protected function findIndexing(array $variants): ?FileIndexing
    {
        if (empty($variants)) {
            return null;
        }

        return FileIndexing::on('sqlsrv')
            ->where(function ($query) use ($variants) {
                $query->whereIn('file_number', $variants)
                    ->orWhereIn('new_kangis_file_no', $variants)
                    ->orWhereIn('kangis_file_no', $variants)
                    ->orWhereIn('mls_file_no', $variants)
                    ->orWhereIn('st_fillno', $variants)
                    // Temporary file numbers (e.g. "RES-2007-63(T)") are indexed on
                    // their own column, so an indexed temp file must match here too —
                    // otherwise it falls through to PENDING_FILE ("Not Indexed"),
                    // mirroring the Create File Tracker lookup.
                    ->orWhereIn('temp_file_no', $variants);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A tracker is "terminal" (no longer holding the file out) once the file has
     * been logged back into the registry (COMPLETED / "Log-in") or the movement
     * was cancelled. Every other status — ACTIVE plus the approval-workflow states
     * (submitted, recommended, approved, rejected, in_processing, cross_*) — means
     * the file is still physically out of the registry and therefore IN_TRANSIT.
     * Mirrors the file-tracker API's "NOT IN ('COMPLETED','CANCELLED')" logged-out
     * test, extended with the manual log-back statuses ("Log-in", "Canceled").
     */
    public function isTerminalTrackerStatus(?string $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), [
            FileTracker::STATUS_COMPLETED,   // COMPLETED
            FileTracker::STATUS_CANCELLED,   // CANCELLED
            'CANCELED',                      // manual-update spelling variant
            'LOG-IN',                        // manually logged back into the Registry (Origin)
        ], true);
    }

    protected function findTracker(array $variants): ?FileTracker
    {
        if (empty($variants)) {
            return null;
        }

        return FileTracker::whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Human-readable "where is it now" for a logged-out tracker.
     *
     * A KANGIS approval-workflow file parks in the generic "Department Queue"
     * office (current_office_name) after DG approval, but its real destination
     * (e.g. "Honorable Commissioners Office") lives on the tracker's
     * workflow_config. Mirror the Create File Tracker page, which resolves the
     * queue label back to that office, so Quick Search / mobile / PHS show the
     * same destination instead of the internal "Department Queue" placeholder.
     */
    protected function resolveTransitLocation(FileTracker $tracker): ?string
    {
        $location = $tracker->current_office_name
            ?: $tracker->receiving_office_name
            ?: $tracker->destination;

        if (trim((string) $location) !== self::KANGIS_QUEUE_OFFICE) {
            return $location;
        }

        $config = (array) ($tracker->workflow_config ?? []);
        foreach (['original_destination_office_name', 'destination_office_name'] as $key) {
            $dest = trim((string) ($config[$key] ?? ''));
            if ($dest !== '' && $dest !== self::KANGIS_QUEUE_OFFICE) {
                return $dest;
            }
        }

        return $location;
    }

    /**
     * When the current holder took possession of the file. Prefers the active
     * movement's acceptance/log-in time (when they physically collected it), then
     * the log-out time, and finally the request/creation time as a floor. This is
     * the anchor for "how long has the file been with this holder".
     */
    protected function holderSince(FileTracker $tracker): ?\Carbon\Carbon
    {
        $current = method_exists($tracker, 'getCurrentMovement') ? $tracker->getCurrentMovement() : null;
        if (is_array($current)) {
            $candidates = [
                $current['accepted_at'] ?? null,
                !empty($current['log_in_date'])  ? trim($current['log_in_date']  . ' ' . ($current['log_in_time']  ?? '')) : null,
                !empty($current['log_out_date']) ? trim($current['log_out_date'] . ' ' . ($current['log_out_time'] ?? '')) : null,
            ];
            foreach ($candidates as $raw) {
                if (!empty($raw)) {
                    try {
                        return \Carbon\Carbon::parse($raw);
                    } catch (\Throwable $e) {
                        // fall through to the next candidate
                    }
                }
            }
        }

        $fallback = $tracker->date_requested ?? $tracker->date_created ?? $tracker->created_at;
        try {
            return $fallback ? \Carbon\Carbon::parse($fallback) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whole calendar days the file has been with the current holder (0 = today).
     */
    protected function daysWithHolder(?\Carbon\Carbon $heldSince): ?int
    {
        if (!$heldSince) {
            return null;
        }

        return (int) $heldSince->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Human label for the holder duration: "Today", "1 day", "5 days".
     */
    protected function durationLabel(?\Carbon\Carbon $heldSince): ?string
    {
        $days = $this->daysWithHolder($heldSince);
        if ($days === null) {
            return null;
        }

        return $days === 0 ? 'Today' : $days . ' day' . ($days === 1 ? '' : 's');
    }

    /**
     * Per-registry "printed label" tables that carry a file's home shelf. Each
     * has the same file_number + shelf_location shape. The registry-specific
     * tables are checked before the generic Lands/MLS table; the first non-empty
     * shelf wins. A file number lives in exactly one of them, so ordering only
     * matters for the rare cross-registry duplicate.
     */
    protected const LABEL_TABLES = [
        'kangis_print_label_batch_items',
        'sltr_print_label_batch_items',
        'st_print_label_batch_items',
        'cadastral_print_label_batch_items',
        'dciv_print_label_batch_items',
        'print_label_batch_items',
    ];

    /** Cache of which LABEL_TABLES actually exist on this connection. */
    protected ?array $existingLabelTables = null;

    /**
     * Physical rack/shelf for the file. Scans every registry's print-label table
     * (KANGIS / SLTR / ST / Cadastral / DCIV / Lands), then falls back to
     * file_indexings.shelf_location.
     */
    protected function getRackShelf(string $fileNumber, ?FileIndexing $indexing): ?string
    {
        $key = strtoupper(trim($fileNumber));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $this->rackShelfCache)) {
            return $this->rackShelfCache[$key];
        }

        // Match on the search key plus every alternate number on the indexing row.
        // A file's shelf label may be stored under its registry-specific number
        // (e.g. a KANGIS file's shelf is keyed on the KANGIS number) rather than
        // the number the user searched with, so widen the match to all of them.
        $keys = [$key];
        if ($indexing) {
            foreach ([
                $indexing->file_number ?? null,
                $indexing->new_kangis_file_no ?? null,
                $indexing->kangis_file_no ?? null,
                $indexing->mls_file_no ?? null,
                $indexing->st_fillno ?? null,
            ] as $kn) {
                $kn = strtoupper(trim((string) $kn));
                if ($kn !== '') {
                    $keys[] = $kn;
                }
            }
        }
        $keys = array_values(array_unique($keys));

        $shelf = null;
        foreach ($this->labelTables() as $table) {
            $shelf = DB::connection('sqlsrv')
                ->table($table)
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keys)
                ->orderByDesc('id')
                ->value('shelf_location');
            if ($shelf) {
                break;
            }
        }

        if (!$shelf && $indexing) {
            $shelf = $indexing->shelf_location;
        }

        return $this->rackShelfCache[$key] = ($shelf ?: null);
    }

    /**
     * The subset of LABEL_TABLES that exist on the connection (resolved once).
     */
    protected function labelTables(): array
    {
        if ($this->existingLabelTables === null) {
            $this->existingLabelTables = array_values(array_filter(
                self::LABEL_TABLES,
                fn ($table) => Schema::connection('sqlsrv')->hasTable($table)
            ));
        }

        return $this->existingLabelTables;
    }
}
