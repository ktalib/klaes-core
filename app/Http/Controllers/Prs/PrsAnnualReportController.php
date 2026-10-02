<?php

namespace App\Http\Controllers\Prs;

use App\Http\Controllers\Controller;
use App\Services\Prs\PrsReportAggregator;
use Illuminate\Http\Request;

/**
 * PRS Annual Progress Report — LIVE.
 *
 * Serves from PrsReportAggregator, which queries the database directly. The
 * static fixture (App\Services\Prs\PrsSampleData) is retained as the 2025
 * reference transcription but is no longer wired to this page.
 *
 * The year is data-driven, not hardcoded. KLAES holds almost no 2025 activity —
 * commissioning, OSS and search all went live in 2026 — so offering 2025 would
 * print a 74-100% shortfall against a report PRS already published. See
 * docs/prs-2025/20-live-data-implementation.md §5.
 */
class PrsAnnualReportController extends Controller
{
    public function __construct(private PrsReportAggregator $reports)
    {
    }

    public function index(Request $request)
    {
        $department = $request->query('dept', 'all');
        $years      = $this->reports->availableYears();

        $year = (int) $request->query('year', 0);

        if (!isset($years[$year])) {
            $year = $this->reports->year();
        }

        $report   = $this->reports->forYear($year);
        $sections = $report->sections();

        if ($department !== 'all') {
            $sections = array_values(array_filter(
                $sections,
                fn ($s) => $s['department'] === $department
            ));
        }

        return view('prs.annual_report.index', [
            'year'        => $year,
            'years'       => array_keys($years),
            'sections'    => $sections,
            'highlights'  => $report->highlights(),
            'departments' => PrsReportAggregator::DEPARTMENTS,
            'department'  => $department,
        ]);
    }
}
