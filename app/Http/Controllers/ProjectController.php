<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorker;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ValuationCompensationFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    protected $auditService;
    protected $fileNumberService;

    public function __construct(AuditService $auditService, ValuationCompensationFileNumberService $fileNumberService)
    {
        $this->auditService = $auditService;
        $this->fileNumberService = $fileNumberService;
    }

    public function index()
    {
        $projects = Project::active()->with(['workers.user', 'subProjects'])->withCount(['valuations' => fn ($q) => $q->where('is_deleted', 0)])
            ->orderBy('created_at', 'desc')->get();
        
        // Fetch all workers from the VFC pool
        $workers = \App\Models\VfcWorker::with('user')
            ->where('is_active', true)
            ->get()
            ->map(function($w) {
                $w->user->vfc_worker_id = $w->vfc_worker_id;
                return $w->user;
            });

        // Location data
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
            
        $lgaNames = $lgas->pluck('LGAName')->toArray();

        $districts = DB::connection('sqlsrv')->table('districts')
            ->where('is_active', 1)
            ->whereNotIn('name', $lgaNames) // Filter out LGAs
            ->orderBy('name')
            ->get();

        $streets = \App\Models\StreetName::orderBy('name')->get();

        return view('valuation_compensations.projects.index', compact('projects', 'workers', 'lgas', 'districts', 'streets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string',
            'number_of_items' => 'required|integer|min:1',
            'project_type' => 'required|string',
            'project_fileno' => ($request->project_type !== 'Ministry' || $request->boolean('use_existing_fileno')) ? 'required|string' : 'nullable|string',
            'number_of_sub_projects' => 'required|integer|min:0',
            'our_reference' => 'required|string',
            'your_reference' => 'nullable|string',
            'addressed_to' => 'nullable|string',
            'worker_assignments' => 'required|array|min:1',
            'worker_assignments.*.user_id' => 'required|exists:sqlsrv.users,id',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $projectCode = $this->generateNextProjectCode();
            // Auto-generate for Ministry projects, unless the user chose to
            // continue with an existing (old) file number.
            $projectFileNo = ($request->project_type === 'Ministry' && !$request->boolean('use_existing_fileno'))
                ? $this->fileNumberService->generateNextFileNumber()
                : $request->project_fileno;

            $project = Project::create([
                'project_name' => $request->project_name,
                'project_code' => $projectCode,
                'project_fileno' => $projectFileNo,
                'number_of_items' => $request->number_of_items,
                'street' => $request->street,
                'district' => is_array($request->district) ? implode(', ', $request->district) : $request->district,
                'lga' => is_array($request->lga) ? implode(', ', $request->lga) : $request->lga,
                'state' => $request->state ?? 'Kano',
                'project_type' => $request->project_type,
                'project_type_other' => $request->project_type_other,
                'our_reference' => $request->our_reference,
                'your_reference' => $request->your_reference,
                'addressed_to' => $request->addressed_to,
                'user_id' => Auth::id(),
            ]);

            // Create Sub-Projects
            $subProjectNames = $request->sub_project_names ?? [];
            foreach ($subProjectNames as $index => $name) {
                $i = $index + 1;
                \App\Models\SubProject::create([
                    'project_id' => $project->id,
                    'name' => $name ?: "Sub-Project A{$i}",
                    'code' => "{$project->project_code}-A{$i}"
                ]);
            }

            foreach ($request->worker_assignments as $index => $assignment) {
                $workerIndex = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $workerCode = "WRK-{$project->project_code}-{$workerIndex}";

                ProjectWorker::create([
                    'project_id' => $project->id,
                    'user_id' => $assignment['user_id'],
                    'worker_code' => $workerCode,
                ]);
            }

            DB::connection('sqlsrv')->commit();

            $this->forgetMobileLookupCache();

            $this->auditService->logAction('CREATED', 'Project', $project->id, null, $project->toArray(), "Created project {$project->project_name}");

            return response()->json(['success' => true, 'message' => 'Project created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $project = Project::active()->findOrFail($id);
        $oldData = $project->toArray();

        $request->validate([
            'project_name' => 'required|string',
            'number_of_items' => 'required|integer|min:1',
            'project_type' => 'required|string',
            'number_of_sub_projects' => 'required|integer|min:0',
            'our_reference' => 'required|string',
            'your_reference' => 'nullable|string',
            'addressed_to' => 'nullable|string',
        ]);

        $project->update([
            'project_name' => $request->project_name,
            'number_of_items' => $request->number_of_items,
            'street' => $request->street,
            'district' => is_array($request->district) ? implode(', ', $request->district) : $request->district,
            'lga' => is_array($request->lga) ? implode(', ', $request->lga) : $request->lga,
            'state' => $request->state ?? 'Kano',
            'project_type' => $request->project_type,
            'project_type_other' => $request->project_type_other,
            'our_reference' => $request->our_reference,
            'your_reference' => $request->your_reference,
            'addressed_to' => $request->addressed_to,
        ]);

        // Sync Sub-Projects
        $subProjectNames = $request->sub_project_names ?? [];
        $existingSubProjects = $project->subProjects()->orderBy('id')->get();
        
        foreach ($subProjectNames as $index => $name) {
            $i = $index + 1;
            $code = "{$project->project_code}-A{$i}";
            if (isset($existingSubProjects[$index])) {
                // Update existing
                $existingSubProjects[$index]->update([
                    'name' => $name ?: "Sub-Project A{$i}",
                    'code' => $code
                ]);
            } else {
                // Create new
                \App\Models\SubProject::create([
                    'project_id' => $project->id,
                    'name' => $name ?: "Sub-Project A{$i}",
                    'code' => $code
                ]);
            }
        }
        
        // Remove extras if count decreased
        if (count($existingSubProjects) > count($subProjectNames)) {
            for ($i = count($subProjectNames); $i < count($existingSubProjects); $i++) {
                $existingSubProjects[$i]->delete();
            }
        }

        $this->forgetMobileLookupCache();

        $this->auditService->logAction('UPDATED', 'Project', $project->id, $oldData, $project->toArray(), "Updated project {$project->project_name}");

        return response()->json(['success' => true, 'message' => 'Project updated successfully.']);
    }

    public function getProjectsForSelection()
    {
        $projects = Project::active()->withCount(['valuations', 'workers'])->get()->map(function($p) {
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
                'addressed_to' => $p->addressed_to,
                'district' => $p->district,
                'lga' => $p->lga,
                'sub_projects' => $p->subProjects->map(function($sp) {
                    return [
                        'id' => $sp->id,
                        'name' => $sp->name,
                        'code' => $sp->code
                    ];
                })
            ];
        });

        return response()->json($projects);
    }

    public function getProjectWorkers($id)
    {
        $workers = ProjectWorker::where('project_id', $id)->with('user')->get();
        return response()->json($workers);
    }

    public function generateWorkerTemplates($id)
    {
        $project = Project::with('workers.user')->findOrFail($id);
        return view('valuation_compensations.projects.worker_template', compact('project'));
    }

    public function getNextCode()
    {
        return response()->json([
            'code' => $this->generateNextProjectCode(),
            'fileno' => $this->fileNumberService->peekNextFileNumber()
        ]);
    }

    public function addWorkerToProject(Request $request, $projectId)
    {
        $request->validate([
            'user_id' => 'required|exists:sqlsrv.users,id',
        ]);

        $project = Project::active()->findOrFail($projectId);
        
        // Check if already assigned
        $exists = ProjectWorker::where('project_id', $projectId)->where('user_id', $request->user_id)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Worker already assigned to this project.']);
        }

        // Generate worker code
        $count = ProjectWorker::where('project_id', $projectId)->count();
        $workerIndex = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $workerCode = "WRK-{$project->project_code}-{$workerIndex}";

        $worker = ProjectWorker::create([
            'project_id' => $projectId,
            'user_id' => $request->user_id,
            'worker_code' => $workerCode,
        ]);

        $this->auditService->logAction('UPDATED', 'Project', $projectId, null, $worker->toArray(), "Assigned worker {$workerCode} to project {$project->project_name}");

        return response()->json(['success' => true, 'message' => 'Worker assigned successfully.', 'worker' => $worker->load('user')]);
    }

    public function removeWorkerFromProject($projectId, $workerId)
    {
        try {
            $assignment = ProjectWorker::where('project_id', $projectId)->where('id', $workerId)->firstOrFail();
            $oldData = $assignment->toArray();
            $assignment->delete();

            $this->auditService->logAction('UPDATED', 'Project', $projectId, $oldData, null, "Removed worker from project");

            return response()->json(['success' => true, 'message' => 'Worker removed from project.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * What a delete would take with it — drives the confirmation warning so the
     * user sees the valuation count BEFORE they confirm.
     */
    public function deleteSummary($id)
    {
        $project = Project::active()->withCount(['workers'])->findOrFail($id);

        $valuations = \App\Models\ValuationCompensation::where('project_id', $project->id)
            ->where('is_deleted', 0)
            ->count();

        return response()->json([
            'success' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->project_name,
                'code' => $project->project_code,
                'fileno' => $project->project_fileno,
            ],
            'valuations_count' => $valuations,
            'workers_count' => $project->workers_count,
            'sub_projects_count' => $project->subProjects()->count(),
        ]);
    }

    /**
     * Delete an unwanted project.
     *
     * This is a soft delete on both levels: the project is flagged
     * `is_deleted` and every valuation captured against it is flagged the same
     * way, so the whole set disappears from the VFC console and the mobile app
     * together but stays recoverable. Worker assignments and sub-projects are
     * left in place — they are meaningless without the project and are needed
     * if it is ever restored.
     */
    public function destroy(Request $request, $id)
    {
        $project = Project::active()->findOrFail($id);
        $oldData = $project->toArray();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $valuationIds = \App\Models\ValuationCompensation::where('project_id', $project->id)
                ->where('is_deleted', 0)
                ->pluck('id');

            if ($valuationIds->isNotEmpty()) {
                \App\Models\ValuationCompensation::whereIn('id', $valuationIds)
                    ->update(['is_deleted' => 1]);
            }

            $project->update([
                'is_deleted' => 1,
                'deleted_at' => now(),
                'deleted_by' => Auth::id(),
            ]);

            DB::connection('sqlsrv')->commit();
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $this->forgetMobileLookupCache();

        $this->auditService->logAction(
            'DELETED',
            'Project',
            $project->id,
            $oldData,
            ['is_deleted' => 1],
            "Deleted project {$project->project_name} ({$project->project_code}) and {$valuationIds->count()} valuation(s) captured against it"
        );

        return response()->json([
            'success' => true,
            'message' => $valuationIds->count() > 0
                ? "Project deleted along with {$valuationIds->count()} valuation record(s)."
                : 'Project deleted successfully.',
            'valuations_deleted' => $valuationIds->count(),
        ]);
    }

    /**
     * The mobile app reads its project list from a 24h cache; anything that
     * adds, renames or deletes a project has to drop it or the handsets keep
     * capturing against stale projects for a day.
     */
    private function forgetMobileLookupCache(): void
    {
        Cache::forget('vfc_mobile_lookup_data_v2');
    }

    private function generateNextProjectCode()
    {
        $year = \Carbon\Carbon::now()->year;
        
        // Get the next identity value for vfc_projects
        $result = DB::connection('sqlsrv')
            ->select("SELECT IDENT_CURRENT('vfc_projects') + IDENT_INCR('vfc_projects') as next_id");
            
        $nextId = $result[0]->next_id ?? 1;
        $serial = str_pad($nextId, 4, '0', STR_PAD_LEFT);

        return "PRJ-{$year}-{$serial}";
    }
}
