<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use Illuminate\Support\Facades\DB;

/**
 * CR 11112026-2 item 9a: the staged Computation Sheet (CS-…) and KMC Access farm records (KMC-…) become real
 * Survey records (user, 2026-11-12):
 *
 *   the whole batch        one project, "KMC Phase 1 Compensation"
 *   each sheet (Q16, P12…) one land (survey_comp_cases); KMC farm records join the land of their Sheet No
 *   each staged line       an allocation line (survey_plot_allocations) and a farm (survey_beneficiaries,
 *                          with a new FRM- Farm ID, the photo and the district; the KMC Property ID stays on
 *                          the line as F/N)
 *   each person            a farmer (survey_farmers, FMR-). User's choice: a name matching exactly one
 *                          farmer already on record is linked to that person (who gets a Farmer ID); a name
 *                          matching several is not guessed, it gets a new farmer and the line says so.
 *
 * Areas1 (land areas with no farmer or Farm ID) stays in staging. Everything written carries import_ref;
 * undo() removes it and clears the Farmer IDs it set on existing farms. Each land commits on its own.
 */
class ComputationSheetPromote
{
    private const CONN = 'sqlsrv';
    private const CHUNK = 100;
    public const PROJECT_NAME = 'KMC Phase 1 Compensation';

    private array $lines = [];      // staged lines, in sheet/row order
    private array $lands = [];      // sheet key => [line indexes]
    private array $persons = [];    // name key => ['name', 'existing' => ben id|null, 'same_name' => n]
    private array $stats = [];

    public function __construct(private array $refs = ['CS-20261106-1', 'KMC-20261111-1'])
    {
    }

    public static function nameKey(?string $s): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper((string) $s));
    }

    /** Read the staging rows and the farmers on record. Nothing is written. */
    public function plan(): array
    {
        $db = DB::connection(self::CONN);
        [$cs, $kmc] = $this->refs;
        $rows = $db->table(ComputationSheetImport::TABLE)->where('kind', 'line')
            ->where(fn ($q) => $q->where('import_ref', $cs)->orWhere(fn ($q) => $q->where('import_ref', $kmc)->where('sheet_name', 'farm records')))
            ->orderByRaw('CASE WHEN import_ref = ? THEN 0 ELSE 1 END', [$cs])->orderBy('sheet_name')->orderBy('source_row')->get();

        $existing = [];
        foreach ($db->table('survey_beneficiaries')->whereNull('deleted_at')->select('id', 'full_name')->orderBy('id')->cursor() as $b) {
            $existing[self::nameKey($b->full_name)][] = $b->id;
        }

        foreach ($rows as $r) {
            $name = trim(preg_replace('/\s+/', ' ', "{$r->first_name} {$r->middle_name} {$r->last_name}"));
            $sheet = $r->import_ref === $cs ? $r->sheet_name : (strtoupper(trim((string) $r->sheet_no)) ?: 'No sheet');
            $key = self::nameKey($name);
            if ($key !== '' && ! isset($this->persons[$key])) {
                $hits = $existing[$key] ?? [];
                $this->persons[$key] = ['name' => $name, 'existing' => count($hits) === 1 ? $hits[0] : null, 'same_name' => count($hits)];
            }
            $this->lands[$sheet][] = count($this->lines);
            $this->lines[] = ['r' => $r, 'name' => $name, 'key' => $key, 'sheet' => $sheet];
        }

        $p = collect($this->persons);
        $l = collect($this->lines)->filter(fn ($x) => $x['key'] !== '');

        return $this->stats = [
            'lines'          => count($this->lines),
            'cs_lines'       => $rows->where('import_ref', $cs)->count(),
            'kmc_lines'      => $rows->where('import_ref', $kmc)->count(),
            'lands'          => count($this->lands),
            'no_name'        => count($this->lines) - $l->count(),
            'persons'        => $p->count(),
            'linked'         => $p->whereNotNull('existing')->count(),
            'linked_lines'   => $l->filter(fn ($x) => $this->persons[$x['key']]['existing'])->count(),
            'ambiguous'      => $p->where('same_name', '>', 1)->count(),
            'ambiguous_lines'=> $l->filter(fn ($x) => $this->persons[$x['key']]['same_name'] > 1)->count(),
            'new_persons'    => $p->where('same_name', 0)->count(),
            'photos'         => $rows->whereNotNull('photo_path')->count(),
            'area_ha'        => round($rows->sum(fn ($r) => (float) $r->area_ha), 4),
            'land_sizes'     => array_map('count', $this->lands),
        ];
    }

    /** The farms this batch will give a Farmer ID to (for the backup before commit). */
    public function existingToLink(): array
    {
        return array_values(array_filter(array_column($this->persons, 'existing')));
    }

    public function commit(string $ref, ?int $userId, ?callable $progress = null): array
    {
        $db = DB::connection(self::CONN);
        $now = now();
        $stamp = ['created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now, 'import_ref' => $ref];
        $counts = ['projects' => 0, 'lands' => 0, 'farmers' => 0, 'linked' => 0, 'farms' => 0, 'lines' => 0];

        // Farmers: one per person, numbered on from the last Farmer ID.
        $fmr = (int) substr((string) ($db->table('survey_farmers')->max('farmer_id_no') ?? 'FMR-0'), 4);
        $nos = [];
        foreach (array_chunk(array_keys($this->persons), self::CHUNK) as $keys) {
            $db->transaction(function () use ($db, $keys, $stamp, &$fmr, &$nos, &$counts) {
                $insert = [];
                foreach ($keys as $k) {
                    $nos[$k] = sprintf('FMR-%06d', ++$fmr);
                    $insert[] = ['farmer_id_no' => $nos[$k], 'full_name' => mb_substr($this->persons[$k]['name'], 0, 255)] + $stamp;
                }
                $db->table('survey_farmers')->insert($insert);
                $counts['farmers'] += count($insert);
            });
        }
        $farmerIds = [];
        foreach (array_chunk(array_values($nos), 1000) as $chunk) {
            $farmerIds += $db->table('survey_farmers')->whereIn('farmer_id_no', $chunk)->pluck('id', 'farmer_id_no')->all();
        }
        $farmerOf = array_map(fn ($no) => $farmerIds[$no], $nos);

        // The one farm already on record under each linked name joins that person.
        foreach (array_chunk(array_filter(array_map(fn ($p) => $p['existing'], $this->persons)), 200, true) as $chunk) {
            $db->transaction(function () use ($db, $chunk, $farmerOf, $userId, $now, &$counts) {
                foreach ($chunk as $k => $benId) {
                    $counts['linked'] += $db->table('survey_beneficiaries')->where('id', $benId)->whereNull('survey_farmer_id')
                        ->update(['survey_farmer_id' => $farmerOf[$k], 'updated_by' => $userId, 'updated_at' => $now]);
                }
            });
        }

        // The project.
        $area = $this->stats['area_ha'] ?? 0;
        $projectId = $db->table('survey_projects')->insertGetId($stamp + [
            'project_code'        => SurveyProject::nextRef('project_code', 'PRJ'),
            'name'                => self::PROJECT_NAME,
            'scheme_type'         => SurveyProject::SCHEME_LAND,
            'status'              => 'Active',
            'description'         => 'Moved from the Computation Sheet (' . $this->refs[0] . ') and the KMC Access farm records (' . $this->refs[1] . '). One land per sheet.',
            'prop_state'          => 'Kano',
            'acquisition_size_ha' => $area > 0 ? $area : null,
        ]);
        $counts['projects']++;

        // Lands, each with its farms and lines.
        $frm = (int) substr((string) ($db->table('survey_beneficiaries')->max('farm_id_no') ?? 'FRM-0'), 4);
        $farmIds = [];   // person|KMC Property ID => FRM-
        foreach ($this->lands as $sheet => $idx) {
            $db->transaction(function () use ($db, $sheet, $idx, $stamp, $projectId, $farmerOf, &$frm, &$farmIds, &$counts) {
                $lines = array_map(fn ($i) => $this->lines[$i], $idx);
                $area = array_sum(array_map(fn ($x) => (float) $x['r']->area_ha, $lines));
                $plots = array_sum(array_map(fn ($x) => CompensationListImport::countPlots($x['r']->plots), $lines));
                $districts = array_count_values(array_filter(array_map(fn ($x) => trim((string) $x['r']->district), $lines)));
                arsort($districts);
                $kmc = count(array_filter($lines, fn ($x) => $x['r']->import_ref === $this->refs[1]));

                $caseId = $db->table('survey_comp_cases')->insertGetId($stamp + [
                    'case_ref'        => SurveyCompCase::nextRef('case_ref', 'C'),
                    'survey_project_id' => $projectId,
                    'scheme_type'     => SurveyProject::SCHEME_LAND,
                    'area_ha'         => $area > 0 ? round($area, 4) : null,
                    'num_plots'       => $plots ?: null,
                    'status'          => 'Completed',
                    'description'     => "Sheet {$sheet}. " . (count($lines) - $kmc) . ' line(s) from the Computation Sheet'
                        . ($kmc ? ", {$kmc} from the KMC Access farm records" : '') . '.',
                    'prop_district'   => array_key_first($districts),
                    'prop_state'      => 'Kano',
                    'portion_size_ha' => $area > 0 ? round($area, 4) : null,
                ]);
                $counts['lands']++;

                $farms = [];
                $allocs = [];
                $farmNo = 0;
                foreach ($lines as $x) {
                    $r = $x['r'];
                    $person = $x['key'] !== '' ? $this->persons[$x['key']] : null;
                    $note = array_filter([
                        $r->remarks,
                        $r->tag_no ? 'Tag ' . $r->tag_no : null,
                        $person && $person['existing'] ? 'Linked to the farmer already on record by name' : null,
                        $person && $person['same_name'] > 1 ? "Same name as {$person['same_name']} farmers on record: check" : null,
                        ($r->import_ref === $this->refs[0] ? 'Computation Sheet' : 'KMC Access') . " {$r->sheet_name} row {$r->source_row}",
                    ]);
                    $allocs[] = $stamp + [
                        'survey_comp_case_id' => $caseId,
                        'sr'          => is_numeric($r->sn_written) ? (int) $r->sn_written : null,
                        'f_no'        => $r->farm_id ? mb_substr($r->farm_id, 0, 50) : null,
                        'farmer_name' => $x['name'] !== '' ? mb_substr($x['name'], 0, 255) : null,
                        'acreage'     => $r->area_ha !== null ? round((float) $r->area_ha, 4) : null,
                        'creetable'   => $r->creatable !== null ? (string) $r->creatable : null,
                        'govt_50'     => $r->govt_50 !== null ? round((float) $r->govt_50, 4) : null,
                        'farmer_50'   => $r->farm_50 !== null ? round((float) $r->farm_50, 4) : null,
                        'plot_no'     => $r->plots !== null ? mb_substr($r->plots, 0, 400) : null,
                        'remarks'     => mb_substr(implode(' · ', $note), 0, 500),
                    ];
                    if (! $person) continue;   // no name on the line: a line, not a farmer

                    $farmerId = $farmerOf[$x['key']];
                    $fk = $farmerId . '|' . ($r->farm_id ?: 'row' . $r->id);
                    $farmIds[$fk] ??= sprintf('FRM-%06d', ++$frm);
                    $farms[] = $stamp + [
                        'survey_comp_case_id' => $caseId,
                        'full_name'        => mb_substr($x['name'], 0, 255),
                        'status'           => 'Pending',
                        'photo_path'       => $r->photo_path,
                        'addr_district'    => $r->district ? mb_substr($r->district, 0, 100) : null,
                        'addr_state'       => 'Kano',
                        'survey_farmer_id' => $farmerId,
                        'farm_id_no'       => $farmIds[$fk],
                        'farm_no'          => ++$farmNo,
                    ];
                }
                foreach (array_chunk($allocs, self::CHUNK) as $c) $db->table('survey_plot_allocations')->insert($c);
                foreach (array_chunk($farms, self::CHUNK) as $c) $db->table('survey_beneficiaries')->insert($c);
                $counts['lines'] += count($allocs);
                $counts['farms'] += count($farms);
            });
            if ($progress) $progress($sheet, $counts);
        }

        return $counts;
    }

    /** Remove one batch: its rows everywhere, and the Farmer IDs it gave to farms already on record. */
    public static function undo(string $ref): array
    {
        $db = DB::connection(self::CONN);
        $out = [];
        $farmers = $db->table('survey_farmers')->where('import_ref', $ref)->pluck('id')->all();
        $out['unlinked'] = 0;
        foreach (array_chunk($farmers, 1000) as $ids) {
            $out['unlinked'] += $db->table('survey_beneficiaries')->whereIn('survey_farmer_id', $ids)
                ->where(fn ($q) => $q->whereNull('import_ref')->orWhere('import_ref', '!=', $ref))->update(['survey_farmer_id' => null]);
        }
        foreach (['survey_plot_allocations', 'survey_beneficiaries', 'survey_comp_cases', 'survey_projects', 'survey_farmers'] as $t) {
            $n = 0;
            do {
                $ids = $db->table($t)->where('import_ref', $ref)->limit(1000)->pluck('id')->all();
                if ($ids) $n += $db->table($t)->whereIn('id', $ids)->delete();
            } while ($ids);
            $out[$t] = $n;
        }

        return $out;
    }
}
