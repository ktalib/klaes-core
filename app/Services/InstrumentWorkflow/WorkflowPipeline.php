<?php

namespace App\Services\InstrumentWorkflow;

use App\Models\InstrumentWorkflow\InstrumentApplication as IA;
use App\Models\InstrumentWorkflow\InstrumentApplicationCheck as Check;
use App\Models\InstrumentWorkflow\WorkflowStep;
use Illuminate\Support\Facades\Schema;

/**
 * The configurable part of the Instrument Registration Workflow: the ministry
 * checks between the application fee receipt and the LIC.
 *
 * System Admin → Configurable Entries sets, per check (Lands Registry, Survey,
 * Physical Planning): its order, whether it is on, its name and its department.
 * Each check keeps its own stage (lands_check, survey_check, planning_check) and
 * its own screen (the application page, Survey → Approvals, PP Director →
 * Instrument Registration Approval) whatever its position.
 *
 * An application moves to the first check that is ON and not yet passed, in the
 * configured order; when none is left, the LIC is generated. Checks that are OFF
 * are recorded as "skipped" when the LIC is generated, so the history shows they
 * were not done rather than silently missing.
 */
class WorkflowPipeline
{
    public const DEFAULTS = [
        Check::TYPE_LANDS => ['label' => 'Lands Registry check', 'department' => 'Lands Registry', 'sort_order' => 1],
        Check::TYPE_SURVEY => ['label' => 'Survey — free from Government Acquisition', 'department' => 'Survey', 'sort_order' => 2],
        Check::TYPE_PLANNING => ['label' => 'Physical Planning check', 'department' => 'Physical Planning', 'sort_order' => 3],
    ];

    public const STAGES = [
        Check::TYPE_LANDS => IA::STAGE_LANDS_CHECK,
        Check::TYPE_SURVEY => IA::STAGE_SURVEY_CHECK,
        Check::TYPE_PLANNING => IA::STAGE_PLANNING_CHECK,
    ];

    /** Where each check is recorded; fixed, because each department has its own screen. */
    public const SCREENS = [
        Check::TYPE_LANDS => 'Land → the application page',
        Check::TYPE_SURVEY => 'Survey → Approvals',
        Check::TYPE_PLANNING => 'Physical Planning → PP Director → Instrument Registration Approval',
    ];

    /** @var array<string, array{key: string, label: string, department: string, sort_order: int, enabled: bool, stage: string, screen: string}>|null */
    private static ?array $cache = null;

    /** The checks in their configured order. */
    public function steps(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = [];
        try {
            if (Schema::connection('sqlsrv')->hasTable('instrument_workflow_steps')) {
                $rows = WorkflowStep::query()->get()->keyBy('step_key');
            }
        } catch (\Throwable $e) {
            $rows = [];
        }

        $steps = [];
        foreach (self::DEFAULTS as $key => $default) {
            $row = $rows[$key] ?? null;
            $steps[$key] = [
                'key' => $key,
                'label' => $row?->label ?: $default['label'],
                'department' => $row?->department ?: $default['department'],
                'sort_order' => $row ? (int) $row->sort_order : $default['sort_order'],
                'enabled' => $row ? (bool) $row->is_enabled : true,
                'stage' => self::STAGES[$key],
                'screen' => self::SCREENS[$key],
            ];
        }

        uasort($steps, fn ($a, $b) => [$a['sort_order'], array_search($a['key'], array_keys(self::DEFAULTS), true)]
            <=> [$b['sort_order'], array_search($b['key'], array_keys(self::DEFAULTS), true)]);

        return self::$cache = $steps;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public function step(string $key): ?array
    {
        return $this->steps()[$key] ?? null;
    }

    public function label(string $key): string
    {
        return $this->step($key)['label'] ?? $key;
    }

    public function department(string $key): string
    {
        return $this->step($key)['department'] ?? $key;
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->step($key)['enabled'] ?? true);
    }

    public function typeForStage(string $stage): ?string
    {
        $type = array_search($stage, self::STAGES, true);

        return $type === false ? null : $type;
    }

    /** IA::ORDER with the three check stages in the configured order. */
    public function order(): array
    {
        $checks = array_column($this->steps(), 'stage');
        $order = [];
        $inserted = false;

        foreach (IA::ORDER as $stage) {
            if (in_array($stage, self::STAGES, true)) {
                if (!$inserted) {
                    array_push($order, ...$checks);
                    $inserted = true;
                }
                continue;
            }
            $order[] = $stage;
        }

        return $order;
    }

    /**
     * Where the application goes next: the first check that is ON and whose latest
     * result is not "passed", in the configured order; the LIC when none is left.
     */
    public function nextStage(IA $application): string
    {
        $latest = Check::query()
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->get(['check_type', 'outcome'])
            ->unique('check_type')
            ->pluck('outcome', 'check_type');

        foreach ($this->steps() as $step) {
            if ($step['enabled'] && ($latest[$step['key']] ?? null) !== Check::OUTCOME_PASSED) {
                return $step['stage'];
            }
        }

        return IA::STAGE_LIC_ISSUED;
    }

    /** Checks that are ON and have not passed, for the LIC guard. */
    public function outstandingChecks(IA $application): array
    {
        $latest = Check::query()
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->get(['check_type', 'outcome'])
            ->unique('check_type')
            ->pluck('outcome', 'check_type');

        return array_values(array_filter(
            $this->steps(),
            fn ($step) => $step['enabled'] && ($latest[$step['key']] ?? null) !== Check::OUTCOME_PASSED
        ));
    }

    /**
     * Save order, on/off, names and departments.
     *
     * @param array<string, array{label?: string, department?: string, sort_order?: int, enabled?: bool}> $input
     */
    public function save(array $input, ?string $userName): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            $values = $input[$key] ?? [];
            WorkflowStep::query()->updateOrCreate(['step_key' => $key], [
                'label' => trim((string) ($values['label'] ?? '')) ?: $default['label'],
                'department' => trim((string) ($values['department'] ?? '')) ?: $default['department'],
                'sort_order' => (int) ($values['sort_order'] ?? $default['sort_order']),
                'is_enabled' => (bool) ($values['enabled'] ?? true),
                'updated_by_name' => $userName,
            ]);
        }

        self::flush();
    }
}

