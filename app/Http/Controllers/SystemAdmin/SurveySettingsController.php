<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use App\Services\Survey\SurveySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Configurable Entities > Survey (CR 30102026, user 2026-10-31): the sharing ratios
 * (item 11), the Classmark (item 8), the land rates (item 5) and the beacon prefixes
 * (Issue Beacon No). Saves only; the tab itself is drawn by ConfigurableEntriesController.
 */
class SurveySettingsController extends Controller
{
    use RunsWorkflowActions;

    /** URL list name => table. */
    private const LISTS = [
        'ratios'          => 'survey_sharing_ratios',
        'classmarks'      => 'survey_classmarks',
        'land-rates'      => 'survey_land_rates',
        'beacon-prefixes' => 'survey_beacon_prefixes',
        'companies'       => 'survey_companies',
        'survey-types'    => 'survey_types',
    ];

    /** Plots a Classmark range may give: nothing, quarter, half, three-quarter, one. */
    public const CLASSMARK_PLOTS = ['0' => 'Nothing', '0.25' => '¼ plot', '0.5' => '½ plot', '0.75' => '¾ plot', '1' => '1 full plot'];

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    public function save(Request $r, string $list, ?int $id = null)
    {
        $this->authorizeAdmin();
        $table = self::LISTS[$list] ?? abort(404);
        $values = match ($list) {
            'ratios'          => $this->ratioValues($r, $id),
            'classmarks'      => $this->classmarkValues($r, $id),
            'land-rates'      => $this->landRateValues($r, $id),
            'beacon-prefixes' => $this->prefixValues($r, $id),
            'companies'       => $this->companyValues($r, $id),
            'survey-types'    => $this->surveyTypeValues($r, $id),
        };

        return $this->attempt(function () use ($table, $id, $values, $list) {
            $row = $id ? $this->db()->table($table)->where('id', $id)->first() : null;
            if ($id && ! $row) throw WorkflowGuardException::because('That entry no longer exists. Reload the page.');

            $values += ['updated_by' => Auth::id(), 'updated_at' => now()];
            $this->db()->transaction(function () use ($table, $id, $values) {
                // One default at a time (sharing ratio, land rate).
                if (! empty($values['is_default'])) {
                    $this->db()->table($table)->when($id, fn ($q) => $q->where('id', '<>', $id))->update(['is_default' => 0]);
                }
                $id ? $this->db()->table($table)->where('id', $id)->update($values)
                    : $this->db()->table($table)->insert($values + ['is_active' => 1, 'created_at' => now()]);
            });
            Log::info('Configurable Entries: Survey setting saved', ['list' => $list, 'id' => $id, 'before' => $row ? (array) $row : null, 'after' => $values, 'by' => Auth::id()]);

            return 'Saved.';
        }, 'Saved.');
    }

    /** Switch an entry on or off. The general sharing ratio cannot be switched off. */
    public function toggle(string $list, int $id)
    {
        $this->authorizeAdmin();
        $table = self::LISTS[$list] ?? abort(404);

        return $this->attempt(function () use ($table, $id, $list) {
            $row = $this->db()->table($table)->where('id', $id)->first();
            if (! $row) throw WorkflowGuardException::because('That entry no longer exists. Reload the page.');
            if ($row->is_active && ! empty($row->is_default)) {
                throw WorkflowGuardException::because('This is the default. Make another entry the default first.');
            }
            $this->db()->table($table)->where('id', $id)->update(['is_active' => $row->is_active ? 0 : 1, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            Log::info('Configurable Entries: Survey setting switched', ['list' => $list, 'id' => $id, 'is_active' => ! $row->is_active, 'by' => Auth::id()]);

            return $row->is_active ? 'Switched off. It is no longer offered; records that use it keep it.' : 'Switched back on.';
        }, 'Updated.');
    }

    /** Delete an entry nothing uses; one in use can only be switched off. */
    public function delete(string $list, int $id)
    {
        $this->authorizeAdmin();
        $table = self::LISTS[$list] ?? abort(404);

        return $this->attempt(function () use ($table, $id, $list) {
            $row = $this->db()->table($table)->where('id', $id)->first();
            if (! $row) throw WorkflowGuardException::because('That entry no longer exists. Reload the page.');
            if (! empty($row->is_default)) throw WorkflowGuardException::because('This is the default. Make another entry the default first.');

            $uses = match ($list) {
                'ratios' => $this->db()->table('survey_projects')->where('farmer_share_pct', $row->farmer_pct)->whereNull('deleted_at')->count(),
                'beacon-prefixes' => $this->db()->getSchemaBuilder()->hasTable('survey_beacon_issues')
                    ? $this->db()->table('survey_beacon_issues')->where('prefix', $row->prefix)->count() : 0,
                'companies' => $this->db()->table('survey_beacon_register')->where('company_id', $id)->whereNull('deleted_at')->count(),
                'survey-types' => $this->db()->table('survey_its')->where('survey_type', $row->name)->count()
                    + $this->db()->table('cadastral_survey_jobs')->where('survey_type', $row->name)->count(),
                default => 0,
            };
            if ($uses) throw WorkflowGuardException::because("In use on {$uses} record(s). Switch it off instead.");

            $this->db()->table($table)->where('id', $id)->delete();
            Log::info('Configurable Entries: Survey setting deleted', ['list' => $list, 'row' => (array) $row, 'by' => Auth::id()]);

            return 'Deleted.';
        }, 'Deleted.');
    }

    private function ratioValues(Request $r, ?int $id): array
    {
        $d = $r->validate([
            'farmer_pct' => ['required', 'integer', 'min:1', 'max:99',
                Rule::unique('sqlsrv.survey_sharing_ratios', 'farmer_pct')->ignore($id)],
            'is_default' => 'nullable|boolean',
        ], [
            'farmer_pct.required' => 'Enter the farmer\'s share in per cent.',
            'farmer_pct.unique'   => 'That ratio is already in the list.',
        ]);

        return ['farmer_pct' => (int) $d['farmer_pct'], 'govt_pct' => 100 - (int) $d['farmer_pct'], 'is_default' => (int) (bool) ($d['is_default'] ?? false)]
            + (! empty($d['is_default']) ? ['is_active' => 1] : []);
    }

    private function classmarkValues(Request $r, ?int $id): array
    {
        $d = $r->validate([
            // Up to 1.00: Survey's last range is 0.60 to 1.00 (user, 2026-11-03).
            'from_fraction' => 'required|numeric|min:0|max:1',
            'to_fraction'   => 'required|numeric|min:0|max:1|gte:from_fraction',
            'plots'         => ['required', Rule::in(array_keys(self::CLASSMARK_PLOTS))],
        ], [
            'to_fraction.gte' => 'The range must end at or after where it starts.',
        ]);
        $from = round((float) $d['from_fraction'], 2);
        $to = round((float) $d['to_fraction'], 2);

        // Ranges may not overlap, or a part plot would have two readings.
        $clash = SurveySettings::classmarks()->first(fn ($c) => (int) $c->id !== (int) $id
            && $from <= (float) $c->to_fraction + 1e-9 && $to >= (float) $c->from_fraction - 1e-9);
        if ($clash) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from_fraction' => sprintf('That range overlaps .%02d–.%02d. Change or switch that one off first.', round($clash->from_fraction * 100), round($clash->to_fraction * 100))]);
        }

        return ['from_fraction' => $from, 'to_fraction' => $to, 'plots' => (float) $d['plots']];
    }

    private function landRateValues(Request $r, ?int $id): array
    {
        $d = $r->validate([
            'name'       => 'required|string|max:100',
            'rate'       => 'required|numeric|gt:0|max:9999999999999',
            'unit'       => ['required', Rule::in(array_keys(SurveySettings::LAND_UNITS))],
            'is_default' => 'nullable|boolean',
        ], [
            'name.required' => 'Name the rate, e.g. Farmland.',
            'rate.gt'       => 'The rate must be more than zero.',
        ]);
        // A label: spacing tidied, first letter capitalised, the rest as typed.
        $name = trim(preg_replace('/\s+/u', ' ', $d['name']));
        $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
        $clash = $this->db()->table('survey_land_rates')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->where('unit', $d['unit'])
            ->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
        if ($clash) throw \Illuminate\Validation\ValidationException::withMessages(['name' => "\"{$name}\" " . SurveySettings::LAND_UNITS[$d['unit']] . ' is already in the list.']);

        return ['name' => $name, 'rate' => round((float) $d['rate'], 2), 'unit' => $d['unit'], 'is_default' => (int) (bool) ($d['is_default'] ?? false)]
            + (! empty($d['is_default']) ? ['is_active' => 1] : []);
    }

    /** A survey type. "Other" is the form's own option, so it cannot be a list entry. */
    private function surveyTypeValues(Request $r, ?int $id): array
    {
        $d = $r->validate([
            'name' => ['required', 'string', 'max:100', 'not_in:Other,other,OTHER', Rule::unique('sqlsrv.survey_types', 'name')->ignore($id)],
        ], ['name.unique' => 'That survey type is already in the list.', 'name.not_in' => 'Other is already offered on the form.']);

        return ['name' => preg_replace('/\s+/', ' ', trim($d['name']))];
    }

    /** A private survey company: a name, its beacon letters (one company to a set of letters) and its surveyor. */
    private function companyValues(Request $r, ?int $id): array
    {
        $r->merge(['beacon_letters' => strtoupper(trim((string) $r->input('beacon_letters'))) ?: null]);
        $d = $r->validate([
            'name'           => ['required', 'string', 'max:255', Rule::unique('sqlsrv.survey_companies', 'name')->ignore($id)],
            'beacon_letters' => ['nullable', 'regex:/^[A-Z]{1,3}$/', Rule::unique('sqlsrv.survey_companies', 'beacon_letters')->ignore($id)],
            'surveyor_name'  => 'nullable|string|max:255',
        ], [
            'name.unique'           => 'That company is already in the list.',
            'beacon_letters.regex'  => 'Letters only, at most three, e.g. G or AB.',
            'beacon_letters.unique' => 'Another company already has those letters.',
        ]);

        return ['name' => preg_replace('/\s+/', ' ', trim($d['name'])), 'beacon_letters' => $d['beacon_letters'] ?? null,
                'surveyor_name' => preg_replace('/\s+/', ' ', trim((string) ($d['surveyor_name'] ?? ''))) ?: null];
    }

    private function prefixValues(Request $r, ?int $id): array
    {
        $d = $r->validate([
            // Letters only: a beacon number is the prefix, then the serial digits (SurveyBeaconRegister::parseBeacon).
            'prefix'      => ['required', 'string', 'max:20', 'regex:/^[A-Za-z\/-]+$/'],
            'description' => 'nullable|string|max:255',
            // Government or Private (user, 2026-11-03); blank = offered for both.
            'beacon_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Survey\SurveyBeaconRegister::LAYOUT_TYPES))],
        ], [
            'prefix.regex' => 'A prefix is letters only, e.g. PBB: the serial number follows it.',
        ]);
        // An identifier: upper case, no spaces.
        $prefix = mb_strtoupper(trim($d['prefix']));
        $clash = $this->db()->table('survey_beacon_prefixes')->whereRaw('UPPER(prefix) = ?', [$prefix])
            ->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
        if ($clash) throw \Illuminate\Validation\ValidationException::withMessages(['prefix' => "{$prefix} is already in the list."]);

        return ['prefix' => $prefix, 'description' => trim((string) ($d['description'] ?? '')) ?: null, 'beacon_type' => $d['beacon_type'] ?? null];
    }

    /** As ConfigurableEntriesController: Super Admin or the System Settings role. */
    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        $roles = array_map(fn ($r) => strtolower(trim((string) $r)), $user?->assignedRoleNames() ?? []);

        abort_unless($user && ($user->isSuperAdmin() || in_array('system settings', $roles, true)), 403, 'Configurable Entries needs the System Settings role.');
    }
}
