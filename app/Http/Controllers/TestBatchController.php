<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestBatchController extends Controller
{
    public function testBatches()
    {
        try {
            echo "Testing batch API...\n";
            
            // Check if the Rack_Shelf_Labels table exists
            $tableExists = DB::connection('sqlsrv')->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Rack_Shelf_Labels'");
            echo "Table exists check: " . json_encode($tableExists) . "\n";

            if (empty($tableExists)) {
                echo "Table 'Rack_Shelf_Labels' does not exist. Returning default batches.\n";
                $batches = [];
                for ($i = 1; $i <= 10; $i++) {
                    $batches[] = [
                        'id' => $i,
                        'text' => $i
                    ];
                }
                return response()->json([
                    'success' => true,
                    'batches' => $batches,
                    'message' => 'Default batches (table not found)'
                ]);
            }

            // Get available batches (is_used = 0 or null)
            $query = DB::connection('sqlsrv')
                ->table('Rack_Shelf_Labels')
                ->where(function($query) {
                    $query->where('is_used', 0)
                          ->orWhereNull('is_used');
                });

            $availableBatches = $query
                ->orderBy('id')
                ->limit(10)
                ->get(['id', 'full_label']);

            echo "Available batches from DB: " . json_encode($availableBatches) . "\n";

            $batches = [];
            foreach ($availableBatches as $batch) {
                $batches[] = [
                    'id' => $batch->id,
                    'text' => $batch->id
                ];
            }

            return response()->json([
                'success' => true,
                'batches' => $batches,
                'message' => 'Batches from database'
            ]);
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            
            // If there's any error, return default batches
            $batches = [];
            for ($i = 1; $i <= 10; $i++) {
                $batches[] = [
                    'id' => $i,
                    'text' => $i
                ];
            }
            return response()->json([
                'success' => true,
                'batches' => $batches,
                'error' => $e->getMessage()
            ]);
        }
    }
}
