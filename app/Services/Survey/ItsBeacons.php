<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The beacons on an Instruction to Survey (CR 03112026 item 11, Survey and Cadastral). A beacon must not
 * have been issued to another surveyor, and goes on one job only (recording: "so that they won't go and
 * use the same beacon for somewhere else"). It must also be in the Beacon Register, except on the two forms
 * with Other (I to S, Computation File): that rule is off there "for now" (user, 2026-11-06). The form sends
 * saved numbers back as picks, so the rule is off for the whole form, not only the Other box.
 */
class ItsBeacons
{
    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** @return array<int, string> the job's numbers, as they read on screen */
    public function forSurveyIts(int $id): array
    {
        return $this->list('survey_its_id', $id);
    }

    /** @return array<int, string> */
    public function forCadastralJob(int $id): array
    {
        return $this->list('cadastral_survey_job_id', $id);
    }

    /** @return array<int, string> the numbers saved on the Computation File itself */
    public function forComputationFile(int $id): array
    {
        return $this->list('survey_computation_file_id', $id);
    }

    private function list(string $column, int $id): array
    {
        return $this->db()->table('survey_its_beacons')->where($column, $id)
            ->orderBy('beacon_series')->orderBy('beacon_seq')->pluck('beacon_no')
            ->map(fn ($n) => SurveyBeaconRegister::display($n))->all();
    }

    /**
     * The problems with these numbers for this job, as one list of sentences.
     *
     * @param array<int, array{no:string, series:string, seq:int}> $numbers
     * @return array<int, string>
     */
    public function problems(array $numbers, ?string $surveyor, string $column, ?int $id, bool $mustBeInRegister = true): array
    {
        if (! $numbers) return [];

        $out = [];
        $mine = $this->norm((string) $surveyor);
        foreach (collect($numbers)->groupBy('series') as $series => $rows) {
            $seqs = $rows->pluck('seq')->all();
            $issued = $this->db()->table('survey_beacon_numbers as n')
                ->join('survey_beacon_register as r', 'r.id', '=', 'n.survey_beacon_register_id')
                ->whereNull('n.deleted_at')->whereNull('r.deleted_at')
                ->where('n.beacon_series', $series)->whereIn('n.beacon_seq', $seqs)
                ->pluck('r.surveyor_name', 'n.beacon_seq');
            $used = $this->db()->table('survey_its_beacons')->where('beacon_series', $series)->whereIn('beacon_seq', $seqs)
                ->when($id, fn ($q) => $q->where(fn ($w) => $w->whereNull($column)->orWhere($column, '<>', $id)))
                ->get(['beacon_seq', 'survey_its_id', 'cadastral_survey_job_id', 'survey_computation_file_id'])->keyBy('beacon_seq');

            foreach ($rows as $n) {
                $label = SurveyBeaconRegister::display($n['no']);
                if (! $issued->has($n['seq']) && $mustBeInRegister) {
                    $out[] = "{$label} is not in the Beacon Register.";
                    continue;
                }
                // A register line naming no surveyor (most of the book) cannot contradict the job.
                $holder = (string) $issued->get($n['seq']);
                if ($mine !== '' && $this->norm($holder) !== '' && $this->norm($holder) !== $mine) {
                    $out[] = "{$label} was issued to {$holder}.";
                }
                if ($used->has($n['seq'])) {
                    $out[] = "{$label} is already on " . $this->jobName($used->get($n['seq'])) . '.';
                }
            }
        }

        return $out;
    }

    /**
     * Replace a job's beacons with the numbers typed, after checking them. Throws a validation message
     * on the field the form uses. Run inside the save's transaction.
     */
    public function save(?string $text, ?string $surveyor, string $column, int $id, string $field = 'beacon_numbers', bool $mustBeInRegister = true): void
    {
        $this->store($this->read($text, $field), $surveyor, $column, $id, $field, $mustBeInRegister);
    }

    /**
     * The Computation File's beacons (user, 2026-11-06). A number already on its own I to S is the same job:
     * it stays on the I to S, and a beacon goes on one job only.
     */
    public function saveForComputationFile(?string $text, ?string $surveyor, int $id, ?int $itsId, string $field = 'beacon_picks'): void
    {
        $numbers = $this->read($text, $field);
        if ($itsId) {
            $onIts = $this->db()->table('survey_its_beacons')->where('survey_its_id', $itsId)->get(['beacon_series', 'beacon_seq'])
                ->map(fn ($b) => $b->beacon_series . '#' . $b->beacon_seq)->all();
            $numbers = array_values(array_filter($numbers, fn ($n) => ! in_array($n['series'] . '#' . $n['seq'], $onIts, true)));
        }
        $this->store($numbers, $surveyor, 'survey_computation_file_id', $id, $field, false);
    }

    /**
     * Register numbers free for this job, for the searchable pick: not on any other job, and not issued to
     * another surveyor. A line naming no surveyor (most of the book) counts as free.
     *
     * @return array<int, string> the numbers as they read on screen
     */
    public function available(string $term, ?string $surveyor, string $column, ?int $id, int $limit = 30): array
    {
        $code = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], preg_replace('/\s+/', '', mb_strtoupper($term)));
        $mine = $this->norm((string) $surveyor);

        return $this->db()->table('survey_beacon_numbers as n')
            ->join('survey_beacon_register as r', 'r.id', '=', 'n.survey_beacon_register_id')
            ->whereNull('n.deleted_at')->whereNull('r.deleted_at')
            ->when($code !== '', fn ($q) => $q->where('n.beacon_no', 'like', '%' . $code . '%'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('survey_its_beacons as b')
                ->whereColumn('b.beacon_series', 'n.beacon_series')->whereColumn('b.beacon_seq', 'n.beacon_seq')
                ->when($id, fn ($w) => $w->where(fn ($x) => $x->whereNull('b.' . $column)->orWhere('b.' . $column, '<>', $id))))
            ->orderBy('n.beacon_series')->orderBy('n.beacon_seq')->limit(500)->get(['n.beacon_no', 'r.surveyor_name'])
            ->filter(fn ($b) => $mine === '' || $this->norm((string) $b->surveyor_name) === '' || $this->norm((string) $b->surveyor_name) === $mine)
            ->take($limit)->map(fn ($b) => SurveyBeaconRegister::display($b->beacon_no))->values()->all();
    }

    /** @param array<int, array{no:string, series:string, seq:int}> $numbers */
    private function store(array $numbers, ?string $surveyor, string $column, int $id, string $field, bool $mustBeInRegister = true): void
    {
        if ($problems = $this->problems($numbers, $surveyor, $column, $id, $mustBeInRegister)) {
            throw ValidationException::withMessages([$field => implode(' ', array_slice($problems, 0, 6)) . (count($problems) > 6 ? ' …' : '')]);
        }

        $this->db()->table('survey_its_beacons')->where($column, $id)->delete();
        $now = now();
        foreach (array_chunk($numbers, 200) as $chunk) {
            $this->db()->table('survey_its_beacons')->insert(array_map(fn ($n) => [
                $column => $id, 'beacon_no' => $n['no'], 'beacon_series' => $n['series'], 'beacon_seq' => $n['seq'],
                'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }
    }

    /** Add numbers issued against a Cadastral request to its job. Already there is not an error. */
    public function attach(array $numbers, string $column, int $id): void
    {
        $now = now();
        foreach ($numbers as $n) {
            $there = $this->db()->table('survey_its_beacons')->where('beacon_series', $n['series'])->where('beacon_seq', $n['seq'])->exists();
            if (! $there) {
                $this->db()->table('survey_its_beacons')->insert([
                    $column => $id, 'beacon_no' => $n['no'], 'beacon_series' => $n['series'], 'beacon_seq' => $n['seq'],
                    'created_by' => auth()->id(), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** @return array<int, array{no:string, series:string, seq:int}> */
    public function read(?string $text, string $field = 'beacon_numbers'): array
    {
        $parsed = SurveyBeaconRegister::parseList((string) $text);
        if ($parsed['bad']) {
            throw ValidationException::withMessages([$field => 'Not read as beacon numbers: ' . implode(', ', $parsed['bad']) . '.']);
        }

        $out = [];
        foreach ($parsed['ranges'] as $range) {
            foreach (SurveyBeaconRegister::expand($range) as $seq => $no) {
                $out[$range['series'] . '#' . $seq] = ['no' => $no, 'series' => $range['series'], 'seq' => (int) $seq];
            }
        }

        return array_values($out);
    }

    private function jobName(object $row): string
    {
        if ($row->survey_computation_file_id ?? null) {
            return (string) ($this->db()->table('survey_computation_files')->where('id', $row->survey_computation_file_id)->value('computation_no') ?: 'another job');
        }
        if ($row->survey_its_id) {
            $its = $this->db()->table('survey_its')->where('id', $row->survey_its_id)->first(['its_number', 'job_ref']);

            return $its ? ($its->its_number ?: $its->job_ref) : 'another job';
        }
        $job = $this->db()->table('cadastral_survey_jobs')->where('id', $row->cadastral_survey_job_id)->first(['its_number', 'job_number']);

        return $job ? ($job->its_number ?: $job->job_number) : 'another job';
    }

    /** Names are compared without titles, case or punctuation: "Surv. Bala Bello" is "BALA BELLO". */
    private function norm(string $name): string
    {
        $name = mb_strtoupper(trim($name));
        $name = preg_replace('/^(SURV|SURVEYOR|ENGR|ALH|ALHAJI|DR|MR|MRS|MALLAM|MAL)\b\.?\s*/u', '', $name);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
    }
}
