<?php

namespace App\Services\Cadastral;

use App\Support\AddressBuilder;

/**
 * The one place the Cadastral module handles address-builder input.
 *
 * Composition itself is App\Support\AddressBuilder — the same class the models'
 * property_location / person_address accessors call (via HasAddressBuilder) —
 * so what is validated here, what is stored, and what every screen and print
 * shows are built the same way:
 *
 *   property location  ->  "District, LGA, State"           (never the plot)
 *   person address     ->  "Street, Plot|House, District, LGA, State"
 *
 * This class adds what the Cadastral forms need on top of the Survey contract:
 *  - one validation rule set and message set for every controller;
 *  - server-side clean-up of the "Other" boxes, which the browser does too but
 *    cannot be relied on (Select2 off, a hand-built request);
 *  - keeping prop_plot in step with a record's own plot_no column, so a
 *    record never carries two plot numbers that disagree;
 *  - copying the whole address group from a parent record, not half of it.
 */
class CadastralAddress
{
    /** Validation rules for one address group — the Survey rule set, unchanged. */
    public static function rules(string $prefix, bool $required = true): array
    {
        return AddressBuilder::rules($prefix, $required);
    }

    /** The messages every Cadastral form shows for an address group. */
    public static function messages(string $prefix): array
    {
        return [
            $prefix . 'district.required'          => 'The district is required.',
            $prefix . 'district_other.required_if' => 'Please specify the district.',
            $prefix . 'street_other.required_if'   => 'Please specify the street.',
            $prefix . 'lga.required'               => 'The LGA is required.',
            $prefix . 'state.required'             => 'The state is required.',
        ];
    }

    /**
     * Tidy a validated address group before it is saved.
     *
     * Only keys present in $data are touched, so a partial update (the surveyor
     * suspend/reinstate button posts no address at all) never blanks a column.
     *
     * @param  string|null  $plotColumn  the record's own plot column (plot_no),
     *                                   when it has one. It is the plot field the
     *                                   form shows, and prop_plot follows it.
     */
    public static function normalise(array $data, string $prefix, ?string $plotColumn = null): array
    {
        foreach (AddressBuilder::columns($prefix) as $col) {
            if (array_key_exists($col, $data) && is_string($data[$col])) {
                $data[$col] = trim($data[$col]) === '' ? null : trim($data[$col]);
            }
        }

        // A "specify" box only means something while its dropdown reads Other.
        // Left behind, it would silently win over the dropdown when composing.
        foreach (['district', 'street'] as $kind) {
            $select = $prefix . $kind;
            $other  = $prefix . $kind . '_other';

            if (array_key_exists($select, $data) && strcasecmp((string) $data[$select], 'Other') !== 0) {
                $data[$other] = null;
            }
        }

        if ($plotColumn !== null && array_key_exists($plotColumn, $data)) {
            $data[$prefix . 'plot'] = $data[$plotColumn];
        }

        return $data;
    }

    /**
     * The full address group of a parent record, for pre-filling a child form.
     *
     * Copying district without district_other turns an "Other" district into a
     * blank one that fails validation; the whole group travels together.
     *
     * @param  object|array|null  $source
     * @param  string|null        $sourcePlotColumn  the parent's own plot column,
     *                                               preferred over its prop_plot
     */
    public static function inherit($source, string $prefix = 'prop_', ?string $sourcePlotColumn = null): array
    {
        $out = [];
        foreach (AddressBuilder::fromPrefixed($source ?? [], $prefix) as $suffix => $value) {
            $out[$prefix . $suffix] = $value;
        }

        if ($sourcePlotColumn !== null && $source !== null) {
            $plot = is_array($source) ? ($source[$sourcePlotColumn] ?? null) : ($source->{$sourcePlotColumn} ?? null);
            if (trim((string) $plot) !== '') $out[$prefix . 'plot'] = $plot;
        }

        $out[$prefix . 'state'] = $out[$prefix . 'state'] ?: 'Kano';

        return $out;
    }

    /** "District, LGA, State" from prop_* keys — never the plot. */
    public static function propertyLocation($source, string $prefix = 'prop_'): string
    {
        return AddressBuilder::propertyLocation(AddressBuilder::fromPrefixed($source, $prefix));
    }

    /** "Street, Plot|House, District, LGA, State" from addr_* keys. */
    public static function personAddress($source, string $prefix = 'addr_'): string
    {
        return AddressBuilder::personAddress(AddressBuilder::fromPrefixed($source, $prefix));
    }
}
