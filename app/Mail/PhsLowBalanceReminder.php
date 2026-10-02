<?php

namespace App\Mail;

use App\Models\Phs\PhsInstitution;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsLowBalanceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsInstitution $institution, public int $threshold)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'PHS Portal Token Balance Running Low');
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_low_balance',
            with: [
                'institution' => $this->institution,
                'balance' => (int) $this->institution->token_balance,
                'threshold' => $this->threshold,
            ],
        );
    }
}
