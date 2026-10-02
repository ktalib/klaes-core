<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\FileNumber;
use App\Models\DecommissionedFiles;
use Yajra\DataTables\Facades\DataTables;

class FileDecommissioningController extends Controller
{
    /**
     * Display the file decommissioning page
     */
    public function index()
    {
        $totalActiveFiles = FileNumber::active()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $totalDecommissionedFiles = DecommissionedFiles::count();

        return view('file_decommissioning.index', compact('totalActiveFiles', 'totalDecommissionedFiles'));
    }

    /**
     * Display the decommissioned files list page
     */
    public function decommissionedIndex()
    {
        $totalDecommissionedFiles = DecommissionedFiles::count();
        $recentDecommissioned = DecommissionedFiles::recent(30)->count();

        return view('file_decommissioning.decommissioned_list', compact('totalDecommissionedFiles', 'recentDecommissioned'));
    }

    /**
     * Get active files data for DataTables
     */
    public function getActiveFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            // Base query for active files
            $baseQuery = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });

            // Get total count
            $totalRecords = $baseQuery->count();

            // Apply search if provided
            if (!empty($searchValue)) {
                $baseQuery->where(function($query) use ($searchValue) {
                    $query->where('kangisFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('FileName', 'like', "%{$searchValue}%")
                          ->orWhere('mlsfNo', 'like', "%{$searchValue}%");
                });
            }

            // Get filtered count
            $filteredRecords = $baseQuery->count();

            // Get the actual data with ordering and pagination
            $data = $baseQuery->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo', 
                    'FileName',
                    'type',
                    'commissioning_date',
                    'created_at'
                ])
                ->orderBy('id', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            // Format the data
            $formattedData = $data->map(function($row) {
                return [
                    'id' => $row->id,
                    'mlsfNo' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: 'N/A',
                    'FileName' => trim($row->FileName ?? '') ?: 'N/A',
                    'type' => trim($row->type ?? '') ?: 'N/A',
                    'commissioning_date' => $row->commissioning_date ? $row->commissioning_date->format('Y-m-d H:i:s') : 'N/A',
                    'created_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : 'N/A',
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="decommissionFile(' . $row->id . ')" 
                                class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1 rounded" title="Decommission File">
                            <i data-lucide="archive" class="w-4 h-4 inline mr-1"></i>Decommission
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getActiveFilesData: ' . $e->getMessage());
            
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get decommissioned files data for DataTables
     */
    public function getDecommissionedFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            // Get paginated records from file-based storage
            $result = DecommissionedFiles::getPaginatedRecords($start, $length, $searchValue);

            // Format the data
            $formattedData = array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'mls_file_no' => trim($row['mls_file_no'] ?? '') ?: 'N/A',
                    'kangis_file_no' => trim($row['kangis_file_no'] ?? '') ?: 'N/A',
                    'new_kangis_file_no' => trim($row['new_kangis_file_no'] ?? '') ?: 'N/A',
                    'file_name' => trim($row['file_name'] ?? '') ?: 'N/A',
                    'commissioning_date' => $row['commissioning_date'] ?? 'N/A',
                    'decommissioning_date' => $row['decommissioning_date'],
                    'decommissioning_reason' => strlen($row['decommissioning_reason']) > 50 ? 
                        substr($row['decommissioning_reason'], 0, 50) . '...' : 
                        $row['decommissioning_reason'],
                    'decommissioned_by' => trim($row['decommissioned_by']),
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="viewDecommissionedFile(' . $row['id'] . ')" 
                                class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded hover:bg-blue-50" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>'
                ];
            }, $result['data']);

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $result['total'],
                'recordsFiltered' => $result['filtered'],
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getDecommissionedFilesData: ' . $e->getMessage());
            
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get file details for decommissioning
     */
    public function getFileDetails($id)
    {
        try {
            $file = FileNumber::where('id', $id)
                ->where('is_decommissioned', false)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found or already decommissioned'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName,
                    'type' => $file->type,
                    'commissioning_date' => $file->commissioning_date ? $file->commissioning_date->format('Y-m-d\TH:i') : null,
                    'created_at' => $file->created_at ? $file->created_at->format('Y-m-d H:i:s') : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decommission a file
     */
    public function decommissionFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer|exists:fileNumber,id',
            'commissioning_date' => 'nullable|date',
            'decommissioning_date' => 'required|date',
            'decommissioning_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $file = FileNumber::where('id', $request->file_id)
                ->where('is_decommissioned', false)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found or already decommissioned'
                ], 404);
            }

            // Decommission the file using the model method
            $file->decommission(
                $request->decommissioning_reason,
                $request->decommissioning_date,
                $request->commissioning_date
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'File decommissioned successfully',
                'data' => [
                    'file_id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'decommissioning_date' => $request->decommissioning_date,
                    'decommissioning_reason' => $request->decommissioning_reason
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error decommissioning file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error decommissioning file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get decommissioned file details
     */
    public function getDecommissionedFileDetails($id)
    {
        try {
            $decommissionedFile = DecommissionedFiles::with('fileNumber')->find($id);

            if (!$decommissionedFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Decommissioned file record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $decommissionedFile->id,
                    'file_number_id' => $decommissionedFile->file_number_id,
                    'mls_file_no' => $decommissionedFile->mls_file_no,
                    'kangis_file_no' => $decommissionedFile->kangis_file_no,
                    'new_kangis_file_no' => $decommissionedFile->new_kangis_file_no,
                    'file_name' => $decommissionedFile->file_name,
                    'commissioning_date' => $decommissionedFile->commissioning_date ? $decommissionedFile->commissioning_date->format('Y-m-d H:i:s') : null,
                    'decommissioning_date' => $decommissionedFile->decommissioning_date->format('Y-m-d H:i:s'),
                    'decommissioning_reason' => $decommissionedFile->decommissioning_reason,
                    'decommissioned_by' => $decommissionedFile->decommissioned_by,
                    'created_at' => $decommissionedFile->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving decommissioned file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for files by file number
     */
    public function searchFiles(Request $request)
    {
        $search = $request->get('search', '');
        
        if (empty($search)) {
            return response()->json([]);
        }

        try {
            $files = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function($query) use ($search) {
                    $query->where('mlsfNo', 'like', "%{$search}%")
                          ->orWhere('kangisFileNo', 'like', "%{$search}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$search}%")
                          ->orWhere('FileName', 'like', "%{$search}%");
                })
                ->select(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName'])
                ->limit(20)
                ->get();

            $results = $files->map(function($file) {
                $displayText = '';
                if ($file->mlsfNo) $displayText .= $file->mlsfNo . ' - ';
                if ($file->FileName) $displayText .= $file->FileName;
                
                return [
                    'id' => $file->id,
                    'text' => trim($displayText, ' - '),
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName
                ];
            });

            return response()->json($results);

        } catch (\Exception $e) {
            \Log::error('Error searching files: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics()
    {
        try {
            $totalFiles = FileNumber::where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })->count();

            $activeFiles = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            $decommissionedFiles = FileNumber::decommissioned()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            $recentDecommissioned = DecommissionedFiles::recent(30)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_files' => $totalFiles,
                    'active_files' => $activeFiles,
                    'decommissioned_files' => $decommissionedFiles,
                    'recent_decommissioned' => $recentDecommissioned
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}