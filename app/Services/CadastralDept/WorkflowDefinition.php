<?php

namespace App\Services\CadastralDept;

use InvalidArgumentException;

/**
 * Read-only view of config/cadastral_dept.php: desks, workflows and their steps.
 *
 * Holds no state beyond the config array, so it can be built from a test array.
 * validate() lists graph problems (dangling next, unreachable or dead-end steps);
 * WorkflowDefinitionTest asserts it is empty for the shipped config.
 */
class WorkflowDefinition
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('cadastral_dept', []);
    }

    public function desks(): array
    {
        return (array) ($this->config['desks'] ?? []);
    }

    public function desk(string $key): array
    {
        return $this->desks()[$key] ?? throw new InvalidArgumentException("Unknown desk [$key].");
    }

    public function deskLabel(?string $key): string
    {
        return $key ? ($this->desks()[$key]['label'] ?? $key) : '';
    }

    /** The client's short form for a desk (D Cad, DD Cad, ...). */
    public function deskShort(?string $key): string
    {
        return $key ? ($this->desks()[$key]['short'] ?? $this->deskLabel($key)) : '';
    }

    /** Desks that are Cadastral posts, i.e. that have an inbox. */
    public function internalDesks(): array
    {
        return array_filter($this->desks(), fn ($d) => !empty($d['internal']));
    }

    /** Coloured chip for a workflow (Tailwind v2 default palette only). */
    public function wfChip(string $workflow): string
    {
        $w = $this->workflows()[$workflow] ?? ['short' => $workflow, 'colour' => 'gray'];
        $c = $w['colour'] ?? 'gray';

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">'
            . e($w['short'] ?? $workflow) . '</span>';
    }

    /** Coloured chip for a case status. */
    public function statusChip(string $status): string
    {
        [$label, $c] = [
            'in_progress' => ['In progress', 'blue'],
            'completed' => ['Completed', 'green'],
            'returned' => ['Sent back', 'red'],
        ][$status] ?? [$status, 'gray'];

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">'
            . e($label) . '</span>';
    }

    public function workflows(): array
    {
        return (array) ($this->config['workflows'] ?? []);
    }

    public function has(string $workflow): bool
    {
        return isset($this->workflows()[$workflow]);
    }

    public function workflow(string $key): array
    {
        return $this->workflows()[$key] ?? throw new InvalidArgumentException("Unknown workflow [$key].");
    }

    /** File types offered at intake: key => [label, workflows, asks_sub_type?]. */
    public function fileTypes(): array
    {
        return (array) ($this->config['file_types'] ?? []);
    }

    /** File types an application type accepts. */
    public function fileTypesFor(string $workflow): array
    {
        return array_filter($this->fileTypes(), fn ($t) => in_array($workflow, (array) ($t['workflows'] ?? []), true));
    }

    /** Where a main file can come from: key => [label, lookup]. */
    public function fileSources(): array
    {
        return (array) ($this->config['file_sources'] ?? []);
    }

    /** The file-source key for a CadastralRegistryLookup intake source (Land, SLTR, ...), or null. */
    public function fileSourceForLookup(?string $lookup): ?string
    {
        foreach ($this->fileSources() as $key => $src) {
            if ($lookup !== null && ($src['lookup'] ?? null) === $lookup) {
                return $key;
            }
        }

        return null;
    }

    /** The sub-type a file gets when its file type does not ask for one. */
    public function defaultSubType(string $workflow): ?string
    {
        return $this->workflow($workflow)['default_sub_type'] ?? null;
    }

    /** Whether an outcome of a step needs a written reason. */
    public function outcomeNeedsReason(string $workflow, string $step, string $outcome): bool
    {
        return !empty($this->step($workflow, $step)['outcomes'][$outcome]['reason']);
    }

    public function subTypes(string $workflow): array
    {
        return (array) ($this->workflow($workflow)['sub_types'] ?? []);
    }

    public function startStep(string $workflow): string
    {
        return $this->workflow($workflow)['start'];
    }

    public function steps(string $workflow): array
    {
        return (array) ($this->workflow($workflow)['steps'] ?? []);
    }

    public function hasStep(string $workflow, string $step): bool
    {
        return isset($this->steps($workflow)[$step]);
    }

    public function step(string $workflow, string $step): array
    {
        return $this->steps($workflow)[$step]
            ?? throw new InvalidArgumentException("Unknown step [$step] in workflow [$workflow].");
    }

    /** The Cadastral desk that acts on a step: its own desk, or the recorder of an external one. */
    public function actingDesk(string $workflow, string $step): string
    {
        $s = $this->step($workflow, $step);

        return $s['recorded_by'] ?? $s['desk'];
    }

    /** Every step key a step can lead to, whatever the decision or sub-type. */
    public function successors(string $workflow, string $step): array
    {
        $s = $this->step($workflow, $step);

        if (isset($s['next'])) {
            return [$s['next']];
        }
        if (isset($s['outcomes'])) {
            return array_values(array_unique(array_column($s['outcomes'], 'next')));
        }
        if (isset($s['routes'])) {
            return array_values(array_unique(array_values($s['routes'])));
        }

        return [];
    }

    /**
     * Steps in walk order from the start (breadth-first), for display. The main
     * path comes first because the forward outcome is listed first in config.
     */
    public function orderedSteps(string $workflow): array
    {
        $seen = [];
        $queue = [$this->startStep($workflow)];

        while ($queue) {
            $key = array_shift($queue);
            if (isset($seen[$key]) || !$this->hasStep($workflow, $key)) {
                continue;
            }
            $seen[$key] = $this->step($workflow, $key) + ['key' => $key];
            foreach ($this->successors($workflow, $key) as $next) {
                $queue[] = $next;
            }
        }

        return $seen;
    }

    /** Steps grouped by phase number, in walk order. */
    public function stepsByPhase(string $workflow): array
    {
        $out = [];
        foreach ($this->orderedSteps($workflow) as $key => $step) {
            $out[$step['phase'] ?? 0][$key] = $step;
        }
        ksort($out);

        return $out;
    }

    /** [workflow => [step keys]] of the steps a desk acts on. */
    public function stepsForDesk(string $desk): array
    {
        $out = [];
        foreach (array_keys($this->workflows()) as $wf) {
            foreach ($this->steps($wf) as $key => $s) {
                if (($s['recorded_by'] ?? $s['desk']) === $desk) {
                    $out[$wf][] = $key;
                }
            }
        }

        return $out;
    }

    /**
     * Problems with the graph. Empty means every step is well formed, every
     * reference resolves, every step is reachable, and every step can reach an end.
     *
     * @return string[]
     */
    public function validate(): array
    {
        $problems = [];
        $desks = $this->desks();
        $ends = ['completed', 'returned'];

        foreach ($this->fileTypes() as $key => $type) {
            foreach ((array) ($type['workflows'] ?? []) as $wf) {
                if (!$this->has($wf)) {
                    $problems[] = "file type $key: unknown workflow [$wf]";
                }
            }
        }

        foreach ($this->workflows() as $wf => $def) {
            if (isset($def['default_sub_type']) && !isset($def['sub_types'][$def['default_sub_type']])) {
                $problems[] = "$wf: default_sub_type is not one of its sub_types";
            }

            $steps = (array) ($def['steps'] ?? []);

            if (!isset($steps[$def['start'] ?? ''])) {
                $problems[] = "$wf: start step [" . ($def['start'] ?? '') . '] does not exist';
                continue;
            }

            foreach ($steps as $key => $s) {
                $where = "$wf.$key";
                if (!isset($desks[$s['desk'] ?? ''])) {
                    $problems[] = "$where: unknown desk [" . ($s['desk'] ?? '') . ']';
                }
                if (isset($s['recorded_by']) && empty($desks[$s['recorded_by']]['internal'])) {
                    $problems[] = "$where: recorded_by [{$s['recorded_by']}] is not an internal desk";
                }
                if (!empty($s['external']) && !isset($s['recorded_by'])) {
                    $problems[] = "$where: external step has no recorded_by";
                }
                if (empty($s['external']) && empty($desks[$s['desk'] ?? '']['internal'])) {
                    $problems[] = "$where: step at external desk is not marked external";
                }
                if (!isset($def['phases'][$s['phase'] ?? null])) {
                    $problems[] = "$where: phase [" . ($s['phase'] ?? '') . '] is not declared';
                }

                $kinds = array_intersect(['next', 'outcomes', 'route_by', 'end'], array_keys($s));
                if (count($kinds) !== 1) {
                    $problems[] = "$where: needs exactly one of next / outcomes / route_by / end";
                }
                if (isset($s['question']) && !isset($s['outcomes'])) {
                    $problems[] = "$where: a question needs outcomes";
                }
                if (isset($s['end']) && !in_array($s['end'], $ends, true)) {
                    $problems[] = "$where: end must be completed or returned";
                }
                if (isset($s['route_by'])) {
                    $values = array_keys((array) ($def[$s['route_by'] === 'sub_type' ? 'sub_types' : $s['route_by']] ?? []));
                    $missing = array_diff($values, array_keys((array) ($s['routes'] ?? [])));
                    if (!$values || $missing) {
                        $problems[] = "$where: routes do not cover every {$s['route_by']} value";
                    }
                }
                foreach ($this->successors($wf, $key) as $next) {
                    if (!isset($steps[$next])) {
                        $problems[] = "$where: next step [$next] does not exist";
                    }
                }
            }

            $reachable = array_keys($this->orderedSteps($wf));
            foreach (array_diff(array_keys($steps), $reachable) as $orphan) {
                $problems[] = "$wf.$orphan: unreachable from the start";
            }

            // Every step must be able to reach an end step.
            $canEnd = array_keys(array_filter($steps, fn ($s) => isset($s['end'])));
            do {
                $grew = false;
                foreach ($steps as $key => $s) {
                    if (!in_array($key, $canEnd, true)
                        && array_intersect($this->successors($wf, $key), $canEnd)) {
                        $canEnd[] = $key;
                        $grew = true;
                    }
                }
            } while ($grew);
            foreach (array_diff(array_keys($steps), $canEnd) as $stuck) {
                $problems[] = "$wf.$stuck: can never reach an end";
            }
        }

        return $problems;
    }
}
