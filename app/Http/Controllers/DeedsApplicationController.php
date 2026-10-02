<?php

namespace App\Http\Controllers;

use App\Models\ConsentApplication;
use App\Models\DeedsApplication;
use App\Models\LandUseType;
use App\Models\Purpose;
use App\Models\StreetName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DeedsApplicationController extends Controller
{
    public function index(): View
    {
        $applications = ConsentApplication::where('application_type', 'application_for_censent')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Fetch ST Assignments from generated memos for APPROVED applications only
        $stMemos = DB::connection('sqlsrv')
            ->table('memos')
            ->join('mother_applications', 'memos.application_id', '=', 'mother_applications.id')
            ->leftJoin('users', 'memos.created_by', '=', 'users.id')
            ->where('mother_applications.application_status', 'Approved')
            ->where(function($query) {
                $query->where('memos.memo_status', 'GENERATED')
                      ->orWhereNull('memos.memo_status')
                      ->orWhere('memos.memo_status', '');
            })
            ->whereIn(DB::raw('LOWER(memos.memo_type)'), ['primary', 'physical_planning', 'physical planning'])
            ->select(
                'memos.id as memo_id',
                'memos.created_at',
                'mother_applications.id as mother_id',
                'mother_applications.fileno as file_number',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'memos.created_by',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name'
            )
            // Use groupBy to ensure one record per mother_id if multiple memos exist
            ->orderBy('memos.created_at', 'desc')
            ->get()
            ->unique('mother_id');

        $capturedInstruments = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->select('mlsFNo', 'temp_fileno', 'instrument_type')
            ->get();

        // Build an O(1) lookup map for extreme performance
        $instrumentMap = [];
        foreach ($capturedInstruments as $inst) {
            $instType = strtolower(trim($inst->instrument_type ?? ''));
            if (!empty($inst->mlsFNo)) {
                $instrumentMap[strtolower(trim($inst->mlsFNo))][] = $instType;
            }
            if (!empty($inst->temp_fileno)) {
                $instrumentMap[strtolower(trim($inst->temp_fileno))][] = $instType;
            }
        }

        foreach ($applications as $app) {
            $app->applicant_name = strtoupper((string)$app->applicant_name);
            $app->party_name = strtoupper((string)$app->party_name);
            
            // Check if registered in Instrument Capture
            $app->is_registered_in_instrument = false;
            if (!empty($app->file_number)) {
                $fileKey = strtolower(trim($app->file_number));
                if (isset($instrumentMap[$fileKey])) {
                    $consentType = strtolower(trim($app->consent_type ?? ''));
                    foreach ($instrumentMap[$fileKey] as $instType) {
                        if (str_contains($instType, $consentType) || str_contains($consentType, $instType)) {
                            $app->is_registered_in_instrument = true;
                            break;
                        }
                    }
                }
            }
        }

        foreach ($stMemos as $memo) {
            $party1 = $memo->corporate_name ?: trim("{$memo->applicant_title} {$memo->first_name} {$memo->surname}");
            $party1 = strtoupper($party1);
            
            // Fetch units from buyer_list first (standard for ST Memos)
            $buyers = DB::connection('sqlsrv')
                ->table('buyer_list')
                ->leftJoin('st_file_numbers', 'buyer_list.id', '=', 'st_file_numbers.buyer_list_id')
                ->where('buyer_list.application_id', $memo->mother_id)
                ->get(['buyer_list.id', 'buyer_list.buyer_title', 'buyer_list.buyer_name', 'buyer_list.unit_no', 'st_file_numbers.fileno as unit_fileno']);
                
            $unitsData = collect();
            
            foreach ($buyers as $buyer) {
                $unitsData->push([
                    'unit_number' => $buyer->unit_no,
                    'party2_name' => strtoupper(trim("{$buyer->buyer_title} {$buyer->buyer_name}")),
                    'unit_fileno' => $buyer->unit_fileno
                ]);
            }
            
            // Fallback/additional check for subapplications if buyer_list is empty
            if ($unitsData->isEmpty()) {
                $subs = DB::connection('sqlsrv')
                    ->table('subapplications')
                    ->leftJoin('st_file_numbers', 'subapplications.id', '=', 'st_file_numbers.subapplication_id')
                    ->where('subapplications.main_application_id', $memo->mother_id)
                    ->get(['subapplications.id', 'subapplications.applicant_title', 'subapplications.first_name', 'subapplications.surname', 'subapplications.corporate_name', 'subapplications.unit_number', 'st_file_numbers.fileno as unit_fileno']);
                    
                foreach ($subs as $sub) {
                    $unitsData->push([
                        'unit_number' => $sub->unit_number,
                        'party2_name' => strtoupper($sub->corporate_name ?: trim("{$sub->applicant_title} {$sub->first_name} {$sub->surname}")),
                        'unit_fileno' => $sub->unit_fileno
                    ]);
                }
            }
                
            $party2 = 'N/A';
            if ($unitsData->count() > 0) {
                $firstUnit = $unitsData->first();
                $firstUnitName = $firstUnit['party2_name'];
                
                if ($unitsData->count() > 1) {
                    $othersCount = $unitsData->count() - 1;
                    $party2 = "{$firstUnitName} & ({$othersCount}) Others";
                } else {
                    $party2 = $firstUnitName;
                }
            }
            
            // Create a ConsentApplication-like instance
            $stApp = new ConsentApplication();
            $stApp->setAttribute('id', 'memo_' . $memo->memo_id);
            $stApp->setAttribute('file_number', $memo->file_number);
            $stApp->setAttribute('consent_type', 'ST Assignment');
            $stApp->setAttribute('applicant_name', $party1);
            $stApp->setAttribute('party_name', $party2);
            $stApp->setAttribute('application_date', $memo->created_at);
            $stApp->setAttribute('created_at', \Carbon\Carbon::parse($memo->created_at));
            $stApp->setAttribute('print_count', 0);
            $stApp->setAttribute('status', 'Approved');
            $stApp->setAttribute('created_by', strtoupper(trim("{$memo->user_first_name} {$memo->user_last_name}")) ?: $memo->created_by);
            $stApp->setAttribute('st_units', $unitsData->toArray());
            $stApp->setAttribute('is_st_assignment', true);
            $stApp->setAttribute('mother_party1', $party1);
            $stApp->setAttribute('memo_id', $memo->memo_id);
            
            $applications->push($stApp);
        }
        
        // Re-sort the combined collection by created_at desc
        $applications = $applications->sortByDesc('created_at')->values();

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $landUseTypes = LandUseType::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $purposes = Purpose::query()->orderBy('name')->get(['id', 'landuseid', 'name']);

        $consentTodayCount = $applications->filter(function($app) {
            return $app->created_at->isToday();
        })->count();

        return view('deeds_applications.index', compact('applications', 'states', 'lgas', 'districts', 'streetNames', 'landUseTypes', 'purposes', 'consentTodayCount'));
    }

    public function create(): View
    {
        return view('deeds_applications.create', [
            'application' => new DeedsApplication(['status' => DeedsApplication::defaultStatus()]),
            'statusOptions' => DeedsApplication::STATUS_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $application = DeedsApplication::create($this->validatedData($request));

        return redirect()->route('deeds-applications.show', $application)->with('status', 'Application created.');
    }

    public function show(DeedsApplication $deedsApplication): View
    {
        return view('deeds_applications.show', ['application' => $deedsApplication]);
    }

    public function edit(DeedsApplication $deedsApplication): View
    {
        return view('deeds_applications.edit', [
            'application' => $deedsApplication,
            'statusOptions' => DeedsApplication::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request, DeedsApplication $deedsApplication): RedirectResponse
    {
        $deedsApplication->update($this->validatedData($request));

        return redirect()->route('deeds-applications.show', $deedsApplication)->with('status', 'Application updated.');
    }

    public function destroy(DeedsApplication $deedsApplication): RedirectResponse
    {
        $deedsApplication->delete();

        return redirect()->route('deeds-applications.index')->with('status', 'Application deleted.');
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'application_number' => ['required', 'string', 'max:50'],
            'file_number' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(DeedsApplication::STATUS_OPTIONS))],
            'submitted_by' => ['nullable', 'string', 'max:120'],
            'submitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    public function deleteMaster(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application = ConsentApplication::findOrFail($id);
            $fileNo = $application->file_number;

            // Formally log the action
            if (class_exists('\App\Services\AuditService')) {
                app(\App\Services\AuditService::class)->logAction(
                    'Deeds Master Delete',
                    'consent_applications',
                    $application->id,
                    null,
                    null,
                    "Deleted deeds consent application for file: {$fileNo}"
                );
            }

            // Physically delete the application
            $application->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Master record deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete master record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetPrintMaster(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application = ConsentApplication::findOrFail($id);
            $fileNo = $application->file_number;
            $previousPrintCount = $application->print_count;

            $application->print_count = 0;
            $application->save();

            // Formally log the action
            if (class_exists('\App\Services\AuditService')) {
                app(\App\Services\AuditService::class)->logAction(
                    'Deeds Master Print Reset',
                    'consent_applications',
                    $application->id,
                    null,
                    null,
                    "Reset print count from {$previousPrintCount} to 0 for file: {$fileNo}"
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Print count reset successfully. Editing is now allowed.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset print count: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteMasterBulk(Request $request): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid IDs provided.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $deletedCount = 0;
            foreach ($ids as $id) {
                $application = ConsentApplication::find($id);
                if ($application) {
                    $fileNo = $application->file_number;

                    // Formally log the action
                    if (class_exists('\App\Services\AuditService')) {
                        app(\App\Services\AuditService::class)->logAction(
                            'Deeds Master Delete Bulk',
                            'consent_applications',
                            $application->id,
                            null,
                            null,
                            "Deleted deeds consent application for file: {$fileNo} (Bulk)"
                        );
                    }

                    $application->delete();
                    $deletedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} master record(s)."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete bulk deletion: ' . $e->getMessage()
            ], 500);
        }
    }
}
