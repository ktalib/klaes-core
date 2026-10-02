<?php

namespace App\Services;

use App\Models\AllocationSourceLookup;

/**
 * Translates between the two shapes "Allocation Source" has been stored in.
 *
 * Old shape (still written, still read by the Standalone Unit Application form
 * and by instrument registration):
 *      allocation_source  = 'State Government' | 'Local Government'
 *      allocation_entity  = KSIP/HOUSING/KUNPDA, or a council name
 *
 * New shape (captured on the SuA commissioning form, printed on the sheet):
 *      institution_category = 'GOVERNMENT' | 'OTHER'
 *      institution_name     = a name from allocation_source_lookups
 *      allocation_entity    = the council name, and only when the institution
 *                             is LOCAL GOVERNMENT
 *
 * Nothing was back-filled, so every reader goes through resolve() and every
 * writer through toLegacy(). Keeping both directions in one place is what stops
 * the two shapes drifting apart.
 */
class AllocationSourceResolver
{
    public const CATEGORY_GOVERNMENT = 'GOVERNMENT';
    public const CATEGORY_OTHER      = 'OTHER';

    /** The institution under which a council name is the entity. */
    public const LOCAL_GOVERNMENT = 'LOCAL GOVERNMENT';

    /** The sentinel the dropdowns offer; never stored as an institution name. */
    public const OTHERS_SPECIFY = 'OTHERS (SPECIFY)';

    /**
     * The three state bodies the old form offered, mapped onto the institution
     * names they are known by in the lookup table.
     */
    private const LEGACY_ENTITY_MAP = [
        'KUNPDA'  => 'KANO URBAN PLANNING DEVELOPMENT AGENCY',
        'KAMMA'  => 'KANO URBAN PLANNING DEVELOPMENT AGENCY',
        'HOUSING' => 'KANO STATE MINISTRY OF HOUSING',
        'KSIP'    => 'KSIP',
    ];

    /** 'GOVERNMENT' or 'OTHER' for anything else — never null. */
    public static function normalizeCategory($category): string
    {
        return strcasecmp(trim((string) $category), self::CATEGORY_OTHER) === 0
            ? self::CATEGORY_OTHER
            : self::CATEGORY_GOVERNMENT;
    }

    /** True when the institution is the one that needs a council name alongside it. */
    public static function isLocalGovernment(?string $institution): bool
    {
        return strcasecmp(trim((string) $institution), self::LOCAL_GOVERNMENT) === 0;
    }

    /**
     * Read either shape off a row and return the new one.
     *
     * @param  object|array|null $row  anything carrying the allocation columns
     * @return array{category: string|null, institution: string|null, entity: string|null}
     */
    public static function resolve($row): array
    {
        $get = static function ($key) use ($row) {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }

            return is_object($row) ? ($row->{$key} ?? null) : null;
        };

        $institution = trim((string) $get('institution_name'));
        $entity      = trim((string) $get('allocation_entity')) ?: null;

        // Answered on the new form: take it as given.
        if ($institution !== '') {
            return [
                'category'    => self::normalizeCategory($get('institution_category')),
                'institution' => $institution,
                'entity'      => self::isLocalGovernment($institution) ? $entity : null,
            ];
        }

        $source = trim((string) $get('allocation_source'));

        if ($source === '' && $entity === null) {
            return ['category' => null, 'institution' => null, 'entity' => null];
        }

        // Legacy 'Local Government': the council name was the entity, and the
        // institution it was allocated by is the local government itself.
        if (strcasecmp($source, 'Local Government') === 0) {
            return [
                'category'    => self::CATEGORY_GOVERNMENT,
                'institution' => self::LOCAL_GOVERNMENT,
                'entity'      => $entity,
            ];
        }

        // Legacy 'State Government': the entity WAS the institution, under one
        // of three short names.
        $mapped = null;
        foreach (self::LEGACY_ENTITY_MAP as $legacy => $name) {
            if ($entity !== null && strcasecmp($entity, $legacy) === 0) {
                $mapped = $name;
                break;
            }
        }

        return [
            'category'    => self::CATEGORY_GOVERNMENT,
            'institution' => $mapped ?: $entity,
            'entity'      => null,
        ];
    }

    /**
     * The legacy pair to store alongside the new columns, so every existing
     * reader keeps working and the `in:State Government,Local Government`
     * validation rules elsewhere still pass.
     *
     * $category is accepted for symmetry with resolve() but does not affect the
     * result: the old column only ever distinguished a council allocation from
     * everything else, and the institution alone decides that.
     *
     * @return array{allocation_source: string, allocation_entity: string|null}
     */
    public static function toLegacy(?string $category, ?string $institution, ?string $entity = null): array
    {
        $institution = trim((string) $institution) ?: null;
        $entity      = trim((string) $entity) ?: null;

        if (self::isLocalGovernment($institution)) {
            return [
                'allocation_source' => 'Local Government',
                'allocation_entity' => $entity,
            ];
        }

        // Everything else — a state agency or a non-government institution — is
        // filed under the only other value the old column accepts. The legacy
        // column is half the width of institution_name, so a long typed name is
        // clipped rather than failing the insert; institution_name keeps it whole.
        return [
            'allocation_source' => 'State Government',
            'allocation_entity' => $institution === null ? null : mb_substr($institution, 0, 100),
        ];
    }

    /**
     * What the recipient block prints: the institution, with the council name
     * substituted when the institution is the generic LOCAL GOVERNMENT.
     */
    public static function displayInstitution(?string $institution, ?string $entity = null): ?string
    {
        $institution = trim((string) $institution) ?: null;
        $entity      = trim((string) $entity) ?: null;

        if (self::isLocalGovernment($institution) && $entity) {
            return rtrim($entity, ' ') . ' LOCAL GOVERNMENT';
        }

        return $institution;
    }

    /** The lookup type holding the institutions for a category. */
    public static function institutionType(?string $category): string
    {
        return self::normalizeCategory($category) === self::CATEGORY_OTHER
            ? AllocationSourceLookup::TYPE_INSTITUTION_OTHER
            : AllocationSourceLookup::TYPE_INSTITUTION_GOVERNMENT;
    }

    /** The lookup type holding the addressees for a category. */
    public static function addresseeType(?string $category): string
    {
        return self::normalizeCategory($category) === self::CATEGORY_OTHER
            ? AllocationSourceLookup::TYPE_ADDRESSED_TO_OTHER
            : AllocationSourceLookup::TYPE_ADDRESSED_TO_GOVERNMENT;
    }
}
