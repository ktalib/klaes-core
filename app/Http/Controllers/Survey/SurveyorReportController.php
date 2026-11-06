<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveySurveyorReport as SR;
use App\Services\Survey\ReportRecords;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GKN > Surveyors Report (user, 2026-10-25): the Survey Department's Surveyor's
 * Report form (docs/SURVEYOR_s_report-1.docx), raised against an issued
 * Instruction to Survey, Survey's own or Cadastral's. File, grantee, plot and
 * location come from the I to S. One live report per I to S; submitting locks it
 * and moves Survey's own I to S to "Report Submitted".
 *
 * One page since the recording of 2026-11-06: Beacon Nos, List of Coordinates and Computation Field Observations
 * left the sidebar and are the three sections of this one table, a row per issued I to S with or without a report.
 */
class SurveyorReportController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    public function index(Request $r)
    {
        $rows = $this->rows($r);
        $page = max(1, (int) $r->query('page', 1));
        $records = app(ReportRecords::class);
        $shown = $rows->forPage($page, 20)->values()->map(fn ($x) => $x + ['rec' => $x['anchor'] ? $records->summary($x['anchor']) : null]);

        return view('survey_module.gkn.reports.index', [
            'rows'     => new LengthAwarePaginator($shown, $rows->count(), 20, $page, ['path' => $r->url(), 'query' => $r->query()]),
            'stats'    => ['total' => SR::count(), 'draft' => SR::where('status', SR::DRAFT)->count(), 'submitted' => SR::where('status', SR::SUBMITTED)->count()],
            'awaiting' => count($this->openInstructions()),
        ]);
    }

    /**
     * The table's rows with its filters, shared with Export Records: every issued I to S with its report if it has
     * one, newest first, and any report whose I to S has since been cancelled.
     */
    private function rows(Request $r): Collection
    {
        $keyOf = fn (SR $x) => $x->survey_its_id ? 'survey-' . $x->survey_its_id : 'cadastral-' . $x->cadastral_survey_job_id;
        $reports = SR::query()->orderByDesc('id')->get()->keyBy($keyOf);
        $anchors = app(SurveyItsAnchors::class)->all();

        $rows = collect($anchors)->values()->map(fn ($a) => $this->row($a, $reports->get($a['key'])))
            ->concat($reports->reject(fn ($x, $key) => isset($anchors[$key]))->values()->map(fn ($x) => $this->row(null, $x)));

        if ($term = mb_strtolower(trim((string) $r->query('q', '')))) {
            $rows = $rows->filter(fn ($x) => str_contains(mb_strtolower(implode(' ', [$x['report']?->report_ref, $x['its_number'], $x['file_number'], $x['grantee'], $x['surveyor']])), $term));
        }
        $status = $r->query('status');
        if (in_array($status, [SR::DRAFT, SR::SUBMITTED], true)) $rows = $rows->filter(fn ($x) => $x['report']?->status === $status);
        if ($status === 'awaiting') $rows = $rows->filter(fn ($x) => ! $x['report']);
        if (in_array($src = $r->query('source'), ['survey', 'cadastral'], true)) $rows = $rows->filter(fn ($x) => $x['source'] === $src);

        return $rows->values();
    }

    /** One line of the table: the report's own details, or the I to S's until there is a report. */
    private function row(?array $a, ?SR $x): array
    {
        $p = $a['prefill'] ?? [];
        $pick = fn ($field, $key = null) => $x?->{$field} ?: ($p[$key ?? $field] ?? null);

        return [
            'anchor' => $a, 'report' => $x, 'source' => $a['source'] ?? $x?->its_source, 'its_number' => $pick('its_number'),
            'file_number' => $pick('file_number'), 'grantee' => $pick('grantee_name'), 'plot_no' => $pick('plot_no'),
            'district' => $pick('district'), 'lga' => $pick('lga'), 'location' => $pick('location'), 'surveyor' => $pick('surveyor_name'),
        ];
    }

    public function export(Request $r)
    {
        $records = app(ReportRecords::class);

        return $this->exportRecords($r, fn () => $this->rows($r), [
            'report' => 'SR No.', 'its_number' => 'I to S No.', 'file_number' => 'File No.', 'grantee' => 'Grantee', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location',
            'surveyor' => 'Surveyor', 'status' => 'Status', 'beacons' => 'Beacons', 'in_register' => 'In Register', 'flagged' => 'Flagged', 'points' => 'Coordinates', 'area' => 'Area (m²)',
            'perimeter' => 'Perimeter (m)', 'readings' => 'Readings', 'closure' => 'Closure', 'misclosure' => 'Misclosure (m)', 'sketch' => 'Sketch Plan',
        ], function ($x) use ($records) {
            $rec = $x['anchor'] ? $records->summary($x['anchor']) : null;

            return [
                'report' => $x['report']?->report_ref, 'its_number' => $x['its_number'], 'file_number' => $x['file_number'], 'grantee' => $x['grantee'], 'plot_no' => $x['plot_no'],
                'district' => $x['district'], 'lga' => $x['lga'], 'location' => $x['location'], 'surveyor' => $x['surveyor'], 'status' => $x['report']?->status ?? 'Awaiting report',
                'beacons' => $rec['beacons'] ?? null, 'in_register' => $rec['in_register'] ?? null, 'flagged' => $rec['flagged'] ?? null, 'points' => $rec['points'] ?? null,
                'area' => isset($rec['area']) ? round($rec['area'], 2) : null, 'perimeter' => isset($rec['perimeter']) ? round($rec['perimeter'], 2) : null, 'readings' => $rec['readings'] ?? null,
                'closure' => empty($rec['readings']) ? null : ($rec['closed'] ? 'Closed' : 'Open'), 'misclosure' => isset($rec['misclosure']) ? round($rec['misclosure'], 3) : null,
                'sketch' => $rec ? ($rec['sketch'] ? 'Uploaded' : 'Not uploaded') : null,
            ];
        }, null);
    }

    public function create(Request $r)
    {
        $choices = $this->openInstructions();
        $key = (string) $r->query('its', old('its_key', ''));
        $report = new SR(['report_date' => now()->toDateString(), 'field_jacket_checklist' => array_keys(SR::FIELD_RECORDS)]);

        if ($key !== '' && ($anchor = $this->anchor($key))) {
            $report->fill($anchor['prefill']);
        }

        return view('survey_module.gkn.reports.form', compact('report', 'choices', 'key'));
    }

    public function store(Request $r)
    {
        $anchor = $this->anchor((string) $r->input('its_key', ''));
        if (! $anchor) {
            throw ValidationException::withMessages(['its_key' => 'Choose the issued Instruction to Survey this report answers.']);
        }
        if ($existing = $this->liveReportFor($anchor)) {
            return redirect()->route('survey-module.gkn.reports.edit', $existing)
                ->with('error', "{$anchor['prefill']['its_number']} already has report {$existing->report_ref}; it is opened here.");
        }

        $data = $this->validated($r);
        $report = SR::create($data + $anchor['prefill'] + $anchor['keys'] + [
            'report_ref' => SR::nextRef('report_ref', 'SR', 4),
            'status'     => SR::DRAFT,
        ]);

        return redirect()->route('survey-module.gkn.reports.edit', $report)
            ->with('success', "Surveyors Report {$report->report_ref} saved as a draft. Submit it when complete.");
    }

    public function edit(SR $report)
    {
        return view('survey_module.gkn.reports.form', ['report' => $report, 'choices' => [], 'key' => '']);
    }

    public function update(Request $r, SR $report)
    {
        if ($report->isSubmitted()) {
            return back()->with('error', "{$report->report_ref} has been submitted and can no longer be edited.");
        }
        $report->update($this->validated($r));

        return back()->with('success', "{$report->report_ref} saved.");
    }

    /** Lock the report. The form must be complete enough to stand as the official record. */
    public function submit(SR $report)
    {
        if ($report->isSubmitted()) {
            return back()->with('error', "{$report->report_ref} is already submitted.");
        }

        $missing = [];
        foreach (['grid_origin' => 'origin of the local grid', 'control_points' => 'the control points', 'commenced_on' => 'the date the survey commenced',
                  'completed_on' => 'the date it was completed', 'transport_by' => 'who provided transport', 'surveyor_name' => "the surveyor's name"] as $f => $label) {
            if (blank($report->{$f})) $missing[] = $label;
        }
        if ($report->on_layout_plan === null) $missing[] = 'whether the plot is on an approved layout plan';
        if ($report->corners_demarcated === null) $missing[] = 'whether the corners were demarcated';
        if ($missing) {
            return back()->with('error', 'Before submitting, fill in ' . implode(', ', $missing) . '.');
        }

        DB::connection('sqlsrv')->transaction(function () use ($report) {
            $report->update(['status' => SR::SUBMITTED, 'submitted_at' => now(), 'submitted_by' => Auth::id()]);
            // Survey's own job moves on; Cadastral's job is theirs and is left alone.
            if ($report->survey_its_id && ($its = SurveyIts::find($report->survey_its_id)) && $its->status === SurveyIts::ISSUED) {
                $its->update(['status' => SurveyIts::REPORTED]);
            }
        });

        return back()->with('success', "{$report->report_ref} submitted." . ($report->survey_its_id ? " {$report->its_number} is now Report Submitted." : ''));
    }

    public function destroy(SR $report)
    {
        if ($report->isSubmitted()) {
            return back()->with('error', "{$report->report_ref} has been submitted and cannot be deleted.");
        }
        $ref = $report->report_ref;
        $report->delete();

        return redirect()->route('survey-module.gkn.reports.index')->with('success', "Draft {$ref} deleted.");
    }

    public function print(SR $report)
    {
        return view('survey_module.gkn.reports.print', ['report' => $report]);
    }

    /* ------------------------------------------------------------------ */

    /** Issued I to S (Survey's and Cadastral's) with no live report yet, for the create picker. */
    private function openInstructions(): array
    {
        $taken = SR::query()->get(['survey_its_id', 'cadastral_survey_job_id']);
        $takenKeys = $taken->pluck('survey_its_id')->filter()->map(fn ($id) => 'survey-' . $id)
            ->merge($taken->pluck('cadastral_survey_job_id')->filter()->map(fn ($id) => 'cadastral-' . $id))->all();

        return collect(app(SurveyItsAnchors::class)->all())
            ->except($takenKeys)
            ->map(fn ($a) => $a['label'])
            ->all();
    }

    /** The I to S a report answers, and what the report takes from it (SurveyItsAnchors). */
    private function anchor(string $key): ?array
    {
        // Government reports answer a GKN I to S only, never a private report's anchor.
        $a = app(SurveyItsAnchors::class)->resolve($key);

        return $a && in_array($a['source'], ['survey', 'cadastral'], true) ? $a : null;
    }

    private function liveReportFor(array $anchor): ?SR
    {
        return SR::query()
            ->when(isset($anchor['keys']['survey_its_id']), fn ($q) => $q->where('survey_its_id', $anchor['keys']['survey_its_id']))
            ->when(isset($anchor['keys']['cadastral_survey_job_id']), fn ($q) => $q->where('cadastral_survey_job_id', $anchor['keys']['cadastral_survey_job_id']))
            ->first();
    }

    private function validated(Request $r): array
    {
        $yesNo = ['nullable', Rule::in(['1', '0'])];
        $data = $r->validate([
            'grantee_name'          => 'nullable|string|max:500',
            'grid_origin'           => 'nullable|string|max:255',
            'control_points'        => 'nullable|string|max:500',
            'boundaries_pointed_by' => 'nullable|string|max:255',
            'boundaries_pointed_on' => 'nullable|date',
            'commenced_on'          => 'nullable|date',
            'completed_on'          => 'nullable|date|after_or_equal:commenced_on',
            'distance_km'           => 'nullable|numeric|min:0|max:100000',
            'computed_on'           => 'nullable|string|max:255',
            'delay_reason'          => 'nullable|string|max:2000',
            'on_layout_plan'        => $yesNo,
            'layout_plan_no'        => 'nullable|required_if:on_layout_plan,1|string|max:100',
            'corners_demarcated'    => $yesNo,
            'hard_by'               => 'nullable|string|max:500',
            'encroachment'          => $yesNo,
            'encroachment_details'  => 'nullable|required_if:encroachment,1|string|max:2000',
            'developed'             => $yesNo,
            'developed_with'        => 'nullable|required_if:developed,1|string|max:500',
            'beacons_not_emplaced'  => 'nullable|string|max:500',
            'beacons_reason'        => 'nullable|required_with:beacons_not_emplaced|string|max:1000',
            'previous_kn'           => 'nullable|string|max:100',
            'previous_kn_coincides' => $yesNo,
            'transport_by'          => ['nullable', Rule::in(array_keys(SR::TRANSPORT))],
            'remarks'               => 'nullable|string|max:4000',
            'field_jacket_checklist'   => 'nullable|array',
            'field_jacket_checklist.*' => [Rule::in(array_keys(SR::FIELD_RECORDS))],
            'surveyor_name'         => 'nullable|string|max:255',
            'report_date'           => 'nullable|date',
        ], [
            'completed_on.after_or_equal'   => 'The survey cannot be completed before it commenced.',
            'layout_plan_no.required_if'    => 'Give the approved layout plan number.',
            'encroachment_details.required_if' => 'Describe the encroachment noticed.',
            'developed_with.required_if'    => 'Say what the plot is developed with.',
            'beacons_reason.required_with'  => 'Say why those beacons were not emplaced.',
        ]);

        foreach (['on_layout_plan', 'corners_demarcated', 'developed', 'previous_kn_coincides'] as $k) {
            $data[$k] = isset($data[$k]) && $data[$k] !== null ? (bool) $data[$k] : null;
        }
        $data['encroachment'] = (bool) ($data['encroachment'] ?? false);
        if (! $data['encroachment']) $data['encroachment_details'] = null;
        if ($data['on_layout_plan'] === false) $data['layout_plan_no'] = null;
        if ($data['developed'] === false) $data['developed_with'] = null;
        $data['field_jacket_checklist'] = array_values(array_intersect(array_keys(SR::FIELD_RECORDS), $data['field_jacket_checklist'] ?? []));

        return $data;
    }
}
