<?php

namespace App\Support;

use App\Services\KanoLgaDirectory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Source of an Occupancy Permit (user, 2026-10-15): who issued it. Stored in
 * pra.op_source; "Other" keeps its specified text in pra.op_source_other.
 * LGA used to be an OP Type and is a Source now. Shared by the file-indexing OP
 * section and the PRA card so both apply the same rules.
 *
 * "Kano MDAs" (CR 22102026, 2026-10-24) names the issuing MDA from kano_mdas — the
 * list File Movement already uses — and keeps that name in pra.op_source_other too,
 * so the detail of a Source always lives in one column. The MDA list has its own
 * "Other", which reuses the same "specify" box.
 */
class OpSource
{
    public const MDA = 'Kano MDAs';
    public const OPTIONS = ['Ministry', 'KANGIS', 'LGA', self::MDA];
    public const OTHER = 'Other';

    private static ?array $mdas = null;

    /** Active Kano State MDAs (kano_mdas), in display order. */
    public static function mdas(): array
    {
        if (self::$mdas === null) {
            try {
                self::$mdas = DB::connection('sqlsrv')->table('kano_mdas')->where('is_active', 1)
                    ->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
            } catch (Throwable $e) {
                self::$mdas = [];
            }
        }

        return self::$mdas;
    }

    /** Free text: trimmed, inner whitespace collapsed, case kept as typed. */
    public static function normalizeOther($value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /** The listed MDA this text is (any case or spacing), or null. */
    public static function listedMda($value): ?string
    {
        $key = fn ($v) => mb_strtolower((string) self::normalizeOther($v));
        foreach (self::mdas() as $mda) {
            if ($key($mda) === $key($value)) {
                return $mda;
            }
        }

        return null;
    }

    /**
     * Field => message for everything wrong with a permit's Source; empty when valid.
     *
     * @param  string  $sourceField  input name of the Source select
     * @param  string  $otherField   input name of the "specify" box
     * @param  string  $grantorField input name to report an LGA grantor problem on
     * @param  mixed   $mda          the MDA select's value (Source = Kano MDAs)
     * @param  string|null $mdaField input name of the MDA select
     */
    public static function errors($source, $other, $grantor, string $sourceField, string $otherField, string $grantorField, $mda = null, ?string $mdaField = null): array
    {
        $source = trim((string) $source);
        $mdaField = $mdaField ?: $sourceField;

        if (!in_array($source, array_merge(self::OPTIONS, [self::OTHER]), true)) {
            return [$sourceField => 'Please select the Source of the Occupancy Permit (Ministry, KANGIS, LGA, Kano MDAs or Other).'];
        }

        if ($source === self::MDA) {
            $mda = trim((string) $mda);
            if ($mda === self::OTHER) {
                $typed = self::normalizeOther($other);
                if ($typed === null) {
                    return [$otherField => 'MDA is Other: please specify the MDA that issued the Occupancy Permit.'];
                }
                if (mb_strlen($typed) > 100) {
                    return [$otherField => 'The specified MDA may not be longer than 100 characters.'];
                }
                if ($listed = self::listedMda($typed)) {
                    return [$otherField => "\"{$listed}\" is already in the MDA list; select it instead of Other."];
                }
            } elseif ($mda === '' || !in_array($mda, self::mdas(), true)) {
                return [$mdaField => 'Source is Kano MDAs: select the MDA that issued the Occupancy Permit.'];
            }
        }

        if ($source === self::OTHER) {
            $other = self::normalizeOther($other);
            if ($other === null) {
                return [$otherField => 'Source is Other: please specify the Source of the Occupancy Permit.'];
            }
            if (mb_strlen($other) > 100) {
                return [$otherField => 'The specified Source may not be longer than 100 characters.'];
            }
            // A listed option typed in by hand (any case) is that option, not a new one.
            foreach (self::OPTIONS as $option) {
                if (strcasecmp($other, $option) === 0) {
                    return [$otherField => "\"{$option}\" is already in the Source list; select it instead of Other."];
                }
            }
        }

        if ($source === 'LGA'
            && !in_array(trim((string) $grantor), app(KanoLgaDirectory::class)->fullNames(), true)) {
            return [$grantorField => 'Source is LGA: select the Local Government that issued the Occupancy Permit.'];
        }

        return [];
    }

    /**
     * The value to store in op_source_other: the specified text while Source is
     * Other, the MDA (or its specified text) while Source is Kano MDAs, else null.
     */
    public static function otherFor($source, $other, $mda = null): ?string
    {
        $source = trim((string) $source);
        if ($source === self::OTHER) {
            return self::normalizeOther($other);
        }
        if ($source === self::MDA) {
            $mda = trim((string) $mda);
            return $mda === self::OTHER ? self::normalizeOther($other) : self::normalizeOther($mda);
        }

        return null;
    }

    /**
     * What a saved Kano MDAs permit shows on reload: [mda select value, specify text].
     * A stored name that is not (or no longer) listed comes back as Other + its text.
     */
    public static function mdaFormValues($source, $stored): array
    {
        if (trim((string) $source) !== self::MDA) {
            return ['', ''];
        }
        $stored = (string) self::normalizeOther($stored);
        if ($stored === '') {
            return ['', ''];
        }
        $listed = self::listedMda($stored);

        return $listed ? [$listed, ''] : [self::OTHER, $stored];
    }
}
