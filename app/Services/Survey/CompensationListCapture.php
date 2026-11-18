<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyFarmer;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual capture of a paper compensation / resettlement allocation list (user,
 * 2026-10-30): the lists that never came as a spreadsheet are keyed in on one form
 * and become the same records the ALL COMPENSATION import made, i.e.
 *
 *   project (existing, or new with its TP No.)
 *   surveyor on the project; this list is their next portion
 *   land (survey_comp_cases), status Completed
 *   each row               allocation line (survey_plot_allocations)
 *   each distinct name     farmer (survey_beneficiaries), Pending
 *
 * Rows are entered in KLAES, so import_ref stays null (they show as "Entered in KLAES").
 * One list is one short transaction.
 */
class CompensationListCapture
{
    public const LIST_TYPES = [
        'farmer' => 'Farmer compensation list',
        'plot'   => 'Resettlement plot allocation list',
    ];

    /** Fields of one list row, in grid order. */
    public const ROW_FIELDS = ['fn', 'name', 'area', 'creatable', 'govt50', 'farmer50', 'plot', 'op', 'sign', 'remarks', 'farmer_id', 'farm_id'];

    /**
     * $d is the validated form (see CompensationCaptureController::rules()); $rows
     * the non-empty rows. Returns the new land.
     */
    public function save(array $d, array $rows, ?int $userId): SurveyCompCase
    {
        $db = DB::connection('sqlsrv');

        return $db->transaction(function () use ($db, $d, $rows, $userId) {
            $now = now();
            $stamp = ['created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            $address = array_intersect_key($d, array_flip(['prop_district', 'prop_district_other', 'prop_lga', 'prop_state']));

            // A CO number picked in the selector but not yet in KLAES: captured now, as the
            // Capture CO File page would (status Captured, the project's name as its title).
            if ($d['project_mode'] === 'new' && empty($d['survey_co_file_id']) && ! empty($d['co_file_no'])) {
                $d['survey_co_file_id'] = SurveyCoFile::create([
                    'co_file_no' => $d['co_file_no'],
                    'title'      => self::tidy($d['project_name']),
                    'source'     => SurveyCoFile::SOURCE_CAPTURED,
                    'status'     => SurveyCoFile::CAPTURED_STATUS,
                    'remarks'    => 'Captured while keying in a paper compensation list.',
                ] + $address + ['prop_state' => 'Kano'])->id;
            }

            $project = $d['project_mode'] === 'new'
                ? SurveyProject::create([
                    'project_code'      => SurveyProject::nextRef('project_code', 'PRJ'),
                    'name'              => self::tidy($d['project_name']),
                    'plan_no'           => self::tidy($d['plan_no'] ?? null) ?: null,
                    'purpose'           => self::tidy($d['purpose'] ?? null) ?: null,
                    'scheme_type'       => $d['scheme_type'],
                    'status'            => 'Active',
                    'survey_co_file_id' => $d['survey_co_file_id'] ?? null,
                    'description'       => 'Opened while capturing a paper compensation list.',
                ] + $address)
                : SurveyProject::findOrFail($d['survey_project_id']);

            $area = round(array_sum(array_map(fn ($r) => (float) ($r['area'] ?? 0), $rows)), 4);
            $plots = array_sum(array_map(fn ($r) => CompensationListImport::countPlots($r['plot'] ?? null), $rows));

            // The surveyor on this project; this list is their next portion.
            $allocation = null;
            $portion = null;
            if ($name = self::tidy($d['surveyor_name'] ?? null)) {
                $allocation = SurveyProjectSurveyor::where('survey_project_id', $project->id)
                    ->whereRaw('LOWER(LTRIM(RTRIM(surveyor_name))) = ?', [mb_strtolower($name)])->first();
                if ($allocation) {
                    $portion = $allocation->cases()->max('portion_no') + 1;
                    if ($portion > (int) $allocation->portions) $allocation->update(['portions' => $portion]);
                } else {
                    $allocation = SurveyProjectSurveyor::create([
                        'survey_project_id' => $project->id,
                        'surveyor_id'       => CompensationListImport::matchStaff($name, CompensationListImport::staffIndex()),
                        'surveyor_name'     => $name,
                        'portions'          => 1,
                        'portion_size_ha'   => $area > 0 ? $area : null,
                    ]);
                    $portion = 1;
                }
            }

            $type = self::LIST_TYPES[$d['list_type']];
            $case = SurveyCompCase::create([
                'case_ref'                   => SurveyCompCase::nextRef('case_ref', 'C'),
                'survey_project_id'          => $project->id,
                'scheme_type'                => $project->scheme_type,
                'survey_officer'             => $name ?: null,
                'area_ha'                    => $area > 0 ? $area : null,
                'num_plots'                  => $plots ?: null,
                'status'                     => 'Completed',
                // An existing record: no Generate OP on it (CR 30102026 item 32).
                'captured_from_paper'        => true,
                'case_date'                  => $d['list_date'] ?? null,
                'description'                => trim($type
                    . (($t = self::tidy($d['list_title'] ?? null)) ? ': ' . $t : '')
                    . ($name ? '. Surveyed by ' . $name : '')
                    . (($s = self::tidy($d['section'] ?? null)) ? ', Section ' . $s : '')
                    . '. Captured from the paper list'
                    . (($ref = self::tidy($d['source_ref'] ?? null)) ? ' (' . $ref . ')' : '') . '.'),
                'survey_project_surveyor_id' => $allocation?->id,
                'portion_no'                 => $portion,
                'portion_size_ha'            => $area > 0 ? $area : null,
                // The paper's Section No., when it is a number (user, 2026-11-18).
                'section_no'                 => ctype_digit((string) self::tidy($d['section'] ?? null)) ? (int) $d['section'] : null,
            ] + $address);

            // Farmers: each distinct real name once, or once per Farm ID when the paper gives one
            // (ABSENT and the like stay lines only).
            $farmers = [];
            foreach ($rows as $i => $r) {
                $n = $r['name'] ?? null;
                if (CompensationListReader::isPlaceholderName($n)) continue;
                $k = preg_replace('/[^A-Z]/', '', strtoupper((string) $n));
                if ($k === '') continue;
                $k .= '|' . mb_strtoupper($r['farm_id'] ?? '');
                $farmers[$k] ??= [
                    'name'      => $n,
                    'farmer_id' => $this->farmerIdFor($r['farmer_id'] ?? null, (string) $n, $i + 1),
                    'farm_id'   => $this->farmIdFor($r['farm_id'] ?? null, $i + 1),
                    'farm_no'   => ctype_digit((string) ($r['fn'] ?? '')) ? (int) $r['fn'] : null,
                ];
            }
            foreach (array_chunk(array_values($farmers), 200) as $chunk) {
                $db->table('survey_beneficiaries')->insert(array_map(fn ($f) => $stamp + [
                    'survey_comp_case_id' => $case->id, 'full_name' => $f['name'], 'status' => 'Pending', 'addr_state' => 'Kano',
                    'survey_farmer_id' => $f['farmer_id'], 'farm_id_no' => $f['farm_id'], 'farm_no' => $f['farm_no'],
                ], $chunk));
            }

            // Allocation lines, S/N in the order keyed.
            $lines = [];
            foreach (array_values($rows) as $i => $r) {
                $lines[] = $stamp + [
                    'survey_comp_case_id' => $case->id,
                    'sr'                  => $i + 1,
                    'f_no'                => $r['fn'] ?? null,
                    'farmer_name'         => $r['name'] ?? null,
                    'acreage'             => isset($r['area']) ? round((float) $r['area'], 4) : null,
                    'creetable'           => $r['creatable'] ?? null,
                    'govt_50'             => isset($r['govt50']) ? round((float) $r['govt50'], 4) : null,
                    'farmer_50'           => isset($r['farmer50']) ? round((float) $r['farmer50'], 4) : null,
                    'plot_no'             => $r['plot'] ?? null,
                    'op_no'               => $r['op'] ?? null,
                    'sign'                => $r['sign'] ?? null,
                    'remarks'             => $r['remarks'] ?? null,
                ];
            }
            foreach (array_chunk($lines, 120) as $chunk) {
                $db->table('survey_plot_allocations')->insert($chunk);
            }

            return $case;
        });
    }

    /**
     * The paper's Farmer ID (user, 2026-11-18): one already on record is that farmer, and must carry the same name;
     * a KLAES number (FMR-...) that is not on record is refused; any other number is a new farmer under it.
     */
    private function farmerIdFor(?string $id, string $name, int $row): ?int
    {
        if (($id = self::tidy($id)) === null) return null;
        $letters = fn ($v) => preg_replace('/[^A-Z]/', '', strtoupper((string) $v));

        if ($farmer = SurveyFarmer::where('farmer_id_no', $id)->first()) {
            if ($letters($farmer->full_name) !== $letters($name)) {
                throw ValidationException::withMessages(['rows' => "Row {$row}: Farmer ID {$id} is {$farmer->full_name}, not {$name}."]);
            }
            return $farmer->id;
        }
        if (preg_match('/^FMR-\d/i', $id)) {
            throw ValidationException::withMessages(['rows' => "Row {$row}: Farmer ID {$id} is not on record."]);
        }

        return SurveyFarmer::create(['farmer_id_no' => $id, 'full_name' => $name])->id;
    }

    /** The paper's Farm ID, kept as written; a KLAES number (FRM-...) must already be on record. */
    private function farmIdFor(?string $id, int $row): ?string
    {
        if (($id = self::tidy($id)) === null) return null;
        if (preg_match('/^FRM-\d/i', $id) && ! SurveyBeneficiary::where('farm_id_no', $id)->exists()) {
            throw ValidationException::withMessages(['rows' => "Row {$row}: Farm ID {$id} is not on record."]);
        }

        return $id;
    }

    /** Collapse whitespace; values keep the case they were typed in (names, plot and file numbers alike). */
    public static function tidy(?string $v): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $v === '' ? null : $v;
    }
}
