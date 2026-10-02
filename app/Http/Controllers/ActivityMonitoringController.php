<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityMonitoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ActivityMonitoringController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesModules;

    /** The module this screen belongs to. */
    private const MODULE = 'Activity Monitoring';

    public function __construct(private ActivityMonitoringService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'view');

        $filters = $this->extractFilters($request);
        $forceAlert = $this->shouldForceAlert($request);
        $payload = $this->service->getUserStatistics($filters, $request->user(), $forceAlert);

        return view('activity-monitoring.index', [
            'initialPayload' => $payload,
            'defaultFilters' => $filters,
            // Was hardcoded true, so the Export menu appeared for everyone who could open
            // the page and the CSV/PDF endpoints behind it were reachable by anyone at all.
            'canExport' => $this->userCanDo(self::MODULE, 'export'),
            'forceAlert' => $forceAlert,
        ]);
    }

    public function stats(Request $request)
    {
        $payload = $this->service->getUserStatistics(
            $this->extractFilters($request),
            $request->user(),
            $this->shouldForceAlert($request)
        );

        return response()->json($payload);
    }

    public function personalAlert(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['data' => null], Response::HTTP_UNAUTHORIZED);
        }

        $alert = $this->service->personalAlertFor($user, $this->shouldForceAlert($request));

        return response()->json([
            'data' => $alert,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function moduleBreakdown(Request $request, User $user)
    {
        $payload = $this->service->getModuleBreakdown($user->id, $this->extractFilters($request));

        return response()->json($payload);
    }

    public function userTimeline(Request $request, User $user)
    {
        $limit = (int) $request->input('limit', 50);

        return response()->json([
            'data' => $this->service->getUserDetailTimeline($user->id, $limit),
        ]);
    }

    public function weeklyBreakdown(Request $request, User $user)
    {
        return response()->json($this->service->getWeeklyBreakdown($user->id));
    }

    public function exportCsv(Request $request)
    {
        // Reachable directly, so the endpoint re-checks rather than trusting the
        // menu that was hidden on the page.
        $this->authorizeModule(self::MODULE, 'export');

        $branding = $this->resolveExportBranding($request);
        $payload = $this->service->getUserStatistics(
            $this->extractFilters($request),
            $request->user(),
            $this->shouldForceAlert($request)
        );
        $columns = $payload['leaderboard']['columns'] ?? [];
        $rows = $this->transformLeaderboardToRows($payload['leaderboard']['rows'] ?? [], $columns);

        $headers = $this->exportHeadings($columns);
        $filename = 'activity-monitoring-' . now()->format('Ymd_His') . '.csv';
        $window = $payload['meta']['date_range']['label']
            ?? (($request->input('date_from') && $request->input('date_to'))
                ? ($request->input('date_from') . ' to ' . $request->input('date_to'))
                : 'Current selection');

        return response()->streamDownload(function () use ($branding, $headers, $rows, $window) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [$branding['title_line_1']]);
            fputcsv($file, [$branding['title_line_2']]);
            fputcsv($file, ['Report Type', 'Monthly Activity Monitoring Report']);
            fputcsv($file, ['Reporting Window', $window]);
            fputcsv($file, ['Generated At', now()->format('Y-m-d H:i:s')]);

            if (!empty($branding['logo_text'])) {
                fputcsv($file, ['Logo', $branding['logo_text']]);
            }

            fputcsv($file, []);
            fputcsv($file, $headers);

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $row[$header] ?? '';
                }
                fputcsv($file, $line);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        // Reachable directly, so the endpoint re-checks rather than trusting the
        // menu that was hidden on the page.
        $this->authorizeModule(self::MODULE, 'export');

        $filters = $this->extractFilters($request);
        $payload = $this->service->getUserStatistics($filters, $request->user(), $this->shouldForceAlert($request));
        $branding = $this->resolveExportBranding($request);

        $pdf = Pdf::loadView('activity-monitoring.exports.summary', [
            'payload' => $payload,
            'generatedAt' => now(),
            'filters' => $payload['meta']['date_range'] ?? [],
            'branding' => $branding,
        ])->setPaper('a4', 'landscape');

        $filename = 'activity-monitoring-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function leaderboard(Request $request)
    {
        $filters = $this->extractFilters($request);
        $filters['leaderboard_limit'] = max(1, (int) $request->input('leaderboard_limit', 15));

        $payload = $this->service->getUserStatistics($filters, $request->user(), $this->shouldForceAlert($request));

        return view('activity-monitoring.leaderboard', [
            'payload' => $payload,
        ]);
    }

    private function shouldForceAlert(Request $request): bool
    {
        if (!$request->boolean('force_alert')) {
            return false;
        }

        return config('app.debug') || app()->environment(['local', 'development']);
    }

    private function extractFilters(Request $request): array
    {
        return [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'preset' => $request->input('preset', $request->input('range', 'this_week')),
            'users' => $this->parseIds($request->input('users', $request->input('user_ids'))),
            'workstations' => $this->parseWorkStations($request->input('workstations')),
            'leaderboard_limit' => $request->has('leaderboard_limit')
                ? (int) $request->input('leaderboard_limit')
                : null,
        ];
    }

    private function parseIds($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private function parseWorkStations($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value)));
        }

        return [];
    }

    private function transformLeaderboardToRows(array $rows, array $columns = []): array
    {
        $columnLabels = [];
        foreach ($columns as $column) {
            $label = $column['label'] ?? Str::title(str_replace('_', ' ', $column['key'] ?? ''));
            $columnLabels[$column['key']] = $label;
        }

        if (empty($columnLabels)) {
            $defaults = [
                'file_indexing' => 'File Indexing',
                'pra' => 'PRA',
                'pic' => 'PIC',
                'page_typing' => 'Page Typing',
                'scanning' => 'Scanning',
                'blind_scan' => 'Blind Scan',
            ];
            $columnLabels = $defaults;
        }

        return array_map(function ($row) use ($columnLabels) {
            return $this->mapRowToExport($row, $columnLabels);
        }, $rows);
    }

    private function mapRowToExport(array $row, array $columnLabels): array
    {
        $data = [
            'User' => $row['user']['name'] ?? '',
            'Department' => $row['user']['department'] ?? '',
            'Work Station' => $row['user']['work_station'] ?? '',
        ];

        foreach ($columnLabels as $key => $label) {
            $data[$label] = $row['metrics'][$key] ?? 0;
        }

        $data['Total'] = $row['total'] ?? 0;
        $data['Target'] = $row['target'] ?? 0;
        $data['Percent Complete'] = $row['percent_complete'] ?? 0;
        $data['Status'] = ucfirst(str_replace('_', ' ', $row['status'] ?? ''));

        return $data;
    }

    private function exportHeadings(array $columns = []): array
    {
        $headings = [
            'User',
            'Department',
            'Work Station',
        ];

        if (empty($columns)) {
            $columns = [
                ['key' => 'file_indexing', 'label' => 'File Indexing'],
                ['key' => 'pra', 'label' => 'PRA'],
                ['key' => 'pic', 'label' => 'PIC'],
                ['key' => 'page_typing', 'label' => 'Page Typing'],
                ['key' => 'scanning', 'label' => 'Scanning'],
                ['key' => 'blind_scan', 'label' => 'Blind Scan'],
            ];
        }

        foreach ($columns as $column) {
            $headings[] = $column['label'] ?? Str::title(str_replace('_', ' ', $column['key'] ?? ''));
        }

        $headings[] = 'Total';
        $headings[] = 'Target';
        $headings[] = 'Percent Complete';
        $headings[] = 'Status';

        return $headings;
    }

    private function resolveExportBranding(Request $request): array
    {
        $module = strtolower((string) $request->query('url', ''));

        if ($module === 'kangis') {
            return [
                'title_line_1' => 'KANO STATE GEOGRAPHIC INFORMATION SERVICE',
                'title_line_2' => 'KANGIS REGISTRY',
                'logo_left' => asset('assets/logo/kangis.jpg'),
                'logo_right' => null,
                'logo_text' => 'assets/logo/kangis.jpg',
            ];
        }

        return [
            'title_line_1' => 'MINISTRY OF LAND AND PHYSICAL PLANNING',
            'title_line_2' => 'KANO',
            'logo_left' => asset('assets/logo/ministry1.jpg'),
            'logo_right' => asset('assets/logo/ministry2.png'),
            'logo_text' => 'assets/logo/ministry1.jpg; assets/logo/ministry2.png',
        ];
    }
}
