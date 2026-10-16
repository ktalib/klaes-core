<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralFmc;
use App\Models\CadastralDept\CadastralVerificationReport as Report;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TitleHolderResolver;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The Cadastral Verification Report Sheet (client DOCX, 2026-10-15), completed
 * during the Verification workflow. Sections marked [Auto] on the sheet are
 * filled from the File Movement Card and the registry; the encumbrance and
 * title checks start from the File Status checks (VerificationFileStatus) for
 * the officer to confirm; the rest is the officer's. Section 7 names the
 * officers who acted at each signing desk, for the print's signature lines.
 */
class VerificationReports
{
    private const CONN = 'sqlsrv';

    /** @var array<string, array> */
    private array $titleCache = [];

    /** Instrument wording → the sheet's Section 2 "Nature of Transaction". */
    private const NATURE_PATTERNS = [
        'Gift' => '/gift/i',
        'Mortgage' => '/mortgage/i',
        'Assignment' => '/assign|sale|purchase|transfer|conveyance/i',
    ];

    public function __construct(private VerificationFileStatus $status, private AuditService $audit, private TitleHolderResolver $titles)
    {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('cadastral_verification_reports');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function applies(CadastralCase $case): bool
    {
        return $case->workflow === 'verification';
    }

    /** Filled in while the Verification file is open. */
    public function editable(CadastralCase $case): bool
    {
        return self::installed() && $this->applies($case) && $case->isOpen();
    }

    public function forCase(CadastralCase $case): ?Report
    {
        return self::installed() ? Report::query()->where('cadastral_case_id', $case->id)->first() : null;
    }

    /**
     * Section 1 from the records: the FMC first (the sheet says so), then the
     * case and the file's land recommendation. Blank where nothing holds it.
     *
     * @return array<string, ?string>
     */
    public function general(CadastralCase $case): array
    {
        $fmc = FileMovementCards::installed() ? CadastralFmc::query()->where('cadastral_case_id', $case->id)->first() : null;
        $rec = $this->recommendation($case);

        $district = trim(implode(', ', array_filter([
            $fmc?->district ?: $case->district,
            $fmc?->lga ?: $case->lga,
        ])));
        $location = $fmc?->location ?: $case->location;

        $route = app(WorkflowDefinition::class)->subTypes('direct')[$case->allocation_route ?? ''] ?? null;

        return [
            'application_date' => $this->dmy($rec?->application_date),
            'allottee_name' => $this->blank($fmc?->allottee_name ?: ($case->file_title ?: $rec?->applicant_name)),
            'application_type' => $this->blank($route ?: ($rec?->application_type ?: $rec?->type)),
            'plot_no' => $this->blank($fmc?->plot_no ?: ($case->plot_no ?: $rec?->plot_number)),
            'layout_no' => $this->blank($fmc?->layout_no ?: $rec?->layout_plan_no),
            'district_location' => $this->blank($location && stripos($location, (string) $district) === false && $district !== ''
                ? $district . ' · ' . $location : ($location ?: $district)),
            'fmc_commission_date' => $this->dmy($fmc?->commissioned_at),
        ];
    }

    /**
     * Starting values for the officer's sections, from the property records and
     * the File Status checks. Only used where nothing has been saved.
     *
     * @return array<string, ?string>
     */
    public function suggestions(CadastralCase $case): array
    {
        $out = $this->holders($case);

        try {
            $checks = collect($this->status->results($case))->keyBy('key');
        } catch (\Throwable $e) {
            Log::warning('Verification report: file status checks failed', ['case' => $case->id, 'error' => $e->getMessage()]);
            $checks = collect();
        }
        $c = fn (string $k) => $checks->get($k) ?? ['tone' => 'error', 'status' => '', 'detail' => ''];

        $caveat = $c('caveat');
        if ($caveat['tone'] !== 'error') {
            $out['caveat_status'] = $caveat['tone'] === 'warn' ? 'Encumbered' : 'Clear';
            $out['caveat_ref'] = $caveat['tone'] === 'clear' ? null : trim($caveat['status'] . ': ' . $caveat['detail'], ': ');
        }

        $mortgage = $c('mortgage');
        if ($mortgage['tone'] !== 'error') {
            $out['mortgage_status'] = $mortgage['tone'] === 'warn' ? 'Encumbered' : 'Clear';
            $out['mortgage_ref'] = $mortgage['tone'] === 'clear' ? null : trim($mortgage['status'] . ': ' . $mortgage['detail'], ': ');
            $released = in_array($mortgage['status'], ['Released', 'Partly released'], true);
            $out['release_status'] = $released ? 'Executed' : 'Not Applicable';
            $out['release_ref'] = $released ? $mortgage['detail'] : null;
        }

        $out = array_merge($out, $this->sectionFive($case, $checks));

        $dciv = $c('dciv');
        if ($dciv['tone'] === 'warn') {
            // Under Investigation, as Legal Search flags it: the investigation is still open.
            $out['dciv_status'] = 'Pending';
            $out['dciv_remarks'] = ($dciv['note'] ?? '') ?: 'Under Investigation: ' . $dciv['detail'];
        }

        return array_map(fn ($v) => $this->blank($v), $out);
    }

    /**
     * Section 5 ticked from the records, where the file has any (user, 2026-10-15):
     * the title from the CofO / RofO / OP / recommendation checks, the parcel
     * update from the APU/SPU records, the survey plan from the survey records and
     * survey jobs, and charting from this case's current charting. A parameter
     * with nothing on record stays unticked for the officer.
     *
     * @return array<string, ?string>
     */
    public function sectionFive(CadastralCase $case, $checks = null): array
    {
        $out = [];
        if ($checks === null) {
            try {
                $checks = collect($this->status->results($case))->keyBy('key');
            } catch (\Throwable $e) {
                Log::warning('Verification report: file status checks failed', ['case' => $case->id, 'error' => $e->getMessage()]);
                $checks = collect();
            }
        }
        $c = fn (string $k) => $checks->get($k) ?? ['tone' => 'error', 'status' => '', 'detail' => ''];
        $cofo = $c('cofo');
        $rofo = $c('rofo');
        $titles = $this->titleHolders($case);
        $root = $titles['root_of_title'] ?? null;
        $isOp = strcasecmp(trim((string) ($root['instrument'] ?? '')), 'OP') === 0;
        // The first Ministry title the deeds history names, for a grant the RofO / CofO checks miss.
        $grant = $titles['original_holder'] ?? null;
        $grantIs = fn (string $re) => (bool) preg_match($re, (string) ($grant['instrument'] ?? ''));
        $grantRef = fn (string $label) => trim($label . ' · ' . ($grant['name'] ?? '') . (($grant['date'] ?? null) ? ' · ' . $this->dmy($grant['date']) : ''));
        if ($cofo['tone'] === 'found') {
            [$out['title_status'], $out['title_ref']] = ['C of O', $cofo['detail'] ?: null];
        } elseif ($grantIs('/c\s*of\s*o|certificate of occupancy/i')) {
            [$out['title_status'], $out['title_ref']] = ['C of O', $grantRef('CofO')];
        } elseif ($rofo['tone'] === 'found' && $rofo['status'] === 'Yes') {
            [$out['title_status'], $out['title_ref']] = ['RofO', $rofo['detail'] ?: null];
        } elseif ($grantIs('/r\s*of\s*o|right of occupancy/i')) {
            [$out['title_status'], $out['title_ref']] = ['RofO', $grantRef('RofO')];
        } elseif ($isOp) {
            [$out['title_status'], $out['title_ref']] = ['OP', trim('OP · ' . ($root['name'] ?? '') . (($root['date'] ?? null) ? ' · ' . $this->dmy($root['date']) : ''))];
        } elseif ($rofo['status'] === 'Recommended') {
            [$out['title_status'], $out['title_ref']] = ['Pending', $rofo['detail'] ?: null];
        } elseif ($cofo['tone'] === 'clear' && $rofo['tone'] === 'clear') {
            $out['title_status'] = 'None';
        }

        [$numbers] = $this->safeIdentity($case);
        $db = DB::connection(self::CONN);

        // Parcel update (APU/SPU): the file as a source or a result of an update.
        try {
            if ($numbers) {
                $statuses = $db->table('duplex_parcel_update_files as f')
                    ->join('duplex_parcel_updates as u', 'u.id', '=', 'f.duplex_parcel_update_id')
                    ->where(fn ($q) => $q->whereNull('u.is_deleted')->orWhere('u.is_deleted', 0))
                    ->where(fn ($q) => $q->whereIn('f.source_file_no', $numbers)->orWhereIn('f.final_file_no', $numbers))
                    ->pluck('u.status')->map(fn ($s) => strtolower((string) $s))->unique();
                if ($statuses->contains('committed')) {
                    $out['parcel_update_status'] = 'Completed';
                } elseif ($statuses->isNotEmpty()) {
                    $out['parcel_update_status'] = 'In Progress';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Verification report: parcel update check failed', ['case' => $case->id, 'error' => $e->getMessage()]);
        }

        // Survey plan: an approved plan on the survey record, else the survey job's state.
        try {
            if ($numbers) {
                $plans = $db->table('surveyCadastralRecord')
                    ->where(fn ($q) => $q->whereIn('fileno', $numbers)->orWhereIn('STFileNo', $numbers))
                    ->get(['approved_plan_no', 'approved_by', 'approved_by_date']);
                $jobs = $db->table('cadastral_survey_jobs')->whereNull('deleted_at')
                    ->where(fn ($q) => $q->whereIn('file_number', $numbers)->orWhere('cadastral_case_id', $case->id ?: 0))
                    ->pluck('status')->map(fn ($s) => strtolower((string) $s));
                if ($plans->contains(fn ($p) => trim((string) ($p->approved_plan_no ?: $p->approved_by ?: $p->approved_by_date)) !== '')
                    || $jobs->contains('accepted')) {
                    $out['survey_plan_status'] = 'Approved';
                } elseif ($plans->isNotEmpty() || $jobs->intersect(['issued', 'in field', 'submitted'])->isNotEmpty()) {
                    $out['survey_plan_status'] = 'Pending';
                } elseif ($jobs->contains('rejected')) {
                    $out['survey_plan_status'] = 'Rejected';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Verification report: survey plan check failed', ['case' => $case->id, 'error' => $e->getMessage()]);
        }

        // Charting: this case's current charting record.
        try {
            $chart = $case->id ? $db->table('cadastral_chartings')->where('cadastral_case_id', $case->id)
                ->where('is_current', 1)->orderByDesc('charted_at')->first() : null;
            if ($chart) {
                if ((string) $chart->matches_record === '0' || (string) $chart->falls_on_existing === '1' || (string) $chart->agrees_with_first === '0') {
                    $out['charting_status'] = 'Discrepancy Found';
                } elseif ((string) $chart->matches_record === '1' || (string) $chart->charted === '1') {
                    $out['charting_status'] = 'Matches UTM Sheet';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Verification report: charting check failed', ['case' => $case->id, 'error' => $e->getMessage()]);
        }

        return array_map(fn ($v) => $this->blank($v), $out);
    }

    private function safeIdentity(CadastralCase $case): array
    {
        try {
            return $this->status->identity($case);
        } catch (\Throwable $e) {
            return [[], []];
        }
    }

    /** TitleHolderResolver's reading of the file, once per request per file. */
    private function titleHolders(CadastralCase $case): array
    {
        $key = trim((string) $case->file_number);
        if ($key === '') {
            return ['root_of_title' => null, 'original_holder' => null, 'current_holder' => null];
        }
        if (!array_key_exists($key, $this->titleCache)) {
            try {
                $this->titleCache[$key] = $this->titles->resolve($key);
            } catch (\Throwable $e) {
                Log::warning('Verification report: title holders failed', ['case' => $case->id, 'error' => $e->getMessage()]);
                $this->titleCache[$key] = ['root_of_title' => null, 'original_holder' => null, 'current_holder' => null];
            }
        }

        return $this->titleCache[$key];
    }

    /**
     * Section 7: the officer who last acted at each signing desk, and when.
     *
     * @return array<string, array{role: string, name: ?string, date: ?string}>
     */
    public function signOff(CadastralCase $case): array
    {
        $moves = DB::connection(self::CONN)->table('cadastral_case_movements')
            ->where('cadastral_case_id', $case->id)
            ->whereIn('from_desk', array_keys(Report::SIGN_OFF))
            ->orderBy('acted_at')->orderBy('id')
            ->get(['from_desk', 'acted_by', 'acted_at'])
            ->keyBy('from_desk');
        $users = User::query()->whereIn('id', $moves->pluck('acted_by')->filter()->unique())->get()->keyBy('id');

        $out = [];
        foreach (Report::SIGN_OFF as $desk => $role) {
            $m = $moves->get($desk);
            $u = $m ? $users->get($m->acted_by) : null;
            $out[$desk] = [
                'role' => $role,
                'name' => $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->name ?? null) : null,
                'date' => $m ? $this->dmy($m->acted_at) : null,
            ];
        }

        return $out;
    }

    /**
     * What the form and the print show: saved values, else the records.
     *
     * @return array<string, ?string>
     */
    public function values(CadastralCase $case, ?Report $report): array
    {
        $general = $this->general($case);
        $saved = $report ? $report->only(Report::COLUMNS) : [];
        $start = $report ? array_merge($this->holders($case), $this->sectionFive($case)) : $this->suggestions($case);

        // The holders and Section 5 fill in automatically: a saved sheet keeps what
        // the officer typed, and only a blank field there takes the records' value.
        $out = [];
        foreach (Report::COLUMNS as $col) {
            $out[$col] = in_array($col, Report::GENERAL, true)
                ? ($general[$col] ?? null) ?: ($saved[$col] ?? null)
                : ($report
                    ? ($saved[$col] ?? null) ?: (array_key_exists($col, $start) ? ($start[$col] ?? null) : null)
                    : ($start[$col] ?? null));
        }

        return $out;
    }

    /** Save the sheet. Section 1 keeps the records' values; a blank there is the officer's to fill. */
    public function save(CadastralCase $case, array $data, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The Verification Report Sheet table has not been created yet.');
        }
        if (!$this->applies($case)) {
            throw WorkflowGuardException::because('The Verification Report Sheet is for Verification files.');
        }
        if (!$case->isOpen()) {
            throw WorkflowGuardException::because("{$case->case_ref} is closed, so its Verification Report Sheet can no longer change.");
        }

        foreach ($this->general($case) as $col => $value) {
            if ($value !== null) {
                $data[$col] = $value;
            }
        }
        foreach (['allottee_name', 'original_holder_name', 'current_holder_name', 'dciv_officer'] as $col) {
            if (isset($data[$col])) {
                $data[$col] = Normalise::name($data[$col]) ?: null;
            }
        }
        // Remarks keep their lines (they print line by line); each line is tidied.
        foreach (['dciv_remarks', 'remarks'] as $col) {
            if (isset($data[$col])) {
                $lines = array_filter(array_map(fn ($l) => Normalise::text($l), preg_split('/\r?\n/', (string) $data[$col])), fn ($l) => $l !== '');
                $data[$col] = $lines ? implode("\n", $lines) : null;
            }
        }
        foreach ($data as $k => $v) {
            if (is_string($v) && !in_array($k, ['dciv_remarks', 'remarks'], true)) {
                $data[$k] = trim($v) === '' ? null : trim($v);
            }
        }

        // Every column is written, so a cleared field really clears.
        $values = array_intersect_key(array_merge(array_fill_keys(Report::COLUMNS, null), $data), array_flip(Report::COLUMNS));

        return DB::connection(self::CONN)->transaction(function () use ($case, $values, $userId) {
            $report = Report::query()->firstOrNew(['cadastral_case_id' => $case->id]);
            $report->fill($values + ['file_number' => $case->file_number, 'saved_by' => $userId]);
            $complete = $report->verdict !== null;
            $report->completed_at = $complete ? ($report->completed_at ?? now()) : null;
            $report->save();

            $this->audit->logAction('Cadastral Verification Report Saved', 'CadastralVerificationReport', $report->id, null,
                ['verdict' => $report->verdict], "Verification Report Sheet for {$case->case_ref}" . ($complete ? ' (' . Report::VERDICTS[$report->verdict][0] . ')' : ' (no verdict yet)'));

            return $complete
                ? 'Verification Report Sheet saved: ' . Report::VERDICTS[$report->verdict][0] . '.'
                : 'Verification Report Sheet saved. Give the verdict (Section 6) to complete it.';
        });
    }

    public function countPrint(Report $report): void
    {
        $report->increment('print_count');
    }

    /**
     * Section 2 from the same Root of Title / Current Holder reading the Search
     * screens use (TitleHolderResolver): the Original Holder (First Allottee) is
     * the Root of Title, the Current Holder (Applicant) the holder after the
     * latest ownership-changing dealing. With no dealing on record the applicant
     * falls back to the recommendation's applicant, then the file title.
     *
     * @return array<string, ?string>
     */
    private function holders(CadastralCase $case): array
    {
        $out = [];
        $r = $this->titleHolders($case);

        $root = $r['root_of_title'] ?: $r['original_holder'];
        $out['original_holder_name'] = $this->blank($root['name'] ?? null) ?: $this->blank($case->file_title);
        $out['original_holder_ref'] = $this->holderRef($case, $root);
        $out['original_holder_date'] = $this->dmy($root['date'] ?? null);

        $current = $r['current_holder'];
        $applicant = $this->blank($current['name'] ?? null);
        if ($applicant) {
            $out['current_holder_ref'] = $this->holderRef($case, $current);
            $out['current_holder_date'] = $this->dmy($current['date'] ?? null);
            // A current holder who is the root holder came by the grant, not a dealing.
            if (strcasecmp($applicant, (string) $out['original_holder_name']) !== 0 && ($current['instrument'] ?? null)) {
                $out['current_transaction_nature'] = $this->nature((string) $current['instrument']);
            }
        } else {
            $applicant = $this->blank($this->recommendation($case)?->applicant_name) ?: $this->blank($case->file_title);
            $out['current_holder_ref'] = $applicant ? $case->file_number : null;
        }
        $out['current_holder_name'] = $applicant;

        return $out;
    }

    /** "RES-2010-4268 · RofO". */
    private function holderRef(CadastralCase $case, ?array $holder): ?string
    {
        $instrument = trim((string) ($holder['instrument'] ?? ''));

        return $this->blank(trim($case->file_number . ($instrument !== '' ? ' · ' . $instrument : '')));
    }

    private function nature(string $instrument): string
    {
        foreach (self::NATURE_PATTERNS as $label => $re) {
            if (preg_match($re, $instrument)) {
                return $label;
            }
        }

        return 'Other: ' . $instrument;
    }

    private function recommendation(CadastralCase $case)
    {
        try {
            [$numbers] = $this->status->identity($case);

            return $numbers ? DB::connection(self::CONN)->table('land_recommendations')
                ->whereIn('file_number', $numbers)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')
                ->first(['application_date', 'applicant_name', 'application_type', 'type', 'plot_number', 'layout_plan_no']) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function dmy($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $this->blank((string) $value);
        }
    }

    private function blank($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' || in_array(strtoupper($v), ['N/A', 'NULL', '-'], true) ? null : $v;
    }
}
