<?php

namespace App\Services;

/**
 * The 44 Local Government Areas of Kano State, in the full form stored on an Occupancy
 * Permit issued by an LGA rather than by the State Government.
 *
 * WHY A LIST AND NOT THE `lgas` TABLE: the value written to `pra.party_1` has to be
 * identical across the three screens that capture an OP (the File Indexing transaction
 * history card, the PRA form, and the Occupancy Permit section of the create-indexing
 * card), and it has to stay identical over time so those permits can be grouped by
 * issuing LGA later. The `lgas` table is a lookup for property location: it carries an
 * extra "Unknown" row, its names have no "Local Government" suffix, and rows there can be
 * deactivated or renamed without anyone thinking about permits. This list is the
 * membership of Kano State — a fixed fact — so it is stated once, here.
 *
 * The short names mirror the 44 real rows of the `lgas` table exactly, so a full name can
 * be reduced back to a location LGA with shortName().
 *
 * Note "Garko", not "Garki" — Garki is a Jigawa State LGA and has no place in this list.
 */
class KanoLgaDirectory
{
    /** The suffix that turns a location LGA into the name of the issuing authority. */
    public const SUFFIX = ' Local Government';

    /** @var list<string> The 44, alphabetical — the order they are offered in. */
    private const SHORT_NAMES = [
        'Ajingi',
        'Albasu',
        'Bagwai',
        'Bebeji',
        'Bichi',
        'Bunkure',
        'Dala',
        'Dambatta',
        'Dawakin Kudu',
        'Dawakin Tofa',
        'Doguwa',
        'Fagge',
        'Gabasawa',
        'Garko',
        'Garun Mallam',
        'Gaya',
        'Gezawa',
        'Ghari',
        'Gwale',
        'Gwarzo',
        'Kabo',
        'Kano Municipal',
        'Karaye',
        'Kibiya',
        'Kiru',
        'Kumbotso',
        'Kura',
        'Madobi',
        'Makoda',
        'Minjibir',
        'Nasarawa',
        'Rano',
        'Rimin Gado',
        'Rogo',
        'Shanono',
        'Sumaila',
        'Takai',
        'Tarauni',
        'Tofa',
        'Tsanyawa',
        'Tudun Wada',
        'Ungogo',
        'Warawa',
        'Wudil',
    ];

    /**
     * The 44 issuing-authority names, e.g. "Fagge Local Government".
     * This is what a dropdown offers and what gets saved as party_1.
     *
     * @return list<string>
     */
    public function fullNames(): array
    {
        return array_map(static fn(string $name): string => $name . self::SUFFIX, self::SHORT_NAMES);
    }

    /**
     * The bare LGA names, matching the `lgas` lookup table.
     *
     * @return list<string>
     */
    public function shortNames(): array
    {
        return self::SHORT_NAMES;
    }

    /**
     * Is this string one of the 44 issuing authorities?
     *
     * Tolerant of case and spacing, and of a value stored without the suffix, because the
     * same column also holds free-typed history ("KANO STATE GOVERNMENT", a person's name).
     */
    public function isLgaAuthority(?string $value): bool
    {
        return $this->shortName($value) !== null;
    }

    /**
     * Reduce an issuing-authority name back to its `lgas` table name, or null when the
     * value is not one of the 44 — "Fagge Local Government" and "fagge" both give "Fagge".
     */
    public function shortName(?string $value): ?string
    {
        $needle = strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));
        if ($needle === '') {
            return null;
        }

        $suffix = strtoupper(self::SUFFIX);
        if (str_ends_with($needle, $suffix)) {
            $needle = trim(substr($needle, 0, -strlen($suffix)));
        }

        foreach (self::SHORT_NAMES as $short) {
            if (strtoupper($short) === $needle) {
                return $short;
            }
        }

        return null;
    }

    /**
     * The issuing-authority name for a bare LGA, or null when it is not one of the 44.
     * Lets a screen pre-select the dropdown from the file's existing location LGA.
     */
    public function fullName(?string $value): ?string
    {
        $short = $this->shortName($value);

        return $short === null ? null : $short . self::SUFFIX;
    }
}
