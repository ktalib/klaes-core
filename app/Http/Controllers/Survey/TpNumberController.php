<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyTpNumber as TP;
use App\Services\Survey\TpLookupSync;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Survey > Public Survey > TP Nos (user, 2026-10-30): the register of layout plan TP
 * numbers, listed by serial number. The entry modal doubles as the edit form (?edit=<id>),
 * as on Private Layout.
 */
class TpNumberController extends Controller
{
    public function index(Request $r, TpLookupSync $sync)
    {
        $sync->pullNew();   // TP numbers added to tp_lookups since the last visit show up by themselves

        $q = TP::query()->with('creator:id,first_name,last_name');
        if (($term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')))) !== '') {
            $q->where(fn ($w) => $w->where('tp_no', 'like', "%{$term}%")->orWhere('serial_no', 'like', "%{$term}%")
                ->orWhere('layout_name', 'like', "%{$term}%")->orWhere('prop_district', 'like', "%{$term}%")
                ->orWhere('prop_district_other', 'like', "%{$term}%"));
        }
        if ($p = $r->query('prefix')) $q->where('prefix', $p);
        if ($lga = $r->query('lga')) $q->where('prop_lga', 'like', "%{$lga}%");

        $tps = $q->serialOrder()->paginate(25)->withQueryString();

        // Projects that carry each TP No. on this page (matched on the number, ignoring case and spaces).
        $keys = $tps->getCollection()->mapWithKeys(fn ($t) => [$t->id => self::key($t->tp_no)]);
        $projects = $keys->isEmpty() ? collect() : SurveyProject::whereNotNull('plan_no')->get(['id', 'project_code', 'name', 'plan_no'])
            ->groupBy(fn ($p) => self::key($p->plan_no));

        return view('survey_module.records.tp_numbers', [
            'tps'      => $tps,
            'projects' => $projects,
            'editing'  => $r->query('edit') ? TP::find($r->query('edit')) : null,
            'prefixes' => TP::prefixOptions(),
            'purposes' => TP::purposeOptions(),
            'stats'    => [
                'total'    => TP::count(),
                'prefixes' => TP::distinct()->count('prefix'),
                'plots'    => (int) TP::sum('plot_count'),
                'linked'   => TP::whereIn('tp_no', SurveyProject::whereNotNull('plan_no')->pluck('plan_no'))->count(),
            ],
        ]);
    }

    public function store(Request $r)
    {
        $tp = TP::create($this->validated($r));

        return redirect()->route('survey-module.records.tp-numbers')->with('success', "TP No. {$tp->tp_no} added.");
    }

    public function update(Request $r, TP $tp)
    {
        $tp->update($this->validated($r, $tp));

        return redirect()->route('survey-module.records.tp-numbers')->with('success', "TP No. {$tp->tp_no} updated.");
    }

    public function destroy(TP $tp)
    {
        $tp->delete();

        return back()->with('success', "TP No. {$tp->tp_no} deleted.");
    }

    /** TP numbers compared without case or spaces. */
    public static function key(?string $tp): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', (string) $tp));
    }

    private function validated(Request $r, ?TP $existing = null): array
    {
        $data = $r->validate([
            'prefix'        => ['required', 'string', 'max:80'],
            'prefix_other'  => ['nullable', 'string', 'max:80', Rule::requiredIf(fn () => $r->input('prefix') === TP::OTHER)],
            'serial_no'     => ['required', 'string', 'max:40'],
            'layout_name'   => 'required|string|max:255',
            'land_use'       => ['nullable', Rule::in(array_merge(TP::LAND_USES, [TP::OTHER]))],
            'land_use_other' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('land_use') === TP::OTHER)],
            'purpose'        => 'nullable|string|max:150',
            'purpose_other'  => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => $r->input('purpose') === TP::OTHER)],
            'plot_count'    => 'nullable|integer|min:1|max:1000000',
            'area_ha'       => 'nullable|numeric|min:0|max:9999999',
            'approval_date' => 'nullable|date|before_or_equal:today',
            'remarks'       => 'nullable|string|max:2000',
        ] + AddressBuilder::rules('prop_', false), [
            'prefix.required'       => 'Choose the TP No. prefix.',
            'prefix_other.required' => 'Type the prefix.',
            'serial_no.required'    => 'Enter the serial number.',
            'layout_name.required'  => 'Give the layout name.',
            'land_use_other.required' => 'Type the land use.',
            'purpose_other.required'  => 'Type the purpose.',
        ]);

        // Land use: "Other" keeps the typed value beside it; a listed value clears it.
        $data['land_use_other'] = ($data['land_use'] ?? null) === TP::OTHER
            ? (trim(preg_replace('/\s+/', ' ', (string) $data['land_use_other'])) ?: null) : null;
        // Purpose: a typed "Other" is stored as the purpose, using a listed spelling if it is one already.
        if (($data['purpose'] ?? null) === TP::OTHER) {
            $typed = trim(preg_replace('/\s+/', ' ', (string) $data['purpose_other']));
            $known = collect(TP::purposeOptions())->first(fn ($p) => mb_strtoupper($p) === mb_strtoupper($typed));
            $data['purpose'] = $known ?? $typed;
        }
        unset($data['purpose_other']);

        // Identifiers keep their format (TP/KNUPDA, 338A): only spaces and stray slashes go. Names: spacing tidied, case kept.
        $prefix = $data['prefix'] === TP::OTHER ? (string) $data['prefix_other'] : $data['prefix'];
        $data['prefix'] = trim(preg_replace('/\s+/', '', $prefix), '/');
        $data['serial_no'] = trim(preg_replace('/\s+/', '', $data['serial_no']), '/');
        unset($data['prefix_other']);
        if ($data['prefix'] === '' || $data['serial_no'] === '') {
            throw ValidationException::withMessages(['serial_no' => 'Enter the prefix and the serial number.']);
        }
        foreach (['layout_name', 'purpose', 'remarks'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/\s+/', ' ', $data[$k])) ?: null;
        }

        $data['tp_no'] = TP::compose($data['prefix'], $data['serial_no']);
        $data['serial_sort'] = TP::serialSort($data['serial_no']);

        $taken = TP::whereRaw('UPPER(tp_no) = ?', [mb_strtoupper($data['tp_no'])])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['serial_no' => "TP No. {$data['tp_no']} is already on the register."]);
        }

        return $data;
    }
}
