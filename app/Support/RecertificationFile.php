<?php

namespace App\Support;

/**
 * Recognises a Recertification land file from its number.
 *
 * A recertification is not recorded in a column of its own — it is carried in the file
 * number's prefix, where an "RC" token follows the land use:
 *
 *   IND-RC-2026-1        recertification of an Industrial file
 *   CON-RES-RC-1991-39   recertification of a converted Residential file
 *
 * The registry treats both families the same way, which is why the test is the presence
 * of the token rather than a list of prefixes. `LegalSearchService::isRecertLandFile()`
 * applies the identical rule when it synthesises the Ministry recertification line on the
 * Legal Search timeline; the two must stay in step.
 *
 * A KANGIS number is excluded: "KNML 74" carries no prefix structure at all, and the
 * token test would misread numbers that merely contain the letters.
 */
class RecertificationFile
{
    /**
     * What `mls_file_no.source` holds for a recertification, and what prints in the
     * "(File Type)" bracket on the commissioning sheet.
     */
    public const SOURCE_LABEL = 'Recertification';

    /** True when the file number carries the RC token, e.g. IND-RC-2026-1. */
    public static function isRecertFileNumber(?string $fileNo): bool
    {
        $value = strtoupper(trim((string) $fileNo));

        if ($value === '' || FileNumberLandUse::isKangisNumber($value)) {
            return false;
        }

        return (bool) preg_match('~(?:^|[-_/ ])RC(?:[-_/ ]|$)~', $value);
    }

    /**
     * The label a file's commissioning source should carry, given the stored value.
     *
     * The RC token wins over whatever the allocation route resolved to: a file commissioned
     * as a Direct Allocation but numbered IND-RC-… is a recertification of an earlier file,
     * and so is a converted one numbered CON-RES-RC-…. Everything else is passed through.
     */
    public static function applySourceLabel(?string $fileNo, ?string $source): ?string
    {
        return self::isRecertFileNumber($fileNo) ? self::SOURCE_LABEL : $source;
    }
}
