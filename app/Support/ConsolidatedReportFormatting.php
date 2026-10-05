<?php

namespace App\Support;

class ConsolidatedReportFormatting
{
    public static function location($district, $lga, $state, $storedLocation = '', $plotNumber = ''): string
    {
        $district = trim((string) $district);
        $lga = trim((string) $lga);
        $state = trim((string) $state);
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $storedLocation)), fn ($v) => $v !== ''));

        // OSS legacy locations often contain just district and LGA. Only use
        // that pair when the last component matches the saved property LGA.
        if (count($parts) === 2 && $lga !== '' && strcasecmp($lga, $parts[1]) === 0
            && !preg_match('/\b(?:plot|street|road|rd|st)\b|^no\.?\s*\d/i', $parts[0])) {
            $district = $district ?: $parts[0];
        }

        // Older records store the address as a comma-separated string. Recover
        // only a recognizable district/LGA/state suffix, never the street/plot.
        if (count($parts) >= 3) {
            [$tailDistrict, $tailLga, $tailState] = array_slice($parts, -3);
            if (($lga !== '' && strcasecmp($lga, $tailLga) === 0)
                || preg_match('/^(?:Kano(?: State)?)$/i', $tailState)
                || preg_match('/\sState$/i', $tailState)) {
                if (!preg_match('/\b(?:plot|street|road|rd|st)\b|^no\.?\s*\d/i', $tailDistrict)) {
                    $district = $district ?: $tailDistrict;
                }
                $lga = $lga ?: $tailLga;
                $state = $state ?: $tailState;
            }
        }

        $parts = array_filter([$district, $lga, $state], function ($part) use ($plotNumber) {
            return $part !== ''
                && !(trim((string) $plotNumber) !== '' && strcasecmp($part, trim((string) $plotNumber)) === 0)
                && !preg_match('/^(?:plot|p\/n)\b[\s.:]*(?:no\b[\s.:]*)?[\w\/-]+$/i', $part)
                && !preg_match('/^\d+[a-z]?(?:[\/-]\d*[a-z]?)?$/i', $part);
        });

        // Keep repeated names: Kano district, Kano LGA and Kano state are
        // separate address components even when their text is identical.
        return mb_strtoupper(implode(', ', $parts), 'UTF-8');
    }

    public static function row(array $row): array
    {
        return array_map(fn ($value) => is_string($value) ? mb_strtoupper($value, 'UTF-8') : $value, $row);
    }
}
