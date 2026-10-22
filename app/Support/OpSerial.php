<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** OP serials are identifiers, not registration serial/page/volume numbers. */
final class OpSerial
{
    /**
     * Typed serials: positive digits, no leading zero. System-generated serials (for an
     * OP that has none, user 2026-10-16) are six digits zero-padded, 000001 .. 999999,
     * so a leading zero marks "made by the system". Both are valid everywhere; only the
     * capture screens stop an officer TYPING a leading zero (see manualValid()).
     */
    public const RULE = 'regex:/^(?:[1-9][0-9]*|(?!0{6})0[0-9]{5})$/';

    private const TYPED = '/^[1-9][0-9]*$/D';
    private const GENERATED = '/^(?!0{6})0[0-9]{5}$/D';

    public static function rules(string $presence = 'required'): array
    {
        return [$presence, 'string', 'max:100', self::RULE];
    }

    /** Shaped like a system-generated serial (000001 .. 099999). */
    public static function isGeneratedFormat($value): bool
    {
        return (is_string($value) || is_int($value)) && preg_match(self::GENERATED, trim((string) $value)) === 1;
    }

    /** What an officer may type: no leading zero. */
    public static function manualValid($value): bool
    {
        return (is_string($value) || is_int($value))
            && preg_match(self::TYPED, trim((string) $value)) === 1
            && strlen(trim((string) $value)) <= 100;
    }

    /** The printed form of generated serial number $n: 1 => "000001". */
    public static function formatGenerated(int $n): string
    {
        return str_pad((string) $n, 6, '0', STR_PAD_LEFT);
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
        return self::manualValid($value) || self::isGeneratedFormat($value);
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
