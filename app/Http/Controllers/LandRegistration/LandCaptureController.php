<?php

namespace App\Http\Controllers\LandRegistration;

use App\Models\Gender;
use App\Http\Controllers\Controller;
use App\Models\StreetName;
use App\Services\InstrumentCaptureService;
use App\Services\InstrumentRegistrationService;
use App\Services\TimelineWeightingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Instrument Capture for the Land Registry.
 *
 * A clone of the Deeds capture screen at the CONTROLLER level only: it writes to
 * the same `instrument_capture` / `deed_registrations` tables through the same
 * InstrumentCaptureService, and every query here is scoped by instrument_type to
 * config('land_registration.instrument_type').
 *
 * Two deliberate differences from InstrumentController:
 *
 *  1. NO CONSENT. A deed of purchase is registered straight, with no consent
 *     application behind it. checkDuplicate() below therefore has no consent
 *     section at all and always answers consent_app: null. This is not an
 *     oversight to be "fixed" later - see the note on that method.
 *
 *  2. NO OP / ST branches. Occupancy Permits, sectional titles and their
 *     temp-file-number machinery cannot reach this module, so the branches that
 *     serve them are left out rather than carried across dead.
 *
 * Capture IS registration here, exactly as on the Deeds side: the service
 * reserves the Serial/Page/Volume from the vault and writes the matching
 * deed_registrations row inside one sqlsrv transaction.
 */
class LandCaptureController extends Controller
{
    public function __construct(
        protected InstrumentCaptureService $captureService
    ) {
    }

    /** The one instrument type this whole module deals in. */
    private function instrumentType(): string
    {
        return (string) config('land_registration.instrument_type');
    }

    /**
     * The Land Registry: historical Deeds of Purchase, as backfilled through PRA.
     *
     * This screen reads `pra`, NOT `instrument_capture`. Sections 4.1 and 19 of
     * the spec split the two deliberately: an old deed being digitised goes in
     * through the Property Records Assistant and is a PRA row, while a NEW deed
     * is registered through this module and lands in deed_registrations. Showing
     * captures here would have mixed the two and made a backfilled record look
     * like a fresh registration.
     *
     * Matched on transaction_type OR instrument_type: PRA writes the chosen
     * instrument into transaction_type, but older rows and some import paths set
     * only instrument_type.
     */
    public function index()
    {
        $type = $this->instrumentType();
        $PageTitle = 'Land Registry';
        $PageDescription = '';

        $hasReceiptNo = Schema::connection('sqlsrv')->hasColumn('pra', 'receipt_no');
        $hasAmount = Schema::connection('sqlsrv')->hasColumn('pra', 'consideration_amount');

        $rows = DB::connection('sqlsrv')->table('pra')
            ->leftJoin('users as u', 'pra.created_by', '=', 'u.id')
            ->where(function ($q) use ($type) {
                $q->where('pra.transaction_type', $type)
                    ->orWhere('pra.instrument_type', $type);
            })
            ->select(
                'pra.*',
                DB::raw($hasAmount ? 'pra.consideration_amount' : "CAST(NULL AS varchar(100)) as consideration_amount"),
                DB::raw($hasReceiptNo ? 'pra.receipt_no' : "CAST(NULL AS varchar(100)) as receipt_no"),
                DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as captured_by_name")
            )
            ->orderBy('pra.created_at', 'desc')
            ->get()
            ->map(function ($row) {
                // Shaped to match the register's rows so both screens can read
                // the same way, even though they come from different tables.
                return (object) [
                    'id' => $row->id,
                    'fileno' => $row->mlsFNo ?: ($row->kangisFileNo ?: ($row->NewKANGISFileno ?: ($row->fileno ?: $row->temp_fileno))),
                    'registration_number' => $this->praParticulars($row),
                    'vendor' => $row->Vendor ?: ($row->Grantor ?: ($row->party_1 ?: '')),
                    'purchaser' => $row->Purchaser ?: ($row->Grantee ?: ($row->party_2 ?: '')),
                    'amount' => $row->consideration_amount,
                    'receipt_no' => $row->receipt_no,
                    'deeds_date' => $row->deeds_date ?: $row->transaction_date,
                    'deeds_time' => $row->deeds_time,
                    // When the deed itself was executed, as distinct from the
                    // day the registry entered it. PRA keeps its own column for
                    // this; deeds_date is the registration.
                    'transaction_date' => $row->transaction_date,
                    'captured_at' => $row->created_at,
                    'plot_number' => $row->plot_no,
                    'size' => $row->plot_size,
                    'district' => $row->location,
                    'lga' => $row->lgsaOrCity,
                    'captured_by_name' => trim($row->captured_by_name ?? ''),
                    'prop_id' => $row->prop_id,
                ];
            });

        $totalCount = $rows->count();

        // Timeline counts for the Timeline column, batched: one pass for the whole
        // register rather than four queries per row.
        $timelineCounts = app(TimelineWeightingService::class)->getWeightedCountsForMany(
            $rows->mapWithKeys(fn ($r) => [$r->id => [
                'fileNumber' => $r->fileno,
                'propId' => (string) ($r->prop_id ?? ''),
            ]])->all()
        );

        $today = now()->toDateString();
        $capturedTodayCount = $rows
            ->filter(fn ($r) => $r->captured_at
                && \Carbon\Carbon::parse($r->captured_at)->toDateString() === $today)
            ->count();

        return view('land_registration.capture.index', [
            'PageTitle' => $PageTitle,
            'PageDescription' => $PageDescription,
            'records' => $rows,
            'totalCount' => $totalCount,
            'capturedTodayCount' => $capturedTodayCount,
            'timelineCounts' => $timelineCounts,
        ]);
    }

    /**
     * Serial/Page/Volume as PRA stores it — three separate columns, any of which
     * may be blank on a historical record. Returns null rather than "//" so the
     * screen can show the row as un-numbered.
     */
    private function praParticulars(object $row): ?string
    {
        $parts = array_map(
            fn ($v) => trim((string) ($v ?? '')),
            [$row->serialNo ?? null, $row->pageNo ?? null, $row->volumeNo ?? null]
        );

        return implode('', $parts) === '' ? null : implode('/', $parts);
    }

    /**
     * The capture card. One card, because this registry has one instrument.
     */
    public function create()
    {
        $PageTitle = 'Land Registration - Capture';
        $PageDescription = 'Capture a Deed of Purchase for registration';

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = $this->getKanoLgasForSelect();
        $districts = $this->getDistrictsForSelect($lgas);
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view('land_registration.capture.create', compact(
            'PageTitle',
            'PageDescription',
            'states',
            'lgas',
            'districts',
            'streetNames'
        ));
    }

    public function store(Request $request)
    {
        $ref = $this->newCaptureRef();
        $type = $this->instrumentType();

        $this->captureLog()->info('Land capture submitted', [
            'ref' => $ref,
            'user_id' => Auth::id(),
            'instrument_type' => $type,
            'fileno' => $request->input('fileno') ?: $request->input('temp_fileno'),
            'payload' => Arr::except($request->all(), ['_token', '_method']),
        ]);

        // Checked BEFORE validation: with an empty $_POST every rule fails and the
        // "field is required" list that comes back describes a form the officer
        // did fill in. See detectDroppedRequestBody().
        [$bodyDropped, $bodyDropReason] = $this->detectDroppedRequestBody($request);
        if ($bodyDropped) {
            $this->captureLog()->error('Land capture aborted: request body dropped by PHP', [
                'ref' => $ref,
                'user_id' => Auth::id(),
                'content_length' => $request->server('CONTENT_LENGTH'),
                'max_multipart_body_parts' => ini_get('max_multipart_body_parts'),
                'max_input_vars' => ini_get('max_input_vars'),
            ]);

            $message = $bodyDropReason . ' Nothing was saved. Reference: ' . $ref;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'ref' => $ref,
                    'error_type' => 'request_body_dropped',
                ], 422);
            }

            return redirect()->back()->withErrors(['instrument_type' => $message]);
        }

        try {
            $rules = [
                'fileno' => 'nullable|string|max:255',
                'temp_fileno' => 'nullable|string|max:255',
                // Party 2's gender must be chosen, not defaulted: a blank is
                // indistinguishable from the legacy rows that predate the column,
                // and those are exactly what the gender reports cannot count.
                'secondPartyGender' => ['required', Rule::in(Gender::options())],
            ];

            // The solicitor block is optional, but half-filling it is not: a
            // partial address on a registered deed is worse than none.
            $includeSolicitor = filter_var($request->input('include_solicitor', false), FILTER_VALIDATE_BOOLEAN);
            $hasAnySolicitorField = $request->filled('solicitorName')
                || $request->filled('solicitorAddress')
                || $request->filled('solicitorDistrict')
                || $request->filled('solicitorState')
                || $request->filled('solicitorLga');

            if ($includeSolicitor || $hasAnySolicitorField) {
                $rules['solicitorName'] = 'required|string|max:255';
                $rules['solicitorAddress'] = 'required|string|max:1000';
                $rules['solicitorState'] = 'required|string|max:255';
                $rules['solicitorLga'] = 'required|string|max:255';
            }

            $messages = [
                'secondPartyGender.required' => 'Purchaser gender is required - please select one.',
                'secondPartyGender.in' => 'Purchaser gender must be one of: ' . implode(', ', Gender::options()) . '.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $this->captureLog()->warning('Land capture rejected by validation', [
                    'ref' => $ref,
                    'errors' => $validator->errors()->toArray(),
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Duplicate check on the five identifying parameters. The party and
            // date keys must be read from the names the form actually posts -
            // read the wrong ones and the check collapses to file number plus
            // type, which every later dealing on a file would then trip.
            $dupParams = [
                'fileno' => $request->input('temp_fileno') ?? $request->input('fileno'),
                'prop_id' => $request->input('prop_id'),
                'instrument_type' => $type,
                'op_serial' => null,
                'reg_no' => $request->input('reg_no'),
                'party_1' => $request->input('party_1_name')
                    ?: $request->input('Grantor')
                    ?: $request->input('firstPartyName'),
                'party_2' => $request->input('party_2_name')
                    ?: $request->input('Grantee')
                    ?: $request->input('secondPartyName'),
                'instrument_date' => $request->input('instrument_date')
                    ?: $request->input('entryDate')
                    ?: $request->input('instrumentDate'),
            ];

            // An officer who chose "Create New" on the duplicate warning has
            // looked at the existing record and decided this is a further
            // dealing. Their decision stands, but it is recorded with what it
            // collided with.
            $allowDuplicate = filter_var($request->input('allow_duplicate', false), FILTER_VALIDATE_BOOLEAN);

            if ($duplicateFound = check_duplicate('instrument', $dupParams)) {
                if ($allowDuplicate) {
                    $this->captureLog()->notice('Land capture duplicate overridden by officer', [
                        'ref' => $ref,
                        'params' => $dupParams,
                        'existing_id' => $duplicateFound->id ?? null,
                        'existing_reg_no' => $duplicateFound->registration_number ?? null,
                        'user_id' => Auth::id(),
                    ]);
                } else {
                    $errorMsg = 'A similar instrument already exists for this property.';

                    $this->captureLog()->warning('Land capture blocked as duplicate', [
                        'ref' => $ref,
                        'params' => $dupParams,
                        'existing_id' => $duplicateFound->id ?? null,
                    ]);

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => $errorMsg,
                            'duplicate' => $duplicateFound,
                        ], 422);
                    }

                    return redirect()->back()->with('error', $errorMsg)->withInput();
                }
            }

            $payload = $request->all();

            // The type is set here, not taken from the form. A land capture is a
            // Deed of Purchase by definition, and letting the client name the
            // type would let a Deeds instrument in through this door and burn a
            // number out of the wrong vault.
            $payload['instrument_type'] = $type;
            unset($payload['consent_application_id']);

            $tempFileno = trim((string) ($payload['temp_fileno'] ?? ''));
            $genericFileno = trim((string) ($payload['fileno'] ?? ''));
            if ($tempFileno === '' && $genericFileno !== '' && preg_match('/^(TEMP-|.*\(T\)$)/i', $genericFileno)) {
                $payload['temp_fileno'] = $genericFileno;
            }

            $result = $this->captureService->capture($payload);

            if ($result['success']) {
                $message = 'Deed of Purchase registered successfully. Ref: ' . ($result['reg_number'] ?? '');

                $this->captureLog()->info('Land capture succeeded', [
                    'ref' => $ref,
                    'instrument_capture_id' => $result['id'] ?? null,
                    'deed_registration_id' => $result['deed_registration_id'] ?? null,
                    'registration_number' => $result['reg_number'] ?? null,
                ]);

                if ($request->expectsJson()) {
                    return response()->json(array_merge(['success' => true, 'message' => $message], $result));
                }

                return redirect()->route('land-registration.capture.index')->with('success', $message);
            }

            $this->captureLog()->error('Land capture returned failure without an exception', ['ref' => $ref]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to register instrument.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to register instrument')->withInput();

        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a TypeError raised deeper in the
            // capture chain is an \Error, and an \Exception-only catch lets it
            // escape as an HTML 500 the form can only report as "invalid
            // response format from the server" - no message, no clue.
            $this->captureLog()->error('Land capture failed: ' . $e->getMessage(), [
                'ref' => $ref,
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'user_id' => Auth::id(),
                'trace' => $this->shortTrace($e),
            ]);

            $message = 'An error occurred: ' . $e->getMessage() . ' [Ref: ' . $ref . ']';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message, 'ref' => $ref], 500);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }
    }

    /**
     * Duplicate check + prior-record lookup for the capture form.
     *
     * THERE IS NO CONSENT SECTION HERE, ON PURPOSE. A Deed of Purchase is a
     * straight capture: no consent application stands behind it, so the response
     * always carries consent_app: null and the capture form's consent picker,
     * banner and auto-fill never fire.
     *
     * Note for anyone tempted to copy the Deeds version across wholesale:
     * InstrumentController::checkDuplicate() ends with a fallback that auto-fills
     * from ANY consent on the file whenever the instrument type is absent from
     * its consent map. This type never is in that map, so copying that block
     * would quietly pull an unrelated consent's parties into a purchase - the
     * exact opposite of what this registry asked for.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $fileno = $request->query('fileno');
        $propId = $request->query('prop_id');
        $type = $this->instrumentType();

        if (!$fileno && !$propId) {
            return response()->json(['exists' => false]);
        }

        $instrument = check_duplicate('instrument', [
            'fileno' => $fileno,
            'prop_id' => $propId,
            'instrument_type' => $type,
            'instrument_date' => $request->query('date'),
            'party_1' => $request->query('party_1'),
            'party_2' => $request->query('party_2'),
            'op_serial' => null,
            'reg_no' => $request->query('reg_no'),
        ]);

        $normalizedFileno = strtoupper(trim((string) $fileno));
        $cleanFileno = preg_replace('/[^A-Z0-9]/', '', $normalizedFileno);

        // Matching is fuzzy on purpose: the same file is written "KNML 4155",
        // "KNML-4155" and "KNML/4155" across the legacy tables.
        $fileMatch = function ($q, array $columns) use ($fileno, $normalizedFileno, $cleanFileno) {
            foreach ($columns as $column) {
                $q->orWhere($column, $fileno)
                    ->orWhere($column, $normalizedFileno);

                if ($cleanFileno) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '/', '') = ?",
                        [$cleanFileno]
                    );
                }
            }
        };

        // PRA history, for the context panel on the duplicate warning.
        $praHistory = DB::connection('sqlsrv')->table('pra')
            ->where(function ($q) use ($fileMatch, $instrument) {
                $q->whereRaw('1 = 0');
                $fileMatch($q, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno']);

                if ($instrument && !empty($instrument->prop_id)) {
                    $q->orWhere('prop_id', $instrument->prop_id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Registrations already on this file, from EITHER registry - an officer
        // needs to see a prior Deeds dealing before adding a purchase to it.
        $deedHistory = DB::connection('sqlsrv')
            ->table('deed_registrations as dr')
            ->leftJoin('instrument_capture as ic', 'dr.instrument_capture_id', '=', 'ic.id')
            ->select(['dr.*', 'ic.party_1_address', 'ic.party_2_address'])
            ->where('dr.fileno', $fileno)
            ->orderBy('dr.created_at', 'desc')
            ->get()
            ->map(function ($item) {
                $item->is_deed_reg = true;
                $item->transaction_type = $item->instrument_type ?? 'Instrument';
                $item->party_1 = $item->grantor ?? '';
                $item->party_2 = $item->grantee ?? '';
                $item->serialNo = $item->serial_no ?? '0';
                $item->pageNo = $item->page_no ?? '0';
                $item->volumeNo = $item->volume_no ?? '0';

                return $item;
            });

        $combinedHistory = $praHistory->concat($deedHistory)->sortByDesc('created_at')->values();

        // The most recent capture on this file, used to pre-fill property
        // details on a further dealing. Never the exact duplicate itself.
        $priorQuery = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) use ($fileMatch) {
                $q->whereRaw('1 = 0');
                $fileMatch($q, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']);
            })
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        if ($instrument) {
            $priorQuery->where('id', '!=', $instrument->id);
        }

        $priorInstrument = $priorQuery->orderBy('created_at', 'desc')->first() ?: $instrument;

        return response()->json([
            'exists' => (bool) $instrument,
            'instrument' => $instrument,
            'pra_history' => $combinedHistory,
            'pra' => $praHistory->first(),
            'prior_instrument' => $priorInstrument,
            // Explicit, not omitted: the capture form reads these keys, and a
            // missing key and a null one are not the same thing to it.
            'consent_app' => null,
            'consent_apps' => [],
            'consent_lock' => null,
        ]);
    }

    /**
     * The number this capture would take, read WITHOUT consuming it, so the
     * officer sees 1/1/22 on the card before committing to it.
     */
    public function nextRegistrationParticulars(): JsonResponse
    {
        try {
            $preview = app(InstrumentRegistrationService::class)
                ->peekRegistrationNumber($this->instrumentType());

            return response()->json(array_merge(['success' => true], $preview));
        } catch (\Throwable $e) {
            // Never block the capture screen over a preview.
            Log::warning('Could not preview land registration particulars', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    public function show($id)
    {
        return $this->recordView($id, 'View Deed of Purchase', 'land_registration.capture.show');
    }

    public function edit($id)
    {
        return $this->recordView($id, 'Edit Deed of Purchase', 'land_registration.capture.edit');
    }

    /**
     * Both the view and edit screens want the same record plus the same
     * reference data; only the title and the blade differ.
     */
    private function recordView($id, string $title, string $view)
    {
        $record = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('id', $id)
            ->where('instrument_type', $this->instrumentType())
            ->first();

        if (!$record) {
            return redirect()->route('land-registration.capture.index')
                ->with('error', 'Record not found in the Land Registry.');
        }

        $PageTitle = $title;
        $PageDescription = '';
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = $this->getKanoLgasForSelect();
        $districts = $this->getDistrictsForSelect($lgas);
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view($view, compact(
            'PageTitle',
            'PageDescription',
            'record',
            'states',
            'lgas',
            'districts',
            'streetNames'
        ));
    }

    public function update(Request $request, $id)
    {
        $ref = $this->newCaptureRef();

        $this->captureLog()->info('Land capture update submitted', [
            'ref' => $ref,
            'instrument_capture_id' => $id,
            'user_id' => Auth::id(),
            'payload' => Arr::except($request->all(), ['_token', '_method']),
        ]);

        // Same guard as store(): an update whose body PHP dropped would
        // otherwise overwrite the record with nothing but nulls.
        [$bodyDropped, $bodyDropReason] = $this->detectDroppedRequestBody($request);
        if ($bodyDropped) {
            $message = $bodyDropReason . ' The record was left unchanged. Reference: ' . $ref;

            $this->captureLog()->error('Land update aborted: request body dropped by PHP', [
                'ref' => $ref,
                'instrument_capture_id' => $id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'ref' => $ref,
                    'error_type' => 'request_body_dropped',
                ], 422);
            }

            return redirect()->back()->withErrors(['instrument_type' => $message]);
        }

        try {
            $payload = $request->all();
            $payload['instrument_type'] = $this->instrumentType();
            unset($payload['consent_application_id']);

            $result = $this->captureService->update($id, $payload);

            if ($result['success']) {
                $message = 'Deed of Purchase updated successfully.';

                $this->captureLog()->info('Land update succeeded', [
                    'ref' => $ref,
                    'instrument_capture_id' => $id,
                ]);

                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => $message]);
                }

                return redirect()->route('land-registration.capture.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update instrument.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update instrument')->withInput();

        } catch (\Throwable $e) {
            $this->captureLog()->error('Land update failed: ' . $e->getMessage(), [
                'ref' => $ref,
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'instrument_capture_id' => $id,
                'trace' => $this->shortTrace($e),
            ]);

            $message = 'An error occurred: ' . $e->getMessage() . ' [Ref: ' . $ref . ']';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message, 'ref' => $ref], 500);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }
    }

    /**
     * Soft delete, matching the Deeds behaviour - a captured instrument is never
     * removed from the table, only flagged.
     */
    public function destroy($id)
    {
        try {
            $affected = DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $id)
                ->where('instrument_type', $this->instrumentType())
                ->update(['is_deleted' => 1]);

            if (!$affected) {
                return redirect()->back()->with('error', 'Record not found in the Land Registry.');
            }

            return redirect()->route('land-registration.capture.index')
                ->with('success', 'Instrument deleted successfully');
        } catch (\Exception $e) {
            Log::error('Land instrument delete error: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete instrument: ' . $e->getMessage());
        }
    }

    /**
     * CSV of everything this registry has captured, in registry order.
     */
    public function exportCapture()
    {
        $rows = DB::connection('sqlsrv')->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
            ->where('ic.instrument_type', $this->instrumentType())
            ->where(function ($q) {
                $q->where('ic.is_deleted', 0)->orWhereNull('ic.is_deleted');
            })
            ->orderBy('dr.volume_no')
            ->orderBy('dr.serial_no')
            ->select(
                'ic.id',
                'ic.mlsFNo',
                'ic.kangisFileNo',
                'ic.NewKANGISFileno',
                'ic.temp_fileno',
                'ic.party_1_name',
                'ic.party_2_name',
                'ic.property_description',
                'ic.lga',
                'ic.district',
                'ic.reg_date',
                'dr.registration_number',
                'dr.serial_no',
                'dr.page_no',
                'dr.volume_no'
            )
            ->get();

        $filename = 'land-registration-captures-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'ID', 'MLS File No', 'KANGIS File No', 'New KANGIS File No', 'Temp File No',
                config('land_registration.parties.first'), config('land_registration.parties.second'),
                'Property', 'LGA', 'District', 'Registered On',
                'Registration No', 'Serial', 'Page', 'Volume',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------
     | Shared plumbing, mirrored from InstrumentController
     ------------------------------------------------------------------ */

    /**
     * Everything one capture does goes to its own channel
     * (storage/logs/instrument_capture-Y-m-d.log), mirrored to the default
     * channel so existing monitoring still sees the errors.
     */
    private function captureLog(): \Psr\Log\LoggerInterface
    {
        return Log::stack(['instrument_capture', config('logging.default')]);
    }

    /**
     * Short, quotable id for one capture attempt: stamped on every log line for
     * the request and shown in the failure message, so an officer can report
     * "CAP-260906-143902-K7QP" and we can find the exact trace.
     */
    private function newCaptureRef(): string
    {
        return 'CAP-' . now()->format('ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
    }

    /** The first frames of a trace, flattened to file:line function. */
    private function shortTrace(\Throwable $e, int $frames = 10): array
    {
        return collect($e->getTrace())
            ->take($frames)
            ->map(fn ($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['function'] ?? ''))
            ->all();
    }

    /**
     * PHP can refuse to parse a multipart body outright, before any of this
     * controller runs. When it does, $_POST arrives EMPTY and the capture fails
     * validation on whatever field is checked first - the officer is told "the
     * gender field is required" about a form they filled in completely, and
     * nothing in that message points at the server.
     *
     * The usual cause is a php.ini limit that has overflowed to a negative
     * number (max_multipart_body_parts, or the max_input_vars it defaults to),
     * after which the very first body part already "exceeds" the limit and PHP
     * drops the whole body.
     *
     * A body PHP would not parse is indistinguishable from an empty form at this
     * level, so the check is: a multipart POST that carried bytes, of which
     * nothing survived. That cannot be a real submission - the capture form
     * always posts _token at minimum.
     *
     * @return array{0:bool,1:string} [dropped, reason]
     */
    private function detectDroppedRequestBody(Request $request): array
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if (!str_contains($contentType, 'multipart/form-data')) {
            return [false, ''];
        }

        $declaredLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($declaredLength <= 0 || !empty($_POST) || !empty($_FILES)) {
            return [false, ''];
        }

        // Report the actual numbers back, not a general instruction to check php.ini.
        $maxParts = ini_get('max_multipart_body_parts');
        $maxVars = ini_get('max_input_vars');

        $reason = sprintf(
            'PHP discarded the submitted form data before it reached the application '
            . '(multipart body dropped; %s bytes were sent, none arrived). '
            . 'This is a server setting, not something in the form: ask IT to check '
            . 'max_multipart_body_parts (currently %s) and max_input_vars (currently %s) '
            . 'in the php.ini Apache/PHP-FPM loads - a value large enough to overflow makes '
            . 'the limit negative, which drops EVERY upload - then restart the web server.',
            number_format($declaredLength),
            $maxParts === false || $maxParts === '' ? 'unset' : $maxParts,
            $maxVars === false || $maxVars === '' ? 'unset' : $maxVars
        );

        return [true, $reason];
    }

    private function getKanoLgasForSelect()
    {
        return DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('StatLGAs.LGAName')
            ->get([
                'StatLGAs.LGAID as id',
                'StatLGAs.LGAName as name',
            ]);
    }

    /**
     * Districts, minus the ones that merely repeat an LGA name - those are
     * offered by the LGA select already, and listing them twice makes the two
     * fields look interchangeable when they are not.
     */
    private function getDistrictsForSelect($kanoLgas = null)
    {
        $districts = DB::connection('sqlsrv')
            ->table('districts')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $lgaNames = collect($kanoLgas ?? $this->getKanoLgasForSelect())
            ->pluck('name')
            ->map(fn ($name) => strtoupper(trim((string) $name)))
            ->filter()
            ->flip();

        $filtered = $districts
            ->reject(fn ($district) => $lgaNames->has(strtoupper(trim((string) $district->name))))
            ->values();

        return $filtered->isNotEmpty() ? $filtered : $districts;
    }
}
