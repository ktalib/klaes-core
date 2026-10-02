<?php

namespace App\Services;

/**
 * The Type and Category an instrument is captured with.
 *
 * Every capture screen asks the same three questions in the same order:
 *
 *   Instrument Type   what the paper is                (dbo.InstrumentTypes, free-growing)
 *   Type              who granted it, or which variant  (this catalogue)
 *   Category          which generation it is: Old / New (this catalogue)
 *
 * Type and Category are ONE field each. Their options change with the instrument,
 * and an instrument that has none simply hides them:
 *
 *   Plot Allocation Letter    Ministry, LGA, Urban Development Board | —
 *   Occupancy Permit          Resettlement, Direct Allocation, LGA   | Old, New
 *   Certificate of Occupancy  Land, Old KANGIS, New KANGIS, LGA      | Old, New
 *   Right of Occupancy        Ministry, LGA                          | Old, New
 *
 * This class is the only copy of that table. The transaction card, the PRA card and
 * the File Index occupancy-permit section all render it, so adding a value is one
 * edit here rather than three that can silently drift apart.
 *
 * WHERE EACH ANSWER IS STORED
 * Type keeps the column its instrument has always used, so nothing downstream has
 * to change: op_type for an Occupancy Permit, cofo_type for a Certificate of
 * Occupancy. Right of Occupancy and Plot Allocation Letter never had one, so they use
 * instrument_subtype. Category is new to all of them and is always instrument_category.
 * typeColumnFor() is the authority on that mapping.
 *
 * MATCHING IS FORGIVING
 * Stored transaction types are inconsistent — 'Occupancy Permit (OP)',
 * 'OCCUPANCY PERMIT', a trailing "\r\n" from the InstrumentTypes import — so
 * lookups normalise case and whitespace and match the KEY as a substring. That is
 * also why ST/SLTR Certificate of Occupancy resolve to the CofO entry — they take the
 * same Types, since the instrument name already says which of the two they are.
 */
class InstrumentTypeCatalog
{
    /** Storage column for the Category answer, whatever the instrument. */
    public const CATEGORY_COLUMN = 'instrument_category';

    /** Storage column for the Type answer when the instrument has no column of its own. */
    public const GENERIC_TYPE_COLUMN = 'instrument_subtype';

    /**
     * Keyed by a normalised fragment of the instrument type, longest key first so
     * 'certificate of occupancy' cannot be claimed by a shorter entry.
     *
     * 'type_column' names where the Type answer is written.
     * An entry with empty types AND empty categories still belongs here — it is how
     * the screens know to hide both fields rather than guess.
     */
    private const CATALOG = [
        'plot allocation letter' => [
            'label' => 'Plot Allocation Letter',
            // The body that issued the letter: the Ministry, a Local Government, or the
            // Urban Development Board. An LGA-issued one is a Plot Allocation Letter of
            // Type 'LGA' -- it is NOT a separate instrument type, which is what
            // 'LGA Allocation Letter' briefly was.
            'types' => ['Ministry', 'LGA', 'Urban Development Board'],
            // The only instrument with no Category: a letter has no Old/New generation.
            'categories' => [],
            'type_column' => self::GENERIC_TYPE_COLUMN,
        ],
        'certificate of occupancy' => [
            'label' => 'Certificate of Occupancy',
            // Which registry issued the certificate, plus the Local-Government-issued one.
            // The stored values keep their existing spelling — ~15,500 CofO_staging rows use
            // them and every reader expects them — so only the LABELS read as the table does.
            // 'LGA CofO' follows that same spelling; the screens recognise an LGA-issued
            // instrument by the LABEL, not the value, so it needs no rule of its own — see
            // isLgaType() in public/js/pra/form-controller.js.
            //
            // SLTR and ST are deliberately NOT offered: they are their own instrument types,
            // so the instrument already says which it is. The ~444 rows still storing
            // 'SLTR CofO' / 'ST CofO' are not lost: a stored value this list does not
            // carry stays selectable as '(legacy)', so loading such a record cannot
            // silently blank its Type.
            'types' => [
                'Land CofO' => 'Land',
                'KANGIS CofO - Old' => 'Old KANGIS',
                'KANGIS CofO - New' => 'New KANGIS',
                'LGA CofO' => 'LGA',
            ],
            'categories' => ['Old', 'New'],
            'type_column' => 'cofo_type',
        ],
        'right of occupancy' => [
            'label' => 'Right of Occupancy',
            // Nothing stored these before, so the values are the labels.
            'types' => ['Ministry', 'LGA'],
            'categories' => ['Old', 'New'],
            'type_column' => self::GENERIC_TYPE_COLUMN,
        ],
        'occupancy permit' => [
            'label' => 'Occupancy Permit',
            // Values keep the historic 'OP ' prefix on the first two: 4,525 pra rows
            // carry them (alongside 29,444 unprefixed ones), and a stored row has to
            // re-select on load. LGA has no prefix and no rows either way.
            'types' => [
                'OP Resettlement' => 'Resettlement',
                'OP Direct Allocation' => 'Direct Allocation',
                'LGA' => 'LGA',
            ],
            'categories' => ['Old', 'New'],
            'type_column' => 'op_type',
        ],
    ];

    /** Normalise a stored or typed instrument name for matching. */
    public static function normalize(?string $instrumentType): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $instrumentType)));
    }

    /** The catalogue entry for an instrument, or null when it has neither field. */
    public static function entryFor(?string $instrumentType): ?array
    {
        $needle = self::normalize($instrumentType);
        if ($needle === '') {
            return null;
        }

        foreach (self::CATALOG as $key => $entry) {
            if (str_contains($needle, $key)) {
                return $entry;
            }
        }

        return null;
    }

    /** Type options as value => label, empty when the instrument has no Type. */
    public static function typesFor(?string $instrumentType): array
    {
        $types = self::entryFor($instrumentType)['types'] ?? [];

        // A plain list means value and label are the same thing.
        return array_is_list($types) ? array_combine($types, $types) : $types;
    }

    /** Category options as value => label, empty when the instrument has no Category. */
    public static function categoriesFor(?string $instrumentType): array
    {
        $categories = self::entryFor($instrumentType)['categories'] ?? [];

        return array_combine($categories, $categories);
    }

    /** Which column the Type answer is written to for this instrument. */
    public static function typeColumnFor(?string $instrumentType): string
    {
        return self::entryFor($instrumentType)['type_column'] ?? self::GENERIC_TYPE_COLUMN;
    }

    /**
     * The whole catalogue in the shape the browser needs:
     * [{ key, label, types: [{value,label}], categories: [{value,label}] }, ...]
     *
     * Order is preserved, so the longest-key-first rule the PHP matcher relies on
     * holds in JavaScript too.
     */
    public static function forJs(): array
    {
        $out = [];

        foreach (self::CATALOG as $key => $entry) {
            $types = array_is_list($entry['types'])
                ? array_combine($entry['types'], $entry['types'])
                : $entry['types'];

            $out[] = [
                'key' => $key,
                'label' => $entry['label'],
                'types' => self::pairs($types),
                'categories' => self::pairs(array_combine($entry['categories'], $entry['categories'])),
            ];
        }

        return $out;
    }

    private static function pairs(array $map): array
    {
        $pairs = [];
        foreach ($map as $value => $label) {
            $pairs[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $pairs;
    }
}
