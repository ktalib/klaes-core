<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AnalyticsDashboardController
 *
 * Handles display of advanced analytics dashboard
 */
class AnalyticsDashboardController extends Controller
{
    /**
     * Display advanced analytics dashboard
     */
    public function index(Request $request)
    {
        // Check authorization
        if (!Auth::user()->can('view-analytics') && Auth::user()->type !== 'super admin') {
            return redirect('/')->with('error', 'Unauthorized');
        }

        try {
            Log::info("Advanced analytics dashboard accessed by " . Auth::user()->name);

            return view('activity.advanced-analytics', [
                'title' => 'Advanced Analytics Dashboard',
                'user' => Auth::user(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading advanced analytics dashboard: {$e->getMessage()}");
            return redirect('/')->with('error', 'Unable to load analytics dashboard');
        }
    }
}
