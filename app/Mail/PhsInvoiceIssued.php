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

class PhsInvoiceIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        $number = $this->request->invoice_number ? ' ' . $this->request->invoice_number : '';

        return new Envelope(
            subject: 'Your PHS Portal Invoice' . $number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_invoice_issued',
            with: ['request' => $this->request],
        );
    }

    /**
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
