<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** OP serials are identifiers, not registration serial/page/volume numbers. */
final class OpSerial
{
    public const RULE = 'regex:/^[1-9][0-9]*$/';

    public static function rules(string $presence = 'required'): array
    {
        return [$presence, 'string', 'max:100', self::RULE];
    }

    /** Unchecked optional OP sections must ignore even stale hidden values. */
    public static function rulesWhenEnabled($enabled): array
    {
        return array_merge([
            \Illuminate\Validation\Rule::excludeIf(!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)),
        ], self::rules());
    }

    public static function valid($value): bool
    {
        return (is_string($value) || is_int($value))
            && preg_match('/^[1-9][0-9]*$/D', trim((string) $value)) === 1
            && strlen(trim((string) $value)) <= 100;
    }

    public static function require($value, string $field = 'op_serial_number'): string
    {
        if (!self::valid($value)) {
            throw ValidationException::withMessages([
                $field => 'OP Serial Number is required and must contain positive digits without leading zeros (for example, 109).',
            ]);
        }
        return trim((string) $value);
    }

    public static function isOp(array $row): bool
    {
        $type = strtolower(($row['instrument_type'] ?? '').' '.($row['transaction_type'] ?? ''));
        return str_contains($type, 'occupancy permit')
            || (str_contains($type, 'transfer') && str_contains($type, 'title') && str_contains($type, '(op)'))
            || strtoupper(trim((string) ($row['system_source'] ?? ''))) === 'OSSOPCHANGEOFNAME';
    }

    public static function isMother(array $row): bool
    {
        $type = strtolower(($row['instrument_type'] ?? '').' '.($row['transaction_type'] ?? ''));
        return str_contains($type, 'occupancy permit') && !str_contains($type, 'transfer');
    }

    /** Omitted values preserve the old serial; explicit clearing is an error. */
    public static function guard(array $input, array $existing = [], string $field = 'op_serial_number'): array
    {
        $effective = array_replace($existing, $input);
        if (self::isOp($effective) || self::isOp($existing)) {
            $input['op_serial_number'] = self::require($effective['op_serial_number'] ?? null, $field);
        }
        return $input;
    }
}
