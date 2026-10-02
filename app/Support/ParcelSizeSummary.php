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

        if ($list->isEmpty()) {
            return ['list' => '', 'total' => '', 'has' => false, 'phrase' => '', 'total_phrase' => ''];
        }

        $formatted = $list->map(fn ($v) => self::number($v))->implode(' + ');
        $total     = self::number($list->sum());

        return [
            'list'  => $formatted,
            'total' => $total,
            'has'   => true,
            // " of 1,500 + 2,000 + 3,000 m²" — appended wherever a parcel is named.
            'phrase' => ' of ' . $formatted . ' m²',
            // The same, with the sum spelled out. Used where one number is clearer
            // than the run — a merger states what the merged parcel comes to.
            'total_phrase' => $list->count() > 1
                ? ' measuring ' . $formatted . ' m² (total ' . $total . ' m²)'
                : ' measuring ' . $formatted . ' m²',
        ];
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
