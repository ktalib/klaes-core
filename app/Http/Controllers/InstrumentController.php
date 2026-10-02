<?php

namespace App\Http\Controllers;

use App\Rules\NigerianPhone;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\InstrumentCaptureService;
use App\Services\StAssignmentConsentResolver;
use App\Models\Gender;
use App\Models\StreetName;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;

class InstrumentController extends Controller
{
    public function __construct(
        protected InstrumentCaptureService $captureService,
        protected \App\Services\TimelineWeightingService $timelineService
    ) {
    }

    public function index(Request $request)
    {
        $PageTitle = 'Instrument Capture';
        $PageDescription = '';

        // Base statistics query (independent)
        //
        // Land Registration captures are excluded throughout this screen. They
        // live in the same table, separated only by instrument type, and belong
        // to the Land Registry's own register - not to Digitization.
        $landType = config('land_registration.instrument_type');
        $typeFilter = trim((string) $request->query('instrument_type', ''));
        $volumeFilter = trim((string) $request->query('volume', ''));

        $statsBase = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('instrument_type', '<>', $landType)
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
            ->where('ic.instrument_type', '<>', $landType)
            ->when($typeFilter !== '', function ($query) use ($typeFilter) {
                // OP sub-types are presented as "OP ..." in the selector but
                // stored in op_type; every other choice is instrument_type.
                if (str_starts_with($typeFilter, 'OP ')) {
                    $query->where('ic.op_type', substr($typeFilter, 3));
                } else {
                    $query->where('ic.instrument_type', $typeFilter);
                }
            })
            ->when($volumeFilter !== '', fn ($query) => $query->where('dr.volume_no', $volumeFilter))
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
            ->where('instrument_type', '<>', $landType)
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

        // Timeline counts for the Timeline column, for this page's rows only.
        // Asked one row at a time this costs ~0.33s per row (getRawRecords runs four
        // queries per property); asked for the page at once it is ~0.45s in total.
        $timelineCounts = $this->timelineService->getWeightedCountsForMany(
            collect($instruments->items())->mapWithKeys(function ($row) {
                return [$row->id => [
                    'fileNumber' => $row->mlsFNo ?: ($row->kangisFileNo ?: ($row->NewKANGISFileno ?: $row->temp_fileno)),
                    'propId' => (string) ($row->prop_id ?? ''),
                ]];
            })->all()
        );

        // Feeds the "Update Solicitors Details" modal, which mirrors the capture
        // card's Solicitor section. Districts are handed to the browser as JSON and
        // the select is built the first time the modal opens - there are ~1,800 of
        // them and this listing has no other reason to carry that many option nodes.
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $solicitorDistricts = $this->getDistrictsForSelect()->pluck('name')->values();

        return view('instruments.index', compact('PageTitle', 'PageDescription', 'instruments', 'totalCount', 'pendingCount', 'verifiedCount', 'todayCount', 'fullDataForJs', 'instrumentTypes', 'states', 'solicitorDistricts', 'timelineCounts', 'typeFilter', 'volumeFilter'));
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

    /**
     * Everything one instrument capture does goes to its own channel
     * (storage/logs/instrument_capture-Y-m-d.log). A failed capture reaches the
     * officer as a single modal with no detail, and hunting the reason among every
     * other request in laravel.log is not something the person who hit the error
     * can do. Errors are additionally mirrored to the default channel so existing
     * monitoring still sees them.
     */
    private function captureLog(): \Psr\Log\LoggerInterface
    {
        return Log::stack(['instrument_capture', config('logging.default')]);
    }

    /**
     * Short, quotable id for one capture attempt: it is stamped on every log line
     * for the request and shown in the failure message, so an officer can report
     * "CAP-260818-143902-K7QP" and we can find the exact trace.
     */
    private function newCaptureRef(): string
    {
        return 'CAP-' . now()->format('ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
    }

    /**
     * The first frames of a trace, flattened to file:line function - enough to
     * place the failure without dumping the whole framework stack into the file.
     */
    private function shortTrace(\Throwable $e, int $frames = 10): array
    {
        return collect($e->getTrace())
            ->take($frames)
            ->map(fn ($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['function'] ?? ''))
            ->all();
    }

    /**
     * PHP can refuse to parse a multipart request body outright, before any of this
     * controller runs. When it does, $_POST and $_FILES arrive EMPTY and the capture
     * fails validation on whatever field is checked first - the officer is told "The
     * instrument type field is required" about a form they filled in completely, and
     * nothing in that message points at the server.
     *
     * The usual cause is a php.ini limit that has overflowed to a negative number
     * (max_multipart_body_parts, or the max_input_vars it defaults to), after which
     * the very first body part already "exceeds" the limit and PHP drops the whole
     * body with:
     *
     *   PHP Request Startup: Multipart body parts limit exceeded -1947483649.
     *   To increase the limit change max_multipart_body_parts in php.ini.
     *
     * A body PHP would not parse is indistinguishable from an empty form at this
     * level, so the check is: a multipart POST that carried bytes, of which nothing
     * survived. That cannot be a real submission - the capture form always posts
     * _token at minimum.
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

        // Report the two limits back so whoever reads the log or the screen has the
        // actual numbers, not a general instruction to "check php.ini".
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

    public function store(Request $request)
    {
        // One reference per capture attempt, stamped on every line this request
        // writes and echoed back in any error shown to the officer. It is what
        // turns "it failed on my screen" into a line we can find in the log.
        $ref = $this->newCaptureRef();

        $this->captureLog()->info('Capture submitted', [
            'ref' => $ref,
            'user_id' => Auth::id(),
            'instrument_type' => $request->input('instrument_type'),
            'fileno' => $request->input('fileno') ?: $request->input('temp_fileno'),
            'payload' => Arr::except($request->all(), ['_token', '_method']),
        ]);

        // Checked BEFORE validation: with an empty $_POST every rule fails, and the
        // "field is required" list that comes back describes a form the officer did
        // fill in. See detectDroppedRequestBody().
        [$bodyDropped, $bodyDropReason] = $this->detectDroppedRequestBody($request);
        if ($bodyDropped) {
            $this->captureLog()->error('Capture aborted: request body dropped by PHP', [
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
            // Basic Validation
            $instrumentType = $request->input('instrument_type');
            $rules = [
                'instrument_type' => 'required|string',
                'temp_fileno' => 'nullable|string|max:255',
                'fileno' => 'nullable|string|max:255',
                // Party 2's gender must be chosen, not left to default. A blank is
                // indistinguishable from the legacy rows that predate the column,
                // and those are exactly what the gender reports cannot count.
                // Must be a NAME from the `genders` lookup, never an id.
                'secondPartyGender' => ['required', Rule::in(Gender::options())],
                // Party 2 is the grantee — the party the registration SMS is sent
                // to (see InstrumentRegistrationController::deedPartyPhone), so a
                // reachable number is required at capture. Party 1 is optional:
                // a grantor is often an estate or a government body with no
                // mobile, but a number given must still be well formed.
                'secondPartyPhone' => ['required', 'string', new NigerianPhone()],
                'firstPartyPhone' => ['nullable', 'string', new NigerianPhone()],
            ];

            if (stripos($instrumentType, 'Occupancy Permit') !== false) {
                $rules['op_serial_number'] = ['required', 'regex:/^[1-9][0-9]*$/'];
            }

            $isAssignmentOrGift = stripos((string) $instrumentType, 'Deed of Assignment') !== false
                || stripos((string) $instrumentType, 'Deed of Gift') !== false;

            if ($isAssignmentOrGift) {
                $includeSolicitor = filter_var($request->input('include_solicitor', false), FILTER_VALIDATE_BOOLEAN);
                $hasAnySolicitorField = $request->filled('solicitorName')
                    || $request->filled('solicitorPhone')
                    || $request->filled('solicitorAddress')
                    || $request->filled('solicitorDistrict')
                    || $request->filled('solicitorState')
                    || $request->filled('solicitorLga');

                if ($includeSolicitor || $hasAnySolicitorField) {
                    $rules['solicitorName'] = 'required|string|max:255';
                    $rules['solicitorPhone'] = ['required', 'string', new NigerianPhone()];
                    $rules['solicitorAddress'] = 'required|string|max:1000';
                    $rules['solicitorState'] = 'required|string|max:255';
                    $rules['solicitorLga'] = 'required|string|max:255';
                }
            }

            $messages = [
                'secondPartyPhone.required' => 'Party 2 phone number is required — the registration notice is sent to it.',
                'secondPartyGender.required' => 'Party 2 gender is required — please select one.',
                'secondPartyGender.in' => 'Party 2 gender must be one of: ' . implode(', ', Gender::options()) . '.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $this->captureLog()->warning('Capture rejected by validation', [
                    'ref' => $ref,
                    'errors' => $validator->errors()->toArray(),
                ]);
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

            // Consent comes before Registration for the instruments a consent
            // authorises. Enforced on the server, not only in the capture
            // screen: that screen's gate is a feature flag that has been turned
            // off before, and a post that never went through the screen would
            // bypass it altogether.
            if ($consentBlock = $this->consentGateFailure($request, $instrumentType)) {
                $this->captureLog()->warning('Capture blocked: consent stage not satisfied', [
                    'ref' => $ref,
                    'instrument_type' => $instrumentType,
                    'fileno' => $request->input('temp_fileno') ?: $request->input('fileno'),
                    'user_id' => Auth::id(),
                ]);

                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $consentBlock], 422);
                }

                return redirect()->back()->with('error', $consentBlock)->withInput();
            }

            // --- Robust Duplicate Check (5 Parameters) ---
            $checkType = (stripos($instrumentType, 'Occupancy Permit') !== false) ? 'op' : 'instrument';

            // The party/date keys below must be read from the names the capture form
            // actually posts. They used to be read as party_1_name/party_2_name/
            // instrument_date - fields the form has never sent - so all three arrived
            // null and the "5 parameter" check collapsed into file number + instrument
            // type. That matches EVERY later dealing on a file: a second assignment on
            // an already-assigned file was rejected as a duplicate of the first one,
            // no matter that the parties and the date were different.
            $dupParams = [
                'fileno' => $request->input('temp_fileno') ?? $request->input('fileno'),
                'prop_id' => $request->input('prop_id'),
                'instrument_type' => $instrumentType,
                'op_serial' => $request->input('op_serial_number'),
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

            // An officer who picked a fresh consent for this file, or chose "Create
            // New" on the duplicate warning, has already looked at the existing record
            // and decided this is a further dealing. Their decision stands - but it is
            // recorded, with what it collided with. Never available for an Occupancy
            // Permit: a repeated OP serial is a numbering clash, not a new dealing.
            $allowDuplicate = $checkType !== 'op'
                && filter_var($request->input('allow_duplicate', false), FILTER_VALIDATE_BOOLEAN);

            if ($duplicateFound = check_duplicate($checkType, $dupParams)) {
                if ($allowDuplicate) {
                    $this->captureLog()->notice('Duplicate overridden by officer', [
                        'ref' => $ref,
                        'params' => $dupParams,
                        'existing_id' => $duplicateFound->id ?? null,
                        'existing_reg_no' => $duplicateFound->registration_number ?? null,
                        'consent_application_id' => $request->input('consent_application_id'),
                        'user_id' => Auth::id(),
                    ]);
                } else {
                    $errorMsg = "A similar " . ($checkType === 'op' ? "Occupancy Permit" : "Instrument") . " already exists for this property or serial number.";
                    $this->captureLog()->warning('Capture blocked as duplicate', [
                        'ref' => $ref,
                        'params' => $dupParams,
                        'existing_id' => $duplicateFound->id ?? null,
                    ]);
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg, 'duplicate' => $duplicateFound], 422);
                    }
                    return redirect()->back()->with('error', $errorMsg)->withInput();
                }
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
                $this->captureLog()->info('Capture succeeded', [
                    'ref' => $ref,
                    'instrument_capture_id' => $result['id'] ?? null,
                    'deed_registration_id' => $result['deed_registration_id'] ?? null,
                    'registration_number' => $result['reg_number'] ?? null,
                    'sync_result' => $result['sync_result'] ?? null,
                ]);
                if ($request->expectsJson()) {
                    return response()->json(array_merge(['success' => true, 'message' => $message], $result));
                }
                return redirect()->route('instruments.index')->with('success', $message);
            }

            $this->captureLog()->error('Capture returned failure without an exception', ['ref' => $ref]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to register instrument.'], 500);
            }
            return redirect()->back()->with('error', 'Failed to register instrument')->withInput();

        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a TypeError/ValueError raised deeper in the
            // capture chain is an \Error, so an \Exception-only catch let it escape as
            // an HTML 500 that the capture form can only report as "invalid response
            // format from the server" - no message, no clue, nothing in the UI.
            $this->captureLog()->error('Capture failed: ' . $e->getMessage(), [
                'ref' => $ref,
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'instrument_type' => $request->input('instrument_type'),
                'fileno' => $request->input('fileno') ?: $request->input('temp_fileno'),
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
     * API: Check for duplicate instrument
     */
    /**
     * Why this capture may not proceed, or null when it may.
     *
     * Applies the Consent-before-Registration gate to the instruments a consent
     * authorises (assignment, gift, mortgage). Anything else — an Occupancy
     * Permit, a surrender, a caveat — is not gated and returns null.
     *
     * Only 'block' stops a capture. In 'warn' or 'off' the capture is allowed;
     * the workflow strip is what reports the stage as taken out of order.
     */
    private function consentGateFailure(Request $request, ?string $instrumentType): ?string
    {
        $pipeline = app(\App\Services\DeedsPipelineStatus::class);

        $mustHaveConsent = $pipeline->gateMode('consent_before_registration') === 'block';
        $mustHavePrintedConsent = $pipeline->gateMode('consent_print_before_registration') === 'block';

        if (!$mustHaveConsent && !$mustHavePrintedConsent) {
            return null;
        }

        $consentType = $pipeline->consentTypeFor($instrumentType);

        if ($consentType === null) {
            return null;
        }

        $fileNo = trim((string) ($request->input('temp_fileno') ?: $request->input('fileno')));

        // No file number yet is a different failure, caught by validation. This
        // gate must not turn that into a confusing consent error.
        if ($fileNo === '') {
            return null;
        }

        $stages = $pipeline->forFile($fileNo, $consentType);
        $consentStage = $stages[\App\Services\DeedsPipelineStatus::STAGE_CONSENT];

        if (!$consentStage['done'] && $mustHaveConsent) {
            // A sectional file carries no consent_applications row at all: its
            // consent is the approved memo on the mother application, which is what
            // /deeds-applications lists as an "ST Assignment". It is already
            // approved and has no separately printable consent letter.
            if (in_array($consentType, ['Assignment', 'Gift'], true)) {
                $stConsent = (new \App\Services\StAssignmentConsentResolver())
                    ->forFileNumber($fileNo)
                    ->first(fn($consent) => !$consent->is_used);

                if ($stConsent) {
                    return null;
                }
            }

            return "A {$consentType} consent must be captured for file {$fileNo} before this instrument can be registered. "
                . 'Create it from the Consent Applications screen first.';
        }

        if (!$consentStage['done']) {
            return "A {$consentType} consent must be captured for file {$fileNo} before it can be printed and this instrument registered.";
        }

        $printStage = $stages[\App\Services\DeedsPipelineStatus::STAGE_PRINT];
        if ($mustHavePrintedConsent && !$printStage['done']) {
            return "The {$consentType} consent letter for file {$fileNo} must be printed before this instrument can be registered.";
        }

        return null;
    }

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
        $deedHistoryQuery = DB::connection('sqlsrv')
            ->table('deed_registrations as dr')
            ->leftJoin('instrument_capture as ic', 'dr.instrument_capture_id', '=', 'ic.id')
            ->select([
                'dr.*',
                'ic.party_1_address',
                'ic.party_2_address',
            ]);
        $deedHistoryQuery->where('dr.fileno', $fileno);

        $deedHistory = $deedHistoryQuery->orderBy('dr.created_at', 'desc')->get()->map(function ($item) {
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

        // 4. Retrieve the file's Consent Applications for Auto-fill
        // Map instrument type to consent_type for accurate party matching
        $consentTypeMap = [
            'Deed of Assignment' => 'Assignment',
            'Deed of Gift' => 'Gift',
            'Deed of Mortgage' => 'Mortgage',
            'Tripartite Mortgage' => 'Mortgage',
        ];

        // The reverse direction: which captured instrument types consume a consent
        // of each type. Used to work out whether a consent has been spent already.
        $consentInstrumentMap = [
            'Assignment' => ['Deed of Assignment'],
            'Gift' => ['Deed of Gift'],
            'Mortgage' => ['Deed of Mortgage', 'Tripartite Mortgage'],
            // "ST Assignment" and "ST Assignment (Transfer of Title)" are the same
            // thing; canonicalType() folds the long form onto the short one.
            StAssignmentConsentResolver::CONSENT_TYPE => ['Deed of Assignment', 'ST Assignment (Transfer of Title)'],
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

        // Every consent on the file, newest first. The duplicate-registration
        // warning lists them all so the user can see what is already spent and
        // pick the one this capture is actually for.
        $consentApps = (clone $consentQuery)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // A sectional file has no row in consent_applications — its consent is the
        // approved memo that /deeds-applications shows as an "ST Assignment". Resolve
        // it here so a LATER assignment of an ST unit can be captured on this form
        // instead of being told the file has no consent. Matches on either the unit's
        // own ST file number or the mother application's file number.
        $stConsents = (new StAssignmentConsentResolver())->forFileNumber($fileno);
        if ($stConsents->isNotEmpty()) {
            $consentApps = $consentApps->concat($stConsents)
                ->sortByDesc(fn ($c) => (string) ($c->created_at ?? ''))
                ->values();
        }

        // A sectional unit's dealings have to be registered in order. Until the
        // FIRST ST Assignment is registered (from /instrument_registration), no
        // consent on the file may be captured against — not the memo, and not a
        // later consent application either, because that would record the second
        // transfer before the first one exists. So the whole picker is locked,
        // rather than just greying the memo out.
        $stPendingConsent = $stConsents->first(fn ($consent) => !$consent->is_used);
        $consentLock = $stPendingConsent ? [
            'locked'           => true,
            'reason'           => 'first_st_assignment_not_registered',
            'unit_file_number' => $stPendingConsent->unit_file_number,
            'mother_file_no'   => $stPendingConsent->file_number,
            'memo_no'          => $stPendingConsent->application_tracking_no,
            'message'          => 'The first ST Assignment for this unit has not been registered yet. '
                . 'A consent on this file cannot be used to register a deed until that first assignment '
                . 'is registered under Instrument Registration.',
        ] : null;

        // 5. Instrument captures on this file — the prior record used for auto-fill
        //    fallback, and the basis for deciding which consents are already spent.
        $captureFileMatch = function ($q) use ($fileno, $normalizedFileno) {
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
        };

        $notDeleted = function ($q) {
            $q->where('is_deleted', 0)
                ->orWhereNull('is_deleted');
        };

        $fileCaptures = $consentApps->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')->table('instrument_capture')
                ->where($captureFileMatch)
                ->where($notDeleted)
                ->orderBy('created_at', 'desc')
                ->get();

        $this->annotateConsentUsage($consentApps, $fileCaptures, $consentInstrumentMap, $type, $consentTypeMap);

        // Default auto-fill consent: the most recent one whose type suits the
        // instrument being captured AND that has not been used yet. A spent
        // consent is only offered when there is nothing fresher, so the common
        // "second assignment on the same file" case pre-fills from the new
        // consent rather than the one already registered.
        $pickConsent = function (array $types, bool $unusedOnly) use ($consentApps) {
            foreach ($consentApps as $candidate) {
                if (!in_array(StAssignmentConsentResolver::canonicalType($candidate->consent_type), $types, true)) {
                    continue;
                }
                if ($unusedOnly && $candidate->is_used) {
                    continue;
                }
                return $candidate;
            }
            return null;
        };

        $consentApp = null;
        if ($type && isset($consentTypeMap[$type])) {
            $requestedType = $consentTypeMap[$type];
            // Assignment and Gift are interchangeable enough to fall back on each
            // other; a Mortgage consent never stands in for either.
            // An ST Assignment consent backs a Deed of Assignment (and a Gift, the
            // same way a plain Assignment consent does), but only as a fallback —
            // a real Assignment consent on the file still wins.
            $consentGroups = [
                'Assignment' => ['Assignment', 'Gift', StAssignmentConsentResolver::CONSENT_TYPE],
                'Gift' => ['Assignment', 'Gift', StAssignmentConsentResolver::CONSENT_TYPE],
                'Mortgage' => ['Mortgage'],
            ];
            $allowedFallbackTypes = $consentGroups[$requestedType] ?? [$requestedType];

            $consentApp = $pickConsent([$requestedType], true)
                ?: $pickConsent($allowedFallbackTypes, true)
                ?: $pickConsent([$requestedType], false)
                ?: $pickConsent($allowedFallbackTypes, false);
        }

        // Final fallback: If no type-specific or group-specific consent was found,
        // we generally do NOT want to auto-fill from a completely unrelated consent
        // (e.g. Mortgage consent should not auto-fill Assignment fields).
        // However, we can still allow auto-fill if the instrument type is NOT in the gated map
        // (like Power of Attorney) to provide basic property info.
        if (!$consentApp && (!$type || !isset($consentTypeMap[$type]))) {
            $consentApp = $consentApps->firstWhere('is_used', false) ?: $consentApps->first();
        }

        $priorQuery = DB::connection('sqlsrv')->table('instrument_capture')
            ->where($captureFileMatch)
            ->where($notDeleted);

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

        // The holder's number as the valuation recorded it. The consent normally
        // carries it forward already, but an instrument that is not consent-gated
        // (a Power of Attorney, say) never passes through one, and a file can be
        // valued before any consent exists. Sent as a last-resort fill only.
        $valuationPhone = null;
        if (Schema::connection('sqlsrv')->hasColumn('valuation_reports', 'phone')) {
            $valuationPhone = DB::connection('sqlsrv')->table('valuation_reports')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim((string) $fileno))])
                ->orderByDesc('id')
                ->value('phone');
            $valuationPhone = trim((string) $valuationPhone) ?: null;
        }

        return response()->json([
            'exists' => $instrument ? true : false,
            'instrument' => $instrument,
            'pra_history' => $combinedHistory,
            'pra' => $praHistory->first(),
            'consent_app' => $consentApp,
            'consent_apps' => $consentApps->values(),
            'consent_lock' => $consentLock,
            'prior_instrument' => $priorInstrument,
            'valuation_phone' => $valuationPhone,
        ]);
    }

    /**
     * Decide, for each consent on a file, whether an instrument has already been
     * registered against it — and stamp `is_used` / `used_by` / `matches_type`
     * onto the consent rows in place.
     *
     * Two ways a consent counts as used:
     *   1. A capture carries consent_application_id pointing at it. Exact, and
     *      the only signal for captures made since that column existed.
     *   2. Legacy captures (no link) are inferred: same file, an instrument type
     *      that consumes this consent's type, and the same second party. The
     *      grantee is what distinguishes two consents of the same type on one
     *      file, so it is the party that must match.
     *
     * A capture is attributed to at most one consent, so two consents of the
     * same type are never both greyed out by a single registration.
     */
    private function annotateConsentUsage(
        $consentApps,
        $fileCaptures,
        array $consentInstrumentMap,
        ?string $type,
        array $consentTypeMap
    ): void {
        $normalizeName = function ($value) {
            return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value)));
        };

        $claimedCaptureIds = [];

        $acceptedTypes = ($type && isset($consentTypeMap[$type]))
            ? [
                $consentTypeMap[$type],
                // A Deed of Assignment / Gift may be backed by the file's ST
                // Assignment memo, so that is on-type here rather than off-type.
                ...($consentTypeMap[$type] === 'Mortgage' ? [] : [StAssignmentConsentResolver::CONSENT_TYPE]),
            ]
            : null;

        foreach ($consentApps as $consent) {
            $consentType = StAssignmentConsentResolver::canonicalType($consent->consent_type);
            $consent->matches_type = $acceptedTypes === null || in_array($consentType, $acceptedTypes, true);

            // An ST memo is spent by the first registration off the approval, which
            // lives in deed_registrations — not in instrument_capture. The resolver
            // has already worked that out, so keep its verdict and skip the pass
            // below: the memo is not a consent_applications row, it can never be
            // linked by id, and (int) 'memo_7' is 0 — which would otherwise match
            // every capture whose consent_application_id is NULL.
            if (!empty($consent->is_synthetic)) {
                continue;
            }

            $usedBy = null;

            foreach ($fileCaptures as $capture) {
                if (isset($claimedCaptureIds[$capture->id])) {
                    continue;
                }
                if ((int) ($capture->consent_application_id ?? 0) === (int) $consent->id) {
                    $usedBy = $capture;
                    break;
                }
            }

            if (!$usedBy) {
                $consumingTypes = $consentInstrumentMap[$consentType] ?? [];
                $consentParty = $normalizeName($consent->party_name ?? '');

                foreach ($fileCaptures as $capture) {
                    if (isset($claimedCaptureIds[$capture->id])) {
                        continue;
                    }
                    // A linked capture already belongs to whichever consent it names.
                    if (!empty($capture->consent_application_id)) {
                        continue;
                    }
                    if (!in_array($capture->instrument_type, $consumingTypes, true)) {
                        continue;
                    }
                    if ($consentParty === '' || $normalizeName($capture->party_2_name ?? '') !== $consentParty) {
                        continue;
                    }

                    $usedBy = $capture;
                    break;
                }
            }

            if ($usedBy) {
                $claimedCaptureIds[$usedBy->id] = true;
            }

            $consent->is_used = (bool) $usedBy;
            $consent->used_by = $usedBy ? [
                'id' => $usedBy->id,
                'instrument_type' => $usedBy->instrument_type,
                'registration_number' => $usedBy->registration_number ?? $usedBy->reg_no ?? null,
                'reg_date' => $usedBy->reg_date ?? null,
                'captured_at' => $usedBy->created_at ?? null,
                'party_2_name' => $usedBy->party_2_name ?? null,
                'is_linked' => !empty($usedBy->consent_application_id),
            ] : null;
        }
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
                'party_2_gender',
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

    /**
     * The next registration particulars the instrument about to be captured would
     * take — read-only, nothing is consumed.
     *
     * Shown on the capture card ("You are about to register ... 19/19/68") so the
     * officer knows the number before committing. OP passes its sub-type, which is
     * what selects the vault (OP Resettlement and OP Direct Allocation paginate
     * separately), and the CofO variants each have their own vault too.
     */
    public function nextRegistrationParticulars(Request $request): JsonResponse
    {
        $instrumentType = trim((string) $request->query('instrument_type', ''));
        $opType = trim((string) $request->query('op_type', ''));

        if ($instrumentType === '') {
            return response()->json(['success' => false, 'message' => 'instrument_type is required.'], 422);
        }

        try {
            $preview = app(\App\Services\InstrumentRegistrationService::class)
                ->peekRegistrationNumber($instrumentType, $opType !== '' ? $opType : null);

            return response()->json(array_merge(['success' => true], $preview));
        } catch (\Throwable $e) {
            // Never block the capture screen over a preview.
            Log::warning('Could not preview registration particulars', [
                'instrument_type' => $instrumentType,
                'op_type' => $opType,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * The registration numbers this register skipped — read-only.
     *
     * Feeds the "Fill a missing registration number" picker on the capture
     * screen, which exists because a registration deleted outright leaves a
     * serial in the paper book that nothing in KLAES accounts for. Recapturing
     * it normally would burn the NEXT number and make the hole permanent.
     *
     * Scoped exactly as allocation is: by the vault the instrument type resolves
     * to, with op_type separating the two Occupancy Permit registers.
     */
    public function missingRegistrationParticulars(Request $request): JsonResponse
    {
        $instrumentType = trim((string) $request->query('instrument_type', ''));
        $opType = trim((string) $request->query('op_type', ''));

        if ($instrumentType === '') {
            return response()->json(['success' => false, 'message' => 'instrument_type is required.'], 422);
        }

        try {
            $report = app(\App\Services\MissingRegistrationParticularsService::class)
                ->report($instrumentType, $opType !== '' ? $opType : null);

            return response()->json(array_merge(['success' => true], $report));
        } catch (\Throwable $e) {
            // Never block the capture screen over an optional picker: the officer
            // can still register on the next number in sequence.
            Log::warning('Could not list missing registration particulars', [
                'instrument_type' => $instrumentType,
                'op_type' => $opType,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
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
        $ref = $this->newCaptureRef();

        $this->captureLog()->info('Update submitted', [
            'ref' => $ref,
            'instrument_capture_id' => $id,
            'user_id' => Auth::id(),
            'payload' => Arr::except($request->all(), ['_token', '_method']),
        ]);

        // Same guard as store(): an update whose body PHP dropped would otherwise
        // overwrite the record with nothing but nulls.
        [$bodyDropped, $bodyDropReason] = $this->detectDroppedRequestBody($request);
        if ($bodyDropped) {
            $this->captureLog()->error('Update aborted: request body dropped by PHP', [
                'ref' => $ref,
                'instrument_capture_id' => $id,
                'content_length' => $request->server('CONTENT_LENGTH'),
                'max_multipart_body_parts' => ini_get('max_multipart_body_parts'),
                'max_input_vars' => ini_get('max_input_vars'),
            ]);

            $message = $bodyDropReason . ' The record was left unchanged. Reference: ' . $ref;

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
            // Validate if necessary?
            // Service handles most logic, but basic presence checks might be good?
            // We'll pass everything to service.

            $result = $this->captureService->update($id, $request->all());

            if ($result['success']) {
                $message = 'Instrument updated successfully. Ref: ' . ($result['reg_number'] ?? '');
                $this->captureLog()->info('Update succeeded', [
                    'ref' => $ref,
                    'instrument_capture_id' => $id,
                    'sync_result' => $result['sync_result'] ?? null,
                ]);

                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => $message]);
                }

                return redirect()->route('instruments.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update instrument.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update instrument')->withInput();

        } catch (\Throwable $e) {
            $this->captureLog()->error('Update failed: ' . $e->getMessage(), [
                'ref' => $ref,
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'instrument_capture_id' => $id,
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
     * Solicitor details for one captured instrument.
     *
     * The listing already ships every row to the browser in window.serverCofoData,
     * but that copy is a page-load snapshot: after the first edit it is stale for
     * every later edit made without a refresh, so the modal reads the current
     * values back from the row it is about to write.
     *
     * State and LGA have no columns of their own - capture folds them into the
     * composed office address - so they are recovered from that string by matching
     * its comma-separated parts against the States and LGA tables. A legacy address
     * that nothing matches comes back with both blank, and the modal then leaves
     * the stored text alone unless the operator changes a location field.
     */
    public function solicitor($id)
    {
        $record = DB::connection('sqlsrv')->table('instrument_capture')
            ->select('id', 'instrument_type', 'solicitor_name', 'solicitor_phone', 'solicitor_district', 'solicitor_address')
            ->where('id', $id)
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Instrument not found.'], 404);
        }

        if ($this->isOccupancyPermit($record->instrument_type)) {
            return response()->json([
                'success' => false,
                'message' => 'Occupancy Permits do not carry solicitor details.',
            ], 422);
        }

        [$state, $lga] = $this->resolveSolicitorStateAndLga($record->solicitor_address);

        return response()->json([
            'success' => true,
            'solicitor' => [
                'name' => $record->solicitor_name,
                'phone' => $record->solicitor_phone,
                'district' => $record->solicitor_district,
                'state' => $state,
                'lga' => $lga,
                'address' => $record->solicitor_address,
            ],
        ]);
    }

    /**
     * An Occupancy Permit records an existing paper permit rather than a
     * registered instrument and carries no solicitor, so the listing withholds the
     * "Update Solicitors Details" action on those rows. Same test here, so the
     * endpoints refuse a hand-made request rather than trusting the hidden menu.
     */
    private function isOccupancyPermit(?string $instrumentType): bool
    {
        return stripos((string) $instrumentType, 'Occupancy Permit') !== false;
    }

    /**
     * Best-effort split of a composed office address back into [state, lga].
     *
     * The capture card joins district, LGA and state with ", ", so the parts are
     * matched against the reference tables rather than read positionally - older
     * rows were written by a space-joining composer and would otherwise line up
     * wrongly. No match means no guess: both come back null.
     */
    private function resolveSolicitorStateAndLga(?string $address): array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return [null, null];
        }

        $parts = collect(preg_split('/[,]+/', $address))
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return [null, null];
        }

        $states = DB::connection('sqlsrv')->table('States')->pluck('StateName', 'StateID');
        $state = null;
        $stateId = null;

        // Walk from the tail: the state is the last thing the composer appends.
        foreach ($parts->reverse() as $part) {
            $match = $states->search(fn ($name) => strcasecmp(trim((string) $name), $part) === 0);
            if ($match !== false) {
                $state = $states[$match];
                $stateId = $match;
                break;
            }
        }

        if ($stateId === null) {
            return [null, null];
        }

        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->where('StateID', $stateId)
            ->pluck('LGAName');

        $lga = null;
        foreach ($parts as $part) {
            if (strcasecmp($part, (string) $state) === 0) {
                continue;
            }
            $hit = $lgas->first(fn ($name) => strcasecmp(trim((string) $name), $part) === 0);
            if ($hit !== null) {
                $lga = $hit;
                break;
            }
        }

        return [$state, $lga];
    }

    /**
     * Write back only the solicitor columns.
     *
     * Deliberately not routed through InstrumentCaptureService::update(): that
     * rebuilds the whole row from the capture form's field names and would blank
     * anything the small modal does not post. Nothing else stores solicitor
     * details - deed_registrations has no such columns - so this row is the only
     * write target.
     *
     * State and LGA are accepted but have no columns; like capture, they survive
     * only inside the composed office address. Unlike the capture service this
     * stores the posted address verbatim rather than re-appending district, LGA
     * and state to it - the modal has already composed it, and appending again is
     * what produced rows reading "... Nassarawa Kano Nassarawa Kano".
     */
    public function updateSolicitor(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'solicitorName' => 'nullable|string|max:255',
            'solicitorPhone' => ['nullable', 'string', new NigerianPhone()],
            'solicitorDistrict' => 'nullable|string|max:255',
            'solicitorDistrictOther' => 'nullable|string|max:255',
            'solicitorState' => 'nullable|string|max:255',
            'solicitorLga' => 'nullable|string|max:255',
            'solicitorAddress' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $record = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $id)->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Instrument not found.'], 404);
        }

        if ($this->isOccupancyPermit($record->instrument_type)) {
            return response()->json([
                'success' => false,
                'message' => 'Occupancy Permits do not carry solicitor details.',
            ], 422);
        }

        // An emptied box means "clear this", so blanks are stored as NULL rather
        // than as an empty string the listing would render as a stray line.
        $clean = static function ($value) {
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        };

        // "Other" is the picker's escape hatch for a district outside Kano's list;
        // the typed value is what belongs on the record, matching how
        // InstrumentCaptureService resolves the same pair of fields.
        $district = $clean($request->input('solicitorDistrict'));
        if ($district === 'Other') {
            $district = $clean($request->input('solicitorDistrictOther'));
        }

        $payload = [
            'solicitor_name' => $clean($request->input('solicitorName')),
            'solicitor_phone' => $clean($request->input('solicitorPhone')),
            'solicitor_district' => $district,
            'solicitor_address' => $clean($request->input('solicitorAddress')),
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ];

        try {
            DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $id)
                ->update($payload);
        } catch (\Throwable $e) {
            $this->captureLog()->error('Solicitor update failed: ' . $e->getMessage(), [
                'instrument_capture_id' => $id,
                'user_id' => Auth::id(),
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update solicitor details: ' . $e->getMessage(),
            ], 500);
        }

        $this->captureLog()->info('Solicitor details updated', [
            'instrument_capture_id' => $id,
            'user_id' => Auth::id(),
            'state' => $request->input('solicitorState'),
            'lga' => $request->input('solicitorLga'),
            'before' => [
                'solicitor_name' => $record->solicitor_name,
                'solicitor_phone' => $record->solicitor_phone,
                'solicitor_district' => $record->solicitor_district ?? null,
                'solicitor_address' => $record->solicitor_address,
            ],
            'after' => Arr::except($payload, ['updated_at', 'updated_by']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitor details updated successfully.',
            'solicitor' => [
                'name' => $payload['solicitor_name'],
                'phone' => $payload['solicitor_phone'],
                'district' => $payload['solicitor_district'],
                'state' => $clean($request->input('solicitorState')),
                'lga' => $clean($request->input('solicitorLga')),
                'address' => $payload['solicitor_address'],
            ],
        ]);
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

    /**
     * TP No lookup for the capture screen.
     *
     * A TP number is a slashed, multi-segment string (TP/RF4/DD4/3/333) and the
     * operator rarely remembers it from the front. Matching on the prefix alone
     * made the middle and tail segments unsearchable, so the search now runs in
     * widening passes and returns them in that order - the closest match first:
     *
     *   1. the term anywhere in the number      "333"      -> TP/RF4/DD4/3/333
     *   2. every token, in any segment          "rf4 333"  -> TP/RF4/DD4/3/333
     *   3. the term with its separators dropped "rf4dd4"   -> TP/RF4/DD4/3/333
     *   4. the letters in order, gaps allowed   "rfd"      -> TP/RF4/DD4/3/333
     *
     * Each pass only needs to fill what the passes before it left empty, so the
     * loose ones never push a better match down the list.
     */
    public function searchTpLookups(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $limit = 20;
        $ordered = [];   // upper-cased tp_no => original, preserving match order

        if ($term !== '') {
            $patterns = $this->tpLookupSearchPatterns($term);

            foreach ($patterns as $pattern) {
                if (count($ordered) >= $limit) {
                    break;
                }

                $rows = DB::connection('sqlsrv')
                    ->table('tp_lookups')
                    ->select('tp_no')
                    ->whereNotNull('tp_no')
                    ->whereRaw($pattern['sql'], $pattern['bindings'])
                    ->distinct()
                    ->orderBy('tp_no')
                    // Ask for the full window each pass: rows already collected by an
                    // earlier pass come back again and are dropped by the key check.
                    ->limit($limit)
                    ->get();

                foreach ($rows as $row) {
                    $tpNo = trim((string) $row->tp_no);
                    if ($tpNo === '') {
                        continue;
                    }
                    $key = mb_strtoupper($tpNo);
                    if (!array_key_exists($key, $ordered)) {
                        $ordered[$key] = $tpNo;
                    }
                    if (count($ordered) >= $limit) {
                        break;
                    }
                }
            }
        }

        $results = collect(array_values($ordered))
            ->map(fn ($tpNo) => ['id' => $tpNo, 'text' => $tpNo])
            ->values();

        $results->push([
            'id' => '__other__',
            'text' => 'Other',
        ]);

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => false],
        ]);
    }

    /**
     * The widening LIKE passes described on searchTpLookups(), as raw predicates.
     * Every pattern is escaped, so a term containing % _ [ is matched literally.
     */
    private function tpLookupSearchPatterns(string $term): array
    {
        $escape = static fn (string $value): string => str_replace(
            ['\\', '%', '_', '['],
            ['\\\\', '\\%', '\\_', '\\['],
            $value
        );

        // The separators a TP number is built from; dropping them lets "rf4dd4"
        // reach TP/RF4/DD4 and lets a pasted number match however it was typed.
        $stripped = preg_replace('/[^A-Za-z0-9]+/', '', $term) ?? '';
        $tokens = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', $term) ?: []));

        // Same de-separating applied to the column, so both sides compare alike.
        $compactColumn = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(tp_no, '/', ''), '-', ''), ' ', ''), '.', ''), '\\', '')";

        $patterns = [];

        // 1. Anywhere in the number.
        $patterns[] = [
            'sql' => "tp_no LIKE ? ESCAPE '\\'",
            'bindings' => ['%' . $escape($term) . '%'],
        ];

        // 2. Every token present, in any segment and any order.
        if (count($tokens) > 1) {
            $patterns[] = [
                'sql' => implode(' AND ', array_fill(0, count($tokens), "tp_no LIKE ? ESCAPE '\\'")),
                'bindings' => array_map(fn ($t) => '%' . $escape($t) . '%', $tokens),
            ];
        }

        // 3. Separators dropped on both sides.
        if ($stripped !== '') {
            $patterns[] = [
                'sql' => $compactColumn . " LIKE ? ESCAPE '\\'",
                'bindings' => ['%' . $escape($stripped) . '%'],
            ];

            // 4. Last resort: the characters in order with anything between them,
            //    which is what turns "rfd" into a hit on TP/RF4/DD4/3/333. Kept off
            //    very short terms only when they would match nearly everything.
            $chars = preg_split('//u', $stripped, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($chars) >= 2) {
                $patterns[] = [
                    'sql' => $compactColumn . " LIKE ? ESCAPE '\\'",
                    'bindings' => ['%' . implode('%', array_map($escape, $chars)) . '%'],
                ];
            }
        }

        return $patterns;
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
                    // Deed of Purchase is not part of the Instrument Capture Register report.
                    ->whereRaw("LOWER(LTRIM(RTRIM(ISNULL(ic.instrument_type, '')))) <> ?", ['deed of purchase'])
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
                    // A TEMP-xxxx placeholder is not a real file number, so the register shows
                    // the instrument with a blank File No rather than the temporary one.
                    'fileno' => $this->registerFileNo($item->mlsFNo ?: $item->kangisFileNo ?: $item->NewKANGISFileno ?: $item->temp_fileno),
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
                    'fileno' => $this->registerFileNo($item->fileno),
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
     * File number as printed on the Instrument Capture Register: temporary
     * TEMP-xxxx placeholders are blanked out.
     */
    private function registerFileNo($fileNo): ?string
    {
        $fileNo = trim((string) $fileNo);

        return ($fileNo === '' || stripos($fileNo, 'TEMP') === 0) ? null : $fileNo;
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
