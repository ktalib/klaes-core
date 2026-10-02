<?php

namespace App\Services\Laas;

use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point the existing Land 12 / Recommendation / RoFO modules
 * call to advance a portal application.
 *
 * Two properties make the hooks safe to drop into working controllers:
 *
 *   1. Silent no-op. advanceByFileNumber() returns null when the file number
 *      belongs to no LAAS application — which is the overwhelming majority of
 *      traffic, since only portal-originated files are tracked here. A normal
 *      Land 12 raised by a counter clerk behaves exactly as it did before.
 *
 *   2. Nothing thrown. Every public method swallows its own exceptions and
 *      logs them. A LAAS failure must never roll back or 500 the registry
 *      action that triggered it — the survey report is the record of truth,
 *      the applicant's SMS is a courtesy on top of it.
 *
 * Stage moves are forward-only (see LaasApplication::canAdvanceTo), so a hook
 * that fires twice — a re-print, a re-save — records nothing the second time.
 */
class LaasWorkflowService
{
    /** Desk that must act once Cadastral returns a completed Land 12. */
    public const DEPT_LAND_OFFICE = 'LANDS';

    /**
     * The event written by the most recent advance() on this instance.
     *
     * Lets a caller report what actually reached the applicant — the SMS
     * gateway refuses messages on content, so "approved" and "the applicant has
     * been notified" are two different facts and the screen must not conflate
     * them.
     */
    private ?LaasApplicationEvent $lastEvent = null;

    public function lastEvent(): ?LaasApplicationEvent
    {
        return $this->lastEvent;
    }

    /** True when the last advance() actually got a text delivered. */
    public function lastNotificationDelivered(): bool
    {
        return $this->lastEvent?->sms_status === LaasApplicationEvent::SMS_SENT;
    }

    public function __construct(private LaasNotificationService $notifications)
    {
    }

    /**
     * Advance the application holding $fileNumber to $stage.
     *
     * @param  array  $meta  Passed through to LaasNotificationService::record(),
     *                       plus optional columns to stamp under 'columns'.
     * @return LaasApplication|null  null when there is no LAAS application for
     *                               this file number, or the move was not a
     *                               forward one.
     */
    public function advanceByFileNumber(string $fileNumber, string $stage, array $meta = []): ?LaasApplication
    {
        try {
            $application = $this->findByFileNumber($fileNumber);

            if (!$application) {
                return null;
            }

            return $this->advance($application, $stage, $meta) ? $application : null;
        } catch (\Throwable $e) {
            Log::error('LAAS: advanceByFileNumber failed', [
                'file_number' => $fileNumber,
                'stage'       => $stage,
                'error'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Advance a known application. Returns false when the stage is not ahead of
     * where the application already is, in which case nothing is written.
     */
    public function advance(LaasApplication $application, string $stage, array $meta = []): bool
    {
        try {
            if (!$application->canAdvanceTo($stage)) {
                return false;
            }

            $columns = $meta['columns'] ?? [];
            unset($meta['columns']);

            $application->fill($columns);
            $application->stage = $stage;

            if ($stage === LaasApplication::STAGE_ROFO_SIGNED) {
                $application->completed_at = now();
            }

            $application->save();

            $this->lastEvent = $this->notifications->record($application, $stage, $meta);

            // Spec (h): a completed Land 12 is what puts the recommendation on
            // the Land Office's desk. It follows from (g) with no separate
            // trigger of its own, so it is derived here rather than hooked.
            if ($stage === LaasApplication::STAGE_LAND12_COMPLETED) {
                $this->openRecommendationStage($application);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('LAAS: advance failed', [
                'reference_no' => $application->reference_no ?? null,
                'stage'        => $stage,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve a LAAS application by its assigned file number.
     *
     * No UPPER() wrapper: the sqlsrv collation is case-insensitive, and
     * wrapping the column makes the predicate non-sargable, forcing a scan
     * instead of the index seek on laas_applications.file_number.
     */
    public function findByFileNumber(string $fileNumber): ?LaasApplication
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return null;
        }

        return LaasApplication::where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Move to `recommendation_pending` and put the alert on the Land Office /
     * OSS Unit desk. Silent for the applicant — they were already texted that
     * the survey report is done.
     */
    private function openRecommendationStage(LaasApplication $application): void
    {
        $application->stage = LaasApplication::STAGE_RECOMMENDATION_PENDING;
        $application->save();

        $this->notifications->record($application, LaasApplication::STAGE_RECOMMENDATION_PENDING, [
            'title'   => 'Recommendation requested',
            'body'    => 'The completed survey report has been returned to the Land Office for recommendation.',
            'sms'     => false,
            'visible' => true,
        ]);

        $this->notifications->alertDepartment(
            $application,
            self::DEPT_LAND_OFFICE,
            LaasApplication::STAGE_RECOMMENDATION_PENDING,
            "Recommendation due — {$application->file_number}",
            sprintf(
                'Cadastral has returned a completed survey report for %s (%s, %s). A recommendation is now due.',
                $application->file_number,
                $application->reference_no,
                $application->applicant_name ?: 'applicant unnamed'
            )
        );
    }
}
