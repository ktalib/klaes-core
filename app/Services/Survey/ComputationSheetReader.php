<?php

namespace App\Services\Survey;

/**
 * Reads one map sheet of Computation sheet.xlsx (user, 2026-11-06). Columns are found by their headers, since each
 * sheet has its own order: Supplementary has FARM before GOVT, R14 starts with S/No and Tag No. The plots column moves
 * (K, L or N) and may be unlabelled or headed REMARK, so it is the side column most of whose cells read as plot numbers;
 * anything else written beside a line goes to its remarks. No database here.
 */
class ComputationSheetReader
{
    private const HEADERS = [
        'sn'        => '/^S\/?\s*NO/i',
        'tag'       => '/^TAG/i',
        'id'        => '/^(F\s*ID|FARM\s*ID|PROPERTY\s*ID)$/i',
        'first'     => '/^(F\s*NAME|FIRST)/i',
        'middle'    => '/^(M\s*NAME|MIDDLE)/i',
        'last'      => '/^(L\s*NAME|LAST)/i',
        'sheet'     => '/^SHEET/i',
        'area_ha'   => '/AREA.*HA/i',
        'area_m2'   => '/AREA.*M/i',
        'creatable' => '/CREATABLE|ENTITLEMENT/i',
        'govt'      => '/^GOVT/i',
        'farmer'    => '/^FARM\s*50/i',
    ];

    /**
     * "388", "418, 419", "267B", "13 & 14", "707-712", a share "3149B (1/2)", a missed comma "36, 37 38A".
     * Not "allocated", "2plots" or a phone number.
     */
    public static function looksLikePlots(?string $v): bool
    {
        $plot = '[A-Z]{0,3}\d{1,5}[A-Z]{0,2}(?:\s*\(\d+\/\d+\))?';

        return (bool) preg_match('/^' . $plot . '(?:(?:\s*[,;&\/\-–]\s*|\s+)' . $plot . ')*[,.]?$/i', trim((string) $v));
    }

    public static function text($v): ?string
    {
        if ($v === null) return null;
        // A whole number Excel holds as 388.0 is 388.
        if (is_float($v) && floor($v) === $v) $v = (int) $v;
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $s === '' ? null : $s;
    }

    private static function number($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** @return array{columns:array, plots_col:?int, side_cols:array, rows:array} one entry per non-blank row below the header */
    public static function read(array $rows): array
    {
        $headerAt = null;
        foreach ($rows as $i => $row) {
            if (self::isHeader($row)) { $headerAt = $i; break; }
        }
        if ($headerAt === null) return ['columns' => [], 'plots_col' => null, 'side_cols' => [], 'rows' => []];

        $columns = [];
        foreach ($rows[$headerAt] as $c => $h) {
            $h = trim((string) $h);
            if ($h === '') continue;
            foreach (self::HEADERS as $key => $re) {
                if (! isset($columns[$key]) && preg_match($re, $h)) { $columns[$key] = $c; break; }
            }
        }

        $lastCore = max($columns);
        $width = max(array_map('count', $rows));
        $sideCols = $width > $lastCore + 1 ? range($lastCore + 1, $width - 1) : [];

        // The plots column: the side column with the most cells that read as plot numbers.
        $plotsCol = null;
        $best = 0;
        foreach ($sideCols as $c) {
            $n = 0;
            foreach ($rows as $i => $row) {
                if ($i > $headerAt && self::looksLikePlots(self::text($row[$c] ?? null))) $n++;
            }
            if ($n > $best) { $best = $n; $plotsCol = $c; }
        }

        $out = [];
        foreach ($rows as $i => $row) {
            if ($i <= $headerAt) {
                if ($i === $headerAt) continue;
                if (self::isBlank($row)) continue;
                $out[] = ['row' => $i + 1, 'kind' => 'other', 'raw' => $row, 'fields' => []];
                continue;
            }
            if (self::isBlank($row)) continue;
            if (self::isHeader($row)) { $out[] = ['row' => $i + 1, 'kind' => 'other', 'raw' => $row, 'fields' => []]; continue; }

            $get = fn ($key) => isset($columns[$key]) ? ($row[$columns[$key]] ?? null) : null;
            $id = self::text($get('id'));
            $name = trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter([self::text($get('first')), self::text($get('middle')), self::text($get('last'))]))));
            $name = $name === '' ? null : $name;
            $ha = self::number($get('area_ha'));
            $m2 = self::number($get('area_m2'));

            $words = implode(' ', array_map(fn ($v) => is_string($v) ? $v : '', $row));
            $isTotal = preg_match('/\bTOTALS?\b/i', $words) && (! $id || stripos((string) $id, 'total') !== false || ! $name);

            if ($isTotal || (! $id && ! $name && ($ha || $m2))) {
                $out[] = ['row' => $i + 1, 'kind' => 'total', 'raw' => $row, 'fields' => []];
                continue;
            }
            if (! $id && ! $name) {
                // Only something written beside the lines, such as a TP note: kept in the raw record.
                $out[] = ['row' => $i + 1, 'kind' => 'other', 'raw' => $row, 'fields' => []];
                continue;
            }

            $plot = null;
            $notes = [];
            foreach ($sideCols as $c) {
                $v = self::text($row[$c] ?? null);
                if ($v === null) continue;
                if ($c === $plotsCol && self::looksLikePlots($v)) $plot = rtrim($v, ',.');
                else $notes[] = $v;
            }
            if (($tag = self::text($get('tag'))) !== null) $notes[] = 'Tag No ' . $tag;

            $out[] = ['row' => $i + 1, 'kind' => 'farmer', 'raw' => $row, 'fields' => [
                'sn'        => self::number($get('sn')) !== null ? (int) self::number($get('sn')) : null,
                'fn'        => $id,
                'name'      => $name,
                'on_sheet'  => self::text($get('sheet')),
                'area_m2'   => $m2,
                'area'      => $ha,
                'creatable' => self::number($get('creatable')),
                'govt50'    => self::number($get('govt')),
                'farmer50'  => self::number($get('farmer')),
                'plot'      => $plot,
                'remarks'   => $notes ? implode(' · ', $notes) : null,
            ]];
        }

        return ['columns' => $columns, 'plots_col' => $plotsCol, 'side_cols' => $sideCols, 'rows' => $out];
    }

    /**
     * Each row with something on it, as the sheet shows it (user, 2026-11-06: "let them display as they are").
     * read() over the values says what kind of row it is; the cells come from $shown, the same sheet as formatted text.
     * Words in the plots column stay in the plots column here. Anything else beside the columns is the row's remarks.
     *
     * @return array<int, array> columns of survey_computation_sheet_rows
     */
    public static function written(array $values, array $shown): array
    {
        $read = self::read($values);
        $out = [];
        foreach ($read['rows'] as $r) {
            $row = $shown[$r['row'] - 1] ?? [];
            $get = fn ($key) => isset($read['columns'][$key]) ? self::text($row[$read['columns'][$key]] ?? null) : null;
            $notes = [];
            foreach ($read['side_cols'] as $c) {
                if ($c !== $read['plots_col'] && ($v = self::text($row[$c] ?? null)) !== null) $notes[] = $v;
            }
            $out[] = [
                'source_row' => $r['row'],
                'kind'       => $r['kind'] === 'farmer' ? 'line' : $r['kind'],
                'farm_id'    => $get('id'), 'tag_no' => $get('tag'), 'sn_written' => $get('sn'),
                'first_name' => $get('first'), 'middle_name' => $get('middle'), 'last_name' => $get('last'), 'sheet_no' => $get('sheet'),
                'area_m2'    => $get('area_m2'), 'area_ha' => $get('area_ha'), 'creatable' => $get('creatable'), 'govt_50' => $get('govt'), 'farm_50' => $get('farmer'),
                'plots'      => $read['plots_col'] !== null ? self::text($row[$read['plots_col']] ?? null) : null,
                'remarks'    => $notes ? implode(' · ', $notes) : null,
                'raw'        => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ];
        }

        return $out;
    }

    private static function isHeader(array $row): bool
    {
        $cells = array_map(fn ($v) => is_string($v) ? trim($v) : '', $row);

        return (bool) preg_grep('/^AREA/i', $cells) && (bool) preg_grep('/NAME/i', $cells);
    }

    private static function isBlank(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string) $v) !== '') return false;
        }

        return true;
    }
}
