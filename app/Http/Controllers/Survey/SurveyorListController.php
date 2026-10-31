<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorListEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Survey > GKN > Surveyors: the Survey Department staff list (surveyors_list),
 * loaded from Surveyor.xlsx on 2026-10-22 (user).
 *
 * One page like the Beacon Register: the slide-open form doubles as the edit
 * form via `?edit=<id>`. Staff are deactivated, never deleted, because Beacon
 * Register lines can point at them.
 */
class SurveyorListController extends Controller
{
    public function index(Request $r)
    {
        // Government (this staff list) / Private tabs (CR 30102026 item 15).
        if ($r->query('tab') === 'private') {
            return $this->privateSurveyors($r);
        }

        $q = SurveyorListEntry::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('first_name', 'like', "%$term%")
                ->orWhere('surname', 'like', "%$term%")
                ->orWhere('other_name', 'like', "%$term%")
                ->orWhere('staff_id', 'like', "%$term%")
                ->orWhere('phone', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%")
                ->orWhere('rank', 'like', "%$term%"));
        }
        if ($rank = $r->query('rank')) {
            $q->where('rank', $rank);
        }
        if ($r->query('status') === 'active') {
            $q->where('is_active', true);
        } elseif ($r->query('status') === 'inactive') {
            $q->where('is_active', false);
        }

        $staff = $q->orderBy('sn')->orderBy('id')->paginate(25)->withQueryString();

        $stats = [
            'total'     => SurveyorListEntry::count(),
            'active'    => SurveyorListEntry::where('is_active', true)->count(),
            'with_id'   => SurveyorListEntry::whereNotNull('staff_id')->count(),
            'sltr'      => SurveyorListEntry::where('rank', 'SLTR')->count(),
        ];

        return view('survey_module.records.surveyors', [
            'staff'   => $staff,
            'stats'   => $stats,
            'ranks'   => SurveyorListEntry::query()->whereNotNull('rank')->distinct()->orderBy('rank')->pluck('rank'),
            'editing' => $r->filled('edit') ? SurveyorListEntry::find($r->query('edit')) : null,
        ]);
    }

    /** Private tab: licensed surveyors in the Cadastral register that are not Survey staff copies. Read-only here. */
    private function privateSurveyors(Request $r)
    {
        $q = \App\Models\Cadastral\CadastralSurveyor::query()->whereNull('surveyors_list_id');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('full_name', 'like', "%$term%")
                ->orWhere('surcon_number', 'like', "%$term%")
                ->orWhere('firm_name', 'like', "%$term%")
                ->orWhere('phone', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%"));
        }

        return view('survey_module.records.surveyors_private', [
            'surveyors' => $q->orderBy('full_name')->paginate(25)->withQueryString(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['sn'] = (int) SurveyorListEntry::max('sn') + 1;
        $data['source_sheet'] = 'KLAES';
        $data['is_active'] = true;
        $s = SurveyorListEntry::create($data);
        $this->syncCadastral($s);

        return redirect()->route('survey-module.records.surveyors.index')
            ->with('success', "{$s->full_name} added to the staff list.");
    }

    public function update(Request $r, SurveyorListEntry $surveyor)
    {
        $surveyor->update($this->validated($r, $surveyor));
        $this->syncCadastral($surveyor);

        return redirect()->route('survey-module.records.surveyors.index', $r->only(['q', 'rank', 'status', 'page']))
            ->with('success', "{$surveyor->full_name} updated.");
    }

    public function toggle(SurveyorListEntry $surveyor)
    {
        $surveyor->update(['is_active' => ! $surveyor->is_active]);
        $this->syncCadastral($surveyor);

        return back()->with('success', $surveyor->full_name . ($surveyor->is_active ? ' reactivated.' : ' deactivated.'));
    }

    /** Keep the staff member's Cadastral Surveyors row in step (user, 2026-10-27). Never blocks the save here. */
    private function syncCadastral(SurveyorListEntry $staff): void
    {
        try {
            app(\App\Services\Cadastral\SurveyStaffSync::class)->syncOne($staff->fresh());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $r, ?SurveyorListEntry $current = null): array
    {
        // Whitespace only: names and identifiers keep the case they were typed in.
        foreach (['staff_id', 'first_name', 'surname', 'other_name', 'rank', 'rank_other', 'unit', 'phone', 'email'] as $f) {
            $v = preg_replace('/\s+/', ' ', trim((string) $r->input($f)));
            $r->merge([$f => $v === '' ? null : $v]);
        }

        // Rank is a dropdown of the ranks already on the list, plus Other.
        // An "Other" that matches an existing rank (any case) reuses that spelling.
        if ($r->input('rank') === 'other') {
            $r->validate(['rank_other' => 'required|string|max:150'], ['rank_other.required' => 'Please specify the rank.']);
            $typed = $r->input('rank_other');
            $existing = SurveyorListEntry::query()->whereNotNull('rank')->distinct()->pluck('rank')
                ->first(fn ($x) => mb_strtoupper($x) === mb_strtoupper($typed));
            $r->merge(['rank' => $existing ?? $typed]);
        }

        return $r->validate([
            'staff_id'      => ['nullable', 'string', 'max:50',
                Rule::unique('sqlsrv.surveyors_list', 'staff_id')->ignore($current?->id)],
            'first_name'    => 'required|string|max:150',
            'surname'       => 'required|string|max:150',
            'other_name'    => 'nullable|string|max:150',
            'rank'          => 'nullable|string|max:150',
            'unit'          => 'nullable|string|max:100',
            'phone'         => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9 \-]{7,20}$/'],
            'email'         => 'nullable|email|max:255',
            'gender'        => ['nullable', Rule::in(SurveyorListEntry::GENDERS)],
            'date_of_birth' => 'nullable|date|before:today',
            'grade_level'   => 'nullable|integer|min:1|max:17',
        ], [
            'staff_id.unique' => 'Another staff member already has this Staff I.D.',
            'phone.regex'     => 'Enter a valid phone number.',
        ]);
    }
}
