<?php

namespace App\Http\Controllers;

use App\Rules\NigerianPhone;
use App\Models\AllocationSourceLookup;
use App\Services\FileIndexingPropagationService;
use App\Services\AllocationSourceResolver;
use App\Services\STFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

            // Edit mode: ?edit={st_file_numbers.id} from the ST Commissioning table's
            // View/Edit card. The form is reused as-is; the file numbers it would have
            // minted are replaced by the ones already issued to this record and locked.
            $editRecord = $this->loadEditRecord(request()->query('edit'));
            $editTab = match (strtoupper((string) ($editRecord['file_no_type'] ?? ''))) {
                'SUA' => 'sua',
                'PUA' => 'pua',
                default => 'primary',
            };

            return view('commission_new_st.index', compact(
                'PageTitle',
                'PageDescription',
                'npFileNo',
                'landUse',
                'currentYear',
                'serialNo',
                'draftMeta',
                'titles',
                'trackingId',
                'editRecord',
                'editTab'
            ));
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@index: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while loading the page.');
        }
    }

    /**
     * Load a commissioned ST record for the edit-mode form.
     *
     * A PRIMARY commissioning writes its location to file_indexings rather than
     * onto st_file_numbers (only SuA/PuA fill the property_* columns), so the
     * indexing row is merged in as the fallback for anything missing.
     *
     * @param  mixed  $editId
     * @return array|null
     */
    private function loadEditRecord($editId): ?array
    {
        if (!$editId || !ctype_digit((string) $editId)) {
            return null;
        }

        $connection = DB::connection('sqlsrv');

        $row = $connection->table('st_file_numbers')->where('id', (int) $editId)->first();
        if (!$row) {
            return null;
        }

        $record = (array) $row;
        $record['registry_file_number'] = $this->registryFileNumberFor($row);

        $indexing = $connection->table('file_indexings')
            ->where('file_number', $record['registry_file_number'])
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first();

        if ($indexing) {
            $fallbacks = [
                'property_street_name' => $indexing->street_name ?? null,
                'property_district'    => $indexing->district ?? null,
                'property_lga'         => $indexing->lga ?? null,
                'property_plot_no'     => $indexing->plot_number ?? null,
                'latitude'             => $indexing->latitude ?? null,
                'longitude'            => $indexing->longitude ?? null,
            ];

            foreach ($fallbacks as $key => $value) {
                if (($record[$key] ?? null) === null || $record[$key] === '') {
                    $record[$key] = $value;
                }
            }

            $record['file_title'] = $indexing->file_title ?? null;
        }

        // What the form shows in its "Applied File Number" slot — the file this
        // commissioning was raised on. On a PRIMARY that is st_file_numbers.fileno
        // (the applied/mother file); related_fileno is the fallback.
        $applied = $record['file_no_type'] === 'PRIMARY'
            ? ($record['fileno'] ?: ($indexing->related_fileno ?? null))
            : null;

        if (is_string($applied) && str_starts_with(trim($applied), '[')) {
            $decoded = json_decode($applied, true);
            $applied = is_array($decoded) ? (reset($decoded) ?: null) : null;
        }

        $record['applied_file_number'] = $applied;

        return $record;
    }

    /**
     * The file number a record is registered under in file_indexings.
     *
     * A PRIMARY's own number is np_fileno — its `fileno` column holds the applied /
     * mother file it was raised on. A SuA / PuA unit is registered under `fileno`.
     */
    private function registryFileNumberFor(object $row): string
    {
        return strtoupper((string) ($row->file_no_type ?? '')) === 'PRIMARY'
            ? (string) $row->np_fileno
            : (string) ($row->fileno ?: $row->np_fileno);
    }

    /**
     * Save edits to an already-commissioned ST record.
     *
     * File numbers are immutable here: np_fileno / fileno / mls_fileno / land use /
     * serial / year / application type are never touched, because they are already
     * issued and referenced by the registry. Only the applicant, gender and
     * property-location details a user can correct are written.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRecord(Request $request, $id)
    {
        try {
            $connection = DB::connection('sqlsrv');

            $existing = $connection->table('st_file_numbers')->where('id', (int) $id)->first();
            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'ST record not found.',
                ], 404);
            }

            $validated = $request->validate([
                'applicant_type' => 'required|string|in:individual,corporate,multiple,Individual,Corporate,Multiple',
                'gender' => 'nullable|string|in:Male,Female,Corporate,Joint',
                'applicant_title' => 'nullable|string|max:20',
                'first_name' => 'nullable|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'surname' => 'nullable|string|max:100',
                'corporate_name' => 'nullable|string|max:200',
                'rc_number' => 'nullable|string|max:50',
                // SuA only — the other tabs never send these.
                // Allocation Source is now the allocating institution itself; the
                // legacy source/entity pair is derived from it in
                // resolveAllocationColumns(). Both are still accepted so an older
                // client (or the LGA council box) keeps working.
                'institution_category' => 'nullable|string|in:GOVERNMENT,OTHER',
                'institution_name' => 'nullable|string|max:255',
                'allocation_source' => 'nullable|string|in:State Government,Local Government',
                'allocation_entity' => 'nullable|string|max:100',
                'allocation_ref_no' => 'nullable|string|max:100',
                'allocation_reference_no' => 'nullable|string|max:100',
                'property_house_no' => 'nullable|string|max:100',
                'property_plot_no' => 'nullable|string|max:100',
                'property_street_name' => 'nullable|string|max:255',
                'property_district' => 'nullable|string|max:255',
                'property_lga' => 'nullable|string|max:255',
                'property_state' => 'nullable|string|max:255',
                'property_address' => 'nullable|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            $applicantType = ucfirst(strtolower($validated['applicant_type']));

            $fileName = $this->buildApplicantDisplayName(
                strtolower($applicantType),
                $validated['applicant_title'] ?? null,
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['surname'] ?? null,
                $validated['corporate_name'] ?? null,
                null
            );

            $fileNumber = $this->registryFileNumberFor($existing);

            // Allocation source/entity/slip + parcel no, keeping whatever the
            // request did not send.
            $allocation = $this->resolveAllocationColumns($validated, $existing);

            $connection->transaction(function () use ($connection, $existing, $validated, $applicantType, $fileName, $fileNumber, $allocation) {
                $connection->table('st_file_numbers')
                    ->where('id', $existing->id)
                    ->update(array_merge($this->propertyLocationColumns($validated), [
                        'applicant_type'  => $applicantType,
                        'applicant_title' => $validated['applicant_title'] ?? null,
                        'first_name'      => $validated['first_name'] ?? null,
                        'middle_name'     => $validated['middle_name'] ?? null,
                        'surname'         => $validated['surname'] ?? null,
                        'corporate_name'  => $validated['corporate_name'] ?? null,
                        'rc_number'       => $validated['rc_number'] ?? null,
                        'gender'          => $validated['gender'] ?? $existing->gender,
                        'updated_at'      => now(),
                    ], $allocation));

                // Keep the registry copy of the same facts in step.
                $indexingUpdate = [
                    'file_title'  => $fileName,
                    'district'    => $validated['property_district'] ?? null,
                    'lga'         => $validated['property_lga'] ?? null,
                    'street_name' => $validated['property_street_name'] ?? null,
                    'plot_number' => $validated['property_plot_no'] ?? null,
                    'location'    => $this->composeLocation(
                        $validated['property_district'] ?? null,
                        $validated['property_lga'] ?? null
                    ),
                    'updated_at'  => now(),
                ];

                if (!empty($validated['gender'])) {
                    $indexingUpdate['gender'] = $validated['gender'];
                    $indexingUpdate['gender_source'] = \App\Services\GenderNormalizer::SOURCE_CAPTURED;
                }
                if (($validated['latitude'] ?? null) !== null && $validated['latitude'] !== '') {
                    $indexingUpdate['latitude'] = $validated['latitude'];
                }
                if (($validated['longitude'] ?? null) !== null && $validated['longitude'] !== '') {
                    $indexingUpdate['longitude'] = $validated['longitude'];
                }

                // Blank location fields mean "not supplied", not "clear it".
                $indexingUpdate = array_filter($indexingUpdate, function ($value) {
                    return $value !== null && $value !== '';
                });

                // Read before the write, so the propagation below can tell what
                // actually changed rather than pushing every column every time.
                $indexingBefore = $connection->table('file_indexings')
                    ->where('file_number', $fileNumber)
                    ->first();

                $connection->table('file_indexings')
                    ->where('file_number', $fileNumber)
                    ->update($indexingUpdate);

                // A corrected name has to reach the file-identity mirrors, or every
                // screen reading them keeps showing the old one — the Confirmation
                // Sheet prints fileNumber.FileName, never st_file_numbers.
                if ($indexingBefore) {
                    $indexingAfter = $connection->table('file_indexings')
                        ->where('id', $indexingBefore->id)
                        ->first();

                    if ($indexingAfter) {
                        app(FileIndexingPropagationService::class)
                            ->propagate($indexingBefore, $indexingAfter);
                    }
                }

                // Not every ST file has an indexing row to propagate from — a
                // directly commissioned SuA usually has none — so the two identity
                // copies this screen owns are written from the record itself.
                $connection->table('fileNumber')
                    ->where(function ($query) use ($fileNumber) {
                        $query->where('st_file_no', $fileNumber)
                            ->orWhere('mlsfNo', $fileNumber);
                    })
                    ->update(['FileName' => $fileName]);

                $connection->table('mls_file_no')
                    ->where('full_file_number', $fileNumber)
                    ->update(['file_name' => $fileName]);
            });

            // Entity / customer staging rows carry the applicant name and address.
            $this->syncToStaging(array_merge($validated, [
                'applicant_type' => strtolower($applicantType),
            ]), $fileNumber);

            Log::info('ST record updated from edit mode', [
                'user_id'     => Auth::id(),
                'st_id'       => $existing->id,
                'file_number' => $fileNumber,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Record for {$fileNumber} updated successfully.",
                'fileNumber' => $fileNumber,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@updateRecord: ' . $e->getMessage(), [
                'st_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating record: ' . $e->getMessage(),
            ], 500);
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
     * Get SuA (Standalone unit Application) data for AJAX requests
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
     * Get PuA (Parented unit Application aka Sub Application) data for AJAX requests
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
                // A New CON-P commissions a brand-new CON land file, so there is no
                // existing file to link to — the picker is hidden on that path. Every
                // other path (Direct Allocation, Existing/Extant conversion) needs one.
                'applied_file_number' => 'required_unless:conversion_mode,new|nullable|string',
                'application_type' => 'required|string|in:Direct Allocation,Conversion',
                // Conversion sub-type: 'new' commissions the CON mother file here,
                // 'existing' hangs the ST primary off an extant CON file.
                'conversion_mode' => 'nullable|string|in:new,existing',
                // Minted by the preview endpoint and shown on the form for a conversion,
                // so what was displayed is what gets stored.
                'tracking_id' => 'nullable|string|max:50',
                'applicant_type' => 'required|string|in:individual,corporate,multiple',
                'gender' => 'required|string|in:Male,Female,Corporate,Joint',
                'land_use' => 'required|string|in:COMMERCIAL,RESIDENTIAL,INDUSTRIAL,MIXED',
                'first_name' => 'nullable|string',
                'middle_name' => 'nullable|string',
                'surname' => 'nullable|string',
                'applicant_title' => 'nullable|string',
                'corporate_name' => 'nullable|string',
                'rc_number' => 'nullable|string',
                // Applicant contact, for the ST commissioning SMS. st_file_numbers
                // had no phone column at all until 2026_09_06_000200.
                'applicant_phone' => ['nullable', 'string', 'max:100', new NigerianPhone()],
                'phone_confirmed' => 'nullable|boolean',
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

            $isConversion = ($validated['application_type'] ?? '') === 'Conversion';
            // An extant conversion reuses a CON file that is already commissioned; a
            // new one mints it here. Only the latter consumes an MLS serial.
            $reusesExistingConversion = $isConversion
                && ($validated['conversion_mode'] ?? 'new') === 'existing';

            // The mother may be an MLS conversion file or a parcel already titled in
            // the KANGIS / SLTR registries — but never an ordinary land file, which
            // belongs to a New CON-P.
            if ($reusesExistingConversion
                && !\App\Models\FileIndexing::isExtantConversionFileNumber($validated['applied_file_number'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'applied_file_number' => 'An Existing / Extant Conversion must link to a CON-, KANGIS or SLTR file.',
                ]);
            }

            // Extract components from file number (e.g., ST-COM-2025-5)
            $parsed = $this->parseStFileNumber($validated['np_fileno']);
            $landUseCode = $parsed['land_use_code'] ?? '';
            $year = $parsed['year'] ?? intval(date('Y'));
            $serialNo = $parsed['serial'] ?? 1;

            $landUseFullName = $this->landUseFullNameFromCode($landUseCode) ?? $validated['land_use'];

            if ($isConversion) {
                // Two numbers are issued, each off its own stream:
                //   the CON land file  -> MLS conversion serial (mls_serial_control)
                //   the ST primary     -> the ST serial for the land use, same pool a
                //                         Direct Allocation would have drawn from
                // The posted NPFN was only a preview; both are allocated in the
                // transaction below.
                $landUseFullName = strtoupper(trim($validated['land_use']));
                // The ST number carries no CON marker: a conversion primary is numbered
                // exactly like a direct allocation (ST-COM-2026-18) off the shared ST
                // pool. Only the mother file it points at says it is a conversion.
                $landUseCode = $this->baseLandUseCode($landUseFullName);
                $year = intval(date('Y'));
                $serialNo = null;
            }

            $creatorName = Auth::user()->name ?? Auth::user()->email ?? 'System';
            $commissionedAt = !empty($validated['commissioned_date'])
                ? Carbon::parse($validated['commissioned_date'])->setTimeFrom(now())
                : now();

            $transactionResult = DB::connection('sqlsrv')->transaction(function () use ($validated, $isConversion, $reusesExistingConversion, $landUseFullName, $landUseCode, $year, $serialNo, $creatorName, $commissionedAt) {
                $connection = DB::connection('sqlsrv');
                // A conversion's tracking ID was minted for the preview and shown on the
                // form; keep it so the operator sees the same ID before and after. Every
                // other path still mints one here.
                $tra = $isConversion
                    ? $this->useOrMintTrackingId($validated['tracking_id'] ?? null)
                    : $this->generateTra();
                $npFileNo = $validated['np_fileno'];
                $fileno = $validated['fileno'] ?? null;
                if (empty($fileno)) {
                    $fileno = $validated['applied_file_number'] ?? null;
                }
                $stFileNo = $npFileNo;
                $mlsFileNo = $fileno;
                $fileNoType = 'PRIMARY';
                $conversionFileNo = null;
                $conversionLandUse = null;
                $conversionSerial = null;

                if ($isConversion) {
                    if ($reusesExistingConversion) {
                        // The CON file already exists and is already numbered: reuse it
                        // as the mother, consuming no MLS serial.
                        $conversionFileNo = trim((string) $validated['applied_file_number']);
                    } else {
                        // The CON land file takes the next serial from the shared MLS
                        // conversion stream, so it can never be re-issued to a land
                        // conversion: CON-COM-2026-484.
                        $conversionLandUse = $this->conversionPrefix($landUseFullName);
                        $allocation = app(\App\Services\MlsSerialAllocationService::class)
                            ->allocateNextFreeSerial($conversionLandUse, $year);
                        $conversionFileNo = $allocation['file_number'];
                        $conversionSerial = (int) $allocation['serial'];

                        if (!empty($allocation['skipped'])) {
                            Log::info('ST conversion skipped taken CON serials', [
                                'land_use' => $conversionLandUse,
                                'skipped' => $allocation['skipped'],
                            ]);
                        }
                    }

                    // Either way the ST primary is numbered off the shared ST pool for
                    // its land use, exactly like a direct allocation: ST-COM-2026-18.
                    $serialNo = $this->nextPrimarySerial($landUseCode, $year);
                    $npFileNo = "ST-{$landUseCode}-{$year}-{$serialNo}";
                    $stFileNo = $npFileNo;
                    $mlsFileNo = $conversionFileNo;
                    // The CON file — new or extant — is the file this ST primary belongs to.
                    $fileno = $conversionFileNo;
                }

                $stFileNumberId = $connection->table('st_file_numbers')->insertGetId([
                    'np_fileno' => $npFileNo,
                    'fileno' => $fileno,
                    'mls_fileno' => $mlsFileNo,
                    'land_use' => $landUseFullName,
                    'land_use_code' => $landUseCode,
                    'gender' => $validated['gender'] ?? null,
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
                    'applicant_phone' => $validated['applicant_phone'] ?? null,
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

                if ($isConversion && !$reusesExistingConversion) {
                    // The conversion number is a real MLS file number: register it in
                    // mls_file_no so it shows on the MLS File Commissioning table and
                    // can never be handed out again. An extant CON file is already there.
                    \App\Models\MlsFileNo::create([
                        'land_use'         => $conversionLandUse,
                        'year'             => $year,
                        'serial_number'    => $conversionSerial,
                        'full_file_number' => $conversionFileNo,
                        'file_name'        => $fileName,
                        'plot_no'          => $validated['property_plot_no'] ?? null,
                        // Same composition the commissioning sheet prints, so the MLS
                        // list and the sheet never disagree about the location.
                        'location'         => $this->composeLocation(
                            $validated['property_district'] ?? null,
                            $validated['property_lga'] ?? null,
                            $validated['property_street_name'] ?? null
                        ),
                        'lga'              => $validated['property_lga'] ?? null,
                        'district'         => $validated['property_district'] ?? null,
                        'tracking_id'      => $tra,
                        'customer_type'    => ucfirst($validated['applicant_type']),
                        'file_option'      => 'normal',
                        // Distinguishes a Sectional Titling conversion from a land
                        // conversion on the MLS File Commissioning list.
                        'source'           => 'ST Conversion',
                        'gender'           => $validated['gender'] ?? null,
                        'system_sub_type'  => \App\Support\OssOpCommissionFilter::MLS,
                        'created_by'       => $creatorName,
                        'commissioning_date' => $commissionedAt,
                    ]);
                }

                $fileNumberId = $this->mirrorStToFileNumber([
                    'tracking_id' => $tra,
                    'mlsfNo' => $mlsFileNo,
                    'st_file_no' => $npFileNo,
                    'FileName' => $fileName,
                    // The MLS File Commissioning table reads its Location and Plot No
                    // from this row, so a new mirror row must carry them.
                    'location' => $this->composeLocation(
                        $validated['property_district'] ?? null,
                        $validated['property_lga'] ?? null,
                        $validated['property_street_name'] ?? null
                    ),
                    'plot_no' => $validated['property_plot_no'] ?? null,
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

                // Whatever file this ST primary was raised on is taken over by it: the
                // CON mother on a conversion, the linked file on a direct allocation.
                // It keeps every other record; this only logs the handover.
                $motherFileNo = $isConversion
                    ? $conversionFileNo
                    : trim((string) ($validated['applied_file_number'] ?? ''));

                if (!empty($motherFileNo)) {
                    $this->recordMotherDecommissioning(
                        $motherFileNo,
                        $npFileNo,
                        $fileName,
                        $fileNumberId,
                        $creatorName,
                        $commissionedAt,
                        $isConversion
                    );
                }

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

                    $indexingRow = [
                        'file_title'     => $fileName,
                        'tracking_id'    => $tra,
                        'land_use_type'  => $landUseFullName,
                        'source'         => 'ST Commissioning',
                        // Both files are commissioned by Sectional Titling — without this
                        // the registry falls back to Lands Registry.
                        'registry'       => 'ST Registry',
                        'gender'         => $validated['gender'] ?? null,
                        'gender_source'  => !empty($validated['gender']) ? \App\Services\GenderNormalizer::SOURCE_CAPTURED : null,
                        'file_type'      => 'PRIMARY',
                        'status'         => 'ACTIVE',
                        // District + LGA when the form supplied them, otherwise whatever
                        // the linked file was indexed with.
                        'location'       => $this->composeLocation($district, $lga)
                            ?? ($sourceIndexing->location ?? null),
                        'latitude'       => $latitude !== null && $latitude !== '' ? $latitude : ($sourceIndexing->latitude ?? null),
                        'longitude'      => $longitude !== null && $longitude !== '' ? $longitude : ($sourceIndexing->longitude ?? null),
                        'district'       => $district !== null && $district !== '' ? $district : ($sourceIndexing->district ?? null),
                        'lga'            => $lga !== null && $lga !== '' ? $lga : ($sourceIndexing->lga ?? null),
                        'street_name'    => $streetName !== null && $streetName !== '' ? $streetName : ($sourceIndexing->street_name ?? null),
                        'plot_number'    => $plotNumber !== null && $plotNumber !== '' ? $plotNumber : ($sourceIndexing->plot_number ?? null),
                        'plot_size'      => $sourceIndexing->plot_size ?? null,
                        'created_by'     => Auth::id(),
                        'created_at'     => $commissionedAt,
                        'updated_at'     => $commissionedAt,
                    ];

                    // A conversion commissions TWO files: the CON land file is the
                    // mother, and the ST primary hangs under it. The mother carries the
                    // parcel's prop_id; the ST row points up at it.
                    $motherPropId = null;
                    if ($isConversion) {
                        try {
                            $motherPropId = app(\App\Services\PropertyIdAllocationService::class)
                                ->allocateOrRetrievePropId($conversionFileNo, $conversionFileNo);
                        } catch (\Throwable $e) {
                            Log::warning('Could not allocate prop_id for ST conversion mother file', [
                                'file_number' => $conversionFileNo,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // An extant conversion is already indexed — $sourceIndexing IS
                        // its row. Only a new CON file needs a mother row written.
                        if (!$reusesExistingConversion) {
                            $connection->table('file_indexings')->insert(array_merge($indexingRow, [
                                'file_number'    => $conversionFileNo,
                                'mls_file_no'    => $conversionFileNo,
                                'file_type'      => 'MOTHER',
                                'related_fileno' => $appliedFileNo !== '' ? json_encode([$appliedFileNo]) : null,
                                'prop_id'        => $motherPropId,
                                'parent_prop_id' => $sourceIndexing->prop_id ?? null,
                            ]));

                            Log::info('file_indexings mother record created for ST conversion', [
                                'file_number' => $conversionFileNo,
                                'prop_id'     => $motherPropId,
                                'tracking_id' => $tra,
                            ]);
                        }
                    }

                    // On an extant conversion the applied file IS the mother, so it must
                    // not be listed twice.
                    $relatedForSt = $isConversion
                        ? array_values(array_unique(array_filter([$conversionFileNo, $appliedFileNo ?: null])))
                        : array_values(array_filter([$appliedFileNo ?: null]));

                    $stIndexingId = $connection->table('file_indexings')->insertGetId(array_merge($indexingRow, [
                        'file_number'    => $npFileNo,
                        'mls_file_no'    => $isConversion ? $conversionFileNo : null,
                        'related_fileno' => $isConversion
                            ? (!empty($relatedForSt) ? json_encode($relatedForSt) : null)
                            : ($appliedFileNo ?: null),
                        'parent_prop_id' => $isConversion
                            ? ($motherPropId ?? ($sourceIndexing->prop_id ?? null))
                            : ($sourceIndexing->prop_id ?? null),
                    ]));

                    Log::info('file_indexings record created for Primary NPFN', [
                        'file_number'    => $npFileNo,
                        'related_fileno' => $relatedForSt,
                        'tracking_id'    => $tra,
                        'location_copied_from' => $sourceIndexing->id ?? null,
                        'parent_prop_id' => $isConversion ? $motherPropId : ($sourceIndexing->prop_id ?? null),
                    ]);

                    // Typed link: the CON file is this ST primary's mother file.
                    if ($isConversion && $stIndexingId) {
                        $this->storeMotherFileLink($npFileNo, $fileName, $conversionFileNo, $motherPropId, $stIndexingId);
                    }
                }

                // Sync to staging tables
                $this->syncToStaging($validated, $npFileNo);

                $syncResult['conversion_file_number'] = $conversionFileNo;

                return $syncResult;
            });

            // A conversion is renumbered server-side, so the committed ST number --
            // not the previewed one posted by the form -- is what everything downstream
            // (EDMS folder, storage summary, the success card) must use.
            $commissionedFileNo = $transactionResult['st_file_no'];

            Log::info('ST File Number Commissioned Successfully', [
                'user_id' => Auth::id(),
                'st_file_number_id' => $transactionResult['st_file_number_id'],
                'file_number_id' => $transactionResult['file_number_id'],
                'file_number' => $commissionedFileNo,
                'mlsf_no' => $transactionResult['mlsf_no'],
                'conversion_file_number' => $transactionResult['conversion_file_number'],
                'applicant_type' => $validated['applicant_type'],
                'tracking_id' => $transactionResult['tracking_id'],
                'data' => $validated
            ]);

            $edmsFolder = $this->ensureEdmsScanFolder($commissionedFileNo);

            // After the transaction, like the scan folder above it: a message
            // saying the file exists must not go out on work that rolled back.
            $this->sendStCommissioningSms(
                $commissionedFileNo,
                $validated,
                $transactionResult['st_file_number_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'fileNumber' => $commissionedFileNo,
                'conversionFileNumber' => $transactionResult['conversion_file_number'],
                'message' => 'ST file number commissioned successfully and saved to database',
                'edms_folder' => $edmsFolder,
                'storage_summary' => $this->buildStorageSummary($commissionedFileNo),
                'data' => array_merge($validated, [
                    'np_fileno' => $commissionedFileNo,
                    'conversion_file_number' => $transactionResult['conversion_file_number'],
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
     * Tell the ST applicant their file has been commissioned.
     *
     * One helper for all three paths -- ST Primary, Standalone Unit (SuA) and
     * Personal Unit (PuA) -- because the message is the same and only the file
     * number differs.
     *
     * The number comes from the form (st_file_numbers.applicant_phone, added
     * alongside this feature). When it is blank the resolver falls back to
     * file_indexings, which is a prefill of last resort rather than a source of
     * truth -- one number there sits on 1,211 different files, so a shared number
     * is recorded as skipped rather than sent.
     *
     * Never throws: a commissioning that succeeded must not be reported as
     * failed because a text message could not be composed.
     */
    private function sendStCommissioningSms(?string $fileNumber, array $validated, $stFileNumberId): void
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return;
        }

        try {
            $key = \App\Models\SmsSetting::KEY_ST_FC;
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);

            if (!$dispatcher->enabled($key)) {
                return;
            }

            $phone = trim((string) ($validated['applicant_phone'] ?? ''));
            $confirmed = (bool) ($validated['phone_confirmed'] ?? false);

            if ($phone === '') {
                $phone = app(\App\Services\Sms\ApplicantPhoneResolver::class)
                    ->forFileNumber($fileNumber)['phone'];
                $confirmed = false;
            }

            $dispatcher->queue($key, $phone, [
                'FileNo' => $fileNumber,
                // 'Direct Allocation' or 'Conversion' -- the values
                // st_file_numbers.application_type is constrained to. SuA and
                // PuA do not collect one, so they fall back to the unit wording.
                'ST Application Type' => trim((string) ($validated['application_type'] ?? '')) ?: 'Sectional Titling',
            ], [
                'dedupe_key' => $key . ':' . $fileNumber,
                'file_number' => $fileNumber,
                'subject_type' => 'st_file_numbers',
                'subject_id' => $stFileNumberId,
                'phone_confirmed' => $confirmed,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CommissionNewSTController: ST commissioning SMS not queued', [
                'file_number' => $fileNumber,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Split an ST file number into its parts.
     *
     * Two shapes exist and a conversion number has FIVE segments, so nothing may
     * index into explode('-') positionally any more:
     *   ST-IND-2026-7            direct allocation (ST serial pool)
     *   ST-CON-IND-2026-1308     conversion (MLS CON-IND serial pool)
     *
     * @return array{land_use_code:string,year:int,serial:int,is_conversion:bool}|null
     */
    private function parseStFileNumber(?string $fileNo): ?array
    {
        if (!preg_match('/^ST-(CON-)?([A-Z]+)-(\d{4})-(\d+)$/i', trim((string) $fileNo), $m)) {
            return null;
        }

        return [
            // A conversion has its own serial pool, so the CON- prefix is part of the
            // pool key: 'CON-COM' is not 'COM'.
            'land_use_code' => ($m[1] !== '' ? 'CON-' : '') . strtoupper($m[2]),
            'year'          => (int) $m[3],
            'serial'        => (int) $m[4],
            'is_conversion' => $m[1] !== '',
        ];
    }

    /**
     * ST land-use code for a land use name (the ST serial pool key).
     */
    private function baseLandUseCode(?string $landUse): string
    {
        return [
            'COMMERCIAL'   => 'COM',
            'RESIDENTIAL'  => 'RES',
            'INDUSTRIAL'   => 'IND',
            'AGRICULTURAL' => 'AG',
            'MIXED'        => 'MIXED',
        ][strtoupper(trim((string) $landUse))] ?? 'COM';
    }

    /**
     * Next ST primary serial for a pool key — 'COM' for direct allocations, 'CON-COM'
     * for conversions, one pool per key per year.
     *
     * The conversion pools started life sharing the direct-allocation counter, so a
     * handful of ST-CON numbers are recorded under the plain code. The composed number
     * is checked against np_fileno and the serial skipped if it is already issued,
     * which stops the fresh CON pool from re-minting one of them.
     */
    private function nextPrimarySerial(string $landUseCode, int $year): int
    {
        $maxSerial = DB::connection('sqlsrv')
            ->table('st_file_numbers')
            ->where('land_use_code', $landUseCode)
            ->where('year', $year)
            ->lockForUpdate()
            ->max('serial_no');

        $serial = (int) ($maxSerial ?? 0) + 1;

        for ($guard = 0; $guard < 500; $guard++) {
            $taken = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('np_fileno', "ST-{$landUseCode}-{$year}-{$serial}")
                ->exists();

            if (!$taken) {
                break;
            }

            Log::info('ST primary serial already issued, skipping', [
                'file_number' => "ST-{$landUseCode}-{$year}-{$serial}",
            ]);
            $serial++;
        }

        return $serial;
    }

    /**
     * Full land use name for a land-use code, conversion prefix included
     * ('IND' and 'CON-IND' are both Industrial). Null when unrecognised.
     */
    private function landUseFullNameFromCode(?string $landUseCode): ?string
    {
        $code = strtoupper(trim((string) $landUseCode));
        $code = preg_replace('/^CON-/', '', $code);

        return [
            'COM'   => 'COMMERCIAL',
            'RES'   => 'RESIDENTIAL',
            'IND'   => 'INDUSTRIAL',
            'AG'    => 'AGRICULTURAL',
            'MIX'   => 'MIXED',
            'MIXED' => 'MIXED',
        ][$code] ?? null;
    }

    /**
     * The Location line: district and LGA, with the LGA in caps.
     *
     * The same string the commissioning sheet prints, so the MLS File Commissioning
     * list and the sheet agree. Falls back to the street name when the file was
     * captured without a district.
     */
    private function composeLocation(?string $district, ?string $lga, ?string $streetName = null): ?string
    {
        $parts = array_filter([
            trim((string) $district),
            mb_strtoupper(trim((string) $lga)),
        ]);

        return implode(', ', $parts) ?: (trim((string) $streetName) ?: null);
    }

    /**
     * MLS conversion prefix for a land use — the mls_serial_control stream the
     * conversion serial is drawn from.
     */
    private function conversionPrefix(?string $landUse): string
    {
        return [
            'COMMERCIAL'   => 'CON-COM',
            'RESIDENTIAL'  => 'CON-RES',
            'INDUSTRIAL'   => 'CON-IND',
            'AGRICULTURAL' => 'CON-AG',
            'MIXED'        => 'CON-MIXED',
        ][strtoupper(trim((string) $landUse))] ?? 'CON-RES';
    }

    /**
     * MASTER DELETE — erase an ST file number from every table it was written to.
     *
     * Destructive and irreversible: commissioning fans out across st_file_numbers,
     * fileNumber, file_indexings, mls_file_no, the staging tables, related_file_number,
     * decommissioned_files, commissioning sheets and the EDMS folder, and this removes
     * all of them in one transaction.
     *
     * Two things are deliberately NOT touched:
     *  - a fileNumber row that existed before the ST file was attached to it (a direct
     *    allocation links to a land file) — only the ST columns are cleared;
     *  - the land file a conversion was raised on when it was already extant. Only a
     *    CON file this commissioning itself created is removed with it.
     *
     * @param Request $request  requires confirm = the exact file number
     */
    public function masterDestroy(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string',
            'confirm'     => 'required|string',
        ]);

        $fileNo = trim($validated['file_number']);

        if (trim($validated['confirm']) !== $fileNo) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation text does not match the file number.',
            ], 422);
        }

        $user = Auth::user();
        if (($user->assign_role ?? null) !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only a Super Admin may delete a file number across all tables.',
            ], 403);
        }

        try {
            $connection = DB::connection('sqlsrv');

            $stRow = $connection->table('st_file_numbers')
                ->where('np_fileno', $fileNo)
                ->orWhere('fileno', $fileNo)
                ->orderBy('id')
                ->first();

            if (!$stRow) {
                return response()->json([
                    'success' => false,
                    'message' => "No ST file number found for {$fileNo}.",
                ], 404);
            }

            $isPrimary = ($stRow->file_no_type ?? '') === 'PRIMARY';
            $motherFileNo = trim((string) ($stRow->mls_fileno ?? ''));

            // Only remove the land file if this commissioning created it: an ST
            // conversion stamps mls_file_no.source, and an extant file predates it.
            $motherIsOurs = false;
            if ($isPrimary && $motherFileNo !== '' && stripos($motherFileNo, 'CON-') === 0) {
                $motherIsOurs = $connection->table('mls_file_no')
                    ->where('full_file_number', $motherFileNo)
                    ->where('source', 'ST Conversion')
                    ->exists();

                // Another ST file still standing on it? Then it is not ours to delete.
                if ($motherIsOurs) {
                    $others = $connection->table('st_file_numbers')
                        ->where('mls_fileno', $motherFileNo)
                        ->where('id', '<>', $stRow->id)
                        ->exists();
                    $motherIsOurs = !$others;
                }
            }

            $deleted = $connection->transaction(function () use ($connection, $stRow, $fileNo, $isPrimary, $motherFileNo, $motherIsOurs) {
                $counts = [];

                // Units hang off a primary by np_fileno; a unit deletes only itself.
                $stQuery = $isPrimary
                    ? $connection->table('st_file_numbers')->where('np_fileno', $fileNo)
                    : $connection->table('st_file_numbers')->where('id', $stRow->id);
                $counts['st_file_numbers'] = $stQuery->delete();

                $indexedNumbers = array_values(array_filter([$fileNo, $motherIsOurs ? $motherFileNo : null]));
                $counts['file_indexings'] = $connection->table('file_indexings')
                    ->whereIn('file_number', $indexedNumbers)->delete();

                $counts['related_file_number'] = $connection->table('related_file_number')
                    ->whereIn('file_number', $indexedNumbers)->delete();

                $counts['decommissioned_files'] = $connection->table('decommissioned_files')
                    ->where('successor_file_no', $fileNo)->delete();

                $counts['entities_staging'] = $connection->table('entities_staging')
                    ->where('file_number', $fileNo)->delete();
                $counts['customers_staging'] = $connection->table('customers_staging')
                    ->where('file_number', $fileNo)->delete();

                $counts['file_commissioning_sheets'] = $connection->table('file_commissioning_sheets')
                    ->whereIn('file_number', $indexedNumbers)->delete();

                // fileNumber: drop rows this commissioning created, and unhook the ST
                // columns from a land file that was here first.
                $counts['fileNumber_deleted'] = 0;
                $counts['fileNumber_unlinked'] = 0;
                foreach ($connection->table('fileNumber')->where('st_file_no', $fileNo)->get() as $mirror) {
                    $ours = $motherIsOurs && trim((string) $mirror->mlsfNo) === $motherFileNo;
                    if ($ours) {
                        $connection->table('fileNumber')->where('id', $mirror->id)->delete();
                        $counts['fileNumber_deleted']++;
                    } else {
                        $connection->table('fileNumber')->where('id', $mirror->id)
                            ->update(['st_file_no' => null, 'updated_at' => now()]);
                        $counts['fileNumber_unlinked']++;
                    }
                }

                $counts['mls_file_no'] = 0;
                $counts['conversion_applications'] = 0;
                if ($motherIsOurs) {
                    $counts['conversion_applications'] = $connection->table('conversion_applications')
                        ->where('full_file_number', $motherFileNo)->delete();
                    $counts['mls_file_no'] = $connection->table('mls_file_no')
                        ->where('full_file_number', $motherFileNo)->delete();
                }

                return $counts;
            });

            $folderRemoved = $this->removeEdmsScanFolder($fileNo);

            Log::warning('ST file number master-deleted', [
                'file_number'    => $fileNo,
                'mother_file'    => $motherFileNo,
                'mother_deleted' => $motherIsOurs,
                'deleted'        => $deleted,
                'edms_removed'   => $folderRemoved,
                'user_id'        => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$fileNo} deleted from all tables.",
                'data' => [
                    'file_number'    => $fileNo,
                    'mother_file'    => $motherFileNo ?: null,
                    'mother_deleted' => $motherIsOurs,
                    'deleted'        => $deleted,
                    'edms_removed'   => $folderRemoved,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CommissionNewSTController@masterDestroy: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error deleting file number: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the EDMS scan folder for a file number, but only when it holds no scans —
     * documents are never destroyed by a record deletion.
     */
    private function removeEdmsScanFolder(string $fileNo): bool
    {
        try {
            $dir = storage_path('app/public/EDMS/SCAN_UPLOAD/ST_Registry/' . str_replace(['/', '\\'], '_', $fileNo));
            if (!is_dir($dir)) {
                return false;
            }

            $entries = array_diff(scandir($dir) ?: [], ['.', '..']);
            if (!empty($entries)) {
                Log::info('EDMS folder kept during master delete: it holds scans', ['dir' => $dir]);

                return false;
            }

            return @rmdir($dir);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Log the file an ST primary was raised on as decommissioned by that primary —
     * the CON mother on a conversion, the linked file on a direct allocation.
     *
     * The mother IS decommissioned: Sectional Titling has taken it over, and the ST
     * primary is recorded as its successor. false_decommissioning = 2 marks the row as
     * an ST handover specifically, but it counts as a real decommissioning everywhere
     * (see App\Support\DecommissionScope) — only value 1, a title-status flag, does not.
     *
     * Nothing is deleted. The land file keeps its file_indexings / fileNumber /
     * mls_file_no rows, flagged in place like any other decommissioning, so its history
     * stays intact and readable under the ST primary.
     */
    private function recordMotherDecommissioning(
        string $motherFileNo,
        string $stFileNo,
        ?string $fileName,
        ?int $fileNumberId,
        string $creatorName,
        $commissionedAt,
        bool $isConversion = true
    ): void {
        try {
            $connection = DB::connection('sqlsrv');

            $already = $connection->table('decommissioned_files')
                ->where('file_no', $motherFileNo)
                ->where('successor_file_no', $stFileNo)
                ->exists();

            if ($already) {
                return;
            }

            // file_number_id is NOT NULL: fall back to the mother's own fileNumber row
            // when the mirror did not hand one back.
            if (empty($fileNumberId)) {
                $fileNumberId = $connection->table('fileNumber')
                    ->whereRaw('LTRIM(RTRIM(mlsfNo)) = ?', [$motherFileNo])
                    ->orderByDesc('id')
                    ->value('id');
            }

            if (empty($fileNumberId)) {
                Log::warning('Skipped ST decommissioning log: no fileNumber row to anchor it', [
                    'mother_file' => $motherFileNo,
                    'st_file_no'  => $stFileNo,
                ]);

                return;
            }

            $connection->table('decommissioned_files')->insert([
                'file_number_id'         => $fileNumberId,
                'file_no'                => $motherFileNo,
                'mls_file_no'            => $motherFileNo,
                'file_name'              => $fileName,
                'commissioning_date'     => $commissionedAt,
                'decommissioning_date'   => $commissionedAt,
                'decommissioning_reason' => ($isConversion ? 'ST Conversion' : 'ST Direct Allocation') . " → {$stFileNo}",
                'decommissioned_by'      => $creatorName,
                'successor_file_no'      => $stFileNo,
                'event_type'             => 'ST Decommissioning',
                'false_decommissioning'  => 2,
                'created_at'             => $commissionedAt,
                'updated_at'             => $commissionedAt,
            ]);

            // Flag the mother's live rows so the tables agree with the archive: the file
            // reads as decommissioned wherever it is listed, pointing at the ST primary
            // that took it over. Rows are only flagged, never removed.
            $reason = ($isConversion ? 'ST Conversion' : 'ST Direct Allocation') . " → {$stFileNo}";

            $targets = [
                'fileNumber'     => fn ($q) => $q->where('mlsfNo', $motherFileNo)->orWhere('kangisFileNo', $motherFileNo),
                'file_indexings' => fn ($q) => $q->where('file_number', $motherFileNo)->orWhere('kangis_file_no', $motherFileNo),
            ];

            foreach ($targets as $table => $match) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'is_decommissioned')) {
                    continue;
                }

                $payload = ['is_decommissioned' => 1];

                foreach ([
                    'decommissioned_at'      => $commissionedAt,
                    'decommissioning_date'   => $commissionedAt,
                    'decommissioned_by'      => $creatorName,
                    'decommissioning_reason' => $reason,
                    'successor_file_no'      => $stFileNo,
                    'updated_at'             => $commissionedAt,
                ] as $column => $value) {
                    if (Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                        $payload[$column] = $value;
                    }
                }

                $connection->table($table)->where($match)->update($payload);
            }
        } catch (\Exception $e) {
            // The file number is already issued; a missing log entry must not undo it.
            Log::warning('Failed to record ST mother file decommissioning', [
                'mother_file' => $motherFileNo,
                'st_file_no'  => $stFileNo,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record the CON land file as the mother file of a commissioned ST primary.
     *
     * The plain numbers already live in file_indexings.related_fileno as JSON;
     * related_file_number is where the RELATIONSHIP TYPE fits.
     */
    private function storeMotherFileLink(string $fileNumber, ?string $fileTitle, string $motherFileNo, $propId, int $sourceIndexingId): void
    {
        try {
            DB::connection('sqlsrv')->table('related_file_number')->insert([
                'related_fileno'   => $motherFileNo,
                'prop_id'          => $propId,
                'source_table'     => 'file_indexings',
                'source_id'        => $sourceIndexingId,
                'file_number'      => $fileNumber,
                'file_title'       => $fileTitle,
                'location'         => null,
                'comment'          => "Mother File of {$motherFileNo} for {$fileNumber}",
                'transaction_type' => 'Mother File',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Exception $e) {
            // A failed link must not roll back a successfully commissioned file number.
            Log::warning('Failed to store ST conversion mother file link', [
                'file_number' => $fileNumber,
                'mother_file' => $motherFileNo,
                'error'       => $e->getMessage(),
            ]);
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
     * The allocation columns to write for a SuA.
     *
     * An absent key means a non-SuA tab posted (or a field the edit screen never
     * touched), so the stored value is kept rather than blanked.
     *
     * The institution IS the Allocation Source, answered here. Only the addressee
     * the Confirmation Sheet is written to stays a per-letter question, answered
     * on the print card and stored on conversion_applications.
     *
     * allocation_source / allocation_entity are derived, never posted: every
     * existing reader still expects the 'State Government' / 'Local Government'
     * pair, and deriving it here is what stops the two shapes drifting apart.
     *
     * @param  array       $validated
     * @param  object|null $existing  the row being updated, if any
     */
    private function resolveAllocationColumns(array $validated, $existing = null): array
    {
        $stored = function (string $key) use ($existing) {
            return $existing->{$key} ?? null;
        };

        $institution = $validated['institution_name'] ?? $stored('institution_name');
        $entity      = $validated['allocation_entity'] ?? $stored('allocation_entity');

        // A name typed under "Others (Specify)" joins the list, so the next
        // commissioning offers it instead of asking for it to be typed again.
        if (isset($validated['institution_name'])) {
            $remembered = AllocationSourceLookup::remember(
                AllocationSourceResolver::institutionType($validated['institution_category'] ?? null),
                $validated['institution_name']
            );

            $institution = $remembered ?? $institution;
        }

        $common = [
            'allocation_ref_no' => $validated['allocation_ref_no'] ?? $stored('allocation_ref_no'),
            'allocation_reference_no' => $validated['allocation_reference_no']
                ?? $stored('allocation_reference_no'),
        ];

        // No institution answered at all (a non-SuA tab, or an older client still
        // posting the binary question) - leave both new columns as they were and
        // keep the legacy pair exactly as posted.
        if (trim((string) $institution) === '') {
            return $common + [
                'institution_category' => $stored('institution_category'),
                'institution_name'     => null,
                'allocation_source'    => $validated['allocation_source'] ?? $stored('allocation_source'),
                'allocation_entity'    => $entity,
            ];
        }

        $category = AllocationSourceResolver::normalizeCategory(
            $validated['institution_category'] ?? $stored('institution_category')
        );

        // Everything downstream - the Standalone Unit Application form, instrument
        // registration, the LGA sheet - still reads the old pair, so it is derived
        // from the institution rather than asked for a second time.
        return $common + [
            'institution_category' => $category,
            'institution_name'     => $institution,
        ] + AllocationSourceResolver::toLegacy($category, $institution, $entity);
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
                // Allocation Information is answered here, at commissioning, and
                // back-filled into the Standalone Unit Application form when this
                // file number is selected there. The institution comes from
                // allocation_source_lookups; only LOCAL GOVERNMENT carries a second
                // name (the LGA), so the entity is conditional rather than required.
                'institution_category' => 'required|string|in:GOVERNMENT,OTHER',
                'institution_name' => 'required|string|max:255',
                'allocation_entity' => 'required_if:institution_name,LOCAL GOVERNMENT|nullable|string|max:100',
                // The slip the allocation was raised under — printed on every sheet.
                'allocation_ref_no' => 'required|string|max:100',
                // The allocation's own reference, kept apart from that slip.
                'allocation_reference_no' => 'nullable|string|max:100',
                'applicant_type' => 'required|string|in:individual,corporate,multiple',
                'first_name' => 'nullable|string',
                'middle_name' => 'nullable|string',
                'surname' => 'nullable|string',
                'applicant_title' => 'nullable|string',
                'corporate_name' => 'nullable|string',
                'rc_number' => 'nullable|string',
                // Applicant contact, for the ST commissioning SMS. st_file_numbers
                // had no phone column at all until 2026_09_06_000200.
                'applicant_phone' => ['nullable', 'string', 'max:100', new NigerianPhone()],
                'phone_confirmed' => 'nullable|boolean',
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

            // Allocation source/entity/slip + the parcel number the sheet quotes.
            $allocation = $this->resolveAllocationColumns($validated);

            $transactionResult = DB::connection('sqlsrv')->transaction(function () use ($validated, $landUseCode, $year, $creatorName, $commissionedAt, $allocation) {
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

                $suaFileNumberId = $connection->table('st_file_numbers')->insertGetId(array_merge($this->propertyLocationColumns($validated), $allocation, [
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
                    'applicant_phone' => $validated['applicant_phone'] ?? null,
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

            $this->sendStCommissioningSms(
                $transactionResult['unit_file_number'],
                $validated,
                $transactionResult['sua_file_number_id'] ?? null
            );

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
                // Applicant contact, for the ST commissioning SMS. st_file_numbers
                // had no phone column at all until 2026_09_06_000200.
                'applicant_phone' => ['nullable', 'string', 'max:100', new NigerianPhone()],
                'phone_confirmed' => 'nullable|boolean',
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

            // Generate PuA file number based on parent (ST-COM-2025-5 or ST-CON-IND-2026-1308)
            $parentParts = $this->parseStFileNumber($validated['parent_file_number']);
            $landUseCode = $parentParts['land_use_code'] ?? '';
            $year = $parentParts['year'] ?? intval(date('Y'));
            $parentSerial = $parentParts['serial'] ?? 1;

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
            $landUseFullName = $this->landUseFullNameFromCode($landUseCode) ?? 'COMMERCIAL';
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
                    'applicant_phone' => $validated['applicant_phone'] ?? null,
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

            $this->sendStCommissioningSms(
                $unitFileNo,
                $validated,
                $transactionResult['pua_file_number_id'] ?? null
            );

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
     * Keep the tracking ID the form displayed, unless it is malformed or already
     * taken — in which case mint a fresh one rather than fail the commissioning.
     */
    private function useOrMintTrackingId(?string $trackingId): string
    {
        $trackingId = strtoupper(trim((string) $trackingId));

        if (!preg_match('/^TRK-[A-Z0-9]{4,12}-[A-Z0-9]{3,8}$/', $trackingId)) {
            return $this->generateTra();
        }

        $taken = DB::connection('sqlsrv')->table('st_file_numbers')->where('tra', $trackingId)->exists();

        return $taken ? $this->generateTra() : $trackingId;
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
            $nextSerial = $this->nextPrimarySerial($landUseCode, (int) $year);

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
            $parsed = $this->parseStFileNumber($fileNo);

            return $parsed['serial'] ?? 1; // ST-COM-2025-5 -> 5

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

            // 2. Build property address. The Location Details section posts a composed
            // address on some paths; otherwise assemble one from the property_* fields
            // the form actually sends (house no, plot, street, district, LGA, state).
            $addressParts = [];
            if (!empty($data['property_house_no'])) $addressParts[] = 'No. ' . $data['property_house_no'];
            if (!empty($data['property_plot_no']))  $addressParts[] = 'Plot ' . $data['property_plot_no'];
            foreach (['property_street_name', 'property_district', 'property_lga', 'property_state'] as $key) {
                if (!empty($data[$key])) {
                    $addressParts[] = trim((string) $data[$key]);
                }
            }
            // Legacy loose keys, kept for callers that still pass them.
            if (empty($addressParts)) {
                if (!empty($data['plot_no'])) $addressParts[] = 'Plot ' . $data['plot_no'];
                if (!empty($data['location'])) $addressParts[] = $data['location'];
                if (!empty($data['lga'])) $addressParts[] = $data['lga'];
            }
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
