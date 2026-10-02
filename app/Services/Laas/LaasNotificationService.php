<?php

namespace App\Services\Laas;

use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Models\Laas\LaasStageNotification;
use App\Services\BulkSmsNgService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Everything the applicant is told, and everything the office is told.
 *
 * The timeline entry and the SMS are written in ONE call so the two can never
 * drift: a message the gateway refused still appears on the applicant's status
 * page, carrying its failure state, rather than silently vanishing.
 *
 * Messages go out through Bulk-SMS.ng, not BetaSMS: production cannot open
 * outbound connections on plain HTTP :80, the only port BetaSMS serves its API
 * on, so notices failed there while sending fine on a developer machine.
 *
 * Each stage still carries a plainer fallback wording. It costs nothing, and it
 * is what got the phone-change code through when BetaSMS's content filter
 * refused the first attempt — a gateway rejecting a message for how it is
 * phrased is not hypothetical. Avoid the vocabulary of loan and prize spam
 * ("approved", "assigned", "quote", "notice") in anything you add.
 */
class LaasNotificationService
{
    public function __construct(private BulkSmsNgService $sms)
    {
    }

    /**
     * The applicant-facing text for a stage, or null when the stage is an
     * internal step the applicant should not be texted about.
     */
    public function messageFor(string $stage, LaasApplication $application): ?string
    {
        $ref    = $application->reference_no;
        $fileNo = $application->file_number;

        switch ($stage) {
            case LaasApplication::STAGE_SUBMITTED:
                return "KLAES LAAS: your land allocation application {$ref} has been received "
                     . 'and processing has started. You will be updated at each stage.';

            case LaasApplication::STAGE_DIRECTOR_APPROVED:
                return "KLAES LAAS: your application {$ref} has been approved by the Director. "
                     . 'Your file number will be assigned shortly.';

            case LaasApplication::STAGE_FILENO_ASSIGNED:
                return "KLAES LAAS: your application {$ref} has been assigned File Number "
                     . "{$fileNo}. Please quote this number in all correspondence.";

            case LaasApplication::STAGE_LAND12_COMPLETED:
                return "KLAES LAAS: the survey report for File Number {$fileNo} ({$ref}) has been "
                     . 'completed by Cadastral. Your recommendation is being prepared.';

            case LaasApplication::STAGE_RECOMMENDATION_APPROVED:
                return "KLAES LAAS: the recommendation for File Number {$fileNo} ({$ref}) has been "
                     . 'approved. Your Right of Occupancy is being prepared.';

            case LaasApplication::STAGE_ROFO_SIGNED:
                return "KLAES LAAS: your Right of Occupancy for File Number {$fileNo} ({$ref}) has "
                     . 'been signed by the Director of Lands and is ready for collection.';

            case LaasApplication::STAGE_REJECTED:
                $reason = trim((string) $application->rejection_reason);

                return "KLAES LAAS: your application {$ref} was not approved."
                     . ($reason !== '' ? " Reason: {$reason}." : '')
                     . ' Please contact the Lands office for guidance.';
        }

        // land12_raised, at_cadastral, recommendation_pending, rofo_generated:
        // real progress, shown on the timeline, but not worth a text each.
        return null;
    }

    /**
     * A plainer wording for the same stage, used when the gateway rejects the
     * first one.
     *
     * Observed on BetaSMS before the switch: "your application ... has been
     * received and processing has started" got through, while "has been
     * approved by the Director" and "has been assigned File Number ... quote
     * this number" were both refused. These fall back to the barest statement
     * of fact, keeping the one detail worth having (the file number) and
     * pointing at the portal for the rest.
     *
     * Verify with: php artisan laas:sms-probe <number>
     */
    public function fallbackMessageFor(string $stage, LaasApplication $application): ?string
    {
        $ref    = $application->reference_no;
        $fileNo = $application->file_number;

        switch ($stage) {
            case LaasApplication::STAGE_FILENO_ASSIGNED:
                // Worth delivering on its own — this is the number they will be
                // asked for at every counter from here on.
                return "KLAES LAAS: {$fileNo} is the file number for your application {$ref}.";

            case LaasApplication::STAGE_SUBMITTED:
            case LaasApplication::STAGE_DIRECTOR_APPROVED:
            case LaasApplication::STAGE_LAND12_COMPLETED:
            case LaasApplication::STAGE_RECOMMENDATION_APPROVED:
            case LaasApplication::STAGE_ROFO_SIGNED:
            case LaasApplication::STAGE_REJECTED:
                return "KLAES LAAS: there is an update on your application {$ref}. Please sign in to the portal to see it.";
        }

        return null;
    }

    /**
     * Record a stage on the applicant's timeline and text them if the stage
     * warrants it.
     *
     * @param  array{title?:string,body?:string,sms?:string|false,actor_type?:string,actor_id?:int|null,actor_name?:string|null,visible?:bool}  $meta
     */
    public function record(LaasApplication $application, string $stage, array $meta = []): LaasApplicationEvent
    {
        $title = $meta['title'] ?? LaasApplication::label($stage);
        $body  = $meta['body']  ?? null;

        // sms => false suppresses the text for this one call (bulk backfills,
        // corrections); omitted means "use the template for this stage".
        $smsBody = array_key_exists('sms', $meta)
            ? ($meta['sms'] === false ? null : (string) $meta['sms'])
            : $this->messageFor($stage, $application);

        $event = new LaasApplicationEvent([
            'laas_application_id'  => $application->id,
            'stage'                => $stage,
            'title'                => $title,
            'body'                 => $body,
            'actor_type'           => $meta['actor_type'] ?? 'system',
            'actor_id'             => $meta['actor_id']   ?? Auth::id(),
            'actor_name'           => $meta['actor_name'] ?? (Auth::user()->name ?? null),
            'visible_to_applicant' => $meta['visible']    ?? true,
        ]);

        if ($smsBody !== null && $smsBody !== '') {
            $phone = trim((string) $application->applicant_phone);

            if ($phone === '') {
                $event->sms_status = LaasApplicationEvent::SMS_SKIPPED;
                $event->sms_body   = $smsBody;
            } else {
                // A caller-supplied wording stands alone; the stage templates get
                // their plainer twin appended, for the gateway's content filter.
                $candidates = [$smsBody];

                if (!array_key_exists('sms', $meta)) {
                    $candidates[] = $this->fallbackMessageFor($stage, $application);
                }

                $delivered = $this->sendQuietly($phone, $candidates, $application, $stage);

                $event->sms_to      = $phone;
                // Record what actually went out, not what we first tried — the
                // timeline is the applicant's evidence of what they were told.
                $event->sms_body    = $delivered ?? $smsBody;
                $event->sms_status  = $delivered ? LaasApplicationEvent::SMS_SENT : LaasApplicationEvent::SMS_FAILED;
                $event->sms_sent_at = $delivered ? now() : null;
            }
        }

        $event->save();

        return $event;
    }

    /**
     * Raise an internal desk alert for a staff unit — spec step (h).
     */
    public function alertDepartment(
        LaasApplication $application,
        string $department,
        string $stage,
        string $title,
        ?string $message = null
    ): LaasStageNotification {
        return LaasStageNotification::create([
            'laas_application_id' => $application->id,
            'department'          => $department,
            'stage'               => $stage,
            'title'               => $title,
            'message'             => $message,
        ]);
    }

    /**
     * The gateway must never be able to break the workflow. A failed or
     * throwing send is logged and reported as a failed event; the stage change
     * that triggered it still stands.
     *
     * @param  array<int,string|null>  $candidates  Best wording first.
     * @return string|null  The wording that was delivered, or null.
     */
    private function sendQuietly(string $phone, array $candidates, LaasApplication $application, string $stage): ?string
    {
        try {
            return $this->sms->sendFirstAccepted($phone, $candidates);
        } catch (\Throwable $e) {
            Log::error('LAAS: SMS send threw', [
                'reference_no' => $application->reference_no,
                'stage'        => $stage,
                'error'        => $e->getMessage(),
            ]);

            return null;
        }
    }
}
