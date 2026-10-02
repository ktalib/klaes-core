<?php

namespace App\Console\Commands;

use App\Models\DuplexParcelUpdate;
use App\Models\User;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replay the duplex notifications that were raised but never delivered.
 *
 * Both events — captured and approved — have always fired at the right moment:
 * saveStage() announces the capture as the last stage completes, and approve()
 * announces the approval. Both went through the same recipient rule, which selected
 * users by the "Generate New FileNo (MLSFileNo)" role id inside the comma-separated
 * assign_role column — and NO user in Land carries it. Every send went to an empty
 * list and logged "0 users notified" as though it had worked.
 *
 * The rule is now MLPP staff in department 3 plus the administrators. This sends the
 * announcements the duplexes captured and approved under the old rule never got.
 *
 * Nothing else is touched: it writes notification rows and reads duplex rows.
 * Dry-run by default, like the other repair commands here.
 *
 *   php artisan duplex:notify-missed
 *   php artisan duplex:notify-missed --apply
 *   php artisan duplex:notify-missed --event=approved --apply
 *   php artisan duplex:notify-missed --duplex=DPX-2026-0004 --apply
 */
class DuplexNotifyCaptured extends Command
{
    protected $signature = 'duplex:notify-missed
        {--apply : Actually send. Omit for a read-only report.}
        {--event=both : captured | approved | both.}
        {--duplex= : One duplex ID, e.g. DPX-2026-0004. Default: every eligible duplex.}
        {--include-later : For the CAPTURED event, also duplexes that have moved past capture.}
        {--force : Send even where that announcement already exists for the duplex.}';

    protected $description = 'Replay the duplex captured / approved notifications missed while the recipient rule selected nobody';

    /**
     * Kept so the name this shipped under still works.
     *
     * Set in the constructor rather than as an $aliases property: Command on
     * Laravel 9 has no such property, so declaring one is silently ignored
     * and `duplex:notify-captured` simply stops existing.
     */
    public function __construct()
    {
        parent::__construct();

        $this->setAliases(['duplex:notify-captured']);
    }

    /**
     * A duplex is past capture once every stage is done. `captured` is the status it
     * lands on, but an officer who carried straight on to KAMMA and approval left it
     * further along — those were captured too, and missed the same announcement.
     */
    private const PAST_CAPTURE = [
        DuplexParcelUpdate::STATUS_PENDING,
        DuplexParcelUpdate::STATUS_APPROVED,
        DuplexParcelUpdate::STATUS_IN_LAND,
        DuplexParcelUpdate::STATUS_COMMITTED,
    ];

    /**
     * Approved and everything after it. in_land and committed are what an approved
     * duplex BECOMES, so they were approved too — the status column only ever holds
     * where a duplex is now, not every gate it has passed.
     */
    private const APPROVED_ONWARD = [
        DuplexParcelUpdate::STATUS_APPROVED,
        DuplexParcelUpdate::STATUS_IN_LAND,
        DuplexParcelUpdate::STATUS_COMMITTED,
    ];

    public function handle(ParcelUpdateNotificationService $notifier): int
    {
        $apply = (bool) $this->option('apply');
        $event = strtolower((string) $this->option('event'));

        if (!in_array($event, ['captured', 'approved', 'both'], true)) {
            $this->error("--event must be captured, approved or both — got \"{$event}\".");
            return self::FAILURE;
        }

        $recipients = $this->recipientCount();

        if ($recipients === 0) {
            $this->error('No recipients: nobody is MLPP in department 3, and there is no admin.');
            return self::FAILURE;
        }

        $this->line(($apply ? '[APPLY] ' : '[DRY-RUN] ') . "{$recipients} recipient(s) per announcement.");

        $sent = 0;
        $skipped = 0;

        foreach (['captured', 'approved'] as $which) {
            if ($event !== 'both' && $event !== $which) {
                continue;
            }

            [$sentHere, $skippedHere] = $this->replay($notifier, $which, $apply, $recipients);
            $sent += $sentHere;
            $skipped += $skippedHere;
        }

        $this->newLine();

        if ($apply) {
            $this->info("Done. {$sent} announcement(s) sent, {$skipped} skipped, "
                . ($sent * $recipients) . ' notification row(s) written.');
        } else {
            $this->info("{$sent} announcement(s) would be sent, {$skipped} skipped — about "
                . ($sent * $recipients) . ' notification row(s).');
            $this->line('Re-run with --apply to send.');
        }

        return self::SUCCESS;
    }

    /**
     * One event across every duplex that should have had it.
     *
     * @return array{0:int,1:int} [sent (or would-send), skipped]
     */
    private function replay(
        ParcelUpdateNotificationService $notifier,
        string $event,
        bool $apply,
        int $recipients
    ): array {
        $statuses = $event === 'approved'
            ? self::APPROVED_ONWARD
            : array_merge(
                [DuplexParcelUpdate::STATUS_CAPTURED],
                $this->option('include-later') ? self::PAST_CAPTURE : []
            );

        $query = DuplexParcelUpdate::visible()->whereIn('status', $statuses);

        if ($one = $this->option('duplex')) {
            $query->where('duplex_id', $one);
        }

        $duplexes = $query->orderBy('id')->get();

        $this->newLine();
        $this->line(strtoupper($event) . '  (' . implode(', ', $statuses) . ')');

        if ($duplexes->isEmpty()) {
            $this->line('  none');
            return [0, 0];
        }

        $sent = 0;
        $skipped = 0;

        foreach ($duplexes as $duplex) {
            // 'created' is the payload's own name for the capture announcement.
            $tag = $event === 'approved' ? 'approved' : 'created';
            $already = $this->alreadySent($duplex, $tag);

            if ($already && !$this->option('force')) {
                $this->line(sprintf('  %-14s %-10s already sent (%d row(s)) — skipped',
                    $duplex->duplex_id, $duplex->status, $already));
                $skipped++;
                continue;
            }

            $this->line(sprintf('  %-14s %-10s %s', $duplex->duplex_id, $duplex->status,
                $apply ? 'sending…' : "would notify {$recipients}"));

            $sent++;

            if (!$apply) {
                continue;
            }

            // The same calls saveStage() and approve() make, so the copy and the
            // payload are identical to an announcement raised at the real moment.
            if ($event === 'approved') {
                $notifier->notifyApproved(
                    'duplex',
                    $duplex->id,
                    $duplex->duplex_id,
                    (string) $duplex->file_title,
                    $this->approverName($duplex)
                );
            } else {
                $notifier->notifyCreated(
                    'duplex',
                    $duplex->id,
                    $duplex->duplex_id,
                    (string) $duplex->file_title,
                    (string) $duplex->applicant_name
                );
            }
        }

        return [$sent, $skipped];
    }

    /** Who approved it, where that is still on record. Blank reads as "-" in the copy. */
    private function approverName(DuplexParcelUpdate $duplex): string
    {
        if (!$duplex->approved_by) {
            return '';
        }

        $user = User::on('sqlsrv')->find($duplex->approved_by);

        return $user ? (string) ($user->name ?? $user->email ?? '') : '';
    }

    /** MLPP in Land, plus the administrators — the rule the service now uses. */
    private function recipientCount(): int
    {
        return DB::connection('sqlsrv')->table('users')
            ->where(function ($q) {
                $q->where(function ($land) {
                    $land->where('staff_type_category', 'MLPP')->where('department_id', 3);
                })->orWhere('is_admin', 1);
            })
            ->count();
    }

    /**
     * Has this announcement already gone out for this duplex?
     *
     * Matched on the duplex id and the event name inside the notification's data
     * payload. Keeps a re-run from sending a second copy to every recipient.
     */
    private function alreadySent(DuplexParcelUpdate $duplex, string $event): int
    {
        return DB::connection('sqlsrv')->table('notifications')
            ->where('module', 'parcel_update')
            ->where('data', 'LIKE', '%"file_no":"' . $duplex->duplex_id . '"%')
            ->where('data', 'LIKE', '%"event":"' . $event . '"%')
            ->count();
    }
}
