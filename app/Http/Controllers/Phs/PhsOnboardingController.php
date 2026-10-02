<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsInvoiceIssued;
use App\Mail\PhsOnboardingRequestSubmitted;
use App\Mail\PhsPaymentLinkSent;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsLookup;
use App\Models\Phs\PhsOnboardingRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PhsOnboardingController extends Controller
{
    public function showForm(Request $request)
    {
        $package = $request->query('package');
        $packages = PhsTokenController::packages();
        $jobTitles = PhsLookup::options(PhsLookup::TYPE_JOB_TITLE);
        $departments = PhsLookup::options(PhsLookup::TYPE_DEPARTMENT);
        return view('phs.onboarding-request-form', compact('package', 'packages', 'jobTitles', 'departments'));
    }

    public function confirmPayment(Request $request)
    {
        // When a file exceeds the server's upload_max_filesize, PHP silently drops
        // it (UPLOAD_ERR_INI_SIZE) and Laravel's mimes rule then reports a misleading
        // "must be a file of type" error for a perfectly valid file. Catch that here
        // and tell the user the real reason.
        $tooLarge = [];
        foreach ($request->allFiles() as $file) {
            foreach (is_array($file) ? $file : [$file] as $f) {
                if ($f && $f->getError() === UPLOAD_ERR_INI_SIZE) {
                    $tooLarge[] = $f->getClientOriginalName();
                }
            }
        }
        if ($tooLarge) {
            $limit = ini_get('upload_max_filesize');
            return back()->withInput()->withErrors([
                'upload' => 'These file(s) exceed the server upload limit (' . $limit . ') and could not be received: '
                    . implode(', ', $tooLarge) . '. Please reduce the file size or ask the administrator to raise the limit.',
            ]);
        }

        // Job Title / Department are Select2 dropdowns backed by the phs_lookups
        // table. When the user picks "Other" they type a free value in the
        // companion *_other input, which overrides the dropdown selection. The
        // new value is remembered in submitRequest() once the form is actually
        // submitted, so the abandoned-at-payment case never pollutes the list.
        $request->merge([
            'job_title' => $request->input('job_title') === '__other__'
                ? trim((string) $request->input('job_title_other'))
                : $request->input('job_title'),
            'department' => $request->input('department') === '__other__'
                ? trim((string) $request->input('department_other'))
                : $request->input('department'),
        ]);

        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'in:bank,law_firm,corporate'],
            'contact_first_name' => ['required', 'string', 'max:255'],
            'contact_last_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_email_confirmation' => ['required', 'email', 'same:contact_email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'additional_notes' => ['nullable', 'string'],
            'cac_registration_number' => ['required', 'string', 'max:100'],
            'cac_document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'additional_documents' => ['required', 'array', 'min:1'],
            'additional_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'request_letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $validated['contact_name'] = trim(($validated['contact_first_name'] ?? '') . ' ' . ($validated['contact_last_name'] ?? ''));

        $validated['cac_document_path'] = $request->file('cac_document')->store('phs/cac-documents', 'public');
        $validated['request_letter_path'] = $request->file('request_letter')->store('phs/request-letters', 'public');

        $additional = [];
        foreach ((array) $request->file('additional_documents', []) as $file) {
            $additional[] = $file->store('phs/additional-documents', 'public');
        }
        $validated['additional_documents'] = $additional;

        $request->session()->put(
            'onboarding_data',
            array_diff_key($validated, ['contact_email_confirmation' => true, 'cac_document' => true, 'request_letter' => true])
        );

        return view('phs.onboarding-payment', compact('validated'));
    }

    public function submitRequest(Request $request)
    {
        $onboardingData = $request->session()->get('onboarding_data');

        if (!$onboardingData) {
            return redirect()->route('phs.request.form')
                ->withErrors(['error' => 'Session expired. Please start again.']);
        }

        $emailExists = \DB::connection('sqlsrv')->table('phs_institutions')
            ->where('email', $onboardingData['contact_email'])
            ->exists();

        if ($emailExists) {
            return back()->withErrors(['contact_email' => 'This email is already registered.']);
        }

        $emailExists = \DB::connection('sqlsrv')->table('phs_members')
            ->where('email', $onboardingData['contact_email'])
            ->exists();

        if ($emailExists) {
            return back()->withErrors(['contact_email' => 'This email is already registered.']);
        }

        $onboardingRequest = PhsOnboardingRequest::create([
            ...$onboardingData,
            'status' => PhsOnboardingRequest::STATUS_PENDING,
        ]);

        $request->session()->forget('onboarding_data');

        // Grow the shared lookup lists with any new "Other" values the user
        // supplied, so they appear in the dropdowns on future forms.
        PhsLookup::remember(PhsLookup::TYPE_JOB_TITLE, $onboardingRequest->job_title);
        PhsLookup::remember(PhsLookup::TYPE_DEPARTMENT, $onboardingRequest->department);

        $this->notifyAdminRequestSubmitted($onboardingRequest);

        return redirect()->route('phs.request.pending', ['id' => $onboardingRequest->id])
            ->with('success', 'Request submitted successfully! Your application is now under review.');
    }

    public function showPending($id)
    {
        $request = PhsOnboardingRequest::findOrFail($id);
        return view('phs.request-pending', compact('request'));
    }

    /**
     * Build, store, and stamp the e-invoice PDF for an onboarding request.
     * Idempotent: reuses the existing invoice number once generated.
     */
    public function generateInvoice(PhsOnboardingRequest $onboardingRequest): PhsOnboardingRequest
    {
        $packages = PhsTokenController::packages();
        $package = $packages[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;
        $amount = $onboardingRequest->payment_amount ?? ($package['price'] ?? 0);

        $invoiceNumber = $onboardingRequest->invoice_number ?: $onboardingRequest->generateInvoiceNumber();

        // QR code encoding the organization reference id (payment reference,
        // falling back to the invoice number) so the invoice can be scanned/verified.
        $qrContent = $onboardingRequest->payment_reference ?: $invoiceNumber;
        $qrCode = $this->buildQrDataUri($qrContent);

        $pdf = Pdf::loadView('phs.invoice-template', [
            'request' => $onboardingRequest,
            'package' => $package,
            'amount' => (float) $amount,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now(),
            'qr_code' => $qrCode,
            'qr_content' => $qrContent,
        ]);

        $path = 'phs/invoices/' . $invoiceNumber . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $onboardingRequest->update([
            'invoice_number' => $invoiceNumber,
            'invoice_pdf_path' => $path,
            'invoice_generated_at' => now(),
        ]);

        return $onboardingRequest;
    }

    /**
     * Render a QR code for the given content as an SVG data URI (embeddable in
     * the DomPDF invoice). Returns null if QR generation fails for any reason.
     */
    private function buildQrDataUri(string $content): ?string
    {
        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(140, 1),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $svg = (new \BaconQrCode\Writer($renderer))->writeString($content);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Download the invoice for a just-submitted onboarding request (pending page). */
    public function downloadInvoice($id)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        if (!$onboardingRequest->invoice_pdf_path) {
            $this->generateInvoice($onboardingRequest);
            $onboardingRequest->refresh();
        }

        return $this->streamInvoice($onboardingRequest);
    }

    /** Download the invoice for the signed-in organization. */
    public function downloadOrgInvoice()
    {
        $institution = Auth::guard('phs')->user()->institution;

        $onboardingRequest = PhsOnboardingRequest::where('created_phs_institution_id', $institution->id)
            ->orderByDesc('id')
            ->first();

        if (!$onboardingRequest) {
            abort(404, 'No invoice is available for your organization.');
        }

        if (!$onboardingRequest->invoice_pdf_path) {
            $this->generateInvoice($onboardingRequest);
            $onboardingRequest->refresh();
        }

        return $this->streamInvoice($onboardingRequest);
    }

    /** Serve the LSA PDF for download (secured by per-request token in URL). */
    public function downloadLsa($id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->lsa_token !== $token, 403);

        $packages = PhsTokenController::packages();

        // Persist the package the org picked on the SLA page (if provided & valid)
        // so the SLA reflects it and it carries through to the final payment step.
        $picked = request('package');
        if ($picked && isset($packages[strtolower((string) $picked)]) && $onboardingRequest->initial_token_package !== $picked) {
            $onboardingRequest->update(['initial_token_package' => $picked]);
        }

        $package = $packages[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;

        $pdf = Pdf::loadView('phs.lsa-template', [
            'onboardingRequest' => $onboardingRequest,
            'package' => $package,
            'date' => now()->format('F j, Y'),
        ]);

        return $pdf->download('PHSP-SLA-' . $onboardingRequest->id . '.pdf');
    }

    /** Show the public LSA upload page (with package selection). */
    public function showLsaUpload($id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->lsa_token !== $token, 403);

        $packages = PhsTokenController::packages();

        return view('phs.lsa-upload', compact('onboardingRequest', 'token', 'packages'));
    }

    /** Handle signed SLA upload from the organization. */
    public function uploadLsa(Request $request, $id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->lsa_token !== $token, 403);

        // The org chooses a subscription package on this page; it is required and
        // is the source of truth for the SLA and the later payment step.
        $packages = PhsTokenController::packages();
        $picked = $request->input('package');
        if (!$picked || !isset($packages[strtolower((string) $picked)])) {
            return back()->withErrors(['package' => 'Please select a subscription package before uploading your signed SLA.']);
        }

        $request->validate(
            ['signed_lsa' => ['required', 'file', 'max:10240']],
            [],
            ['signed_lsa' => 'SLA document']
        );

        $file = $request->file('signed_lsa');
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return back()->withErrors(['signed_lsa' => 'The SLA document must be a PDF file.']);
        }

        $path = $request->file('signed_lsa')->store('phs/lsa-signed', 'public');

        $onboardingRequest->update([
            'initial_token_package' => $picked,
            'lsa_signed_document_path' => $path,
            'lsa_signed_at' => now(),
            'status' => PhsOnboardingRequest::STATUS_SLA_UPLOADED,
        ]);

        return redirect()->route('phs.lsa.upload.form', [$id, $token])
            ->with('success', 'Signed SLA uploaded successfully. Thank you!');
    }

    /** Show the package selection / payment page (token-secured). */
    public function showPaymentPage($id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->payment_token !== $token, 403);
        abort_if($onboardingRequest->status !== PhsOnboardingRequest::STATUS_PAYMENT_PENDING, 403);

        $packages = PhsTokenController::packages();

        return view('phs.payment-form', compact('onboardingRequest', 'token', 'packages'));
    }

    /** Initiate a Paystack transaction for the selected package. */
    public function initiatePaystackPayment(Request $request, $id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->payment_token !== $token, 403);
        abort_if($onboardingRequest->status !== PhsOnboardingRequest::STATUS_PAYMENT_PENDING, 403);

        $packages = PhsTokenController::packages();
        $packageNames = array_column($packages, 'name');

        $request->validate(['package' => ['required', 'string', Rule::in($packageNames)]]);

        $packageKey = strtolower($request->input('package'));
        $package = $packages[$packageKey] ?? null;

        if (!$package) {
            return back()->withErrors(['package' => 'Invalid package selected.']);
        }

        $reference = 'PHSP-' . strtoupper(Str::random(12));
        $amountKobo = (int) round($package['price'] * 100);

        $onboardingRequest->update([
            'paystack_reference' => $reference,
            'initial_token_package' => $request->input('package'),
        ]);

        $response = Http::withToken(config('services.paystack.secret'))
            ->post(config('services.paystack.base_url') . '/transaction/initialize', [
                'email' => $onboardingRequest->contact_email,
                'amount' => $amountKobo,
                'reference' => $reference,
                'callback_url' => route('phs.payment.callback', [$id, $token]),
                'metadata' => [
                    'onboarding_request_id' => $onboardingRequest->id,
                    'organization' => $onboardingRequest->organization_name,
                ],
            ]);

        if (!$response->successful() || !($response->json('data.authorization_url'))) {
            return back()->withErrors(['payment' => 'Could not initialize payment. Please try again.']);
        }

        return redirect($response->json('data.authorization_url'));
    }

    /** Handle Paystack callback after payment. */
    public function handlePaystackCallback($id, $token)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);
        abort_if($onboardingRequest->payment_token !== $token, 403);

        $reference = request('reference') ?: $onboardingRequest->paystack_reference;

        if (!$reference) {
            return redirect()->route('phs.payment.form', [$id, $token])
                ->withErrors(['payment' => 'No payment reference found. Please try again.']);
        }

        $verify = Http::withToken(config('services.paystack.secret'))
            ->get(config('services.paystack.base_url') . '/transaction/verify/' . $reference);

        if (!$verify->successful() || $verify->json('data.status') !== 'success') {
            return redirect()->route('phs.payment.form', [$id, $token])
                ->withErrors(['payment' => 'Payment could not be verified. Please contact support if you were charged.']);
        }

        $paystackAmount = round($verify->json('data.amount') / 100, 2);

        $packages = PhsTokenController::packages();
        $package = $packages[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;
        $packagePrice = $package['price'] ?? $paystackAmount;

        $onboardingRequest->update([
            'status' => PhsOnboardingRequest::STATUS_APPROVED,
            'payment_amount' => $packagePrice,
            'paystack_amount' => $paystackAmount,
            'payment_status' => PhsOnboardingRequest::PAYMENT_COMPLETED,
            'payment_received_at' => now(),
        ]);

        // Mint the registration link so the org can complete sign-up after payment.
        $onboardingRequest->generateActivationToken();

        $this->generateInvoice($onboardingRequest->fresh());

        try {
            $onboardingRequest->refresh();
            Mail::to($onboardingRequest->contact_email)
                ->send(new PhsInvoiceIssued($onboardingRequest));
            $onboardingRequest->update(['invoice_sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }

        $username = PhsInstitution::suggestUsername($onboardingRequest->organization_name);

        return redirect()->to(
            route('phs.register.token', $onboardingRequest->activation_token) . '?username=' . $username
        );
    }

    private function streamInvoice(PhsOnboardingRequest $onboardingRequest)
    {
        $filename = 'Invoice-' . ($onboardingRequest->invoice_number ?: $onboardingRequest->id) . '.pdf';

        if ($onboardingRequest->invoice_pdf_path && Storage::disk('public')->exists($onboardingRequest->invoice_pdf_path)) {
            return Storage::disk('public')->download($onboardingRequest->invoice_pdf_path, $filename);
        }

        abort(404, 'Invoice file not found.');
    }

    private function notifyAdminRequestSubmitted(PhsOnboardingRequest $request)
    {
        $adminEmail = config('mail.admin_email') ?? config('mail.from.address');

        // Support a comma-separated list of admin recipients.
        $recipients = collect(explode(',', (string) $adminEmail))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();

        Mail::to($recipients)->send(new PhsOnboardingRequestSubmitted($request));
    }
}
