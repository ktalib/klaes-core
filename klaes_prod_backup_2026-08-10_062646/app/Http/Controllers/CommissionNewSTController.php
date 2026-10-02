<?php

namespace App\Http\Controllers;
use App\Services\STFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CommissionNewSTController extends Controller
{
    protected $stFileNumberService;

    public function __construct(STFileNumberService $stFileNumberService)
    {
        $this->stFileNumberService = $stFileNumberService;
    }

    /**
     * Display the main commission new ST view with tabs
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        try {
            $PageTitle = 'ST File Number Commissioning';
            $PageDescription = 'Commission New Sectional Titling File Number across different ST workflows';

            // Generate NP FileNo for the main application (same logic as PrimaryFormController)
            $rawLandUse = request()->query('landuse', 'COMMERCIAL');

            // Normalize land use to match database expected values
            $landUse = match (strtoupper(trim($rawLandUse))) {
                'COMMERCIAL', 'COMMERCIAL USE' => 'COMMERCIAL',
                'INDUSTRIAL', 'INDUSTRIAL USE' => 'INDUSTRIAL',
                'RESIDENTIAL', 'RESIDENTIAL USE' => 'RESIDENTIAL',
                'MIXED', 'MIXED USE' => 'MIXED',
                default => 'COMMERCIAL'
            };

            // Determine the land use code
            $landUseCode = match ($landUse) {
                'COMMERCIAL' => 'COM',
                'INDUSTRIAL' => 'IND',
                'RESIDENTIAL' => 'RES',
                'MIXED' => 'MIXED',
                default => 'COM'
            };

            // Get the current year
            $currentYear = date('Y');

            // Get the NEXT available primary file number (unified across all ST types)
            $nextPrimaryFileNo = $this->getNextAvailablePrimaryFileNo($landUseCode, $currentYear);

            // Use it for NP Preview
            $npFileNo = $nextPrimaryFileNo;

            // Extract serial number for display
            $serialNo = $this->extractSerialFromFileNo($nextPrimaryFileNo);

            // Get titles from database
            $titles = DB::connection('sqlsrv')->select("
                SELECT [id], [display_name], [is_active], [sort_order]
                FROM [klas].[dbo].[titles]
                WHERE [is_active] = 1
                ORDER BY [sort_order] ASC, [display_name] ASC
            ");

            // Tracking ID loads from grouping/file records once a file is selected
            $trackingId = null;

            // Mock draft metadata for consistency with primary form
            $draftMeta = [
                'draft_id' => '',
                'version' => 1,
                'last_completed_step' => 1,
                'progress_percent' => 0,
                'last_saved_at' => null,
                'collaborators' => [],
                'np_file_no' => $npFileNo
            ];

            return view('commission_new_st.index', compact(
                'PageTitle',
                'PageDescription',
                'npFileNo',
                'landUse',
                'currentYear',
                'serialNo',
                'draftMeta',
                'titles',
                'trackingId'
            ));
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while loading the page.');
        }
    }

    /**
     * Get Primary data for AJAX requests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrimaryData(Request $request)
    {
        try {
            // TODO: Implement Primary data retrieval
            $data = [
                'message' => 'Primary data endpoint - to be implemented',
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@getPrimaryData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving Primary data'
            ], 500);
        }
    }

    /**
     * Get SuA (Sub Application) data for AJAX requests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuAData(Request $request)
    {
        try {
            // TODO: Implement SuA data retrieval
            $data = [
                'message' => 'SuA data endpoint - to be implemented',
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@getSuAData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving SuA data'
            ], 500);
        }
    }

    /**
     * Get PuA (Public Application) data for AJAX requests
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPuAData(Request $request)
    {
        try {
            // TODO: Implement PuA data retrieval
            $data = [
                'message' => 'PuA data endpoint - to be implemented',
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@getPuAData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving PuA data'
            ], 500);
        }
    }

    /**
     * Get next file number for the specified land use (Primary application)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nextFileNo(Request $request)
    {
        try {
            $landUse = $request->query('landuse', 'Residential');

            // Generate primary file number using the new service
            $result = $this->stFileNumberService->generatePrimaryFileNumber($landUse, [
                'applicant_type' => $request->query('applicant_type', 'Individual'),
                'first_name' => $request->query('first_name'),
                'surname' => $request->query('surname'),
                'corporate_name' => $request->query('corporate_name'),
                'rc_number' => $request->query('rc_number'),
                'applicant_title' => $request->query('applicant_title')
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'fileNumber' => $result['data']['np_fileno'],
                    'data' => $result['data'],
                    'message' => 'Primary file number generated successfully'
                ]);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@nextFileNo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating next file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next SUA file numbers for the specified land use
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suaNextFileNo(Request $request)
    {
        try {
            $landUse = $request->query('landuse', 'Residential');

            // Generate SUA file numbers using the new service
            $result = $this->stFileNumberService->generateSUAFileNumber($landUse, [
                'applicant_type' => $request->query('applicant_type', 'Individual'),
                'first_name' => $request->query('first_name'),
                'surname' => $request->query('surname'),
                'corporate_name' => $request->query('corporate_name'),
                'rc_number' => $request->query('rc_number'),
                'applicant_title' => $request->query('applicant_title')
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'primaryFileNo' => $result['data']['np_fileno'],
                    'unitFileNo' => $result['data']['unit_fileno'],
                    'mlsFileNo' => $result['data']['mls_fileno'],
                    'data' => $result['data'],
                    'message' => 'SUA file numbers generated successfully'
                ]);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@suaNextFileNo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating SUA file numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next PUA file number for the specified parent file number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function puaNextFileNo(Request $request)
    {
        try {
            $parentFileNumber = $request->query('parent_file_number');

            if (!$parentFileNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent file number is required for PUA generation'
                ], 422);
            }

            // Generate PUA file number using the new service
            $result = $this->stFileNumberService->generatePUAFileNumber($parentFileNumber, [
                'applicant_type' => $request->query('applicant_type', 'Individual'),
                'first_name' => $request->query('first_name'),
                'surname' => $request->query('surname'),
                'corporate_name' => $request->query('corporate_name'),
                'rc_number' => $request->query('rc_number'),
                'applicant_title' => $request->query('applicant_title')
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'npFileNo' => $result['data']['np_fileno'],
                    'unitFileNo' => $result['data']['unit_fileno'],
                    'mlsFileNo' => $result['data']['mls_fileno'],
                    'data' => $result['data'],
                    'message' => 'PUA file number generated successfully'
                ]);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@puaNextFileNo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating PUA file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Commission (save) a new ST file number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function commission(Request $request)
    {
        try {
            $validated = $request->validate([
                'np_fileno' => 'required|string',
                'fileno' => 'nullable|string',
                'applied_file_number' => 'required|string',
                'application_type' => 'required|string|in:Direct Allocation,Conversion',
                'applicant_type' => 'required|string|in:individual,corporate,multiple',
                'land_use' => 'required|string|in:COMMERCIAL,RESIDENTIAL,INDUSTRIAL,MIXED',
                'first_name' => 'nullable|string',
                'middle_name' => 'nullable|string',
                'surname' => 'nullable|string',
                'applicant_title' => 'nullable|string',
                'corporate_name' => 'nullable|string',
                'rc_number' => 'nullable|string',
                'commissioned_by' => 'nullable|string',
                'commissioned_date' => 'nullable|date',
                'property_house_no' => 'nullable|string',
                'property_plot_no' => 'nullable|string',
                'property_street_name' => 'nullable|string',
                'property_district' => 'nullable|string',
                'property_lga' => 'nullable|string',
                'property_state' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180'
            ]);

            // Extract components from file number (e.g., ST-COM-2025-5)
            $fileNumberParts = explode('-', $validated['np_fileno']);
            $landUseCode = $fileNumberParts[1] ?? '';
            $year = intval($fileNumberParts[2] ?? date('Y'));
            $serialNo = intval($fileNumberParts[3] ?? 1);

            // Map land use code to full name
            $landUseMapping = [
                'COM' => 'COMMERCIAL',
                'RES' => 'RESIDENTIAL',
                'IND' => 'INDUSTRIAL',
                'MIXED' => 'MIXED'
            ];
            $landUseFullName = $landUseMapping[$landUseCode] ?? $validated['land_use'];

            $creatorName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $commissionedAt = !empty($validated['commissioned_date'])
                ? Carbon::parse($validated['commissioned_date'])->setTimeFrom(now())
                : now();

            $transactionResult = DB::connection('sqlsrv')->transaction(function () use ($validated, $landUseFullName, $landUseCode, $year, $serialNo, $creatorName, $commissionedAt) {
                $connection = DB::connection('sqlsrv');
                $tra = $this->generateTra();
                $npFileNo = $validated['np_fileno'];
                $fileno = $validated['fileno'] ?? null;
                if (empty($fileno)) {
                    $fileno = $validated['applied_file_number'] ?? null;
                }
                $stFileNo = $npFileNo;
                $mlsFileNo = $fileno;
                $fileNoType = 'PRIMARY';

                $stFileNumberId = $connection->table('st_file_numbers')->insertGetId([
                    'np_fileno' => $npFileNo,
                    'fileno' => $fileno,
                    'mls_fileno' => $mlsFileNo,
                    'land_use' => $landUseFullName,
                    'land_use_code' => $landUseCode,
                    'serial_no' => $serialNo,
                    'unit_sequence' => null,
                    'year' => $year,
                    'file_no_type' => $fileNoType,
                    'application_type' => $validated['application_type'],
                    'parent_id' => null,
                    'mother_application_id' => null,
                    'subapplication_id' => null,
                    'status' => 'ACTIVE',
                    'reserved_at' => $commissionedAt,
                    'expires_at' => null,
                    'used_at' => $commissionedAt,
                    'date_commissioned' => $commissionedAt,
                    'tra' => $tra,
                    'applicant_type' => ucfirst($validated['applicant_type']),
                    'applicant_title' => $validated['applicant_title'] ?? null,
                    'first_name' => $validated['first_name'] ?? null,
                    'middle_name' => $validated['middle_name'] ?? null,
                    'surname' => $validated['surname'] ?? null,
                    'corporate_name' => $validated['corporate_name'] ?? null,
                    'rc_number' => $validated['rc_number'] ?? null,
                    'multiple_owners_names' => null,
                    'created_by' => Auth::id(),
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt
                ]);

                $fileName = $this->buildApplicantDisplayName(
                    $validated['applicant_type'],
                    $validated['applicant_title'] ?? null,
                    $validated['first_name'] ?? null,
                    $validated['middle_name'] ?? null,
                    $validated['surname'] ?? null,
                    $validated['corporate_name'] ?? null,
                    null
                );

                $fileNumberId = $this->mirrorStToFileNumber([
                    'tracking_id' => $tra,
                    'mlsfNo' => $mlsFileNo,
                    'st_file_no' => $npFileNo,
                    'FileName' => $fileName,
                    'type' => $fileNoType,
                    'SOURCE' => 'ST Dept',
                    'date_commissioned' => $commissionedAt,
                    'created_by' => Auth::id(),
                    'created_by_name' => $creatorName,
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt,
                    'applicant_type' => ucfirst($validated['applicant_type'])
                ]);

                $syncResult = [
                    'st_file_number_id' => $stFileNumberId,
                    'file_number_id'    => $fileNumberId,
                    'tracking_id'       => $tra,
                    'file_name'         => $fileName,
                    'mlsf_no'           => $mlsFileNo,
                    'st_file_no'        => $npFileNo
                ];

                // -------------------------------------------------------
                // Create file_indexings record for the NPFN.
                // The NPFN (e.g. ST-COM-2026-10) becomes the primary indexed
                // file; the selected existing file number is stored as a
                // related file so history / legal-search links work.
                // -------------------------------------------------------
                $appliedFileNo = trim((string) ($validated['applied_file_number'] ?? ''));

                // Avoid duplicating if a record already exists for this NPFN
                $alreadyIndexed = $connection
                    ->table('file_indexings')
                    ->where('file_number', $npFileNo)
                    ->whereNull('is_deleted')
                    ->orWhere(function ($q) use ($npFileNo) {
                        $q->where('file_number', $npFileNo)->where('is_deleted', 0);
                    })
                    ->exists();

                if (!$alreadyIndexed) {
                    // The selected existing file number is already indexed and is the
                    // source of truth for the property's location. Copy its location
                    // fields onto the new primary NPFN row, and carry its prop_id
                    // forward as parent_prop_id so the new file stays linked to the
                    // parent property record.
                    $sourceIndexing = $appliedFileNo !== ''
                        ? $connection->table('file_indexings')
                            ->where(function ($q) use ($appliedFileNo) {
                                $q->where('file_number', $appliedFileNo)
                                  ->orWhere('mls_file_no', $appliedFileNo)
                                  ->orWhere('kangis_file_no', $appliedFileNo)
                                  ->orWhere('new_kangis_file_no', $appliedFileNo);
                            })
                            ->where(function ($q) {
                                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                            })
                            ->orderByDesc('id')
                            ->first()
                        : null;

                    // Form values (possibly edited by the user after backfill) win;
                    // fall back to the source file's indexed location otherwise.
                    $district = $validated['property_district'] ?? null;
                    $lga = $validated['property_lga'] ?? null;
                    $streetName = $validated['property_street_name'] ?? null;
                    $plotNumber = $validated['property_plot_no'] ?? null;

                    // Pinned coordinates from the map (backfilled, geocoded or
                    // dragged); fall back to the source file's stored pin.
                    $latitude = $validated['latitude'] ?? null;
                    $longitude = $validated['longitude'] ?? null;

                    $connection->table('file_indexings')->insert([
                        'file_number'    => $npFileNo,
                        'file_title'     => $fileName,
                        'tracking_id'    => $tra,
                        'land_use_type'  => $landUseFullName,
                        'related_fileno' => $appliedFileNo ?: null,
                        'source'         => 'ST Commissioning',
                        'file_type'      => 'PRIMARY',
                        'status'         => 'ACTIVE',
                        'location'       => $sourceIndexing->location ?? null,
                        'latitude'       => $latitude !== null && $latitude !== '' ? $latitude : ($sourceIndexing->latitude ?? null),
                        'longitude'      => $longitude !== null && $longitude !== '' ? $longitude : ($sourceIndexing->longitude ?? null),
                        'district'       => $district !== null && $district !== '' ? $district : ($sourceIndexing->district ?? null),
                        'lga'            => $lga !== null && $lga !== '' ? $lga : ($sourceIndexing->lga ?? null),
                        'street_name'    => $streetName !== null && $streetName !== '' ? $streetName : ($sourceIndexing->street_name ?? null),
                        'plot_number'    => $plotNumber !== null && $plotNumber !== '' ? $plotNumber : ($sourceIndexing->plot_number ?? null),
                        'plot_size'      => $sourceIndexing->plot_size ?? null,
                        'parent_prop_id' => $sourceIndexing->prop_id ?? null,
                        'created_by'     => Auth::id(),
                        'created_at'     => $commissionedAt,
                        'updated_at'     => $commissionedAt,
                    ]);

                    Log::info('file_indexings record created for Primary NPFN', [
                        'file_number'    => $npFileNo,
                        'related_fileno' => $appliedFileNo,
                        'tracking_id'    => $tra,
                        'location_copied_from' => $sourceIndexing->id ?? null,
                        'parent_prop_id' => $sourceIndexing->prop_id ?? null,
                    ]);
                }

                // Sync to staging tables
                $this->syncToStaging($validated, $npFileNo);

                return $syncResult;
            });

            Log::info('ST File Number Commissioned Successfully', [
                'user_id' => Auth::id(),
                'st_file_number_id' => $transactionResult['st_file_number_id'],
                'file_number_id' => $transactionResult['file_number_id'],
                'file_number' => $validated['np_fileno'],
                'mlsf_no' => $transactionResult['mlsf_no'],
                'applicant_type' => $validated['applicant_type'],
                'tracking_id' => $transactionResult['tracking_id'],
                'data' => $validated
            ]);

            $edmsFolder = $this->ensureEdmsScanFolder($validated['np_fileno']);

            return response()->json([
                'success' => true,
                'fileNumber' => $validated['np_fileno'],
                'message' => 'ST file number commissioned successfully and saved to database',
                'edms_folder' => $edmsFolder,
                'storage_summary' => $this->buildStorageSummary($validated['np_fileno']),
                'data' => array_merge($validated, [
                    'st_file_number_id' => $transactionResult['st_file_number_id'],
                    'file_number_id' => $transactionResult['file_number_id'],
                    'tracking_id' => $transactionResult['tracking_id'],
                    'database_saved' => true,
                    'status' => 'ACTIVE'
                ])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@commission: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error commissioning file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map the Location Details fields captured on the PuA / SuA tabs onto the
     * st_file_numbers columns. Unit file numbers get no file_indexings row of
     * their own, so the property location is stored with the unit record.
     *
     * @param array $validated
     * @return array
     */
    private function propertyLocationColumns(array $validated): array
    {
        $value = function (string $key) use ($validated) {
            $raw = $validated[$key] ?? null;
            if ($raw === null) {
                return null;
            }
            $raw = is_string($raw) ? trim($raw) : $raw;

            return $raw === '' ? null : $raw;
        };

        return [
            'property_house_no'    => $value('property_house_no'),
            'property_plot_no'     => $value('property_plot_no'),
            'property_street_name' => $value('property_street_name'),
            'property_district'    => $value('property_district'),
            'property_lga'         => $value('property_lga'),
            'property_state'       => $value('property_state'),
            'property_address'     => $value('property_address'),
            'latitude'             => $value('latitude'),
            'longitude'            => $value('longitude'),
        ];
    }

    /**
     * Commission (save) a new SuA file number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function commissionSuA(Request $request)
    {
        try {
            $validated = $request->validate([
                'land_use' => 'required|string',
                'application_type' => 'required|string|in:Direct Allocation,Conversion',
                'applicant_type' => 'required|string|in:individual,corporate,multiple',
                'first_name' => 'nullable|string',
                'middle_name' => 'nullable|string',
                'surname' => 'nullable|string',
                'applicant_title' => 'nullable|string',
                'corporate_name' => 'nullable|string',
                'rc_number' => 'nullable|string',
                'commissioned_by' => 'nullable|string',
                'commissioned_date' => 'nullable|date',
                'property_house_no' => 'nullable|string',
                'property_plot_no' => 'nullable|string',
                'property_street_name' => 'nullable|string',
                'property_district' => 'nullable|string',
                'property_lga' => 'nullable|string',
                'property_state' => 'nullable|string',
                'property_address' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180'
            ]);

            // Generate SuA file number
            $year = date('Y');
            $landUseCode = $this->getLandUseCode($validated['land_use']);
            $creatorName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $commissionedAt = !empty($validated['commissioned_date'])
                ? Carbon::parse($validated['commissioned_date'])->setTimeFrom(now())
                : now();

            $transactionResult = DB::connection('sqlsrv')->transaction(function () use ($validated, $landUseCode, $year, $creatorName, $commissionedAt) {
                $connection = DB::connection('sqlsrv');

                $nextSerial = $connection->table('st_file_numbers')
                    ->where('land_use_code', $landUseCode)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->max('serial_no');

                $serialNo = ($nextSerial ?? 0) + 1;
                $primaryFileNo = "ST-{$landUseCode}-{$year}-{$serialNo}";
                $unitSequence = 1;
                $unitFileNo = $primaryFileNo . '-' . str_pad($unitSequence, 3, '0', STR_PAD_LEFT);
                $fileNoType = 'SUA';
                $tra = $this->generateTra();

                // Rule 2: MLS File No must be identical to the newly generated Primary File No
                $mlsFileNo = $primaryFileNo;

                $suaFileNumberId = $connection->table('st_file_numbers')->insertGetId(array_merge($this->propertyLocationColumns($validated), [
                    'np_fileno' => $primaryFileNo,
                    'fileno' => $unitFileNo,
                    'mls_fileno' => $mlsFileNo,
                    'file_no_type' => $fileNoType,
                    'application_type' => $validated['application_type'],
                    'land_use' => $validated['land_use'],
                    'land_use_code' => $landUseCode,
                    'year' => $year,
                    'serial_no' => $serialNo,
                    'unit_sequence' => $unitSequence,
                    'parent_id' => null,
                    'mother_application_id' => null,
                    'subapplication_id' => null,
                    'status' => 'ACTIVE',
                    'reserved_at' => $commissionedAt,
                    'expires_at' => null,
                    'used_at' => $commissionedAt,
                    'date_commissioned' => $commissionedAt,
                    'tra' => $tra,
                    'applicant_type' => ucfirst($validated['applicant_type']),
                    'applicant_title' => $validated['applicant_title'] ?? null,
                    'first_name' => $validated['first_name'] ?? null,
                    'middle_name' => $validated['middle_name'] ?? null,
                    'surname' => $validated['surname'] ?? null,
                    'corporate_name' => $validated['corporate_name'] ?? null,
                    'rc_number' => $validated['rc_number'] ?? null,
                    'multiple_owners_names' => null,
                    'created_by' => Auth::id(),
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt
                ]));

                $fileName = $this->buildApplicantDisplayName(
                    $validated['applicant_type'],
                    $validated['applicant_title'] ?? null,
                    $validated['first_name'] ?? null,
                    $validated['middle_name'] ?? null,
                    $validated['surname'] ?? null,
                    $validated['corporate_name'] ?? null,
                    null
                );

                $fileNumberId = $this->mirrorStToFileNumber([
                    'tracking_id' => $tra,
                    'mlsfNo' => null,
                    'st_file_no' => $unitFileNo,
                    'FileName' => $fileName,
                    'type' => $fileNoType,
                    'SOURCE' => 'ST Dept',
                    'date_commissioned' => $commissionedAt,
                    'created_by' => Auth::id(),
                    'created_by_name' => $creatorName,
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt,
                    'applicant_type' => ucfirst($validated['applicant_type'])
                ]);

                $syncResult = [
                    'sua_file_number_id' => $suaFileNumberId,
                    'file_number_id' => $fileNumberId,
                    'tracking_id' => $tra,
                    'file_name' => $fileName,
                    'primary_file_number' => $primaryFileNo,
                    'unit_file_number' => $unitFileNo,
                    'serial_no' => $serialNo
                ];

                // Sync to staging tables
                $this->syncToStaging($validated, $unitFileNo);

                return $syncResult;
            });

            Log::info('SuA File Number Commissioned Successfully', [
                'user_id' => Auth::id(),
                'sua_file_number_id' => $transactionResult['sua_file_number_id'],
                'file_number_id' => $transactionResult['file_number_id'],
                'primary_file_number' => $transactionResult['primary_file_number'],
                'unit_file_number' => $transactionResult['unit_file_number'],
                'serial_no' => $transactionResult['serial_no'],
                'applicant_type' => $validated['applicant_type'],
                'tracking_id' => $transactionResult['tracking_id'],
                'data' => $validated
            ]);

            $edmsFolder = $this->ensureEdmsScanFolder($transactionResult['unit_file_number']);

            return response()->json([
                'success' => true,
                'suaFileNumber' => $transactionResult['unit_file_number'],
                'message' => 'SuA file number commissioned successfully and saved to database',
                'edms_folder' => $edmsFolder,
                'storage_summary' => $this->buildStorageSummary($transactionResult['unit_file_number']),
                'data' => array_merge($validated, [
                    'sua_file_number_id' => $transactionResult['sua_file_number_id'],
                    'file_number_id' => $transactionResult['file_number_id'],
                    'serial_no' => $transactionResult['serial_no'],
                    'primary_file_number' => $transactionResult['primary_file_number'],
                    'unit_file_number' => $transactionResult['unit_file_number'],
                    'tracking_id' => $transactionResult['tracking_id'],
                    'database_saved' => true,
                    'status' => 'ACTIVE'
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@commissionSuA: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error commissioning SuA file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Commission (save) a new PuA file number
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function commissionPuA(Request $request)
    {
        try {
            $validated = $request->validate([
                'parent_file_number' => 'required|string',
                'buyer_list_id' => 'nullable|integer|exists:sqlsrv.buyer_list,id',
                'applicant_type' => 'required|string|in:individual,corporate,multiple',
                'first_name' => 'nullable|string',
                'middle_name' => 'nullable|string',
                'surname' => 'nullable|string',
                'applicant_title' => 'nullable|string',
                'corporate_name' => 'nullable|string',
                'rc_number' => 'nullable|string',
                'commissioned_by' => 'nullable|string',
                'commissioned_date' => 'nullable|date',
                'property_house_no' => 'nullable|string',
                'property_plot_no' => 'nullable|string',
                'property_street_name' => 'nullable|string',
                'property_district' => 'nullable|string',
                'property_lga' => 'nullable|string',
                'property_state' => 'nullable|string',
                'property_address' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180'
            ]);

            // Get parent file number details
            $parentFile = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('np_fileno', $validated['parent_file_number'])
                ->where('file_no_type', 'PRIMARY')
                ->whereIn('status', ['ACTIVE', 'USED'])
                ->first();

            if (!$parentFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent file number not found or not available for PuA generation'
                ], 404);
            }

            // Generate PuA file number based on parent
            $parentParts = explode('-', $validated['parent_file_number']); // ST-COM-2025-5
            $landUseCode = $parentParts[1] ?? '';
            $year = intval($parentParts[2] ?? date('Y'));
            $parentSerial = intval($parentParts[3] ?? 1);

            // Get next unit sequence for this parent
            // IMPORTANT: Check BOTH PUA and SUA types to avoid duplicates
            $maxUnitSequence = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('np_fileno', $validated['parent_file_number'])
                ->whereIn('file_no_type', ['PUA', 'SUA'])
                ->whereNotNull('unit_sequence')
                ->max('unit_sequence');

            $nextUnitSequence = ($maxUnitSequence ?? 0) + 1;
            $unitFileNo = $validated['parent_file_number'] . '-' . str_pad($nextUnitSequence, 3, '0', STR_PAD_LEFT);

            // Double-check uniqueness before proceeding
            $existingUnit = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('fileno', $unitFileNo)
                ->exists();

            if ($existingUnit) {
                return response()->json([
                    'success' => false,
                    'message' => "File number {$unitFileNo} already exists. Please refresh and try again."
                ], 409);
            }

            // Map land use code to full name
            $landUseMapping = [
                'COM' => 'COMMERCIAL',
                'RES' => 'RESIDENTIAL',
                'IND' => 'INDUSTRIAL',
                'MIXED' => 'MIXED'
            ];
            $landUseFullName = $landUseMapping[$landUseCode] ?? 'COMMERCIAL';
            $creatorName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $commissionedAt = !empty($validated['commissioned_date'])
                ? Carbon::parse($validated['commissioned_date'])->setTimeFrom(now())
                : now();

            $transactionResult = DB::connection('sqlsrv')->transaction(function () use ($validated, $landUseFullName, $landUseCode, $parentSerial, $nextUnitSequence, $unitFileNo, $year, $parentFile, $creatorName, $commissionedAt) {
                $fileNoType = 'PUA';
                $tra = $this->generateTra();

                $puaFileNumberId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId(array_merge($this->propertyLocationColumns($validated), [
                    'np_fileno' => $validated['parent_file_number'],
                    'fileno' => $unitFileNo,
                    'mls_fileno' => null,
                    'land_use' => $landUseFullName,
                    'land_use_code' => $landUseCode,
                    'serial_no' => $parentSerial,
                    'unit_sequence' => $nextUnitSequence,
                    'year' => $year,
                    'file_no_type' => $fileNoType,
                    'application_type' => $parentFile->application_type,
                    'parent_id' => $parentFile->id,
                    'buyer_list_id' => $validated['buyer_list_id'] ?? null,
                    'mother_application_id' => null,
                    'subapplication_id' => null,
                    'status' => 'ACTIVE',
                    'reserved_at' => $commissionedAt,
                    'expires_at' => null,
                    'used_at' => $commissionedAt,
                    'date_commissioned' => $commissionedAt,
                    'tra' => $tra,
                    'applicant_type' => ucfirst($validated['applicant_type']),
                    'applicant_title' => $validated['applicant_title'] ?? null,
                    'first_name' => $validated['first_name'] ?? null,
                    'middle_name' => $validated['middle_name'] ?? null,
                    'surname' => $validated['surname'] ?? null,
                    'corporate_name' => $validated['corporate_name'] ?? null,
                    'rc_number' => $validated['rc_number'] ?? null,
                    'multiple_owners_names' => null,
                    'created_by' => Auth::id(),
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt
                ]));

                $fileName = $this->buildApplicantDisplayName(
                    $validated['applicant_type'],
                    $validated['applicant_title'] ?? null,
                    $validated['first_name'] ?? null,
                    $validated['middle_name'] ?? null,
                    $validated['surname'] ?? null,
                    $validated['corporate_name'] ?? null,
                    null
                );

                $fileNumberId = $this->mirrorStToFileNumber([
                    'tracking_id' => $tra,
                    'mlsfNo' => null,
                    'st_file_no' => $unitFileNo,
                    'FileName' => $fileName,
                    'type' => $fileNoType,
                    'SOURCE' => 'ST Dept',
                    'date_commissioned' => $commissionedAt,
                    'created_by' => Auth::id(),
                    'created_by_name' => $creatorName,
                    'created_at' => $commissionedAt,
                    'updated_at' => $commissionedAt,
                    'applicant_type' => ucfirst($validated['applicant_type'])
                ]);

                $syncResult = [
                    'pua_file_number_id' => $puaFileNumberId,
                    'file_number_id' => $fileNumberId,
                    'tracking_id' => $tra,
                    'file_name' => $fileName,
                    'parent_file_number' => $validated['parent_file_number'],
                    'unit_file_number' => $unitFileNo,
                    'unit_sequence' => $nextUnitSequence
                ];

                // Sync to staging tables
                $this->syncToStaging($validated, $unitFileNo);

                return $syncResult;
            });

            Log::info('PuA File Number Commissioned Successfully', [
                'user_id' => Auth::id(),
                'pua_file_number_id' => $transactionResult['pua_file_number_id'],
                'file_number_id' => $transactionResult['file_number_id'],
                'parent_file_number' => $validated['parent_file_number'],
                'unit_file_number' => $unitFileNo,
                'applicant_type' => $validated['applicant_type'],
                'tracking_id' => $transactionResult['tracking_id'],
                'data' => $validated
            ]);

            $edmsFolder = $this->ensureEdmsScanFolder($unitFileNo);

            return response()->json([
                'success' => true,
                'parentFileNumber' => $validated['parent_file_number'],
                'unitFileNumber' => $unitFileNo,
                'message' => 'PuA file number commissioned successfully and saved to database',
                'edms_folder' => $edmsFolder,
                'storage_summary' => $this->buildStorageSummary($unitFileNo),
                'data' => array_merge($validated, [
                    'pua_file_number_id' => $transactionResult['pua_file_number_id'],
                    'file_number_id' => $transactionResult['file_number_id'],
                    'unit_file_number' => $unitFileNo,
                    'unit_sequence' => $nextUnitSequence,
                    'tracking_id' => $transactionResult['tracking_id'],
                    'database_saved' => true,
                    'status' => 'ACTIVE'
                ])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@commissionPuA: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error commissioning PuA file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create the commissioned ST file's EDMS scan folder.
     *
     * ST files live under EDMS/SCAN_UPLOAD/ST_Registry/{FILE NUMBER}. Made at
     * commissioning — the same point the Land flow does it — so scanning can start
     * before the file is ever indexed. Shares EdmsScanUploadFolderService with the
     * indexing and MLS commissioning paths so all three agree on the path the
     * scanning/page-typing modules read back from.
     *
     * @return array{created:bool, existed:bool, path:?string, registry:?string, reason:string}
     */
    private function ensureEdmsScanFolder(?string $fileNumber): array
    {
        return app(\App\Services\EdmsScanUploadFolderService::class)
            ->ensure((string) $fileNumber, 'ST Registry', ['source' => 'st_commissioning']);
    }

    /**
     * "Where did this commissioning land?" — counts for the confirmation card.
     *
     * Commissioning an ST file writes across st_file_numbers, fileNumber,
     * file_indexings and the customer/entity staging tables, none of which the
     * operator can see from the form. Reuses IndexingStorageSummaryService so the
     * ST card reads exactly like the one shown after file indexing.
     *
     * Best-effort and read-only: the commissioning is already committed by the time
     * this runs, so a failure here must never turn a successful save into an error.
     */
    private function buildStorageSummary(?string $fileNumber): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        try {
            $indexing = \App\Models\FileIndexing::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->orderByDesc('id')
                ->first();

            if (!$indexing) {
                // SuA / PuA units are allocated in st_file_numbers but get no
                // file_indexings row of their own (only the primary NPFN does). An
                // unsaved stand-in carrying just the number still counts everything
                // keyed BY FILE NUMBER — st_file_numbers, fileNumber, customer and
                // entity staging — while the id-keyed rows correctly come back zero.
                $indexing = new \App\Models\FileIndexing();
                $indexing->setConnection('sqlsrv');
                $indexing->file_number = $fileNumber;
                $indexing->general_registry = 'ST Registry';
            }

            return app(\App\Services\IndexingStorageSummaryService::class)
                ->summarize($indexing, ['is_update' => false]);
        } catch (\Throwable $e) {
            Log::warning('CommissionNewSTController - could not build storage summary', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get available PRIMARY file numbers for PuA generation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailablePrimaryFileNumbers(Request $request)
    {
        try {
            // Get PRIMARY file numbers with ACTIVE or USED status that can be used for PuA
            $primaryFileNumbers = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('file_no_type', 'PRIMARY')
                ->whereIn('status', ['ACTIVE', 'USED'])
                ->orderBy('created_at', 'desc')
                ->select([
                    'id',
                    'np_fileno',
                    'land_use',
                    'land_use_code',
                    'applicant_type',
                    'first_name',
                    'surname',
                    'corporate_name',
                    'created_at'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $primaryFileNumbers,
                'message' => 'Available PRIMARY file numbers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@getAvailablePrimaryFileNumbers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving PRIMARY file numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a TRA tracking identifier that aligns with ST workflows.
     */
    private function generateTra(): string
    {
        return 'TRK-' . strtoupper(Str::random(8)) . '-' . strtoupper(Str::random(5));
    }

    /**
     * Build a consistent display name for the FileName column based on applicant details.
     */
    private function buildApplicantDisplayName(
        string $applicantType,
        ?string $applicantTitle,
        ?string $firstName,
        ?string $middleName,
        ?string $surname,
        ?string $corporateName,
        $multipleOwnersNames = null
    ): string {
        $type = strtolower(trim($applicantType));

        if ($type === 'corporate') {
            $name = trim((string) $corporateName);
            return $name !== '' ? $name : 'Corporate Applicant';
        }

        if ($type === 'multiple') {
            $owners = $this->extractOwnerNames($multipleOwnersNames);
            if (!empty($owners)) {
                if (count($owners) === 1) {
                    return $owners[0];
                }

                return $owners[0] . ' & ' . (count($owners) - 1) . ' Others';
            }
        }

        $parts = array_filter([
            $applicantTitle,
            $surname,
            $firstName,
            $middleName
        ], static function ($value) {
            return !empty(trim((string) $value));
        });

        if (!empty($parts)) {
            return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
        }

        $fallback = trim((string) $corporateName);
        if ($fallback !== '') {
            return $fallback;
        }

        return 'ST Applicant';
    }

    /**
     * Normalize multiple owner structures into a flat list of names.
     *
     * @param mixed $multipleOwners
     * @return array<int, string>
     */
    private function extractOwnerNames($multipleOwners): array
    {
        if (empty($multipleOwners)) {
            return [];
        }

        if (is_string($multipleOwners)) {
            $decoded = json_decode($multipleOwners, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->extractOwnerNames($decoded);
            }

            return array_values(array_filter(array_map(static function ($value) {
                return trim((string) $value);
            }, preg_split('/[,;\n]+/', $multipleOwners) ?: [])));
        }

        if ($multipleOwners instanceof \Traversable) {
            $multipleOwners = iterator_to_array($multipleOwners);
        }

        if (is_array($multipleOwners)) {
            $names = [];
            foreach ($multipleOwners as $owner) {
                if (is_string($owner)) {
                    $value = trim($owner);
                    if ($value !== '') {
                        $names[] = $value;
                    }
                    continue;
                }

                if (is_array($owner)) {
                    $composed = trim(implode(' ', array_filter([
                        $owner['title'] ?? null,
                        $owner['surname'] ?? $owner['last_name'] ?? null,
                        $owner['first_name'] ?? null,
                        $owner['other_names'] ?? $owner['middle_name'] ?? null
                    ], static function ($value) {
                        return !empty(trim((string) $value));
                    })));

                    if ($composed !== '') {
                        $names[] = $composed;
                        continue;
                    }

                    if (!empty($owner['name'])) {
                        $names[] = trim((string) $owner['name']);
                    }
                }
            }

            return array_values(array_filter($names, static function ($value) {
                return $value !== '';
            }));
        }

        return [];
    }

    /**
     * Cast arbitrary datetime input into a Carbon instance.
     */
    private function castToCarbon($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (empty($value)) {
            return now();
        }

        return Carbon::parse((string) $value);
    }

    /**
     * Mirror a commissioned ST record into the legacy fileNumber table.
     */
    private function mirrorStToFileNumber(array $attributes): ?int
    {
        $connection = DB::connection('sqlsrv');

        $mlsfNo = isset($attributes['mlsfNo']) ? trim((string) $attributes['mlsfNo']) : null;
        $stFileNo = isset($attributes['st_file_no']) ? trim((string) $attributes['st_file_no']) : null;
        $trackingId = isset($attributes['tracking_id']) ? trim((string) $attributes['tracking_id']) : null;

        $notDeletedScope = static function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        };

        $createdByRaw = $attributes['created_by'] ?? Auth::id();
        $createdById = null;

        if ($createdByRaw instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            $createdById = (int) $createdByRaw->getAuthIdentifier();
        } elseif (is_numeric($createdByRaw)) {
            $createdById = (int) $createdByRaw;
        }

        if ($createdById === null) {
            $createdById = Auth::id();
        }

        if ($createdById === null) {
            $createdById = 0;
        }

        $createdByName = $attributes['created_by_name'] ?? (Auth::user()->name ?? 'System');

        $createdAt = $this->castToCarbon($attributes['created_at'] ?? now());
        $updatedAt = $this->castToCarbon($attributes['updated_at'] ?? $createdAt);
        $commissioningSource = $attributes['date_commissioned'] ?? $attributes['commissioning_date'] ?? $createdAt;
        if ($commissioningSource instanceof Carbon) {
            $commissioningDate = $commissioningSource->toDateTimeString();
        } elseif ($commissioningSource instanceof \DateTimeInterface) {
            $commissioningDate = Carbon::instance($commissioningSource)->toDateTimeString();
        } else {
            $commissioningDate = (string) $commissioningSource;
        }

        if ($mlsfNo !== null && $mlsfNo !== '') {
            $existingByMls = $connection->table('fileNumber')
                ->whereRaw('LTRIM(RTRIM(mlsfNo)) = ?', [$mlsfNo])
                ->where($notDeletedScope)
                ->orderByDesc('id')
                ->first();

            if ($existingByMls) {
                $updates = [];
                if (($attributes['type'] ?? null) === 'PRIMARY' && $stFileNo !== null && $stFileNo !== '') {
                    $updates['st_file_no'] = $stFileNo;
                } elseif ($stFileNo !== null && $stFileNo !== '' && (trim((string) $existingByMls->st_file_no) === '')) {
                    $updates['st_file_no'] = $stFileNo;
                }

                if ($trackingId !== null && $trackingId !== '' && $existingByMls->tracking_id !== $trackingId) {
                    $updates['tracking_id'] = $trackingId;
                }

                if (!empty($commissioningDate) && $existingByMls->commissioning_date !== $commissioningDate) {
                    $updates['commissioning_date'] = $commissioningDate;
                }

                if ($createdById !== null && (int) $existingByMls->created_by !== $createdById) {
                    $updates['created_by'] = $createdById;
                }

                if (!empty($updates)) {
                    $updates['type'] = $attributes['type'] ?? $existingByMls->type;
                    $updates['SOURCE'] = $attributes['SOURCE'] ?? $existingByMls->SOURCE;
                    $updates['updated_at'] = $updatedAt;

                    $connection->table('fileNumber')->where('id', $existingByMls->id)->update($updates);

                    Log::info('Updated existing fileNumber record during mirror', [
                        'record_id' => $existingByMls->id,
                        'mlsfNo' => $mlsfNo,
                        'st_file_no' => $stFileNo,
                        'updates' => $updates,
                        'created_by_name' => $createdByName
                    ]);
                }

                return (int) $existingByMls->id;
            }
        }

        $duplicateRecord = $connection->table('fileNumber')
            ->where(function ($q) use ($mlsfNo, $stFileNo, $trackingId) {
                $conditions = 0;

                if ($mlsfNo !== null && $mlsfNo !== '') {
                    $q->whereRaw('LTRIM(RTRIM(mlsfNo)) = ?', [$mlsfNo]);
                    $conditions++;
                }

                if ($stFileNo !== null && $stFileNo !== '') {
                    $method = $conditions === 0 ? 'whereRaw' : 'orWhereRaw';
                    $q->{$method}('LTRIM(RTRIM(st_file_no)) = ?', [$stFileNo]);
                    $conditions++;
                }

                if ($trackingId !== null && $trackingId !== '') {
                    $method = $conditions === 0 ? 'where' : 'orWhere';
                    $q->{$method}('tracking_id', $trackingId);
                    $conditions++;
                }

                if ($conditions === 0) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->where($notDeletedScope)
            ->orderByDesc('id')
            ->first();

        if ($duplicateRecord) {
            $updates = [];

            if ($stFileNo !== null && $stFileNo !== '' && trim((string) $duplicateRecord->st_file_no) === '') {
                $updates['st_file_no'] = $stFileNo;
            }

            if ($trackingId !== null && $trackingId !== '' && $duplicateRecord->tracking_id !== $trackingId) {
                $updates['tracking_id'] = $trackingId;
            }

            if (!empty($commissioningDate) && $duplicateRecord->commissioning_date !== $commissioningDate) {
                $updates['commissioning_date'] = $commissioningDate;
            }

            if ($createdById !== null && (int) $duplicateRecord->created_by !== $createdById) {
                $updates['created_by'] = $createdById;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = $updatedAt;
                $connection->table('fileNumber')->where('id', $duplicateRecord->id)->update($updates);

                Log::info('Refreshed duplicate fileNumber record during mirror', [
                    'record_id' => $duplicateRecord->id,
                    'mlsfNo' => $mlsfNo,
                    'st_file_no' => $stFileNo,
                    'updates' => $updates,
                    'created_by_name' => $createdByName
                ]);
            } else {
                Log::info('Skipped fileNumber mirror because matching record already exists', [
                    'mlsfNo' => $mlsfNo,
                    'st_file_no' => $stFileNo,
                    'tracking_id' => $trackingId
                ]);
            }

            return (int) $duplicateRecord->id;
        }

        $payload = [
            'tracking_id' => $trackingId !== '' ? $trackingId : null,
            'mlsfNo' => $mlsfNo !== '' ? $mlsfNo : null,
            'st_file_no' => $stFileNo !== '' ? $stFileNo : null,
            'kangisFileNo' => null,
            'NewKANGISFileNo' => null,
            'FileName' => $attributes['FileName'] ?? 'ST Applicant',
            'plot_no' => $attributes['plot_no'] ?? null,
            'tp_no' => $attributes['tp_no'] ?? null,
            'location' => $attributes['location'] ?? null,
            'type' => $attributes['type'] ?? 'ST',
            'SOURCE' => $attributes['SOURCE'] ?? 'ST Dept',
            'commissioning_date' => $commissioningDate,
            'is_deleted' => 0,
            'created_by' => $createdById,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ];

        return $connection->table('fileNumber')->insertGetId($payload);
    }

    /**
     * Get the next available primary file number (unified across all ST types)
     *
     * @param string $landUseCode
     * @param int $year
     * @return string
     */
    private function getNextAvailablePrimaryFileNo($landUseCode, $year)
    {
        try {
            // Get the latest serial number from unified st_file_numbers table
            $maxSerial = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('land_use_code', $landUseCode)
                ->where('year', $year)
                ->max('serial_no');

            $nextSerial = ($maxSerial ?? 0) + 1;

            return "ST-{$landUseCode}-{$year}-{$nextSerial}";

        } catch (\Exception $e) {
            Log::warning('Error getting next available primary file number', [
                'error' => $e->getMessage(),
                'land_use_code' => $landUseCode,
                'year' => $year
            ]);

            return "ST-{$landUseCode}-{$year}-1";
        }
    }

    /**
     * Extract serial number from file number (e.g., "ST-COM-2025-5" returns 5)
     * 
     * @param string $fileNo - File number to extract from
     * @return int - Serial number
     */
    private function extractSerialFromFileNo($fileNo)
    {
        try {
            // Split by dash and get the last part
            $parts = explode('-', $fileNo);
            if (count($parts) >= 4) {
                return (int) $parts[3]; // ST-COM-2025-5 -> 5
            }

            return 1; // Fallback

        } catch (\Exception $e) {
            Log::warning('Error extracting serial from file number', [
                'error' => $e->getMessage(),
                'file_no' => $fileNo
            ]);

            return 1; // Fallback
        }
    }

    /**
     * Get land use code from land use name
     */
    private function getLandUseCode($landUse)
    {
        $landUseCodes = [
            'RESIDENTIAL' => 'RES',
            'COMMERCIAL' => 'COM',
            'INDUSTRIAL' => 'IND',
            'MIXED' => 'MIX'
        ];

        return $landUseCodes[strtoupper($landUse)] ?? 'RES';
    }

    /**
     * Sync commissioned ST record to entities_staging and customers_staging.
     * 
     * @param array $data Validated data from request
     * @param string $fileNumber The commissioned file number
     */
    private function syncToStaging(array $data, string $fileNumber)
    {
        try {
            $applicantType = ucfirst($data['applicant_type']);
            $entityName = $this->buildApplicantDisplayName(
                $data['applicant_type'],
                $data['applicant_title'] ?? null,
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['surname'] ?? null,
                $data['corporate_name'] ?? null,
                null
            );

            // 1. Find or Create Entity in staging
            $entity = \App\Models\Entity::on('sqlsrv')->updateOrCreate(
                [
                    'entity_name' => $entityName,
                    'file_number' => $fileNumber // Consistent with MlsFileNoController logic
                ],
                [
                    'entity_type' => $applicantType,
                    'updated_at' => now()
                ]
            );

            // 2. Build property address. The Location Details section posts a
            // composed address; fall back to assembling one from loose fields.
            $addressParts = [];
            if (!empty($data['plot_no'])) $addressParts[] = "Plot " . $data['plot_no'];
            if (!empty($data['location'])) $addressParts[] = $data['location'];
            if (!empty($data['lga'])) $addressParts[] = $data['lga'];
            $propertyAddress = trim((string) ($data['property_address'] ?? ''))
                ?: (implode(', ', $addressParts) ?: 'N/A');

            // 3. Create or Update Customer in staging
            \App\Models\Customer::on('sqlsrv')->updateOrCreate(
                [
                    'file_number' => $fileNumber,
                    'customer_name' => $entityName
                ],
                [
                    'customer_type' => $applicantType,
                    'entity_id' => $entity->id,
                    'account_no' => $fileNumber, // Account number is same as file number
                    'property_address' => $propertyAddress,
                    'status' => 'Active',
                    'created_by' => Auth::id(),
                    'updated_at' => now()
                ]
            );

            Log::info('ST record sync\'d to staging tables', [
                'file_number' => $fileNumber,
                'entity_id' => $entity->id,
                'entity_name' => $entityName
            ]);

        } catch (\Exception $e) {
            Log::error('Staging sync failed for ST commissioning', [
                'error' => $e->getMessage(),
                'file_number' => $fileNumber,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}