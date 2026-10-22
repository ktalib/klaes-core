<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The parcel sizes a parcel-update application recorded, as a memo reads them:
 * "1,500 + 2,000 + 3,000 m² (total 6,500 m²)".
 *
 * Lifted out of duplex/print/recommendation.blade.php, which is the sheet the
 * Ministry accepted, so the four single-workflow memos (subdivision, merger,
 * separation, extension) print sizes the same way rather than each growing its own
 * formatter — or, as they did until now, omitting the sizes altogether while the
 * controller had already loaded them.
 *
 * Square metres run to five figures, so they carry a thousands separator: a plot
 * reads "1,410", not "1410". Fractional metres are kept where a survey gives them
 * and dropped where it does not, so a whole number never prints as "1,410.00".
 *
 * A blank or zero size is skipped rather than printed as 0 — a partly-filled
 * application still produces a sensible line, and an application with no sizes at
 * all produces no phrase instead of "of  m²".
 */
class ParcelSizeSummary
{
    /** Square metres in a hectare. The one copy — see hectares(). */
    public const M2_PER_HECTARE = 10000;

    /** How many sides an area can be derived from: length x width, and nothing else. */
    public const DERIVABLE_SIDES = 2;

    /**
     * @param  iterable|null  $sizes  PlotApplicationSize rows (any `type`).
     * @return array{list:string,total:string,has:bool,phrase:string,total_phrase:string}
     */
    public static function of($sizes): array
    {
        $list = Collection::make($sizes ?? [])
            ->map(fn ($row) => is_array($row) ? ($row['plot_size'] ?? null) : ($row->plot_size ?? null))
            ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->values();

        return self::fromNumbers($list);
    }

    /**
     * The same summary from bare sizes (the duplex keeps them on its stage payload
     * rather than as PlotApplicationSize rows).
     *
     * @param  iterable  $values  sizes in m²; blanks and zeros are skipped
     */
    public static function fromValues($values): array
    {
        return self::fromNumbers(
            Collection::make($values ?? [])
                ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
                ->map(fn ($v) => (float) $v)
                ->values()
        );
    }

    private static function fromNumbers(Collection $list): array
    {
        if ($list->isEmpty()) {
            return ['list' => '', 'summary' => '', 'total' => '', 'has' => false, 'phrase' => '', 'total_phrase' => ''];
        }

        $formatted = $list->map(fn ($v) => self::number($v))->implode(' + ');
        $total     = self::number($list->sum());
        $summary   = self::summary($list);
        $repeats   = $summary !== $formatted;

        // A memo for an 82-plot subdivision printed "450 + 450 + ... + 450 m²" eighty-two
        // times over. Repeated sizes are counted instead — "82 parcels of 450 m² each",
        // "80 × 450 + 2 × 600 m²" — and the total is always stated alongside, because a
        // grouped run is harder to add up by eye. Sizes that never repeat read as before.
        $uniform = $list->unique()->count() === 1 && $list->count() > 1;
        $sized   = $uniform ? self::number($list[0]) . ' m² each' : $summary . ' m²';
        $withSum = $list->count() > 1 ? ' (total ' . $total . ' m²)' : '';

        return [
            // Every size, in order. Kept for callers that want the full run.
            'list'    => $formatted,
            // The run with repeats counted: "80 × 450 + 2 × 600".
            'summary' => $summary,
            'total'   => $total,
            'has'     => true,
            // " of 1,500 + 2,000 + 3,000 m²" — appended wherever a parcel is named.
            'phrase'  => ' of ' . $sized . ($repeats ? $withSum : ''),
            // The same, with the sum spelled out. Used where one number is clearer
            // than the run — a merger states what the merged parcel comes to.
            'total_phrase' => ' measuring ' . $sized . $withSum,
        ];
    }

    /**
     * Sizes with repeats counted, in first-appearance order:
     * [450, 450, 600, 450] -> "3 × 450 + 600". No unit — the caller appends m².
     */
    public static function summary($values): string
    {
        return Collection::make($values)
            ->map(fn ($v) => (float) $v)
            ->groupBy(fn ($v) => self::number($v), true)
            ->map(fn ($group, $label) => $group->count() > 1 ? $group->count() . ' × ' . $label : $label)
            ->implode(' + ');
    }

    /**
     * The parcel letter the site plan uses: 0 -> A, 25 -> Z, 26 -> AA, 81 -> CD.
     * Past Z the letters run on as a spreadsheet's columns do, rather than falling
     * back to numbers that read like plot numbers.
     */
    public static function letter(int $index): string
    {
        $out = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $out = chr(65 + ($n - 1) % 26) . $out;
        }

        return $out;
    }

    /**
     * Lettered dimension lines, with parcels of identical dimensions stated once:
     *
     *     [0 => '15.00m x 30.00m', ..., 81 => '15.00m x 30.00m']
     *         -> ['A – CD (82 parcels): 15.00m x 30.00m each.']
     *     [0 => '60m x 21m', 1 => '21m x 95m', 2 => '60m x 21m']
     *         -> ['A, C (2 parcels): 60m x 21m each.', 'B: 21m x 95m.']
     *
     * Keys are the parcel's position (so its letter matches the plan even where a
     * parcel with no dimensions is skipped); groups keep first-appearance order and
     * consecutive letters collapse into a range.
     *
     * @param  array<int,string>  $byIndex
     * @return string[]
     */
    public static function groupedDimensionLines(array $byIndex): array
    {
        $groups = [];
        foreach ($byIndex as $i => $text) {
            $groups[$text][] = (int) $i;
        }

        $lines = [];
        foreach ($groups as $text => $indexes) {
            $count = count($indexes);
            $lines[] = self::letterRanges($indexes)
                . ($count > 1 ? ' (' . $count . ' parcels): ' . $text . ' each.' : ': ' . $text . '.');
        }

        return $lines;
    }

    /** [0,1,2,4,6,7] -> "A – C, E, G, H": runs of three or more become a range. */
    private static function letterRanges(array $indexes): string
    {
        sort($indexes);
        $parts = [];
        $start = $prev = null;

        $flush = function () use (&$parts, &$start, &$prev) {
            if ($start === null) {
                return;
            }
            if ($prev - $start >= 2) {
                $parts[] = self::letter($start) . ' – ' . self::letter($prev);
            } else {
                for ($k = $start; $k <= $prev; $k++) {
                    $parts[] = self::letter($k);
                }
            }
        };

        foreach ($indexes as $i) {
            if ($prev !== null && $i === $prev + 1) {
                $prev = $i;
                continue;
            }
            $flush();
            $start = $prev = $i;
        }
        $flush();

        return implode(', ', $parts);
    }

    /**
     * A parcel's sides as the memo states them: [60, 21, 46] -> "60 x 21 x 46".
     *
     * Blanks are dropped, so a half-filled row of boxes gives the sides that were
     * actually measured rather than "60 x  x 46"; an empty list gives null, which
     * is what a parcel captured before dimensions existed carries.
     *
     * No thousands separator here, unlike an area: a side is a plain measurement
     * off the plan, and "1,200 x 40" invites being read as two numbers.
     */
    public static function dimensionsText($terms): ?string
    {
        $sides = Collection::make(\is_array($terms) ? $terms : [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '' && (float) $v > 0)
            ->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
            ->values();

        return $sides->isEmpty() ? null : $sides->implode(' x ');
    }

    /**
     * The area those sides give, or null where it cannot honestly be derived.
     *
     * A parcel is a polygon, not always a rectangle — which is how the Ministry's
     * own memo states it — so an area is only derived from EXACTLY two sides.
     * Any other count returns null and the officer types the measured area off the
     * plan rather than being handed an invented one.
     *
     * The same rule the capture forms apply in JS (areaFromTerms). It lives here so
     * the two cannot drift: a form posting sides without an area gets the same
     * answer the officer saw on screen, and a payload that never went through the
     * form is not stored with a blank measurement.
     */
    public static function areaFromSides($terms): ?float
    {
        $sides = Collection::make(\is_array($terms) ? $terms : [])
            ->map(fn ($v) => (float) $v)
            ->filter(fn ($v) => is_finite($v) && $v > 0)
            ->values();

        return $sides->count() === self::DERIVABLE_SIDES
            ? $sides[0] * $sides[1]
            : null;
    }

    /**
     * The same area in hectares: 1410 -> "0.141", 39591 -> "3.9591".
     *
     * The sheets state a measurement as "m²/ha", so the two always travel together
     * and the hectare figure is DERIVED — square metres are the stored authority.
     * Four decimal places because a plot is a fraction of a hectare and 0.14 loses
     * the metres; trailing zeros are dropped so a clean 2 ha does not print
     * "2.0000".
     *
     * Written here rather than inline for the fourth time: the /10000 was
     * duplicated in subdivision.blade.php, duplex/js.blade.php and the JSI print
     * clone, each with its own rounding.
     */
    public static function hectares($value): string
    {
        $ha = (float) $value / self::M2_PER_HECTARE;

        return rtrim(rtrim(number_format($ha, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** 1410 -> "1,410"; 1410.5 -> "1,410.5"; 1410.00 -> "1,410". */
    public static function number($value): string
    {
        $f = (float) $value;

        return $f == floor($f)
            ? number_format($f, 0, '.', ',')
            : rtrim(rtrim(number_format($f, 2, '.', ','), '0'), '.');
    }
}
