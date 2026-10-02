<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Composes KLAES address strings from address-builder sub-fields.
 *
 * Two formats:
 *
 *   property location  ->  "District, LGA, State"
 *   person address     ->  "Street, Plot|House, District, LGA, State"
 *
 * Street, district and LGA are multi-select. A parcel can straddle districts and
 * a corner plot can front two streets, so each of those columns may hold several
 * names joined with " | " for storage, and rendered joined with " & " inside its
 * own segment so the commas still separate segments:
 *
 *   "Gwale & Fagge, Gwale & Fagge, Kano"
 *
 * Other rules:
 *  - A "*_other" value wins over its dropdown value (the dropdown reads "Other").
 *  - Plot number wins over house number. If only a house number is present, that
 *    is used instead. They never both appear.
 *  - Empty segments are dropped, so no stray commas.
 *
 * Property location never contains the plot number: plot lives in its own field
 * and is shown separately. This matches the convention used elsewhere in KLAES.
 */
class AddressBuilder
{
    /** How several selections are stored in one column. */
    public const STORE_GLUE = ' | ';

    /** How several selections read inside one segment of the composed string. */
    public const SHOW_GLUE = ' & ';

    /** The sub-fields that accept more than one value. */
    public const MULTI = ['street', 'district', 'lga'];

    /** Every sub-field suffix an address group carries. */
    public const SUFFIXES = ['house', 'plot', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'];

    /**
     * Split a stored column back into its individual values.
     *
     * Accepts an array (straight off a multi-select request) or a stored string.
     *
     * @param  array<int,string>|string|null $value
     * @return array<int,string>
     */
    public static function split($value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $value = (string) $value;
            if (trim($value) === '') return [];
            $parts = explode('|', $value);
        }

        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    /**
     * Collapse one or many values into the single string the column stores.
     *
     * @param  array<int,string>|string|null $value
     */
    public static function toColumn($value): ?string
    {
        $parts = self::split($value);

        return $parts ? implode(self::STORE_GLUE, $parts) : null;
    }

    /**
     * Resolve a dropdown value against its "Other" free-text counterpart.
     *
     * With several values selected they are joined with " & ". "Other" is
     * dropped from the list and replaced by the free text, so picking
     * Gwale + Other with "New Layout" typed reads "Gwale & New Layout".
     *
     * @param array<int,string>|string|null $value
     */
    public static function resolve($value, ?string $other = null): ?string
    {
        $other  = trim((string) $other);
        $values = self::split($value);

        $kept = [];
        $sawOther = false;
        foreach ($values as $v) {
            if (strcasecmp($v, 'Other') === 0) { $sawOther = true; continue; }
            $kept[] = $v;
        }

        // The free text stands in for the Other entry, and only for that. A
        // value left in the box without Other selected is stale — the UI clears
        // it on deselect, so honouring it here would resurrect it.
        if ($sawOther && $other !== '') {
            $kept[] = $other;
        }

        $kept = array_values(array_unique($kept));

        return $kept ? implode(self::SHOW_GLUE, $kept) : null;
    }

    /**
     * Join non-empty segments with ", ".
     *
     * @param array<int,string|null> $parts
     */
    public static function join(array $parts): string
    {
        $clean = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') $clean[] = $p;
        }

        return implode(', ', $clean);
    }

    /**
     * Property / parcel location: "District, LGA, State".
     *
     * @param array<string,mixed> $d Keys: district, district_other, lga, state
     */
    public static function propertyLocation(array $d): string
    {
        return self::join([
            self::resolve($d['district'] ?? null, $d['district_other'] ?? null),
            self::resolve($d['lga'] ?? null),
            $d['state'] ?? null,
        ]);
    }

    /**
     * Person address: "Street, Plot|House, District, LGA, State".
     *
     * Plot number takes precedence over house number; only one is emitted.
     *
     * @param array<string,mixed> $d Keys: house, plot, street, street_other,
     *                               district, district_other, lga, state
     */
    public static function personAddress(array $d): string
    {
        $plot  = trim((string) ($d['plot'] ?? ''));
        $house = trim((string) ($d['house'] ?? ''));
        $unit  = $plot !== '' ? $plot : $house;

        return self::join([
            self::resolve($d['street'] ?? null, $d['street_other'] ?? null),
            $unit,
            self::resolve($d['district'] ?? null, $d['district_other'] ?? null),
            self::resolve($d['lga'] ?? null),
            $d['state'] ?? null,
        ]);
    }

    /**
     * Validation rules for one address group.
     *
     * Street, district and LGA arrive as arrays from the multi-selects. The
     * "_other" box is required only while its dropdown includes Other, which
     * required_if cannot express against an array — hence Rule::requiredIf with
     * a callback over the current request.
     *
     * @return array<string,mixed>
     */
    public static function rules(string $prefix, bool $requireLocation = true): array
    {
        $req = $requireLocation ? 'required' : 'nullable';

        $picked = fn (string $suffix): bool => in_array(
            'Other',
            self::split(request()->input($prefix . $suffix)),
            true
        );

        return [
            $prefix . 'house'    => 'nullable|string|max:100',
            $prefix . 'plot'     => 'nullable|string|max:100',

            $prefix . 'street'   => 'nullable|array',
            $prefix . 'street.*' => 'string|max:255',
            $prefix . 'street_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $picked('street'))],

            $prefix . 'district'   => $req . '|array|min:' . ($requireLocation ? 1 : 0),
            $prefix . 'district.*' => 'string|max:255',
            $prefix . 'district_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $picked('district'))],

            $prefix . 'lga'   => $req . '|array|min:' . ($requireLocation ? 1 : 0),
            $prefix . 'lga.*' => 'string|max:255',

            $prefix . 'state' => $req . '|string|max:255',
        ];
    }

    /** The eight column names for a prefix. */
    public static function columns(string $prefix): array
    {
        return array_map(fn ($s) => $prefix . $s, self::SUFFIXES);
    }

    /**
     * Pull a prefixed set of address columns off a model/array into the plain
     * keys the two builders above expect.
     *
     * e.g. fromPrefixed($case, 'prop_') on columns prop_district, prop_lga, ...
     *
     * @param  array<string,mixed>|object $source
     * @return array<string,mixed>
     */
    public static function fromPrefixed($source, string $prefix): array
    {
        $get = static function ($key) use ($source) {
            if (is_array($source))  return $source[$key] ?? null;
            if (is_object($source)) return $source->{$key} ?? null;
            return null;
        };

        $out = [];
        foreach (self::SUFFIXES as $k) {
            $out[$k] = $get($prefix . $k);
        }

        return $out;
    }
}
