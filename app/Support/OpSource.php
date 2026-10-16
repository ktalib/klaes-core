<?php

namespace App\Support;

use App\Services\KanoLgaDirectory;

/**
 * Source of an Occupancy Permit (user, 2026-10-15): who issued it. Stored in
 * pra.op_source; "Other" keeps its specified text in pra.op_source_other.
 * LGA used to be an OP Type and is a Source now. Shared by the file-indexing OP
 * section and the PRA card so both apply the same rules.
 */
class OpSource
{
    public const OPTIONS = ['Ministry', 'KANGIS', 'LGA'];
    public const OTHER = 'Other';

    /** Free text: trimmed, inner whitespace collapsed, case kept as typed. */
    public static function normalizeOther($value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Field => message for everything wrong with a permit's Source; empty when valid.
     *
     * @param  string  $sourceField  input name of the Source select
     * @param  string  $otherField   input name of the "specify" box
     * @param  string  $grantorField input name to report an LGA grantor problem on
     */
    public static function errors($source, $other, $grantor, string $sourceField, string $otherField, string $grantorField): array
    {
        $source = trim((string) $source);

        if (!in_array($source, array_merge(self::OPTIONS, [self::OTHER]), true)) {
            return [$sourceField => 'Please select the Source of the Occupancy Permit (Ministry, KANGIS, LGA or Other).'];
        }

        if ($source === self::OTHER) {
            $other = self::normalizeOther($other);
            if ($other === null) {
                return [$otherField => 'Source is Other: please specify the Source of the Occupancy Permit.'];
            }
            if (mb_strlen($other) > 100) {
                return [$otherField => 'The specified Source may not be longer than 100 characters.'];
            }
            // A listed option typed in by hand (any case) is that option, not a new one.
            foreach (self::OPTIONS as $option) {
                if (strcasecmp($other, $option) === 0) {
                    return [$otherField => "\"{$option}\" is already in the Source list; select it instead of Other."];
                }
            }
        }

        if ($source === 'LGA'
            && !in_array(trim((string) $grantor), app(KanoLgaDirectory::class)->fullNames(), true)) {
            return [$grantorField => 'Source is LGA: select the Local Government that issued the Occupancy Permit.'];
        }

        return [];
    }

    /** The value to store in op_source_other: only kept while Source is Other. */
    public static function otherFor($source, $other): ?string
    {
        return trim((string) $source) === self::OTHER ? self::normalizeOther($other) : null;
    }
}
