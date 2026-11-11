<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An approved company's MLPP-SG portal login (user, 2026-11-10), sent once. Never ShouldQueue: the temporary
 * password must not be written to the jobs table, and no queue worker is guaranteed (see AccountVerificationCode).
 */
class SurveyCompanyLoginDetails extends Mailable
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $username,
        public string $password,
        public string $loginUrl,
        // A surveyor's login (added by the SG, 2026-11-10): their Surveyor ID; null for a company.
        public ?string $surveyorId = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->surveyorId ? 'Your MLPP-SG Portal surveyor login' : 'Your MLPP-SG Portal company login');
    }

    public function content(): Content
    {
        return new Content(view: 'email.survey_company_login_details', with: [
            'companyName' => $this->companyName,
            'username' => $this->username,
            'password' => $this->password,
            'loginUrl' => $this->loginUrl,
            'surveyorId' => $this->surveyorId,
        ]);
    }
}
