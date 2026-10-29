<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiCategory;
use App\Models\Sdi\SdiMapType;
use App\Models\Sdi\SdiMda;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SDI registry: the Kano MDAs that request maps and the map types the GIS unit
 * produces (ALAES's MDA registry and map types, on one page). Rows are retired
 * with is_active, never deleted, so old requests keep their requester and type.
 */
class SdiRegistryController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'types' ? 'types' : 'mdas';

        $mdas = SdiMda::with('category:id,name')->withCount('mapRequests')
            ->when(trim((string) $r->query('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('abbreviation', 'like', "%$t%")))
            ->when($r->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('category_id')->orderBy('sort_order')->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('survey_module.sdi.registry', [
            'tab'        => $tab,
            'mdas'       => $mdas,
            'categories' => SdiCategory::orderBy('sort_order')->get(['id', 'name']),
            'mapTypes'   => SdiMapType::withCount('requests')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeMda(Request $r)
    {
        $data = $this->mdaData($r);
        $data['code'] = $this->uniqueCode($data['abbreviation'] ?: $data['name']);
        $data['sort_order'] = (int) SdiMda::where('category_id', $data['category_id'])->max('sort_order') + 1;
        $mda = SdiMda::create($data + ['is_active' => true]);

        return back()->with('success', "{$mda->displayName()} added to the MDA registry.");
    }

    public function updateMda(Request $r, SdiMda $mda)
    {
        $mda->update($this->mdaData($r, $mda));

        return back()->with('success', "{$mda->displayName()} updated.");
    }

    public function toggleMda(SdiMda $mda)
    {
        $mda->update(['is_active' => ! $mda->is_active]);

        return back()->with('success', $mda->displayName() . ($mda->is_active ? ' restored.' : ' retired: it no longer appears on new requests.'));
    }

    public function storeType(Request $r)
    {
        $name = $this->typeName($r);
        SdiMapType::create(['name' => $name, 'sort_order' => (int) SdiMapType::max('sort_order') + 1, 'is_active' => true]);

        return redirect()->route('survey-module.sdi.registry', ['tab' => 'types'])->with('success', "Map type \"{$name}\" added.");
    }

    public function updateType(Request $r, SdiMapType $mapType)
    {
        $mapType->update(['name' => $this->typeName($r, $mapType)]);

        return redirect()->route('survey-module.sdi.registry', ['tab' => 'types'])->with('success', "Map type \"{$mapType->name}\" updated.");
    }

    public function toggleType(SdiMapType $mapType)
    {
        $mapType->update(['is_active' => ! $mapType->is_active]);

        return redirect()->route('survey-module.sdi.registry', ['tab' => 'types'])
            ->with('success', "Map type \"{$mapType->name}\" " . ($mapType->is_active ? 'restored.' : 'retired.'));
    }

    /* ------------------------------------------------------------------ */

    private function mdaData(Request $r, ?SdiMda $self = null): array
    {
        $data = $r->validate([
            'category_id'   => ['required', Rule::exists('sqlsrv.sdi_categories', 'id')],
            'name'          => 'required|string|max:300',
            'abbreviation'  => 'nullable|string|max:40',
            'contact_name'  => 'nullable|string|max:200',
            'contact_phone' => 'nullable|string|max:40',
            'contact_email' => 'nullable|email|max:255',
            'is_custodian'  => 'nullable|boolean',
        ]);

        $data['name'] = trim(preg_replace('/\s+/', ' ', $data['name']));
        // Abbreviations are acronyms: kept as typed, only trimmed and upper-cased.
        $data['abbreviation'] = ($a = strtoupper(trim((string) ($data['abbreviation'] ?? '')))) === '' ? null : $a;
        $data['contact_name'] = ($c = trim(preg_replace('/\s+/', ' ', (string) ($data['contact_name'] ?? '')))) === '' ? null : $c;
        foreach (['contact_phone', 'contact_email'] as $k) {
            $data[$k] = ($v = trim((string) ($data[$k] ?? ''))) === '' ? null : $v;
        }
        $data['is_custodian'] = (bool) ($data['is_custodian'] ?? false);

        // No second row for the same body, whatever the case or spacing.
        $dupe = SdiMda::withTrashed()->when($self, fn ($q) => $q->whereKeyNot($self->id))
            ->where(function ($w) use ($data) {
                $w->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])]);
                if ($data['abbreviation']) $w->orWhereRaw('UPPER(abbreviation) = ?', [$data['abbreviation']]);
            })->first();
        if ($dupe) {
            throw ValidationException::withMessages(['name' => "Already in the registry as {$dupe->displayName()}" . ($dupe->is_active ? '.' : ' (retired; restore it instead).')]);
        }

        return $data;
    }

    private function typeName(Request $r, ?SdiMapType $self = null): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $r->validate(['name' => 'required|string|max:150'])['name']));

        $dupe = SdiMapType::withTrashed()->when($self, fn ($q) => $q->whereKeyNot($self->id))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($dupe) {
            throw ValidationException::withMessages(['name' => "\"{$dupe->name}\" already exists" . ($dupe->is_active ? '.' : ' (retired; restore it instead).')]);
        }

        return $name;
    }

    private function uniqueCode(string $base): string
    {
        $code = Str::limit(Str::slug($base, '_'), 50, '') ?: 'mda';
        $try = $code;
        for ($i = 2; SdiMda::withTrashed()->where('code', $try)->exists(); $i++) $try = "{$code}_{$i}";

        return $try;
    }
}
