<?php

namespace App\Mail;

use App\Models\Phs\PhsFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhsFeedbackSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  PhsFeedback  $feedback  The submitted complaint.
     * @param  bool  $forAdmin  True for the Ministry/KLAES admin copy, false for
     *                          the organization's confirmation copy.
     */
    public function __construct(public PhsFeedback $feedback, public bool $forAdmin = true)
    {
    }

    public function envelope(): Envelope
    {
        $org = optional($this->feedback->institution)->name ?: 'an organization';
        $subject = $this->forAdmin
            ? "New PHS Feedback ({$this->feedback->categoryLabel()}) from {$org}"
            : "We've received your PHS feedback — {$this->feedback->categoryLabel()}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $adminDashboardUrl = null;
        try {
            $adminDashboardUrl = \Illuminate\Support\Facades\Route::has('system-admin.phs.feedback')
                ? route('system-admin.phs.feedback')
                : null;
        } catch (\Throwable $e) {
            $adminDashboardUrl = null;
        }
        if (!$adminDashboardUrl) {
            $adminDashboardUrl = config('app.admin_dashboard_url', url('/system-admin/phs/feedback'));
        }

        return new Content(
            view: 'email.phs_feedback_submitted',
            with: [
                'feedback' => $this->feedback,
                'forAdmin' => $this->forAdmin,
                'adminDashboardUrl' => $adminDashboardUrl,
            ],
        );
    }
}
