<?php

namespace App\Support;

/**
 * Breaks a TP number into its parts so OPs can be grouped by layout (CR 03112026 item 2). The same
 * layout is written many ways on OPs: TP/KN/215M, TP/KNUPDA/215M, TP/MLPP/DKD/001, TP/MIPP/DKD/001.
 *
 *   prefix  TP, BLM ...
 *   agency  the planning authority, one spelling per agency (KN, KNUP, KUNPDA -> KNUPDA)
 *   code    the area code between agency and serial, when there is one (DKD, KBT, DTF)
 *   serial  the number that identifies the layout (215M, 001, 5A)
 *
 * key() is what OPs group on: code + serial when there is an area code (DKD/14), else agency + serial
 * (KNUPDA/215M). Only spellings of one agency merge: TP/MB/5A and TP/UDB/5A are different layouts, and
 * TP/K/215M, TP/KN/215M and TP/KNUP/215M are one (the register writes Kano as K). Nothing here changes
 * a stored value.
 */
class TpNumberKey
{
    /** Every spelling met on OPs => the one agency it means. */
    private const AGENCIES = [
        'K' => 'KNUPDA', 'KN' => 'KNUPDA', 'KNUP' => 'KNUPDA', 'KNUPDA' => 'KNUPDA', 'KUNPDA' => 'KNUPDA', 'KNUPD' => 'KNUPDA', 'KW' => 'KNUPDA', 'KANO' => 'KNUPDA',
        'KAS' => 'KASEPPA', 'KASEPPA' => 'KASEPPA', 'KASSEPA' => 'KASEPPA', 'KASSEPPA' => 'KASEPPA', 'KASSEP' => 'KASEPPA', 'KASEPA' => 'KASEPPA', 'KSSEPA' => 'KASEPPA',
        'MLPP' => 'MLPP', 'MIPP' => 'MLPP', 'MLP' => 'MLPP', 'MLUPP' => 'MLPP',
        'PP' => 'PP', 'UDB' => 'UDB', 'UBD' => 'UDB', 'MB' => 'MB',
    ];

    /** Area codes written two ways. */
    private const CODES = ['GZW' => 'GZ'];

    private const PREFIXES = ['TP', 'BLM'];

    /** @return array{raw:string, prefix:?string, agency:?string, code:?string, serial:?string, key:?string} */
    public static function parse(?string $raw): array
    {
        $out = ['raw' => trim((string) $raw), 'prefix' => null, 'agency' => null, 'code' => null, 'serial' => null, 'key' => null];

        $s = strtoupper(preg_replace('/\s+/', '', (string) $raw));
        $s = preg_replace('/[|.\\\\]+/', '/', $s);
        if ($s === '' || in_array($s, ['-', 'NIL', 'N/A', 'NA', 'NONE'], true)) return $out;

        $parts = array_values(array_filter(explode('/', $s), 'strlen'));
        if (! $parts) return $out;

        if (in_array($parts[0], self::PREFIXES, true)) $out['prefix'] = array_shift($parts);

        $codes = [];
        while (count($parts) > 1) {
            $seg = array_shift($parts);
            if ($out['agency'] === null && isset(self::AGENCIES[$seg])) {
                $out['agency'] = self::AGENCIES[$seg];
            } elseif ($seg !== 'UC') {
                $codes[] = self::CODES[$seg] ?? $seg;
            }
        }
        $out['code'] = $codes ? implode('/', $codes) : null;
        $out['serial'] = $parts[0] ?? null;
        if ($out['serial'] === null) return $out;

        // Zeros at the front of a number are padding: DKD/001 and DKD/1 are one layout.
        $serial = preg_replace('/^0+(?=\d)/', '', $out['serial']);
        $out['key'] = match (true) {
            $out['code'] !== null   => $out['code'] . '/' . $serial,
            $out['agency'] !== null => $out['agency'] . '/' . $serial,
            default                 => $serial,
        };

        return $out;
    }

    public static function key(?string $raw): ?string
    {
        return self::parse($raw)['key'];
    }
}
