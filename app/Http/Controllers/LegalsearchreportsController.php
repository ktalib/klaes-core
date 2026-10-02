<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegalsearchreportsController extends Controller
{
    protected array $sourceLabels = [
        'legal_search.print.official'  => ['label' => 'Official',    'color' => 'info'],
        'legal_search.print.onpremise' => ['label' => 'On-Premise',  'color' => 'warning'],
        'legal_search.print.online'    => ['label' => 'Online',      'color' => 'success'],
    ];

    public function index() {
        $PageTitle = 'Legal Search Reports';
        $PageDescription = '';

        $today = now()->startOfDay();
        
        $totalToday = \App\Models\LegalSearchLog::where('created_at', '>=', $today)->count();
        $total = \App\Models\LegalSearchLog::count();
        
        $foundCount = \App\Models\LegalSearchLog::where('result_status', 'Found')->count();
        $notFoundCount = \App\Models\LegalSearchLog::where('result_status', 'Not Found')->count();
        
        $officialCount = \App\Models\LegalSearchLog::where('search_source', 'legal_search.print.official')->count();
        $onPremiseCount = \App\Models\LegalSearchLog::where('search_source', 'legal_search.print.onpremise')->count();

        return view('legalsearchreports.index', compact(
            'PageTitle', 
            'PageDescription',
            'totalToday',
            'total',
            'foundCount',
            'notFoundCount',
            'officialCount',
            'onPremiseCount'
        ));
    }

    public function data(Request $request) {
        $query = \App\Models\LegalSearchLog::with('user');

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('date', function ($log) {
                return $log->created_at ? $log->created_at->format('d M, Y h:i A') : '-';
            })
            ->addColumn('staff', function ($log) {
                return $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : '-';
            })
            ->addColumn('source_display', function ($log) {
                $source = $log->search_source;
                if (!$source || !isset($this->sourceLabels[$source])) {
                    return '<span class="badge badge-light-secondary">—</span>';
                }
                $meta = $this->sourceLabels[$source];
                return '<span class="badge badge-light-'.$meta['color'].'">'.$meta['label'].'</span>';
            })
            ->addColumn('result_display', function ($log) {
                $color = $log->result_status == 'Found' ? 'success' : 'danger';
                return '<span class="badge badge-light-'.$color.'">'.$log->result_status.' ('.$log->results_count.')</span>';
            })
            ->addColumn('printed_status', function ($log) {
                $color = $log->printed ? 'success' : 'secondary';
                $text = $log->printed ? 'Printed' : 'Not Printed';
                return '<span class="badge badge-light-'.$color.'">'.$text.'</span>';
            })
            ->addColumn('action', function ($log) {
                if ($log->search_parameter === 'File Number' && $log->search_value && $log->search_source) {
                    try {
                        $templateUrl = route($log->search_source) . '?file_number=' . urlencode($log->search_value);
                        return '<a href="'.$templateUrl.'" target="_blank" class="btn btn-sm btn-primary">Open Search</a>';
                    } catch (\Exception $e) {
                        // route name not found — fall through
                    }
                }
                if ($log->search_parameter === 'File Number' && $log->search_value) {
                    // Legacy logs without source: default to on-premise template
                    $templateUrl = route('legal_search.print.onpremise') . '?file_number=' . urlencode($log->search_value);
                    return '<a href="'.$templateUrl.'" target="_blank" class="btn btn-sm btn-primary">Open Search</a>';
                }
                if ($log->direct_link) {
                    return '<a href="'.$log->direct_link.'" target="_blank" class="btn btn-sm btn-primary">Open Search</a>';
                }
                return '-';
            })
            ->rawColumns(['source_display', 'result_display', 'printed_status', 'action'])
            ->make(true);
    }
}
