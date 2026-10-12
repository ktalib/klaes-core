<?php

namespace App\Services\CadastralDept;

use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCharting;
use App\Models\CadastralDept\CadastralYesNoForm as Form;
use App\Services\AuditService;
use App\Services\Cadastral\AreaCalculator;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Yes/No form at AD Cad Report (client PDF "This is Yes or No form",
 * 2026-10-11): the official "Report on Application – Cadastral Department",
 * done with Land 12 under the step's `land12` task.
 *
 * Copied from the old module's questionnaire (ReportController::saveApplication,
 * CadastralReport refs and area), so the two forms read and print alike. The
 * `land12` task is still ticked by hand, because Land 12 has no screen here,
 * but not before this form's seven questions are answered.
 */
class YesNoForms
{
    private const CONN = 'sqlsrv';

    public const TASK = 'land12';

    /** Charting stages, best source first, for prefilling the area. */
    private const CHART_PREFERENCE = ['second', 'single', 'first', 'check'];

    public function __construct(private WorkflowDefinition $def, private AreaCalculator $area, private AuditService $audit)
    {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_yes_no_forms');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** True when the current step asks for Land 12 and the Yes/No form. */
    public function dueNow(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(($this->def->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK]);
    }

    public function forCase(CadastralCase $case): ?Form
    {
        return self::installed() ? Form::query()->where('cadastral_case_id', $case->id)->first() : null;
    }

    /** True when the form exists and its seven questions are answered. */
    public function isComplete(CadastralCase $case): bool
    {
        return (bool) $this->forCase($case)?->isComplete();
    }

    /**
     * The four reference blanks at the head of the form, without the prefix
     * the form already prints. The file number goes on the line its type or
     * source says; a value the officer saved wins.
     *
     * @return array{lkn: ?string, sltr: ?string, gkn: ?string, sit: ?string}
     */
    public function refs(CadastralCase $case, ?Form $form = null): array
    {
        $n = strtoupper(ltrim((string) $case->file_number));
        $line = match (true) {
            $case->file_source === 'sltr' || str_starts_with($n, 'SLTR') => 'sltr',
            $case->file_type === 'sit' || str_starts_with($n, 'SIT') => 'sit',
            $case->file_type === 'gkn' || str_starts_with($n, 'GKN') => 'gkn',
            default => 'lkn',
        };
        $mine = fn (string $l) => $line === $l ? $case->file_number : null;

        return [
            'lkn' => self::withoutPrefix($form?->lkn_no ?? $mine('lkn'), 'LKN'),
            'sltr' => self::withoutPrefix($form?->sltr_no ?? $mine('sltr'), 'SLTR'),
            'gkn' => self::withoutPrefix($form?->govt_item_no ?? $mine('gkn'), 'GKN'),
            'sit' => self::withoutPrefix($form?->sit_no ?? $mine('sit'), 'SIT'),
        ];
    }

    /**
     * Area applied for, in hectares, and where it came from: the officer's
     * figure, else the file's plan description (shared with the old screens),
     * else the current charting card.
     *
     * @return array{ha: ?float, source: ?string}
     */
    public function appliedArea(CadastralCase $case, ?Form $form = null): array
    {
        if ($form && $form->area_applied_ha !== null) {
            return ['ha' => round((float) $form->area_applied_ha, 4), 'source' => 'entered'];
        }

        $pd = CadastralPlanDescription::query()->where('file_number', $case->file_number)
            ->whereNotNull('area_sqm')->orderByDesc('id')->first();
        if ($pd) {
            return ['ha' => $this->area->convert((float) $pd->area_sqm)['hectares'], 'source' => 'plan description ' . $pd->pd_ref];
        }

        if (ChartingCards::installed()) {
            $card = CadastralCharting::query()->where('cadastral_case_id', $case->id)->where('is_current', true)
                ->whereNotNull('area_sqm')->get()
                ->sortBy(fn ($c) => array_search($c->stage, self::CHART_PREFERENCE, true))->first();
            if ($card) {
                return ['ha' => $this->area->convert((float) $card->area_sqm)['hectares'], 'source' => 'charting card ' . $card->charting_no];
            }
        }

        return ['ha' => null, 'source' => null];
    }

    /**
     * Save the form. $data is validated by the controller; the branch not
     * taken is cleared here, so a stale answer never prints as if it applied.
     * Saving again edits it; un-answering a question makes it incomplete.
     */
    public function save(CadastralCase $case, array $data, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The Yes/No form table has not been created yet.');
        }
        if (!$this->dueNow($case)) {
            throw WorkflowGuardException::because('The Yes/No form is filled at AD Cad Report, with Land 12.');
        }
        if (in_array(self::TASK, $case->doneTaskKeys($case->current_step), true)) {
            throw WorkflowGuardException::because('Land 12 and the Yes/No form are already marked done for this step.');
        }

        if (($data['q2_ground_open'] ?? null) !== 'No') {
            $data['q2_overlapping_title'] = null;
        }
        if (($data['q3_beaconed'] ?? null) !== 'Yes') {
            $data['q3_tracing_no'] = $data['q3_deposition_plan_no'] = null;
        }
        if (($data['q3_beaconed'] ?? null) !== 'No') {
            $data['q3_unapproved_town_plan_no'] = $data['q3_layout_no'] = $data['q3_separate_survey'] = null;
        }
        if (($data['q4_town_plan'] ?? null) !== 'Yes') {
            $data['q4_town_plan_no'] = $data['q4_shape_agrees'] = $data['q4_purpose'] = $data['q4_area_for_purpose'] = null;
        }
        if (($data['q5_previous_title'] ?? null) !== 'Yes') {
            $data['q5_details'] = null;
        }
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = trim($v) === '' ? null : preg_replace('/\s+/', ' ', trim($v));
            }
        }

        // Every column is written, so a cleared field really clears.
        $values = array_intersect_key(array_merge(array_fill_keys(Form::COLUMNS, null), $data), array_flip(Form::COLUMNS));

        return DB::connection(self::CONN)->transaction(function () use ($case, $values, $userId) {
            $form = Form::query()->firstOrNew(['cadastral_case_id' => $case->id]);
            $form->fill($values + ['file_number' => $case->file_number, 'saved_by' => $userId]);
            $complete = $form->unanswered() === [];
            $form->completed_at = $complete ? ($form->completed_at ?? now()) : null;
            $form->save();

            $this->audit->logAction('Cadastral Yes/No Form Saved', 'CadastralYesNoForm', $form->id, null,
                ['complete' => $complete], "Yes/No form for {$case->case_ref}" . ($complete ? ' (complete)' : ' (incomplete)'));

            return $complete
                ? 'Yes/No form saved. All seven questions are answered; tick "Land 12 and Yes/No form" once Land 12 is done.'
                : 'Yes/No form saved. Still to answer: ' . implode(', ', array_map(fn ($c) => 'Q' . substr($c, 1, 1), $form->unanswered())) . '.';
        });
    }

    public function countPrint(Form $form): void
    {
        $form->increment('print_count');
    }

    /** "LKN/123" on a form that already prints "LKN/" would read LKN/LKN/123. */
    public static function withoutPrefix(?string $value, string $prefix): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_replace('#^' . preg_quote($prefix, '#') . '\s*[/\-]?\s*#i', '', $value) ?: $value;
    }
}
