<?php

namespace App\Http\Controllers;

use App\Services\ConsolidatedReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsolidatedReportController extends Controller
{
    public function export(Request $request, string $report, ConsolidatedReportService $reports)
    {
        $definition = config("consolidated_reports.$report");
        abort_unless(is_array($definition), 404);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(array_keys($definition['statusOptions']))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('start_date') ? 'after_or_equal:start_date' : null]),
        ]);

        return response()->json(['success' => true] + $reports->generate($report, $filters))
            ->header('Cache-Control', 'no-store');
    }
}
