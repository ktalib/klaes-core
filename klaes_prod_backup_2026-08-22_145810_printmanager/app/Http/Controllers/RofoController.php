<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Services\Pra\RofoPraSyncer;
use App\Services\SecurityPaperCodeService;

class RofoController extends Controller
{
    private function getAvailableSecurityPapers()
    {
        return SecurityPaperCodeService::availableQuery()->get();
    }

    public function assignSecurityPaper(Request $request, $id)
    {
        $request->validate([
            'security_paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $rofo = DB::connection('sqlsrv')->table('rofo')->where('sub_application_id', $id)->first();
        if (!$rofo) {
            return response()->json(['success' => false, 'message' => 'RoFO record not found.'], 404);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Check if paper code is already used
            $paper = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->security_paper_code)
                ->first();

            if (($paper->status ?? null) === 'voided') {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'That security paper was voided (' . SecurityPaperCodeService::label($paper->void_reason ?? null) . ') and cannot be reissued.'], 422);
            }

            if ($paper->is_used) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            // If RoFO already has a paper code, mark the old one as unused
            if ($rofo->security_paper_code) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $rofo->security_paper_code)
                    ->update([
                        'is_used' => false,
                        'assigned_to_type' => null,
                        'assigned_to_id' => null,
                        'assigned_by' => null,
                        'assigned_at' => null,
                    ]);
            }

            // Assign new paper code to rofo
            DB::connection('sqlsrv')->table('rofo')
                ->where('sub_application_id', $id)
                ->update(['security_paper_code' => $request->security_paper_code]);

            // Mark as used in global pool
            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->security_paper_code)
                ->update([
                    'is_used' => true,
                    'assigned_to_type' => 'ST',
                    'assigned_to_id' => $rofo->id,
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);

            // Also update security_codes table as requested (logging/link)
            // We use the same service or direct DB
            $subApp = DB::connection('sqlsrv')->table('subapplications')->where('id', $rofo->sub_application_id)->first();
            
            DB::connection('sqlsrv')->table('security_codes')->insert([
                'code' => 'P-' . $request->security_paper_code, // Prefix to distinguish?
                'security_paper_code' => $request->security_paper_code,
                'used_security_paper_code' => $request->security_paper_code,
                'is_used' => true,
                'assigned_to' => 'ST ROFO',
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'file_number' => $subApp->fileno ?? '',
                'document_id' => $rofo->id,
                'document_type' => 'ST ROFO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function resetSecurityPaper(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(SecurityPaperCodeService::REASONS))],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $rofo = DB::connection('sqlsrv')->table('rofo')->where('sub_application_id', $id)->first();
        if (!$rofo) {
            return response()->json(['success' => false, 'message' => 'RoFO record not found.'], 404);
        }

        if (!$rofo->security_paper_code) {
            return response()->json(['success' => false, 'message' => 'No security paper code assigned to reset.'], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $oldCode = $rofo->security_paper_code;

            SecurityPaperCodeService::release($oldCode, $request->reason, 'ST ROFO', $request->note);

            DB::connection('sqlsrv')->table('rofo')
                ->where('sub_application_id', $id)
                ->update(['security_paper_code' => null]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'          => true,
                'returned_to_pool' => SecurityPaperCodeService::returnsToPool($request->reason),
                'paper_code'       => $oldCode,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to reset security paper code. ' . $e->getMessage()], 500);
        }
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

    public function showPuaUnits(Request $request, $mainApplicationId)
    {
        $mainApplicationId = (int) $mainApplicationId;
        $payload = $this->buildPrimaryBatchContext($mainApplicationId);

        if (!$payload || empty($payload['mother'])) {
            return response()->json([
                'status' => 'error',
                'messages' => 'Primary application not found.',
            ], 404);
        }

        $units = collect($payload['units'] ?? []);

        if ($units->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'messages' => 'No unit applications found for this primary.',
            ], 404);
        }

        return view('programmes.partials.rofo_pua_units_modal', [
            'mother' => $payload['mother'],
            'units' => $units,
            'stats' => $payload['stats'] ?? [
                'total_units' => $units->count(),
                'memo_generated' => 0,
                'rofo_generated' => 0,
            ],
            'availableSecurityPapers' => $this->getAvailableSecurityPapers(),
        ]);
    }

    public function showSuaUnits(Request $request)
    {
        $connection = DB::connection('sqlsrv');

        // Get all SUA units
        $suaUnits = $connection->table('subapplications as sa')
            ->leftJoin('rofo', 'sa.id', '=', 'rofo.sub_application_id')
            ->where('sa.is_sua_unit', 1)
            ->where(function ($query) {
                $query->whereNull('sa.is_deleted')
                    ->orWhere('sa.is_deleted', 0);
            })
            ->select(
                'sa.id',
                'sa.scheme_no',
                'sa.fileno',
                'sa.unit_number',
                'sa.block_number',
                'sa.floor_number',
                'sa.unit_type',
                'sa.unit_lga',
                'sa.is_sua_unit',
                'sa.applicant_type',
                'sa.applicant_title',
                'sa.first_name',
                'sa.surname',
                'sa.corporate_name',
                'sa.multiple_owners_names',
                'sa.land_use',
                'sa.created_at',
                'rofo.rofo_no',
                'rofo.print_counter',
                'rofo.approval_date',
                'rofo.security_paper_code',
                'rofo.active as rofo_active',
                'rofo.id as rofo_id'
            )
            ->orderBy('sa.unit_number')
            ->orderBy('sa.id')
            ->get();

        $unitIds = $suaUnits->pluck('id')->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        $memoUploads = collect();
        $memoUploadTableExists = Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads');
        if ($memoUploadTableExists && $unitIds->isNotEmpty()) {
            $memoUploads = $connection->table('unit_st_memo_uploads')
                ->whereIn('unit_application_id', $unitIds)
                ->select('unit_application_id', 'uploaded_at', 'file_path')
                ->orderByDesc('uploaded_at')
                ->get()
                ->keyBy('unit_application_id');
        }

        $suaMemoRecords = collect();
        if ($unitIds->isNotEmpty()) {
            $memoRows = $connection->table('memos')
                ->whereIn('unit_id', $unitIds)
                ->whereRaw('LOWER(memo_type) = ?', ['sua'])
                ->select('unit_id', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            if ($memoRows->isNotEmpty()) {
                $suaMemoRecords = $memoRows->keyBy('unit_id');
            }
        }

        $processedUnits = $suaUnits->map(function ($unit) use ($memoUploads, $suaMemoRecords) {
            $applicantType = strtolower(trim($unit->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($unit->corporate_name)) {
                $unit->owner_name = $this->normalizePersonName(trim($unit->corporate_name));
            } elseif ($applicantType === 'multiple' && !empty($unit->multiple_owners_names)) {
                $ownerArray = json_decode($unit->multiple_owners_names, true);
                if (is_array($ownerArray)) {
                    $normalized = collect($ownerArray)
                        ->map(function ($name) {
                            return $this->normalizePersonName($name);
                        })
                        ->filter()
                        ->values();
                    $unit->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
                } else {
                    $unit->owner_name = 'N/A';
                }
            } else {
                $nameParts = array_filter([
                    trim($unit->applicant_title ?? ''),
                    trim($unit->first_name ?? ''),
                    trim($unit->surname ?? ''),
                ]);
                $unit->owner_name = $this->normalizePersonName(!empty($nameParts) ? implode(' ', $nameParts) : null) ?? 'N/A';
            }

            $memoUploadRecord = $memoUploads->get($unit->id);
            if ($memoUploadRecord) {
                $unit->has_st_memo = true;
                $unit->memo_uploaded_at = optional($memoUploadRecord)->uploaded_at;
            } else {
                $memoRecord = $suaMemoRecords->get($unit->id);
                $unit->has_st_memo = $memoRecord !== null;
                $unit->memo_uploaded_at = optional($memoRecord)->created_at;
            }

            $unit->details_captured = !empty($unit->rofo_id);
            $unit->rofo_active = (int) ($unit->rofo_active ?? 0);
            $unit->rofo_generated = !empty($unit->rofo_no) && $unit->rofo_active === 1;
            $unit->print_counter = (int) ($unit->print_counter ?? 0);

            $unitLga = trim((string) ($unit->unit_lga ?? ''));
            if ($unitLga !== '') {
                $unit->property_lga = $unitLga;
            } else {
                $unit->property_lga = 'N/A';
            }

            return $unit;
        });

        $stats = [
            'total_units' => $processedUnits->count(),
            'memo_generated' => $processedUnits->where('has_st_memo', true)->count(),
            'rofo_generated' => $processedUnits->where('rofo_generated', true)->count(),
        ];

        if ($processedUnits->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'messages' => 'No SUA units found.',
            ], 404);
        }

        return view('programmes.partials.rofo_sua_units_modal', [
            'units' => $processedUnits,
            'stats' => $stats,
            'availableSecurityPapers' => $this->getAvailableSecurityPapers(),
        ]);
    }

    public function RofO(Request $request)
    {
        $PageTitle = 'RoFO Applications';
        $PageDescription = '';

        $connection = DB::connection('sqlsrv');
        $availableSecurityPapers = $this->getAvailableSecurityPapers();

        $motherApplications = $connection->table('mother_applications')
            ->select(
                'id',
                'fileno',
                'np_fileno',
                'applicant_type',
                'applicant_title',
                'first_name',
                'surname',
                'multiple_owners_names',
                'corporate_name',
                'land_use',
                'property_lga',
                'property_street_name',
                'property_district',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->get();

        $motherApplications = $motherApplications->map(function ($mother) {
            $applicantType = strtolower(trim($mother->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($mother->corporate_name)) {
                $mother->owner_name = trim($mother->corporate_name);
            } elseif ($applicantType === 'multiple' && !empty($mother->multiple_owners_names)) {
                $motherList = json_decode($mother->multiple_owners_names, true);
                if (is_array($motherList)) {
                    $normalized = collect($motherList)
                        ->map(function ($name) {
                            return $this->normalizePersonName($name);
                        })
                        ->filter()
                        ->values();
                    $mother->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
                } else {
                    $mother->owner_name = 'N/A';
                }
            } else {
                $nameParts = array_filter([
                    trim($mother->applicant_title ?? ''),
                    trim($mother->first_name ?? ''),
                    trim($mother->surname ?? ''),
                ]);
                $mother->owner_name = $this->normalizePersonName(!empty($nameParts) ? implode(' ', $nameParts) : null) ?? 'N/A';
            }

            $locationParts = array_filter([
                trim($mother->property_street_name ?? ''),
                trim($mother->property_district ?? ''),
                trim($mother->property_lga ?? ''),
            ]);
            $mother->property_label = !empty($locationParts) ? implode(', ', $locationParts) : null;

            return $mother;
        });

        $motherLookup = $motherApplications->keyBy('id');

        $subapplications = $connection->table('subapplications as sa')
            ->leftJoin('rofo', 'sa.id', '=', 'rofo.sub_application_id')
            ->select(
                'sa.id',
                'sa.main_application_id',
                'sa.scheme_no',
                'sa.fileno',
                'sa.unit_number',
                'sa.block_number',
                'sa.floor_number',
                'sa.unit_type',
                'sa.unit_lga',
                'sa.is_sua_unit',
                'sa.applicant_type',
                'sa.applicant_title',
                'sa.first_name',
                'sa.surname',
                'sa.corporate_name',
                'sa.multiple_owners_names',
                'sa.land_use',
                'sa.created_at',
                'rofo.rofo_no',
                'rofo.print_counter',
                'rofo.approval_date',
                'rofo.security_paper_code',
                'rofo.active as rofo_active',
                'rofo.id as rofo_id'
            )
            ->orderByDesc('sa.created_at')
            ->get();

        $subapplications = $subapplications->map(function ($unit) {
            $unit->unit_category = ((int) ($unit->is_sua_unit ?? 0) === 1) ? 'SUA' : 'PUA';
            return $unit;
        });

        $memoUploadTableExists = Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads');

        $unitIds = $subapplications->pluck('id')->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        $memoUploads = collect();
        if ($memoUploadTableExists && $unitIds->isNotEmpty()) {
            $memoUploads = $connection->table('unit_st_memo_uploads')
                ->whereIn('unit_application_id', $unitIds)
                ->select('unit_application_id', 'uploaded_at', 'file_path')
                ->orderByDesc('uploaded_at')
                ->get()
                ->keyBy('unit_application_id');
        }

        $puaMemoRecords = collect();
        $suaMemoRecords = collect();
        if ($unitIds->isNotEmpty()) {
            $memoRows = $connection->table('memos')
                ->whereIn('unit_id', $unitIds)
                ->select('unit_id', 'memo_type', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            if ($memoRows->isNotEmpty()) {
                $puaMemoRecords = $memoRows
                    ->filter(function ($record) {
                        return strtolower(trim($record->memo_type ?? '')) === 'pua';
                    })
                    ->keyBy('unit_id');

                $suaMemoRecords = $memoRows
                    ->filter(function ($record) {
                        return strtolower(trim($record->memo_type ?? '')) === 'sua';
                    })
                    ->keyBy('unit_id');
            }
        }

        $subapplications = $subapplications->map(function ($unit) use ($memoUploads, $puaMemoRecords, $suaMemoRecords) {
            $applicantType = strtolower(trim($unit->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($unit->corporate_name)) {
                $unit->owner_name = trim($unit->corporate_name);
            } elseif ($applicantType === 'multiple' && !empty($unit->multiple_owners_names)) {
                $ownerArray = json_decode($unit->multiple_owners_names, true);
                if (is_array($ownerArray)) {
                    $normalized = collect($ownerArray)
                        ->map(function ($name) {
                            return $this->normalizePersonName($name);
                        })
                        ->filter()
                        ->values();
                    $unit->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
                } else {
                    $unit->owner_name = 'N/A';
                }
            } else {
                $nameParts = array_filter([
                    trim($unit->applicant_title ?? ''),
                    trim($unit->first_name ?? ''),
                    trim($unit->surname ?? ''),
                ]);
                $unit->owner_name = $this->normalizePersonName(!empty($nameParts) ? implode(' ', $nameParts) : null) ?? 'N/A';
            }

            $memoUploadRecord = $memoUploads->get($unit->id);
            if ($memoUploadRecord) {
                $unit->has_st_memo = true;
                $unit->memo_uploaded_at = optional($memoUploadRecord)->uploaded_at;
            } else {
                $unitCategory = strtolower(trim($unit->unit_category ?? 'pua'));
                $memoRecord = $unitCategory === 'sua'
                    ? $suaMemoRecords->get($unit->id)
                    : $puaMemoRecords->get($unit->id);
                $unit->has_st_memo = $memoRecord !== null;
                $unit->memo_uploaded_at = optional($memoRecord)->created_at;
            }

            $unit->details_captured = !empty($unit->rofo_id);
            $unit->rofo_active = (int) ($unit->rofo_active ?? 0);
            $unit->rofo_generated = !empty($unit->rofo_no) && $unit->rofo_active === 1;
            $unit->print_counter = (int) ($unit->print_counter ?? 0);

            $unitLga = trim((string) ($unit->unit_lga ?? ''));
            if ($unitLga !== '') {
                $unit->property_lga = $unitLga;
            }

            return $unit;
        });

        $puaSubapplications = $subapplications->filter(function ($application) {
            return ($application->unit_category ?? 'PUA') === 'PUA';
        })->values();

        $suaSubapplications = $subapplications->filter(function ($application) {
            return ($application->unit_category ?? 'PUA') === 'SUA';
        })->values();

        $puaPrimaries = $puaSubapplications
            ->groupBy(function ($application) {
                return (int) ($application->main_application_id ?? 0);
            })
            ->map(function ($units, $mainId) use ($motherLookup) {
                $mainId = (int) $mainId;
                $mother = $motherLookup->get($mainId);

                $totalUnits = $units->count();
                $rofoGenerated = $units->where(function ($unit) {
                    return (bool) ($unit->rofo_generated ?? false);
                })->count();
                $memoGenerated = $units->where(function ($unit) {
                    return (bool) ($unit->has_st_memo ?? false);
                })->count();
                $detailsCaptured = $units->where(function ($unit) {
                    return (bool) ($unit->details_captured ?? false);
                })->count();

                $sortedUnits = $units->sortBy('id');
                $referenceUnit = $sortedUnits->first();

                $schemeNo = optional($referenceUnit)->scheme_no ?? 'N/A';
                $primaryFileNo = optional($mother)->fileno ?? optional($referenceUnit)->fileno ?? 'N/A';
                $primaryNPFileNo = optional($mother)->np_fileno ?? null;
                $ownerName = optional($mother)->owner_name ?? optional($referenceUnit)->owner_name ?? 'N/A';
                $propertyLga = optional($mother)->property_lga ?? optional($referenceUnit)->property_lga ?? 'N/A';
                $landUse = optional($mother)->land_use ?? optional($referenceUnit)->land_use ?? 'N/A';
                $createdAt = optional($mother)->created_at ?? optional($referenceUnit)->created_at;

                return (object) [
                    'main_application_id' => $mainId,
                    'primary_file_no' => $primaryFileNo,
                    'primary_np_file_no' => $primaryNPFileNo,
                    'scheme_no' => $schemeNo,
                    'owner_name' => $ownerName,
                    'property_lga' => $propertyLga,
                    'land_use' => $landUse,
                    'created_at' => $createdAt,
                    'total_units' => $totalUnits,
                    'generated_units' => $rofoGenerated,
                    'pending_units' => max(0, $totalUnits - $rofoGenerated),
                    'memo_generated_units' => $memoGenerated,
                    'memo_pending_units' => max(0, $totalUnits - $memoGenerated),
                    'details_captured_units' => $detailsCaptured,
                    'details_missing_units' => max(0, $totalUnits - $detailsCaptured),
                    'all_rofo_generated' => $totalUnits > 0 && $rofoGenerated === $totalUnits,
                    'all_memos_generated' => $totalUnits > 0 && $memoGenerated === $totalUnits,
                    'all_details_captured' => $totalUnits > 0 && $detailsCaptured === $totalUnits,
                    'reference_unit_id' => optional($referenceUnit)->id,
                    'units' => $units->values(),
                ];
            })
            ->filter(function ($summary) {
                return $summary !== null && !empty($summary->reference_unit_id);
            })
            ->values();

        return view('programmes.rofo', compact(
            'motherApplications',
            'subapplications',
            'puaSubapplications',
            'suaSubapplications',
            'puaPrimaries',
            'availableSecurityPapers',
            'PageTitle',
            'PageDescription'
        ));
    }

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

    private function buildPrimaryBatchContext(int $mainApplicationId): ?array
    {
        if ($mainApplicationId <= 0) {
            return null;
        }

        $connection = DB::connection('sqlsrv');

        $mother = $connection->table('mother_applications')
            ->where('id', $mainApplicationId)
            ->select(
                'id',
                'fileno',
                'np_fileno',
                'applicant_type',
                'applicant_title',
                'first_name',
                'surname',
                'multiple_owners_names',
                'corporate_name',
                'land_use',
                'property_lga',
                'property_street_name',
                'property_district',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->first();

        if (!$mother) {
            return null;
        }

        $motherApplicantType = strtolower(trim($mother->applicant_type ?? ''));
        if ($motherApplicantType === 'corporate' && !empty($mother->corporate_name)) {
            $mother->owner_name = trim($mother->corporate_name);
        } elseif ($motherApplicantType === 'multiple' && !empty($mother->multiple_owners_names)) {
            $motherList = json_decode($mother->multiple_owners_names, true);
            if (is_array($motherList)) {
                $normalized = collect($motherList)
                    ->map(function ($name) {
                        return $this->normalizePersonName($name);
                    })
                    ->filter()
                    ->values();
                $mother->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
            } else {
                $mother->owner_name = 'N/A';
            }
        } else {
            $motherNameParts = array_filter([
                trim($mother->applicant_title ?? ''),
                trim($mother->first_name ?? ''),
                trim($mother->surname ?? ''),
            ]);
            $mother->owner_name = $this->normalizePersonName(!empty($motherNameParts) ? implode(' ', $motherNameParts) : null) ?? 'N/A';
        }

        $motherLocationParts = array_filter([
            trim($mother->property_street_name ?? ''),
            trim($mother->property_district ?? ''),
            trim($mother->property_lga ?? ''),
        ]);
        $mother->property_label = !empty($motherLocationParts)
            ? implode(', ', $motherLocationParts)
            : null;

        $units = $connection->table('subapplications as sa')
            ->leftJoin('rofo', 'sa.id', '=', 'rofo.sub_application_id')
            ->leftJoin('st_cofo', function ($join) {
                $join->on('sa.id', '=', 'st_cofo.sub_application_id')
                    ->where('st_cofo.is_active', 1);
            })
            ->where('sa.main_application_id', $mainApplicationId)
            ->select(
                'sa.id',
                'sa.scheme_no',
                'sa.fileno',
                'sa.unit_number',
                'sa.block_number',
                'sa.floor_number',
                'sa.unit_type',
                'sa.is_sua_unit',
                'sa.applicant_type',
                'sa.applicant_title',
                'sa.first_name',
                'sa.surname',
                'sa.corporate_name',
                'sa.multiple_owners_names',
                'sa.specific_land_use',
                'sa.created_at',
                'rofo.rofo_no',
                'rofo.print_counter',
                'rofo.approval_date',
                'rofo.ground_rent',
                'rofo.recertification_fee',
                'rofo.security_paper_code',
                'rofo.id as rofo_id',
                'st_cofo.id as cofo_id',
                'st_cofo.certificate_number'
            )
            ->orderBy('sa.unit_number')
            ->orderBy('sa.id')
            ->get();

        if ($units->isEmpty()) {
            return [
                'mother' => $mother,
                'units' => collect(),
                'stats' => [
                    'total_units' => 0,
                    'memo_generated' => 0,
                    'rofo_generated' => 0,
                ],
            ];
        }

        $memoUploadTableExists = Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads');

        $unitIds = $units->pluck('id')->filter()->map(function ($id) {
            return (int) $id;
        })->unique()->values();

        $memoUploads = collect();
        if ($memoUploadTableExists && $unitIds->isNotEmpty()) {
            $memoUploads = $connection->table('unit_st_memo_uploads')
                ->whereIn('unit_application_id', $unitIds)
                ->select('unit_application_id', 'uploaded_at', 'file_path')
                ->orderByDesc('uploaded_at')
                ->get()
                ->keyBy('unit_application_id');
        }

        $puaMemoRecords = collect();
        if ($memoUploads->isEmpty() && $unitIds->isNotEmpty()) {
            $puaMemoRecords = $connection->table('memos')
                ->whereRaw('LOWER(memo_type) = ?', ['pua'])
                ->whereIn('unit_id', $unitIds)
                ->select('unit_id', 'created_at')
                ->get()
                ->keyBy('unit_id');
        }

        $memoGeneratedCount = 0;
        $rofoGeneratedCount = 0;
        $detailsCapturedCount = 0;

        foreach ($units as $unit) {
            $applicantType = strtolower(trim($unit->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($unit->corporate_name)) {
                $unit->owner_name = trim($unit->corporate_name);
            } elseif ($applicantType === 'multiple' && !empty($unit->multiple_owners_names)) {
                $ownerArray = json_decode($unit->multiple_owners_names, true);
                if (is_array($ownerArray)) {
                    $normalized = collect($ownerArray)
                        ->map(function ($name) {
                            return $this->normalizePersonName($name);
                        })
                        ->filter()
                        ->values();
                    $unit->owner_name = $normalized->isNotEmpty() ? implode(', ', $normalized->all()) : 'N/A';
                } else {
                    $unit->owner_name = 'N/A';
                }
            } else {
                $nameParts = array_filter([
                    trim($unit->applicant_title ?? ''),
                    trim($unit->first_name ?? ''),
                    trim($unit->surname ?? ''),
                ]);
                $unit->owner_name = $this->normalizePersonName(!empty($nameParts) ? implode(' ', $nameParts) : null) ?? 'N/A';
            }

            $unit->unit_label = collect([
                trim((string) ($unit->unit_number ?? '')),
                trim((string) ($unit->floor_number ?? '')),
                trim((string) ($unit->block_number ?? '')),
            ])->filter()->implode(' • ');

            if ($memoUploadTableExists) {
                $memoRecord = $memoUploads->get($unit->id);
                $unit->has_st_memo = $memoRecord !== null;
                $unit->memo_uploaded_at = optional($memoRecord)->uploaded_at;
            } else {
                $memoRecord = $puaMemoRecords->get($unit->id);
                $unit->has_st_memo = $memoRecord !== null;
                $unit->memo_uploaded_at = optional($memoRecord)->created_at;
            }

            if ($unit->has_st_memo) {
                $memoGeneratedCount++;
            }

            $unit->details_captured = !empty($unit->rofo_id);
            $unit->rofo_generated = !empty($unit->rofo_no);
            if ($unit->rofo_generated) {
                $rofoGeneratedCount++;
            }
            if ($unit->details_captured) {
                $detailsCapturedCount++;
            }

            $unit->print_counter = (int) ($unit->print_counter ?? 0);
        }

        return [
            'mother' => $mother,
            'units' => $units,
            'stats' => [
                'total_units' => $units->count(),
                'memo_generated' => $memoGeneratedCount,
                'rofo_generated' => $rofoGeneratedCount,
                'details_captured' => $detailsCapturedCount,
            ],
        ];
    }

    // Generate ROFO form
    public function generateRofO(Request $request, $id)
    {
        $PageTitle = $request->query('edit') === 'yes' ? 'Edit RofO (Letter of Grant)' : 'Generate RofO (Letter of Grant)';
        $PageDescription = '';
        $isAjax = $request->ajax();
        $isModal = $request->boolean('modal');
        $isBatch = $request->boolean('batch');

        if ($isBatch && $request->query('edit') !== 'yes') {
            $PageTitle = 'Batch Generate RofO (Letter of Grant)';
        }

        try {
            // Fetch the subapplication details with mother application data
            $rofo = DB::connection('sqlsrv')->table('subapplications')
                ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                ->where('subapplications.id', $id)
                ->select(
                    'subapplications.*',
                    'mother_applications.property_house_no',
                    'mother_applications.property_plot_no',
                    'mother_applications.property_street_name',
                    'mother_applications.property_district',
                    'mother_applications.property_lga',
                    'mother_applications.property_state',
                    'mother_applications.land_use as mother_land_use',
                    DB::raw('CASE WHEN ISNULL(subapplications.is_sua_unit, 0) = 1 THEN \'SUA\' ELSE \'PUA\' END AS unit_category')
                )
                ->first();

            if (!$rofo) {
                if ($isAjax || $isModal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Application not found.',
                    ], 404);
                }

                return back()->with('error', 'Application not found');
            }

            $mainApplicationId = (int) ($rofo->main_application_id ?? 0);
            $batchContext = null;
            $batchUnits = collect();

            if ($isBatch) {
                $batchContext = $this->buildPrimaryBatchContext($mainApplicationId);
                $batchUnits = collect($batchContext['units'] ?? []);
                $hasValidBatch = $batchContext && !empty($batchContext['mother']) && $batchUnits->isNotEmpty();

                if (!$hasValidBatch) {
                    $errorMessage = 'Unable to load batch context for this primary application.';

                    if ($isAjax || $isModal) {
                        return response()->json([
                            'success' => false,
                            'message' => $errorMessage,
                        ], 404);
                    }

                    return back()->with('error', $errorMessage);
                }
            }

            // Fetch land administration data
            $landAdmin = DB::connection('sqlsrv')->table('landAdministration')
                ->where('sub_application_id', $id)
                ->first();

            // Fetch financial data from final_bills table
            $finalBill = DB::connection('sqlsrv')->table('final_bills')
                ->where('sub_application_id', $id)
                ->first();

            // Fetch survey cadastral record for plan number
            $surveyRecord = DB::connection('sqlsrv')->table('surveyCadastralRecord')
                ->where('sub_application_id', $id)
                ->first();

            // Fetch memo data to determine term years and improvement timeline
            $memoRecord = DB::connection('sqlsrv')->table('memos')
                ->where('unit_id', $id)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($memoRecord && isset($memoRecord->term_years) && !empty($memoRecord->term_years)) {
                $rofo->memo_term_years = (int) $memoRecord->term_years;
            }

            // Process owner names
            $applicantType = strtolower(trim($rofo->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($rofo->corporate_name)) {
                $rofo->owner_name = trim($rofo->corporate_name);
            } elseif ($applicantType === 'multiple' && !empty($rofo->multiple_owners_names)) {
                $ownerArray = json_decode($rofo->multiple_owners_names, true);
                $rofo->owner_name = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
            } elseif ($applicantType === 'individual' || empty($applicantType)) {
                // Build individual name from parts, filtering out empty values
                $nameParts = array_filter([
                    trim($rofo->applicant_title ?? ''),
                    trim($rofo->first_name ?? ''),
                    trim($rofo->surname ?? '')
                ]);
                $rofo->owner_name = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
            } else {
                // Fallback: try all methods
                if (!empty($rofo->multiple_owners_names)) {
                    $ownerArray = json_decode($rofo->multiple_owners_names, true);
                    $rofo->owner_name = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
                } elseif (!empty($rofo->corporate_name)) {
                    $rofo->owner_name = trim($rofo->corporate_name);
                } else {
                    $nameParts = array_filter([
                        trim($rofo->applicant_title ?? ''),
                        trim($rofo->first_name ?? ''),
                        trim($rofo->surname ?? '')
                    ]);
                    $rofo->owner_name = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
                }
            }

            // Check if a ROFO already exists for this application
            $existingRofo = DB::connection('sqlsrv')->table('rofo')
                ->where('sub_application_id', $id)
                ->first();

            // Calculate next ROFO number
            $lastId = DB::connection('sqlsrv')->table('rofo')->max('id') ?? 0;
            $nextId = $lastId + 1;
            $currentYear = Carbon::now()->format('Y');
            $nextRofoNo = 'ST/ROFO/' . $currentYear . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            if ($isAjax || $isModal) {
                return response()->json([
                    'success' => true,
                    'html' => view('programmes.partials.rofo_form', [
                        'rofo' => $rofo,
                        'landAdmin' => $landAdmin,
                        'existingRofo' => $existingRofo,
                        'finalBill' => $finalBill,
                        'surveyRecord' => $surveyRecord,
                        'memoRecord' => $memoRecord,
                        'nextRofoNo' => $nextRofoNo,
                        'PageTitle' => $PageTitle,
                        'PageDescription' => $PageDescription,
                        'isModal' => true,
                        'batchMode' => false,
                    ])->render(),
                    'meta' => [
                        'rofo_exists' => (bool) $existingRofo,
                        'rofo_no' => $existingRofo->rofo_no ?? null,
                    ],
                ]);
            }

            return view('programmes.generate_rofo', [
                'rofo' => $rofo,
                'landAdmin' => $landAdmin,
                'existingRofo' => $existingRofo,
                'finalBill' => $finalBill,
                'surveyRecord' => $surveyRecord,
                'memoRecord' => $memoRecord,
                'nextRofoNo' => $nextRofoNo,
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription,
                'batchMode' => $isBatch,
                'batchMother' => $batchContext['mother'] ?? null,
                'batchUnits' => $isBatch ? $batchUnits : collect(),
                'batchStats' => $batchContext['stats'] ?? [
                    'total_units' => 0,
                    'memo_generated' => 0,
                    'rofo_generated' => 0,
                ],
                'batchPrimaryId' => $mainApplicationId,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in generateROFO: ' . $e->getMessage());
            if ($isAjax || $isModal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to load the RoFO form. Please try again later.',
                ], 500);
            }

            return back()->with('error', 'An error occurred while preparing ROFO form');
        }
    }

    // Save ROFO data
    public function saveRofO(Request $request)
    {
        try {
            $isModal = $request->boolean('modal');
            $isBatch = $request->boolean('batch');
            $expectsJson = $request->ajax() || $request->wantsJson() || $isModal;

            $validated = $request->validate([
                'sub_application_id' => 'required|integer',
                'application_id' => 'nullable|integer',
                'shop_house_no' => 'nullable|string',
                'floor_no' => 'nullable|string|max:10',
                'location' => 'required|string',
                'application_date' => 'required|date',
                'approval_date' => 'required|date',
                'plot_no' => 'nullable|string|max:20',
                'block_no' => 'nullable|string|max:20',
                'plan_no' => 'nullable|string|max:20',
                'recertification_fee' => 'nullable|string',
                'development_charges' => 'nullable', // Allow "to follow" string
                'survey_processing_fees' => 'nullable|numeric',
                'term_years' => 'required|integer|min:1|max:999',
                'purpose' => 'required|string',
                'improvement_value' => 'nullable',
                'improvement_time_limit' => 'nullable|integer|min:0|max:999',
                'commissioner_name' => 'required|string',
                'signed_date' => 'required|date',
            ]);

            $connection = DB::connection('sqlsrv');

            $mainApplicationId = isset($validated['application_id']) && $validated['application_id'] !== null
                ? (int) $validated['application_id']
                : 0;
            $targetSubApplicationId = (int) $validated['sub_application_id'];

            $unitApplications = collect();

            if ($mainApplicationId > 0) {
                $unitApplications = $connection->table('subapplications')
                    ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                    ->where('subapplications.main_application_id', $mainApplicationId)
                    ->select(
                        'subapplications.id',
                        'subapplications.floor_number',
                        'subapplications.block_number',
                        'subapplications.site_plan_fee',
                        'mother_applications.property_house_no',
                        'mother_applications.property_plot_no',
                        'mother_applications.property_street_name',
                        'mother_applications.property_district',
                        'subapplications.specific_land_use'
                    )
                    ->get();
            }

            if ($unitApplications->isEmpty()) {
                $unitApplications = collect([
                    (object) [
                        'id' => $targetSubApplicationId,
                        'floor_number' => $validated['floor_no'] ?? null,
                        'block_number' => $validated['block_no'] ?? null,
                        'property_house_no' => $validated['shop_house_no'] ?? null,
                        'property_plot_no' => $validated['plot_no'] ?? null,
                        'property_street_name' => null,
                        'property_district' => null,
                        'specific_land_use' => null,
                        'site_plan_fee' => null,
                    ],
                ]);
            }

            $unitIds = $unitApplications->pluck('id')
                ->filter()
                ->map(function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->values();

            $planNumbers = $unitIds->isEmpty()
                ? collect()
                : $connection->table('surveyCadastralRecord')
                    ->whereIn('sub_application_id', $unitIds)
                    ->select('sub_application_id', 'approved_plan_no')
                    ->orderByDesc('id')
                    ->get()
                    ->unique('sub_application_id')
                    ->keyBy('sub_application_id');

            $recertificationFee = isset($validated['recertification_fee']) && $validated['recertification_fee'] !== ''
                ? $validated['recertification_fee']
                : null;

            // Handle Development Charges: accept "to follow" (case-insensitive) as null, otherwise float
            $devChargesInput = isset($validated['development_charges']) ? trim($validated['development_charges']) : '';
            if (strtolower($devChargesInput) === 'to follow') {
                $developmentCharges = null;
            } else {
                $developmentCharges = $devChargesInput !== '' ? (float) $devChargesInput : null;
            }

            $surveyProcessingFees = isset($validated['survey_processing_fees']) && $validated['survey_processing_fees'] !== ''
                ? (float) $validated['survey_processing_fees']
                : null;
            $improvementValueInput = isset($validated['improvement_value']) ? trim($validated['improvement_value']) : '';
            $improvementValue = (is_numeric($improvementValueInput) && $improvementValueInput !== '')
                ? (float) $improvementValueInput
                : null;
            $improvementTimeLimit = isset($validated['improvement_time_limit']) && $validated['improvement_time_limit'] !== ''
                ? (int) $validated['improvement_time_limit']
                : null;
            $termYears = (int) $validated['term_years'];
            $startDate = Carbon::parse($validated['approval_date']);
            $endDate = $startDate->copy()->addYears($termYears);
            $currentYear = Carbon::now()->format('Y');
            $now = now();

            $commFee = $request->input('rent_category_comm');
            $resFee = $request->input('rent_category_res');

            $summary = $connection->transaction(function () use ($connection, $unitApplications, $unitIds, $planNumbers, $recertificationFee, $developmentCharges, $surveyProcessingFees, $termYears, $validated, $improvementValue, $improvementTimeLimit, $isModal, $now, $startDate, $endDate, $mainApplicationId, $targetSubApplicationId, $currentYear, $commFee, $resFee) {
                $existingRofos = $unitIds->isEmpty()
                    ? collect()
                    : $connection->table('rofo')
                        ->whereIn('sub_application_id', $unitIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('sub_application_id');

                $lastId = (int) ($connection->table('rofo')->max('id') ?? 0);

                $primaryRofoId = null;
                $createdCount = 0;
                $updatedCount = 0;
                $affectedIds = [];

                foreach ($unitApplications as $unit) {
                    $unitId = (int) ($unit->id ?? 0);
                    if ($unitId === 0) {
                        continue;
                    }

                    $locationParts = array_filter([
                        $unit->property_house_no ?? null,
                        $unit->property_plot_no ?? null,
                        $unit->property_street_name ?? null,
                        $unit->property_district ?? null,
                    ]);
                    $unitLocation = trim(implode(' ', $locationParts));
                    if ($unitLocation === '') {
                        $unitLocation = $validated['location'];
                    }

                    $unitPlanNo = optional($planNumbers->get($unitId))->approved_plan_no ?? ($validated['plan_no'] ?? null);

                    $unitInfoSpecificUse = strtolower($unit->specific_land_use ?? '');
                    $finalFee = $recertificationFee;

                    if ($commFee || $resFee) {
                        if (Str::contains($unitInfoSpecificUse, 'commercial') && $commFee) {
                            // Format as ₦100.00K
                            $numericVal = (float) str_replace(['₦', 'K', ','], '', $commFee);
                            $finalFee = '₦' . number_format($numericVal, 2) . 'K';
                        } elseif (Str::contains($unitInfoSpecificUse, 'residential') && $resFee) {
                            // Format as ₦12.00K
                            $numericVal = (float) str_replace(['₦', 'K', ','], '', $resFee);
                            $finalFee = '₦' . number_format($numericVal, 2) . 'K';
                        }
                    }

                    $updateData = [
                        'shop_house_no' => $unit->property_house_no ?? ($validated['shop_house_no'] ?? null),
                        'floor_no' => $unit->floor_number ?? ($validated['floor_no'] ?? null),
                        'location' => $unitLocation,
                        'application_date' => $validated['application_date'],
                        'approval_date' => $validated['approval_date'],
                        'plot_no' => $unit->property_plot_no ?? ($validated['plot_no'] ?? null),
                        'block_no' => $unit->block_number ?? ($validated['block_no'] ?? null),
                        'plan_no' => $unitPlanNo,
                        'recertification_fee' => $finalFee,
                        'ground_rent' => $finalFee, // Save to ground_rent as requested
                        'development_charges' => $developmentCharges,
                        'survey_processing_fees' => ($unit->site_plan_fee ?? null) !== null ? (float) $unit->site_plan_fee : 0.0,
                        'term_years' => $termYears,
                        'purpose' => $validated['purpose'],
                        'improvement_value' => $improvementValue,
                        'improvement_time_limit' => $improvementTimeLimit,
                        'commissioner_name' => $validated['commissioner_name'],
                        'signed_date' => $validated['signed_date'],
                        'updated_at' => $now,
                    ];

                    if (!$isModal) {
                        $updateData['active'] = 1;
                    }

                    $existing = $existingRofos->get($unitId);

                    if ($existing) {
                        $connection->table('rofo')
                            ->where('id', $existing->id)
                            ->update($updateData);

                        $processedId = (int) $existing->id;
                        $updatedCount++;
                    } else {
                        $nextId = ++$lastId;
                        $rofoNo = 'ST/ROFO/' . $currentYear . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                        $insertData = array_merge($updateData, [
                            'rofo_no' => $rofoNo,
                            'application_id' => $mainApplicationId > 0 ? $mainApplicationId : null,
                            'sub_application_id' => $unitId,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'active' => $isModal ? 0 : 1,
                            'created_at' => $now,
                        ]);

                        $processedId = $connection->table('rofo')->insertGetId($insertData);
                        $createdCount++;
                    }

                    // Also sync improvement_value to subapplications table
                    $connection->table('subapplications')
                        ->where('id', $unitId)
                        ->update(['improvement_value' => $improvementValue]);

                    $affectedIds[] = $unitId;

                    if ($unitId === $targetSubApplicationId) {
                        $primaryRofoId = $processedId;
                    }
                }

                return [
                    'primary_rofo_id' => $primaryRofoId,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total_units' => count($affectedIds),
                    'affected_sub_application_ids' => $affectedIds,
                ];
            });

            $primaryRofoId = $summary['primary_rofo_id'] ?? null;
            if (!$primaryRofoId) {
                $primaryRofoId = $connection->table('rofo')
                    ->where('sub_application_id', $targetSubApplicationId)
                    ->value('id');
            }

            $createdCount = $summary['created'] ?? 0;
            $updatedCount = $summary['updated'] ?? 0;
            $totalProcessed = $summary['total_units'] ?? $unitApplications->count();

            app(RofoPraSyncer::class)->syncStUnits($summary['affected_sub_application_ids'] ?? []);

            if ($isModal) {
                $message = 'RoFO details saved successfully';
            } elseif ($isBatch) {
                $message = sprintf(
                    'Batch RoFO processed: %d generated, %d updated across %d unit(s).',
                    $createdCount,
                    $updatedCount,
                    $totalProcessed
                );
            } else {
                $message = $createdCount > 0 ? 'ROFO generated successfully' : 'ROFO updated successfully';
            }

            if ($expectsJson) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'rofo_id' => $primaryRofoId,
                    'affected_sub_application_ids' => $summary['affected_sub_application_ids'] ?? [],
                    'redirect_url' => route('programmes.view_rofo', [
                        $targetSubApplicationId,
                        'modal' => 1,
                    ]),
                    'batch' => $isBatch,
                ]);
            }

            if ($isBatch) {
                return redirect()->route('programmes.rofo')
                    ->with('success', $message);
            }

            return redirect()->route('programmes.view_rofo', $targetSubApplicationId)
                ->with('success', $message);
        } catch (\Exception $e) {
            \Log::error('Error saving ROFO: ' . $e->getMessage());
            if ($expectsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to save RoFO data. ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'An error occurred while saving ROFO data: ' . $e->getMessage())
                ->withInput();
        }
    }

    //view rofo
    public function viewRofO(Request $request, $id)
    {
        $PageTitle = 'RofO (Letter of Grant)';
        $PageDescription = '';

        try {
            // Check if a ROFO record exists for this application
            $rofoData = DB::connection('sqlsrv')->table('rofo')
                ->where('sub_application_id', $id)
                ->first();

            // Debug the ROFO data to check what's being retrieved
            \Log::info('ROFO Data for ID ' . $id . ':', ['data' => $rofoData]);

            if (!$rofoData) {
                // If no ROFO exists, redirect to generate ROFO
                return redirect()->route('programmes.generate_rofo', $id)
                    ->with('info', 'No ROFO has been generated yet. Please generate one first.');
            }

            $watermarkStages = ['ORIGINAL', 'DUPLICATE', 'TRIPLICATE'];
            $maxPrints = count($watermarkStages);
            $printCounter = (int) ($rofoData->print_counter ?? 0);
            $defaultWatermark = $watermarkStages[0];
            $currentWatermark = $printCounter > 0
                ? $watermarkStages[min($printCounter, $maxPrints) - 1]
                : $defaultWatermark;

            $requestedWatermark = strtoupper($request->query('watermark', ''));
            if ($requestedWatermark && in_array($requestedWatermark, $watermarkStages, true)) {
                $expectedIndex = min($printCounter, $maxPrints - 1);
                $expectedWatermark = $watermarkStages[$expectedIndex];

                if ($printCounter < $maxPrints && $requestedWatermark === $expectedWatermark) {
                    DB::connection('sqlsrv')->table('rofo')
                        ->where('sub_application_id', $id)
                        ->update([
                            'print_counter' => $printCounter + 1,
                            'updated_at' => now(),
                        ]);

                    $printCounter++;
                    $currentWatermark = $requestedWatermark;
                    $rofoData->print_counter = $printCounter;
                } elseif ($printCounter >= $maxPrints) {
                    $currentWatermark = $watermarkStages[$maxPrints - 1];
                }
            }

            // Fetch the subapplication details with mother application data
            $rofo = DB::connection('sqlsrv')->table('subapplications')
                ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                ->where('subapplications.id', $id)
                ->select(
                    'subapplications.*',
                    'subapplications.specific_land_use as unit_specific_use',
                    'mother_applications.property_house_no',
                    'mother_applications.property_plot_no',
                    'mother_applications.property_street_name',
                    'mother_applications.property_district',
                    'mother_applications.property_lga',
                    'mother_applications.property_state',
                    'mother_applications.land_use'
                )
                ->first();

            if (!$rofo) {
                return back()->with('error', 'Application not found');
            }

            // Fetch land administration data
            $landAdmin = DB::connection('sqlsrv')->table('landAdministration')
                ->where('sub_application_id', $id)
                ->first();

            // Calculate residual years
            $startDate = \Carbon\Carbon::parse($rofoData->start_date ?? now());
            $endDate = \Carbon\Carbon::parse($rofoData->end_date ?? now()->addYears(40));
            $totalYears = $rofoData->term_years ?? 40;
            $residualYears = $startDate->diffInYears($endDate);

            // Process owner names
            $applicantType = strtolower(trim($rofo->applicant_type ?? ''));

            if ($applicantType === 'corporate' && !empty($rofo->corporate_name)) {
                $rofo->owner_name = trim($rofo->corporate_name);
            } elseif ($applicantType === 'multiple' && !empty($rofo->multiple_owners_names)) {
                $ownerArray = json_decode($rofo->multiple_owners_names, true);
                $rofo->owner_name = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
            } elseif ($applicantType === 'individual' || empty($applicantType)) {
                // Build individual name from parts, filtering out empty values
                $nameParts = array_filter([
                    trim($rofo->applicant_title ?? ''),
                    trim($rofo->first_name ?? ''),
                    trim($rofo->surname ?? '')
                ]);
                $rofo->owner_name = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
            } else {
                // Fallback: try all methods
                if (!empty($rofo->multiple_owners_names)) {
                    $ownerArray = json_decode($rofo->multiple_owners_names, true);
                    $rofo->owner_name = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
                } elseif (!empty($rofo->corporate_name)) {
                    $rofo->owner_name = trim($rofo->corporate_name);
                } else {
                    $nameParts = array_filter([
                        trim($rofo->applicant_title ?? ''),
                        trim($rofo->first_name ?? ''),
                        trim($rofo->surname ?? '')
                    ]);
                    $rofo->owner_name = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
                }
            }

            // Set default page info if landAdmin data is missing
            $pageNo = $landAdmin->page_no ?? '01';

            // Make sure we're passing the correct data to the view - verify all fields exist
            $finalRofoData = [];
            foreach ((array) $rofoData as $key => $value) {
                $finalRofoData[$key] = $value;
            }

            return view('programmes.view_rofo', compact(
                'rofo',
                'rofoData',
                'finalRofoData', // Add this to help debugging
                'landAdmin',
                'totalYears',
                'residualYears',
                'PageTitle',
                'PageDescription',
                'pageNo',
                'printCounter',
                'currentWatermark'
            ));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in viewROFO: ' . $e->getMessage());
            \Log::error('Error stack trace: ' . $e->getTraceAsString());
            return back()->with('error', 'An error occurred while fetching rofo data');
        }
    }

    public function printRofO($id)
    {
        $PageTitle = 'RofO (Letter of Grant)';
        $PageDescription = '';

        try {
            // Fetch the subapplication to find the main_application_id
            $initialSubApp = DB::connection('sqlsrv')->table('subapplications')->where('id', $id)->first();

            if (!$initialSubApp) {
                return redirect()->route('programmes.view_rofo', $id)->with('error', 'Application not found');
            }

            $mainApplicationId = $initialSubApp->main_application_id;

            // Fetch ALL subapplications for this main application
            $allSubApps = DB::connection('sqlsrv')->table('subapplications')
                ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                ->where('subapplications.main_application_id', $mainApplicationId)
                ->select(
                    'subapplications.*',
                    'subapplications.specific_land_use as unit_specific_use',
                    'mother_applications.property_house_no',
                    'mother_applications.property_plot_no',
                    'mother_applications.property_street_name',
                    'mother_applications.property_district',
                    'mother_applications.property_lga',
                    'mother_applications.property_state',
                    'mother_applications.land_use'
                )
                ->get();

            $batchRofos = [];

            // --- Determine watermark sequence from Print Manager params ---
            $watermarkStages = ['ORIGINAL', 'DUPLICATE', 'TRIPLICATE'];
            $maxPrints = count($watermarkStages);
            $status = strtoupper(request()->query('status', ''));
            $isCTC = request()->query('isCTC', false);
            $requestedWatermark = strtoupper(request()->query('watermark', ''));

            // Determine watermark sequence for this print job.
            // Both normal batch and CTC now produce all 3 copies at once:
            // ORIGINAL (Red), DUPLICATE (Blue), TRIPLICATE (Green).
            // (CTC differs only in that it does NOT consume the print_counter below.)
            $watermarkSequence = $watermarkStages;

            $finalWatermarkForView = $watermarkSequence[0];

            // Update print_counter for all prints (not CTC)
            if (!$isCTC) {
                foreach ($allSubApps as $subApp) {
                    $rofoRecord = DB::connection('sqlsrv')->table('rofo')
                        ->where('sub_application_id', $subApp->id)
                        ->first();

                    if (!$rofoRecord)
                        continue;

                    $currentCounter = (int) ($rofoRecord->print_counter ?? 0);

                    // All 3 copies printed at once, set counter to max
                    if ($currentCounter < $maxPrints) {
                        DB::connection('sqlsrv')->table('rofo')
                            ->where('sub_application_id', $subApp->id)
                            ->update([
                                'print_counter' => $maxPrints,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
            // ---------------------------

            foreach ($allSubApps as $subApp) {
                // Fetch ROFO data for this specific unit
                $unitRofoData = DB::connection('sqlsrv')->table('rofo')
                    ->where('sub_application_id', $subApp->id)
                    ->first();

                // Skip if no ROFO generated for this unit yet
                if (!$unitRofoData)
                    continue;

                $unitLandAdmin = DB::connection('sqlsrv')->table('landAdministration')
                    ->where('sub_application_id', $subApp->id)
                    ->first();

                $startDate = \Carbon\Carbon::parse($unitRofoData->start_date ?? now());
                $endDate = \Carbon\Carbon::parse($unitRofoData->end_date ?? now()->addYears(40));
                $totalYears = $unitRofoData->term_years ?? 40;
                $residualYears = $startDate->diffInYears($endDate);

                // --- Owner Name Logic ---
                $applicantType = strtolower(trim($subApp->applicant_type ?? ''));
                $ownerName = 'N/A';

                if ($applicantType === 'corporate' && !empty($subApp->corporate_name)) {
                    $ownerName = trim($subApp->corporate_name);
                } elseif ($applicantType === 'multiple' && !empty($subApp->multiple_owners_names)) {
                    $ownerArray = json_decode($subApp->multiple_owners_names, true);
                    $ownerName = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
                } elseif ($applicantType === 'individual' || empty($applicantType)) {
                    $nameParts = array_filter([
                        trim($subApp->applicant_title ?? ''),
                        trim($subApp->first_name ?? ''),
                        trim($subApp->surname ?? '')
                    ]);
                    $ownerName = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
                } else {
                    // Fallback logic
                    if (!empty($subApp->multiple_owners_names)) {
                        $ownerArray = json_decode($subApp->multiple_owners_names, true);
                        $ownerName = $ownerArray ? implode(', ', $ownerArray) : 'N/A';
                    } elseif (!empty($subApp->corporate_name)) {
                        $ownerName = trim($subApp->corporate_name);
                    } else {
                        $nameParts = array_filter([
                            trim($subApp->applicant_title ?? ''),
                            trim($subApp->first_name ?? ''),
                            trim($subApp->surname ?? '')
                        ]);
                        $ownerName = !empty($nameParts) ? implode(' ', $nameParts) : 'N/A';
                    }
                }
                $subApp->owner_name = $ownerName;
                // ------------------------

                $pageNo = $unitLandAdmin->page_no ?? '01';

                // Generate security code ONCE per unit (stays the same across all print types)
                $securityCodeService = app(\App\Services\SecurityCodeService::class);
                $securityCode = $securityCodeService->getOrGenerateForDocument(
                    $subApp->fileno,
                    $unitRofoData->id,
                    'ST ROFO'
                );

                $finalRofoData = [];
                foreach ((array) $unitRofoData as $key => $value) {
                    $finalRofoData[$key] = $value;
                }

                // Create an entry for EACH watermark in the sequence
                // Security code remains the same across all copies (Original, Duplicate, Triplicate)
                foreach ($watermarkSequence as $watermark) {
                    $batchRofos[] = [
                        'subApp' => $subApp,
                        'unitRofoData' => $unitRofoData,
                        'finalRofoData' => $finalRofoData,
                        'unitLandAdmin' => $unitLandAdmin,
                        'totalYears' => $totalYears,
                        'residualYears' => $residualYears,
                        'pageNo' => $pageNo,
                        'securityCode' => $securityCode,
                        'watermark' => $watermark,
                    ];
                }
            }

            if (empty($batchRofos)) {
                return redirect()->route('programmes.generate_rofo', $id)
                    ->with('info', 'No ROFOs have been generated for any unit in this application.');
            }

            return view('programmes.rofo_content', [
                'batchRofos' => $batchRofos,
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription,
                'isPrintRoute' => true,
                'autoPrint' => true,
                'currentWatermark' => $finalWatermarkForView,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in printROFO: ' . $e->getMessage());
            \Log::error('Error stack trace: ' . $e->getTraceAsString());
            return redirect()->route('programmes.view_rofo', $id)
                ->with('error', 'An error occurred while preparing the printable ROFO.');
        }
    }
}
