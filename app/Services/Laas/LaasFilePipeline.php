<?php

namespace App\Services\Laas;

use App\Models\CadastralDept\CadastralCase;
use App\Models\Laas\LaasApplication;
use App\Services\CadastralDept\WorkflowDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The portal's Progress pipeline, department by department, from the user's "LAAS Portal Flow"
 * document (2026-11-16). Each step is read live from the record that proves it (allocation list,
 * OP, recommendation, RofO, Land 12, For Information, the Cadastral case, Deeds bill balance,
 * recertification), never from a stored stage, so the portal agrees with the desks.
 *
 * Step states:
 *   done     its own record exists
 *   passed   no record of its own, but a later step on the line is done (e.g. a file that took the
 *            older route before the Cadastral workflow existed)
 *   current  the first open step after the furthest step reached
 *   todo     not reached yet
 *
 * KANGIS Recertification is its own line: it starts only when a recertification application exists
 * and never makes the main line look further along. Every file shows the whole document from Land:
 * Allocation List on, nothing added in front (user, 2026-11-16: "this was not what i sent").
 * Desks, officers and minutes are never shown (user, 2026-11-02).
 */
class LaasFilePipeline
{
    /** The OP steps: left grey for a file with no allocation-list entry and no OP. */
    private const OP_STEPS = ['allocation_list', 'issue_op', 'op_payment', 'register_op'];

    /** instrument_type of an Occupancy Permit, as OpLookup reads it. */
    private const OP = 'Occupancy Permit (OP)';

    /**
     * The document's departments and steps, in order. 'implied_by' marks a step with no record of
     * its own: it is done when that later step is done. 'indent' draws a sub-step.
     */
    public const GROUPS = [
        ['key' => 'land_op', 'dept' => 'Land', 'steps' => [
            'allocation_list' => ['label' => 'Allocation List'],
            'issue_op'        => ['label' => 'Issue OP', 'indent' => true],
            'op_payment'      => ['label' => 'Payment', 'implied_by' => 'register_op'],
        ]],
        ['key' => 'deeds_op', 'dept' => 'Deeds', 'steps' => [
            'register_op' => ['label' => 'Register OP'],
        ]],
        ['key' => 'land_oss', 'dept' => 'Land/OSS', 'steps' => [
            'payment'    => ['label' => 'Payment', 'implied_by' => 'commission'],
            'commission' => ['label' => 'Commission File'],
        ]],
        // "Add All the Cadastral Workflow here": the Cadastral workflow's phases before Planning Advice
        // (phases 1 and 2), in the portal wording of LaasCadastralProgress.
        ['key' => 'cadastral_intake', 'dept' => 'Cadastral', 'steps' => [
            'cad_phase_1' => ['label' => 'Received & under review', 'phase' => 1],
            'cad_phase_2' => ['label' => 'Acquisition & compensation check', 'phase' => 2],
        ]],
        ['key' => 'planning', 'dept' => 'KAMMA/Physical Planning', 'steps' => [
            'planning_recommendation' => ['label' => 'Planning Recommendation', 'phase' => 3],
        ]],
        ['key' => 'cadastral_correspondence', 'dept' => 'Cadastral', 'steps' => [
            'correspondence' => ['label' => 'Commissioning of Correspondence File'],
        ]],
        ['key' => 'land', 'dept' => 'Land', 'steps' => [
            'recommendation'  => ['label' => 'Recommendation'],
            'rofo'            => ['label' => 'RofO (Letter of Grant)'],
            'land12'          => ['label' => 'Land 12'],
            'for_information' => ['label' => 'For Information'],
        ]],
        ['key' => 'cadastral_charting', 'dept' => 'Cadastral', 'steps' => [
            'charting' => ['label' => 'Charting'],
        ]],
        ['key' => 'survey_plotting', 'dept' => 'Survey', 'steps' => [
            'plotting' => ['label' => 'Plotting'],
        ]],
        ['key' => 'cadastral_bill', 'dept' => 'Cadastral', 'steps' => [
            'pnd_bill' => ['label' => 'Fees, Plans and Description Bill'],
        ]],
        ['key' => 'survey_final_plan', 'dept' => 'Survey', 'steps' => [
            'final_survey_plan' => ['label' => 'Final Survey Plan'],
        ]],
        ['key' => 'cadastral_signing', 'dept' => 'Cadastral', 'steps' => [
            'sign_bill'          => ['label' => 'Signing of Fees, Plans and Description Bill'],
            'cadastral_archive'  => ['label' => 'Cadastral Archive'],
        ]],
        ['key' => 'land_archive', 'dept' => 'Land', 'steps' => [
            'land_archive' => ['label' => 'Archive', 'implied_by' => 'bill_balance'],
        ]],
        ['key' => 'deeds_bill', 'dept' => 'Deeds', 'steps' => [
            'bill_balance' => ['label' => 'Bill Balance'],
        ]],
        ['key' => 'kangis', 'dept' => 'KANGIS', 'title' => 'Recertification', 'separate' => true, 'steps' => [
            'recert_payment'      => ['label' => 'Payment', 'indent' => true],
            'recert_capture'      => ['label' => 'Capture', 'indent' => true],
            'recert_verification' => ['label' => 'Verification', 'indent' => true],
            'recert_geometry'     => ['label' => 'Geometry', 'indent' => true, 'implied_by' => 'recert_production'],
            'recert_production'   => ['label' => 'Production', 'indent' => true],
        ]],
    ];

    public function forApplication(LaasApplication $application): array
    {
        return $this->forApplications([$application])[$application->id];
    }

    /** Pipelines for several applications (the dashboard), keyed by application id. */
    public function forApplications(iterable $applications): array
    {
        $applications = collect($applications);
        // A transferred-out copy shows its old holder nothing live: the file now moves for someone else.
        $files = $applications->reject->isTransferredOut()
            ->map(fn ($a) => trim((string) $a->file_number))->filter()->unique()->values()->all();

        $evidence = $this->evidence($files);

        $out = [];
        foreach ($applications as $application) {
            $key = $application->isTransferredOut() ? null : self::key($application->file_number);
            $out[$application->id] = $this->build($application, $key ? ($evidence[$key] ?? []) : []);
        }

        return $out;
    }

    private function build(LaasApplication $application, array $ev): array
    {
        $rank = LaasApplication::rank($application->stage);
        $reached = fn (string $stage) => $rank >= LaasApplication::rank($stage);

        // The application's own stage is evidence too, where only the Recommendation / RofO hooks set it.
        // Not for Land 12: the stage line puts it before "At Cadastral", which the Cadastral case sets
        // without any Land 12, so only a survey_report_requests row counts.
        $done = $ev['done'] ?? [];
        $done['commission'] = ($done['commission'] ?? false) || $application->origin === LaasApplication::ORIGIN_COMMISSIONING
            || ($application->file_number && $reached(LaasApplication::STAGE_FILENO_ASSIGNED));
        $done['recommendation'] = ($done['recommendation'] ?? false) || $reached(LaasApplication::STAGE_RECOMMENDATION_APPROVED);
        $done['rofo'] = ($done['rofo'] ?? false) || $reached(LaasApplication::STAGE_ROFO_GENERATED);

        // Exactly the document's groups for every file: no "Application" group in front and the
        // Land OP / Deeds Register OP steps always shown (user, 2026-11-16).
        $groups = self::GROUPS;
        // A file with no allocation-list entry and no OP leaves those steps grey, as not reached, with no
        // label added (user, 2026-11-16): never ticked or current, and not counted in the percentage.
        $noOp = empty($ev['has_op']) && !array_filter(array_intersect_key($done, array_flip(self::OP_STEPS)));

        foreach ($groups as $g) {
            foreach ($g['steps'] as $s => $def) {
                if (!empty($def['implied_by']) && !empty($done[$def['implied_by']])) {
                    $done[$s] = true;
                }
            }
        }

        $current = $ev['current'] ?? [];   // the Cadastral step the case is on now

        // Lay out both lines: the main line, then the separate one(s).
        $rows = [];
        foreach ([false, true] as $separate) {
            $line = [];
            foreach ($groups as $gi => $g) {
                if (!empty($g['separate']) !== $separate) {
                    continue;
                }
                foreach ($g['steps'] as $s => $def) {
                    if ($noOp && in_array($s, self::OP_STEPS, true)) {
                        $rows[$gi][] = ['key' => $s, 'label' => $def['label'], 'indent' => !empty($def['indent']), 'state' => 'todo', 'no_op' => true];
                        continue;
                    }
                    $line[] = [$gi, $s, $def];
                }
            }

            $furthest = -1;
            foreach ($line as $i => [, $s]) {
                if (!empty($done[$s])) {
                    $furthest = $i;
                }
            }
            // A separate line opens only once it has started.
            $openAt = null;
            if (!$separate || $furthest >= 0) {
                foreach ($line as $i => [, $s]) {
                    if (!empty($current[$s]) && empty($done[$s])) {
                        $openAt = $i;
                        break;
                    }
                }
                if ($openAt === null && $furthest + 1 < count($line)) {
                    $openAt = $furthest + 1;
                }
            }
            // Steps behind the open one with no record were passed; a live Cadastral case that is
            // behind an older record (e.g. a RofO from before the workflow) keeps the steps ahead of it open.
            $passedBefore = $openAt === null ? $furthest : min($openAt, $furthest);

            foreach ($line as $i => [$gi, $s, $def]) {
                $state = !empty($done[$s]) ? 'done' : ($i === $openAt ? 'current' : ($i < $passedBefore ? 'passed' : 'todo'));
                $rows[$gi][] = ['key' => $s, 'label' => $def['label'], 'indent' => !empty($def['indent']), 'state' => $state];
            }
        }

        $out = [];
        $main = 0;
        $mainDone = 0;
        $currentLabel = null;
        foreach ($groups as $gi => $g) {
            $steps = $rows[$gi] ?? [];
            foreach ($steps as $st) {
                if (empty($g['separate']) && empty($st['no_op'])) {
                    $main++;
                    $mainDone += in_array($st['state'], ['done', 'passed'], true) ? 1 : 0;
                    if ($st['state'] === 'current' && $currentLabel === null) {
                        $currentLabel = $g['dept'] . ': ' . $st['label'];
                    }
                }
            }
            $states = array_column($steps, 'state');
            $out[] = [
                'key'      => $g['key'],
                'dept'     => $g['dept'],
                'title'    => $g['title'] ?? null,
                'separate' => !empty($g['separate']),
                'state'    => in_array('current', $states, true) ? 'current'
                    : (count(array_diff($states, ['done', 'passed'])) === 0 ? 'done' : 'todo'),
                'steps'    => $steps,
            ];
        }

        return [
            'groups'   => $out,
            'current'  => $currentLabel,
            'complete' => $main > 0 && $mainDone === $main,
            'percent'  => $main ? (int) round($mainDone / $main * 100) : 0,
            'returned' => !empty($ev['returned']),
        ];
    }

    /**
     * What the records say about each file, keyed by upper-cased file number:
     *   done[step] => true, current[step] => true (the Cadastral case is on it), has_op, returned.
     * Read-only. Any source that fails is skipped and logged, never thrown.
     */
    private function evidence(array $files): array
    {
        $ev = [];
        if (!$files) {
            return $ev;
        }
        $mark = function (?string $file, string $step, string $what = 'done') use (&$ev) {
            $k = self::key($file);
            if ($k !== '') {
                $ev[$k][$what][$step] = true;
            }
        };
        $db = DB::connection('sqlsrv');
        $read = function (string $source, callable $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::warning('LAAS pipeline: ' . $source . ' could not be read', ['error' => $e->getMessage()]);
            }
        };

        // The whole list goes as one JSON parameter, one query per file-number column: an OR across
        // the columns makes SQL Server scan pra (185k rows) once per value (a holder can have 1,500 files).
        $json = json_encode(array_values($files));
        $wanted = array_flip(array_map([self::class, 'key'], $files));
        $rowsIn = function (string $table, array $columns, array $select, ?callable $scope = null) use ($db, $json) {
            $rows = collect();
            foreach ($columns as $col) {
                $q = $db->table($table)->whereRaw("[{$col}] in (select [value] from openjson(?))", [$json]);
                if ($scope) {
                    $scope($q);
                }
                $rows = $rows->concat($q->get($select));
            }

            return $rows;
        };
        $notDeleted = fn ($q) => $q->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));
        // Each matching file number of a row that carries several.
        $hits = fn (object $r, array $cols) => array_filter(
            array_unique(array_map(fn ($c) => self::key($r->{$c}), $cols)),
            fn ($k) => $k !== '' && isset($wanted[$k])
        );

        $read('allocation list', function () use ($rowsIn, $mark, &$ev) {
            foreach ($rowsIn('allocation_list_stage', ['file_no'], ['file_no']) as $r) {
                $mark($r->file_no, 'allocation_list');
                $ev[self::key($r->file_no)]['has_op'] = true;
            }
        });

        // OPs: hand-captured (instrument_capture) and Deeds (pra), as OpLookup reads them.
        $read('OPs', function () use ($rowsIn, $notDeleted, $hits, $mark, &$ev) {
            $captureCols = ['mlsFNo', 'temp_fileno'];
            $capture = $rowsIn('instrument_capture', $captureCols, [...$captureCols, 'registration_number'],
                fn ($q) => $notDeleted($q->where('instrument_type', self::OP)));
            $praCols = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno'];
            $pra = $rowsIn('pra', $praCols, [...$praCols, 'regNo'],
                fn ($q) => $notDeleted($q->where('instrument_type', self::OP)->whereNull('instrument_capture_id')));

            $rows = $capture->map(fn ($r) => [$hits($r, $captureCols), $r->registration_number])
                ->concat($pra->map(fn ($r) => [$hits($r, $praCols), $r->regNo]));
            foreach ($rows as [$keys, $regNo]) {
                foreach ($keys as $k) {
                    $mark($k, 'issue_op');
                    $ev[$k]['has_op'] = true;
                    if (trim((string) $regNo) !== '') {
                        $mark($k, 'register_op');
                    }
                }
            }
        });

        $read('recommendations', function () use ($rowsIn, $notDeleted, $mark) {
            foreach ($rowsIn('land_recommendations', ['file_number'], ['file_number', 'rofo_status'], $notDeleted) as $r) {
                $mark($r->file_number, 'recommendation');
                if ($r->rofo_status === 'generated') {
                    $mark($r->file_number, 'rofo');
                }
            }
        });

        foreach (['survey_report_requests' => 'land12', 'for_information' => 'for_information', 'deeds_bill_balances_metadata' => 'bill_balance'] as $table => $step) {
            $read($table, function () use ($rowsIn, $mark, $table, $step) {
                foreach ($rowsIn($table, ['file_number'], ['file_number']) as $r) {
                    $mark($r->file_number, $step);
                }
            });
        }

        $read('recertification', function () use ($rowsIn, $notDeleted, $hits, $mark) {
            $cols = ['file_number', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileno'];
            $rows = $rowsIn('recertification_applications', $cols,
                [...$cols, 'receipt_no', 'payment_date', 'verification', 'verification_date', 'certificate_generated'], $notDeleted);
            foreach ($rows as $r) {
                foreach ($hits($r, $cols) as $k) {
                    $mark($k, 'recert_capture');
                    if (trim((string) $r->receipt_no) !== '' || $r->payment_date) {
                        $mark($k, 'recert_payment');
                    }
                    if (trim((string) $r->verification) !== '' || $r->verification_date) {
                        $mark($k, 'recert_verification');
                    }
                    if (!empty($r->certificate_generated)) {
                        $mark($k, 'recert_production');
                    }
                }
            }
        });

        $read('Cadastral', function () use ($files, $mark, &$ev) {
            if (!LaasCadastralProgress::installed()) {
                return;
            }
            $this->cadastralEvidence($files, $mark, $ev);
        });

        return $ev;
    }

    /** The Cadastral case's position, mapped onto the pipeline's Cadastral, Planning, Survey and Land steps. */
    private function cadastralEvidence(array $files, callable $mark, array &$ev): void
    {
        $cases = collect();
        foreach (array_chunk($files, 1000) as $chunk) {
            $cases = $cases->concat(CadastralCase::query()->whereIn('file_number', $chunk)->orderBy('id')->get());
        }
        if ($cases->isEmpty()) {
            return;
        }
        $latest = $cases->keyBy(fn ($c) => self::key($c->file_number));   // the latest case of each file wins
        $ids = $latest->pluck('id')->all();
        $db = DB::connection('sqlsrv');

        $moves = $db->table('cadastral_case_movements')->whereIn('cadastral_case_id', $ids)->whereNotNull('to_step')
            ->get(['cadastral_case_id', 'to_step'])->groupBy('cadastral_case_id');
        $tasks = $db->table('cadastral_case_tasks')->whereIn('cadastral_case_id', $ids)
            ->get(['cadastral_case_id', 'task_key'])->groupBy('cadastral_case_id');
        $charted = $db->table('cadastral_chartings')->whereIn('cadastral_case_id', $ids)
            ->where('is_current', 1)->where('charted', 1)->whereIn('stage', ['second', 'final'])
            ->pluck('cadastral_case_id')->flip();

        $def = new WorkflowDefinition();
        foreach ($latest as $k => $case) {
            $wf = (string) $case->workflow;
            if (!$def->has($wf)) {
                continue;
            }
            $phaseOf = fn (?string $step) => $step && $def->hasStep($wf, $step) ? (int) ($def->step($wf, $step)['phase'] ?? 0) : 0;

            $completed = $case->status === CadastralCase::STATUS_COMPLETED;
            $returned = $case->status === CadastralCase::STATUS_RETURNED || $case->current_step === 'registry_return';
            $reachedSteps = $moves->get($case->id, collect())->pluck('to_step')->push($case->current_step)->filter()->unique()->all();
            $reached = fn (string $step) => in_array($step, $reachedSteps, true);
            $passed = fn (string $step) => $reached($step) && ($completed || $case->current_step !== $step);
            $taskDone = fn (string $t) => $tasks->get($case->id, collect())->contains('task_key', $t);
            $currentPhase = $completed ? 99 : $phaseOf($case->current_step);
            $ev[$k]['returned'] = $returned;

            // Phases 1-3: done once the case is past them; current while it is in them.
            foreach (['cad_phase_1' => 1, 'cad_phase_2' => 2, 'planning_recommendation' => 3] as $step => $n) {
                if ($currentPhase > $n) {
                    $mark($k, $step);
                } elseif ($currentPhase === $n && !$returned) {
                    $mark($k, $step, 'current');
                }
            }

            if ($taskDone('correspondence_created') || $reached('land_processing') || $passed('registry_to_land')) {
                $mark($k, 'correspondence');
            } elseif ($case->current_step === 'registry_to_land') {
                $mark($k, 'correspondence', 'current');
            }

            if ($passed('land_processing')) {
                $mark($k, 'recommendation');
                $mark($k, 'rofo');
            }

            // Charting: the Direct Allocation 2nd charting or the Final Charting card.
            if ($charted->has($case->id) || $passed('final_charting')) {
                $mark($k, 'charting');
            } elseif ($case->current_step === 'final_charting') {
                $mark($k, 'charting', 'current');
            }

            foreach ([
                'plotting'          => ['field_survey'],
                'pnd_bill'          => ['pnd_billing'],
                'final_survey_plan' => ['sg_signing'],
                'sign_bill'         => ['info_sign_bill'],
                'cadastral_archive' => ['registry_dispatch'],
            ] as $step => $wfSteps) {
                foreach ($wfSteps as $s) {
                    if ($passed($s) || ($step === 'pnd_bill' && $taskDone('bill_generated'))) {
                        $mark($k, $step);
                    } elseif ($case->current_step === $s && !$returned) {
                        $mark($k, $step, 'current');
                    }
                }
            }
            // A private surveyor's plan skips the SG: it is final once AD Cad Info receives it.
            if ($reached('info_after_sg')) {
                $mark($k, 'final_survey_plan');
            }
        }
    }

    public static function key(?string $no): string
    {
        return mb_strtoupper(trim((string) $no));
    }
}
