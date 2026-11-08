<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyItsCoordinate;
use App\Models\Survey\SurveyItsObservation;
use App\Models\Survey\SurveyRegisterFile;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\PrivateRegisterLines;
use App\Services\Survey\SurveyDocuments;
use App\Services\SurveyItsAnchors;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Examination/Verification > Surveyor's Report (Private). It uses the standard
 * Surveyor's Report fields and print template, with its linked private survey
 * records anchored as "private-<id>" (SurveyItsAnchors).
 */
class ChangeReportController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    /** Off for now (user, 2026-11-06): the table and cards hold only reports. True lists the Private Beacon Register lines again. */
    private const FROM_BEACON_REGISTER = false;

    public function index(Request $r)
    {
        $all = $this->allRows();
        $rows = $this->filtered($all, $r);
        $page = max(1, (int) $r->query('page', 1));
        $shown = $rows->forPage($page, 20)->values()->map(fn ($x) => $x + $this->sections($x));

        // Examination lines cleared with "Changes required" and no report yet.
        $waiting = SurveyRegisterFile::where('finding', 'changes_required')
            ->whereNotIn('id', CR::whereNotNull('survey_register_file_id')->pluck('survey_register_file_id')->all() ?: [0])
            ->orderByDesc('id')->get(['id', 'register_ref', 'file_number', 'file_title']);

        return view('survey_module.workflow.change_reports.index', [
            'reports'  => new LengthAwarePaginator($shown, $rows->count(), 20, $page, ['path' => $r->url(), 'query' => $r->query()]),
            'stats'    => ['total' => CR::count(), 'draft' => CR::where('status', CR::DRAFT)->count(), 'issued' => CR::where('status', CR::ISSUED)->count()],
            'waiting'  => $waiting,
            'awaiting' => $waiting->count() + $all->whereNull('report')->count(),
        ]);
    }

    /**
     * Every row of the table (user, 2026-11-06, as GKN lists every I to S): the reports, each with the Private
     * Beacon Register lines of its file if there are any, then those lines' files that have no report yet.
     */
    private function allRows(): Collection
    {
        $groups = self::FROM_BEACON_REGISTER ? app(PrivateRegisterLines::class)->groups() : collect();
        $reports = CR::query()->with('registerFile')->orderByDesc('id')->get()->toBase();

        $withReport = $reports->map(function (CR $x) use ($groups) {
            // Its file's lines, or the one line with no file number it was raised from.
            $g = $groups->get(PrivateRegisterLines::key($x->file_number) ?? '') ?? $groups->get('#' . $x->survey_beacon_register_id);

            return ['report' => $x, 'group' => $g, 'file_number' => $x->file_number, 'plan_no' => $x->plan_no ?: ($g['plan_no'] ?? null), 'surveyor' => $x->surveyor_name ?: ($g['surveyor'] ?? null),
                'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga, 'location' => $g['location'] ?? null, 'date' => $x->report_date];
        });
        $reported = $withReport->map(fn ($x) => $x['group']['key'] ?? null)->filter()->flip();

        return $withReport->concat($groups->reject(fn ($g, $key) => $reported->has($key))->values()->map(fn ($g) => [
            'report' => null, 'group' => $g, 'file_number' => $g['file_number'], 'plan_no' => $g['plan_no'], 'surveyor' => $g['surveyor'],
            'plot_no' => $g['plot_no'], 'district' => $g['district'], 'lga' => $g['lga'], 'location' => $g['location'], 'date' => $g['date'],
        ]))->values();
    }

    /** The table's filters, shared with Export Records. */
    private function filtered(Collection $rows, Request $r): Collection
    {
        if ($term = mb_strtolower(trim((string) $r->query('q', '')))) {
            $rows = $rows->filter(fn ($x) => str_contains(mb_strtolower(implode(' ', [$x['report']?->report_ref, $x['file_number'], $x['plan_no'], $x['surveyor'], $x['plot_no'], $x['district'], $x['lga'], $x['location'], $x['group']['numbers'] ?? ''])), $term));
        }
        $status = $r->query('status');
        if (in_array($status, [CR::DRAFT, CR::ISSUED], true)) $rows = $rows->filter(fn ($x) => $x['report']?->status === $status);
        if ($status === 'awaiting') $rows = $rows->filter(fn ($x) => ! $x['report']);

        return $rows->values();
    }

    /**
     * A row's three sections: the report's own records, and the register's figures where its own list has no
     * beacons yet. A file with no report has only the register's.
     *
     * @return array{anchor:?array, rec:?array, links:array}
     */
    private function sections(array $x): array
    {
        $a = $x['report'] ? app(SurveyItsAnchors::class)->resolve('private-' . $x['report']->id) : null;
        $rec = $a ? app(\App\Services\Survey\ReportRecords::class)->summary($a) : null;
        $g = $x['group'];
        if (! $g || ($rec && $rec['beacons'] > 0)) {
            return ['anchor' => $a, 'rec' => $rec, 'links' => []];
        }
        $reg = PrivateRegisterLines::summary($g);
        $keep = ['beacons' => $reg['beacons'], 'in_register' => $reg['in_register'], 'flagged' => $reg['flagged'], 'points' => $reg['points']];

        return ['anchor' => $a, 'rec' => $rec ? $keep + $rec : $reg, 'links' => PrivateRegisterLines::links($g)];
    }

    public function create(Request $r)
    {
        $line = $r->query('line') ? SurveyRegisterFile::find($r->query('line')) : null;
        $report = new CR([
            'survey_register_file_id' => $line?->id,
            'file_number' => $line?->file_number, 'file_title' => $line?->file_title,
            'plot_no' => $line?->plot_no, 'district' => $line?->district, 'lga' => $line?->lga,
            'location' => $line?->location,
            'report_date' => now()->toDateString(), 'examined_by' => Auth::user()?->name,
        ]);

        // From a Private Beacon Register row. Only a row that exists: this is a URL, and a prefilled file number locks.
        $group = ! $line && $r->query('beacon') ? app(PrivateRegisterLines::class)->groups()->get((string) $r->query('beacon')) : null;
        if ($group) {
            $found = $group['file_number'] ? app(SurveyItsController::class)->lookup(Request::create('/', 'GET', ['file_number' => $group['file_number']]))->getData(true) : [];
            $report->fill([
                'file_number' => $group['file_number'], 'file_title' => $found['title'] ?? null, 'plan_no' => $group['plan_no'], 'surveyor_name' => $group['surveyor'],
                'plot_no' => ($found['plot'] ?? null) ?: $group['plot_no'], 'district' => ($found['district'] ?? null) ?: $group['district'], 'lga' => ($found['lga'] ?? null) ?: $group['lga'],
                'location' => $found['location'] ?? $group['location'],
            ]);
        }

        return view('survey_module.workflow.change_reports.form', [
            'report' => $report,
            'line' => $line,
            'fromBeacons' => (bool) ($group['file_number'] ?? null),
            'beaconLine' => $group && ! $group['file_number'] ? $group['line_id'] : null,
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        if (! empty($data['survey_register_file_id'])
            && ($existing = CR::where('survey_register_file_id', $data['survey_register_file_id'])->first())) {
            return redirect()->route('survey-module.workflow.change-reports.edit', $existing)
                ->with('error', "That Register File line already has {$existing->report_ref}; it is opened here.");
        }
        $report = CR::create($data + [
            'discrepancies' => '',
            'required_changes' => '',
            'report_ref' => CR::nextRef('report_ref', 'SRC'),
            'status' => CR::DRAFT,
        ]);

        return redirect()->route('survey-module.workflow.change-reports.edit', $report)
            ->with('success', "{$report->report_ref} saved. Issue it to print when ready.");
    }

    public function edit(CR $report)
    {
        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $report->registerFile, 'records' => $this->records($report)]);
    }

    public function update(Request $r, CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} has been issued and can no longer be edited.");
        $report->update($this->validated($r, $report));

        return back()->with('success', "{$report->report_ref} saved.");
    }

    public function issue(CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} is already issued.");
        $missing = [];
        foreach ([
            'grid_origin' => 'origin of the local grid',
            'control_points' => 'the control points',
            'commenced_on' => 'the date the survey commenced',
            'completed_on' => 'the date it was completed',
            'transport_by' => 'who provided transport',
            'surveyor_name' => "the surveyor's name",
        ] as $field => $label) {
            if (blank($report->{$field})) $missing[] = $label;
        }
        if (blank($report->file_number)) $missing[] = 'the file number';
        if ($report->on_layout_plan === null) $missing[] = 'whether the plot is on an approved layout plan';
        if ($report->corners_demarcated === null) $missing[] = 'whether the corners were demarcated';
        if ($missing) {
            return back()->with('error', 'Before issuing, fill in ' . implode(', ', $missing) . '.');
        }

        $report->update(['status' => CR::ISSUED, 'issued_at' => now(), 'issued_by' => Auth::id()]);

        return redirect()->route('survey-module.workflow.change-reports.print', $report);
    }

    public function destroy(CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} has been issued and cannot be deleted.");
        $ref = $report->report_ref;
        $report->delete();

        return redirect()->route('survey-module.workflow.change-reports.index')->with('success', "Report {$ref} deleted.");
    }

    public function print(CR $report)
    {
        return view('survey_module.gkn.reports.print', ['report' => $report, 'privateReport' => true]);
    }

    /** The report's List of Coordinates, Beacon Nos, Field Observations and Sketch Plan. */
    private function records(CR $report): array
    {
        $anchor = app(SurveyItsAnchors::class)->resolve('private-' . $report->id);
        $coords = SurveyItsCoordinate::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();
        $beacons = app(BeaconNumberCheck::class)->forAnchor($anchor);
        $sketch = app(SurveyDocuments::class)->for('private', $report->id, SurveyDocuments::KIND_SKETCH_PLAN)->firstWhere('is_superseded', 0);

        return [
            'key'      => $anchor['key'],
            'coords'   => $coords,
            'beacons'  => $beacons,
            'summary'  => BeaconNumberCheck::summary($beacons),
            'readings' => SurveyItsObservation::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->count(),
            'sketch'   => $sketch,
        ];
    }

    private function validated(Request $r, ?CR $existing = null): array
    {
        $yesNo = ['nullable', Rule::in(['1', '0'])];
        $data = $r->validate([
            'survey_register_file_id' => ['nullable', Rule::exists('sqlsrv.survey_register_files', 'id')->whereNull('deleted_at')],
            'survey_beacon_register_id' => ['nullable', Rule::exists('sqlsrv.survey_beacon_register', 'id')->where('layout_type', 'Private')->whereNull('deleted_at')],
            // From a Register File line the file is the line's; otherwise it must be picked.
            'file_number'      => ($existing?->survey_register_file_id || $r->filled('survey_register_file_id')) ? 'nullable|string|max:100' : 'required|string|max:100',
            'grantee_name'     => 'nullable|string|max:500',
            'district'         => 'required|string|max:255',
            'district_other'   => 'nullable|string|max:255|required_if:district,Other',
            'plot_no'          => 'nullable|string|max:100',
            'lga'              => 'required|string|max:255',
            'location'          => 'nullable|string|max:500',
            'grid_origin'       => 'nullable|string|max:255',
            'control_points'    => 'nullable|string|max:500',
            'boundaries_pointed_by' => 'nullable|string|max:255',
            'boundaries_pointed_on' => 'nullable|date',
            'commenced_on'      => 'nullable|date',
            'completed_on'      => 'nullable|date|after_or_equal:commenced_on',
            'distance_km'       => 'nullable|numeric|min:0|max:100000',
            'computed_on'       => 'nullable|string|max:255',
            'delay_reason'      => 'nullable|string|max:2000',
            'on_layout_plan'    => $yesNo,
            'layout_plan_no'    => 'nullable|required_if:on_layout_plan,1|string|max:100',
            'corners_demarcated' => $yesNo,
            'hard_by'           => 'nullable|string|max:500',
            'encroachment'      => $yesNo,
            'encroachment_details' => 'nullable|required_if:encroachment,1|string|max:2000',
            'developed'         => $yesNo,
            'developed_with'    => 'nullable|required_if:developed,1|string|max:500',
            'beacons_not_emplaced' => 'nullable|string|max:500',
            'beacons_reason'    => 'nullable|required_with:beacons_not_emplaced|string|max:1000',
            'previous_kn'       => 'nullable|string|max:100',
            'previous_kn_coincides' => $yesNo,
            'transport_by'      => ['nullable', Rule::in(array_keys(\App\Models\Survey\SurveySurveyorReport::TRANSPORT))],
            'remarks'           => 'nullable|string|max:4000',
            'field_jacket_checklist' => 'nullable|array',
            'field_jacket_checklist.*' => [Rule::in(array_keys(\App\Models\Survey\SurveySurveyorReport::FIELD_RECORDS))],
            'surveyor_name'     => 'nullable|string|max:255',
            'report_date'      => 'nullable|date',
        ], [
            'file_number.required' => 'Select the file with Select File Number, or raise the report from its Register File line.',
            'completed_on.after_or_equal' => 'The survey cannot be completed before it commenced.',
            'layout_plan_no.required_if' => 'Give the approved layout plan number.',
            'encroachment_details.required_if' => 'Describe the encroachment noticed.',
            'developed_with.required_if' => 'Say what the plot is developed with.',
            'beacons_reason.required_with' => 'Say why those beacons were not emplaced.',
        ]);

        $data['file_title'] = $data['grantee_name'] ?? null;
        unset($data['grantee_name']);
        $districtChoice = trim($data['district']);
        if (strcasecmp($districtChoice, 'Other') === 0) {
            $district = trim(preg_replace('/\s+/u', ' ', (string) ($data['district_other'] ?? '')));
            if ($district === '') throw \Illuminate\Validation\ValidationException::withMessages(['district_other' => 'Specify the district.']);
        } else {
            $district = LookupController::canonicalOption('districts', $districtChoice);
            if (! $district) throw \Illuminate\Validation\ValidationException::withMessages(['district' => 'Choose a district from the searchable list, or select Other (specify).']);
        }
        $lga = LookupController::canonicalOption('lgas', $data['lga']);
        if (! $lga) throw \Illuminate\Validation\ValidationException::withMessages(['lga' => 'Choose an LGA from the searchable list.']);
        $data['district'] = mb_substr($district, 0, 255);
        $data['lga'] = $lga;
        $data['location'] = AddressBuilder::propertyLocation(['district' => $district, 'lga' => $lga, 'state' => 'Kano']);
        unset($data['district_other']);
        foreach (['on_layout_plan', 'corners_demarcated', 'developed', 'previous_kn_coincides'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== null ? (bool) $data[$field] : null;
        }
        $data['encroachment'] = (bool) ($data['encroachment'] ?? false);
        if (! $data['encroachment']) $data['encroachment_details'] = null;
        if ($data['on_layout_plan'] === false) $data['layout_plan_no'] = null;
        if ($data['developed'] === false) $data['developed_with'] = null;
        $data['field_jacket_checklist'] = array_values(array_intersect(
            array_keys(\App\Models\Survey\SurveySurveyorReport::FIELD_RECORDS),
            $data['field_jacket_checklist'] ?? []
        ));

        // A report raised from a Register File line keeps that line's file.
        $lineId = $existing?->survey_register_file_id ?? ($data['survey_register_file_id'] ?? null);
        if ($lineId && ($line = SurveyRegisterFile::find($lineId))) {
            $data['survey_register_file_id'] = $line->id;
            $data['file_number'] = $line->file_number;
        }

        return $data;
    }

    public function export(Request $r)
    {
        return $this->exportRecords($r, fn () => $this->filtered($this->allRows(), $r), [
            'ref' => 'Report No', 'register_ref' => 'Register File', 'file_number' => 'File No.', 'plan_no' => 'TP No.', 'surveyor' => 'Surveyor', 'plot_no' => 'Plot No.',
            'district' => 'District', 'lga' => 'LGA', 'location' => 'Location', 'report_date' => 'Date', 'status' => 'Status',
            'beacons' => 'Beacons', 'in_register' => 'In Register', 'flagged' => 'Flagged', 'points' => 'Coordinates', 'area' => 'Area (m²)', 'perimeter' => 'Perimeter (m)',
            'readings' => 'Readings', 'misclosure' => 'Misclosure (m)',
        ], function ($x) {
            $rec = $this->sections($x)['rec'];

            return [
                'ref' => $x['report']?->report_ref, 'register_ref' => $x['report']?->registerFile?->register_ref, 'file_number' => $x['file_number'], 'plan_no' => $x['plan_no'], 'surveyor' => $x['surveyor'],
                'plot_no' => $x['plot_no'], 'district' => $x['district'], 'lga' => $x['lga'], 'location' => $x['location'], 'report_date' => $this->exportDate($x['date']), 'status' => $x['report']?->status ?? 'Awaiting report',
                'beacons' => $rec['beacons'] ?? null, 'in_register' => $rec['in_register'] ?? null, 'flagged' => $rec['flagged'] ?? null, 'points' => $rec['points'] ?? null,
                'area' => $rec['area'] ?? null, 'perimeter' => $rec['perimeter'] ?? null, 'readings' => $rec['readings'] ?? null, 'misclosure' => $rec['misclosure'] ?? null,
            ];
        }, 'date');
    }
}
