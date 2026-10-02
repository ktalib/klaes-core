<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * A Nigerian mobile number in the canonical form the SMS gateways expect:
 * 11 digits beginning with 0, e.g. 08012345678.
 *
 * Presence is NOT this rule's business — pair it with `required` or `nullable`.
 * An empty value always passes so the rule can sit on optional fields.
 */
class NigerianPhone implements Rule
{
    public function passes($attribute, $value)
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return (bool) preg_match('/^0\d{10}$/', trim((string) $value));
    }

    public function message()
    {
        return 'The :attribute must be an 11-digit number starting with 0, such as 08012345678.';
    }

    /**
     * Best-effort conversion of the shapes people actually type into the
     * canonical 0XXXXXXXXXX form. Returns the input untouched when it cannot
     * be recognised, so callers can let validation report the real problem.
     */
    public static function normalize($value)
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return is_string($value) ? trim($value) : $value;
        }

        // +234 801 234 5678 / 234... -> 0801...
        if (strlen($digits) === 13 && strpos($digits, '234') === 0) {
            $digits = '0' . substr($digits, 3);
        } elseif (strlen($digits) === 10 && $digits[0] !== '0') {
            // 8012345678 -> 08012345678
            $digits = '0' . $digits;
        }

        return preg_match('/^0\d{10}$/', $digits) ? $digits : (is_string($value) ? trim($value) : $value);
    }
}
