<?php

namespace App\Mail;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsPaymentLinkSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsOnboardingRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PHS Portal — Your Onboarding Link to Complete Registration',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_payment_link',
            with: [
                'request' => $this->request,
                'paymentUrl' => route('phs.payment.form', [$this->request->id, $this->request->payment_token]),
                'suggestedUsername' => \App\Models\Phs\PhsInstitution::suggestUsername($this->request->organization_name),
            ],
        );
    }
}
