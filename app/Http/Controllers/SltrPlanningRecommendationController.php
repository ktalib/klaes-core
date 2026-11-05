<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\LandUseType;
use App\Models\Lga;
use App\Models\Registry;
use App\Models\SltrJointSiteInspectionReport;
use App\Models\StreetName;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Physical Planning > SLTR Applications > Planning Recommendation, and the PP Director's
 * SLTR approval. A copy of the Conversion Planning Recommendation screens, working only on
 * sltr_joint_site_inspection_reports and SLTR- files from File Indexing.
 *
 * application_id on an SLTR report is the file's file_indexings.id; file_number is the
 * report's key (one report per SLTR file).
 */
class SltrPlanningRecommendationController extends Controller
{
    public const SOURCE = 'SLTR Applications';
    public const FILE_PREFIX = 'SLTR-';

    private const TABLE = 'sltr_joint_site_inspection_reports';

    public function index(Request $request)
    {
        $PageTitle = 'SLTR Applications - Planning Recommendation';
        $PageDescription = 'Manage SLTR applications for planning recommendation';
        $isPPDirector = strtolower((string) $request->query('role', '')) === 'pp_director';

        $statsBaseQuery = DB::connection('sqlsrv')->table(self::TABLE . ' as jsi');

        $stats = [
            'total_reports' => (clone $statsBaseQuery)->count(),
            'obtainable_reports' => (clone $statsBaseQuery)->where('jsi.compliance_status', 'obtainable')->count(),
            'not_obtainable_reports' => (clone $statsBaseQuery)->where('jsi.compliance_status', 'not_obtainable')->count(),
        ];

        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $registries = Registry::where('is_active', 1)->orderBy('name')->get();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $landUseTypes = Cache::remember('land_use_types_all', 3600, function () {
            return LandUseType::orderBy('name')->get(['id', 'name']);
        });
        $inspectorColumns = ['id', 'name', 'rank'];
        if (Schema::connection('sqlsrv')->hasColumn('pp_inspectors', 'in_sectional_titling')) {
            $inspectorColumns[] = 'in_physical_planning';
            $inspectorColumns[] = 'in_sectional_titling';
        }
        $inspectors = Cache::remember('pp_inspectors_all', 1800, function () use ($inspectorColumns) {
            return DB::connection('sqlsrv')
                ->table('pp_inspectors')
                ->select($inspectorColumns)
                ->orderBy('name')
                ->get();
        });
        $detailedLandUseHasLandUseId = Schema::connection('sqlsrv')->hasColumn('detailed_land_use', 'land_use_id');

        $jsiDetailedLandUsesQuery = DB::connection('sqlsrv')
            ->table('detailed_land_use')
            ->where('is_active', 1)
            ->orderByRaw("CASE name WHEN 'Residential' THEN 1 WHEN 'Commercial' THEN 2 WHEN 'Agricultural' THEN 3 WHEN 'Industrial' THEN 4 ELSE 99 END")
            ->orderBy('name');

        if ($detailedLandUseHasLandUseId) {
            $jsiDetailedLandUsesQuery->select('id', 'land_use_id', 'name');
        } else {
            $jsiDetailedLandUsesQuery->select('id', 'name');
        }

        $jsiDetailedLandUses = $jsiDetailedLandUsesQuery->get();

        $jsiPurposesByLandUse = DB::connection('sqlsrv')
            ->table('jsi_lut_purposes')
            ->select('id', 'land_use_id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy('land_use_id');
        $jsiRoadCategories = DB::connection('sqlsrv')
            ->table('jsi_lut_road_categories')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
        $jsiReservations = DB::connection('sqlsrv')
            ->table('jsi_lut_reservations')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
        $user = Auth::user();

        return view('physical_planning.sltr.planning_recommendation', compact(
            'PageTitle',
            'PageDescription',
            'isPPDirector',
            'districts',
            'lgas',
            'registries',
            'landUseTypes',
            'inspectors',
            'jsiDetailedLandUses',
            'jsiPurposesByLandUse',
            'jsiRoadCategories',
            'jsiReservations',
            'stats',
            'user',
            'states',
            'streetNames'
        ));
    }

    /**
     * PP Director approval workbench for an SLTR JSI report.
     */
    public function approvalPage(Request $request, $applicationId)
    {
        $PageTitle = 'SLTR Planning Recommendation Approval';
        $PageDescription = 'Review and approve/decline Joint Site Inspection report.';

        $report = DB::connection('sqlsrv')
            ->table(self::TABLE . ' as jsi')
            ->select([
                'jsi.id as jsi_report_id',
                'jsi.application_id',
                'jsi.file_number',
                'jsi.applicant_name',
                'jsi.applicant_address',
                'jsi.location',
                'jsi.plot_number',
                'jsi.lkn_number',
                'jsi.compliance_status',
                'jsi.inspection_date',
                'jsi.inspection_officer',
                'jsi.purpose',
                'jsi.source',
                'jsi.applied_land_use',
                'jsi.prevailing_land_use',
                'jsi.existing_site_area',
                'jsi.recommended_site_area',
                'jsi.is_approved',
                'jsi.approved_by',
                'jsi.approved_at',
            ])
            ->where('jsi.application_id', $applicationId)
            ->orderByDesc('jsi.id')
            ->first();

        abort_if(!$report, 404, 'No SLTR JSI report found for this file.');

        return view('physical_planning.sltr.planning_recommendation_approval', [
            'PageTitle' => $PageTitle,
            'PageDescription' => $PageDescription,
            'report' => $report,
            'isPPDirector' => strtolower((string) $request->query('role', '')) === 'pp_director',
        ]);
    }

    /**
     * SLTR JSI report rows for DataTables.
     */
    public function getData(Request $request)
    {
        $query = DB::connection('sqlsrv')
            ->table(self::TABLE . ' as jsi')
            ->leftJoin('file_indexings as fi', 'fi.id', '=', DB::raw('TRY_CAST(jsi.application_id AS BIGINT)'))
            ->select([
                'jsi.id as jsi_report_id',
                'jsi.application_id',
                'jsi.file_number',
                'jsi.applicant_name',
                'jsi.applicant_address',
                'jsi.applied_land_use',
                'jsi.prevailing_land_use',
                'jsi.plot_number',
                'jsi.location',
                'jsi.lkn_number',
                'jsi.inspection_date',
                'jsi.inspection_officer',
                'jsi.existing_site_area',
                'jsi.recommended_site_area',
                'jsi.compliance_status',
                'jsi.purpose',
                'jsi.source',
                'jsi.conformity',
                'jsi.accessibility',
                'jsi.is_generated as jsi_is_generated',
                'jsi.is_submitted as jsi_is_submitted',
                'jsi.is_approved as jsi_is_approved',
                'jsi.generated_at as jsi_generated_at',
                'jsi.submitted_at as jsi_submitted_at',
                'jsi.approved_at as jsi_approved_at',
                'jsi.updated_at as jsi_updated_at',
                'jsi.created_at',
                'fi.tp_no',
            ]);

        if ($request->has('search') && !empty($request->search['value'])) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('jsi.file_number', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.applicant_name', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.applied_land_use', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.prevailing_land_use', 'LIKE', "%{$search}%")
                    ->orWhere('fi.tp_no', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.plot_number', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.location', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.lkn_number', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.existing_site_area', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.recommended_site_area', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.compliance_status', 'LIKE', "%{$search}%")
                    ->orWhere('jsi.inspection_officer', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('land_use_type') && !empty($request->land_use_type)) {
            $landUseType = $request->land_use_type;
            $query->where(function ($q) use ($landUseType) {
                $q->where('jsi.applied_land_use', $landUseType)
                    ->orWhere('jsi.prevailing_land_use', $landUseType);
            });
        }

        if ($request->has('compliance_status') && !empty($request->compliance_status)) {
            $query->where('jsi.compliance_status', $request->compliance_status);
        }

        if ($request->has('district') && !empty($request->district)) {
            $query->where('jsi.location', 'LIKE', "%{$request->district}%");
        }
        if ($request->has('lga') && !empty($request->lga)) {
            $query->where('jsi.location', 'LIKE', "%{$request->lga}%");
        }

        $totalRecords = DB::connection('sqlsrv')->table(self::TABLE)->count();
        $filteredRecords = $query->count();

        $summaryBaseQuery = clone $query;
        $summary = [
            'total_reports' => $filteredRecords,
            'obtainable_reports' => (clone $summaryBaseQuery)->where('jsi.compliance_status', 'obtainable')->count(),
            'not_obtainable_reports' => (clone $summaryBaseQuery)->where('jsi.compliance_status', 'not_obtainable')->count(),
        ];

        // Column indexes match the DataTable; 0 is S/N (not sortable).
        $orderColumnIndex = (int) $request->input('order.0.column', 13);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableColumns = [
            1 => 'jsi.file_number',
            2 => 'jsi.applicant_name',
            3 => 'jsi.applied_land_use',
            4 => 'fi.tp_no',
            5 => 'jsi.lkn_number',
            6 => 'jsi.plot_number',
            7 => 'jsi.location',
            8 => 'jsi.inspection_date',
            9 => 'jsi.recommended_site_area',
            10 => 'jsi.existing_site_area',
            11 => 'jsi.compliance_status',
            12 => 'jsi.is_approved',
            13 => 'jsi.created_at',
        ];

        $query->orderBy($sortableColumns[$orderColumnIndex] ?? 'jsi.created_at', $orderDir)
            ->orderBy('jsi.id', $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $files = $query->skip($start)->take($length)->get();

        $data = $files->map(function ($file) {
            $isApproved = (bool) ($file->jsi_is_approved ?? false);

            return [
                'id'                    => $file->jsi_report_id,
                'jsi_report_id'         => $file->jsi_report_id,
                'application_id'        => $file->application_id,
                'file_number'           => $file->file_number ?? 'N/A',
                'applicant_name'        => $file->applicant_name ?? 'N/A',
                'applied_land_use'      => $file->applied_land_use ?? 'N/A',
                'prevailing_land_use'   => $file->prevailing_land_use ?? 'N/A',
                'tp_no'                 => $file->tp_no ?? 'N/A',
                'plot_number'           => $file->plot_number ?? 'N/A',
                'location'              => $file->location ?? 'N/A',
                'lkn_number'            => $file->lkn_number ?? 'N/A',
                'inspection_date'       => $file->inspection_date ?? null,
                'inspection_officer'    => $file->inspection_officer ?? 'N/A',
                'existing_site_area'    => $file->existing_site_area,
                'recommended_site_area' => $file->recommended_site_area,
                'compliance_status'     => $file->compliance_status ?? 'N/A',
                'purpose'               => $file->purpose ?? 'N/A',
                'source'                => $file->source ?? 'N/A',
                'jsi_status_code'       => $isApproved ? 'approved' : 'pending',
                'jsi_status_label'      => $isApproved ? 'Approved' : 'Pending',
                'jsi_is_generated'      => (bool) ($file->jsi_is_generated ?? false),
                'jsi_is_submitted'      => (bool) ($file->jsi_is_submitted ?? false),
                'jsi_is_approved'       => $isApproved,
                'jsi_generated_at'      => $file->jsi_generated_at,
                'jsi_submitted_at'      => $file->jsi_submitted_at,
                'jsi_approved_at'       => $file->jsi_approved_at,
                'jsi_updated_at'        => $file->jsi_updated_at,
                'created_at'            => $file->created_at ?? null,
            ];
        });

        return response()->json([
            'draw'            => intval($request->input('draw')),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'summary'         => $summary,
            'data'            => $data,
        ]);
    }

    /**
     * Select2 options for the SLTR JSI File Number dropdown: SLTR- files from File
     * Indexing only, searchable. application_id is the file's file_indexings.id.
     */
    public function jsiFileOptions(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', 'LIKE', self::FILE_PREFIX . '%')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });

        if ($term !== '') {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], strtoupper($term)) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('UPPER(file_number) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(file_title) LIKE ?', [$like]);
            });
        }

        $rows = $query
            ->orderBy('file_number')
            ->orderBy('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'location', 'tp_no', 'lpkn_no']);

        $more = $rows->count() > $perPage;

        $results = $rows->take($perPage)->unique('file_number')->map(fn ($r) => [
            'id' => $r->file_number,
            'text' => $r->file_number,
            'file_title' => $r->file_title,
            'plot_number' => $r->plot_number,
            'district' => $r->district,
            'lga' => $r->lga,
            'location' => $r->location,
            'lpkn_no' => $r->lpkn_no ?: $r->tp_no,
            'application_id' => $r->id,
        ])->values();

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $more],
        ]);
    }

    // ---- JSI report: save / generate / submit / show / approve / status ---------------

    public function storeJsi(Request $request)
    {
        switch ($request->input('action', 'save')) {
            case 'generate':
                return $this->generateJsi($request);
            case 'submit':
                return $this->submitJsi($request);
        }

        $validated = $request->validate([
            'application_id' => 'nullable|integer',
            'record_id' => 'nullable|integer',
            'file_number' => 'required|string|max:100',
            'inspection_date' => 'required|date',
            'lkn_number' => 'nullable|string|max:255',
            'applicant_name' => 'nullable|string|max:255',
            'applicant_address' => 'nullable|string|max:500',
            'applicant_address_components' => 'nullable|array',
            'applicant_address_components.plot_number' => 'nullable|string|max:255',
            'applicant_address_components.street' => 'nullable|string|max:255',
            'applicant_address_components.street_other' => 'nullable|string|max:255',
            'applicant_address_components.district' => 'nullable|string|max:255',
            'applicant_address_components.district_other' => 'nullable|string|max:255',
            'applicant_address_components.lga' => 'nullable|string|max:255',
            'applicant_address_components.state' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'location_components' => 'nullable|array',
            'location_components.plot_number' => 'nullable|string|max:255',
            'location_components.street' => 'nullable|string|max:255',
            'location_components.street_other' => 'nullable|string|max:255',
            'location_components.district' => 'nullable|string|max:255',
            'location_components.district_other' => 'nullable|string|max:255',
            'location_components.lga' => 'nullable|string|max:255',
            'location_components.state' => 'nullable|string|max:255',
            'plot_number' => 'nullable|string|max:255',
            'scheme_number' => 'nullable|string|max:255',
            'purpose' => 'required|string|max:50',
            'private_layout_number' => 'required_if:purpose,Private Layout|nullable|string|max:100',
            'conformity' => 'required|boolean',
            'accessibility' => 'required|boolean',
            'existing_road_reservation' => 'nullable|string|max:255',
            'recommended_road_reservation' => 'nullable|string|max:255',
            'observed_road_category' => 'nullable|string|max:255',
            'recommended_comment_status' => 'nullable|string|max:100',
            'how_many_meters' => 'nullable|string|max:100',
            'road_category' => 'nullable|string|max:255',
            'adequate_size_requirement' => 'required|boolean',
            'land_traversing_utility' => 'required|boolean',
            'existing_site_length' => 'nullable|string',
            'existing_site_width' => 'nullable|string',
            'existing_site_area' => 'nullable|numeric|min:0',
            'recommended_site_length' => 'nullable|string',
            'recommended_site_width' => 'nullable|string',
            'recommended_site_area' => 'nullable|numeric|min:0',
            'inspection_officer_id' => 'nullable|integer',
            'boundary_description' => 'nullable|string',
            'boundary_segments' => 'nullable|array',
            'boundary_segments.north' => 'nullable|string|max:1000',
            'boundary_segments.east' => 'nullable|string|max:1000',
            'boundary_segments.south' => 'nullable|string|max:1000',
            'boundary_segments.west' => 'nullable|string|max:1000',
            'road_reservation' => 'nullable|string|max:255',
            'prevailing_land_use' => 'nullable|string|max:255',
            'applied_land_use' => 'nullable|string|max:255',
            'compliance_status' => 'nullable|string|in:obtainable,not_obtainable',
            'additional_observations' => 'nullable|string',
            'inspection_officer' => 'nullable|string|max:255',
            'existing_site_measurement_summary' => 'nullable|string',
            'existing_site_measurement_entries' => 'nullable|array',
            'existing_site_measurement_entries.*.description' => 'nullable|string|max:255',
            'existing_site_measurement_entries.*.measurement_dimension' => 'nullable|string',
            'existing_site_measurement_entries.*.dimension' => 'nullable|string',
            'existing_site_measurement_entries.*.count' => 'nullable|numeric|min:0',
            'existing_site_measurement_entries.*.sn' => 'nullable|integer|min:1',
        ]);

        $fileNumber = strtoupper(trim((string) $validated['file_number']));
        if (strpos($fileNumber, self::FILE_PREFIX) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only SLTR (SLTR-) file numbers can be used for an SLTR Joint Site Inspection Report.',
            ], 422);
        }

        // The file must be in File Indexing; its row id is the report's application_id.
        $indexedFile = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('id')
            ->first(['id']);

        if (!$indexedFile) {
            return response()->json([
                'success' => false,
                'message' => "{$fileNumber} is not in File Indexing.",
            ], 422);
        }

        $recordId = $validated['record_id'] ?? null;
        if ($recordId) {
            $report = SltrJointSiteInspectionReport::find($recordId);
            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joint Site Inspection Report not found for update',
                ], 404);
            }
            if (strtoupper((string) $report->file_number) !== $fileNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record does not belong to the selected file',
                ], 403);
            }
        } else {
            $report = SltrJointSiteInspectionReport::firstOrNew(['file_number' => $fileNumber]);
        }

        $report->application_id = (string) $indexedFile->id;
        $report->sub_application_id = null;
        $report->file_number = $fileNumber;
        $report->source = self::SOURCE;

        $report->inspection_date = Carbon::parse($validated['inspection_date'])->format('Y-m-d');
        $report->lkn_number = $validated['lkn_number'] ?? null;
        $report->applicant_name = $validated['applicant_name'] ?? null;

        $locationComponents = $this->normalizeAddressComponents($request->input('location_components'));
        // SLTR properties are in Kano: the form shows the State fixed, and the save enforces it.
        $locationComponents['state'] = 'Kano';
        $applicantAddressComponents = $this->normalizeAddressComponents($request->input('applicant_address_components'));

        $report->location_components = !empty($locationComponents) ? $locationComponents : null;
        $report->applicant_address_components = !empty($applicantAddressComponents) ? $applicantAddressComponents : null;
        // Property location is district + LGA (+ state); the plot number has its own field.
        $report->location = $this->compileAddressString($locationComponents, $validated['location'] ?? null, false);
        $report->applicant_address = $this->compileAddressString($applicantAddressComponents, $validated['applicant_address'] ?? null);
        $report->plot_number = $validated['plot_number'] ?? ($locationComponents['plot_number'] ?? null);
        $report->scheme_number = $validated['scheme_number'] ?? null;
        $report->available_on_ground = $request->boolean('available_on_ground');

        $report->purpose = $validated['purpose'] ?? null;
        $report->private_layout_number = $validated['private_layout_number'] ?? null;

        $toNullableBool = function ($key) use ($request) {
            if (!$request->has($key)) {
                return null;
            }
            return $request->boolean($key);
        };

        $report->conformity = $toNullableBool('conformity');
        $report->accessibility = $toNullableBool('accessibility');
        $report->adequate_size_requirement = $toNullableBool('adequate_size_requirement');
        $report->land_traversing_utility = $toNullableBool('land_traversing_utility');

        $report->existing_road_reservation = $validated['existing_road_reservation'] ?? null;
        $report->recommended_road_reservation = $validated['recommended_road_reservation'] ?? null;
        $report->observed_road_category = $validated['observed_road_category']
            ?? ($validated['road_category'] ?? null);
        $report->recommended_comment_status = $validated['recommended_comment_status'] ?? null;
        $report->how_many_meters = $validated['how_many_meters'] ?? null;

        if ($report->observed_road_category && !$report->existing_road_reservation) {
            $report->existing_road_reservation = $report->observed_road_category;
        }

        $preparedBoundarySegments = SltrJointSiteInspectionReport::prepareBoundarySegments(
            $request->input('boundary_segments', null),
            $validated['boundary_description'] ?? null
        );
        $report->boundary_description = SltrJointSiteInspectionReport::compileBoundaryDescription(
            $preparedBoundarySegments,
            $validated['boundary_description'] ?? null
        );
        $report->road_reservation = $validated['road_reservation'] ?? null;
        $report->prevailing_land_use = $validated['prevailing_land_use'] ?? null;
        $report->applied_land_use = $validated['applied_land_use'] ?? null;
        $report->shared_utilities = [];
        $report->compliance_status = $validated['compliance_status'] ?? null;
        $report->has_additional_observations = $request->boolean('has_additional_observations');
        $report->additional_observations = $validated['additional_observations'] ?? null;

        $inspectionOfficerId = $validated['inspection_officer_id'] ?? null;
        $inspectionOfficerLabel = $validated['inspection_officer'] ?? null;
        if ($inspectionOfficerId && $inspectionOfficerLabel) {
            $report->inspection_officer = trim($inspectionOfficerLabel) . ' [ID: ' . $inspectionOfficerId . ']';
        } elseif ($inspectionOfficerLabel) {
            $report->inspection_officer = $inspectionOfficerLabel;
        } else {
            $report->inspection_officer = null;
        }
        $report->inspection_officer_id = $inspectionOfficerId;

        $rawMeasurementEntries = $request->input('existing_site_measurement_entries', []);
        if (!is_array($rawMeasurementEntries)) {
            $rawMeasurementEntries = [];
        }

        $processedMeasurementEntries = collect($rawMeasurementEntries)->map(function ($entry, $index) {
            if (!is_array($entry)) {
                return null;
            }

            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
            $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
            $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
            $countInput = isset($entry['count']) ? trim((string) $entry['count']) : '';
            $count = $countInput === '' ? '1' : $countInput;

            if ($description === '' && $dimension === '' && $measurementDimension === '') {
                return null;
            }

            return [
                'sn' => isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1),
                'description' => $description === '' ? null : $description,
                'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                'dimension' => $dimension === '' ? null : $dimension,
                'count' => $count,
            ];
        })->filter()->values();

        $report->existing_site_measurement_entries = $processedMeasurementEntries->all();

        if ($processedMeasurementEntries->isEmpty()) {
            $report->existing_site_measurement_summary = $validated['existing_site_measurement_summary']
                ?? 'No recorded dimensions were submitted for this inspection.';
        } else {
            $report->existing_site_measurement_summary = $validated['existing_site_measurement_summary'] ?? null;
        }

        $report->existing_site_length = $validated['existing_site_length'] ?? null;
        $report->existing_site_width = $validated['existing_site_width'] ?? null;
        $report->existing_site_area = $validated['existing_site_area'] ?? null;
        $report->recommended_site_length = $validated['recommended_site_length'] ?? null;
        $report->recommended_site_width = $validated['recommended_site_width'] ?? null;
        $report->recommended_site_area = $validated['recommended_site_area'] ?? null;

        if (!$report->exists) {
            $report->is_generated = false;
            $report->is_submitted = false;
        }

        if (Auth::check()) {
            if (!$report->exists || empty($report->created_by)) {
                $report->created_by = Auth::id();
            }
            $report->updated_by = Auth::id();
        }

        $report->save();

        return response()->json([
            'success' => true,
            'message' => $report->wasRecentlyCreated
                ? 'Joint site inspection report saved successfully.'
                : 'Joint site inspection report updated successfully.',
            'view_url' => route('physical-planning.sltr.jsi.show', $report->application_id),
            'report_id' => $report->id,
            'record_id' => $report->id,
            'application_id' => $report->application_id,
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'created' => $report->wasRecentlyCreated,
        ]);
    }

    public function generateJsi(Request $request)
    {
        $report = $this->findReport($request);
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found. Please save the report first.',
            ], 404);
        }

        if ($report->is_submitted) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate - report has already been submitted.',
            ], 422);
        }

        if ($report->is_generated) {
            return response()->json([
                'success' => false,
                'message' => 'Report has already been generated.',
            ], 422);
        }

        $report->is_generated = true;
        $report->generated_at = now();
        $report->generated_by = Auth::id();
        $report->updated_by = Auth::id();
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report generated successfully. You can now submit it.',
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'report_url' => route('physical-planning.sltr.jsi.show', $report->application_id),
        ]);
    }

    public function submitJsi(Request $request)
    {
        $report = $this->findReport($request);
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found. Please save the report first.',
            ], 404);
        }

        if (!$report->is_generated) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit - report must be generated first.',
            ], 422);
        }

        if ($report->is_submitted) {
            return response()->json([
                'success' => false,
                'message' => 'Report has already been submitted.',
            ], 422);
        }

        $report->is_submitted = true;
        $report->submitted_at = now();
        $report->submitted_by = Auth::id();
        $report->updated_by = Auth::id();
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report submitted successfully.',
            'is_generated' => (bool) $report->is_generated,
            'is_submitted' => (bool) $report->is_submitted,
            'reload' => true,
        ]);
    }

    /**
     * The JSI report (JSON for the modal, or the printable sheet). {application} is the
     * file's file_indexings.id; a report id is accepted as a fallback.
     */
    public function showJsi(Request $request, $applicationId)
    {
        $report = SltrJointSiteInspectionReport::where('application_id', (string) $applicationId)
            ->orderByDesc('id')
            ->first()
            ?? SltrJointSiteInspectionReport::find($applicationId);

        $wantsJson = $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if (!$report) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => 'Joint site inspection report not found'], 404);
            }
            abort(404, 'Joint site inspection report not found');
        }

        // SLTR files have no ST application: the sheet reads its header from the report.
        $application = (object) [
            'id' => $report->application_id,
            'fileno' => $report->file_number,
            'lkn_number' => $report->lkn_number,
            'property_street_name' => null,
            'property_lga' => null,
            'property_location' => $report->location,
            'property_plot_no' => $report->plot_number,
            'scheme_no' => $report->scheme_number,
            'applicant_type' => null,
            'applicant_title' => null,
            'first_name' => null,
            'surname' => null,
            'rc_number' => null,
            'corporate_name' => $report->applicant_name,
            'multiple_owners_names' => null,
            'applicant_name' => $report->applicant_name,
            'unit_number' => null,
            'land_use' => $report->applied_land_use,
            'shared_areas' => null,
        ];

        $dimensions = collect($report->existing_site_measurement_entries ?? [])
            ->filter(fn ($entry) => is_array($entry))
            ->map(function ($entry, $index) {
                $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
                $dimension = isset($entry['dimension']) ? trim((string) $entry['dimension']) : '';
                $measurementDimension = isset($entry['measurement_dimension']) ? trim((string) $entry['measurement_dimension']) : '';
                $countInput = isset($entry['count']) ? trim((string) $entry['count']) : '';

                if ($description === '' && $dimension === '' && $measurementDimension === '' && $countInput === '') {
                    return null;
                }

                return (object) [
                    'sn' => isset($entry['sn']) && is_numeric($entry['sn']) ? (int) $entry['sn'] : ($index + 1),
                    'description' => $description === '' ? null : $description,
                    'dimension' => $dimension === '' ? null : $dimension,
                    'measurement_dimension' => $measurementDimension === '' ? null : $measurementDimension,
                    'count' => $countInput === '' ? '1' : $countInput,
                ];
            })
            ->filter()
            ->sortBy('sn')
            ->values();

        $payload = [
            'application' => $application,
            'report' => $report,
            'dimensions' => $dimensions,
            'utilities' => collect(),
            'unitMeasurements' => collect(),
            'sharedAreasList' => [],
        ];

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'data' => $report,
                'dimensions' => $dimensions,
                'utilities' => [],
                'unitMeasurements' => [],
                'sharedAreasList' => [],
            ]);
        }

        return view('physical_planning.sltr.print.jsi_report', $payload + ['printMode' => $request->boolean('print')]);
    }

    public function approveJsi(Request $request)
    {
        $report = $this->findReport($request);
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found',
            ], 404);
        }

        if ($report->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report is already approved',
            ], 400);
        }

        $updateData = [
            'is_approved' => 1,
            'approved_by' => Auth::user()->name ?? 'System',
            'approved_at' => now()->format('Y-m-d H:i:s'),
        ];

        DB::connection('sqlsrv')->table(self::TABLE)->where('id', $report->id)->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report approved successfully',
            'data' => [
                'is_approved' => true,
                'approved_by' => $updateData['approved_by'],
                'approved_at' => $updateData['approved_at'],
            ],
        ]);
    }

    public function updateJsiStatus(Request $request)
    {
        $report = $this->findReport($request);
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Joint Site Inspection Report not found',
            ], 404);
        }

        $updateData = [];

        if ($request->input('is_generated') !== null) {
            $updateData['is_generated'] = (bool) $request->input('is_generated');
            $updateData['generated_at'] = $request->input('generated_at')
                ? date('Y-m-d H:i:s', strtotime($request->input('generated_at')))
                : now()->format('Y-m-d H:i:s');
            $updateData['generated_by'] = Auth::user()->name ?? 'System';
        }

        if ($request->input('is_submitted') !== null) {
            $updateData['is_submitted'] = (bool) $request->input('is_submitted');
            $updateData['submitted_at'] = $request->input('submitted_at')
                ? date('Y-m-d H:i:s', strtotime($request->input('submitted_at')))
                : now()->format('Y-m-d H:i:s');
            $updateData['submitted_by'] = Auth::user()->name ?? 'System';
        }

        if (empty($updateData)) {
            return response()->json([
                'success' => false,
                'message' => 'No status updates provided',
            ], 400);
        }

        DB::connection('sqlsrv')->table(self::TABLE)->where('id', $report->id)->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Joint Site Inspection Report status updated successfully',
            'data' => $updateData,
        ]);
    }

    /**
     * Print final recommendation for an SLTR file.
     */
    public function printRecommendation($applicationId)
    {
        $report = DB::connection('sqlsrv')
            ->table(self::TABLE)
            ->where('application_id', $applicationId)
            ->orderByDesc('id')
            ->first();

        if (!$report) {
            abort(404, 'Recommendation data not found for this SLTR file.');
        }

        $rawEntries = $report->existing_site_measurement_entries ?? [];
        if (is_string($rawEntries) && trim($rawEntries) !== '') {
            $decoded = json_decode($rawEntries, true);
            $rawEntries = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($rawEntries)) {
            $rawEntries = [];
        }

        // The form stores its purpose/reservation meta under the conversion sentinel names.
        $metaEntries = collect($rawEntries)
            ->filter(fn ($entry) => is_array($entry) && !empty($entry['description']))
            ->keyBy(fn ($entry) => strtolower(trim((string) ($entry['description'] ?? ''))));

        $purposeMeta = $metaEntries->get('__conversion_purpose_lut__', []);
        $reservationMeta = $metaEntries->get('__conversion_reservation__', []);

        $purposeMetaDetail = [];
        if (!empty($purposeMeta['dimension'])) {
            $parsed = json_decode((string) $purposeMeta['dimension'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $purposeMetaDetail = $parsed;
            }
        }

        $reservationMetaDetail = [];
        if (!empty($reservationMeta['dimension'])) {
            $parsed = json_decode((string) $reservationMeta['dimension'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $reservationMetaDetail = $parsed;
            }
        }

        $detailedLandUseName = null;
        $detailedLandUseId = $purposeMetaDetail['detailed_land_use_id'] ?? null;
        if (!empty($detailedLandUseId)) {
            $detailedLandUseName = DB::connection('sqlsrv')
                ->table('detailed_land_use')
                ->where('id', $detailedLandUseId)
                ->value('name');
        }

        $existingMeasurement = trim(implode(' x ', array_filter([
            $report->existing_site_length ?? null,
            $report->existing_site_width ?? null,
        ], fn ($value) => $value !== null && $value !== '')));
        if (($report->existing_site_area ?? null) !== null && $report->existing_site_area !== '') {
            $existingMeasurement .= ($existingMeasurement !== '' ? ' | ' : '') . 'Area: ' . number_format((float) $report->existing_site_area, 2) . ' m2';
        }

        $recommendedMeasurement = trim(implode(' x ', array_filter([
            $report->recommended_site_length ?? null,
            $report->recommended_site_width ?? null,
        ], fn ($value) => $value !== null && $value !== '')));
        if (($report->recommended_site_area ?? null) !== null && $report->recommended_site_area !== '') {
            $recommendedMeasurement .= ($recommendedMeasurement !== '' ? ' | ' : '') . 'Area: ' . number_format((float) $report->recommended_site_area, 2) . ' m2';
        }

        $resolvedPurpose = $report->purpose ?? ($purposeMeta['measurement_dimension'] ?? null);

        $resolvedExistingRoadReservation = $report->existing_road_reservation
            ?? ($report->observed_road_category ?? null)
            ?? ($reservationMetaDetail['observed_road_category'] ?? null)
            ?? ($reservationMetaDetail['road_category'] ?? null);

        $resolvedRecommendedRoadReservation = $report->recommended_road_reservation
            ?? (($reservationMetaDetail['right_of_way'] ?? null) === 'OTHER'
                ? ($reservationMetaDetail['right_of_way_other'] ?? null)
                : ($reservationMetaDetail['right_of_way'] ?? null))
            ?? $report->road_reservation;

        $resolvedInspectionOfficer = preg_replace('/\s*\[ID:\s*\d+\]\s*/', '', (string) ($report->inspection_officer ?? ''));

        return view('physical_planning.sltr.print.recommendation', [
            'report' => $report,
            'applicationDate' => $report->inspection_date ? Carbon::parse($report->inspection_date) : null,
            'applicantName' => $report->applicant_name ?? '',
            'applicationNumber' => $report->file_number ?? ($report->application_id ?? $applicationId),
            'location' => $report->location ?? '',
            'purpose' => $resolvedPurpose,
            'siteMeasurements' => $existingMeasurement !== '' ? $existingMeasurement : 'N/A',
            'recommendedSiteMeasurements' => $recommendedMeasurement !== '' ? $recommendedMeasurement : 'N/A',
            'existingRoadReservation' => $resolvedExistingRoadReservation ?? 'N/A',
            'surroundingLandUse' => ($report->prevailing_land_use ?? $detailedLandUseName) ?? 'N/A',
            'recommendedRoadReservation' => $resolvedRecommendedRoadReservation ?? 'N/A',
            'recommendedLandUse' => ($report->applied_land_use ?? $detailedLandUseName) ?? 'N/A',
            'inspectionOfficer' => $resolvedInspectionOfficer,
        ]);
    }

    // ---- helpers --------------------------------------------------------------------

    /** The SLTR report named by record_id, or else by application_id (file_indexings.id). */
    private function findReport(Request $request): ?SltrJointSiteInspectionReport
    {
        $recordId = (int) $request->input('record_id', 0);
        if ($recordId > 0) {
            return SltrJointSiteInspectionReport::find($recordId);
        }

        $applicationId = trim((string) $request->input('application_id', ''));
        if ($applicationId === '') {
            return null;
        }

        return SltrJointSiteInspectionReport::where('application_id', $applicationId)
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeAddressComponents($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach (['plot_number', 'street', 'street_other', 'district', 'district_other', 'lga', 'state'] as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $raw = is_string($value[$key]) ? trim($value[$key]) : $value[$key];
            if ($raw === '' || $raw === null) {
                continue;
            }

            $normalized[$key] = $raw;
        }

        return $normalized;
    }

    /**
     * Compile an address line from its components. $withPlotAndStreet = false gives a
     * property Location: district + LGA (+ state) only.
     */
    private function compileAddressString(array $components, ?string $fallback = null, bool $withPlotAndStreet = true): ?string
    {
        $street = $components['street'] ?? null;
        if ($street === 'Other') {
            $street = $components['street_other'] ?? null;
        }

        $district = $components['district'] ?? null;
        if ($district === 'Other') {
            $district = $components['district_other'] ?? null;
        }

        $parts = [];
        if ($withPlotAndStreet && !empty($components['plot_number'])) {
            $parts[] = $components['plot_number'];
        }
        if ($withPlotAndStreet && !empty($street)) {
            $parts[] = $street;
        }
        if (!empty($district)) {
            $parts[] = $district;
        }
        if (!empty($components['lga'])) {
            $parts[] = $components['lga'] . ' LGA';
        }
        if (!empty($components['state'])) {
            $parts[] = $components['state'] . ' State';
        }

        $compiled = implode(', ', array_filter($parts, fn ($value) => $value !== null && $value !== ''));
        if ($compiled !== '') {
            return $compiled;
        }

        $normalizedFallback = is_string($fallback) ? trim($fallback) : $fallback;

        return $normalizedFallback === '' ? null : $normalizedFallback;
    }
}
