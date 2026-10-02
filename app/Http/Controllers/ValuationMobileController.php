<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorker;
use App\Models\ValuationCompensation;
use App\Models\BuildingType;
use App\Models\StreetName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ValuationMobileController extends Controller
{
    /**
     * Show the mobile valuation form.
     */
    public function index()
    {
        $user = Auth::user();
        return view('valuation_compensations.mobile', compact('user'));
    }

    /**
     * Get data for mobile lookups.
     */
    public function getLookupData()
    {
        return Cache::remember('vfc_mobile_lookup_data_v2', 60 * 60 * 24, function () {
            $projects = Project::active()
                ->select('id', 'project_name', 'project_code', 'project_fileno', 'number_of_items', 'our_reference', 'your_reference')
                ->with([
                    'subProjects' => function ($q) {
                        $q->select('id', 'project_id', 'name', 'code');
                    }
                ])
                ->withCount(['valuations', 'workers'])
                ->orderBy('project_name')
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->project_name,
                        'code' => $p->project_code,
                        'fileno' => $p->project_fileno,
                        'total_items' => $p->number_of_items,
                        'valuations_count' => $p->valuations_count,
                        'workers_count' => $p->workers_count,
                        'our_reference' => $p->our_reference,
                        'your_reference' => $p->your_reference,
                        'sub_projects' => $p->subProjects->map(function ($sp) {
                            return [
                                'id' => $sp->id,
                                'name' => $sp->name,
                                'code' => $sp->code
                            ];
                        })
                    ];
                });

            $buildingTypes = BuildingType::active()
                ->orderByRaw("CASE WHEN name IN ('Other', 'Others') THEN 1 ELSE 0 END ASC")
                ->orderBy('name')
                ->get();
            $streets = StreetName::orderBy('name')->get();

            $lgas = DB::connection('sqlsrv')->table('StatLGAs')
                ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
                ->where('States.StateName', 'Kano')
                ->orderBy('LGAName')
                ->get();
            $lgaNames = $lgas->pluck('LGAName')->map(fn ($name) => strtoupper(trim((string) $name)))->all();
            $districts = DB::connection('sqlsrv')
                ->table('districts')
                ->where('is_active', 1)
                ->whereNotIn(DB::raw('UPPER(LTRIM(RTRIM(name)))'), $lgaNames)
                ->orderBy('name')
                ->get();

            $banks = \App\Models\VfcBank::orderBy('name')->get()->map(function ($b) {
                return [
                    'name' => $b->name,
                    'logo' => asset('assets/' . $b->logo),
                    'code' => $b->code
                ];
            });

            $valuationItems = DB::connection('sqlsrv')->table('vfc_valuation_items')
                ->where('is_active', 1)
                ->orderByRaw("CASE WHEN name IN ('Other', 'Others') THEN 1 ELSE 0 END ASC")
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'projects' => $projects,
                'buildingTypes' => $buildingTypes,
                'streets' => $streets,
                'lgas' => $lgas,
                'districts' => $districts,
                'banks' => $banks,
                'valuationItems' => $valuationItems
            ]);
        });
    }

    /**
     * Get workers for a specific project.
     */
    public function getProjectWorkers($projectId)
    {
        $workers = ProjectWorker::where('project_id', $projectId)
            ->with('user')
            ->get()
            ->map(function ($w) {
                return [
                    'id' => $w->id,
                    'worker_code' => $w->worker_code,
                    'name' => trim($w->user->first_name . ' ' . $w->user->last_name),
                ];
            });

        return response()->json([
            'success' => true,
            'workers' => $workers
        ]);
    }

    /**
     * Store a new valuation record from mobile.
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:sqlsrv.vfc_projects,id',
            'worker_id' => 'required|exists:sqlsrv.vfc_project_workers,id',
            'owner_name' => 'required|string',
            'compensation_amount' => 'required|numeric|min:0',
            'valuation_date' => 'required|date',
            'lga' => 'required',
            'location' => 'required',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'plot_no' => 'required|string',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $project = \App\Models\Project::active()->findOrFail($request->project_id);
            $fileNumber = $project->project_fileno;

            // Handle 'Other' building type - now comma separated
            $buildingType = $request->building_type;
            
            // Handle completion stage - now comma separated
            $completionStage = $request->completion_stage;

            // Handle 'Other' compensated items
            $compItems = $request->compensated_items;
            if ($request->compensated_items_other) {
                $itemsArray = explode(', ', $compItems);
                // Remove 'Other' if present in the string
                $itemsArray = array_filter($itemsArray, fn($i) => $i !== 'Other');
                $itemsArray[] = $request->compensated_items_other;
                $compItems = implode(', ', array_unique($itemsArray));
            }

            $record = ValuationCompensation::create([
                'project_id' => $request->project_id,
                'worker_id' => $request->worker_id,
                'file_number' => $fileNumber,
                'our_ref' => $request->our_ref ?? $fileNumber,
                'your_ref' => $request->your_ref,
                'owner_name' => $request->owner_name,
                'valuation_date' => $request->valuation_date,
                'building_type' => $buildingType,
                'building_count' => $request->building_count ?? 1,
                'completion_stage' => $completionStage,
                'number_of_floors' => $request->number_of_floors,
                'length' => $request->length,
                'breadth' => $request->breadth,
                'area_covered' => $request->area_covered,
                'rate_of_cost' => $request->rate_of_cost,
                'compensation_amount' => $request->compensation_amount,
                'compensated_items' => $compItems,
                'compensated_items_other' => $request->compensated_items_other,
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name,
                'phone_number' => $request->phone_number,
                'nin' => $request->nin,
                'remarks' => $request->remarks,
                'plot_no' => $request->plot_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state ?? 'Kano',
                'location' => $request->location,
                'user_id' => Auth::id(),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Valuation record saved successfully!',
                'file_number' => $fileNumber
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
