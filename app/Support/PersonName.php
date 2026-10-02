<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * One case for a person's or company's name on screen.
 *
 * Names reach the registry in whatever case the officer typed: "IBRAHIM DANLADI" from
 * one capture, "Murtala Muhammad Illallah" from the next. Both are the same fact
 * recorded two ways, and a register listing them side by side reads as though the
 * difference means something.
 *
 * Title case, not upper: this is for LISTS, which are scanned rather than read, and a
 * column of shouting is harder to scan than a column of names. The printed
 * instruments keep their Str::upper() — a memo is a formal document and the Ministry
 * sets names in capitals there — so the two conventions are deliberate, not a drift.
 *
 * DISPLAY ONLY. Nothing here is written back: the stored value is what the officer
 * entered and what every match, lookup and lineage comparison already runs against.
 * Normalising the column would silently rewrite hundreds of rows to settle a visual
 * complaint, and would break any comparison that expects what was typed.
 */
class PersonName
{
    /**
     * "IBRAHIM DANLADI" and "ibrahim danladi" both become "Ibrahim Danladi".
     *
     * Runs of whitespace collapse — a double space between names is never meaningful.
     */
    public static function display(?string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '') {
            return '';
        }

        // A name already in mixed case was typed the way its owner writes it — leave
        // it alone. Only the all-one-case values are ambiguous enough to restyle, and
        // this is what stops "McDonald" being flattened to "Mcdonald".
        if ($name !== Str::upper($name) && $name !== Str::lower($name)) {
            return $name;
        }

        // MB_CASE_TITLE restarts after any non-letter, so hyphenated names come out
        // right on their own: DAN-FODIO -> Dan-Fodio.
        $titled = Str::title($name);

        // The apostrophe is the exception it does NOT handle — O'BRIEN comes back as
        // "O'brien". Only a one- or two-letter prefix is capitalised after it, which
        // catches O'Brien and D'Angelo while leaving a possessive ("Ahmed's Ventures")
        // as it should be.
        return preg_replace_callback(
            "/\b(\pL{1,2})'(\pL)/u",
            fn ($m) => $m[1] . "'" . Str::upper($m[2]),
            $titled
        );
    }
}
