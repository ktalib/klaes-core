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

    public static function next(string $prefix, ?int $year = null): string
    {
        $prefix = strtoupper($prefix);
        if (! in_array($prefix, self::PREFIXES, true)) {
            throw new \InvalidArgumentException("{$prefix} is not a Computation File prefix.");
        }
        $year = in_array($prefix, self::NO_YEAR, true) ? self::ALL_YEARS : ($year ?? (int) date('Y'));

        $row = SurveyComputationSerial::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first()
            ?? SurveyComputationSerial::create(['prefix' => $prefix, 'year' => $year, 'last_serial' => 0]);

        $row->last_serial = (int) $row->last_serial + 1;
        $row->save();

        return self::format($prefix, $year, (int) $row->last_serial);
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
        $last = (int) SurveyComputationSerial::query()->where('prefix', $prefix)->where('year', $year)->value('last_serial');

        return self::format($prefix, $year, $last + 1);
    }
}
