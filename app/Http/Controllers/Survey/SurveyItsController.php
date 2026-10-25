<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyorListEntry;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * GKN > Instruction to Survey (user transcript, 2026-10-25).
 *
 * The register lists Survey's own instructions (survey_its) and, read-only, every
 * instruction Cadastral has issued (cadastral_survey_jobs with an I to S number),
 * with a Source column. Survey's create form follows the Cadastral survey-job
 * pattern: register the job (Draft, job number), then issue the I to S (number,
 * date, issuer) and print it. The surveyor is Survey Department staff
 * (surveyors_list), as these are Government jobs.
 *
 * The file comes from the global file number selector (GKN / LPKN / Misc KN), and
 * its title and location are filled from the records.
 */
class SurveyItsController extends Controller
{
    public function index(Request $r)
    {
        $term   = trim((string) $r->query('q', ''));
        $source = in_array($r->query('source'), ['survey', 'cadastral'], true) ? $r->query('source') : null;
        $status = $r->query('status');

        $rows = collect();

        if ($source !== 'cadastral') {
            $q = SurveyIts::query();
            if ($term !== '') {
                $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")->orWhere('job_ref', 'like', "%$term%")
                    ->orWhere('its_number', 'like', "%$term%")->orWhere('file_title', 'like', "%$term%")
                    ->orWhere('surveyor_name', 'like', "%$term%"));
            }
            if ($status) $q->where('status', $status);
            foreach ($q->get() as $i) {
                $rows->push(['source' => 'survey', 'model' => $i, 'id' => $i->id, 'its_number' => $i->its_number, 'job' => $i->job_ref,
                    'file_number' => $i->file_number, 'file_title' => $i->file_title, 'plot' => $i->plot_no, 'district' => $i->district,
                    'lga' => $i->lga, 'surveyor' => $i->surveyor_name, 'issued' => $i->its_issued_at, 'status' => $i->status,
                    'sort' => $i->issued_at ?? $i->created_at]);
            }
        }

        if ($source !== 'survey') {
            $q = CadastralSurveyJob::query()->whereNotNull('its_number');
            if ($term !== '') {
                $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")->orWhere('job_number', 'like', "%$term%")
                    ->orWhere('its_number', 'like', "%$term%")->orWhere('file_title', 'like', "%$term%")
                    ->orWhere('surveyor_name', 'like', "%$term%"));
            }
            if ($status) $q->where('status', $status);
            foreach ($q->get() as $j) {
                $rows->push(['source' => 'cadastral', 'model' => $j, 'id' => $j->id, 'its_number' => $j->its_number, 'job' => $j->job_number,
                    'file_number' => $j->file_number, 'file_title' => $j->file_title, 'plot' => $j->prop_plot, 'district' => $j->prop_district,
                    'lga' => $j->prop_lga, 'surveyor' => $j->surveyor_name, 'issued' => $j->its_issued_at, 'status' => $j->status,
                    'sort' => $j->issued_at ?? $j->created_at]);
            }
        }

        $rows = $rows->sortByDesc(fn ($x) => optional($x['sort'])->timestamp ?? 0)->values();
        $page = max(1, (int) $r->query('page', 1));

        return view('survey_module.gkn.its.index', [
            'rows'  => new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $r->url(), 'query' => $r->query()]),
            'stats' => [
                'survey'    => SurveyIts::count(),
                'draft'     => SurveyIts::where('status', SurveyIts::DRAFT)->count(),
                'issued'    => SurveyIts::where('status', SurveyIts::ISSUED)->count(),
                'cadastral' => CadastralSurveyJob::whereNotNull('its_number')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('survey_module.gkn.its.form', ['its' => new SurveyIts(), 'surveyors' => $this->surveyors()]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $its = SurveyIts::create($data + ['job_ref' => SurveyIts::nextRef('job_ref', 'SJ', 4), 'status' => SurveyIts::DRAFT]);

        return redirect()->route('survey-module.gkn.its.edit', $its)
            ->with('success', "Survey job {$its->job_ref} registered for {$its->file_number}. Check the instruction and issue it.");
    }

    public function edit(SurveyIts $its)
    {
        return view('survey_module.gkn.its.form', ['its' => $its, 'surveyors' => $this->surveyors($its->surveyor_id)]);
    }

    public function update(Request $r, SurveyIts $its)
    {
        if ($its->isIssued()) {
            return back()->with('error', "{$its->its_number} has been issued and can no longer be edited.");
        }

        // The file stays the job's own once registered.
        $data = $this->validated($r, $its);
        $its->update($data);

        return back()->with('success', "{$its->job_ref} updated.");
    }

    /** Issue the I to S: number, date and issuer; the instruction is locked from here. */
    public function issue(Request $r, SurveyIts $its)
    {
        $data = $r->validate(['its_instructions' => 'required|string|max:8000'], [
            'its_instructions.required' => 'The instruction text is required.',
        ]);

        if ($its->isIssued()) return back()->with('error', "{$its->job_ref} already carries {$its->its_number}.");
        if ($its->status === SurveyIts::CANCELLED) return back()->with('error', "{$its->job_ref} is cancelled.");
        if (! $its->surveyor_id) return back()->with('error', 'Assign a surveyor before issuing the instruction.');

        $its = DB::connection('sqlsrv')->transaction(function () use ($its, $data) {
            $its->update([
                'its_instructions'      => trim($data['its_instructions']),
                'its_number'            => SurveyIts::nextItsNumber(),
                'its_issued_at'         => now()->toDateString(),
                'its_issued_by'         => Auth::user()?->name,
                'its_issued_by_user_id' => Auth::id(),
                'issued_at'             => now(),
                'status'                => SurveyIts::ISSUED,
            ]);

            return $its;
        });

        return redirect()->route('survey-module.gkn.its.index')
            ->with('success', "Instruction {$its->its_number} issued to {$its->surveyor_name}.");
    }

    public function complete(SurveyIts $its)
    {
        if (! in_array($its->status, [SurveyIts::ISSUED, SurveyIts::REPORTED], true)) {
            return back()->with('error', "{$its->job_ref} is {$its->status}; only an issued job can be completed.");
        }
        $its->update(['status' => SurveyIts::COMPLETED, 'completed_at' => now()]);

        return back()->with('success', "{$its->its_number} marked completed.");
    }

    public function cancel(Request $r, SurveyIts $its)
    {
        $reason = trim((string) $r->input('reason', ''));
        if ($its->isClosed()) return back()->with('error', "{$its->job_ref} is already {$its->status}.");
        if ($reason === '') return back()->with('error', 'Give a reason for cancelling.');

        $its->update(['status' => SurveyIts::CANCELLED, 'remarks' => trim(($its->remarks ? $its->remarks . "\n" : '') . 'Cancelled ' . now()->format('d M Y') . ': ' . $reason)]);

        return back()->with('success', "{$its->job_ref} cancelled.");
    }

    public function print(SurveyIts $its)
    {
        if (! $its->isIssued()) return back()->with('error', "{$its->job_ref} has no instruction to print yet.");

        return view('survey_module.gkn.its.print', ['its' => $its->load('surveyor')]);
    }

    /** Backfill for a number picked in the global file number selector. */
    public function lookup(Request $r)
    {
        $number = trim(mb_substr((string) $r->query('file_number', ''), 0, 100));
        if ($number === '') return response()->json(['found' => false], 422);

        $db = DB::connection('sqlsrv');
        $fi = $db->table('file_indexings')->where('file_number', $number)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')->first(['id', 'file_title', 'plot_number', 'district', 'lga', 'location']);

        $out = ['found' => (bool) $fi, 'file_number' => $number, 'type' => GknApplicationController::typeOf($number),
            'title' => $fi->file_title ?? null, 'plot' => $fi->plot_number ?? null, 'district' => $fi->district ?? null, 'lga' => $fi->lga ?? null];
        if (! $fi && ($fn = $db->table('fileNumber')->where(fn ($q) => $q->where('gkn_fileno', $number)->orWhere('mlsfNo', $number))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))->orderByDesc('id')->first(['FileName', 'plot_no', 'lga']))) {
            $out = array_merge($out, ['found' => true, 'title' => $fn->FileName, 'plot' => $fn->plot_no, 'lga' => $fn->lga]);
        }

        $out['open_jobs'] = SurveyIts::where('file_number', $number)->whereNotIn('status', [SurveyIts::COMPLETED, SurveyIts::CANCELLED])->pluck('job_ref')->all();

        return response()->json(array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $out));
    }

    /* ------------------------------------------------------------------ */

    private function surveyors(?int $keep = null)
    {
        return SurveyorListEntry::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($w) => $w->orWhere('id', $keep)))
            ->orderBy('first_name')->orderBy('surname')
            ->get(['id', 'first_name', 'surname', 'other_name', 'rank', 'is_active']);
    }

    private function validated(Request $r, ?SurveyIts $existing = null): array
    {
        $data = $r->validate([
            'file_number' => $existing ? 'nullable' : 'required|string|max:100',
            'file_title'  => 'nullable|string|max:500',
            'plot_no'     => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:255',
            'lga'         => 'nullable|string|max:255',
            'surveyor_id' => ['nullable', Rule::exists('sqlsrv.surveyors_list', 'id')],
            'job_scope'   => 'nullable|string|max:4000',
            'its_instructions' => 'nullable|string|max:8000',
            'remarks'     => 'nullable|string|max:2000',
        ], [
            'file_number.required' => 'Select the file with Select File Number.',
        ]);

        if ($existing) {
            unset($data['file_number']);
        } else {
            $data['file_number'] = trim($data['file_number']);
            $data['file_type'] = GknApplicationController::typeOf($data['file_number']);
        }

        // The surveyor's name and rank are kept as they were when assigned.
        $sv = ! empty($data['surveyor_id']) ? SurveyorListEntry::find($data['surveyor_id']) : null;
        $data['surveyor_name'] = $sv?->full_name;
        $data['surveyor_rank'] = $sv?->rank;

        // The location is district + LGA; the plot number never goes in it.
        $data['location'] = implode(', ', array_filter([$data['district'] ?? null, $data['lga'] ?? null])) ?: null;
        foreach (['file_title', 'job_scope', 'remarks'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/[ \t]+/', ' ', $data[$k])) ?: null;
        }

        return $data;
    }
}
