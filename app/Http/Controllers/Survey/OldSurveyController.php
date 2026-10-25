<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOldSurvey as OS;
use App\Models\Survey\SurveyRegisterFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examination/Verification > Old Surveys (docs/KLAES-CORE Survey Module.md 5.3):
 * historical surveys checked for authenticity. Record the old survey (plan no., KN,
 * surveyor, date, where the record was found), then give the verdict: Authentic,
 * Not Authentic or Inconclusive, with findings. A verdict locks the record.
 */
class OldSurveyController extends Controller
{
    public const SOURCE_OTHER = '__other__';

    public function index(Request $r)
    {
        return view('survey_module.workflow.old_surveys', $this->pageData($r));
    }

    public function edit(Request $r, OS $old)
    {
        if ($old->isVerified()) {
            return redirect()->route('survey-module.workflow.old-surveys.index')
                ->with('error', "{$old->old_survey_ref} has a verdict and can no longer be edited.");
        }

        return view('survey_module.workflow.old_surveys', $this->pageData($r, $old));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $o = OS::create($data + ['old_survey_ref' => OS::nextRef('old_survey_ref', 'OS'), 'verification_status' => OS::PENDING]);

        return redirect()->route('survey-module.workflow.old-surveys.index')
            ->with('success', "Old survey {$o->old_survey_ref} recorded" . ($o->old_plan_no ? " (plan {$o->old_plan_no})" : '') . '. Give the verdict once checked.');
    }

    public function update(Request $r, OS $old)
    {
        if ($old->isVerified()) {
            return back()->with('error', "{$old->old_survey_ref} has a verdict and can no longer be edited.");
        }
        $old->update($this->validated($r));

        return redirect()->route('survey-module.workflow.old-surveys.index')->with('success', "{$old->old_survey_ref} updated.");
    }

    /** The verdict, with findings. Locks the record. */
    public function verify(Request $r, OS $old)
    {
        if ($old->isVerified()) {
            return back()->with('error', "{$old->old_survey_ref} already has a verdict: {$old->verification_status}.");
        }
        $data = $r->validate([
            'verification_status' => ['required', Rule::in([OS::AUTHENTIC, OS::NOT_AUTHENTIC, OS::INCONCLUSIVE])],
            'findings'            => 'required|string|max:4000',
        ], ['findings.required' => 'Record the findings that support the verdict.']);

        $old->update($data + ['verified_by' => Auth::id(), 'verified_at' => now()]);

        return back()->with('success', "{$old->old_survey_ref}: {$data['verification_status']}.");
    }

    public function destroy(OS $old)
    {
        if ($old->isVerified()) {
            return back()->with('error', "{$old->old_survey_ref} has a verdict and cannot be deleted.");
        }
        $ref = $old->old_survey_ref;
        $old->delete();

        return back()->with('success', "{$ref} deleted.");
    }

    /* ------------------------------------------------------------------ */

    private function pageData(Request $r, ?OS $editing = null): array
    {
        $q = OS::query()->with('registerFile');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('old_survey_ref', 'like', "%$term%")->orWhere('file_number', 'like', "%$term%")
                ->orWhere('old_plan_no', 'like', "%$term%")->orWhere('kn_number', 'like', "%$term%")
                ->orWhere('surveyor_name', 'like', "%$term%")->orWhere('district', 'like', "%$term%"));
        }
        if (in_array($s = $r->query('status'), OS::STATUSES, true)) $q->where('verification_status', $s);

        $stats = [];
        foreach (OS::STATUSES as $s) $stats[$s] = OS::where('verification_status', $s)->count();

        return [
            'olds'    => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats'   => $stats,
            'old'     => $editing ?? new OS(),
            'sources' => $this->sources(),
            'lines'   => SurveyRegisterFile::orderByDesc('id')->limit(300)->get(['id', 'register_ref', 'file_number', 'purpose']),
        ];
    }

    private function sources(): array
    {
        return array_values(array_filter(array_unique(array_merge(OS::RECORD_SOURCES, OS::distinct()->pluck('record_source')->all()))));
    }

    private function validated(Request $r): array
    {
        $data = $r->validate([
            'survey_register_file_id' => ['nullable', Rule::exists('sqlsrv.survey_register_files', 'id')->whereNull('deleted_at')],
            'file_number'   => 'nullable|string|max:100',
            'old_plan_no'   => 'nullable|string|max:100',
            'kn_number'     => 'nullable|string|max:100',
            'surveyor_name' => 'nullable|string|max:255',
            'survey_date'   => 'nullable|date|before_or_equal:today',
            'record_source' => 'nullable|string|max:100',
            'record_source_other' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('record_source') === self::SOURCE_OTHER)],
            'plot_no'       => 'nullable|string|max:100',
            'district'      => 'nullable|string|max:255',
            'lga'           => 'nullable|string|max:255',
        ], [
            'record_source_other.required' => 'Please specify where the record was found.',
            'survey_date.before_or_equal'  => 'The survey date cannot be in the future.',
        ]);

        if (blank($data['old_plan_no'] ?? null) && blank($data['kn_number'] ?? null) && blank($data['file_number'] ?? null)) {
            throw ValidationException::withMessages(['old_plan_no' => 'Give at least the old plan no., the KN or the file number.']);
        }

        if (($data['record_source'] ?? null) === self::SOURCE_OTHER) {
            $typed = trim(preg_replace('/\s+/', ' ', (string) $data['record_source_other']));
            $data['record_source'] = collect($this->sources())->first(fn ($s) => mb_strtolower($s) === mb_strtolower($typed)) ?? $typed;
        }
        unset($data['record_source_other']);

        // Plan and KN numbers keep their exact format; only stray spaces go.
        foreach (['old_plan_no', 'kn_number'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/\s+/', ' ', $data[$k])) ?: null;
        }
        if (! empty($data['survey_register_file_id']) && empty($data['file_number'])) {
            $line = SurveyRegisterFile::find($data['survey_register_file_id']);
            $data['file_number'] = $line?->file_number;
            foreach (['plot_no', 'district', 'lga'] as $k) $data[$k] = $data[$k] ?? $line?->{$k};
        }
        $data['location'] = implode(', ', array_filter([$data['district'] ?? null, $data['lga'] ?? null])) ?: null;

        return $data;
    }
}
