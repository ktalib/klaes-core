<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeaconNumber;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyorListEntry;
use App\Support\AddressBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SURCON Beacon Register (Survey > Examination/Verification), the digital copy
 * of the printed register book (user, 2026-10-22).
 *
 * One page, like Misc KN: the slide-open entry form doubles as the edit form
 * via `?edit=<id>`. Quarter / month / year are derived from the issue date and
 * the total is counted from the beacon range whenever the range is parseable,
 * so neither is trusted from the client. Overlapping ranges are refused: a
 * beacon number is issued once.
 *
 * Entries are typed in for now; auto-population from the Examination/Verification
 * beacon generation is a later step.
 */
class BeaconRegisterController extends Controller
{
    public function index(Request $r)
    {
        $q = $this->filtered($r);
        $view = $r->query('view') === 'numbers' ? 'numbers' : 'issues';

        $entries = (clone $q)->with([
                'creator:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'auditor:id,first_name,last_name',
                'numbers:id,survey_beacon_register_id,beacon_no,beacon_seq',
            ])
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        // "By beacon number": one row per expanded number, carrying its line's details.
        $numbers = null;
        if ($view === 'numbers') {
            $nq = SurveyBeaconNumber::query()
                ->whereIn('survey_beacon_register_id', (clone $q)->select('id'))
                ->with('register');
            $term = SurveyBeaconRegister::normalise($r->query('q'));
            if ($term && SurveyBeaconRegister::parseBeacon($term)) {
                $nq->where('beacon_no', 'like', "%$term%");
            }
            $numbers = $nq->orderBy('beacon_series')->orderBy('beacon_seq')->paginate(50)->withQueryString();
        }

        $filteredBeacons = (int) (clone $q)->sum('total_issued');
        $filteredAmount  = (float) (clone $q)->sum('amount');

        $stats = [
            'entries'        => SurveyBeaconRegister::count(),
            'beacons'        => SurveyBeaconNumber::count(),
            'this_month'     => (int) SurveyBeaconRegister::where('year', now()->year)->where('month', now()->month)->sum('total_issued'),
            'amount'         => (float) SurveyBeaconRegister::sum('amount'),
        ];

        $years = SurveyBeaconRegister::query()->distinct()->orderByDesc('year')->pluck('year');

        $editing = $r->filled('edit') ? SurveyBeaconRegister::find($r->query('edit')) : null;

        return view('survey_module.workflow.beacon_register', [
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
        [$data, $range] = $this->validated($r);

        $entry = $this->saving(function () use ($data, $range) {
            $entry = SurveyBeaconRegister::create($data);
            $this->syncNumbers($entry, $range);

            return $entry;
        });

        return redirect()->route('survey-module.workflow.beacon-register.index')
            ->with('success', "{$entry->total_issued} beacon(s) {$entry->issued_range} recorded for {$entry->surveyor_name}.");
    }

    public function update(Request $r, SurveyBeaconRegister $entry)
    {
        if ($entry->audited_at) {
            return back()->with('error', 'Audited entries are locked.');
        }
        [$data, $range] = $this->validated($r, $entry);

        $this->saving(function () use ($entry, $data, $range) {
            $entry->update($data);
            $this->syncNumbers($entry, $range);
        });

        return redirect()->route('survey-module.workflow.beacon-register.index', $r->only(['q', 'year', 'quarter', 'month', 'page']))
            ->with('success', "Entry {$entry->issued_range} updated.");
    }

    public function destroy(SurveyBeaconRegister $entry)
    {
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
    public function review(SurveyBeaconRegister $entry)
    {
        if ($entry->reviewed_at) {
            return back()->with('error', 'This entry has already been reviewed.');
        }
        $entry->update(['reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('success', "Entry {$entry->issued_range} marked reviewed.");
    }

    /** Footer "Audited by": only after review. */
    public function audit(SurveyBeaconRegister $entry)
    {
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
     * Expand the line's range into one survey_beacon_numbers row per number.
     * Regenerated only when the range changed, so an edit to the payment or
     * location leaves the numbers alone.
     */
    private function syncNumbers(SurveyBeaconRegister $entry, array $range): void
    {
        $live = SurveyBeaconNumber::query()->where('survey_beacon_register_id', $entry->id);

        if ((clone $live)->count() === $range['count']
            && (clone $live)->where('beacon_series', $range['series'])->min('beacon_seq') == $range['start']
            && (clone $live)->where('beacon_series', $range['series'])->max('beacon_seq') == $range['end']) {
            return;
        }

        SurveyBeaconNumber::withTrashed()->where('survey_beacon_register_id', $entry->id)->forceDelete();

        $now = now();
        $rows = [];
        foreach (SurveyBeaconRegister::expand($range) as $seq => $no) {
            $rows[] = [
                'survey_beacon_register_id' => $entry->id,
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
            DB::connection('sqlsrv')->table('survey_beacon_numbers')->insert($chunk);
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
            if (str_contains($e->getMessage(), 'ux_survey_beacon_numbers_live')) {
                throw ValidationException::withMessages([
                    'beacon_from' => 'One of these beacon numbers was issued by someone else just now. Reload and check the range.',
                ]);
            }
            throw $e;
        }
    }

    private function filtered(Request $r)
    {
        $q = SurveyBeaconRegister::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $one = SurveyBeaconRegister::parseBeacon($term);
            $code = SurveyBeaconRegister::normalise($term);
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
                    ->orWhere('register_no', 'like', "%$term%");

                // A beacon number finds the line it was issued on: by the number as
                // written, or by series + number so C0098 also finds C98.
                $w->orWhereIn('id', SurveyBeaconNumber::query()->select('survey_beacon_register_id')
                    ->where('beacon_no', 'like', "%$code%"));
                if ($one) {
                    $w->orWhereIn('id', SurveyBeaconNumber::query()->select('survey_beacon_register_id')
                        ->where('beacon_series', $one['series'])->where('beacon_seq', $one['n']));
                }
            });
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
    private function surveyorOptions(?SurveyBeaconRegister $editing = null): array
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
    private function validated(Request $r, ?SurveyBeaconRegister $entry = null): array
    {
        $clean = fn ($v) => ($v = preg_replace('/\s+/', ' ', trim((string) $v))) === '' ? null : $v;
        foreach (['register_no', 'plan_no', 'surveyor_name', 'surveyor_registration', 'surveyor_name_other', 'teller_no'] as $f) {
            $r->merge([$f => $clean($r->input($f))]);
        }
        // Beacon numbers: upper case, no spaces, so "c 9302b" and "C9302B" are one number.
        foreach (['beacon_from', 'beacon_to'] as $f) {
            $r->merge([$f => SurveyBeaconRegister::normalise($r->input($f))]);
        }

        $data = $r->validate([
            'register_no'           => 'nullable|string|max:30',
            'issue_date'            => 'required|date|before_or_equal:today',
            'beacon_from'           => 'required|string|max:30',
            'beacon_to'             => 'nullable|string|max:30',
            'plan_no'               => 'nullable|string|max:100',
            'surveyor_choice'       => 'required|string|max:30',
            'surveyor_name_other'   => 'nullable|required_if:surveyor_choice,other|string|max:255',
            'surveyor_registration' => 'nullable|string|max:50',
            'teller_no'             => 'nullable|string|max:100',
            'amount'                => 'nullable|numeric|min:0|max:9999999999999',
            'date_received'         => 'nullable|date|after_or_equal:issue_date',
        ] + AddressBuilder::rules('prop_'), [
            'issue_date.required'            => 'The issue date is required.',
            'beacon_from.required'           => 'Enter the first beacon number of the range.',
            'surveyor_choice.required'       => 'Choose the surveyor, or Other.',
            'surveyor_name_other.required_if' => "Type the surveyor's name.",
            'date_received.after_or_equal'   => 'Date received cannot be before the issue date.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required'    => 'Please specify the district.',
            'prop_street_other.required'      => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
            'prop_state.required'             => 'The state is required.',
        ]);

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
        $data['beacon_from']  = SurveyBeaconRegister::expand(['end' => $range['start']] + $range)[$range['start']];
        $data['beacon_to']    = SurveyBeaconRegister::expand(['start' => $range['end']] + $range)[$range['end']];
        $data['total_issued'] = $range['count'];

        $date = \Carbon\Carbon::parse($data['issue_date']);

        return [$data + [
            'beacon_series'   => $range['series'],
            'beacon_start_no' => $range['start'],
            'beacon_end_no'   => $range['end'],
            'year'            => $date->year,
            'month'           => $date->month,
            'quarter'         => $date->quarter,
        ], $range];
    }

    /**
     * Parse and check a range: both ends must be beacon numbers of the same
     * series, the end not lower than the start, at most MAX_RANGE numbers, and
     * none of them already issued on another line.
     */
    private function checkedRange(string $from, ?string $to, ?SurveyBeaconRegister $entry): array
    {
        $example = 'e.g. C9302B to C9306B';

        if (! SurveyBeaconRegister::parseBeacon($from)) {
            throw ValidationException::withMessages(['beacon_from' => "The first number must be letters then digits ($example)."]);
        }
        if ($to !== null && ! SurveyBeaconRegister::parseBeacon($to)) {
            throw ValidationException::withMessages(['beacon_to' => "The last number must be letters then digits ($example)."]);
        }

        $range = SurveyBeaconRegister::parseRange($from, $to);
        if (! $range) {
            $a = SurveyBeaconRegister::parseBeacon($from);
            $b = SurveyBeaconRegister::parseBeacon($to);
            throw ValidationException::withMessages(['beacon_to' => $a['series'] !== $b['series']
                ? "Both numbers must have the same letters before and after the digits ($example)."
                : 'The last number cannot be lower than the first.']);
        }

        if ($range['count'] > SurveyBeaconRegister::MAX_RANGE) {
            throw ValidationException::withMessages(['beacon_to' => "That range is " . number_format($range['count'])
                . ' beacons; one entry can hold at most ' . number_format(SurveyBeaconRegister::MAX_RANGE)
                . '. Check the last number, or split it into several entries.']);
        }

        $taken = SurveyBeaconNumber::query()->with('register:id,surveyor_name,issue_date,beacon_from,beacon_to')
            ->where('beacon_series', $range['series'])
            ->whereBetween('beacon_seq', [$range['start'], $range['end']])
            ->when($entry, fn ($q) => $q->where('survey_beacon_register_id', '<>', $entry->id))
            ->orderBy('beacon_seq')->limit(6)->get();
        if ($taken->isNotEmpty()) {
            $first = $taken->first()->register;
            $list = $taken->take(5)->pluck('beacon_no')->implode(', ') . ($taken->count() > 5 ? ', …' : '');
            throw ValidationException::withMessages(['beacon_from' => "Already issued: $list (to {$first->surveyor_name} on "
                . $first->issue_date->format('d/m/Y') . ", entry {$first->issued_range})."]);
        }

        return $range;
    }
}
