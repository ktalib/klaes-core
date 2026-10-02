<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralSurveyor;
use App\Services\Cadastral\CadastralAddress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The registered surveyor and firm directory (concept note 4.3c).
 *
 * Distinct from cadastral_officers, which is Ministry staff. This is the
 * profession — the people an Instruction to Surveyor can be addressed to.
 *
 * The address uses the addr_* builder group and renders as
 * "Street, Plot, District, LGA, State".
 */
class SurveyorController extends Controller
{
    public function index(Request $r)
    {
        $q = CadastralSurveyor::query()->withCount('jobs');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('full_name', 'like', "%$term%")
                  ->orWhere('firm_name', 'like', "%$term%")
                  ->orWhere('surcon_number', 'like', "%$term%");
            });
        }
        if ($s = $r->query('licence_status')) $q->where('licence_status', $s);

        $surveyors = $q->orderBy('full_name')->paginate(15)->withQueryString();

        $stats = [
            'total'     => CadastralSurveyor::count(),
            'active'    => CadastralSurveyor::where('licence_status', 'Active')->where('is_active', true)->count(),
            'suspended' => CadastralSurveyor::whereIn('licence_status', ['Suspended', 'Struck Off'])->count(),
            'firms'     => CadastralSurveyor::whereNotNull('firm_name')->distinct('firm_name')->count('firm_name'),
        ];

        // ?edit=ID turns the add form into an edit form for that surveyor, so the
        // address builder re-populates from the saved columns.
        $editing = $r->query('edit') ? CadastralSurveyor::find($r->query('edit')) : null;

        return view('cadastral_module.information.surveyors', compact('surveyors', 'stats', 'editing'));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $surveyor = CadastralSurveyor::create($data);

        return back()->with('success', "{$surveyor->full_name} added to the surveyor directory.");
    }

    public function update(Request $r, CadastralSurveyor $surveyor)
    {
        $surveyor->update($this->validated($r, $surveyor));

        // From the edit form, back to the plain list rather than the ?edit= form
        // just submitted; the row's suspend button keeps the list's filters.
        $to = $r->boolean('from_edit')
            ? redirect()->route('cadastral-module.surveyors.index')
            : back();

        return $to->with('success', "{$surveyor->full_name} updated.");
    }

    /**
     * A surveyor with jobs against them is deactivated, not deleted — the jobs
     * snapshot the name, but the directory row is what an audit follows back.
     */
    public function destroy(CadastralSurveyor $surveyor)
    {
        if ($surveyor->jobs()->exists()) {
            return back()->with('error',
                "{$surveyor->full_name} has {$surveyor->jobs()->count()} survey job(s) and cannot be deleted. Set the licence status instead.");
        }

        $name = $surveyor->full_name;
        $surveyor->delete();

        return back()->with('success', "{$name} removed from the directory.");
    }

    private function validated(Request $r, ?CadastralSurveyor $existing = null): array
    {
        $rules = [
            'surcon_number'      => [
                'nullable', 'string', 'max:50',
                Rule::unique('sqlsrv.cadastral_surveyors', 'surcon_number')
                    ->ignore($existing?->id)
                    ->whereNull('deleted_at'),
            ],
            'full_name'          => 'required|string|max:255',
            'firm_name'          => 'nullable|string|max:255',
            'firm_rc_no'         => 'nullable|string|max:50',
            'phone'              => 'nullable|string|max:50',
            'email'              => 'nullable|email|max:255',
            'licence_status'     => ['required', Rule::in(CadastralSurveyor::LICENCE_STATUSES)],
            'licence_expires_on' => 'nullable|date',
            'is_active'          => 'nullable|boolean',
        ] + CadastralAddress::rules('addr_', false);

        // The suspend/reinstate button posts no address; normalise() leaves
        // absent keys alone, so that update cannot blank the address.
        $data = CadastralAddress::normalise(
            $r->validate($rules, CadastralAddress::messages('addr_')),
            'addr_'
        );

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
