<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyLpkn;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyOpShowingPhoto as ShowingPhoto;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlotCheck as Check;
use App\Models\Survey\SurveyPlotCheckPhoto as CheckPhoto;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\OpLookup;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Plot Confirmation app (App\Support\SurveyMobileApps 'plot', user 2026-10-30). Two jobs:
 *
 *   Show OP plot   the surveyor shows an Occupancy Permit's plot to its allottee and
 *                  records the visit (Showing History: survey_op_showings, numbered per
 *                  OP, reason required from the 2nd showing), with GPS and photos
 *   Ground check   the surveyor stands on an OP's plot, or a plot of a layout plan,
 *                  reads the beacon numbers there and records Confirmed / Discrepancy;
 *                  the beacons are checked against the Beacon Register when it is saved
 *
 * OPs are the Deeds OPs (OpLookup). Records save offline through the SCF queue
 * (client_uuid, safe to resend); photos follow, one at a time. Photos stay on the
 * private local disk: an OP has no land file, so there is no EDMS folder for them.
 */
class MobilePlotController extends Controller
{
    public const OTHER = '__other__';

    public const SURVEYOR_OTHER = self::OTHER;

    public function __construct(private OpLookup $ops)
    {
    }

    /** Home: today's and this month's work, and the latest records. */
    public function index()
    {
        $mineS = fn () => Showing::where('created_by', Auth::id())->whereNotNull('op_source');
        $mineC = fn () => Check::where('created_by', Auth::id());

        return view('survey_module.mobile.plots.index', [
            'stats' => [
                'showings_today' => $mineS()->whereDate('created_at', today())->count(),
                'showings_month' => $mineS()->where('created_at', '>=', now()->startOfMonth())->count(),
                'checks_today'   => $mineC()->whereDate('created_at', today())->count(),
                'checks_month'   => $mineC()->where('created_at', '>=', now()->startOfMonth())->count(),
                'discrepancies'  => $mineC()->where('result', Check::DISCREPANCY)->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'showings' => $mineS()->withCount('photos')->orderByDesc('id')->limit(8)->get(),
            'checks'   => $mineC()->withCount('photos')->orderByDesc('id')->limit(8)->get(),
        ]);
    }

    /** Find an OP: serial, allottee, plot no., file no. or registration no. */
    public function ops(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        $for = $r->query('for') === 'check' ? 'check' : 'show';

        return view('survey_module.mobile.plots.ops', [
            'q'       => $q,
            'for'     => $for,
            'results' => $q !== '' ? $this->ops->search($q) : [],
            'counts'  => $q !== '' ? $this->showingCounts() : collect(),
        ]);
    }

    /** One OP: its details, its Showing History, and the form for the next showing. */
    public function op(string $op)
    {
        $record = $this->opOr404($op);
        $showings = Showing::with('photos')->where('op_source', $record['source'])->where('op_source_id', $record['id'])
            ->orderByDesc('showing_no')->get();

        return view('survey_module.mobile.plots.op', [
            'op'        => $record,
            'showings'  => $showings,
            'next'      => (int) $showings->max('showing_no') + 1,
            'checks'    => Check::where('op_source', $record['source'])->where('op_source_id', $record['id'])->orderByDesc('id')->limit(5)->get(),
            'surveyors' => $this->surveyors(),
            'me'        => $this->myStaffId(),
        ]);
    }

    /** One showing. JSON for the offline queue; photos follow to the returned URL. */
    public function storeShowing(Request $r, string $op)
    {
        $record = $this->opOr404($op);
        $uuid = $r->input('client_uuid') ?: null;
        if ($uuid && ($done = Showing::where('client_uuid', $uuid)->first())) {
            return $this->answerShowing($r, $record, $done, true);
        }

        $count = (int) Showing::where('op_source', $record['source'])->where('op_source_id', $record['id'])->max('showing_no');
        $data = $r->validate([
            'client_uuid'      => 'nullable|string|max:40',
            'surveyor'         => 'required|string|max:50',
            'surveyor_other'   => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === self::SURVEYOR_OTHER)],
            'shown_on'         => 'required|date|before_or_equal:today',
            'shown_at'         => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'allottee_present' => 'required|in:1,0',
            'reason'           => [Rule::requiredIf($count > 0), 'nullable', Rule::in(Showing::REASONS)],
            'notes'            => 'nullable|string|max:2000',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180|required_with:latitude',
            'accuracy_m'       => 'nullable|numeric|min:0|max:100000',
        ], [
            'surveyor.required'         => 'Choose the surveyor who showed the plot.',
            'surveyor_other.required'   => "Type the surveyor's name.",
            'shown_at.regex'            => 'Give the time as HH:MM.',
            'allottee_present.required' => 'Say whether the allottee was there.',
            'reason.required'           => 'This OP has been shown before: say why (Change of Ownership or Verification).',
        ]);
        [$staffId, $name] = $this->surveyor($data);

        try {
            $showing = DB::connection('sqlsrv')->transaction(function () use ($record, $data, $staffId, $name, $uuid) {
                // Re-read inside the transaction so two officers saving at once do not share a number.
                $no = (int) Showing::where('op_source', $record['source'])->where('op_source_id', $record['id'])->lockForUpdate()->max('showing_no') + 1;

                return Showing::create([
                    'op_source'         => $record['source'],
                    'op_source_id'      => $record['id'],
                    'op_serial'         => $record['serial'],
                    'allottee_name'     => $record['allottee'],
                    'plot_no'           => $record['plot_no'],
                    'district'          => $record['district'],
                    'lga'               => $record['lga'],
                    'location'          => $record['location'] ? mb_substr($record['location'], 0, 500) : null,
                    'showing_no'        => $no,
                    'shown_on'          => $data['shown_on'],
                    'shown_at'          => $data['shown_at'],
                    'surveyors_list_id' => $staffId,
                    'surveyor_name'     => $name,
                    'reason'            => $no > 1 ? $data['reason'] : null,
                    'allottee_present'  => (bool) $data['allottee_present'],
                    'notes'             => $this->text($data['notes'] ?? null),
                    'latitude'          => $data['latitude'] ?? null,
                    'longitude'         => $data['longitude'] ?? null,
                    'accuracy_m'        => $data['accuracy_m'] ?? null,
                    'client_uuid'       => $uuid,
                    'source'            => 'mobile',
                ]);
            });
        } catch (QueryException $e) {
            if ($uuid && ($done = Showing::where('client_uuid', $uuid)->first())) return $this->answerShowing($r, $record, $done, true);
            throw $e;
        }

        \App\Services\Survey\LayoutRecords::forget();   // Layout Records counts showings

        return $this->answerShowing($r, $record, $showing, false);
    }

    public function storeShowingPhoto(Request $r, Showing $showing)
    {
        abort_unless($showing->op_source, 404);

        return $this->storePhoto($r, ShowingPhoto::class, 'survey_op_showing_id', $showing->id, "app/survey/op-showings/{$showing->op_source}-{$showing->op_source_id}");
    }

    public function showingPhoto(ShowingPhoto $photo)
    {
        return $this->servePhoto(ShowingPhoto::DISK, $photo);
    }

    /** The ground-check form: for an OP (?op=capture-1) or a plot of a layout plan. */
    public function createCheck(Request $r)
    {
        $op = $r->query('op') ? $this->ops->find((string) $r->query('op')) : null;

        return view('survey_module.mobile.plots.check_form', [
            'op'        => $op,
            'layouts'   => SurveyLpkn::orderBy('lpkn_number')->get(['id', 'lpkn_number', 'layout_name', 'prop_district', 'prop_lga']),
            'surveyors' => $this->surveyors(),
            'me'        => $this->myStaffId(),
        ]);
    }

    public function storeCheck(Request $r)
    {
        $uuid = $r->input('client_uuid') ?: null;
        if ($uuid && ($done = Check::where('client_uuid', $uuid)->first())) {
            return $this->answerCheck($r, $done, true);
        }

        $data = $r->validate([
            'client_uuid'       => 'nullable|string|max:40',
            'target_type'       => ['required', Rule::in([Check::TARGET_OP, Check::TARGET_LAYOUT])],
            'op'                => 'nullable|required_if:target_type,op|string|max:40',
            'layout'            => 'nullable|required_if:target_type,layout|string|max:20',
            'layout_other'      => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('target_type') === Check::TARGET_LAYOUT && $r->input('layout') === self::OTHER)],
            'plot_no'           => 'nullable|required_if:target_type,layout|string|max:100',
            'district'          => 'nullable|string|max:150',
            'lga'               => 'nullable|string|max:150',
            'surveyor'          => 'required|string|max:50',
            'surveyor_other'    => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === self::SURVEYOR_OTHER)],
            'checked_on'        => 'required|date|before_or_equal:today',
            'checked_at'        => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'beacons_found'     => 'nullable|string|max:1000',
            'result'            => ['required', Rule::in(Check::RESULTS)],
            'discrepancy'       => ['nullable', 'required_if:result,' . Check::DISCREPANCY, Rule::in(Check::DISCREPANCIES)],
            'discrepancy_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('result') === Check::DISCREPANCY && $r->input('discrepancy') === Check::OTHER)],
            'notes'             => 'nullable|string|max:2000',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180|required_with:latitude',
            'accuracy_m'        => 'nullable|numeric|min:0|max:100000',
        ], [
            'op.required_if'                => 'Find the OP whose plot you are checking.',
            'layout.required_if'            => 'Choose the layout plan.',
            'layout_other.required'         => 'Type the layout number.',
            'plot_no.required_if'           => 'Give the plot number.',
            'surveyor.required'             => 'Choose the surveyor.',
            'surveyor_other.required'       => "Type the surveyor's name.",
            'result.required'               => 'Say whether the plot is confirmed or there is a discrepancy.',
            'discrepancy.required_if'       => 'Say what the discrepancy is.',
            'discrepancy_other.required'    => 'Describe the discrepancy.',
            'checked_at.regex'              => 'Give the time as HH:MM.',
        ]);
        [$staffId, $name] = $this->surveyor($data);

        // The plot: copied from the OP, or the layout plan and the plot typed on site.
        $target = ['target_type' => $data['target_type']];
        if ($data['target_type'] === Check::TARGET_OP) {
            $op = $this->ops->find($data['op']);
            if (! $op) throw ValidationException::withMessages(['op' => 'That OP was not found.']);
            $target += ['op_source' => $op['source'], 'op_source_id' => $op['id'], 'op_serial' => $op['serial'], 'holder_name' => $op['allottee'],
                        'plot_no' => $op['plot_no'], 'district' => $op['district'], 'lga' => $op['lga'], 'layout_no' => $op['layout'],
                        'location' => $op['location'] ? mb_substr($op['location'], 0, 500) : null];
        } else {
            $lpkn = $data['layout'] !== self::OTHER ? SurveyLpkn::find((int) $data['layout']) : null;
            if ($data['layout'] !== self::OTHER && ! $lpkn) throw ValidationException::withMessages(['layout' => 'Choose the layout plan.']);
            $target += ['survey_lpkn_id' => $lpkn?->id, 'layout_no' => $lpkn?->lpkn_number ?? mb_strtoupper($this->text($data['layout_other'])),
                        'plot_no' => mb_strtoupper($this->text($data['plot_no'])),
                        // Places as typed, spacing tidied; district names are kept upper case like the land records.
                        'district' => ($d = $this->text($data['district'] ?? null)) ? mb_strtoupper($d) : null,
                        'lga' => ($l = $this->text($data['lga'] ?? null)) ? mb_convert_case(mb_strtolower($l), MB_CASE_TITLE) : null];
            $target['location'] = implode(', ', array_filter([$target['district'], $target['lga']])) ?: null;
        }

        // Beacons read off the ground, against the Beacon Register.
        $codes = collect(preg_split('/[\s,;]+/', (string) ($data['beacons_found'] ?? '')))->map(fn ($c) => trim($c))->filter()->values()->all();
        $check = app(BeaconNumberCheck::class)->forCodes($codes);

        try {
            $row = Check::create($target + [
                'check_ref'         => Check::nextRef('check_ref', 'PC', 4),
                'checked_on'        => $data['checked_on'],
                'checked_at'        => $data['checked_at'] ?? null,
                'surveyors_list_id' => $staffId,
                'surveyor_name'     => $name,
                'beacons_found'     => $codes ? implode(', ', array_column($check, 'beacon')) : null,
                'beacon_check'      => $check ?: null,
                'result'            => $data['result'],
                'discrepancy'       => $data['result'] === Check::DISCREPANCY ? $data['discrepancy'] : null,
                'discrepancy_other' => $data['result'] === Check::DISCREPANCY && $data['discrepancy'] === Check::OTHER ? $this->text($data['discrepancy_other']) : null,
                'notes'             => $this->text($data['notes'] ?? null),
                'latitude'          => $data['latitude'] ?? null,
                'longitude'         => $data['longitude'] ?? null,
                'accuracy_m'        => $data['accuracy_m'] ?? null,
                'client_uuid'       => $uuid,
                'source'            => 'mobile',
            ]);
        } catch (QueryException $e) {
            if ($uuid && ($done = Check::where('client_uuid', $uuid)->first())) return $this->answerCheck($r, $done, true);
            throw $e;
        }

        return $this->answerCheck($r, $row, false);
    }

    public function check(Check $check)
    {
        return view('survey_module.mobile.plots.check_show', ['check' => $check->load('photos')]);
    }

    public function storeCheckPhoto(Request $r, Check $check)
    {
        return $this->storePhoto($r, CheckPhoto::class, 'survey_plot_check_id', $check->id, "app/survey/plot-checks/{$check->id}");
    }

    public function checkPhoto(CheckPhoto $photo)
    {
        return $this->servePhoto(CheckPhoto::DISK, $photo);
    }

    /* ------------------------------------------------------------------ */

    private function opOr404(string $key): array
    {
        $op = $this->ops->find($key);
        abort_unless($op, 404);

        return $op;
    }

    /** Showings so far per OP key, for the search results. */
    private function showingCounts()
    {
        return Showing::whereNotNull('op_source')->selectRaw('op_source, op_source_id, COUNT(*) n')->groupBy('op_source', 'op_source_id')->get()
            ->mapWithKeys(fn ($x) => [$x->op_source . '-' . $x->op_source_id => (int) $x->n]);
    }

    private function surveyors()
    {
        return SurveyorListEntry::query()->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']);
    }

    /** The signed-in officer's own entry on the Survey staff list, to preselect. */
    private function myStaffId(): ?int
    {
        $u = Auth::user();
        $first = mb_strtolower(trim((string) $u?->first_name));
        $last = mb_strtolower(trim((string) $u?->last_name));
        if ($first === '' || $last === '') return null;

        return $this->surveyors()->first(fn ($s) => mb_strtolower(trim((string) $s->first_name)) === $first
            && mb_strtolower(trim((string) $s->surname)) === $last)?->id;
    }

    /** [surveyors_list_id, name]: a Survey staff member, or a name typed under Other (matched to the list if on it). */
    private function surveyor(array $data): array
    {
        if ($data['surveyor'] === self::SURVEYOR_OTHER) {
            $name = $this->text($data['surveyor_other']);
            $match = SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($name));

            return [$match?->id, $match?->full_name ?? $name];
        }
        if (preg_match('/^staff:(\d+)$/', $data['surveyor'], $m) && ($s = SurveyorListEntry::find((int) $m[1]))) {
            return [$s->id, $s->full_name];
        }
        throw ValidationException::withMessages(['surveyor' => 'Choose the surveyor.']);
    }

    private function text(?string $v): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', (string) $v));

        return $v === '' ? null : $v;
    }

    private function answerShowing(Request $r, array $op, Showing $showing, bool $duplicate)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $showing->id, 'duplicate' => $duplicate, 'label' => $showing->label,
                                      'photos' => route('survey-module.mobile.plots.showings.photos.store', $showing)]);
        }

        return redirect()->route('survey-module.mobile.plots.op', $op['key'])->with('success', "{$showing->label} recorded.");
    }

    private function answerCheck(Request $r, Check $check, bool $duplicate)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $check->id, 'duplicate' => $duplicate, 'ref' => $check->check_ref,
                                      'photos' => route('survey-module.mobile.plots.checks.photos.store', $check),
                                      'redirect' => route('survey-module.mobile.plots.checks.show', $check)]);
        }

        return redirect()->route('survey-module.mobile.plots.checks.show', $check)->with('success', "Ground check {$check->check_ref} saved.");
    }

    /** One geo-tagged photo; safe to resend (client_uuid). */
    private function storePhoto(Request $r, string $model, string $fk, int $ownerId, string $dir)
    {
        $data = $r->validate([
            'photo'       => 'required|file|image|mimes:jpg,jpeg,png|max:12288',
            'client_uuid' => 'nullable|string|max:40',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'accuracy_m'  => 'nullable|numeric|min:0|max:100000',
            'taken_at'    => 'nullable|date',
            'caption'     => 'nullable|string|max:255',
        ]);
        if (! empty($data['client_uuid']) && ($done = $model::where('client_uuid', $data['client_uuid'])->first())) {
            return response()->json(['ok' => true, 'id' => $done->id, 'duplicate' => true]);
        }
        if ($model::where($fk, $ownerId)->count() >= 10) {
            throw ValidationException::withMessages(['photo' => 'At most 10 photographs per record.']);
        }

        /** @var UploadedFile $file */
        $file = $r->file('photo');
        $path = Storage::disk($model::DISK)->putFileAs($dir, $file, Str::uuid() . '.' . strtolower($file->extension() ?: 'jpg'));
        try {
            $photo = $model::create([
                $fk             => $ownerId,
                'client_uuid'   => $data['client_uuid'] ?? null,
                'path'          => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'latitude'      => $data['latitude'] ?? null,
                'longitude'     => $data['longitude'] ?? null,
                'accuracy_m'    => $data['accuracy_m'] ?? null,
                'taken_at'      => isset($data['taken_at']) ? \Illuminate\Support\Carbon::parse($data['taken_at'])->setTimezone(config('app.timezone')) : null,
                'caption'       => $this->text($data['caption'] ?? null),
            ]);
        } catch (QueryException $e) {
            Storage::disk($model::DISK)->delete($path);
            if (! empty($data['client_uuid']) && ($done = $model::where('client_uuid', $data['client_uuid'])->first())) {
                return response()->json(['ok' => true, 'id' => $done->id, 'duplicate' => true]);
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'id' => $photo->id]);
    }

    private function servePhoto(string $disk, $photo)
    {
        abort_unless(Storage::disk($disk)->exists($photo->path), 404);

        return response()->file(Storage::disk($disk)->path($photo->path), [
            'Content-Type'  => $photo->mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
