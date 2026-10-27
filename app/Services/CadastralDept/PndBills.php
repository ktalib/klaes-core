<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralBill;
use App\Models\Cadastral\CadastralPillar;
use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralCharting;
use App\Services\AuditService;
use App\Services\Cadastral\AreaCalculator;
use App\Services\Cadastral\CadastralBillCalculator;
use App\Services\Cadastral\CadastralSettings;
use App\Services\Cadastral\LandDescriptionGenerator;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The Fees P&D bill at AD P&D (client brief, 2026-10-10): the old module's
 * three screens (Area & Pillars, Descriptions, Fee Calculator) on one page.
 *
 * The writes are the old module's own records, unchanged: one
 * cadastral_plan_descriptions row per file (matched by file number, so a file
 * the old screens already worked on keeps its record), its cadastral_pillars,
 * and cadastral_bills issued by CadastralBillCalculator::issue() from the
 * official fee sheet. No payment is taken here; Deeds Bill Balance picks the
 * issued total up as the survey fee.
 *
 * A surveyor mobile app is to backfill the field data later; until then the
 * beacons and area are prefilled from the file's charting card.
 *
 * Plan Description (change request 26-10-2026): each pillar row is a boundary line in the
 * CAD 20 form - its beacon (from the Survey beacon register, or typed when not in it), the
 * beacon type, and the distance and bearing to the next beacon. The area is typed, or worked
 * out from coordinates, or from the lines (BoundaryTraverse; the plot need not close). The
 * description prints on CAD 20, or CAD 13 for SLTR files.
 */
class PndBills
{
    private const CONN = 'sqlsrv';

    public const TASK = 'bill_generated';

    public const FORM_CAD20 = 'cad20';
    public const FORM_CAD13 = 'cad13';
    public const FORMS = [self::FORM_CAD20 => 'CAD 20 (bearings and distances)', self::FORM_CAD13 => 'CAD 13 (SLTR)'];

    private static ?bool $linesInstalled = null;

    /** Charting stages, best source first, for prefilling the beacons. */
    private const CHART_PREFERENCE = ['second', 'single', 'first', 'check'];

    public function __construct(
        private CadastralBillCalculator $calculator,
        private LandDescriptionGenerator $descriptions,
        private AreaCalculator $area,
        private CadastralSettings $settings,
        private WorkflowDefinition $def,
        private AuditService $audit
    ) {
    }

    /** The 2026_10_27_100000 migration has run (boundary-line and header columns exist). */
    public static function linesInstalled(): bool
    {
        return self::$linesInstalled ??= Schema::connection(self::CONN)->hasColumn('cadastral_pillars', 'distance_m')
            && Schema::connection(self::CONN)->hasColumn('cadastral_plan_descriptions', 'situated_at');
    }

    /** Beacon ownership a new line starts with: Conversion files are private (change request 26-10-2026). */
    public static function defaultOwnership(CadastralCase $case): string
    {
        return $case->workflow === 'conversion' ? CadastralPillar::OWNERSHIP_PRIVATE : CadastralPillar::OWNERSHIP_GOVERNMENT;
    }

    /** CAD 13 for SLTR files, CAD 20 for the rest. */
    public static function defaultForm(CadastralCase $case): string
    {
        return str_starts_with(strtoupper((string) $case->file_number), 'SLTR') || $case->file_source === 'sltr'
            ? self::FORM_CAD13 : self::FORM_CAD20;
    }

    /** @return array<int, string> */
    public static function beaconTypes(): array
    {
        return array_values((array) config('cadastral_dept.beacon_types', []));
    }

    /** Beacon numbers in the Survey beacon register, for the line picker. */
    public static function registerBeacons(): array
    {
        if (!Schema::connection(self::CONN)->hasTable('survey_beacon_numbers')) {
            return [];
        }

        return DB::connection(self::CONN)->table('survey_beacon_numbers')->whereNull('deleted_at')
            ->distinct()->orderBy('beacon_no')->pluck('beacon_no')->map(fn ($b) => (string) $b)->all();
    }

    /** Header fields, prefilled from the file while the record has none (development standard 5). */
    public function headerDefaults(CadastralCase $case, ?CadastralPlanDescription $pd): array
    {
        $col = fn (string $c) => $pd && self::linesInstalled() ? $pd->{$c} : null;

        return [
            'situated_at' => $col('situated_at') ?: ($case->location ?: null),
            'district' => $pd->prop_district ?? $case->district,
            'lga' => $pd->prop_lga ?? $case->lga,
            'plan_drawing_no' => $col('plan_drawing_no'),
            'copied_from_plan_no' => $col('copied_from_plan_no'),
            'dimensions' => $col('dimensions'),
            'description_form' => $col('description_form') ?: self::defaultForm($case),
        ];
    }

    /**
     * The Plan Description and bill can be changed: at the billing step, and at AD Cad Info's signing
     * step until it is signed (change request 26-10-2026: "edit Fees, P&D bill"). A corrected bill
     * replaces the unsigned one.
     */
    public function dueNow(CadastralCase $case): bool
    {
        if (!$case->isOpen()) {
            return false;
        }
        $tasks = $this->def->step($case->workflow, $case->current_step)['tasks'] ?? [];

        return isset($tasks[self::TASK])
            || (isset($tasks[StepDocuments::BILL_SIGNED]) && !in_array(StepDocuments::BILL_SIGNED, $case->doneTaskKeys($case->current_step), true));
    }

    /** At the signing step rather than the billing step: edits there correct an issued bill. */
    public function isCorrection(CadastralCase $case): bool
    {
        return $this->dueNow($case) && !isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK]);
    }

    /** The file's working record (newest), shared with the old Plan & Description screens. */
    public function record(CadastralCase $case): ?CadastralPlanDescription
    {
        return CadastralPlanDescription::query()->where('file_number', $case->file_number)->orderByDesc('id')->first();
    }

    /** The newest bill for the file that still counts (not cancelled). */
    public static function liveBill(string $fileNumber): ?CadastralBill
    {
        return CadastralBill::query()->where('file_number', $fileNumber)
            ->where('status', 'Issued')->orderByDesc('id')->first();
    }

    /**
     * Beacons and area from the file's charting card, for a record that has
     * none yet. Returns the card used, or null when no card has beacons.
     *
     * @return array{source: string, pillars: array<int, array>, area_sqm: ?float}|null
     */
    public function backfill(CadastralCase $case): ?array
    {
        if (!ChartingCards::installed()) {
            return null;
        }
        $cards = CadastralCharting::query()->with('beacons')->where('cadastral_case_id', $case->id)
            ->where('is_current', true)->get()
            ->sortBy(fn ($c) => array_search($c->stage, self::CHART_PREFERENCE, true));

        foreach ($cards as $card) {
            if ($card->beacons->isNotEmpty()) {
                return [
                    'source' => $card->charting_no . ' (' . $card->stageLabel() . ')',
                    'pillars' => $card->beacons->map(fn ($b) => [
                        'pillar_number' => $b->beacon, 'ownership' => self::defaultOwnership($case),
                        'easting' => (string) $b->easting, 'northing' => (string) $b->northing,
                    ])->values()->all(),
                    'area_sqm' => $card->area_sqm,
                ];
            }
        }

        return null;
    }

    /**
     * Save area and beacons: starts the record when the file has none.
     * A typed area wins; otherwise it is worked out from 3+ beacons.
     */
    public function saveArea(CadastralCase $case, array $input, ?int $userId): CadastralPlanDescription
    {
        $this->assertDue($case);
        $lines = self::linesInstalled();
        $pillars = $this->cleanPillars((array) ($input['pillars'] ?? []), $lines);
        $header = $lines ? $this->cleanHeader($input) : [];
        $traverse = $lines ? BoundaryTraverse::measure($pillars) : ['area_sqm' => null, 'misclosure_m' => null];

        $typed = trim((string) ($input['area_value'] ?? ''));
        $unit = array_key_exists($input['area_unit'] ?? 'sqm', AreaCalculator::UNITS) ? ($input['area_unit'] ?? 'sqm') : 'sqm';
        if ($typed !== '') {
            if (!is_numeric($typed) || (float) $typed <= 0) {
                throw WorkflowGuardException::because('The area must be a number above 0.');
            }
            $sqm = $this->area->toSqm((float) $typed, $unit);
            $source = 'manual';
        } else {
            $sqm = $this->area->areaSqm(array_filter($pillars, fn ($p) => $p['easting'] !== null && $p['northing'] !== null));
            $source = 'coordinates';
            if ($sqm === null && $traverse['area_sqm']) {
                // From the bearings and distances; the plot need not close (change request 26-10-2026).
                $sqm = $traverse['area_sqm'];
                $source = 'bearings';
            }
            if ($sqm === null) {
                throw WorkflowGuardException::because('Type the area, or enter at least 3 lines with distances and bearings (or 3 beacons with coordinates) so it can be worked out.');
            }
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $pillars, $sqm, $source, $userId, $header) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertDue($case);

            $pd = $this->record($case);
            $before = $pd ? ['area_sqm' => $pd->area_sqm, 'pillars' => $pd->pillars()->count()] : null;
            if (!$pd) {
                $pd = CadastralPlanDescription::create([
                    'pd_ref' => CadastralPlanDescription::nextRef('pd_ref', 'PND', 4),
                    'file_number' => $case->file_number,
                    'file_title' => $case->file_title,
                    'prop_plot' => $case->plot_no,
                    'prop_district' => $case->district,
                    'prop_lga' => $case->lga,
                    'prop_state' => 'Kano',
                    'plot_size_sqm' => $this->settings->plotSizeSqm(),
                    'description_complexity' => 'standard',
                    'area_sqm' => $sqm,
                    'area_source' => $source,
                ] + $header);
            } else {
                $pd->update(['area_sqm' => $sqm, 'area_source' => $source] + $header);
            }

            // Replaced pillar rows are soft-deleted, as on the old screen.
            $pd->pillars()->get()->each->delete();
            foreach ($pillars as $i => $p) {
                CadastralPillar::create(['cadastral_plan_description_id' => $pd->id, 'sort_order' => $i] + $p);
            }

            $this->audit->logAction('Cadastral P&D Area Saved', 'CadastralPlanDescription', $pd->id, $before,
                ['area_sqm' => $sqm, 'area_source' => $source, 'pillars' => count($pillars)] + $header,
                "{$pd->pd_ref} area and beacons for {$case->case_ref}");

            return $pd;
        });
    }

    /** Write the description from the record's template (or the default one). */
    public function generateDescription(CadastralCase $case): CadastralPlanDescription
    {
        $this->assertDue($case);
        $pd = $this->requireRecord($case);
        $key = $pd->template_key ?: $this->descriptions->defaultKeyFor($pd);
        $pd->update([
            'template_key' => $key,
            'description_body' => $this->descriptions->generate($pd, $key),
            'description_generated_at' => now(),
        ]);

        return $pd;
    }

    public function saveDescription(CadastralCase $case, ?string $body, ?int $userId): CadastralPlanDescription
    {
        $this->assertDue($case);
        $pd = $this->requireRecord($case);
        $body = trim((string) $body);
        if ($body === '') {
            throw WorkflowGuardException::because('The description is empty. Generate it, then edit it.');
        }
        $pd->update(['description_body' => $body, 'description_edited_by' => $userId]);

        return $pd;
    }

    /** The bill's lines without saving (the page's live preview). */
    public function preview(CadastralCase $case, array $input): array
    {
        $pd = $this->record($case);
        if (!$pd) {
            return ['ok' => false, 'refusal' => 'Save the area and beacons first.', 'refusals' => ['Save the area and beacons first.'], 'flags' => [], 'lines' => [], 'total' => null];
        }

        return $this->calculator->compute($pd, $input);
    }

    /** Issue the bill (no payment) and tick the task. Re-issuing supersedes the unpaid one. */
    public function issue(CadastralCase $case, array $input, ?int $userId): CadastralBill
    {
        return DB::connection(self::CONN)->transaction(function () use ($case, $input, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertDue($case);
            $pd = $this->requireRecord($case);

            try {
                $bill = $this->calculator->issue($pd, $input);
            } catch (ValidationException $e) {
                throw WorkflowGuardException::because((string) collect($e->errors())->flatten()->first());
            }

            // At the signing step (a correction) the billing step's task row takes the new bill's reference.
            $task = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->when(!$this->isCorrection($case), fn ($q) => $q->where('step', $case->current_step))
                ->where('task_key', self::TASK)->orderByDesc('id')->first();
            if ($task) {
                $task->update(['note' => $bill->bill_ref]);
            } elseif (!$this->isCorrection($case)) {
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => self::TASK,
                    'note' => $bill->bill_ref,
                    'done_by' => $userId,
                    'done_at' => now(),
                ]);
            }

            $this->audit->logAction($this->isCorrection($case) ? 'Cadastral P&D Bill Corrected' : 'Cadastral P&D Bill Issued', 'CadastralBill', $bill->id, null,
                ['bill_ref' => $bill->bill_ref, 'grand_total' => $bill->grand_total, 'file_number' => $bill->file_number],
                "{$bill->bill_ref} issued for {$case->case_ref}: ₦" . number_format((float) $bill->grand_total, 2));

            return $bill;
        });
    }

    /**
     * Posted boundary lines, cleaned: blank rows dropped, codes upper-cased, numbers checked.
     * With the line columns installed, each line also needs its beacon number and type, and its
     * distance and bearing come as a set (all or none), so a line may carry coordinates instead.
     */
    private function cleanPillars(array $rows, bool $lines = false): array
    {
        $types = self::beaconTypes();
        $register = $lines ? array_flip(array_map(fn ($b) => self::beaconKey($b), self::registerBeacons())) : [];
        $out = [];
        foreach (array_slice($rows, 0, 500) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $num = fn ($v) => ($v = str_replace([' ', ','], '', trim((string) $v))) === '' ? null : $v;
            $e = $num($row['easting'] ?? '');
            $n = $num($row['northing'] ?? '');
            $no = Normalise::code($row['pillar_number'] ?? null);
            $dist = $num($row['distance_m'] ?? '');
            $deg = $num($row['bearing_deg'] ?? '');
            $min = $num($row['bearing_min'] ?? '');
            $sec = $num($row['bearing_sec'] ?? '');
            if ($no === null && $e === null && $n === null && $dist === null && $deg === null && $min === null && $sec === null) {
                continue;
            }
            $where = 'Line ' . (count($out) + 1) . ($no ? " ({$no})" : '');
            if (($e !== null && !is_numeric($e)) || ($n !== null && !is_numeric($n))) {
                throw WorkflowGuardException::because('Each easting and northing must be a number' . ($no ? " (beacon {$no})" : '') . '.');
            }
            $clean = [
                'pillar_number' => $no,
                'ownership' => ($row['ownership'] ?? '') === CadastralPillar::OWNERSHIP_PRIVATE ? CadastralPillar::OWNERSHIP_PRIVATE : CadastralPillar::OWNERSHIP_GOVERNMENT,
                'easting' => $e === null ? null : (float) $e,
                'northing' => $n === null ? null : (float) $n,
            ];

            if ($lines) {
                if ($no === null) {
                    throw WorkflowGuardException::because("{$where}: enter the beacon number.");
                }
                $type = $this->beaconType($row, $types, $where);

                if ($dist !== null || $deg !== null || $min !== null || $sec !== null) {
                    if ($dist === null || $deg === null || $min === null) {
                        throw WorkflowGuardException::because("{$where}: enter the distance and the bearing (degrees and minutes), or leave all of them blank.");
                    }
                    if (!is_numeric($dist) || (float) $dist <= 0 || (float) $dist > 100000) {
                        throw WorkflowGuardException::because("{$where}: the distance must be a number of metres above 0.");
                    }
                    if (!ctype_digit((string) $deg) || (int) $deg > 359) {
                        throw WorkflowGuardException::because("{$where}: bearing degrees must be a whole number from 0 to 359.");
                    }
                    if (!ctype_digit((string) $min) || (int) $min > 59) {
                        throw WorkflowGuardException::because("{$where}: bearing minutes must be a whole number from 0 to 59.");
                    }
                    if ($sec !== null && (!is_numeric($sec) || (float) $sec < 0 || (float) $sec >= 60)) {
                        throw WorkflowGuardException::because("{$where}: bearing seconds must be from 0 to 59.");
                    }
                } elseif ($e === null || $n === null) {
                    throw WorkflowGuardException::because("{$where}: enter the distance and bearing to the next beacon, or its easting and northing.");
                }

                $clean += [
                    'pillar_type' => $type,
                    'distance_m' => $dist === null ? null : round((float) $dist, 3),
                    'bearing_deg' => $deg === null ? null : (int) $deg,
                    'bearing_min' => $min === null ? null : (int) $min,
                    'bearing_sec' => $deg === null ? null : ($sec === null ? 0 : round((float) $sec, 2)),
                    'in_beacon_register' => isset($register[self::beaconKey($no)]),
                ];
            }
            $out[] = $clean;
        }

        return $out;
    }

    /** The line's beacon type: one of the list, or the typed "Other" (matched to the list ignoring case). */
    private function beaconType(array $row, array $types, string $where): string
    {
        $picked = trim((string) ($row['pillar_type'] ?? ''));
        if ($picked === '') {
            throw WorkflowGuardException::because("{$where}: choose the beacon type.");
        }
        if ($picked === 'other') {
            $picked = mb_substr(Normalise::text($row['pillar_type_other'] ?? ''), 0, 50);
            if ($picked === '') {
                throw WorkflowGuardException::because("{$where}: type the beacon type (Other).");
            }
        }
        foreach ($types as $t) {
            if (mb_strtolower($t) === mb_strtolower($picked)) {
                return $t;
            }
        }

        return $picked;
    }

    /** Beacon numbers compared without case or spaces ("LAD 2902" = "lad2902"). */
    public static function beaconKey(?string $beacon): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $beacon));
    }

    /** The Plan Description header (the CAD 20 / CAD 13 blanks). */
    private function cleanHeader(array $input): array
    {
        $text = fn (string $k, int $max) => ($v = Normalise::text($input[$k] ?? '')) === '' ? null : mb_substr($v, 0, $max);
        $form = (string) ($input['description_form'] ?? '');
        $place = $text('situated_at', 255);

        return [
            'situated_at' => $place === null ? null : Normalise::name($place),
            'plan_drawing_no' => $text('plan_drawing_no', 100),
            'copied_from_plan_no' => $text('copied_from_plan_no', 100),
            'dimensions' => ($d = trim((string) ($input['dimensions'] ?? ''))) === '' ? null : mb_substr($d, 0, 4000),
            'description_form' => array_key_exists($form, self::FORMS) ? $form : self::FORM_CAD20,
        ];
    }

    private function assertDue(CadastralCase $case): void
    {
        if (!$this->dueNow($case)) {
            throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the Fees P&D bill is made or corrected (it can be corrected until AD Cad Info signs it).");
        }
    }

    private function requireRecord(CadastralCase $case): CadastralPlanDescription
    {
        $pd = $this->record($case);
        if (!$pd) {
            throw WorkflowGuardException::because('Save the area and beacons first.');
        }

        return $pd;
    }
}
