<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class CofoController extends Controller
{
    private function getAvailableSecurityPapers()
    {
        return DB::connection('sqlsrv')->table('st_rofo_security_paper_codes')
            ->where('is_used', false)
            ->orderBy('paper_code', 'asc')
            ->get();
    }
    private function getApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    public function CertificateOfOccupancy()
    {
        $PageTitle = 'ST Certificate of Occupancy';
        $PageDescription = '';

        $connection = DB::connection('sqlsrv');

        $primaryApplications = $connection
            ->table('mother_applications as m')
            ->where('m.planning_recommendation_status', 'Approved')
            ->where('m.application_status', 'Approved')
            ->where(function ($query) {
                $query->whereNull('m.is_deleted')
                    ->orWhere('m.is_deleted', 0);
            })
            ->select(
                'm.id',
                'm.fileno',
                'm.np_fileno',
                'm.scheme_no',
                'm.land_use',
                'm.property_lga',
                'm.property_district',
                'm.property_state',
                'm.property_plot_no',
                'm.property_house_no',
                'm.applicant_type',
                'm.applicant_title',
                'm.first_name',
                'm.middle_name',
                'm.surname',
                'm.corporate_name',
                'm.multiple_owners_names',
                'm.sys_date',
                'm.created_at'
            )
            ->orderByDesc('m.sys_date')
            ->get();

        $primaryIds = $primaryApplications
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $puaSummaries = collect();
        $primaryMemoLookup = [];

        if ($primaryIds->isNotEmpty()) {
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
                ->leftJoin('rofo as r', function ($join) {
                    $join->on('r.sub_application_id', '=', 's.id')
                        ->where(function ($condition) {
                            $condition->whereNull('r.active')
                                ->orWhereIn('r.active', [0, 1]);
                        });
                })
                ->leftJoin('st_cofo as c', function ($join) {
                    $join->on('c.sub_application_id', '=', 's.id')
                        ->where('c.is_active', 1);
                })
                ->whereIn('s.main_application_id', $primaryIds)
                ->where(function ($query) {
                    $query->whereNull('s.is_deleted')
                        ->orWhere('s.is_deleted', 0);
                })
                ->select(
                    's.id',
                    's.main_application_id',
                    's.is_sua_unit',
                    'r.id as rofo_id',
                    'r.rofo_no',
                    'r.active as rofo_active',
                    'c.id as cofo_id'
                )
                ->get();

            $unitIds = $unitRecords
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            $memoUnitIds = collect();

            if ($unitIds->isNotEmpty() && Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
                $memoUnitIds = $connection
                    ->table('unit_st_memo_uploads')
                    ->whereIn('unit_application_id', $unitIds)
                    ->pluck('unit_application_id')
                    ->map(fn($id) => (int) $id)
                    ->unique();
            }

            if ($memoUnitIds->isEmpty() && $unitIds->isNotEmpty()) {
                $memoUnitIds = $connection
                    ->table('memos')
                    ->whereIn('unit_id', $unitIds)
                    ->whereRaw('LOWER(memo_type) = ?', ['pua'])
                    ->pluck('unit_id')
                    ->map(fn($id) => (int) $id)
                    ->unique();
            }

            $memoLookup = $memoUnitIds
                ->mapWithKeys(fn($id) => [$id => true])
                ->all();

            $summaryData = [];

            foreach ($unitRecords as $record) {
                $primaryId = (int) ($record->main_application_id ?? 0);

                if ($primaryId === 0 || (int) ($record->is_sua_unit ?? 0) === 1) {
                    continue;
                }

                if (!array_key_exists($primaryId, $summaryData)) {
                    $summaryData[$primaryId] = [
                        'total_units' => 0,
                        'memo_generated' => 0,
                        'details_captured' => 0,
                        'rofo_generated' => 0,
                        'cofo_generated' => 0,
                    ];
                }

                $summary =& $summaryData[$primaryId];

                $summary['total_units']++;

                $unitId = (int) ($record->id ?? 0);

                if ($unitId > 0 && isset($memoLookup[$unitId])) {
                    $summary['memo_generated']++;
                }

                if (!empty($record->rofo_id)) {
                    $summary['details_captured']++;
                }

                $rofoActive = (int) ($record->rofo_active ?? 0);

                if (!empty($record->rofo_no) && $rofoActive === 1) {
                    $summary['rofo_generated']++;
                }

                if (!empty($record->cofo_id)) {
                    $summary['cofo_generated']++;
                }
            }

            $puaSummaries = collect($summaryData)->map(function (array $summary, $primaryId) use ($primaryMemoLookup) {
                $totalUnits = max(0, (int) ($summary['total_units'] ?? 0));
                $memoGenerated = max(0, (int) ($summary['memo_generated'] ?? 0));
                $detailsCaptured = max(0, (int) ($summary['details_captured'] ?? 0));
                $rofoGenerated = max(0, (int) ($summary['rofo_generated'] ?? 0));
                $cofoGenerated = max(0, (int) ($summary['cofo_generated'] ?? 0));

                $memoPending = max(0, $totalUnits - $memoGenerated);
                $detailsPending = max(0, $totalUnits - $detailsCaptured);
                $cofoPending = max(0, $totalUnits - $cofoGenerated);

                $primaryMemoGenerated = isset($primaryMemoLookup[$primaryId]);
                $memoPrerequisiteMet = $memoPending === 0 || $primaryMemoGenerated;

                $prerequisitesMet = $totalUnits > 0 && $memoPrerequisiteMet && $detailsPending === 0;
                $rofoDetailsComplete = $totalUnits > 0 && $detailsPending === 0;

                return (object) [
                    'total_units' => $totalUnits,
                    'memo_generated' => $memoGenerated,
                    'memo_pending' => $memoPending,
                    'primary_memo_generated' => $primaryMemoGenerated,
                    'details_captured' => $detailsCaptured,
                    'details_pending' => $detailsPending,
                    'rofo_generated' => $rofoGenerated,
                    'cofo_generated' => $cofoGenerated,
                    'cofo_pending' => $cofoPending,
                    'prerequisites_met' => $prerequisitesMet,
                    'rofo_details_complete' => $rofoDetailsComplete,
                    'cofo_details_entered' => false,
                    'has_units' => $totalUnits > 0,
                ];
            });
        }

        $cofoDetailsByPrimaryId = [];

        if ($primaryApplications->isNotEmpty()) {
            $primaryFileNumbers = $primaryApplications
                ->pluck('fileno')
                ->filter()
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->filter()
                ->unique()
                ->values();

            $primaryNpFileNumbers = $primaryApplications
                ->pluck('np_fileno')
                ->filter()
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->filter()
                ->unique()
                ->values();

            $cofoRecords = collect();

            if ($primaryFileNumbers->isNotEmpty() || $primaryNpFileNumbers->isNotEmpty()) {
                $cofoQuery = $connection
                    ->table('CofO')
                    ->select('mlsFNo', 'kangisFileNo', 'NewKANGISFileno');

                $cofoQuery->where(function ($query) use ($primaryFileNumbers, $primaryNpFileNumbers) {
                    $applied = false;

                    if ($primaryFileNumbers->isNotEmpty()) {
                        $fileNumbers = $primaryFileNumbers->all();
                        $query->whereIn('mlsFNo', $fileNumbers)
                            ->orWhereIn('kangisFileNo', $fileNumbers);
                        $applied = true;
                    }

                    if ($primaryNpFileNumbers->isNotEmpty()) {
                        $npNumbers = $primaryNpFileNumbers->all();
                        if ($applied) {
                            $query->orWhereIn('NewKANGISFileno', $npNumbers);
                        } else {
                            $query->whereIn('NewKANGISFileno', $npNumbers);
                        }
                    }
                });

                $cofoRecords = $cofoQuery->get();
            }

            $cofoLookup = [];

            foreach ($cofoRecords as $record) {
                foreach (['mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $column) {
                    $value = trim((string) ($record->{$column} ?? ''));
                    if ($value !== '') {
                        $cofoLookup[$value] = true;
                    }
                }
            }

            foreach ($primaryApplications as $primary) {
                $fileno = trim((string) ($primary->fileno ?? ''));
                $npFileno = trim((string) ($primary->np_fileno ?? ''));
                $cofoDetailsByPrimaryId[(int) ($primary->id ?? 0)] = (
                    ($fileno !== '' && isset($cofoLookup[$fileno])) ||
                    ($npFileno !== '' && isset($cofoLookup[$npFileno]))
                );
            }
        }

        $puaPrimaryApplications = $primaryApplications->map(function ($primary) use ($puaSummaries, $cofoDetailsByPrimaryId) {
            $summary = $puaSummaries->get((int) ($primary->id ?? 0), (object) [
                'total_units' => 0,
                'memo_generated' => 0,
                'memo_pending' => 0,
                'details_captured' => 0,
                'details_pending' => 0,
                'rofo_generated' => 0,
                'cofo_generated' => 0,
                'cofo_pending' => 0,
                'prerequisites_met' => false,
                'rofo_details_complete' => false,
                'cofo_details_entered' => false,
                'has_units' => false,
            ]);

            $primaryId = (int) ($primary->id ?? 0);
            $summary->cofo_details_entered = $cofoDetailsByPrimaryId[$primaryId] ?? false;

            $primary->owner_name = $this->normalizePersonName(formatApplicantDisplayName(
                $primary->applicant_type ?? '',
                $primary->applicant_title ?? '',
                $primary->first_name ?? '',
                $primary->middle_name ?? '',
                $primary->surname ?? '',
                $primary->corporate_name ?? '',
                $primary->multiple_owners_names ?? ''
            )) ?? 'N/A';

            $primary->pua_summary = $summary;

            return $primary;
        })->filter(function ($primary) {
            return ($primary->pua_summary->total_units ?? 0) > 0;
        })->values();

        $puaPrimaryStats = [
            'total' => $puaPrimaryApplications->count(),
            'ready' => $puaPrimaryApplications->filter(function ($primary) {
                $summary = $primary->pua_summary;
                return ($summary->prerequisites_met ?? false) && ($summary->cofo_pending ?? 0) > 0;
            })->count(),
            'completed' => $puaPrimaryApplications->filter(function ($primary) {
                $summary = $primary->pua_summary;
                return ($summary->total_units ?? 0) > 0 && ($summary->cofo_pending ?? 0) === 0 && ($summary->cofo_generated ?? 0) > 0;
            })->count(),
        ];

        $puaPrimaryStats['pending'] = $puaPrimaryApplications->filter(function ($primary) {
            return ($primary->pua_summary->cofo_pending ?? 0) > 0;
        })->count();

        $suaUnitApplications = $connection
            ->table('subapplications as sa')
            ->leftJoin('mother_applications as m', 'sa.main_application_id', '=', 'm.id')
            ->leftJoin('rofo as r', function ($join) {
                $join->on('sa.id', '=', 'r.sub_application_id')
                    ->where(function ($condition) {
                        $condition->whereNull('r.active')
                            ->orWhereIn('r.active', [0, 1]);
                    });
            })
            ->leftJoin('st_cofo as c', function ($join) {
                $join->on('sa.id', '=', 'c.sub_application_id')
                    ->where('c.is_active', 1);
            })
            ->where('sa.is_sua_unit', 1)
            ->where(function ($query) {
                $query->whereNull('sa.is_deleted')
                    ->orWhere('sa.is_deleted', 0);
            })
            ->where(function ($query) {
                $query->whereNull('m.is_deleted')
                    ->orWhere('m.is_deleted', 0);
            })
            ->select(
                'sa.id',
                'sa.fileno',
                'sa.unit_number',
                'sa.applicant_type',
                'sa.applicant_title',
                'sa.first_name',
                'sa.middle_name',
                'sa.surname',
                'sa.corporate_name',
                'sa.multiple_owners_names',
                'sa.land_use as unit_land_use',
                'sa.unit_lga',
                'sa.application_status',
                'sa.planning_recommendation_status',
                'sa.created_at',
                'sa.updated_at',
                'm.land_use as mother_land_use',
                'm.property_lga as mother_property_lga',
                'r.id as rofo_id',
                'r.rofo_no',
                'r.active as rofo_active',
                'r.approval_date as rofo_approval_date',
                'c.id as cofo_id',
                'c.certificate_number',
                'c.created_at as certificate_created_at'
            )
            ->orderByDesc('sa.created_at')
            ->get();

        // Debug: Log SUA query results
        \Log::info('SUA Units Found', [
            'total_sua_units' => $suaUnitApplications->count(),
            'sample_unit' => $suaUnitApplications->first(),
        ]);

        $suaUnitIds = $suaUnitApplications
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $suaMemoUploads = collect();
        if ($suaUnitIds->isNotEmpty() && Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
            $suaMemoUploads = $connection
                ->table('unit_st_memo_uploads')
                ->whereIn('unit_application_id', $suaUnitIds)
                ->select('unit_application_id', 'uploaded_at')
                ->orderByDesc('uploaded_at')
                ->get()
                ->mapWithKeys(function ($record) {
                    return [(int) ($record->unit_application_id ?? 0) => $record];
                });
        }

        $suaMemoFallback = collect();
        if ($suaUnitIds->isNotEmpty()) {
            $memoRecords = $connection
                ->table('memos')
                ->whereIn('unit_id', $suaUnitIds)
                ->whereRaw('LOWER(memo_type) = ?', ['sua'])
                ->select('unit_id', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            if ($memoRecords->isNotEmpty()) {
                $suaMemoFallback = $memoRecords->mapWithKeys(function ($record) {
                    $unitId = (int) ($record->unit_id ?? 0);
                    return [$unitId => $record];
                });
            }
        }

        $suaUnitApplications = $suaUnitApplications->map(function ($application) use ($suaMemoUploads, $suaMemoFallback) {
            $unitId = (int) ($application->id ?? 0);

            $multipleOwners = [];
            if (!empty($application->multiple_owners_names)) {
                $decoded = json_decode($application->multiple_owners_names, true);
                if (is_array($decoded)) {
                    $multipleOwners = collect($decoded)
                        ->map(fn($name) => is_string($name) ? trim($name) : null)
                        ->filter()
                        ->values()
                        ->all();
                }
            }

            $ownerName = '';
            $applicantType = strtolower(trim((string) ($application->applicant_type ?? '')));
            if (!empty($multipleOwners)) {
                $ownerName = implode(', ', array_map([$this, 'normalizePersonName'], $multipleOwners));
            } elseif ($applicantType === 'corporate' && !empty($application->corporate_name)) {
                $ownerName = $this->normalizePersonName(trim((string) $application->corporate_name));
            } else {
                $nameParts = array_filter([
                    trim((string) ($application->applicant_title ?? '')),
                    trim((string) ($application->first_name ?? '')),
                    trim((string) ($application->middle_name ?? '')),
                    trim((string) ($application->surname ?? '')),
                ]);
                $ownerName = $this->normalizePersonName(implode(' ', $nameParts));
            }
            $application->owner_name = $ownerName !== '' && $ownerName !== null ? $ownerName : 'N/A';

            $unitLandUse = trim((string) ($application->unit_land_use ?? ''));
            $motherLandUse = trim((string) ($application->mother_land_use ?? ''));
            $application->land_use = $unitLandUse !== '' ? $unitLandUse : ($motherLandUse !== '' ? $motherLandUse : 'N/A');

            $currentLga = trim((string) ($application->property_lga ?? ''));
            $unitLga = trim((string) ($application->unit_lga ?? ''));
            $motherLga = trim((string) ($application->mother_property_lga ?? ''));
            if ($currentLga === '') {
                if ($unitLga !== '') {
                    $application->property_lga = $unitLga;
                } elseif ($motherLga !== '') {
                    $application->property_lga = $motherLga;
                } else {
                    $application->property_lga = 'N/A';
                }
            }

            $memoUpload = $suaMemoUploads->get($unitId);
            $memoFallback = $suaMemoFallback->get($unitId);
            $memoGenerated = $memoUpload !== null || $memoFallback !== null;
            $application->memo_generated = $memoGenerated;
            $application->memo_generated_at = $memoUpload->uploaded_at ?? $memoFallback->created_at ?? null;

            $application->details_captured = !empty($application->rofo_id);
            $application->rofo_active = (int) ($application->rofo_active ?? 0);
            $application->rofo_generated = !empty($application->rofo_no) && $application->rofo_active === 1;
            $application->rofo_generated_at = $application->rofo_approval_date ?? null;

            $application->certificate_issued = !empty($application->certificate_number);
            $application->certificate_generated_at = $application->certificate_created_at ?? null;

            $application->ready_for_cofo = $memoGenerated && $application->rofo_generated && !$application->certificate_issued;

            $pendingRequirements = [];
            if (!$memoGenerated) {
                $pendingRequirements[] = 'ST Memo';
            }
            if (!$application->rofo_generated) {
                $pendingRequirements[] = 'RoFO';
            }

            if ($application->certificate_issued) {
                $application->progress_status = 'Certificate Issued';
                $application->progress_class = 'badge-issued';
                $application->readiness_message = 'Certificate already issued for this SUA unit.';
            } elseif (empty($pendingRequirements)) {
                $application->progress_status = 'Ready for CofO';
                $application->progress_class = 'badge-approved';
                $application->readiness_message = 'Memo and RoFO prerequisites are satisfied.';
            } else {
                $application->progress_status = 'Prerequisites Pending';
                $application->progress_class = 'badge-blocked';
                $application->readiness_message = 'Pending: ' . implode(' & ', $pendingRequirements) . '.';
            }

            $planningStatus = trim((string) ($application->planning_recommendation_status ?? ''));
            $applicationStatus = trim((string) ($application->application_status ?? ''));

            $application->planning_status_label = $planningStatus !== '' ? $planningStatus : 'N/A';
            $application->application_status_label = $applicationStatus !== '' ? $applicationStatus : 'N/A';

            return $application;
        });

        $suaStats = [
            'total' => $suaUnitApplications->count(),
            'generated' => $suaUnitApplications->where('certificate_issued', true)->count(),
            'pending' => $suaUnitApplications->where('certificate_issued', '!=', true)->count(),
            'ready' => $suaUnitApplications->where('ready_for_cofo', true)->count(),
        ];

        /*
         | The workflow pipeline, shown on both certificate screens.
         |
         | This screen is stage 3 of six, and an officer standing on it should be able to
         | see what comes before and after without opening the other screen. The stage list
         | and the counts come from StCofoPipeline so the two screens cannot disagree.
         |
         | Resolved through the container rather than newed up: the pipeline needs the TDP
         | library, which carries its own configuration.
        */
        $pipeline = app(\App\Services\Cofo\StCofoPipeline::class);

        return view('programmes.certificates', [
            'PageTitle' => $PageTitle,
            'PageDescription' => $PageDescription,
            'puaPrimaryApplications' => $puaPrimaryApplications,
            'suaUnitApplications' => $suaUnitApplications,
            'puaPrimaryStats' => $puaPrimaryStats,
            'suaStats' => $suaStats,
            'availableSecurityPapers' => $this->getAvailableSecurityPapers(),
            'stages' => $pipeline->stages(),
            'stageCounts' => $pipeline->counts(),
            'currentStage' => 'front_page',
        ]);
    }

    /**
     * Normalize person name to proper title case
     */
    private function normalizePersonName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $collapsed = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return (string) Str::of($collapsed)->lower()->title();
    }

    public function showSuaUnits(Request $request)
    {
        $connection = DB::connection('sqlsrv');

        // Get all SUA units with their CoFO status
        $suaUnits = $connection->table('subapplications as sa')
            ->leftJoin('rofo as r', function ($join) {
                $join->on('sa.id', '=', 'r.sub_application_id')
                    ->where(function ($condition) {
                        $condition->whereNull('r.active')
                            ->orWhereIn('r.active', [0, 1]);
                    });
            })
            ->leftJoin('st_cofo as c', function ($join) {
                $join->on('sa.id', '=', 'c.sub_application_id')
                    ->where('c.is_active', 1);
            })
            ->where('sa.is_sua_unit', 1)
            ->where(function ($query) {
                $query->whereNull('sa.is_deleted')
                    ->orWhere('sa.is_deleted', 0);
            })
            ->select(
                'sa.id',
                'sa.fileno',
                'sa.unit_number',
                'sa.applicant_type',
                'sa.applicant_title',
                'sa.first_name',
                'sa.middle_name',
                'sa.surname',
                'sa.corporate_name',
                'sa.multiple_owners_names',
                'sa.land_use as unit_land_use',
                'sa.unit_lga',
                'sa.application_status',
                'sa.planning_recommendation_status',
                'sa.created_at',
                'r.id as rofo_id',
                'r.rofo_no',
                'r.active as rofo_active',
                'r.approval_date as rofo_approval_date',
                'c.id as cofo_id',
                'c.certificate_number',
                'c.created_at as certificate_created_at'
            )
            ->orderByDesc('sa.created_at')
            ->get();

        $unitIds = $suaUnits->pluck('id')->filter()->map(fn($id) => (int) $id)->unique()->values();

        $suaMemoUploads = collect();
        if ($unitIds->isNotEmpty() && Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
            $suaMemoUploads = $connection
                ->table('unit_st_memo_uploads')
                ->whereIn('unit_application_id', $unitIds)
                ->select('unit_application_id', 'uploaded_at')
                ->orderByDesc('uploaded_at')
                ->get()
                ->mapWithKeys(function ($record) {
                    return [(int) ($record->unit_application_id ?? 0) => $record];
                });
        }

        $suaMemoFallback = collect();
        if ($unitIds->isNotEmpty()) {
            $memoRecords = $connection
                ->table('memos')
                ->whereIn('unit_id', $unitIds)
                ->whereRaw('LOWER(memo_type) = ?', ['sua'])
                ->select('unit_id', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            if ($memoRecords->isNotEmpty()) {
                $suaMemoFallback = $memoRecords->mapWithKeys(function ($record) {
                    $unitId = (int) ($record->unit_id ?? 0);
                    return [$unitId => $record];
                });
            }
        }

        $processedUnits = $suaUnits->map(function ($application) use ($suaMemoUploads, $suaMemoFallback) {
            $unitId = (int) ($application->id ?? 0);

            $multipleOwners = [];
            if (!empty($application->multiple_owners_names)) {
                $decoded = json_decode($application->multiple_owners_names, true);
                if (is_array($decoded)) {
                    $multipleOwners = collect($decoded)
                        ->map(fn($name) => is_string($name) ? trim($name) : null)
                        ->filter()
                        ->values()
                        ->all();
                }
            }

            $applicantType = strtolower(trim((string) ($application->applicant_type ?? '')));
            if (!empty($multipleOwners)) {
                $ownerName = implode(', ', array_map([$this, 'normalizePersonName'], $multipleOwners));
            } elseif ($applicantType === 'corporate' && !empty($application->corporate_name)) {
                $ownerName = $this->normalizePersonName(trim((string) $application->corporate_name));
            } else {
                $nameParts = array_filter([
                    trim((string) ($application->applicant_title ?? '')),
                    trim((string) ($application->first_name ?? '')),
                    trim((string) ($application->middle_name ?? '')),
                    trim((string) ($application->surname ?? '')),
                ]);
                $ownerName = $this->normalizePersonName(implode(' ', $nameParts));
            }
            $application->owner_name = $ownerName !== '' && $ownerName !== null ? $ownerName : 'N/A';

            $application->land_use = trim((string) ($application->unit_land_use ?? 'N/A'));

            $unitLga = trim((string) ($application->unit_lga ?? ''));
            $application->property_lga = $unitLga !== '' ? $unitLga : 'N/A';

            $memoUpload = $suaMemoUploads->get($unitId);
            $memoFallback = $suaMemoFallback->get($unitId);
            $memoGenerated = $memoUpload !== null || $memoFallback !== null;
            $application->memo_generated = $memoGenerated;
            $application->memo_generated_at = $memoUpload->uploaded_at ?? $memoFallback->created_at ?? null;

            $application->details_captured = !empty($application->rofo_id);
            $application->rofo_active = (int) ($application->rofo_active ?? 0);
            $application->rofo_generated = !empty($application->rofo_no) && $application->rofo_active === 1;
            $application->rofo_generated_at = $application->rofo_approval_date ?? null;

            $application->certificate_issued = !empty($application->certificate_number);
            $application->certificate_generated_at = $application->certificate_created_at ?? null;

            $application->ready_for_cofo = $memoGenerated && $application->rofo_generated && !$application->certificate_issued;

            $pendingRequirements = [];
            if (!$memoGenerated) {
                $pendingRequirements[] = 'ST Memo';
            }
            if (!$application->rofo_generated) {
                $pendingRequirements[] = 'RoFO';
            }

            if ($application->certificate_issued) {
                $application->progress_status = 'Certificate Issued';
                $application->progress_class = 'badge-issued';
                $application->readiness_message = 'Certificate already issued for this SUA unit.';
            } elseif (empty($pendingRequirements)) {
                $application->progress_status = 'Ready for CofO';
                $application->progress_class = 'badge-approved';
                $application->readiness_message = 'Memo and RoFO prerequisites are satisfied.';
            } else {
                $application->progress_status = 'Prerequisites Pending';
                $application->progress_class = 'badge-blocked';
                $application->readiness_message = 'Pending: ' . implode(' & ', $pendingRequirements) . '.';
            }

            $planningStatus = trim((string) ($application->planning_recommendation_status ?? ''));
            $applicationStatus = trim((string) ($application->application_status ?? ''));

            $application->planning_status_label = $planningStatus !== '' ? $planningStatus : 'N/A';
            $application->application_status_label = $applicationStatus !== '' ? $applicationStatus : 'N/A';

            return $application;
        });

        $stats = [
            'total_units' => $processedUnits->count(),
            'memo_generated' => $processedUnits->where('memo_generated', true)->count(),
            'rofo_generated' => $processedUnits->where('rofo_generated', true)->count(),
            'ready' => $processedUnits->where('ready_for_cofo', true)->count(),
            'generated' => $processedUnits->where('certificate_issued', true)->count(),
            'pending' => $processedUnits->where('certificate_issued', '!=', true)->count(),
        ];

        if ($processedUnits->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'messages' => 'No SUA units found.',
            ], 404);
        }

        return view('programmes.partials.cofo_sua_units_modal', [
            'units' => $processedUnits,
            'stats' => $stats,
        ]);
    }

    /**
     * Generate the next available certificate number
     * 
     * @return string
     */
    private function generateNextCertificateNumber()
    {
        $year = date('Y');
        $lastCofO = DB::connection('sqlsrv')->table('st_cofo')
            ->where('certificate_number', 'like', "ST/COFO/$year/%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = 0;
        if ($lastCofO) {
            $parts = explode('/', $lastCofO->certificate_number);
            $lastNumber = (int) end($parts);
        }
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        return "ST/COFO/$year/$newNumber";
    }

    /**
     * Create or update a CofO record using normalized payload data.
     */
    protected function persistCofORecord(array $data, bool $activateRecord = true): array
    {
        $connection = DB::connection('sqlsrv');

        $subApplicationId = (int) ($data['sub_application_id'] ?? 0);
        if ($subApplicationId <= 0) {
            throw new \InvalidArgumentException('Invalid sub-application identifier provided for CofO generation.');
        }

        $motherApplicationId = (int) ($data['mother_application_id'] ?? 0);
        if ($motherApplicationId <= 0) {
            throw new \InvalidArgumentException('Invalid mother application identifier provided for CofO generation.');
        }

        $startDate = isset($data['start_date']) && $data['start_date'] !== null
            ? Carbon::parse($data['start_date'])
            : now();

        $totalTerm = isset($data['total_term']) ? (int) $data['total_term'] : 40;
        if ($totalTerm <= 0) {
            $totalTerm = 40;
        }

        $remainingTerm = max(0, $totalTerm - (now()->year - $startDate->year));

        $existingCofO = $connection
            ->table('st_cofo')
            ->where('sub_application_id', $subApplicationId)
            ->orderByDesc('id')
            ->first();

        $certificateNumber = $data['certificate_number'] ?? null;
        if (empty($certificateNumber)) {
            if ($existingCofO && !empty($existingCofO->certificate_number)) {
                $certificateNumber = $existingCofO->certificate_number;
            } else {
                $certificateNumber = $this->generateNextCertificateNumber();
            }
        }

        $holderAddress = $data['holder_address'] ?? null;
        if (!empty($holderAddress)) {
            $holderAddress = normalizeLocationText($holderAddress);
        }

        $propertyStreet = $data['property_street_name'] ?? null;
        if (!empty($propertyStreet)) {
            $propertyStreet = normalizeLocationText($propertyStreet);
        }

        $propertyDistrict = $data['property_district'] ?? null;
        if (!empty($propertyDistrict)) {
            $propertyDistrict = normalizeLocationText($propertyDistrict);
        }

        $propertyLga = $data['property_lga'] ?? null;
        if (!empty($propertyLga)) {
            $propertyLga = normalizeLocationText($propertyLga);
        }

        $propertyState = $data['property_state'] ?? 'Kano';
        if (!empty($propertyState)) {
            $propertyState = normalizeLocationText($propertyState);
        }

        $issuedDateInput = $data['issued_date'] ?? null;
        if ($issuedDateInput instanceof Carbon) {
            $issuedDate = $issuedDateInput;
        } elseif (!empty($issuedDateInput)) {
            $issuedDate = Carbon::parse($issuedDateInput);
        } elseif ($activateRecord) {
            $issuedDate = now();
        } elseif ($existingCofO && !empty($existingCofO->issued_date)) {
            $issuedDate = Carbon::parse($existingCofO->issued_date);
        } else {
            $issuedDate = null;
        }

        $payload = [
            'file_no' => $data['file_no'] ?? null,
            'sub_application_id' => $subApplicationId,
            'mother_application_id' => $motherApplicationId,
            'plot_no' => $data['plot_no'] ?? null,
            'block_no' => $data['block_no'] ?? null,
            'floor_no' => $data['floor_no'] ?? null,
            'flat_no' => $data['flat_no'] ?? null,
            'holder_name' => $data['holder_name'] ?? null,
            'holder_address' => $holderAddress,
            'certificate_number' => $certificateNumber,
            'land_use' => $data['land_use'] ?? null,
            'start_date' => $startDate->format('Y-m-d'),
            'total_term' => $totalTerm,
            'remaining_term' => $remainingTerm,
            'property_house_no' => $data['property_house_no'] ?? null,
            'property_street_name' => $propertyStreet,
            'property_district' => $propertyDistrict,
            'property_lga' => $propertyLga,
            'property_state' => $propertyState,
            'issued_date' => $issuedDate ? $issuedDate->format('Y-m-d H:i:s') : null,
            'signed_by' => $data['signed_by'] ?? null,
            'signed_title' => $data['signed_title'] ?? 'Honorable Commissioner of Land and Physical Planning',
            'is_active' => $activateRecord ? 1 : ($existingCofO ? (int) ($existingCofO->is_active ?? 0) : 0),
            'updated_at' => now(),
        ];

        if ($existingCofO) {
            $connection
                ->table('st_cofo')
                ->where('id', $existingCofO->id)
                ->update($payload);

            $cofoId = $existingCofO->id;
        } else {
            $payload['created_at'] = now();
            $payload['created_by'] = Auth::id();

            $cofoId = $connection
                ->table('st_cofo')
                ->insertGetId($payload);
        }

        return [
            'cofo_id' => $cofoId,
            'certificate_number' => $certificateNumber,
            'activated' => (int) $payload['is_active'] === 1,
            'existing_record' => (bool) $existingCofO,
        ];
    }

    // Generate CofO form
    public function generateCofO(Request $request, $id)
    {
        $PageTitle = 'Generate Certificate of Occupancy';
        $PageDescription = 'Create a new Certificate of Occupancy for sectional title';
        $isModal = $request->boolean('modal');
        $expectsJson = $request->ajax() || $request->wantsJson() || $isModal;

        try {
            // Fetch the subapplication details with mother application data
            $application = DB::connection('sqlsrv')->table('subapplications')
                ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                ->where('subapplications.id', $id)
                ->select(
                    'subapplications.*',
                    'mother_applications.id as mother_id',
                    'mother_applications.property_house_no',
                    'mother_applications.property_plot_no',
                    'mother_applications.property_street_name',
                    'mother_applications.property_district',
                    'mother_applications.property_lga',
                    'mother_applications.property_state',
                    'mother_applications.land_use as mother_land_use'
                )
                ->first();

            if (!$application) {
                if ($expectsJson) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Application not found.',
                    ], 404);
                }

                return back()->with('error', 'Application not found');
            }

            // Normalize location-related fields for consistent casing
            if (!empty($application->address ?? null)) {
                $application->address = normalizeLocationText($application->address);
            }
            $locationFields = [
                'property_location',
                'property_street_name',
                'property_district',
                'property_lga',
                'property_state',
            ];
            foreach ($locationFields as $field) {
                if (!empty($application->{$field} ?? null)) {
                    $application->{$field} = normalizeLocationText($application->{$field});
                }
            }

            if (empty($application->property_location)) {
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
                    $application->property_location = normalizeLocationText(implode(', ', $locationParts));
                }
            }


            if (!$isModal) {
                // Check prerequisites before allowing CofO generation when rendering the full page
                $validationErrors = [];

                // Debug: Log the application details
                \Log::info('Checking prerequisites for application ID: ' . $application->id);
                \Log::info('Main application ID: ' . ($application->main_application_id ?? 'NULL'));
                \Log::info('Mother ID: ' . ($application->mother_id ?? 'NULL'));

                // Check if ST Memo has been generated (ST Memo is linked to the main application)
                $memoApplicationId = $application->main_application_id ?? $application->mother_id;
                \Log::info('Looking for memo with application_id: ' . $memoApplicationId);

                $hasSTMemo = DB::connection('sqlsrv')->table('memos')
                    ->where('application_id', $memoApplicationId)
                    ->exists();

                \Log::info('ST Memo exists: ' . ($hasSTMemo ? 'YES' : 'NO'));

                if (!$hasSTMemo) {
                    $validationErrors[] = 'ST Memo has not been generated for this application (Main App ID: ' . $memoApplicationId . ')';
                }

                // Check if RofO has been generated (RofO is linked to the sub application)
                \Log::info('Looking for RofO with sub_application_id: ' . $application->id);

                $hasRofo = DB::connection('sqlsrv')->table('rofo')
                    ->where('sub_application_id', $application->id)
                    ->exists();

                \Log::info('RofO exists: ' . ($hasRofo ? 'YES' : 'NO'));

                if (!$hasRofo) {
                    $validationErrors[] = 'RofO (Right of Occupancy) has not been generated for this application (Sub App ID: ' . $application->id . ')';
                }

                /* 
                // Prerequisites relaxed as requested for PuA
                if (!empty($validationErrors)) {
                    $errorMessage = 'Cannot generate Certificate of Occupancy. The following prerequisites are missing:';
                    foreach ($validationErrors as $error) {
                        $errorMessage .= "\n• " . $error;
                    }
                    $errorMessage .= "\n\nPlease ensure all prerequisites are completed before generating the CofO.";

                    \Log::error('Validation failed: ' . $errorMessage);

                    if ($expectsJson) {
                        return response()->json([
                            'success' => false,
                            'message' => $errorMessage,
                        ], 422);
                    }

                    return back()->with('error', $errorMessage);
                }
                */

                \Log::info('All prerequisites met, proceeding with CofO generation');
            } else {
                \Log::info('Skipping prerequisite enforcement for modal CofO capture', [
                    'sub_application_id' => $application->id,
                ]);
            }

            // Process owner names based on applicant type
            $applicantType = strtolower(trim($application->applicant_type ?? ''));

            if ($applicantType === 'multiple' && !empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif ($applicantType === 'corporate' && !empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } elseif ($applicantType === 'individual' || empty($applicantType)) {
                $nameParts = array_filter([
                    trim($application->applicant_title ?? ''),
                    trim($application->first_name ?? ''),
                    trim($application->surname ?? '')
                ]);
                $application->owner_name = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
            } else {
                $application->owner_name = 'N/A';
            }

            $unitLandUse = trim((string) ($application->land_use ?? ''));
            $motherLandUse = trim((string) ($application->mother_land_use ?? ''));
            if ($unitLandUse !== '') {
                $application->land_use = $unitLandUse;
            } elseif ($motherLandUse !== '') {
                $application->land_use = $motherLandUse;
            } else {
                $application->land_use = 'N/A';
            }

            // Check if a CofO already exists for this application
            $existingCofO = DB::connection('sqlsrv')->table('st_cofo')
                ->where('sub_application_id', $id)
                ->where('is_active', 1)
                ->first();

            $certificateNumber = $existingCofO->certificate_number ?? null;
            $nextAvailableCertificateNumber = $certificateNumber ? null : $this->generateNextCertificateNumber();

            $startDate = $existingCofO && $existingCofO->start_date
                ? Carbon::parse($existingCofO->start_date)->format('Y-m-d')
                : now()->format('Y-m-d');

            $totalYears = $existingCofO->total_term ?? 40;

            $viewData = [
                'application' => $application,
                'startDate' => $startDate,
                'totalYears' => $totalYears,
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription,
                'certificateNumber' => $certificateNumber,
                'nextAvailableCertificateNumber' => $nextAvailableCertificateNumber,
                'existingCofO' => $existingCofO,
                'isModal' => $isModal,
            ];

            if ($expectsJson) {
                return response()->json([
                    'success' => true,
                    'html' => view('programmes.partials.cofo_form', $viewData)->render(),
                    'meta' => [
                        'cofo_exists' => (bool) $existingCofO,
                        'certificate_number' => $certificateNumber,
                        'start_date' => $existingCofO->start_date ?? null,
                        'total_term' => $existingCofO->total_term ?? null,
                    ],
                ]);
            }

            return view('programmes.generate_cofo', $viewData);

        } catch (\Exception $e) {
            \Log::error('Error generating CofO form: ' . $e->getMessage());
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to load the CofO card at the moment.',
                ], 500);
            }

            return back()->with('error', 'An error occurred while generating the Certificate of Occupancy form');
        }
    }

    // Save CofO to database
    public function saveCofO(Request $request)
    {
        $isModal = $request->boolean('modal');
        $expectsJson = $request->ajax() || $request->wantsJson() || $isModal;

        try {
            $validated = $request->validate([
                'file_no' => 'required|string|max:50',
                'sub_application_id' => 'required|integer',
                'mother_application_id' => 'required|integer',
                'holder_name' => 'required|string|max:255',
                'holder_address' => 'required|string',
                'land_use' => 'required|string|max:100',
                'start_date' => 'required|date',
                'total_term' => 'required|integer|min:1|max:99',
                'signed_by' => 'nullable|string|max:255',
                'signed_title' => 'required|string|max:255',
            ]);
        } catch (ValidationException $exception) {
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            throw $exception;
        }

        try {
            $payloadData = [
                'file_no' => $validated['file_no'],
                'sub_application_id' => (int) $validated['sub_application_id'],
                'mother_application_id' => (int) $validated['mother_application_id'],
                'plot_no' => $request->plot_no,
                'block_no' => $request->block_no,
                'floor_no' => $request->floor_no,
                'flat_no' => $request->flat_no,
                'holder_name' => $validated['holder_name'],
                'holder_address' => $request->holder_address,
                'land_use' => $validated['land_use'],
                'start_date' => $validated['start_date'],
                'total_term' => (int) $validated['total_term'],
                'property_house_no' => $request->property_house_no,
                'property_street_name' => $request->property_street_name,
                'property_district' => $request->property_district,
                'property_lga' => $request->property_lga,
                'property_state' => $request->property_state ?? 'Kano',
                'signed_by' => $request->filled('signed_by') ? $request->signed_by : null,
                'signed_title' => $validated['signed_title'],
                'issued_date' => $isModal ? null : now(),
            ];

            $result = $this->persistCofORecord($payloadData, !$isModal);

            $successMessage = $isModal ? 'CofO details saved successfully.' : 'CofO Front Page Generated Successfully';

            if ($expectsJson) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'data' => [
                        'certificate_number' => $result['certificate_number'],
                        'start_date' => $validated['start_date'],
                        'total_term' => $validated['total_term'],
                        'cofo_id' => $result['cofo_id'],
                    ],
                ]);
            }

            return redirect()->route('programmes.view_cofo', $validated['sub_application_id'])
                ->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Error saving CofO: ' . $e->getMessage());
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while saving the CofO Front Page.',
                ], 500);
            }

            return back()->with('error', 'An error occurred while saving the CofO Front Page: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function batchGenerateCofO(Request $request, int $primaryId)
    {
        $expectsJson = $request->ajax() || $request->wantsJson() || true;

        $connection = DB::connection('sqlsrv');

        try {
            $primary = $connection
                ->table('dbo.mother_applications')
                ->where('id', $primaryId)
                ->first();

            if (!$primary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Primary application not found.',
                ], 404);
            }

            $subApplications = $connection
                ->table('dbo.subapplications as s')
                ->leftJoin('dbo.mother_applications as m', 'm.id', '=', 's.main_application_id')
                ->leftJoin('dbo.st_cofo as c', function ($join) {
                    $join->on('c.sub_application_id', '=', 's.id')
                        ->where('c.is_active', 1);
                })
                ->where('s.main_application_id', $primaryId)
                ->where(function ($query) {
                    $query->whereNull('s.is_deleted')
                        ->orWhere('s.is_deleted', 0);
                })
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
                    's.address',
                    's.property_location as unit_property_location',
                    's.land_use as unit_land_use',
                    's.is_sua_unit',
                    'm.land_use as mother_land_use',
                    'm.property_plot_no',
                    'm.property_house_no',
                    'm.property_street_name',
                    'm.property_district',
                    'm.property_lga',
                    'm.property_state',
                    'c.id as cofo_id',
                    'c.certificate_number'
                )
                ->orderBy('s.unit_number')
                ->orderBy('s.block_number')
                ->orderBy('s.floor_number')
                ->get();

            if ($subApplications->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No PuA units found for this primary application.',
                ], 404);
            }

            $unitIds = $subApplications
                ->pluck('id')
                ->filter()
                ->map(function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->values();

            $memoUnitIds = collect();
            if ($unitIds->isNotEmpty()) {
                if (Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
                    $memoUnitIds = $connection
                        ->table('unit_st_memo_uploads')
                        ->whereIn('unit_application_id', $unitIds)
                        ->pluck('unit_application_id')
                        ->map(function ($id) {
                            return (int) $id;
                        })
                        ->unique();
                }

                if ($memoUnitIds->isEmpty()) {
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
            }

            $memoLookup = $memoUnitIds
                ->mapWithKeys(function ($id) {
                    return [$id => true];
                })
                ->all();

            $rofoRecords = $unitIds->isEmpty()
                ? collect()
                : $connection
                    ->table('dbo.rofo')
                    ->whereIn('sub_application_id', $unitIds)
                    ->where('active', 1)
                    ->select(
                        'id',
                        'sub_application_id',
                        'rofo_no',
                        'term_years',
                        'approval_date',
                        'created_at'
                    )
                    ->get()
                    ->keyBy('sub_application_id');

            $primaryTermYears = $rofoRecords
                ->pluck('term_years')
                ->filter(function ($value) {
                    return $value !== null && $value !== '';
                })
                ->map(function ($value) {
                    return is_numeric($value) ? (int) $value : null;
                })
                ->filter()
                ->first();

            if ($primaryTermYears === null) {
                $primaryTermYears = $connection
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
                }
            }

            if ($primaryTermYears === null) {
                $primaryTermYears = $connection
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
                }
            }

            // Check if Primary Memo exists
            $primaryMemoExists = $connection
                ->table('dbo.memos')
                ->where('application_id', $primaryId)
                ->whereNull('unit_id')
                ->whereIn('memo_type', ['primary', 'PRIMARY'])
                ->exists();

            $primaryPlanningApproved = false;
            if ($primary) {
                 $primaryPlanningApproved = strcasecmp((string) ($primary->planning_recommendation_status ?? ''), 'Approved') === 0;
            }

            $generated = [];
            $skipped = [];

            foreach ($subApplications as $unit) {
                $unitId = (int) ($unit->id ?? 0);
                if ($unitId <= 0) {
                    continue;
                }

                $unitLabel = trim((string) ($unit->fileno ?? '')) !== '' ? trim((string) $unit->fileno) : 'Unit #' . $unitId;

                if ((int) ($unit->is_sua_unit ?? 0) === 1) {
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'Standalone (SUA) units are not included in PuA batch generation.',
                    ];
                    continue;
                }

                // Skip memo check as requested
                /*
                if (!$primaryMemoExists && !isset($memoLookup[$unitId])) {
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'ST Memo not found for this unit.',
                    ];
                    continue;
                }
                */

                $rofo = $rofoRecords->get($unitId);
                if (!$rofo || empty($rofo->id)) {
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'RoFO details have not been captured for this unit.',
                    ];
                    continue;
                }

                if (empty($rofo->rofo_no)) {
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'RoFO has not been generated for this unit.',
                    ];
                    continue;
                }

                if (!empty($unit->cofo_id)) {
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'An active CofO record already exists for this unit.',
                    ];
                    continue;
                }

                $applicationApproved = strcasecmp((string) ($unit->application_status ?? ''), 'Approved') === 0;
                $planningApproved = strcasecmp((string) ($unit->planning_recommendation_status ?? ''), 'Approved') === 0;
                
                // If primary planning is approved, we can bypass unit planning check
                // BUT unit application status must still be Approved
                /*
                if (!$applicationApproved || (!$planningApproved && !$primaryPlanningApproved)) {
                     $reasons = [];
                     if (!$applicationApproved) $reasons[] = 'Unit application not approved';
                     if (!$planningApproved && !$primaryPlanningApproved) $reasons[] = 'Planning recommendation not approved';
                     
                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'Prerequisites not met: ' . implode(', ', $reasons),
                    ];
                    continue;
                }
                */

                $termYears = $rofo->term_years ?? null;
                $termYears = is_numeric($termYears) ? (int) $termYears : null;
                if ($termYears === null) {
                    $termYears = $primaryTermYears ?? 40;
                }
                if ($termYears <= 0) {
                    $termYears = 40;
                }

                $startDateCandidate = $rofo->approval_date ?? $rofo->created_at ?? now();
                try {
                    $startDate = Carbon::parse($startDateCandidate)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $startDate = now()->format('Y-m-d');
                }

                $ownerName = formatApplicantDisplayName(
                    $unit->applicant_type ?? '',
                    $unit->applicant_title ?? '',
                    $unit->first_name ?? '',
                    $unit->middle_name ?? '',
                    $unit->surname ?? '',
                    $unit->corporate_name ?? '',
                    $unit->multiple_owners_names ?? ''
                );

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $fallbackCorporate = trim((string) ($unit->corporate_name ?? ''));
                    if ($fallbackCorporate !== '') {
                        $ownerName = $fallbackCorporate;
                    }
                }

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $ownersList = function_exists('parseApplicantOwnersList')
                        ? parseApplicantOwnersList($unit->multiple_owners_names ?? null)
                        : [];
                    if (!empty($ownersList)) {
                        $ownerName = implode(', ', $ownersList);
                    }
                }

                if ($ownerName === '' || strtoupper($ownerName) === 'N/A') {
                    $ownerName = 'N/A';
                }

                $holderAddress = $unit->address ?? null;
                if (empty($holderAddress)) {
                    $holderAddressParts = array_filter([
                        $unit->property_house_no ?? null,
                        $unit->property_street_name ?? null,
                        $unit->property_district ?? null,
                        $unit->property_lga ?? null,
                    ]);

                    if (!empty($holderAddressParts)) {
                        $holderAddress = implode(', ', $holderAddressParts);
                    }
                }

                if (empty($holderAddress)) {
                    $holderAddress = $primary->property_district ?? 'Kano State';
                }

                $propertyLocation = $unit->unit_property_location;
                if (empty($propertyLocation)) {
                    $locationParts = [];
                    if (!empty($unit->block_number)) {
                        $locationParts[] = 'BLOCK ' . strtoupper(trim((string) $unit->block_number));
                    }
                    if (!empty($unit->floor_number)) {
                        $locationParts[] = 'FLOOR ' . strtoupper(trim((string) $unit->floor_number));
                    }
                    if (!empty($unit->unit_number)) {
                        $locationParts[] = 'UNIT ' . strtoupper(trim((string) $unit->unit_number));
                    }
                    if (!empty($unit->property_plot_no)) {
                        $locationParts[] = 'PLOT ' . strtoupper(trim((string) $unit->property_plot_no));
                    }

                    if (!empty($locationParts)) {
                        $propertyLocation = normalizeLocationText(implode(', ', $locationParts));
                    }
                }

                $landUse = trim((string) ($unit->unit_land_use ?? ''));
                if ($landUse === '') {
                    $landUse = trim((string) ($unit->mother_land_use ?? ''));
                }

                $persistPayload = [
                    'file_no' => $unit->fileno,
                    'sub_application_id' => $unitId,
                    'mother_application_id' => (int) $primaryId,
                    'plot_no' => $unit->property_plot_no,
                    'block_no' => $unit->block_number,
                    'floor_no' => $unit->floor_number,
                    'flat_no' => $unit->unit_number,
                    'holder_name' => $ownerName,
                    'holder_address' => $holderAddress,
                    'land_use' => $landUse !== '' ? $landUse : ($primary->land_use ?? null),
                    'start_date' => $startDate,
                    'total_term' => $termYears,
                    'property_house_no' => $unit->property_house_no,
                    'property_street_name' => $unit->property_street_name,
                    'property_district' => $unit->property_district,
                    'property_lga' => $unit->property_lga,
                    'property_state' => $unit->property_state ?? 'Kano',
                    'signed_by' => null,
                    'signed_title' => 'Honorable Commissioner of Land and Physical Planning',
                    'issued_date' => now(),
                ];

                try {
                    $result = $this->persistCofORecord($persistPayload, true);

                    $generated[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'certificate_number' => $result['certificate_number'],
                    ];
                } catch (\Throwable $unitException) {
                    \Log::error('Failed to generate CofO during batch process', [
                        'primary_id' => $primaryId,
                        'sub_application_id' => $unitId,
                        'message' => $unitException->getMessage(),
                    ]);

                    $skipped[] = [
                        'unit_id' => $unitId,
                        'fileno' => $unitLabel,
                        'reason' => 'Generation failed: ' . $unitException->getMessage(),
                    ];
                }
            }

            $generatedCount = count($generated);
            $skippedCount = count($skipped);

            if ($generatedCount === 0) {
                $message = $skippedCount > 0
                    ? $skipped[0]['reason']
                    : 'No eligible PuA units were found for CofO batch generation.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'generated' => $generated,
                        'skipped' => $skipped,
                    ],
                ], 422);
            }

            $summaryMessage = sprintf(
                'Generated CofO certificates for %d PuA unit%s. %d unit%s skipped.',
                $generatedCount,
                $generatedCount === 1 ? '' : 's',
                $skippedCount,
                $skippedCount === 1 ? '' : 's'
            );

            return response()->json([
                'success' => true,
                'message' => $summaryMessage,
                'data' => [
                    'generated' => $generated,
                    'skipped' => $skipped,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('CofO batch generation failed', [
                'primary_id' => $primaryId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete CofO batch generation at this time.',
            ], 500);
        }
    }

    // View CofO
    public function ViewCofO($id)
    {
        $PageTitle = 'Certificate of Occupancy';
        $PageDescription = 'Sectional Title Certificate';

        try {
            // Check if a CofO record exists for this application
            $cofo = DB::connection('sqlsrv')->table('st_cofo')
                ->where('sub_application_id', $id)
                ->where('is_active', 1)
                ->first();

            // If CofO not found, redirect to generate
            if (!$cofo) {
                // Get application data for redirect
                $application = DB::connection('sqlsrv')->table('subapplications')
                    ->where('id', $id)
                    ->first();

                if (!$application) {
                    return back()->with('error', 'Application not found');
                }

                return redirect()->route('programmes.generate_cofo', $id)
                    ->with('info', 'CofO Front Page has not been generated yet. Please generate it.');
            }

            if (!empty($cofo->holder_address ?? null)) {
                $normalized = normalizeLocationText($cofo->holder_address);
                $cofo->holder_address = $normalized !== '' ? $normalized : $cofo->holder_address;
            }

            $printUrl = route('programmes.print_cofo', $id);

            return view('programmes.view_cofo', compact(
                'cofo',
                'PageTitle',
                'PageDescription',
                'printUrl'
            ));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in ViewCofO: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while fetching certificate data');
        }
    }

    // Print CofO in new tab
    public function PrintCofO($id)
    {
        $PageTitle = 'Certificate of Occupancy - Print';
        $PageDescription = 'Sectional Title Certificate for Printing';

        try {
            // Check if a CofO record exists for this application
            // We search by sub_application_id as passed from the view
            $cofo = DB::connection('sqlsrv')->table('st_cofo as c')
                ->join('subapplications as s', 'c.sub_application_id', '=', 's.id')
                ->where('c.sub_application_id', $id)
                ->where('c.is_active', 1)
                ->select('c.*', 's.passport', 's.multiple_owners_passport')
                ->first();

            // If CofO not found, redirect to generate
            if (!$cofo) {
                return back()->with('error', 'Certificate of Occupancy not found. Please generate it first.');
            }

            return view('programmes.print_cofo_front_page', compact(
                'cofo',
                'PageTitle',
                'PageDescription'
            ));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in PrintCofO: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while preparing certificate for printing');
        }
    }

    // Batch Print CofO for all units of a primary application
    public function PrintCofOBatch($primaryId)
    {
        $PageTitle = 'Batch Certificate of Occupancy - Print';
        $PageDescription = 'Sectional Title Certificates for Printing';

        try {
            $connection = DB::connection('sqlsrv');

            // Fetch all active certificates for units belonging to this primary application
            $certificates = $connection->table('st_cofo as c')
                ->join('subapplications as s', 'c.sub_application_id', '=', 's.id')
                ->where('s.main_application_id', $primaryId)
                ->where('c.is_active', 1)
                ->select('c.*', 's.passport', 's.multiple_owners_passport')
                ->orderBy('s.unit_number')
                ->orderBy('s.id')
                ->get();

            if ($certificates->isEmpty()) {
                return back()->with('error', 'No generated Certificates of Occupancy found for this primary application.');
            }

            return view('programmes.print_cofo_batch', compact(
                'certificates',
                'PageTitle',
                'PageDescription'
            ));
        } catch (\Exception $e) {
            return back()->with('error', 'Error preparing certificates for printing: ' . $e->getMessage());
        }
    }

    public function puaUnitsModal(Request $request, $mainApplicationId)
    {
        $mainApplicationId = (int) $mainApplicationId;
        $payload = $this->buildPuABatchContext($mainApplicationId);

        if (!$payload || empty($payload['mother'])) {
            return response()->json([
                'status' => 'error',
                'messages' => 'Primary application not found.',
            ], 404);
        }

        return view('programmes.partials.cofo_pua_units_modal', [
            'mother' => $payload['mother'],
            'units' => $payload['units'],
            'stats' => $payload['stats'],
            'availableSecurityPapers' => $this->getAvailableSecurityPapers(),
        ]);
    }

    private function buildPuABatchContext(int $mainApplicationId): ?array
    {
        $connection = DB::connection('sqlsrv');

        $mother = $connection->table('mother_applications')
            ->where('id', $mainApplicationId)
            ->first();

        if (!$mother) return null;

        // Owner Name Normalization
        $applicantType = strtolower(trim($mother->applicant_type ?? ''));
        if ($applicantType === 'corporate' && !empty($mother->corporate_name)) {
            $mother->owner_name = trim($mother->corporate_name);
        } elseif ($applicantType === 'multiple' && !empty($mother->multiple_owners_names)) {
            $list = json_decode($mother->multiple_owners_names, true);
            $normalized = collect(is_array($list) ? $list : [])->map(fn($n) => $this->normalizePersonName($n))->filter()->values();
            $mother->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
        } else {
            $nameParts = array_filter([trim($mother->applicant_title ?? ''), trim($mother->first_name ?? ''), trim($mother->surname ?? '')]);
            $mother->owner_name = $this->normalizePersonName(!empty($nameParts) ? implode(' ', $nameParts) : null) ?? 'N/A';
        }

        $mother->property_label = implode(', ', array_filter([trim($mother->property_street_name ?? ''), trim($mother->property_district ?? ''), trim($mother->property_lga ?? '')]));

        $units = $connection->table('subapplications as sa')
            ->leftJoin('rofo', 'sa.id', '=', 'rofo.sub_application_id')
            ->leftJoin('st_cofo', function ($join) {
                $join->on('sa.id', '=', 'st_cofo.sub_application_id')->where('st_cofo.is_active', 1);
            })
            ->where('sa.main_application_id', $mainApplicationId)
            ->select(
                'sa.id', 'sa.fileno', 'sa.unit_number as unit_label', 'sa.scheme_no', 'sa.applicant_type', 'sa.applicant_title', 'sa.first_name', 'sa.surname', 'sa.corporate_name', 'sa.multiple_owners_names',
                'rofo.rofo_no', 'rofo.print_counter', 'rofo.security_paper_code',
                'st_cofo.id as cofo_id'
            )
            ->orderBy('sa.id')
            ->get();

        foreach ($units as $unit) {
            $type = strtolower(trim($unit->applicant_type ?? ''));
            if ($type === 'corporate') $unit->owner_name = $unit->corporate_name;
            elseif ($type === 'multiple') {
                $l = json_decode($unit->multiple_owners_names, true);
                $unit->owner_name = implode(', ', array_map([$this, 'normalizePersonName'], is_array($l) ? $l : []));
            } else {
                $unit->owner_name = $this->normalizePersonName(implode(' ', array_filter([$unit->applicant_title, $unit->first_name, $unit->surname])));
            }
            
            $unit->rofo_generated = !empty($unit->rofo_no);
            $unit->cofo_generated = !empty($unit->cofo_id);
            $unit->has_st_memo = $connection->table('memos')->where('unit_id', $unit->id)->whereIn('memo_type', ['pua', 'PUA'])->exists();
        }

        return [
            'mother' => $mother,
            'units' => $units,
            'stats' => [
                'total_units' => $units->count(),
                'memo_generated' => $units->where('has_st_memo', true)->count(),
                'rofo_generated' => $units->where('rofo_generated', true)->count(),
                'cofo_generated' => $units->where('cofo_generated', true)->count(),
            ]
        ];
    }
}