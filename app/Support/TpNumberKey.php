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

    /**
     * Why a TP No typed on an OP cannot be saved, or null when it can (user, 2026-11-05: required, and in
     * the right shape, on PRA, Instrument and OSS Capture OP). The shape is TP or BLM / a known agency /
     * letter-only area codes / the layout number: TP/KN/215M, TP/MLPP/DKD/001, TP/K/UC/DKD/14, BLM/PP/005.
     * Spaces are ignored; nothing is rewritten. A layout missing from Public Layout Plans is not refused.
     */
    public static function problem(?string $raw): ?string
    {
        $s = strtoupper(preg_replace('/\s+/', '', (string) $raw));
        if ($s === '') return 'TP No is required for an Occupancy Permit (OP).';

        $parts = explode('/', $s);
        if (! in_array($parts[0], self::PREFIXES, true)) {
            return "TP No \"{$raw}\" must start with TP/ (or BLM/) and use / between parts, e.g. TP/KN/215M.";
        }
        if (count($parts) < 3) return "TP No \"{$raw}\" needs the agency and the layout number, e.g. TP/KN/215M or TP/MLPP/DKD/001.";
        if (in_array('', $parts, true)) return "TP No \"{$raw}\" has an empty part (two slashes together, or one at the end).";
        if (! isset(self::AGENCIES[$parts[1]])) {
            return "TP No \"{$raw}\": \"{$parts[1]}\" is not a known agency. Use K, KN, KNUPDA, MLPP, KASEPPA, PP, UDB or MB.";
        }
        foreach (array_slice($parts, 2, -1) as $seg) {
            if (! preg_match('/^[A-Z]+$/', $seg)) return "TP No \"{$raw}\": \"{$seg}\" is not an area code. Area codes are letters only (DKD, KBT, UC).";
        }
        $serial = end($parts);
        if (! preg_match('/^\d+[A-Z]{0,2}$/', $serial)) {
            return "TP No \"{$raw}\": \"{$serial}\" is not a layout number. It is digits, then up to two letters (215M, 001, 5A).";
        }

        return null;
    }
}
