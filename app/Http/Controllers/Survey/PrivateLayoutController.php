<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyPrivateLayout as PL;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GKN → Public Survey → Private Layout (user, 2026-10-30): register of privately owned
 * layouts. The entry modal doubles as the edit form (?edit=<id>), as on Public
 * Layout Plans.
 */
class PrivateLayoutController extends Controller
{
    public function index(Request $r)
    {
        $q = PL::query()->with('creator:id,first_name,last_name');
        if (($term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')))) !== '') {
            $q->where(fn ($w) => $w->where('layout_ref', 'like', "%{$term}%")->orWhere('layout_no', 'like', "%{$term}%")
                ->orWhere('layout_name', 'like', "%{$term}%")->orWhere('owner_name', 'like', "%{$term}%")
                ->orWhere('surveyor_name', 'like', "%{$term}%")->orWhere('prop_district', 'like', "%{$term}%"));
        }
        if (in_array($r->query('status'), PL::STATUSES, true)) $q->where('status', $r->query('status'));
        if ($lu = $r->query('land_use')) $q->where('land_use', $lu);
        if ($lga = $r->query('lga')) $q->where('prop_lga', 'like', "%{$lga}%");

        return view('survey_module.records.private_layouts', [
            'layouts' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'editing' => $r->query('edit') ? PL::find($r->query('edit')) : null,
            'stats'   => [
                'total'    => PL::count(),
                'approved' => PL::where('status', 'Approved')->count(),
                'pending'  => PL::whereIn('status', ['Submitted', 'Under Review'])->count(),
                'plots'    => (int) PL::sum('plot_count'),
            ],
            'nextRef' => PL::nextRef('layout_ref', 'PVL', 4),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $layout = PL::create($data + ['layout_ref' => PL::nextRef('layout_ref', 'PVL', 4)]);
        $this->savePlan($r, $layout);

        return redirect()->route('survey-module.records.private-layouts')->with('success', "Private layout {$layout->layout_name} added.");
    }

    public function update(Request $r, PL $layout)
    {
        $layout->update($this->validated($r, $layout));
        $this->savePlan($r, $layout);

        return redirect()->route('survey-module.records.private-layouts')->with('success', "Private layout {$layout->layout_name} updated.");
    }

    public function destroy(PL $layout)
    {
        $layout->delete();

        return back()->with('success', "Private layout {$layout->layout_name} deleted.");
    }

    public function plan(PL $layout)
    {
        abort_unless($layout->plan_path && Storage::disk(PL::DISK)->exists($layout->plan_path), 404);

        return Storage::disk(PL::DISK)->response($layout->plan_path, $layout->plan_name ?: basename($layout->plan_path));
    }

    private function validated(Request $r, ?PL $existing = null): array
    {
        $data = $r->validate([
            'layout_no'             => 'nullable|string|max:100',
            'layout_name'           => 'required|string|max:255',
            'owner_name'            => 'required|string|max:255',
            'owner_phone'           => 'nullable|string|max:50',
            'surveyor_name'         => 'nullable|string|max:255',
            'surveyor_registration' => 'nullable|string|max:100',
            'plot_count'            => 'nullable|integer|min:1|max:1000000',
            'area_ha'               => 'nullable|numeric|min:0|max:9999999',
            'land_use'              => ['nullable', Rule::in(array_merge(PL::LAND_USES, [PL::OTHER]))],
            'land_use_other'        => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('land_use') === PL::OTHER)],
            'status'                => ['required', Rule::in(PL::STATUSES)],
            'submitted_on'          => 'nullable|date|before_or_equal:today',
            'approved_on'           => ['nullable', 'date', 'before_or_equal:today', Rule::requiredIf(fn () => $r->input('status') === 'Approved')],
            'remarks'               => 'nullable|string|max:2000',
            'plan'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ] + AddressBuilder::rules('prop_'), [
            'layout_name.required'    => 'Give the layout name.',
            'owner_name.required'     => 'Give the owner or developer.',
            'land_use_other.required' => 'Specify the land use.',
            'approved_on.required'    => 'An approved layout needs its approval date.',
            'prop_district.required'  => 'The district is required.',
            'prop_lga.required'       => 'The LGA is required.',
            'plan.mimes'              => 'The plan must be a PDF, JPG or PNG.',
        ]);
        unset($data['plan']);

        // Names and places: spacing tidied, case kept as typed. Plan numbers are identifiers: trimmed only.
        foreach (['layout_name', 'owner_name', 'surveyor_name', 'land_use_other', 'remarks'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/\s+/', ' ', $data[$k])) ?: null;
        }
        foreach (['layout_no', 'owner_phone', 'surveyor_registration'] as $k) {
            if (isset($data[$k])) $data[$k] = trim($data[$k]) ?: null;
        }
        if (($data['land_use'] ?? null) !== PL::OTHER) $data['land_use_other'] = null;

        if (! empty($data['layout_no'])) {
            $taken = PL::where('layout_no', $data['layout_no'])->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
            if ($taken) throw ValidationException::withMessages(['layout_no' => "Layout {$data['layout_no']} is already registered."]);
        }

        return $data;
    }

    private function savePlan(Request $r, PL $layout): void
    {
        if (! $r->hasFile('plan')) return;
        $file = $r->file('plan');
        $path = Storage::disk(PL::DISK)->putFileAs("app/survey/private-layouts/{$layout->id}", $file, Str::uuid() . '.' . strtolower($file->extension() ?: 'pdf'));
        $layout->update(['plan_path' => $path, 'plan_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'plan_mime' => $file->getMimeType()]);
    }
}
