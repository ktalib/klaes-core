<?php

namespace App\Models\Concerns;

use App\Support\AddressBuilder;

/**
 * Address-builder accessors for models carrying prop_* / addr_* columns.
 *
 *   $case->property_location   "Nassarawa, Nassarawa, Kano"
 *   $ben->person_address       "Ahmadu Bello Way, 4, Nassarawa, Nassarawa, Kano"
 *
 * Also exposes the resolved district/street (the "_other" free text wins over
 * the dropdown value) for tables and detail screens.
 */
trait HasAddressBuilder
{
    /** The eight sub-field suffixes every address group uses. */
    public static function addressSuffixes(): array
    {
        return ['house', 'plot', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'];
    }

    /** Fully-qualified column names for a prefix, e.g. addressColumns('prop_'). */
    public static function addressColumns(string $prefix): array
    {
        return array_map(fn ($s) => $prefix . $s, self::addressSuffixes());
    }

    /** "District, LGA, State" from the prop_* columns. */
    public function getPropertyLocationAttribute(): string
    {
        return AddressBuilder::propertyLocation(AddressBuilder::fromPrefixed($this, 'prop_'));
    }

    /** "Street, Plot|House, District, LGA, State" from the addr_* columns. */
    public function getPersonAddressAttribute(): string
    {
        return AddressBuilder::personAddress(AddressBuilder::fromPrefixed($this, 'addr_'));
    }

    /** District with "Other" free text resolved, for whichever prefix. */
    public function resolvedDistrict(string $prefix = 'prop_'): ?string
    {
        return AddressBuilder::resolve($this->{$prefix . 'district'} ?? null, $this->{$prefix . 'district_other'} ?? null);
    }

    /** Street with "Other" free text resolved, for whichever prefix. */
    public function resolvedStreet(string $prefix = 'addr_'): ?string
    {
        return AddressBuilder::resolve($this->{$prefix . 'street'} ?? null, $this->{$prefix . 'street_other'} ?? null);
    }
}
