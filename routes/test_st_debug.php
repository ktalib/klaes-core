<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MlsFileNoController;

// Add a test route for debugging ST file numbers
Route::get('/test-st-debug', function() {
    try {
        // Query all records with st_file_no
        $stRecords = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereNotNull('st_file_no')
            ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted', 'FileName')
            ->get();
        
        $response = [
            'total_st_records' => $stRecords->count(),
            'st_records' => $stRecords->toArray(),
            'controller_query_test' => []
        ];
        
        // Test the controller's query logic
        $controllerRecords = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->select([
                'fileNumber.id',
                'fileNumber.mlsfNo',
                'fileNumber.kangisFileNo', 
                'fileNumber.NewKANGISFileNo',
                'fileNumber.st_file_no',
                'fileNumber.SOURCE',
                'fileNumber.is_deleted'
            ])
            ->where(function($q) {
                // Include records with MLS file numbers OR ST file numbers
                $q->whereNotNull('fileNumber.mlsfNo')
                  ->orWhereNotNull('fileNumber.st_file_no')
                  ->orWhereNotNull('fileNumber.kangisFileNo')
                  ->orWhereNotNull('fileNumber.NewKANGISFileNo');
            })
            ->where(function($q) {
                $q->whereNull('fileNumber.is_deleted')->orWhere('fileNumber.is_deleted', 0);
            })
            ->get();
            
        $response['controller_query_test'] = [
            'total_records' => $controllerRecords->count(),
            'st_file_no_records' => $controllerRecords->where('st_file_no', '!=', null)->count(),
            'st_dept_records' => $controllerRecords->where('SOURCE', 'ST Dept')->count(),
            'sample_records' => $controllerRecords->take(10)->toArray()
        ];
        
        // Test specific ST file numbers
        $searchNumbers = [
            'ST-RES-2025-1',
            'ST-COM-2025-1-001',
            'ST-COM-2025-2-001', 
            'ST-COM-2025-3-001',
            'ST-COM-2025-4',
            'ST-MIXED-2025-1'
        ];
        
        $response['search_results'] = [];
        foreach ($searchNumbers as $stNumber) {
            $found = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->where(function($query) use ($stNumber) {
                    $query->where('st_file_no', 'LIKE', "%{$stNumber}%")
                          ->orWhere('mlsfNo', 'LIKE', "%{$stNumber}%")
                          ->orWhere('kangisFileNo', 'LIKE', "%{$stNumber}%")
                          ->orWhere('NewKANGISFileNo', 'LIKE', "%{$stNumber}%");
                })
                ->select('id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'SOURCE', 'is_deleted')
                ->get();
                
            $response['search_results'][$stNumber] = [
                'found_count' => $found->count(),
                'records' => $found->toArray()
            ];
        }
        
        return response()->json($response, 200, [], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});