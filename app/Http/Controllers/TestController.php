<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    public function test()
    {
        try {
            // Test database connection
            $dbTest = DB::connection('sqlsrv')->select('SELECT 1 as test');

            // Test file indexing model
            $fileIndexingCount = \App\Models\FileIndexing::on('sqlsrv')->count();

            // Test scanning model
            $scanningCount = \App\Models\Scanning::on('sqlsrv')->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Backend connectivity test passed',
                'data' => [
                    'database_connection' => 'OK',
                    'file_indexing_count' => $fileIndexingCount,
                    'scanning_count' => $scanningCount,
                    'storage_path' => file_storage_path('app/public'),
                    'storage_exists' => is_dir(file_storage_path('app/public')),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backend connectivity test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
