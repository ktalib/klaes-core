<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PhsPaymentConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->request->payment_status) {
            PhsOnboardingRequest::PAYMENT_INCOMPLETE => 'PHS Portal Payment Received — Balance Outstanding',
            PhsOnboardingRequest::PAYMENT_OVERPAID => 'PHS Portal Payment Received — Confirmed',
            PhsOnboardingRequest::PAYMENT_NOT_PAID => 'PHS Portal Payment Outstanding',
            default => 'PHS Portal Payment Confirmed',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_payment_confirmed',
            with: ['request' => $this->request],
        );
    }

    /**
     * Attach the invoice (now reflecting the verified payment) when available.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->request->invoice_pdf_path
            && Storage::disk('public')->exists($this->request->invoice_pdf_path)) {
            return [
                Attachment::fromStorageDisk('public', $this->request->invoice_pdf_path)
                    ->as('Invoice-' . $this->request->invoice_number . '.pdf')
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
