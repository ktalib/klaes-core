<?php

namespace App\Models\Concerns;

/**
 * Sequence numbering for SPAS reference numbers.
 *
 * SPAS references were issued with a "SPA" prefix and are issued with "SPAS"
 * from 2026-08-18 on. Numbers already printed on paper are NOT rewritten, so
 * both forms exist side by side and every generator has to read both:
 *
 *   SPA/2026/0001       →  SPAS/2026/0002        (memo)
 *   SPA-COP/2026/0001   →  SPAS-COP/2026/0002    (change of purpose sheet)
 *   SPA-BILL-2026-001   →  SPAS-BILL-2026-002    (bill)
 *
 * Reading only the new prefix would restart the count at 1 and hand out a
 * sequence number already in use that year — two documents differing by one
 * letter, which is exactly the kind of thing that goes unnoticed until two
 * files claim the same reference.
 */
trait GeneratesSpaReference
{
    /**
     * The next sequence number, given every reference issued this year under
     * either prefix.
     *
     * The trailing digit group is what counts, so this works whichever
     * separator a reference uses ("/0001" or "-001").
     *
     * @param  iterable<string|null>  $existing
     */
    protected static function nextSpaSequence(iterable $existing): int
    {
        $highest = 0;

        foreach ($existing as $reference) {
            if (preg_match('/(\d+)$/', (string) $reference, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        return $highest + 1;
    }

    /**
     * A LIKE pattern matching both the old "SPA" and the new "SPAS" prefix.
     *
     * `SPA%` rather than two queries: the wildcard matches nothing for the old
     * form and the "S" for the new one.
     */
    protected static function spaPrefixPattern(string $tail): string
    {
        return 'SPA%'.$tail;
    }
}
