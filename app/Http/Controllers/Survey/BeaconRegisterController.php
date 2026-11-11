<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeaconNumber;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyBeaconRegisterFile;
use App\Models\Survey\SurveyTpNumber;
use App\Models\Survey\SurveyorListEntry;
use App\Services\Survey\BeaconPool;
use App\Services\Survey\SurveySettings;
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
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    /** The module role for this page (user, 2026-10-23). */
    public const MODULE = 'Survey - Beacon Register';

    /** Legacy fixed prefix used by older private beacon numbers and the existing request flow. */
    public const PRIVATE_PREFIX = 'A';

    /**
     * EnforceModulePermission only refuses delete for now and records the rest,
     * so the page checks its own actions, as StCofoTdpController does.
     */
    private function authorizeAction(string $action): void
    {
        $user = Auth::user();
        $allowed = $user && (
            $user->canDo(self::MODULE, $action)
            || $user->canDo('Survey - Examination/Verification', $action)
        );

        abort_unless($allowed, 403,
            "You do not have the {$action} permission on " . self::MODULE . '.');
    }

    public function index(Request $r)
    {
        $this->authorizeAction('view');
        $q = $this->filtered($r);
        $view = in_array($r->query('view'), ['numbers', 'groups'], true) ? $r->query('view') : 'issues';

        $entries = (clone $q)->with([
                'creator:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'auditor:id,first_name,last_name',
                'numbers:id,survey_beacon_register_id,beacon_no,beacon_seq',
                'files:id,survey_beacon_register_id,file_number,sort_order',
            ])
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        // "By beacon number": one row per expanded number, carrying its line's details.
        $numbers = null;
        if ($view === 'numbers') {
            $nq = $this->numbersQuery($q, $r);
            $numbers = $nq->orderBy('beacon_series')->orderBy('beacon_seq')->paginate(50)->withQueryString();
        }

        // "By group": combine every suffix and prefix variant under its first letter.
        $groups = $groupNumbers = null;
        $openGroup = null;
        if ($view === 'groups') {
            $groups = SurveyBeaconNumber::query()
                ->whereIn('survey_beacon_register_id', (clone $q)->select('id'))
                ->selectRaw(self::GROUP_LETTER_SQL . ' AS beacon_series, COUNT(*) AS issued, MIN(beacon_seq) AS lowest, MAX(beacon_seq) AS highest')
                ->groupByRaw(self::GROUP_LETTER_SQL)->get()
                ->sortBy(fn ($g) => $g->beacon_series === '' ? '~' : $g->beacon_series)->values();
            $openGroup = $groups->firstWhere('beacon_series', (string) $r->query('group'))?->beacon_series;
            if ($openGroup !== null) {
                $groupNumbers = SurveyBeaconNumber::query()
                    ->whereIn('survey_beacon_register_id', (clone $q)->select('id'))
                    ->whereRaw(self::GROUP_LETTER_SQL . ' = ?', [$openGroup])->with('register.files')
                    ->orderBy('beacon_seq')->get();
            }
        }

        // "By issue" lists the letter groups; a click opens a group's entries in place (groupRows).
        $letterGroups = $view === 'issues' ? $this->letterGroups($q) : null;

        $filteredBeacons = (int) (clone $q)->sum('total_issued');
        $filteredAmount  = (float) (clone $q)->sum('amount');

        $stats = [
            'entries'        => SurveyBeaconRegister::count(),
            'beacons'        => SurveyBeaconNumber::count(),
            'this_month'     => (int) SurveyBeaconRegister::where('year', now()->year)->where('month', now()->month)->sum('total_issued'),
            'amount'         => (float) SurveyBeaconRegister::sum('amount'),
        ];

        // The same four figures split Public / Private (user, 2026-10-26).
        $stats['by_type'] = [];
        foreach (array_keys(SurveyBeaconRegister::LAYOUT_TYPES) as $type) {
            $ofType = fn () => SurveyBeaconRegister::where('layout_type', $type);
            $stats['by_type'][$type] = [
                'entries'    => $ofType()->count(),
                'beacons'    => SurveyBeaconNumber::whereIn('survey_beacon_register_id', $ofType()->select('id'))->count(),
                'this_month' => (int) $ofType()->where('year', now()->year)->where('month', now()->month)->sum('total_issued'),
                'amount'     => (float) $ofType()->sum('amount'),
            ];
        }

        $years = SurveyBeaconRegister::query()->distinct()->orderByDesc('year')->pluck('year');

        $editing = $r->filled('edit') ? SurveyBeaconRegister::find($r->query('edit')) : null;

        return view('survey_module.workflow.beacon_register', [
            'entries'         => $entries,
            'numbers'         => $numbers,
            'groups'          => $groups,
            'openGroup'       => $openGroup,
            'groupNumbers'    => $groupNumbers,
            'letterGroups'    => $letterGroups,
            'view'            => $view,
            'stats'           => $stats,
            'years'           => $years,
            'editing'         => $editing,
            'filteredBeacons' => $filteredBeacons,
            'filteredAmount'  => $filteredAmount,
            'pageBeacons'     => (int) $entries->getCollection()->sum('total_issued'),
            'surveyors'       => $this->surveyorOptions($editing),
            'companies'       => SurveySettings::companies(),
            'tpNumbers'       => $this->tpNumbers($editing),
            'pool'            => BeaconPool::status(),
        ]);
    }

    /** TP No. options from Public Layout Plans, keeping a saved legacy value editable. */
    private function tpNumbers(?SurveyBeaconRegister $entry = null)
    {
        $numbers = SurveyTpNumber::listed()->whereNotNull('tp_no')->where('tp_no', '<>', '')
            ->serialOrder()->get(['tp_no', 'layout_name'])
            ->map(fn ($row) => ['no' => trim((string) $row->tp_no), 'name' => trim((string) $row->layout_name)])
            ->filter(fn ($row) => $row['no'] !== '')
            ->unique(fn ($row) => mb_strtoupper(str_replace(' ', '', $row['no'])))
            ->values();

        if ($entry && trim((string) $entry->plan_no) !== '' && ! $numbers->contains(fn ($row) => $row['no'] === trim((string) $entry->plan_no))) {
            $numbers->prepend(['no' => trim((string) $entry->plan_no), 'name' => 'Previously saved']);
        }

        return $numbers;
    }

    /**
     * Issue Beacon No: the next free serial for a prefix and optional legacy suffix, one above the
     * highest already issued in that series. Serials above 9999 do not set the next number.
     */
    public function nextSerial(Request $r)
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z\/\-]/', '', (string) $r->query('prefix', '')));
        $suffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $r->query('suffix', '')));
        if ($prefix === '') {
            return response()->json(['message' => 'Choose a beacon prefix.'], 422);
        }
        $series = $prefix . '|' . $suffix;
        $last = (int) SurveyBeaconNumber::query()->where('beacon_series', $series)->where('beacon_seq', '<=', BeaconPool::MAX_SEQ)->max('beacon_seq');

        return response()->json(['series' => $series, 'last' => $last ?: null, 'next' => $last + 1]);
    }

    /**
     * Issue Beacon No, Public (CR 03112026): the next numbers from the pool, nothing taken yet. `skip`
     * leaves out the numbers already in the window's table. The save takes them for real.
     */
    public function nextPublic(Request $r)
    {
        $this->authorizeAction('view');
        $count = max(0, min(SurveyBeaconRegister::MAX_RANGE, (int) $r->query('count', 0)));

        return response()->json(BeaconPool::peek($count, (int) $r->query('skip', 0)));
    }

    /**
     * Duplicate Checker (user, 2026-11-05): paste numbers or ranges, separated by commas or lines, and
     * see which are in the register and who holds them. Read-only.
     */
    public function duplicateCheck(Request $r)
    {
        $this->authorizeAction('view');
        $parsed = SurveyBeaconRegister::parseList((string) $r->input('numbers', ''));
        $bad = $parsed['bad'];
        $wanted = [];
        foreach ($parsed['ranges'] as $range) {
            $wanted[$range['series']][] = [$range['start'], $range['end']];
        }

        $checked = 0;
        foreach ($wanted as $spans) {
            foreach ($spans as [$a, $b]) $checked += $b - $a + 1;
        }
        if ($checked > 5000) {
            return response()->json(['message' => 'At most 5,000 numbers at a time.'], 422);
        }

        $found = collect();
        foreach ($wanted as $series => $spans) {
            $found = $found->concat(SurveyBeaconNumber::query()->with('register:id,surveyor_name,company_name,layout_type,issue_date,beacon_from,beacon_to')
                ->where('beacon_series', $series)
                ->where(function ($w) use ($spans) {
                    foreach ($spans as [$a, $b]) $w->orWhereBetween('beacon_seq', [$a, $b]);
                })->orderBy('beacon_seq')->limit(1000)->get());
        }

        return response()->json([
            'checked' => $checked,
            'bad'     => $bad,
            'found'   => $found->map(fn ($n) => [
                'no'       => SurveyBeaconRegister::display($n->beacon_no),
                'type'     => $n->register?->layout_type_label,
                'company'  => $n->register?->company_name,
                'surveyor' => $n->register?->surveyor_name,
                'date'     => optional($n->register?->issue_date)->format('d/m/Y'),
                'entry'    => $n->register?->issued_range,
            ])->values(),
        ]);
    }

    /**
     * Issue Beacon No duplicate check: the numbers in a prefix/serial/suffix series
     * already in the register, with who they were issued to. Read-only; save checks again.
     */
    public function checkRange(Request $r)
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z\/\-]/', '', (string) $r->query('prefix', '')));
        $suffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $r->query('suffix', '')));
        $start = (int) $r->query('start', 0);
        $count = (int) $r->query('count', 0);
        $except = (int) $r->query('except', 0);   // Capture / Edit: the line being edited does not clash with itself.
        if (($prefix === '' && ! $r->boolean('capture')) || $start < 1 || $count < 1 || $count > SurveyBeaconRegister::MAX_RANGE) {
            return response()->json(['message' => 'Choose the prefix, the number of beacons and the start serial.'], 422);
        }

        $taken = SurveyBeaconNumber::query()->with('register:id,surveyor_name,issue_date,beacon_from,beacon_to')
            ->where('beacon_series', $prefix . '|' . $suffix)
            ->whereBetween('beacon_seq', [$start, $start + $count - 1])
            ->when($except, fn ($w) => $w->where('survey_beacon_register_id', '<>', $except))
            ->orderBy('beacon_seq')->get()
            ->map(fn ($n) => [
                'seq'      => (int) $n->beacon_seq,
                'no'       => SurveyBeaconRegister::display($n->beacon_no),
                'surveyor' => $n->register?->surveyor_name,
                'date'     => optional($n->register?->issue_date)->format('d/m/Y'),
                'entry'    => $n->register?->issued_range,
            ])->values();

        return response()->json(['taken' => $taken]);
    }

    public function store(Request $r)
    {
        $this->authorizeAction('create');
        [$data, $range, $files] = $this->validated($r);

        $entry = $this->saving(function () use ($data, $range, $files) {
            $entry = SurveyBeaconRegister::create($this->withCompany($data, $range));
            $this->syncNumbers($entry, $range);
            $this->syncFiles($entry, $files);

            return $entry;
        });

        return redirect()->route('survey-module.workflow.beacon-register.index')
            ->with('success', "{$entry->total_issued} beacon(s) {$entry->issued_range} recorded"
                . ($entry->surveyor_name ? " for {$entry->surveyor_name}." : '.'));
    }

    /**
     * Issue Beacon No, several groups at once (user, 2026-11-02): each group (a run of numbers with its
     * type, surveyor and location) becomes its own register entry, all in one transaction, through the
     * same checks as New Entry. Groups are saved in order, so a group overlapping an earlier one is refused.
     */
    public function issueBatch(Request $r)
    {
        $this->authorizeAction('create');
        $r->validate([
            'issue_date' => 'required|date|before_or_equal:today',
            'groups'     => 'required|array|min:1|max:50',
            'groups.*'   => 'array',
        ], [
            'issue_date.required' => 'Enter the issue date.',
            'groups.required'     => 'Add at least one group to the table.',
            'groups.max'          => 'At most 50 groups in one save.',
        ]);

        $entries = $this->issueGroups(array_values($r->input('groups')), (string) $r->input('issue_date'));

        $total = array_sum(array_map(fn ($e) => $e->total_issued, $entries));
        session()->flash('success', "{$total} beacon(s) recorded in " . count($entries) . ' entr' . (count($entries) === 1 ? 'y' : 'ies') . ': '
            . implode(', ', array_map(fn ($e) => $e->issued_range, $entries)) . '. Add the payment with Edit on each entry.');

        return response()->json(['message' => 'Saved.', 'redirect' => route('survey-module.workflow.beacon-register.index')]);
    }

    /**
     * Save groups as register entries, each through the same checks as New Entry. Also how the SG's Beacon
     * Requests screen issues (App\Services\Survey\BeaconRequests), so a request gets the same pool and
     * duplicate guard; that screen holds its own permission, so this one checks none.
     *
     * @return array<int, SurveyBeaconRegister>
     */
    public function issueGroups(array $groups, string $issueDate): array
    {
        $fields = array_merge(['layout_type', 'beacon_from', 'beacon_to', 'count', 'company_choice', 'company_other', 'surveyor_choice', 'surveyor_name_other'], AddressBuilder::columns('prop_'));
        return $this->saving(function () use ($groups, $issueDate, $fields) {
            $out = [];
            foreach ($groups as $i => $g) {
                $g = \Illuminate\Support\Arr::only((array) $g, $fields);
                $public = ($g['layout_type'] ?? null) === 'Government';
                $label = 'Group ' . ($i + 1) . (! $public && ! empty($g['beacon_from']) ? ' (' . $g['beacon_from'] . (! empty($g['beacon_to']) ? ' to ' . $g['beacon_to'] : '') . ')' : '');
                try {
                    // AddressBuilder's "Other needs specifying" rule reads the outer request, so it is checked here.
                    foreach (['street', 'district'] as $k) {
                        if (in_array('Other', (array) ($g["prop_{$k}"] ?? []), true) && trim((string) ($g["prop_{$k}_other"] ?? '')) === '') {
                            throw ValidationException::withMessages(["prop_{$k}_other" => "Specify the {$k}."]);
                        }
                    }
                    if (($g['surveyor_choice'] ?? '') === 'other' && trim((string) ($g['surveyor_name_other'] ?? '')) === '') {
                        throw ValidationException::withMessages(['surveyor_name_other' => "Type the surveyor's name."]);
                    }
                    foreach ($public ? $this->publicRuns($g) : [$this->privateRun($g)] as $run) {
                        [$data, $range] = $this->validated(Request::create('/', 'POST', array_merge($g, $run, ['issue_date' => $issueDate])));
                        $entry = SurveyBeaconRegister::create($this->withCompany($data, $range));
                        $this->syncNumbers($entry, $range);
                        $out[] = $entry;
                    }
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(['groups' => $label . ': ' . collect($e->errors())->flatten()->first()]);
                }
            }

            return $out;
        });
    }

    /**
     * Public: the numbers come from the pool, never from the client (CR 03112026). More than one run
     * when the quantity crosses into the next prefix.
     *
     * @return array<int, array{beacon_from:string, beacon_to:?string}>
     */
    private function publicRuns(array $g): array
    {
        $count = (int) ($g['count'] ?? 0);
        if ($count < 1 || $count > SurveyBeaconRegister::MAX_RANGE) {
            throw ValidationException::withMessages(['count' => 'Enter the number of beacons, at most ' . SurveyBeaconRegister::MAX_RANGE . '.']);
        }

        return array_map(fn ($run) => ['beacon_from' => $run['from'], 'beacon_to' => $run['to'] === $run['from'] ? null : $run['to']], BeaconPool::claim($count));
    }

    /** Private: selected company letters form the prefix; accept legacy A + serial + letters too. */
    private function privateRun(array $g): array
    {
        $a = SurveyBeaconRegister::parseBeacon($g['beacon_from'] ?? '');
        // Prefix letters + serial, with an optional one-letter suffix (user, 2026-11-09: "add the box for suffix too").
        $newPrefix = $a && preg_match('/^[A-Z]{1,3}$/', $a['prefix']) && preg_match('/^[A-Z]?$/', $a['suffix']);
        $legacy = $a && $a['prefix'] === self::PRIVATE_PREFIX && preg_match('/^[A-Z]{1,3}$/', $a['suffix']);
        if (! $newPrefix && ! $legacy) {
            throw ValidationException::withMessages(['beacon_from' => 'Choose one to three prefix letters A-Z, the serial, and optionally one suffix letter, e.g. B0001, AB0001 or AB0001C.']);
        }
        if (trim((string) ($g['company_choice'] ?? '')) === '' || (($g['company_choice'] ?? '') === 'other' && trim((string) ($g['company_other'] ?? '')) === '')) {
            throw ValidationException::withMessages(['company_choice' => 'Choose the company.']);
        }

        return ['beacon_from' => $g['beacon_from'], 'beacon_to' => $g['beacon_to'] ?? null];
    }

    public function update(Request $r, SurveyBeaconRegister $entry)
    {
        $this->authorizeAction('edit');
        if ($entry->audited_at) {
            return back()->with('error', 'Audited entries are locked.');
        }
        [$data, $range, $files] = $this->validated($r, $entry);

        $this->saving(function () use ($entry, $data, $range, $files) {
            $entry->update($this->withCompany($data, $range));
            $this->syncNumbers($entry, $range);
            $this->syncFiles($entry, $files);
        });

        return redirect()->route('survey-module.workflow.beacon-register.index', $r->only(['q', 'layout', 'year', 'quarter', 'month', 'status', 'view', 'page']))
            ->with('success', "Entry {$entry->issued_range} updated.");
    }

    public function destroy(SurveyBeaconRegister $entry)
    {
        $this->authorizeAction('delete');
        if ($entry->audited_at) {
            return back()->with('error', 'Audited entries are locked.');
        }
        $range = $entry->issued_range;
        DB::connection('sqlsrv')->transaction(function () use ($entry) {
            // Releases the numbers: they may be issued again on a new line.
            $entry->numbers()->get()->each->delete();
            BeaconPool::release($entry->id);
            $entry->delete();
        });

        return back()->with('success', "Entry {$range} deleted.");
    }

    /** Footer "Reviewed by": stamps the current user. */
    public function review(SurveyBeaconRegister $entry)
    {
        $this->authorizeAction('approve');
        if ($entry->reviewed_at) {
            return back()->with('error', 'This entry has already been reviewed.');
        }
        $entry->update(['reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('success', "Entry {$entry->issued_range} marked reviewed.");
    }

    /** Footer "Audited by": only after review. */
    public function audit(SurveyBeaconRegister $entry)
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
     * Expand the line's range into one survey_beacon_numbers row per number.
     * Regenerated only when the range changed, so an edit to the payment or
     * location leaves the numbers alone.
     */
    /**
     * Replace the line's file numbers with the list from the form, in order.
     *
     * @param array<int, string> $files
     */
    /**
     * Rev-M payment for the Payment section (spec: amount and file pulled from the linked
     * payment). Matches a successful payment by teller / transaction id, receipt no or bill
     * ref; the amount is the bill's Beacons line when it has one, else what was paid.
     * Reads only (2026-10-25).
     */
    public function revmLookup(Request $r)
    {
        $this->authorizeAction('view');
        $ref = trim(mb_substr((string) $r->query('ref', ''), 0, 100));
        if ($ref === '') {
            return response()->json(['found' => false, 'message' => 'Enter the teller, transaction, receipt or bill number first.'], 422);
        }

        $db = DB::connection('sqlsrv');
        $pay = $db->table('bill_payments')->where('status', 'successful')
            ->where(fn ($w) => $w->where('transaction_id', $ref)->orWhere('receipt_no', $ref)->orWhere('bill_ref_id', $ref))
            ->orderByDesc('id')->first(['bill_ref_id', 'transaction_id', 'receipt_no', 'amount', 'paid_at']);
        $billRef = $pay?->bill_ref_id
            ?? $db->table('bill_master')->where(fn ($w) => $w->where('bill_ref_id', $ref)->orWhere('receipt_no', $ref)->orWhere('transaction_id', $ref))->value('bill_ref_id');
        if (! $billRef) {
            return response()->json(['found' => false, 'message' => "No Rev-M payment found for {$ref}."]);
        }

        $bill = $db->table('bill_master')->where('bill_ref_id', $billRef)
            ->first(['bill_ref_id', 'file_number', 'payer_name', 'plot_no', 'district', 'lga', 'location', 'amount_paid', 'status']);
        $beaconLine = $db->table('bill_items')->where('bill_ref_id', $billRef)->where('item_code', 'BEACON')->sum('amount');

        return response()->json([
            'found'       => true,
            'bill_ref'    => $billRef,
            'paid'        => (bool) $pay,
            'amount'      => (float) ($beaconLine > 0 ? $beaconLine : ($pay->amount ?? $bill->amount_paid ?? 0)),
            'is_beacon'   => $beaconLine > 0,
            'paid_on'     => $pay?->paid_at ? substr((string) $pay->paid_at, 0, 10) : null,
            'teller_no'   => $pay->transaction_id ?? $pay->receipt_no ?? $ref,
            'file_number' => $bill->file_number ?? null,
            'payer'       => $bill->payer_name ?? null,
            'plot'        => $bill->plot_no ?? null,
            'location'    => $bill->location ?? implode(', ', array_filter([$bill->district ?? null, $bill->lga ?? null])),
        ]);
    }

    /**
     * The register as the printed book (spec: "a PDF that perfectly mimics the physical
     * layout"): the current filters, 20 lines a page, each page headed with its register
     * no., quarter, month and the total issued on it. Print to PDF from the browser.
     * Digital signatures are not part of this yet (user, 2026-10-25).
     */
    public function printBook(Request $r)
    {
        $this->authorizeAction('view');
        $rows = $this->filtered($r)
            ->with(['creator:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'auditor:id,first_name,last_name'])
            ->orderBy('issue_date')->orderBy('id')
            ->limit(2000)->get();

        return view('survey_module.workflow.beacon_register_print', [
            'pages'   => $rows->chunk(20),
            'filters' => $r->only(['year', 'quarter', 'month', 'layout', 'status', 'q']),
            'capped'  => $rows->count() >= 2000,
        ]);
    }

    private function syncFiles(SurveyBeaconRegister $entry, array $files): void
    {
        SurveyBeaconRegisterFile::where('survey_beacon_register_id', $entry->id)->delete();

        foreach (array_values($files) as $i => $no) {
            SurveyBeaconRegisterFile::create([
                'survey_beacon_register_id' => $entry->id,
                'file_number' => $no,
                'sort_order'  => $i,
                'created_by'  => Auth::id(),
                'updated_by'  => Auth::id(),
            ]);
        }
    }

    private function syncNumbers(SurveyBeaconRegister $entry, array $range): void
    {
        $live = SurveyBeaconNumber::query()->where('survey_beacon_register_id', $entry->id);

        if ((clone $live)->count() === $range['count']
            && (clone $live)->where('beacon_series', $range['series'])->min('beacon_seq') == $range['start']
            && (clone $live)->where('beacon_series', $range['series'])->max('beacon_seq') == $range['end']) {
            return;
        }

        SurveyBeaconNumber::withTrashed()->where('survey_beacon_register_id', $entry->id)->forceDelete();
        BeaconPool::release($entry->id);

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
        // However the number reached the register, the pool must never offer it again.
        BeaconPool::markIssued($entry->id, $range['series'], $range['start'], $range['end']);
    }

    /**
     * The company chosen, or a typed one added to the list (user recording: "if it's not there they will
     * enter it and it will update"). Run inside the save, so a refused save adds nothing.
     */
    private function withCompany(array $data, array $range): array
    {
        if (! empty($data['company_id']) || empty($data['company_name'])) return $data;

        $db = DB::connection('sqlsrv');
        $name = $data['company_name'];
        $id = $db->table('survey_companies')->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->value('id');
        $data['company_id'] = $id ?: $db->table('survey_companies')->insertGetId([
            'name'           => $name,
            'beacon_letters' => $range['suffix'] !== '' && strlen($range['suffix']) <= 3
                ? $range['suffix']
                : (preg_match('/^[A-Z]{1,3}$/', $range['prefix']) ? $range['prefix'] : null),
            'surveyor_name'  => $data['surveyor_name'] ?? null,
            'is_active'      => 1,
            'created_by'     => Auth::id(), 'updated_by' => Auth::id(),
            'created_at'     => now(), 'updated_at' => now(),
        ]);

        return $data;
    }

    /** The company for a Private line: a list entry, or a typed name (Other). Public lines carry none. */
    private function resolveCompany(Request $r): array
    {
        $none = ['company_id' => null, 'company_name' => null];
        $choice = (string) $r->input('company_choice');
        if ($r->input('layout_type') !== 'Private' || $choice === '') return $none;

        if ($choice === 'other') {
            $name = preg_replace('/\s+/', ' ', trim((string) $r->input('company_other')));
            if ($name === '') throw ValidationException::withMessages(['company_other' => "Type the company's name."]);

            return ['company_id' => null, 'company_name' => mb_substr($name, 0, 255)];
        }

        $company = ctype_digit($choice) ? DB::connection('sqlsrv')->table('survey_companies')->find($choice) : null;
        if (! $company) throw ValidationException::withMessages(['company_choice' => 'Choose a company from the list, or Other.']);

        return ['company_id' => $company->id, 'company_name' => $company->name];
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

    /** Group every beacon series and suffix by its single leading alphabetic character. */
    private const GROUP_LETTER_SQL = "CASE WHEN LEFT(beacon_series, 1) LIKE '[A-Z]' THEN LEFT(beacon_series, 1) ELSE '' END";
    private const LETTERS_SQL = self::GROUP_LETTER_SQL;

    /**
     * The leading-letter groups of a selection, in alphabetical order. Each carries its first entry,
     * the lowest number, which is the group's row in the table.
     */
    private function letterGroups($q)
    {
        return SurveyBeaconNumber::query()
            ->whereIn('survey_beacon_register_id', (clone $q)->select('id'))
            ->selectRaw(self::LETTERS_SQL . ' AS letters, COUNT(*) AS issued, COUNT(DISTINCT survey_beacon_register_id) AS entries, MIN(beacon_seq) AS lowest, MAX(beacon_seq) AS highest')
            ->groupByRaw(self::LETTERS_SQL)->get()
            ->sortBy(fn ($g) => $g->letters === '' ? '~' : $g->letters)->values()
            ->each(fn ($g) => $g->setRelation('firstEntry', $this->groupEntries($q, (string) $g->letters)->first()));
    }

    /** A letter group's entries, lowest number first, with what their table rows show. */
    private function groupEntries($q, string $letters)
    {
        $inGroup = SurveyBeaconNumber::query()->whereRaw(self::LETTERS_SQL . ' = ?', [$letters]);

        return (clone $q)->whereIn('id', (clone $inGroup)->select('survey_beacon_register_id'))
            ->with([
                'creator:id,first_name,last_name', 'reviewer:id,first_name,last_name', 'auditor:id,first_name,last_name',
                'numbers:id,survey_beacon_register_id,beacon_no,beacon_seq',
                'files:id,survey_beacon_register_id,file_number,sort_order',
            ])
            ->orderBy((clone $inGroup)->selectRaw('MIN(beacon_seq)')->whereColumn('survey_beacon_numbers.survey_beacon_register_id', 'survey_beacon_register.id'))
            ->orderBy('id');
    }

    /**
     * The remaining entries in a leading-letter group. The first entry is already shown in the group row.
     */
    public function groupRows(Request $r)
    {
        $this->authorizeAction('view');
        $letters = (string) $r->query('group');
        $first = $this->groupEntries($this->filtered($r), $letters)->value('id');

        $entries = $this->groupEntries($this->filtered($r), $letters)->where('id', '!=', (int) $first)->get();

        return response()->json([
            'html' => view('survey_module.partials.sections._beacon_issue_rows', [
                'entries' => $entries,
                'groupNo' => (int) $r->query('sn'),
                'rowStart' => 2,
            ])->render(),
        ]);
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
                    ->orWhere('company_name', 'like', "%$term%")
                    ->orWhere('surveyor_registration', 'like', "%$term%")
                    ->orWhere('plan_no', 'like', "%$term%")
                    ->orWhere('teller_no', 'like', "%$term%")
                    ->orWhere('location', 'like', "%$term%")
                    ->orWhere('prop_plot', 'like', "%$term%")
                    ->orWhere('prop_district_other', 'like', "%$term%")
                    ->orWhere('register_no', 'like', "%$term%")
                    ->orWhere('file_number', 'like', "%$term%")
                    ->orWhereIn('id', SurveyBeaconRegisterFile::query()->select('survey_beacon_register_id')
                        ->where('file_number', 'like', "%$term%"));

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
        if (array_key_exists($lt = (string) $r->query('layout'), SurveyBeaconRegister::LAYOUT_TYPES)) {
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
    private function surveyorOptions(?SurveyBeaconRegister $editing = null): array
    {
        $staff = SurveyorListEntry::query()
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($editing && $editing->surveyors_list_id, fn ($w) => $w->orWhere('id', $editing->surveyors_list_id)))
            ->orderBy('first_name')->orderBy('surname')->get();

        $surcon = DB::connection('sqlsrv')->table('cadastral_surveyors')->whereNull('deleted_at')
            // Staff copied into Cadastral Surveyors (SurveyStaffSync) are already listed above.
            ->when(\App\Services\Cadastral\SurveyStaffSync::installed(), fn ($q) => $q->whereNull('surveyors_list_id'))
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
    private function validated(Request $r, ?SurveyBeaconRegister $entry = null): array
    {
        $clean = fn ($v) => ($v = preg_replace('/\s+/', ' ', trim((string) $v))) === '' ? null : $v;
        foreach (['register_no', 'plan_no', 'surveyor_name', 'surveyor_registration', 'surveyor_name_other', 'teller_no',
                  'coord_x', 'coord_y'] as $f) {
            $r->merge([$f => $clean($r->input($f))]);
        }
        // Beacon numbers: upper case, no spaces, so "c 9302b" and "C9302B" are one number.
        foreach (['beacon_from', 'beacon_to'] as $f) {
            $r->merge([$f => SurveyBeaconRegister::normalise($r->input($f))]);
        }

        $data = $r->validate([
            'layout_type'           => ['required', Rule::in(array_keys(SurveyBeaconRegister::LAYOUT_TYPES))],
            'register_no'           => 'nullable|string|max:30',
            'issue_date'            => 'required|date|before_or_equal:today',
            'beacon_from'           => 'required|string|max:30',
            'beacon_to'             => 'nullable|string|max:30',
            'plan_no'               => ['nullable', 'string', 'max:100', Rule::in(
                $this->tpNumbers($entry)->pluck('no')->all()
            )],
            // Several file numbers, from the global selector. No format validation (user, 2026-10-23).
            'file_numbers'          => 'nullable|array|max:100',
            'file_numbers.*'        => 'nullable|string|max:100',
            'coord_x'               => 'nullable|numeric|between:-99999999999999,99999999999999',
            'coord_y'               => 'nullable|numeric|between:-99999999999999,99999999999999',
            'company_choice'        => 'nullable|string|max:30',
            'company_other'         => 'nullable|string|max:255',
            'surveyor_choice'       => 'nullable|string|max:30',
            'surveyor_name_other'   => 'nullable|string|max:255',
            'surveyor_registration' => 'nullable|string|max:50',
            'teller_no'             => 'nullable|string|max:100',
            'amount'                => 'nullable|numeric|min:0|max:9999999999999',
            'date_received'         => 'nullable|date|after_or_equal:issue_date',
        ] + AddressBuilder::rules('prop_', false), [
            'layout_type.required'           => 'Choose the type: Public or Private.',
            'layout_type.in'                 => 'Choose the type: Public or Private.',
            'issue_date.required'            => 'The issue date is required.',
            'beacon_from.required'           => 'Enter the first beacon number of the range.',
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

        $data = array_merge(\Illuminate\Support\Arr::except($data, ['surveyor_choice', 'surveyor_name_other', 'company_choice', 'company_other']), $this->resolveSurveyor($r), $this->resolveCompany($r));

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
        ], $range, $files];
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
            $list = $taken->take(5)->map(fn ($n) => SurveyBeaconRegister::display($n->beacon_no))->implode(', ') . ($taken->count() > 5 ? ', …' : '');
            throw ValidationException::withMessages(['beacon_from' => "Already issued: $list (to " . ($first->surveyor_name ?: 'no surveyor named') . " on "
                . $first->issue_date->format('d/m/Y') . ", entry {$first->issued_range})."]);
        }

        return $range;
    }

    /** The By number view's list, shared with Export Records. */
    private function numbersQuery($q, Request $r)
    {
        $nq = SurveyBeaconNumber::query()
            ->whereIn('survey_beacon_register_id', (clone $q)->select('id'))
            ->with('register.files');
        $term = SurveyBeaconRegister::normalise($r->query('q'));
        if ($term && SurveyBeaconRegister::parseBeacon($term)) {
            $nq->where('beacon_no', 'like', "%$term%");
        }

        return $nq;
    }

    /** Export Records: the By number view gives one row per number; the other views give the issue lines. */
    public function export(Request $r)
    {
        $this->authorizeAction('view');

        $issue = fn ($x) => [
            'type' => $x?->layout_type_label, 'file_number' => $x ? implode(', ', $x->fileNumberList()) : null, 'register_no' => $x?->register_no, 'plan_no' => $x?->plan_no,
            'company' => $x->company_name, 'surveyor' => $x?->surveyor_name, 'registration' => $x?->surveyor_registration, 'plot_no' => $x?->prop_plot,
            'district' => $x?->resolvedDistrict('prop_'), 'lga' => $x ? mb_strtoupper((string) \App\Support\AddressBuilder::resolve($x->prop_lga)) : null,
            'location' => $x?->resolvedStreet('prop_'), 'x' => $x?->coord_x, 'y' => $x?->coord_y, 'teller' => $x?->teller_no,
        ];
        $columns = [
            'type' => 'Type', 'file_number' => 'File No.', 'register_no' => 'Register No.', 'plan_no' => 'Plan No.', 'company' => 'Company', 'surveyor' => "Surveyor's Name",
            'registration' => 'Registration', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location', 'x' => 'X', 'y' => 'Y', 'teller' => 'Teller No.',
        ];

        if ($r->query('view') === 'numbers') {
            return $this->exportRecords($r, fn () => $this->numbersQuery($this->filtered($r), $r)->orderBy('beacon_series')->orderBy('beacon_seq')->orderBy('id'),
                ['beacon_no' => 'Beacon No.', 'date' => 'Date'] + $columns,
                fn ($n) => ['beacon_no' => SurveyBeaconRegister::display($n->beacon_no), 'date' => $this->exportDate($n->register?->issue_date, 'd/m/Y')] + $issue($n->register), null);
        }

        return $this->exportRecords($r, fn () => $this->filtered($r)->with(['creator:id,first_name,last_name', 'files:id,survey_beacon_register_id,file_number,sort_order'])
            ->orderByDesc('issue_date')->orderByDesc('id'),
            ['date' => 'Date'] + $columns + ['issued' => 'Issued Number', 'total' => 'Total', 'amount' => 'Amount (₦)', 'received' => 'Date Received', 'created_by' => 'Created By', 'status' => 'Status'],
            fn ($x) => ['date' => $this->exportDate($x->issue_date, 'd/m/Y')] + $issue($x) + [
                'issued' => $x->issued_range, 'total' => $x->total_issued, 'amount' => $x->amount, 'received' => $this->exportDate($x->date_received, 'd/m/Y'),
                'created_by' => $this->exportUser($x->creator), 'status' => $x->audited_at ? 'Audited' : ($x->reviewed_at ? 'Reviewed' : 'Entered'),
            ], 'issue_date');
    }
}
