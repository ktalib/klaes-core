<?php

namespace App\Mail;

use App\Models\Phs\PhsTokenTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsTopupConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PhsTokenTransaction $transaction, public int $newBalance)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'PHS Portal Token Top-up Confirmed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.phs_topup_confirmed',
            with: [
                'txn' => $this->transaction,
                'institution' => $this->transaction->institution,
                'newBalance' => $this->newBalance,
            ],
        );
    }
}
