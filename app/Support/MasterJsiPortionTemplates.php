<?php

namespace App\Support;

/**
 * The five Master JSI sheets, as the Ministry writes them.
 *
 * Each parcel update states its measurements in its own shape — an Extension names
 * the title, the strip being added and the sum of the two; a Merger names the
 * portions going in and the parcel coming out; a Change of Purpose names a land use
 * on each line and no areas at all. The narrative paragraph above the table changes
 * with it.
 *
 * One place, because the alternative is five nearly-identical blades that drift:
 * the form renders whichever rows the chosen type names, the print sheet reads the
 * same list, and adding a sixth parcel update is an entry here rather than a new
 * screen.
 *
 * Row shape:
 *   role        stable key stored on master_jsi_portions.role — never shown
 *   label       what the PORTION column prints
 *   land_use    the row carries a land use instead of / beside an area
 *   count       the row carries a count ("No of Subdivision")
 *   derived     the row is the RESULT the officer recommends, not an observation;
 *               rendered last and highlighted so the sheet reads as an answer
 *
 * Separation deliberately shares the Subdivision sheet — the Ministry sample is one
 * page headed "SUBDIVISION / SEPERATION".
 */
class MasterJsiPortionTemplates
{
    public const TEMPLATES = [
        'extension' => [
            'heading'   => 'Extension',
            'narrative' => 'This is an application for Extension over a title. The existing title, the extension portion and the recommended measurement are stated below.',
            'rows'      => [
                ['role' => 'existing_title',      'label' => 'Existing Title'],
                ['role' => 'extension_portion',   'label' => 'Extension Portion'],
                ['role' => 'recommended_portion', 'label' => 'Recommended Site Portion', 'derived' => true],
            ],
        ],

        'merger' => [
            'heading'   => 'Merger',
            'narrative' => 'Physical site inspection was conducted and the site found to be available on ground. This is an application for the merger of the portions below into one portion.',
            // Portion A and B are the sample; a merger of more parcels adds rows,
            // so the form lets these repeat.
            'repeatable' => 'portion',
            'rows'       => [
                ['role' => 'portion_a',      'label' => 'Portion A'],
                ['role' => 'portion_b',      'label' => 'Portion B'],
                ['role' => 'merged_portion', 'label' => 'Recommended Merged Portion', 'derived' => true],
            ],
        ],

        'subdivision' => [
            'heading'   => 'Subdivision / Separation',
            'narrative' => 'A joint site inspection revealed that the site is for subdivision. The site was found available and conforms with the surrounding land use.',
            'rows'       => [
                ['role' => 'existing_site',      'label' => 'Existing Site'],
                ['role' => 'subdivision_units',  'label' => 'No of Subdivision', 'count' => true],
                ['role' => 'average_size',       'label' => 'Average Size'],
                ['role' => 'recommended_site',   'label' => 'Recommended Site Measurement', 'derived' => true],
            ],
        ],

        'change_of_purpose' => [
            'heading'   => 'Change of Purpose',
            'narrative' => 'This is an application for change of purpose. The prevailing land use, conformity with the surrounding land use and the recommended land use are stated below.',
            'rows'      => [
                ['role' => 'existing_land_use',    'label' => 'Existing Land Use',    'land_use' => true],
                ['role' => 'recommended_land_use', 'label' => 'Recommended Land Use', 'land_use' => true, 'derived' => true],
            ],
        ],
    ];

    /**
     * Separation prints the Subdivision sheet — one page covers both.
     *
     * Kept as an alias rather than a copied entry so a change to the subdivision
     * sheet cannot leave separation printing last month's wording.
     */
    public const ALIASES = [
        'separation' => 'subdivision',
    ];

    /** Every type that has a sheet, including the aliased ones. */
    public static function types(): array
    {
        return array_merge(array_keys(self::TEMPLATES), array_keys(self::ALIASES));
    }

    public static function has(?string $type): bool
    {
        return $type !== null && in_array($type, self::types(), true);
    }

    /** The sheet for a type, or null when the type has none. */
    public static function for(?string $type): ?array
    {
        if ($type === null) {
            return null;
        }

        $key = self::ALIASES[$type] ?? $type;

        return self::TEMPLATES[$key] ?? null;
    }

    /** Just the row definitions, each with its defaults filled in. */
    public static function rows(?string $type): array
    {
        $template = self::for($type);

        if ($template === null) {
            return [];
        }

        $sn = 0;

        return array_map(function (array $row) use (&$sn) {
            return [
                'sn'       => ++$sn,
                'role'     => $row['role'],
                'label'    => $row['label'],
                'land_use' => $row['land_use'] ?? false,
                'count'    => $row['count'] ?? false,
                'derived'  => $row['derived'] ?? false,
            ];
        }, $template['rows']);
    }

    public static function narrative(?string $type): string
    {
        return self::for($type)['narrative'] ?? '';
    }

    public static function heading(?string $type): string
    {
        return self::for($type)['heading'] ?? '';
    }

    /** Whether the officer may add more observation rows (a merger of 3+ parcels). */
    public static function repeatable(?string $type): ?string
    {
        return self::for($type)['repeatable'] ?? null;
    }
}
