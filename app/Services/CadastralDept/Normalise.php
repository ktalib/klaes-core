<?php

namespace App\Services\CadastralDept;

/**
 * Text clean-up before saving (development standard 3): one rule per kind of
 * field, never blanket upper or title case. File numbers are never passed
 * through here; they come from the records exactly as written.
 */
final class Normalise
{
    /** Free text (reasons, remarks): trimmed, inner whitespace collapsed, case kept. */
    public static function text(?string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $s));
    }

    /** Codes (block no., layout no.): trimmed, collapsed, upper case. */
    public static function code(?string $s): ?string
    {
        $s = mb_strtoupper(self::text($s));

        return $s === '' ? null : $s;
    }

    /**
     * Names of units and places: each word capitalised, except words typed as
     * short all-capital acronyms (KAMMA, DSG, KNUPDA), which are kept, and short
     * joining words after the first (of, and, the ...), which are lower case.
     */
    public static function name(?string $s): string
    {
        $words = explode(' ', self::text($s));

        return implode(' ', array_map(function ($w, $i) {
            if ($w === '') {
                return $w;
            }
            if ($i > 0 && in_array(mb_strtolower($w), ['of', 'and', 'the', 'for', 'in', 'on', 'at', 'to'], true)) {
                return mb_strtolower($w);
            }
            if (preg_match('/^[A-Z0-9&.\/-]{2,6}$/u', $w)) {
                return $w;
            }

            return mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1));
        }, $words, array_keys($words)));
    }
}
