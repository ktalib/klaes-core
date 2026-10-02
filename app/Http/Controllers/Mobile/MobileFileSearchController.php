<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\FileSearchRequest;
use App\Models\MissingFile;
use App\Services\FileLocationResolver;
use App\Services\TimelineWeightingService;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCB Monitor (mobile-only file searcher) endpoints for the Quick Search &
 * File Location module. Reuses the shared FileLocationResolver so the mobile
 * search returns the exact same five-outcome payload as the web.
 */
class MobileFileSearchController extends Controller
{
    public function __construct(
        protected UserNotificationService $notificationService,
        protected TimelineWeightingService $timelineService
    ) {}

    /**
     * GET /api/mobile/files/search?q=RES-1985-1
     * Quick search — same engine as web, minimal fields for mobile.
     */
    public function search(Request $request, FileLocationResolver $resolver): JsonResponse
    {
        $q = trim((string) $request->get('q', $request->get('query', '')));
        if ($q === '') {
            return response()->json(['success' => false, 'message' => 'Provide a file number.'], 422);
        }

        $result = $resolver->resolve($q);
        $resolver->persist($result);

        $dcivInfo = $resolver->dcivInfoFor($result['file_number'], $result['indexing'] ?? null, $result['registry'] ?? null);

        $fileTitle = $result['tracker']->file_title
            ?? $result['indexing']->file_title
            ?? null;

        // Resolve the receiving officer's person name. Prefer the linked user account
        // (authoritative) over the free-text column, which sometimes holds an office
        // label rather than a person's name.
        $tracker      = $result['tracker'] ?? null;
        $officerName  = $tracker->receiving_officer_name ?? null;
        $officerId    = $tracker->receiving_officer_id ?? null;
        if ($officerId) {
            $user = \App\Models\User::find($officerId);
            if ($user) {
                $resolvedName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                    ?: ($user->username ?? null);
                if ($resolvedName) {
                    $officerName = $resolvedName;
                }
            }
        }

        // In-transit timeline: when the file was requested, and when the current
        // holder physically collected it (the active movement's acceptance / log-in).
        $dateRequested = null;
        $dateCollected = null;
        if ($result['status'] === FileLocationResolver::STATUS_IN_TRANSIT && $tracker) {
            $requested = $tracker->date_requested ?? $tracker->date_created ?? $tracker->created_at;
            $dateRequested = $requested ? \Carbon\Carbon::parse($requested)->format('Y-m-d g:i A') : null;

            $current = $tracker->getCurrentMovement();
            if ($current) {
                if (!empty($current['accepted_at'])) {
                    $dateCollected = \Carbon\Carbon::parse($current['accepted_at'])->format('Y-m-d g:i A');
                } elseif (!empty($current['log_in_date'])) {
                    $collected = trim($current['log_in_date'] . ' ' . ($current['log_in_time'] ?? ''));
                    $dateCollected = \Carbon\Carbon::parse($collected)->format('Y-m-d g:i A');
                }
            }
        }

        // The receiving officer's department (from the offices table), used to label
        // who currently holds the file. Match by office code first, then office name.
        $receivingDepartment = null;
        if ($tracker && ($tracker->receiving_office_code || $tracker->receiving_office_name)) {
            $receivingDepartment = \App\Models\Office::query()
                ->when($tracker->receiving_office_code, fn ($q) => $q->where('office_code', $tracker->receiving_office_code))
                ->when(! $tracker->receiving_office_code, fn ($q) => $q->where('office_name', $tracker->receiving_office_name))
                ->value('department');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'file_number'      => $result['file_number'],
                'file_title'       => $fileTitle,
                'status'           => $result['status'],
                'registry'         => $result['registry'],
                // Origin registry the file physically belongs to (KANGIS / SLTR / ST /
                // Cadastral), so the Send-to-SCB form can pre-select the Registry (Origin)
                // and theme its colour. Null when the file type can't be classified.
                'origin_registry'  => $this->detectOriginRegistry($result['indexing'] ?? null),
                'current_location' => $result['current_location'],
                'rack_shelf'       => $result['rack_shelf'],
                'next_action'      => $result['next_action'],
                'slip_variant'     => $result['slip_variant'],
                'can_send_fr'      => (bool) ($result['can_send_fr'] ?? false),
                'can_log'          => (bool) ($result['can_log'] ?? false),
                'is_blind'         => (bool) ($result['is_blind'] ?? false),
                // Flag set when this file number exists in the missing_files table.
                // When true, the "Send Blind Request to SCB Monitor" button changes to
                // "Send Blind Request to the Original Registry" and saves without SCB.
                'is_missing_file'  => (bool) ($result['is_missing_file'] ?? false),
                // True when a FileIndexing row exists for this file number. Combined
                // with is_missing_file above, this flags a stale missing_files report —
                // the file has since been indexed (physically returned and filed), so
                // the UI can surface a note instead of treating it as still missing.
                'is_indexed'       => (bool) ($result['indexing'] ?? null),
                // Temporary "(T)" file — resolved standalone (never against its stripped
                // base number); the UI shows an "Is Temporary File" badge.
                'is_temp_file'     => (bool) ($result['is_temp_file'] ?? false),
                // In-transit files can be re-directed straight to the office currently
                // holding them (the last receiving officer) instead of going to the SCB.
                'can_redirect'           => $result['status'] === FileLocationResolver::STATUS_IN_TRANSIT,
                'receiving_officer_name'   => $officerName,
                'receiving_office_name'    => $tracker->receiving_office_name ?? null,
                'receiving_department'     => $receivingDepartment,
                // In-transit timeline (null for every other outcome).
                'date_requested'           => $dateRequested,
                'date_collected'           => $dateCollected,
                // How long the file has been with the current holder.
                'held_since'               => $result['held_since'] ?? null,
                'days_with_holder'         => $result['days_with_holder'] ?? null,
                'duration_with_holder'     => $result['duration_with_holder'] ?? null,
                // DCIV investigation flag: true when this file is either flagged
                // dciv_status=1 (a related file linked to a DCIV master) OR is itself
                // the DCIV master record (own registry is the DCIV Registry, or it has
                // related files linked to it). See FileLocationResolver::dcivInfoFor().
                'dciv_status'              => (int) $dcivInfo['status'],
                'dciv_fileno'              => $dcivInfo['fileno'],
                'dciv_reason'              => $dcivInfo['reason'],
                // The reverse case: the searched number IS a DCIV master file itself —
                // every related file linked to it via master_dciv_links.
                'dciv_related_files'       => $dcivInfo['related_files'],
                // Duplicate-registry flag (CofO collected/ready, duplicate, temp, W/C/R) when
                // the file number is registered in duplicate_fileno — null otherwise.
                'duplicate_flag'           => $result['duplicate_flag'] ?? null,
                // Ownership history — the chronological chain of holders derived from the
                // cross-table property timeline (file_history/CofO/pra/deeds). Rendered as a
                // vertical timeline in the Holders panel. Only surfaced for indexed files: a
                // file with no indexing row (Pending / Not Indexed) brings no transactions.
                'holder_history'           => ($result['indexing'] ?? null)
                    ? $this->timelineService->holderHistory($result['file_number'])
                    : [],
                'original_holder'          => $result['indexing']?->formattedHolder('original_holder'),
                'current_holder'           => $result['indexing']?->formattedHolder('current_holder'),
                'bill_balance'             => \App\Models\BillBalance::summaryForFile($result['file_number']),
                // Indexing bills (Bill Balance + Grant Rent amounts captured during File Indexing).
                'indexing_bills'           => \App\Models\FileIndexingBill::amountsForFile($result['file_number']),
            ],
        ]);
    }

    /**
     * Classify a file's origin registry from its indexing row, so the Send-to-SCB
     * form can pre-select the Registry (Origin). Returns a physical_registries.name
     * (matching the dropdown options) or null when it can't be classified.
     *
     *   - Cadastral : a corresponding (land) file — is_corresponding_file = 1.
     *   - KANGIS / SLTR / ST : tagged on the indexing's general/physical registry.
     *
     * Cadastral wins first: a land file flagged as corresponding is cadastral even
     * if it also carries a KANGIS general-registry tag.
     */
    private function detectOriginRegistry($indexing): ?string
    {
        if (!$indexing) {
            return null;
        }

        $phys = trim((string) ($indexing->physical_registry ?? ''));
        if ($phys !== '') {
            return $phys;
        }

        if ((string) ($indexing->is_corresponding_file ?? '') === '1') {
            return 'Registry 1 - Cadastral';
        }

        $gen = strtoupper(trim((string) ($indexing->general_registry ?? '')));
        $hay = $gen;

        if (str_contains($hay, 'KANGIS')) {
            return 'KANGIS Registry';
        }
        if (str_contains($hay, 'SLTR')) {
            return 'SLTR Registry';
        }
        // ST (STIL) files carry an "ST-" prefix in the file number itself — not a
        // land file that merely references an ST number in st_fillno.
        $fileNo = strtoupper(trim((string) ($indexing->file_number ?? '')));
        if (str_starts_with($fileNo, 'ST-') || $gen === 'ST REGISTRY') {
            return 'ST Registry';
        }

        return null;
    }

    /**
     * GET /api/mobile/file-requests
     * Open File Requests for the authenticated SCB Monitor (assigned to them
     * or unassigned/broadcast).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $userId  = $request->user()->id;
        $isAdmin = $this->isAdmin($request);

        $base = FileSearchRequest::open()
            ->when(! $isAdmin, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->whereNull('assigned_monitor_id')
                        ->orWhere('assigned_monitor_id', $userId);
                });
            });

        // True total for the tab badge (counted before the display limit, so the
        // badge keeps climbing past 100 instead of freezing at the cap).
        $total = (clone $base)->count();

        $requests = $base
            ->with('requester:id,first_name,last_name')
            // OFS (Office Priority Search) requests float to the top, then by seniority
            // weight, then newest first.
            ->orderByDesc('is_ofs')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'request_no', 'file_number', 'file_title', 'current_location', 'status', 'resolved_status', 'source', 'requester_user_id', 'receiving_officer', 'requester_office', 'requester_department', 'is_ofs', 'ofs_rank', 'priority', 'created_at', 'request_purpose_name', 'expected_return_date']);

        $data = $requests->map(function (FileSearchRequest $fr) {
            $req = $fr->requester;
            return array_merge([
                'id'                  => $fr->id,
                'request_no'          => $fr->request_no,
                'file_number'         => $fr->file_number,
                'file_title'          => $fr->file_title,
                'requester'           => $req ? trim($req->first_name . ' ' . $req->last_name) : '—',
                'receiving_officer'   => $fr->receiving_officer,
                'requester_office'    => $fr->requester_office,
                'requester_department'=> $fr->requester_department,
                'current_location'    => $fr->current_location,
                'status'              => $fr->status,
                'is_ofs'              => (bool) $fr->is_ofs,
                'ofs_rank'            => $fr->ofs_rank,
                'created_at'          => optional($fr->created_at)->format('Y-m-d g:i A'),
                'request_purpose_name'  => $fr->request_purpose_name,
                'expected_return_date'  => optional($fr->expected_return_date)->format('Y-m-d'),
                'timeline_status'       => $fr->timeline_status,
                'days_until_deadline'   => $fr->days_until_deadline,
                'delay_reason'          => null,
            ], $this->requestTypeMeta($fr));
        });

        return response()->json(['success' => true, 'data' => $data, 'total' => $total]);
    }

    /**
     * GET /api/mobile/file-requests/log
     * FSR History for the authenticated SCB Monitor — every File Search Request
     * routed to them (assigned or broadcast) that has been handled (FOUND/
     * NOT_FOUND/CLOSED), with the requester, file location, status and outcome.
     * Still-open requests (PENDING/SEARCHING) live in the Open inbox, not here.
     * Optional ?status= filter.
     */
    public function log(Request $request): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $userId  = $request->user()->id;
        $isAdmin = $this->isAdmin($request);

        $query = FileSearchRequest::with([
                'requester:id,first_name,last_name',
                'responder:id,first_name,last_name',
            ])
            ->when(! $isAdmin, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->whereNull('assigned_monitor_id')
                        ->orWhere('assigned_monitor_id', $userId);
                });
            });

        // FSR History is the record of handled requests — exclude the still-open
        // ones (PENDING/SEARCHING) which live in the Open inbox, so a request the
        // SCB hasn't acted on yet doesn't appear in both tabs at once.
        $query->whereNotIn('status', [
            FileSearchRequest::STATUS_PENDING,
            FileSearchRequest::STATUS_SEARCHING,
        ]);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // True total for the tab badge (counted before the display limit, so the
        // badge reflects the real history size instead of freezing at the cap).
        // Computed before the free-text search so the badge stays the full history
        // size rather than shrinking to the number of matches.
        $total = (clone $query)->count();

        // Free-text search (file no, title, request no, requester, office). Applied
        // server-side so the panel can find matches beyond the 200 most-recent rows
        // returned below — client-side filtering alone would miss older requests.
        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('file_number', 'like', $like)
                  ->orWhere('file_title', 'like', $like)
                  ->orWhere('request_no', 'like', $like)
                  ->orWhere('receiving_officer', 'like', $like)
                  ->orWhere('requester_office', 'like', $like)
                  ->orWhere('requester_department', 'like', $like)
                  ->orWhere('current_location', 'like', $like);
            });
        }

        $rows = $query->orderByDesc('is_ofs')->orderByDesc('priority')->orderByDesc('id')->limit(200)->get();

        $data = $rows->map(function (FileSearchRequest $fr) {
            $req  = $fr->requester;
            $resp = $fr->responder;

            // "Requested by" is the selected Requester Officer — not the user
            // account that raised the request. Fall back to office/department,
            // then the creating user (mirrors the web frDuplicatePayload logic).
            $createdBy = $req ? trim($req->first_name . ' ' . $req->last_name) : '';
            $requester = $fr->receiving_officer
                ?: ($fr->requester_office ?: ($fr->requester_department ?: ($createdBy ?: '—')));

            return array_merge($this->requestTypeMeta($fr), [
                'id'               => $fr->id,
                'request_no'       => $fr->request_no,
                'file_number'      => $fr->file_number,
                'file_title'       => $fr->file_title,
                'requester'        => $requester,
                'receiving_officer'    => $fr->receiving_officer,
                'requester_office'     => $fr->requester_office,
                'requester_department' => $fr->requester_department,
                'created_by'           => $createdBy ?: null,
                'current_location' => $fr->current_location,
                'status'           => $fr->status,
                'not_found_type'   => $fr->not_found_type,
                'is_ofs'           => (bool) $fr->is_ofs,
                'ofs_rank'         => $fr->ofs_rank,
                'feedback_note'    => $fr->feedback_note,
                'responder'        => $resp ? trim($resp->first_name . ' ' . $resp->last_name) : null,
                'created_at'       => optional($fr->created_at)->format('Y-m-d g:i A'),
                'responded_at'     => optional($fr->responded_at)->format('Y-m-d g:i A'),
                // A responded request can be reverted (undone) only while the Front Desk
                // has not yet acted on it — e.g. an accidental "Found" tap.
                'can_revert'       => in_array($fr->status, [FileSearchRequest::STATUS_FOUND, FileSearchRequest::STATUS_NOT_FOUND], true)
                    && $fr->front_desk_acted_at === null,
            ]);
        });

        return response()->json(['success' => true, 'data' => $data, 'total' => $total]);
    }

    /**
     * GET /api/mobile/my-file-requests
     * The authenticated user's OWN File Search Requests — what they sent, the SCB
     * status, and (once the file is retrieved + logged out) whether it has been
     * logged out to them / their office. Used by OFS requesters to track outcomes.
     */
    public function mine(Request $request, FileLocationResolver $resolver): JsonResponse
    {
        $userId = $request->user()->id;

        $requests = FileSearchRequest::where('requester_user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'request_no', 'file_number', 'file_title', 'current_location', 'status', 'resolved_status', 'source', 'is_ofs', 'ofs_rank', 'feedback_note', 'responded_at', 'created_at']);

        // Latest file_tracker per requested file, to surface a logged-out outcome.
        $fileNumbers = $requests->pluck('file_number')->filter()->unique()->values();
        $trackers = collect();
        if ($fileNumbers->isNotEmpty()) {
            $trackers = \App\Models\FileTracker::whereIn('file_number', $fileNumbers->all())
                ->orderByDesc('id')
                ->get(['id', 'file_number', 'status', 'current_office_name', 'receiving_office_name', 'receiving_officer_id', 'receiving_officer_name'])
                ->groupBy(fn ($t) => strtoupper(trim((string) $t->file_number)))
                ->map(fn ($group) => $group->first());
        }

        $data = $requests->map(function (FileSearchRequest $fr) use ($trackers, $userId, $resolver) {
            $t          = $trackers[strtoupper(trim((string) $fr->file_number))] ?? null;
            // A file is out to an office for any non-terminal tracker status (ACTIVE plus
            // the approval-workflow states), not only the literal "ACTIVE" — mirrors the
            // FileLocationResolver IN_TRANSIT rule so the badge matches the search result.
            $loggedOut  = $t && ! $resolver->isTerminalTrackerStatus($t->status);
            $office     = $t ? ($t->current_office_name ?: $t->receiving_office_name) : null;
            $toMe       = $t && (int) $t->receiving_officer_id === (int) $userId;

            return array_merge($this->requestTypeMeta($fr), [
                'id'                => $fr->id,
                'request_no'        => $fr->request_no,
                'file_number'       => $fr->file_number,
                'file_title'        => $fr->file_title,
                'current_location'  => $fr->current_location,
                'status'            => $fr->status,
                'is_ofs'            => (bool) $fr->is_ofs,
                'ofs_rank'          => $fr->ofs_rank,
                'feedback_note'     => $fr->feedback_note,
                'created_at'        => optional($fr->created_at)->format('Y-m-d g:i A'),
                'responded_at'      => optional($fr->responded_at)->format('Y-m-d g:i A'),
                // Logged-out outcome: the file was retrieved and is now out to an office.
                'logged_out'        => (bool) $loggedOut,
                'logged_out_office' => $loggedOut ? $office : null,
                'logged_out_to_me'  => (bool) ($loggedOut && $toMe),
                'handler'           => $t ? $t->receiving_officer_name : null,
            ]);
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * DELETE /api/mobile/file-requests/{id}
     * Remove a File Search Request. Super Admins only.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Not authorized — Super Admins only.'], 403);
        }

        $fr = FileSearchRequest::find($id);
        if (!$fr) {
            return response()->json(['success' => false, 'message' => 'File Request not found.'], 404);
        }

        $fr->delete();

        return response()->json(['success' => true, 'message' => 'File Request deleted.']);
    }

    /**
     * Derive the display type for an FSR: DFR (raised from a Digital File Request),
     * Blind Request (non-indexed file), or Open Request.
     *
     * @return array{request_type:string, is_blind:bool, is_dfr:bool}
     */
    protected function requestTypeMeta(FileSearchRequest $fr): array
    {
        if ($fr->source === FileSearchRequest::SOURCE_DFR) {
            return ['request_type' => 'DFR', 'is_blind' => false, 'is_dfr' => true];
        }

        $isBlind = in_array($fr->resolved_status, [
            FileLocationResolver::STATUS_PENDING_FILE,
            FileLocationResolver::STATUS_BLIND_REQUEST_SENT,
        ], true);

        return [
            'request_type' => $isBlind ? 'Blind Request' : 'Open Request',
            'is_blind'     => $isBlind,
            'is_dfr'       => false,
        ];
    }

    /** A user is an SCB Monitor when users.fr_permissions = 'SCB' (Super Admins included). */
    protected function isScbMonitor(Request $request): bool
    {
        return $this->isAdmin($request) || ($request->user()->fr_permissions ?? '') === 'SCB';
    }

    /** Super Admins see every File Search Request, not just those assigned/broadcast to them. */
    protected function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    /**
     * POST /api/mobile/file-requests/{id}/respond
     * Body: { result: found|not_found, note?: string }
     */
    public function respond(Request $request, int $id, FileLocationResolver $resolver): JsonResponse
    {
        if (!$this->isScbMonitor($request)) {
            return response()->json(['success' => false, 'message' => 'Not authorized — SCB Monitors only.'], 403);
        }

        $validated = $request->validate([
            'result'         => 'required|in:found,not_found',
            'note'           => 'nullable|string|max:1000',
            // When marking Not Found, the SCB Monitor must say which kind it is.
            'not_found_type' => 'required_if:result,not_found|nullable|in:missing,pending',
        ]);

        $fr = FileSearchRequest::find($id);
        if (!$fr) {
            return response()->json(['success' => false, 'message' => 'File Request not found.'], 404);
        }

        $found = $validated['result'] === 'found';
        // MISSING / PENDING only applies to a Not Found response; clear it on Found.
        $notFoundType = (!$found && !empty($validated['not_found_type']))
            ? strtoupper($validated['not_found_type'])
            : null;
        $fr->forceFill([
            'status'              => $found ? FileSearchRequest::STATUS_FOUND : FileSearchRequest::STATUS_NOT_FOUND,
            'not_found_type'      => $notFoundType,
            'feedback_note'       => $validated['note'] ?? null,
            'assigned_monitor_id' => $fr->assigned_monitor_id ?: $request->user()->id,
            'responded_by'        => $request->user()->id,
            'responded_at'        => now(),
        ])->save();

        // Combine the originating location with the SCB result into the outcome
        // status (IN_ARCHIVE_FOUND / IN_POOL_OFFICE_NOT_FOUND, …) and store it on
        // the matching file_indexings row so the Front Desk Quick Search reflects it.
        $resolved      = $resolver->resolve($fr->file_number);
        $outcomeStatus = $resolver->combineScbOutcome($fr->resolved_status, $found);

        if ($indexing = $resolved['indexing']) {
            $indexing->forceFill([
                'tracking_status'        => $outcomeStatus,
                'location_status_manual' => now(),   // make the resolver honour this stored outcome
            ])->save();
        }

        // Not Found — Missing: record the file on the missing_files list so future
        // Quick Search requests for it divert to the Original Registry instead of
        // going back to the SCB. Skipped when an unresolved report already exists.
        if (!$found && $notFoundType === FileSearchRequest::NOT_FOUND_MISSING) {
            $alreadyListed = MissingFile::where('file_number', $fr->file_number)
                ->where('status', '!=', MissingFile::STATUS_FOUND)
                ->exists();

            if (! $alreadyListed) {
                $monitor = $request->user();

                // Backfill the file's home location (registry + rack/shelf) — the
                // same fields the manual Capture Missing File form collects — so the
                // Missing Files list shows where the file should have been shelved.
                // Best-effort: a file with no resolvable location leaves them null.
                $indexing  = $resolved['indexing'] ?? null;
                // An uncaptured file resolves to PENDING_FILE with no registry, but the
                // prefix+year range table may still know its home registry — use it.
                $registry  = $resolved['registry'] ?? null;
                if ($registry === null) {
                    $registry = $resolver->matchRange($fr->file_number)['registry'] ?? null;
                }
                $fullLabel = strtoupper(trim((string) ($resolved['rack_shelf'] ?? ''))) ?: null;
                $shelfLocation = $fullLabel !== null
                    ? (str_replace(['-', ' ', '/', '\\'], '', $fullLabel) ?: null)
                    : null;
                $rackPrimary = $shelfNumber = null;
                if ($shelfLocation && preg_match('/^([A-Z]+)(\d+)$/', $shelfLocation, $m)) {
                    $rackPrimary = $m[1];
                    $shelfNumber = $m[2];
                }

                MissingFile::create([
                    'file_number'      => $fr->file_number,
                    'file_title'       => $fr->file_title,
                    'tracking_id'      => $indexing->tracking_id ?? null,
                    'archive_registry' => $registry !== null ? mb_substr($registry, 0, 50) : null,
                    'rack_primary'     => $rackPrimary,
                    'shelf_number'     => $shelfNumber,
                    'full_label'       => $fullLabel !== null ? mb_substr($fullLabel, 0, 30) : null,
                    'shelf_location'   => $shelfLocation !== null ? mb_substr($shelfLocation, 0, 30) : null,
                    'property_location' => $indexing->property_description ?? null,
                    'reported_by'      => $monitor->id ?? null,
                    'reported_by_name' => $monitor ? trim(($monitor->first_name ?? '') . ' ' . ($monitor->last_name ?? '')) : null,
                    'remarks'          => trim('Reported Not Found (Missing) by the SCB Monitor on ' . $fr->request_no . '.'
                        . (!empty($validated['note']) ? ' Note: ' . $validated['note'] : '')),
                    'status'           => MissingFile::STATUS_MISSING,
                    'source'           => $fr->source ?: FileSearchRequest::SOURCE_QUICK_SEARCH,
                    'request_no'       => $fr->request_no,
                ]);
            }
        }

        // Not-found on a non-indexed (or uncaptured) file → print Missing/Refer slip.
        $slipVariant = $found ? null : 'refer_registry';

        // Notify the original requester of the outcome.
        if ($fr->requester_user_id) {
            $this->notificationService->create(
                $fr->requester_user_id,
                'file_search_request',
                "File Request {$fr->request_no}: " . ($found ? 'Found' : 'Not Found' . ($notFoundType ? " ({$notFoundType})" : '')),
                "File {$fr->file_number} was marked " . ($found ? 'FOUND' : 'NOT FOUND' . ($notFoundType ? " — {$notFoundType}" : '')) . ' by the SCB Monitor.'
                    . (!empty($validated['note']) ? " Note: {$validated['note']}" : ''),
                ['request_id' => $fr->id, 'request_no' => $fr->request_no, 'file_number' => $fr->file_number],
                ['module' => 'file_search_request']
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status'         => $fr->status,
                'not_found_type' => $notFoundType,
                'outcome_status' => $outcomeStatus,
                'next_action'    => $found ? 'Front Desk can now Log the file' : 'Refer to Original Registry',
                'slip_variant'   => $slipVariant,
            ],
            'message' => 'Feedback recorded.',
        ]);
    }

    /**
     * Revert (undo) an SCB Found/Not-Found response — e.g. when the monitor tapped it
     * by accident — putting the request back into the open queue. Only allowed while
     * the Front Desk has not yet acted on it.
     * POST /api/mobile/file-requests/{id}/revert
     */
    public function revert(Request $request, int $id, FileLocationResolver $resolver): JsonResponse
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Not authorized — Super Admins only.'], 403);
        }

        $fr = FileSearchRequest::find($id);
        if (!$fr) {
            return response()->json(['success' => false, 'message' => 'File Request not found.'], 404);
        }

        if (!in_array($fr->status, [FileSearchRequest::STATUS_FOUND, FileSearchRequest::STATUS_NOT_FOUND], true)) {
            return response()->json(['success' => false, 'message' => 'Only a Found / Not-Found response can be reverted.'], 422);
        }

        if ($fr->front_desk_acted_at !== null) {
            return response()->json(['success' => false, 'message' => 'The Front Desk has already acted on this request — it can no longer be reverted.'], 422);
        }

        // Remove the missing-file report auto-created by a Not Found (Missing)
        // response, so future requests stop diverting to the Original Registry.
        MissingFile::where('request_no', $fr->request_no)
            ->where('status', '!=', MissingFile::STATUS_FOUND)
            ->delete();

        // Put the request back in the open queue (clear the response fields).
        $fr->forceFill([
            'status'         => FileSearchRequest::STATUS_PENDING,
            'not_found_type' => null,
            'feedback_note'  => null,
            'responded_by'   => null,
            'responded_at'   => null,
        ])->save();

        // Undo the SCB outcome stamped on the matching file_indexings row so the Front
        // Desk Quick Search stops showing the (accidental) Found / Not-Found outcome.
        $resolved = $resolver->resolve($fr->file_number);
        if ($indexing = $resolved['indexing']) {
            $indexing->forceFill([
                'tracking_status'        => null,
                'location_status_manual' => null,
            ])->save();
        }

        // Let the original requester know the outcome was withdrawn.
        if ($fr->requester_user_id) {
            $this->notificationService->create(
                $fr->requester_user_id,
                'file_search_request',
                "File Request {$fr->request_no}: response reverted",
                "The SCB Monitor reverted the earlier response for file {$fr->file_number}. It is awaiting a fresh physical check.",
                ['request_id' => $fr->id, 'request_no' => $fr->request_no, 'file_number' => $fr->file_number],
                ['module' => 'file_search_request']
            );
        }

        return response()->json([
            'success' => true,
            'data'    => ['status' => $fr->status],
            'message' => 'Response reverted — the request is back in the open queue.',
        ]);
    }
}
