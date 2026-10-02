<?php

namespace App\Support\Permissions;

/**
 * Canonical form of a module name.
 *
 * "Modules" are the rows of user_roles — they are named menu areas ("Survey - Records",
 * "Deeds - Consent", "Print File Labels"), not job roles. A user's grant is a CSV of these
 * NAMES in users.assign_role, so every comparison in the app is a string comparison and
 * the spelling has to be reconciled before it can be trusted.
 *
 * Production data makes that non-optional. The same module is written three ways:
 *
 *   "ST – Final Conveyance"       en-dash, from a paste out of Word
 *   "Deeds  -  Property Records"  padded hyphen
 *   "deeds - property records"    lower case
 *
 * This logic is lifted verbatim from the closure that resources/views/admin/menu.blade.php
 * has always used, so normalising here cannot change which menu items a user sees. Both the
 * menu and the permission gate now call this, which is the point: two copies of these rules
 * would drift, and a drift means a user silently loses a module.
 *
 * Deliberately NOT handled: 22 granted names have no user_roles row at all, and 2 of those
 * carry a mojibake dash ("File Digital Library \xef\xbf\xbd Doc-WARE") from an encoding-damaged
 * import. Those are data to repair, not spellings to absorb — normalising them here would
 * hide the damage instead of surfacing it.
 */
final class ModuleName
{
    /**
     * Fold one module name to the form used as a lookup key.
     */
    public static function normalize(?string $name): string
    {
        $value = trim((string) $name);

        if ($value === '') {
            return '';
        }

        // En/em dash -> hyphen. Role names are authored in both.
        $value = str_replace(['–', '—'], '-', $value);
        // One space either side of a hyphen, so "A-B", "A - B" and "A  -  B" agree.
        $value = preg_replace('/\s*-\s*/u', ' - ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * Split a users.assign_role CSV into normalized module names.
     *
     * Empty tokens are dropped; the whole-system grant ("Supper Admin" / "Super Admin") is
     * kept, because callers need to see it to short-circuit. Duplicates collapse.
     *
     * @return array<int, string>
     */
    public static function listFrom(?string $csv): array
    {
        $names = [];

        foreach (explode(',', (string) $csv) as $token) {
            $key = self::normalize($token);

            if ($key !== '') {
                $names[$key] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * True when the name is the whole-system grant, in either of the two spellings
     * production uses. The misspelling is the value actually stored.
     */
    public static function isSuperAdminGrant(?string $name): bool
    {
        return in_array(self::normalize($name), ['super admin', 'supper admin'], true);
    }
}
