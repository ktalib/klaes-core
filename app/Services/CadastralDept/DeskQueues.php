<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\Cache;

/**
 * The sidebar's per-desk lists (config cadastral_dept.queues): which open files
 * belong on each one. A list is a filter over the desk's inbox, so a file can
 * be on several lists, and the row's action menu still decides what it can do.
 */
class DeskQueues
{
    public const CACHE_KEY = 'cadastral_dept.sidebar_queue_counts';

    public function __construct(private WorkflowEngine $engine)
    {
    }

    /** True when the file belongs on the list. A list with no rules takes every file. */
    public function matches(CadastralCase $case, array $rules): bool
    {
        $def = $this->engine->definition();
        $hasRule = false;

        if (!empty($rules['steps'])) {
            $hasRule = true;
            if (in_array($case->current_step, (array) $rules['steps'], true)) {
                return true;
            }
        }

        $missing = null;
        $open = function () use ($case, &$missing) {
            return $missing ??= $this->engine->missingTasks($case->workflow, $case->current_step, $case->doneTaskKeys());
        };

        if (!empty($rules['tasks'])) {
            $hasRule = true;
            if (array_intersect((array) $rules['tasks'], $open())) {
                return true;
            }
        }

        if (!empty($rules['at'])) {
            $hasRule = true;
            if (in_array($case->current_desk, (array) $rules['at'], true)) {
                return true;
            }
        }

        if (!empty($rules['to'])) {
            $hasRule = true;
            try {
                $actions = $this->engine->actions($case->workflow, $case->current_step, $case->sub_type);
                // Final Charting: only the route the file's surveyor calls for (SG for a Government survey).
                $actions = app(\App\Services\CadastralDept\SurveyRoute::class)->filter($case, $actions);
            } catch (WorkflowGuardException $e) {
                $actions = [];
            }
            foreach ($actions as $a) {
                if ($a['next'] && in_array($def->step($case->workflow, $a['next'])['desk'] ?? null, (array) $rules['to'], true)) {
                    return true;
                }
            }
        }

        // Approval: files the unit's staff sent to the head, waiting there (HeadApproval).
        if (!empty($rules['awaiting_head'])) {
            $hasRule = true;
            if (app(HeadApproval::class)->awaiting($case)) {
                return true;
            }
        }

        // Registry > DMS Update (sidebar, 2026-10-20): files back from Land whose new pages are
        // still to scan (Scan More: count not entered, or nothing uploaded yet) or to page-type
        // (More Pages: uploaded, the DMS update not marked done). PageCounts / EdmsUpdate.
        if (!empty($rules['dms'])) {
            $hasRule = true;
            if ($this->dmsStage($case) === $rules['dms']) {
                return true;
            }
        }

        if (!empty($rules['ready'])) {
            $hasRule = true;
            if ($open() === []) {
                return true;
            }
        }

        return !$hasRule;
    }

    /** scan | type | null: where a file back from Land stands on its DMS update. */
    public function dmsStage(CadastralCase $case): ?string
    {
        $counts = app(PageCounts::class);
        if ($counts->direction($case) !== 'back' || in_array(EdmsUpdate::TASK, $case->doneTaskKeys(), true)) {
            return null;
        }
        $state = $counts->state($case);
        if ($state['triggered'] === false) {
            return null;
        }
        if ($state['triggered'] === null) {
            return 'scan';
        }

        return app(EdmsUpdate::class)->status($case)['new_pages'] > 0 ? 'type' : 'scan';
    }

    /**
     * Open files a desk handles: those at the desk, and those out with another
     * department that this desk records back (the inbox's two lists).
     */
    public function deskCases(string $desk)
    {
        $def = $this->engine->definition();
        $recorded = [];
        foreach ($def->stepsForDesk($desk) as $wf => $steps) {
            foreach ($steps as $step) {
                if (!empty($def->step($wf, $step)['external'])) {
                    $recorded[$wf][] = $step;
                }
            }
        }

        return CadastralCase::query()->open()->with(['tasks', 'latestMovement'])
            ->where(function ($q) use ($desk, $recorded) {
                $q->where('current_desk', $desk);
                foreach ($recorded as $wf => $steps) {
                    $q->orWhere(fn ($w) => $w->where('workflow', $wf)->whereIn('current_step', $steps));
                }
            })->get();
    }

    /**
     * Counts for the sidebar, cached for a minute: [desk => ['_all' => n, key => n]].
     * A failure gives no counts; it never takes the sidebar down.
     */
    public function counts(): array
    {
        try {
            if (!CaseWorkflow::installed()) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, 60, function () {
                $out = [];
                foreach ($this->engine->definition()->queues() as $desk => $q) {
                    $cases = $this->deskCases($desk);
                    $out[$desk]['_all'] = $cases->count();
                    foreach ((array) ($q['entries'] ?? []) as $key => $rules) {
                        $out[$desk][$key] = $cases->filter(fn ($c) => $this->matches($c, $rules))->count();
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            return [];
        }
    }
}
