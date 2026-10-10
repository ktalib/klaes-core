<?php

namespace App\Services;

use App\Models\SpaApplication;
use App\Models\SpaNotice;
use Illuminate\Support\Facades\Log;

/**
 * SPAS contravention notices: the First Serve, served the moment a property is
 * found in contravention, and the SMS that carries each notice.
 *
 * The wording is the Ministry's and lives on SpaNotice (FIRST_SERVE_SMS /
 * SECOND_SERVE_SMS), so the manual screen, this automatic path and the
 * scheduled second-serve command (spa:trigger-second-service) always send the
 * same text, under the KANOMLPP sender (config services.bulk_sms_ng.sender).
 */
class SpaNoticeService
{
    public function __construct(private ?SpaBillingService $billing = null)
    {
        $this->billing ??= new SpaBillingService();
    }

    /**
     * Serve the First Serve for a record found in contravention.
     *
     * Once per application: an existing first serve is returned untouched. The
     * notice is recorded as SERVED either way; the SMS goes out only when the
     * record has a phone number the gateway can reach. A notice whose SMS did
     * not go is never escalated to a second serve (that command requires a
     * delivered first serve), and its row offers a re-send.
     *
     * Never throws: a failure here must not lose the land record being saved.
     */
    public function serveFirstForContravention(SpaApplication $app, ?string $actor = null, ?int $actorId = null): ?SpaNotice
    {
        try {
            if (! $this->billing->contravenes($app)) {
                return null;
            }

            $existing = SpaNotice::where('notice_type', 'first')
                ->where(fn ($q) => $q->where('spa_application_id', $app->id)
                    ->when($app->file_number, fn ($qq) => $qq->orWhere('file_number', $app->file_number)))
                ->first();

            if ($existing) {
                return $existing;
            }

            $notice = SpaNotice::create([
                'spa_application_id' => $app->id,
                'file_number'        => $app->file_number,
                'notice_type'        => 'first',
                'recipient_name'     => $app->owner_name,
                'phone'              => $app->phone,
                'served_by'          => $actorId,
                'served_date'        => now()->toDateString(),
                'status'             => 'served',
                'created_by'         => $actor ?: 'system:spa-auto',
            ]);

            $sms = $this->canText($app->phone)
                ? $this->sendSms((string) $app->phone, (string) $app->owner_name, 'first', $app->file_number)
                : ['sent' => false, 'code' => null, 'reason' => 'no usable phone number'];

            $sentAt = $sms['sent'] ? now() : null;
            $notice->update([
                'sms_sent'       => $sms['sent'],
                'sms_sent_at'    => $sentAt,
                // The two weeks run from delivery, as on the manual screen.
                'scheduled_date' => $sentAt ? $sentAt->copy()->addDays(SpaNotice::SECOND_SERVE_AFTER_DAYS)->toDateString() : null,
            ]);

            Log::info('SPAS first serve served automatically', [
                'application' => $app->id,
                'file_no'     => $app->file_number,
                'sms_sent'    => $sms['sent'],
                'reason'      => $sms['reason'],
            ]);

            return $notice;
        } catch (\Throwable $e) {
            Log::warning('SPAS automatic first serve failed', ['application' => $app->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Whether the gateway can deliver to this number at all. */
    public function canText(?string $phone): bool
    {
        return $phone !== null && BulkSmsNgService::normalizeNumber($phone) !== null;
    }

    /**
     * Send a contravention notice by SMS.
     *
     * @return array{sent:bool, code:?string, reason:?string} the result plus why it
     *         failed, so a caller can say what happened rather than "could not send"
     */
    public function sendSms(string $phone, string $name, string $type, ?string $fileNo): array
    {
        // Per call: lastStatusCode()/lastFailureReason() describe the most recent
        // send on an instance, so a shared one would report stale state.
        $sms = new BulkSmsNgService();

        try {
            $sent = $sms->send($phone, SpaNotice::smsBody($type));

            if (! $sent) {
                Log::warning('SPAS: notice SMS not sent', [
                    'phone' => $phone, 'file_no' => $fileNo, 'recipient' => $name, 'type' => $type,
                    'code' => $sms->lastStatusCode(), 'reason' => $sms->lastFailureReason(),
                ]);
            }

            return [
                'sent'   => $sent,
                'code'   => $sms->lastStatusCode(),
                'reason' => $sent ? null : ($sms->lastFailureReason() ?? 'unknown gateway failure'),
            ];
        } catch (\Throwable $e) {
            Log::warning('SPAS: SMS send failed', [
                'phone' => $phone, 'file_no' => $fileNo, 'recipient' => $name, 'type' => $type, 'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'code' => null, 'reason' => 'exception: ' . $e->getMessage()];
        }
    }
}
