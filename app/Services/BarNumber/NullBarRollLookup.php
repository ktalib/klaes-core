<?php

namespace App\Services\BarNumber;

/**
 * The default: no roll of legal practitioners is available to this deployment.
 *
 * Answers "unknown" rather than "rejected" — an absent service says nothing about
 * whether a practitioner is genuine, and rejecting on its silence would block
 * every lawyer from completing a search.
 */
class NullBarRollLookup implements BarRollLookup
{
    public function lookup(string $barNumber, string $name): ?bool
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
