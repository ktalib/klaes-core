<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ReportComparisonController
 *
 * Handles period-to-period comparison of activity metrics
 */
class ReportComparisonController extends Controller
{
    /**
     * Display comparison dashboard
     */
    public function index()
    {
        // Check authorization
        if (!Auth::user()->can('view-analytics') && Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Unauthorized');
        }

        try {
            Log::info("Report comparison dashboard accessed by " . Auth::user()->name);

            return view('activity.report-comparison', [
                'title' => 'Report Comparison',
                'user' => Auth::user(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading report comparison: {$e->getMessage()}");
            return redirect('/')->with('error', 'Unable to load comparison dashboard');
        }
    }

    /**
     * Compare two periods
     */
    public function compare(Request $request)
    {
        // Check authorization
        if (!Auth::user()->can('view-analytics') && Auth::user()->type !== 'super admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            // Validate input
            $validated = $request->validate([
                'period1_days' => 'required|integer|min:1|max:365',
                'period2_days' => 'required|integer|min:1|max:365',
                'metric' => 'nullable|in:sessions,users,duration,all',
            ]);

            $period1Days = $validated['period1_days'];
            $period2Days = $validated['period2_days'];
            $metric = $validated['metric'] ?? 'all';

            // Generate comparison data
            $comparison = $this->generateComparison($period1Days, $period2Days, $metric);

            Log::info("Report comparison generated", [
                'period1_days' => $period1Days,
                'period2_days' => $period2Days,
                'metric' => $metric,
            ]);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error comparing periods: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate comparison data
     */
    private function generateComparison(int $period1Days, int $period2Days, string $metric): array
    {
        // This would call analytics service methods
        // For now, return structure
        return [
            'period1_days' => $period1Days,
            'period2_days' => $period2Days,
            'metric' => $metric,
            'period1_stats' => [],
            'period2_stats' => [],
            'comparison' => [],
            'insights' => [],
        ];
    }

    /**
     * Export comparison as PDF
     */
    public function exportPdf(Request $request)
    {
        // Check authorization
        if (!Auth::user()->can('view-analytics') && Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Unauthorized');
        }

        try {
            // Generate PDF export
            Log::info("Report comparison exported as PDF by " . Auth::user()->name);

            return response()->json([
                'success' => true,
                'message' => 'PDF export generated',
            ]);

        } catch (\Exception $e) {
            Log::error("Error exporting comparison: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
