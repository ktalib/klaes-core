<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyItsCoordinate;
use App\Models\Survey\SurveyItsObservation;
use App\Models\Survey\SurveyTpNumber;
use App\Models\Survey\SurveyRegisterFile;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\PrivateRegisterLines;
use App\Services\Survey\SurveyDocuments;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\Survey\SurveyorListEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examination/Verification > Surveyors' Report (docs/KLAES-CORE Survey
 * Module.md 5.7): "if discrepancies are found between the submitted plan and the
 * existing records, this unit generates and prints a Surveyors Report detailing the
 * required changes." Raised from a Register File line (its file details are taken
 * from it) or on its own. Draft until issued; issuing locks and prints it.
 *
 * Surveyor's Report (Private) (change list p.2, 2026-10-29): once saved it carries the
 * same core records as the GKN report: List of Coordinates, Beacon Nos, Computation
 * Field Observations and the Sketch Plan, edited with the GKN editors anchored
 * "private-<id>" (SurveyItsAnchors) and locked with the report on issue.
 */
class ChangeReportController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

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
        $groups = app(PrivateRegisterLines::class)->groups();
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
            'report_date' => now()->toDateString(), 'examined_by' => Auth::user()?->name,
        ]);

        // From a Private Beacon Register row. Only a row that exists: this is a URL, and a prefilled file number locks.
        $group = ! $line && $r->query('beacon') ? app(PrivateRegisterLines::class)->groups()->get((string) $r->query('beacon')) : null;
        if ($group) {
            $found = $group['file_number'] ? app(SurveyItsController::class)->lookup(Request::create('/', 'GET', ['file_number' => $group['file_number']]))->getData(true) : [];
            $report->fill([
                'file_number' => $group['file_number'], 'file_title' => $found['title'] ?? null, 'plan_no' => $group['plan_no'], 'surveyor_name' => $group['surveyor'],
                'plot_no' => ($found['plot'] ?? null) ?: $group['plot_no'], 'district' => ($found['district'] ?? null) ?: $group['district'], 'lga' => ($found['lga'] ?? null) ?: $group['lga'],
            ]);
        }

        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $line, 'surveyors' => $this->surveyorOptions(), 'plans' => $this->planOptions(),
            'fromBeacons' => (bool) ($group['file_number'] ?? null), 'beaconLine' => $group && ! $group['file_number'] ? $group['line_id'] : null]);
    }

    /**
     * "Plan prepared by" dropdown (user, 2026-11-03): Survey Department staff and the SURCON register,
     * as on the Beacon Register, keyed "staff:<id>" / "surcon:<id>". "Other" is added by the view.
     */
    private function surveyorOptions(): array
    {
        $out = ['staff' => [], 'surcon' => []];
        foreach (SurveyorListEntry::active()->orderBy('first_name')->orderBy('surname')->get() as $s) {
            $out['staff']['staff:' . $s->id] = ['name' => $s->full_name, 'hint' => $s->rank];
        }
        $surcon = DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')
            ->when(\App\Services\Cadastral\SurveyStaffSync::installed(), fn ($q) => $q->whereNull('surveyors_list_id'))
            ->orderBy('full_name')->get(['id', 'full_name', 'surcon_number']);
        foreach ($surcon as $s) {
            $out['surcon']['surcon:' . $s->id] = ['name' => trim($s->full_name), 'hint' => $s->surcon_number ? 'Reg. ' . $s->surcon_number : null];
        }

        return $out;
    }

    /** TP No. dropdown (user, 2026-11-06: "plan no is the same as TP no"): the Public Layout Plans register, as on Record Plot Showing. "Other" is added by the view. */
    private function planOptions(): array
    {
        return SurveyTpNumber::listed()->whereNotNull('tp_no')->where('tp_no', '<>', '')->serialOrder()->get(['id', 'tp_no', 'layout_name'])
            ->map(fn ($t) => ['no' => trim((string) $t->tp_no), 'name' => trim((string) $t->layout_name)])
            ->filter(fn ($t) => $t['no'] !== '')->unique(fn ($t) => self::tpKey($t['no']))->values()->all();
    }

    /** The same TP number whatever its case or spaces. */
    public static function tpKey(?string $no): string
    {
        return mb_strtoupper(str_replace(' ', '', (string) $no));
    }

    /** The TP No. to store (in plan_no): one from the register as it is written there, or typed under Other. */
    private function planNo(Request $r): ?string
    {
        $choice = trim((string) $r->input('plan_choice'));
        if ($choice === '') return null;
        $typed = $choice === 'other';
        $wanted = $typed ? trim(preg_replace('/\s+/u', ' ', (string) $r->input('plan_no_other'))) : $choice;
        if ($typed && $wanted === '') throw ValidationException::withMessages(['plan_no_other' => 'Type the TP number.']);

        // A listed number typed again under Other, in any case or spacing, is that number.
        $listed = collect($this->planOptions())->first(fn ($p) => self::tpKey($p['no']) === self::tpKey($wanted));
        if (! $listed && ! $typed) throw ValidationException::withMessages(['plan_choice' => 'Choose a TP number from the list, or Other.']);

        return $listed['no'] ?? mb_substr($wanted, 0, 100);
    }

    /** The stored name for the dropdown choice: read from the source table, or typed under Other. */
    private function surveyorName(Request $r): ?string
    {
        $choice = (string) $r->input('surveyor_choice');
        if ($choice === '') return null;
        if ($choice === 'other') {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $r->input('surveyor_name_other')));
            if ($name === '') throw ValidationException::withMessages(['surveyor_name_other' => "Type the name of the surveyor who prepared the plan."]);
            return mb_substr($name, 0, 255);
        }
        [$src, $id] = array_pad(explode(':', $choice, 2), 2, null);
        $name = match ($src) {
            'staff'  => SurveyorListEntry::find((int) $id)?->full_name,
            'surcon' => DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')->where('id', (int) $id)->value('full_name'),
            default  => null,
        };
        if (! $name) throw ValidationException::withMessages(['surveyor_choice' => 'Choose the surveyor who prepared the plan.']);

        return trim($name);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        if (! empty($data['survey_register_file_id'])
            && ($existing = CR::where('survey_register_file_id', $data['survey_register_file_id'])->first())) {
            return redirect()->route('survey-module.workflow.change-reports.edit', $existing)
                ->with('error', "That Register File line already has {$existing->report_ref}; it is opened here.");
        }
        $report = CR::create($data + ['report_ref' => CR::nextRef('report_ref', 'SRC'), 'status' => CR::DRAFT]);

        return redirect()->route('survey-module.workflow.change-reports.edit', $report)
            ->with('success', "{$report->report_ref} saved as a draft. Issue it to print and send.");
    }

    public function edit(CR $report)
    {
        return view('survey_module.workflow.change_reports.form', ['report' => $report, 'line' => $report->registerFile, 'records' => $this->records($report), 'surveyors' => $this->surveyorOptions(), 'plans' => $this->planOptions()]);
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
        $report->update(['status' => CR::ISSUED, 'issued_at' => now(), 'issued_by' => Auth::id()]);

        return redirect()->route('survey-module.workflow.change-reports.print', $report);
    }

    public function destroy(CR $report)
    {
        if ($report->isIssued()) return back()->with('error', "{$report->report_ref} has been issued and cannot be deleted.");
        $ref = $report->report_ref;
        $report->delete();

        return redirect()->route('survey-module.workflow.change-reports.index')->with('success', "Draft {$ref} deleted.");
    }

    public function print(CR $report)
    {
        return view('survey_module.workflow.change_reports.print', ['report' => $report, 'records' => $this->records($report)]);
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
        $data = $r->validate([
            'survey_register_file_id' => ['nullable', Rule::exists('sqlsrv.survey_register_files', 'id')->whereNull('deleted_at')],
            'survey_beacon_register_id' => ['nullable', Rule::exists('sqlsrv.survey_beacon_register', 'id')->where('layout_type', 'Private')->whereNull('deleted_at')],
            // From a Register File line the file is the line's; otherwise it must be picked.
            'file_number'      => ($existing?->survey_register_file_id || $r->filled('survey_register_file_id')) ? 'nullable|string|max:100' : 'required|string|max:100',
            'file_title'       => 'nullable|string|max:500',
            'plot_no'          => 'nullable|string|max:100',
            'district'         => 'nullable|string|max:255',
            'lga'              => 'nullable|string|max:255',
            'plan_choice'      => 'nullable|string|max:100',
            'plan_no_other'    => 'nullable|string|max:100',
            'surveyor_choice'  => 'nullable|string|max:50',
            'surveyor_name_other' => 'nullable|string|max:255',
            'discrepancies'    => 'required|string|max:4000',
            'required_changes' => 'required|string|max:4000',
            'report_date'      => 'nullable|date',
            'examined_by'      => 'nullable|string|max:255',
        ], [
            'file_number.required'      => 'Select the file with Select File Number, or raise the report from its Register File line.',
            'discrepancies.required'    => 'Describe what does not match the records.',
            'required_changes.required' => 'List the changes required.',
        ]);

        unset($data['surveyor_choice'], $data['surveyor_name_other'], $data['plan_choice'], $data['plan_no_other']);
        $data['surveyor_name'] = $this->surveyorName($r);
        $data['plan_no'] = $this->planNo($r);

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
