<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyComplaint;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compensation complaints (docs/KLAES-CORE Survey Module.md 2.4).
 *
 * One page: the register plus an inline panel that is both the create and the
 * edit form, as on Beneficiaries. Picking a beneficiary fills the complainant,
 * case and project from that record. Status only moves along SurveyComplaint::NEXT,
 * and a Resolved complaint can no longer be edited or deleted.
 */
class ComplaintController extends Controller
{
    /** The category dropdown's single "Other (specify)" value; never stored. */
    public const CATEGORY_OTHER = '__other__';

    public function index(Request $r)
    {
        return view('survey_module.compensation.complaints', $this->pageData($r));
    }

    public function edit(Request $r, SurveyComplaint $complaint)
    {
        if ($complaint->isFrozen()) {
            return redirect()->route('survey-module.compensation.complaints')
                ->with('error', "{$complaint->complaint_ref} is resolved and can no longer be edited.");
        }

        return view('survey_module.compensation.complaints', $this->pageData($r, $complaint));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['complaint_ref'] = SurveyComplaint::nextRef('complaint_ref', 'CMP');
        $data['status'] = SurveyComplaint::OPEN;

        $c = SurveyComplaint::create($data);

        return redirect()->route('survey-module.compensation.complaints')
            ->with('success', "Complaint {$c->complaint_ref} logged for {$c->complainant_name}.");
    }

    public function update(Request $r, SurveyComplaint $complaint)
    {
        if ($complaint->isFrozen()) {
            return back()->with('error', "{$complaint->complaint_ref} is resolved and can no longer be edited.");
        }

        $complaint->update($this->validated($r));

        return redirect()->route('survey-module.compensation.complaints')
            ->with('success', "Complaint {$complaint->complaint_ref} updated.");
    }

    /** Move along the status chain. Resolving needs a note of how it was resolved. */
    public function status(Request $r, SurveyComplaint $complaint)
    {
        $data = $r->validate([
            'status'     => ['required', Rule::in(SurveyComplaint::STATUSES)],
            'resolution' => 'nullable|string|max:4000',
        ]);

        if (! $complaint->canMoveTo($data['status'])) {
            return back()->with('error', "{$complaint->complaint_ref} is {$complaint->status}; it cannot move to {$data['status']}.");
        }

        $note = trim((string) ($data['resolution'] ?? ''));
        if (in_array($data['status'], [SurveyComplaint::RESOLVED, SurveyComplaint::ESCALATED], true) && $note === '') {
            throw ValidationException::withMessages([
                'resolution' => $data['status'] === SurveyComplaint::RESOLVED
                    ? 'Say how the complaint was resolved.'
                    : 'Say why the complaint is being escalated, and to whom.',
            ]);
        }

        $attrs = ['status' => $data['status']];
        if ($note !== '') {
            // Keep the trail: each step's note is appended, never overwritten.
            $stamp = now()->format('d M Y H:i') . ' · ' . (Auth::user()?->name ?? 'system') . ' · ' . $data['status'];
            $attrs['resolution'] = trim(($complaint->resolution ? $complaint->resolution . "\n" : '') . "[$stamp] $note");
        }
        if ($data['status'] === SurveyComplaint::RESOLVED) {
            $attrs['resolved_at'] = now();
            $attrs['resolved_by'] = Auth::id();
        }

        $complaint->update($attrs);

        return back()->with('success', "Complaint {$complaint->complaint_ref} is now {$data['status']}.");
    }

    public function destroy(SurveyComplaint $complaint)
    {
        if ($complaint->status !== SurveyComplaint::OPEN) {
            return back()->with('error', "{$complaint->complaint_ref} is {$complaint->status}; only an Open complaint logged in error can be deleted.");
        }

        $ref = $complaint->complaint_ref;
        $complaint->delete();

        return back()->with('success', "Complaint {$ref} deleted.");
    }

    /* ------------------------------------------------------------------ */

    private function pageData(Request $r, ?SurveyComplaint $editing = null): array
    {
        $q = SurveyComplaint::query()->with(['project', 'case', 'beneficiary']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('complaint_ref', 'like', "%$term%")
                  ->orWhere('complainant_name', 'like', "%$term%")
                  ->orWhere('complainant_phone', 'like', "%$term%")
                  ->orWhere('subject', 'like', "%$term%");
            });
        }
        if ($st = $r->query('status'))   $q->where('status', $st);
        if ($c = $r->query('category'))  $q->where('category', $c);
        if ($p = $r->query('project'))   $q->where('survey_project_id', $p);

        $stats = [];
        foreach (SurveyComplaint::STATUSES as $s) $stats[$s] = SurveyComplaint::where('status', $s)->count();

        $beneficiaries = SurveyBeneficiary::with('case.project')->orderBy('full_name')
            ->get(['id', 'full_name', 'phone', 'survey_comp_case_id']);

        return [
            'complaints'    => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats'         => $stats,
            'projects'      => SurveyProject::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'cases'         => SurveyCompCase::orderByDesc('id')->get(['id', 'case_ref', 'survey_project_id']),
            'beneficiaries' => $beneficiaries,
            'complaint'     => $editing ?? new SurveyComplaint(['received_date' => now()->toDateString()]),
            'categories'    => $this->categories(),
        ];
    }

    /** The listed categories plus any already used, so an edited record keeps its own. */
    private function categories(): array
    {
        $used = SurveyComplaint::distinct()->pluck('category')->all();

        return array_values(array_unique(array_merge(SurveyComplaint::CATEGORIES, $used)));
    }

    private function validated(Request $r): array
    {
        $data = $r->validate([
            'survey_beneficiary_id' => ['nullable', Rule::exists('sqlsrv.survey_beneficiaries', 'id')->whereNull('deleted_at')],
            'survey_comp_case_id'   => ['nullable', Rule::exists('sqlsrv.survey_comp_cases', 'id')->whereNull('deleted_at')],
            'survey_project_id'     => ['nullable', Rule::exists('sqlsrv.survey_projects', 'id')->whereNull('deleted_at')],
            'complainant_name'      => 'required|string|max:255',
            'complainant_phone'     => 'nullable|string|max:50',
            'category'              => 'required|string|max:100',
            'category_other'        => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('category') === self::CATEGORY_OTHER)],
            'subject'               => 'required|string|max:255',
            'description'           => 'nullable|string|max:4000',
            'received_date'         => 'required|date|before_or_equal:today',
        ], [
            'category_other.required'       => 'Please specify the category.',
            'received_date.before_or_equal' => 'The date received cannot be in the future.',
        ]);

        // "Other": the typed category is stored, but a listed one typed in another case wins.
        if ($data['category'] === self::CATEGORY_OTHER) {
            $typed = trim(preg_replace('/\s+/', ' ', (string) $data['category_other']));
            $match = collect($this->categories())->first(fn ($c) => mb_strtolower($c) === mb_strtolower($typed));
            $data['category'] = $match ?? $typed;
        }
        unset($data['category_other']);

        // The beneficiary's own case and project win over whatever was posted alongside.
        if (! empty($data['survey_beneficiary_id'])) {
            $b = SurveyBeneficiary::with('case')->find($data['survey_beneficiary_id']);
            if ($b?->case) {
                $data['survey_comp_case_id'] = $b->case->id;
                $data['survey_project_id'] = $b->case->survey_project_id;
            }
        } elseif (! empty($data['survey_comp_case_id'])) {
            $data['survey_project_id'] = SurveyCompCase::whereKey($data['survey_comp_case_id'])->value('survey_project_id');
        }

        foreach (['complainant_name', 'subject'] as $k) {
            $data[$k] = trim(preg_replace('/\s+/', ' ', $data[$k]));
        }

        return $data;
    }
}
