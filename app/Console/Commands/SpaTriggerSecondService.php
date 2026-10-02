<?php

namespace App\Console\Commands;

use App\Models\SpaNotice;
use App\Services\BulkSmsNgService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Escalate a first serve to a second serve, two weeks after the owner was told.
 *
 * THE CLOCK STARTS AT DELIVERY, NOT AT DATA ENTRY
 * Eligibility used to be `served_date <= today - 14`, which is the date the
 * notice row was created. That produced two wrong outcomes:
 *
 *   1. A first serve whose SMS FAILED still escalated. Fourteen days later the
 *      owner received "there was no response from you after the first serve,
 *      hence you will pay the contravention charges plus penalty" — having
 *      never been told anything. Levying a penalty off a notice that was never
 *      delivered is indefensible, and failed sends are not hypothetical here:
 *      the gateway refuses some messages on wording and still answers 200.
 *
 *   2. A first serve sent LATE started its clock early. If the SMS went out
 *      five days after the row was created, the statutory two weeks ran from
 *      creation and the owner effectively got nine days.
 *
 * So a first serve is only eligible once `sms_sent` is true, and the two weeks
 * run from `sms_sent_at` — falling back to `served_date` for rows written
 * before that timestamp was recorded.
 *
 * A first serve whose SMS never sent is therefore never escalated. That is the
 * point, not an oversight, so the command reports how many are stuck rather
 * than letting them sit invisibly forever.
 */
class SpaTriggerSecondService extends Command
{
    protected $signature = 'spa:trigger-second-service
                            {--dry-run : List what would be served, send nothing}';

    protected $description = 'Escalate first-serve SPAS notices to a second serve, 14 days after the first-serve SMS was delivered.';

    /**
     * When the first serve actually reached the owner.
     *
     * COALESCE because sms_sent_at was added after some rows already existed;
     * those have sms_sent = 1 but no timestamp, and served_date is the best
     * evidence available for them.
     */
    private const EFFECTIVE_SERVED_AT = 'COALESCE(spa_notices.sms_sent_at, spa_notices.served_date)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::today()->subDays(SpaNotice::SECOND_SERVE_AFTER_DAYS);

        $eligible = SpaNotice::where('notice_type', 'first')
            // The escalation is only defensible if the owner was actually told.
            ->where('sms_sent', 1)
            ->whereRaw(self::EFFECTIVE_SERVED_AT.' <= ?', [$cutoff->toDateTimeString()])
            ->whereNotExists(function ($query) {
                // Free-style notices carry no application id, so they are matched
                // on file number instead — matching on a NULL id never holds in
                // SQL and would re-issue their second serve on every run.
                $query->select(DB::raw(1))
                    ->from('spa_notices as sn2')
                    ->where('sn2.notice_type', 'second')
                    ->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->whereNotNull('spa_notices.spa_application_id')
                               ->whereColumn('sn2.spa_application_id', 'spa_notices.spa_application_id');
                        })->orWhere(function ($q2) {
                            $q2->whereNull('spa_notices.spa_application_id')
                               ->whereNull('sn2.spa_application_id')
                               ->whereColumn('sn2.file_number', 'spa_notices.file_number');
                        });
                    });
            })
            ->with('application')
            ->get();

        $this->reportStuck($cutoff);

        if ($eligible->isEmpty()) {
            $this->info('No first serves are due for escalation.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn($eligible->count().' first serve(s) would be escalated:');

            foreach ($eligible as $first) {
                $this->line(sprintf(
                    '  %-22s %-16s first served %s',
                    $first->file_number,
                    $first->phone,
                    optional($first->sms_sent_at ?? $first->served_date)->format('Y-m-d')
                ));
            }

            $this->comment('Dry run — nothing created, nothing sent.');

            return self::SUCCESS;
        }

        $sms = app(BulkSmsNgService::class);
        $created = 0;
        $failed = 0;
        $smsFailed = 0;

        foreach ($eligible as $first) {
            // Re-check immediately before writing. The daily run and a manual
            // run can overlap, and a duplicate second serve is a duplicate
            // penalty demand.
            $alreadyExists = SpaNotice::where('notice_type', 'second')
                ->when(
                    $first->spa_application_id,
                    fn ($q) => $q->where('spa_application_id', $first->spa_application_id),
                    fn ($q) => $q->whereNull('spa_application_id')->where('file_number', $first->file_number)
                )
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            try {
                $second = SpaNotice::create([
                    'spa_application_id' => $first->spa_application_id,
                    'file_number'        => $first->file_number,
                    'notice_type'        => 'second',
                    'recipient_name'     => $first->recipient_name,
                    'phone'              => $first->phone,
                    'served_by'          => $first->served_by,
                    'served_date'        => now()->toDateString(),
                    'status'             => 'served',
                    'created_by'         => 'system:spa-auto',
                ]);

                // Wording is the Ministry's, shared with the manual path.
                $smsSent = false;

                try {
                    $smsSent = $sms->send($first->phone, SpaNotice::smsBody('second'));
                } catch (\Throwable $e) {
                    Log::warning('SPAS auto-second-serve SMS failed', [
                        'phone' => $first->phone,
                        'error' => $e->getMessage(),
                    ]);
                }

                $second->update([
                    'sms_sent'    => $smsSent,
                    'sms_sent_at' => $smsSent ? now() : null,
                ]);

                if (! $smsSent) {
                    $smsFailed++;
                }

                $created++;
                $this->line(sprintf(
                    '  %s second serve %s (%s) — SMS %s',
                    $smsSent ? '✓' : '!',
                    $first->file_number,
                    $first->phone,
                    $smsSent ? 'sent' : 'FAILED'
                ));
            } catch (\Throwable $e) {
                $failed++;
                Log::error('SPAS auto-second-serve failed', ['first_id' => $first->id, 'error' => $e->getMessage()]);
                $this->error("  ✗ {$first->file_number}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Created: {$created}, SMS failures: {$smsFailed}, errors: {$failed}.");
        Log::info('spa:trigger-second-service', compact('created', 'smsFailed', 'failed'));

        return self::SUCCESS;
    }

    /**
     * First serves old enough to escalate whose SMS never went.
     *
     * They are deliberately NOT escalated, but they are also not resolved: the
     * owner has never been notified and nothing is chasing it. Without this
     * line they would sit unseen indefinitely.
     */
    private function reportStuck(Carbon $cutoff): void
    {
        $stuck = SpaNotice::where('notice_type', 'first')
            ->where('sms_sent', 0)
            ->whereNotNull('served_date')
            ->where('served_date', '<=', $cutoff)
            ->count();

        if ($stuck > 0) {
            $this->warn(
                $stuck.' first serve(s) are past 14 days but their SMS never sent — '
                .'NOT escalated, because the owner was never notified. Re-send the first serve.'
            );

            Log::warning('SPAS first serves stuck unsent', ['count' => $stuck]);
        }
    }
}
