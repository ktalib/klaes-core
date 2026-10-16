<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCharting;
use App\Models\CadastralDept\CadastralFmc;

/**
 * The checks "Send to Deeds" runs (client brief, 2026-10-10): it is the last
 * action, only after the correspondence file is detached, and only when the
 * FMC exists, charting is done, the correspondence file exists and the Fees
 * P&D bill has been issued. A step lists the checks it needs in
 * config/cadastral_dept.php ('checks').
 */
class DeedsDispatchChecks
{
    public const LABELS = [
        'fmc' => 'File Movement Card commissioned',
        'charting' => 'Charting done',
        'correspondence' => 'Correspondence file created',
        'bill' => 'Fees P&D bill issued',
        'detached' => 'Correspondence file detached',
    ];

    public function __construct(private WorkflowDefinition $def, private CorrespondenceCustody $custody)
    {
    }

    /** The checks the case's current step needs; empty when none. */
    public function keys(CadastralCase $case): array
    {
        return (array) ($this->def->step($case->workflow, $case->current_step)['checks'] ?? []);
    }

    /**
     * Each check with its result and what was found.
     *
     * @return array<string, array{label: string, ok: bool, detail: string}>
     */
    public function results(CadastralCase $case): array
    {
        $out = [];
        foreach ($this->keys($case) as $key) {
            [$ok, $detail] = match ($key) {
                'fmc' => $this->fmc($case),
                'charting' => $this->charting($case),
                'correspondence' => trim((string) $case->correspondence_fileno) !== ''
                    ? [true, $case->correspondence_fileno . ($case->shadow_fileno ? ' · ' . $case->shadow_fileno : '')]
                    : [false, 'None yet'],
                'bill' => ($b = PndBills::liveBill((string) $case->file_number))
                    ? [true, $b->bill_ref . ' · ₦' . number_format((float) $b->grand_total, 2)]
                    : [false, 'No issued bill'],
                'detached' => !trim((string) $case->correspondence_fileno)
                    ? [true, 'Not needed: the file has no correspondence file']
                    : (!CorrespondenceCustody::installed()
                    ? [true, 'Not tracked yet (custody table not created)']
                    : ($this->custody->isDetached($case) ? [true, 'Detached'] : [false, 'Still attached'])),
                default => [false, 'Unknown check'],
            };
            $out[$key] = ['label' => self::LABELS[$key] ?? $key, 'ok' => $ok, 'detail' => $detail];
        }

        return $out;
    }

    /** Labels of the checks that fail. */
    public function failing(CadastralCase $case): array
    {
        return array_values(array_map(fn ($r) => $r['label'], array_filter($this->results($case), fn ($r) => !$r['ok'])));
    }

    private function fmc(CadastralCase $case): array
    {
        if (!FileMovementCards::installed()) {
            return [false, 'FMC tables not created'];
        }
        $fmc = CadastralFmc::query()->where('cadastral_case_id', $case->id)->first(['fmc_no']);

        return $fmc ? [true, $fmc->fmc_no] : [false, 'Not commissioned'];
    }

    /** Every charting stage the case's workflow asks for has a current card. */
    private function charting(CadastralCase $case): array
    {
        if (!ChartingCards::installed()) {
            return [false, 'Charting tables not created'];
        }
        $stages = [];
        foreach ($this->def->steps($case->workflow) as $step) {
            foreach (array_keys((array) ($step['tasks'] ?? [])) as $task) {
                if (isset(ChartingCards::TASK_STAGE[$task])) {
                    $stages[ChartingCards::TASK_STAGE[$task]] = true;
                }
            }
        }
        $cards = CadastralCharting::query()->where('cadastral_case_id', $case->id)->where('is_current', true)
            ->whereIn('stage', array_keys($stages))->get(['stage', 'charting_no'])->keyBy('stage');
        $missing = array_diff(array_keys($stages), $cards->keys()->all());
        if ($missing) {
            return [false, 'Missing: ' . implode(', ', array_map(fn ($s) => CadastralCharting::STAGES[$s] ?? $s, $missing))];
        }

        return [true, $cards->pluck('charting_no')->unique()->implode(', ')];
    }
}
