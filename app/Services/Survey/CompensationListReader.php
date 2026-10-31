<?php

namespace App\Services\Survey;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the department's paper-era compensation workbooks ("ALL COMPENSATION",
 * user 2026-10-30) into rows with their context. Two kinds of list:
 *
 *   farmer  compensation / allocation lists grouped by project and surveyor:
 *           titles, "SURVEYED BY:- <name> [SECTION 3A]", then F/N, NAME, SURNAME,
 *           AREA (ha), CREATABLE, FARMER 50%, GOVT 50%, PLOTS
 *   plot    resettlement plot allocation lists, one row per plot, grouped by
 *           project: S/N, NAME, PLAN, LOCATION, LGA, PLOTS, STATUS
 *
 * The sheets are print layouts: the titles repeat on every page, the header row
 * moves between columns, and a few sheets carry two tables side by side. So every
 * header row opens a "table" over its own column range; titles above it set the
 * context (plan no., project name, purpose, LGA, CO number, surveyor, section,
 * list title); each later row is read by every table whose columns it touches.
 *
 * Nothing is dropped: rows that are not farmers or plots come back as total /
 * summary / other, for the staging table.
 */
class CompensationListReader
{
    public const LGAS = ['Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dala', 'Dambatta', 'Dawakin Kudu', 'Dawakin Tofa',
        'Doguwa', 'Fagge', 'Gabasawa', 'Garko', 'Garun Mallam', 'Gaya', 'Gezawa', 'Ghari', 'Gwale', 'Gwarzo', 'Kabo', 'Kano Municipal',
        'Karaye', 'Kibiya', 'Kiru', 'Kumbotso', 'Kura', 'Madobi', 'Makoda', 'Minjibir', 'Nasarawa', 'Rano', 'Rimin Gado', 'Rogo',
        'Shanono', 'Sumaila', 'Takai', 'Tarauni', 'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Warawa', 'Wudil'];

    /** Header cell text => field. */
    private const HEADERS = [
        'S/N' => 'sn', 'S/NO' => 'sn', 'SN' => 'sn', 'F/N' => 'fn', 'F/NO' => 'fn', 'ID' => 'fn',
        'NAME' => 'name', 'M NAME' => 'mname', 'SURNAME' => 'surname', 'S NAME' => 'surname',
        'AREA' => 'area', 'AREA(HA)' => 'area', 'AREA (HA)' => 'area', 'CREATABLE' => 'creatable',
        'FARMER 50%' => 'farmer50', 'GOVT 50%' => 'govt50', 'GOVT. 50%' => 'govt50',
        'PLOT' => 'plot', 'PLOTS' => 'plot', 'PLOTS №' => 'plot', 'PLOTS NO' => 'plot',
        'STATUS' => 'status', 'STAUS' => 'status', 'LOCATION' => 'location', 'PLAN' => 'plan', 'PLAN NO.' => 'plan', 'PLAN NO' => 'plan',
        'LOCAL GOVT' => 'lga', 'LOCAL GOVT.' => 'lga', 'L.G' => 'lga', 'LG' => 'lga', 'LGA' => 'lga',
        'SIGN' => 'sign', 'REMARKS' => 'remarks', 'LANDUSE' => 'landuse', 'NAIRA' => 'naira',
    ];

    private const PLACEHOLDER_NAMES = ['ABSENT', 'CEMETRY', 'CEMETERY', 'NIL', 'N/A', '-'];

    /**
     * Every non-blank row of every sheet: [sheet, row, kind, raw, ctx, fields].
     *
     * @return \Generator<int, array>
     */
    public function read(string $path): \Generator
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        foreach ($book->getWorksheetIterator() as $sheet) {
            yield from $this->sheet($sheet, basename($path));
        }
        $book->disconnectWorksheets();
    }

    private function sheet(Worksheet $sheet, string $file): \Generator
    {
        $ctx = $this->freshContext($file);
        $tables = [];            // [['from' => col, 'to' => col, 'map' => [field => col], 'kind' => farmer|plot, 'ctx' => [...]]]
        $titlesSinceData = false;

        foreach ($sheet->getRowIterator() as $row) {
            $r = $row->getRowIndex();
            $cells = [];
            $it = $row->getCellIterator();
            $it->setIterateOnlyExistingCells(true);
            foreach ($it as $cell) {
                $v = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $v = $v->getPlainText();
                if (is_string($v)) $v = trim(preg_replace('/\s+/u', ' ', $v));
                if ($v === null || $v === '') continue;
                $cells[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn())] = $v;
            }
            if (! $cells) continue;
            ksort($cells);

            // A header row opens tables over its column ranges (others stay open).
            $groups = $this->headerGroups($cells);
            if ($groups) {
                if ($titlesSinceData || ! $tables) {
                    // Context titles belong to the tables opened now.
                }
                foreach ($groups as $g) {
                    $tables = array_values(array_filter($tables, fn ($t) => $t['to'] < $g['from'] || $t['from'] > $g['to']));
                    $tables[] = $g + ['ctx' => $ctx];
                }
                usort($tables, fn ($a, $b) => $a['from'] <=> $b['from']);
                // Cells of this row outside the new headers may be data of an older side table.
                $rest = array_filter($cells, function ($c) use ($groups) {
                    foreach ($groups as $g) if ($c >= $g['from'] && $c <= $g['to']) return false;
                    return true;
                }, ARRAY_FILTER_USE_KEY);
                foreach ($this->dataRows($rest, $tables, $sheet->getTitle(), $r) as $out) yield $out;
                continue;
            }

            // A title line: few cells, words, no table data in it.
            if ($this->isTitle($cells, $tables)) {
                $text = implode(' | ', array_map('strval', $cells));
                if (preg_match('/MINISTRY OF|BUREAU FOR LAND|ACQUISITION OF LAND/i', $text) && $titlesSinceData === false && $tables) {
                    // A new title block after data: a new list starts (surveyor/section reset).
                    $ctx = $this->freshContext($file, $ctx);
                }
                $ctx = $this->applyTitle($ctx, $text);
                $titlesSinceData = true;
                // Open tables take the new surveyor / section / list (a page that names a new surveyor).
                foreach ($tables as &$t) $t['ctx'] = $ctx;
                unset($t);
                yield ['sheet' => $sheet->getTitle(), 'row' => $r, 'kind' => 'title', 'raw' => $cells, 'ctx' => $ctx, 'fields' => []];
                continue;
            }

            $titlesSinceData = false;
            $any = false;
            foreach ($this->dataRows($cells, $tables, $sheet->getTitle(), $r) as $out) {
                $any = true;
                yield $out;
            }
            if (! $any) {
                yield ['sheet' => $sheet->getTitle(), 'row' => $r, 'kind' => 'other', 'raw' => $cells, 'ctx' => $ctx, 'fields' => []];
            }
        }
    }

    /** Rows of data for each open table that has cells in this row. */
    private function dataRows(array $cells, array $tables, string $sheet, int $r): array
    {
        $out = [];
        foreach ($tables as $t) {
            $mine = array_filter($cells, fn ($c) => $c >= $t['from'] && $c <= $t['to'], ARRAY_FILTER_USE_KEY);
            if (! $mine) continue;
            $f = [];
            foreach ($t['map'] as $field => $col) $f[$field] = $mine[$col] ?? null;

            $name = trim(implode(' ', array_filter([$this->text($f['name'] ?? null), $this->text($f['mname'] ?? null), $this->text($f['surname'] ?? null)])));
            $plot = $this->plot($f['plot'] ?? null);
            $fields = [
                'sn'        => is_numeric($f['sn'] ?? null) ? (int) $f['sn'] : null,
                'fn'        => $this->text($f['fn'] ?? null),
                'name'      => $name !== '' ? $name : null,
                'area'      => $this->num($f['area'] ?? null),
                'creatable' => $this->num($f['creatable'] ?? null),
                'farmer50'  => $this->num($f['farmer50'] ?? null),
                'govt50'    => $this->num($f['govt50'] ?? null),
                'plot'      => $plot,
                'status'    => $this->text($f['status'] ?? null),
                'location'  => $this->text($f['location'] ?? null),
                'plan'      => $this->text($f['plan'] ?? null),
                'lga'       => $this->text($f['lga'] ?? null),
                'sign'      => $this->text($f['sign'] ?? null),
                'remarks'   => trim(implode(' · ', array_filter([$this->text($f['remarks'] ?? null), $this->text($f['landuse'] ?? null)]))) ?: null,
                'naira'     => $this->num($f['naira'] ?? null),
            ];

            if ($fields['name'] && preg_match('/^SECTION\b/i', $fields['name'])) {
                $kind = 'summary';
            } elseif ($fields['name'] || $fields['plot']) {
                $kind = $t['kind'];
            } elseif ($fields['area'] !== null || $fields['creatable'] !== null || $fields['farmer50'] !== null) {
                $kind = 'total';
            } else {
                $kind = 'other';
            }
            $out[] = ['sheet' => $sheet, 'row' => $r, 'kind' => $kind, 'raw' => $mine, 'ctx' => $t['ctx'], 'fields' => $fields, 'table' => $t['kind']];
        }

        return $out;
    }

    /** Header groups in a row: each starts at a S/N, F/N or the NAME before which there is none. */
    private function headerGroups(array $cells): array
    {
        $hits = [];
        foreach ($cells as $col => $v) {
            if (! is_string($v)) continue;
            $k = strtoupper(trim(preg_replace('/\s+/', ' ', $v)));
            if (isset(self::HEADERS[$k])) $hits[$col] = self::HEADERS[$k];
        }
        $fields = array_values($hits);
        // A list headed SURNAME with no NAME column keeps the whole name there (215E LG list).
        if (! in_array('name', $fields, true) && in_array('surname', $fields, true) && in_array('plot', $fields, true)) {
            $hits = array_map(fn ($f) => $f === 'surname' ? 'name' : $f, $hits);
            $fields = array_values($hits);
        }
        if (! in_array('name', $fields, true) || ! (in_array('plot', $fields, true) || in_array('area', $fields, true) || in_array('surname', $fields, true))) {
            return [];
        }

        // Split into groups: a new group starts at sn/fn, or at a name when the current group already has one.
        $groups = [];
        $cur = null;
        foreach ($hits as $col => $field) {
            $starts = $cur === null
                || (in_array($field, ['sn', 'fn'], true) && (isset($cur['map']['name']) || isset($cur['map'][$field])))
                || ($field === 'name' && isset($cur['map']['name']));
            if ($starts) {
                if ($cur) $groups[] = $cur;
                $cur = ['from' => $col, 'to' => $col, 'map' => []];
            }
            if (! isset($cur['map'][$field])) $cur['map'][$field] = $col;
            $cur['to'] = $col;
        }
        if ($cur) $groups[] = $cur;

        $groups = array_values(array_filter($groups, fn ($g) => isset($g['map']['name'])));
        // Five unlabelled columns between the names and PLOTS (RGFD): m², ha, creatable, govt 50%, farmer 50%.
        foreach ($groups as &$g) {
            if (! isset($g['map']['area']) && isset($g['map']['plot'])) {
                $lastName = max(array_intersect_key($g['map'], array_flip(['name', 'mname', 'surname'])));
                $labelled = array_filter($g['map'], fn ($c) => $c > $lastName && $c < $g['map']['plot']);
                if ($g['map']['plot'] - $lastName - 1 === 5 && ! $labelled) {
                    $g['map'] += ['area_m2' => $lastName + 1, 'area' => $lastName + 2, 'creatable' => $lastName + 3, 'govt50' => $lastName + 4, 'farmer50' => $lastName + 5];
                }
            }
        }
        unset($g);
        foreach ($groups as $i => &$g) {
            // A table reaches to the column before the next one (sign / remarks columns may be unlabelled).
            $g['to'] = isset($groups[$i + 1]) ? $groups[$i + 1]['from'] - 1 : max($g['to'] + 2, $g['to']);
            $g['kind'] = isset($g['map']['area']) || isset($g['map']['creatable']) || isset($g['map']['farmer50']) ? 'farmer' : 'plot';
        }

        return $groups;
    }

    private function isTitle(array $cells, array $tables): bool
    {
        $strings = array_filter($cells, 'is_string');
        if (count($cells) > 3 || count($strings) !== count($cells)) return false;
        $text = strtoupper(implode(' ', $strings));
        if (preg_match('/MINISTRY|BUREAU|SURVEY|ACQUISITION|LAYOUT|LOCAL GOV|\bLGA\b|TP\/|BLM\/|\bCO\s*\/|LIST|SUMMARY|PERMIT|SECTION|PENDING|EXTENSION|PHASE|OFFICE OF|KANO STATE|RESIDENTIAL|ENTITLEMENT|AT [A-Z]/', $text)) {
            // A one-word name in a name column is data, not a title.
            if ($tables && count($cells) <= 2 && ! preg_match('/\s/', trim($text))) return false;
            return true;
        }

        return false;
    }

    private function freshContext(string $file, ?array $prev = null): array
    {
        return [
            'file'      => $file,
            'plan_no'   => $prev['plan_no'] ?? null,
            'project'   => $prev['project'] ?? null,
            'purpose'   => $prev['purpose'] ?? null,
            'lga'       => $prev['lga'] ?? null,
            'co'        => $prev['co'] ?? null,
            'surveyor'  => null,
            'section'   => null,
            'list'      => $prev['list'] ?? null,
            'title_set' => false,
        ];
    }

    private function applyTitle(array $ctx, string $text): array
    {
        $up = strtoupper($text);

        if (preg_match('/\b((?:TP|BLM)\/[A-Z]+(?:\/[A-Z0-9]+)*)/', $up, $m)) {
            $ctx['plan_no'] = rtrim($m[1], '/');
            if (preg_match('/' . preg_quote($m[1], '/') . '\s*[-–]\s*([A-Z][A-Z\/ &\'.,]+?)(?:\(|$|\|)/', $up, $n)) {
                $ctx['project'] = trim($n[1], " ,.");
            }
        }
        // The words after the plan no.: "TP/KNUPDA/333A RIGAFADA KUMBOTSO LGA, KANO" → RIGAFADA.
        if (preg_match('/\b(?:TP|BLM)\/[A-Z]+(?:\/[A-Z0-9]+)*\)?\s*,?\s+([A-Z][A-Z\/ \'.]+)/', $up, $m)) {
            $after = trim(preg_replace('/\b(?:' . $this->lgaPattern() . '|UNGOGGO|LOCAL|LGA|KANO)\b.*$/', '', $m[1]), " ,.-");
            if ($after !== '') $ctx['project'] = $after;
        }
        // "ACQUISITION OF LAND FOR KUYAN TA INNA TP/KNUPDA/338E" → KUYAN TA INNA.
        if (preg_match('/ACQUISITION OF LAND FOR\s+([A-Z][A-Z\/ \'.]+?)\s+(?:TP|BLM)\//', $up, $m) && ! preg_match('/RESIDENTIAL|COMMERCIAL|LAYOUT|INDUSTRIAL/', $m[1])) {
            $ctx['project'] = trim($m[1]);
        }
        // "LAYOUT AT DAUSAYI (TP/...)" → DAUSAYI; "LAYOUT AT KUMBOTSO" names an LGA, not the layout.
        if (preg_match('/\bLAYOUT AT\s+([A-Z][A-Z\/ &\'.]+?)\s*(?:\(|TP\/|$|\|)/', $up, $m) && ! $this->isLgaName($m[1])) {
            $ctx['project'] = trim($m[1]);
        } elseif (preg_match('/ACQUISITION OF LAND FOR [A-Z ]+? AT ([A-Z][A-Z\/ &\'.]+?)\s*(?:TP\/|\(|$|\|)/', $up, $m) && ! $this->isLgaName($m[1])) {
            $ctx['project'] = trim($m[1]);
        }
        if (preg_match('/ACQUISITION OF LAND FOR\s+(?:PROPOSED\s+)?([A-Z&\/ ]+?)(?:\s+LAYOUT|\s+AT\b|$|\|)/', $up, $m)) {
            $p = trim(preg_replace('/\s+/', ' ', str_replace('&', ' & ', $m[1])));
            if ($p === 'LAYOUT') $ctx['purpose'] = 'Layout';
            elseif (preg_match('/RESIDENTIAL|COMMERCIAL|INDUSTRIAL|DENSITY|INSTITUTIONAL/', $p)) $ctx['purpose'] =mb_convert_case(mb_strtolower($p), MB_CASE_TITLE) . (str_contains($up, 'LAYOUT') ? ' Layout' : '');
        }
        if (preg_match('/\bCO\s*\/\s*0*(\d{1,4})\s*\/\s*(\d{4})/', $up, $m)) {
            $ctx['co'] = 'CO-' . (int) $m[1] . '-' . $m[2];
        }
        if (preg_match('/SURVEY(?:ED)? BY\s*:?\s*-?\s*(.+)$/', $up, $m)) {
            $who = trim(preg_replace('/\bSECTION\s+\S+.*$/', '', $m[1]), " -:|");
            $ctx['surveyor'] = $who !== '' ? $this->personName($who) : null;
        }
        if (preg_match('/\bSECTION\s+([0-9]+[A-Z]?|[A-Z]{1,2}\b|ONE|TWO|THREE|FOUR|FIVE|SIX|SEVEN|EIGHT|NINE)\b/', $up, $m) && ! preg_match('/SECTION\s+1\s*-\s*8/', $up)) {
            $ctx['section'] = $m[1];
        }
        if (preg_match('/(?:LOCAL GOV|\bLGA\b|\bL\.G\b)/', $up)) {
            $ctx['lga'] = $this->lga($up) ?? $ctx['lga'];
        }
        if (preg_match('/LIST|SUMMARY|OCCUPANCY PERMIT|PENDING|ENTITLEMENT|OMMISSION/', $up) && ! preg_match('/SURVEY(ED)? BY/', $up)) {
            $ctx['list'] = trim(preg_replace('/\(?CO\s*\/[^)]*\)?/', '', $text), " |");
        }

        return $ctx;
    }

    /** The Kano LGA named in a line, tolerating the sheets' spellings (UNGOGGO, KUMBOTSO LGA, …). */
    public function lga(?string $text): ?string
    {
        $flat = preg_replace('/[^A-Z]/', '', strtoupper((string) $text));
        if ($flat === '') return null;
        $flat = str_replace(['UNGOGGO', 'DAWAKINKUDU', 'NASSARAWA'], ['UNGOGO', 'DAWAKINKUDU', 'NASARAWA'], $flat);
        $found = [];
        foreach (self::LGAS as $l) {
            $k = preg_replace('/[^A-Z]/', '', strtoupper($l));
            if (($p = strpos($flat, $k)) !== false) $found[$p] = $l;
        }
        ksort($found);

        return $found ? implode(' | ', array_unique($found)) : null;
    }

    private function lgaPattern(): string
    {
        return implode('|', array_map(fn ($l) => preg_quote(strtoupper($l), '/'), self::LGAS));
    }

    private function isLgaName(string $s): bool
    {
        $flat = preg_replace('/[^A-Z]/', '', strtoupper($s));
        foreach (self::LGAS as $l) {
            if (preg_replace('/[^A-Z]/', '', strtoupper($l)) === $flat) return true;
        }

        return $flat === 'UNGOGGO';
    }

    /** "ABDULLAHI M. T/WADA" →"Abdullahi M. T/Wada": person names in title case, initials kept. */
    public function personName(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return preg_replace_callback('/[A-Za-z\']+/', fn ($m) => ucfirst(strtolower($m[0])), $s);
    }

    /** A plot cell as text: 141 → "141", "1518,1519" kept, 12.0 → "12". */
    private function plot($v): ?string
    {
        if ($v === null) return null;
        if (is_float($v) && floor($v) == $v) $v = (int) $v;
        $s = trim(preg_replace('/\s+/', ' ', (string) $v));

        return $s === '' ? null : rtrim($s, ', ');
    }

    private function num($v): ?float
    {
        if (is_int($v) || is_float($v)) return (float) $v;
        if (is_string($v) && is_numeric(str_replace(',', '', $v))) return (float) str_replace(',', '', $v);

        return null;
    }

    private function text($v): ?string
    {
        if ($v === null) return null;
        if (is_float($v) && floor($v) == $v) $v = (int) $v;
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $s === '' ? null : $s;
    }

    public static function isPlaceholderName(?string $name): bool
    {
        return $name === null || in_array(strtoupper(trim($name)), self::PLACEHOLDER_NAMES, true);
    }
}
