<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a district / lga column value to a display name.
 *
 * Most rows already hold the name ("Zawachiki") and are returned untouched.
 * Some hold a bare reference id ("10173") because populateSelectFromText() in
 * create-indexing-dialog.js used to submit the id instead of the name — those
 * are looked up here so the screen reads correctly even before the data is
 * repaired by `php artisan geo:fix-numeric-ids --apply`.
 *
 * A number that matches no reference row resolves to null: the small-id space
 * (1-78) predates a dedup of the districts table and those rows are gone, so
 * there is no name to show. Callers hide the badge rather than print a number.
 */
class GeoName
{
    /** @var array<string,\Illuminate\Support\Collection<int,string>> */
    private static array $maps = [];

    public static function district(?string $value): ?string
    {
        return self::resolve($value, 'districts');
    }

    public static function lga(?string $value): ?string
    {
        return self::resolve($value, 'lgas');
    }

    private static function resolve(?string $value, string $table): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            return $value;
        }

        $name = self::map($table)->get((int) $value);

        return ($name === null || $name === '') ? null : $name;
    }

    /** Loaded once per request; the reference tables are small and static. */
    private static function map(string $table)
    {
        if (!isset(self::$maps[$table])) {
            self::$maps[$table] = DB::connection('sqlsrv')->table($table)->pluck('name', 'id');
        }

        return self::$maps[$table];
    }
}
