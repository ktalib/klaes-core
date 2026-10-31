<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports the "ALL COMPENSATION" workbooks (user 2026-10-30) into the Survey records,
 * as the user chose: both kinds of list, as real records, every row loaded and
 * tagged with its source.
 *
 *   (CO/02/2018) in a title       captured CO file CO-2-2018
 *   plan no. + layout name        project (land-to-land scheme), plan_no kept
 *   SURVEYED BY:- <name>          project surveyor (matched to the Survey staff list by name)
 *   a surveyor's block / a list   land (survey_comp_cases), status Completed
 *   each row                      allocation line (survey_plot_allocations): S/N, F/N,
 *                                 name, area, creatable, Govt / Farmer 50%, plot(s), status
 *   each distinct name on a land  farmer (survey_beneficiaries), Pending
 *   every source row              survey_comp_import_rows (file / sheet / row, raw JSON,
 *                                 what it became, likely duplicates across files flagged)
 *
 * Everything written carries import_ref, so undo() removes one batch completely.
 * Each land is committed on its own: klas has no snapshot isolation, so a long
 * transaction would block live reads.
 */
class CompensationListImport
{
    private const CONN = 'sqlsrv';

    /** Allocation lines per insert (sqlsrv allows 2100 parameters). */
    private const CHUNK = 120;

    private array $projects = [];   // planKey => [...]
    private array $lands = [];      // landKey => [...]
    private array $rows = [];       // staged rows
    private array $byKind = [];
    private array $files = [];

    public function __construct(private CompensationListReader $reader)
    {
    }

    /** Read every workbook in $dir and group the rows. Nothing is written. */
    public function plan(string $dir): array
    {
        $paths = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.xlsx') ?: [];
        sort($paths);
        foreach ($paths as $path) {
            $file = basename($path);
            $this->files[$file] = ['farmer' => 0, 'plot' => 0, 'total' => 0, 'summary' => 0, 'other' => 0];
            $rows = [];
            $plans = [];
            foreach ($this->reader->read($path) as $row) {
                if ($row['kind'] === 'title') continue;
                $rows[] = $row;
                if (in_array($row['kind'], ['farmer', 'plot'], true) && ($p = $this->rowPlan($row))) $plans[$p] = ($plans[$p] ?? 0) + 1;
            }
            // A plan on a few rows only, where another has hundreds, is an Excel drag-fill slip (GZ/02 → GZ/03, GZ/04 …).
            arsort($plans);
            $main = array_key_first($plans);
            $fix = [];
            foreach ($plans as $p => $n) {
                if ($p !== $main && $n <= 3 && $plans[$main] >= 100 && $this->planKey($p, $file) !== $this->planKey($main, $file)) $fix[$p] = $main;
            }
            foreach ($rows as $row) {
                if (($p = $this->rowPlan($row)) && isset($fix[$p])) $row['fields']['plan'] = $fix[$p];
                $this->add($file, $row);
            }
        }
        $this->flagDuplicates();

        return $this->summary();
    }

    private function add(string $file, array $row): void
    {
        $ctx = $row['ctx'];
        $f = $row['fields'];
        $kind = $row['kind'];
        $this->files[$file][$kind] = ($this->files[$file][$kind] ?? 0) + 1;
        $this->byKind[$kind] = ($this->byKind[$kind] ?? 0) + 1;

        $plan = $f['plan'] ?? null;
        $plan = $plan && preg_match('/^(TP|BLM)\//i', $plan) ? strtoupper($plan) : ($ctx['plan_no'] ?? null);
        $planKey = $this->planKey($plan, $file);

        $staged = [
            'source_file'  => $file,
            'source_sheet' => mb_substr($row['sheet'], 0, 100),
            'source_row'   => $row['row'],
            'kind'         => $kind,
            'raw'          => json_encode($row['raw'], JSON_UNESCAPED_UNICODE),
            'plan_key'     => in_array($kind, ['farmer', 'plot'], true) ? $planKey : null,
            'surveyor_name' => $ctx['surveyor'] ?? null,
            'farmer_name'  => isset($f['name']) ? mb_substr($f['name'], 0, 255) : null,
            'plot_no'      => isset($f['plot']) ? mb_substr($f['plot'], 0, 400) : null,
            'land'         => null,
            'line'         => null,
        ];

        if (in_array($kind, ['farmer', 'plot'], true)) {
            $p = &$this->projects[$planKey];
            $p ??= ['key' => $planKey, 'plans' => [], 'names' => [], 'purposes' => [], 'lgas' => [], 'cos' => [], 'files' => [], 'area' => 0.0, 'lines' => 0];
            if ($plan) $p['plans'][$plan] = ($p['plans'][$plan] ?? 0) + 1;
            $name = $ctx['project'] ?: $this->nameFromFile($file);
            $p['names'][$name] = ($p['names'][$name] ?? 0) + 1;
            if ($ctx['purpose']) $p['purposes'][$ctx['purpose']] = ($p['purposes'][$ctx['purpose']] ?? 0) + 1;
            $lga = $this->reader->lga($f['lga'] ?? null) ?? $ctx['lga'];
            if ($lga) $p['lgas'][$lga] = ($p['lgas'][$lga] ?? 0) + 1;
            if ($ctx['co']) $p['cos'][$ctx['co']] = ($p['cos'][$ctx['co']] ?? 0) + 1;
            $p['files'][$file] = true;
            $p['area'] += (float) ($f['area'] ?? 0);
            $p['lines']++;
            unset($p);

            $surveyor = $kind === 'farmer' ? ($ctx['surveyor'] ?? null) : null;
            $section = $kind === 'farmer' ? ($ctx['section'] ?? null) : null;
            $landKey = implode('|', [$file, $row['sheet'], $planKey, $kind, $surveyor, $section, $ctx['list']]);
            $l = &$this->lands[$landKey];
            $l ??= ['key' => $landKey, 'plan_key' => $planKey, 'kind' => $kind, 'file' => $file, 'sheet' => $row['sheet'], 'surveyor' => $surveyor,
                    'section' => $section, 'list' => $ctx['list'], 'lga' => null, 'locations' => [], 'from' => $row['row'], 'to' => $row['row'], 'lines' => []];
            $l['to'] = $row['row'];
            if ($lga && ! $l['lga']) $l['lga'] = $lga;
            if ($f['location'] ?? null) $l['locations'][$f['location']] = ($l['locations'][$f['location']] ?? 0) + 1;
            $l['lines'][] = count($this->rows);
            unset($l);
            $staged['land'] = $landKey;

            $nameKey = $f['name'] ? preg_replace('/[^A-Z]/', '', strtoupper($f['name'])) : '';
            $plotKey = $f['plot'] ? preg_replace('/\s+/', '', strtoupper($f['plot'])) : '';
            $staged['duplicate_key'] = $plotKey !== '' ? mb_substr("{$planKey}|{$plotKey}|{$nameKey}", 0, 500) : null;
        }

        $staged['fields'] = $f;
        $this->rows[] = $staged;
    }

    /** A likely duplicate: the same plot and name under the same plan in another file. */
    private function flagDuplicates(): void
    {
        $filesByKey = [];
        foreach ($this->rows as $r) {
            if (! empty($r['duplicate_key'])) $filesByKey[$r['duplicate_key']][$r['source_file']] = true;
        }
        foreach ($this->rows as &$r) {
            $r['is_duplicate'] = ! empty($r['duplicate_key']) && count($filesByKey[$r['duplicate_key']]) > 1;
        }
        unset($r);
    }

    public function summary(): array
    {
        $staff = $this->staffIndex();
        $projects = collect($this->projects)->map(function ($p) {
            return [
                'key'     => $p['key'],
                'plan_no' => $this->top($p['plans']),
                'name'    => $this->projectName($p),
                'purpose' => $this->top($p['purposes']),
                'lga'     => $this->top($p['lgas']),
                'co'      => $this->top($p['cos']),
                'cos'     => array_keys($p['cos']),
                'files'   => count($p['files']),
                'lines'   => $p['lines'],
                'area'    => round($p['area'], 4),
            ];
        })->sortBy('key')->values();

        $surveyors = collect($this->lands)->where('kind', 'farmer')->groupBy(fn ($l) => $l['plan_key'] . '|' . ($l['surveyor'] ?? ''))
            ->map(fn ($g) => ['plan_key' => $g->first()['plan_key'], 'name' => $g->first()['surveyor'], 'lands' => $g->count(),
                              'staff_id' => $g->first()['surveyor'] ? $this->matchStaff($g->first()['surveyor'], $staff) : null])->values();

        return [
            'files'      => $this->files,
            'kinds'      => $this->byKind,
            'projects'   => $projects,
            'lands'      => count($this->lands),
            'surveyors'  => $surveyors,
            'duplicates' => collect($this->rows)->where('is_duplicate', true)->count(),
            'long_plots' => collect($this->rows)->filter(fn ($r) => mb_strlen((string) ($r['fields']['plot'] ?? '')) > 400)->count(),
            'rows'       => count($this->rows),
        ];
    }

    /**
     * Write the batch. $userId stamps created_by. Returns counts.
     */
    public function commit(string $ref, ?int $userId, ?callable $progress = null): array
    {
        $db = DB::connection(self::CONN);
        $now = now();
        $stamp = ['created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now, 'import_ref' => $ref];
        $staff = $this->staffIndex();
        $counts = ['co_files' => 0, 'projects' => 0, 'surveyors' => 0, 'lands' => 0, 'farmers' => 0, 'lines' => 0, 'staged' => 0];

        // CO files: captured, by number; an existing one with the same number is used, not duplicated.
        $coIds = [];
        $summary = $this->summary();
        foreach ($summary['projects']->groupBy('co') as $co => $ps) {
            if (! $co) continue;
            $no = SurveyCoFile::normaliseNumber($co);
            if (! $no) continue;
            $existing = SurveyCoFile::withTrashed()->where('co_file_no', $no)->first();
            if ($existing) {
                $coIds[$co] = $existing->id;
                continue;
            }
            $lga = $ps->pluck('lga')->filter()->first();
            $coIds[$co] = $db->table('survey_co_files')->insertGetId($stamp + [
                'co_file_no'      => $no,
                'title'           => mb_substr($ps->pluck('name')->unique()->implode(' / '), 0, 255),
                'source'          => SurveyCoFile::SOURCE_CAPTURED,
                'status'          => SurveyCoFile::CAPTURED_STATUS,
                'commission_date' => null,
                'prop_lga'        => $lga,
                'prop_state'      => 'Kano',
                'remarks'         => 'Captured from the ALL COMPENSATION lists (' . $ps->pluck('plan_no')->filter()->unique()->implode(', ') . ').',
            ]);
            $counts['co_files']++;
        }

        // Projects.
        $projectIds = [];
        foreach ($summary['projects'] as $p) {
            $projectIds[$p['key']] = $db->table('survey_projects')->insertGetId($stamp + [
                'project_code'        => SurveyProject::nextRef('project_code', 'PRJ'),
                'name'                => mb_substr($p['name'], 0, 255),
                'purpose'             => $p['purpose'] ? mb_substr($p['purpose'], 0, 100) : null,
                'scheme_type'         => SurveyProject::SCHEME_LAND,
                'status'              => 'Active',
                'plan_no'             => $p['plan_no'],
                'description'         => 'Imported from the ALL COMPENSATION lists'
                                         . (count($p['cos']) > 1 ? '. CO numbers on the lists: ' . implode(', ', $p['cos']) : '') . '.',
                'prop_lga'            => $p['lga'],
                'prop_state'          => 'Kano',
                'survey_co_file_id'   => $p['co'] ? ($coIds[$p['co']] ?? null) : null,
                'acquisition_size_ha' => $p['area'] > 0 ? $p['area'] : null,
            ]);
            $counts['projects']++;
        }

        // Project surveyors: one per surveyor per project; each of their blocks is a portion.
        $surveyorIds = [];
        $portionNo = [];
        foreach (collect($this->lands)->where('kind', 'farmer')->whereNotNull('surveyor')->groupBy(fn ($l) => $l['plan_key'] . '|' . $l['surveyor']) as $k => $ls) {
            $area = $ls->sum(fn ($l) => collect($l['lines'])->sum(fn ($i) => (float) ($this->rows[$i]['fields']['area'] ?? 0)));
            $first = $ls->first();
            $surveyorIds[$k] = $db->table('survey_project_surveyors')->insertGetId($stamp + [
                'survey_project_id' => $projectIds[$first['plan_key']],
                'surveyor_id'       => $this->matchStaff($first['surveyor'], $staff),
                'surveyor_name'     => mb_substr($first['surveyor'], 0, 255),
                'portions'          => $ls->count(),
                'portion_size_ha'   => round($area / max(1, $ls->count()), 4),
            ]);
            $counts['surveyors']++;
        }

        // Lands, each with its farmers, allocation lines and staged rows, committed one at a time.
        foreach ($this->lands as $land) {
            $db->transaction(function () use ($db, $land, $stamp, $projectIds, $surveyorIds, &$portionNo, &$counts, $ref) {
                $lines = collect($land['lines'])->map(fn ($i) => $this->rows[$i]);
                $area = $lines->sum(fn ($r) => (float) ($r['fields']['area'] ?? 0));
                $plots = $lines->sum(fn ($r) => $this->countPlots($r['fields']['plot'] ?? null));
                $sk = $land['surveyor'] ? $land['plan_key'] . '|' . $land['surveyor'] : null;
                $portion = null;
                if ($sk) {
                    $portionNo[$sk] = ($portionNo[$sk] ?? 0) + 1;
                    $portion = $portionNo[$sk];
                }
                arsort($land['locations']);
                $location = array_key_first($land['locations']);

                $caseId = $db->table('survey_comp_cases')->insertGetId($stamp + [
                    'case_ref'                   => SurveyCompCase::nextRef('case_ref', 'C'),
                    'survey_project_id'          => $projectIds[$land['plan_key']],
                    'scheme_type'                => SurveyProject::SCHEME_LAND,
                    'purpose'                    => null,
                    'survey_officer'             => $land['surveyor'],
                    'area_ha'                    => $area > 0 ? round($area, 4) : null,
                    'num_plots'                  => $plots ?: null,
                    'status'                     => 'Completed',
                    'description'                => trim(($land['kind'] === 'farmer' ? 'Farmer compensation list' : 'Resettlement plot allocation list')
                        . ($land['list'] ? ': ' . $land['list'] : '') . ($land['surveyor'] ? '. Surveyed by ' . $land['surveyor'] : '')
                        . ($land['section'] ? ', Section ' . $land['section'] : '') . ($location ? '. Location: ' . $location : '')
                        . ". Imported from {$land['file']} › {$land['sheet']} (rows {$land['from']}–{$land['to']})."),
                    'prop_lga'                   => $land['lga'],
                    'prop_state'                 => 'Kano',
                    'survey_project_surveyor_id' => $sk ? ($surveyorIds[$sk] ?? null) : null,
                    'portion_no'                 => $portion,
                    'portion_size_ha'            => $area > 0 ? round($area, 4) : null,
                ]);
                $counts['lands']++;

                // Farmers: each distinct real name once (placeholders such as ABSENT are lines, not farmers).
                $farmers = [];
                foreach ($lines as $r) {
                    $n = $r['fields']['name'] ?? null;
                    if (CompensationListReader::isPlaceholderName($n)) continue;
                    $k = preg_replace('/[^A-Z]/', '', strtoupper($n));
                    if ($k !== '' && ! isset($farmers[$k])) $farmers[$k] = mb_substr($n, 0, 255);
                }
                foreach (array_chunk(array_values($farmers), 200) as $chunk) {
                    $db->table('survey_beneficiaries')->insert(array_map(fn ($n) => $stamp + [
                        'survey_comp_case_id' => $caseId, 'full_name' => $n, 'status' => 'Pending', 'addr_state' => 'Kano',
                    ], $chunk));
                    $counts['farmers'] += count($chunk);
                }

                // Allocation lines, then the staged rows pointing at them.
                foreach ($lines->chunk(self::CHUNK) as $chunk) {
                    $insert = $chunk->map(function ($r) use ($caseId, $stamp) {
                        $f = $r['fields'];
                        $plot = $f['plot'] ?? null;
                        $remarks = array_filter([$f['status'] ?? null, $f['remarks'] ?? null,
                            ($f['naira'] ?? null) ? '₦' . number_format($f['naira'], 2) : null,
                            $plot !== null && mb_strlen($plot) > 400 ? 'Plots: ' . $plot : null,
                            $r['is_duplicate'] ? 'Also on another list' : null]);

                        return $stamp + [
                            'survey_comp_case_id' => $caseId,
                            'sr'                  => $f['sn'] ?? null,
                            'f_no'                => isset($f['fn']) ? mb_substr($f['fn'], 0, 50) : null,
                            'farmer_name'         => isset($f['name']) ? mb_substr($f['name'], 0, 255) : null,
                            'acreage'             => isset($f['area']) ? round($f['area'], 4) : null,
                            'creetable'           => isset($f['creatable']) ? (string) round($f['creatable'], 4) : null,
                            'govt_50'             => isset($f['govt50']) ? round($f['govt50'], 4) : null,
                            'farmer_50'           => isset($f['farmer50']) ? round($f['farmer50'], 4) : null,
                            'plot_no'             => $plot !== null ? mb_substr($plot, 0, 400) : null,
                            'sign'                => isset($f['sign']) ? mb_substr($f['sign'], 0, 100) : null,
                            'remarks'             => $remarks ? mb_substr(implode(' · ', $remarks), 0, 500) : null,
                        ];
                    })->values()->all();
                    $db->table('survey_plot_allocations')->insert($insert);
                    $counts['lines'] += count($insert);
                }
            });
            if ($progress) $progress($counts);
        }

        // Link staged rows to their land and line by order (lines were inserted in staged order per land).
        $caseIds = $db->table('survey_comp_cases')->where('import_ref', $ref)->orderBy('id')->pluck('id')->values();
        $landKeys = array_keys($this->lands);
        $lineIdsByCase = [];
        foreach ($db->table('survey_plot_allocations')->where('import_ref', $ref)->orderBy('id')->get(['id', 'survey_comp_case_id']) as $x) {
            $lineIdsByCase[$x->survey_comp_case_id][] = $x->id;
        }
        $landCase = [];
        foreach ($landKeys as $i => $k) $landCase[$k] = $caseIds[$i] ?? null;
        $cursor = [];
        $buffer = [];
        $flush = function () use (&$buffer, $db, &$counts) {
            if (! $buffer) return;
            $db->table('survey_comp_import_rows')->insert($buffer);
            $counts['staged'] += count($buffer);
            $buffer = [];
        };
        foreach ($this->rows as $r) {
            $case = $r['land'] ? $landCase[$r['land']] : null;
            $line = null;
            if ($case) {
                $cursor[$case] = ($cursor[$case] ?? -1) + 1;
                $line = $lineIdsByCase[$case][$cursor[$case]] ?? null;
            }
            $buffer[] = [
                'import_ref' => $ref, 'source_file' => mb_substr($r['source_file'], 0, 255), 'source_sheet' => $r['source_sheet'], 'source_row' => $r['source_row'],
                'kind' => $r['kind'], 'raw' => $r['raw'], 'plan_key' => $r['plan_key'], 'surveyor_name' => $r['surveyor_name'] ? mb_substr($r['surveyor_name'], 0, 255) : null,
                'farmer_name' => $r['farmer_name'], 'plot_no' => $r['plot_no'], 'survey_comp_case_id' => $case, 'survey_plot_allocation_id' => $line,
                'duplicate_key' => $r['duplicate_key'] ?? null, 'is_duplicate' => ! empty($r['is_duplicate']), 'note' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
            if (count($buffer) >= self::CHUNK) $flush();
        }
        $flush();

        return $counts;
    }

    /** Remove one batch from every table it wrote to. */
    public static function undo(string $ref): array
    {
        $db = DB::connection(self::CONN);
        $out = [];
        foreach (['survey_comp_import_rows', 'survey_plot_allocations', 'survey_beneficiaries', 'survey_comp_cases', 'survey_project_surveyors', 'survey_projects', 'survey_co_files'] as $t) {
            $n = 0;
            do {
                $ids = $db->table($t)->where('import_ref', $ref)->limit(1000)->pluck('id')->all();
                if ($ids) $n += $db->table($t)->whereIn('id', $ids)->delete();
            } while ($ids);
            $out[$t] = $n;
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    private function rowPlan(array $row): ?string
    {
        $plan = $row['fields']['plan'] ?? null;

        return $plan && preg_match('/^(TP|BLM)\//i', $plan) ? strtoupper($plan) : ($row['ctx']['plan_no'] ?? null);
    }

    /** Projects by layout: TP/KNUPDA/215E → 215E; TP/KNUPDA/UC/GZ/02 → GZ/02; BLM/PP/005 → BLM/005; none → the file. */
    public function planKey(?string $plan, string $file): string
    {
        if ($plan) {
            $parts = array_values(array_filter(explode('/', strtoupper(trim($plan))), 'strlen'));
            $last = end($parts);
            if (preg_match('/^\d{1,2}$/', $last) && count($parts) > 2) $last = $parts[count($parts) - 2] . '/' . $last;
            if ($parts[0] === 'BLM') $last = 'BLM/' . $last;

            return $last;
        }

        return 'FILE:' . strtoupper(pathinfo($file, PATHINFO_FILENAME));
    }

    /** "215G KUDIDDIFAWA list.xlsx" → "KUDIDDIFAWA". */
    private function nameFromFile(string $file): string
    {
        $s = strtoupper(pathinfo($file, PATHINFO_FILENAME));
        $s = preg_replace('/\b(LIST|UPDATED|FINAL|ALL|SUBMITTED|COMPENSATION)\b/', '', $s);
        $s = preg_replace('/^\s*\d+[A-Z]?\s*/', '', $s);

        return trim(preg_replace('/\s+/', ' ', $s)) ?: strtoupper(pathinfo($file, PATHINFO_FILENAME));
    }

    private function projectName(array $p): string
    {
        $name = $this->top($p['names']) ?? $p['key'];
        $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        // Roman numerals and vowel-less abbreviations stay upper case: "Phase II", "RGFD".
        $name = preg_replace_callback('/\b(?:[IVX]{1,4}|[B-DF-HJ-NP-TV-Z]{2,5})\b/i', fn ($m) => strtoupper($m[0]), $name);

        // The TP no. is the project's own field (plan_no), shown in its own column; not repeated in the name.
        return $name;
    }

    private function top(array $counts): ?string
    {
        if (! $counts) return null;
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** "1518,1519" → 2; "707-712" → 6; "141" → 1. */
    public static function countPlots(?string $plot): int
    {
        if ($plot === null || $plot === '') return 0;
        $n = 0;
        foreach (preg_split('/\s*[,;&]\s*/', $plot) as $p) {
            if ($p === '') continue;
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $p, $m) && $m[2] >= $m[1] && $m[2] - $m[1] < 500) $n += $m[2] - $m[1] + 1;
            else $n++;
        }

        return $n;
    }

    public static function staffIndex(): Collection
    {
        return SurveyorListEntry::all(['id', 'first_name', 'surname', 'other_name'])->map(fn ($s) => [
            'id'     => $s->id,
            'tokens' => array_values(array_filter(preg_split('/[^A-Z]+/', strtoupper("{$s->first_name} {$s->surname} {$s->other_name}")), fn ($t) => strlen($t) > 1)),
        ]);
    }

    /** The staff entry whose name words include every full word of the list's name (initials ignored); only when unique. */
    public static function matchStaff(string $name, Collection $staff): ?int
    {
        $want = array_values(array_filter(preg_split('/[^A-Z]+/', strtoupper($name)), fn ($t) => strlen($t) > 1));
        if (count($want) < 2) return null;
        $hits = $staff->filter(fn ($s) => ! array_diff($want, $s['tokens']));

        return $hits->count() === 1 ? $hits->first()['id'] : null;
    }
}
