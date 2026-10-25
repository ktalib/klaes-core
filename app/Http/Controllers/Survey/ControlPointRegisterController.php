<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyControlPointNumber;
use App\Models\Survey\SurveyControlPointRegister;
use App\Models\Survey\SurveyControlPointRegisterFile;
use App\Models\Survey\SurveyorListEntry;
use App\Support\AddressBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Control Points register (Survey > Examination/Verification), a copy of the
 * Beacon Register (user transcript, 2026-10-25: "we already have this beacon
 * register, just copy it and call this one control points"). Kept in its own
 * tables (survey_control_point_*), so control point numbers never collide with
 * beacon numbers, and gated by Survey - Records rather than the Beacon Register role.
 *
 * Copied from BeaconRegisterController (clone per module): edit this copy, not
 * the original. Column names match the Beacon Register's; the screens say
 * "control point".
 */
class ControlPointRegisterController extends Controller
{
    /** The module role for this page: Survey - Records, like the other Examination pages. */
    public const MODULE = 'Survey - Records';

    /**
     * EnforceModulePermission only refuses delete for now and records the rest,
     * so the page checks its own actions, as StCofoTdpController does.
     */
    private function authorizeAction(string $action): void
    {
        abort_unless((bool) optional(Auth::user())->canDo(self::MODULE, $action), 403,
            "You do not have the {$action} permission on " . self::MODULE . '.');
    }

    public function index(Request $r)
    {
        $this->authorizeAction('view');
        $q = $this->filtered($r);
        $view = $r->query('view') === 'numbers' ? 'numbers' : 'issues';

        $entries = (clone $q)->with([
                'creator:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'auditor:id,first_name,last_name',
                'numbers:id,survey_control_point_register_id,beacon_no,beacon_seq',
                'files:id,survey_control_point_register_id,file_number,sort_order',
            ])
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        // "By beacon number": one row per expanded number, carrying its line's details.
        $numbers = null;
        if ($view === 'numbers') {
            $nq = SurveyControlPointNumber::query()
                ->whereIn('survey_control_point_register_id', (clone $q)->select('id'))
                ->with('register.files');
            $term = SurveyControlPointRegister::normalise($r->query('q'));
            if ($term && SurveyControlPointRegister::parseBeacon($term)) {
                $nq->where('beacon_no', 'like', "%$term%");
            }
            $numbers = $nq->orderBy('beacon_series')->orderBy('beacon_seq')->paginate(50)->withQueryString();
        }

        $filteredBeacons = (int) (clone $q)->sum('total_issued');
        $filteredAmount  = (float) (clone $q)->sum('amount');

        $stats = [
            'entries'        => SurveyControlPointRegister::count(),
            'beacons'        => SurveyControlPointNumber::count(),
            'this_month'     => (int) SurveyControlPointRegister::where('year', now()->year)->where('month', now()->month)->sum('total_issued'),
            'amount'         => (float) SurveyControlPointRegister::sum('amount'),
        ];

        $years = SurveyControlPointRegister::query()->distinct()->orderByDesc('year')->pluck('year');

        $editing = $r->filled('edit') ? SurveyControlPointRegister::find($r->query('edit')) : null;

        return view('survey_module.workflow.control_points', [
            'entries'         => $entries,
            'numbers'         => $numbers,
            'view'            => $view,
            'stats'           => $stats,
            'years'           => $years,
            'editing'         => $editing,
            'filteredBeacons' => $filteredBeacons,
            'filteredAmount'  => $filteredAmount,
            'pageBeacons'     => (int) $entries->getCollection()->sum('total_issued'),
            'surveyors'       => $this->surveyorOptions($editing),
        ]);
    }

    public function store(Request $r)
    {
        $this->authorizeAction('create');
        [$data, $range, $files] = $this->validated($r);

        $entry = $this->saving(function () use ($data, $range, $files) {
            $entry = SurveyControlPointRegister::create($data);
            $this->syncNumbers($entry, $range);
            $this->syncFiles($entry, $files);

            return $entry;
        });

        return redirect()->route('survey-module.workflow.control-points.index')
            ->with('success', "{$entry->total_issued} control point(s) {$entry->issued_range} recorded"
                . ($entry->surveyor_name ? " for {$entry->surveyor_name}." : '.'));
    }

    public function update(Request $r, SurveyControlPointRegister $entry)
    {
        $this->authorizeAction('edit');
        if ($entry->audited_at) {
            return back()->with('error', 'Audited entries are locked.');
        }
        [$data, $range, $files] = $this->validated($r, $entry);

        $this->saving(function () use ($entry, $data, $range, $files) {
            $entry->update($data);
            $this->syncNumbers($entry, $range);
            $this->syncFiles($entry, $files);
        });

        return redirect()->route('survey-module.workflow.control-points.index', $r->only(['q', 'layout', 'year', 'quarter', 'month', 'status', 'view', 'page']))
            ->with('success', "Entry {$entry->issued_range} updated.");
    }

    public function destroy(SurveyControlPointRegister $entry)
    {
        $this->authorizeAction('delete');
        if ($entry->audited_at) {
            return back()->with('error', 'Audited entries are locked.');
        }
        $range = $entry->issued_range;
        DB::connection('sqlsrv')->transaction(function () use ($entry) {
            // Releases the numbers: they may be issued again on a new line.
            $entry->numbers()->get()->each->delete();
            $entry->delete();
        });

        return back()->with('success', "Entry {$range} deleted.");
    }

    /** Footer "Reviewed by": stamps the current user. */
    public function review(SurveyControlPointRegister $entry)
    {
        $this->authorizeAction('approve');
        if ($entry->reviewed_at) {
            return back()->with('error', 'This entry has already been reviewed.');
        }
        $entry->update(['reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('success', "Entry {$entry->issued_range} marked reviewed.");
    }

    /** Footer "Audited by": only after review. */
    public function audit(SurveyControlPointRegister $entry)
    {
        $this->authorizeAction('approve');
        if (! $entry->reviewed_at) {
            return back()->with('error', 'Review the entry before auditing it.');
        }
        if ($entry->audited_at) {
            return back()->with('error', 'This entry has already been audited.');
        }
        $entry->update(['audited_by' => Auth::id(), 'audited_at' => now()]);

        return back()->with('success', "Entry {$entry->issued_range} marked audited.");
    }

    /**
     * Expand the line's range into one survey_control_point_numbers row per number.
     * Regenerated only when the range changed, so an edit to the payment or
     * location leaves the numbers alone.
     */
    /**
     * Replace the line's file numbers with the list from the form, in order.
     *
     * @param array<int, string> $files
     */
    private function syncFiles(SurveyControlPointRegister $entry, array $files): void
    {
        SurveyControlPointRegisterFile::where('survey_control_point_register_id', $entry->id)->delete();

        foreach (array_values($files) as $i => $no) {
            SurveyControlPointRegisterFile::create([
                'survey_control_point_register_id' => $entry->id,
                'file_number' => $no,
                'sort_order'  => $i,
                'created_by'  => Auth::id(),
                'updated_by'  => Auth::id(),
            ]);
        }
    }

    private function syncNumbers(SurveyControlPointRegister $entry, array $range): void
    {
        $live = SurveyControlPointNumber::query()->where('survey_control_point_register_id', $entry->id);

        if ((clone $live)->count() === $range['count']
            && (clone $live)->where('beacon_series', $range['series'])->min('beacon_seq') == $range['start']
            && (clone $live)->where('beacon_series', $range['series'])->max('beacon_seq') == $range['end']) {
            return;
        }

        SurveyControlPointNumber::withTrashed()->where('survey_control_point_register_id', $entry->id)->forceDelete();

        $now = now();
        $rows = [];
        foreach (SurveyControlPointRegister::expand($range) as $seq => $no) {
            $rows[] = [
                'survey_control_point_register_id' => $entry->id,
                'beacon_no'     => $no,
                'beacon_series' => $range['series'],
                'beacon_seq'    => $seq,
                'created_by'    => Auth::id(),
                'updated_by'    => Auth::id(),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        // sqlsrv allows 2,100 bound parameters per statement: 8 columns x 200 rows.
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::connection('sqlsrv')->table('survey_control_point_numbers')->insert($chunk);
        }
    }

    /**
     * Run a save in one transaction. Two users issuing the same number at once
     * both get past the overlap check; the unique index then refuses the
     * second, and it comes back as a normal validation message.
     */
    private function saving(callable $work)
    {
        try {
            return DB::connection('sqlsrv')->transaction($work);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_survey_control_point_numbers_live')) {
                throw ValidationException::withMessages([
                    'beacon_from' => 'One of these control point numbers was issued by someone else just now. Reload and check the range.',
                ]);
            }
            throw $e;
        }
    }

    private function filtered(Request $r)
    {
        $q = SurveyControlPointRegister::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $one = SurveyControlPointRegister::parseBeacon($term);
            $code = SurveyControlPointRegister::normalise($term);
            $q->where(function ($w) use ($term, $one, $code) {
                $w->where('beacon_from', 'like', "%$term%")
                    ->orWhere('beacon_to', 'like', "%$term%")
                    ->orWhere('surveyor_name', 'like', "%$term%")
                    ->orWhere('surveyor_registration', 'like', "%$term%")
                    ->orWhere('plan_no', 'like', "%$term%")
                    ->orWhere('teller_no', 'like', "%$term%")
                    ->orWhere('location', 'like', "%$term%")
                    ->orWhere('prop_plot', 'like', "%$term%")
                    ->orWhere('prop_district_other', 'like', "%$term%")
                    ->orWhere('register_no', 'like', "%$term%")
                    ->orWhere('file_number', 'like', "%$term%")
                    ->orWhereIn('id', SurveyControlPointRegisterFile::query()->select('survey_control_point_register_id')
                        ->where('file_number', 'like', "%$term%"));

                // A beacon number finds the line it was issued on: by the number as
                // written, or by series + number so C0098 also finds C98.
                $w->orWhereIn('id', SurveyControlPointNumber::query()->select('survey_control_point_register_id')
                    ->where('beacon_no', 'like', "%$code%"));
                if ($one) {
                    $w->orWhereIn('id', SurveyControlPointNumber::query()->select('survey_control_point_register_id')
                        ->where('beacon_series', $one['series'])->where('beacon_seq', $one['n']));
                }
            });
        }
        if (array_key_exists($lt = (string) $r->query('layout'), SurveyControlPointRegister::LAYOUT_TYPES)) {
            $q->where('layout_type', $lt);
        }
        foreach (['year', 'quarter', 'month'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, (int) $r->query($f));
            }
        }
        if ($status = $r->query('status')) {
            match ($status) {
                'pending'  => $q->whereNull('reviewed_at'),
                'reviewed' => $q->whereNotNull('reviewed_at')->whereNull('audited_at'),
                'audited'  => $q->whereNotNull('audited_at'),
                default    => null,
            };
        }

        return $q;
    }

    /**
     * The surveyor dropdown: Survey Department staff (surveyors_list) and the
     * SURCON register (cadastral_surveyors), keyed "staff:<id>" / "surcon:<id>".
     * "Other" is added by the view. Inactive staff are kept only when the entry
     * being edited already names them.
     *
     * @return array{staff: array<string, array{name:string, reg:?string}>, surcon: array<string, array{name:string, reg:?string}>}
     */
    private function surveyorOptions(?SurveyControlPointRegister $editing = null): array
    {
        $staff = SurveyorListEntry::query()
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($editing && $editing->surveyors_list_id, fn ($w) => $w->orWhere('id', $editing->surveyors_list_id)))
            ->orderBy('first_name')->orderBy('surname')->get();

        $surcon = DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')
            ->orderBy('full_name')->get(['id', 'full_name', 'surcon_number']);

        $out = ['staff' => [], 'surcon' => []];
        foreach ($staff as $s) {
            $out['staff']['staff:' . $s->id] = ['name' => $s->full_name, 'reg' => null, 'hint' => $s->rank];
        }
        foreach ($surcon as $s) {
            $out['surcon']['surcon:' . $s->id] = ['name' => trim($s->full_name), 'reg' => $s->surcon_number, 'hint' => $s->surcon_number ? 'Reg. ' . $s->surcon_number : null];
        }

        return $out;
    }

    /**
     * Resolve the dropdown choice to the stored surveyor fields. The name is read
     * from the source table, never trusted from the client.
     *
     * @return array<string, mixed>
     */
    private function resolveSurveyor(Request $r): array
    {
        $choice = (string) $r->input('surveyor_choice');

        // Optional (user, 2026-10-23): no pick means no surveyor on the line.
        if ($choice === '') {
            return [
                'surveyor_source'       => null,
                'surveyors_list_id'     => null,
                'cadastral_surveyor_id' => null,
                'surveyor_name'         => null,
            ];
        }

        if ($choice === 'other') {
            return [
                'surveyor_source'       => 'other',
                'surveyors_list_id'     => null,
                'cadastral_surveyor_id' => null,
                'surveyor_name'         => $r->input('surveyor_name_other'),
            ];
        }

        if (preg_match('/^staff:(\d+)$/', $choice, $m) && ($s = SurveyorListEntry::find($m[1]))) {
            return [
                'surveyor_source'       => 'staff',
                'surveyors_list_id'     => $s->id,
                'cadastral_surveyor_id' => null,
                'surveyor_name'         => $s->full_name,
            ];
        }

        if (preg_match('/^surcon:(\d+)$/', $choice, $m)
            && ($s = DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')->find($m[1]))) {
            return [
                'surveyor_source'       => 'surcon',
                'surveyors_list_id'     => null,
                'cadastral_surveyor_id' => $s->id,
                'surveyor_name'         => trim($s->full_name),
            ];
        }

        throw ValidationException::withMessages(['surveyor_choice' => 'Choose a surveyor from the list, or Other.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $r, ?SurveyControlPointRegister $entry = null): array
    {
        $clean = fn ($v) => ($v = preg_replace('/\s+/', ' ', trim((string) $v))) === '' ? null : $v;
        foreach (['register_no', 'plan_no', 'surveyor_name', 'surveyor_registration', 'surveyor_name_other', 'teller_no',
                  'coord_x', 'coord_y'] as $f) {
            $r->merge([$f => $clean($r->input($f))]);
        }
        // Beacon numbers: upper case, no spaces, so "c 9302b" and "C9302B" are one number.
        foreach (['beacon_from', 'beacon_to'] as $f) {
            $r->merge([$f => SurveyControlPointRegister::normalise($r->input($f))]);
        }

        $data = $r->validate([
            'layout_type'           => ['required', Rule::in(array_keys(SurveyControlPointRegister::LAYOUT_TYPES))],
            'register_no'           => 'nullable|string|max:30',
            'issue_date'            => 'required|date|before_or_equal:today',
            'beacon_from'           => 'required|string|max:30',
            'beacon_to'             => 'nullable|string|max:30',
            'plan_no'               => 'nullable|string|max:100',
            // Several file numbers, from the global selector. No format validation (user, 2026-10-23).
            'file_numbers'          => 'nullable|array|max:100',
            'file_numbers.*'        => 'nullable|string|max:100',
            'coord_x'               => 'nullable|numeric|between:-99999999999999,99999999999999',
            'coord_y'               => 'nullable|numeric|between:-99999999999999,99999999999999',
            'surveyor_choice'       => 'nullable|string|max:30',
            'surveyor_name_other'   => 'nullable|string|max:255',
            'surveyor_registration' => 'nullable|string|max:50',
            'teller_no'             => 'nullable|string|max:100',
            'amount'                => 'nullable|numeric|min:0|max:9999999999999',
            'date_received'         => 'nullable|date|after_or_equal:issue_date',
        ] + AddressBuilder::rules('prop_', false), [
            'layout_type.required'           => 'Choose the type: Government or Private.',
            'layout_type.in'                 => 'Choose the type: Government or Private.',
            'issue_date.required'            => 'The issue date is required.',
            'beacon_from.required'           => 'Enter the first control point number of the range.',
            'coord_x.numeric'                => 'X must be a number.',
            'coord_y.numeric'                => 'Y must be a number.',
            'date_received.after_or_equal'   => 'Date received cannot be before the issue date.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required'    => 'Please specify the district.',
            'prop_street_other.required'      => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
            'prop_state.required'             => 'The state is required.',
        ]);

        // File numbers: trimmed, blanks dropped, duplicates (any case) dropped, order kept.
        $files = [];
        foreach ((array) ($data['file_numbers'] ?? []) as $no) {
            $no = trim(preg_replace('/\s+/', ' ', (string) $no));
            if ($no !== '' && ! in_array(mb_strtoupper($no), array_map('mb_strtoupper', $files), true)) {
                $files[] = $no;
            }
        }
        unset($data['file_numbers']);
        // The single column keeps the first one, for anything that still reads it.
        $data['file_number'] = $files[0] ?? null;

        // Composed "District, LGA, State" snapshot for search and the listing; never the plot.
        $data['location'] = AddressBuilder::propertyLocation([
            'district'       => AddressBuilder::toColumn($data['prop_district'] ?? null),
            'district_other' => $data['prop_district_other'] ?? null,
            'lga'            => AddressBuilder::toColumn($data['prop_lga'] ?? null),
            'state'          => $data['prop_state'] ?? null,
        ]) ?: null;

        $data = array_merge(\Illuminate\Support\Arr::except($data, ['surveyor_choice', 'surveyor_name_other']), $this->resolveSurveyor($r));

        $range = $this->checkedRange($data['beacon_from'], $data['beacon_to'] ?? null, $entry);

        // Stored as the expanded ends, so a blank "to" reads as the single number
        // and a zero-padded start keeps its padding on the end too.
        $data['beacon_from']  = SurveyControlPointRegister::expand(['end' => $range['start']] + $range)[$range['start']];
        $data['beacon_to']    = SurveyControlPointRegister::expand(['start' => $range['end']] + $range)[$range['end']];
        $data['total_issued'] = $range['count'];

        $date = \Carbon\Carbon::parse($data['issue_date']);

        return [$data + [
            'beacon_series'   => $range['series'],
            'beacon_start_no' => $range['start'],
            'beacon_end_no'   => $range['end'],
            'year'            => $date->year,
            'month'           => $date->month,
            'quarter'         => $date->quarter,
        ], $range, $files];
    }

    /**
     * Parse and check a range: both ends must be beacon numbers of the same
     * series, the end not lower than the start, at most MAX_RANGE numbers, and
     * none of them already issued on another line.
     */
    private function checkedRange(string $from, ?string $to, ?SurveyControlPointRegister $entry): array
    {
        $example = 'e.g. C9302B to C9306B';

        if (! SurveyControlPointRegister::parseBeacon($from)) {
            throw ValidationException::withMessages(['beacon_from' => "The first number must be letters then digits ($example)."]);
        }
        if ($to !== null && ! SurveyControlPointRegister::parseBeacon($to)) {
            throw ValidationException::withMessages(['beacon_to' => "The last number must be letters then digits ($example)."]);
        }

        $range = SurveyControlPointRegister::parseRange($from, $to);
        if (! $range) {
            $a = SurveyControlPointRegister::parseBeacon($from);
            $b = SurveyControlPointRegister::parseBeacon($to);
            throw ValidationException::withMessages(['beacon_to' => $a['series'] !== $b['series']
                ? "Both numbers must have the same letters before and after the digits ($example)."
                : 'The last number cannot be lower than the first.']);
        }

        if ($range['count'] > SurveyControlPointRegister::MAX_RANGE) {
            throw ValidationException::withMessages(['beacon_to' => "That range is " . number_format($range['count'])
                . ' control points; one entry can hold at most ' . number_format(SurveyControlPointRegister::MAX_RANGE)
                . '. Check the last number, or split it into several entries.']);
        }

        $taken = SurveyControlPointNumber::query()->with('register:id,surveyor_name,issue_date,beacon_from,beacon_to')
            ->where('beacon_series', $range['series'])
            ->whereBetween('beacon_seq', [$range['start'], $range['end']])
            ->when($entry, fn ($q) => $q->where('survey_control_point_register_id', '<>', $entry->id))
            ->orderBy('beacon_seq')->limit(6)->get();
        if ($taken->isNotEmpty()) {
            $first = $taken->first()->register;
            $list = $taken->take(5)->pluck('beacon_no')->implode(', ') . ($taken->count() > 5 ? ', …' : '');
            throw ValidationException::withMessages(['beacon_from' => "Already issued: $list (to " . ($first->surveyor_name ?: 'no surveyor named') . " on "
                . $first->issue_date->format('d/m/Y') . ", entry {$first->issued_range})."]);
        }

        return $range;
    }
}
