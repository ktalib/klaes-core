<?php

namespace App\Services;

use App\Models\Caveat;
use App\Services\Sms\CaveatSmsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lifting a caveat, in one place.
 *
 * Before this there were two lift paths doing the same job differently:
 * CaveatController::lift() (the Lift Caveat tab) wrote status 'released', and
 * removeByFile() (the Legal Search "Remove Caveat" action) wrote 'lifted'. Both
 * cleared the is_caveated flags, both appended notes, and neither knew about the
 * other. Adding a third caller -- the six-month expiry sweep -- and an SMS to all
 * three made that untenable.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: normalise the status strings. 'released'
 * and 'lifted' both survive, each written by the path that has always written
 * it, because every historical row and every query reading them was built
 * against that split. Collapsing them would silently reinterpret existing data.
 * Only the expiry sweep introduces a new one, 'expired' -- already a permitted
 * value on the table's CHECK constraint, but previously only ever set by hand at
 * creation time, for back-captured historical caveats.
 *
 * THE FLAG CLEARING IS THE POINT. A caveat row moving to 'lifted' means nothing
 * on its own: what actually unblocks dealings on the property is is_caveated
 * going to 0 on pra / CofO_staging / file_history_staging / deed_registrations.
 * Every caller therefore passes a flag-clearing callback -- the controller
 * already owns that logic and its several source-table special cases.
 */
class CaveatLiftService
{
    public const MODE_MANUAL = 'manual';
    public const MODE_REMOVE_BY_FILE = 'remove_by_file';
    public const MODE_AUTO = 'auto';

    /** What each path writes into caveats.status. See the note above. */
    public const STATUS_BY_MODE = [
        self::MODE_MANUAL => 'released',
        self::MODE_REMOVE_BY_FILE => 'lifted',
        self::MODE_AUTO => 'expired',
    ];

    public function __construct(private CaveatSmsNotifier $sms)
    {
    }

    /**
     * Mark a caveat lifted and tell the caveator.
     *
     * The caller does the flag clearing inside $clearFlags, because which rows to
     * clear depends on how the caveat was found -- by source record, by file
     * number, or by prop_id -- and the controller already resolves that.
     *
     * @param  callable|null  $clearFlags  Runs after the status is written.
     * @param  string|null    $liftedBy    Named in the message. Defaults to
     *                                     whoever the row records as updating it.
     */
    public function lift(Caveat $caveat, string $mode, ?callable $clearFlags = null, ?string $liftedBy = null, ?string $releaseDate = null): Caveat
    {
        $status = self::STATUS_BY_MODE[$mode] ?? self::STATUS_BY_MODE[self::MODE_MANUAL];

        $caveat->release_date = $releaseDate ?: ($caveat->release_date ?: now()->toDateString());
        $caveat->status = $status;
        $caveat->updated_by = $liftedBy ?: ($caveat->updated_by ?: 'System');
        $caveat->save();

        if ($clearFlags !== null) {
            $clearFlags($caveat);
        }

        /*
         | Queued, not sent. queue() only writes to the after-response job queue,
         | so nothing reaches the gateway until the response has been flushed --
         | by which time a rolled back transaction has already aborted the
         | request and the job with it.
         */
        if ($mode === self::MODE_AUTO) {
            $this->sms->autoLifted($caveat);
        } else {
            $this->sms->lifted($caveat, $liftedBy);
        }

        return $caveat;
    }

    /**
     * Every caveat due to be lifted after six months.
     *
     * TWO RULES, because release_date is optional at creation and most rows have
     * none:
     *   - a caveat WITH a release_date is due once that date has passed. The
     *     officer named an expiry and it has arrived.
     *   - a caveat WITHOUT one is due six months after start_date. This is the
     *     rule the Ministry asked for, and the only thing that will ever clear
     *     the historical backlog.
     *
     * Only 'active' rows. One already released, lifted or expired is done with,
     * and a draft was never in force.
     */
    public function due(?int $limit = null)
    {
        $sixMonthsAgo = now()->subMonths(6)->toDateTimeString();
        $today = now()->toDateString();

        $query = Caveat::query()
            ->where('status', 'active')
            ->where(function ($q) use ($today, $sixMonthsAgo) {
                $q->where(function ($w) use ($today) {
                    $w->whereNotNull('release_date')
                        ->whereDate('release_date', '<=', $today);
                })->orWhere(function ($w) use ($sixMonthsAgo) {
                    $w->whereNull('release_date')
                        ->whereNotNull('start_date')
                        ->where('start_date', '<=', $sixMonthsAgo);
                });
            })
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Clear the is_caveated flags an expiring caveat left behind.
     *
     * The controller's flag helpers are private and take a file number it has
     * already resolved; the sweep has only the caveat row, so it clears by
     * caveat_id -- the link every flagged row carries, and the only key that is
     * right for a caveat found this way.
     */
    public function clearFlagsByCaveatId(Caveat $caveat): void
    {
        $comment = "Caveat Expired\nCaveat No: " . $caveat->caveat_number
            . "\nAutomatically lifted after 6 months on " . now()->format('M d, Y, h:i A');

        foreach (['pra', 'CofO_staging', 'file_history_staging', 'pic', 'property_records'] as $table) {
            try {
                DB::connection('sqlsrv')->table($table)
                    ->where('caveat_id', $caveat->id)
                    ->update([
                        'is_caveated' => 0,
                        'caveated_comment' => $comment,
                        'caveat_id' => null,
                    ]);
            } catch (\Throwable $e) {
                // Not every one of these tables exists on every deployment, and a
                // missing one must not stop the rest of the sweep.
                Log::debug('CaveatLiftService: could not clear flags on ' . $table, [
                    'caveat_id' => $caveat->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            // deed_registrations has no flag columns -- it carries the state in
            // status instead, so 'caveated' has to go back to 'active'.
            DB::connection('sqlsrv')->table('deed_registrations')
                ->where('caveat_id', $caveat->id)
                ->where('status', 'caveated')
                ->update(['status' => 'active', 'caveat_id' => null]);
        } catch (\Throwable $e) {
            Log::debug('CaveatLiftService: could not clear deed_registrations status', [
                'caveat_id' => $caveat->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
