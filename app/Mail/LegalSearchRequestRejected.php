<?php

namespace App\Mail;

use App\Models\LegalSearchOnlineRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the requester their Online Legal Search request was declined, and why.
 */
class LegalSearchRequestRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LegalSearchOnlineRequest $searchRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Legal Search Request Declined — ' . ($this->searchRequest->request_no ?: 'Online Legal Search'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.legal_search_request_rejected',
            with: ['searchRequest' => $this->searchRequest],
        );
    }
}
