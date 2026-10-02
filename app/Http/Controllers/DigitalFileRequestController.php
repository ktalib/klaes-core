<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DigitalFileAccess;
use App\Models\DigitalFileRequest;
use App\Models\FileIndexing;
use App\Models\FileSearchRequest;
use App\Models\FileTracker;
use App\Models\Office;
use App\Models\User;
use App\Services\DigitalFileAccessService;
use App\Services\FileLocationResolver;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class DigitalFileRequestController extends Controller
{
    public function __construct(
        protected UserNotificationService    $notifications,
        protected DigitalFileAccessService   $digitalAccess
    ) {}

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();

        // KANGIS-scoped view: ?kangis (or ?url=kangis) shows KANGIS files only + KANGIS branding.
        $isKangis = $request->has('kangis') || strtolower((string) $request->input('url')) === 'kangis';

        // Stats for header cards
        $base  = DigitalFileRequest::query();
        if ($isKangis) {
            $base->kangisFiles();
        }
        $stats = [
            'total'    => (clone $base)->count(),
            'pending'  => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
            'rejected' => (clone $base)->rejected()->count(),
        ];

        // Officers for the "Receiving Officer" dropdown (same staff_type_category pattern)
        $officers = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->where('staff_type_category', 'MLPP')
            ->select('id', 'first_name', 'last_name', 'department_id')
            ->orderBy('first_name')
            ->get();

        // All active offices (used by JS after department filter)
        $offices = Office::active()->orderBy('office_name')->get(['id', 'office_name', 'office_code', 'department']);

        return view('digital_request.index', compact('stats', 'officers', 'offices', 'user', 'isKangis'));
    }

    // ── AJAX: file-number typeahead (Select2 source) ───────────────────────

    public function fileNumberSearch(Request $request)
    {
        $q = trim((string) $request->get('q', $request->get('term', '')));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%' . $q . '%';

        $rows = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($w) use ($like) {
                $w->where('file_number', 'like', $like)
                  ->orWhere('new_kangis_file_no', 'like', $like)
                  ->orWhere('kangis_file_no', 'like', $like)
                  ->orWhere('mls_file_no', 'like', $like)
                  ->orWhere('st_fillno', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['file_number', 'new_kangis_file_no', 'kangis_file_no', 'mls_file_no', 'st_fillno', 'file_title']);

        $results = [];
        $seen    = [];
        foreach ($rows as $r) {
            $fileNo = trim((string) ($r->file_number ?: $r->new_kangis_file_no ?: $r->kangis_file_no ?: $r->mls_file_no ?: $r->st_fillno));
            if ($fileNo === '' || isset($seen[$fileNo])) {
                continue;
            }
            $seen[$fileNo] = true;
            $results[] = [
                'id'    => $fileNo,
                'text'  => $fileNo,
                'title' => $r->file_title,
            ];
        }

        return response()->json(['results' => $results]);
    }

    // ── AJAX: check file availability before request ───────────────────────

    public function checkFileAvailability(Request $request)
    {
        $fileNo = trim((string) $request->input('file_no', ''));

        if ($fileNo === '') {
            return response()->json(['available' => false, 'message' => 'File number is required.'], 422);
        }

        // Any open File Search Request already raised for this file (so the form can
        // warn that it has already been sent to SCB before a duplicate is submitted).
        $openRequest = FileSearchRequest::openForFile($fileNo)
            ->with('requester:id,first_name,last_name')
            ->orderBy('id')
            ->first();
        $openRequestPayload = $openRequest ? [
            'request_no'     => $openRequest->request_no,
            'requester_name' => $openRequest->requester
                ? trim($openRequest->requester->first_name . ' ' . $openRequest->requester->last_name)
                : '—',
            'status'         => $openRequest->status,
            'requested_at'   => optional($openRequest->created_at)->format('Y-m-d H:i'),
        ] : null;

        // Find the most recent tracker entry for this file number
        $tracker = FileTracker::where('file_number', $fileNo)
            ->orderByDesc('created_at')
            ->first();

        if (! $tracker) {
            // No tracking record yet — fall back to the range-based location engine
            // (config/file_ranges.php) so the file still resolves to its Pool Office /
            // Archive instead of a blank "no record" message.
            $resolved = app(FileLocationResolver::class)->resolve($fileNo);

            return response()->json([
                'found'           => true,
                'available'       => true,   // not in transit → Front-Desk can retrieve it
                'file_no'         => $fileNo,
                'file_title'      => optional($resolved['indexing'])->file_title,
                'current_office'  => $resolved['current_location'],
                'location_status' => $resolved['status'],
                'location_label'  => $this->locationLabel($resolved['status']),
                'status'          => $this->locationLabel($resolved['status']),
                'open_request'    => $openRequestPayload,
            ]);
        }

        $log         = $tracker->movement_log ?? [];
        $lastEntry   = !empty($log) ? $log[array_key_last($log)] : null;
        $trackerStatus = strtolower(trim((string) ($tracker->status ?? '')));

        // Derive the human-readable movement status from the last log entry
        $movementStatus = null;
        if ($lastEntry) {
            if (!empty($lastEntry['log_out_date'])) {
                $movementStatus = 'Log-out'; // dispatched — in transit to another office
            } else {
                $movementStatus = 'In-Transit'; // received but not yet returned to registry
            }
        }

        // The file is only available at the Registry when the tracker is fully completed/cancelled.
        // Any active tracking record (In-Transit, Log-out, Pending acceptance, etc.) means
        // the file is NOT physically at the Registry.
        $isCompleted  = in_array($trackerStatus, ['completed', 'cancelled', 'canceled']);
        $available    = $isCompleted || ($lastEntry === null && $trackerStatus === '');

        $currentOffice   = $tracker->current_office_name  ?? 'Unknown Office';
        $currentOfficer  = $tracker->receiving_officer_name ?? 'Unknown Officer';
        $displayStatus   = $movementStatus ?? ucfirst($trackerStatus) ?: 'In-Transit';

        $response = [
            'found'                   => true,
            'available'               => $available,
            'file_no'                 => $tracker->file_number,
            'file_title'              => $tracker->file_title,
            'current_office'          => $currentOffice,
            'current_office_code'     => $tracker->receiving_office_code ?? null,
            'receiving_officer'       => $currentOfficer,
            'receiving_officer_id'    => $tracker->receiving_officer_id ?? null,
            'status'                  => $displayStatus,
            'tracker_status'          => $trackerStatus,
            'open_request'            => $openRequestPayload,
        ];

        if (! $available) {
            $response['message'] = sprintf(
                'This file is currently not available in the Registry. The file is presently in %s under Receiving Officer %s.',
                $currentOffice,
                $currentOfficer
            );
        }

        return response()->json($response);
    }

    /**
     * Human-readable location label for a FileLocationResolver status.
     */
    private function locationLabel(string $status): string
    {
        return match ($status) {
            FileLocationResolver::STATUS_IN_POOL      => 'In Pool Office',
            FileLocationResolver::STATUS_IN_ARCHIVE   => 'In Archive',
            FileLocationResolver::STATUS_IN_TRANSIT   => 'In Transit',
            FileLocationResolver::STATUS_REFER        => 'Refer to Original Registry',
            FileLocationResolver::STATUS_PENDING_FILE => 'Not Indexed',
            default                                   => 'Available at Registry',
        };
    }

    // ── AJAX: inline digital-file preview for a searched file ──────────────

    /**
     * List the digital pages for a file number, sourced from the same data behind the
     * File Digital Archive (/filearchive) and Scan Uploads: the page-typings and the
     * raw scannings. URLs are built straight from the stored paths (no on-disk folder
     * validation) so the preview works even when the local copy is empty.
     */
    public function digitalFiles(Request $request)
    {
        $fileNo = trim((string) $request->input('file_no', ''));
        if ($fileNo === '') {
            return response()->json(['available' => false, 'files' => []]);
        }

        // The DFR dropdown may pass any of the file-number variants — match the same
        // columns fileNumberSearch() searches.
        $indexing = FileIndexing::query()
            ->where('file_number', $fileNo)
            ->orWhere('new_kangis_file_no', $fileNo)
            ->orWhere('kangis_file_no', $fileNo)
            ->orWhere('mls_file_no', $fileNo)
            ->orWhere('st_fillno', $fileNo)
            ->with([
                'pagetypings' => function ($q) {
                    $q->select(['id', 'file_indexing_id', 'page_number', 'page_code', 'definition', 'file_path', 'scanning_id'])
                      ->with('scanning:id,document_path,display_order,original_filename');
                },
                'scannings' => function ($q) {
                    $q->select(['id', 'file_indexing_id', 'document_path', 'display_order', 'original_filename']);
                },
            ])
            ->first();

        if (! $indexing) {
            return response()->json(['available' => false, 'files' => []]);
        }

        // Deterministic page order (done in PHP to avoid duplicate ORDER BY columns
        // from the relations' default ordering).
        $pagetypings = $indexing->pagetypings->sortBy('page_number')->values();
        $scannings   = $indexing->scannings
            ->sortBy(fn ($s) => [$s->display_order ?? 999999, $s->id])
            ->values();

        // Build a public URL from a stored relative path (mirrors /filearchive's
        // prefix normalisation) WITHOUT checking that the file exists on disk.
        $toUrl = function (?string $rawPath): ?string {
            if (! $rawPath) {
                return null;
            }
            $p = ltrim(str_replace('\\', '/', $rawPath), '/');
            foreach (['storage/app/public/', 'app/public/', 'public/', 'storage/'] as $prefix) {
                if (stripos($p, $prefix) === 0) {
                    $p = substr($p, strlen($prefix));
                    break;
                }
            }
            $p = ltrim($p, '/');
            if ($p === '') {
                return null;
            }
            // Collapse accidental double slashes (e.g. host//storage) without
            // touching the scheme's "://".
            return preg_replace('#(?<!:)//+#', '/', Storage::disk('public')->url($p));
        };

        $toFile = function (?string $rawPath, ?string $label) use ($toUrl) {
            $url = $toUrl($rawPath);
            if (! $url) {
                return null;
            }
            $name = $label ?: basename(str_replace('\\', '/', $rawPath));
            return [
                'name' => $name,
                'ext'  => strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)),
                'url'  => $url,
            ];
        };

        // Prefer the page-typed pages (Digital Archive); fall back to raw Scan Uploads.
        $files = collect();
        foreach ($pagetypings as $pt) {
            $raw  = optional($pt->scanning)->document_path ?: $pt->file_path;
            $name = optional($pt->scanning)->original_filename ?: ($pt->page_code ?: null);
            if ($f = $toFile($raw, $name)) {
                $files->push($f);
            }
        }
        if ($files->isEmpty()) {
            foreach ($scannings as $sc) {
                if ($f = $toFile($sc->document_path, $sc->original_filename)) {
                    $files->push($f);
                }
            }
        }

        return response()->json([
            'available' => $files->isNotEmpty(),
            'count'     => $files->count(),
            'files'     => $files->values(),
        ]);
    }

    // ── AJAX: registry folder digital preview (raw on-disk source) ─────────

    /**
     * Resolve a file number (scoped to the selected Registry) to the scanned
     * images/documents stored in that registry's on-disk folder, imported by
     * `php artisan registry:sync` into the registry_* tables.
     *
     * This is the folder-based source for the SLTR / Cadastral / KANGIS /
     * Physical Planning registries. The Land registries keep using the
     * FileIndexing-based source above (digitalFiles).
     */
    public function registryFiles(Request $request)
    {
        $fileNo   = trim((string) $request->input('file_no', ''));
        $registry = trim((string) ($request->input('registry') ?? $request->input('registry_code') ?? ''));

        if ($fileNo === '') {
            return response()->json(['available' => false, 'count' => 0, 'files' => []]);
        }

        $result = app(\App\Services\RegistrySourceService::class)
            ->digitalFilesFor($fileNo, $registry !== '' ? $registry : null);

        return response()->json($result);
    }

    // ── AJAX: offices filtered by the logged-in user's department ──────────

    public function getOfficesByDepartment()
    {
        $user       = Auth::user();
        $deptId     = $user->department_id;

        if (! $deptId) {
            return response()->json([]);
        }

        $deptName = DB::connection('sqlsrv')
            ->table('departments')
            ->where('id', $deptId)
            ->value('name');

        if (! $deptName) {
            return response()->json([]);
        }

        $offices = Office::active()
            ->where('department', $deptName)
            ->orderBy('office_name')
            ->get(['id', 'office_name', 'office_code', 'department']);

        // Mark the office that should be pre-selected for this user (their own office),
        // so the mobile form can auto-pick a sensible destination on login.
        $defaultId = $this->pickDefaultOfficeId($user, $offices);

        return response()->json($offices->map(fn ($o) => [
            'id'          => $o->id,
            'office_name' => $o->office_name,
            'office_code' => $o->office_code,
            'department'  => $o->department,
            'is_default'  => $o->id === $defaultId,
        ]));
    }

    /**
     * Pick the destination office to default to for a user: the one whose name best
     * matches their work station or rank (e.g. "Director Deeds"), else the first
     * office in their department.
     */
    private function pickDefaultOfficeId(User $user, $offices): ?int
    {
        if ($offices->isEmpty()) {
            return null;
        }

        $needles = array_filter([
            trim((string) ($user->work_station ?? '')),
            trim((string) ($user->rank ?? '')),
        ]);

        foreach ($needles as $needle) {
            // Exact name match first, then a loose contains-match either way.
            $match = $offices->first(fn ($o) => mb_strtolower(trim($o->office_name)) === mb_strtolower($needle))
                ?? $offices->first(fn ($o) => $o->office_name
                    && (mb_stripos($o->office_name, $needle) !== false || mb_stripos($needle, $o->office_name) !== false));
            if ($match) {
                return $match->id;
            }
        }

        return $offices->first()->id;
    }

    // ── Store new request ──────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_no'                 => 'required|string|max:100',
            'file_title'              => 'nullable|string|max:255',
            'request_type'            => 'nullable|string|in:Physical,Digital',
            'destination_office_id'   => 'nullable|integer',
            'destination_office_name' => 'nullable|string|max:255',
            'receiving_officer'       => 'nullable|string|max:255',
            'remarks'                 => 'nullable|string|max:1000',
            'is_redirected'           => 'boolean',
            'current_file_location'   => 'nullable|string|max:255',
            'current_file_holder'     => 'nullable|string|max:255',
        ]);

        $requestType = $validated['request_type'] ?? DigitalFileRequest::TYPE_PHYSICAL;

        $user = Auth::user();

        // Duplicate guard: if the file already has an open request to SCB, warn (with who
        // raised it) before creating another. The requester may proceed by re-submitting
        // with force=1 — earlier requests are never cancelled.
        if (! $request->boolean('force')) {
            $existing = FileSearchRequest::openForFile($validated['file_no'])
                ->with('requester:id,first_name,last_name')
                ->orderBy('id')
                ->first();

            if ($existing) {
                $req = $existing->requester;
                return response()->json([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => 'This file has already been requested and sent to SCB.',
                    'existing'  => [
                        'request_no'     => $existing->request_no,
                        'requester_name' => $req ? trim($req->first_name . ' ' . $req->last_name) : '—',
                        'status'         => $existing->status,
                        'requested_at'   => optional($existing->created_at)->format('Y-m-d H:i'),
                    ],
                ], 200);
            }
        }

        // Source office: derive from user's department
        $sourceOffice = $this->resolveUserOffice($user);

        try {
            $req = DigitalFileRequest::create([
                'request_no'             => DigitalFileRequest::generateRequestNo(),
                'request_type'           => $requestType,
                'file_no'                => $validated['file_no'],
                'file_title'             => $validated['file_title'] ?? null,
                'requester_user_id'      => $user->id,
                'sending_officer'        => trim($user->first_name . ' ' . $user->last_name),
                'receiving_officer'      => $validated['receiving_officer'] ?? null,
                'source_office_id'       => $sourceOffice?->id,
                'source_office_name'     => $sourceOffice?->office_name,
                'destination_office_id'  => $validated['destination_office_id'] ?? null,
                'destination_office_name'=> $validated['destination_office_name'] ?? ($requestType === DigitalFileRequest::TYPE_DIGITAL ? 'Digital Access' : null),
                'current_file_location'  => $validated['current_file_location'] ?? null,
                'current_file_holder'    => $validated['current_file_holder'] ?? null,
                'is_redirected'          => $request->boolean('is_redirected'),
                'request_status'         => DigitalFileRequest::STATUS_PENDING,
                'remarks'                => $validated['remarks'] ?? null,
                'requested_at'           => now(),
            ]);

            // ── Notifications ─────────────────────────────────────────────
            $this->notifyRequestCreated($req, $user);

            // Physical files routed to the Front-Desk (not an in-transit redirect)
            // also raise a File Search Request so the SCB searches the file.
            if ($req->request_type === DigitalFileRequest::TYPE_PHYSICAL && ! $req->is_redirected) {
                $this->raiseScbSearchRequest($req);
            }

            $typeLabel = $req->request_type === DigitalFileRequest::TYPE_DIGITAL
                ? 'Digital access request'
                : 'Request';

            return response()->json([
                'success'      => true,
                'message'      => "{$typeLabel} {$req->request_no} submitted successfully.",
                'request_no'   => $req->request_no,
                'request_type' => $req->request_type,
                'id'           => $req->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('DigitalFileRequest store error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create request. Please try again.'], 500);
        }
    }

    // ── Send OTP for digital signature ────────────────────────────────────────

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'method' => 'required|in:email,sms',
        ]);

        $user   = Auth::user();
        $method = $validated['method'];
        $code   = (string) random_int(100000, 999999);

        session()->put("dr_sig_otp.{$user->id}", [
            'code'       => $code,
            'method'     => $method,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        if ($method === 'email') {
            $email = $user->email;
            if (! $email) {
                return response()->json(['success' => false, 'message' => 'No email address configured for your account.'], 422);
            }
            Mail::raw(
                "Your Digital File Request signature OTP is: {$code}\n\nThis code expires in 10 minutes.",
                fn ($m) => $m->to($email)->subject('Digital Signature OTP — File Request')
            );
        }

        if ($method === 'sms') {
            Log::info('Digital Request SMS OTP generated', ['user_id' => $user->id, 'code' => $code]);
        }

        return response()->json(['success' => true, 'message' => strtoupper($method) . ' OTP sent successfully.']);
    }

    // ── Verify digital signature ───────────────────────────────────────────────

    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'method'            => 'required|in:email,sms,password',
            'verification_code' => 'nullable|string|max:10',
            'password'          => 'nullable|string',
        ]);

        $user   = Auth::user();
        $method = $validated['method'];

        if ($method === 'password') {
            $pwd = $request->input('password', '');
            if (! $pwd || ! Hash::check($pwd, $user->password)) {
                return response()->json(['success' => false, 'message' => 'Password confirmation failed.'], 422);
            }
        } else {
            $code       = (string) $request->input('verification_code', '');
            $sessionOtp = session()->get("dr_sig_otp.{$user->id}");

            if (empty($sessionOtp)) {
                return response()->json(['success' => false, 'message' => 'No OTP found. Please request a new OTP first.'], 422);
            }
            if (now()->timestamp > (int) ($sessionOtp['expires_at'] ?? 0)) {
                session()->forget("dr_sig_otp.{$user->id}");
                return response()->json(['success' => false, 'message' => 'OTP has expired. Please request a new one.'], 422);
            }
            if ($code !== (string) $sessionOtp['code']) {
                return response()->json(['success' => false, 'message' => 'Invalid OTP code. Please try again.'], 422);
            }
            session()->forget("dr_sig_otp.{$user->id}");
        }

        // Resolve signature — same path as Land 12:
        // prefer LandOfficer.signature_file, fall back to User.signature
        $landOfficer = \App\Models\LandOfficer::where('user_id', $user->id)->first();
        $sigPath     = $landOfficer?->signature_file ?? $user->signature ?? null;

        // Files stored via ->store('public/...') on the local disk carry a 'public/' prefix
        // in the DB (e.g. 'public/signing_officer_signatures/file.jpg').
        // The symlink makes them accessible at /storage/<path-without-public/>.
        // Using asset() avoids the double-slash caused by APP_URL having a trailing slash.
        $sigUrl = null;
        if ($sigPath) {
            $urlPath = ltrim(preg_replace('#^public/#', '', $sigPath), '/');
            $sigUrl  = asset('storage/' . $urlPath);
        }

        // Store verification in session — valid for 15 minutes (single-use after approve)
        session()->put("dr_sig_verified.{$user->id}", [
            'method'      => $method,
            'verified_at' => now()->timestamp,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Signature verified successfully.',
            'signature_url' => $sigUrl,
        ]);
    }

    // ── Approve ────────────────────────────────────────────────────────────

    public function approve(Request $request, $id)
    {
        $req = DigitalFileRequest::findOrFail($id);

        if ($req->request_status !== DigitalFileRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be approved.'], 422);
        }

        // ── Require digital signature verification ─────────────────────────────
        $userId     = (int) Auth::id();
        $sigSession = session()->get("dr_sig_verified.{$userId}");
        $cutoff     = now()->subMinutes(15)->timestamp;
        $sigValid   = is_array($sigSession)
            && isset($sigSession['verified_at'])
            && (int) $sigSession['verified_at'] >= $cutoff;

        if (! $sigValid) {
            return response()->json([
                'success' => false,
                'message' => 'Digital signature verification is required before approving. Please verify your signature.',
            ], 422);
        }

        $user = Auth::user();

        $req->update([
            'request_status' => DigitalFileRequest::STATUS_APPROVED,
            'approved_by'    => $user->id,
            'approved_at'    => now(),
            'remarks'        => $request->input('remarks', $req->remarks),
        ]);

        // Consume signature verification (single-use)
        session()->forget("dr_sig_verified.{$userId}");

        // ── Update FileTracker movement log ───────────────────────────────
        $tracker = FileTracker::where('file_number', $req->file_no)
            ->orderByDesc('created_at')
            ->first();

        if ($tracker) {
            try {
                // Close out the current movement
                $tracker->completeCurrentMovement(now()->format('H:i'), 'Digital request approved — file dispatched to ' . $req->destination_office_name);

                // Open new movement to destination
                $tracker->addMovementLog(
                    $req->destination_office_name, // office_code (use name as code if no code stored)
                    $req->destination_office_name,
                    now()->format('H:i'),
                    now()->format('Y-m-d'),
                    'Digital request ' . $req->request_no . ' approved',
                    $user->id,
                    trim($user->first_name . ' ' . $user->last_name),
                    [
                        'receiving_officer_id'   => $req->requester_user_id,
                        'receiving_officer_name' => $req->sending_officer,
                        'receiving_office_name'  => $req->destination_office_name,
                        'requires_acceptance'    => true,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('DigitalFileRequest approve: tracker update failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        // ── Digital file: copy to temporary folder ────────────────────────────
        $digitalNote = '';
        if ($req->request_type === DigitalFileRequest::TYPE_DIGITAL) {
            try {
                $access      = $this->digitalAccess->grantAccess($req);
                $days        = config('dfr.access_days', 5);
                $digitalNote = " Digital copy granted — access expires in {$days} working days.";
            } catch (\Throwable $e) {
                Log::error('DFR digital copy failed after approval', [
                    'request_id' => $req->id,
                    'error'      => $e->getMessage(),
                ]);
                // Don't block the approval; inform the response so UI can surface the warning
                $digitalNote = ' (Warning: digital file copy could not be created — ' . $e->getMessage() . ')';
            }
        }

        $this->notifyRequestApproved($req, $user);

        $successMsg = $req->request_type === DigitalFileRequest::TYPE_DIGITAL
            ? 'Digital access request approved.' . $digitalNote
            : 'Request approved successfully.';

        return response()->json(['success' => true, 'message' => $successMsg]);
    }

    // ── Reject ─────────────────────────────────────────────────────────────

    public function reject(Request $request, $id)
    {
        $req = DigitalFileRequest::findOrFail($id);

        if ($req->request_status !== DigitalFileRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be rejected.'], 422);
        }

        $request->validate(['rejection_reason' => 'required|string|max:1000']);

        $user = Auth::user();

        $req->update([
            'request_status'   => DigitalFileRequest::STATUS_REJECTED,
            'rejected_by'      => $user->id,
            'rejected_at'      => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        $this->notifyRequestRejected($req, $user);

        return response()->json(['success' => true, 'message' => 'Request rejected.']);
    }

    // ── Show details (AJAX) ────────────────────────────────────────────────

    public function show($id)
    {
        $req = DigitalFileRequest::with(['requester', 'approver', 'rejecter'])->findOrFail($id);

        // Resolve signature URLs (same path-strip logic as verifySignature)
        $resolveSignatureUrl = function (?User $user): ?string {
            if (! $user) return null;
            $lo      = \App\Models\LandOfficer::where('user_id', $user->id)->first();
            $sigPath = $lo?->signature_file ?? $user->signature ?? null;
            if (! $sigPath) return null;
            $urlPath = ltrim(preg_replace('#^public/#', '', $sigPath), '/');
            return asset('storage/' . $urlPath);
        };

        return response()->json([
            'id'                     => $req->id,
            'request_no'             => $req->request_no,
            'file_no'                => $req->file_no,
            'file_title'             => $req->file_title,
            'sending_officer'        => $req->sending_officer,
            'receiving_officer'      => $req->receiving_officer,
            'source_office_name'     => $req->source_office_name,
            'destination_office_name'=> $req->destination_office_name,
            'current_file_location'  => $req->current_file_location,
            'current_file_holder'    => $req->current_file_holder,
            'is_redirected'          => $req->is_redirected,
            'request_status'         => $req->request_status,
            'status_badge_class'     => $req->status_badge_class,
            'remarks'                => $req->remarks,
            'rejection_reason'       => $req->rejection_reason,
            'requested_at'           => $req->requested_at?->format('d M Y H:i'),
            'approved_at'            => $req->approved_at?->format('d M Y H:i'),
            'rejected_at'            => $req->rejected_at?->format('d M Y H:i'),
            'approved_by_name'       => $req->approver ? trim($req->approver->first_name . ' ' . $req->approver->last_name) : null,
            'rejected_by_name'       => $req->rejecter ? trim($req->rejecter->first_name . ' ' . $req->rejecter->last_name) : null,
            'requester_signature_url'=> $resolveSignatureUrl($req->requester),
            'approver_signature_url' => $resolveSignatureUrl($req->approver),
        ]);
    }

    // ── My Digital Files (user's temp copies) ────────────────────────────────

    public function myFiles(Request $request)
    {
        $userId = Auth::id();

        $active  = DigitalFileAccess::where('requester_user_id', $userId)
            ->active()
            ->with('request')
            ->orderByDesc('granted_at')
            ->get();

        $history = DigitalFileAccess::where('requester_user_id', $userId)
            ->where('access_status', '!=', DigitalFileAccess::STATUS_ACTIVE)
            ->with('request')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get();

        return view('digital_request.my_files', compact('active', 'history'));
    }

    // ── Active file nos with digital access (for badge rendering) ───────────

    public function activeFileNos()
    {
        $fileNos = DigitalFileAccess::where('requester_user_id', Auth::id())
            ->active()
            ->pluck('file_no')
            ->unique()
            ->values();

        return response()->json(['file_nos' => $fileNos]);
    }

    // ── View a single digital file inline (no download) ─────────────────────

    public function viewFile(DigitalFileAccess $access, string $filename)
    {
        // Ownership check
        if ((int) $access->requester_user_id !== (int) Auth::id()) {
            abort(403, 'You do not have permission to view this file.');
        }

        // Expiry check
        if ($access->isExpired()) {
            abort(410, 'This digital file access has expired.');
        }

        // Filename allowlist check (prevent path traversal)
        $allowedFiles = (array) ($access->files_copied ?? []);
        if (! in_array($filename, $allowedFiles, true)) {
            abort(404, 'File not found in this access grant.');
        }

        $absolutePath = $access->absoluteTempPath() . DIRECTORY_SEPARATOR . $filename;

        if (! is_file($absolutePath)) {
            abort(404, 'The requested file no longer exists on the server.');
        }

        // Extension-based MIME map (reliable on Windows where mime_content_type can fail)
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeMap  = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',
        ];
        $mimeType = $mimeMap[$ext]
            ?? (function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream');

        // ── Flush all output buffers before streaming binary content ──────────
        // A rogue space/byte is being output somewhere in the middleware chain
        // which corrupts the binary file. Clean ALL ob levels before streaming.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Stream directly via PHP to ensure no buffered garbage precedes the binary
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Frame-Options: SAMEORIGIN');
        header('Access-Control-Allow-Origin: *');
        readfile($absolutePath);
        exit();
    }

    // ── Revoke an access (admin / approver) ───────────────────────────────────

    public function revokeAccess(DigitalFileAccess $access)
    {
        try {
            $this->digitalAccess->revokeAccess($access);
            return response()->json(['success' => true, 'message' => 'Digital access revoked and temporary files removed.']);
        } catch (\Throwable $e) {
            Log::error('DFR revoke failed', ['access_id' => $access->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not revoke access: ' . $e->getMessage()], 500);
        }
    }

    // ── Pending count (for live polling) ───────────────────────────────────

    public function pendingCount()
    {
        $count  = DigitalFileRequest::pending()->count();
        $latest = DigitalFileRequest::pending()
            ->orderByDesc('created_at')
            ->first(['request_no', 'file_no', 'sending_officer', 'created_at']);

        return response()->json([
            'count'   => $count,
            'latest'  => $latest ? [
                'request_no'      => $latest->request_no,
                'file_no'         => $latest->file_no,
                'sending_officer' => $latest->sending_officer,
            ] : null,
        ]);
    }

    // ── JSON list (for table AJAX load) ────────────────────────────────────

    public function listJson(Request $request)
    {
        try {
        $query = DigitalFileRequest::query()->orderByDesc('created_at');

        // KANGIS-scoped list: only KANGIS-format file numbers
        if ($request->has('kangis') || strtolower((string) $request->input('url')) === 'kangis') {
            $query->kangisFiles();
        }

        // Mobile: only return the current user's own requests
        if ($request->boolean('mine')) {
            $query->where('requester_user_id', Auth::id());
        }

        if ($status = $request->input('status')) {
            $query->where('request_status', $status);
        }
        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('file_no', 'like', "%{$search}%")
                  ->orWhere('file_title', 'like', "%{$search}%")
                  ->orWhere('sending_officer', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $page    = (int) $request->input('page', 1);
        $total   = $query->count();
        $rows    = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Eager-load approver names in one query
        $approverIds = $rows->pluck('approved_by')->filter()->unique()->values();
        $approvers   = $approverIds->isNotEmpty()
            ? User::whereIn('id', $approverIds)->get(['id','first_name','last_name'])->keyBy('id')
            : collect();

        // Resolve the physical location (Registry / Archive + rack/shelf) per row when
        // requested (mobile DFR list). Gated by a flag so the web list keeps its perf.
        $resolveLocation = $request->boolean('with_location')
            ? $this->buildLocationResolver()
            : null;

        $data = $rows->map(fn ($r) => [
            'id'                      => $r->id,
            'request_no'              => $r->request_no,
            'request_type'            => $r->request_type,
            'file_no'                 => $r->file_no,
            'file_title'              => $r->file_title,
            'remarks'                 => $r->remarks,
            'sending_officer'         => $r->sending_officer,
            'receiving_officer'       => $r->receiving_officer,
            'destination_office_name' => $r->destination_office_name,
            'approved_by_name'        => $r->approved_by && isset($approvers[$r->approved_by])
                                            ? trim($approvers[$r->approved_by]->first_name . ' ' . $approvers[$r->approved_by]->last_name)
                                            : null,
            'request_status'          => $r->request_status,
            'status_badge_class'      => $r->status_badge_class,
            'is_redirected'           => $r->is_redirected,
            'requested_at'            => $r->requested_at?->format('Y-m-d') ?? $r->created_at?->format('Y-m-d'),
            'location'                => $resolveLocation ? $resolveLocation($r->file_no) : null,
        ]);

        return response()->json([
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ]);

        } catch (\Throwable $e) {
            \Log::error('DFR listJson error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Build a closure that resolves a file number to a compact location summary
     * (Registry / Archive label + registry name + rack/shelf). Returns null per
     * row when the file number is empty or resolution fails.
     */
    private function buildLocationResolver(): \Closure
    {
        $resolver = app(\App\Services\FileLocationResolver::class);

        $labels = [
            \App\Services\FileLocationResolver::STATUS_IN_ARCHIVE   => 'Archive',
            \App\Services\FileLocationResolver::STATUS_IN_POOL      => 'Registry (Pool Office)',
            \App\Services\FileLocationResolver::STATUS_IN_TRANSIT   => 'In Transit',
            \App\Services\FileLocationResolver::STATUS_PENDING_FILE => 'Not Indexed',
            \App\Services\FileLocationResolver::STATUS_REFER        => 'Refer to Original Registry',
            \App\Services\FileLocationResolver::STATUS_NOT_FOUND    => 'Missing',
        ];

        return function (?string $fileNo) use ($resolver, $labels): ?array {
            $fileNo = trim((string) $fileNo);
            if ($fileNo === '') {
                return null;
            }
            try {
                $r = $resolver->resolve($fileNo);
            } catch (\Throwable $e) {
                return null;
            }
            // A file keeps its assigned home shelf even while in transit, so fall
            // back to a direct shelf lookup when resolve() didn't supply one.
            $rackShelf = $r['rack_shelf'] ?: $resolver->rackShelfFor($fileNo);

            return [
                'status'           => $r['status'],
                'label'            => $labels[$r['status']] ?? 'Registry',
                'registry'         => $r['registry'],
                'rack_shelf'       => $rackShelf,
                'current_location' => $r['current_location'],
            ];
        };
    }

    /**
     * Resolve a receiving officer's rank from their "First Last" name (the value stored
     * by the DFR Receiving Officer dropdown), so seniority can be derived. Returns null
     * when no matching active user / rank is found.
     */
    private function resolveOfficerRank(?string $officerName): ?string
    {
        $officerName = trim((string) $officerName);
        if ($officerName === '') {
            return null;
        }

        $rank = DB::connection('sqlsrv')
            ->table('users')
            ->where('is_active', 1)
            ->whereRaw("LTRIM(RTRIM(CONCAT(first_name, ' ', last_name))) = ?", [$officerName])
            ->value('rank');

        return $rank ?: null;
    }

    private function resolveUserOffice(User $user): ?Office
    {
        if (! $user->department_id) return null;

        $deptName = DB::connection('sqlsrv')
            ->table('departments')
            ->where('id', $user->department_id)
            ->value('name');

        if (! $deptName) return null;

        return Office::active()
            ->where('department', $deptName)
            ->orderBy('office_name')
            ->first();
    }

    private function notifyRequestCreated(DigitalFileRequest $req, User $requester): void
    {
        $title = "Digital File Request: {$req->request_no}";
        $body  = "{$req->sending_officer} has submitted a request for file {$req->file_no} to be sent to {$req->destination_office_name}.";

        // Notify the requester themselves (confirmation)
        $this->notifications->create($requester->id, 'digital_request', $title, $body, [
            'request_id' => $req->id,
            'request_no' => $req->request_no,
            'file_no'    => $req->file_no,
        ], ['module' => 'digital_request']);

        // Routing:
        //  • In-transit file → redirected to the last receiving officer currently holding it.
        //  • Otherwise → sent to the KLAES Front-Desk (DFR approvers) to retrieve the file.
        if ($req->is_redirected && $req->receiving_officer) {
            $receivingUser = User::where(
                DB::raw("LTRIM(RTRIM(first_name)) + ' ' + LTRIM(RTRIM(last_name))"),
                $req->receiving_officer
            )->first();

            if ($receivingUser) {
                $this->notifications->create($receivingUser->id, 'digital_request', $title,
                    "A file request ({$req->request_no}) for {$req->file_no} has been redirected to you — our records show this file is currently in your custody ({$req->current_file_location}).",
                    ['request_id' => $req->id],
                    ['module' => 'digital_request']
                );
            }
        } else {
            $this->notifyFrontDesk($req);
        }
    }

    /**
     * Notify the KLAES Front-Desk — users who can approve/fulfil Digital File Requests
     * (dfr_permissions contains 'approve_request').
     */
    private function notifyFrontDesk(DigitalFileRequest $req): void
    {
        $frontDesk = User::where('is_active', 1)
            ->where('dfr_permissions', 'like', '%approve_request%')
            ->get(['id']);

        $title = "New File Request: {$req->request_no}";
        $body  = "{$req->sending_officer} has requested file {$req->file_no}. Please retrieve it from the registry and process the request.";

        foreach ($frontDesk as $fd) {
            $this->notifications->create($fd->id, 'digital_request', $title, $body,
                ['request_id' => $req->id, 'file_no' => $req->file_no],
                ['module' => 'digital_request']
            );
        }
    }

    /**
     * Raise a File Search Request (FSR) to the SCB from a Digital File Request,
     * so the file also surfaces in the SCB's Quick Search / File Requests inbox.
     * Tagged with source = DFR. The DFR itself is unaffected.
     */
    private function raiseScbSearchRequest(DigitalFileRequest $req): void
    {
        try {
            $resolver = app(FileLocationResolver::class);
            $resolved = $resolver->resolve($req->file_no);

            // Seniority follows the Requester seniority hierarchy. Consider BOTH the
            // Receiving Officer (the senior person the file is requested FOR) and the
            // user who raised the DFR (who may themselves be a ranked/OFS officer), and
            // honour whichever is more senior.
            $officerRank   = $this->resolveOfficerRank($req->receiving_officer);
            $officerBasis  = $officerRank ?: $req->receiving_officer;
            $requesterRank = $req->requester_user_id
                ? \App\Models\User::whereKey($req->requester_user_id)->value('rank')
                : null;

            $pOfficer   = FileSearchRequest::priorityFor($officerBasis);
            $pRequester = FileSearchRequest::priorityFor($requesterRank);
            $priority   = max($pOfficer, $pRequester);
            // OFS when either side resolves to a ranked officer; label with the rank that
            // drove it (prefer the requester on a tie — they are the acting officer).
            $isOfs   = $priority > 0;
            $ofsRank = ! $isOfs ? null : ($pRequester >= $pOfficer ? $requesterRank : $officerBasis);

            $fr = FileSearchRequest::create([
                'request_no'        => FileSearchRequest::generateRequestNo(),
                'file_number'       => $req->file_no,
                'file_title'        => $req->file_title,
                'requester_user_id' => $req->requester_user_id,
                'status'            => FileSearchRequest::STATUS_PENDING,
                'resolved_status'   => $resolved['status'] ?? null,
                'current_location'  => $resolved['current_location'] ?? null,
                'source'            => FileSearchRequest::SOURCE_DFR,
                'receiving_officer' => $req->receiving_officer,
                'is_ofs'            => $isOfs,
                'ofs_rank'          => $ofsRank,
                'priority'          => $priority,
            ]);

            $this->notifyScbMonitors($fr, $req->sending_officer ?: 'A registry user');
        } catch (\Throwable $e) {
            // Never block the DFR if the SCB request can't be raised.
            Log::warning('DFR raiseScbSearchRequest failed', ['dfr_id' => $req->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Notify every SCB Monitor (fr_permissions = 'SCB') about a new File Search Request.
     */
    private function notifyScbMonitors(FileSearchRequest $fr, string $requesterName): void
    {
        $monitors = User::where('fr_permissions', 'SCB')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->get(['id']);

        $title = "File Search Request: {$fr->request_no}";
        $body  = "{$requesterName} raised a DFR for file {$fr->file_number}. Please physically locate the file and confirm Found / Not Found.";

        foreach ($monitors as $monitor) {
            $this->notifications->create($monitor->id, 'file_search_request', $title, $body,
                ['request_id' => $fr->id, 'request_no' => $fr->request_no, 'file_number' => $fr->file_number],
                ['module' => 'file_search_request']
            );
        }
    }

    private function notifyRequestApproved(DigitalFileRequest $req, User $approver): void
    {
        $approverName = trim($approver->first_name . ' ' . $approver->last_name);
        $body = "Your file request {$req->request_no} for file {$req->file_no} has been APPROVED by {$approverName}. The file will be dispatched to {$req->destination_office_name}.";

        $this->notifications->create($req->requester_user_id, 'digital_request',
            "Request Approved: {$req->request_no}", $body,
            ['request_id' => $req->id, 'status' => 'Approved'],
            ['module' => 'digital_request']
        );
    }

    private function notifyRequestRejected(DigitalFileRequest $req, User $rejecter): void
    {
        $rejecterName = trim($rejecter->first_name . ' ' . $rejecter->last_name);
        $body = "Your file request {$req->request_no} for file {$req->file_no} has been REJECTED by {$rejecterName}. Reason: {$req->rejection_reason}";

        $this->notifications->create($req->requester_user_id, 'digital_request',
            "Request Rejected: {$req->request_no}", $body,
            ['request_id' => $req->id, 'status' => 'Rejected'],
            ['module' => 'digital_request']
        );
    }
}
