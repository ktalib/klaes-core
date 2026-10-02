<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\InstrumentCaptureService;
use App\Models\StreetName;

class InstrumentController extends Controller
{
    public function __construct(
        protected InstrumentCaptureService $captureService
    ) {
    }

    public function index()
    {
        $PageTitle = 'Instrument Capture';
        $PageDescription = '';

        // Base statistics query (independent)
        $statsBase = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) {
                $q->where('is_deleted', 0)
                    ->orWhereNull('is_deleted');
            });
        $totalCount = (clone $statsBase)->count();
        $pendingCount = (clone $statsBase)->whereNull('registration_number')->count();
        $verifiedCount = (clone $statsBase)->whereNotNull('registration_number')->count();
        $todayCount = (clone $statsBase)->whereDate('created_at', today())->count();

        // Load only one page from DB to avoid rendering the full dataset in the browser
        $instruments = DB::connection('sqlsrv')->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', function ($join) {
                $join->on('ic.registration_number', '=', 'dr.registration_number')
                    ->on('ic.instrument_type', '=', 'dr.instrument_type');
            })
            ->where(function ($q) {
                $q->where('ic.is_deleted', 0)
                    ->orWhereNull('ic.is_deleted');
            })
            ->leftJoin('users as u', 'ic.created_by', '=', 'u.id')
            ->select(
                'ic.*',
                'dr.volume_no',
                'dr.page_no',
                'dr.serial_no',
                'dr.deeds_date',
                'dr.deeds_time',
                // CONCAT() in SQL Server treats NULL as '', which would yield the literal string
                // 'deed_reg_' for unregistered rows and trigger "Instrument not found" when the
                // Generate RDS / CoR actions POST that placeholder id. Emit real NULL instead so
                // Blade guards like @if(!empty($instrument->registered_instrument_id)) skip the
                // row until it has been registered.
                DB::raw("CASE WHEN dr.id IS NULL THEN NULL ELSE CONCAT('deed_reg_', dr.id) END as registered_instrument_id"),
                DB::raw("CASE WHEN dr.id IS NULL THEN 'pending' ELSE 'registered' END as status"),
                DB::raw("ISNULL(u.first_name, '') + ' ' + ISNULL(u.last_name, '') as created_by_name")
            )
            ->orderBy('ic.created_at', 'desc')
            ->orderBy('dr.deeds_date', 'desc')
            ->orderBy('ic.id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Prepare data for JS for the current page only
        $fullDataForJs = collect($instruments->items())->map(function ($item) {
            return (array) $item;
        });

        // Fetch unique instrument types for filtering
        $baseTypes = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) {
                $q->where('is_deleted', 0)
                    ->orWhereNull('is_deleted');
            })
            ->whereNotNull('instrument_type')
            ->distinct()
            ->pluck('instrument_type')->toArray();

        $opTypes = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('instrument_type', 'Occupancy Permit (OP)')
            ->where(function ($q) {
                $q->where('is_deleted', 0)
                    ->orWhereNull('is_deleted');
            })
            ->whereNotNull('op_type')
            ->distinct()
            ->pluck('op_type')
            ->map(function ($t) {
                return str_starts_with($t, 'OP ') ? $t : 'OP ' . $t;
            })->toArray();

        // Also include ST instrument types from deed_registrations (not captured via IC)
        $stTypes = DB::connection('sqlsrv')->table('deed_registrations')
            ->whereRaw("(instrument_type LIKE 'ST %' OR instrument_type = 'Sectional Titling CofO')")
            ->whereNotNull('instrument_type')
            ->distinct()
            ->pluck('instrument_type')->toArray();

        // Merge and unique
        $instrumentTypes = array_unique(array_merge($baseTypes, $opTypes, $stTypes));
        sort($instrumentTypes);

        return view('instruments.index', compact('PageTitle', 'PageDescription', 'instruments', 'totalCount', 'pendingCount', 'verifiedCount', 'todayCount', 'fullDataForJs', 'instrumentTypes'));
    }

    public function create()
    {
        $PageTitle = 'Instrument Capture';
        $PageDescription = 'Capture a new instrument ';

        // Fetch states, lgas, and districts for dropdowns
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = $this->getKanoLgasForSelect();
        $districts = $this->getDistrictsForSelect($lgas);
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view('instruments.create', compact('PageTitle', 'PageDescription', 'states', 'lgas', 'districts', 'streetNames'));
    }

    public function store(Request $request)
    {
        Log::info('Instrument Store Request', $request->all());
        try {
            // Basic Validation
            $instrumentType = $request->input('instrument_type');
            $rules = [
                'instrument_type' => 'required|string',
                'temp_fileno' => 'nullable|string|max:255',
                'fileno' => 'nullable|string|max:255',
            ];

            if (stripos($instrumentType, 'Occupancy Permit') !== false) {
                $rules['op_serial_number'] = ['required', 'regex:/^[1-9][0-9]*$/'];
            }

            $isAssignmentOrGift = stripos((string) $instrumentType, 'Deed of Assignment') !== false
                || stripos((string) $instrumentType, 'Deed of Gift') !== false;

            if ($isAssignmentOrGift) {
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
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // --- Robust Duplicate Check (5 Parameters) ---
            $checkType = (stripos($instrumentType, 'Occupancy Permit') !== false) ? 'op' : 'instrument';
            $dupParams = [
                'fileno' => $request->input('temp_fileno') ?? $request->input('fileno'),
                'prop_id' => $request->input('prop_id'),
                'instrument_type' => $instrumentType,
                'op_serial' => $request->input('op_serial_number'),
                'reg_no' => $request->input('reg_no'),
                'party_1' => $request->input('party_1_name'),
                'party_2' => $request->input('party_2_name'),
                'instrument_date' => $request->input('instrument_date'),
            ];

            if ($duplicateFound = check_duplicate($checkType, $dupParams)) {
                $errorMsg = "A similar " . ($checkType === 'op' ? "Occupancy Permit" : "Instrument") . " already exists for this property or serial number.";
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $errorMsg, 'duplicate' => $duplicateFound], 422);
                }
                return redirect()->back()->with('error', $errorMsg)->withInput();
            }
            // ----------------------------------------------

            $payload = $request->all();
            $tempFileno = trim((string) ($payload['temp_fileno'] ?? ''));
            $genericFileno = trim((string) ($payload['fileno'] ?? ''));

            // Keep OP submission non-blocking when temp file number is absent.
            // If generic fileno looks like a temporary number, use it as temp_fileno.
            if ($tempFileno === '' && $genericFileno !== '' && preg_match('/^(TEMP-|.*\(T\)$)/i', $genericFileno)) {
                $payload['temp_fileno'] = $genericFileno;
            }

            if (stripos((string) $instrumentType, 'Occupancy Permit') !== false && empty($payload['temp_fileno'])) {
                Log::warning('OP capture submitted without temp_fileno; allowing submission.', [
                    'fileno' => $genericFileno ?: null,
                    'op_serial_number' => $payload['op_serial_number'] ?? null,
                    'user_id' => Auth::id(),
                ]);
            }

            // Delegate to Service
            $result = $this->captureService->capture($payload);

            if ($result['success']) {
                $message = 'Instrument registered successfully. Ref: ' . ($result['reg_number'] ?? '');
                if ($request->expectsJson()) {
                    return response()->json(array_merge(['success' => true, 'message' => $message], $result));
                }
                return redirect()->route('instruments.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to register instrument.'], 500);
            }
            return redirect()->back()->with('error', 'Failed to register instrument')->withInput();

        } catch (\Exception $e) {
            Log::error('Instrument Capture Error: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * API: Check for duplicate instrument
     */
    public function checkDuplicate(Request $request)
    {
        $fileno = $request->query('fileno');
        $type = $request->query('instrument_type');
        $date = $request->query('date'); // instrumentDate
        $prop_id = $request->query('prop_id');
        $party_1 = $request->query('party_1');
        $party_2 = $request->query('party_2');
        $op_serial = $request->query('op_serial');
        $reg_no = $request->query('reg_no');

        Log::info('checkDuplicate API Called', [
            'fileno' => $fileno,
            'type' => $type,
            'date' => $date,
            'prop_id' => $prop_id,
            'party_1' => $party_1,
            'party_2' => $party_2,
            'op_serial' => $op_serial,
            'reg_no' => $reg_no
        ]);

        if (!$fileno && !$prop_id && !$op_serial && !$reg_no) {
            return response()->json(['exists' => false]);
        }

        // 1. Check for existing Instrument Record using DuplicateCheckService
        $params = [
            'fileno' => $fileno,
            'prop_id' => $prop_id,
            'instrument_type' => $type,
            'instrument_date' => $date,
            'party_1' => $party_1,
            'party_2' => $party_2,
            'op_serial' => $op_serial,
            'reg_no' => $reg_no
        ];

        // Determine subtype for service
        $checkType = ($type === 'Occupancy Permit' || !empty($op_serial)) ? 'op' : 'instrument';
        $instrument = check_duplicate($checkType, $params);

        // 2. Retrieve PRA (Property Record) History for Context
        // We always search for PRA history if a file number is provided, 
        // regardless of whether a duplicate instrument was found.
        $praHistoryQuery = DB::connection('sqlsrv')->table('pra');

        $normalizedFileno = strtoupper(trim($fileno));
        $praHistoryQuery->where(function ($q) use ($fileno, $normalizedFileno, $instrument) {
            $q->where('mlsFNo', $fileno)
                ->orWhere('mlsFNo', $normalizedFileno)
                ->orWhere('kangisFileNo', $fileno)
                ->orWhere('kangisFileNo', $normalizedFileno)
                ->orWhere('NewKANGISFileno', $fileno)
                ->orWhere('NewKANGISFileno', $normalizedFileno)
                ->orWhere('fileno', $fileno)
                ->orWhere('fileno', $normalizedFileno);

            // Fuzzy match for PRA history
            $cleanFileno = preg_replace('/[^A-Z0-9]/', '', $normalizedFileno);
            if ($cleanFileno) {
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(mlsFNo, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(kangisFileNo, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(NewKANGISFileno, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(fileno, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno]);
            }

            // If we found an instrument with a prop_id, include that in the search
            if ($instrument && !empty($instrument->prop_id)) {
                $q->orWhere('prop_id', $instrument->prop_id);
            }
        });

        $praHistory = $praHistoryQuery->orderBy('created_at', 'desc')->get();

        // 3. Retrieve Deed Registrations (Instrument Registration) History
        $deedHistoryQuery = DB::connection('sqlsrv')->table('deed_registrations');
        $deedHistoryQuery->where('fileno', $fileno);

        $deedHistory = $deedHistoryQuery->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $item->is_deed_reg = true; // Mark as deed registration record

            // Map common fields to match PRA history structure for unified JS display
            $item->transaction_type = $item->instrument_type ?? 'Instrument';

            // Map Parties
            $item->party_1 = $item->grantor ?? '';
            $item->party_2 = $item->grantee ?? '';

            // Map Particulars (Frontend expects serialNo, pageNo, volumeNo)
            $item->serialNo = $item->serial_no ?? '0';
            $item->pageNo = $item->page_no ?? '0';
            $item->volumeNo = $item->volume_no ?? '0';

            return $item;
        });

        // Merge histories for the modal
        $combinedHistory = $praHistory->concat($deedHistory)->sortByDesc('created_at')->values();

        // 4. Retrieve Latest Consent Application for Auto-fill
        // Map instrument type to consent_type for accurate party matching
        $consentTypeMap = [
            'Deed of Assignment' => 'Assignment',
            'Deed of Gift' => 'Gift',
            'Deed of Mortgage' => 'Mortgage',
            'Tripartite Mortgage' => 'Mortgage',
        ];

        // Normalize fileno for more robust matching
        $normalizedFileno = strtoupper(trim($fileno));

        $consentQuery = DB::connection('sqlsrv')->table('consent_applications')
            ->where(function ($q) use ($fileno, $normalizedFileno) {
                $q->where('file_number', $fileno)
                    ->orWhere('file_number', $normalizedFileno)
                    ->orWhere('c_of_o_no', $fileno)
                    ->orWhere('c_of_o_no', $normalizedFileno)
                    ->orWhere('right_of_occupancy_number', $fileno)
                    ->orWhere('right_of_occupancy_number', $normalizedFileno);

                // Fuzzy match by removing spaces and dashes for cases like "KNML 4155" vs "KNML-4155"
                $cleanFileno = preg_replace('/[^A-Z0-9]/', '', $normalizedFileno);
                if ($cleanFileno) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(file_number, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(c_of_o_no, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(right_of_occupancy_number, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno]);
                }
            });

        $consentApp = null;
        if ($type && isset($consentTypeMap[$type])) {
            // Priority 1: Match by specific consent type (strict match)
            $consentApp = (clone $consentQuery)
                ->where('consent_type', $consentTypeMap[$type])
                ->orderBy('created_at', 'desc')
                ->first();

            // Priority 2: Fallback within the same group (e.g., Assignment fallback to Gift and vice versa)
            if (!$consentApp) {
                $requestedType = $consentTypeMap[$type];
                $consentGroups = [
                    'Assignment' => ['Assignment', 'Gift'],
                    'Gift' => ['Assignment', 'Gift'],
                    'Mortgage' => ['Mortgage'],
                ];
                $allowedFallbackTypes = $consentGroups[$requestedType] ?? [$requestedType];

                $consentApp = (clone $consentQuery)
                    ->whereIn('consent_type', $allowedFallbackTypes)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }
        }

        // Final fallback: If no type-specific or group-specific consent was found,
        // we generally do NOT want to auto-fill from a completely unrelated consent
        // (e.g. Mortgage consent should not auto-fill Assignment fields).
        // However, we can still allow auto-fill if the instrument type is NOT in the gated map 
        // (like Power of Attorney) to provide basic property info.
        if (!$consentApp && (!$type || !isset($consentTypeMap[$type]))) {
            $consentApp = $consentQuery->orderBy('created_at', 'desc')->first();
        }

        // 5. Retrieve latest prior instrument capture record for this file (any type)
        //    Used as fallback for auto-filling property & solicitor details.
        $priorQuery = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) use ($fileno, $normalizedFileno) {
                $q->where('mlsFNo', $fileno)
                    ->orWhere('mlsFNo', $normalizedFileno)
                    ->orWhere('kangisFileNo', $fileno)
                    ->orWhere('kangisFileNo', $normalizedFileno)
                    ->orWhere('NewKANGISFileno', $fileno)
                    ->orWhere('NewKANGISFileno', $normalizedFileno)
                    ->orWhere('temp_fileno', $fileno)
                    ->orWhere('temp_fileno', $normalizedFileno);

                // Fuzzy match for instrument capture as well
                $cleanFileno = preg_replace('/[^A-Z0-9]/', '', $normalizedFileno);
                if ($cleanFileno) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(mlsFNo, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(kangisFileNo, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(NewKANGISFileno, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(temp_fileno, ' ', ''), '-', ''), '/', '') = ?", [$cleanFileno]);
                }
            })
            ->where(function ($q) {
                $q->where('is_deleted', 0)
                    ->orWhereNull('is_deleted');
            });

        // Exclude the exact duplicate so prior_instrument is always a different record
        if ($instrument) {
            $priorQuery->where('id', '!=', $instrument->id);
        }

        $priorInstrument = $priorQuery->orderBy('created_at', 'desc')->first();

        // If there is no separate prior record, return the duplicate record itself
        // so frontend Create New can still auto-fill from instrument_capture.
        if (!$priorInstrument && $instrument) {
            $priorInstrument = $instrument;
        }

        return response()->json([
            'exists' => $instrument ? true : false,
            'instrument' => $instrument,
            'pra_history' => $combinedHistory,
            'pra' => $praHistory->first(),
            'consent_app' => $consentApp,
            'prior_instrument' => $priorInstrument
        ]);
    }

    /**
     * API: Find OP records that EXACTLY match all supplied identifying fields
     * (op_serial_number + serial_no + page_no + volume_no + party_2_name).
     * Used by the OP capture modal to prompt the user before creating a fresh
     * record when an existing OP exactly matches their entry. Returns 0 or 1
     * candidates in typical use (rare to exceed 1 since the 5-field combo is
     * effectively unique).
     */
    public function checkOpCandidates(Request $request)
    {
        $fields = [
            'op_serial_number' => $request->query('op_serial_number'),
            'serial_no'        => $request->query('serial_no'),
            'page_no'          => $request->query('page_no'),
            'volume_no'        => $request->query('volume_no'),
            'party_2_name'     => $request->query('party_2_name'),
        ];

        try {
            $service    = app(\App\Services\Pra\PraRecordService::class);
            $candidates = $service->findCandidateOpsByFields($fields);
        } catch (\Throwable $e) {
            Log::error('OP candidate check failed', [
                'error'  => $e->getMessage(),
                'fields' => $fields,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Candidate check failed due to server error.',
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'count'      => count($candidates),
            'candidates' => $candidates,
        ]);
    }

    /**
     * API: Lookup occupancy permit capture by OP SerialNo.
     */
    public function lookupByOpSerialNumber(Request $request)
    {
        $serial = strtoupper(trim((string) $request->query('op_serial_number', '')));

        if ($serial === '') {
            return response()->json([
                'success' => false,
                'found' => false,
                'message' => 'OP SerialNo is required.'
            ], 422);
        }

        try {
            $available = Schema::connection('sqlsrv')->getColumnListing('instrument_capture');
            $availableLookup = array_flip($available);

            if (!isset($availableLookup['op_serial_number'])) {
                return response()->json([
                    'success' => false,
                    'found' => false,
                    'message' => 'OP serial lookup is not available on this schema.'
                ], 500);
            }

            $desiredColumns = [
                'id',
                'prop_id',
                'op_serial_number',
                'op_type',
                'instrument_type',
                'temp_fileno',
                'mlsFNo',
                'kangisFileNo',
                'NewKANGISFileno',
                'fileno',
                'serial_no',
                'page_no',
                'volume_no',
                'registration_number',
                'deeds_serial_no',
                'party_1_name',
                'party_2_name',
                'plot_number',
                'tp_no',
                'survey_plan_no',
                'land_use',
                'purpose',
                'property_description',
                'property_location',
                'district',
                'lga',
                'party_1_state',
                'party_1_address',
                'party_1_lga',
                'party_1_district',
                'party_2_state',
                'party_2_address',
                'party_2_lga',
                'party_2_district',
                'street_name',
                'transaction_date',
                'entry_date',
                'reg_date',
                'reg_time',
                'deeds_date',
                'deeds_time',
                'instrument_date',
                'created_at'
            ];

            $selectColumns = array_values(array_filter($desiredColumns, function ($column) use ($availableLookup) {
                return isset($availableLookup[$column]);
            }));

            if (empty($selectColumns)) {
                $selectColumns = ['id', 'op_serial_number'];
            }

            $captureRecords = DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->select($selectColumns)
                ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
                ->orderByDesc('id')
                ->get();

            $captureRecords = $captureRecords->map(function ($row) {
                $item = (array) $row;
                $item['source_table'] = 'instrument_capture';
                $item['source_pra_id'] = $item['source_pra_id'] ?? null;
                return (object) $item;
            });

            $praRecords = collect();
            if (Schema::connection('sqlsrv')->hasTable('pra')) {
                $availablePra = Schema::connection('sqlsrv')->getColumnListing('pra');
                $availablePraLookup = array_flip($availablePra);

                if (isset($availablePraLookup['op_serial_number'])) {
                    $desiredPraColumns = [
                        'id',
                        'prop_id',
                        'op_serial_number',
                        'op_type',
                        'transaction_type',
                        'instrument_type',
                        'fileno',
                        'mlsFNo',
                        'regNo',
                        'reg_no',
                        'registration_number',
                        'serialNo',
                        'serial_no',
                        'pageNo',
                        'page_no',
                        'volumeNo',
                        'volume_no',
                        'is_subdivided',
                        'op_count',
                        'party_1',
                        'party_2',
                        'party_1_name',
                        'party_2_name',
                        'plot_no',
                        'plot_number',
                        'tp_no',
                        'survey_plan_no',
                        'land_use',
                        'purpose',
                        'property_description',
                        'property_location',
                        'district',
                        'districtName',
                        'lga',
                        'lgsaOrCity',
                        'streetName',
                        'location',
                        'transaction_date',
                        'entry_date',
                        'reg_date',
                        'reg_time',
                        'deeds_date',
                        'deeds_time',
                        'created_at',
                    ];

                    $selectPraColumns = array_values(array_filter($desiredPraColumns, function ($column) use ($availablePraLookup) {
                        return isset($availablePraLookup[$column]);
                    }));

                    if (empty($selectPraColumns)) {
                        $selectPraColumns = ['id', 'op_serial_number'];
                    }

                    $rawPraRecords = DB::connection('sqlsrv')
                        ->table('pra')
                        ->select($selectPraColumns)
                        ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
                        ->orderByDesc('id')
                        ->get();

                    $praRecords = $rawPraRecords->map(function ($row) {
                        $item = (array) $row;
                        $pick = static function (array $source, array $keys, $default = null) {
                            foreach ($keys as $key) {
                                if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                                    return $source[$key];
                                }
                            }
                            return $default;
                        };

                        $serialNo = $pick($item, ['serial_no', 'serialNo']);
                        $pageNo = $pick($item, ['page_no', 'pageNo'], $serialNo);
                        $registrationNo = $pick($item, ['registration_number', 'regNo', 'reg_no']);

                        return (object) [
                            'id' => $pick($item, ['id']),
                            'source_table' => 'pra',
                            'source_pra_id' => $pick($item, ['id']),
                            'prop_id' => $pick($item, ['prop_id']),
                            'op_serial_number' => $pick($item, ['op_serial_number']),
                            'op_type' => $pick($item, ['op_type']),
                            'instrument_type' => $pick($item, ['transaction_type', 'instrument_type'], 'Occupancy Permit (OP)'),
                            'temp_fileno' => $pick($item, ['fileno']),
                            'mlsFNo' => $pick($item, ['mlsFNo']),
                            'kangisFileNo' => null,
                            'NewKANGISFileno' => null,
                            'fileno' => $pick($item, ['fileno']),
                            'serial_no' => $serialNo,
                            'page_no' => $pageNo,
                            'volume_no' => $pick($item, ['volume_no', 'volumeNo']),
                            'registration_number' => $registrationNo,
                            'deeds_serial_no' => $registrationNo,
                            'party_1_name' => $pick($item, ['party_1_name', 'party_1']),
                            'party_2_name' => $pick($item, ['party_2_name', 'party_2']),
                            'plot_number' => $pick($item, ['plot_number', 'plot_no']),
                            'tp_no' => $pick($item, ['tp_no']),
                            'survey_plan_no' => $pick($item, ['survey_plan_no']),
                            'land_use' => $pick($item, ['land_use']),
                            'purpose' => $pick($item, ['purpose']),
                            'property_description' => $pick($item, ['property_description']),
                            'property_location' => $pick($item, ['property_location', 'location']),
                            'district' => $pick($item, ['district', 'districtName']),
                            'lga' => $pick($item, ['lga', 'lgsaOrCity']),
                            'street_name' => $pick($item, ['streetName']),
                            'party_1_state' => null,
                            'party_2_state' => null,
                            'transaction_date' => $pick($item, ['transaction_date']),
                            'entry_date' => $pick($item, ['entry_date']),
                            'reg_date' => $pick($item, ['reg_date']),
                            'reg_time' => $pick($item, ['reg_time']),
                            'deeds_date' => $pick($item, ['deeds_date']),
                            'deeds_time' => $pick($item, ['deeds_time']),
                            'created_at' => $pick($item, ['created_at']),
                            'is_subdivided' => (int) ($item['is_subdivided'] ?? 0),
                            'op_count' => isset($item['op_count']) && $item['op_count'] !== null ? (int) $item['op_count'] : null,
                        ];
                    });
                }
            }

            $records = $captureRecords
                ->concat($praRecords)
                ->sortByDesc(function ($row) {
                    return $row->created_at ?? 0;
                })
                ->values();

            // Deduplicate equivalent OP rows (same OP serial + allottee + plot/tp/property)
            // and keep the most relevant record for UI display.
            $normalize = static function ($value) {
                return strtoupper(trim((string) ($value ?? '')));
            };

            $scoreRecord = static function ($row) use ($normalize) {
                $instrumentType = $normalize($row->instrument_type ?? '');
                $opType = $normalize($row->op_type ?? '');
                $source = $normalize($row->source_table ?? '');
                $hasPropId = !empty($row->prop_id);
                $hasRegDate = !empty($row->deeds_date) || !empty($row->reg_date);
                $hasRegTime = !empty($row->deeds_time) || !empty($row->reg_time);

                $score = 0;

                // Strongly prefer records that carry prop_id, since downstream
                // flows (Update Existing Record) require it.
                if ($hasPropId) {
                    $score += 200;
                }

                // Prefer records that can autofill registration date/time fields.
                if ($hasRegDate) {
                    $score += 80;
                }
                if ($hasRegTime) {
                    $score += 40;
                }

                // Prefer explicit Occupancy Permit display over Transfer/other variants.
                if (str_contains($instrumentType, 'OCCUPANCY PERMIT')) {
                    $score += 120;
                }

                // Prefer OP resettlement variants when available.
                if (str_contains($opType, 'RESETTLEMENT')) {
                    $score += 40;
                }

                // Prefer records with stronger identifying details.
                if (!empty($row->plot_number)) {
                    $score += 10;
                }
                if (!empty($row->tp_no)) {
                    $score += 10;
                }
                if (!empty($row->property_description) || !empty($row->property_location)) {
                    $score += 5;
                }

                // Slightly prefer capture table when all else is equal.
                if ($source === 'INSTRUMENT_CAPTURE') {
                    $score += 3;
                }

                return $score;
            };

            $deduped = [];

            foreach ($records as $row) {
                $serialKey = $normalize($row->op_serial_number ?? '');
                $allotteeKey = $normalize($row->party_2_name ?? '');

                // Deduplicate by OP serial + allottee only; minor data
                // differences (plot number, property description) across
                // source tables should not produce separate cards.
                $equivalenceKey = $serialKey . '|' . $allotteeKey;

                $existing = $deduped[$equivalenceKey] ?? null;

                if (!$existing) {
                    $deduped[$equivalenceKey] = $row;
                    continue;
                }

                $existingScore = $scoreRecord($existing);
                $currentScore = $scoreRecord($row);

                // Hard preference: if only one candidate has prop_id, keep that one.
                $existingHasPropId = !empty($existing->prop_id);
                $currentHasPropId = !empty($row->prop_id);
                if ($currentHasPropId && !$existingHasPropId) {
                    $deduped[$equivalenceKey] = $row;
                    continue;
                }
                if ($existingHasPropId && !$currentHasPropId) {
                    continue;
                }

                // Prefer records with registration date/time where available.
                $existingHasRegDate = !empty($existing->deeds_date) || !empty($existing->reg_date);
                $currentHasRegDate = !empty($row->deeds_date) || !empty($row->reg_date);
                if ($currentHasRegDate && !$existingHasRegDate) {
                    $deduped[$equivalenceKey] = $row;
                    continue;
                }
                if ($existingHasRegDate && !$currentHasRegDate) {
                    continue;
                }

                $existingHasRegTime = !empty($existing->deeds_time) || !empty($existing->reg_time);
                $currentHasRegTime = !empty($row->deeds_time) || !empty($row->reg_time);
                if ($currentHasRegTime && !$existingHasRegTime) {
                    $deduped[$equivalenceKey] = $row;
                    continue;
                }
                if ($existingHasRegTime && !$currentHasRegTime) {
                    continue;
                }

                if ($currentScore > $existingScore) {
                    $deduped[$equivalenceKey] = $row;
                }
            }

            $records = collect(array_values($deduped))
                ->sortByDesc(function ($row) {
                    return $row->created_at ?? 0;
                })
                ->values();

            // Second pass: exclude OPs that already have a Transfer of Title.
            // If a Transfer of Title exists for the same prop_id or fileno,
            // that OP is "used up" and should not appear as a selectable result.
            // Map prop_id/fileno → the linked file number from the Transfer record
            $transferPropIds = [];
            $transferFilenos = [];

            foreach ($records as $row) {
                $type = $normalize($row->instrument_type ?? '');
                if (str_contains($type, 'TRANSFER')) {
                    $pid = $normalize($row->prop_id ?? '');
                    $fno = $normalize($row->fileno ?? $row->temp_fileno ?? '');
                    $displayFno = $row->mlsFNo ?? $row->fileno ?? $row->temp_fileno ?? '';
                    if ($pid !== '')
                        $transferPropIds[$pid] = $displayFno;
                    if ($fno !== '')
                        $transferFilenos[$fno] = $displayFno;
                }
            }

            // Also check PRA for Transfer of Title records sharing the same
            // OP serial number that may not have been returned above (e.g.
            // different instrument_type column value).
            if (isset($rawPraRecords) && !empty($rawPraRecords)) {
                foreach ($rawPraRecords as $rawRow) {
                    $rawItem = (array) $rawRow;
                    $txnType = $normalize($rawItem['transaction_type'] ?? '');
                    $instType = $normalize($rawItem['instrument_type'] ?? '');
                    if (str_contains($txnType, 'TRANSFER') || str_contains($instType, 'TRANSFER')) {
                        $pid = $normalize($rawItem['prop_id'] ?? '');
                        $fno = $normalize($rawItem['fileno'] ?? '');
                        $displayFno = $rawItem['mlsFNo'] ?? $rawItem['fileno'] ?? '';
                        if ($pid !== '')
                            $transferPropIds[$pid] = $displayFno;
                        if ($fno !== '')
                            $transferFilenos[$fno] = $displayFno;
                    }
                }
            }

            $preFilterCount = $records->count();
            $hasTransfer = !empty($transferPropIds) || !empty($transferFilenos);

            $filtered = [];
            $usedRecords = [];
            foreach ($records as $row) {
                $type = $normalize($row->instrument_type ?? '');
                // Always exclude Transfer of Title rows themselves from OP lookup results
                if (str_contains($type, 'TRANSFER')) {
                    continue;
                }

                $pid = $normalize($row->prop_id ?? '');
                $fno = $normalize($row->fileno ?? $row->temp_fileno ?? '');

                // Mark OP as already_used if a Transfer already exists for this property/file.
                // For subdivided OPs (is_subdivided=1), count Transfer rows; only mark used
                // when the number of linked Transfers reaches op_count.
                // For merger OPs (is_merger_op=1), detect completion by merger_group_id:
                // the ToT row gets the same merger_group_id so prop_id/fileno matching
                // would never find it (ToT has a new merged file's prop_id).
                $isSubdivided = !empty($row->is_subdivided);
                $opCount = isset($row->op_count) && $row->op_count !== null ? (int) $row->op_count : null;
                $isMergerOp = !empty($row->is_merger_op);
                $mergerGroupId = $row->merger_group_id ?? null;

                // Case 3: Merger OP — detect by shared merger_group_id on the ToT row
                if ($isMergerOp && $mergerGroupId) {
                    $mergerToT = DB::connection('sqlsrv')->table('pra')
                        ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(CAST(merger_group_id AS NVARCHAR(36)),\\'\\')))) = ?", [strtoupper($mergerGroupId)])
                        ->where(function ($q) {
                            $q->whereRaw("UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER%'")
                                ->orWhereRaw("UPPER(ISNULL(transaction_type,'')) LIKE '%TRANSFER%'");
                        })
                        ->select(['mlsFNo', 'fileno'])
                        ->first();
                    if ($mergerToT) {
                        $row->already_used = true;
                        $row->linked_fileno = $mergerToT->mlsFNo ?? $mergerToT->fileno ?? '';
                        $usedRecords[] = $row;
                    } else {
                        $row->already_used = false;
                        $row->is_merger_op = 1; // keep flag for frontend badge
                        $filtered[] = $row;
                    }
                    continue;
                }

                $hasTransferMatch = ($pid !== '' && isset($transferPropIds[$pid])) || ($fno !== '' && isset($transferFilenos[$fno]));

                if ($hasTransferMatch) {
                    if ($isSubdivided && $opCount !== null && $opCount > 1) {
                        // Count Transfer of Title rows in PRA by prop_id/fileno.
                        // Cannot rely on $rawPraRecords here because it is filtered
                        // to op_serial_number = 608 only; existing Transfer rows for
                        // this property may carry a different (or null) op_serial_number.
                        $tcQuery = DB::connection('sqlsrv')->table('pra')
                            ->where(function ($q) use ($pid, $fno) {
                                if ($pid !== '') {
                                    $q->orWhereRaw("UPPER(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(100))))) = ?", [$pid]);
                                }
                                if ($fno !== '') {
                                    $q->orWhereRaw("UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?", [$fno]);
                                }
                            })
                            ->where(function ($q) {
                                $q->whereRaw("UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER%'")
                                    ->orWhereRaw("UPPER(ISNULL(transaction_type,'')) LIKE '%TRANSFER%'");
                            });
                        $transferCount = $tcQuery->count();
                        $row->transfer_count = $transferCount;
                        $row->op_count = $opCount;
                        if ($transferCount >= $opCount) {
                            // All subdivision slots consumed — now truly linked
                            $row->already_used = true;
                            $linkedFno = ($pid !== '' && isset($transferPropIds[$pid])) ? $transferPropIds[$pid] : ($transferFilenos[$fno] ?? '');
                            $row->linked_fileno = $linkedFno;
                            $usedRecords[] = $row;
                        } else {
                            // Still has remaining subdivision slots — keep as Unlinked
                            $row->already_used = false;
                            $filtered[] = $row;
                        }
                        continue;
                    }

                    $row->already_used = true;
                    $linkedFno = ($pid !== '' && isset($transferPropIds[$pid])) ? $transferPropIds[$pid] : ($transferFilenos[$fno] ?? '');
                    $row->linked_fileno = $linkedFno;
                    $usedRecords[] = $row;
                    continue;
                }

                $row->already_used = false;
                $filtered[] = $row;
            }

            // Combine: available records first, then used records at the end
            $allRecords = array_merge($filtered, $usedRecords);

            $records = collect($allRecords)
                ->sort(function ($a, $b) {
                    // Available first, then used
                    $aUsed = !empty($a->already_used) ? 1 : 0;
                    $bUsed = !empty($b->already_used) ? 1 : 0;
                    if ($aUsed !== $bUsed)
                        return $aUsed - $bUsed;
                    // Then by created_at descending
                    return strcmp($b->created_at ?? '', $a->created_at ?? '');
                })
                ->values();
        } catch (\Throwable $e) {
            Log::error('OP serial lookup failed', [
                'op_serial_number' => $serial,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'found' => false,
                'message' => 'Lookup failed due to server error.'
            ], 500);
        }

        if ($records->isEmpty()) {
            return response()->json([
                'success' => true,
                'found' => false,
                'message' => 'No matching OP SerialNo record found.'
            ]);
        }

        $data = $records->first();
        $multiple = $records->count() > 1;
        $hasUsedRecords = $records->contains(function ($row) {
            return !empty($row->already_used);
        });
        $allUsed = $records->every(function ($row) {
            return !empty($row->already_used);
        });

        return response()->json([
            'success' => true,
            'found' => true,
            'multiple' => $multiple,
            'count' => $records->count(),
            'has_used_records' => $hasUsedRecords,
            'all_used' => $allUsed,
            // Keep backward compatibility for consumers expecting a single object.
            'data' => $data,
            // New payload for multi-select UI.
            'records' => $records->values()
        ]);
    }

    /**
     * API: Get Capture Record
     */
    public function getCaptureRecord($id)
    {
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function generateParticulars()
    {
        try {
            // Get the last record from the NEW table
            $lastRecord = DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->whereNotNull('root_reg_number')
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastRecord || !$lastRecord->root_reg_number) {
                $serial_no = 1;
                $page_no = 1;
                $volume_no = 1;
            } else {
                $regParts = explode('/', $lastRecord->root_reg_number);
                $serial_no = (int) ($regParts[0] ?? 0) + 1;
                $page_no = (int) ($regParts[1] ?? 0) + 1;
                $volume_no = (int) ($regParts[2] ?? 1);

                if ($serial_no > 300) {
                    $serial_no = 1;
                    $page_no = 1;
                    $volume_no++;
                }
            }

            $formatted = "{$serial_no}/{$page_no}/{$volume_no}";

            return response()->json([
                'success' => true,
                'rootRegistrationNumber' => $formatted,
                'serial_no' => $serial_no,
                'page_no' => $page_no,
                'volume_no' => $volume_no
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate particulars: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Coroi()
    {
        $title = 'Confirmation Of Instrument Registration';
        return view('coroi.index', compact('title'));
    }

    public function show($id)
    {
        $PageTitle = 'View Instrument';
        $PageDescription = 'View details of an instrument record';
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = $this->getKanoLgasForSelect();
        $districts = $this->getDistrictsForSelect($lgas);
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return redirect()->route('instruments.index')->with('error', 'Record not found');
        }

        return view('instruments.show', compact('PageTitle', 'PageDescription', 'record', 'states', 'lgas', 'districts', 'streetNames'));
    }

    public function edit($id)
    {
        $PageTitle = 'Edit Instrument';
        $PageDescription = 'Edit an existing instrument record';
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = $this->getKanoLgasForSelect();
        $districts = $this->getDistrictsForSelect($lgas);
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return redirect()->route('instruments.index')->with('error', 'Record not found');
        }

        return view('instruments.edit', compact('PageTitle', 'PageDescription', 'states', 'record', 'lgas', 'districts', 'streetNames'));
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

        $filteredDistricts = $districts
            ->reject(fn ($district) => $lgaNames->has(strtoupper(trim((string) $district->name))))
            ->values();

        return $filteredDistricts->isNotEmpty() ? $filteredDistricts : $districts;
    }

    public function update(Request $request, $id)
    {
        try {
            // Validate if necessary?
            // Service handles most logic, but basic presence checks might be good?
            // We'll pass everything to service.

            $result = $this->captureService->update($id, $request->all());

            if ($result['success']) {
                $message = 'Instrument updated successfully. Ref: ' . ($result['reg_number'] ?? '');

                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => $message]);
                }

                return redirect()->route('instruments.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update instrument.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update instrument')->withInput();

        } catch (\Exception $e) {
            Log::error('Instrument Update Error: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $id)
                ->update(['is_deleted' => 1]);

            return redirect()->route('instruments.index')->with('success', 'Instrument deleted successfully');
        } catch (\Exception $e) {
            Log::error('Instrument Delete Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete instrument: ' . $e->getMessage());
        }
    }

    public function getLgas($state)
    {
        $query = DB::connection('sqlsrv')->table('StatLGAs');

        if (is_numeric($state)) {
            $query->where('StateID', $state);
        } else {
            $stateRecord = DB::connection('sqlsrv')->table('States')
                ->where('StateName', $state)
                ->first();

            if ($stateRecord) {
                $query->where('StateID', $stateRecord->StateID);
            } else {
                return response()->json([]);
            }
        }

        $lgas = $query->orderBy('LGAName')->get();
        return response()->json($lgas);
    }

    public function searchTpLookups(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $results = collect();

        if ($term !== '') {
            $results = DB::connection('sqlsrv')
                ->table('tp_lookups')
                ->select('tp_no')
                ->whereNotNull('tp_no')
                ->where('tp_no', 'like', $term . '%')
                ->distinct()
                ->orderBy('tp_no')
                ->limit(20)
                ->get()
                ->map(function ($row) {
                    $tpNo = trim((string) $row->tp_no);
                    return [
                        'id' => $tpNo,
                        'text' => $tpNo,
                    ];
                })
                ->filter(fn ($row) => $row['id'] !== '')
                ->values();
        }

        $results->push([
            'id' => '__other__',
            'text' => 'Other',
        ]);

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => false],
        ]);
    }

    public function storeTpLookup(Request $request)
    {
        $validated = $request->validate([
            'tp_no' => ['required', 'string', 'max:255'],
        ]);

        $tpNo = strtoupper(trim($validated['tp_no']));

        if ($tpNo === '' || in_array($tpNo, ['OTHER', 'OTHERS', '__OTHER__'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid TP number.',
            ], 422);
        }

        $exists = DB::connection('sqlsrv')
            ->table('tp_lookups')
            ->where('tp_no', $tpNo)
            ->exists();

        if (!$exists) {
            DB::connection('sqlsrv')->table('tp_lookups')->insert([
                'tp_no' => $tpNo,
            ]);
        }

        return response()->json([
            'success' => true,
            'tp_no' => $tpNo,
        ]);
    }

    /**
     * Backfill District/LGA for a selected TP No by checking source tables
     * that carry both a tp_no and district/lga (instrument_capture, file_indexings, pra).
     */
    public function tpLookupLocation(Request $request)
    {
        $tpNo = strtoupper(trim((string) $request->input('tp_no', '')));

        if ($tpNo === '' || in_array($tpNo, ['OTHER', 'OTHERS', '__OTHER__'], true)) {
            return response()->json(['district' => null, 'lga' => null]);
        }

        $matchTpNo = fn ($query, string $column) => $query->whereRaw(
            'UPPER(LTRIM(RTRIM(CAST(' . $column . ' AS NVARCHAR(255))))) = ?',
            [$tpNo]
        );

        $sources = [
            ['table' => 'instrument_capture', 'tp_col' => 'tp_no', 'district_col' => 'district', 'lga_col' => 'lga'],
            ['table' => 'file_indexings', 'tp_col' => 'tp_no', 'district_col' => 'district', 'lga_col' => 'lga'],
            ['table' => 'pra', 'tp_col' => 'tp_no', 'district_col' => 'districtName', 'lga_col' => 'lgsaOrCity'],
        ];

        foreach ($sources as $source) {
            if (!Schema::connection('sqlsrv')->hasTable($source['table'])) {
                continue;
            }

            $columns = Schema::connection('sqlsrv')->getColumnListing($source['table']);
            if (!in_array($source['tp_col'], $columns) || !in_array($source['district_col'], $columns) || !in_array($source['lga_col'], $columns)) {
                continue;
            }

            $row = $matchTpNo(
                DB::connection('sqlsrv')->table($source['table']),
                $source['tp_col']
            )
                ->whereNotNull($source['district_col'])
                ->whereNotNull($source['lga_col'])
                ->where($source['district_col'], '!=', '')
                ->where($source['lga_col'], '!=', '')
                ->orderByDesc('id')
                ->select([$source['district_col'] . ' as district', $source['lga_col'] . ' as lga'])
                ->first();

            if ($row) {
                return response()->json([
                    'district' => trim((string) $row->district),
                    'lga' => trim((string) $row->lga),
                ]);
            }
        }

        return response()->json(['district' => null, 'lga' => null]);
    }

    /**
     * Backfill LGA for a known District by taking the most common lga value
     * associated with that district across the same source tables used by
     * tpLookupLocation(). Data in these tables is inconsistently spelled, so
     * this picks the majority value rather than the first/latest row.
     */
    public function districtLookupLga(Request $request)
    {
        $district = strtoupper(trim((string) $request->input('district', '')));

        if ($district === '') {
            return response()->json(['lga' => null]);
        }

        $sources = [
            ['table' => 'instrument_capture', 'district_col' => 'district', 'lga_col' => 'lga'],
            ['table' => 'file_indexings', 'district_col' => 'district', 'lga_col' => 'lga'],
            ['table' => 'pra', 'district_col' => 'districtName', 'lga_col' => 'lgsaOrCity'],
        ];

        foreach ($sources as $source) {
            if (!Schema::connection('sqlsrv')->hasTable($source['table'])) {
                continue;
            }

            $columns = Schema::connection('sqlsrv')->getColumnListing($source['table']);
            if (!in_array($source['district_col'], $columns) || !in_array($source['lga_col'], $columns)) {
                continue;
            }

            $lgaCol = $source['lga_col'];

            $top = DB::connection('sqlsrv')->table($source['table'])
                ->whereRaw('UPPER(LTRIM(RTRIM(CAST(' . $source['district_col'] . ' AS NVARCHAR(255))))) = ?', [$district])
                ->whereNotNull($lgaCol)
                ->where($lgaCol, '!=', '')
                ->whereRaw('LTRIM(RTRIM(CAST(' . $lgaCol . ' AS NVARCHAR(255)))) NOT LIKE ?', ['%[0-9]%'])
                ->select($lgaCol . ' as lga', DB::raw('COUNT(*) as cnt'))
                ->groupBy($lgaCol)
                ->orderByDesc('cnt')
                ->first();

            if ($top) {
                return response()->json(['lga' => trim((string) $top->lga)]);
            }
        }

        return response()->json(['lga' => null]);
    }

    public function getNextTempFileNo()
    {
        $prefix = 'TEMP-';

        try {
            // BUG FIX: previously this method scanned `is_used = 0` rows and
            // returned an id without marking it used, so concurrent / repeated
            // requests handed out the same TEMP-XXXXX value (causing duplicate
            // temp_fileno across instrument_capture / pra / pic).
            //
            // Always allocate a NEW sequence id and mark it used in the same
            // INSERT so it can never be handed out twice.
            $sequenceId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                'created_by' => Auth::id(),
                'is_used' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tempFileNo = $prefix . str_pad((string) $sequenceId, 5, '0', STR_PAD_LEFT);

            return response()->json([
                'status' => 'success',
                'temp_fileno' => $tempFileNo,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Failed to generate temp file number from sequence table in InstrumentController: ' . $e->getMessage());

            // Fallback to old method if sequence table fails
            return $this->getNextTempFileNoLegacy();
        }
    }

    /**
     * Fallback method for generating temp file numbers (Legacy Logic)
     * Used only if sequence table insertion fails
     */
    private function getNextTempFileNoLegacy()
    {
        try {
            $prefix = 'TEMP-';
            $maxNumber = 0;
            $tablesToCheck = ['pra', 'pic', 'instrument_capture'];

            foreach ($tablesToCheck as $table) {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }

                // Check both temp_fileno AND mlsFNo columns since some records miss temp_fileno
                $colsToCheck = ['temp_fileno', 'mlsFNo'];
                foreach ($colsToCheck as $col) {
                    if (!Schema::connection('sqlsrv')->hasColumn($table, $col)) {
                        continue;
                    }

                    $latestValue = DB::connection('sqlsrv')
                        ->table($table)
                        ->whereNotNull($col)
                        ->where($col, 'like', $prefix . '%')
                        ->orderByDesc(DB::raw("TRY_CONVERT(INT, REPLACE(CAST(" . $col . " AS VARCHAR), '{$prefix}', ''))"))
                        ->orderByDesc($col)
                        ->value($col);

                    if ($latestValue && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', $latestValue, $matches)) {
                        $maxNumber = max($maxNumber, (int) $matches[1]);
                    }
                }
            }

            $nextNumber = $maxNumber + 1;
            $tempFileNo = $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            return response()->json([
                'status' => 'success',
                'temp_fileno' => $tempFileNo,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate temporary file number in InstrumentController (Legacy): ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to generate a temporary file number',
            ], 500);
        }
    }

    public function previewRegistrationNumber(Request $request)
    {
        $type = $request->query('instrument_type');
        $opType = $request->query('op_type');

        if (!$type) {
            return response()->json(['success' => false, 'message' => 'Instrument type required'], 400);
        }

        try {
            $service = app(\App\Services\InstrumentRegistrationService::class);
            $preview = $service->previewNextNumber($type, $opType);

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'deeds_date' => $preview['deeds_date'] ?? null,
                'deeds_time' => $preview['deeds_time'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Instrument Capture data
     */
    public function exportCapture(Request $request)
    {
        try {
            $instrumentType = $request->query('instrument_type');
            $volumeNo = $request->query('volume_no');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // ST instruments are registered directly in deed_registrations (no IC record)
            $isSTType = $instrumentType && (
                str_starts_with($instrumentType, 'ST ') || $instrumentType === 'Sectional Titling CofO'
            );

            $icResults = collect();

            // Skip IC query when a specific ST type is selected
            if (!$isSTType) {
                $query = DB::connection('sqlsrv')->table('instrument_capture as ic')
                    ->leftJoin('deed_registrations as dr', function ($join) {
                        $join->on('ic.registration_number', '=', 'dr.registration_number')
                            ->on('ic.instrument_type', '=', 'dr.instrument_type');
                    })
                    ->where(function ($q) {
                        $q->where('ic.is_deleted', 0)
                            ->orWhereNull('ic.is_deleted');
                    })
                    ->select(
                        'ic.*',
                        'dr.volume_no',
                        'dr.page_no',
                        'dr.serial_no as reg_serial_no',
                        'dr.deeds_date as reg_date'
                    );

                if ($instrumentType) {
                    if ($instrumentType === 'Occupancy Permit (OP)') {
                        $query->where('ic.instrument_type', 'Occupancy Permit (OP)');
                    } elseif (str_starts_with($instrumentType, 'OP ')) {
                        $query->where('ic.instrument_type', 'Occupancy Permit (OP)')
                            ->where(function ($q) use ($instrumentType) {
                                $q->where('ic.op_type', $instrumentType)
                                    ->orWhere('ic.op_type', str_replace('OP ', '', $instrumentType));
                            });
                    } else {
                        $query->where('ic.instrument_type', $instrumentType);
                    }
                }

                if ($volumeNo) {
                    $query->where('dr.volume_no', $volumeNo);
                }

                if ($startDate) {
                    $query->whereRaw("CAST(COALESCE(dr.deeds_date, ic.reg_date, ic.created_at) AS DATE) >= ?", [$startDate]);
                }

                if ($endDate) {
                    $query->whereRaw("CAST(COALESCE(dr.deeds_date, ic.reg_date, ic.created_at) AS DATE) <= ?", [$endDate]);
                }

                $icResults = $query
                    ->orderBy('ic.instrument_type', 'asc')
                    ->orderByRaw("CASE WHEN dr.volume_no IS NULL THEN 1 ELSE 0 END")
                    ->orderByRaw("TRY_CONVERT(INT, dr.volume_no)")
                    ->orderByRaw("CASE WHEN dr.serial_no IS NULL THEN 1 ELSE 0 END")
                    ->orderByRaw("TRY_CONVERT(INT, dr.serial_no)")
                    ->orderBy('ic.created_at', 'desc')
                    ->get();
            }

            // Fetch ST instruments from deed_registrations (no IC counterpart)
            $stResults = collect();
            if (!$instrumentType || $isSTType) {
                $stQuery = DB::connection('sqlsrv')->table('deed_registrations as dr')
                    ->where('dr.status', 'registered')
                    ->whereRaw("(dr.instrument_type LIKE 'ST %' OR dr.instrument_type = 'Sectional Titling CofO')");

                if ($isSTType) {
                    $stQuery->where('dr.instrument_type', $instrumentType);
                }

                if ($volumeNo) {
                    $stQuery->where('dr.volume_no', $volumeNo);
                }

                if ($startDate) {
                    $stQuery->whereRaw("CAST(dr.deeds_date AS DATE) >= ?", [$startDate]);
                }

                if ($endDate) {
                    $stQuery->whereRaw("CAST(dr.deeds_date AS DATE) <= ?", [$endDate]);
                }

                $stResults = $stQuery
                    ->orderBy('dr.instrument_type', 'asc')
                    ->orderByRaw("CASE WHEN dr.volume_no IS NULL THEN 1 ELSE 0 END")
                    ->orderByRaw("TRY_CONVERT(INT, dr.volume_no)")
                    ->orderByRaw("CASE WHEN dr.serial_no IS NULL THEN 1 ELSE 0 END")
                    ->orderByRaw("TRY_CONVERT(INT, dr.serial_no)")
                    ->get();
            }

            // Map IC results
            $icMapped = $icResults->map(function ($item) {
                $deedDate = $item->reg_date ?? $item->deeds_date ?? $item->created_at;
                $location = $item->property_location ?? $item->location ?? $item->property_description ?? null;

                return [
                    'fileno' => $item->mlsFNo ?: $item->kangisFileNo ?: $item->NewKANGISFileno ?: $item->temp_fileno,
                    'serialNo' => $item->reg_serial_no ?? 'N/A',
                    'pageNo' => $item->page_no ?? 'N/A',
                    'volumeNo' => $item->volume_no ?? 'N/A',
                    'reg_particulars' => ($item->reg_serial_no || $item->page_no || $item->volume_no)
                        ? (($item->reg_serial_no ?? '0') . '/' . ($item->page_no ?? '0') . '/' . ($item->volume_no ?? '0'))
                        : '-',
                    'party_1' => $item->party_1_name,
                    'party_2' => $item->party_2_name,
                    'party_3' => $item->party_3_name,
                    'instrument_type' => $item->instrument_type,
                    'deed_time' => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('g:i A') : 'N/A',
                    'deed_date' => $deedDate ? \Carbon\Carbon::parse($deedDate)->format('d/m/Y') : 'N/A',
                    'op_serial' => $item->op_serial_number ?? 'N/A',
                    'location' => $location ?? 'N/A',
                    '_sort_type' => $item->instrument_type ?? '',
                    '_sort_vol' => $item->volume_no,
                    '_sort_serial' => $item->reg_serial_no,
                ];
            });

            // Map ST (DR) results
            $stMapped = $stResults->map(function ($item) {
                $location = trim(
                    ($item->lga ? $item->lga . ', ' : '') .
                    ($item->district ? $item->district . ', ' : '') .
                    ($item->plot_number ? 'Plot ' . $item->plot_number . ', ' : '') .
                    ($item->property_description ?? '')
                );

                return [
                    'fileno' => $item->fileno,
                    'serialNo' => $item->serial_no ?? 'N/A',
                    'pageNo' => $item->page_no ?? 'N/A',
                    'volumeNo' => $item->volume_no ?? 'N/A',
                    'reg_particulars' => ($item->serial_no ?? '0') . '/' . ($item->page_no ?? '0') . '/' . ($item->volume_no ?? '0'),
                    'party_1' => $item->grantor,
                    'party_2' => $item->grantee,
                    'party_3' => 'N/A',
                    'instrument_type' => $item->instrument_type,
                    'deed_time' => $item->deeds_time ?? 'N/A',
                    'deed_date' => $item->deeds_date ? \Carbon\Carbon::parse($item->deeds_date)->format('d/m/Y') : 'N/A',
                    'op_serial' => 'N/A',
                    'location' => $location ?: 'N/A',
                    '_sort_type' => $item->instrument_type ?? '',
                    '_sort_vol' => $item->volume_no,
                    '_sort_serial' => $item->serial_no,
                ];
            });

            // Merge and sort by type → volume → serial
            $merged = $icMapped->concat($stMapped)->sortBy([
                ['_sort_type', 'asc'],
                ['_sort_vol', 'asc'],
                ['_sort_serial', 'asc'],
            ])->values();

            $data = $merged->map(function ($item, $index) {
                unset($item['_sort_type'], $item['_sort_vol'], $item['_sort_serial']);
                $item['SN'] = $index + 1;
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve duplicate temp_fileno + prop_id collisions across OP source rows.
     *
     * When the user selects an OP from the lookup card and other rows in
     * instrument_capture / deed_registrations share the SAME temp_fileno AND
     * prop_id, keep the selected row's values intact and reassign each
     * duplicate sibling a fresh temp_fileno (canonical sequence) and a fresh
     * prop_id (PropertyIdAllocationService) so downstream lookups stop being
     * ambiguous.
     */
    public function resolveOpDuplicates(Request $request)
    {
        $validated = $request->validate([
            'selected_source_op_id' => 'required|integer|min:1',
            'selected_source_op_table' => 'required|in:instrument_capture,deed_registrations',
        ]);

        $selectedId = (int) $validated['selected_source_op_id'];
        $selectedTable = $validated['selected_source_op_table'];
        $selectedCol = $selectedTable === 'deed_registrations' ? 'fileno' : 'temp_fileno';

        try {
            $selected = DB::connection('sqlsrv')
                ->table($selectedTable)
                ->where('id', $selectedId)
                ->first([DB::raw($selectedCol . ' as temp_value'), 'prop_id']);

            if (!$selected) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected source row not found.',
                ], 404);
            }

            $tempValue = $selected->temp_value ?? null;
            $propId = $selected->prop_id ?? null;

            if (!$tempValue || !$propId) {
                return response()->json([
                    'success' => true,
                    'reassigned' => [],
                    'message' => 'Selected row has no temp_fileno + prop_id pair to deduplicate.',
                ]);
            }

            $tempValueNorm = strtoupper(trim((string) $tempValue));
            if (!preg_match('/^TEMP-/i', $tempValueNorm)) {
                return response()->json([
                    'success' => true,
                    'reassigned' => [],
                    'message' => 'Selected row already uses an official file number; no reassignment performed.',
                ]);
            }

            $propIdAllocator = new \App\Services\PropertyIdAllocationService();
            $reassigned = [];

            $tablesAndCols = [
                'instrument_capture' => 'temp_fileno',
                'deed_registrations' => 'fileno',
            ];

            DB::connection('sqlsrv')->transaction(function () use ($tablesAndCols, $selectedTable, $selectedId, $tempValueNorm, $propId, $propIdAllocator, &$reassigned) {
                foreach ($tablesAndCols as $table => $col) {
                    if (
                        !Schema::connection('sqlsrv')->hasTable($table)
                        || !Schema::connection('sqlsrv')->hasColumn($table, $col)
                        || !Schema::connection('sqlsrv')->hasColumn($table, 'prop_id')
                    ) {
                        continue;
                    }

                    $query = DB::connection('sqlsrv')
                        ->table($table)
                        ->whereRaw('UPPER(LTRIM(RTRIM(' . $col . '))) = ?', [$tempValueNorm])
                        ->where('prop_id', $propId);

                    if ($table === $selectedTable) {
                        $query->where('id', '!=', $selectedId);
                    }

                    if (Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
                        $query->where(function ($q) {
                            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                        });
                    }

                    $duplicates = $query->get(['id', DB::raw($col . ' as temp_value'), 'prop_id']);

                    foreach ($duplicates as $dup) {
                        $seqId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                            'created_by' => Auth::id(),
                            'is_used' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $newTempFileno = 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT);

                        $newPropId = $propIdAllocator->allocateOrRetrievePropId(
                            $newTempFileno,
                            null,
                            null,
                            null,
                            [
                                'temp_fileno' => $newTempFileno,
                                'allow_temp_only' => true,
                                'skip_lookup' => true,
                            ]
                        );

                        $update = [
                            $col => $newTempFileno,
                            'prop_id' => $newPropId,
                        ];
                        if (Schema::connection('sqlsrv')->hasColumn($table, 'updated_at')) {
                            $update['updated_at'] = now();
                        }

                        DB::connection('sqlsrv')->table($table)
                            ->where('id', $dup->id)
                            ->update($update);

                        $reassigned[] = [
                            'table' => $table,
                            'id' => $dup->id,
                            'old_temp_fileno' => $dup->temp_value,
                            'old_prop_id' => $dup->prop_id,
                            'new_temp_fileno' => $newTempFileno,
                            'new_prop_id' => $newPropId,
                        ];
                    }
                }
            });

            Log::info('OP duplicate resolver completed', [
                'user_id' => Auth::id(),
                'selected_table' => $selectedTable,
                'selected_id' => $selectedId,
                'kept_temp_fileno' => $tempValueNorm,
                'kept_prop_id' => $propId,
                'reassigned_count' => count($reassigned),
                'reassigned' => $reassigned,
            ]);

            return response()->json([
                'success' => true,
                'kept' => [
                    'table' => $selectedTable,
                    'id' => $selectedId,
                    'temp_fileno' => $tempValueNorm,
                    'prop_id' => $propId,
                ],
                'reassigned' => $reassigned,
            ]);
        } catch (\Throwable $e) {
            Log::error('resolveOpDuplicates failed: ' . $e->getMessage(), [
                'selected_id' => $selectedId,
                'selected_table' => $selectedTable,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve duplicates: ' . $e->getMessage(),
            ], 500);
        }
    }
}
