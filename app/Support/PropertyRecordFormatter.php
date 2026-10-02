<?php

namespace App\Support;

class PropertyRecordFormatter
{
    /**
     * Order of file number fields to inspect when resolving the primary identifier.
     */
    protected const FILE_NUMBER_FIELDS = [
        'file_number_display',
        'mlsfno',
        'kangisfileno',
        'newkangisfileno',
        'fileno',
        'temp_fileno',
        'tempfileno',
        'tempfilenumber',
    ];

    /**
     * Prefix tokens mapped to normalized land use labels.
     */
    protected const LAND_USE_PREFIX_MAP = [
        'RES' => 'RESIDENTIAL',
        'RESIDENTIAL' => 'RESIDENTIAL',
        'COM' => 'COMMERCIAL',
        'COMMERCIAL' => 'COMMERCIAL',
        'IND' => 'INDUSTRIAL',
        'INDUSTRIAL' => 'INDUSTRIAL',
        'AG' => 'AGRICULTURAL',
        'AGR' => 'AGRICULTURAL',
        'AGRIC' => 'AGRICULTURAL',
        'AGRICULTURAL' => 'AGRICULTURAL',
        'MIX' => 'MIXED USE',
        'MIXED' => 'MIXED USE',
    ];

    /**
     * Preferred field order for each party column.
     */
    protected const FROM_PARTY_FIELDS = [
        'assignor' => 'Assignor',
        'grantor' => 'Grantor',
        'mortgagor' => 'Mortgagor',
        'surrenderor' => 'Surrenderor',
        'lessor' => 'Lessor',
    ];

    protected const TO_PARTY_FIELDS = [
        'assignee' => 'Assignee',
        'grantee' => 'Grantee',
        'mortgagee' => 'Mortgagee',
        'surrenderee' => 'Surrenderee',
        'lessee' => 'Lessee',
    ];

    /**
     * Convert a record object/array into a case-insensitive associative array.
     */
    public static function normalizeRecord($record): array
    {
        if (is_array($record)) {
            $array = $record;
        } elseif (is_object($record)) {
            $array = get_object_vars($record);
        } else {
            $array = [];
        }

        return array_change_key_case($array, CASE_LOWER);
    }

    /**
     * Resolve the best file number to display for a record.
     */
    public static function resolveFileNumber(array $record): ?string
    {
        foreach (self::FILE_NUMBER_FIELDS as $field) {
            $value = self::getValue($record, $field);

            if (self::hasStringValue($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * Derive a normalized land use label, preferring stored values and falling back to file number tokens.
     */
    public static function deriveLandUse(array $record): string
    {
        $existing = self::stringValue(
            self::getValue($record, 'land_use'),
            self::getValue($record, 'land_use_type')
        );

        if ($existing !== null && strtoupper($existing) !== 'N/A') {
            return strtoupper($existing);
        }

        $fileNumber = self::resolveFileNumber($record);
        $derived = $fileNumber ? self::deriveLandUseFromFileNumber($fileNumber) : null;

        if ($derived !== null) {
            return $derived;
        }

        return $existing !== null ? strtoupper($existing) : 'N/A';
    }

    /**
     * Build a human-friendly location string with fallbacks.
     */
    public static function formatLocation(array $record): string
    {
        $candidates = [
            self::stringValue(self::getValue($record, 'location')),
            self::stringValue(self::getValue($record, 'property_description')),
            self::stringValue(self::getValue($record, 'description')),
            self::combineParts([
                self::stringValue(self::getValue($record, 'plot_no', 'plotno', 'plotnumber')),
                self::stringValue(self::getValue($record, 'block_no', 'blockno', 'blocknumber')),
                self::stringValue(self::getValue($record, 'streetname', 'street_name')),
                self::stringValue(self::getValue($record, 'districtname', 'district')),
                self::stringValue(self::getValue($record, 'lganame', 'lga', 'lgsaorcity')),
                self::stringValue(self::getValue($record, 'layoutname', 'layout')),
            ]),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return 'N/A';
    }

    /**
     * Combine serial/page/volume into a registration particulars string.
     */
    public static function formatRegistrationParticulars(array $record): string
    {
        $serial = self::stringValue(self::getValue($record, 'serialno'), self::getValue($record, 'serial_no'));
        $page = self::stringValue(self::getValue($record, 'pageno'), self::getValue($record, 'page_no'));
        $volume = self::stringValue(self::getValue($record, 'volumeno'), self::getValue($record, 'volume_no'));

        if ($serial !== null || $page !== null || $volume !== null) {
            return sprintf(
                '%s/%s/%s',
                $serial ?? '0',
                $page ?? '0',
                $volume ?? '0'
            );
        }

        $regNo = self::stringValue(self::getValue($record, 'regno'));

        return $regNo ?? '0/0/0';
    }

    /**
     * Resolve the correct party labels/values for Grantor/Grantee columns.
     */
    public static function resolvePartyDetails(array $record): array
    {
        $from = self::firstAvailableParty($record, self::FROM_PARTY_FIELDS, 'Grantor');
        $to = self::firstAvailableParty($record, self::TO_PARTY_FIELDS, 'Grantee');

        return [
            'from_label' => $from['label'],
            'from_value' => $from['value'],
            'to_label' => $to['label'],
            'to_value' => $to['value'],
        ];
    }

    /**
     * Helper to grab the first non-empty party field.
     */
    protected static function firstAvailableParty(array $record, array $fields, string $defaultLabel): array
    {
        foreach ($fields as $field => $label) {
            $value = self::stringValue(self::getValue($record, $field));

            if ($value !== null) {
                return [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        $fallback = self::stringValue(self::getValue($record, strtolower($defaultLabel)));

        return [
            'label' => $defaultLabel,
            'value' => $fallback,
        ];
    }

    /**
     * Attempt to derive the land use from tokens embedded inside a file number string.
     */
    protected static function deriveLandUseFromFileNumber(string $fileNumber): ?string
    {
        $normalized = strtoupper(str_replace(['_', '\\'], '-', $fileNumber));
        $tokens = preg_split('/[^A-Z]/', $normalized);

        if (!$tokens) {
            return null;
        }

        foreach ($tokens as $token) {
            if ($token !== '' && isset(self::LAND_USE_PREFIX_MAP[$token])) {
                return self::LAND_USE_PREFIX_MAP[$token];
            }
        }

        return null;
    }

    /**
     * Safely fetch the first non-null field value.
     */
    protected static function getValue(array $record, string ...$fields)
    {
        foreach ($fields as $field) {
            $key = strtolower($field);

            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        return null;
    }

    /**
     * Return a trimmed string or null when empty.
     */
    protected static function stringValue(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $string = trim((string) $value);

            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    /**
     * Determine if a raw value contains a meaningful string.
     */
    protected static function hasStringValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value) || $value === '0' || $value === 0;
    }

    /**
     * Combine location components.
     */
    protected static function combineParts(array $parts): ?string
    {
        $filtered = array_values(array_filter($parts, fn($part) => $part !== null));

        if (empty($filtered)) {
            return null;
        }

        return implode(', ', $filtered);
    }
}
