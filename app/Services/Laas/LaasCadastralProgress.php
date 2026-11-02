<?php

namespace App\Services\Laas;

use App\Models\CadastralDept\CadastralCase;
use App\Models\Laas\LaasApplication;
use App\Services\CadastralDept\WorkflowDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The Cadastral Department workflow on the LAAS portal (user, 2026-11-02): a portal holder sees
 * where their file is in Cadastral (and the Survey stages inside it: compensation check, field
 * survey, examination, charting), read live from the file's Cadastral case.
 *
 * Milestones are the workflow's own phases (config/cadastral_dept.php), so the portal and the
 * Cadastral screens always agree. Desks, officers and minutes are never shown; a returned file
 * reads "the office will contact you" (user: no reason shown). No SMS for now (user, 2026-11-02):
 * phase changes only add a line to the portal's "What has happened".
 */
class LaasCadastralProgress
{
    /** Public wording per phase name; anything not listed shows the phase name itself. */
    private const PUBLIC_PHASE = [
        'Initial Reception & Recommendation' => 'Received & under review',
        'Acquisition Check & GIS Buffer'     => 'Acquisition & compensation check',
        'Acquisition Check & Routing'        => 'Acquisition & compensation check',
        'Planning Advice'                    => 'Planning advice',
        'Registry & Land Dept Processing'    => 'With the Land Department',
        'Survey Instruction & Execution'     => 'Field survey',
        'Survey Examination & Final Charting' => 'Survey examination & charting',
        'Billing & Final Handoff'            => 'Billing & dispatch',
        'Charting Verification'              => 'Charting verification',
        'File Return'                        => 'File return',
    ];

    public static function installed(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('cadastral_cases');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The progress of one file, or null when the file has never been to Cadastral. */
    public function forFile(?string $fileNumber): ?array
    {
        return $this->forFiles([$fileNumber])[self::key($fileNumber)] ?? null;
    }

    /**
     * Progress for several files at once (the dashboard), keyed by upper-cased file number.
     * The latest Cadastral case of each file wins.
     */
    public function forFiles(array $fileNumbers): array
    {
        $nos = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $fileNumbers))));
        if (!$nos || !self::installed()) {
            return [];
        }

        try {
            $cases = collect();
            foreach (array_chunk($nos, 1000) as $chunk) {
                $cases = $cases->concat(CadastralCase::query()->whereIn('file_number', $chunk)->orderBy('id')->get());
            }
            if ($cases->isEmpty()) {
                return [];
            }
            $latest = $cases->keyBy(fn ($c) => self::key($c->file_number));

            $moves = DB::connection('sqlsrv')->table('cadastral_case_movements')
                ->whereIn('cadastral_case_id', $latest->pluck('id')->all())
                ->whereNotNull('to_step')
                ->orderBy('acted_at')->orderBy('id')
                ->get(['cadastral_case_id', 'to_step', 'acted_at'])
                ->groupBy('cadastral_case_id');

            $def = new WorkflowDefinition();
            $out = [];
            foreach ($latest as $key => $case) {
                $out[$key] = $this->build($def, $case, $moves->get($case->id, collect()));
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('LAAS: Cadastral progress could not be read', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function build(WorkflowDefinition $def, CadastralCase $case, $moves): array
    {
        $wf = $def->has((string) $case->workflow) ? $def->workflow($case->workflow) : ['label' => 'Cadastral', 'phases' => []];
        $phases = (array) ($wf['phases'] ?? []);
        $current = $this->phaseOf($def, $case->workflow, $case->current_step);

        // When each phase was first reached, from the movement log.
        $reached = [];
        foreach ($moves as $m) {
            $p = $this->phaseOf($def, $case->workflow, $m->to_step);
            if ($p && !isset($reached[$p])) {
                $reached[$p] = $m->acted_at;
            }
        }
        $reached[1] ??= $case->received_at ?? $case->created_at;

        $completed = $case->status === CadastralCase::STATUS_COMPLETED;
        $returned  = $case->status === CadastralCase::STATUS_RETURNED || $case->current_step === 'registry_return';

        $rows = [];
        foreach ($phases as $n => $name) {
            $state = $completed || ($current && $n < $current) ? 'done' : ($n === $current ? 'current' : 'todo');
            $rows[] = [
                'n'     => $n,
                'label' => self::publicPhase($name),
                'state' => $state,
                'at'    => $state === 'todo' ? null : ($reached[$n] ?? null),
            ];
        }

        return [
            'workflow'  => $wf['label'] ?? 'Cadastral',
            'phases'    => $rows,
            'current'   => $current ? self::publicPhase($phases[$current] ?? '') : null,
            'completed' => $completed,
            'returned'  => $returned,
            'closed_at' => $case->closed_at,
            'percent'   => $phases ? (int) round(($completed ? count($phases) : max(0, ($current ?: 1) - 1)) / count($phases) * 100) : 0,
        ];
    }

    private function phaseOf(WorkflowDefinition $def, ?string $workflow, ?string $step): ?int
    {
        if (!$workflow || !$step || !$def->has($workflow) || !$def->hasStep($workflow, $step)) {
            return null;
        }
        $p = (int) ($def->step($workflow, $step)['phase'] ?? 0);

        return $p > 0 ? $p : null;
    }

    public static function publicPhase(string $name): string
    {
        return self::PUBLIC_PHASE[$name] ?? $name;
    }

    private static function key(?string $no): string
    {
        return mb_strtoupper(trim((string) $no));
    }

    /**
     * CaseWorkflow::move() calls this for every movement. When the file belongs to a portal
     * holder, the opening moves the portal stage to "At Cadastral" and each new phase, a return
     * or the completion adds a line to the portal's timeline. No SMS. Never throws, and runs
     * after the Cadastral transaction commits so it cannot hold up or undo the movement.
     */
    public function moved(CadastralCase $case, ?string $fromStep, ?string $toStep, string $outcome): void
    {
        $snapshot = clone $case;
        $run = function () use ($snapshot, $fromStep, $toStep, $outcome) {
            try {
                $this->record($snapshot, $fromStep, $toStep, $outcome);
            } catch (\Throwable $e) {
                Log::warning('LAAS: Cadastral progress event failed', ['case' => $snapshot->case_ref, 'error' => $e->getMessage()]);
            }
        };

        DB::connection('sqlsrv')->afterCommit($run);
    }

    /** What moved() writes; public so a backfill can replay it. */
    public function record(CadastralCase $case, ?string $fromStep, ?string $toStep, string $outcome): ?string
    {
        $workflow = app(LaasWorkflowService::class);
        $application = $workflow->findByFileNumber((string) $case->file_number);
        if (!$application || $application->isReadOnly()) {
            return null;
        }

        $def = new WorkflowDefinition();
        $phases = $def->has((string) $case->workflow) ? (array) ($def->workflow($case->workflow)['phases'] ?? []) : [];
        $from = $this->phaseOf($def, $case->workflow, $fromStep);
        $to   = $this->phaseOf($def, $case->workflow, $toStep);
        $meta = ['sms' => false, 'visible' => true, 'actor_type' => 'system'];

        // Opened: the portal's main tracker moves to "At Cadastral" (only ever forward).
        if ($fromStep === null) {
            $meta += ['title' => 'Received at Cadastral', 'body' => 'Your file has been received by the Cadastral Department.'];
            if ($application->canAdvanceTo(LaasApplication::STAGE_AT_CADASTRAL)) {
                $workflow->advance($application, LaasApplication::STAGE_AT_CADASTRAL, $meta);
            } else {
                app(LaasNotificationService::class)->record($application, LaasApplication::STAGE_AT_CADASTRAL, $meta);
            }

            return 'opened';
        }

        if ($toStep === 'registry_return') {
            $meta += ['title' => 'Returned by Cadastral', 'body' => 'Your file has been returned by the Cadastral Department. The office will contact you.'];
            $kind = 'returned';
        } elseif ($toStep === null && $case->status === CadastralCase::STATUS_RETURNED) {
            return null;   // the close of a returned file: the holder was told when it was sent back
        } elseif ($toStep === null) {
            $meta += ['title' => 'Cadastral work completed', 'body' => 'The Cadastral Department has completed its work on your file.'];
            $kind = 'completed';
        } elseif ($to && $to !== $from) {
            $label = self::publicPhase($phases[$to] ?? '');
            $meta += ['title' => 'Cadastral: ' . $label, 'body' => 'Your file has moved to "' . $label . '" in the Cadastral Department.'];
            $kind = 'phase';
        } else {
            return null;   // a move within the same phase: nothing new for the holder
        }

        app(LaasNotificationService::class)->record($application, LaasApplication::STAGE_AT_CADASTRAL, $meta);

        return $kind;
    }
}
