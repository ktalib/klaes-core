<?php

namespace App\Console\Commands;

use App\Models\SpaApplication;
use App\Models\SpaNotice;
use App\Services\SpaBillingService;
use App\Services\SpaNoticeService;
use Illuminate\Console\Command;

/**
 * Serve the First Serve on every record in contravention that has none yet.
 *
 * New contraventions are served automatically when they are recorded
 * (SpaNoticeService, called from SpaMobileService). This catches the ones
 * recorded before that existed. Each notice is recorded as SERVED, with the
 * SMS sent when the record has a usable phone number. Records with no
 * contravention are left alone — they owe no notice.
 *
 * Sends real SMS. Run --dry-run first.
 */
class SpaServePendingFirstServes extends Command
{
    protected $signature = 'spa:serve-pending-first-serves {--dry-run : List what would be served, send nothing}';

    protected $description = 'Serve the First Serve (with SMS) on SPAS records in contravention that have none yet.';

    public function handle(SpaBillingService $billing, SpaNoticeService $notices): int
    {
        $pending = SpaApplication::orderBy('id')->get()->filter(function ($app) use ($billing) {
            if (! $billing->contravenes($app)) {
                return false;
            }

            return ! SpaNotice::where('notice_type', 'first')
                ->where(fn ($q) => $q->where('spa_application_id', $app->id)
                    ->when($app->file_number, fn ($qq) => $qq->orWhere('file_number', $app->file_number)))
                ->exists();
        });

        if ($pending->isEmpty()) {
            $this->info('No record in contravention is waiting for its First Serve.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn($pending->count() . ' First Serve(s) would be served:');
            foreach ($pending as $app) {
                $this->line(sprintf('  #%-4s %-18s %-32s %s', $app->id, $app->file_number, $app->owner_name,
                    $notices->canText($app->phone) ? 'SMS to ' . $app->phone : 'no SMS (phone "' . ($app->phone ?: '') . '")'));
            }
            $this->comment('Dry run — nothing created, nothing sent.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($pending as $app) {
            $notice = $notices->serveFirstForContravention($app, 'system:spa-backfill');
            $ok = $notice && $notice->fresh()->sms_sent;
            $sent += $ok ? 1 : 0;
            $this->line(sprintf('  %s #%-4s %-18s %-32s %s', $notice ? '✓' : '✗', $app->id, $app->file_number, $app->owner_name,
                $notice ? ($ok ? 'served, SMS sent' : 'served, no SMS') : 'FAILED — see log'));
        }

        $this->info("Done. Served: {$pending->count()}, SMS sent: {$sent}.");

        return self::SUCCESS;
    }
}
