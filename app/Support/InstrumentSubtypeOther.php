<?php

namespace App\Support;

use App\Services\InstrumentTypeCatalog;

/**
 * "Other" on an instrument Type stored in instrument_subtype (user, 2026-10-23,
 * Change of Ownership: LGA / Ordinary / Other). instrument_subtype stays 'Other'
 * and the specified text goes to instrument_subtype_other. Shared by the PRA card
 * and the File History transaction card. An Occupancy Permit's Other is OpType's.
 */
class InstrumentSubtypeOther
{
    public const OTHER = 'Other';

    public static function normalizeOther($value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /** True when this instrument's Type lives in instrument_subtype and offers Other. */
    public static function applies($instrumentType): bool
    {
        return InstrumentTypeCatalog::typeColumnFor($instrumentType) === InstrumentTypeCatalog::GENERIC_TYPE_COLUMN
            && array_key_exists(self::OTHER, InstrumentTypeCatalog::typesFor($instrumentType));
    }

    /** Field => message when Type is Other but its "specify" text is missing or a listed Type. */
    public static function errors($instrumentType, $subtype, $other, string $otherField): array
    {
        if (!self::applies($instrumentType) || trim((string) $subtype) !== self::OTHER) {
            return [];
        }

        $other = self::normalizeOther($other);
        if ($other === null) {
            return [$otherField => 'Type is Other: please specify the Type.'];
        }
        if (mb_strlen($other) > 100) {
            return [$otherField => 'The specified Type may not be longer than 100 characters.'];
        }

        foreach (InstrumentTypeCatalog::typesFor($instrumentType) as $value => $label) {
            if ($value !== self::OTHER && strcasecmp($other, (string) $label) === 0) {
                return [$otherField => "\"{$label}\" is already in the Type list; select it instead of Other."];
            }
        }

        return [];
    }

    /** The value to store in instrument_subtype_other: only kept while Type is Other. */
    public static function otherFor($instrumentType, $subtype, $other): ?string
    {
        return self::applies($instrumentType) && trim((string) $subtype) === self::OTHER
            ? self::normalizeOther($other)
            : null;
    }
}
