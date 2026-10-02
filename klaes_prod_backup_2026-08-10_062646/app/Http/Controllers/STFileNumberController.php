<?php

namespace App\Http\Controllers;

use App\Services\STFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ST File Number Controller
 * 
 * Handles AJAX endpoints for ST file number generation and management
 */
class STFileNumberController extends Controller
{
    protected $stFileNumberService;

    public function __construct(STFileNumberService $stFileNumberService)
    {
        $this->stFileNumberService = $stFileNumberService;
    }

    /**
     * Reserve a primary ST file number
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reservePrimary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'land_use' => 'required|string|in:Residential,Commercial,Industrial,Mixed,RESIDENTIAL,COMMERCIAL,INDUSTRIAL,MIXED,INDUSTRY',
            'applicant_type' => 'nullable|string|in:Individual,Corporate,Multiple',
            'applicant_title' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'surname' => 'nullable|string|max:100',
            'corporate_name' => 'nullable|string|max:200',
            'rc_number' => 'nullable|string|max:50',
            'multiple_owners_names' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->stFileNumberService->generatePrimaryFileNumber(
                $request->input('land_use'),
                $request->only([
                    'applicant_type',
                    'applicant_title',
                    'first_name',
                    'surname',
                    'corporate_name',
                    'rc_number',
                    'multiple_owners_names'
                ])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in reservePrimary: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the file number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reserve SUA file numbers
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reserveSUA(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'land_use' => 'required|string|in:Residential,Commercial,Industrial,Mixed,RESIDENTIAL,COMMERCIAL,INDUSTRIAL,MIXED,INDUSTRY',
            'applicant_type' => 'nullable|string|in:Individual,Corporate,Multiple',
            'applicant_title' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'surname' => 'nullable|string|max:100',
            'corporate_name' => 'nullable|string|max:200',
            'rc_number' => 'nullable|string|max:50',
            'multiple_owners_names' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->stFileNumberService->generateSUAFileNumber(
                $request->input('land_use'),
                $request->only([
                    'applicant_type',
                    'applicant_title',
                    'first_name',
                    'surname',
                    'corporate_name',
                    'rc_number',
                    'multiple_owners_names'
                ])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in reserveSUA: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating SUA file numbers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reserve PUA file number
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reservePUA(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'parent_file_number' => 'required|string|regex:/^ST-[A-Z]+-\d{4}-\d+$/',
            'applicant_type' => 'nullable|string|in:Individual,Corporate,Multiple',
            'applicant_title' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'surname' => 'nullable|string|max:100',
            'corporate_name' => 'nullable|string|max:200',
            'rc_number' => 'nullable|string|max:50',
            'multiple_owners_names' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->stFileNumberService->generatePUAFileNumber(
                $request->input('parent_file_number'),
                $request->only([
                    'applicant_type',
                    'applicant_title',
                    'first_name',
                    'surname',
                    'corporate_name',
                    'rc_number',
                    'multiple_owners_names'
                ])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in reservePUA: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating PUA file number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm a file number reservation
     * 
     * @param Request $request
     * @param string $fileNumber
     * @return JsonResponse
     */
    public function confirm(Request $request, string $fileNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|integer|exists:mother_applications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->stFileNumberService->confirmReservation(
                $fileNumber,
                $request->input('application_id')
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'File number reservation confirmed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to confirm reservation. File number may not exist or already be used.'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error in confirm: ' . $e->getMessage(), [
                'file_number' => $fileNumber,
                'application_id' => $request->input('application_id'),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while confirming the reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release a file number reservation
     * 
     * @param string $fileNumber
     * @return JsonResponse
     */
    public function release(string $fileNumber): JsonResponse
    {
        try {
            $result = $this->stFileNumberService->releaseReservation($fileNumber);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'File number reservation released successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to release reservation. File number may not exist or already be used.'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error in release: ' . $e->getMessage(), [
                'file_number' => $fileNumber,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while releasing the reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file number details
     * 
     * @param string $fileNumber
     * @return JsonResponse
     */
    public function getDetails(string $fileNumber): JsonResponse
    {
        try {
            $details = $this->stFileNumberService->getFileNumberDetails($fileNumber);

            if ($details) {
                return response()->json([
                    'success' => true,
                    'data' => $details
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error in getDetails: ' . $e->getMessage(), [
                'file_number' => $fileNumber,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving file number details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get units by parent file number
     * 
     * @param string $parentFileNumber
     * @return JsonResponse
     */
    public function getUnitsByParent(string $parentFileNumber): JsonResponse
    {
        try {
            $units = $this->stFileNumberService->getUnitsByParent($parentFileNumber);

            return response()->json([
                'success' => true,
                'data' => $units
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getUnitsByParent: ' . $e->getMessage(), [
                'parent_file_number' => $parentFileNumber,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next available file number preview (without reserving)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getNextPreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'land_use' => 'required|string|in:Residential,Commercial,Industrial,Mixed,RESIDENTIAL,COMMERCIAL,INDUSTRIAL,MIXED,INDUSTRY',
            'type' => 'required|string|in:PRIMARY,SUA,PUA',
            'parent_file_number' => 'required_if:type,PUA|string|regex:/^ST-[A-Z]+-\d{4}-\d+$/'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // This is a preview method that doesn't actually reserve the number
            $landUse = $request->input('land_use');
            $type = $request->input('type');

            // Normalize land use
            $landUseInfo = $this->stFileNumberService->normalizeLandUse($landUse);
            $year = date('Y');

            if ($type === 'PUA') {
                $parentFileNumber = $request->input('parent_file_number');

                // Parse parent file number
                if (!preg_match('/^ST-([A-Z]+)-(\d{4})-(\d+)$/', $parentFileNumber, $matches)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid parent file number format'
                    ], 422);
                }

                // Get next unit sequence
                $nextUnitSequence = $this->getNextUnitSequencePreview($parentFileNumber);
                $unitSequencePadded = str_pad($nextUnitSequence, 3, '0', STR_PAD_LEFT);
                $previewFileNumber = "{$parentFileNumber}-{$unitSequencePadded}";

                return response()->json([
                    'success' => true,
                    'data' => [
                        'preview_file_number' => $previewFileNumber,
                        'np_fileno' => $parentFileNumber,
                        'unit_fileno' => $previewFileNumber,
                        'mls_fileno' => $parentFileNumber,
                        'unit_sequence' => $nextUnitSequence,
                        'type' => $type
                    ]
                ]);

            } else {
                // For PRIMARY and SUA
                $nextSerial = $this->getNextSerialPreview($landUseInfo['code'], $year);
                $npFileNo = "ST-{$landUseInfo['code']}-{$year}-{$nextSerial}";

                if ($type === 'SUA') {
                    $unitFileNo = "{$npFileNo}-001";
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'preview_np_fileno' => $npFileNo,
                            'preview_unit_fileno' => $unitFileNo,
                            'preview_mls_fileno' => $npFileNo,
                            'serial_no' => $nextSerial,
                            'unit_sequence' => 1,
                            'type' => $type
                        ]
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'preview_file_number' => $npFileNo,
                            'serial_no' => $nextSerial,
                            'type' => $type
                        ]
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error in getNextPreview: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to normalize land use (duplicated from service for preview)
     */
    private function normalizeLandUse(string $landUse): array
    {
        return match (strtoupper(trim($landUse))) {
            'COMMERCIAL', 'COMMERCIAL USE' => ['full' => 'Commercial', 'code' => 'COM'],
            'INDUSTRIAL', 'INDUSTRIAL USE', 'INDUSTRY' => ['full' => 'Industrial', 'code' => 'IND'],
            'RESIDENTIAL', 'RESIDENTIAL USE' => ['full' => 'Residential', 'code' => 'RES'],
            'MIXED', 'MIXED USE' => ['full' => 'Mixed', 'code' => 'MIXED'],
            default => ['full' => 'Residential', 'code' => 'RES']
        };
    }

    /**
     * Helper method to get next serial for preview (unified across all ST types)
     */
    private function getNextSerialPreview(string $landUseCode, int $year): int
    {
        // Get the latest serial number from unified st_file_numbers table
        $maxSerial = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('land_use_code', $landUseCode)
            ->where('year', $year)
            ->max('serial_no');

        return ($maxSerial ?? 0) + 1;
    }

    /**
     * Helper method to get next unit sequence for preview
     */
    private function getNextUnitSequencePreview(string $parentFileNumber): int
    {
        $maxUnit = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('np_fileno', $parentFileNumber)
            ->whereIn('file_no_type', ['PUA', 'SUA'])
            ->max('unit_sequence');

        return ($maxUnit ?? 0) + 1;
    }

    /**
     * Validate a file number exists and return its details
     * 
     * @param string $fileNumber
     * @return JsonResponse
     */
    public function validateFileNumber(string $fileNumber): JsonResponse
    {
        try {
            $record = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('np_fileno', $fileNumber)
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File number found',
                'data' => [
                    'id' => $record->id,
                    'np_fileno' => $record->np_fileno,
                    'fileno' => $record->fileno,
                    'land_use' => $record->land_use,
                    'land_use_code' => $record->land_use_code,
                    'file_no_type' => $record->file_no_type,
                    'status' => $record->status,
                    'applicant_type' => $record->applicant_type,
                    'first_name' => $record->first_name,
                    'surname' => $record->surname,
                    'corporate_name' => $record->corporate_name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating file number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for file numbers with filters
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = DB::connection('sqlsrv')->table('st_file_numbers');

            // Apply filters
            if ($request->has('file_no_type')) {
                $query->where('file_no_type', $request->input('file_no_type'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            } else {
                // By default, exclude USED status to prevent duplicate file number usage
                $query->where('status', '!=', 'USED');
            }

            if ($request->has('land_use_code')) {
                $query->where('land_use_code', $request->input('land_use_code'));
            }

            if ($request->has('search_term')) {
                $searchTerm = $request->input('search_term');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('np_fileno', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('fileno', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('surname', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('corporate_name', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Pagination
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 10);
            $offset = ($page - 1) * $perPage;

            $total = $query->count();
            $records = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'data' => $records,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching file numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get preview of next file number without creating database record
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'land_use' => 'required|string|in:Residential,Commercial,Industrial,Mixed,RESIDENTIAL,COMMERCIAL,INDUSTRIAL,MIXED,INDUSTRY',
            'type' => 'required|string|in:primary,sua,pua'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $landUse = $request->input('land_use');
            $type = $request->input('type');

            // Normalize land use
            $landUseInfo = $this->stFileNumberService->normalizeLandUse($landUse);
            $year = date('Y');

            // Get what the next serial would be (without creating record)
            $nextSerial = $this->stFileNumberService->peekNextSerial($landUseInfo['code'], $year);

            // Generate preview file numbers based on type
            $preview = [];

            if ($type === 'primary') {
                $npFileNo = "ST-{$landUseInfo['code']}-{$year}-{$nextSerial}";
                $preview = [
                    'np_fileno' => $npFileNo,
                    'fileno' => $npFileNo,
                    'mls_fileno' => $npFileNo
                ];
            } elseif ($type === 'sua') {
                $npFileNo = "ST-{$landUseInfo['code']}-{$year}-{$nextSerial}";
                $unitFileNo = "ST-{$landUseInfo['code']}-{$year}-{$nextSerial}-001";
                $preview = [
                    'np_fileno' => $npFileNo,
                    'fileno' => $unitFileNo,
                    'mls_fileno' => $npFileNo
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Preview generated successfully',
                'data' => [
                    'preview' => $preview,
                    'land_use' => $landUseInfo['full'],
                    'land_use_code' => $landUseInfo['code'],
                    'serial_no' => $nextSerial,
                    'year' => $year,
                    'type' => $type
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ST File Number Preview Error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available buyers for a parent PRIMARY file number
     * 
     * @param string $parentFileNumber
     * @return JsonResponse
     */
    public function getBuyersForParent($parentFileNumber): JsonResponse
    {
        try {
            Log::info('Fetching buyers for parent file number', ['parent' => $parentFileNumber]);

            // Get parent record
            $parentRecord = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('np_fileno', $parentFileNumber)
                ->where('file_no_type', 'PRIMARY')
                ->first();

            if (!$parentRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent file number not found'
                ], 404);
            }

            if (!$parentRecord->mother_application_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent file number is not linked to a mother application'
                ], 404);
            }

            // Fetch buyers for this application
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list as bl')
                ->leftJoin('st_unit_measurements as sum', function ($join) use ($parentRecord) {
                    $join->on('bl.id', '=', 'sum.buyer_id')
                        ->where('sum.application_id', '=', $parentRecord->mother_application_id);
                })
                ->where('bl.application_id', $parentRecord->mother_application_id)
                // Exclude buyers already linked to PUA file numbers
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('st_file_numbers as sfn')
                        ->whereColumn('sfn.buyer_list_id', 'bl.id')
                        ->where('sfn.file_no_type', 'PUA');
                })
                ->select(
                    'bl.id as buyer_id',
                    'bl.buyer_title',
                    'bl.buyer_name',
                    'bl.unit_no',
                    'bl.land_use',
                    'sum.measurement'
                )
                ->get();

            Log::info('Found buyers for parent', ['count' => $buyers->count()]);

            return response()->json([
                'success' => true,
                'data' => $buyers,
                'parent_file_number' => $parentFileNumber,
                'message' => $buyers->count() > 0
                    ? "Found {$buyers->count()} available buyer(s)"
                    : 'No buyers available for this parent application'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching buyers for parent: ' . $e->getMessage(), [
                'parent_file_number' => $parentFileNumber,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching buyers: ' . $e->getMessage()
            ], 500);
        }
    }
}