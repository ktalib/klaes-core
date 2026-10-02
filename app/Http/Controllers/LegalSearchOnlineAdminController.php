<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineFeedback;
use App\Models\LegalSearchOnlineRequest;
use App\Services\LandOfficerSignatureService;
use App\Services\LegalSearchApprovalService;
use App\Services\LegalSearchService;
use Carbon\Carbon;

class LegalSearchOnlineAdminController extends Controller
{
    // ── Admin Dashboard ─────────────────────────────────────────────

    public function admin()
    {
        $PageTitle = 'Online Legal Search — Admin';

        // KPI stats
        $totalRevenue      = LegalSearchOnlinePayment::where('status', 'paid')->sum('amount'); // kobo
        $totalPaid         = LegalSearchOnlinePayment::where('status', 'paid')->count();
        $todayRevenue      = LegalSearchOnlinePayment::where('status', 'paid')
                                ->whereDate('paid_at', today())->sum('amount');
        $monthRevenue      = LegalSearchOnlinePayment::where('status', 'paid')
                                ->whereMonth('paid_at', now()->month)
                                ->whereYear('paid_at', now()->year)
                                ->sum('amount');
        $openFeedback      = LegalSearchOnlineFeedback::where('status', 'open')->count();

        $stats = [
            'total_revenue'  => $totalRevenue / 100,  // naira
            'today_revenue'  => $todayRevenue / 100,
            'month_revenue'  => $monthRevenue / 100,
            'total_paid'     => $totalPaid,
            'open_feedback'  => $openFeedback,
        ];

        // `verification` carries the applicant's real name — the `user` relation is
        // always null here, because the portal is public and has no accounts.
        $payments = LegalSearchOnlinePayment::with(['user', 'verification'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('legal_search_online.admin', compact('PageTitle', 'stats', 'payments'));
    }

    // ── Feedback (admin view) ────────────────────────────────────────

    public function feedbackIndex()
    {
        $PageTitle = 'Online Legal Search — Feedback & Complaints';
        $isAdmin   = auth()->user()?->assign_role === 'Supper Admin';

        if ($isAdmin) {
            $feedbacks = LegalSearchOnlineFeedback::with(['user', 'resolver'])
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->paginate(40);
        } else {
            $feedbacks = LegalSearchOnlineFeedback::where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('legal_search_online.feedback', compact('PageTitle', 'feedbacks', 'isAdmin'));
    }

    public function feedbackStore(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'      => 'required|string|max:150',
            'email'     => 'required|email|max:255',
            'phone'     => 'nullable|string|max:30',
            'subject'   => 'required|string|max:200',
            'message'   => 'required|string|max:3000',
            'reference' => 'nullable|string|max:60',
        ]);

        LegalSearchOnlineFeedback::create(array_merge($validated, [
            'user_id' => $user?->id,
        ]));

        return back()->with('success', 'Your feedback has been submitted. We will respond within 2 business days.');
    }

    public function feedbackUpdate(Request $request, int $id)
    {
        abort_unless(auth()->user()?->assign_role === 'Supper Admin', 403);

        $feedback = LegalSearchOnlineFeedback::findOrFail($id);

        $validated = $request->validate([
            'status'         => 'required|in:open,in_progress,resolved',
            'admin_response' => 'nullable|string|max:3000',
        ]);

        $feedback->update(array_merge($validated, [
            'resolved_by' => $validated['status'] === 'resolved' ? auth()->id() : $feedback->resolved_by,
            'resolved_at' => $validated['status'] === 'resolved' ? ($feedback->resolved_at ?? now()) : $feedback->resolved_at,
        ]));

        return response()->json(['success' => true, 'message' => 'Feedback updated.']);
    }

    // ── Search Request Approval (Director / Deputy Director) ─────────

    /**
     * Approval queue for public Online Legal Search requests.
     *
     * Visible to any signed-in staff member so the queue can be monitored, but
     * only Directors / Deputy Directors (and super admins) may act on it.
     */
    public function requestsIndex(Request $request, LegalSearchApprovalService $approvalService)
    {
        $PageTitle = 'Online Legal Search — Search Requests';

        $status = $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = LegalSearchOnlineRequest::with('reviewer')->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', '%' . $search . '%')
                    ->orWhere('file_number', 'like', '%' . $search . '%')
                    ->orWhere('requester_email', 'like', '%' . $search . '%')
                    ->orWhere('tracking_id', 'like', '%' . $search . '%');
            });
        }

        $requests = $query->paginate(30)->withQueryString();

        $counts = [
            'pending'  => LegalSearchOnlineRequest::where('status', 'pending')->count(),
            'approved' => LegalSearchOnlineRequest::where('status', 'approved')->count(),
            'rejected' => LegalSearchOnlineRequest::where('status', 'rejected')->count(),
        ];

        // Oldest item still waiting — the number that tells an approver whether
        // the queue is being kept current.
        $oldestPending = LegalSearchOnlineRequest::where('status', 'pending')
            ->orderBy('submitted_at')
            ->value('submitted_at');

        $stats = [
            'pending'        => $counts['pending'],
            'oldest_pending' => $oldestPending ? Carbon::parse($oldestPending) : null,
            'approved_today' => LegalSearchOnlineRequest::where('status', 'approved')
                                    ->whereDate('reviewed_at', today())->count(),
            'approved_total' => $counts['approved'],
            'rejected'       => $counts['rejected'],
            // Approved but the report never reached the requester — these need
            // a "Resend report" rather than a fresh approval.
            'undelivered'    => LegalSearchOnlineRequest::where('status', 'approved')
                                    ->whereNull('emailed_at')->count(),
        ];

        return view('legal_search_online.requests', [
            'PageTitle'  => $PageTitle,
            'requests'   => $requests,
            'counts'     => $counts,
            'stats'      => $stats,
            'status'     => $status,
            'q'          => $request->query('q', ''),
            'highlight'  => (int) $request->query('highlight', 0),
            'canApprove' => $approvalService->isApprover(auth()->user()),
        ]);
    }

    /**
     * Preview the report a request would release, so an approver can read it
     * before signing off.
     *
     * Streams the actual PDF rather than an HTML lookalike — what the approver
     * reads here is the same document the requester receives, produced by the
     * same renderer. Nothing is sent; delivery happens only on approval.
     */
    /**
     * The identification (IYC) submitted behind a search request.
     *
     * Approver-gated like the rest of the queue. This is the only screen that
     * surfaces the uploaded document, and it does so through the streaming route
     * rather than by exposing a storage path.
     */
    public function requestIdentification(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless(
            $approvalService->isApprover(auth()->user()),
            403,
            'Only a Director or Deputy Director may view submitted identification.'
        );

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);
        $verification  = $searchRequest->verification();

        abort_if(
            !$verification,
            404,
            'No identification was recorded for this request. It predates the IYC step.'
        );

        // Every file the payment covered, so a reviewer can see this identification
        // belongs to a multi-file basket rather than only the file in front of them.
        $siblings = LegalSearchOnlineRequest::where('payment_id', $searchRequest->payment_id)
            ->orderBy('id')
            ->get();

        return view('legal_search_online.identification', [
            'PageTitle'     => 'Identify your Customer — ' . $searchRequest->request_no,
            'searchRequest' => $searchRequest,
            'verification'  => $verification,
            'siblings'      => $siblings,
        ]);
    }

    public function requestPreview(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403, 'Only a Director or Deputy Director may review Online Legal Search requests.');

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);
        $report = $approvalService->buildReport($searchRequest);

        if (!$report) {
            abort(404, 'The report for file ' . ($searchRequest->file_number ?: '—') . ' could not be generated. Approving this request will fail until the underlying record is fixed.');
        }

        // preview: true — this copy is being reviewed, not delivered, so it carries
        // the red PREVIEW mark. The emailed attachment renders without it.
        return $approvalService->renderPdf($searchRequest, $report, true)
            ->stream($approvalService->pdfFileName($searchRequest));
    }

    /**
     * The correction workspace for a request: edit the records BEFORE approving.
     *
     * The Preview action streams a PDF, which cannot host controls, so correcting
     * a result meant leaving the approval screen entirely. This renders the same
     * report as an editable page, using the SAME record-editing endpoints as the
     * Legal Search timeline (legalsearch.update / .remove / .drop) and the same
     * Edit Record modal, so a correction made here behaves identically to one made
     * there. Every change lands in audit_logs against the admin's name.
     */
    public function requestCorrect(int $id, LegalSearchApprovalService $approvalService, LegalSearchService $searchService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403, 'Only a Director or Deputy Director may correct Online Legal Search requests.');

        $searchRequest = LegalSearchOnlineRequest::with('payment')->findOrFail($id);
        $fileNumber = trim((string) $searchRequest->file_number);

        $report = null;
        $reportError = null;
        $records = [];
        $propId = null;

        // ONE engine pass. buildPrintReport() calls search() internally, so asking
        // for both ran the whole search twice — 22s of engine time on a heavy file.
        // search() already returns the real records with ids, prop_id-expanded, which
        // is what correcting needs; the printed report only added read-only synthetic
        // rows on top.
        try {
            $found = $searchService->search(['query' => $fileNumber]);

            if (empty($found['transactions'])) {
                $reportError = 'No records were found for ' . ($fileNumber ?: '—') . '.';
            }

            foreach ($found['transactions'] ?? [] as $t) {
                if (empty($t['id'])) {
                    continue;
                }

                $table = [
                    'File History'      => 'file_history_staging',
                    'CofO'              => 'CofO_staging',
                    'PRA'               => 'pra',
                    'Deed Registration' => 'deed_registrations',
                ][$t['source_table'] ?? ''] ?? null;

                if (!$table) {
                    continue;
                }

                // The arrangement table is keyed on prop_id, so capture it off the
                // rows rather than looking it up again.
                if ($propId === null && !empty($t['prop_id'])) {
                    $propId = (string) $t['prop_id'];
                }

                $records[] = [
                    'id'          => $t['id'],
                    'table'       => $table,
                    'instrument'  => $t['transaction_type'] ?? 'Instrument',
                    'party_1'     => $t['party_1'] ?? null,
                    'party_2'     => $t['party_2'] ?? null,
                    'reg_no'      => $t['regNo'] ?? ($t['registration'] ?? null),
                    'date'        => $t['transaction_date'] ?? ($t['reg_date'] ?? null),
                    'file_number' => $t['lifecycle_file_no'] ?? ($t['file_number'] ?? $fileNumber),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return view('legal_search_online.correct', [
            'PageTitle'     => 'Preview Report — ' . ($searchRequest->request_no ?: $fileNumber),
            'searchRequest' => $searchRequest,
            'report'        => $report,
            'reportError'   => $reportError,
            'records'       => $records,
            'propId'        => $propId,
            'fileNumber'    => $fileNumber,
        ]);
    }

    /**
     * Preview the payment invoice that accompanies an approved report.
     *
     * Streams the same document the requester receives as the second email
     * attachment. Viewing it sends nothing.
     */
    public function requestInvoice(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403, 'Only a Director or Deputy Director may review Online Legal Search requests.');

        $searchRequest = LegalSearchOnlineRequest::with('payment')->findOrFail($id);

        // No payment row means no invoice was ever issued for this request.
        if (!$searchRequest->payment) {
            abort(404, 'Request ' . $searchRequest->request_no . ' has no payment recorded against it, so there is no invoice to show.');
        }

        return $approvalService->renderInvoicePdf($searchRequest)
            ->stream($approvalService->invoiceFileName($searchRequest));
    }

    /**
     * Approve a request: generates the report and emails it to the requester.
     */
    public function requestApprove(Request $request, int $id, LegalSearchApprovalService $approvalService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403, 'Only a Director or Deputy Director may approve Online Legal Search requests.');

        $validated = $request->validate([
            'review_note'     => 'nullable|string|max:1000',
            'apply_signature' => 'nullable|boolean',
        ]);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isPending()) {
            return back()->with('error', 'Request ' . $searchRequest->request_no . ' has already been ' . $searchRequest->status . '.');
        }

        $sign = (bool) ($validated['apply_signature'] ?? false);

        if ($sign) {
            // Signing was requested but no usable signature exists — stop rather
            // than quietly issuing an unsigned report.
            $signature = $approvalService->signatureFor($user);
            if (!$signature['has_signature']) {
                return back()->with('error', $this->signatureMessage($signature) . ' The report was not sent.');
            }

            // The dialog sets this flag client-side, so second-level auth is
            // re-checked here before a signature is stamped on an issued report.
            if (!$approvalService->signatureVerified($user)) {
                return back()->with('error', 'Your signature verification has expired. Re-open the request, press Sign and confirm again.');
            }
        }

        try {
            $emailed = $approvalService->approve($searchRequest, $user, $validated['review_note'] ?? null, $sign);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Each signature is authorised separately — the next one re-verifies.
        if ($sign) {
            $approvalService->forgetSignatureVerification($user);
        }

        return back()->with(
            $emailed ? 'success' : 'error',
            $emailed
                ? 'Request ' . $searchRequest->request_no . ' approved. The report was emailed to ' . $searchRequest->requester_email . '.'
                : 'Request ' . $searchRequest->request_no . ' was approved, but the email to ' . $searchRequest->requester_email . ' failed to send. Use "Resend report" to try again.'
        );
    }

    /**
     * The signed-in approver's own digital signature, for the "Sign" button in
     * the approve dialog to preview before the report goes out.
     */
    public function requestSignature(LegalSearchApprovalService $approvalService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403);

        $signature = $approvalService->signatureFor($user);

        return response()->json([
            'has_signature' => $signature['has_signature'],
            // Withheld until second-level auth passes, so the dialog cannot
            // show a signature the approver has not yet authorised.
            'data_uri'      => $approvalService->signatureVerified($user) ? $signature['data_uri'] : null,
            'verified'      => $approvalService->signatureVerified($user),
            'method'        => $signature['method'],
            'registered'    => $signature['registered'],
            'name'          => $signature['name'],
            'rank'          => $signature['rank'],
            'message'       => $this->signatureMessage($signature),
        ]);
    }

    /**
     * Why a signature cannot be used, in the approver's terms.
     */
    protected function signatureMessage(array $signature): ?string
    {
        if ($signature['has_signature']) {
            return $signature['method'] ? null : 'No verification method is set for you in Digital Signature Control. Password confirmation will be used.';
        }

        if (!$signature['registered']) {
            return 'You are not registered in Digital Signature Control, so no signature is available. Ask a system administrator to add you as a signing officer.';
        }

        return $signature['unreadable']
            ? 'Your signature file could not be read on the server, so it cannot be added to the report. Please re-upload it in Digital Signature Control.'
            : 'No signature file is saved against you in Digital Signature Control. Upload one there to sign reports.';
    }

    /**
     * Send the OTP for approvers whose Digital Signature Control verification
     * method is Email OTP or SMS OTP.
     */
    public function requestSignatureOtp(LegalSearchApprovalService $approvalService, LandOfficerSignatureService $signatureService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403);

        $officer = $approvalService->signingOfficerFor($user);

        if (!$officer) {
            return response()->json(['success' => false, 'message' => 'You are not registered in Digital Signature Control.'], 422);
        }

        $method = $officer->notification_type ?: 'password';

        if (!in_array($method, ['email', 'sms'], true)) {
            return response()->json(['success' => false, 'message' => 'Your verification method does not use an OTP.'], 422);
        }

        $result = $signatureService->sendOtp($officer, $method);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Clear second-level auth and release the signature to the dialog.
     */
    public function requestSignatureVerify(
        Request $request,
        LegalSearchApprovalService $approvalService,
        LandOfficerSignatureService $signatureService
    ) {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403);

        $validated = $request->validate([
            'password' => 'nullable|string',
            'otp_code' => 'nullable|string|max:10',
        ]);

        $signature = $approvalService->signatureFor($user);

        if (!$signature['has_signature']) {
            return response()->json(['success' => false, 'message' => $this->signatureMessage($signature)], 422);
        }

        $officer = $approvalService->signingOfficerFor($user);
        $method  = $officer->notification_type ?: 'password';

        $result = $signatureService->verifySecondLevelAuth(
            $officer,
            $method,
            $validated['otp_code'] ?? null,
            $validated['password'] ?? null
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $approvalService->markSignatureVerified($user);

        return response()->json([
            'success'  => true,
            'message'  => $result['message'],
            'data_uri' => $signature['data_uri'],
            'name'     => $signature['name'],
            'rank'     => $signature['rank'],
        ]);
    }

    /**
     * Decline a request and tell the requester why.
     */
    public function requestReject(Request $request, int $id, LegalSearchApprovalService $approvalService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403, 'Only a Director or Deputy Director may decline Online Legal Search requests.');

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isPending()) {
            return back()->with('error', 'Request ' . $searchRequest->request_no . ' has already been ' . $searchRequest->status . '.');
        }

        $approvalService->reject($searchRequest, $user, $validated['rejection_reason']);

        return back()->with('success', 'Request ' . $searchRequest->request_no . ' declined. The requester has been notified.');
    }

    /**
     * Re-send the report for a request that was approved but whose email failed
     * (or that the requester never received).
     */
    public function requestResend(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isApproved()) {
            return back()->with('error', 'Only an approved request can have its report re-sent.');
        }

        try {
            $sent = $approvalService->resend($searchRequest);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'Report re-sent to ' . $searchRequest->requester_email . '.'
                : 'The report could not be emailed to ' . $searchRequest->requester_email . '. See the recorded error on the request.'
        );
    }
}
