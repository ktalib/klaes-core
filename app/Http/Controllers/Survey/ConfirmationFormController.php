<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyConfirmationForm as Form;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Survey > Compensation > Confirmation Forms (user, 2026-11-02), from the new paper template. Serials are
 * issued for a layout (a Survey project, or one typed under Other, e.g. BUK/Zakara Layout) with a blank
 * printed form; the filled paper is keyed in against its serial. A serial is voided, never deleted.
 * Not on the sidebar yet (user).
 */
class ConfirmationFormController extends Controller
{
    public const TABS = ['forms' => 'Confirmation Forms', 'serials' => 'Serials & Blank Forms'];
    public const MAX_BATCH = 500;
    private const ROUTE = 'survey-module.compensation.confirmation-forms';

    public function index(Request $r)
    {
        $tab = array_key_exists($r->query('tab'), self::TABS) ? $r->query('tab') : 'forms';
        $q = trim((string) $r->query('q', ''));
        $layout = trim((string) $r->query('layout', ''));

        $forms = Form::where('status', Form::CAPTURED)
            ->when($layout !== '', fn ($w) => $w->where('layout_name', $layout))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q) . '%';
                $w->where(fn ($x) => $x->where('land_owner_name', 'like', $like)->orWhere('id_number', 'like', $like)
                    ->orWhere('nin', 'like', $like)->orWhere('gsm', 'like', $like)->orWhere('location', 'like', $like)
                    ->orWhere('section', 'like', $like)->orWhere('vendor_name', 'like', $like)
                    ->orWhere('serial_no', ctype_digit($q) ? (int) $q : -1));
            })
            ->orderByDesc('captured_at')->paginate(20, ['*'], 'page')->withQueryString();

        $status = in_array($r->query('status'), Form::STATUSES, true) ? $r->query('status') : null;
        $serials = Form::when($status, fn ($w) => $w->where('status', $status))
            ->when($layout !== '', fn ($w) => $w->where('layout_name', $layout))
            ->orderByDesc('serial_no')->paginate(25, ['*'], 'spage')->withQueryString();

        $open = Form::whereIn('status', Form::OPEN)->orderBy('serial_no')->get(['id', 'serial_no', 'status', 'layout_name']);
        $editing = $r->query('edit') ? Form::where('status', Form::CAPTURED)->find($r->query('edit')) : null;

        $counts = Form::select('status', DB::raw('COUNT(*) AS n'))->groupBy('status')->pluck('n', 'status');
        $stats = [
            'total'    => (int) $counts->sum(),
            'open'     => (int) (($counts[Form::GENERATED] ?? 0) + ($counts[Form::PRINTED] ?? 0)),
            'captured' => (int) ($counts[Form::CAPTURED] ?? 0),
            'voided'   => (int) ($counts[Form::VOIDED] ?? 0),
        ];

        $userIds = $forms->pluck('captured_by')->merge($serials->pluck('captured_by'))->filter()->unique();

        return view('survey_module.compensation.confirmation_forms', compact('tab', 'forms', 'serials', 'open', 'editing', 'stats', 'q', 'status', 'layout') + [
            'nextSerial' => ((int) Form::max('serial_no')) + 1,
            'projects'   => SurveyProject::orderBy('name')->get(['id', 'project_code', 'name']),
            'layouts'    => Form::whereNotNull('layout_name')->distinct()->orderBy('layout_name')->pluck('layout_name'),
            'users'      => \App\Models\User::whereIn('id', $userIds->all() ?: [0])->get()
                ->mapWithKeys(fn ($u) => [$u->id => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->name ?? '')]),
        ]);
    }

    /** Issue the next $count serials for a layout; the blank forms are printed from the link in the message. */
    public function generate(Request $r)
    {
        $d = $r->validate([
            'survey_project_id' => ['required'],
            'layout_other'      => 'nullable|required_if:survey_project_id,other|string|max:255',
            'count'             => 'required|integer|min:1|max:' . self::MAX_BATCH,
        ], [
            'survey_project_id.required' => 'Choose the layout, or Other and type its name.',
            'layout_other.required_if'   => 'Type the name of the layout.',
            'count.max'                  => 'At most ' . self::MAX_BATCH . ' serials at a time.',
        ]);

        if ($d['survey_project_id'] === 'other') {
            $projectId = null;
            $layoutName = trim(preg_replace('/\s+/', ' ', $d['layout_other']));
            // An Other that names a listed project is that project (no duplicate under a second spelling).
            if ($p = SurveyProject::where('name', $layoutName)->first()) { $projectId = $p->id; $layoutName = $p->name; }
        } else {
            $p = SurveyProject::find((int) $d['survey_project_id']);
            if (! $p) return back()->withInput()->withErrors(['survey_project_id' => 'That layout no longer exists. Choose another.']);
            $projectId = $p->id;
            $layoutName = $p->name;
        }
        $count = (int) $d['count'];

        [$first, $last, $ids] = DB::connection('sqlsrv')->transaction(function () use ($count, $projectId, $layoutName) {
            // Hold the highest serial so two officers cannot issue the same numbers.
            $max = (int) DB::connection('sqlsrv')->selectOne('SELECT MAX(serial_no) AS m FROM survey_confirmation_forms WITH (UPDLOCK, HOLDLOCK)')->m;
            $now = now();
            $rows = [];
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = ['serial_no' => $max + $i, 'status' => Form::GENERATED, 'survey_project_id' => $projectId, 'layout_name' => $layoutName,
                           'created_by' => Auth::id(), 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 250) as $chunk) {   // 7 columns x 250 < 2,100 parameters
                DB::connection('sqlsrv')->table('survey_confirmation_forms')->insert($chunk);
            }

            return [$max + 1, $max + $count, Form::whereBetween('serial_no', [$max + 1, $max + $count])->orderBy('serial_no')->pluck('id')->all()];
        });

        $from = Form::formatSerial($first); $to = Form::formatSerial($last);
        Log::info('Confirmation form serials issued', ['layout' => $layoutName, 'from' => $from, 'to' => $to, 'by' => Auth::id()]);

        return redirect()->route(self::ROUTE, ['tab' => 'serials'])
            ->with('success', ($count === 1 ? "Serial {$from}" : "{$count} serials, {$from} to {$to},") . " issued for {$layoutName}.")
            ->with('print_ids', $ids);
    }

    /** The form, one A4 page per serial: blank for an open serial (it then counts as printed), filled for a captured one. */
    public function print(Request $r)
    {
        $ids = collect(explode(',', (string) $r->query('ids')))->map(fn ($v) => (int) $v)->filter()->unique()->take(self::MAX_BATCH)->values();
        $forms = Form::whereIn('id', $ids->all() ?: [0])->where('status', '!=', Form::VOIDED)->orderBy('serial_no')->get();
        abort_if($forms->isEmpty(), 404, 'No form to print.');

        $blank = $forms->filter->isOpen();
        if ($blank->isNotEmpty()) {
            Form::whereIn('id', $blank->pluck('id'))->increment('print_count', 1, ['updated_at' => now()]);
            Form::whereIn('id', $blank->pluck('id'))->where('status', Form::GENERATED)->update(['status' => Form::PRINTED, 'printed_at' => now()]);
        }

        return view('survey_module.compensation.confirmation_forms_print', ['forms' => $forms]);
    }

    /** Key in a filled paper form against its serial. */
    public function capture(Request $r)
    {
        $data = $this->validated($r, true);
        $form = Form::find($data['form_id']);
        if (! $form || ! $form->isOpen()) {
            return back()->withInput()->with('error', 'That serial has already been captured or voided. Choose another.');
        }
        unset($data['form_id']);
        if ($path = $this->storePassport($r, $form)) $data['passport_path'] = $path;

        $form->update($data + ['status' => Form::CAPTURED, 'captured_by' => Auth::id(), 'captured_at' => now(), 'updated_by' => Auth::id()]);

        return redirect()->route(self::ROUTE)->with('success', "Form {$form->serial} ({$form->layout_name}) captured for {$form->land_owner_name}.");
    }

    public function update(Request $r, Form $form)
    {
        abort_unless($form->status === Form::CAPTURED, 404);
        $data = $this->validated($r, false);
        $before = $form->only(array_keys($data));
        // A new photo replaces the old one on the form; the old file is left on disk.
        if ($path = $this->storePassport($r, $form)) $data['passport_path'] = $path;
        $form->update($data + ['updated_by' => Auth::id()]);
        Log::info('Confirmation form edited', ['serial' => $form->serial, 'before' => $before, 'after' => $data, 'by' => Auth::id()]);

        return redirect()->route(self::ROUTE)->with('success', "Form {$form->serial} updated.");
    }

    /** A serial not captured can be voided (spoilt or lost paper); it is never reissued. */
    public function void(Request $r, Form $form)
    {
        $d = $r->validate(['void_reason' => 'required|string|max:500'], ['void_reason.required' => 'Give the reason for voiding this serial.']);
        if (! $form->isOpen()) {
            return back()->with('error', "Serial {$form->serial} is {$form->status} and cannot be voided.");
        }
        $form->update(['status' => Form::VOIDED, 'void_reason' => trim($d['void_reason']), 'voided_by' => Auth::id(), 'voided_at' => now(), 'updated_by' => Auth::id()]);

        return back()->with('success', "Serial {$form->serial} voided.");
    }

    /**
     * The land owner's passport, kept with the form on the public disk: a confirmation form has no
     * file number, so there is no EDMS folder to file it in.
     */
    private function storePassport(Request $r, Form $form): ?string
    {
        if (! $r->hasFile('passport')) return null;
        $file = $r->file('passport');
        $name = $form->serial . '_' . now()->format('YmdHis') . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');

        return $file->storeAs('survey/confirmation_forms/passports', $name, 'public');
    }

    private function validated(Request $r, bool $withSerial): array
    {
        $r->merge(collect($r->only(['land_owner_name', 'vendor_name', 'chairman_name', 'village_head_name', 'compensation_head_name',
                'section', 'id_number', 'location', 'address', 'vendor_address']))
            ->map(fn ($v) => is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : $v)->all());
        // Phone and NIN: digits only (a leading + kept on phones).
        foreach (['gsm', 'vendor_gsm'] as $k) if ($r->filled($k)) $r->merge([$k => preg_replace('/(?!^\+)[^0-9]/', '', (string) $r->input($k))]);
        if ($r->filled('nin')) $r->merge(['nin' => preg_replace('/\D/', '', (string) $r->input('nin'))]);

        $rules = [
            'land_owner_name'        => 'required|string|max:255',
            'section'                => 'nullable|string|max:100',
            'id_number'              => 'nullable|string|max:100',
            'location'               => 'nullable|string|max:255',
            'nin'                    => 'nullable|digits:11',
            'gsm'                    => ['nullable', 'regex:/^\+?[0-9]{7,15}$/'],
            'address'                => 'nullable|string|max:500',
            'vendor_name'            => 'nullable|string|max:255',
            'vendor_address'         => 'nullable|string|max:500',
            'vendor_date'            => 'nullable|date',
            'vendor_gsm'             => ['nullable', 'regex:/^\+?[0-9]{7,15}$/'],
            'chairman_name'          => 'nullable|string|max:255',
            'chairman_date'          => 'nullable|date',
            'village_head_name'      => 'nullable|string|max:255',
            'village_head_date'      => 'nullable|date',
            'compensation_head_name' => 'nullable|string|max:255',
            'compensation_head_date' => 'nullable|date',
            // Checked for one clear face in the browser (js/passport-face-check.js, as at commissioning).
            'passport'               => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ];
        if ($withSerial) $rules['form_id'] = 'required|integer';

        $data = $r->validate($rules, [
            'form_id.required'         => 'Choose the serial number written on the form.',
            'land_owner_name.required' => 'Enter the land owner\'s name.',
            'nin.digits'               => 'The NIN is 11 digits.',
            'gsm.regex'                => 'Enter a valid GSM number.',
            'vendor_gsm.regex'         => 'Enter a valid GSM number for the vendor / agent.',
            'passport.mimes'           => 'The passport must be a JPG or PNG photo.',
            'passport.max'             => 'The passport photo must be 2MB or smaller.',
        ]);
        unset($data['passport']);
        foreach (['vendor_signed', 'chairman_signed', 'village_head_signed', 'compensation_head_signed'] as $k) $data[$k] = $r->boolean($k);
        foreach ($data as $k => $v) if ($v === '') $data[$k] = null;

        return $data;
    }
}
