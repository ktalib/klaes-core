<?php

namespace App\Support;

class SltrDigitRank
{
    public static function fromFileNumber(?string $fileNumber): ?int
    {
        $fileNumber = strtoupper(trim((string) $fileNumber));

        if ($fileNumber === '' || !str_contains($fileNumber, 'SLTR')) {
            return null;
        }

        if (!preg_match('/(\d+)(?!.*\d)/', $fileNumber, $matches)) {
            return null;
        }

        return strlen($matches[1]);
    }
}
