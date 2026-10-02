<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsSlaRequestLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PHS Portal — Action Required: Select Your Package & Sign Your SLA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_sla_request_link',
            with: [
                'request' => $this->request,
                'uploadUrl' => route('phs.lsa.upload.form', [$this->request->id, $this->request->lsa_token]),
                'downloadUrl' => route('phs.lsa.download', [$this->request->id, $this->request->lsa_token]),
            ],
        );
    }
}
