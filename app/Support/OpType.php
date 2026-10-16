<?php

namespace App\Support;

/**
 * "Other" on an Occupancy Permit's Type (user, 2026-10-15): op_type stays 'Other'
 * and the specified text goes to pra.op_type_other. Shared by the file-indexing OP
 * section and the PRA card. The Type itself stays optional; only Other is checked.
 */
class OpType
{
    public const OTHER = 'Other';

    /** Listed Types, compared without the "OP " prefix some screens store. */
    public const LISTED = ['Resettlement', 'Direct Allocation'];

    public static function normalizeOther($value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /** Field => message when Type is Other but its "specify" text is missing or a duplicate. */
    public static function errors($type, $other, string $otherField): array
    {
        if (trim((string) $type) !== self::OTHER) {
            return [];
        }

        $other = self::normalizeOther($other);
        if ($other === null) {
            return [$otherField => 'OP Type is Other: please specify the OP Type.'];
        }
        if (mb_strlen($other) > 100) {
            return [$otherField => 'The specified OP Type may not be longer than 100 characters.'];
        }

        $bare = preg_replace('/^OP\s+/i', '', $other);
        foreach (self::LISTED as $listed) {
            if (strcasecmp($bare, $listed) === 0) {
                return [$otherField => "\"{$listed}\" is already in the OP Type list; select it instead of Other."];
            }
        }
        if (strcasecmp($bare, 'LGA') === 0) {
            return [$otherField => 'LGA is a Source, not an OP Type: set Source to LGA instead.'];
        }

        return [];
    }

    /** The value to store in op_type_other: only kept while Type is Other. */
    public static function otherFor($type, $other): ?string
    {
        return trim((string) $type) === self::OTHER ? self::normalizeOther($other) : null;
    }
}
