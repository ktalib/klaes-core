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
        
        // Distinct searches today: the same file (or value) searched again counts once.
        $searchesTodayRaw = \App\Models\LegalSearchLog::where('created_at', '>=', $today)->count();
        $totalToday = \App\Models\LegalSearchLog::where('created_at', '>=', $today)
            ->selectRaw('search_parameter, ' . self::GROUP_VALUE . ' AS v')
            ->groupByRaw('search_parameter, ' . self::GROUP_VALUE)
            ->get()
            ->count();
        $total = \App\Models\LegalSearchLog::count();
        
        $foundCount = \App\Models\LegalSearchLog::where('result_status', 'Found')->count();
        $notFoundCount = \App\Models\LegalSearchLog::where('result_status', 'Not Found')->count();
        
        $officialCount = \App\Models\LegalSearchLog::where('search_source', 'legal_search.print.official')->count();
        $onPremiseCount = \App\Models\LegalSearchLog::where('search_source', 'legal_search.print.onpremise')->count();

        return view('legalsearchreports.index', compact(
            'PageTitle', 
            'PageDescription',
            'totalToday',
            'searchesTodayRaw',
            'total',
            'foundCount',
            'notFoundCount',
            'officialCount',
            'onPremiseCount'
        ));
    }

    /**
     * Repeat searches of the same value collapse into one row: grouped on the search
     * parameter plus the value, case- and space-insensitive.
     */
    private const GROUP_VALUE = 'UPPER(LTRIM(RTRIM(search_value)))';

    /** Logs matching the Search Type filter (Official / Commercial / all). */
    private function filteredLogs(Request $request)
    {
        $query = \App\Models\LegalSearchLog::query();
        $searchType = strtolower(trim((string) $request->input('search_type', 'all')));
        if (in_array($searchType, ['official', 'commercial'], true)) {
            $query->where('search_type', ucfirst($searchType));
        }
        return $query;
    }

    private static function groupKey($parameter, $value): string
    {
        return (string) $parameter . '|' . strtoupper(trim((string) $value));
    }

    public function data(Request $request) {
        // One row per searched value: its most recent search. The earlier searches of
        // the same value open underneath it (history()).
        $latestIds = $this->filteredLogs($request)
            ->selectRaw('MAX(id)')
            ->groupByRaw('search_parameter, ' . self::GROUP_VALUE);
        $query = \App\Models\LegalSearchLog::with('user')->whereIn('id', $latestIds);

        $groupCounts = $this->filteredLogs($request)
            ->selectRaw('search_parameter, ' . self::GROUP_VALUE . ' AS v, COUNT(*) AS n')
            ->groupByRaw('search_parameter, ' . self::GROUP_VALUE)
            ->get()
            ->mapWithKeys(fn ($g) => [self::groupKey($g->search_parameter, $g->v) => (int) $g->n]);

        // The Transaction Token Control (TTC) token a logged search ran on: the file's
        // latest token created before the search — the same token the search was
        // logged against. Tokens are loaded once per file for the page being drawn.
        $tokensByFile = [];
        $ttcFor = function ($log) use (&$tokensByFile) {
            if ($log->search_parameter !== 'File Number' || trim((string) $log->search_value) === '') {
                return null;
            }
            $fileNo = trim((string) $log->search_value);
            if (!array_key_exists($fileNo, $tokensByFile)) {
                $tokensByFile[$fileNo] = \App\Models\LegalSearchToken::where('file_number', $fileNo)
                    ->orderByDesc('created_at')
                    ->get(['token', 'client_name', 'client_address', 'applicant_name', 'property_location', 'general_body',
                        'receipt_number', 'amount_paid', 'date_paid', 'payment_reason', 'created_at']);
            }
            return $tokensByFile[$fileNo]->first(
                fn ($t) => !$log->created_at || !$t->created_at || $t->created_at->lte($log->created_at)
            );
        };

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('search_count', fn ($log) => $groupCounts[self::groupKey($log->search_parameter, $log->search_value)] ?? 1)
            ->addColumn('search_type_display', function ($log) use ($ttcFor) {
                $type = trim((string) ($log->search_type ?? ''));
                if ($type === '') {
                    return '<span class="badge badge-light-secondary">—</span>';
                }
                $official = strcasecmp($type, 'Official') === 0;
                // An Official search names the Requesting Body from its TTC (e.g. EFCC).
                $body = $official ? trim((string) optional($ttcFor($log))->general_body) : '';
                $class = $official ? 'badge-type-official' : 'badge-type-commercial';
                return '<span class="badge '.$class.'">'.e($type).($body !== '' ? ' · '.e($body) : '').'</span>';
            })
            ->addColumn('requester_display', function ($log) use ($ttcFor) {
                $ttc = $ttcFor($log);
                if (!$ttc) {
                    return '<span class="cell-secondary">—</span>';
                }
                // Only the name shows in the table; it opens the Requester card, filled
                // from the row's `requester` data below.
                $name = trim((string) ($ttc->client_name ?? '')) ?: trim((string) ($ttc->applicant_name ?? ''));
                return '<a href="#" class="ls-requester-link">'.e($name !== '' ? $name : 'View requester').'</a>';
            })
            ->addColumn('requester', function ($log) use ($ttcFor) {
                $ttc = $ttcFor($log);
                if (!$ttc) {
                    return null;
                }
                // Token masked, receipt, date paid, search type and Requesting Body.
                $details = app(\App\Services\LegalSearchService::class)->requesterDetails($ttc) ?? [];
                return array_merge($details, [
                    'client_name'       => trim((string) ($ttc->client_name ?? '')) ?: null,
                    'client_address'    => trim((string) ($ttc->client_address ?? '')) ?: null,
                    'property_location' => trim((string) ($ttc->property_location ?? '')) ?: null,
                    'file_number'       => trim((string) $log->search_value) ?: null,
                    'issued_at'         => $ttc->created_at ? $ttc->created_at->format('d M, Y h:i A') : null,
                ]);
            })
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
            ->rawColumns(['search_type_display', 'requester_display', 'source_display', 'result_display', 'printed_status', 'action'])
            ->make(true);
    }

    /**
     * Every search of one value (newest first), for the collapsed row's expansion.
     */
    public function history(Request $request)
    {
        $parameter = (string) $request->input('search_parameter', '');
        $value = strtoupper(trim((string) $request->input('search_value', '')));
        if ($parameter === '' || $value === '') {
            return response()->json(['success' => false, 'rows' => []], 422);
        }

        $logs = $this->filteredLogs($request)
            ->with('user')
            ->where('search_parameter', $parameter)
            ->whereRaw(self::GROUP_VALUE . ' = ?', [$value])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Requester per search: the file's latest TTC token issued before that search.
        $tokens = $parameter === 'File Number'
            ? \App\Models\LegalSearchToken::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$value])
                ->orderByDesc('created_at')->get(['client_name', 'applicant_name', 'created_at'])
            : collect();

        $rows = $logs->map(function ($log) use ($tokens) {
            $ttc = $tokens->first(fn ($t) => !$log->created_at || !$t->created_at || $t->created_at->lte($log->created_at));
            $requester = $ttc ? (trim((string) $ttc->client_name) ?: trim((string) $ttc->applicant_name)) : '';
            return [
                'date'        => $log->created_at ? $log->created_at->format('d M, Y h:i A') : '-',
                'staff'       => $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : '-',
                'search_type' => $log->search_type ?: null,
                'source'      => $this->sourceLabels[$log->search_source]['label'] ?? null,
                'requester'   => $requester !== '' ? $requester : null,
                'result'      => trim($log->result_status . ' (' . $log->results_count . ')'),
                'receipt_no'  => $log->receipt_no ?: null,
                'printed'     => (bool) $log->printed,
            ];
        })->values();

        return response()->json(['success' => true, 'rows' => $rows]);
    }
}
