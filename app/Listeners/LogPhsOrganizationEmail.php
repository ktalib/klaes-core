<?php

namespace App\Listeners;

use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

class LogPhsOrganizationEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $recipients = collect($message->getTo() ?? [])
            ->map(fn ($address) => strtolower(trim((string) $address->getAddress())))
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $recipientEmails = $recipients->all();

        $institutionsByEmail = DB::connection('sqlsrv')
            ->table('phs_institutions')
            ->select('id', 'email')
            ->whereNotNull('email')
            ->whereIn(DB::raw('LOWER(email)'), $recipientEmails)
            ->get()
            ->mapWithKeys(fn (object $row) => [strtolower((string) $row->email) => (int) $row->id]);

        $memberInstitutionsByEmail = DB::connection('sqlsrv')
            ->table('phs_members')
            ->select('phs_institution_id', 'email')
            ->whereNotNull('email')
            ->whereIn(DB::raw('LOWER(email)'), $recipientEmails)
            ->get()
            ->mapWithKeys(fn (object $row) => [strtolower((string) $row->email) => (int) $row->phs_institution_id]);

        $onboardingByEmail = PhsOnboardingRequest::query()
            ->select('id', 'contact_email')
            ->whereNotNull('contact_email')
            ->whereIn(DB::raw('LOWER(contact_email)'), $recipientEmails)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (PhsOnboardingRequest $row) => [strtolower((string) $row->contact_email) => (int) $row->id]);

        $subject = $message->getSubject();
        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
        $messageId = $message->getHeaders()->has('Message-ID')
            ? trim((string) $message->getHeaders()->get('Message-ID')->getBodyAsString(), '<>')
            : null;

        $mailable = null;
        if (isset($event->data['__laravel_notification'])) {
            $mailable = get_class($event->data['__laravel_notification']);
        } elseif (isset($event->data['__laravel_mailable'])) {
            $mailable = get_class($event->data['__laravel_mailable']);
        }

        $rows = [];
        $sentAt = now();
        $meta = [
            'to' => $recipientEmails,
            'cc' => collect($message->getCc() ?? [])->map(fn ($a) => (string) $a->getAddress())->values()->all(),
            'bcc' => collect($message->getBcc() ?? [])->map(fn ($a) => (string) $a->getAddress())->values()->all(),
            'headers' => [
                'x-mailer' => $message->getHeaders()->has('X-Mailer')
                    ? $message->getHeaders()->get('X-Mailer')->getBodyAsString()
                    : null,
            ],
        ];

        foreach ($recipientEmails as $email) {
            $institutionId = $institutionsByEmail->get($email) ?? $memberInstitutionsByEmail->get($email);
            $onboardingId = $onboardingByEmail->get($email);

            if (!$institutionId && !$onboardingId) {
                continue;
            }

            $rows[] = [
                'phs_institution_id' => $institutionId,
                'phs_onboarding_request_id' => $onboardingId,
                'recipient_email' => $email,
                'subject' => $subject,
                'body_html' => $htmlBody,
                'body_text' => $textBody,
                'message_id' => $messageId,
                'mailable' => $mailable,
                'mailer' => method_exists($message, 'getHeaders') && $message->getHeaders()->has('X-Mailer')
                    ? $message->getHeaders()->get('X-Mailer')->getBodyAsString()
                    : null,
                'meta' => json_encode($meta),
                'sent_at' => $sentAt,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ];
        }

        if (!empty($rows)) {
            DB::connection('sqlsrv')->table('phs_email_histories')->insert($rows);
        }
    }
}