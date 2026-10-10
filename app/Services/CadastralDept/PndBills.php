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
 */
class PndBills
{
    private const CONN = 'sqlsrv';

    public const TASK = 'bill_generated';

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

    public function dueNow(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK]);
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
                        'pillar_number' => $b->beacon, 'ownership' => 'government',
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
        $pillars = $this->cleanPillars((array) ($input['pillars'] ?? []));

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
            if ($sqm === null) {
                throw WorkflowGuardException::because('Type the area, or enter at least 3 beacons with coordinates so it can be worked out.');
            }
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $pillars, $sqm, $source, $userId) {
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
                ]);
            } else {
                $pd->update(['area_sqm' => $sqm, 'area_source' => $source]);
            }

            // Replaced pillar rows are soft-deleted, as on the old screen.
            $pd->pillars()->get()->each->delete();
            foreach ($pillars as $i => $p) {
                CadastralPillar::create(['cadastral_plan_description_id' => $pd->id, 'sort_order' => $i] + $p);
            }

            $this->audit->logAction('Cadastral P&D Area Saved', 'CadastralPlanDescription', $pd->id, $before,
                ['area_sqm' => $sqm, 'area_source' => $source, 'pillars' => count($pillars)],
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

            $task = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', self::TASK)->first();
            if ($task) {
                $task->update(['note' => $bill->bill_ref]);
            } else {
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => self::TASK,
                    'note' => $bill->bill_ref,
                    'done_by' => $userId,
                    'done_at' => now(),
                ]);
            }

            $this->audit->logAction('Cadastral P&D Bill Issued', 'CadastralBill', $bill->id, null,
                ['bill_ref' => $bill->bill_ref, 'grand_total' => $bill->grand_total, 'file_number' => $bill->file_number],
                "{$bill->bill_ref} issued for {$case->case_ref}: ₦" . number_format((float) $bill->grand_total, 2));

            return $bill;
        });
    }

    /** Posted beacon rows, cleaned: blank rows dropped, codes upper-cased, coordinates numeric. */
    private function cleanPillars(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, 500) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $num = fn ($v) => ($v = str_replace([' ', ','], '', trim((string) $v))) === '' ? null : $v;
            $e = $num($row['easting'] ?? '');
            $n = $num($row['northing'] ?? '');
            $no = Normalise::code($row['pillar_number'] ?? null);
            if ($no === null && $e === null && $n === null) {
                continue;
            }
            if (($e !== null && !is_numeric($e)) || ($n !== null && !is_numeric($n))) {
                throw WorkflowGuardException::because('Each easting and northing must be a number' . ($no ? " (beacon {$no})" : '') . '.');
            }
            $out[] = [
                'pillar_number' => $no,
                'ownership' => ($row['ownership'] ?? '') === CadastralPillar::OWNERSHIP_PRIVATE ? CadastralPillar::OWNERSHIP_PRIVATE : CadastralPillar::OWNERSHIP_GOVERNMENT,
                'easting' => $e === null ? null : (float) $e,
                'northing' => $n === null ? null : (float) $n,
            ];
        }

        return $out;
    }

    private function assertDue(CadastralCase $case): void
    {
        if (!$this->dueNow($case)) {
            throw WorkflowGuardException::because("{$case->case_ref} is not at the step where the Fees P&D bill is made.");
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
