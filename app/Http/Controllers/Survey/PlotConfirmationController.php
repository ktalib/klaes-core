<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyOpShowingPhoto as ShowingPhoto;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlotCheck as Check;
use App\Models\Survey\SurveyPlotCheckPhoto as CheckPhoto;
use App\Models\Survey\SurveyTpNumber;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\OpLookup;
use App\Services\Survey\OpTpGroups;
use App\Services\Pra\PraRecordService;
use App\Services\OpSerialGenerator;
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
 * first, a reason from the 2nd showing on). Ground checks too, on that form's Ground Check tab
 * (user, 2026-11-06). Search, filters and S/N in display order.
 *
 * Two entries (CR 03112026 item 1): GKN > Plot Showing Register has Resettlement + Direct Allocation and
 * every action; Compensation > Plot Showing Register - Resettlement is the same list, Resettlement only,
 * view only. Old register lines are not a tab of their own: an attribute of the entry, shown in one list.
 */
class PlotConfirmationController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    private const GKN = 'survey-module.gkn.plot-showing';
    private const COMPENSATION = 'survey-module.compensation.plot-confirmations';

    /** GKN > Plot Showing Register: both OP types, with the actions. */
    public function gkn(Request $r)
    {
        return $this->index($r, true);
    }

    /** Compensation > Plot Showing Register - Resettlement: Resettlement only, view only, unless called as gkn(). */
    public function index(Request $r, bool $gkn = false)
    {
        // Old links: Confirmation Forms lives on the GKN entry; the Old Register tab is now a filter.
        if (! $gkn && $r->query('tab') === 'confirmation') {
            session()->reflash();

            return redirect()->route(self::GKN, $r->query());
        }
        [$tab, $source, $resettlementOnly, $resettlementChecks, $showings, $checks] = $this->registerQueries($r, $gkn);

        $opShowings = fn () => $resettlementOnly(Showing::query())->whereNotNull('op_source')->where('op_source', '!=', Showing::SOURCE_OLD);

        return view('survey_module.compensation.plot_confirmations', [
            'tab'      => $tab,
            'isGkn'    => $gkn,
            'register' => $gkn ? self::GKN : self::COMPENSATION,
            'source'   => $source,
            'surveyors' => $this->surveyors(),
            // TP No. on Record Plot Showing (user, 2026-11-03): the Public Layout Plans register.
            // Only the numbers, each once (user, 2026-11-03): a number drawn twice in the register is its first entry,
            // the one an Other with that number is matched to.
            'tpNumbers' => SurveyTpNumber::listed()->whereNotNull('tp_no')->where('tp_no', '<>', '')->serialOrder()->get(['id', 'tp_no'])
                ->groupBy(fn ($t) => mb_strtoupper(str_replace(' ', '', $t->tp_no)))->map(fn ($g) => $g->sortBy('id')->first())->values(),
            // Record Plot Showing (New) starts from the layout (CR 03112026 item 3).
            'tpGroups' => $gkn ? app(OpTpGroups::class)->options() : collect(),
            'totals'   => [
                'showings'      => $opShowings()->count(),
                'ops'           => $opShowings()->distinct()->count('op_source_id'),
                'old'           => $resettlementOnly(Showing::query())->where('op_source', Showing::SOURCE_OLD)->count(),
                'checks'        => $resettlementChecks(Check::query())->count(),
                'discrepancies' => $resettlementChecks(Check::query())->where('result', Check::DISCREPANCY)->count(),
            ],
            'rows'     => match ($tab) {
                'checks' => $checks->with('photos', 'showing:id,showing_no,shown_on')->orderByDesc('checked_on')->orderByDesc('id')->paginate(20)->withQueryString(),
                default  => $showings->with('photos', 'groundChecks:id,survey_op_showing_id,check_ref,result')->orderByDesc('shown_on')->orderByDesc('id')->paginate(20)->withQueryString(),
            },
            'editOld'  => $gkn && $r->query('edit') ? Showing::where('op_source', Showing::SOURCE_OLD)->find((int) $r->query('edit')) : null,
        ] + ($gkn ? [
            'opCaptureLandUses' => \App\Models\LandUse::orderBy('landuse')->pluck('landuse'),
            'opCaptureDistricts' => LookupController::options('districts'),
            'opCaptureLgas' => LookupController::options('lgas'),
        ] + app(ConfirmationFormController::class)->panelData($r) : []));
    }

    /** OPs for the Record Plot Showing form: Deeds OPs by OP serial number only (user, 2026-11-03), with the next showing. */
    public function opSearch(Request $r, OpLookup $ops)
    {
        return response()->json($this->opRows($ops->searchSerial((string) $r->query('q', ''), 20)));
    }

    /** The plots of a layout for Record Plot Showing (CR 03112026 item 3): pick the TP No., then the plot. */
    public function tpPlots(Request $r, OpLookup $ops)
    {
        $group = (int) $r->query('group');
        if ($group < 1) return response()->json([]);

        return response()->json($this->opRows($ops->inGroup($group, (string) $r->query('plot', ''))));
    }

    /** Search active file-indexing records for the OP capture modal. */
    public function indexedFileSearch(Request $r)
    {
        $search = trim((string) $r->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['files' => [], 'has_more' => false]);
        }

        $page = max(1, (int) $r->query('page', 1));
        $limit = min(50, max(1, (int) $r->query('limit', 20)));
        $columns = ['file_number', 'temp_file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'];
        $union = null;

        foreach ($columns as $column) {
            $query = DB::connection('sqlsrv')->table('file_indexings')
                ->selectRaw("LTRIM(RTRIM([$column])) AS file_number")
                ->whereNotNull($column)
                ->whereRaw("LTRIM(RTRIM([$column])) <> ''")
                ->whereRaw("LTRIM(RTRIM([$column])) LIKE ?", ['%' . $search . '%'])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));

            if ($union === null) {
                $union = $query;
            } else {
                $union->union($query);
            }
        }

        $matches = DB::connection('sqlsrv')->query()
            ->fromSub($union, 'indexed_files')
            ->select('file_number')
            ->distinct()
            ->orderBy('file_number')
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get();

        return response()->json([
            'files' => $matches->take($limit)->map(fn ($row) => ['file_number' => $row->file_number])->values(),
            'has_more' => $matches->count() > $limit,
        ]);
    }

    /** Capture a missing OP from the Plot Showing form and return it for immediate selection. */
    public function storeShowingOp(Request $r, OpLookup $ops, PraRecordService $pra)
    {
        $data = $r->validate([
            'file_number' => 'nullable|string|max:255',
            'op_serial_number' => ['required', 'string', 'max:100'],
            'op_type' => ['nullable', 'string', Rule::in(['Resettlement', 'Direct Allocation', 'Other'])],
            'op_type_other' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date',
            'land_use' => ['nullable', 'string', Rule::in(\App\Models\LandUse::orderBy('landuse')->pluck('landuse')->all())],
            'grantor' => ['required', 'string', Rule::in(['KANO STATE GOVERNMENT'])],
            'grantee' => 'required|string|max:255',
            'serialNo' => 'nullable|string|max:100',
            'volumeNo' => 'nullable|string|max:100',
            'deeds_date' => 'nullable|date',
            'deeds_time' => 'nullable|string|max:20',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:255',
            'district' => ['nullable', 'string', Rule::in(LookupController::options('districts'))],
            'lga' => ['nullable', 'string', Rule::in(LookupController::options('lgas'))],
            'plot_size' => 'nullable|string|max:100',
        ], [
            'grantor.required' => 'Enter the grantor or issuing authority.',
            'grantee.required' => 'Enter the allottee name.',
        ]);

        $typeErrors = \App\Support\OpType::errors(
            $data['op_type'] ?? null,
            $data['op_type_other'] ?? null,
            'op_type_other'
        );
        if ($typeErrors) {
            throw ValidationException::withMessages($typeErrors);
        }

        $serialNumber = trim((string) $data['op_serial_number']);
        if (! \App\Support\OpSerial::manualValid($serialNumber)) {
            throw ValidationException::withMessages([
                'op_serial_number' => 'Enter a positive OP serial number without leading zeros.',
            ]);
        }
        if ($ops->exactSerial($serialNumber, 1)) {
            throw ValidationException::withMessages([
                'op_serial_number' => 'An Occupancy Permit with this serial number is already captured.',
            ]);
        }

        $saved = DB::connection('sqlsrv')->transaction(function () use ($data, $pra, $serialNumber) {
            $identifiers = $this->showingOpFileIdentifiers(trim((string) ($data['file_number'] ?? '')));
            if ($identifiers === null) {
                $sequenceId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                    'created_by' => auth()->id(),
                    'is_used' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $identifiers = ['temp_fileno' => 'TEMP-' . str_pad((string) $sequenceId, 5, '0', STR_PAD_LEFT)];
            }

            $indexed = $this->showingOpFileDetails($data['file_number'] ?? null, $identifiers);
            $plotNo = trim((string) ($data['plot_no'] ?? '')) ?: trim((string) ($indexed->plot_number ?? ''));
            if ($plotNo === '') {
                throw ValidationException::withMessages([
                    'plot_no' => 'Enter the plot number for the Occupancy Permit.',
                ]);
            }

            $serial = app(OpSerialGenerator::class)->resolve(
                false,
                $serialNumber,
                null,
                'op_serial_number'
            );
            $grantor = 'KANO STATE GOVERNMENT';
            $grantee = trim($data['grantee']);
            $district = trim((string) ($data['district'] ?? ''));
            $lga = trim((string) ($data['lga'] ?? ''));
            $locationParts = array_filter([$district, $lga]);
            $location = $locationParts ? implode(', ', array_merge($locationParts, ['Kano State'])) : null;
            $tpNo = trim((string) ($data['tp_no'] ?? '')) ?: trim((string) ($indexed->tp_no ?? ''));
            $registrationSerial = trim((string) ($data['serialNo'] ?? ''));
            $regNo = implode('/', [
                $registrationSerial ?: '0',
                $registrationSerial ?: '0',
                trim((string) ($data['volumeNo'] ?? '')) ?: '0',
            ]);

            return $pra->createRecord(array_merge($identifiers, $serial, [
                'transaction_type' => 'Occupancy Permit (OP)',
                'instrument_type' => 'Occupancy Permit (OP)',
                'source' => 'Plot Showing Register',
                'op_source' => 'Ministry',
                'op_source_other' => null,
                'op_type' => $data['op_type'] ?? null,
                'op_type_other' => \App\Support\OpType::otherFor(
                    $data['op_type'] ?? null,
                    $data['op_type_other'] ?? null
                ),
                'transaction_date' => $data['transaction_date'] ?? null,
                'land_use' => trim((string) ($data['land_use'] ?? '')) ?: $this->showingOpLandUse($indexed->land_use_type ?? null),
                'Grantor' => $grantor,
                'Grantee' => $grantee,
                'regNo' => $regNo,
                'serialNo' => $registrationSerial ?: null,
                'pageNo' => $registrationSerial ?: null,
                'volumeNo' => $data['volumeNo'] ?? null,
                'deeds_date' => $data['deeds_date'] ?? null,
                'deeds_time' => $data['deeds_time'] ?? null,
                'plot_no' => $plotNo,
                'tp_no' => $tpNo ?: null,
                'districtName' => $district ?: null,
                'lgsaOrCity' => $lga ?: null,
                'location' => $location ?: null,
                'property_description' => $location ?: null,
                'plot_size' => trim((string) ($data['plot_size'] ?? '')) ?: ($indexed->plot_size ?? null),
                'status' => 'Active',
            ]), auth()->id());
        });

        $id = (int) ($saved['id'] ?? 0);
        $op = $id > 0 ? $ops->find('pra-' . $id) : null;
        if (! $op) {
            throw new \RuntimeException('The Occupancy Permit was saved but could not be loaded for Plot Showing.');
        }

        return response()->json([
            'success' => true,
            'op' => $this->opRows([$op])[0],
        ]);
    }

    /**
     * Resolve a selected file number to the identifier columns PRA uses. An empty
     * selection deliberately returns null so the caller allocates a TEMP number.
     */
    private function showingOpFileIdentifiers(string $fileNumber): ?array
    {
        if ($fileNumber === '') {
            return null;
        }

        $file = DB::connection('sqlsrv')->table('fileNumber')
            ->where(function ($q) use ($fileNumber) {
                $q->where('mlsfNo', $fileNumber)
                    ->orWhere('kangisFileNo', $fileNumber)
                    ->orWhere('NewKANGISFileNo', $fileNumber);
            })
            ->first(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo']);

        if ($file) {
            return [
                'mlsFNo' => $file->mlsfNo,
                'kangisFileNo' => $file->kangisFileNo,
                'NewKANGISFileno' => $file->NewKANGISFileNo,
            ];
        }

        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('fileno', $fileNumber)
            ->orWhere('np_fileno', $fileNumber)
            ->first(['fileno', 'np_fileno']);

        if ($application) {
            return [
                'fileno' => $application->fileno,
                'np_fileno' => $application->np_fileno,
            ];
        }

        $indexed = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($q) use ($fileNumber) {
                $q->where('file_number', $fileNumber)
                    ->orWhere('temp_file_no', $fileNumber)
                    ->orWhere('mls_file_no', $fileNumber)
                    ->orWhere('kangis_file_no', $fileNumber)
                    ->orWhere('new_kangis_file_no', $fileNumber);
            })
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['file_number', 'temp_file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no']);

        if ($indexed) {
            return [
                'mlsFNo' => $indexed->mls_file_no ?: ($indexed->temp_file_no ? null : $indexed->file_number),
                'kangisFileNo' => $indexed->kangis_file_no,
                'NewKANGISFileno' => $indexed->new_kangis_file_no,
                'temp_fileno' => $indexed->temp_file_no,
            ];
        }

        throw ValidationException::withMessages([
            'file_number' => 'Select a file number from the search results, or clear the selection to generate a temporary file number.',
        ]);
    }

    /** Property details for a selected file number, used only to fill gaps in the modal. */
    private function showingOpFileDetails(?string $fileNumber, array $identifiers): ?object
    {
        $numbers = array_values(array_filter(array_unique(array_merge(
            [$fileNumber],
            array_values($identifiers)
        ))));
        if (! $numbers) {
            return null;
        }

        return DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($q) use ($numbers) {
                foreach ($numbers as $number) {
                    $q->orWhere('file_number', $number)
                        ->orWhere('temp_file_no', $number)
                        ->orWhere('mls_file_no', $number)
                        ->orWhere('kangis_file_no', $number)
                        ->orWhere('new_kangis_file_no', $number);
                }
            })
            ->orderByDesc('id')
            ->first(['plot_number', 'tp_no', 'district', 'lga', 'location', 'property_description', 'land_use_type', 'plot_size']);
    }

    private function showingOpLandUse(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = strtoupper(app(\App\Services\Prs\Support\LandUseNormalizer::class)->normalize($value));
        $options = \App\Models\LandUse::orderBy('landuse')->pluck('landuse')
            ->map(fn ($landUse) => strtoupper(trim((string) $landUse)));

        return $options->first(fn ($landUse) => $landUse === $normalized);
    }

    /** OPs as the form uses them, with the next showing and the register row for the layout. */
    private function opRows(array $results): array
    {
        $ids = array_column($results, 'id');
        $counts = $ids ? Showing::whereNotNull('op_source')->whereIn('op_source_id', $ids)->selectRaw('op_source, op_source_id, MAX(showing_no) n')->groupBy('op_source', 'op_source_id')
            ->get()->mapWithKeys(fn ($x) => [$x->op_source . '-' . $x->op_source_id => (int) $x->n]) : collect();
        // The showings a ground check can answer, latest first (Against Showing on the Ground Check tab).
        $shown = $ids ? Showing::whereNotNull('op_source')->whereIn('op_source_id', $ids)->orderByDesc('showing_no')->get(['id', 'op_source', 'op_source_id', 'showing_no', 'shown_on'])
            ->groupBy(fn ($x) => $x->op_source . '-' . $x->op_source_id) : collect();
        $groups = app(OpTpGroups::class);

        return array_map(function ($op) use ($counts, $groups, $shown) {
            $next = ($counts[$op['key']] ?? 0) + 1;

            return array_intersect_key($op, array_flip(['key', 'serial', 'type', 'allottee', 'address', 'plot_no', 'district', 'lga', 'location', 'layout', 'file_number']))
                + ['next' => $next, 'next_label' => Showing::ordinal($next) . ' showing', 'tp_number_id' => $groups->forRaw($op['tp_no'])->survey_tp_number_id ?? null,
                   'showings' => collect($shown[$op['key']] ?? [])->map(fn ($s) => ['id' => $s->id, 'label' => trim($s->label . ' · ' . $s->shown_on?->format('d M Y'), ' ·')])->values()->all()];
        }, $results);
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

        return redirect()->route(self::GKN, ['source' => 'old'])->with('success', 'Old register entry saved.');
    }

    public function updateOld(Request $r, Showing $showing)
    {
        abort_unless($showing->op_source === Showing::SOURCE_OLD, 404);
        $showing->update($this->oldValues($r) + ['updated_by' => auth()->id()]);

        return redirect()->route(self::GKN, ['source' => 'old'])->with('success', 'Old register entry updated.');
    }

    public function destroyOld(Showing $showing)
    {
        abort_unless($showing->op_source === Showing::SOURCE_OLD, 404);
        $showing->update(['updated_by' => auth()->id()]);
        $showing->delete();

        return redirect()->route(self::GKN, ['source' => 'old'])->with('success', 'Old register entry removed.');
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
            'op_type'       => ['nullable', Rule::in([Showing::RESETTLEMENT, Showing::DIRECT])],
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
            'op_type'       => $d['op_type'] ?? null,
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

        [$staffId, $svName] = $this->pickedSurveyor($data);
        $notes = trim(preg_replace('/\s+/', ' ', (string) ($data['notes'] ?? '')));
        // Who asked for the showing (user, 2026-11-02).
        $requester = \App\Services\Survey\ShowingRequester::resolve($r, $op);
        $tp = $this->tpValue($data['tp'] ?? null, $data['tp_other'] ?? null);
        // Nobody picked one: the layout written on the OP, so the showing still groups by TP No.
        if (! $tp['tp_no']) $tp = app(OpTpGroups::class)->showingValues($op['tp_no']);

        $stored = [];
        try {
            $showing = DB::connection('sqlsrv')->transaction(function () use ($op, $data, $staffId, $svName, $notes, $requester, $tp, $r, &$stored) {
                // Re-read inside the transaction so two officers saving at once do not share a number.
                $no = (int) Showing::where('op_source', $op['source'])->where('op_source_id', $op['id'])->lockForUpdate()->max('showing_no') + 1;
                $showing = Showing::create([
                    'op_source'         => $op['source'],
                    'op_source_id'      => $op['id'],
                    'op_serial'         => $op['serial'],
                    'op_type'           => Showing::typeOf($op['type']),
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

        return redirect()->route(self::GKN)
            ->with('success', "{$showing->label} of OP {$showing->op_serial} recorded.");
    }

    /**
     * Ground Check from the office (user, 2026-11-06): the Ground Check tab on Record Plot Showing, the same
     * record the Plot Confirmation app makes for an OP's plot and under the same rules: it answers one of the
     * OP's showings (the latest unless picked), and the beacons are read against the Beacon Register.
     * A layout plot with no OP stays on the app.
     */
    public function storeCheck(Request $r, OpLookup $ops)
    {
        $op = $ops->find((string) $r->input('op'));
        if (! $op) {
            throw ValidationException::withMessages(['op' => 'Find and pick the OP whose plot was checked.']);
        }

        $data = $r->validate([
            'showing'           => 'nullable|integer',
            'surveyor'          => 'required|string|max:50',
            'surveyor_other'    => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === 'other')],
            'checked_on'        => 'required|date|before_or_equal:today',
            'checked_at'        => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'beacons_found'     => 'nullable|string|max:1000',
            'result'            => ['required', Rule::in(Check::RESULTS)],
            'discrepancy'       => ['nullable', 'required_if:result,' . Check::DISCREPANCY, Rule::in(Check::DISCREPANCIES)],
            'discrepancy_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('result') === Check::DISCREPANCY && $r->input('discrepancy') === Check::OTHER)],
            'notes'             => 'nullable|string|max:2000',
            'photos'            => 'nullable|array|max:10',
            'photos.*'          => 'file|mimes:jpg,jpeg,png|max:12288',
        ], [
            'surveyor.required'          => 'Choose the surveyor who checked the plot.',
            'checked_on.before_or_equal' => 'The date cannot be in the future.',
            'surveyor_other.required'    => "Type the surveyor's name.",
            'checked_at.regex'           => 'Give the time as HH:MM.',
            'result.required'            => 'Say whether the plot is confirmed or there is a discrepancy.',
            'discrepancy.required_if'    => 'Say what the discrepancy is.',
            'discrepancy_other.required' => 'Describe the discrepancy.',
            'photos.max'                 => 'At most 10 photographs per ground check.',
            'photos.*.mimes'             => 'Photographs must be JPG or PNG.',
            'photos.*.max'               => 'Each photograph can be at most 12 MB.',
        ]);

        // An OP never shown cannot be ground-checked yet: the showing is the record the check answers.
        $showings = Showing::where('op_source', $op['source'])->where('op_source_id', $op['id']);
        $against = ! empty($data['showing']) ? (clone $showings)->whereKey((int) $data['showing'])->first() : (clone $showings)->orderByDesc('showing_no')->first();
        if (! $against) {
            throw ValidationException::withMessages(['op' => 'Record the OP showing first: the ground check is the response to a showing.']);
        }

        [$staffId, $svName] = $this->pickedSurveyor($data);
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;
        $discrepancy = $data['result'] === Check::DISCREPANCY;

        // Beacons read off the ground, against the Beacon Register.
        $codes = collect(preg_split('/[\s,;]+/', (string) ($data['beacons_found'] ?? '')))->map(fn ($c) => trim($c))->filter()->values()->all();
        $beacons = app(BeaconNumberCheck::class)->forCodes($codes);

        $stored = [];
        try {
            $check = DB::connection('sqlsrv')->transaction(function () use ($op, $data, $against, $staffId, $svName, $clean, $discrepancy, $codes, $beacons, $r, &$stored) {
                $check = Check::create([
                    'check_ref'            => Check::nextRef('check_ref', 'PC', 4),
                    'target_type'          => Check::TARGET_OP,
                    'survey_op_showing_id' => $against->id,
                    'op_source'            => $op['source'],
                    'op_source_id'         => $op['id'],
                    'op_serial'            => $op['serial'],
                    'holder_name'          => $op['allottee'],
                    'layout_no'            => $op['layout'],
                    'plot_no'              => $op['plot_no'],
                    'district'             => $op['district'],
                    'lga'                  => $op['lga'],
                    'location'             => $op['location'] ? mb_substr($op['location'], 0, 500) : null,
                    'checked_on'           => $data['checked_on'],
                    'checked_at'           => $data['checked_at'] ?? null,
                    'surveyors_list_id'    => $staffId,
                    'surveyor_name'        => $svName,
                    'beacons_found'        => $codes ? implode(', ', array_column($beacons, 'beacon')) : null,
                    'beacon_check'         => $beacons ?: null,
                    'result'               => $data['result'],
                    'discrepancy'          => $discrepancy ? $data['discrepancy'] : null,
                    'discrepancy_other'    => $discrepancy && $data['discrepancy'] === Check::OTHER ? $clean($data['discrepancy_other']) : null,
                    'notes'                => $clean($data['notes'] ?? null),
                    'source'               => 'office',
                ]);
                foreach ((array) $r->file('photos', []) as $file) {
                    $path = Storage::disk(CheckPhoto::DISK)->putFileAs("app/survey/plot-checks/{$check->id}", $file, Str::uuid() . '.' . strtolower($file->extension() ?: 'jpg'));
                    $stored[] = $path;
                    CheckPhoto::create([
                        'survey_plot_check_id' => $check->id,
                        'path'                 => $path,
                        'original_name'        => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime'                 => $file->getMimeType(),
                        'size'                 => $file->getSize(),
                    ]);
                }

                return $check;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) Storage::disk(CheckPhoto::DISK)->delete($path);   // nothing left behind
            throw $e;
        }

        return redirect()->route(self::GKN, ['tab' => 'checks'])
            ->with('success', "Ground check {$check->check_ref} of OP {$check->op_serial} recorded.");
    }

    /** A Survey staff member, or a name typed under Other (taken as that staff member if on the list). */
    private function pickedSurveyor(array $data): array
    {
        if ($data['surveyor'] === 'other') {
            $name = trim(preg_replace('/\s+/', ' ', (string) $data['surveyor_other']));
            $match = SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($name));

            return [$match?->id, $match?->full_name ?? $name];
        }
        if ($s = SurveyorListEntry::find((int) $data['surveyor'])) {
            return [$s->id, $s->full_name];
        }

        throw ValidationException::withMessages(['surveyor' => 'Choose the surveyor.']);
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

    /** The tab and the two lists with their filters, shared with Export Records. */
    private function registerQueries(Request $r, bool $gkn): array
    {
        $source = $r->query('tab') === 'old' ? 'old' : (string) $r->query('source', '');

        // Confirmation Forms is a tab here too (user, 2026-11-03); it switches without a page load.
        $tab = $r->query('tab') === 'checks' ? 'checks' : ($gkn && $r->query('tab') === 'confirmation' ? 'confirmation' : 'showings');
        // Compensation sees Resettlement only (CR 03112026 item 1).
        $resettlementOnly = fn ($q) => $gkn ? $q : $q->where('op_type', Showing::RESETTLEMENT);
        $resettlementChecks = fn ($q) => $gkn ? $q : $q->whereIn('survey_op_showing_id', Showing::query()->where('op_type', Showing::RESETTLEMENT)->select('id'));
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
        $from = $r->query('from');
        $to = $r->query('to');

        // One list (CR 03112026): OP showings and lines from the old book (op_source 'old', no OP), told apart by the Source column.
        $showings = $resettlementOnly(Showing::query())->whereNotNull('op_source')->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('op_serial', 'like', $like)->orWhere('allottee_name', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)
                ->orWhere('requester_name', 'like', $like)->orWhere('tp_no', 'like', $like)->orWhere('notes', 'like', $like)))
            ->when($source === 'old', fn ($q) => $q->where('op_source', Showing::SOURCE_OLD))
            ->when($source === 'new', fn ($q) => $q->where('op_source', '!=', Showing::SOURCE_OLD))
            ->when(in_array($r->query('type'), [Showing::RESETTLEMENT, Showing::DIRECT], true) && $gkn,
                fn ($q) => $r->query('type') === Showing::RESETTLEMENT ? $q->where('op_type', Showing::RESETTLEMENT)
                    : $q->where(fn ($w) => $w->whereNull('op_type')->orWhere('op_type', '!=', Showing::RESETTLEMENT)))
            ->when(in_array($r->query('reason'), Showing::REASONS, true), fn ($q) => $q->where('reason', $r->query('reason')))
            ->when($r->query('reason') === 'first', fn ($q) => $q->where('showing_no', 1)->where('op_source', '!=', Showing::SOURCE_OLD))
            ->when($from, fn ($q) => $q->whereDate('shown_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('shown_on', '<=', $to));

        $checks = $resettlementChecks(Check::query())->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('check_ref', 'like', $like)->orWhere('op_serial', 'like', $like)
                ->orWhere('holder_name', 'like', $like)->orWhere('plot_no', 'like', $like)->orWhere('layout_no', 'like', $like)
                ->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)->orWhere('beacons_found', 'like', $like)))
            ->when(in_array($r->query('result'), Check::RESULTS, true), fn ($q) => $q->where('result', $r->query('result')))
            ->when($from, fn ($q) => $q->whereDate('checked_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('checked_on', '<=', $to));

        return [$tab, $source, $resettlementOnly, $resettlementChecks, $showings, $checks];
    }

    public function export(Request $r)
    {
        return $this->exportRegister($r, false);
    }

    public function gknExport(Request $r)
    {
        return $this->exportRegister($r, true);
    }

    private function exportRegister(Request $r, bool $gkn)
    {
        if ($gkn && $r->query('tab') === 'confirmation') {
            return $this->exportRecords($r, fn () => app(ConfirmationFormController::class)->capturedForms((string) $r->query('q'), trim((string) $r->query('cflayout', ''))), [
                'serial_no' => 'Serial No.', 'land_owner' => 'Land Owner', 'layout' => 'Layout', 'section' => 'Section', 'id_number' => 'ID Number', 'location' => 'Location',
                'nin' => 'NIN', 'gsm' => 'GSM', 'vendor' => 'Vendor / Agent', 'guarantor' => 'Guarantor', 'captured' => 'Captured',
            ], fn ($x) => [
                'serial_no' => $x->serial_no, 'land_owner' => $x->land_owner_name, 'layout' => $x->layout_name, 'section' => $x->section, 'id_number' => $x->id_number,
                'location' => $x->location, 'nin' => $x->nin, 'gsm' => $x->gsm, 'vendor' => $x->vendor_name, 'guarantor' => $x->guarantor_name,
                'captured' => $this->exportDate($x->captured_at),
            ], 'captured_at');
        }

        if ($r->query('tab') === 'checks') {
            return $this->exportRecords($r, fn () => $this->registerQueries($r, $gkn)[5]->orderByDesc('checked_on')->orderByDesc('id'), [
                'check_ref' => 'Ref', 'layout' => 'TP No. / Layout', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location', 'op' => 'OP',
                'holder' => 'Holder', 'checked' => 'Checked', 'surveyor' => 'Surveyor', 'beacons' => 'Beacons', 'result' => 'Result', 'discrepancy' => 'Discrepancy',
            ], fn ($x) => [
                'check_ref' => $x->check_ref, 'layout' => $x->layout_no, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga, 'location' => $x->location,
                'op' => $x->op_serial, 'holder' => $x->holder_name, 'checked' => $this->exportDate($x->checked_on), 'surveyor' => $x->surveyor_name,
                'beacons' => $x->beacons_found, 'result' => $x->result, 'discrepancy' => $x->discrepancy_label,
            ], 'checked_on');
        }

        return $this->exportRecords($r, fn () => $this->registerQueries($r, $gkn)[4]->with('groundChecks:id,survey_op_showing_id,check_ref,result')->orderByDesc('shown_on')->orderByDesc('id'), [
            'op' => 'OP', 'allottee' => 'Allottee', 'tp_no' => 'TP No.', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location',
            'source' => 'Source', 'op_type' => 'OP Type', 'showing' => 'Showing', 'reason' => 'Reason', 'shown' => 'Shown', 'surveyor' => 'Surveyor / Pointed Out By',
            'present' => 'Allottee there', 'requester' => 'Requester', 'ground_check' => 'Ground Check',
        ], function ($x) {
            $old = $x->op_source === Showing::SOURCE_OLD;

            return [
                'op' => $x->op_serial, 'allottee' => $x->allottee_name, 'tp_no' => $x->tp_no, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga,
                'location' => $x->location, 'source' => $old ? 'Old Register' : 'New', 'op_type' => $x->op_type ?: ($old ? null : Showing::DIRECT), 'showing' => $x->showing_no,
                'reason' => $x->reason, 'shown' => $this->exportDate($x->shown_on), 'surveyor' => $x->surveyor_name, 'present' => $x->allottee_present ? 'Yes' : 'No',
                'requester' => $x->requester_name, 'ground_check' => $x->groundChecks->map(fn ($c) => trim($c->check_ref . ' ' . $c->result))->implode(', '),
            ];
        }, 'shown_on');
    }
}
