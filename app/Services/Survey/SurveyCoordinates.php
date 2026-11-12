<?php

namespace App\Services\Survey;

/**
 * CR 11112026-2 item 10: X is the Easting, Y the Northing (Minna / UTM 32N in Kano: Easting six digits,
 * Northing seven). Many beacons were captured the other way round. orient() puts a pair the right way
 * round when it is clearly reversed, so every capture form corrects it on save.
 */
class SurveyCoordinates
{
    public static function isEasting(float $v): bool
    {
        return $v >= 100000 && $v < 1000000;
    }

    public static function isNorthing(float $v): bool
    {
        return $v >= 1000000 && $v < 2000000;
    }

    /**
     * Clearly reversed: X is larger than Y, and either Y is a valid Easting or X a valid Northing (so one
     * wrongly typed digit on the other value still counts). Anything less certain is left as typed.
     */
    public static function isReversed($x, $y): bool
    {
        if (! is_numeric($x) || ! is_numeric($y)) return false;
        $x = (float) $x;
        $y = (float) $y;

        return $x > $y && (self::isEasting($y) || self::isNorthing($x)) && ! (self::isEasting($x) && self::isNorthing($y));
    }

    /** [x, y, swapped]; values keep their own type and text. */
    public static function orient($x, $y): array
    {
        return self::isReversed($x, $y) ? [$y, $x, true] : [$x, $y, false];
    }
}
