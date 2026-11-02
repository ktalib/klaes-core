<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyOpShowingPhoto as ShowingPhoto;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlotCheck as Check;
use App\Services\Survey\OpLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Plot Confirmation register (desktop, 2026-10-30): what the Plot Confirmation app
 * records in the field, for the office. Two tabs:
 *
 *   Showings       each time an OP's plot was shown to its allottee (Showing History)
 *   Ground checks  each ground check of a plot against its layout and the Beacon Register
 *
 * Opened from Records > Plot Showing Register (user, 2026-11-02). Showings are recorded in the
 * field with the app, or here with Record Plot Showing (same rules: numbered per OP, latest
 * first, a reason from the 2nd showing on). Search, filters and S/N in display order.
 */
class PlotConfirmationController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'checks' ? 'checks' : 'showings';
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
        $from = $r->query('from');
        $to = $r->query('to');

        $showings = Showing::query()->whereNotNull('op_source')->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('op_serial', 'like', $like)->orWhere('allottee_name', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)
                ->orWhere('requester_name', 'like', $like)))
            ->when(in_array($r->query('reason'), Showing::REASONS, true), fn ($q) => $q->where('reason', $r->query('reason')))
            ->when($r->query('reason') === 'first', fn ($q) => $q->where('showing_no', 1))
            ->when($from, fn ($q) => $q->whereDate('shown_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('shown_on', '<=', $to));

        $checks = Check::query()->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('check_ref', 'like', $like)->orWhere('op_serial', 'like', $like)
                ->orWhere('holder_name', 'like', $like)->orWhere('plot_no', 'like', $like)->orWhere('layout_no', 'like', $like)
                ->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)->orWhere('beacons_found', 'like', $like)))
            ->when(in_array($r->query('result'), Check::RESULTS, true), fn ($q) => $q->where('result', $r->query('result')))
            ->when($from, fn ($q) => $q->whereDate('checked_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('checked_on', '<=', $to));

        return view('survey_module.compensation.plot_confirmations', [
            'tab'      => $tab,
            'surveyors' => $this->surveyors(),
            'totals'   => [
                'showings'      => Showing::whereNotNull('op_source')->count(),
                'ops'           => Showing::whereNotNull('op_source')->distinct()->count('op_source_id'),
                'checks'        => Check::count(),
                'discrepancies' => Check::where('result', Check::DISCREPANCY)->count(),
            ],
            'rows'     => $tab === 'checks'
                ? $checks->with('photos')->orderByDesc('checked_on')->orderByDesc('id')->paginate(20)->withQueryString()
                : $showings->with('photos')->orderByDesc('shown_on')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    /** OPs for the Record Plot Showing form: Deeds OPs by serial, allottee, plot or file no., with the next showing. */
    public function opSearch(Request $r, OpLookup $ops)
    {
        $results = $ops->search((string) $r->query('q', ''), 20);
        $counts = Showing::whereNotNull('op_source')->selectRaw('op_source, op_source_id, MAX(showing_no) n')->groupBy('op_source', 'op_source_id')
            ->get()->mapWithKeys(fn ($x) => [$x->op_source . '-' . $x->op_source_id => (int) $x->n]);

        return response()->json(array_map(function ($op) use ($counts) {
            $next = ($counts[$op['key']] ?? 0) + 1;

            return array_intersect_key($op, array_flip(['key', 'serial', 'type', 'allottee', 'address', 'plot_no', 'district', 'lga', 'location', 'layout', 'file_number']))
                + ['next' => $next, 'next_label' => Showing::ordinal($next) . ' showing'];
        }, $results));
    }

    /**
     * Record Plot Showing (office, user 2026-11-02): one showing of an OP's plot, as the Plot
     * Confirmation app records it: surveyor, date, time, allottee there, photos, and from the
     * 2nd showing a reason (Change of Ownership / Verification).
     */
    public function storeShowing(Request $r, OpLookup $ops)
    {
        $op = $ops->find((string) $r->input('op'));
        if (! $op) {
            throw ValidationException::withMessages(['op' => 'Find and pick the OP that was shown.']);
        }
        $count = (int) Showing::where('op_source', $op['source'])->where('op_source_id', $op['id'])->max('showing_no');

        $data = $r->validate([
            'surveyor'         => 'required|string|max:50',
            'surveyor_other'   => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === 'other')],
            'shown_on'         => 'required|date|before_or_equal:today',
            'shown_at'         => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'allottee_present' => 'required|in:1,0',
            'reason'           => [Rule::requiredIf($count > 0), 'nullable', Rule::in(Showing::REASONS)],
            'notes'            => 'nullable|string|max:2000',
            'requester_is_allottee' => 'nullable|in:0,1',
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|mimes:jpg,jpeg,png|max:12288',
        ] + \App\Support\AddressBuilder::rules('req_'), [
            'req_district.required'       => "Choose the requester's district.",
            'req_district_other.required' => "Specify the requester's district.",
            'req_street_other.required'   => "Specify the requester's street.",
            'req_lga.required'            => "Choose the requester's LGA.",
            'req_state.required'          => "Choose the requester's state.",
            'surveyor.required'         => 'Choose the surveyor who showed the plot.',
            'surveyor_other.required'   => "Type the surveyor's name.",
            'shown_at.regex'            => 'Give the time as HH:MM.',
            'allottee_present.required' => 'Say whether the allottee was there.',
            'reason.required'           => 'This OP has been shown before: say why (Change of Ownership or Verification).',
            'photos.max'                => 'At most 10 photographs per showing.',
            'photos.*.mimes'            => 'Photographs must be JPG or PNG.',
            'photos.*.max'              => 'Each photograph can be at most 12 MB.',
        ]);

        // A Survey staff member, or a name typed under Other (taken as that staff member if on the list).
        if ($data['surveyor'] === 'other') {
            $name = trim(preg_replace('/\s+/', ' ', (string) $data['surveyor_other']));
            $match = SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($name));
            [$staffId, $svName] = [$match?->id, $match?->full_name ?? $name];
        } elseif ($s = SurveyorListEntry::find((int) $data['surveyor'])) {
            [$staffId, $svName] = [$s->id, $s->full_name];
        } else {
            throw ValidationException::withMessages(['surveyor' => 'Choose the surveyor.']);
        }
        $notes = trim(preg_replace('/\s+/', ' ', (string) ($data['notes'] ?? '')));
        // Who asked for the showing (user, 2026-11-02).
        $requester = \App\Services\Survey\ShowingRequester::resolve($r, $op);

        $stored = [];
        try {
            $showing = DB::connection('sqlsrv')->transaction(function () use ($op, $data, $staffId, $svName, $notes, $requester, $r, &$stored) {
                // Re-read inside the transaction so two officers saving at once do not share a number.
                $no = (int) Showing::where('op_source', $op['source'])->where('op_source_id', $op['id'])->lockForUpdate()->max('showing_no') + 1;
                $showing = Showing::create([
                    'op_source'         => $op['source'],
                    'op_source_id'      => $op['id'],
                    'op_serial'         => $op['serial'],
                    'allottee_name'     => $op['allottee'],
                    'plot_no'           => $op['plot_no'],
                    'district'          => $op['district'],
                    'lga'               => $op['lga'],
                    'location'          => $op['location'] ? mb_substr($op['location'], 0, 500) : null,
                    'showing_no'        => $no,
                    'shown_on'          => $data['shown_on'],
                    'shown_at'          => $data['shown_at'],
                    'surveyors_list_id' => $staffId,
                    'surveyor_name'     => $svName,
                    'reason'            => $no > 1 ? $data['reason'] : null,
                    'allottee_present'  => (bool) $data['allottee_present'],
                    'notes'             => $notes === '' ? null : $notes,
                    'source'            => 'office',
                ] + $requester);
                foreach ((array) $r->file('photos', []) as $file) {
                    $path = Storage::disk(ShowingPhoto::DISK)->putFileAs("app/survey/op-showings/{$op['source']}-{$op['id']}", $file,
                        Str::uuid() . '.' . strtolower($file->extension() ?: 'jpg'));
                    $stored[] = $path;
                    ShowingPhoto::create([
                        'survey_op_showing_id' => $showing->id,
                        'path'                 => $path,
                        'original_name'        => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime'                 => $file->getMimeType(),
                        'size'                 => $file->getSize(),
                    ]);
                }

                return $showing;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) Storage::disk(ShowingPhoto::DISK)->delete($path);   // nothing left behind
            throw $e;
        }

        \App\Services\Survey\LayoutRecords::forget();   // Layout Records counts showings

        return redirect()->route('survey-module.compensation.plot-confirmations')
            ->with('success', "{$showing->label} of OP {$showing->op_serial} recorded.");
    }

    private function surveyors()
    {
        return SurveyorListEntry::query()->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']);
    }
}
