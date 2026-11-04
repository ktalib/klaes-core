<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyOpShowingPhoto as ShowingPhoto;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlotCheck as Check;
use App\Models\Survey\SurveyTpNumber;
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
        // Confirmation Forms is a tab here too (user, 2026-11-03); it switches without a page load.
        $tab = in_array($r->query('tab'), ['checks', 'old', 'confirmation'], true) ? $r->query('tab') : 'showings';
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
        $from = $r->query('from');
        $to = $r->query('to');

        // OP showings only; the old register's entries (op_source 'old', no OP) have their own tab.
        $showings = Showing::query()->whereNotNull('op_source')->where('op_source', '!=', Showing::SOURCE_OLD)->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('op_serial', 'like', $like)->orWhere('allottee_name', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)
                ->orWhere('requester_name', 'like', $like)->orWhere('tp_no', 'like', $like)))
            ->when(in_array($r->query('reason'), Showing::REASONS, true), fn ($q) => $q->where('reason', $r->query('reason')))
            ->when($r->query('reason') === 'first', fn ($q) => $q->where('showing_no', 1))
            ->when($from, fn ($q) => $q->whereDate('shown_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('shown_on', '<=', $to));

        // Plot Showing Register (Old) (user, 2026-11-03): Date, Plot No., Allottee, C of O / OP No., Pointed Out By, Remark.
        $old = Showing::query()->where('op_source', Showing::SOURCE_OLD)->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('op_serial', 'like', $like)->orWhere('allottee_name', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('surveyor_name', 'like', $like)->orWhere('notes', 'like', $like)
                ->orWhere('tp_no', 'like', $like)))
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
            // TP No. on Record Plot Showing (user, 2026-11-03): the Public Layout Plans register.
            // Only the numbers, each once (user, 2026-11-03): a number drawn twice in the register is its first entry,
            // the one an Other with that number is matched to.
            'tpNumbers' => SurveyTpNumber::listed()->whereNotNull('tp_no')->where('tp_no', '<>', '')->serialOrder()->get(['id', 'tp_no'])
                ->groupBy(fn ($t) => mb_strtoupper(str_replace(' ', '', $t->tp_no)))->map(fn ($g) => $g->sortBy('id')->first())->values(),
            'totals'   => [
                'showings'      => Showing::whereNotNull('op_source')->where('op_source', '!=', Showing::SOURCE_OLD)->count(),
                'ops'           => Showing::whereNotNull('op_source')->where('op_source', '!=', Showing::SOURCE_OLD)->distinct()->count('op_source_id'),
                'old'           => Showing::where('op_source', Showing::SOURCE_OLD)->count(),
                'checks'        => Check::count(),
                'discrepancies' => Check::where('result', Check::DISCREPANCY)->count(),
            ],
            'rows'     => match ($tab) {
                'checks' => $checks->with('photos', 'showing:id,showing_no,shown_on')->orderByDesc('checked_on')->orderByDesc('id')->paginate(20)->withQueryString(),
                'old'    => $old->with('photos')->orderByDesc('shown_on')->orderByDesc('id')->paginate(20)->withQueryString(),
                default  => $showings->with('photos', 'groundChecks:id,survey_op_showing_id,check_ref,result')->orderByDesc('shown_on')->orderByDesc('id')->paginate(20)->withQueryString(),
            },
            'editOld'  => $tab === 'old' && $r->query('edit') ? Showing::where('op_source', Showing::SOURCE_OLD)->find((int) $r->query('edit')) : null,
        ] + app(ConfirmationFormController::class)->panelData($r));
    }

    /** OPs for the Record Plot Showing form: Deeds OPs by OP serial number only (user, 2026-11-03), with the next showing. */
    public function opSearch(Request $r, OpLookup $ops)
    {
        $results = $ops->searchSerial((string) $r->query('q', ''), 20);
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
    /**
     * Plot Showing Register (Old) (user, 2026-11-03): an entry copied from the old register book, which has
     * no OP numbers ("you have to relax"). Only the date is required, plus one of plot, allottee or number;
     * "C of O No." in the book may really be the OP number, so it is kept exactly as written.
     */
    public function storeOld(Request $r)
    {
        Showing::create($this->oldValues($r) + [
            'op_source'  => Showing::SOURCE_OLD,
            'showing_no' => 1,
            'shown_at'   => '',
            'source'     => 'old_register',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('survey-module.compensation.plot-confirmations', ['tab' => 'old'])->with('success', 'Old register entry saved.');
    }

    public function updateOld(Request $r, Showing $showing)
    {
        abort_unless($showing->op_source === Showing::SOURCE_OLD, 404);
        $showing->update($this->oldValues($r) + ['updated_by' => auth()->id()]);

        return redirect()->route('survey-module.compensation.plot-confirmations', ['tab' => 'old'])->with('success', 'Old register entry updated.');
    }

    public function destroyOld(Showing $showing)
    {
        abort_unless($showing->op_source === Showing::SOURCE_OLD, 404);
        $showing->update(['updated_by' => auth()->id()]);
        $showing->delete();

        return redirect()->route('survey-module.compensation.plot-confirmations', ['tab' => 'old'])->with('success', 'Old register entry removed.');
    }

    private function oldValues(Request $r): array
    {
        $d = $r->validate([
            // The date is optional (user, 2026-11-03: "remove validation on the date"): lines may be undated. A date
            // after today is still refused (user, same day: "yes, optional").
            'shown_on'      => 'nullable|date|before_or_equal:today',
            'plot_no'       => 'nullable|string|max:100',
            'allottee_name' => 'nullable|string|max:255',
            'op_serial'     => 'nullable|string|max:50',
            'pointed_by'    => 'nullable|string|max:50',
            'pointed_by_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('pointed_by') === 'other')],
            'notes'         => 'nullable|string|max:2000',
            'tp'            => 'nullable|string|max:20',
            'tp_other'      => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('tp') === 'other')],
            'requester_is_allottee' => 'nullable|in:0,1',
            'requester_name' => 'nullable|string|max:255',
        ], [
            'shown_on.date'            => 'Enter the date as a date, or leave it empty.',
            'shown_on.before_or_equal' => 'The date cannot be in the future.',
            'tp_other.required'        => 'Specify the TP No.',
            'pointed_by_other.required' => "Type the name of the surveyor who pointed out the plot.",
        ]);
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;

        // Pointed Out By (user, 2026-11-03): a Survey staff member from the list, or a name under Other
        // (taken as that staff member if the name is on the list). Optional: the book may not say.
        [$staffId, $byName] = [null, null];
        if (($d['pointed_by'] ?? '') === 'other') {
            $byName = $clean($d['pointed_by_other']);
            $match = SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($byName));
            [$staffId, $byName] = [$match?->id, $match?->full_name ?? $byName];
        } elseif (! empty($d['pointed_by'])) {
            $s = SurveyorListEntry::find((int) $d['pointed_by']);
            if (! $s) throw ValidationException::withMessages(['pointed_by' => 'Choose the surveyor who pointed out the plot.']);
            [$staffId, $byName] = [$s->id, $s->full_name];
        }
        $values = [
            'shown_on'      => ($d['shown_on'] ?? '') !== '' ? $d['shown_on'] : null,   // never '' (SQL Server reads it as 1900-01-01)
            'plot_no'       => $clean($d['plot_no'] ?? null),
            'allottee_name' => $clean($d['allottee_name'] ?? null),
            // An identifier: exactly as written in the book.
            'op_serial'     => $clean($d['op_serial'] ?? null),
            // Pointed Out By (the column cannot be empty).
            'surveyors_list_id' => $staffId,
            'surveyor_name'     => $byName ?? '',
            'notes'         => $clean($d['notes'] ?? null),
            // Requester (user, 2026-11-03): ticked, the allottee is the requester.
            'requester_is_allottee' => ($d['requester_is_allottee'] ?? '0') === '1',
            'requester_name' => ($d['requester_is_allottee'] ?? '0') === '1' ? $clean($d['allottee_name'] ?? null) : $clean($d['requester_name'] ?? null),
        ] + $this->tpValue($d['tp'] ?? null, $d['tp_other'] ?? null);
        if (! $values['plot_no'] && ! $values['allottee_name'] && ! $values['op_serial']) {
            throw ValidationException::withMessages(['plot_no' => 'Enter at least the plot no., the allottee or the C of O / OP no.']);
        }

        return $values;
    }

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
            'tp'               => 'nullable|string|max:20',
            'tp_other'         => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('tp') === 'other')],
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
            'tp_other.required'         => 'Specify the TP No.',
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
        $tp = $this->tpValue($data['tp'] ?? null, $data['tp_other'] ?? null);

        $stored = [];
        try {
            $showing = DB::connection('sqlsrv')->transaction(function () use ($op, $data, $staffId, $svName, $notes, $requester, $tp, $r, &$stored) {
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
                ] + $requester + $tp);
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

    /**
     * The TP No. picked on Record Plot Showing: a Public Layout Plans entry, or Other with the number typed (an
     * identifier: kept as typed, spaces tidied). An Other that is already in the register is taken as that entry.
     */
    private function tpValue(?string $tp, ?string $other): array
    {
        if ($tp === null || $tp === '') return ['survey_tp_number_id' => null, 'tp_no' => null];
        if ($tp === 'other') {
            $typed = trim(preg_replace('/\s+/', ' ', (string) $other));
            $match = SurveyTpNumber::listed()->whereRaw("UPPER(REPLACE(tp_no, ' ', '')) = ?", [mb_strtoupper(str_replace(' ', '', $typed))])->orderBy('id')->first();

            return ['survey_tp_number_id' => $match?->id, 'tp_no' => $match?->tp_no ?? $typed];
        }
        $row = ctype_digit($tp) ? SurveyTpNumber::listed()->find((int) $tp) : null;
        if (! $row) throw ValidationException::withMessages(['tp' => 'Choose the TP No. from the list, or Other and type it.']);

        return ['survey_tp_number_id' => $row->id, 'tp_no' => $row->tp_no];
    }

    private function surveyors()
    {
        return SurveyorListEntry::query()->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']);
    }
}
