<?php

namespace App\Support;

/**
 * A stand-in typed into a required phone field because the applicant gave none —
 * 08000000000, 00000000000, 08012345678 — or the system's own SYS-######## filler.
 *
 * The SMS gateway accepts these numbers and bills for them, but nobody holds the
 * line, so the applicant is never told. Anything that texts an applicant asks here
 * first and records "not sent: placeholder number" instead of sending, and the
 * commissioning checklist shows that flag.
 *
 * Extra exact numbers can be listed in config('klaes_sms.placeholder_phones').
 */
class PlaceholderPhone
{
    public const REASON = 'The number on file is a placeholder (e.g. 08000000000) entered because the applicant gave no phone.';

    public static function is(?string $raw): bool
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return false; // blank is "no phone", reported separately
        }
        if (stripos($raw, 'SYS-') === 0) {
            return true;
        }

        $national = self::national($raw);
        if ($national === '') {
            return false;
        }

        foreach ((array) config('klaes_sms.placeholder_phones', []) as $listed) {
            if (self::national((string) $listed) === $national) {
                return true;
            }
        }

        return preg_match('/^(\d)\1+$/', $national) === 1          // 00000000000, 11111111111
            || preg_match('/^0[789][01]?0{7,}$/', $national) === 1   // 08000000000, 08100000000
            || in_array($national, ['08012345678', '07012345678', '09012345678', '01234567890', '12345678901', '08123456789'], true);
    }

    /** Digits in local form: +234 803... and 234803... both read as 0803... */
    private static function national(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0' . substr($digits, 3);
        }
        if (strlen($digits) === 10 && in_array($digits[0], ['7', '8', '9'], true)) {
            return '0' . $digits;
        }
        return $digits;
    }
}
