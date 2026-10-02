<?php

namespace App\Services;

use App\Mail\LegalSearchRequestApproved;
use App\Mail\LegalSearchRequestPendingApproval;
use App\Mail\LegalSearchRequestRejected;
use App\Mail\LegalSearchRequestSubmitted;
use App\Models\LandOfficer;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Drives the Online Legal Search request → approval → email workflow.
 *
 * The public portal used to render the full report as soon as a payment
 * cleared. It now opens a request instead: Directors / Deputy Directors are
 * notified, and only on their approval is the report emailed out as a PDF.
 */
class LegalSearchApprovalService
{
    public function __construct(
        protected LegalSearchService $searchService,
        protected UserNotificationService $notifier,
    ) {
    }

    // ── Approver resolution ──────────────────────────────────────────

    /**
     * Staff who may review Online Legal Search requests: anyone whose
     * `users.rank` matches the configured Director / Deputy Director ranks,
     * plus super admins when allowed.
     */
    public function approvers(): Collection
    {
        $cfg = config('legal_search.online_approval');

        $users = User::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($cfg) {
                foreach ((array) ($cfg['approver_ranks'] ?? []) as $rank) {
                    $q->orWhere('rank', $rank);
                }
                foreach ((array) ($cfg['approver_rank_prefixes'] ?? []) as $prefix) {
                    $q->orWhere('rank', 'like', $prefix . '%');
                }
                if (!empty($cfg['allow_super_admin'])) {
                    $q->orWhere('assign_role', 'Supper Admin');
                }
            })
            ->get();

        $excluded = array_map('strtolower', (array) ($cfg['excluded_ranks'] ?? []));

        return $users->reject(
            fn (User $u) => in_array(strtolower((string) $u->rank), $excluded, true)
        )->values();
    }

    /**
     * May this user act on the approval queue?
     */
    public function isApprover(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $cfg = config('legal_search.online_approval');

        if (!empty($cfg['allow_super_admin']) && $user->assign_role === 'Supper Admin') {
            return true;
        }

        $rank = trim((string) $user->rank);
        if ($rank === '') {
            return false;
        }

        if (in_array(strtolower($rank), array_map('strtolower', (array) ($cfg['excluded_ranks'] ?? [])), true)) {
            return false;
        }

        foreach ((array) ($cfg['approver_ranks'] ?? []) as $allowed) {
            if (strcasecmp($rank, $allowed) === 0) {
                return true;
            }
        }

        foreach ((array) ($cfg['approver_rank_prefixes'] ?? []) as $prefix) {
            if (stripos($rank, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    // ── Request lifecycle ────────────────────────────────────────────

    /**
     * Open a request off a verified payment and alert the approvers.
     * Idempotent: a payment only ever opens one request.
     */
    public function openRequest(LegalSearchOnlinePayment $payment, array $context = []): LegalSearchOnlineRequest
    {
        // A payment may cover several files, each with its own request row, so
        // idempotency is per (payment, file) rather than per payment. Without the
        // file in this lookup a multi-file payment would open one request and
        // silently drop the rest.
        $fileNumber = trim((string) ($context['file_number'] ?? $payment->file_number));

        $existing = LegalSearchOnlineRequest::where('payment_id', $payment->id)
            ->where('file_number', $fileNumber)
            ->first();

        if ($existing) {
            return $existing;
        }

        $request = LegalSearchOnlineRequest::create([
            'payment_id'      => $payment->id,
            'reference'       => $payment->reference,
            'tracking_id'     => $payment->tracking_id,
            'requester_email' => $payment->email,
            'requester_name'  => $context['name'] ?? null,
            'requester_phone' => $context['phone'] ?? null,
            'file_number'     => $fileNumber,
            'search_params'   => $payment->search_params,
            'purpose_id'      => $context['purpose_id'] ?? null,
            'purpose'         => $context['purpose'] ?? null,
            'ip_address'      => $context['ip'] ?? null,
            'status'          => LegalSearchOnlineRequest::STATUS_PENDING,
            'submitted_at'    => now(),
        ]);

        $request->assignRequestNo();

        $this->notifyApprovers($request);
        $this->acknowledgeRequester($request);

        return $request;
    }

    /**
     * Set once the mail transport has proved unreachable during THIS request.
     *
     * Opening a request sends one mail per approver plus one to the requester -
     * seven sends in the current configuration. When the SMTP host is
     * unreachable each of those waits for the socket timeout, so the failures
     * multiply: seven dead sends is seven timeouts stacked inside a single web
     * request. The first connection-level failure tells us the rest will fail
     * the same way, so they are skipped and reported once.
     *
     * Deliberately per-instance rather than cached: a transport that is down now
     * may be up on the next request, and this must never suppress mail beyond
     * the request that observed the failure.
     */
    protected bool $mailTransportDown = false;

    /**
     * Send a mailable, giving up on the transport after the first failure that
     * looks like the host being unreachable.
     *
     * Returns true when the mail was handed to the transport. A false return is
     * never fatal to the caller - these are notifications, and the payment or
     * approval they accompany has already happened.
     */
    protected function sendMail(string $email, $mailable, array $logContext = []): bool
    {
        if ($this->mailTransportDown) {
            Log::warning('LegalSearchApprovalService: skipped mail, transport already failed this request', $logContext + [
                'email' => $email,
            ]);
            return false;
        }

        if (!$this->mailHostReachable()) {
            $this->mailTransportDown = true;
            Log::error('LegalSearchApprovalService: mail host unreachable on pre-flight, skipping all sends', $logContext + [
                'email' => $email,
            ]);
            return false;
        }

        try {
            Mail::to($email)->send($mailable);
            return true;
        } catch (\Throwable $e) {
            // A transport-level failure (refused, timed out, DNS) will repeat for
            // every remaining recipient. Anything else - a bad address, a broken
            // template - is specific to this one mail, so keep going.
            if ($this->isTransportFailure($e)) {
                $this->mailTransportDown = true;
                Log::error('LegalSearchApprovalService: mail transport unreachable, skipping remaining sends', $logContext + [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            } else {
                Log::warning('LegalSearchApprovalService: mail failed', $logContext + [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Cheap pre-flight: can we open a socket to the mail host at all?
     *
     * The mailer's own `timeout` bounds the SMTP CONVERSATION, not the TCP
     * connect underneath it. Against a blackholed port the OS connect alone
     * takes ~21s, so the first send blew the request budget even with a 5s
     * mailer timeout configured. A 2s probe answers the same question for a
     * hundredth of the cost.
     *
     * The verdict is cached briefly so a run of payments does not each pay the
     * probe, but only briefly - a mail host that comes back up must start
     * delivering again without a deploy.
     *
     * Fails OPEN for non-SMTP mailers (log, array, ses): there is no host to
     * probe and those transports do not block.
     */
    protected function mailHostReachable(): bool
    {
        $mailer = config('mail.default');
        if ($mailer !== 'smtp') {
            return true;
        }

        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        if ($host === '' || $port === 0) {
            return true;
        }

        $cacheKey = 'mail:smtp-reachable:' . $host . ':' . $port;

        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached !== null) {
                return (bool) $cached;
            }
        } catch (\Throwable $e) {
            // A broken cache store must not stop mail being attempted.
        }

        $errno = 0;
        $error = '';
        $socket = @fsockopen($host, $port, $errno, $error, 2);
        $reachable = $socket !== false;

        if ($socket) {
            fclose($socket);
        }

        try {
            // Remember "down" only briefly; remember "up" a little longer, since
            // a working host is the normal case and re-probing it is pure cost.
            \Illuminate\Support\Facades\Cache::put($cacheKey, $reachable, $reachable ? 300 : 60);
        } catch (\Throwable $e) {
            // Same as above - caching is an optimisation, not a requirement.
        }

        if (!$reachable) {
            Log::warning('LegalSearchApprovalService: SMTP pre-flight failed', [
                'host'  => $host,
                'port'  => $port,
                'errno' => $errno,
                'error' => $error,
            ]);
        }

        return $reachable;
    }

    /**
     * Whether this exception means the mail HOST is unreachable, as opposed to
     * something wrong with this particular message.
     */
    protected function isTransportFailure(\Throwable $e): bool
    {
        if ($e instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach ([
            'connection could not be established',
            'connection timed out',
            'connection refused',
            'could not connect',
            'timed out',
            'network is unreachable',
            'name or service not known',
            'ssl',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * In-app notification (bell) + email to every Director / Deputy Director.
     * Best-effort: a notification failure must never lose the request.
     */
    protected function notifyApprovers(LegalSearchOnlineRequest $request): void
    {
        $approvers = $this->approvers();

        foreach ($approvers as $approver) {
            try {
                $this->notifier->create(
                    (int) $approver->id,
                    'legal_search_online_request',
                    'Online Legal Search request awaiting approval',
                    sprintf(
                        '%s requested a Legal Search on file %s. Review and approve to release the report.',
                        $request->requester_email,
                        $request->file_number ?: '—'
                    ),
                    [
                        'request_id'  => $request->id,
                        'request_no'  => $request->request_no,
                        'file_number' => $request->file_number,
                        'url'         => route('legal-search-online.admin.requests', ['highlight' => $request->id]),
                    ],
                    ['module' => 'legal_search_online']
                );
            } catch (\Throwable $e) {
                Log::warning('LegalSearchApprovalService: in-app notification failed', [
                    'request_id' => $request->id,
                    'user_id'    => $approver->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $email = trim((string) $approver->email);
            if ($email === '') {
                continue;
            }

            $this->sendMail(
                $email,
                new LegalSearchRequestPendingApproval($request, $approver),
                ['request_id' => $request->id, 'stage' => 'approver_notification']
            );
        }

        if ($approvers->isEmpty()) {
            Log::warning('LegalSearchApprovalService: no approvers configured — request will sit unreviewed', [
                'request_id' => $request->id,
            ]);
        }
    }

    /**
     * Tell the requester their request is in the queue.
     */
    protected function acknowledgeRequester(LegalSearchOnlineRequest $request): void
    {
        $this->sendMail(
            (string) $request->requester_email,
            new LegalSearchRequestSubmitted($request),
            ['request_id' => $request->id, 'stage' => 'requester_acknowledgement']
        );
    }

    /**
     * Build the report payload for a request using the canonical print engine.
     */
    public function buildReport(LegalSearchOnlineRequest $request): ?array
    {
        $fileNumber = trim((string) $request->file_number);
        if ($fileNumber === '') {
            return null;
        }

        $built = $this->searchService->buildPrintReport(['file_number' => $fileNumber]);

        return $built['payload']['data'] ?? null;
    }

    /**
     * Render the report exactly as the requester receives it.
     *
     * Single source of truth for the emailed attachment and the approver's
     * preview, so what is reviewed is byte-for-byte what is delivered — with one
     * deliberate exception: $preview stamps a red PREVIEW line above the Ministry
     * name.
     *
     * $preview defaults to FALSE so the mark can only ever appear when a caller
     * explicitly asks for it. The delivered copy must never carry it, and a
     * default of true would put it on every attachment the moment someone added a
     * caller and forgot the argument.
     */
    public function renderPdf(LegalSearchOnlineRequest $request, array $report, bool $preview = false): \Barryvdh\DomPDF\PDF
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('online_legal_search.print.report_pdf', [
            'report'        => $report,
            'searchRequest' => $request,
            'isPreview'     => $preview,
            // Must be an inline data URI — DomPDF will not fetch over HTTP, so an
            // unreadable signature has to render as a blank line, never a broken one.
            'signature'     => \App\Support\SignatureImage::embeddable($request->reviewer_signature_path),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * The signing user's own signature, ready to preview in the approve dialog.
     *
     * @return array{has_signature: bool, data_uri: ?string}
     */
    public function signatureFor(?User $user): array
    {
        $officer = $this->signingOfficerFor($user);

        // Digital Signature Control (signing_officers) is the authority for a
        // staff signature; users.signature is only a legacy fallback. This is
        // the same precedence the Digital Signature Control listing uses.
        $path = trim((string) ($officer->signature_file ?? '')) ?: trim((string) ($user->signature ?? ''));

        // Deliberately the strict check: if the file cannot be read and inlined
        // it will not appear on the issued PDF either, so the approver must be
        // told it is unusable rather than shown a preview the report will not
        // reproduce.
        $dataUri = \App\Support\SignatureImage::embeddable($path);

        return [
            'has_signature' => $dataUri !== null,
            'data_uri'      => $dataUri,
            'path'          => $dataUri !== null ? $path : null,
            // Distinguishes "never uploaded one" from "uploaded, file missing".
            'unreadable'    => $path !== '' && $dataUri === null,
            'registered'    => $officer !== null,
            // Second-level auth required before the signature may be applied.
            'method'        => $officer->notification_type ?? null,
            'name'          => $officer->name ?? ($user->name ?? $user->username ?? null),
            'rank'          => ($user->rank ?? null) ?: ($officer->rank ?? null),
        ];
    }

    /**
     * The Digital Signature Control record for a staff user, if registered.
     */
    public function signingOfficerFor(?User $user): ?LandOfficer
    {
        if (!$user) {
            return null;
        }

        return LandOfficer::where('user_id', $user->id)->first();
    }

    /**
     * Session key holding the moment this user last cleared second-level auth.
     */
    protected function signatureSessionKey(User $user): string
    {
        return 'ols_signature_verified_at_' . $user->id;
    }

    /**
     * Record that the approver has just cleared their verification method.
     */
    public function markSignatureVerified(User $user): void
    {
        session([$this->signatureSessionKey($user) => now()->toIso8601String()]);
    }

    /**
     * Has this user cleared second-level auth recently enough to sign?
     *
     * The dialog can set the "apply signature" flag on its own, so the server
     * re-checks this before stamping a signature onto an issued report.
     */
    public function signatureVerified(User $user, int $withinMinutes = 15): bool
    {
        $at = session($this->signatureSessionKey($user));

        if (!$at) {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($at)->greaterThan(now()->subMinutes($withinMinutes));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear the verification, so each signature is separately authorised.
     */
    public function forgetSignatureVerification(User $user): void
    {
        session()->forget($this->signatureSessionKey($user));
    }

    /**
     * Invoice number for a request, e.g. ONLS-INV-20260814-0004. Mirrors the
     * PHS Portal's PHSP-INV-… scheme so both portals number alike.
     */
    public function invoiceNumber(LegalSearchOnlineRequest $request): string
    {
        $date = optional($request->submitted_at ?: $request->created_at)->format('Ymd') ?: now()->format('Ymd');

        return 'ONLS-INV-' . $date . '-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Render the payment invoice that accompanies an approved report.
     */
    public function renderInvoicePdf(LegalSearchOnlineRequest $request): \Barryvdh\DomPDF\PDF
    {
        $invoiceNumber = $this->invoiceNumber($request);
        $payment       = $request->payment;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('online_legal_search.print.invoice', [
            'searchRequest'  => $request,
            'payment'        => $payment,
            'invoice_number' => $invoiceNumber,
            'invoice_date'   => $payment->paid_at ?? $request->submitted_at ?? now(),
            // Scannable back to the payment, as the PHS invoice does.
            'qr_content'     => $payment->reference ?? $invoiceNumber,
        ]);
    }

    public function invoiceFileName(LegalSearchOnlineRequest $request): string
    {
        return $this->invoiceNumber($request) . '.pdf';
    }

    /**
     * File name used for the attachment and the preview tab.
     */
    public function pdfFileName(LegalSearchOnlineRequest $request): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($request->file_number ?: 'report'));
        $slug = trim((string) $slug, '-') ?: 'report';

        return 'Legal-Search-Report-' . $slug . '.pdf';
    }

    /**
     * Approve a request and email the report to the requester as a PDF.
     * Returns true when the email went out.
     *
     * @throws \RuntimeException when the report cannot be generated.
     */
    public function approve(LegalSearchOnlineRequest $request, User $approver, ?string $note = null, bool $sign = false): bool
    {
        $report = $this->buildReport($request);

        if (!$report) {
            throw new \RuntimeException(
                'The report for file ' . ($request->file_number ?: '—') . ' could not be generated, so nothing was sent. Nothing has been changed.'
            );
        }

        // Signing is opt-in: the approver presses "Sign" in the approve dialog
        // and clears their Digital Signature Control verification method.
        // Without it the report goes out with a blank signature line.
        $signaturePath = $sign ? (string) ($this->signatureFor($approver)['path'] ?? '') : '';

        $request->forceFill([
            'status'                  => LegalSearchOnlineRequest::STATUS_APPROVED,
            'reviewed_by'             => $approver->id,
            'reviewer_name'           => $approver->name ?: $approver->username,
            'reviewer_rank'           => $approver->rank,
            'reviewer_signature_path' => $signaturePath ?: null,
            'signed_at'               => $signaturePath !== '' ? now() : null,
            'reviewed_at'             => now(),
            'review_note'             => $note ?: null,
        ])->save();

        try {
            Mail::to($request->requester_email)->send(
                new LegalSearchRequestApproved($request, $report)
            );

            $request->forceFill(['emailed_at' => now(), 'email_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            // The approval stands; only delivery failed. Record why so staff can
            // retry from the queue rather than re-approving.
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            Log::error('LegalSearchApprovalService: report email failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Re-send the report for an already-approved request.
     */
    public function resend(LegalSearchOnlineRequest $request): bool
    {
        $report = $this->buildReport($request);

        if (!$report) {
            throw new \RuntimeException('The report could not be generated, so nothing was sent.');
        }

        try {
            Mail::to($request->requester_email)->send(
                new LegalSearchRequestApproved($request, $report)
            );

            $request->forceFill(['emailed_at' => now(), 'email_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            return false;
        }
    }

    /**
     * Reject a request and tell the requester why.
     */
    public function reject(LegalSearchOnlineRequest $request, User $approver, string $reason): void
    {
        $request->forceFill([
            'status'           => LegalSearchOnlineRequest::STATUS_REJECTED,
            'reviewed_by'      => $approver->id,
            'reviewer_name'    => $approver->name ?: $approver->username,
            'reviewer_rank'    => $approver->rank,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ])->save();

        try {
            Mail::to($request->requester_email)->send(new LegalSearchRequestRejected($request));
        } catch (\Throwable $e) {
            $request->forceFill(['email_error' => $e->getMessage()])->save();

            Log::warning('LegalSearchApprovalService: rejection email failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
