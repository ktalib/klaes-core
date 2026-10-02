<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\FileIndexing;
use App\Models\PageTyping;
use Exception;

class PTQController extends Controller
{
    /**
     * Display the PTQ Control dashboard
     */
    public function index()
    {
        try {
            $PageTitle = 'PTQ Control';
            $PageDescription = 'Quality Control for Page Typing';
            
            $stats = [
                'pending_count' => $this->getPendingQCCount(),
                'in_progress_count' => $this->getInProgressQCCount(),
                'completed_count' => $this->getCompletedQCCount(),
            ];

            return view('ptq-control.index', compact('PageTitle', 'PageDescription', 'stats'));
        } catch (Exception $e) {
            Log::error('Error loading PTQ Control dashboard', [
                'error' => $e->getMessage()
            ]);
            
            return view('ptq-control.index', [
                'PageTitle' => 'PTQ Control',
                'PageDescription' => 'Quality Control for Page Typing',
                'stats' => [
                    'pending_count' => 0,
                    'in_progress_count' => 0,
                    'completed_count' => 0,
                ]
            ]);
        }
    }

    /**
     * Get pending QC files
     */
    public function listPending(Request $request)
    {
        try {
            // Implementation for listing pending QC files
            $pendingFiles = FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where('qc_status', '!=', 'completed')
                ->paginate(20);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $pendingFiles
                ]);
            }

            return view('ptq-control.pending', compact('pendingFiles'));
        } catch (Exception $e) {
            Log::error('Error getting pending QC files', ['error' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading pending files'
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Error loading pending files');
        }
    }

    /**
     * Get in-progress QC files
     */
    public function listInProgress(Request $request)
    {
        try {
            $inProgressFiles = FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where('qc_status', 'in_progress')
                ->paginate(20);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $inProgressFiles
                ]);
            }

            return view('ptq-control.in-progress', compact('inProgressFiles'));
        } catch (Exception $e) {
            Log::error('Error getting in-progress QC files', ['error' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading in-progress files'
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Error loading in-progress files');
        }
    }

    /**
     * Get completed QC files
     */
    public function listCompleted(Request $request)
    {
        try {
            $completedFiles = FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where('qc_status', 'completed')
                ->paginate(20);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $completedFiles
                ]);
            }

            return view('ptq-control.completed', compact('completedFiles'));
        } catch (Exception $e) {
            Log::error('Error getting completed QC files', ['error' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading completed files'
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Error loading completed files');
        }
    }

    /**
     * Get QC details for a specific file
     */
    public function getQCDetails(Request $request, $fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')
                ->with(['pagetypings', 'scannings'])
                ->find($fileIndexingId);

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $fileIndexing
            ]);
        } catch (Exception $e) {
            Log::error('Error getting QC details', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading QC details'
            ], 500);
        }
    }

    /**
     * Mark QC status
     */
    public function markQCStatus(Request $request)
    {
        try {
            $request->validate([
                'file_indexing_id' => 'required|integer',
                'status' => 'required|string|in:pending,in_progress,completed',
                'comments' => 'nullable|string'
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->find($request->file_indexing_id);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileIndexing->qc_status = $request->status;
            $fileIndexing->qc_comments = $request->comments;
            $fileIndexing->qc_user_id = Auth::id();
            $fileIndexing->qc_updated_at = now();
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'QC status updated successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error marking QC status', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating QC status'
            ], 500);
        }
    }

    /**
     * Override QC
     */
    public function overrideQC(Request $request)
    {
        try {
            $request->validate([
                'file_indexing_id' => 'required|integer',
                'override_reason' => 'required|string'
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->find($request->file_indexing_id);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileIndexing->qc_override = true;
            $fileIndexing->qc_override_reason = $request->override_reason;
            $fileIndexing->qc_override_user_id = Auth::id();
            $fileIndexing->qc_override_at = now();
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'QC override applied successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error overriding QC', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error applying QC override'
            ], 500);
        }
    }

    /**
     * Batch QC operation
     */
    public function batchQCOperation(Request $request)
    {
        try {
            $request->validate([
                'file_ids' => 'required|array',
                'operation' => 'required|string|in:approve,reject,reset',
                'comments' => 'nullable|string'
            ]);

            $updatedCount = 0;
            foreach ($request->file_ids as $fileId) {
                $fileIndexing = FileIndexing::on('sqlsrv')->find($fileId);
                if ($fileIndexing) {
                    switch ($request->operation) {
                        case 'approve':
                            $fileIndexing->qc_status = 'completed';
                            break;
                        case 'reject':
                            $fileIndexing->qc_status = 'pending';
                            break;
                        case 'reset':
                            $fileIndexing->qc_status = null;
                            break;
                    }
                    $fileIndexing->qc_comments = $request->comments;
                    $fileIndexing->qc_user_id = Auth::id();
                    $fileIndexing->qc_updated_at = now();
                    $fileIndexing->save();
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Batch operation completed. Updated {$updatedCount} files."
            ]);
        } catch (Exception $e) {
            Log::error('Error in batch QC operation', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error in batch operation'
            ], 500);
        }
    }

    /**
     * Approve for archiving
     */
    public function approveForArchiving(Request $request)
    {
        try {
            $request->validate([
                'file_indexing_id' => 'required|integer'
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->find($request->file_indexing_id);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileIndexing->approved_for_archiving = true;
            $fileIndexing->approved_for_archiving_by = Auth::id();
            $fileIndexing->approved_for_archiving_at = now();
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'File approved for archiving'
            ]);
        } catch (Exception $e) {
            Log::error('Error approving for archiving', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error approving for archiving'
            ], 500);
        }
    }

    /**
     * Archive file
     */
    public function archiveFile(Request $request)
    {
        try {
            $request->validate([
                'file_indexing_id' => 'required|integer'
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->find($request->file_indexing_id);
            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileIndexing->archived = true;
            $fileIndexing->archived_by = Auth::id();
            $fileIndexing->archived_at = now();
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'File archived successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error archiving file', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error archiving file'
            ], 500);
        }
    }

    /**
     * Get QC audit trail
     */
    public function getQCAuditTrail(Request $request, $fileIndexingId)
    {
        try {
            // Implementation would fetch audit trail data
            // This is a placeholder implementation
            $auditTrail = [
                [
                    'action' => 'QC Started',
                    'user' => 'System',
                    'timestamp' => now()->subHours(2),
                    'details' => 'File entered QC process'
                ],
                [
                    'action' => 'QC Review',
                    'user' => Auth::user()->name ?? 'Unknown',
                    'timestamp' => now()->subHour(),
                    'details' => 'QC review in progress'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $auditTrail
            ]);
        } catch (Exception $e) {
            Log::error('Error getting QC audit trail', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading audit trail'
            ], 500);
        }
    }

    /**
     * Get QC statistics
     */
    public function getQCStats(Request $request)
    {
        try {
            $stats = [
                'pending_count' => $this->getPendingQCCount(),
                'in_progress_count' => $this->getInProgressQCCount(),
                'completed_count' => $this->getCompletedQCCount(),
                'total_files' => $this->getTotalQCFiles(),
                'completion_rate' => $this->getQCCompletionRate()
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Error getting QC stats', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading QC statistics',
                'stats' => [
                    'pending_count' => 0,
                    'in_progress_count' => 0,
                    'completed_count' => 0,
                    'total_files' => 0,
                    'completion_rate' => 0
                ]
            ], 500);
        }
    }

    /**
     * Helper methods for statistics
     */
    private function getPendingQCCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where(function($query) {
                    $query->where('qc_status', '!=', 'completed')
                          ->orWhereNull('qc_status');
                })
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting pending QC count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getInProgressQCCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where('qc_status', 'in_progress')
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting in-progress QC count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getCompletedQCCount()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->where('qc_status', 'completed')
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting completed QC count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getTotalQCFiles()
    {
        try {
            return FileIndexing::on('sqlsrv')
                ->whereHas('pagetypings')
                ->count();
        } catch (Exception $e) {
            Log::error('Error getting total QC files', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getQCCompletionRate()
    {
        try {
            $total = $this->getTotalQCFiles();
            if ($total == 0) return 0;
            
            $completed = $this->getCompletedQCCount();
            return round(($completed / $total) * 100, 2);
        } catch (Exception $e) {
            Log::error('Error calculating QC completion rate', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
