<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use App\Models\PropertyRecord;
use App\Support\PropertyRecordFormatter;
use App\Models\InstrumentType;
use App\Support\DateFormatter;
use App\Services\TimelineWeightingService;

class PropertyCardController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Cached CofO table name to avoid repeated schema lookups.
     */
    protected ?string $resolvedCofOTable = null;

    public function index()
    {
        $PageTitle = 'Property Record Assistant';
        $PageDescription = '';

        $featuredRecord = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($query) {
                $query->where('title_type', '!=', 'Certificate of Occupancy')
                    ->orWhereNull('title_type');
            })
            ->where(function ($query) {
                $query->where('instrument_type', '!=', 'Certificate of Occupancy')
                    ->orWhereNull('instrument_type');
            })
            ->select([
                'id',
                'prop_id',
                'kangisFileNo',
                'mlsFNo',
                'NewKANGISFileno',
                'fileno',
                'temp_fileno',
                'title_type',
                'transaction_type',
                'instrument_type',
                'plot_no',
                'block_no',
                'layout',
                'districtName',
                'lgsaOrCity',
                'location',
                'property_description',
                'transaction_date',
                'created_at',
                'regNo',
                'Assignor',
                'Assignee',
                'Grantor',
                'Grantee',
                'Mortgagor',
                'Mortgagee',
                'Surrenderor',
                'Surrenderee',
                'Lessor',
                'Lessee',
            ])
            // Keep selected card consistent with DataTable Date Captured ordering.
            // created_at contains mixed legacy formats, so id is the reliable chronological proxy.
            ->orderByDesc('id')
            ->first();

        // Fetch systemic active instrument types for filtering, matching the Add modal options
        $instrumentTypes = InstrumentType::active()
            ->orderBy('InstrumentName')
            ->pluck('InstrumentName');

        return view('propertycard.index', compact('pageLength', 'PageTitle', 'PageDescription', 'featuredRecord', 'instrumentTypes'));
    }
    public function capture(Request $request)
    {


        return view('propertycard.capture');
    }

    public function getData(Request $request)
    {
        try {
            // Get DataTables parameters
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = (int) $request->input('length', 20);
            if ($length <= 0) {
                $length = 20;
            }
            $length = min($length, 200);
            $searchValue = $request->input('search.value', '');

            // Get ordering parameters
            $orderColumn = $request->input('order.0.column', 9); // Default to Date Captured column (index 9)
            $orderDirInput = strtolower($request->input('order.0.dir', 'desc'));
            $orderDir = $orderDirInput === 'asc' ? 'asc' : 'desc';

            // Column mapping must match the DataTable column order in the shared partial:
            // 0=S/N, 1=File Number, 2=Reg Particulars, 3=Instrument Type,
            // 4=Party 1, 5=Party 2, 6=Party 3, 7=Party 4, 8=Location,
            // 9=Date Captured, 10=Actions
            $columns = [
                0  => 'id',               // S/N (serial)
                1  => 'kangisFileNo',      // File Number
                2  => 'regNo',             // Registration Particulars
                3  => 'transaction_type',  // Instrument Type
                4  => 'party_1',           // Party 1
                5  => 'party_2',           // Party 2
                6  => 'party_3',           // Party 3
                7  => 'party_4',           // Party 4
                8  => 'property_description', // Location
                9  => 'created_at',        // Date Captured
                10 => 'id',                // Actions
            ];

            $selectColumns = [
                'id',
                'prop_id',
                'kangisFileNo',
                'mlsFNo',
                'NewKANGISFileno',
                'fileno',
                'temp_fileno',
                'title_type',
                'transaction_type',
                'instrument_type',
                'property_description',
                'location',
                'streetName',
                'house_no',
                'districtName',
                'plot_no',
                'lgsaOrCity',
                'layout',
                'land_use',
                'regNo',
                'serialNo',
                'pageNo',
                'volumeNo',
                'transaction_date',
                'created_at',
                'Assignor',
                'Assignee',
                'Grantor',
                'Grantee',
                'Mortgagor',
                'Mortgagee',
                'Surrenderor',
                'Surrenderee',
                'Lessor',
                'Lessee',
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                'deeds_date',
                'deeds_time',
            ];

            $baseQuery = DB::connection('sqlsrv')->table('pra')
                ->where(function ($q) {
                    $q->where('title_type', '!=', 'Certificate of Occupancy')
                        ->orWhereNull('title_type');
                })
                ->where(function ($q) {
                    $q->where('instrument_type', '!=', 'Certificate of Occupancy')
                        ->orWhereNull('instrument_type');
                });

            // Use approximate count to avoid timeout - exact count isn't critical for pagination
            $recordsTotal = 20000;

            $query = (clone $baseQuery)->select($selectColumns);

            if ($request->filled('instrument_type')) {
                $query->where('instrument_type', $request->input('instrument_type'));
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $likeValue = "%{$searchValue}%";
                    $q->where('mlsFNo', 'like', $likeValue)
                        ->orWhere('kangisFileNo', 'like', $likeValue)
                        ->orWhere('NewKANGISFileno', 'like', $likeValue)
                        ->orWhere('fileno', 'like', $likeValue)
                        ->orWhere('temp_fileno', 'like', $likeValue)
                        ->orWhere('regNo', 'like', $likeValue)
                        ->orWhere('prop_id', 'like', $likeValue)
                        ->orWhere('serialNo', 'like', $likeValue)
                        ->orWhere('pageNo', 'like', $likeValue)
                        ->orWhere('volumeNo', 'like', $likeValue)
                        ->orWhere('property_description', 'like', $likeValue)
                        ->orWhere('location', 'like', $likeValue)
                        ->orWhere('transaction_type', 'like', $likeValue)
                        ->orWhere('instrument_type', 'like', $likeValue)
                        ->orWhere('Grantor', 'like', $likeValue)
                        ->orWhere('Grantee', 'like', $likeValue)
                        ->orWhere('Assignor', 'like', $likeValue)
                        ->orWhere('Assignee', 'like', $likeValue)
                        ->orWhere('Mortgagor', 'like', $likeValue)
                        ->orWhere('Mortgagee', 'like', $likeValue);
                });
            }

            $recordsFiltered = !empty($searchValue)
                ? min((clone $query)->count(), 1000) // Limit count query to prevent timeout
                : $recordsTotal;

            $orderColumnName = $columns[$orderColumn] ?? 'created_at';

            if ($orderColumnName === 'transaction_date') {
                $query->orderByRaw('CASE WHEN transaction_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('transaction_date', $orderDir)
                    ->orderBy('id', 'desc');
            } elseif ($orderColumnName === 'created_at') {
                // created_at has mixed date formats; use id as a reliable chronological proxy
                $query->orderBy('id', $orderDir);
            } else {
                $query->orderBy($orderColumnName, $orderDir);
                if ($orderColumnName !== 'id') {
                    $query->orderBy('id', 'desc');
                }
            }

            $records = $query->skip($start)->take($length)->get();
            $timelineCounts = $this->resolveTimelineCounts($records);

            // Format the data for DataTables
            $data = [];
            foreach ($records as $record) {
                $normalized = PropertyRecordFormatter::normalizeRecord($record);
                $fileNumberDisplay = PropertyRecordFormatter::resolveFileNumber($normalized);
                $landUseDisplay = PropertyRecordFormatter::deriveLandUse($normalized);
                $locationDisplay = PropertyRecordFormatter::formatLocation($normalized);
                $registrationDisplay = PropertyRecordFormatter::formatRegistrationParticulars($normalized);
                $parties = PropertyRecordFormatter::resolvePartyDetails($normalized);

                $propId = $record->prop_id ?? null;
                $propIdKey = $propId !== null ? trim((string) $propId) : null;
                $timelineCount = $propIdKey ? (int) ($timelineCounts[$propIdKey] ?? 1) : 1;

                $data[] = [
                    'id' => $record->id,
                    'prop_id' => $propId,
                    'kangisFileNo' => $record->kangisFileNo,
                    'mlsFNo' => $record->mlsFNo,
                    'NewKANGISFileno' => $record->NewKANGISFileno,
                    'fileno' => $record->fileno ?? null,
                    'temp_fileno' => $record->temp_fileno ?? null,
                    'property_description' => $record->property_description,
                    'file_number_display' => $fileNumberDisplay,
                    'location' => $locationDisplay,
                    'regNo' => $registrationDisplay,
                    'registration_particulars' => $registrationDisplay,
                    'land_use' => $landUseDisplay,
                    'land_use_type' => $landUseDisplay,
                    'transaction_type' => $record->transaction_type,
                    'instrument_type' => $record->instrument_type,
                    'transaction_date' => $record->transaction_date,
                    'deeds_date' => $record->deeds_date,
                    'deeds_time' => $record->deeds_time,
                    'created_at' => DateFormatter::toISO($record->created_at)
                        ?? DateFormatter::toISO($record->transaction_date)
                        ?? DateFormatter::toISO($record->deeds_date),
                    'timeline_count' => max(1, $timelineCount),
                    // Party fields matching the DataTable columns
                    'party_1' => $record->party_1 ?? $parties['from_value'],
                    'party_2' => $record->party_2 ?? $parties['to_value'],
                    'party_3' => $record->party_3 ?? null,
                    'party_4' => $record->party_4 ?? null,
                    'grantor' => $parties['from_value'],
                    'grantor_label' => $parties['from_label'],
                    'grantee' => $parties['to_value'],
                    'grantee_label' => $parties['to_label'],
                ];
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getData: ' . $e->getMessage());
            \Log::error('Error trace: ' . $e->getTraceAsString());

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error retrieving data: ' . $e->getMessage()
            ]);
        }
    }

    public function search(Request $request)
    {
        \Log::info('Search request received:', $request->all());

        $query = DB::table('pra');
        $hasConditions = false;

        // Handle each possible search field
        if ($request->filled('kangisFileNo')) {
            $query->where('kangisFileNo', 'LIKE', '%' . $request->kangisFileNo . '%');
            $hasConditions = true;
        }

        if ($request->filled('mlsfNo')) {
            $query->where('mlsfNo', 'LIKE', '%' . $request->mlsfNo . '%');
            $hasConditions = true;
        }

        if ($request->filled('oldTitleSerialNo')) {
            $query->where('oldTitleSerialNo', 'LIKE', '%' . $request->oldTitleSerialNo . '%');
            $hasConditions = true;
        }

        if ($request->filled('oldTitlePageNo')) {
            $query->where('oldTitlePageNo', 'LIKE', '%' . $request->oldTitlePageNo . '%');
            $hasConditions = true;
        }

        if ($request->filled('oldTitleVolumeNo')) {
            $query->where('oldTitleVolumeNo', 'LIKE', '%' . $request->oldTitleVolumeNo . '%');
            $hasConditions = true;
        }

        if ($request->filled('lgaName')) {
            $query->where('lgaName', 'LIKE', '%' . $request->lgaName . '%');
            $hasConditions = true;
        }

        if ($request->filled('originalAllottee')) {
            $query->where('originalAllottee', 'LIKE', '%' . $request->originalAllottee . '%');
            $hasConditions = true;
        }

        if ($request->filled('currentAllottee')) {
            $query->where('currentAllottee', 'LIKE', '%' . $request->currentAllottee . '%');
            $hasConditions = true;
        }

        if (!$hasConditions) {
            return response()->json(['success' => false, 'message' => 'No search criteria provided']);
        }

        $result = $query->first();
        \Log::info('Search result:', ['result' => $result]);

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'No matching records found']);
        }

        // Map the database fields to view fields
        $kangisFileNo = $result->kangisFileNo ?? '';
        $fileNoPrefix = '';
        $fileNumber = '';

        // Safely split kangisFileNo into prefix and number
        if (!empty($kangisFileNo)) {
            $parts = explode(' ', $kangisFileNo, 2);
            $fileNoPrefix = $parts[0] ?? '';
            $fileNumber = $parts[1] ?? '';
        }

        $mappedResult = [
            'success' => true,
            'data' => [
                'id' => $result->id ?? 'N/A',
                'fileNoPrefix' => $fileNoPrefix,
                'fileNumber' => $fileNumber,
                'plotNo' => $result->plotNo ?: 'N/A',
                'blockNo' => $result->blockNo ?: 'N/A',
                'districtName' => $result->districtName ?: 'N/A',
                'lgaName' => $result->lgaName ?: 'N/A',
                'description' => trim(implode(' ', array_filter([
                    $result->plotNo,
                    $result->blockNo,
                    $result->districtName,
                    $result->lgaName
                ], function ($value) {
                    return $value !== null && $value !== '';
                }))),
                'originalAllottee' => $result->originalAllottee ?: 'N/A',
                'currentAllottee' => $result->currentAllottee ?: 'N/A',
                'oldTitleSerialNo' => $result->oldTitleSerialNo ?: 'N/A',
                'oldTitlePageNo' => $result->oldTitlePageNo ?: 'N/A',
                'oldTitleVolumeNo' => $result->oldTitleVolumeNo ?: 'N/A',
                'layoutName' => $result->layoutName ?: 'N/A',
                'mlsfNo' => $result->mlsfNo ?: 'N/A',
                'kangisFileNo' => $result->kangisFileNo ?: 'N/A'
            ]
        ];

        return response()->json($mappedResult);
    }


    public function create(Request $request)
    {
        $result = null;
        $recordCount = DB::table('pra')->count();
        return view('propertycard.create', compact('result', 'recordCount'));
    }

    public function store(Request $request)
    {
        \Log::info('Store request received:', $request->all());

        $validatedData = $request->validate([
            'planid' => 'nullable|string|max:255',
            'mlsfNo' => 'nullable|string|max:255',
            'kangisFileNo' => 'nullable|string|max:255',
            'plotNo' => 'nullable|string|max:255',
            'blockNo' => 'nullable|string|max:255',
            'approvedPlanNo' => 'nullable|string|max:255',
            'tpPlanNo' => 'nullable|string|max:255',
            'surveyedBy' => 'nullable|string|max:255',
            'drawnBy' => 'nullable|string|max:255',
            'checkedBy' => 'nullable|string|max:255',
            'passedBy' => 'nullable|string|max:255',
            'plotYearCreated' => 'nullable|string|max:255',
            'beaconControlName' => 'nullable|string|max:255',
            'beaconControlX' => 'nullable|string|max:255',
            'beaconControlY' => 'nullable|string|max:255',
            'metricSheetIndex' => 'nullable|string|max:255',
            'metricSheetNo' => 'nullable|string|max:255',
            'imperialSheet' => 'nullable|string|max:255',
            'imperialSheetNo' => 'nullable|string|max:255',
            'layoutName' => 'nullable|string|max:255',
            'districtName' => 'nullable|string|max:255',
            'lgaName' => 'nullable|string|max:255',
            'streetName' => 'nullable|string|max:255',
            'houseNo' => 'nullable|string|max:255',
            'tenancy' => 'nullable|string|max:255',
            'areaInHectares' => 'nullable|string|max:255',
            'titleStatus' => 'nullable|string|max:255',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'oldTitleSerialNo' => 'nullable|string|max:255',
            'oldTitlePageNo' => 'nullable|string|max:255',
            'oldTitleVolumeNo' => 'nullable|string|max:255',
            'deedsDate' => 'nullable|date',
            'deedsTime' => 'nullable|string|max:255',
            'certificateDate' => 'nullable|date',
            'originalAllottee' => 'nullable|string|max:255',
            'addressOfOriginalAllottee' => 'nullable|string|max:255',
            'titleIssuedYear' => 'nullable|string|max:255',
            'currentAllottee' => 'nullable|string|max:255',
            'addressOfCurrentAllottee' => 'nullable|string|max:255',
            'titleOfCurrentAllottee' => 'nullable|string|max:255',
            'currentYearTitleOwned' => 'nullable|string|max:255',
            'phoneNo' => 'nullable|string|max:255',
            'emailAddress' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'parcelNewlyDigitized' => 'nullable|string|max:255',
            'digitizationSource' => 'nullable|string|max:255',
            'workTime' => 'nullable|string|max:255',
            'electronicSupervisor' => 'nullable|string|max:255',
            'directorGeneral' => 'nullable|string|max:255',
            'evidenceOfPayment' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'transactionDocument' => 'nullable|string|max:255',
            'passportPhoto' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'nationalId' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'internationalPassport' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'businessRegCert' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'formCO7AndCO4' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'certOfIncorporation' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'memorandumAndArticle' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'letterOfAdmin' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'courtAffidavit' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'policeReport' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'newspaperAdvert' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'acknowledgementStatus' => 'nullable|string|max:255',
            'acknowledgementIssuedDate' => 'nullable|date',
            'acknowledgementIssuedBy' => 'nullable|string|max:255',
            'bankDraftSerialNo' => 'nullable|string|max:255',
            'bankName' => 'nullable|string|max:255',
            'conflictExistence' => 'nullable|string|max:255',
            'problemNature1' => 'nullable|string|max:255',
            'problemNature2' => 'nullable|string|max:255',
            'landUse' => 'nullable|string|max:255',
            'specifically' => 'nullable|string|max:255',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'nullable|string|max:255',
            'party_3' => 'nullable|string|max:255',
            'party_4' => 'nullable|string|max:255',
            'Mortgagor_2' => 'nullable|string|max:255',
        ]);

        // Create registration number from old components if present (strip leading zeros)
        if ($request->filled('oldTitleSerialNo')) $validatedData['oldTitleSerialNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitleSerialNo));
        if ($request->filled('oldTitlePageNo')) $validatedData['oldTitlePageNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitlePageNo));
        if ($request->filled('oldTitleVolumeNo')) $validatedData['oldTitleVolumeNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitleVolumeNo));

        $propertyRecord = new PropertyRecord($validatedData);

        // Handle file uploads
        $fileFields = [
            'picture',
            'passportPhoto',
            'nationalId',
            'internationalPassport',
            'businessRegCert',
            'formCO7AndCO4',
            'certOfIncorporation',
            'memorandumAndArticle',
            'letterOfAdmin',
            'courtAffidavit',
            'policeReport',
            'newspaperAdvert',
        ];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads', $filename, 'public'); // Store in the 'public' disk under 'uploads' folder
                $propertyRecord->$field = $path; // Save the path to the database
            }
        }

        $propertyRecord->save();

        return redirect()->route('propertycard.index')->with('success', 'Property record created successfully.');
    }

    public function savePropertyRecord(Request $request)
    {
        // Validate and sanitize the input
        $validatedData = $request->validate([
            'mlsfNo' => 'nullable|string|max:255',
            'kangisFileNo' => 'nullable|string|max:255',
            'plotNo' => 'nullable|string|max:255',
            'blockNo' => 'nullable|string|max:255',
            'approvedPlanNo' => 'nullable|string|max:255',
            'tpPlanNo' => 'nullable|string|max:255',
            'surveyedBy' => 'nullable|string|max:255',
            'layoutName' => 'nullable|string|max:255',
            'districtName' => 'nullable|string|max:255',
            'lgaName' => 'nullable|string|max:255',
            'oldTitleSerialNo' => 'nullable|string|max:255',
            'oldTitlePageNo' => 'nullable|string|max:255',
            'oldTitleVolumeNo' => 'nullable|string|max:255',
            'deedsDate' => 'nullable|date',
            'deedsTime' => 'nullable|string|max:255',
            'certificateDate' => 'nullable|date',
            'originalAllottee' => 'nullable|string|max:255',
            'addressOfOriginalAllottee' => 'nullable|string|max:255',
            'titleIssuedYear' => 'nullable|string|max:255',
            'changeOfOwnership' => 'nullable|string|max:255',
            'reasonForChange' => 'nullable|string|max:255',
            'currentAllottee' => 'nullable|string|max:255',
            'addressOfCurrentAllottee' => 'nullable|string|max:255',
            'titleOfCurrentAllottee' => 'nullable|string|max:255',
            'currentYearTitleOwned' => 'nullable|string|max:255',
            'phoneNo' => 'nullable|string|max:255',
            'landUse' => 'nullable|string|max:255',
            'specifically' => 'nullable|string|max:255',
            'tenancy' => 'nullable|string|max:255',
            'areaInHectares' => 'nullable|string|max:255',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'nullable|string|max:255',
            'party_3' => 'nullable|string|max:255',
            'party_4' => 'nullable|string|max:255',
        ]);

        // Create registration number from old components if present (strip leading zeros)
        if ($request->filled('oldTitleSerialNo')) $validatedData['oldTitleSerialNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitleSerialNo));
        if ($request->filled('oldTitlePageNo')) $validatedData['oldTitlePageNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitlePageNo));
        if ($request->filled('oldTitleVolumeNo')) $validatedData['oldTitleVolumeNo'] = (string)intval(preg_replace('/\D/', '', $request->oldTitleVolumeNo));

        // Create and save a new property record ensuring we use the pra table
        $record = new \App\Models\PropertyRecord($validatedData);
        $record->setDataSource('pra');
        $record->save();

        return response()->json(['success' => true]);
    }

    public function navigateRecord(Request $request)
    {
        $currentId = $request->input('currentId');
        $direction = $request->input('direction');

        // Query builder for pra table
        $query = DB::table('pra');

        // Handle different navigation directions
        switch ($direction) {
            case 'first':
                $record = $query->orderBy('id', 'asc')->first();
                break;

            case 'last':
                $record = $query->orderBy('id', 'desc')->first();
                break;

            case 'previous':
                if (empty($currentId)) {
                    $record = $query->orderBy('id', 'desc')->first();
                } else {
                    $record = $query->where('id', '<', $currentId)
                        ->orderBy('id', 'desc')
                        ->first();

                    if (!$record) {
                        $record = $query->orderBy('id', 'desc')->first();
                    }
                }
                break;

            case 'next':
                if (empty($currentId)) {
                    $record = $query->orderBy('id', 'asc')->first();
                } else {
                    $record = $query->where('id', '>', $currentId)
                        ->orderBy('id', 'asc')
                        ->first();

                    if (!$record) {
                        $record = $query->orderBy('id', 'asc')->first();
                    }
                }
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Invalid navigation direction']);
        }

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No records found']);
        }

        // Safely map kangisFileNo parts without assuming a space exists
        $fileNoPrefix = '';
        $fileNumber = '';
        if (!empty($record->kangisFileNo)) {
            $pos = strpos($record->kangisFileNo, ' ');
            if ($pos !== false) {
                $fileNoPrefix = substr($record->kangisFileNo, 0, $pos);
                $fileNumber = substr($record->kangisFileNo, $pos + 1);
            } else {
                $fileNoPrefix = $record->kangisFileNo;
            }
        }

        $mappedResult = [
            'id' => $record->id ?? 'N/A',
            'fileNoPrefix' => $fileNoPrefix ?: 'N/A',
            'fileNumber' => $fileNumber ?: 'N/A',
            'plotNo' => $record->plotNo ?: 'N/A',
            'blockNo' => $record->blockNo ?: 'N/A',
            'districtName' => $record->districtName ?: 'N/A',
            'lgaName' => $record->lgaName ?: 'N/A',
            'description' => $record->specifically ?: 'N/A',
            'originalAllottee' => $record->originalAllottee ?: 'N/A',
            'currentAllottee' => $record->currentAllottee ?: 'N/A',
            'layoutName' => $record->layoutName ?: 'N/A',
            'locationSummary' => ($summary = trim(implode(' ', array_filter([
                $record->plotNo,
                $record->blockNo,
                $record->districtName,
                $record->lgaName
            ], function ($value) {
                return $value !== null && $value !== '';
            })))) !== '' ? $summary : 'N/A',
            'oldTitleSerialNo' => $record->oldTitleSerialNo ?: 'N/A',
            'oldTitlePageNo' => $record->oldTitlePageNo ?: 'N/A',
            'oldTitleVolumeNo' => $record->oldTitleVolumeNo ?: 'N/A',
            'mlsfNo' => $record->mlsfNo ?: 'N/A',
            'kangisFileNo' => $record->kangisFileNo ?: 'N/A'
        ];

        $recordCount = DB::table('pra')->count();

        return response()->json(array_merge(['success' => true, 'recordCount' => $recordCount], $mappedResult));
    }

    public function getRecordDetails(Request $request)
    {
        try {
            $id = $request->input('id');

            if (!$id) {
                return response()->json(['success' => false, 'message' => 'Record ID is required']);
            }

            $record = DB::table('pra')->where('id', $id)->first();

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found']);
            }

            return response()->json(['success' => true, 'data' => $record]);
        } catch (\Exception $e) {
            \Log::error('Error fetching record details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    public function aiIndex()
    {
        return view('propertycard.ai.index');
    }

    public function saveAiPropertyRecord(Request $request)
    {
        \Log::info('AI Property Record save request received:', $request->all());

        // Transform the AI form data to match the PropertyRecordController's expected format
        $transformedRequest = new Request();

        // Copy all original data first
        $transformedRequest->replace($request->all());

        // Robust file number handling (mirror manual behavior)
        // If caller already provided any of these, respect them
        $mlsExisting = trim((string) $request->input('mlsFNo', ''));
        $kangisExisting = trim((string) $request->input('kangisFileNo', ''));
        $newKangisExisting = trim((string) $request->input('NewKANGISFileno', ''));

        // Derive a complete file number if needed
        $fileNumber = $request->input('complete-file-no') ?: $request->input('fileno');
        if (!$fileNumber && $request->filled('file-prefix') && $request->filled('file-serial-no')) {
            $fileNumber = trim($request->input('file-prefix') . ' ' . $request->input('file-serial-no'));
        }

        // Determine which column to target
        $fileNumberType = $request->input('file-number-type');
        // Fallback to activeFileTab from AI UI (values like 'mls', 'kangis', 'newkangis')
        if (!$fileNumberType) {
            $aft = $request->input('activeFileTab');
            if ($aft) {
                $map = [
                    'mls' => 'mlsFileNo',
                    'mlsFNo' => 'mlsFileNo',
                    'kangis' => 'kangisFileNo',
                    'kangisFileNo' => 'kangisFileNo',
                    'newkangis' => 'newKangisFileNo',
                    'NewKANGISFileno' => 'newKangisFileNo',
                ];
                $fileNumberType = $map[$aft] ?? null;
            }
        }

        // If specific file number fields are missing, populate based on detected type
        if (!$mlsExisting && !$kangisExisting && !$newKangisExisting && $fileNumber) {
            switch ($fileNumberType) {
                case 'mlsFileNo':
                    $transformedRequest->merge(['mlsFNo' => $fileNumber]);
                    break;
                case 'newKangisFileNo':
                    $transformedRequest->merge(['NewKANGISFileno' => $fileNumber]);
                    break;
                case 'kangisFileNo':
                default:
                    $transformedRequest->merge(['kangisFileNo' => $fileNumber]);
                    break;
            }
        }
        // Otherwise, leave any provided mlsFNo/kangisFileNo/NewKANGISFileno as-is

        // Normalize street/district selections
        $streetName = $request->input('streetName');
        if ($streetName === 'other' && $request->input('otherStreetName')) {
            $streetName = $request->input('otherStreetName');
        }
        $district = $request->input('district');
        if ($district === 'other' && $request->input('otherDistrict')) {
            $district = $request->input('otherDistrict');
        }

        // Keep lgsaOrCity provided by caller; fallback to legacy "lga" param if needed
        $lgsaOrCity = $request->input('lgsaOrCity');
        if (!$lgsaOrCity) {
            $lgsaOrCity = $request->input('lga');
        }

        // Build location string similar to manual assistant
        $location = trim(implode(', ', array_filter([
            $request->input('house_no'),
            $streetName,
            $district,
            $lgsaOrCity,
            $request->input('state', 'Kano')
        ], function ($v) {
            return !is_null($v) && $v !== '';
        })));

        // Merge normalized fields
        $transformedRequest->merge([
            'location' => $location,
            'lgsaOrCity' => $lgsaOrCity, // do not overwrite with null
            'title_type' => $request->input('title_type') ?: 'STATUTORY RIGHT OF OCCUPANCY',
            'instrumentType' => $request->input('instrumentType') ?: $request->input('transactionType'),
        ]);

        // Use the PropertyRecordController's store method which has the correct database mapping
        $propertyRecordController = app(\App\Http\Controllers\PropertyRecordController::class);
        return $propertyRecordController->store($transformedRequest);
    }

    public function cofoIndex()
    {
        $PageTitle = 'Certificate of Occupancy (CofO)';
        $PageDescription = '';

        $cofoTable = $this->resolveCofOTable();
        $cofoRecords = collect();

        if ($cofoTable) {
            $cofoRecords = DB::connection('sqlsrv')
                ->table($cofoTable)
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        $pageLength = 25; // set default page length for CofO records
        return view('propertycard.cofo', compact('pageLength', 'PageTitle', 'PageDescription', 'cofoRecords'));
    }

    public function getCofOData(Request $request)
    {
        try {
            $cofoTable = $this->resolveCofOTable();

            if (!$cofoTable) {
                return response()->json([
                    'draw' => intval($request->input('draw', 1)),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'CofO data source is unavailable. Please contact the system administrator.'
                ]);
            }

            // Get DataTables parameters
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = $request->input('length', 25);
            $searchValue = $request->input('search.value', '');

            // Get ordering parameters
            $orderColumn = $request->input('order.0.column', 8); // Default to created_at column (index 8)
            $orderDir = $request->input('order.0.dir', 'desc');

            // Define column mapping for ordering
            $columns = [
                0 => 'kangisFileNo', // File Number (composite in UI; using kangisFileNo for ordering)
                1 => 'property_description',
                2 => 'location',
                3 => 'regNo',
                4 => 'land_use',
                5 => 'instrument_type',
                6 => 'Grantor',
                7 => 'Grantee',
                8 => 'created_at', // DATE CAPTURED
                9 => 'id' // Actions column
            ];

            // Build base query for CofO table
            $query = DB::connection('sqlsrv')->table($cofoTable);

            $hasGrantorColumn = Schema::connection('sqlsrv')->hasColumn($cofoTable, 'Grantor');
            $hasGranteeColumn = Schema::connection('sqlsrv')->hasColumn($cofoTable, 'Grantee');

            $selectColumns = [
                'id',
                'prop_id',
                'kangisFileNo',
                'mlsFNo',
                'NewKANGISFileno',
                'property_description',
                'location',
                'regNo',
                'cofo_type',
                'land_use',
                'instrument_type',
                'created_at',
            ];

            if ($hasGrantorColumn) {
                $selectColumns[] = 'Grantor';
            }

            if ($hasGranteeColumn) {
                $selectColumns[] = 'Grantee';
            }

            $query->select($selectColumns);

            // Apply search if provided
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue, $hasGrantorColumn, $hasGranteeColumn) {
                    $q->where('mlsFNo', 'like', "%{$searchValue}%")
                        ->orWhere('kangisFileNo', 'like', "%{$searchValue}%")
                        ->orWhere('NewKANGISFileno', 'like', "%{$searchValue}%")
                        ->orWhere('property_description', 'like', "%{$searchValue}%")
                        ->orWhere('location', 'like', "%{$searchValue}%")
                        ->orWhere('regNo', 'like', "%{$searchValue}%")
                        ->orWhere('transaction_type', 'like', "%{$searchValue}%")
                        ->orWhere('cofo_type', 'like', "%{$searchValue}%")
                        ->orWhere('land_use', 'like', "%{$searchValue}%");

                    if ($hasGrantorColumn) {
                        $q->orWhere('Grantor', 'like', "%{$searchValue}%");
                    }

                    if ($hasGranteeColumn) {
                        $q->orWhere('Grantee', 'like', "%{$searchValue}%");
                    }
                });
            }

            // Get total count before applying pagination
            $recordsTotal = DB::connection('sqlsrv')->table($cofoTable)->count();
            $recordsFiltered = $query->count();

            // Apply ordering
            $orderColumnName = $columns[$orderColumn] ?? 'id';

            if (!Schema::connection('sqlsrv')->hasColumn($cofoTable, $orderColumnName)) {
                $orderColumnName = 'id';
            }

            // Special handling for transaction_date ordering to prioritize newest records
            if ($orderColumnName === 'transaction_date') {
                // Order by transaction_date first, then by id for records with same or null transaction_date
                $query->orderByRaw("CASE WHEN transaction_date IS NULL THEN 1 ELSE 0 END")
                    ->orderBy('transaction_date', $orderDir)
                    ->orderBy('id', 'desc'); // Always show newest records first as secondary sort
            } else {
                $query->orderBy($orderColumnName, $orderDir);
            }

            // Apply pagination
            $records = $query->skip($start)->take($length)->get();

            // Cross-table timeline counts (pra + file_history_staging + CofO_staging + deed_registrations)
            $timelineCounts = $this->resolveTimelineCounts($records);

            // Format the data for DataTables
            $data = [];
            foreach ($records as $record) {
                $propId = $record->prop_id ?? null;
                $propIdKey = $propId !== null ? trim((string) $propId) : null;
                $timelineCount = $propIdKey
                    ? (int) ($timelineCounts[$propIdKey] ?? 1)
                    : (int) ($timelineCounts[(string) $record->id] ?? 1);

                $data[] = [
                    'id' => $record->id,
                    'prop_id' => $propId,
                    'kangisFileNo' => $record->kangisFileNo,
                    'mlsFNo' => $record->mlsFNo,
                    'NewKANGISFileno' => $record->NewKANGISFileno,
                    'property_description' => $record->property_description,
                    'location' => $record->location,
                    'regNo' => $record->regNo,
                    'cofo_type' => $record->cofo_type,
                    'land_use' => $record->land_use,
                    'instrument_type' => $record->instrument_type, // Changed from transaction_date to instrument_type
                    'Grantor' => $record->Grantor ?? ($record->grantor ?? null),
                    'Grantee' => $record->Grantee ?? ($record->grantee ?? null),
                    'created_at' => $record->created_at,
                    'timeline_count' => max(1, $timelineCount),
                ];
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getCofOData: ' . $e->getMessage());
            \Log::error('Error trace: ' . $e->getTraceAsString());

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error retrieving CofO data: ' . $e->getMessage()
            ]);
        }
    }

    public function showCofO($id)
    {
        try {
            $cofoTable = $this->resolveCofOTable();

            if (!$cofoTable) {
                return response()->json([
                    'success' => false,
                    'message' => 'CofO data source is unavailable.'
                ], 503);
            }

            $record = DB::connection('sqlsrv')->table($cofoTable)->where('id', $id)->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'CofO record not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $record,
                'meta' => [
                    'source_table' => $cofoTable
                ]
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error fetching CofO record', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while loading the CofO record.'
            ], 500);
        }
    }

    protected function resolveCofOTable(): ?string
    {
        if ($this->resolvedCofOTable !== null) {
            return $this->resolvedCofOTable;
        }

        $candidates = ['CofO_staging'];

        foreach ($candidates as $table) {
            try {
                if (Schema::connection('sqlsrv')->hasTable($table)) {
                    $this->resolvedCofOTable = $table;
                    return $this->resolvedCofOTable;
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to inspect CofO table candidate', [
                    'table' => $table,
                    'message' => $e->getMessage()
                ]);
            }
        }

        \Log::error('No CofO table detected on sqlsrv connection.');
        $this->resolvedCofOTable = null;

        return null;
    }

    /**
     * Fetch cross-table timeline counts keyed by prop_id for the provided records.
     * Queries file_history_staging, pra, CofO_staging, and deed_registrations.
     * Matches by prop_id OR file_number (same logic as the timeline modal).
     */
    protected function resolveTimelineCounts($records)
    {
        $counts = [];

        foreach ($records as $record) {
            $propId = trim((string) ($record->prop_id ?? ''));
            $key = $propId !== '' ? $propId : (string) ($record->id ?? '0');

            $primaryFileNumber = $record->mlsFNo ?? ($record->fileno ?? ($record->kangisFileNo ?? ($record->NewKANGISFileno ?? null)));

            if ($propId === '' && !$primaryFileNumber) {
                $counts[$key] = 1;
                continue;
            }

            $rawRecords = $this->timelineService->getRawRecords($primaryFileNumber, $propId);
            $weightedCount = $this->timelineService->getWeightedCount($rawRecords);

            $counts[$key] = max(1, $weightedCount);
        }

        return collect($counts);
    }

    /**
     * Count records in a single timeline table matching by prop_id OR file_number.
     */
    protected function countInTimelineTable(string $table, string $propId, array $fileNumbers, array $fileColumns, bool $excludeDeleted = false): int
    {
        try {
            $query = DB::connection('sqlsrv')->table($table);

            $query->where(function ($q) use ($propId, $fileNumbers, $fileColumns) {
                if ($propId !== '') {
                    $q->orWhereRaw("CAST(prop_id AS NVARCHAR(50)) = ?", [$propId]);
                }
                foreach ($fileNumbers as $fn) {
                    foreach ($fileColumns as $col) {
                        $q->orWhereRaw("UPPER({$col}) = ?", [$fn]);
                    }
                }
            });

            if ($excludeDeleted) {
                $query->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            }

            return $query->count();
        } catch (\Throwable $e) {
            \Log::warning("countInTimelineTable: error on {$table}", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    protected function resolveTableColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }


}
