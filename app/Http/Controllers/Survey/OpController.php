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
 * is decided by the case's scheme and is always recomputed here: a Land-for-Land
 * OP carries the plot schedule, a Monetary OP carries the cash figure. The two
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
        $q = SurveyOpRecord::query()->with(['case', 'beneficiary', 'steps']);

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
            'total'  => SurveyOpRecord::count(),
            'draft'  => SurveyOpRecord::where('status', self::STATUS_DRAFT)->count(),
            'ready'  => SurveyOpRecord::where('status', self::STATUS_READY)->count(),
            'issued' => SurveyOpRecord::where('status', self::STATUS_ISSUED)->count(),
        ];

        return view('survey_module.compensation.op', [
            'ops'        => $ops,
            'stats'      => $stats,
            'candidates' => $this->candidates(),
        ]);
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
            if ($this->existingOp($case->id, $beneficiary?->id)) {
                $skipped[] = "$label (already has an OP)";
                continue;
            }

            $ref = $this->reference($case, $beneficiary);

            $op = SurveyOpRecord::create([
                'op_number'             => SurveyOpRecord::nextRef('op_number', 'OP'),
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
     * What the permit points at. Monetary carries cash and no plot schedule;
     * Land-for-Land carries the plot schedule and no cash. Returning a null ref
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
            ->whereHas('case', fn ($c) => $c->whereIn('status', self::READY_CASE_STATUSES))
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
