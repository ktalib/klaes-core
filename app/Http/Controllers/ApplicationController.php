<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\LandRecommendation;
use App\Models\LandsOneStopShopApplication;
use App\Models\StreetName;
use App\Services\Pra\PraRecordService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    /**
     * Display the general Lands One Stop Shop applications listing.
     * Now pulls from oss_applications table and the legacy instrument_capture.
     */
    public function index(Request $request): View
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));
        $typeFilter = $request->input('type');

        // 'change-of-name' and 'no-change-of-name' are page-mode flags, not application_type values
        $appTypeFilter = in_array($typeFilter, ['change-of-name', 'no-change-of-name'], true)
            ? null
            : $typeFilter;

        // Build schema-safe PRA fallback SQL snippets (avoid hardcoding non-existent columns).
        $praColumns = $this->sqlsrvColumnMap('pra');
        $pickPraColumn = static function (array $candidates) use ($praColumns): ?string {
            foreach ($candidates as $candidate) {
                $key = strtolower((string) $candidate);
                if (isset($praColumns[$key])) {
                    return $praColumns[$key];
                }
            }

            return null;
        };

        $praFileMatchParts = [];
        foreach (['temp_fileno', 'mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno'] as $candidate) {
            $column = $pickPraColumn([$candidate]);
            if ($column !== null) {
                $praFileMatchParts[] = "UPPER(p.[{$column}]) = UPPER(oa.file_no)";
            }
        }
        $praFileMatchSql = implode(' OR ', $praFileMatchParts);

        $buildPraValueExpr = static function (array $columns): string {
            if (empty($columns)) {
                return 'NULL';
            }

            $parts = array_map(static function ($column) {
                return "NULLIF(LTRIM(RTRIM(p.[{$column}])), '')";
            }, $columns);

            if (count($parts) === 1) {
                return $parts[0];
            }

            return 'COALESCE(' . implode(', ', $parts) . ')';
        };

        $plotCols = array_values(array_filter([
            $pickPraColumn(['plot_no']),
            $pickPraColumn(['plot_number']),
        ]));

        $planCols = array_values(array_filter([
            $pickPraColumn(['approved_plan_no']),
            $pickPraColumn(['tp_no']),
            $pickPraColumn(['lpkn_no']),
        ]));

        $opSerialCols = array_values(array_filter([
            $pickPraColumn(['op_serial_number']),
            $pickPraColumn(['serialNo']),
            $pickPraColumn(['pageNo']),
        ]));

        $praPlotExpr = $buildPraValueExpr($plotCols);
        $praPlanExpr = $buildPraValueExpr($planCols);
        $praOpSerialExpr = $buildPraValueExpr($opSerialCols);

        $locationCols = array_values(array_filter([
            $pickPraColumn(['property_description']),
            $pickPraColumn(['location']),
        ]));
        $praLocationExpr = $buildPraValueExpr($locationCols);

        $praPlotSubquery = $praFileMatchSql !== ''
            ? "(SELECT TOP 1 {$praPlotExpr} FROM pra p WHERE {$praFileMatchSql} ORDER BY p.id DESC)"
            : 'NULL';
        $praPlanSubquery = $praFileMatchSql !== ''
            ? "(SELECT TOP 1 {$praPlanExpr} FROM pra p WHERE {$praFileMatchSql} ORDER BY p.id DESC)"
            : 'NULL';
        $praOpSerialSubquery = $praFileMatchSql !== ''
            ? "(SELECT TOP 1 {$praOpSerialExpr} FROM pra p WHERE {$praFileMatchSql} ORDER BY p.id DESC)"
            : 'NULL';
        $praLocationSubquery = $praFileMatchSql !== ''
            ? "(SELECT TOP 1 {$praLocationExpr} FROM pra p WHERE {$praFileMatchSql} ORDER BY p.id DESC)"
            : 'NULL';

        // Build file_indexings fallback subqueries (schema‑safe)
        $fiColumns = $this->sqlsrvColumnMap('file_indexings');
        $pickFiColumn = static function (array $candidates) use ($fiColumns): ?string {
            foreach ($candidates as $c) {
                if (isset($fiColumns[strtolower($c)])) {
                    return $fiColumns[strtolower($c)];
                }
            }
            return null;
        };
        $fiFileNumberCol = $pickFiColumn(['file_number']);
        $fiLocationCol   = $pickFiColumn(['location']);
        $fiPlotCol       = $pickFiColumn(['plot_no', 'plot_number']);
        $fiPlanCol       = $pickFiColumn(['plan_no', 'tp_no']);

        $fiLocationSubquery = ($fiFileNumberCol && $fiLocationCol)
            ? "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fi.[{$fiLocationCol}])), '') FROM file_indexings fi WHERE UPPER(fi.[{$fiFileNumberCol}]) = UPPER(oa.file_no) ORDER BY fi.id DESC)"
            : 'NULL';
        $fiPlotSubquery = ($fiFileNumberCol && $fiPlotCol)
            ? "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fi.[{$fiPlotCol}])), '') FROM file_indexings fi WHERE UPPER(fi.[{$fiFileNumberCol}]) = UPPER(oa.file_no) ORDER BY fi.id DESC)"
            : 'NULL';
        $fiPlanSubquery = ($fiFileNumberCol && $fiPlanCol)
            ? "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fi.[{$fiPlanCol}])), '') FROM file_indexings fi WHERE UPPER(fi.[{$fiFileNumberCol}]) = UPPER(oa.file_no) ORDER BY fi.id DESC)"
            : 'NULL';

        // Build fileNumber fallback subqueries
        $fnLocationSubquery = "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fn.[location])), '') FROM fileNumber fn WHERE UPPER(fn.mlsfNo) = UPPER(oa.file_no) ORDER BY fn.id DESC)";
        $fnPlotSubquery     = "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fn.[plot_no])), '') FROM fileNumber fn WHERE UPPER(fn.mlsfNo) = UPPER(oa.file_no) ORDER BY fn.id DESC)";
        $fnPlanSubquery     = "(SELECT TOP 1 NULLIF(LTRIM(RTRIM(fn.[tp_no])), '') FROM fileNumber fn WHERE UPPER(fn.mlsfNo) = UPPER(oa.file_no) ORDER BY fn.id DESC)";

        // ── Pull from oss_applications table with instrument_capture join ──
        $records = DB::connection('sqlsrv')
            ->table('oss_applications as oa')
            ->leftJoin('instrument_capture as ic', 'ic.id', '=', 'oa.instrument_capture_id')
            ->select([
                'oa.*',
                'ic.temp_fileno',
                'ic.plot_number as ic_plot_number',
                'ic.survey_plan_no as ic_plan_no',
                'ic.property_description as ic_location',
                'ic.op_serial_number as ic_op_serial_number',
                DB::raw("{$praPlotSubquery} as pra_plot_no"),
                DB::raw("{$praPlanSubquery} as pra_plan_no"),
                DB::raw("{$praOpSerialSubquery} as pra_op_serial_number"),
                DB::raw("{$praLocationSubquery} as pra_location"),
                DB::raw("{$fiLocationSubquery} as fi_location"),
                DB::raw("{$fiPlotSubquery} as fi_plot_no"),
                DB::raw("{$fiPlanSubquery} as fi_plan_no"),
                DB::raw("{$fnLocationSubquery} as fn_location"),
                DB::raw("{$fnPlotSubquery} as fn_plot_no"),
                DB::raw("{$fnPlanSubquery} as fn_plan_no"),
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('oa.applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('oa.file_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.plan_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.location', 'LIKE', "%{$search}%")
                        ->orWhere('oa.phone', 'LIKE', "%{$search}%")
                        ->orWhere('ic.temp_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('ic.op_serial_number', 'LIKE', "%{$search}%");
                });
            })
            ->when($appTypeFilter, function ($q) use ($appTypeFilter) {
                $q->where('oa.application_type', $appTypeFilter);
            })
            ->orderBy('oa.created_at')
            ->limit($limit)
            ->get();

        // Build a displayable URL for each record's passport photo
        $records->transform(function ($record) {
            $record->passport_photo_url = null;
            if (!empty($record->passport_photo)) {
                $path = $record->passport_photo;
                // Normalise legacy paths that stored the "public/" prefix
                if (str_starts_with($path, 'public/')) {
                    $path = substr($path, 7);
                }
                if (Storage::disk('public')->exists($path)) {
                    $record->passport_photo_url = asset('storage/' . $path);
                }
            }
            return $record;
        });

        // Supporting data for modals / dropdowns
        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // ── Card counts by application type ──
        $cardCounts = LandsOneStopShopApplication::query()
            ->selectRaw("application_type, COUNT(*) as cnt")
            ->groupBy('application_type')
            ->pluck('cnt', 'application_type')
            ->toArray();

        return view('lands_one_stop_shop.all_applications', [
            'pageTitle'     => 'Applications',
            'records'       => $records,
            'limit'         => $limit,
            'typeFilter'    => $typeFilter,
            'states'        => $states,
            'lgas'          => $lgas,
            'districts'     => $districts,
            'streetNames'   => $streetNames,
            'typeOptions'   => LandsOneStopShopApplication::typeOptions(),
            'statusOptions' => LandsOneStopShopApplication::statusOptions(),
            'cardCounts'    => $cardCounts,
        ]);
    }

    /**
     * Store a new OSS application.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['captured_by'] = Auth::id();
        $data['status']      = LandsOneStopShopApplication::STATUS_PENDING;

        // Handle passport photo upload into EDMS Lands Registry folder by selected file number.
        if ($request->hasFile('passport_photo')) {
            $selectedFileNo = (string) ($data['file_no'] ?? $request->input('file_no', ''));
            $data['passport_photo'] = $this->storeOssPassportPhoto(
                $request->file('passport_photo'),
                $selectedFileNo
            );
        }

        // Remove passport_photo from data if it's the UploadedFile (already handled)
        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        $record = LandsOneStopShopApplication::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Application created successfully.',
            'data'    => $record,
        ]);
    }

    /**
     * Show a single application.
     */
    public function show(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Application retrieved.',
            'data'    => $record,
        ]);
    }

    /**
     * Update an existing application.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);

        $rules = $this->validationRules();
        // On update, application_type and applicant_name are optional
        $rules['application_type'] = 'sometimes|required|in:residential,commercial,industrial,agricultural';
        $rules['applicant_name']   = 'sometimes|required|string|max:255';
        $rules['status']           = 'nullable|in:pending,processing,approved,rejected';

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id();

        // Handle passport photo upload into EDMS Lands Registry folder by selected file number.
        if ($request->hasFile('passport_photo')) {
            $selectedFileNo = (string) ($data['file_no'] ?? $record->file_no ?? $request->input('file_no', ''));
            $data['passport_photo'] = $this->storeOssPassportPhoto(
                $request->file('passport_photo'),
                $selectedFileNo
            );
        }

        // Remove passport_photo from data if it's the UploadedFile (already handled)
        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data'    => $record->fresh(),
        ]);
    }

    /**
     * Delete an application.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ]);
    }

    /**
     * Store OSS passport photo in EDMS Lands Registry path:
     * storage/app/public/EDMS/SCAN_UPLOAD/Lands_Registry/{FILE_NO}
     *
     * Existing folders/files are preserved; each upload gets a unique file name.
     */
    private function storeOssPassportPhoto(UploadedFile $file, ?string $selectedFileNo): string
    {
        $rawFileNo = strtoupper(trim((string) $selectedFileNo));
        $folderFileNo = $rawFileNo !== '' ? $rawFileNo : 'UNASSIGNED';
        $folderFileNo = preg_replace('/[^A-Z0-9\-_]/', '-', $folderFileNo);
        $folderFileNo = trim((string) $folderFileNo, '-');
        if ($folderFileNo === '') {
            $folderFileNo = 'UNASSIGNED';
        }

        $directory = 'EDMS/SCAN_UPLOAD/Lands_Registry/' . $folderFileNo . '/A4';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $filename = 'passport_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;

        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * Generate a bill for an OSS application.
     * Saves to the `billing` table with source = OSS_OP_APPLICATION_FEE.
     *
     * Works for both:
     *  - oss_applications records (uses application_type)
     *  - instrument_capture / OP Resettlement records (land_use passed via request)
     */
    public function bill(Request $request, int $id): JsonResponse
    {
        // Check if a bill already exists for this source + id
        $existing = Billing::where('source', 'OSS_OP_APPLICATION_FEE')
            ->where('source_id', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A bill has already been generated for this application.',
            ], 409);
        }

        // Determine type: prefer request land_use (OP Resettlement), fall back to oss_applications record
        $type = null;
        if ($request->filled('land_use')) {
            $type = strtolower(trim($request->input('land_use')));
        } else {
            $application = LandsOneStopShopApplication::find($id);
            $type = $application ? $application->application_type : 'residential';
        }

        // Bill amounts per application type
        $billRates = [
            'residential' => ['Change_of_Name' => 25000, 'Processing_Fee' => 3000,  'survey_fee' => 10000, 'Application_Form' => 2000],
            'commercial'  => ['Change_of_Name' => 25000, 'Processing_Fee' => 20000, 'survey_fee' => 20000, 'Application_Form' => 10000],
            'industrial'  => ['Change_of_Name' => 25000, 'Processing_Fee' => 30000, 'survey_fee' => 30000, 'Application_Form' => 20000],
            'agricultural'=> ['Change_of_Name' => 25000, 'Processing_Fee' => 3000,  'survey_fee' => 10000, 'Application_Form' => 2000],
        ];

        $rates = $billRates[$type] ?? $billRates['residential'];

        $refId = $this->generateOssBillRefId();

        $bill = Billing::create([
            'Change_of_Name'  => $rates['Change_of_Name'],
            'Processing_Fee'  => $rates['Processing_Fee'],
            'survey_fee'      => $rates['survey_fee'],
            'Application_Form'=> $rates['Application_Form'],
            'source'          => 'OSS_OP_APPLICATION_FEE',
            'source_id'       => $id,
            'ref_id'          => $refId,
            'Payment_Status'  => 'paid',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill generated successfully.',
            'data'    => $bill,
        ]);
    }

    /**
     * Check whether an OSS bill already exists for an application/source ID.
     */
    public function billStatus(int $id): JsonResponse
    {
        $bill = Billing::where('source', 'OSS_OP_APPLICATION_FEE')
            ->where('source_id', $id)
            ->orderByDesc('ID')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Bill status fetched successfully.',
            'data' => [
                'exists' => (bool) $bill,
                'bill_id' => $bill->ID ?? null,
                'source_id' => $id,
            ],
        ]);
    }

    private function generateOssBillRefId(): string
    {
        $prefix = 'OSS-1-';

        $latest = Billing::where('ref_id', 'LIKE', "{$prefix}%")
            ->orderByRaw("TRY_CAST(REPLACE(ref_id, '{$prefix}', '') AS INT) DESC")
            ->value('ref_id');

        $next = 1;
        if ($latest) {
            $number = (int) str_replace($prefix, '', $latest);
            $next = max(1, $number + 1);
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Print the OP Acknowledgement form (opens in new tab).
     * The $id corresponds to instrument_capture.id (the capture_id used by both controllers).
     */
    public function printAcknowledgement(int $id): View
    {
        // 1) Primary path: ID is instrument_capture.id
        $row = DB::connection('sqlsrv')
            ->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
            ->where('ic.id', $id)
            ->select([
                'ic.id',
                'ic.party_1_name',
                'ic.party_2_name',
                'ic.plot_number',
                'ic.survey_plan_no',
                'ic.tp_no',
                'ic.op_serial_number',
                'ic.property_description',
                'ic.district',
                'ic.lga',
                'ic.created_at',
                'dr.registration_number',
            ])
            ->first();

        // 2) Fallback path: ID is fileNumber.id (OP resettlement/applications listing row)
        if (!$row) {
            $row = DB::connection('sqlsrv')
                ->table('fileNumber as fn')
                ->leftJoin('mls_file_no as mfn', 'fn.tracking_id', '=', 'mfn.tracking_id')
                ->leftJoin('instrument_capture as ic', 'mfn.source_instrument_capture_id', '=', 'ic.id')
                ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
                ->where('fn.id', $id)
                ->select([
                    DB::raw('COALESCE(ic.id, fn.id) as id'),
                    'ic.party_1_name',
                    'ic.party_2_name',
                    DB::raw('COALESCE(ic.plot_number, fn.plot_no) as plot_number'),
                    DB::raw('COALESCE(ic.survey_plan_no, ic.tp_no, ic.op_serial_number, fn.tp_no, fn.mlsfNo) as survey_plan_no'),
                    DB::raw('COALESCE(ic.property_description, fn.location) as property_description'),
                    DB::raw('COALESCE(ic.created_at, fn.created_at) as created_at'),
                    'dr.registration_number',
                    'fn.FileName as fallback_file_title',
                ])
                ->first();
        }

        abort_unless($row, 404, 'Application not found.');

        $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;

        // Build a simple object matching the Blade template expectations
        $record = (object) [
            'applicant_name' => strtoupper(
                collect([$row->party_1_name, $row->party_2_name])
                    ->filter()
                    ->implode(' / ')
            ) ?: strtoupper((string) ($row->fallback_file_title ?? '—')),
            'plot_no'        => strtoupper($row->plot_number ?? ''),
            'plan_no'        => strtoupper($row->survey_plan_no ?? $row->tp_no ?? $row->op_serial_number ?? ''),
            'location'       => strtoupper($row->property_description ?? ''),
            'date_captured'  => $createdAt ? strtoupper($createdAt->format('M d, Y')) : '',
            'time_captured'  => $createdAt ? strtoupper($createdAt->format('g:i A')) : '',
        ];

        return view('lands_one_stop_shop.partials.print_acknowledgement', compact('record'));
    }

    /**
     * Print the Recommendation for Grant of Statutory Right of Occupancy.
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printRecommendation(Request $request): View
    {
        $fileRef = strtoupper(trim((string) $request->input('file_ref', '')));
        $recordId = (int) $request->input('record_id', 0);

        $pickFilled = static function (array $values): string {
            foreach ($values as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }

            return '';
        };

        $formatNaira = static function ($value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }

            if (preg_match('/^to\s*follow$/i', $raw)) {
                return 'TO FOLLOW';
            }

            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
            if ($normalized === '' || !is_numeric($normalized)) {
                return strtoupper($raw);
            }

            return '₦' . number_format((float) $normalized, 2);
        };

        $plotNo = strtoupper(trim((string) $request->input('plot_no', '')));
        $planNo = strtoupper(trim((string) $request->input('plan_no', '')));
        $location = trim((string) $request->input('location', ''));

        // Safety fallback: if modal missed mapped values, resolve from the source application record.
        if (($plotNo === '' || $planNo === '' || $location === '') && $recordId > 0) {
            $row = DB::connection('sqlsrv')
                ->table('oss_applications as oa')
                ->leftJoin('instrument_capture as ic', 'ic.id', '=', 'oa.instrument_capture_id')
                ->where('oa.id', $recordId)
                ->select([
                    'oa.plot_no',
                    'oa.plan_no',
                    'oa.location',
                    'ic.plot_number as ic_plot_no',
                    'ic.survey_plan_no as ic_plan_no',
                    'ic.tp_no as ic_tp_no',
                    'ic.op_serial_number as ic_op_serial_number',
                    'ic.property_description as ic_location',
                    'ic.district as ic_district',
                ])
                ->first();

            if ($row) {
                if ($plotNo === '') {
                    $plotNo = strtoupper($pickFilled([
                        $row->plot_no ?? '',
                        $row->ic_plot_no ?? '',
                    ]));
                }

                if ($planNo === '') {
                    $planNo = strtoupper($pickFilled([
                        $row->plan_no ?? '',
                        $row->ic_plan_no ?? '',
                        $row->ic_tp_no ?? '',
                        $row->ic_op_serial_number ?? '',
                    ]));
                }

                if ($location === '') {
                    $location = $pickFilled([
                        $row->location ?? '',
                        $row->ic_location ?? '',
                        $row->ic_district ?? '',
                    ]);
                }
            }
        }

        $trackingId = '';
        $rofoSerialNo = '';
        if ($fileRef) {
            $indexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $fileRef)
                ->whereNotNull('tracking_id')
                ->value('tracking_id');
            $trackingId = (string) ($indexing ?? '');

            $rec = DB::connection('sqlsrv')
                ->table('land_recommendations')
                ->whereRaw('UPPER(file_number) = ?', [$fileRef])
                ->orderByDesc('id')
                ->value('land_rofo_serial_no');
            $rofoSerialNo = (string) ($rec ?? '');
        }

        $record = (object) [
            'applicant_name'    => strtoupper((string) $request->input('applicant_name', '')),
            'file_ref'          => $fileRef,
            'purpose'           => strtoupper((string) $request->input('purpose', '')),
            'location'          => $location,
            'plot_no'           => $plotNo,
            'plan_no'           => $planNo,
            'term'              => (string) $request->input('term', ''),
            'dev_value'         => $formatNaira($request->input('dev_value', '')),
            'completion_time'   => (string) $request->input('completion_time', ''),
            'ground_rent'       => $formatNaira($request->input('ground_rent', '')),
            'dev_charge'        => $formatNaira($request->input('dev_charge', '')),
            'survey_charges'    => $formatNaira($request->input('survey_charges', '')),
            'director_reasons'  => (string) $request->input('director_reasons', ''),
            'director_sign'     => (string) $request->input('director_sign', ''),
            'director_date'     => (string) $request->input('director_date', ''),
            'ps_plot'           => strtoupper((string) $request->input('ps_plot', '')),
            'ps_location'       => strtoupper((string) $request->input('ps_location', '')),
            'ps_sign'           => (string) $request->input('ps_sign', ''),
            'ps_date'           => (string) $request->input('ps_date', ''),
            'commissioner_name' => (string) $request->input('commissioner_name', ''),
            'commissioner_date' => (string) $request->input('commissioner_date', ''),
            'approval_status'   => (string) $request->input('approval_status', ''),
            'tracking_id'       => $trackingId,
            'rofo_serial_no'    => $rofoSerialNo,
        ];

        return view('lands_one_stop_shop.partials.print_recommendation', compact('record'));
    }

    /**
     * Fetch existing recommendation by file reference.
     * Used by OSS recommendation modal to decide Save/Print state.
     */
    public function recommendationStatus(Request $request): JsonResponse
    {
        $fileRef = strtoupper(trim((string) $request->query('file_ref', '')));

        if ($fileRef === '') {
            return response()->json([
                'success' => false,
                'message' => 'File reference is required.',
            ], 422);
        }

        $recommendation = LandRecommendation::query()
            ->whereRaw('UPPER(file_number) = ?', [$fileRef])
            ->orderByDesc('id')
            ->first();

        if (!$recommendation) {
            return response()->json([
                'success' => true,
                'message' => 'No recommendation found.',
                'data' => [
                    'exists' => false,
                ],
            ]);
        }

        if (empty($recommendation->land_rofo_serial_no) && strtoupper((string) ($recommendation->type ?? '')) === 'OSS') {
            DB::connection('sqlsrv')->transaction(function () use ($recommendation) {
                $maxSerial = DB::connection('sqlsrv')
                    ->table(DB::raw('land_recommendations WITH (UPDLOCK, HOLDLOCK)'))
                    ->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS'])
                    ->selectRaw('MAX(TRY_CONVERT(int, land_rofo_serial_no)) as max_serial')
                    ->value('max_serial');

                $recommendation->update([
                    'land_rofo_serial_no' => str_pad((int) ($maxSerial ?? 0) + 1, 5, '0', STR_PAD_LEFT),
                    'updated_by' => Auth::id(),
                ]);
            });
            $recommendation->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Recommendation found.',
            'data' => [
                'exists' => true,
                'id' => $recommendation->id,
                'file_ref' => (string) ($recommendation->file_number ?? ''),
                'term' => (string) ($recommendation->term ?? ''),
                'dev_value' => (string) ($recommendation->development_value ?? ''),
                'completion_time' => (string) ($recommendation->development_period ?? ''),
                'ground_rent' => (string) ($recommendation->ground_rent ?? ''),
                'dev_charge' => (string) ($recommendation->development_charge ?? ''),
                'survey_charges' => (string) ($recommendation->survey_fees ?? ''),
                'director_reasons' => (string) ($recommendation->recommendation ?? ''),
                'status' => (string) ($recommendation->status ?? ''),
            ],
        ]);
    }

    /**
     * Check whether a verification record exists for a given OP capture record.
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        $recordId = (int) $request->query('record_id', 0);

        if ($recordId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Record ID is required.',
            ], 422);
        }

        $exists = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $recordId)
            ->exists();

        return response()->json([
            'success' => true,
            'message' => 'Verification status fetched successfully.',
            'data' => [
                'exists' => (bool) $exists,
                'record_id' => $recordId,
            ],
        ]);
    }

    /**
     * Persist recommendation into land_recommendations table.
     */
    public function saveRecommendation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_id' => 'nullable|integer',
            'applicant_name' => 'nullable|string|max:255',
            'file_ref' => 'required|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'plan_no' => 'nullable|string|max:100',
            'term' => 'required|string|max:100',
            'dev_value' => 'required|string|max:100',
            'completion_time' => 'required|string|max:100',
            'ground_rent' => 'required|string|max:100',
            'dev_charge' => 'required|string|max:100',
            'survey_charges' => 'required|string|max:100',
            'director_reasons' => 'nullable|string',
        ]);

        $fileRef = strtoupper(trim((string) $validated['file_ref']));

        $existing = LandRecommendation::query()
            ->whereRaw('UPPER(file_number) = ?', [$fileRef])
            ->orderByDesc('id')
            ->first();

        $assignNextOssSerial = static function (): string {
            $maxSerial = DB::connection('sqlsrv')
                ->table(DB::raw('land_recommendations WITH (UPDLOCK, HOLDLOCK)'))
                ->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS'])
                ->selectRaw('MAX(TRY_CONVERT(int, land_rofo_serial_no)) as max_serial')
                ->value('max_serial');

            return str_pad((int) ($maxSerial ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        };

        if ($existing) {
            if (empty($existing->land_rofo_serial_no) && strtoupper((string) ($existing->type ?? '')) === 'OSS') {
                DB::connection('sqlsrv')->transaction(function () use ($existing, $assignNextOssSerial) {
                    $existing->update([
                        'land_rofo_serial_no' => $assignNextOssSerial(),
                        'updated_by' => Auth::id(),
                    ]);
                });
                $existing->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation already exists.',
                'data' => [
                    'exists' => true,
                    'id' => $existing->id,
                ],
            ]);
        }

        $toNumber = static function ($value) {
            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
            return $normalized === '' ? null : (float) $normalized;
        };

        $recommendation = DB::connection('sqlsrv')->transaction(function () use ($fileRef, $validated, $toNumber, $assignNextOssSerial) {
            // Keep OSS serial sequence independent; first record starts at 00001.
            $nextSerial = $assignNextOssSerial();

            return LandRecommendation::create([
                'file_number' => $fileRef,
                'applicant_name' => (string) ($validated['applicant_name'] ?? ''),
                'purpose_of_clause' => (string) ($validated['purpose'] ?? ''),
                'location' => (string) ($validated['location'] ?? ''),
                'plot_number' => (string) ($validated['plot_no'] ?? ''),
                'layout_plan_no' => (string) ($validated['plan_no'] ?? ''),
                'term' => (string) ($validated['term'] ?? ''),
                'development_value' => $toNumber($validated['dev_value'] ?? null),
                'development_period' => (string) ($validated['completion_time'] ?? ''),
                'ground_rent' => $toNumber($validated['ground_rent'] ?? null),
                'development_charge' => $toNumber($validated['dev_charge'] ?? null),
                'survey_fees' => $toNumber($validated['survey_charges'] ?? null),
                'recommendation' => (string) ($validated['director_reasons'] ?? ''),
                'land_use' => (string) ($validated['purpose'] ?? ''),
                'tracking_id' => (string) ($validated['record_id'] ?? ''),
                'land_rofo_serial_no' => $nextSerial,
                'status' => LandRecommendation::STATUS_PENDING,
                'rofo_status' => LandRecommendation::ROFO_GENERATED,
                'rofo_generated_at' => now(),
                'type' => 'OSS',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Recommendation saved successfully.',
            'data' => [
                'exists' => true,
                'id' => $recommendation->id,
            ],
        ]);
    }

    /**
     * Save Change of Ownership form data.
     */
    public function saveChangeOfOwnership(Request $request): JsonResponse
    {
        $request->validate([
            'record_id'        => 'required|integer',
            'current_name'     => 'required|string|max:255',
        ]);

        $recordId = (int) $request->input('record_id');

        $row = DB::connection('sqlsrv')
            ->table('oss_change_of_ownership')
            ->where('instrument_capture_id', $recordId)
            ->first();

        $data = [
            'instrument_capture_id' => $recordId,
            'op_number'        => (string) $request->input('op_number', ''),
            'location'         => (string) $request->input('location', ''),
            'plot_no'          => (string) $request->input('plot_no', ''),
            'plan_no'          => (string) $request->input('plan_no', ''),
            'date_of_issuance' => $request->input('date_of_issuance') ?: null,
            'original_name'    => (string) $request->input('original_name', ''),
            'original_address' => (string) $request->input('original_address', ''),
            'original_phone'   => (string) $request->input('original_phone', ''),
            'current_name'     => (string) $request->input('current_name', ''),
            'current_address'  => (string) $request->input('current_address', ''),
            'current_phone'    => (string) $request->input('current_phone', ''),
            'ownership_method' => (string) $request->input('ownership_method', ''),
            'updated_at'       => now(),
        ];

        if ($row) {
            DB::connection('sqlsrv')
                ->table('oss_change_of_ownership')
                ->where('id', $row->id)
                ->update($data);
        } else {
            $data['captured_by'] = Auth::id();
            $data['created_at']  = now();
            DB::connection('sqlsrv')
                ->table('oss_change_of_ownership')
                ->insert($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Change of ownership saved successfully.',
        ]);
    }

    /**
     * Save FFR Change of Name flow.
     * Appends PRA timeline row and synchronizes holder/name fields in indexing and customer tables.
     */
    public function saveFfrChangeOfName(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no' => 'required|string|max:120',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'required|string|max:255',
            'residence_address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'prop_id' => 'nullable',
            'op_serial_number' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:500',
            'land_use' => 'nullable|string|max:150',
        ]);

        $sourceFileNo = trim((string) $validated['source_file_no']);
        $newParty2 = trim((string) $validated['party_2']);
        $resolvedCustomerType = $this->resolveCustomerTypeFromName($newParty2);

        Log::info('FFR save initiated', [
            'source_file_no' => $sourceFileNo,
            'party_1' => (string) ($validated['party_1'] ?? ''),
            'party_2' => $newParty2,
            'resolved_customer_type' => $resolvedCustomerType,
            'user_id' => Auth::id(),
        ]);

        $sourceHistory = $praService->findAllByFileNumber($sourceFileNo);
        if (empty($sourceHistory)) {
            Log::warning('FFR save aborted: no PRA source history found', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No PRA record found for selected source file number.',
            ], 404);
        }

        $opRecord = $this->resolveMotherOpRecord($sourceHistory);

        if (!$opRecord) {
            Log::warning('FFR save aborted: source history has no OP record', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Source file does not contain an OP record in PRA history.',
            ], 422);
        }

        $partyFromOp = trim((string) (
            $validated['party_1']
            ?? data_get($opRecord, 'parties.party_2')
            ?? data_get($opRecord, 'party_2')
            ?? ''
        ));

        if ($partyFromOp === '') {
            Log::warning('FFR save aborted: party_1 unresolved from OP record', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve Party 1 from OP record. Please provide Party 1.',
            ], 422);
        }

        $propId = $validated['prop_id']
            ?? data_get($opRecord, 'prop_id')
            ?? data_get($sourceHistory, '0.prop_id');

        $inheritedRegNo = $this->firstFilledValue(
            $opRecord,
            ['regNo', 'reg_no', 'registration_number', 'deeds_serial_no']
        );
        $inheritedRegDate = $this->firstFilledValue(
            $opRecord,
            ['reg_date', 'regDate', 'deeds_date', 'transaction_date']
        );
        $inheritedRegTime = $this->firstFilledValue(
            $opRecord,
            ['reg_time', 'regTime', 'deeds_time', 'transaction_time']
        );

        $normalizeName = static function (?string $value): string {
            return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
        };




   


        // If holder/allottee name does not change, this is not a transfer.
        $hasNameChange = $normalizeName($partyFromOp) !== ''
            && $normalizeName($newParty2) !== ''
            && $normalizeName($partyFromOp) !== $normalizeName($newParty2);

        $resolvedTransactionType = $hasNameChange
            ? 'Transfer of Title (OP)'
            : 'Occupancy Permit (OP)';

        $resolvedInstrumentType = $hasNameChange
            ? 'Transfer of Title (OP)'
            : 'Occupancy Permit (OP)';

        Log::info('FFR save transaction resolution', [
            'source_file_no' => $sourceFileNo,
            'party_from_op' => $partyFromOp,
            'new_party_2' => $newParty2,
            'has_name_change' => $hasNameChange,
            'resolved_transaction_type' => $resolvedTransactionType,
            'resolved_instrument_type' => $resolvedInstrumentType,
            'inherited_reg_no' => $inheritedRegNo,
            'inherited_reg_date' => $inheritedRegDate,
            'inherited_reg_time' => $inheritedRegTime,
            'user_id' => Auth::id(),
        ]);
      
        $praPayload = [
            'prop_id' => $propId,
            'mlsFNo' => $sourceFileNo,
            'fileno' => $sourceFileNo,
            'transaction_type' => $resolvedTransactionType,
            'instrument_type' => $resolvedInstrumentType,
            'op_serial_number' => $validated['op_serial_number'] ?? data_get($opRecord, 'op_serial_number'),
            'regNo' => $inheritedRegNo,
            'reg_date' => $inheritedRegDate,
            'reg_time' => $inheritedRegTime,
            'land_use' => $validated['land_use'] ?? data_get($opRecord, 'land_use'),
            'location' => $validated['location'] ?? data_get($opRecord, 'location') ?? data_get($opRecord, 'property_description'),
            'property_description' => $validated['location'] ?? data_get($opRecord, 'property_description') ?? data_get($opRecord, 'location'),
            'plot_no' => data_get($opRecord, 'plot_no') ?? data_get($opRecord, 'plot_number'),
            'tp_no' => data_get($opRecord, 'tp_no'),
            'lgsaOrCity' => data_get($opRecord, 'lga') ?? data_get($opRecord, 'lgsaOrCity'),
            'source' => 'FFR Change of Name',
            'system_source' => 'OSSOPCHANGEOFNAME',
            
            'Grantor' => $partyFromOp,
            'Grantee' => $newParty2,
            'party_1' => $partyFromOp,
            'party_2' => $newParty2,
            'parties' => [
                'grantor' => $partyFromOp,
                'grantee' => $newParty2,
                'party_1' => $partyFromOp,
                'party_2' => $newParty2,
            ],
        ];

        // Pull geo fields from the mother OP record so the OSS table shows them
        $tpNo     = data_get($opRecord, 'tp_no');
        $plotNo   = data_get($opRecord, 'plot_no') ?? data_get($opRecord, 'plot_number');
        $lgaValue = data_get($opRecord, 'lga') ?? data_get($opRecord, 'lgsaOrCity');

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use (
                $praService,
                $praPayload,
                $sourceFileNo,
                $partyFromOp,
                $newParty2,
                $validated,
                $resolvedCustomerType,
                $tpNo,
                $plotNo,
                $lgaValue
            ) {
                $praRecord = $praService->createRecord($praPayload, Auth::id());
                $now = now();

                $indexingUpdated = $this->safeUpdateByLookup(
                    'file_indexings',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'file_title' => $newParty2,
                        'current_holder' => $newParty2,
                        'original_holder' => $partyFromOp,
                        'party_1' => $partyFromOp,
                        'party_2' => $newParty2,
                        'file_type' => $resolvedCustomerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $validated['land_use'] ?? null,
                        'residence_address' => $validated['residence_address'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'customer_type' => $resolvedCustomerType,
                        'is_updated' => 1,
                        'updated_by' => Auth::id(),
                        'updated_at' => $now,
                    ]
                );

                $customerUpdated = $this->safeUpdateByLookup(
                    'customers_staging',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'customer_name' => $newParty2,
                        'customer_type' => $resolvedCustomerType,
                        'updated_by' => Auth::id(),
                        'updated_at' => $now,
                    ]
                );

                if ($customerUpdated === 0) {
                    $inserted = $this->safeInsertRow(
                        'customers_staging',
                        [
                            'customer_type' => $resolvedCustomerType,
                            'file_number' => $sourceFileNo,
                            'customer_name' => $newParty2,
                            'status' => 'Active',
                            'created_by' => Auth::id(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );

                    if ($inserted) {
                        $customerUpdated = 1;
                    }
                }

                $mlsFileNoUpdated = $this->safeUpdateByLookup(
                    'mls_file_no',
                    [
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                    ],
                    [
                        'file_name' => $newParty2,
                        'customer_type' => $resolvedCustomerType,
                        'updated_at' => $now,
                    ]
                );

                // Build fileNumber update — always update name; only update geo fields when the
                // mother OP record has values (avoids overwriting existing data with nulls).
                $fileNumberData = [
                    'FileName' => $newParty2,
                    'customer_type' => $resolvedCustomerType,
                    'updated_at' => $now,
                ];
                if ($tpNo)     $fileNumberData['tp_no']   = $tpNo;
                if ($plotNo)   $fileNumberData['plot_no']  = $plotNo;
                if ($lgaValue) $fileNumberData['lga']      = $lgaValue;

                $fileNumberUpdated = $this->safeUpdateByLookup(
                    'fileNumber',
                    [
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                    ],
                    $fileNumberData
                );

                Log::info('FFR sync completed', [
                    'source_file_no' => $sourceFileNo,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'mls_file_no_updated' => $mlsFileNoUpdated,
                    'file_number_updated' => $fileNumberUpdated,
                    'resolved_customer_type' => $resolvedCustomerType,
                    'user_id' => Auth::id(),
                ]);

                return [
                    'pra' => $praRecord,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'mls_file_no_updated' => $mlsFileNoUpdated,
                    'file_number_updated' => $fileNumberUpdated,
                    'resolved_customer_type' => $resolvedCustomerType,
                ];
            });
        } catch (\Throwable $e) {
            $errorContext = [
                'source_file_no' => $sourceFileNo,
                'payload' => $validated,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('FFR save failed', $errorContext);
            $this->writeFfrDiagnostic('error', 'FFR save failed', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete FFR save operation.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'FFR saved successfully. PRA timeline and name fields were synchronized.',
            'data' => $result,
        ]);
    }

    /**
     * Look up the temp file number for a given MLS file number.
     */
    public function lookupTempFileno(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no'));
        if (!$fileNo) {
            return response()->json(['success' => false, 'tracking_id' => null]);
        }

        // Simple SELECT from grouping table to get tracking_id by awaiting_fileno
        $row = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', $fileNo)
            ->select('tracking_id')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => (bool) ($row && $row->tracking_id),
            'tracking_id' => $row->tracking_id ?? null,
        ]);
    }

    /**
     * Capture Existing flow for FFR when file number is not found in PRA.
        * Keeps the same file number (no extension/new serial), syncs local tables,
        * and stores the mapped Occupancy Permit (OP) PRA record for the file number.
     */
    public function captureFfrExisting(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no' => 'required|string|max:120',
            'file_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:100',
            'customer_type' => 'nullable|string|max:50',
            'land_use' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'purpose_id' => 'nullable|integer',
            'phone_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'op_type' => 'nullable|string|max:100',
            'op_serial_number' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:50',
            'page_no' => 'nullable|string|max:50',
            'volume_no' => 'nullable|string|max:50',
            'transaction_date' => 'nullable|date',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'nullable|string|max:255',
            'tracking_id' => 'nullable|string|max:100',
            'ffr_first_pra_prop_id' => 'nullable|integer',
            'ffr_temp_fileno' => 'nullable|string|max:100',
            'from_op_flow' => 'nullable|string|max:10',
        ]);

        $sourceFileNo = trim((string) $validated['source_file_no']);
        $fileName = trim((string) $validated['file_name']);
        $location = trim((string) ($validated['location'] ?? ''));
        $lga = trim((string) ($validated['lga'] ?? 'Unknown'));
        $customerType = trim((string) ($validated['customer_type'] ?? 'Individual'));
        $landUse = trim((string) ($validated['land_use'] ?? ''));
        $purpose = trim((string) ($validated['purpose'] ?? ''));
        $purposeId = $validated['purpose_id'] ?? null;
        $phoneNo = trim((string) ($validated['phone_no'] ?? ''));
        $address = trim((string) ($validated['address'] ?? ''));
        $plotNo = trim((string) ($validated['plot_no'] ?? ''));
        $tpNo = trim((string) ($validated['tp_no'] ?? ''));
        $opType = trim((string) ($validated['op_type'] ?? 'OP Resettlement'));
        $opSerialNumber = trim((string) ($validated['op_serial_number'] ?? ''));
        $registrationNumber = trim((string) ($validated['registration_number'] ?? ''));
        $serialNo = trim((string) ($validated['serial_no'] ?? ''));
        $pageNo = trim((string) ($validated['page_no'] ?? ''));
        $volumeNo = trim((string) ($validated['volume_no'] ?? ''));
        $transactionDate = trim((string) ($validated['transaction_date'] ?? ''));
        $party1 = trim((string) ($validated['party_1'] ?? ''));
        $party2 = trim((string) ($validated['party_2'] ?? $fileName));
        $trackingId = trim((string) ($validated['tracking_id'] ?? ''));
        $ffrFirstPraPropId = $validated['ffr_first_pra_prop_id'] ?? null;
        $ffrTempFileno = trim((string) ($validated['ffr_temp_fileno'] ?? ''));

        $sourceHistory = $praService->findAllByFileNumber($sourceFileNo);
        $motherOpRecord = $this->resolveMotherOpRecord($sourceHistory);

        if (!$motherOpRecord) {
            Log::warning('FFR existing capture: mother OP record not found, using request payload fallbacks', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);
        }

        $inheritedRegNo = $this->firstFilledValue(
            $motherOpRecord,
            ['regNo', 'reg_no', 'registration_number', 'deeds_serial_no']
        );
        $inheritedRegDate = $this->firstFilledValue(
            $motherOpRecord,
            ['reg_date', 'regDate', 'deeds_date', 'transaction_date']
        );
        $inheritedRegTime = $this->firstFilledValue(
            $motherOpRecord,
            ['reg_time', 'regTime', 'deeds_time', 'transaction_time']
        );
        $inheritedPlotNo = $this->firstFilledValue($motherOpRecord, ['plot_no', 'plot_number']);
        $inheritedTpNo = $this->firstFilledValue($motherOpRecord, ['tp_no']);
        $inheritedLga = $this->firstFilledValue($motherOpRecord, ['lga', 'lgsaOrCity']);
        $inheritedLocation = $this->firstFilledValue($motherOpRecord, ['location', 'property_description']);
        $inheritedLandUse = $this->firstFilledValue($motherOpRecord, ['land_use']);
        $inheritedOpSerialNumber = $this->firstFilledValue($motherOpRecord, ['op_serial_number']);

        $resolvedRegistrationNumber = $inheritedRegNo ?: $registrationNumber;
        $resolvedRegDate = $inheritedRegDate;
        $resolvedRegTime = $inheritedRegTime;
        $resolvedPlotNo = $plotNo !== '' ? $plotNo : ($inheritedPlotNo ?? '');
        $resolvedTpNo = $tpNo !== '' ? $tpNo : ($inheritedTpNo ?? '');
        $resolvedLga = $lga !== '' && strtolower($lga) !== 'unknown' ? $lga : ($inheritedLga ?? $lga);
        $resolvedLocation = $location !== '' ? $location : ($inheritedLocation ?? '');
        $resolvedLandUse = $landUse !== '' ? $landUse : ($inheritedLandUse ?? '');
        $resolvedOpSerialNumber = $opSerialNumber !== '' ? $opSerialNumber : ($inheritedOpSerialNumber ?? '');
        $resolvedPageNo = $pageNo !== '' ? $pageNo : ($serialNo !== '' ? $serialNo : '');
        $resolvedTransactionDate = $transactionDate !== '' ? $transactionDate : null;

        // Step 2 branching:
        // When from_op_flow=1, this is always a Transfer of Title (OP).
        // The OP row was already created by directOpCapture; this row is the transfer.
        // party_1 = original allottee (from OP), party_2 = new holder (file_name entered by user).
        $fromOpFlow = trim((string) ($validated['from_op_flow'] ?? '')) === '1';

        $normalizedName = static function (?string $value): string {
            return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
        };

        if ($fromOpFlow) {
            // From OP flow: always Transfer of Title
            $originalAllottee = $party1 !== '' ? $party1 : 'Unknown';
            $enteredAllottee = $fileName;
            $hasNameChange = true;
        } else {
            $originalAllottee = $party2 !== '' ? $party2 : $fileName;
            $enteredAllottee = $fileName !== '' ? $fileName : $party2;
            $hasNameChange = $originalAllottee !== ''
                && $enteredAllottee !== ''
                && $normalizedName($originalAllottee) !== $normalizedName($enteredAllottee);
        }

        $resolvedTransactionType = ($hasNameChange || $fromOpFlow)
            ? 'Transfer Of Title (OP)'
            : 'Occupancy Permit (OP)';
        $resolvedInstrumentType = $resolvedTransactionType;
        $resolvedGrantor = ($hasNameChange || $fromOpFlow)
            ? $originalAllottee
            : ($party1 !== '' ? $party1 : 'Kano State Government');
        $resolvedGrantee = $hasNameChange
            ? $enteredAllottee
            : ($originalAllottee !== '' ? $originalAllottee : $enteredAllottee);

        Log::info('FFR existing capture flow resolved', [
            'source_file_no' => $sourceFileNo,
            'has_name_change' => $hasNameChange,
            'original_allottee' => $originalAllottee,
            'entered_allottee' => $enteredAllottee,
            'transaction_type' => $resolvedTransactionType,
            'instrument_type' => $resolvedInstrumentType,
            'inherited_reg_no' => $inheritedRegNo,
            'inherited_reg_date' => $inheritedRegDate,
            'inherited_reg_time' => $inheritedRegTime,
            'user_id' => Auth::id(),
        ]);

        Log::info('FFR existing capture initiated', [
            'source_file_no' => $sourceFileNo,
            'op_type' => $opType,
            'user_id' => Auth::id(),
        ]);

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use (
                $praService,
                $trackingId,
                $ffrFirstPraPropId,
                $ffrTempFileno,
                $sourceFileNo,
                $fileName,
                $location,
                $lga,
                $customerType,
                $landUse,
                $purpose,
                $purposeId,
                $phoneNo,
                $address,
                $resolvedPlotNo,
                $resolvedTpNo,
                $resolvedLga,
                $resolvedLocation,
                $resolvedLandUse,
                $opType,
                $resolvedOpSerialNumber,
                $resolvedRegistrationNumber,
                $resolvedRegDate,
                $resolvedRegTime,
                $resolvedTransactionDate,
                $serialNo,
                $resolvedPageNo,
                $volumeNo,
                $party1,
                $party2,
                $resolvedTransactionType,
                $resolvedInstrumentType,
                $resolvedGrantor,
                $resolvedGrantee,
                $hasNameChange,
                $originalAllottee,
                $enteredAllottee
            ) {
                $now = now();
                $userId = Auth::id();

                // Use tracking_id from grouping table; fallback to generated if empty
                if (empty($trackingId)) {
                    $trackingId = 'FFR-' . now()->format('YmdHis') . '-' . random_int(100, 999);
                }

                $fileNumberUpdated = $this->safeUpdateByLookup(
                    'fileNumber',
                    [
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                    ],
                    [
                        'FileName' => $fileName,
                        'tracking_id' => $trackingId,
                        'location' => $resolvedLocation,
                        'lga' => $resolvedLga,
                        'customer_type' => $customerType,
                        'phone_no' => $phoneNo,
                        'address' => $address,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'purpose_id' => $purposeId,
                        'type' => 'Captured',
                        'SOURCE' => 'FFR_Existing_Capture',
                        'updated_at' => $now,
                    ]
                );

                if ($fileNumberUpdated === 0) {
                    $inserted = $this->safeInsertRow('fileNumber', [
                        'tracking_id' => $trackingId,
                        'mlsfNo' => $sourceFileNo,
                        'FileName' => $fileName,
                        'location' => $resolvedLocation,
                        'lga' => $resolvedLga,
                        'customer_type' => $customerType,
                        'phone_no' => $phoneNo,
                        'address' => $address,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'purpose_id' => $purposeId,
                        'type' => 'Captured',
                        'SOURCE' => 'FFR_Existing_Capture',
                        'created_by' => (Auth::user()->name ?? Auth::user()->email ?? 'System'),
                        'is_deleted' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $fileNumberUpdated = 1;
                    }
                }

                $indexingUpdated = $this->safeUpdateByLookup(
                    'file_indexings',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'file_title' => $fileName,
                        'tracking_id' => $trackingId,
                        'current_holder' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                        'file_type' => $customerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $resolvedLandUse ?: null,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'is_updated' => 1,
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]
                );

                if ($indexingUpdated === 0) {
                    $inserted = $this->safeInsertRow('file_indexings', [
                        'file_number' => $sourceFileNo,
                        'tracking_id' => $trackingId,
                        'file_title' => $fileName,
                        'current_holder' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                        'file_type' => $customerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $resolvedLandUse ?: null,
                        'lga' => $resolvedLga,
                        'location' => $resolvedLocation,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'is_updated' => 1,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $indexingUpdated = 1;
                    }
                }

                $customerUpdated = $this->safeUpdateByLookup(
                    'customers_staging',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'customer_name' => $resolvedGrantee,
                        'customer_type' => $customerType,
                        'status' => 'Active',
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]
                );

                if ($customerUpdated === 0) {
                    $inserted = $this->safeInsertRow('customers_staging', [
                        'file_number' => $sourceFileNo,
                        'customer_name' => $resolvedGrantee,
                        'customer_type' => $customerType,
                        'status' => 'Active',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $customerUpdated = 1;
                    }
                }

                $praOpRecord = $praService->createRecord([
                    'prop_id' => $ffrFirstPraPropId ?: null,
                    'mlsFNo' => $sourceFileNo,
                    'fileno' => $sourceFileNo,
                    'temp_fileno' => $ffrTempFileno ?: null,
                    'transaction_type' => $resolvedTransactionType,
                    'instrument_type' => $resolvedInstrumentType,
                    'transaction_date' => $resolvedTransactionDate ?: null,
                    'source' => 'FFR Existing Capture',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'op_type' => $opType,
                    'op_serial_number' => $resolvedOpSerialNumber ?: null,
                    'regNo' => $resolvedRegistrationNumber ?: null,
                    'reg_date' => $resolvedRegDate ?: null,
                    'reg_time' => $resolvedRegTime ?: null,
                    'serialNo' => $serialNo ?: null,
                    'pageNo' => ($resolvedPageNo ?: $serialNo) ?: null,
                    'volumeNo' => $volumeNo ?: null,
                    'location' => $resolvedLocation,
                    'property_description' => $resolvedLocation,
                    'land_use' => $resolvedLandUse ?: null,
                    'purpose' => $purpose ?: null,
                    'plot_no' => $resolvedPlotNo ?: null,
                    'tp_no' => $resolvedTpNo ?: null,
                    'lgsaOrCity' => $resolvedLga ?: null,
                    'phone_no' => $phoneNo ?: null,
                    'address' => $address ?: null,
                    'Grantor' => $resolvedGrantor,
                    'Grantee' => $resolvedGrantee,
                    'party_1' => $resolvedGrantor,
                    'party_2' => $resolvedGrantee,
                    'parties' => [
                        'grantor' => $resolvedGrantor,
                        'grantee' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                    ],
                ], $userId);

                return [
                    'scenario' => $hasNameChange ? 'name-change' : 'no-name-change',
                    'resolved_data' => [
                        'temp_fileno' => $ffrTempFileno ?: null,
                        'mlsFNo' => $sourceFileNo,
                        'Grantor' => $resolvedGrantor,
                        'party_1' => $resolvedGrantor,
                        'Grantee' => $resolvedGrantee,
                        'party_2' => $resolvedGrantee,
                        'instrument_type' => $resolvedInstrumentType,
                        'transaction_type' => $resolvedTransactionType,
                        'prop_id' => $ffrFirstPraPropId ?: null,
                        'inherited_reg_no' => $resolvedRegistrationNumber ?: null,
                        'inherited_reg_date' => $resolvedRegDate ?: null,
                        'inherited_reg_time' => $resolvedRegTime ?: null,
                        'transaction_date' => $resolvedTransactionDate ?: null,
                        'source_allottee' => $originalAllottee,
                        'entered_allottee' => $enteredAllottee,
                    ],
                    'file_number_updated' => $fileNumberUpdated,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'pra_record' => $praOpRecord,
                ];
            });
        } catch (\Throwable $e) {
            $errorContext = [
                'source_file_no' => $sourceFileNo,
                'payload' => $validated,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('FFR existing capture failed', $errorContext);
            $this->writeFfrDiagnostic('error', 'FFR existing capture failed', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete existing capture synchronization.',
                'error' => $e->getMessage(),
            ], 500);
        }

        Log::info('FFR existing capture completed', [
            'source_file_no' => $sourceFileNo,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Existing capture synchronized: OP data mapped to file number, indexing, customer, and PRA.',
            'data' => $result,
        ]);
    }

    /**
     * Direct OP Capture — for indexed files with no OP in PRA.
     *
     * Creates two PRA rows in a single transaction:
     *   Row 1 (OP):                Party 1 = Kano State Government, Party 2 = allottee
     *   Row 2 (Transfer of Title): Party 1 = allottee,              Party 2 = current holder (from fileNumber.FileName)
     *
     * Also syncs file_indexings, customers_staging with the new holder data.
     */
    public function directOpCapture(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no'      => 'required|string|max:120',
            'allottee'            => 'required|string|max:255',
            'current_holder'      => 'nullable|string|max:255',
            'op_type'             => 'nullable|string|max:100',
            'op_serial_number'    => 'nullable|string|max:100',
            'transaction_date'    => 'nullable|date',
            'registration_number' => 'nullable|string|max:100',
            'serial_no'           => 'nullable|string|max:50',
            'page_no'             => 'nullable|string|max:50',
            'volume_no'           => 'nullable|string|max:50',
            'location'            => 'nullable|string|max:500',
            'plot_no'             => 'nullable|string|max:100',
            'tp_no'               => 'nullable|string|max:100',
            'lga'                 => 'nullable|string|max:100',
            'land_use'            => 'nullable|string|max:100',
            'temp_fileno'         => 'nullable|string|max:120',
        ]);

        $sourceFileNo   = trim((string) $validated['source_file_no']);
        $allottee        = trim((string) $validated['allottee']);
        $currentHolder   = trim((string) $validated['current_holder']);
        $opType          = trim((string) ($validated['op_type'] ?? 'OP Resettlement'));
        $opSerialNumber  = trim((string) ($validated['op_serial_number'] ?? ''));
        $txDate          = trim((string) ($validated['transaction_date'] ?? ''));
        $regNo           = trim((string) ($validated['registration_number'] ?? ''));
        $serialNo        = trim((string) ($validated['serial_no'] ?? ''));
        $pageNo          = trim((string) ($validated['page_no'] ?? ''));
        $volumeNo        = trim((string) ($validated['volume_no'] ?? ''));
        $location        = trim((string) ($validated['location'] ?? ''));
        $plotNo          = trim((string) ($validated['plot_no'] ?? ''));
        $tpNo            = trim((string) ($validated['tp_no'] ?? ''));
        $lga             = trim((string) ($validated['lga'] ?? ''));
        $landUse         = trim((string) ($validated['land_use'] ?? ''));
        $tempFileno      = trim((string) ($validated['temp_fileno'] ?? ''));

        $resolvedCurrentHolder = $this->resolveIndexedFileTitleByFileNo($sourceFileNo, $currentHolder);
        if ($resolvedCurrentHolder === '') {
            // Fallback: use allottee name if current holder cannot be resolved
            $resolvedCurrentHolder = $allottee;
        }

        $customerType = $this->resolveCustomerTypeFromName($resolvedCurrentHolder);

        Log::info('FFR direct OP capture initiated', [
            'source_file_no' => $sourceFileNo,
            'allottee'       => $allottee,
            'current_holder' => $resolvedCurrentHolder,
            'op_type'        => $opType,
            'user_id'        => Auth::id(),
        ]);

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use (
                $praService, $sourceFileNo, $tempFileno, $allottee, $currentHolder,
                $resolvedCurrentHolder, $opType, $opSerialNumber, $txDate,
                $regNo, $serialNo, $pageNo, $volumeNo, $location, $plotNo,
                $tpNo, $lga, $landUse, $customerType
            ) {
                $userId = Auth::id();
                $now = now();

                // ── Row 1: Occupancy Permit ──
                // OP row uses the system temp file number; mlsFNo is null for OP.
                $opFileNo = $tempFileno ?: $sourceFileNo;
                $opPra = $praService->createRecord([
                    'mlsFNo'               => null,
                    'fileno'               => $opFileNo,
                    'temp_fileno'          => $tempFileno ?: null,
                    'transaction_type'     => 'Occupancy Permit (OP)',
                    'instrument_type'      => 'Occupancy Permit (OP)',
                    'op_type'              => $opType,
                    'op_serial_number'     => $opSerialNumber ?: null,
                    'transaction_date'     => $txDate ?: null,
                    'regNo'                => $regNo ?: null,
                    'serialNo'             => $serialNo ?: null,
                    'pageNo'               => ($pageNo ?: $serialNo) ?: null,
                    'volumeNo'             => $volumeNo ?: null,
                    'location'             => $location ?: null,
                    'property_description' => $location ?: null,
                    'plot_no'              => $plotNo ?: null,
                    'tp_no'                => $tpNo ?: null,
                    'lgsaOrCity'           => $lga ?: null,
                    'land_use'             => $landUse ?: null,
                    'source'               => 'FFR Direct OP Capture',
                    'system_source'        => 'OSSOPCHANGEOFNAME',
                    'Grantor'              => 'Kano State Government',
                    'Grantee'              => $allottee,
                    'party_1'              => 'Kano State Government',
                    'party_2'              => $allottee,
                    'parties'              => [
                        'grantor'  => 'Kano State Government',
                        'grantee'  => $allottee,
                        'party_1'  => 'Kano State Government',
                        'party_2'  => $allottee,
                    ],
                ], $userId);

                $opPropId = data_get($opPra, 'prop_id');
                $opRowId  = data_get($opPra, 'id');

                // Fallback: if prop_id not in response, query by the row id
                if (empty($opPropId) && !empty($opRowId)) {
                    $opPropId = DB::connection('sqlsrv')
                        ->table('pra')
                        ->where('id', $opRowId)
                        ->value('prop_id');
                }

                // Fallback 2: query latest by file number (temp or source)
                if (empty($opPropId)) {
                    $opPropId = DB::connection('sqlsrv')
                        ->table('pra')
                        ->where('mlsFNo', $opFileNo)
                        ->orderByDesc('id')
                        ->value('prop_id');
                }

                Log::info('FFR direct OP capture: Row 1 (OP) created', [
                    'op_row_id'  => $opRowId,
                    'op_prop_id' => $opPropId,
                ]);

                if (empty($opPropId)) {
                    throw new \RuntimeException('Failed to resolve prop_id after creating OP PRA row.');
                }

                $opInheritedLocation = data_get($opPra, 'location') ?: $location;
                $opInheritedPropertyDescription = data_get($opPra, 'property_description')
                    ?: data_get($opPra, 'location')
                    ?: $location;
                $opInheritedPlotNo = data_get($opPra, 'plot_no') ?: $plotNo;
                $opInheritedTpNo = data_get($opPra, 'tp_no') ?: $tpNo;
                $opInheritedLga = data_get($opPra, 'lgsaOrCity') ?: $lga;
                $opInheritedLandUse = data_get($opPra, 'land_use') ?: $landUse;
                $opInheritedOpType = data_get($opPra, 'op_type') ?: $opType;
                $opInheritedOpSerial = data_get($opPra, 'op_serial_number') ?: $opSerialNumber;
                $opInheritedTransactionDate = data_get($opPra, 'transaction_date') ?: ($txDate ?: null);
                $opInheritedRegDate = data_get($opPra, 'reg_date') ?: null;
                $opInheritedRegTime = data_get($opPra, 'reg_time') ?: null;

                // ── Row 2: Transfer of Title ──
                // For indexed files: allottee becomes party_1, current holder becomes party_2
                $totPra = $praService->createRecord([
                    'mlsFNo'               => $sourceFileNo,
                    'fileno'               => $sourceFileNo,
                    'temp_fileno'          => $tempFileno ?: null,
                    'prop_id'              => $opPropId,
                    'transaction_type'     => 'Transfer of Title (OP)',
                    'instrument_type'      => 'Transfer of Title (OP)',
                    'op_type'              => $opInheritedOpType,
                    'op_serial_number'     => $opInheritedOpSerial ?: null,
                    'transaction_date'     => $opInheritedTransactionDate,
                    'regNo'                => '0/0/0',
                    'serialNo'             => '0',
                    'pageNo'               => '0',
                    'volumeNo'             => '0',
                    'reg_date'             => $opInheritedRegDate,
                    'reg_time'             => $opInheritedRegTime,
                    'location'             => $opInheritedLocation ?: null,
                    'property_description' => $opInheritedPropertyDescription ?: null,
                    'plot_no'              => $opInheritedPlotNo ?: null,
                    'tp_no'                => $opInheritedTpNo ?: null,
                    'lgsaOrCity'           => $opInheritedLga ?: null,
                    'land_use'             => $opInheritedLandUse ?: null,
                    'source'               => 'FFR Direct OP Capture',
                    'system_source'        => 'OSSOPCHANGEOFNAME',
                    'customer_type'        => $customerType,
                    'Grantor'              => $allottee,
                    'Grantee'              => $resolvedCurrentHolder,
                    'party_1'              => $allottee,
                    'party_2'              => $resolvedCurrentHolder,
                    'parties'              => [
                        'grantor'  => $allottee,
                        'grantee'  => $resolvedCurrentHolder,
                        'party_1'  => $allottee,
                        'party_2'  => $resolvedCurrentHolder,
                    ],
                ], $userId);

                Log::info('FFR direct OP capture: Row 2 (Transfer of Title) created', [
                    'tot_row_id' => data_get($totPra, 'id'),
                    'prop_id'    => $opPropId,
                ]);

                return [
                    'op_pra'              => $opPra,
                    'tot_pra'             => $totPra,
                    'prop_id'             => $opPropId,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('FFR direct OP capture failed', [
                'source_file_no' => $sourceFileNo,
                'error'          => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'user_id'        => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to capture OP record.',
                'error'   => $e->getMessage(),
            ], 500);
        }

        Log::info('FFR direct OP capture completed', [
            'source_file_no' => $sourceFileNo,
            'prop_id'        => $result['prop_id'] ?? null,
            'user_id'        => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OP and Transfer of Title captured successfully.',
            'data'    => $result,
        ]);
    }

    private function resolveIndexedFileTitleByFileNo(string $sourceFileNo, string $fallback = ''): string
    {
        $fileNo = strtoupper(trim($sourceFileNo));
        if ($fileNo === '') {
            return strtoupper(trim($fallback));
        }

        $fromFileNumber = strtoupper(trim((string) (DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereRaw('UPPER(mlsfNo) = ?', [$fileNo])
            ->value('FileName') ?? '')));
        if ($fromFileNumber !== '') {
            return $fromFileNumber;
        }

        $indexingColumns = $this->sqlsrvColumnMap('file_indexings');
        $titleColumn = $indexingColumns['file_title'] ?? ($indexingColumns['file_name'] ?? null);
        if ($titleColumn !== null) {
            $fromIndexing = strtoupper(trim((string) (DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereRaw('UPPER(file_number) = ?', [$fileNo])
                ->value($titleColumn) ?? '')));
            if ($fromIndexing !== '') {
                return $fromIndexing;
            }
        }

        $mlsColumns = $this->sqlsrvColumnMap('mls_file_no');
        $mlsFileNoColumn = $mlsColumns['full_file_number'] ?? null;
        $mlsFileNameColumn = $mlsColumns['file_name'] ?? null;
        if ($mlsFileNoColumn !== null && $mlsFileNameColumn !== null) {
            $fromMls = strtoupper(trim((string) (DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->whereRaw('UPPER([' . $mlsFileNoColumn . ']) = ?', [$fileNo])
                ->value($mlsFileNameColumn) ?? '')));
            if ($fromMls !== '') {
                return $fromMls;
            }
        }

        return strtoupper(trim($fallback));
    }

    private function safeUpdateByLookup(string $table, array $lookupCandidates, array $payload): int
    {
        $columns = $this->sqlsrvColumnMap($table);
        if (empty($columns)) {
            Log::warning('FFR safe update skipped: table has no discoverable columns', [
                'table' => $table,
            ]);
            return 0;
        }

        $filteredPayload = [];
        foreach ($payload as $column => $value) {
            if (isset($columns[strtolower($column)])) {
                $filteredPayload[$columns[strtolower($column)]] = $value;
            }
        }

        if (empty($filteredPayload)) {
            Log::warning('FFR safe update skipped: payload has no matching columns', [
                'table' => $table,
                'payload_keys' => array_keys($payload),
            ]);
            return 0;
        }

        foreach ($lookupCandidates as $column => $value) {
            $lookup = strtolower($column);
            if (!isset($columns[$lookup])) {
                continue;
            }

            return DB::connection('sqlsrv')
                ->table($table)
                ->where($columns[$lookup], $value)
                ->update($filteredPayload);
        }

        Log::warning('FFR safe update skipped: no matching lookup column found', [
            'table' => $table,
            'lookup_candidates' => array_keys($lookupCandidates),
        ]);

        return 0;
    }

    private function resolveMotherOpRecord(array $history): ?array
    {
        $candidates = collect($history)
            ->filter(fn($row) => is_array($row))
            ->filter(function (array $row): bool {
                $transactionType = strtolower(trim((string) (
                    $row['transaction_type']
                    ?? $row['instrument_type']
                    ?? ''
                )));

                if ($transactionType === '') {
                    return false;
                }

                $looksLikeOp = str_contains($transactionType, 'occupancy permit')
                    || preg_match('/\bop\b/i', $transactionType) === 1;

                if (!$looksLikeOp) {
                    return false;
                }

                // Mother OP should be the base OP transaction, not transfer rows like
                // "Transfer of Title (OP)".
                return !str_contains($transactionType, 'transfer');
            })
            ->values()
            ->all();

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return $this->rowTimestampForMotherSelection($left) <=> $this->rowTimestampForMotherSelection($right);
        });

        return $candidates[0] ?? null;
    }

    private function firstFilledValue(?array $source, array $keys): ?string
    {
        if (!$source) {
            return null;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = trim((string) $source[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function rowTimestampForMotherSelection(array $row): int
    {
        $dateValue = $this->firstFilledValue($row, [
            'reg_date',
            'regDate',
            'deeds_date',
            'transaction_date',
            'created_at',
            'updated_at',
        ]);

        if ($dateValue === null) {
            return PHP_INT_MAX;
        }

        $timeValue = $this->firstFilledValue($row, ['reg_time', 'regTime', 'deeds_time', 'transaction_time']);
        $candidate = $dateValue . ($timeValue ? (' ' . $timeValue) : '');
        $timestamp = strtotime($candidate);

        if ($timestamp === false) {
            $timestamp = strtotime($dateValue);
        }

        return $timestamp === false ? PHP_INT_MAX : $timestamp;
    }

    private function safeInsertRow(string $table, array $payload): bool
    {
        $columns = $this->sqlsrvColumnMap($table);
        if (empty($columns)) {
            Log::warning('FFR safe insert skipped: table has no discoverable columns', [
                'table' => $table,
            ]);
            return false;
        }

        $filteredPayload = [];
        foreach ($payload as $column => $value) {
            if (isset($columns[strtolower($column)])) {
                $filteredPayload[$columns[strtolower($column)]] = $value;
            }
        }

        if (empty($filteredPayload)) {
            Log::warning('FFR safe insert skipped: payload has no matching columns', [
                'table' => $table,
                'payload_keys' => array_keys($payload),
            ]);
            return false;
        }

        return (bool) DB::connection('sqlsrv')->table($table)->insert($filteredPayload);
    }

    private function sqlsrvColumnMap(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $list = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($table);
        } catch (\Throwable $e) {
            Log::warning('FFR schema inspection failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            $list = [];
        }

        $map = [];
        foreach ($list as $column) {
            $map[strtolower((string) $column)] = (string) $column;
        }

        $cache[$table] = $map;
        return $cache[$table];
    }

    private function writeFfrDiagnostic(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = storage_path('logs/ffr-change-of-name.log');
            $line = json_encode([
                'timestamp' => now()->toDateTimeString(),
                'level' => strtoupper($level),
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES);

            if ($line === false) {
                $line = sprintf(
                    '{"timestamp":"%s","level":"%s","message":"%s"}',
                    now()->toDateTimeString(),
                    strtoupper($level),
                    addslashes($message)
                );
            }

            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $exception) {
            Log::warning('FFR diagnostic fallback logging failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveCustomerTypeFromName(string $name): string
    {
        $raw = trim($name);
        if ($raw === '') {
            return 'Individual';
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $raw));

        $corporateTokens = [
            'ltd', 'limited', 'plc', 'inc', 'llc', 'company', 'co.', 'corp', 'corporate',
            'enterprise', 'ventures', 'bank', 'foundation', 'ministry', 'government',
            'authority', 'agency', 'board', 'association', 'union', 'church', 'mosque',
            'nig.', 'nigeria', 'nig '
        ];

        foreach ($corporateTokens as $token) {
            if ($token !== '' && str_contains($normalized, $token)) {
                return 'Corporate';
            }
        }

        $multipleSignals = [
            'multiple', 'multiple owners', 'joint', 'co-owner', 'co owners', 'co-owners',
            'partners', 'partnership'
        ];

        foreach ($multipleSignals as $signal) {
            if ($signal !== '' && str_contains($normalized, $signal)) {
                return 'Multiple';
            }
        }

        if (preg_match('/\s(?:and|&)\s/i', $raw)) {
            return 'Multiple';
        }

        if (preg_match('/[\/,;]+/', $raw)) {
            return 'Multiple';
        }

        return 'Individual';
    }

    /**
     * Save Verification form data.
     */
    public function saveVerification(Request $request): JsonResponse
    {
        $request->validate([
            'record_id'        => 'required|integer',
            'applicant_name'   => 'required|string|max:255',
            'passport_photo'   => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $recordId = (int) $request->input('record_id');

        $row = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $recordId)
            ->first();

        // Handle passport photo upload
        $passportPath = $row->passport_photo ?? null;
        if ($request->hasFile('passport_photo')) {
            $file = $request->file('passport_photo');
            $filename = 'ver_' . $recordId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $passportPath = $file->storeAs('public/oss_verifications/passports', $filename);
        }

        $data = [
            'instrument_capture_id' => $recordId,
            'applicant_name'    => (string) $request->input('applicant_name', ''),
            'applicant_address' => (string) $request->input('address', ''),
            'applicant_phone'   => (string) $request->input('phone', ''),
            'applicant_email'   => (string) $request->input('email', ''),
            'id_type'           => (string) $request->input('id_type', ''),
            'original_allottee' => (string) $request->input('original_allottee', ''),
            'op_number'         => (string) $request->input('op_number', ''),
            'plot_no'           => (string) $request->input('plot_plan', '') ? explode('/', (string) $request->input('plot_plan', ''))[0] ?? '' : '',
            'plan_no'           => (string) $request->input('plot_plan', '') ? (explode('/', (string) $request->input('plot_plan', ''))[1] ?? '') : '',
            'location'          => (string) $request->input('location', ''),
            'recommendation'    => (string) $request->input('recommendation', ''),
            'chairman_name'     => (string) $request->input('chairman_name', ''),
            'passport_photo'    => $passportPath,
            'updated_at'        => now(),
        ];

        if ($row) {
            DB::connection('sqlsrv')
                ->table('oss_verifications')
                ->where('id', $row->id)
                ->update($data);
        } else {
            $data['captured_by'] = Auth::id();
            $data['created_at']  = now();
            DB::connection('sqlsrv')
                ->table('oss_verifications')
                ->insert($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification saved successfully.',
        ]);
    }

    /**
     * Print the Change of Ownership form.
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printChangeOfOwnership(Request $request): View
    {
        $data = [
            'file_no'          => strtoupper((string) $request->input('file_no', '')),
            'op_number'        => strtoupper((string) $request->input('op_number', '')),
            'location'         => strtoupper((string) $request->input('location', '')),
            'plot_no'          => strtoupper((string) $request->input('plot_no', '')),
            'plan_no'          => strtoupper((string) $request->input('plan_no', '')),
            'date_of_issuance' => (string) $request->input('date_of_issuance', ''),
            'original_name'    => strtoupper((string) $request->input('original_name', '')),
            'original_address' => (string) $request->input('original_address', ''),
            'original_phone'   => (string) $request->input('original_phone', ''),
            'current_name'     => strtoupper((string) $request->input('current_name', '')),
            'current_address'  => (string) $request->input('current_address', ''),
            'current_phone'    => (string) $request->input('current_phone', ''),
            'ownership_method' => (string) $request->input('ownership_method', ''),
        ];

        return view('lands_one_stop_shop.print.print_change', compact('data'));
    }

    /**
     * Print the Verification form (FORM LN-03A).
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printVerification(Request $request): View
    {
        $data = [
            'applicant_name'    => strtoupper((string) $request->input('applicant_name', '')),
            'applicant_address' => (string) $request->input('applicant_address', ''),
            'applicant_phone'   => (string) $request->input('applicant_phone', ''),
            'applicant_email'   => (string) $request->input('applicant_email', ''),
            'id_type'           => (string) $request->input('id_type', ''),
            'original_allottee' => strtoupper((string) $request->input('original_allottee', '')),
            'op_number'         => strtoupper((string) $request->input('op_number', '')),
            'plot_no'           => strtoupper((string) $request->input('plot_no', '')),
            'plan_no'           => strtoupper((string) $request->input('plan_no', '')),
            'location'          => strtoupper((string) $request->input('location', '')),
            'recommendation'    => (string) $request->input('recommendation', ''),
            'chairman_name'     => (string) $request->input('chairman_name', ''),
            'passport_photo_url' => '',
        ];

        // Look up saved passport photo from DB
        $recordId = (int) $request->input('record_id', 0);
        if ($recordId) {
            $row = DB::connection('sqlsrv')
                ->table('oss_verifications')
                ->where('instrument_capture_id', $recordId)
                ->first();
            if ($row && !empty($row->passport_photo)) {
                $data['passport_photo_url'] = asset(str_replace('public/', 'storage/', $row->passport_photo));
            }
        }

        return view('lands_one_stop_shop.print.print_verification', compact('data'));
    }

    /**
     * Print the Verification form directly from a previously saved verification record.
     */
    public function printVerificationByRecord(int $id): View
    {
        $row = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $id)
            ->first();

        abort_unless($row, 404, 'Verification not found for this record.');

        $data = [
            'applicant_name' => strtoupper((string) ($row->applicant_name ?? '')),
            'applicant_address' => (string) ($row->applicant_address ?? ''),
            'applicant_phone' => (string) ($row->applicant_phone ?? ''),
            'applicant_email' => (string) ($row->applicant_email ?? ''),
            'id_type' => (string) ($row->id_type ?? ''),
            'original_allottee' => strtoupper((string) ($row->original_allottee ?? '')),
            'op_number' => strtoupper((string) ($row->op_number ?? '')),
            'plot_no' => strtoupper((string) ($row->plot_no ?? '')),
            'plan_no' => strtoupper((string) ($row->plan_no ?? '')),
            'location' => strtoupper((string) ($row->location ?? '')),
            'recommendation' => (string) ($row->recommendation ?? ''),
            'chairman_name' => (string) ($row->chairman_name ?? ''),
            'passport_photo_url' => '',
        ];

        if (!empty($row->passport_photo)) {
            $data['passport_photo_url'] = asset(str_replace('public/', 'storage/', (string) $row->passport_photo));
        }

        return view('lands_one_stop_shop.print.print_verification', compact('data'));
    }

    /**
     * Search instrument_capture records for OP Resettlement
     * (by allottee, registration number, or OP serial number).
     */
    public function searchInstrumentCaptures(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $query = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->select([
                'id',
                'instrument_type',
                'mlsFNo',
                'temp_fileno',
                'land_use',
                'registration_number',
                'party_2_name',
                'property_description',
                'plot_number',
                'lga',
                'op_serial_number',
                'op_type',
                'tp_no',
            ])
            ->where('op_type', 'OP Resettlement')
            ->where(function ($q) {
                $q->where('instrument_type', 'Occupancy Permit (OP)')
                  ->orWhere('instrument_type', 'Occupancy Permit');
            });

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('party_2_name', 'LIKE', "%{$term}%")
                  ->orWhere('registration_number', 'LIKE', "%{$term}%")
                  ->orWhere('op_serial_number', 'LIKE', "%{$term}%");
            });
        }

        $results = $query
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'id'                   => (int) $row->id,
                    'instrument_type'      => strtoupper((string) ($row->instrument_type ?? '')),
                    'mlsFNo'               => strtoupper((string) ($row->mlsFNo ?? '')),
                    'temp_fileno'          => strtoupper((string) ($row->temp_fileno ?? '')),
                    'land_use'             => strtoupper((string) ($row->land_use ?? '')),
                    'registration_number'  => strtoupper((string) ($row->registration_number ?? '')),
                    'party_2_name'         => strtoupper((string) ($row->party_2_name ?? '')),
                    'property_description' => strtoupper((string) ($row->property_description ?? '')),
                    'plot_number'          => strtoupper((string) ($row->plot_number ?? '')),
                    'lga'                  => strtoupper((string) ($row->lga ?? '')),
                    'op_serial_number'     => strtoupper((string) ($row->op_serial_number ?? '')),
                    'op_type'              => strtoupper((string) ($row->op_type ?? '')),
                    'tp_no'                => strtoupper((string) ($row->tp_no ?? '')),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }

    /**
     * Look up a PRA record by file number.
     * Used by the OSS application form to prefill fields from PRA history.
     */
    public function lookupFileIndexing(Request $request, PraRecordService $praService): JsonResponse
    {
        $fileNo = strtoupper(trim((string) $request->input('file_no')));

        if ($fileNo === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        $indexingColumns = $this->sqlsrvColumnMap('file_indexings');
        $titleColumn = $indexingColumns['file_title'] ?? ($indexingColumns['file_name'] ?? null);
        $customerTypeColumn = $indexingColumns['customer_type'] ?? null;

        $indexingQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw('UPPER(file_number) = ?', [$fileNo]);

        if ($titleColumn !== null) {
            $indexingQuery->addSelect(DB::raw("[{$titleColumn}] as applicant_title"));
        }
        if ($customerTypeColumn !== null) {
            $indexingQuery->addSelect(DB::raw("[{$customerTypeColumn}] as applicant_customer_type"));
        }

        $indexingAliases = [
            'file_number' => 'idx_file_number',
            'land_use' => 'idx_land_use',
            'plot_no' => 'idx_plot_no',
            'plan_no' => 'idx_plan_no',
            'location' => 'idx_location',
            'lga' => 'idx_lga', 
            'tp_no' => 'idx_tp_no',
            'registration_number' => 'idx_registration_number',
            'phone' => 'idx_phone',
        ];

        foreach ($indexingAliases as $column => $alias) {
            if (isset($indexingColumns[$column])) {
                $actualColumn = $indexingColumns[$column];
                $indexingQuery->addSelect(DB::raw("[{$actualColumn}] as {$alias}"));
            }
        }

        $indexingRow = $indexingQuery->first();

        // Also check mls_file_no metadata table for indexed/commissioned files
        $mlsColumns = $this->sqlsrvColumnMap('mls_file_no');
        $mlsFileNumberColumn = $mlsColumns['full_file_number'] ?? null;
        $mlsFileNameColumn = $mlsColumns['file_name'] ?? null;
        $mlsLandUseColumn = $mlsColumns['land_use'] ?? null;
        $mlsCustomerTypeColumn = $mlsColumns['customer_type'] ?? null;

        $mlsRow = null;
        if ($mlsFileNumberColumn !== null) {
            $mlsQuery = DB::connection('sqlsrv')->table('mls_file_no')
                ->whereRaw('UPPER([' . $mlsFileNumberColumn . ']) = ?', [$fileNo]);

            if ($mlsFileNameColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsFileNameColumn . '] as mls_file_name'));
            }
            if ($mlsLandUseColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsLandUseColumn . '] as mls_land_use'));
            }
            if ($mlsCustomerTypeColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsCustomerTypeColumn . '] as mls_customer_type'));
            }
            $mlsQuery->addSelect(DB::raw('[' . $mlsFileNumberColumn . '] as mls_file_number'));

            $mlsRow = $mlsQuery->first();
        }

        // Also check fileNumber table (files captured via Capture Existing / commissioning)
        $fileNumberRow = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereRaw('UPPER(mlsfNo) = ?', [$fileNo])
            ->select('mlsfNo', 'FileName', 'plot_no', 'tp_no', 'lga', 'location', 'tracking_id')
            ->first();

        $records = $praService->findAllByFileNumber($fileNo);
        if (empty($records) && !$indexingRow && !$fileNumberRow && !$mlsRow) {
            return response()->json([
                'success' => false,
                'message' => 'No PRA, file indexing, fileNumber, or mls_file_no record found for this file number.',
            ]);
        }

        $row = collect($records)
            ->map(static fn ($record) => (array) $record)
            ->sortByDesc(fn (array $record) => $this->rowTimestampForMotherSelection($record))
            ->first() ?? [];

        $idx = static function ($indexingRow, string $alias): string {
            return strtoupper(trim((string) ($indexingRow->{$alias} ?? '')));
        };

        // Helper: get value from fileNumber row
        $fnr = static function (?object $fileNumberRow, string $col): string {
            return strtoupper(trim((string) ($fileNumberRow->{$col} ?? '')));
        };

        $fileNumber = strtoupper((string) ($this->firstFilledValue($row, [
            'mlsFNo',
            'fileno',
            'kangisFileNo',
            'NewKANGISFileno',
            'temp_fileno',
        ]) ?? ($idx($indexingRow, 'idx_file_number') ?: ($fnr($fileNumberRow, 'mlsfNo') ?: (strtoupper(trim((string) ($mlsRow->mls_file_number ?? ''))) ?: $fileNo)))));

        $applicantNameFromPra = strtoupper((string) ($this->firstFilledValue($row, [
            'Grantee',
            'party_2',
            'Assignee',
            'Mortgagee',
            'Lessee',
            'Surrenderee',
            'Grantor',
            'party_1',
            'Assignor',
            'Mortgagor',
            'Lessor',
        ]) ?? ''));

        $applicantNameFromIndexing = strtoupper(trim((string) ($indexingRow->applicant_title ?? '')));
        $applicantNameFromFileNumber = $fnr($fileNumberRow, 'FileName');
        $applicantNameFromMls = strtoupper(trim((string) ($mlsRow->mls_file_name ?? '')));
        $applicantName = $applicantNameFromIndexing !== '' ? $applicantNameFromIndexing
            : ($applicantNameFromPra !== '' ? $applicantNameFromPra
            : ($applicantNameFromFileNumber !== '' ? $applicantNameFromFileNumber : $applicantNameFromMls));

        $landUse = strtoupper((string) ($this->firstFilledValue($row, ['land_use', 'landUse']) ?? ($idx($indexingRow, 'idx_land_use') ?: ($fnr($fileNumberRow, 'land_use') ?: strtoupper(trim((string) ($mlsRow->mls_land_use ?? '')))))));
        $plotNo = strtoupper((string) ($this->firstFilledValue($row, ['plot_no', 'plotNo', 'plot_number']) ?? ($idx($indexingRow, 'idx_plot_no') ?: $fnr($fileNumberRow, 'plot_no'))));
        $planNo = strtoupper((string) ($this->firstFilledValue($row, ['approved_plan_no', 'tp_no', 'tpNo', 'lpkn_no']) ?? ($idx($indexingRow, 'idx_plan_no') ?: $fnr($fileNumberRow, 'tp_no'))));
        $location = strtoupper((string) ($this->firstFilledValue($row, ['location', 'property_description']) ?? ($idx($indexingRow, 'idx_location') ?: $fnr($fileNumberRow, 'location'))));
        $lga = strtoupper((string) ($this->firstFilledValue($row, ['lga', 'lgsaOrCity', 'lga_name']) ?? ($idx($indexingRow, 'idx_lga') ?: $fnr($fileNumberRow, 'lga'))));
        $state = strtoupper((string) ($this->firstFilledValue($row, ['state', 'state_name']) ?? ''));
        $registrationNo = strtoupper((string) ($this->firstFilledValue($row, ['regNo', 'reg_no', 'registration_number']) ?? $idx($indexingRow, 'idx_registration_number')));
        $opSerialNo = strtoupper((string) ($this->firstFilledValue($row, ['op_serial_number', 'serialNo', 'pageNo']) ?? ''));
        $phone = (string) ($this->firstFilledValue($row, [
            'phone',
            'phone_no',
            'gsm',
            'telephone',
            'telephone_no',
            'party_1_phone',
            'party_2_phone',
        ]) ?? trim((string) ($indexingRow->idx_phone ?? '')));
        $purpose = strtoupper((string) ($this->firstFilledValue($row, ['purpose', 'transaction_type', 'instrument_type']) ?? ''));
        $tpNo = strtoupper((string) ($this->firstFilledValue($row, ['tp_no', 'tpNo']) ?? ($idx($indexingRow, 'idx_tp_no') ?: $fnr($fileNumberRow, 'tp_no'))));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => (int) ($row['id'] ?? 0),
                'file_number'         => $fileNumber,
                'file_name'           => $applicantName,
                'file_title'          => $applicantName,
                'current_holder'      => $applicantName,
                'tracking_id'         => (string) ($row['prop_id'] ?? ($fileNumberRow->tracking_id ?? '')),
                'land_use'            => $landUse,
                'plot_no'             => $plotNo,
                'plan_no'             => $planNo,
                'location'            => $location,
                'lga'                 => $lga,
                'state'               => $state,
                'tp_no'               => $tpNo,
                'registration_number' => $registrationNo,
                'op_serial_number'    => $opSerialNo,
                'phone'               => $phone,
                'purpose'             => $purpose,
                'customer_type'       => strtoupper(trim((string) (($indexingRow->applicant_customer_type ?? '') ?: ($mlsRow->mls_customer_type ?? '')))),
            ],
        ]);
    }

    /**
     * Validation rules covering all 3 form types.
     */
    private function validationRules(): array
    {
        return [
            'instrument_capture_id'      => 'nullable|integer',
            'file_no'                    => 'nullable|string|max:120',
            'application_type'          => 'required|in:residential,commercial,industrial,agricultural',
            'applicant_name'            => 'required|string|max:255',
            'phone'                     => 'nullable|string|max:50',
            'email'                     => 'nullable|email|max:255',
            'state_of_origin'           => 'nullable|string|max:100',
            'lga'                       => 'nullable|string|max:100',
            'nationality'               => 'nullable|string|max:255',
            'correspondence_address'    => 'nullable|string|max:2000',
            'purpose'                   => 'nullable|string|max:500',
            'plot_no'                   => 'nullable|string|max:100',
            'plan_no'                   => 'nullable|string|max:100',
            'location'                  => 'nullable|string|max:500',
            'prev_allocated'            => 'nullable|string|max:10',
            'prev_allocation_details'   => 'nullable|string',
            'remarks'                   => 'nullable|string',

            // Residential
            'age'                       => 'nullable|string|max:10',
            'sex'                       => 'nullable|string|max:20',
            'marital_status'            => 'nullable|string|max:50',
            'husband_name_address'      => 'nullable|string|max:500',
            'residential_address'       => 'nullable|string|max:2000',
            'business_address'          => 'nullable|string|max:2000',
            'occupation'                => 'nullable|string|max:255',
            'annual_income'             => 'nullable|string|max:100',

            // Commercial
            'home_domicile'             => 'nullable|string|max:255',
            'occupation_or_business'    => 'nullable|string|max:255',
            'nature_of_commerce'        => 'nullable|string|max:500',
            'company_registered_under'  => 'nullable|string|max:500',
            'registration_particulars'  => 'nullable|string|max:500',
            'business_location'         => 'nullable|string|max:500',
            'annual_income_anticipation'=> 'nullable|string|max:100',
            'prev_land_purpose'         => 'nullable|string|max:500',
            'intended_activities'       => 'nullable|string',

            // Industrial
            'nature_of_occupation'      => 'nullable|string|max:500',
            'annual_income_turnover'    => 'nullable|string|max:100',
            'number_of_employees'       => 'nullable|string|max:50',
            'nature_of_industrial'      => 'nullable|string|max:500',
            'waste_disposal_requirements' => 'nullable|string',
            'nature_of_agricultural'    => 'nullable|string|max:500',

            // Address builder sub-fields (residential)
            'res_addr_house'            => 'nullable|string|max:100',
            'res_addr_plot'             => 'nullable|string|max:100',
            'res_addr_street'           => 'nullable|string|max:255',
            'res_addr_street_other'     => 'nullable|string|max:255',
            'res_addr_district'         => 'nullable|string|max:255',
            'res_addr_district_other'   => 'nullable|string|max:255',
            'res_addr_lga'              => 'nullable|string|max:255',
            'res_addr_state'            => 'nullable|string|max:255',
            'res_corr_house'            => 'nullable|string|max:100',
            'res_corr_plot'             => 'nullable|string|max:100',
            'res_corr_street'           => 'nullable|string|max:255',
            'res_corr_street_other'     => 'nullable|string|max:255',
            'res_corr_district'         => 'nullable|string|max:255',
            'res_corr_district_other'   => 'nullable|string|max:255',
            'res_corr_lga'              => 'nullable|string|max:255',
            'res_corr_state'            => 'nullable|string|max:255',
            'res_biz_house'             => 'nullable|string|max:100',
            'res_biz_plot'              => 'nullable|string|max:100',
            'res_biz_street'            => 'nullable|string|max:255',
            'res_biz_street_other'      => 'nullable|string|max:255',
            'res_biz_district'          => 'nullable|string|max:255',
            'res_biz_district_other'    => 'nullable|string|max:255',
            'res_biz_lga'               => 'nullable|string|max:255',
            'res_biz_state'             => 'nullable|string|max:255',
            // Address builder sub-fields (commercial)
            'com_biz_house'             => 'nullable|string|max:100',
            'com_biz_plot'              => 'nullable|string|max:100',
            'com_biz_street'            => 'nullable|string|max:255',
            'com_biz_street_other'      => 'nullable|string|max:255',
            'com_biz_district'          => 'nullable|string|max:255',
            'com_biz_district_other'    => 'nullable|string|max:255',
            'com_biz_lga'               => 'nullable|string|max:255',
            'com_biz_state'             => 'nullable|string|max:255',
            'com_corr_house'            => 'nullable|string|max:100',
            'com_corr_plot'             => 'nullable|string|max:100',
            'com_corr_street'           => 'nullable|string|max:255',
            'com_corr_street_other'     => 'nullable|string|max:255',
            'com_corr_district'         => 'nullable|string|max:255',
            'com_corr_district_other'   => 'nullable|string|max:255',
            'com_corr_lga'              => 'nullable|string|max:255',
            'com_corr_state'            => 'nullable|string|max:255',
            // Address builder sub-fields (industrial)
            'ind_biz_house'             => 'nullable|string|max:100',
            'ind_biz_plot'              => 'nullable|string|max:100',
            'ind_biz_street'            => 'nullable|string|max:255',
            'ind_biz_street_other'      => 'nullable|string|max:255',
            'ind_biz_district'          => 'nullable|string|max:255',
            'ind_biz_district_other'    => 'nullable|string|max:255',
            'ind_biz_lga'               => 'nullable|string|max:255',
            'ind_biz_state'             => 'nullable|string|max:255',
            'ind_corr_house'            => 'nullable|string|max:100',
            'ind_corr_plot'             => 'nullable|string|max:100',
            'ind_corr_street'           => 'nullable|string|max:255',
            'ind_corr_street_other'     => 'nullable|string|max:255',
            'ind_corr_district'         => 'nullable|string|max:255',
            'ind_corr_district_other'   => 'nullable|string|max:255',
            'ind_corr_lga'              => 'nullable|string|max:255',
            'ind_corr_state'            => 'nullable|string|max:255',
            // Address builder sub-fields (agricultural)
            'agr_biz_house'             => 'nullable|string|max:100',
            'agr_biz_plot'              => 'nullable|string|max:100',
            'agr_biz_street'            => 'nullable|string|max:255',
            'agr_biz_street_other'      => 'nullable|string|max:255',
            'agr_biz_district'          => 'nullable|string|max:255',
            'agr_biz_district_other'    => 'nullable|string|max:255',
            'agr_biz_lga'               => 'nullable|string|max:255',
            'agr_biz_state'             => 'nullable|string|max:255',
            'agr_corr_house'            => 'nullable|string|max:100',
            'agr_corr_plot'             => 'nullable|string|max:100',
            'agr_corr_street'           => 'nullable|string|max:255',
            'agr_corr_street_other'     => 'nullable|string|max:255',
            'agr_corr_district'         => 'nullable|string|max:255',
            'agr_corr_district_other'   => 'nullable|string|max:255',
            'agr_corr_lga'              => 'nullable|string|max:255',
            'agr_corr_state'            => 'nullable|string|max:255',

            // Passport photo
            'passport_photo'            => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }
}
