<?php

namespace App\Support;

/**
 * The unit a ground rent is charged by - per square metre, per hectare, or
 * whatever the grant actually says.
 *
 * Stored as the literal words that appear on the letter rather than a code,
 * because that is the only form anything downstream needs: the templates print
 * it straight after the figure and nothing has to map it back.
 *
 * The form offers a fixed list plus "Other", and posts the free text in its own
 * field. Only the resolved value is ever stored, so no screen, print or export
 * has to know which of the fixed choices a unit came from.
 *
 * Shared by Land Recommendations and SLTR Recommendations, which capture and
 * print the same thing and must offer the same list.
 */
class GroundRentUnit
{
    /** The fixed choices; anything else the officer types is "Other". */
    public const OPTIONS = [
        'Per Square Meters',
        'Ha',
    ];

    /**
     * What a record keyed before this field existed was charged by.
     *
     * Every such recommendation was per square metre - the SLTR letter said so in
     * so many words, and the Land letter said nothing because there was nothing
     * else it could have meant. Used by the backfills and as the default a new
     * form opens on, so old and new letters read alike.
     */
    public const LEGACY_DEFAULT = 'Per Square Meters';

    /** Is this a unit the officer typed rather than picked? */
    public static function isOther(?string $unit): bool
    {
        $unit = trim((string) $unit);

        return $unit !== '' && !in_array($unit, self::OPTIONS, true);
    }

    /**
     * Resolve a posted payload down to the words that will print.
     *
     * "Other" becomes the free text; "Other" with nothing typed is no unit at all,
     * not the literal word. A payload that carries no unit key is left untouched,
     * so a partial update cannot blank a stored value.
     *
     * @param  array  $data     the request payload, or one child of a batch
     * @param  string $key      the unit field
     * @param  string $otherKey the free-text field, removed on the way out
     */
    public static function resolve(
        array $data,
        string $key = 'ground_rent_unit',
        string $otherKey = 'ground_rent_unit_other'
    ): array {
        if (!array_key_exists($key, $data)) {
            unset($data[$otherKey]);

            return $data;
        }

        $unit = trim((string) ($data[$key] ?? ''));

        if (strcasecmp($unit, 'Other') === 0) {
            $unit = trim((string) ($data[$otherKey] ?? ''));
        }

        $data[$key] = $unit === '' ? null : $unit;
        unset($data[$otherKey]);

        return $data;
    }

    /**
     * The rent as it should read on a letter: the figure and its unit,
     * e.g. "5,000.00 Per Square Meters".
     *
     * The currency mark is NOT included - every template already prints its own
     * ("N" on the RofO, "₦" on the recommendation) and doubling it would be worse
     * than leaving it out. A record with no unit gives the bare figure, which is
     * how every record keyed before the unit existed has always printed.
     */
    public static function label($amount, ?string $unit): string
    {
        $figure = number_format((float) ($amount ?? 0), 2);
        $unit = trim((string) $unit);

        return $unit === '' ? $figure : $figure . ' ' . $unit;
    }
}
