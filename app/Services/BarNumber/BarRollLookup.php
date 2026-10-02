<?php

namespace App\Services\BarNumber;

/**
 * An external check of a Call-to-Bar number against a roll of legal practitioners.
 *
 * No such API is wired up for Nigeria today — NullBarRollLookup is bound by
 * default and answers "unknown" to everything. The interface exists so a real
 * roll (Nigerian Bar Association, Supreme Court roll, a ministry service) can be
 * added later without touching the verification workflow or the payment flow.
 */
interface BarRollLookup
{
    /**
     * Is this number on the roll, held by this name?
     *
     * @return bool|null  true  = confirmed on the roll
     *                    false = positively rejected (not on the roll)
     *                    null  = could not be determined; no service, a timeout,
     *                            or an inconclusive answer. Callers MUST treat
     *                            null as "unknown", never as a rejection.
     */
    public function lookup(string $barNumber, string $name): ?bool;

    /** Is a roll actually reachable? Lets callers record why nothing was checked. */
    public function isAvailable(): bool;
}
