<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyComputationSerial;

/**
 * Computation File numbers (CR 30102026 items 13-14, user 2026-10-31): LPKN-<serial> (no year, user
 * 2026-11-02) and LPPL-<year>-<serial>:
 * LPKN for Survey's Government files, LPPL for Cadastral's Private ones. The serial restarts
 * each year from 1, or continues from the last serial set under Configurable Entities >
 * Serial Initialization. Call inside the transaction that saves the file: the counter row
 * is locked until it commits, so two officers never get the same number.
 */
class ComputationNumbers
{
    public const PREFIXES = ['LPKN', 'LPPL'];

    /**
     * Series with no year in the number, counted on and on (user, 2026-11-02: "LPKN did not have
     * year in it, e.g. LPKN-123"). Their counter row is kept at year 0 ("all years").
     */
    public const NO_YEAR = ['LPKN'];

    public const ALL_YEARS = 0;

    /**
     * One LPKN series (user, 2026-11-03: "both LPKN should share the same serial numbers"): Computation Files take
     * their LPKN numbers from the Survey file-number generator's counter (gkn_serial_control, prefix LPKN), so a
     * number is given once, by whichever saves first. The number is also marked used in the generator's pool.
     */
    public const SHARED_WITH_GENERATOR = ['LPKN' => ['pool' => 'lpkn_grouping', 'awaiting' => 'lpkn_awaiting_fileno', 'fileno' => 'lpkn_fileno']];

    public static function next(string $prefix, ?int $year = null): string
    {
        $prefix = strtoupper($prefix);
        if (! in_array($prefix, self::PREFIXES, true)) {
            throw new \InvalidArgumentException("{$prefix} is not a Computation File prefix.");
        }
        $year = in_array($prefix, self::NO_YEAR, true) ? self::ALL_YEARS : ($year ?? (int) date('Y'));

        if (isset(self::SHARED_WITH_GENERATOR[$prefix])) {
            return self::nextShared($prefix);
        }

        $row = SurveyComputationSerial::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first()
            ?? SurveyComputationSerial::create(['prefix' => $prefix, 'year' => $year, 'last_serial' => 0]);

        $row->last_serial = max((int) $row->last_serial, self::applicationFloor($prefix)) + 1;
        $row->save();

        return self::format($prefix, $year, (int) $row->last_serial);
    }

    /**
     * The next number on the generator's counter, locked until the caller's transaction commits (the generator locks
     * the same row). Never below a number already indexed (applicationFloor). Refused, like the generator, until the
     * counter is set under Configurable Entities > Serial Initialization > Survey — File numbers.
     */
    private static function nextShared(string $prefix): string
    {
        $pool = self::SHARED_WITH_GENERATOR[$prefix];
        $row = \App\Models\GknSerialControl::query()->where('prefix', $prefix)->lockForUpdate()->first();
        if (! $row || ! $row->is_initialized) {
            throw \Illuminate\Validation\ValidationException::withMessages(['computation_no' =>
                "{$prefix} numbering has not been set. Set its last serial under Configurable Entities > Serial Initialization > Survey — File numbers."]);
        }
        $row->last_serial = max((int) $row->last_serial, self::applicationFloor($prefix)) + 1;
        $row->save();
        $no = self::format($prefix, self::ALL_YEARS, (int) $row->last_serial);

        // Marked used in the generator's pool, as the generator marks its own.
        \Illuminate\Support\Facades\DB::connection('sqlsrv')->table($pool['pool'])->where($pool['awaiting'], $no)
            ->where(fn ($q) => $q->where('mapping', 0)->orWhereNull('mapping'))
            ->update(['mapping' => 1, $pool['fileno'] => $no, 'created_by' => \Illuminate\Support\Facades\Auth::id(), 'updated_at' => now()]);

        return $no;
    }

    /** LPKN-123, or LPPL-2026-1 for a yearly series. */
    private static function format(string $prefix, int $year, int $serial): string
    {
        return $year === self::ALL_YEARS ? "{$prefix}-{$serial}" : "{$prefix}-{$year}-{$serial}";
    }

    /**
     * The number next() would give now, for showing on the form before it is saved. Nothing is
     * reserved: next() decides on save, so a form left open may end up with a later number.
     */
    public static function peek(string $prefix, ?int $year = null): string
    {
        $prefix = strtoupper($prefix);
        $year = in_array($prefix, self::NO_YEAR, true) ? self::ALL_YEARS : ($year ?? (int) date('Y'));
        $last = isset(self::SHARED_WITH_GENERATOR[$prefix])
            ? (int) \App\Models\GknSerialControl::query()->where('prefix', $prefix)->value('last_serial')
            : (int) SurveyComputationSerial::query()->where('prefix', $prefix)->where('year', $year)->value('last_serial');

        return self::format($prefix, $year, max($last, self::applicationFloor($prefix)) + 1);
    }

    /**
     * LPKN continues after the highest LPKN file under GKN > Applications (user, 2026-11-03: the start
     * comes "under application file"), i.e. the File Indexing LPKN-<n> numbers. So an indexed file is
     * never given again, even if the counter was set lower. Other prefixes have no floor.
     */
    public static function applicationFloor(string $prefix): int
    {
        if (strtoupper($prefix) !== 'LPKN') {
            return 0;
        }

        return (int) \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', 'like', 'LPKN-%')
            ->max(\Illuminate\Support\Facades\DB::raw('TRY_CAST(SUBSTRING(file_number, 6, 20) AS INT)'));
    }
}
