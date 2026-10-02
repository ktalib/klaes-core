<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Fixed batch data route
// Fixed batch data route
Route::get('/instrument_registration/get-batch-data-fixed', function (Request $request) {
    try {
        $filter = $request->query('filter');
        $data = collect();

        // 1. Fetch from instrument_registration (Generic Instruments)
        // If filter is empty or matches a generic type (or 'batch'/'other')
        if (empty($filter) || $filter === 'batch' || $filter === 'other' || !in_array($filter, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO', 'ST Fragmentation'])) {
            $query = DB::connection('sqlsrv')->table('instrument_registration')
                ->where(function ($q) {
                    $q->where('status', '!=', 'registered')
                        ->orWhereNull('status');
                })
                ->whereNull('particularsRegistrationNumber')
                ->whereNull('STM_Ref')
                ->select(
                    'id',
                    DB::raw("COALESCE(MLSFileNo, KAGISFileNO, NewKANGISFileNo) as fileno"),
                    'instrument_type',
                    'Grantor as grantor',
                    'Grantee as grantee',
                    'lga',
                    'district',
                    'size',
                    'plotNumber',
                    'created_at',
                    DB::raw("'pending' as status"),
                    DB::raw("'Other Instruments' as source_type")
                );

            if (!empty($filter) && $filter !== 'batch' && $filter !== 'other') {
                $query->where('instrument_type', $filter);
            }

            $genericInstruments = $query->get();

            foreach ($genericInstruments as $instrument) {
                $data->push((object) [
                    'id' => 'instr_reg_' . $instrument->id,
                    'fileno' => $instrument->fileno,
                    'instrument_type' => $instrument->instrument_type,
                    'grantor' => $instrument->grantor,
                    'grantee' => $instrument->grantee,
                    'lga' => $instrument->lga,
                    'district' => $instrument->district,
                    'size' => $instrument->size,
                    'plotNumber' => $instrument->plotNumber,
                    'created_at' => $instrument->created_at,
                    'status' => 'pending',
                    'source_type' => 'Other Instruments'
                ]);
            }

            // 1b. Fetch from instrument_capture
            $capQuery = DB::connection('sqlsrv')->table('instrument_capture')
                ->where(function ($q) {
                    $q->where('is_deed_registered', 0)
                        ->orWhereNull('is_deed_registered');
                })
                ->select(
                    'id',
                    DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as fileno"),
                    'instrument_type',
                    'party_1_name as grantor',
                    'party_2_name as grantee',
                    'lga',
                    'district',
                    'plot_size as size',
                    'plot_number as plotNumber',
                    'created_at',
                    DB::raw("'pending' as status"),
                    DB::raw("'Captured' as source_type")
                );

            $mapping = [
                'Power of Attorney' => 'Power of Attorney',
                'Irrevocable Power of Attorney' => 'Power of Attorney',
                'Deed of Mortgage' => 'Deed of Mortgage',
                'Tripartite Mortgage' => 'Deed of Mortgage',
                'Deed of Assignment' => 'Deed of Assignment',
                'Deed of Lease' => 'Deed of Lease',
                'Deed of Sub-Lease' => 'Sub Lease',
                'Deed of Surrender / Release' => 'Deed of Surrender and Release',
                'Occupancy Permit' => 'Occupancy Permit (OP)',
                'Devolution Order' => 'Devolution Order',
                'Deed of Gift' => 'Deed of Gift',
                'Deed of Sub-Division' => 'Deed of Sub-Division',
                'Deed of Merger' => 'Deed of Merger',
            ];

            $capturedInstruments = $capQuery->get();
            foreach ($capturedInstruments as $cap) {
                $type = $mapping[$cap->instrument_type] ?? $cap->instrument_type;

                // If filter is active, check against mapped type
                if (!empty($filter) && $filter !== 'batch' && $filter !== 'other' && $type !== $filter) {
                    continue;
                }

                $data->push((object) [
                    'id' => 'instr_capture_' . $cap->id,
                    'fileno' => $cap->fileno,
                    'instrument_type' => $type,
                    'grantor' => $cap->grantor,
                    'grantee' => $cap->grantee,
                    'lga' => $cap->lga,
                    'district' => $cap->district,
                    'size' => $cap->size,
                    'plotNumber' => $cap->plotNumber,
                    'created_at' => $cap->created_at,
                    'status' => 'pending',
                    'source_type' => 'Captured'
                ]);
            }
        }

        // 2. Fetch from Subapplications (ST Related) based on Filter
        // We only check subapplications if the filter is specific to ST types or is 'batch' (all)
        $checkStAssignment = empty($filter) || $filter === 'batch' || $filter === 'ST Assignment (Transfer of Title)' || $filter === 'stAssignment';
        $checkSectional = empty($filter) || $filter === 'batch' || $filter === 'Sectional Titling CofO' || $filter === 'sectional';

        // ST Fragmentation is now handled automatically and hidden from manual registration lists
        $checkFragmentation = false;

        // Helper to query subapps
        $querySubApps = function ($instrumentType) {
            return DB::connection('sqlsrv')->table('subapplications as s')
                ->leftJoin('mother_applications as m', 's.main_application_id', '=', 'm.id')
                ->leftJoin('registered_instruments as ri', function ($join) use ($instrumentType) {
                    $join->on('s.fileno', '=', 'ri.StFileNo')
                        ->where('ri.instrument_type', '=', $instrumentType)
                        ->where('ri.status', '=', 'registered');
                })
                ->where('s.application_status', 'Approved')
                ->where(function ($query) {
                    $query->where('s.planning_recommendation_status', 'Approved')
                        ->orWhere(function ($q) {
                            $q->where('m.planning_recommendation_status', 'Approved')
                                ->where('s.is_sua_unit', 0);
                        });
                })
                ->whereNull('ri.id')
                ->select(
                    's.id',
                    's.fileno',
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN s.corporate_name IS NOT NULL AND s.corporate_name <> '' THEN s.corporate_name
                        WHEN s.multiple_owners_names IS NOT NULL AND s.multiple_owners_names <> '' THEN s.multiple_owners_names
                        ELSE CONCAT(COALESCE(s.applicant_title,''), ' ', COALESCE(s.first_name,''), ' ', COALESCE(s.middle_name,''), ' ', COALESCE(s.surname,''))
                    END, '  ', ' ')) as sub_applicant"),
                    DB::raw("TRIM(REPLACE(CASE 
                        WHEN m.corporate_name IS NOT NULL AND m.corporate_name <> '' THEN m.corporate_name
                        WHEN m.multiple_owners_names IS NOT NULL AND m.multiple_owners_names <> '' THEN m.multiple_owners_names
                        ELSE CONCAT(COALESCE(m.applicant_title,''), ' ', COALESCE(m.first_name,''), ' ', COALESCE(m.middle_name,''), ' ', COALESCE(m.surname,''))
                    END, '  ', ' ')) as mother_applicant"),
                    'm.property_lga as lga',
                    'm.property_district as district',
                    'm.plot_size as size',
                    'm.property_plot_no as plotNumber',
                    's.created_at'
                )
                ->get();
        };

        if ($checkStAssignment) {
            $stAssignments = $querySubApps('ST Assignment (Transfer of Title)');

            // Files with ST Fragmentation registered are proven same-owner (express)
            // ST Assignment for these should be auto-registered, not shown in the modal
            $registeredFragFiles = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('StFileNo')
                ->filter()
                ->map(fn($v) => strtolower(trim($v)))
                ->toArray();

            // Also check MLSFileNo for fragmentation records
            $registeredFragMLSFiles = DB::connection('sqlsrv')->table('registered_instruments')
                ->where('instrument_type', 'ST Fragmentation')
                ->where('status', 'registered')
                ->pluck('MLSFileNo')
                ->filter()
                ->map(fn($v) => strtolower(trim($v)))
                ->toArray();

            $allFragFiles = array_unique(array_merge($registeredFragFiles, $registeredFragMLSFiles));

            foreach ($stAssignments as $subApp) {
                $fileKey = strtolower(trim($subApp->fileno));

                // Skip if ST Fragmentation is registered for this file (proves same-owner/express)
                if (in_array($fileKey, $allFragFiles)) {
                    continue;
                }

                // Also skip if names match exactly (original express check)
                $motherName = strtolower(trim(str_replace('  ', ' ', $subApp->mother_applicant)));
                $subName = strtolower(trim(str_replace('  ', ' ', $subApp->sub_applicant)));

                if ($motherName === $subName && !empty($motherName)) {
                    continue; // Skip express assignments
                }

                $data->push((object) [
                    'id' => $subApp->id . '_st_assignment',
                    'fileno' => $subApp->fileno,
                    'instrument_type' => 'ST Assignment (Transfer of Title)',
                    'grantor' => $subApp->mother_applicant,
                    'grantee' => $subApp->sub_applicant,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'size' => $subApp->size,
                    'plotNumber' => $subApp->plotNumber,
                    'created_at' => $subApp->created_at,
                    'status' => 'pending',
                    'source_type' => 'ST Assignment'
                ]);
            }
        }

        if ($checkSectional) {
            $sectionalCofOs = $querySubApps('Sectional Titling CofO');
            foreach ($sectionalCofOs as $subApp) {
                $data->push((object) [
                    'id' => $subApp->id . '_sectional_cofo',
                    'fileno' => $subApp->fileno,
                    'instrument_type' => 'Sectional Titling CofO',
                    'grantor' => 'Kano State Government',
                    'grantee' => $subApp->sub_applicant,
                    'lga' => $subApp->lga,
                    'district' => $subApp->district,
                    'size' => $subApp->size,
                    'plotNumber' => $subApp->plotNumber,
                    'created_at' => $subApp->created_at,
                    'status' => 'pending',
                    'source_type' => 'Sectional Titling'
                ]);
            }
        }

        Log::info('Fixed batch data retrieved (Refactored)', [
            'filter' => $filter,
            'count' => $data->count(),
            'sample_data' => $data->take(3)->toArray()
        ]);

        return response()->json($data->values()->toArray()); // Ensure strict array re-indexing

    } catch (\Exception $e) {
        Log::error('Error in fixed getBatchData', [
            'filter' => $request->query('filter'),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'Failed to retrieve batch data: ' . $e->getMessage()
        ], 500);
    }
});