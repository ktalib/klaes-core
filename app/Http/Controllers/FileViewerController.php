<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileViewerController extends Controller
{
    /**
     * Display files for a primary application
     */
    public function viewPrimaryFiles($applicationId)
    {
        try {
            // Get application details
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $applicationId)
                ->first();

            if (!$application) {
                return redirect()->back()->with('error', 'Application not found');
            }

            // Get file indexing data
            $fileIndexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where('main_application_id', $applicationId)
                ->first();

            $scannings = collect();
            $pageTypings = collect();

            if ($fileIndexing) {
                // Get scanning data
                $scannings = DB::connection('sqlsrv')->table('scannings')
                    ->where('file_indexing_id', $fileIndexing->id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Get page typing data
                $pageTypings = DB::connection('sqlsrv')->table('pagetypings')
                    ->where('file_indexing_id', $fileIndexing->id)
                    ->orderBy('page_number', 'asc')
                    ->get();
            }

            // Process owner name
            $ownerName = $this->getOwnerName($application);

            $PageTitle = 'File Viewer - Primary Application';
            $PageDescription = 'View all files and documents for this application';

            return view('programmes.file-viewer.primary', compact(
                'application',
                'fileIndexing',
                'scannings',
                'pageTypings',
                'ownerName',
                'PageTitle',
                'PageDescription'
            ));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error loading files: ' . $e->getMessage());
        }
    }

    /**
     * Display files for a unit application
     */
    public function viewUnitFiles($subApplicationId)
    {
        try {
            // Get sub-application details
            $subApplication = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if (!$subApplication) {
                return redirect()->back()->with('error', 'Unit application not found');
            }

            // Get parent application details
            $parentApplication = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $subApplication->main_application_id)
                ->first();

            // Get file indexing data
            $fileIndexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where('subapplication_id', $subApplicationId)
                ->first();

            $scannings = collect();
            $pageTypings = collect();

            if ($fileIndexing) {
                // Get scanning data
                $scannings = DB::connection('sqlsrv')->table('scannings')
                    ->where('file_indexing_id', $fileIndexing->id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                // Get page typing data
                $pageTypings = DB::connection('sqlsrv')->table('pagetypings')
                    ->where('file_indexing_id', $fileIndexing->id)
                    ->orderBy('page_number', 'asc')
                    ->get();
            }

            // Process owner name
            $ownerName = $this->getOwnerName($subApplication);

            $PageTitle = 'File Viewer - Unit Application';
            $PageDescription = 'View all files and documents for this unit application';

            return view('programmes.file-viewer.unit', compact(
                'subApplication',
                'parentApplication',
                'fileIndexing',
                'scannings',
                'pageTypings',
                'ownerName',
                'PageTitle',
                'PageDescription'
            ));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error loading files: ' . $e->getMessage());
        }
    }

    /**
     * Get file content for preview
     */
    public function getFilePreview($scanningId)
    {
        try {
            $scanning = DB::connection('sqlsrv')->table('scannings')
                ->where('id', $scanningId)
                ->first();

            if (!$scanning) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $filePath = file_storage_path('app/public/' . $scanning->document_path);
            
            if (!file_exists($filePath)) {
                return response()->json(['error' => 'Physical file not found'], 404);
            }

            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);

            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $scanning->id,
                    'name' => $scanning->original_filename,
                    'path' => asset('storage/app/public/' . $scanning->document_path),
                    'extension' => $fileExtension,
                    'size' => $this->formatFileSize($fileSize),
                    'mime_type' => $mimeType,
                    'document_type' => $scanning->document_type,
                    'paper_size' => $scanning->paper_size,
                    'notes' => $scanning->notes,
                    'uploaded_at' => $scanning->created_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error loading file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download a file
     */
    public function downloadFile($scanningId)
    {
        try {
            $scanning = DB::connection('sqlsrv')->table('scannings')
                ->where('id', $scanningId)
                ->first();

            if (!$scanning) {
                abort(404, 'File not found');
            }

            $filePath = file_storage_path('app/public/' . $scanning->document_path);
            
            if (!file_exists($filePath)) {
                abort(404, 'Physical file not found');
            }

            return response()->download($filePath, $scanning->original_filename);

        } catch (\Exception $e) {
            abort(500, 'Error downloading file: ' . $e->getMessage());
        }
    }

    /**
     * Get owner name from application data
     */
    private function getOwnerName($application)
    {
        if (!empty($application->multiple_owners_names)) {
            $owners = json_decode($application->multiple_owners_names, true);
            return is_array($owners) ? implode(', ', $owners) : 'Multiple Owners';
        } elseif (!empty($application->corporate_name)) {
            return $application->corporate_name;
        } else {
            return trim(($application->first_name ?? '') . ' ' . ($application->surname ?? ''));
        }
    }

    /**
     * Format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}