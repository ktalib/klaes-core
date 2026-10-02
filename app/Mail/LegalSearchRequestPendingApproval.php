<?php

namespace App\Mail;

use App\Models\LegalSearchOnlineRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alert to a Director / Deputy Director that an Online Legal Search request
 * is waiting on their approval.
 */
class LegalSearchRequestPendingApproval extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalSearchOnlineRequest $searchRequest,
        public User $approver,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required — Online Legal Search Request ' . ($this->searchRequest->request_no ?: ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.legal_search_request_pending_approval',
            with: [
                'searchRequest' => $this->searchRequest,
                'approver' => $this->approver,
                'queueUrl' => route('legal-search-online.admin.requests', ['highlight' => $this->searchRequest->id]),
            ],
        );
    }
}
