<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyOpRecord;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Occupancy Permits and the five-stage pipeline:
 * Survey -> GIS/KANGIS -> Commissioner -> Deeds -> Land/OSS.
 *
 * An OP belongs to one case and (usually) one beneficiary. What it references
 * is decided by the case's scheme and is always recomputed here: a Land to Land
 * OP carries the plot schedule, a Monetary Compensation OP carries the cash figure. The two
 * are never mixed and never read from the request.
 */
class OpController extends Controller
{
    public const STATUS_DRAFT  = 'Draft';
    public const STATUS_READY  = 'Ready';
    public const STATUS_ISSUED = 'Issued';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_READY, self::STATUS_ISSUED];

    /**
     * A case must be signed off before a permit can be raised against it.
     *
     * These are drawn from CaseController::STATUSES — keep the two in step.
     * 'Pending' is a draft, 'Review' is still with Examination, and 'Rejected'
     * is finished with, so none of them appear in the generation queue.
     */
    public const READY_CASE_STATUSES = ['Active', 'Completed'];

    /** How many generation candidates to offer at once. */
    private const CANDIDATE_LIMIT = 50;

    public function index(Request $r)
    {
        // Two tabs (user, 2026-11-03): Existing (OPs issued before, captured as written) and New (generated here).
        $tab = $r->query('tab') === 'new' ? 'new' : 'existing';
        if ($tab === 'existing') {
            return $this->existingIndex($r);
        }

        $q = SurveyOpRecord::query()->new()->with(['case', 'beneficiary', 'steps']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('op_number', 'like', "%$term%")
                  ->orWhere('plot_or_cash_ref', 'like', "%$term%")
                  ->orWhereHas('case', fn ($c) => $c->where('case_ref', 'like', "%$term%"))
                  ->orWhereHas('beneficiary', fn ($b) => $b->where('full_name', 'like', "%$term%"));
            });
        }
        if ($s = $r->query('scheme')) $q->where('scheme_type', $s);
        if ($st = $r->query('status')) $q->where('status', $st);

        $ops = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'  => SurveyOpRecord::new()->count(),
            'draft'  => SurveyOpRecord::new()->where('status', self::STATUS_DRAFT)->count(),
            'ready'  => SurveyOpRecord::new()->where('status', self::STATUS_READY)->count(),
            'issued' => SurveyOpRecord::new()->where('status', self::STATUS_ISSUED)->count(),
        ];

        return view('survey_module.compensation.op', [
            'tab'        => 'new',
            'counts'     => ['existing' => $this->existingQuery()->count(), 'new' => $stats['total']],
            'ops'        => $ops,
            'stats'      => $stats,
            'candidates' => $this->candidates(),
        ]);
    }

    /**
     * Existing tab (user, 2026-11-03: "let the Existing tab show the existing OPs"): the OPs already issued,
     * read from Deeds as the Plot Showing search reads them (OpLookup: instrument_capture OPs + the older
     * standalone pra OPs), plus any typed in here with Capture Existing OP. Deeds rows are read-only here.
     */
    private function existingIndex(Request $r)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
        $kind = in_array($r->query('kind'), self::OP_KINDS, true) ? $r->query('kind') : null;

        $ops = $this->existingQuery()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('serial', 'like', $like)->orWhere('allottee', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('file_no', 'like', $like)->orWhere('district', 'like', $like)->orWhere('lga', 'like', $like)))
            ->when($kind, fn ($q) => $q->where('op_kind', $kind))
            ->when($r->query('project'), fn ($q, $p) => $q->where('project_id', (int) $p))
            ->orderByRaw('CASE WHEN issued IS NULL THEN 1 ELSE 0 END, issued DESC, rid DESC')
            ->paginate(20)->withQueryString();

        $totals = $this->existingQuery()->selectRaw("COUNT(*) total, SUM(CASE WHEN op_kind = 'Resettlement' THEN 1 ELSE 0 END) resettlement, "
            . "SUM(CASE WHEN op_kind = 'Direct Allocation' THEN 1 ELSE 0 END) direct, SUM(CASE WHEN src = 'klaes' THEN 1 ELSE 0 END) captured")->first();

        return view('survey_module.compensation.op', [
            'tab'      => 'existing',
            'counts'   => ['existing' => (int) $totals->total, 'new' => SurveyOpRecord::new()->count()],
            'ops'      => $ops,
            'kinds'    => self::OP_KINDS,
            'projects' => SurveyProject::orderBy('name')->get(['id', 'project_code', 'name']),
            'editing'  => $r->query('edit') ? SurveyOpRecord::existing()->find((int) $r->query('edit')) : null,
            // Dashboard cards (user, 2026-11-03).
            'stats'    => [
                'total'        => (int) $totals->total,
                'resettlement' => (int) $totals->resettlement,
                'direct'       => (int) $totals->direct,
                'captured'     => (int) $totals->captured,
            ],
        ]);
    }

    /** OP types as the Existing tab groups them; Deeds writes them as "OP Resettlement" / "Resettlement" etc. */
    private const OP_KINDS = ['Resettlement', 'Direct Allocation'];

    /** Every existing OP in one shape: Deeds (capture + standalone pra) and the ones captured on this page. */
    private function existingQuery(): \Illuminate\Database\Query\Builder
    {
        $db = \Illuminate\Support\Facades\DB::connection('sqlsrv');
        $op = 'Occupancy Permit (OP)';
        $t = fn ($col, $n = 255) => "CAST(NULLIF(LTRIM(RTRIM(CAST({$col} AS NVARCHAR({$n})))), '') AS NVARCHAR({$n}))";
        // An OP with no type in Deeds counts as Direct Allocation (user, 2026-11-03); Deeds itself is not changed.
        $kind = fn ($col) => "CASE WHEN {$col} LIKE '%Resettlement%' THEN 'Resettlement' ELSE 'Direct Allocation' END";
        $live = fn ($alias) => fn ($q) => $q->whereNull("{$alias}.is_deleted")->orWhere("{$alias}.is_deleted", 0);

        $capture = $db->table('instrument_capture as ic')->where('ic.instrument_type', $op)->where($live('ic'))
            ->selectRaw("'capture' AS src, ic.id AS rid, {$t('ic.op_serial_number', 50)} AS serial, {$kind('ic.op_type')} AS op_kind, "
                . "{$t('ic.party_2_name')} AS allottee, {$t('ic.plot_number', 100)} AS plot_no, {$t('ic.district', 150)} AS district, {$t('ic.lga', 150)} AS lga, "
                . "COALESCE({$t('ic.property_location', 500)}, {$t('ic.property_description', 500)}) AS location, COALESCE({$t('ic.mlsFNo', 100)}, {$t('ic.temp_fileno', 100)}) AS file_no, "
                . "TRY_CAST(ic.reg_date AS date) AS issued, CAST(NULL AS int) AS project_id, CAST(NULL AS NVARCHAR(2000)) AS remarks");

        $pra = $db->table('pra as p')->where('p.instrument_type', $op)->whereNull('p.instrument_capture_id')->where($live('p'))
            ->selectRaw("'pra' AS src, p.id AS rid, {$t('p.op_serial_number', 50)} AS serial, {$kind('p.op_type')} AS op_kind, "
                . "{$t('p.Grantee')} AS allottee, {$t('p.plot_no', 100)} AS plot_no, {$t('p.districtName', 150)} AS district, CAST(NULL AS NVARCHAR(150)) AS lga, "
                . "COALESCE({$t('p.location', 500)}, {$t('p.property_description', 500)}) AS location, COALESCE({$t('p.mlsFNo', 100)}, {$t('p.temp_fileno', 100)}) AS file_no, "
                . "TRY_CAST(p.reg_date AS date) AS issued, CAST(NULL AS int) AS project_id, CAST(NULL AS NVARCHAR(2000)) AS remarks");

        $klaes = $db->table('survey_op_records as o')->where('o.is_existing', 1)->whereNull('o.deleted_at')
            ->selectRaw("'klaes' AS src, o.id AS rid, {$t('o.op_number', 50)} AS serial, 'Resettlement' AS op_kind, "
                . "{$t('o.allottee_name')} AS allottee, {$t('o.plot_no', 100)} AS plot_no, {$t('o.district', 150)} AS district, {$t('o.lga', 150)} AS lga, "
                . "CAST(NULL AS NVARCHAR(500)) AS location, CAST(NULL AS NVARCHAR(100)) AS file_no, "
                . "TRY_CAST(o.issued_at AS date) AS issued, o.survey_project_id AS project_id, {$t('o.remarks', 2000)} AS remarks");

        return $db->query()->fromSub($capture->unionAll($pra)->unionAll($klaes), 'x');
    }

    public function storeExisting(Request $r)
    {
        $op = SurveyOpRecord::create($this->existingValues($r) + [
            'is_existing' => true,
            'status'      => self::STATUS_ISSUED,
            'created_by'  => auth()->id(),
        ]);

        return redirect()->route('survey-module.compensation.op', ['tab' => 'existing'])->with('success', "Existing OP {$op->op_number} captured.");
    }

    public function updateExisting(Request $r, SurveyOpRecord $op)
    {
        abort_unless($op->is_existing, 404);
        $op->update($this->existingValues($r, $op) + ['updated_by' => auth()->id()]);

        return redirect()->route('survey-module.compensation.op', ['tab' => 'existing'])->with('success', "Existing OP {$op->op_number} updated.");
    }

    public function destroyExisting(SurveyOpRecord $op)
    {
        abort_unless($op->is_existing, 404);
        $op->update(['updated_by' => auth()->id()]);
        $op->delete();

        return redirect()->route('survey-module.compensation.op', ['tab' => 'existing'])->with('success', "Existing OP {$op->op_number} removed.");
    }

    private function existingValues(Request $r, ?SurveyOpRecord $existing = null): array
    {
        $d = $r->validate([
            'op_number'         => 'required|string|max:50',
            'allottee_name'     => 'required|string|max:255',
            'survey_project_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('sqlsrv.survey_projects', 'id')],
            'plot_no'           => 'nullable|string|max:100',
            'district'          => 'nullable|string|max:150',
            'lga'               => 'nullable|string|max:150',
            'issued_at'         => 'nullable|date|before_or_equal:today',
            'scheme_type'       => ['nullable', \Illuminate\Validation\Rule::in([SurveyProject::SCHEME_MONETARY, SurveyProject::SCHEME_LAND])],
            'remarks'           => 'nullable|string|max:2000',
        ], [
            'op_number.required'     => 'Enter the OP number as it is on the OP.',
            'allottee_name.required' => 'Enter the allottee.',
        ]);
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;
        // An identifier: exactly as on the OP. The same number twice (any case or spacing) is refused.
        $no = $clean($d['op_number']);
        $taken = SurveyOpRecord::withTrashed()->whereRaw("UPPER(REPLACE(op_number, ' ', '')) = ?", [mb_strtoupper(str_replace(' ', '', $no))])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages(['op_number' => "OP {$no} is already recorded."]);
        }
        $project = ! empty($d['survey_project_id']) ? SurveyProject::find($d['survey_project_id']) : null;

        return [
            'op_number'         => $no,
            'allottee_name'     => $clean($d['allottee_name']),
            'survey_project_id' => $project?->id,
            'scheme_type'       => $d['scheme_type'] ?? $project?->scheme_type,
            'plot_no'           => $clean($d['plot_no'] ?? null),
            'plot_or_cash_ref'  => $clean($d['plot_no'] ?? null),
            'district'          => $clean($d['district'] ?? null),
            'lga'               => $clean($d['lga'] ?? null),
            'issued_at'         => $d['issued_at'] ?? null,
            'remarks'           => $clean($d['remarks'] ?? null),
        ];
    }

    /**
     * Bulk "Generate Selected". Each target names either a beneficiary
     * ("b:12") or a case with no beneficiaries of its own ("c:5"); everything
     * else about the permit is derived server-side.
     */
    public function generate(Request $r)
    {
        $data = $r->validate([
            'targets'   => 'required|array|min:1',
            'targets.*' => ['string', 'regex:/^[bc]:[0-9]+$/'],
        ], [
            'targets.required' => 'Select at least one case or beneficiary to generate an OP for.',
        ]);

        $created = 0;
        $skipped = [];

        foreach (array_unique($data['targets']) as $target) {
            [$kind, $id] = explode(':', $target, 2);

            if ($kind === 'b') {
                $beneficiary = SurveyBeneficiary::with('case')->find($id);
                $case        = $beneficiary?->case;
                $label       = $beneficiary?->full_name ?? "beneficiary #$id";
            } else {
                $beneficiary = null;
                $case        = SurveyCompCase::find($id);
                $label       = $case?->case_ref ?? "case #$id";
            }

            if (!$case) {
                $skipped[] = "$label (no case)";
                continue;
            }
            if (!in_array($case->status, self::READY_CASE_STATUSES, true)) {
                $skipped[] = "$label ({$case->case_ref} is {$case->status})";
                continue;
            }
            // Existing records (imported or captured from paper) already hold their OPs; none is generated here (CR 30102026 item 32).
            if ($case->isExistingRecord()) {
                $skipped[] = "$label ({$case->case_ref} is an existing record)";
                continue;
            }
            if ($this->existingOp($case->id, $beneficiary?->id)) {
                $skipped[] = "$label (already has an OP)";
                continue;
            }

            $ref = $this->reference($case, $beneficiary);

            $op = SurveyOpRecord::create([
                // The lowest free number: one freed by a Return is used again (CR 30102026 item 35).
                'op_number'             => SurveyOpRecord::nextOpNumber(),
                'survey_comp_case_id'   => $case->id,
                'survey_beneficiary_id' => $beneficiary?->id,
                // Scheme is inherited from the case, never taken from the request.
                'scheme_type'           => $case->scheme_type,
                'plot_or_cash_ref'      => $ref['ref'],
                'cash_amount'           => $ref['cash'],
                // Nothing to reference yet means the permit cannot be printed.
                'status'                => $ref['ref'] ? self::STATUS_READY : self::STATUS_DRAFT,
            ]);

            $op->seedWorkflow();
            $created++;
        }

        $msg = $created
            ? "Generated $created occupancy permit(s)."
            : 'No occupancy permits were generated.';

        if ($skipped) {
            $msg .= ' Skipped: ' . implode('; ', array_slice($skipped, 0, 5))
                  . (count($skipped) > 5 ? ' …' : '') . '.';
        }

        return redirect()
            ->route('survey-module.compensation.op')
            ->with($created ? 'success' : 'error', $msg);
    }

    /**
     * Return a generated OP made by mistake (CR 30102026 item 35, user 2026-10-31): the permit
     * and its pipeline are removed, so the case / beneficiary is back in the table above, ready
     * to generate again, and its OP number is freed for the next OP (SurveyOpRecord::nextOpNumber).
     * An issued OP, or one already sent to Deeds, cannot be returned here.
     */
    public function returnOp(SurveyOpRecord $op)
    {
        if ($op->status === self::STATUS_ISSUED) {
            return back()->with('error', "{$op->op_number} has already been issued and cannot be returned.");
        }
        if ($op->sent_to_deeds_at) {
            return back()->with('error', "{$op->op_number} has already been sent to Deeds and cannot be returned here.");
        }

        $number = $op->op_number;
        $who = $op->beneficiary?->full_name ?: $op->case?->case_ref;
        \Illuminate\Support\Facades\Log::info('Survey OP returned', ['op' => $op->toArray(), 'steps' => $op->steps()->get()->toArray(), 'by' => auth()->id()]);
        \Illuminate\Support\Facades\DB::connection('sqlsrv')->transaction(function () use ($op) {
            // Removed outright, not soft-deleted, so the number is free again (the steps first: they hold the key).
            $op->steps()->withTrashed()->forceDelete();
            $op->forceDelete();
        });

        return back()->with('success', "{$number} returned" . ($who ? " for {$who}" : '') . '. It is back in the table above, and the number will be used by the next OP generated.');
    }

    public function destroy(SurveyOpRecord $op)
    {
        if ($op->status === self::STATUS_ISSUED) {
            return back()->with('error',
                "{$op->op_number} has already been issued and cannot be deleted.");
        }

        $number = $op->op_number;
        $op->steps()->delete();
        $op->delete();

        return back()->with('success', "OP $number deleted.");
    }

    /**
     * The pipeline page. Shows one OP's chain; defaults to the most recently
     * touched permit that is still moving.
     */
    public function workflow(Request $r)
    {
        $op = null;

        if ($id = $r->query('op')) {
            $op = SurveyOpRecord::with(['steps', 'case', 'beneficiary'])->find($id);
        }

        $op ??= SurveyOpRecord::with(['steps', 'case', 'beneficiary'])
            ->where('status', '!=', self::STATUS_ISSUED)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $op ??= SurveyOpRecord::with(['steps', 'case', 'beneficiary'])->orderByDesc('id')->first();

        // Dropdown of everything the user could look at.
        $choices = SurveyOpRecord::with('beneficiary')->orderByDesc('id')->limit(200)->get();

        return view('survey_module.workflow.occupancy', [
            'op'      => $op,
            'steps'   => $op ? $op->steps : collect(),
            'choices' => $choices,
            'stats'   => [
                'in_progress' => SurveyOpRecord::where('status', '!=', self::STATUS_ISSUED)->count(),
                'issued'      => SurveyOpRecord::where('status', self::STATUS_ISSUED)->count(),
            ],
        ]);
    }

    /**
     * Complete the active step and wake the next one. Completing step 5 issues
     * the permit.
     */
    public function advance(Request $r, SurveyOpRecord $op)
    {
        $r->validate(['note' => 'nullable|string|max:1000']);

        $steps = $op->steps()->get();

        if ($steps->isEmpty()) {
            return back()->with('error',
                "{$op->op_number} has no workflow steps. Re-generate the permit to seed the pipeline.");
        }

        // The first step that is not finished is the one in play, whether or not
        // its status was left as "waiting" by an earlier hiccup.
        $current = $steps->first(fn ($s) => $s->status !== 'done');

        if (!$current) {
            return back()->with('error',
                "{$op->op_number} has already completed all " . count(SurveyOpRecord::STEPS) . ' stages.');
        }

        $current->update([
            'status'       => 'done',
            'note'         => $r->input('note') ?: $current->note,
            'completed_at' => Carbon::now(),
        ]);

        $next = $steps->first(fn ($s) => $s->step_no > $current->step_no);

        if ($next) {
            $next->update(['status' => 'active']);
            $flash = "{$op->op_number}: {$current->step_name} completed. Now with {$next->actor}.";
        } else {
            $op->update(['status' => self::STATUS_ISSUED, 'issued_at' => Carbon::now()]);
            $flash = "{$op->op_number} issued — all " . count(SurveyOpRecord::STEPS) . ' stages complete.';
        }

        return redirect()
            ->route('survey-module.workflow.occupancy', ['op' => $op->id])
            ->with('success', $flash);
    }

    /* ------------------------------------------------------------------ */

    /** An OP already raised for this case/beneficiary pair. */
    private function existingOp(int $caseId, ?int $beneficiaryId): bool
    {
        return SurveyOpRecord::where('survey_comp_case_id', $caseId)
            ->when($beneficiaryId, fn ($q) => $q->where('survey_beneficiary_id', $beneficiaryId))
            ->when(!$beneficiaryId, fn ($q) => $q->whereNull('survey_beneficiary_id'))
            ->exists();
    }

    /**
     * What the permit points at. Monetary Compensation carries cash and no plot schedule;
     * Land to Land carries the plot schedule and no cash. Returning a null ref
     * means there is nothing to print yet, which keeps the OP in Draft.
     *
     * @return array{ref: ?string, cash: ?float}
     */
    private function reference(SurveyCompCase $case, ?SurveyBeneficiary $beneficiary): array
    {
        if ($case->scheme_type === SurveyProject::SCHEME_MONETARY) {
            $cash = (float) $case->trees()->sum('line_total');

            return $cash > 0
                ? ['ref' => 'Cash ₦' . number_format($cash, 2), 'cash' => $cash]
                : ['ref' => null, 'cash' => null];
        }

        $plots = $this->plotSchedule($case, $beneficiary);

        return ['ref' => $plots ? 'Plots ' . $plots : null, 'cash' => null];
    }

    /**
     * Plot numbers from the case's allocation schedule, preferring the rows
     * that name this beneficiary and falling back to the whole case.
     */
    private function plotSchedule(SurveyCompCase $case, ?SurveyBeneficiary $beneficiary): ?string
    {
        $base = fn () => $case->plots()
            ->whereNotNull('plot_no')
            ->where('plot_no', '<>', '')
            ->orderBy('sr')
            ->orderBy('id');

        $numbers = collect();

        if ($beneficiary && trim((string) $beneficiary->full_name) !== '') {
            $numbers = $base()
                ->whereRaw('LOWER(farmer_name) = ?', [mb_strtolower(trim($beneficiary->full_name))])
                ->pluck('plot_no');
        }

        if ($numbers->isEmpty()) {
            $numbers = $base()->pluck('plot_no');
        }

        if ($numbers->isEmpty()) return null;

        $text = $this->compressRuns($numbers);

        // plot_or_cash_ref is nvarchar(255); a long schedule is truncated, not rejected.
        return mb_strlen($text) > 250 ? mb_substr($text, 0, 247) . '…' : $text;
    }

    /** "1,2,3,7" -> "1–3, 7". Non-numeric entries are left alone. */
    private function compressRuns(Collection $values): string
    {
        $out = [];
        $run = [];

        $flush = function () use (&$out, &$run) {
            if (!$run) return;
            $out[] = count($run) > 1 ? $run[0] . '–' . end($run) : $run[0];
            $run = [];
        };

        foreach ($values as $v) {
            $v = trim((string) $v);
            if ($v === '') continue;

            $prev = $run ? end($run) : null;
            if ($prev !== null && is_numeric($v) && is_numeric($prev) && (int) $v === (int) $prev + 1) {
                $run[] = $v;
                continue;
            }

            $flush();
            $run = [$v];
        }
        $flush();

        return implode(', ', $out);
    }

    /**
     * Eligible case/beneficiary pairs with no permit yet, each carrying a
     * preview of the reference the permit would be given.
     */
    private function candidates(): Collection
    {
        $rows = collect();

        $beneficiaries = SurveyBeneficiary::with('case')
            ->whereHas('case', fn ($c) => $c->whereIn('status', self::READY_CASE_STATUSES)->newInKlaes())
            ->whereNotIn('id', SurveyOpRecord::whereNotNull('survey_beneficiary_id')->select('survey_beneficiary_id'))
            ->orderByDesc('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        foreach ($beneficiaries as $b) {
            $ref = $this->reference($b->case, $b);
            $rows->push([
                'key'         => 'b:' . $b->id,
                'case'        => $b->case,
                'beneficiary' => $b->full_name,
                'ref'         => $ref['ref'],
            ]);
        }

        // Cases registered without a named beneficiary still need a permit.
        $cases = SurveyCompCase::whereIn('status', self::READY_CASE_STATUSES)
            ->newInKlaes()   // existing records (imported or captured from paper) are never offered (CR 30102026 item 32)
            ->whereDoesntHave('beneficiaries')
            ->whereDoesntHave('opRecords')
            ->orderByDesc('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        foreach ($cases as $c) {
            $ref = $this->reference($c, null);
            $rows->push([
                'key'         => 'c:' . $c->id,
                'case'        => $c,
                'beneficiary' => null,
                'ref'         => $ref['ref'],
            ]);
        }

        return $rows;
    }
}
