<?php

namespace App\Services\CadastralDept;

use App\Services\InstrumentWorkflow\WorkflowGuardException;

/**
 * Decides what a case may do next. Pure: it takes the case's workflow, step,
 * sub-type and completed tasks and returns actions or the next step. Writing the
 * move is CaseWorkflow's job.
 */
class WorkflowEngine
{
    public function __construct(private WorkflowDefinition $definition)
    {
    }

    public function definition(): WorkflowDefinition
    {
        return $this->definition;
    }

    /**
     * Buttons for the current step: [outcome, label, next, ends, reason].
     * reason is true when the choice needs a written reason.
     * A plain step has one 'forward' action; a decision has one per outcome; a
     * sub-type route has one 'forward' whose next depends on the case.
     */
    public function actions(string $workflow, string $step, ?string $subType = null): array
    {
        $s = $this->definition->step($workflow, $step);

        if (isset($s['end'])) {
            return [[
                'outcome' => 'close',
                'label' => $s['action_label'] ?? $s['label'],
                'next' => null,
                'ends' => $s['end'],
                'reason' => false,
            ]];
        }

        if (isset($s['outcomes'])) {
            $out = [];
            foreach ($s['outcomes'] as $key => $o) {
                $out[] = ['outcome' => $key, 'label' => $o['label'], 'next' => $o['next'], 'ends' => null, 'reason' => !empty($o['reason'])];
            }

            return $out;
        }

        $next = $this->nextStep($workflow, $step, 'forward', $subType);
        $nextDesk = $this->definition->step($workflow, $next)['desk'];

        // A file out with another department: the receiving desk records that
        // it came back, and where it goes from there.
        $label = !empty($s['external'])
            ? $this->definition->deskShort($s['desk']) . ' done: ' . ($nextDesk === $this->definition->actingDesk($workflow, $step)
                ? 'sent back to ' . $this->definition->deskShort($nextDesk)   // e.g. "DSG done: sent back to D Cad" (user, 2026-10-12)
                : 'now with ' . $this->definition->deskShort($nextDesk))
            : 'Send to ' . $this->definition->deskShort($nextDesk);

        return [[
            'outcome' => 'forward',
            'label' => $label,
            'next' => $next,
            'ends' => null,
            'reason' => false,
        ]];
    }

    /**
     * The step an outcome leads to. Null when the step closes the case.
     *
     * @throws WorkflowGuardException for an outcome the step does not offer, or a
     *         sub-type route with no sub-type on the case.
     */
    public function nextStep(string $workflow, string $step, string $outcome, ?string $subType = null): ?string
    {
        $s = $this->definition->step($workflow, $step);

        if (isset($s['end'])) {
            if ($outcome !== 'close') {
                throw WorkflowGuardException::because('This step closes the case.');
            }

            return null;
        }

        if (isset($s['outcomes'])) {
            return $s['outcomes'][$outcome]['next']
                ?? throw WorkflowGuardException::because("\"$outcome\" is not a decision this step offers.");
        }

        if ($outcome !== 'forward') {
            throw WorkflowGuardException::because('This step only forwards.');
        }

        if (isset($s['route_by'])) {
            if (!$subType || !isset($s['routes'][$subType])) {
                throw WorkflowGuardException::because('Set the case sub-type before forwarding: it decides where the file goes next.');
            }

            return $s['routes'][$subType];
        }

        return $s['next'];
    }

    /** Task keys of the step not yet done. */
    public function missingTasks(string $workflow, string $step, array $doneTaskKeys): array
    {
        $tasks = array_keys((array) ($this->definition->step($workflow, $step)['tasks'] ?? []));

        return array_values(array_diff($tasks, $doneTaskKeys));
    }

    /**
     * The process as a pipeline: the steps already taken (from the movement log),
     * the step the file is at, then the expected way on to the end. The way on
     * takes the forward answer at each decision and the sub-type's route, and
     * lists the other answers so the officer sees where else the file could go.
     * With no history and no current step it is the plain route from the start.
     *
     * @param  iterable<object>  $movements  CadastralCaseMovement rows, oldest first
     * @return list<array{key:?string, label:string, desk:string, state:string, outcome:?string, by:?string, at:mixed, options:list<string>}>
     */
    public function pipeline(string $workflow, ?string $subType = null, iterable $movements = [], ?string $current = null, ?string $closedAs = null): array
    {
        $def = $this->definition;
        $out = [];

        foreach ($movements as $m) {
            if ($m->from_step === null) {   // registration
                $out[] = ['key' => null, 'label' => 'Registered', 'desk' => $def->deskShort($m->from_desk ?: 'registry'), 'state' => 'done',
                    'outcome' => null, 'by' => optional($m->actor)->name, 'at' => $m->acted_at, 'options' => []];
                continue;
            }
            if (!$def->hasStep($workflow, $m->from_step)) {
                continue;
            }
            $s = $def->step($workflow, $m->from_step);
            // Head-of-unit approval stays on the step: show it as its own entry (HeadApproval).
            if ($m->outcome === HeadApproval::TO_HEAD || $m->outcome === HeadApproval::RETURNED) {
                $head = $def->desks()[$s['desk']]['head'] ?? $def->deskShort($s['desk']);
                $out[] = ['key' => $m->from_step, 'label' => $m->outcome === HeadApproval::TO_HEAD ? "Sent to the {$head} for approval" : "Returned to staff by the {$head}",
                    'desk' => $m->outcome === HeadApproval::TO_HEAD ? $head : $def->deskShort($s['desk']), 'state' => 'done',
                    'outcome' => null, 'by' => optional($m->actor)->name, 'at' => $m->acted_at, 'options' => []];
                continue;
            }
            $out[] = ['key' => $m->from_step, 'label' => $s['label'], 'desk' => $def->deskShort($s['desk']), 'state' => 'done',
                'outcome' => $s['outcomes'][$m->outcome]['label'] ?? null, 'by' => optional($m->actor)->name, 'at' => $m->acted_at, 'options' => []];
        }

        if ($closedAs) {
            $out[] = ['key' => null, 'label' => $closedAs === 'returned' ? 'Sent back to source' : 'Completed', 'desk' => '',
                'state' => 'end', 'outcome' => null, 'by' => null, 'at' => null, 'options' => []];

            return $out;
        }

        // The way on, from the current step (or the start).
        $key = $current ?? $def->startStep($workflow);
        $seen = [];
        while ($key && $def->hasStep($workflow, $key) && !isset($seen[$key]) && count($seen) < 60) {
            $seen[$key] = true;
            $s = $def->step($workflow, $key);
            $options = [];
            $next = null;

            if (isset($s['outcomes'])) {
                foreach ($s['outcomes'] as $o) {
                    $options[] = $o['label'];
                }
                // The forward answer: the first one that is not a refusal.
                foreach ($s['outcomes'] as $o) {
                    if (empty($o['reason'])) {
                        $next = $o['next'];
                        break;
                    }
                }
            } elseif (isset($s['routes'])) {
                foreach ($s['routes'] as $sub => $to) {
                    $options[] = ($def->subTypes($workflow)[$sub] ?? $sub) . ': ' . $def->deskShort($def->step($workflow, $to)['desk']);
                }
                $next = $s['routes'][$subType ?? ''] ?? reset($s['routes']);
            } else {
                $next = $s['next'] ?? null;
            }

            $out[] = ['key' => $key, 'label' => $s['label'], 'desk' => $def->deskShort($s['desk']),
                'state' => $key === $current ? 'current' : 'next', 'outcome' => null, 'by' => null, 'at' => null,
                'options' => count($options) > 1 ? $options : []];

            if (isset($s['end'])) {
                $out[] = ['key' => null, 'label' => $s['end'] === 'returned' ? 'Sent back to source' : 'Completed', 'desk' => '',
                    'state' => 'next', 'outcome' => null, 'by' => null, 'at' => null, 'options' => []];
                break;
            }
            $key = $next;
        }

        return $out;
    }

    /** Phase progress for a stage tracker: [phase => done|current|waiting]. */
    public function phaseProgress(string $workflow, string $currentStep, bool $closed = false): array
    {
        $current = $this->definition->step($workflow, $currentStep)['phase'] ?? 0;
        $out = [];
        foreach (array_keys($this->definition->workflow($workflow)['phases']) as $phase) {
            $out[$phase] = $closed || $phase < $current ? 'done' : ($phase === $current ? 'current' : 'waiting');
        }

        return $out;
    }
}
