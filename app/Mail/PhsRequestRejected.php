<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsRequestRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PHS Portal Onboarding Request Status Update',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_request_rejected',
            with: [
                'request' => $this->request,
                'resubmitUrl' => route('phs.request.form'),
            ],
        );
    }
}
