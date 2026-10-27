<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\SurveyReportRequest;

/**
 * Land 12 at AD Cad Report (step 4) (user, 2026-10-27: "at that step the Land 12 form modal should
 * open, so the details will be captured, some might backfill").
 *
 * The file page opens the shared Land 12 form (SurveyReportController, url=cadastral) in a pop-up:
 * the Land 12 Lands raised for the file when there is one (to complete Cadastral's part), else a new
 * one. Opened with cd_case, the form is prefilled from the file (prefill()) and saving it ticks the
 * step's Land 12 task (recorded()), so the task is no longer ticked blind.
 */
class Land12Link
{
    public const TASK = 'land12';

    /** The case's current step asks for Land 12 and it is not ticked yet. */
    public function due(CadastralCase $case): bool
    {
        return $case->isOpen()
            && isset(((new WorkflowDefinition())->step($case->workflow, $case->current_step)['tasks'] ?? [])[self::TASK])
            && !in_array(self::TASK, $case->doneTaskKeys($case->current_step), true);
    }

    /** The newest Land 12 for the file, or null. */
    public function latest(CadastralCase $case): ?SurveyReportRequest
    {
        return SurveyReportRequest::query()->where('file_number', $case->file_number)->orderByDesc('id')->first();
    }

    /**
     * What the form starts with for this file (development standard 5). Only blanks are filled:
     * a value already on the Land 12 wins. The file number and title are locked.
     *
     * @return array{case_id:int, next:string, file_number:string, file_title:?string, plot:?string, district:?string,
     *               lga:?string, beacons:?string, item_g:?string, date:string}
     */
    public function prefill(CadastralCase $case): array
    {
        $beacons = null;
        try {
            $card = app(PndBills::class)->backfill($case);
            $beacons = $card ? implode("\n", array_filter(array_column($card['pillars'], 'pillar_number'))) ?: null : null;
        } catch (\Throwable $e) {
            report($e);
        }
        $itemG = null;
        try {
            $itemG = YesNoForms::installed() ? (app(YesNoForms::class)->refs($case)['gkn'] ?? null) : null;
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'case_id' => (int) $case->id,
            // After saving: on to the next card, the Yes/No form (change request 26-10-2026).
            'next' => route('cadastral-dept.cases.yes-no.show', $case->id, false),
            'file_number' => $case->file_number,
            'file_title' => $case->file_title,
            'plot' => $case->plot_no,
            'district' => $case->district,
            'lga' => $case->lga,
            'beacons' => $beacons,
            'item_g' => $itemG,
            'date' => now()->format('Y-m-d'),
        ];
    }

    /** A Land 12 was saved from the file's pop-up: tick the step's task with its reference. */
    public function recorded(int $caseId, SurveyReportRequest $land12, ?int $userId): void
    {
        $case = CadastralCase::find($caseId);
        if (!$case || !$this->due($case) || $land12->file_number !== $case->file_number) {
            return;
        }
        CadastralCaseTask::create([
            'cadastral_case_id' => $case->id,
            'step' => $case->current_step,
            'task_key' => self::TASK,
            'note' => 'Land 12 #' . $land12->id . ($land12->status ? " ({$land12->status})" : ''),
            'done_by' => $userId,
            'done_at' => now(),
        ]);
        app(\App\Services\AuditService::class)->logAction('Cadastral Land 12 Recorded', 'CadastralCase', $case->id, null,
            ['survey_report_request_id' => $land12->id], "{$case->case_ref}: Land 12 #{$land12->id} saved from the file page");
    }
}
