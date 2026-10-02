<?php

namespace App\Support;

use Carbon\Carbon;

class DateFormatter
{
    /**
     * Parse a date value from SQL Server into ISO 8601 format.
     *
     * Handles mixed formats stored in the database:
     *   - ISO: "2026-04-06T10:37:55.300" or "2026-04-06 10:37:55.300"
     *   - SQL Server format 100: "Apr  5 2026  4:24PM" (double spaces for single-digit day or before time)
     *   - SQL Server format 100: "Mar 31 2026  3:22PM"
     *   - NULL or empty strings
     *
     * @param  mixed  $value  The raw date value from the database
     * @param  string $format The desired output format (default: ISO 8601)
     * @return string|null    Formatted date string or null if unparseable
     */
    public static function toISO($value): ?string
    {
        return self::parse($value)?->format('Y-m-d\TH:i:s');
    }

    /**
     * Format a date for display: "06 Apr 2026"
     */
    public static function toDisplay($value): ?string
    {
        return self::parse($value)?->format('d M Y');
    }

    /**
     * Format a date with time for display: "06 Apr 2026 04:24 PM"
     */
    public static function toDisplayWithTime($value): ?string
    {
        return self::parse($value)?->format('d M Y h:i A');
    }

    /**
     * Format a date for sorting: "2026-04-06"
     */
    public static function toSortable($value): ?string
    {
        return self::parse($value)?->format('Y-m-d H:i:s');
    }

    /**
     * Core parser: normalize SQL Server date quirks, then parse with Carbon.
     *
     * @param  mixed $value
     * @return Carbon|null
     */
    public static function parse($value): ?Carbon
    {
        if (empty($value) || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $value = trim((string) $value);

        // Collapse multiple whitespace into single spaces.
        // This fixes SQL Server format 100: "Apr  5 2026  4:24PM" → "Apr 5 2026 4:24PM"
        $normalized = preg_replace('/\s+/', ' ', $value);

        try {
            return Carbon::parse($normalized);
        } catch (\Exception $e) {
            // Silent fall-through to next attempt
        }

        // Fallback: try original value (in case regex mangled something)
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
