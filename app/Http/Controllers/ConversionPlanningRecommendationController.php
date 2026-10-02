<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Lga;
use App\Models\Registry;
use App\Models\LandUseType;
use App\Models\StreetName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ConversionPlanningRecommendationController extends Controller
{
    /**
     * Display the Conversion Applications Planning Recommendation index.
     */
    public function index(Request $request)
    {
        $PageTitle = 'Conversion Applications - Planning Recommendation';
        $PageDescription = 'Manage conversion applications for planning recommendation';
        $isPPDirector = strtolower((string) $request->query('role', '')) === 'pp_director';

        $statsBaseQuery = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports as jsi')
            ->whereNull('jsi.sub_application_id')
            ->where(function ($q) {
                $q->where('jsi.source', 'Conversion Applications')
                    ->orWhere('jsi.file_number', 'LIKE', 'CON-%')
                    ->orWhere('jsi.file_number', 'LIKE', 'RES-%');
            });

        $stats = [
            'total_reports' => (clone $statsBaseQuery)->count(),
            'submitted_reports' => (clone $statsBaseQuery)->where('jsi.is_submitted', 1)->count(),
            'generated_reports' => (clone $statsBaseQuery)
                ->where('jsi.is_generated', 1)
                ->where(function ($q) {
                    $q->whereNull('jsi.is_submitted')->orWhere('jsi.is_submitted', 0);
                })
                ->count(),
            'draft_reports' => (clone $statsBaseQuery)
                ->where(function ($q) {
                    $q->whereNull('jsi.is_generated')->orWhere('jsi.is_generated', 0);
                })
                ->where(function ($q) {
                    $q->whereNull('jsi.is_submitted')->orWhere('jsi.is_submitted', 0);
                })
                ->count(),
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

        return view('physical_planning.conversion.planning_recommendation', compact(
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
     * PP Director approval workbench for a conversion application.
     */
    public function approvalPage(Request $request, $applicationId)
    {
        $PageTitle = 'Planning Recommendation Approval';
        $PageDescription = 'Review and approve/decline Joint Site Inspection report.';

        $report = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports as jsi')
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
            ->whereNull('jsi.sub_application_id')
            ->where('jsi.application_id', $applicationId)
            ->where(function ($q) {
                $q->where('jsi.source', 'Conversion Applications')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-%');
            })
            ->orderByDesc('jsi.id')
            ->first();

        abort_if(!$report, 404, 'No conversion JSI report found for this application.');

        return view('physical_planning.conversion.planning_recommendation_approval', [
            'PageTitle' => $PageTitle,
            'PageDescription' => $PageDescription,
            'report' => $report,
            'isPPDirector' => strtolower((string) $request->query('role', '')) === 'pp_director',
        ]);
    }

    /**
     * Get conversion JSI report data for DataTables.
     * Data loaded directly from joint_site_inspection_reports table.
     */
    public function getData(Request $request)
    {
        $baseWhere = function ($q) {
            $q->where('jsi.source', 'Conversion Applications')
              ->orWhere('jsi.file_number', 'LIKE', 'CON-RES%')
              ->orWhere('jsi.file_number', 'LIKE', 'CON-COM%')
              ->orWhere('jsi.file_number', 'LIKE', 'CON-IND%')
              ->orWhere('jsi.file_number', 'LIKE', 'CON-AG%');
        };

        $query = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports as jsi')
            ->leftJoin('file_indexings as fi', 'fi.file_number', '=', 'jsi.file_number')
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
            ])
            ->whereNull('jsi.sub_application_id')
            ->where($baseWhere);

        // Apply search filter
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

        // Apply land use type filter
        if ($request->has('land_use_type') && !empty($request->land_use_type)) {
            $landUseType = $request->land_use_type;
            $query->where(function ($q) use ($landUseType) {
                $q->where('jsi.applied_land_use', $landUseType)
                  ->orWhere('jsi.prevailing_land_use', $landUseType);
            });
        }

        // Apply compliance status filter
        if ($request->has('compliance_status') && !empty($request->compliance_status)) {
            $query->where('jsi.compliance_status', $request->compliance_status);
        }

        // Apply location-based filters (district, LGA, registry search within location text)
        if ($request->has('district') && !empty($request->district)) {
            $query->where('jsi.location', 'LIKE', "%{$request->district}%");
        }
        if ($request->has('lga') && !empty($request->lga)) {
            $query->where('jsi.location', 'LIKE', "%{$request->lga}%");
        }

        // Get total records count (same base filter, no search/filter applied)
        $totalRecords = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports as jsi')
            ->whereNull('jsi.sub_application_id')
            ->where(function ($q) {
                $q->where('jsi.source', 'Conversion Applications')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-RES%')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-COM%')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-IND%')
                  ->orWhere('jsi.file_number', 'LIKE', 'CON-AG%');
            })
            ->count();

        $filteredRecords = $query->count();

        $summaryBaseQuery = clone $query;
        $summary = [
            'total_reports' => $filteredRecords,
            'submitted_reports' => (clone $summaryBaseQuery)->where('jsi.is_submitted', 1)->count(),
            'generated_reports' => (clone $summaryBaseQuery)
                ->where('jsi.is_generated', 1)
                ->where(function ($q) {
                    $q->whereNull('jsi.is_submitted')->orWhere('jsi.is_submitted', 0);
                })
                ->count(),
            'draft_reports' => (clone $summaryBaseQuery)
                ->where(function ($q) {
                    $q->whereNull('jsi.is_generated')->orWhere('jsi.is_generated', 0);
                })
                ->where(function ($q) {
                    $q->whereNull('jsi.is_submitted')->orWhere('jsi.is_submitted', 0);
                })
                ->count(),
            'obtainable_reports' => (clone $summaryBaseQuery)->where('jsi.compliance_status', 'obtainable')->count(),
            'not_obtainable_reports' => (clone $summaryBaseQuery)->where('jsi.compliance_status', 'not_obtainable')->count(),
        ];

        // Apply ordering – column indexes map to the DataTable columns
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');

        $sortableColumns = [
            0 => 'jsi.file_number',           // File Number
            1 => 'jsi.applicant_name',        // Applicant
            2 => 'jsi.applied_land_use',      // Applied Land Use
            3 => 'fi.tp_no',                  // TPNo
            4 => 'jsi.lkn_number',            // LKN No
            5 => 'jsi.plot_number',           // Plot No
            6 => 'jsi.location',              // Location
            7 => 'jsi.inspection_date',       // Inspection Date
            8 => 'jsi.recommended_site_area', // Recommended Site Measurement
            9 => 'jsi.existing_site_area',    // Existing Site Measurement
            10 => 'jsi.compliance_status',    // Compliance
            11 => 'jsi.is_approved',          // JSI Status
            12 => 'jsi.created_at',           // Created
        ];

        if (isset($sortableColumns[$orderColumnIndex])) {
            $query->orderBy($sortableColumns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('jsi.created_at', 'desc');
        }

        // Pagination
        $start = $request->input('start', 0);
        $length = $request->input('length', 25);
        $files = $query->skip($start)->take($length)->get();

        // Format data for DataTables
        $data = $files->map(function ($file) {
            $isGenerated = (bool) ($file->jsi_is_generated ?? false);
            $isSubmitted = (bool) ($file->jsi_is_submitted ?? false);
            $isApproved = (bool) ($file->jsi_is_approved ?? false);

            // JSI status is only Pending or Approved.
            $jsiStatusCode = $isApproved ? 'approved' : 'pending';
            $jsiStatusLabel = $isApproved ? 'Approved' : 'Pending';

            return [
                'id'                  => $file->jsi_report_id,
                'jsi_report_id'       => $file->jsi_report_id,
                'application_id'      => $file->application_id,
                'file_number'         => $file->file_number ?? 'N/A',
                'applicant_name'      => $file->applicant_name ?? 'N/A',
                'applied_land_use'    => $file->applied_land_use ?? 'N/A',
                'prevailing_land_use' => $file->prevailing_land_use ?? 'N/A',
                'tp_no'               => $file->tp_no ?? 'N/A',
                'plot_number'         => $file->plot_number ?? 'N/A',
                'location'            => $file->location ?? 'N/A',
                'lkn_number'          => $file->lkn_number ?? 'N/A',
                'inspection_date'     => $file->inspection_date ?? null,
                'inspection_officer'  => $file->inspection_officer ?? 'N/A',
                'existing_site_area'  => $file->existing_site_area,
                'recommended_site_area' => $file->recommended_site_area,
                'compliance_status'   => $file->compliance_status ?? 'N/A',
                'purpose'             => $file->purpose ?? 'N/A',
                'source'              => $file->source ?? 'N/A',
                'jsi_status_code'     => $jsiStatusCode,
                'jsi_status_label'    => $jsiStatusLabel,
                'jsi_is_generated'    => $isGenerated,
                'jsi_is_submitted'    => $isSubmitted,
                'jsi_is_approved'     => $isApproved,
                'jsi_generated_at'    => $file->jsi_generated_at,
                'jsi_submitted_at'    => $file->jsi_submitted_at,
                'jsi_approved_at'     => $file->jsi_approved_at,
                'jsi_updated_at'      => $file->jsi_updated_at,
                'created_at'          => $file->created_at ?? null,
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
     * Print final recommendation for a conversion application.
     */
    public function printRecommendation($applicationId)
    {
        $report = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->where(function ($query) {
                $query->where('source', 'Conversion Applications')
                    ->orWhere('file_number', 'LIKE', 'CON-%');
            })
            ->orderByDesc('id')
            ->first();

        if (!$report) {
            abort(404, 'Recommendation data not found for this conversion application.');
        }

        $rawEntries = $report->existing_site_measurement_entries ?? [];
        if (is_string($rawEntries) && trim($rawEntries) !== '') {
            $decoded = json_decode($rawEntries, true);
            $rawEntries = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($rawEntries)) {
            $rawEntries = [];
        }

        $metaEntries = collect($rawEntries)
            ->filter(fn($entry) => is_array($entry) && !empty($entry['description']))
            ->keyBy(fn($entry) => strtolower(trim((string) ($entry['description'] ?? ''))));

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
            $detailedLandUse = DB::connection('sqlsrv')
                ->table('detailed_land_use')
                ->select('name')
                ->where('id', $detailedLandUseId)
                ->first();
            $detailedLandUseName = $detailedLandUse->name ?? null;
        }

        $existingMeasurement = trim(implode(' x ', array_filter([
            $report->existing_site_length ?? null,
            $report->existing_site_width ?? null,
        ], function ($value) {
            return $value !== null && $value !== '';
        })));
        if (($report->existing_site_area ?? null) !== null && $report->existing_site_area !== '') {
            $existingMeasurement .= ($existingMeasurement !== '' ? ' | ' : '') . 'Area: ' . number_format((float) $report->existing_site_area, 2) . ' m2';
        }

        $recommendedMeasurement = trim(implode(' x ', array_filter([
            $report->recommended_site_length ?? null,
            $report->recommended_site_width ?? null,
        ], function ($value) {
            return $value !== null && $value !== '';
        })));
        if (($report->recommended_site_area ?? null) !== null && $report->recommended_site_area !== '') {
            $recommendedMeasurement .= ($recommendedMeasurement !== '' ? ' | ' : '') . 'Area: ' . number_format((float) $report->recommended_site_area, 2) . ' m2';
        }

        $resolvedPurpose = $report->purpose
            ?? ($purposeMeta['measurement_dimension'] ?? null)
            ?? null;

        $resolvedExistingRoadReservation = $report->existing_road_reservation
            ?? ($report->observed_road_category ?? null)
            ?? ($reservationMetaDetail['observed_road_category'] ?? null)
            ?? ($reservationMetaDetail['road_category'] ?? null)
            ?? null;

        $resolvedRecommendedRoadReservation = $report->recommended_road_reservation
            ?? (($reservationMetaDetail['right_of_way'] ?? null) === 'OTHER'
                ? ($reservationMetaDetail['right_of_way_other'] ?? null)
                : ($reservationMetaDetail['right_of_way'] ?? null))
            ?? $report->road_reservation
            ?? null;

        $resolvedSurroundingLandUse = $report->prevailing_land_use
            ?? $detailedLandUseName
            ?? null;

        $resolvedRecommendedLandUse = $report->applied_land_use
            ?? $detailedLandUseName
            ?? null;

        $resolvedApplicationNumber = $report->file_number
            ?? ($report->application_id ?? $applicationId);

        $resolvedInspectionOfficer = preg_replace('/\s*\[ID:\s*\d+\]\s*/', '', (string) ($report->inspection_officer ?? ''));

        return view('physical_planning.conversion.print.recommendation', [
            'report' => $report,
            'applicationDate' => $report->inspection_date ? \Carbon\Carbon::parse($report->inspection_date) : null,
            'applicantName' => $report->applicant_name ?? '',
            'applicationNumber' => $resolvedApplicationNumber,
            'location' => $report->location ?? '',
            'purpose' => $resolvedPurpose,
            'siteMeasurements' => $existingMeasurement !== '' ? $existingMeasurement : 'N/A',
            'recommendedSiteMeasurements' => $recommendedMeasurement !== '' ? $recommendedMeasurement : 'N/A',
            'existingRoadReservation' => $resolvedExistingRoadReservation ?? 'N/A',
            'surroundingLandUse' => $resolvedSurroundingLandUse ?? 'N/A',
            'recommendedRoadReservation' => $resolvedRecommendedRoadReservation ?? 'N/A',
            'recommendedLandUse' => $resolvedRecommendedLandUse ?? 'N/A',
            'inspectionOfficer' => $resolvedInspectionOfficer,
        ]);
    }
}
