<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The files a desk has decided and sent on, for the "Approved" tab of an Approval / Recommend
 * table (CR item 13, 2026-11-09). A decision is a movement out of one of the desk's steps to
 * another desk (or closing the file). Registration, Send to head, the head returning a file to
 * staff and an admin's move are not decisions.
 */
class DeskDecisions
{
    public const NOT_DECISIONS = [HeadApproval::TO_HEAD, HeadApproval::RETURNED, CaseWorkflow::JUMP];

    public function __construct(private WorkflowDefinition $def)
    {
    }

    /** Narrows a CadastralCase query to the files with a decision made at $desk. */
    public function decidedAt(Builder $query, string $desk): Builder
    {
        return $query->whereHas('movements', fn ($m) => $this->decisionMovements($m, $desk));
    }

    /**
     * The latest decision at $desk for each case: [case id => label, tone (green|red), by, at, to].
     *
     * @return array<int, array{label:string, tone:string, by:?string, at:mixed, to:?string}>
     */
    public function latest(Collection $cases, string $desk): array
    {
        if ($cases->isEmpty()) {
            return [];
        }
        $byCase = $cases->keyBy('id');
        $out = [];
        $this->decisionMovements(CadastralCaseMovement::query()->with('actor'), $desk)
            ->whereIn('cadastral_case_id', $byCase->keys()->all())
            ->orderByDesc('acted_at')->orderByDesc('id')->get()
            ->each(function ($m) use (&$out, $byCase) {
                if (isset($out[$m->cadastral_case_id])) {
                    return;
                }
                $case = $byCase[$m->cadastral_case_id];
                $step = $this->def->hasStep($case->workflow, (string) $m->from_step) ? $this->def->step($case->workflow, $m->from_step) : [];
                $answer = $step['outcomes'][$m->outcome] ?? null;
                $out[$m->cadastral_case_id] = [
                    // A step with answers shows the answer picked; a plain send on is an approval.
                    'label' => $answer['label'] ?? ($m->outcome === 'close' ? 'Closed' : 'Approved'),
                    // An answer that needs a reason is the refusal (Not Recommended, back to ...).
                    'tone' => !empty($answer['reason']) ? 'red' : 'green',
                    'by' => optional($m->actor)->name,
                    'at' => $m->acted_at,
                    'to' => $m->to_desk ? $this->def->deskShort($m->to_desk) : null,
                ];
            });

        return $out;
    }

    private function decisionMovements($query, string $desk)
    {
        return $query->where('from_desk', $desk)
            ->whereNotNull('from_step')
            ->whereNotIn('outcome', self::NOT_DECISIONS)
            ->where(fn ($w) => $w->whereNull('to_desk')->orWhereColumn('to_desk', '<>', 'from_desk'));
    }
}
