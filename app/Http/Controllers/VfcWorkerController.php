<?php

namespace App\Http\Controllers;

use App\Models\VfcWorker;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VfcWorkerController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function getNextId()
    {
        return response()->json(['next_id' => $this->generateNextWorkerId()]);
    }

    public function index()
    {
        $workers = VfcWorker::with('user')
            ->withCount('assignments')
            ->orderBy('vfc_worker_id')
            ->get();
        
        // Fetch users who are not already in the pool
        $assignedUserIds = $workers->pluck('user_id')->toArray();
        $availableUsers = User::whereNotIn('id', $assignedUserIds)
            ->where('staff_type_category', '!=', 'MDCM')
            ->orderBy('first_name')
            ->get();

        return view('valuation_compensations.workers.index', compact('workers', 'availableUsers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:sqlsrv.users,id|unique:sqlsrv.vfc_workers,user_id',
        ]);

        try {
            $vfcWorkerId = $this->generateNextWorkerId();
            
            $worker = VfcWorker::create([
                'user_id' => $request->user_id,
                'vfc_worker_id' => $vfcWorkerId,
                'is_active' => true,
            ]);

            $this->auditService->logAction('CREATED', 'VfcWorker', $worker->id, null, $worker->toArray(), "Added worker {$vfcWorkerId} to VFC pool");

            return response()->json(['success' => true, 'message' => 'Worker added to pool successfully.', 'worker' => $worker->load('user')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Edit an officer in the pool — retirement, transfer or a change of
     * schedule. The officer is deactivated rather than removed: an inactive
     * worker stops being offered for new project assignments (see
     * ProjectController@index, which only lists active workers) while their
     * vfc_worker_id stays attached to everything they already captured.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
            'deactivation_reason' => 'nullable|string|max:255',
        ]);

        try {
            $worker = VfcWorker::with('user')->findOrFail($id);
            $oldData = $worker->toArray();

            $isActive = $request->boolean('is_active');

            $worker->update([
                'is_active' => $isActive,
                'deactivated_at' => $isActive ? null : ($worker->deactivated_at ?? now()),
                'deactivation_reason' => $isActive ? null : $request->deactivation_reason,
            ]);

            $name = trim(($worker->user->first_name ?? '') . ' ' . ($worker->user->last_name ?? '')) ?: $worker->vfc_worker_id;
            $state = $isActive ? 'Reactivated' : 'Deactivated';

            $this->auditService->logAction(
                'UPDATED',
                'VfcWorker',
                $worker->id,
                $oldData,
                $worker->toArray(),
                "{$state} VFC worker {$worker->vfc_worker_id} ({$name})"
                    . ($isActive || !$request->deactivation_reason ? '' : " - {$request->deactivation_reason}")
            );

            return response()->json([
                'success' => true,
                'message' => $isActive
                    ? 'Worker reactivated and available for new project assignments.'
                    : 'Worker deactivated. They will no longer appear for new project assignments.',
                'worker' => $worker->load('user'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $worker = VfcWorker::with('user')->findOrFail($id);
            $oldData = $worker->toArray();

            // Removing an officer who is still on live projects loses the pool
            // record behind their worker_code. Make the caller confirm it, and
            // point them at deactivation, which keeps that trail.
            $assignments = $worker->assignments()->count();
            if ($assignments > 0 && !$request->boolean('force')) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'assignments_count' => $assignments,
                    'message' => "This officer is still assigned to {$assignments} project(s). "
                        . 'Deactivate them instead to keep the record, or confirm to remove them from the pool.',
                ], 409);
            }

            $worker->delete();

            $this->auditService->logAction('DELETED', 'VfcWorker', $id, $oldData, null, "Removed worker {$oldData['vfc_worker_id']} from VFC pool");

            return response()->json(['success' => true, 'message' => 'Worker removed from pool.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function generateNextWorkerId()
    {
        $year = date('Y');
        $last = VfcWorker::where('vfc_worker_id', 'like', "WRK-PRJ-{$year}-%")
            ->orderBy('vfc_worker_id', 'desc')
            ->first();
            
        if (!$last) {
            return "WRK-PRJ-{$year}-001";
        }

        $lastId = $last->vfc_worker_id;
        $parts = explode('-', $lastId);
        $num = (int) end($parts);
        $nextNum = str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        
        return "WRK-PRJ-{$year}-{$nextNum}";
    }
}
