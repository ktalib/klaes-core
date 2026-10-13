<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCaseTask;
use App\Models\CadastralDept\CadastralCharting;
use App\Models\CadastralDept\CadastralChartingBeacon;
use App\Services\AuditService;
use App\Services\Cadastral\AreaCalculator;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The charting card (step 8), built as drafted in
 * docs/plans/CADASTRAL_CHARTING_CARD_DRAFT.md. Charting on the sheet stays
 * outside KLAES; the card records the result and ticks the step's charting task.
 *
 * Defaults used for the client's open questions (user, 2026-10-10: "go with
 * what we have for now"): a file that falls on an existing plot, or cannot be
 * charted, still moves on, flagged on the card and the file page; the 2nd
 * charting shows the 1st and can correct it; coordinates are UTM Zone 32N on
 * the Minna datum; numbers are CH-YYYY-NNNNN; the card prints.
 */
class ChartingCards
{
    private const CONN = 'sqlsrv';

    /** Step task => card stage. */
    public const TASK_STAGE = [
        'charted' => 'single',
        'chart_first' => 'first',
        'chart_second' => 'second',
        'charting_checked' => 'check',
    ];

    public const NOT_CHARTED_REASONS = [
        'Survey plan missing or unreadable',
        'Coordinates incomplete',
        'Sheet not available',
    ];

    public function __construct(
        private WorkflowDefinition $def,
        private AuditService $audit,
        private AreaCalculator $area
    ) {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_chartings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The charting task of the case's current step, if it has one.
     *
     * @return array{task:string, stage:string}|null
     */
    public function due(CadastralCase $case): ?array
    {
        if (!$case->isOpen()) {
            return null;
        }
        $tasks = (array) ($this->def->step($case->workflow, $case->current_step)['tasks'] ?? []);
        foreach (self::TASK_STAGE as $task => $stage) {
            if (isset($tasks[$task])) {
                return ['task' => $task, 'stage' => $stage];
            }
        }

        return null;
    }

    /** The current card for a stage (and, when given, the step it was made at). */
    public function current(CadastralCase $case, string $stage, ?string $step = null): ?CadastralCharting
    {
        return CadastralCharting::query()->with('beacons')
            ->where('cadastral_case_id', $case->id)->where('stage', $stage)->where('is_current', true)
            ->when($step, fn ($q) => $q->where('step', $step))
            ->orderByDesc('id')->first();
    }

    /**
     * Other files charted on the same plot in the same district: this
     * workflow's cards and the old module's charts. A suggestion for the
     * officer to confirm on the sheet, never a decision.
     *
     * @return string[]
     */
    public function suggestions(CadastralCase $case): array
    {
        $plot = trim((string) ($case->plot_no ?? ''));
        $district = trim((string) ($case->district ?? ''));
        // Placeholder "plots" match everything and mean nothing.
        if ($plot === '' || preg_match('/piece of land|farm ?land|unknown|^n\/?a$/i', $plot)) {
            return [];
        }
        $db = DB::connection(self::CONN);

        $out = $db->table('cadastral_chartings as ch')
            ->join('cadastral_cases as c', 'c.id', '=', 'ch.cadastral_case_id')
            ->where('ch.is_current', true)
            ->where('c.plot_no', $plot)
            ->when($district !== '', fn ($q) => $q->where('c.district', $district))
            ->where('c.file_number', '!=', $case->file_number)
            ->limit(10)->pluck('c.file_number')->all();

        if (Schema::connection(self::CONN)->hasTable('cadastral_charts')) {
            $out = array_merge($out, $db->table('cadastral_charts')
                ->where('is_current', 1)->whereNull('deleted_at')
                ->where('plot_no', $plot)
                ->when($district !== '', fn ($q) => $q->where('prop_district', $district))
                ->where('file_number', '!=', $case->file_number)
                ->limit(10)->pluck('file_number')->all());
        }

        return array_values(array_unique($out));
    }

    /** Save the card for the case's current charting step, as a new version if one exists. */
    public function save(CadastralCase $case, array $in, ?int $userId): CadastralCharting
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The charting tables have not been created yet.');
        }
        $due = $this->due($case);
        if (!$due) {
            throw WorkflowGuardException::because("{$case->case_ref} is not at a charting step.");
        }
        $values = $this->validated($due['stage'], $in);
        $beacons = $this->beacons($in['beacons'] ?? []);
        $values['area_sqm'] = count($beacons) >= 3 ? $this->area->areaSqm($beacons) : null;

        return DB::connection(self::CONN)->transaction(function () use ($case, $due, $values, $beacons, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($this->due($case) != $due) {
                throw WorkflowGuardException::because('The file has moved on; reload the page.');
            }

            // Versions run per step: Verification checks at two desks, two cards.
            $prev = CadastralCharting::query()->where('cadastral_case_id', $case->id)
                ->where('stage', $due['stage'])->where('step', $case->current_step)
                ->where('is_current', true)->lockForUpdate()->first();
            if ($prev) {
                $prev->update(['is_current' => false]);
            }

            $now = now();
            $card = CadastralCharting::create($values + [
                'cadastral_case_id' => $case->id,
                'charting_no' => $prev ? $prev->charting_no : $this->nextNo($now->year),
                'stage' => $due['stage'],
                'step' => $case->current_step,
                'version' => $prev ? $prev->version + 1 : 1,
                'supersedes_id' => $prev?->id,
                'is_current' => true,
                'charted_by' => $userId,
                'charted_at' => $now,
            ]);
            foreach ($beacons as $i => $b) {
                CadastralChartingBeacon::create($b + ['cadastral_charting_id' => $card->id, 'sort_order' => $i + 1]);
            }

            $ticked = CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', $due['task'])->exists();
            if (!$ticked) {
                CadastralCaseTask::create([
                    'cadastral_case_id' => $case->id,
                    'step' => $case->current_step,
                    'task_key' => $due['task'],
                    'note' => $card->charting_no,
                    'done_by' => $userId,
                    'done_at' => $now,
                ]);
            }

            $this->audit->logAction($prev ? 'Cadastral Charting Corrected' : 'Cadastral Charting Saved', 'CadastralCharting', $card->id,
                $prev ? ['id' => $prev->id, 'version' => $prev->version] : null,
                $card->only(['charting_no', 'stage', 'version', 'charted', 'falls_on_existing']),
                "{$card->charting_no} v{$card->version} for {$case->case_ref}");

            return $card;
        });
    }

    /** The card's answers, checked per stage. */
    private function validated(string $stage, array $in): array
    {
        $bool = fn ($k) => isset($in[$k]) && $in[$k] !== '' ? in_array($in[$k], ['1', 1, true, 'yes'], true) : null;
        $v = [
            'description' => Normalise::text($in['description'] ?? null) ?: null,
            'sheet_type' => in_array($in['sheet_type'] ?? null, ['metric', 'imperial'], true) ? $in['sheet_type'] : null,
            'sheet_no' => Normalise::code($in['sheet_no'] ?? null),
            'layout' => Normalise::text($in['layout'] ?? null) ?: null,
            'plan_no' => Normalise::code($in['plan_no'] ?? null),
            'remarks' => Normalise::text($in['remarks'] ?? null) ?: null,
        ];

        // A Metric sheet comes from the metric_sheets lookup, kept in its own spelling. "Other"
        // keeps what was specified (still matched to the list, in case it is one of them).
        if ($v['sheet_type'] === 'metric' && MetricSheets::installed()) {
            $other = ($in['sheet_other'] ?? '') === '1';
            if ($other && $v['sheet_no'] === null) {
                throw WorkflowGuardException::because('Specify the sheet: Other was chosen.');
            }
            if ($v['sheet_no'] !== null) {
                $known = app(MetricSheets::class)->canonical($in['sheet_no'] ?? null);
                if ($known === null && !$other) {
                    throw WorkflowGuardException::because('Pick the Metric sheet from the list, or choose Other and specify it.');
                }
                $v['sheet_no'] = $known ?? $v['sheet_no'];
            }
        }

        if ($stage === 'check') {
            $v['matches_record'] = $bool('matches_record');
            if ($v['matches_record'] === null) {
                throw WorkflowGuardException::because('Say whether the charting information matches the record.');
            }
            $v['match_note'] = Normalise::text($in['match_note'] ?? null) ?: null;
            if ($v['matches_record'] === false && !$v['match_note']) {
                throw WorkflowGuardException::because('Say what does not match.');
            }

            return $v;
        }

        if ($stage === 'second') {
            $v['agrees_with_first'] = $bool('agrees_with_first');
            if ($v['agrees_with_first'] === null) {
                throw WorkflowGuardException::because('Say whether it agrees with the 1st charting.');
            }
            $v['agree_note'] = Normalise::text($in['agree_note'] ?? null) ?: null;
            if ($v['agrees_with_first'] === false && !$v['agree_note']) {
                throw WorkflowGuardException::because('Say what is different from the 1st charting.');
            }
        }

        $v['charted'] = $bool('charted');
        if ($v['charted'] === null) {
            throw WorkflowGuardException::because('Say whether the record was charted.');
        }
        if ($v['charted'] === false) {
            $reason = ($in['not_charted_reason'] ?? '') === 'other'
                ? Normalise::text($in['not_charted_other'] ?? null)
                : (in_array($in['not_charted_reason'] ?? '', self::NOT_CHARTED_REASONS, true) ? $in['not_charted_reason'] : '');
            if ($reason === '') {
                throw WorkflowGuardException::because('Say why the record was not charted (specify it for Other).');
            }
            $v['not_charted_reason'] = $reason;
        }

        $v['falls_on_existing'] = $bool('falls_on_existing');
        if ($v['charted'] && $v['falls_on_existing'] === null) {
            throw WorkflowGuardException::because('Say whether it falls on an existing plot.');
        }
        if ($v['falls_on_existing']) {
            $files = array_values(array_unique(array_filter(array_map(fn ($f) => trim((string) $f), (array) ($in['existing_files'] ?? [])))));
            if (!$files) {
                throw WorkflowGuardException::because('Pick the file(s) it falls on with the file number selector.');
            }
            $v['existing_files'] = mb_substr(implode(', ', $files), 0, 500);
            $v['overlap_extent'] = in_array($in['overlap_extent'] ?? null, ['fully', 'partly'], true) ? $in['overlap_extent'] : null;
        }

        return $v;
    }

    /** Beacon rows with both coordinates; blank rows dropped. */
    private function beacons(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $e = str_replace([' ', ','], '', (string) ($r['easting'] ?? ''));
            $n = str_replace([' ', ','], '', (string) ($r['northing'] ?? ''));
            if ($e === '' && $n === '') {
                continue;
            }
            if (!is_numeric($e) || !is_numeric($n)) {
                throw WorkflowGuardException::because('Each beacon needs a numeric easting and northing.');
            }
            $out[] = [
                'beacon' => Normalise::code($r['beacon'] ?? null),
                'easting' => (float) $e,
                'northing' => (float) $n,
                'remarks' => Normalise::text($r['remarks'] ?? null) ?: null,
            ];
        }

        return $out;
    }

    /** CH-2026-00001. Caller holds the transaction. */
    private function nextNo(int $year): string
    {
        $prefix = "CH-{$year}-";
        $last = CadastralCharting::query()->where('charting_no', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('id')->value('charting_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
