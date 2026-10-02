<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsOnboardingRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New PHS Portal Onboarding Request: {$this->request->organization_name}",
        );
    }

    public function content(): Content
    {
        $adminDashboardUrl = null;
        try {
            $adminDashboardUrl = \Illuminate\Support\Facades\Route::has('admin.phs.requests.index')
                ? route('admin.phs.requests.index')
                : null;
        } catch (\Throwable $e) {
            $adminDashboardUrl = null;
        }

        if (!$adminDashboardUrl) {
            $adminDashboardUrl = config('app.admin_dashboard_url', url('/admin/phs/requests'));
        }

        return new Content(
            view: 'email.phs_onboarding_request',
            with: [
                'request' => $this->request,
                'adminDashboardUrl' => $adminDashboardUrl,
            ],
        );
    }

    /**
     * Attach the generated e-invoice PDF, if one exists.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->request->invoice_pdf_path
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->request->invoice_pdf_path)) {
            return [
                Attachment::fromStorageDisk('public', $this->request->invoice_pdf_path)
                    ->as('Invoice-' . $this->request->invoice_number . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
