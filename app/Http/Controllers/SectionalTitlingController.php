<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class SectionalTitlingController extends Controller
{





    public function index()
    {
        $PageTitle = 'Sectional Titling Module (STM)';
        $PageDescription = 'Process CofO for individually owned sections of multi-unit developments.';
        $Primary = DB::connection('sqlsrv')->table('dbo.mother_applications')
            ->orderBy('sys_date', 'desc')
            ->take(5)
            ->get();

        $Secondary = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->orderBy('sys_date', 'desc')
            ->take(5)
            ->get();


        return view('sectionaltitling.index', compact(
            'Primary',
            'Secondary',
            'PageTitle',
            'PageDescription'
        ));
    }

    public function Primary(Request $request)
    {
        if ($request->has('survey')) {
            $PageTitle = 'Sectional Titling Survey';
            $PageDescription = 'Process Survey Applications';
        } else {
            $PageTitle = $request->get('url') === 'phy_planning' ? 'Planning Recommendation Approval' :
                ($request->get('url') === 'recommendation' ? 'Planning Recommendation' : 'Primary Applications');
            $PageDescription = $request->get('url') === 'phy_planning' ? '' :
                ($request->get('url') === 'recommendation' ? 'Review and process planning recommendation for sectional titles' : 'Process CofO for individually owned sections of multi-unit developments.');
        }

        $PrimaryApplications = DB::connection('sqlsrv')
            ->table('dbo.mother_applications')
            ->leftJoin('dbo.joint_site_inspection_reports as jsi', function ($join) {
                $join->on('jsi.application_id', '=', 'dbo.mother_applications.id')
                    ->whereNull('jsi.sub_application_id');
            })
            ->whereRaw("(dbo.mother_applications.is_deleted IS NULL OR dbo.mother_applications.is_deleted = '0')")
            ->leftJoin('dbo.users as creator', DB::raw('TRY_CAST(dbo.mother_applications.created_by AS INT)'), '=', 'creator.id')
            ->select(
                'dbo.mother_applications.*',
                DB::raw("LTRIM(RTRIM(COALESCE(creator.first_name, '') + ' ' + COALESCE(creator.last_name, ''))) as created_by_name"),
                'jsi.is_generated as jsi_is_generated',
                'jsi.is_submitted as jsi_is_submitted',
                'jsi.is_approved as jsi_is_approved',
                'jsi.approved_at as jsi_approved_at',
                'jsi.inspection_date as jsi_inspection_date',
                'dbo.mother_applications.planning_recommendation_status as mother_planning_status',
                'dbo.mother_applications.application_status as mother_application_status'
            )
            ->orderBy('dbo.mother_applications.sys_date', 'desc')
            ->get();

        $primaryIds = $PrimaryApplications
            ->pluck('id')
            ->filter()
            ->map(function ($id) {
                return (string) $id;
            })
            ->unique()
            ->values();

        $latestUploadsMap = [];
        if ($primaryIds->isNotEmpty()) {
            $latestUploads = DB::connection('sqlsrv')
                ->table('dbo.final_conveyance_uploads')
                ->whereIn('application_id', $primaryIds)
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', 'active');
                })
                ->orderBy('application_id')
                ->orderByDesc('id') // Ensure we get the latest if multiples exist
                ->get();

            // Group by application_id and take the first (latest due to ordering)
            foreach ($latestUploads as $upload) {
                if (!isset($latestUploadsMap[$upload->application_id])) {
                    $latestUploadsMap[$upload->application_id] = $upload;
                }
            }
        }

        $puaSummaries = collect();
        $primaryMemoLookup = [];

        if ($primaryIds->isNotEmpty()) {
            $connection = DB::connection('sqlsrv');

            $primaryMemoLookup = $connection
                ->table('memos')
                ->whereIn('application_id', $primaryIds)
                ->whereNull('unit_id')
                ->whereIn('memo_type', ['primary', 'PRIMARY'])
                ->pluck('application_id')
                ->mapWithKeys(function ($id) {
                    return [(int) $id => true];
                })
                ->all();

            $unitRecords = $connection
                ->table('subapplications as s')
                ->leftJoin('rofo as r', 'r.sub_application_id', '=', 's.id')
                ->leftJoin('st_cofo as c', 'c.sub_application_id', '=', 's.id')
                ->whereRaw("TRY_CAST(s.main_application_id AS BIGINT) IN (" . $primaryIds->map(fn($id) => (int) $id)->implode(',') . ")")
                ->whereRaw("(s.is_deleted IS NULL OR s.is_deleted = '0')")
                ->select(
                    's.id',
                    's.main_application_id',
                    'r.id as rofo_id',
                    'r.rofo_no',
                    'c.id as cofo_id'
                )
                ->get();

            $unitIds = $unitRecords
                ->pluck('id')
                ->filter()
                ->map(function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->values();

            $memoUnitIds = collect();

            if ($unitIds->isNotEmpty() && Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
                $memoUnitIds = $connection
                    ->table('unit_st_memo_uploads')
                    ->whereIn('unit_application_id', $unitIds)
                    ->pluck('unit_application_id')
                    ->map(function ($id) {
                        return (int) $id;
                    })
                    ->unique();
            }

            if ($memoUnitIds->isEmpty() && $unitIds->isNotEmpty()) {
                $memoUnitIds = $connection
                    ->table('memos')
                    ->whereIn('unit_id', $unitIds)
                    ->whereRaw('LOWER(memo_type) = ?', ['pua'])
                    ->pluck('unit_id')
                    ->map(function ($id) {
                        return (int) $id;
                    })
                    ->unique();
            }

            $memoLookup = $memoUnitIds
                ->mapWithKeys(function ($id) {
                    return [$id => true];
                })
                ->all();

            $summaryData = [];

            foreach ($unitRecords as $record) {
                $primaryId = (int) ($record->main_application_id ?? 0);

                if ($primaryId === 0) {
                    continue;
                }

                if (!array_key_exists($primaryId, $summaryData)) {
                    $summaryData[$primaryId] = [
                        'total_units' => 0,
                        'memo_generated' => 0,
                        'rofo_details_captured' => 0,
                        'rofo_generated' => 0,
                        'cofo_generated' => 0,
                    ];
                }

                $summary = &$summaryData[$primaryId];

                $summary['total_units']++;

                if (isset($memoLookup[(int) ($record->id ?? 0)])) {
                    $summary['memo_generated']++;
                }

                if (!empty($record->rofo_id)) {
                    $summary['rofo_details_captured']++;
                }

                if (!empty($record->rofo_no)) {
                    $summary['rofo_generated']++;
                }

                if (!empty($record->cofo_id)) {
                    $summary['cofo_generated']++;
                }
            }

            $puaSummaries = collect($summaryData)->map(function (array $summary) {
                $totalUnits = max(0, (int) ($summary['total_units'] ?? 0));
                $memoGenerated = max(0, (int) ($summary['memo_generated'] ?? 0));
                $detailsCaptured = max(0, (int) ($summary['rofo_details_captured'] ?? 0));
                $rofoGenerated = max(0, (int) ($summary['rofo_generated'] ?? 0));
                $cofoGenerated = max(0, (int) ($summary['cofo_generated'] ?? 0));

                $memoPending = max(0, $totalUnits - $memoGenerated);
                $detailsPending = max(0, $totalUnits - $detailsCaptured);
                $cofoPending = max(0, $totalUnits - $cofoGenerated);

                $prerequisitesMet = $totalUnits > 0 && $memoPending === 0 && $detailsPending === 0;

                return (object) [
                    'total_units' => $totalUnits,
                    'memo_generated' => $memoGenerated,
                    'memo_pending' => $memoPending,
                    'details_captured' => $detailsCaptured,
                    'details_pending' => $detailsPending,
                    'rofo_generated' => $rofoGenerated,
                    'cofo_generated' => $cofoGenerated,
                    'cofo_pending' => $cofoPending,
                    'prerequisites_met' => $prerequisitesMet,
                ];
            });
        }

        $PrimaryApplications = $PrimaryApplications->map(function ($primary) use ($puaSummaries, $primaryMemoLookup, $latestUploadsMap) {
            $primaryId = (int) ($primary->id ?? 0);
            $primary->primary_memo_generated = isset($primaryMemoLookup[$primaryId]);

            // Map upload info
            if (isset($latestUploadsMap[$primaryId])) {
                $upload = $latestUploadsMap[$primaryId];
                $primary->latest_upload_id = $upload->id;
                $primary->latest_upload_filename = $upload->original_filename;
                $primary->latest_upload_at = $upload->uploaded_at;
            } else {
                $primary->latest_upload_id = null;
                $primary->latest_upload_filename = null;
                $primary->latest_upload_at = null;
            }

            $primary->pua_unit_summary = $puaSummaries->get($primaryId, (object) [
                'total_units' => 0,
                'memo_generated' => 0,
                'memo_pending' => 0,
                'details_captured' => 0,
                'details_pending' => 0,
                'rofo_generated' => 0,
                'cofo_generated' => 0,
                'cofo_pending' => 0,
                'prerequisites_met' => false,
            ]);

            return $primary;
        });


        return view('sectionaltitling.primary', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function Secondary(Request $request)
    {
        if ($request->has('survey')) {
            $PageTitle = 'Sectional Titling Survey';
            $PageDescription = 'Process Survey Applications';
        } else {
            $PageTitle = $request->get('url') === 'phy_planning' ? 'Planning Recommendation Approval' :
                ($request->get('url') === 'recommendation' ? 'Planning Recommendation' : 'Secondary  Applications');
            $PageDescription = $request->get('url') === 'phy_planning' ? '' :
                ($request->get('url') === 'recommendation' ? 'Review and process planning recommendation for sectional titles' : 'Process CofO for individually owned sections of multi-unit developments.');
        }

        $SecondaryApplications = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
                'dbo.subapplications.fileno',
                'dbo.subapplications.applicant_type',
                'dbo.subapplications.scheme_no',
                'dbo.subapplications.id',
                'dbo.subapplications.unit_type',
                'dbo.subapplications.main_application_id',
                'dbo.subapplications.applicant_title',
                'dbo.subapplications.first_name',
                'dbo.subapplications.surname',
                'dbo.subapplications.middle_name',
                'dbo.subapplications.corporate_name',
                'dbo.subapplications.multiple_owners_names',
                'dbo.subapplications.phone_number',
                'dbo.subapplications.planning_recommendation_status',
                'dbo.subapplications.application_status',
                'dbo.subapplications.created_at',
                'dbo.subapplications.unit_number',
                'dbo.subapplications.main_id',
                'dbo.subapplications.is_sua_unit',
                'dbo.subapplications.land_use as sub_land_use',
                'dbo.subapplications.planning_approval_date',
                'dbo.subapplications.approval_date',
                'dbo.subapplications.passport',
                'dbo.subapplications.multiple_owners_passport',
                'dbo.mother_applications.fileno as mother_fileno',
                'dbo.mother_applications.passport as mother_passport',
                'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
                'dbo.mother_applications.applicant_title as mother_applicant_title',
                'dbo.mother_applications.first_name as mother_first_name',
                'dbo.mother_applications.surname as mother_surname',
                'dbo.mother_applications.middle_name as mother_middle_name',
                'dbo.mother_applications.corporate_name as mother_corporate_name',
                'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
                'dbo.mother_applications.land_use',
                'dbo.mother_applications.property_house_no',
                'dbo.mother_applications.property_plot_no',
                'dbo.mother_applications.property_street_name',
                'dbo.mother_applications.property_district',
                'dbo.mother_applications.property_lga',
                'dbo.mother_applications.np_fileno',
                'dbo.mother_applications.planning_recommendation_status as mother_planning_status',
                'dbo.mother_applications.application_status as mother_application_status'
            )
            ->orderBy('dbo.subapplications.sys_date', 'desc')
            ->get();


        return view('sectionaltitling.secondary', compact('SecondaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function getRofoUnits($primaryId)
    {
        try {
            $primaryRecord = DB::connection('sqlsrv')
                ->table('dbo.mother_applications as m')
                ->select(
                    'm.applicant_type',
                    'm.applicant_title',
                    'm.first_name',
                    'm.surname',
                    'm.middle_name',
                    'm.corporate_name',
                    'm.multiple_owners_names'
                )
                ->where('m.id', $primaryId)
                ->first();

            $primaryOwnerName = $primaryRecord
                ? formatApplicantDisplayName(
                    $primaryRecord->applicant_type ?? '',
                    $primaryRecord->applicant_title ?? '',
                    $primaryRecord->first_name ?? '',
                    $primaryRecord->middle_name ?? '',
                    $primaryRecord->surname ?? '',
                    $primaryRecord->corporate_name ?? '',
                    $primaryRecord->multiple_owners_names ?? ''
                )
                : 'N/A';

            $subApplications = DB::connection('sqlsrv')
                ->table('dbo.subapplications as s')
                ->leftJoin('dbo.rofo as r', 'r.sub_application_id', '=', 's.id')
                ->whereRaw("TRY_CAST(s.main_application_id AS BIGINT) = ?", [(int) $primaryId])
                ->whereRaw("(s.is_deleted IS NULL OR s.is_deleted = '0')")
                ->select(
                    's.id',
                    's.fileno',
                    's.scheme_no',
                    's.unit_number',
                    's.block_number',
                    's.floor_number',
                    's.applicant_type',
                    's.applicant_title',
                    's.first_name',
                    's.middle_name',
                    's.surname',
                    's.corporate_name',
                    's.multiple_owners_names',
                    's.application_status',
                    's.planning_recommendation_status',
                    's.created_at',
                    'r.rofo_no',
                    'r.id as rofo_id',
                    'r.created_at as rofo_created_at',
                    'r.term_years as rofo_term_years'
                )
                ->orderBy('s.unit_number')
                ->orderBy('s.block_number')
                ->orderBy('s.floor_number')
                ->get();

            if ($subApplications->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $unitIds = $subApplications->pluck('id')->filter()->all();
            $memoUnitIds = empty($unitIds)
                ? []
                : DB::connection('sqlsrv')
                    ->table('dbo.memos')
                    ->where('memo_type', 'SUA')
                    ->whereIn('unit_id', $unitIds)
                    ->pluck('unit_id')
                    ->unique()
                    ->toArray();

            $primaryTermYears = $subApplications
                ->pluck('rofo_term_years')
                ->filter(function ($value) {
                    return $value !== null && $value !== '';
                })
                ->map(function ($value) {
                    return is_numeric($value) ? (int) $value : null;
                })
                ->filter()
                ->first();

            if ($primaryTermYears === null) {
                $primaryTermYears = DB::connection('sqlsrv')
                    ->table('dbo.rofo')
                    ->where('application_id', $primaryId)
                    ->whereNotNull('term_years')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('created_at')
                    ->value('term_years');

                if ($primaryTermYears !== null && $primaryTermYears !== '') {
                    $primaryTermYears = is_numeric($primaryTermYears)
                        ? (int) $primaryTermYears
                        : null;
                } else {
                    $primaryTermYears = null;
                }
            }

            if ($primaryTermYears === null) {
                $primaryTermYears = DB::connection('sqlsrv')
                    ->table('dbo.memos')
                    ->where('application_id', $primaryId)
                    ->whereNull('unit_id')
                    ->whereIn('memo_type', ['primary', 'PRIMARY'])
                    ->whereNotNull('term_years')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('created_at')
                    ->value('term_years');

                if ($primaryTermYears !== null && $primaryTermYears !== '') {
                    $primaryTermYears = is_numeric($primaryTermYears)
                        ? (int) $primaryTermYears
                        : null;
                } else {
                    $primaryTermYears = null;
                }
            }

            $payload = $subApplications->map(function ($application) use ($memoUnitIds) {
                $ownerName = formatApplicantDisplayName(
                    $application->applicant_type ?? '',
                    $application->applicant_title ?? '',
                    $application->first_name ?? '',
                    $application->middle_name ?? '',
                    $application->surname ?? '',
                    $application->corporate_name ?? '',
                    $application->multiple_owners_names ?? ''
                );

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $fallbackCorporate = trim((string) ($application->corporate_name ?? ''));
                    if ($fallbackCorporate !== '') {
                        $ownerName = $fallbackCorporate;
                    }
                }

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $ownersList = parseApplicantOwnersList($application->multiple_owners_names ?? null);
                    if (!empty($ownersList)) {
                        $ownerName = implode(', ', $ownersList);
                    }
                }

                $termYears = $application->rofo_term_years ?? null;
                $termYears = is_numeric($termYears) ? (int) $termYears : null;

                return [
                    'id' => $application->id,
                    'fileno' => $application->fileno,
                    'scheme_no' => $application->scheme_no,
                    'unit_number' => $application->unit_number,
                    'block_number' => $application->block_number,
                    'floor_number' => $application->floor_number,
                    'owner_name' => $ownerName,
                    'application_status' => $application->application_status,
                    'planning_status' => $application->planning_recommendation_status,
                    'created_at' => $application->created_at,
                    'rofo_exists' => !empty($application->rofo_no),
                    'rofo_no' => $application->rofo_no,
                    'rofo_id' => $application->rofo_id,
                    'rofo_created_at' => $application->rofo_created_at,
                    'has_st_memo' => in_array($application->id, $memoUnitIds, true),
                    'term_years' => $termYears,
                ];
            });

            if ($primaryTermYears !== null) {
                $payload = $payload->map(function ($unit) use ($primaryTermYears) {
                    if (!array_key_exists('term_years', $unit) || $unit['term_years'] === null) {
                        $unit['term_years'] = $primaryTermYears;
                    }

                    return $unit;
                });
            }

            $payload = $payload->values();

            return response()->json([
                'success' => true,
                'data' => $payload,
                'primary_owner_name' => $primaryOwnerName,
                'primary_term_years' => $primaryTermYears,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to fetch ROFO units', [
                'primary_id' => $primaryId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load ROFO details for this application.',
            ], 500);
        }
    }

    public function units(Request $request)
    {
        $PageTitle = 'Parented Units';
        $PageDescription = 'Process CofO for individually owned sections of multi-unit developments.';

        $query = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->leftJoin('dbo.joint_site_inspection_reports as jsi', function ($join) {
                $join->on('jsi.sub_application_id', '=', 'dbo.subapplications.id');
            })
            ->leftJoin('dbo.users as creator', DB::raw('TRY_CAST(dbo.subapplications.created_by AS INT)'), '=', 'creator.id')
            ->select(
                'dbo.subapplications.fileno',
                'dbo.subapplications.scheme_no',
                'dbo.subapplications.id',
                'dbo.subapplications.main_application_id',
                'dbo.subapplications.applicant_title',
                'dbo.subapplications.first_name',
                'dbo.subapplications.surname',
                'dbo.subapplications.middle_name',
                'dbo.subapplications.corporate_name',
                'dbo.subapplications.multiple_owners_names',
                'dbo.subapplications.phone_number',
                'dbo.subapplications.planning_recommendation_status',
                'dbo.subapplications.application_status',
                'dbo.subapplications.created_at',
                'dbo.subapplications.sys_date',
                'dbo.subapplications.application_date',
                'dbo.subapplications.unit_number',
                'dbo.subapplications.main_id',
                'dbo.subapplications.passport',
                'dbo.subapplications.multiple_owners_passport',
                'dbo.mother_applications.fileno as mother_fileno',
                'mother_applications.id as mother_id',
                'dbo.mother_applications.passport as mother_passport',
                'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
                'dbo.mother_applications.applicant_title as mother_applicant_title',
                'dbo.mother_applications.first_name as mother_first_name',
                'dbo.mother_applications.surname as mother_surname',
                'dbo.mother_applications.middle_name as mother_middle_name',
                'dbo.mother_applications.corporate_name as mother_corporate_name',
                'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
                'dbo.mother_applications.land_use',
                'dbo.mother_applications.property_house_no',
                'dbo.mother_applications.property_plot_no',
                'dbo.mother_applications.property_street_name',
                'dbo.mother_applications.property_district',
                'dbo.mother_applications.property_lga',
                'dbo.mother_applications.np_fileno',
                'dbo.subapplications.is_sua_unit',
                // Creator for the "Created By" column's profile card. The id itself is
                // selected too: the view opens the card by id, and this query lists its
                // columns explicitly, so leaving it out made $app->created_by undefined.
                'dbo.subapplications.created_by',
                DB::raw("LTRIM(RTRIM(COALESCE(creator.first_name, '') + ' ' + COALESCE(creator.last_name, ''))) as created_by_name"),
                'jsi.is_generated as jsi_is_generated',
                'jsi.is_submitted as jsi_is_submitted',
                'jsi.is_approved as jsi_is_approved',
                'jsi.approved_at as jsi_approved_at',
                'jsi.inspection_date as jsi_inspection_date',
                'dbo.mother_applications.planning_recommendation_status as mother_planning_status',
                'dbo.mother_applications.application_status as mother_application_status'
            )
            // Only fetch records that are not soft-deleted
            ->whereRaw("(dbo.subapplications.is_deleted IS NULL OR dbo.subapplications.is_deleted = '0')");

        // Check if main_application_id parameter exists in URL
        if ($request->has('main_application_id')) {
            $mainApplicationId = $request->get('main_application_id');
            $query->whereRaw("TRY_CAST(dbo.subapplications.main_application_id AS BIGINT) = ?", [(int) $mainApplicationId]);
        }

        $SecondaryApplications = $query
            ->where(function ($q) {
                $q->where('dbo.subapplications.unit_type', '!=', 'SUA')
                    ->orWhereNull('dbo.subapplications.unit_type');
            })
            ->orderBy('dbo.subapplications.sys_date', 'desc')
            ->get();


        return view('sectionaltitling.units', compact('SecondaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function getCofoUnits(Request $request, $primaryId)
    {
        try {
            $primaryRecord = DB::connection('sqlsrv')
                ->table('dbo.mother_applications as m')
                ->select(
                    'm.applicant_type',
                    'm.applicant_title',
                    'm.first_name',
                    'm.middle_name',
                    'm.surname',
                    'm.corporate_name',
                    'm.multiple_owners_names'
                )
                ->where('m.id', $primaryId)
                ->first();

            $primaryOwnerName = $primaryRecord
                ? formatApplicantDisplayName(
                    $primaryRecord->applicant_type ?? '',
                    $primaryRecord->applicant_title ?? '',
                    $primaryRecord->first_name ?? '',
                    $primaryRecord->middle_name ?? '',
                    $primaryRecord->surname ?? '',
                    $primaryRecord->corporate_name ?? '',
                    $primaryRecord->multiple_owners_names ?? ''
                )
                : 'N/A';

            $subApplications = DB::connection('sqlsrv')
                ->table('dbo.subapplications as s')
                ->leftJoin('dbo.mother_applications as m', 'm.id', '=', 's.main_application_id')
                ->leftJoin('dbo.st_cofo as c', function ($join) {
                    $join->on('c.sub_application_id', '=', 's.id')
                        ->where('c.is_active', 1);
                })
                ->whereRaw("TRY_CAST(s.main_application_id AS BIGINT) = ?", [(int) $primaryId])
                ->whereRaw("(s.is_deleted IS NULL OR s.is_deleted = '0')")
                ->select(
                    's.id',
                    's.fileno',
                    's.scheme_no',
                    's.unit_number',
                    's.block_number',
                    's.floor_number',
                    's.applicant_type',
                    's.applicant_title',
                    's.first_name',
                    's.middle_name',
                    's.surname',
                    's.corporate_name',
                    's.multiple_owners_names',
                    's.application_status',
                    's.planning_recommendation_status',
                    's.created_at',
                    's.land_use as unit_land_use',
                    's.address',
                    's.property_location as unit_property_location',
                    'm.id as mother_id',
                    'm.land_use as mother_land_use',
                    'm.property_plot_no',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.property_district',
                    'm.property_lga',
                    'm.property_state',
                    'c.id as cofo_id',
                    'c.certificate_number',
                    'c.start_date as cofo_start_date',
                    'c.total_term as cofo_total_term',
                    'c.remaining_term as cofo_remaining_term',
                    'c.issued_date as cofo_issued_date'
                )
                ->orderBy('s.unit_number')
                ->orderBy('s.block_number')
                ->orderBy('s.floor_number')
                ->get();

            if ($subApplications->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $unitIds = $subApplications->pluck('id')->filter()->all();
            $memoUnitIds = empty($unitIds)
                ? []
                : DB::connection('sqlsrv')
                    ->table('dbo.memos')
                    ->where('memo_type', 'SUA')
                    ->whereIn('unit_id', $unitIds)
                    ->pluck('unit_id')
                    ->unique()
                    ->toArray();

            $payload = $subApplications->map(function ($application) use ($memoUnitIds) {
                $ownerName = formatApplicantDisplayName(
                    $application->applicant_type ?? '',
                    $application->applicant_title ?? '',
                    $application->first_name ?? '',
                    $application->middle_name ?? '',
                    $application->surname ?? '',
                    $application->corporate_name ?? '',
                    $application->multiple_owners_names ?? ''
                );

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $fallbackCorporate = trim((string) ($application->corporate_name ?? ''));
                    if ($fallbackCorporate !== '') {
                        $ownerName = $fallbackCorporate;
                    }
                }

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $ownersList = parseApplicantOwnersList($application->multiple_owners_names ?? null);
                    if (!empty($ownersList)) {
                        $ownerName = implode(', ', $ownersList);
                    }
                }

                $resolvedLandUse = trim((string) ($application->unit_land_use ?? ''));
                if ($resolvedLandUse === '') {
                    $resolvedLandUse = trim((string) ($application->mother_land_use ?? ''));
                }

                $startDate = $application->cofo_start_date ? Carbon::parse($application->cofo_start_date)->format('Y-m-d') : null;
                $totalTerm = $application->cofo_total_term ?? null;

                $propertyLocation = $application->unit_property_location;
                if (empty($propertyLocation)) {
                    $locationParts = [];

                    if (!empty($application->block_number)) {
                        $locationParts[] = 'BLOCK ' . strtoupper(trim((string) $application->block_number));
                    }
                    if (!empty($application->floor_number)) {
                        $locationParts[] = 'FLOOR ' . strtoupper(trim((string) $application->floor_number));
                    }
                    if (!empty($application->unit_number)) {
                        $locationParts[] = 'UNIT ' . strtoupper(trim((string) $application->unit_number));
                    }
                    if (!empty($application->property_plot_no)) {
                        $locationParts[] = 'PLOT ' . strtoupper(trim((string) $application->property_plot_no));
                    }

                    if (!empty($locationParts)) {
                        $propertyLocation = normalizeLocationText(implode(', ', $locationParts));
                    }
                }

                $holderAddress = $application->address ? normalizeLocationText($application->address) : '';

                return [
                    'id' => $application->id,
                    'fileno' => $application->fileno,
                    'scheme_no' => $application->scheme_no,
                    'unit_number' => $application->unit_number,
                    'block_number' => $application->block_number,
                    'floor_number' => $application->floor_number,
                    'owner_name' => $ownerName,
                    'application_status' => $application->application_status,
                    'planning_status' => $application->planning_recommendation_status,
                    'created_at' => $application->created_at,
                    'cofo_exists' => !empty($application->certificate_number),
                    'cofo_certificate_number' => $application->certificate_number,
                    'cofo_start_date' => $startDate,
                    'cofo_total_term' => $totalTerm,
                    'cofo_remaining_term' => $application->cofo_remaining_term,
                    'cofo_issued_date' => $application->cofo_issued_date,
                    'has_st_memo' => in_array($application->id, $memoUnitIds, true),
                    'application_payload' => [
                        'id' => $application->id,
                        'fileno' => $application->fileno,
                        'scheme_no' => $application->scheme_no,
                        'block_number' => $application->block_number,
                        'floor_number' => $application->floor_number,
                        'unit_number' => $application->unit_number,
                        'land_use' => $resolvedLandUse,
                        'mother_id' => $application->mother_id,
                        'owner_name' => $ownerName,
                        'address' => $holderAddress,
                        'property_location' => $propertyLocation,
                        'property_plot_no' => $application->property_plot_no,
                        'property_house_no' => $application->property_house_no,
                        'property_street_name' => $application->property_street_name,
                        'property_district' => $application->property_district,
                        'property_lga' => $application->property_lga,
                        'property_state' => $application->property_state,
                    ],
                ];
            });

            $selectedUnitId = $request->get('sub_application_id');

            return response()->json([
                'success' => true,
                'data' => $payload,
                'primary_owner_name' => $primaryOwnerName,
                'selected_unit_id' => $selectedUnitId,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to fetch CofO units', [
                'primary_id' => $primaryId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load CofO details for this application.',
            ], 500);
        }
    }



    public function Map()
    {
        $PageTitle = 'GIS Mapping - Sectional Titling';
        $PageDescription = 'Geospatial visualization of sectional title properties in Kano State.';

        return view('map.index', compact('PageTitle', 'PageDescription'));
    }

    public function mother(Request $request)
    {
        if ($request->has('survey')) {
            $PageTitle = 'Sectional Titling Survey - Mother Applications';
            $PageDescription = 'Process Survey Applications for Mother Applications';
        } else {
            $PageTitle = $request->get('url') === 'phy_planning' ? 'Planning Recommendation Approval - Mother Applications' :
                ($request->get('url') === 'recommendation' ? 'Planning Recommendation - Mother Applications' : 'Mother Applications');
            $PageDescription = $request->get('url') === 'phy_planning' ? 'Physical planning approval for mother applications' :
                ($request->get('url') === 'recommendation' ? 'Review and process planning recommendation for mother applications' : 'Manage and process mother applications for sectional titling.');
        }

        $PrimaryApplications = DB::connection('sqlsrv')->table('dbo.mother_applications')
            ->orderBy('sys_date', 'desc')
            ->get();


        return view('sectionaltitling.mother', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function getBuyerList($applicationId)
    {
        try {
            // Query to get buyer list with unit measurements
            $buyers = DB::connection('sqlsrv')
                ->table('dbo.buyer_list as bl')
                ->leftJoin('dbo.st_unit_measurements as sum', function ($join) {
                    $join->on('bl.application_id', '=', 'sum.application_id')
                        ->on('bl.unit_no', '=', 'sum.unit_no');
                })
                ->select(
                    'bl.application_id',
                    'bl.unit_measurement_id',
                    'bl.buyer_title',
                    'bl.buyer_name',
                    'bl.unit_no',
                    'sum.buyer_id',
                    'sum.measurement'
                )
                ->where('bl.application_id', $applicationId)
                ->get();

            return response()->json([
                'success' => true,
                'buyers' => $buyers,
                'message' => 'Buyer list retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving buyer list: ' . $e->getMessage(),
                'buyers' => []
            ]);
        }
    }

    public function getCofoDetails(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number'));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
            ], 422);
        }

        $variants = $this->buildFileNumberVariants($fileNumber);

        $record = DB::connection('sqlsrv')
            ->table('CofO')
            ->select([
                'serialNo',
                'pageNo',
                'volumeNo',
                'reg_serial',
                'reg_page',
                'reg_volume',
                'regNo',
                'cofo_no',
                'transaction_date',
                'transactionDate',
                'transaction_time',
                'certificate_date',
                'certificateDate',
                'land_use',
                'cofo_type',
                'property_description',
            ])
            ->where(function ($query) use ($variants) {
                foreach (['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'fileno', 'cofo_no'] as $column) {
                    $query->orWhereIn($column, $variants);
                }
            })
            ->orderByDesc(DB::raw('ISNULL(transaction_date, transactionDate)'))
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No CofO record found for this file number.'
            ]);
        }

        $serial = data_get($record, 'serialNo') ?? data_get($record, 'reg_serial');
        $page = data_get($record, 'pageNo') ?? data_get($record, 'reg_page') ?? $serial;
        $volume = data_get($record, 'volumeNo') ?? data_get($record, 'reg_volume');

        $transactionDateRaw = data_get($record, 'transaction_date')
            ?? data_get($record, 'transactionDate')
            ?? data_get($record, 'cofo_date');

        $transactionDate = null;
        $transactionTime = data_get($record, 'transaction_time');

        if ($transactionDateRaw) {
            try {
                $parsed = Carbon::parse($transactionDateRaw);
                $transactionDate = $parsed->format('Y-m-d');
                $transactionTime = $transactionTime ?: $parsed->format('H:i');
            } catch (\Throwable $e) {
                $transactionDate = $transactionDateRaw;
            }
        }

        if (!$transactionTime && !empty(data_get($record, 'transaction_time'))) {
            $transactionTime = substr(data_get($record, 'transaction_time'), 0, 5);
        }

        $certificateDateRaw = data_get($record, 'certificate_date')
            ?? data_get($record, 'certificateDate')
            ?? data_get($record, 'cofo_date');

        $certificateDate = null;

        if ($certificateDateRaw) {
            try {
                $certificateDate = Carbon::parse($certificateDateRaw)->format('Y-m-d');
            } catch (\Throwable $e) {
                $certificateDate = $certificateDateRaw;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'serial_no' => $serial,
                'page_no' => $page,
                'volume_no' => $volume,
                'transaction_date' => $transactionDate,
                'transaction_time' => $transactionTime,
                'cofo_no' => data_get($record, 'cofo_no'),
                'certificate_date' => $certificateDate,
                'reg_no' => data_get($record, 'regNo') ?? ($serial && $page && $volume ? sprintf('%s/%s/%s', $serial, $page, $volume) : null),
                'land_use' => data_get($record, 'land_use'),
                'cofo_type' => data_get($record, 'cofo_type'),
                'property_description' => data_get($record, 'property_description'),
            ],
        ]);
    }

    protected function buildFileNumberVariants(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));
        $variants = [$normalized];

        // Remove spaces variant
        $variants[] = str_replace(' ', '', $normalized);

        // Legacy KANGIS variants e.g. CON-RES-2024-0001 vs CONRES-2024-1
        if (preg_match('/^(CO?N?-?)([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $prefix = $matches[1];
            $landUse = $matches[2];
            $year = $matches[3];
            $serial = ltrim($matches[4], '0');
            if ($serial !== '') {
                $variants[] = sprintf('%s%s-%s-%s', $prefix, $landUse, $year, $serial);
                $variants[] = sprintf('%s%s-%s-%04d', $prefix, $landUse, $year, (int) $serial);
            }
        }

        // Modern ST variants without prefix e.g. RES-2024-0001 vs RES-2024-1
        if (preg_match('/^([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $landUse = $matches[1];
            $year = $matches[2];
            $serial = ltrim($matches[3], '0');
            if ($serial !== '') {
                $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);
            }
        }

        // Remove hyphen variant
        $variants[] = str_replace('-', '', $normalized);

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Render A4 landscape Acknowledgement Slip for a primary (mother) application.
     */
    public function printAcknowledgement($id)
    {
        // Permissions: allow super admin or users with view permission; otherwise, block
        // if (Auth::user()->type != 'super admin' && !Auth::user()->can('View Application')) {
        //     return redirect()->back()->with('error', __('Permission Denied.'));
        // }

        // Fetch mother application from SQL Server
        $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();
        if (!$app) {
            abort(404, 'Application not found');
        }

        // Map applicant name based on applicant_type
        $applicantType = $app->applicant_type ?? null;
        $applicantName = '-';
        if ($applicantType === 'individual') {
            $applicantName = trim(($app->applicant_title ?? '') . ' ' . ($app->first_name ?? '') . ' ' . ($app->middle_name ?? '') . ' ' . ($app->surname ?? ''));
        } elseif ($applicantType === 'corporate') {
            $applicantName = $app->corporate_name ?? '-';
        } elseif ($applicantType === 'multiple') {
            $applicantName = $app->multiple_owners_names ?? '-';
        }

        // Payment fields may or may not exist on mother_applications; read defensively
        $applicationFee = $app->application_fee ?? null;
        $processingFee = $app->processing_fee ?? null;
        $sitePlanFee = $app->site_plan_fee ?? null;
        $receiptNumber = $app->receipt_number ?? null;
        $paymentDate = $app->payment_date ?? null;

        // Enhanced payment status logic
        $paymentStatus = [
            'application_fee_paid' => !empty($applicationFee) && $applicationFee > 0,
            'processing_fee_paid' => !empty($processingFee) && $processingFee > 0,
            'site_plan_fee_paid' => !empty($sitePlanFee) && $sitePlanFee > 0,
        ];

        // Compute total if possible
        $normalize = function ($val) {
            if (is_null($val) || $val === '')
                return 0.0;
            // remove commas and non-numeric except dot
            $val = preg_replace('/[^0-9.]/', '', (string) $val);
            return is_numeric($val) ? (float) $val : 0.0;
        };
        $totalFee = null;
        if (!is_null($applicationFee) || !is_null($processingFee) || !is_null($sitePlanFee)) {
            $sum = $normalize($applicationFee) + $normalize($processingFee) + $normalize($sitePlanFee);
            $totalFee = number_format($sum, 2);
        }

        // Build address string
        $propertyFullAddress = trim(implode(', ', array_filter([
            $app->property_house_no ?? null,
            $app->property_plot_no ?? null,
            $app->property_street_name ?? null,
            $app->property_district ?? null,
            $app->property_lga ?? null,
            $app->property_state ?? 'Kano',
        ])));

        // Document status logic
        $documentsData = json_decode($app->documents ?? '[]', true);
        $documentsWithStatus = [
            'Application Letter *' => isset($documentsData['application_letter']) && !empty($documentsData['application_letter']['path']),
            'Building Plan *' => isset($documentsData['building_plan']) && !empty($documentsData['building_plan']['path']),
            'Architectural Design' => isset($documentsData['architectural_design']) && !empty($documentsData['architectural_design']['path']),
            'Ownership Document' => isset($documentsData['ownership_document']) && !empty($documentsData['ownership_document']['path']),
            'Site Plan (Survey)' => isset($documentsData['survey_plan']) && !empty($documentsData['survey_plan']['path']),
        ];

        // Tracking/watermark
        $track = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('application_id', $id)
            ->first();
        $printedCount = $track ? ((int) ($track->printed_count ?? 0)) : 0;
        $watermarkText = $printedCount > 0 ? 'COPY OF ORIGINAL' : 'ORIGINAL';

        return view('sectionaltitling.print.acknowledgement', [
            'applicationId' => $app->id,
            'landUse' => $app->land_use ?? '-',
            'applicantType' => $applicantType ?? '-',
            'applicantName' => $applicantName,
            'applicantEmail' => $app->owner_email ?? '-',
            'applicantPhone' => $app->phone_number ?? '-',
            'applicantAddress' => ($app->address ?? null) ?: ($app->owner_address ?? '-'),

            'residenceType' => $app->residenceType ?? ($app->residence_type ?? '-'),
            // As requested: Units = NoOfUnits, Blocks = NoOfSections, Sections = NoOfBlocks
            'units' => $app->NoOfUnits ?? ($app->units_count ?? '-'),
            'blocks' => $app->NoOfSections ?? ($app->sections_count ?? '-'),
            'sections' => $app->NoOfBlocks ?? ($app->blocks_count ?? '-'),
            // Both file numbers
            'fileNumber' => $app->fileno ?? '-',
            'npFileNumber' => $app->np_fileno ?? '-',

            'propertyHouseNo' => $app->property_house_no ?? '-',
            'propertyPlotNo' => $app->property_plot_no ?? '-',
            'propertyStreet' => $app->property_street_name ?? '-',
            'propertyDistrict' => $app->property_district ?? '-',
            'propertyLGA' => $app->property_lga ?? '-',
            'propertyState' => $app->property_state ?? 'Kano',
            'propertyFullAddress' => $propertyFullAddress,

            'applicationFee' => $applicationFee,
            'processingFee' => $processingFee,
            'sitePlanFee' => $sitePlanFee,
            'totalFee' => $totalFee,
            'receiptNumber' => $receiptNumber,
            'paymentDate' => $paymentDate,

            // Payment and document status
            'paymentStatus' => $paymentStatus,
            'documentsWithStatus' => $documentsWithStatus,

            // Tracking for watermark and UI
            'printedCount' => $printedCount,
            'watermarkText' => $watermarkText,
        ]);
    }

    /**
     * Generate/Create the Acknowledgement tracking record.
     */
    public function generateAcknowledgement($id)
    {


        $app = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $id)->first();
        if (!$app) {
            return response()->json(['success' => false, 'message' => 'Application not found'], 404);
        }

        // Upsert-like behavior: create if not exists
        $exists = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('application_id', $id)
            ->exists();

        if (!$exists) {
            DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')->insert([
                'application_id' => $id,
                'generated_at' => now(),
                'generated_by_user_id' => Auth::id(),
                'generated_by_user_name' => optional(Auth::user())->name,
                'printed_count' => 0,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Acknowledgement generated']);
    }

    /**
     * Mark an acknowledgement as printed (increment count and timestamps).
     */
    public function markAcknowledgementPrinted($id)
    {
        $track = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('application_id', $id)
            ->first();

        if (!$track) {
            // Auto-create tracking if missing
            DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')->insert([
                'application_id' => $id,
                'generated_at' => now(),
                'generated_by_user_id' => Auth::id(),
                'generated_by_user_name' => optional(Auth::user())->name,
                'printed_count' => 0,
            ]);
            $track = (object) ['printed_count' => 0];
        }

        $now = now();
        $firstPrintedAt = $track->printed_count > 0 ? $track->first_printed_at : $now;

        DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')
            ->where('application_id', $id)
            ->update([
                'printed_count' => DB::raw('ISNULL(printed_count,0) + 1'),
                'first_printed_at' => $firstPrintedAt,
                'last_printed_at' => $now,
                'last_printed_by_user_id' => Auth::id(),
                'last_printed_by_user_name' => optional(Auth::user())->name,
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Generate/Create the Sub-application Acknowledgement tracking record.
     */
    public function generateSubAcknowledgement($id)
    {
        // Sub-application exists?
        $sub = DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->first();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => 'Sub-application not found'], 404);
        }

        $exists = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('sub_application_id', $id)
            ->exists();

        if (!$exists) {
            DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')->insert([
                'application_id' => $sub->main_application_id ?? null,
                'sub_application_id' => $id,
                'generated_at' => now(),
                'generated_by_user_id' => Auth::id(),
                'generated_by_user_name' => optional(Auth::user())->name,
                'printed_count' => 0,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Sub Acknowledgement generated']);
    }

    /**
     * Render Acknowledgement Slip for a sub-application (Unit or SUA) with watermark.
     */
    public function printSubAcknowledgement($id)
    {
        $sub = DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->first();
        if (!$sub)
            abort(404, 'Sub-application not found');

        // Resolve mother application using either main_application_id or legacy main_id
        $mother = null;
        $motherId = null;
        if (!empty($sub->main_application_id)) {
            $motherId = $sub->main_application_id;
        } elseif (!empty($sub->main_id)) {
            $motherId = $sub->main_id;
        }
        if (!empty($motherId)) {
            $mother = DB::connection('sqlsrv')->table('dbo.mother_applications')->where('id', $motherId)->first();
        }

        // Applicant name logic (sub-level)
        $applicantType = $sub->applicant_type ?? '-';
        $applicantName = '-';
        if ($applicantType === 'individual') {
            $applicantName = trim(($sub->applicant_title ?? '') . ' ' . ($sub->first_name ?? '') . ' ' . ($sub->middle_name ?? '') . ' ' . ($sub->surname ?? ''));
        } elseif ($applicantType === 'corporate') {
            $applicantName = $sub->corporate_name ?? '-';
        } elseif ($applicantType === 'multiple') {
            $applicantName = $sub->multiple_owners_names ?? '-';
        }

        // Address from sub (fallback to mother if missing)
        $propertyFullAddress = trim(implode(', ', array_filter([
            $sub->property_house_no ?? ($mother->property_house_no ?? null),
            $sub->property_plot_no ?? ($mother->property_plot_no ?? null),
            $sub->property_street_name ?? ($mother->property_street_name ?? null),
            $sub->property_district ?? ($mother->property_district ?? null),
            $sub->property_lga ?? ($mother->property_lga ?? null),
            $sub->property_state ?? ($mother->property_state ?? 'Kano'),
        ])));

        // Tracking/watermark
        $track = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('sub_application_id', $id)
            ->first();
        $printedCount = $track ? ((int) ($track->printed_count ?? 0)) : 0;
        $watermarkText = $printedCount > 0 ? 'COPY OF ORIGINAL' : 'ORIGINAL';

        // Units vs SUA identification
        $isSUA = isset($sub->unit_type) && strtoupper($sub->unit_type) === 'SUA';

        // Enhanced payment status logic for sub-applications
        $subPaymentStatus = [
            'application_fee_paid' => !empty($sub->application_fee) && $sub->application_fee > 0,
            'processing_fee_paid' => !empty($sub->processing_fee) && $sub->processing_fee > 0,
            'site_plan_fee_paid' => !empty($sub->site_plan_fee) && $sub->site_plan_fee > 0,
        ];

        // Parse documents JSON from sub-application or fall back to mother application
        $subDocumentsData = [];
        if (!empty($sub->documents)) {
            $subDocumentsData = json_decode($sub->documents, true) ?? [];
        } elseif (!empty($mother->documents ?? null)) {
            $subDocumentsData = json_decode($mother->documents, true) ?? [];
        }

        // Define documents list for sub-applications with status - using same structure as mother application
        $subDocumentsWithStatus = [
            'Application Letter *' => isset($subDocumentsData['application_letter']) && !empty($subDocumentsData['application_letter']['path']),
            'Building Plan *' => isset($subDocumentsData['building_plan']) && !empty($subDocumentsData['building_plan']['path']),
            'Architectural Design' => isset($subDocumentsData['architectural_design']) && !empty($subDocumentsData['architectural_design']['path']),
            'Ownership Document' => isset($subDocumentsData['ownership_document']) && !empty($subDocumentsData['ownership_document']['path']),
            'Site Plan (Survey)' => isset($subDocumentsData['survey_plan']) && !empty($subDocumentsData['survey_plan']['path']),
        ];

        return view('sectionaltitling.print.acknowledgement_sub', [
            // IDs, type, and labels
            'applicationId' => $sub->id,
            'applicantType' => $applicantType,
            'applicantName' => $applicantName,
            'applicantEmail' => $sub->owner_email ?? ($mother->owner_email ?? '-'),
            'applicantPhone' => $sub->phone_number ?? ($mother->phone_number ?? '-'),
            'applicantAddress' => ($sub->address ?? null) ?: ($mother->address ?? ($mother->owner_address ?? '-')),

            'residenceType' => $sub->residenceType ?? ($sub->residence_type ?? ($mother->residenceType ?? ($mother->residence_type ?? '-'))),
            // Sub specifics (Unit Detail mapping per request)
            'unitType' => $sub->unit_type ?? 'Parented Unit',
            'unitNumber' => $sub->unit_number ?? ($sub->unit_no ?? '-'),
            'blockNumber' => $sub->block_number ?? ($sub->block_no ?? '-'),
            'unitSize' => $sub->unit_size ?? '-',
            'unitFileNumber' => $sub->fileno ?? '-',
            // Prefer subapplications.np_fileno if present, otherwise fallback to mother_applications.np_fileno
            'npFileNumber' => ($sub->np_fileno ?? null) ?: ($mother->np_fileno ?? '-'),

            // Property Address mapping (Unit/SUA fields)
            'unitLGA' => $sub->unit_lga ?? ($sub->property_lga ?? ($mother->property_lga ?? '-')),
            'unitDistrict' => $sub->unit_district ?? ($sub->property_district ?? ($mother->property_district ?? '-')),
            'propertyLocation' => $sub->property_location ?? ($mother->property_location ?? $propertyFullAddress ?? '-'),

            'applicationFee' => $sub->application_fee ?? null,
            'processingFee' => $sub->processing_fee ?? null,
            'sitePlanFee' => $sub->site_plan_fee ?? null,
            'totalFee' => null, // optional compute if needed later
            'receiptNumber' => $sub->receipt_number ?? null,
            'paymentDate' => $sub->payment_date ?? null,

            // Enhanced status for sub-applications
            'paymentStatus' => $subPaymentStatus,
            'documentsWithStatus' => $subDocumentsWithStatus,

            'printedCount' => $printedCount,
            'watermarkText' => $watermarkText,
        ]);
    }

    /**
     * Mark sub-application acknowledgement as printed.
     */
    public function markSubAcknowledgementPrinted($id)
    {
        $track = DB::connection('sqlsrv')
            ->table('dbo.st_acknowledgement_tracking')
            ->where('sub_application_id', $id)
            ->first();

        if (!$track) {
            // Best-effort create if missing
            $sub = DB::connection('sqlsrv')->table('dbo.subapplications')->where('id', $id)->first();
            DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')->insert([
                'application_id' => $sub->main_application_id ?? null,
                'sub_application_id' => $id,
                'generated_at' => now(),
                'generated_by_user_id' => Auth::id(),
                'generated_by_user_name' => optional(Auth::user())->name,
                'printed_count' => 0,
            ]);
            $track = (object) ['printed_count' => 0];
        }

        $now = now();
        $firstPrintedAt = $track->printed_count > 0 ? $track->first_printed_at : $now;

        DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')
            ->where('sub_application_id', $id)
            ->update([
                'printed_count' => DB::raw('ISNULL(printed_count,0) + 1'),
                'first_printed_at' => $firstPrintedAt,
                'last_printed_at' => $now,
                'last_printed_by_user_id' => Auth::id(),
                'last_printed_by_user_name' => optional(Auth::user())->name,
            ]);

        return response()->json(['success' => true]);
    }

    public function saveCofoDetails(Request $request)
    {
        try {
            // Log the incoming request for debugging
            \Log::info('CofO Details Save Request', [
                'method' => $request->method(),
                'url' => $request->url(),
                'data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Validate the request
            $validatedData = $request->validate([
                'application_id' => 'required|integer',
                'transaction_type' => 'required|string',
                'cofo_no' => 'nullable|string',
                'certificate_date' => 'nullable|string',
                'serial_no' => 'nullable|string',
                'page_no' => 'nullable|string',
                'volume_no' => 'nullable|string',
                'transaction_date' => 'nullable|string',
                'transaction_time' => 'nullable|string',
                'land_use' => 'nullable|string',
                'period' => 'nullable|integer',
                'period_unit' => 'nullable|string|in:years,months,weeks',
                'grantor' => 'required|string',
                'grantee' => 'required|string',
                'property_description' => 'nullable|string',
                'reg_no' => 'nullable|string'
            ]);

            \Log::info('CofO Details Validation Passed', ['validated_data' => $validatedData]);

            // Get the mother application data
            $motherApplication = DB::connection('sqlsrv')
                ->table('dbo.mother_applications')
                ->where('id', $request->application_id)
                ->first();

            if (!$motherApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ]);
            }

            // Check for duplicates based on application ID
            $existingRecord = DB::connection('sqlsrv')
                ->table('dbo.CofO')
                ->where('mlsFNo', $motherApplication->fileno)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'CofO details already exist for this application'
                ]);
            }

            // Prepare data for insertion
            $cofoData = [
                'mlsFNo' => $motherApplication->fileno,
                'kangisFileNo' => $motherApplication->fileno, // Using fileno as kangisFileNo
                'NewKANGISFileno' => $motherApplication->np_fileno ?? $motherApplication->fileno,
                'title_type' => 'Certificate of Occupancy', // Default title type
                'transaction_type' => $request->transaction_type,
                'cofo_no' => $request->cofo_no,
                'transaction_date' => $request->transaction_date,
                'transaction_time' => $request->transaction_time,
                'serialNo' => $request->serial_no,
                'pageNo' => $request->page_no,
                'volumeNo' => $request->volume_no,
                'regNo' => $request->reg_no, // This comes from the preview field
                'instrument_type' => 'Certificate of Occupancy', // Fixed as Certificate of Occupancy
                'period' => $request->period,
                'period_unit' => $request->period_unit,
                'land_use' => $request->land_use ?: $motherApplication->land_use, // Use form data or fallback to mother application
                'Assignor' => null, // Will be set based on transaction type
                'Assignee' => null,
                'Mortgagor' => null,
                'Mortgagee' => null,
                'Surrenderor' => null,
                'Surrenderee' => null,
                'Lessor' => null,
                'Lessee' => null,
                'Grantor' => $request->grantor,
                'Grantee' => $request->grantee,
                'property_description' => $request->property_description ?: (isset($motherApplication->property_description) ? $motherApplication->property_description : ''),
                'location' => '', // Removed location field
                'plot_no' => $motherApplication->property_house_no ?: $motherApplication->property_plot_no,
                'lgsaOrCity' => $motherApplication->property_lga,
                'layout' => '', // Removed layout field
                'schedule' => '', // Removed schedule field
                'application_id' => $request->application_id,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Set transaction-specific parties based on transaction type
            switch (strtolower($request->transaction_type)) {
                case 'assignment':
                    $cofoData['Assignor'] = $request->grantor;
                    $cofoData['Assignee'] = $request->grantee;
                    break;
                case 'mortgage':
                    $cofoData['Mortgagor'] = $request->grantee; // The one giving the mortgage
                    $cofoData['Mortgagee'] = $request->grantor; // The one receiving the mortgage
                    break;
                case 'lease':
                    $cofoData['Lessor'] = $request->grantor;
                    $cofoData['Lessee'] = $request->grantee;
                    break;
                case 'surrender':
                    $cofoData['Surrenderor'] = $request->grantee;
                    $cofoData['Surrenderee'] = $request->grantor;
                    break;
                case 'certificate of occupancy':
                    // For Certificate of Occupancy, use Grantor and Grantee
                    $cofoData['Grantor'] = $request->grantor;
                    $cofoData['Grantee'] = $request->grantee;
                    break;
            }

            // Log the data being inserted
            \Log::info('CofO Data to be inserted', ['cofo_data' => $cofoData]);

            // Insert into CofO table
            $insertResult = DB::connection('sqlsrv')->table('dbo.CofO')->insert($cofoData);

            \Log::info('CofO Insert Result', ['result' => $insertResult]);

            return response()->json([
                'success' => true,
                'message' => 'CofO details saved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('CofO Validation Error', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('CofO Save Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving CofO details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteSubapplication(Request $request, $id)
    {
        try {
            // Soft delete: mark the sub-application as deleted
            $affected = DB::connection('sqlsrv')
                ->table('dbo.subapplications')
                ->where('id', $id)
                ->update([
                    'is_deleted' => 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-application not found or already deleted.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sub-application deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting sub-application: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function conveyance(Request $request)
    {
        $PageTitle = 'Final Conveyance';
        $PageDescription = 'Generate Final Conveyance for Sectional Titling Applications';

        $finalConveyanceLatest = DB::connection('sqlsrv')->table('dbo.final_conveyance')
            ->select('application_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('application_id');

        $PrimaryApplications = DB::connection('sqlsrv')->table('dbo.mother_applications as ma')
            ->leftJoin('dbo.memos as m', function ($join) {
                $join->on('ma.id', '=', 'm.application_id')
                    ->where('m.memo_type', '=', 'primary');
            })
            ->leftJoinSub($finalConveyanceLatest, 'fc_latest', function ($join) {
                $join->on('ma.id', '=', 'fc_latest.application_id');
            })
            ->leftJoin('dbo.final_conveyance as fc', function ($join) {
                $join->on('fc.id', '=', 'fc_latest.latest_id');
            })
            ->select(
                'ma.*',
                DB::raw('CASE WHEN m.application_id IS NOT NULL THEN \'Generated\' ELSE \'Not Generated\' END as st_memo_status'),
                'm.planner_recommendation as st_memo_comments',
                'fc.id as final_conveyance_id',
                'fc.status as final_conveyance_status',
                'fc.generated_date as fc_generated_date'
            )
            ->orderBy('ma.id', 'desc')
            ->get();

        // --- Start of Fix: Fetch Latest Uploads ---
        $primaryIds = $PrimaryApplications->pluck('id')->unique()->values();

        $latestUploadsMap = [];
        if ($primaryIds->isNotEmpty()) {
            $latestUploads = DB::connection('sqlsrv')
                ->table('dbo.final_conveyance_uploads')
                ->whereIn('application_id', $primaryIds)
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', 'active');
                })
                ->orderBy('application_id')
                ->orderByDesc('id') // Ensure we get the latest if multiples exist
                ->get();

            // Group by application_id and take the first (latest due to ordering)
            foreach ($latestUploads as $upload) {
                if (!isset($latestUploadsMap[$upload->application_id])) {
                    $latestUploadsMap[$upload->application_id] = $upload;
                }
            }
        }

        $PrimaryApplications = $PrimaryApplications->map(function ($primary) use ($latestUploadsMap) {
            $primaryId = (int) ($primary->id ?? 0);

            // Map upload info
            if (isset($latestUploadsMap[$primaryId])) {
                $upload = $latestUploadsMap[$primaryId];
                $primary->latest_upload_id = $upload->id;
                $primary->latest_upload_filename = $upload->original_filename;
                $primary->latest_upload_at = $upload->uploaded_at;
            } else {
                $primary->latest_upload_id = null;
                $primary->latest_upload_filename = null;
                $primary->latest_upload_at = null;
            }

            return $primary;
        });
        // --- End of Fix ---

        return view('sectionaltitling.conveyance', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

}


// sort all  record by the latest created record first
