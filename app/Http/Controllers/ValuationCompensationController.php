<?php

namespace App\Http\Controllers;

use App\Models\ValuationCompensation;
use App\Models\BuildingType;
use App\Models\PrintLog;
use App\Services\AuditService;
use App\Services\ValuationCompensationFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ValuationCompensationController extends Controller
{
    protected $auditService;
    protected $fileNumberService;

    public function __construct(AuditService $auditService, ValuationCompensationFileNumberService $fileNumberService)
    {
        $this->auditService = $auditService;
        $this->fileNumberService = $fileNumberService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $records = ValuationCompensation::active()
            ->with(['project', 'subProject'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('project_id');

        // Statistics
        $stats = [
            'total_valuations' => ValuationCompensation::active()->count(),
            'total_amount' => ValuationCompensation::active()->sum('compensation_amount'),
            'total_buildings' => ValuationCompensation::active()->sum('building_count'),
            'today_count' => ValuationCompensation::active()->whereDate('created_at', Carbon::today())->count(),
        ];

        // Fetch Location Data for the Modal Form
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $lgaNames = $lgas->pluck('LGAName')->toArray();

        $districts = DB::connection('sqlsrv')
            ->table('districts')
            ->where('is_active', 1)
            ->whereNotIn('name', $lgaNames) // Filter out LGAs
            ->orderBy('name')
            ->get();

        $buildingTypes = BuildingType::active()
            ->orderByRaw("CASE WHEN name IN ('Other', 'Others') THEN 1 ELSE 0 END ASC")
            ->orderBy('name')
            ->get();

        $streets = \App\Models\StreetName::orderBy('name')->get();

        $valuationItems = DB::connection('sqlsrv')->table('vfc_valuation_items')
            ->where('is_active', 1)
            ->orderByRaw("CASE WHEN name IN ('Other', 'Others') THEN 1 ELSE 0 END ASC")
            ->orderBy('name')
            ->get();

        $banks = \App\Models\VfcBank::orderBy('name')->get()->map(function ($b) {
            return [
                'title' => $b->name,
                'route' => asset('assets/' . $b->logo),
                'code' => $b->code
            ];
        });
        $PageTitle = "Valuation for Compensation";

        return view('valuation_compensations.index', compact('records', 'stats', 'PageTitle', 'states', 'lgas', 'districts', 'buildingTypes', 'streets', 'banks', 'valuationItems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('valuation_compensations.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'our_ref' => 'nullable|string',
            'your_ref' => 'nullable|string',
            'valuation_date' => 'required|date',
            'location' => 'required|string',
            'owner_name' => 'required|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'building_type' => 'nullable|string',
            'building_count' => 'nullable|integer|min:0',
            'area_covered' => 'nullable|numeric|min:0',
            'rate_of_cost' => 'nullable|numeric|min:0',
            'compensation_amount' => 'nullable|numeric|min:0',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
            'phone_number' => 'required|string',
            'nin' => 'nullable|string',
            'remarks' => 'nullable|string',
            'compensated_items' => 'nullable|string',
            'compensated_items_other' => 'nullable|string',
            'completion_stage' => 'nullable|string',
            'number_of_floors' => 'nullable|string',
            'project_id' => 'required|exists:sqlsrv.vfc_projects,id',
            'sub_project_id' => 'nullable|exists:sqlsrv.vfc_sub_projects,id',
            'project_fileno' => 'nullable|string',
            'worker_id' => 'required|string',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
        ]);

        $data['user_id'] = Auth::id();

        // Auto-generate file number if not provided
        if (empty($data['our_ref'])) {
            $project = \App\Models\Project::active()->findOrFail($data['project_id']);
            $data['our_ref'] = $project->project_fileno;
            $data['apply_percentage'] = $project->apply_percentage; // Capture current project percentage
        } else {
            // Even if ref exists, ensure we capture the project percentage for new records
            $project = \App\Models\Project::active()->find($data['project_id']);
            if ($project)
                $data['apply_percentage'] = $project->apply_percentage;
        }

        $record = ValuationCompensation::create($data);

        // Audit log
        $this->auditService->logAction(
            'CREATED',
            'ValuationCompensation',
            $record->id,
            null,
            $record->toArray(),
            'Created valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation recorded successfully.',
            'id' => $record->id
        ]);
    }

    /**
     * Store multiple records in batch.
     */
    public function batchStore(Request $request)
    {
        $request->validate([
            'records' => 'required|array|min:1',
        ]);

        $createdCount = 0;
        DB::beginTransaction();
        try {
            foreach ($request->records as $data) {
                $data['user_id'] = Auth::id();

                $project = \App\Models\Project::active()->find($data['project_id']);
                if ($project) {
                    $data['apply_percentage'] = $project->apply_percentage;
                    if (empty($data['our_ref'])) {
                        $data['our_ref'] = $project->project_fileno;
                    }
                }

                $record = ValuationCompensation::create($data);

                $this->auditService->logAction(
                    'BATCH_CREATED',
                    'ValuationCompensation',
                    $record->id,
                    null,
                    $record->toArray(),
                    'Batch created valuation for ' . $record->owner_name
                );

                $createdCount++;
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully saved {$createdCount} records in batch."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        return view('valuation_compensations.form', compact('record'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        $oldData = $record->toArray();

        $data = $request->validate([
            'our_ref' => 'nullable|string',
            'your_ref' => 'nullable|string',
            'valuation_date' => 'required|date',
            'location' => 'required|string',
            'owner_name' => 'required|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'building_type' => 'nullable|string',
            'building_count' => 'nullable|integer|min:0',
            'area_covered' => 'nullable|numeric|min:0',
            'rate_of_cost' => 'nullable|numeric|min:0',
            'compensation_amount' => 'nullable|numeric|min:0',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
            'phone_number' => 'required|string',
            'nin' => 'nullable|string',
            'remarks' => 'nullable|string',
            'compensated_items' => 'nullable|string',
            'compensated_items_other' => 'nullable|string',
            'completion_stage' => 'nullable|string',
            'number_of_floors' => 'nullable|string',
            'project_id' => 'required|exists:sqlsrv.vfc_projects,id',
            'sub_project_id' => 'nullable|exists:sqlsrv.vfc_sub_projects,id',
            'project_fileno' => 'nullable|string',
            'worker_id' => 'required|string',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
        ]);

        $record->update($data);

        // Audit log
        $this->auditService->logAction(
            'UPDATED',
            'ValuationCompensation',
            $record->id,
            $oldData,
            $record->toArray(),
            'Updated valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation updated successfully.',
            'id' => $record->id
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        return view('valuation_compensations.template', compact('record'));
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        $oldData = $record->toArray();

        $record->update(['is_deleted' => 1]);

        // Audit log
        $this->auditService->logAction(
            'DELETED',
            'ValuationCompensation',
            $record->id,
            $oldData,
            ['is_deleted' => 1],
            'Soft deleted valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation deleted successfully.'
        ]);
    }

    /**
     * Log print action.
     */
    public function logPrint(Request $request, $id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $record->our_ref ?? 'COMP-' . $record->id,
                'document_type' => 'Valuation for Compensation',
                'print_type' => 'Individual',
                'status' => 'Original',
                'user_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Print all records for a project in a single template.
     */
    public function projectPrint(\Illuminate\Http\Request $request, $projectId)
    {
        $records = ValuationCompensation::active()
            ->where('project_id', $projectId)
            ->with(['project', 'subProject'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return redirect()->back()->with('error', 'No records found for this project.');
        }

        $project = $records->first()->project;
        // A supplied 0 is a real choice ("no adjustment"), so only treat a missing
        // or non-numeric value as "fall back to the project's stored percentage".
        $requestedPercentage = $request->query('apply_percentage');
        $customPercentage = is_numeric($requestedPercentage) ? (float) $requestedPercentage : null;

        return view('valuation_compensations.batch_template', compact('records', 'project', 'customPercentage'));
    }
}