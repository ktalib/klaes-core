<?php

namespace App\Models\Survey;

/**
 * One layout plan TP number in the TP Nos register (Survey > Public Survey > TP Nos,
 * user 2026-10-30), e.g. TP/KNUPDA/338A = prefix TP/KNUPDA + serial 338A. Listed by
 * serial number.
 */
class SurveyTpNumber extends SurveyModel
{
    protected $table = 'survey_tp_numbers';

    protected $casts = [
        'serial_sort'   => 'integer',
        'plot_count'    => 'integer',
        'area_ha'       => 'decimal:4',
        'approval_date' => 'date',
    ];

    /** Prefixes offered first; "Other" lets staff type one, which is then offered too. */
    public const PREFIXES = ['TP/KNUPDA', 'TP/KNUPDA/UC', 'TP/MLPP', 'BLM/PP'];

    public const OTHER = 'Other';

    /** Land Use dropdown: the Private Layout list, plus "Other (specify)". */
    public const LAND_USES = SurveyPrivateLayout::LAND_USES;

    /** Land use as shown: the typed value when "Other" was picked. */
    public function getLandUseLabelAttribute(): ?string
    {
        return $this->land_use === self::OTHER ? ($this->land_use_other ?: self::OTHER) : $this->land_use;
    }

    /** Purpose dropdown: the purposes already on the register, each once (case-insensitive), A–Z. */
    public static function purposeOptions(): array
    {
        $out = [];
        foreach (static::query()->whereNotNull('purpose')->distinct()->pluck('purpose')->all() as $p) {
            $p = trim(preg_replace('/\s+/', ' ', (string) $p));
            if ($p !== '' && ! in_array(mb_strtoupper($p), array_map('mb_strtoupper', $out), true)) $out[] = $p;
        }
        natcasesort($out);

        return array_values($out);
    }

    /** TP No. as written: prefix/serial. */
    public static function compose(string $prefix, string $serial): string
    {
        return rtrim(trim($prefix), '/') . '/' . ltrim(trim($serial), '/');
    }

    /**
     * A tp_lookups value as [prefix, serial], or null when it is not a valid TP number
     * (dates, bare numbers, names, "PART OF …", several numbers in one, other plan
     * types such as PL/ RD/ SP/). Only typing slips are repaired: spaces around slashes,
     * doubled slashes, "|", "TP," / "TP.", "TP KAS 193", a serial glued to its prefix
     * (TP/KAS215H) and a split suffix ("215 B").
     */
    public static function parseLookup(?string $raw): ?array
    {
        $v = mb_strtoupper(trim((string) $raw));
        $v = preg_replace('/[`\'".,;:]+$/', '', $v);
        $v = preg_replace('/^TP\s*[,.+]\s*/', 'TP/', $v);
        $v = str_replace('|', '/', $v);
        $v = preg_replace('/(\d)\s+([A-Z])$/', '$1$2', $v);         // 215 B -> 215B
        $v = preg_replace('/(?<=[A-Z])\.(?=\d)/', '/', $v);         // TP/K.241
        $v = preg_replace('/\s*\/\s*/', '/', $v);
        if (str_starts_with($v, 'TP')) $v = preg_replace('/\s+/', '/', $v);
        $v = trim(preg_replace('#/[/+]+#', '/', $v), '/ ');
        $v = preg_replace('#^(TP(?:/[A-Z]{1,12})*/[A-Z]{2,12})(\d{1,5}[A-Z]{0,3})$#', '$1/$2', $v); // TP/KAS215H

        // TP / authority segment(s) / serial; serial = number + letters, or year/number.
        if (! preg_match('#^(TP(?:/[A-Z]{1,12}){1,4})/(\d{1,5}[A-Z]{0,3}|(?:19|20)\d\d/\d{1,4}[A-Z]{0,2})$#', $v, $m)) {
            return null;
        }

        return [$m[1], $m[2]];
    }

    /** The serial's first number (338A → 338, GZ/02 → 2), so 9 sorts before 10. */
    public static function serialSort(?string $serial): ?int
    {
        return preg_match('/\d+/', (string) $serial, $m) ? (int) min($m[0], 2000000000) : null;
    }

    /** Known prefixes and those already used in the register, each once (case-insensitive). */
    public static function prefixOptions(): array
    {
        $out = [];
        foreach (array_merge(self::PREFIXES, static::query()->distinct()->pluck('prefix')->all()) as $p) {
            $p = trim((string) $p);
            if ($p !== '' && ! in_array(mb_strtoupper($p), array_map('mb_strtoupper', $out), true)) $out[] = $p;
        }

        return $out;
    }

    /** Register order: by serial number, then serial text, then prefix. */
    public function scopeSerialOrder($q)
    {
        return $q->orderByRaw('CASE WHEN serial_sort IS NULL THEN 1 ELSE 0 END')
            ->orderBy('serial_sort')->orderBy('serial_no')->orderBy('prefix')->orderBy('id');
    }
}
