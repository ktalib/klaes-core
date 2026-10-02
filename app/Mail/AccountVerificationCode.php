<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The sign-in verification code, by email — the gate's default route.
 *
 * NEVER `implements ShouldQueue`, and that is not a style choice.
 * QUEUE_CONNECTION is `database` on this deployment and nothing guarantees a
 * worker is running against it. A queued verification code sits in the jobs
 * table while the member of staff stares at a card that says one is on its way,
 * and the account it is holding cannot do anything else. This one goes out on
 * the request, or fails loudly enough for PhoneOtpService to tell the user so.
 *
 * The Queueable trait is kept only because every other mailable here has it and
 * it costs nothing on its own — it is ShouldQueue that decides.
 *
 * The code is passed as a plain string rather than a User: SerializesModels
 * would reload the row, and this message is about the value that was just
 * generated, not about whatever is on the account by the time it is rendered.
 */
class AccountVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $recipientName,
        public int $expiresInMinutes,
        public ?string $requestedFrom = null,
        // Added last, and nullable, so every existing caller keeps the staff
        // subject line. The LAAS Portal passes its own: an applicant has no
        // KLAES account and would not recognise a subject that names one.
        //
        // NOT named $subject. Illuminate\Mail\Mailable already declares a
        // public UNTYPED $subject, and PHP refuses to let a subclass redeclare
        // an untyped parent property with a type — the class then fatals on
        // load, which is a blank 500 on the sign-in screen rather than anything
        // that points at this line.
        public ?string $subjectLine = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine
                ?: (string) config('phone_verification.email_subject', 'Your KLAES verification code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.account_verification_code',
            with: [
                'code' => $this->code,
                'recipientName' => $this->recipientName,
                'expiresInMinutes' => $this->expiresInMinutes,
                'requestedFrom' => $this->requestedFrom,
            ],
        );
    }
}
