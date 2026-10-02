<?php

namespace App\Support;

/**
 * One way to decide whether two holder names are the same name.
 *
 * WHY THIS IS SHARED
 * Two modules ask the same question of the same pair of names and must not give
 * two answers. MLS/MLPP File Number Commissioning asks it to keep an OP in the
 * right stream — a Land OP keeps its allottee as the file title, an OSS OP is a
 * Change of Ownership and therefore has a different one. OSS asks it to decide
 * what it is writing — a name change makes the row a Transfer of Title, no name
 * change leaves it an Occupancy Permit.
 *
 * They used to normalise separately: MLS stripped honorifics and punctuation,
 * OSS only lowercased and collapsed whitespace. On "ALH. MUSA ABDULLAHI" vs
 * "Musa Abdullahi" — an everyday pair in these records — MLS said no change and
 * sent the officer to Land, while OSS wrote the same pair as a Transfer of
 * Title. Both cannot be right, and the disagreement was invisible because each
 * module only ever saw its own verdict.
 *
 * WHAT IT DOES NOT DO
 * No fuzzy matching, no soundex, no reordering of name parts. "Musa Abdullahi"
 * and "Abdullahi Musa" are different people until an officer says otherwise;
 * guessing that they are the same would silently merge two holders, which is
 * far worse than asking. This only removes differences that carry no meaning:
 * case, spacing, punctuation, and the honorifics people vary between documents.
 */
final class HolderName
{
    /**
     * Honorifics that appear on one document and not the next, and so cannot be
     * allowed to make a name look changed. Matched as whole words only, with an
     * optional trailing full stop ("ALH." as well as "ALH").
     */
    private const HONORIFICS = ['ALHAJI', 'ALH', 'HAJIYA', 'HAJ', 'MALLAM', 'MAL', 'DR', 'MR', 'MRS', 'MISS', 'CHIEF', 'HON'];

    /** Comparison key for one name. Equal keys mean the same holder. */
    public static function normalise(?string $name): string
    {
        $name = strtoupper(trim((string) $name));
        $name = preg_replace('/\b(' . implode('|', self::HONORIFICS) . ')\.?\b/u', '', $name);

        return preg_replace('/[^A-Z0-9]+/', '', (string) $name) ?? '';
    }

    /**
     * True when both names are present and name the same holder.
     *
     * An empty name on either side is NOT "the same" — it means we do not know,
     * and callers must treat that as undecided rather than as a match.
     */
    public static function same(?string $a, ?string $b): bool
    {
        $keyA = self::normalise($a);
        $keyB = self::normalise($b);

        return $keyA !== '' && $keyB !== '' && $keyA === $keyB;
    }

    /**
     * True when both names are present and they name different holders.
     *
     * The deliberate counterpart to same(): with one side missing, both return
     * false, so an unknown name never reads as a change of ownership.
     */
    public static function changed(?string $a, ?string $b): bool
    {
        $keyA = self::normalise($a);
        $keyB = self::normalise($b);

        return $keyA !== '' && $keyB !== '' && $keyA !== $keyB;
    }
}
