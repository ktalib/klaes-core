<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityMonitoringService
{
    private const CACHE_TAG = 'activity-monitoring';
    private const CACHE_TTL = 900; // 15 minutes
    private ?array $moduleTargetsCache = null;
    private ?array $workStationChoicesCache = null;

    private const MODULES = [
        'file_indexing' => [
            'label' => 'File Indexing',
            'color' => '#2563eb',
            'table' => 'file_indexings',
            'user_column' => 'created_by',
            'uses_name_identifier' => true,
        ],
        'pra' => [
            'label' => 'Property Records (PRA)',
            'color' => '#0f9d58',
            'table' => 'pra',
            'user_column' => 'created_by',
        ],
        'pic' => [
            'label' => 'Property Index Cards (PIC)',
            'color' => '#c026d3',
            'table' => 'pic',
            'user_column' => 'created_by',
        ],
        'page_typing' => [
            'label' => 'Page Typing',
            'color' => '#f97316',
            'table' => 'pagetypings',
            'user_column' => 'typed_by',
        ],
        'scanning' => [
            'label' => 'Document Scanning',
            'color' => '#0891b2',
            'table' => 'scannings',
            'user_column' => 'uploaded_by',
        ],
        'blind_scan' => [
            'label' => 'Blind Scan',
            'color' => '#7c3aed',
            'table' => 'blind_scannings',
            'user_column' => 'uploaded_by',
        ],
    ];

    public function getUserStatistics(array $filters = [], ?User $viewer = null, bool $forceAlertPreview = false): array
    {
        $viewer = $viewer ?? auth()->user();
        [$startDate, $endDate, $preset] = $this->resolveDateRange($filters);
        $selectedUsers = $this->sanitizeIds($filters['users'] ?? $filters['user_ids'] ?? []);
        [$workStationFilterRaw, $workStationFilterNormalized] = $this->sanitizeWorkStationFilter($filters['workstations'] ?? []);
        $moduleFilterInput = $filters['modules'] ?? [];
        if (empty($moduleFilterInput) && !empty($workStationFilterNormalized)) {
            $moduleFilterInput = $this->modulesFromWorkStations($workStationFilterNormalized);
        }
        $activeModules = $this->resolveModuleFilter($moduleFilterInput);
        $leaderboardLimit = isset($filters['leaderboard_limit'])
            ? max(1, (int) $filters['leaderboard_limit'])
            : null;

        $cacheKey = $this->cacheKey('stats', [
            'viewer' => $viewer?->id,
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'users' => $selectedUsers,
            'modules' => $activeModules,
            'workstations' => $workStationFilterNormalized,
            'leaderboard_limit' => $leaderboardLimit,
        ]);

        $payload = $this->remember($cacheKey, function () use (
            $viewer,
            $selectedUsers,
            $activeModules,
            $startDate,
            $endDate,
            $preset,
            $leaderboardLimit,
            $workStationFilterNormalized,
            $workStationFilterRaw
        ) {
            $availableUsers = $this->loadEligibleUsers($viewer);
            $usersForStats = $selectedUsers
                ? $availableUsers->whereIn('id', $selectedUsers)
                : $availableUsers;

            if (!empty($workStationFilterNormalized)) {
                $usersForStats = $usersForStats->filter(function (User $user) use ($workStationFilterNormalized) {
                    return $this->workStationMatches($user->work_station, $workStationFilterNormalized);
                });
            }

            $usersForStats = $usersForStats->keyBy('id');

            if ($usersForStats->isEmpty()) {
                return $this->emptyPayload($availableUsers, $startDate, $endDate, $activeModules, $preset, $workStationFilterRaw);
            }

            $nameIndex = $this->buildUserNameIndex($usersForStats);
            $moduleStats = $this->collectModuleStats($startDate, $endDate, $usersForStats, $nameIndex, true);

            $rangeDays = $startDate->diffInDays($endDate) + 1;
            $previousEnd = $startDate->copy()->subDay();
            $previousStart = $previousEnd->copy()->subDays($rangeDays - 1);
            $previousStats = $this->collectModuleStats($previousStart, $previousEnd, $usersForStats, $nameIndex, false, $activeModules);

            return $this->formatResponse(
                $availableUsers,
                $usersForStats,
                $moduleStats,
                $previousStats,
                $activeModules,
                $startDate,
                $endDate,
                $preset,
                $leaderboardLimit,
                $workStationFilterRaw
            );
        });

        if ($viewer) {
            $payload['personal_alert'] = $this->personalAlertFor($viewer, $forceAlertPreview);
        } else {
            $payload['personal_alert'] = null;
        }

        return $payload;
    }

    public function personalAlertFor(?User $user, bool $forcePreview = false): ?array
    {
        if (!$user) {
            return null;
        }

        if ($this->isSuperAdminUser($user)) {
            return null;
        }

        return $this->buildDailyPersonalAlert($user, $forcePreview);
    }

    public function getModuleBreakdown(int $userId, array $dateRange): array
    {
        [$start, $end] = $this->resolveDateRange($dateRange);
        $user = User::findOrFail($userId);
        $userCollection = collect([$user->id => $user]);
        $nameIndex = $this->buildUserNameIndex($userCollection);
        $stats = $this->collectModuleStats($start, $end, $userCollection, $nameIndex, true, null);

        $dates = $this->dateSeries($start, $end);
        $categories = array_map(fn (Carbon $date) => $date->toDateString(), $dates);

        $series = [];
        foreach (self::MODULES as $key => $meta) {
            $series[] = [
                'name' => $meta['label'],
                'color' => $meta['color'],
                'data' => array_map(function (Carbon $date) use ($stats, $key, $user) {
                    return $stats[$key]['per_user_daily'][$user->id][$date->toDateString()] ?? 0;
                }, $dates),
            ];
        }

        return [
            'categories' => $categories,
            'series' => $series,
        ];
    }

    public function getUserDetailTimeline(int $userId, int $limit = 50): array
    {
        $user = User::findOrFail($userId);
        $timeline = [];

        $timeline = array_merge(
            $timeline,
            $this->timelineFromTable('file_indexings', 'created_by', $user, 'Indexed file'),
            $this->timelineFromTable('pra', 'created_by', $user, 'Created PRA record'),
            $this->timelineFromTable('pic', 'created_by', $user, 'Created PIC record'),
            $this->timelineFromTable('pagetypings', 'typed_by', $user, 'Typed page'),
            $this->timelineFromTable('scannings', 'uploaded_by', $user, 'Uploaded scan'),
            $this->timelineFromTable('blind_scannings', 'uploaded_by', $user, 'Completed blind scan')
        );

        usort($timeline, fn ($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

        return array_slice($timeline, 0, $limit);
    }

    public function getWeeklyBreakdown(int $userId): array
    {
        $user = User::findOrFail($userId);
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $userCollection = collect([$user->id => $user]);
        $nameIndex = $this->buildUserNameIndex($userCollection);
        $stats = $this->collectModuleStats($startOfWeek, $endOfWeek, $userCollection, $nameIndex, true, null);

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $series = [];

        foreach (self::MODULES as $key => $meta) {
            $series[$key] = [
                'label' => $meta['label'],
                'data' => [],
            ];

            foreach ($days as $offset => $label) {
                $day = $startOfWeek->copy()->addDays($offset)->toDateString();
                $series[$key]['data'][$label] = $stats[$key]['per_user_daily'][$user->id][$day] ?? 0;
            }
        }

        return [
            'days' => $days,
            'series' => $series,
        ];
    }

    public function flushCache(): void
    {
        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            Cache::tags(self::CACHE_TAG)->flush();
            return;
        }

        // Fallback: bump a namespace token
        Cache::forget($this->cacheKey('version', []));
    }

    private function resolveTarget(?User $user = null, ?array $modules = null): int
    {
        $modules = $modules ?: array_keys(self::MODULES);
        $moduleTargets = $this->moduleTargets();
        $total = 0;

        foreach ($modules as $module) {
            $value = $moduleTargets[$module] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                $total += (int) $value;
            }
        }

        if ($total > 0) {
            return $total;
        }

        return (int) (config('activity-monitoring.weekly_target')
            ?? env('ACTIVITY_MONITORING_WEEKLY_TARGET', 400));
    }

    private function resolveStatus(?float $percent): string
    {
        if ($percent === null) {
            return 'pending';
        }

        if ($percent >= 100) {
            return 'exceeded';
        }

        if ($percent >= 75) {
            return 'on_track';
        }

        return 'behind';
    }

    private function remember(string $key, Closure $callback)
    {
        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            return Cache::tags(self::CACHE_TAG)->remember($key, self::CACHE_TTL, $callback);
        }

        return Cache::remember($key, self::CACHE_TTL, $callback);
    }

    private function cacheKey(string $suffix, array $parts): string
    {
        return sprintf('%s.%s', self::CACHE_TAG, $suffix) . '.' . md5(json_encode($parts));
    }

    private function resolveDateRange(array $filters): array
    {
        $preset = strtolower((string) ($filters['preset'] ?? $filters['range'] ?? 'this_week'));
        $start = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : null;
        $end = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : null;

        if ($start && $end && $start->lte($end)) {
            return [$start->startOfDay(), $end->endOfDay(), $preset];
        }

        switch ($preset) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today();
                break;
            case 'last_30_days':
                $start = Carbon::today()->subDays(29);
                $end = Carbon::today();
                break;
            case 'this_month':
                $start = Carbon::today()->startOfMonth();
                $end = Carbon::today()->endOfMonth();
                break;
            case 'custom':
                $start = Carbon::today()->subDays(6);
                $end = Carbon::today();
                break;
            case 'this_week':
            default:
                $preset = 'this_week';
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay(), $preset];
    }

    private function sanitizeIds($value): array
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
            ->unique()
            ->values()
            ->all();
    }

    private function resolveModuleFilter($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return array_keys(self::MODULES);
        }

        $modules = collect($value)
            ->map(fn ($module) => strtolower(trim((string) $module)))
            ->filter(fn ($module) => $module !== '' && $module !== 'all')
            ->filter(fn ($module) => array_key_exists($module, self::MODULES))
            ->unique()
            ->values()
            ->all();

        return $modules ?: array_keys(self::MODULES);
    }

    private function loadEligibleUsers(?User $viewer): Collection
    {
        $query = User::query()
            ->with('department:id,name,color')
            ->select(['id', 'first_name', 'last_name', 'email', 'department_id', 'type', 'profile', 'username', 'work_station']);

        if ($viewer && !$this->isSuperAdminUser($viewer) && $viewer->department_id) {
            $query->where('department_id', $viewer->department_id);
        }

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->keyBy('id');
    }

    private function emptyPayload(
        Collection $availableUsers,
        Carbon $start,
        Carbon $end,
        array $modules,
        string $preset,
        array $selectedWorkStations = []
    ): array
    {
        return [
            'meta' => [
                'date_range' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'preset' => $preset,
                ],
                'active_modules' => $modules,
                'module_options' => $this->moduleOptions([]),
                'module_counts' => [],
                'auto_refresh_seconds' => 300,
                'last_refreshed_at' => Carbon::now()->toIso8601String(),
            ],
            'filters' => [
                'users' => [
                    'options' => $this->serializeUsers($availableUsers),
                    'selected' => [],
                ],
                'workstations' => [
                    'options' => $this->workStationOptions($availableUsers),
                    'selected' => $selectedWorkStations,
                ],
            ],
            'summary_cards' => ['items' => [], 'team_completion' => null],
            'summary_bar' => [],
            'charts' => [
                'activity_heatmap' => ['categories' => [], 'series' => []],
                'user_contribution' => ['series' => []],
                'module_distribution' => ['series' => []],
            ],
            'leaderboard' => ['columns' => [], 'rows' => []],
            'kpis' => [],
            'personal_alert' => null,
        ];
    }

    private function serializeUsers(Collection $users): array
    {
        return $users->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'department' => optional($user->department)->name,
                'department_color' => $this->departmentColor($user),
                'avatar' => $this->avatarUrl($user),
                'work_station' => $user->work_station,
            ];
        })->values()->all();
    }

    private function buildUserNameIndex(Collection $users): array
    {
        $index = [];

        foreach ($users as $user) {
            $candidates = [
                $user->name,
                trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                $user->username ?? null,
            ];

            foreach ($candidates as $candidate) {
                $normalized = $this->normalizeName($candidate);
                if ($normalized) {
                    $index[$normalized][] = $user->id;
                }
            }
        }

        return array_map(fn ($ids) => array_values(array_unique($ids)), $index);
    }

    private function collectModuleStats(
        Carbon $start,
        Carbon $end,
        Collection $users,
        array $nameIndex,
        bool $withDaily,
        ?array $onlyModules = null
    ): array {
        $stats = [];
        $requestedModules = [];

        foreach (self::MODULES as $key => $meta) {
            if (empty($onlyModules) || in_array($key, $onlyModules, true)) {
                $requestedModules[] = $key;
            }
        }

        foreach ($requestedModules as $key) {
            $meta = self::MODULES[$key];
            if (!empty($meta['uses_name_identifier'])) {
                $stats[$key] = $this->aggregateFileIndexing($start, $end, $users, $nameIndex, $withDaily);
            } else {
                $stats[$key] = $this->aggregateNumericModule(
                    $meta['table'],
                    $meta['user_column'],
                    $start,
                    $end,
                    $users,
                    $nameIndex,
                    $withDaily
                );
            }

            $stats[$key]['label'] = $meta['label'];
            $stats[$key]['color'] = $meta['color'];
        }

        foreach (self::MODULES as $key => $meta) {
            if (!isset($stats[$key])) {
                $stats[$key] = $this->blankModulePayload($withDaily);
                $stats[$key]['label'] = $meta['label'];
                $stats[$key]['color'] = $meta['color'];
            }
        }

        return $stats;
    }

    private function aggregateNumericModule(
        string $table,
        string $userColumn,
        Carbon $start,
        Carbon $end,
        Collection $users,
        array $nameIndex,
        bool $withDaily
    ): array {
        $payload = $this->blankModulePayload($withDaily);
        if ($users->isEmpty()) {
            return $payload;
        }
        $baseQuery = DB::connection('sqlsrv')
            ->table($table)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull($userColumn);

        $rows = (clone $baseQuery)
            ->selectRaw("{$userColumn} as identifier, COUNT(*) as total")
            ->groupBy($userColumn)
            ->get();

        foreach ($rows as $row) {
            $matched = $this->matchUserIdentifiers($row->identifier ?? null, $users, $nameIndex);
            if (empty($matched)) {
                continue;
            }

            $count = (int) $row->total;
            foreach ($matched as $userId) {
                $payload['per_user'][$userId] = ($payload['per_user'][$userId] ?? 0) + $count;
            }
            $payload['total'] += $count;
        }

        if ($withDaily) {
            $dailyRows = $baseQuery
                ->selectRaw("{$userColumn} as identifier, CAST(created_at AS date) as activity_date, COUNT(*) as total")
                ->groupBy($userColumn, DB::raw('CAST(created_at AS date)'))
                ->get();

            foreach ($dailyRows as $row) {
                $matched = $this->matchUserIdentifiers($row->identifier ?? null, $users, $nameIndex);
                if (empty($matched)) {
                    continue;
                }

                $dateKey = $row->activity_date instanceof Carbon
                    ? $row->activity_date->toDateString()
                    : Carbon::parse($row->activity_date)->toDateString();
                $count = (int) $row->total;

                $payload['per_day'][$dateKey] = ($payload['per_day'][$dateKey] ?? 0) + $count;
                foreach ($matched as $userId) {
                    $payload['per_user_daily'][$userId][$dateKey] = ($payload['per_user_daily'][$userId][$dateKey] ?? 0) + $count;
                }
            }
        }

        return $payload;
    }

    private function aggregateFileIndexing(
        Carbon $start,
        Carbon $end,
        Collection $users,
        array $nameIndex,
        bool $withDaily
    ): array {
        $payload = $this->blankModulePayload($withDaily);
        $baseQuery = FileIndexing::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($withDaily) {
            $dailyRows = (clone $baseQuery)
                ->selectRaw('created_by, CAST(created_at AS date) as activity_date, COUNT(*) as total')
                ->groupBy('created_by', DB::raw('CAST(created_at AS date)'))
                ->get();

            foreach ($dailyRows as $row) {
                $matched = $this->matchFileIndexingUserIds($row->created_by, $users, $nameIndex);
                if (empty($matched)) {
                    continue;
                }

                $dateKey = $row->activity_date instanceof Carbon
                    ? $row->activity_date->toDateString()
                    : Carbon::parse($row->activity_date)->toDateString();
                $count = (int) $row->total;

                $payload['total'] += $count;
                $payload['per_day'][$dateKey] = ($payload['per_day'][$dateKey] ?? 0) + $count;

                foreach ($matched as $userId) {
                    $payload['per_user'][$userId] = ($payload['per_user'][$userId] ?? 0) + $count;
                    $payload['per_user_daily'][$userId][$dateKey] = ($payload['per_user_daily'][$userId][$dateKey] ?? 0) + $count;
                }
            }

            return $payload;
        }

        $rows = (clone $baseQuery)
            ->selectRaw('created_by, COUNT(*) as total')
            ->groupBy('created_by')
            ->get();

        foreach ($rows as $row) {
            $matched = $this->matchFileIndexingUserIds($row->created_by, $users, $nameIndex);
            if (empty($matched)) {
                continue;
            }

            $count = (int) $row->total;
            foreach ($matched as $userId) {
                $payload['per_user'][$userId] = ($payload['per_user'][$userId] ?? 0) + $count;
            }

            $payload['total'] += $count;
        }

        return $payload;
    }

    private function blankModulePayload(bool $withDaily): array
    {
        return [
            'per_user' => [],
            'per_day' => $withDaily ? [] : [],
            'per_user_daily' => $withDaily ? [] : [],
            'total' => 0,
        ];
    }

    private function formatResponse(
        Collection $availableUsers,
        Collection $users,
        array $moduleStats,
        array $previousStats,
        array $activeModules,
        Carbon $start,
        Carbon $end,
        string $preset,
        ?int $leaderboardLimit = null,
        array $selectedWorkStations = []
    ): array {
        $moduleCounts = $this->moduleCounts($moduleStats);
        $moduleOptions = $this->moduleOptions($moduleStats);

        $cards = $this->buildCards($moduleStats, $previousStats, $users, $activeModules);
        $leaderboard = $this->buildLeaderboard($users, $moduleStats, $activeModules, $start, $end, $leaderboardLimit);
        $summaryBar = $this->buildSummaryBar($moduleStats, $previousStats, $activeModules);
        $charts = $this->buildCharts($moduleStats, $activeModules, $start, $end, $users);

        return [
            'meta' => [
                'date_range' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'preset' => $preset,
                ],
                'active_modules' => $activeModules,
                'module_options' => $moduleOptions,
                'module_counts' => $moduleCounts,
                'auto_refresh_seconds' => 300,
                'last_refreshed_at' => Carbon::now()->toIso8601String(),
            ],
            'filters' => [
                'users' => [
                    'options' => $this->serializeUsers($availableUsers),
                    'selected' => $users->keys()->values()->all(),
                ],
                'workstations' => [
                    'options' => $this->workStationOptions($availableUsers),
                    'selected' => $selectedWorkStations,
                ],
            ],
            'summary_cards' => $cards,
            'summary_bar' => $summaryBar,
            'charts' => $charts,
            'leaderboard' => $leaderboard,
            'kpis' => [
                'team_completion' => $cards['team_completion'] ?? null,
            ],
        ];
    }

    private function buildCards(
        array $moduleStats,
        array $previousStats,
        Collection $users,
        array $activeModules
    ): array {
        $active = $activeModules ?: array_keys(self::MODULES);
        $targetPerUser = $this->resolveTarget(null, $active);
        $userCount = max(1, $users->count());

        $filesIndexed = in_array('file_indexing', $active, true) ? $moduleStats['file_indexing']['total'] : 0;
        $recordsEntered = 0;
        foreach (['pra', 'pic'] as $module) {
            if (in_array($module, $active, true)) {
                $recordsEntered += $moduleStats[$module]['total'] ?? 0;
            }
        }

        $pagesProcessed = 0;
        foreach (['page_typing', 'scanning', 'blind_scan'] as $module) {
            if (in_array($module, $active, true)) {
                $pagesProcessed += $moduleStats[$module]['total'] ?? 0;
            }
        }

        $activeTotals = $this->aggregateUserTotals($moduleStats, $users, $active);
        $teamTotal = array_sum($activeTotals);
        $teamPercent = $targetPerUser > 0 ? round(($teamTotal / ($targetPerUser * $userCount)) * 100, 1) : null;

        $previousTotals = $this->aggregateUserTotals($previousStats, $users, $active);
        $previousTotal = array_sum($previousTotals);
        $previousPercent = $targetPerUser > 0 ? round(($previousTotal / ($targetPerUser * $userCount)) * 100, 1) : null;

        $cards = [
            [
                'key' => 'file_indexing',
                'label' => 'Total Files Indexed',
                'value' => $filesIndexed,
            ],
            [
                'key' => 'records_entered',
                'label' => 'Total Records Entered',
                'value' => $recordsEntered,
            ],
            [
                'key' => 'pages_processed',
                'label' => 'Total Pages Processed',
                'value' => $pagesProcessed,
            ],
            [
                'key' => 'team_completion_rate',
                'label' => 'Team Completion Rate',
                'value' => $teamPercent,
                'suffix' => '%',
            ],
        ];

        return [
            'items' => $cards,
            'team_completion' => [
                'current' => $teamPercent,
                'previous' => $previousPercent,
                'direction' => $this->trendDirection($teamPercent, $previousPercent),
                'total' => $teamTotal,
                'target' => $targetPerUser * $userCount,
            ],
        ];
    }

    private function buildSummaryBar(array $moduleStats, array $previousStats, array $activeModules): array
    {
        $items = [];
        $modules = [
            'file_indexing' => 'Files Indexed',
            'pra' => 'PRA',
            'pic' => 'PIC',
            'page_typing' => 'Pages Typed',
            'scanning' => 'Scanned',
            'blind_scan' => 'Blind Scan',
        ];

        foreach ($modules as $key => $label) {
            if (!in_array($key, $activeModules, true)) {
                continue;
            }

            $current = $moduleStats[$key]['total'] ?? 0;
            $previous = $previousStats[$key]['total'] ?? 0;

            $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

            $items[] = [
                'key' => $key,
                'label' => $label,
                'value' => $current,
                'trend' => [
                    'direction' => $this->trendDirection($current, $previous),
                    'change' => $change,
                ],
            ];
        }

        return $items;
    }

    private function buildCharts(
        array $moduleStats,
        array $activeModules,
        Carbon $start,
        Carbon $end,
        Collection $users
    ): array {
        $modules = $activeModules ?: array_keys(self::MODULES);
        $chartStart = $start->copy();
        $lookback = $end->copy()->subDays(29);
        if ($lookback->greaterThan($chartStart)) {
            $chartStart = $lookback;
        }

        $dates = $this->dateSeries($chartStart, $end);
        $categories = array_map(fn (Carbon $date) => $date->toDateString(), $dates);

        $heatmapSeries = [];
        foreach ($modules as $module) {
            $heatmapSeries[] = [
                'name' => self::MODULES[$module]['label'],
                'color' => self::MODULES[$module]['color'],
                'data' => array_map(fn ($dateKey) => $moduleStats[$module]['per_day'][$dateKey] ?? 0, $categories),
            ];
        }

        $contributions = [];
        foreach ($users as $user) {
            $value = 0;
            foreach ($modules as $module) {
                $value += $moduleStats[$module]['per_user'][$user->id] ?? 0;
            }

            $contributions[] = [
                'id' => $user->id,
                'name' => $user->name,
                'value' => $value,
            ];
        }

        usort($contributions, fn ($a, $b) => $b['value'] <=> $a['value']);
        $top = array_slice($contributions, 0, 5);
        $othersTotal = array_sum(array_column(array_slice($contributions, 5), 'value'));
        if ($othersTotal > 0) {
            $top[] = ['id' => null, 'name' => 'Others', 'value' => $othersTotal];
        }

        $moduleDistribution = [];
        foreach ($modules as $module) {
            $segments = [];
            foreach ($users as $user) {
                $segments[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'value' => $moduleStats[$module]['per_user'][$user->id] ?? 0,
                ];
            }

            $moduleDistribution[] = [
                'key' => $module,
                'label' => self::MODULES[$module]['label'],
                'color' => self::MODULES[$module]['color'],
                'segments' => $segments,
                'total' => $moduleStats[$module]['total'] ?? 0,
            ];
        }

        return [
            'activity_heatmap' => [
                'categories' => $categories,
                'series' => $heatmapSeries,
            ],
            'user_contribution' => [
                'series' => $top,
            ],
            'module_distribution' => [
                'series' => $moduleDistribution,
            ],
        ];
    }

    private function buildLeaderboard(
        Collection $users,
        array $moduleStats,
        array $activeModules,
        Carbon $start,
        Carbon $end,
        ?int $limitOverride = null
    ): array {
        $visibleModules = $activeModules ?: array_keys(self::MODULES);
        $columns = [];
        foreach ($visibleModules as $key) {
            $meta = self::MODULES[$key];
            $columns[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
            ];
        }

        $sparkStart = $end->copy()->subDays(6);
        if ($sparkStart->lessThan($start)) {
            $sparkStart = $start->copy();
        }
        $sparklineDates = $this->dateSeries($sparkStart, $end);

        $totals = $this->aggregateUserTotals($moduleStats, $users, $visibleModules);
        arsort($totals);
        $totalUsers = count($totals);
        $limit = $limitOverride ?? max(5, (int) config('activity-monitoring.leaderboard_limit', 60));
        $topUserIds = array_slice(array_keys($totals), 0, $limit);

        $rows = [];
        foreach ($topUserIds as $userId) {
            $user = $users->get($userId);
            if (!$user) {
                continue;
            }

            $metrics = [];
            $fullTotal = 0;

            foreach ($visibleModules as $key) {
                $value = $moduleStats[$key]['per_user'][$userId] ?? 0;
                $metrics[$key] = $value;
                $fullTotal += $value;
            }

            $target = $this->resolveTarget($user, $visibleModules);
            $percent = $target > 0 ? round(($fullTotal / $target) * 100, 1) : null;
            $status = $this->resolveStatus($percent);

            $sparkline = [];
            foreach ($sparklineDates as $date) {
                $dateKey = $date->toDateString();
                $dailyTotal = 0;
                foreach ($visibleModules as $module) {
                    $dailyTotal += $moduleStats[$module]['per_user_daily'][$userId][$dateKey] ?? 0;
                }
                $sparkline[] = $dailyTotal;
            }

            $rows[] = [
                'user' => [
                    'id' => $userId,
                    'name' => $user->name,
                    'department' => optional($user->department)->name,
                    'department_color' => $this->departmentColor($user),
                    'avatar' => $this->avatarUrl($user),
                    'work_station' => $user->work_station,
                ],
                'metrics' => $metrics,
                'total' => $fullTotal,
                'target' => $target,
                'percent_complete' => $percent,
                'status' => $status,
                'sparkline' => $sparkline,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'meta' => [
                'limited' => $totalUsers > $limit,
                'limit' => $limit,
                'total' => $totalUsers,
            ],
        ];
    }

    private function moduleTargets(): array
    {
        if ($this->moduleTargetsCache !== null) {
            return $this->moduleTargetsCache;
        }

        $settings = function_exists('settings') ? settings() : [];
        $targets = [];

        foreach (array_keys(self::MODULES) as $module) {
            $value = $settings['activity_target_' . $module] ?? null;
            $targets[$module] = is_numeric($value) ? (int) $value : null;
        }

        return $this->moduleTargetsCache = $targets;
    }

    private function aggregateUserTotals(array $moduleStats, Collection $users, array $modules): array
    {
        $modules = $modules ?: array_keys(self::MODULES);
        $totals = [];

        foreach ($users as $user) {
            $total = 0;
            foreach ($modules as $module) {
                $total += $moduleStats[$module]['per_user'][$user->id] ?? 0;
            }
            $totals[$user->id] = $total;
        }

        return $totals;
    }

    private function moduleCounts(array $moduleStats): array
    {
        $counts = [];
        foreach (self::MODULES as $key => $meta) {
            $counts[$key] = $moduleStats[$key]['total'] ?? 0;
        }

        return $counts;
    }

    private function moduleOptions(array $moduleStats): array
    {
        $options = [];
        foreach (self::MODULES as $key => $meta) {
            $options[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'count' => $moduleStats[$key]['total'] ?? 0,
            ];
        }

        return $options;
    }

    private function workStationOptions(Collection $users): array
    {
        $options = [];
        $order = 0;

        foreach ($this->workStationDefinitions() as $definition) {
            $normalized = $this->normalizeWorkStationValue($definition['label']);
            if (!$normalized) {
                continue;
            }

            $options[$normalized] = [
                'value' => $definition['label'],
                'label' => $definition['label'],
                'count' => 0,
                'order' => $order++,
            ];
        }

        foreach ($users as $user) {
            $normalized = $this->normalizeWorkStationValue($user->work_station);
            if (!$normalized) {
                continue;
            }

            if (!isset($options[$normalized])) {
                $options[$normalized] = [
                    'value' => $user->work_station,
                    'label' => $user->work_station,
                    'count' => 0,
                    'order' => $order++,
                ];
            }

            $options[$normalized]['count']++;
        }

        uasort($options, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return array_values(array_map(function ($option) {
            return [
                'value' => $option['value'],
                'label' => $option['label'],
                'count' => $option['count'],
            ];
        }, $options));
    }

    private function sanitizeWorkStationFilter($value): array
    {
        if (is_null($value)) {
            return [[], []];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [[], []];
        }

        $raw = collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();

        $normalized = array_values(array_filter(array_map(fn ($item) => $this->normalizeWorkStationValue($item), $raw)));

        return [$raw, $normalized];
    }

    private function workStationMatches(?string $workStation, array $normalizedFilter): bool
    {
        if (empty($normalizedFilter)) {
            return true;
        }

        $value = $this->normalizeWorkStationValue($workStation);
        if ($value === null) {
            return false;
        }

        return in_array($value, $normalizedFilter, true);
    }

    private function modulesFromWorkStations(array $normalizedValues): array
    {
        if (empty($normalizedValues)) {
            return [];
        }

        $map = [];
        foreach ($this->workStationDefinitions() as $definition) {
            $normalized = $this->normalizeWorkStationValue($definition['label']);
            if (!$normalized || empty($definition['module_key'])) {
                continue;
            }
            $map[$normalized] = $definition['module_key'];
        }

        $modules = [];
        foreach ($normalizedValues as $value) {
            if (isset($map[$value])) {
                $modules[] = $map[$value];
            }
        }

        return array_values(array_unique($modules));
    }

    private function buildDailyPersonalAlert(?User $user, bool $forcePreview = false): ?array
    {
        if (!$user && !$forcePreview) {
            return null;
        }

        [$windowStart, $windowEnd] = $this->workdayWindow();
        $now = Carbon::now();

        $modules = $this->determineModulesForUser($user);
        $targets = config('activity-monitoring.daily_targets', []);
        $monitoredModules = array_values(array_filter($modules, function ($module) use ($targets) {
            return isset($targets[$module]) && (int) $targets[$module] > 0;
        }));

        if (empty($monitoredModules) && $forcePreview) {
            $monitoredModules = $this->previewModulesFromTargets($targets);
        }

        if (empty($monitoredModules)) {
            return [
                'status' => 'no_target',
                'tone' => 'info',
                'headline' => 'No daily target assigned',
                'details' => 'Set your workspace to File Indexing, File History, PIC or PRA so we can track your shift target.',
                'modules' => [],
                'target' => 0,
                'achieved' => 0,
                'percent' => null,
                'expected' => 0,
                'window' => $this->formatWorkdayWindow($windowStart, $windowEnd),
            ];
        }

        $targetTotal = array_sum(array_map(fn ($module) => (int) ($targets[$module] ?? 0), $monitoredModules));

        if ($targetTotal <= 0 && $forcePreview) {
            $monitoredModules = $this->previewModulesFromTargets($targets);
            $targetTotal = array_sum(array_map(fn ($module) => (int) ($targets[$module] ?? 0), $monitoredModules));
        }

        if ($targetTotal <= 0) {
            return [
                'status' => 'no_target',
                'tone' => 'info',
                'headline' => 'Targets not configured',
                'details' => 'Define numeric goals for your module inside Settings ▸ Activity Monitoring.',
                'modules' => [],
                'target' => 0,
                'achieved' => 0,
                'percent' => null,
                'expected' => 0,
                'window' => $this->formatWorkdayWindow($windowStart, $windowEnd),
            ];
        }

        if ($now->lt($windowStart) && !$forcePreview) {
            return [
                'status' => 'pre_shift',
                'tone' => 'info',
                'headline' => sprintf('Shift starts at %s', $windowStart->format('g:i A')),
                'details' => 'We will begin evaluating your progress once the 9 AM work window opens.',
                'modules' => [],
                'target' => $targetTotal,
                'achieved' => 0,
                'percent' => 0,
                'expected' => 0,
                'window' => $this->formatWorkdayWindow($windowStart, $windowEnd),
            ];
        }

        $effectiveEnd = $now->gt($windowEnd) ? $windowEnd : $now;
        if ($forcePreview && $effectiveEnd->lt($windowStart)) {
            $effectiveEnd = min($windowEnd, $windowStart->copy()->addHours(4));
        }

        $userCollection = collect([$user->id => $user]);
        $nameIndex = $this->buildUserNameIndex($userCollection);
        $stats = $this->collectModuleStats($windowStart, $effectiveEnd, $userCollection, $nameIndex, false, $monitoredModules);

        $breakdown = [];
        $achieved = 0;
        foreach ($monitoredModules as $module) {
            $count = $stats[$module]['per_user'][$user->id] ?? 0;
            $breakdown[] = [
                'key' => $module,
                'label' => self::MODULES[$module]['label'] ?? Str::title(str_replace('_', ' ', $module)),
                'count' => $count,
                'target' => (int) ($targets[$module] ?? 0),
            ];
            $achieved += $count;
        }

        if ($forcePreview && $achieved === 0 && $targetTotal > 0) {
            foreach ($breakdown as &$moduleBreakdown) {
                $moduleBreakdown['count'] = max(1, (int) round(($moduleBreakdown['target'] ?? 0) * 0.4));
            }
            unset($moduleBreakdown);
            $achieved = array_sum(array_column($breakdown, 'count')) ?: max(1, (int) round($targetTotal * 0.4));
        }

        $percent = $targetTotal > 0 ? round(($achieved / $targetTotal) * 100, 1) : null;
        $windowMinutes = max(1, $windowEnd->diffInMinutes($windowStart));
        $elapsedMinutes = max(0, $effectiveEnd->diffInMinutes($windowStart));
        if ($forcePreview && $elapsedMinutes === 0) {
            $elapsedMinutes = (int) floor($windowMinutes / 2);
        }
        $expectedByNow = round($targetTotal * min(1, $elapsedMinutes / $windowMinutes), 1);
        if ($forcePreview && $expectedByNow <= 0 && $targetTotal > 0) {
            $expectedByNow = round($targetTotal * 0.5, 1);
        }

        [$status, $tone, $headline, $details] = $this->deriveAlertCopy(
            $achieved,
            $targetTotal,
            $expectedByNow,
            $now->gte($windowEnd)
        );

        return [
            'status' => $status,
            'tone' => $tone,
            'headline' => $headline,
            'details' => $details,
            'modules' => $breakdown,
            'target' => $targetTotal,
            'achieved' => $achieved,
            'percent' => $percent,
            'expected' => $expectedByNow,
            'window' => $this->formatWorkdayWindow($windowStart, $windowEnd),
        ];
    }

    private function determineModulesForUser(User $user): array
    {
        $normalized = $this->normalizeWorkStationValue($user->work_station);
        if (!$normalized) {
            return [];
        }

        $modules = $this->modulesFromWorkStations([$normalized]);

        return array_values(array_filter($modules, fn ($module) => array_key_exists($module, self::MODULES)));
    }

    private function workdayWindow(): array
    {
        $config = config('activity-monitoring.workday', []);
        $startHour = (int) ($config['start_hour'] ?? 9);
        $endHour = (int) ($config['end_hour'] ?? 17);

        $start = Carbon::today()->setHour($startHour)->setMinute(0)->setSecond(0);
        $end = Carbon::today()->setHour($endHour)->setMinute(0)->setSecond(0);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHours(8);
        }

        return [$start, $end];
    }

    private function formatWorkdayWindow(Carbon $start, Carbon $end): array
    {
        return [
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'label' => sprintf('%s - %s', $start->format('g:i A'), $end->format('g:i A')),
        ];
    }

    private function deriveAlertCopy(int $achieved, int $target, float $expectedByNow, bool $shiftEnded): array
    {
        if ($target <= 0) {
            return ['no_target', 'info', 'Targets not configured', 'Configure module targets to enable shift prompts.'];
        }

        if ($achieved >= $target) {
            return [
                'completed',
                'success',
                'Great job!',
                sprintf('You cleared today\'s goal with %d of %d records.', $achieved, $target),
            ];
        }

        if ($shiftEnded) {
            return [
                'missed',
                'danger',
                'Target missed',
                sprintf('The shift closed at %d of %d. Flag blockers and plan a catch-up.', $achieved, $target),
            ];
        }

        if ($expectedByNow <= 0) {
            return [
                'warming_up',
                'info',
                'Shift in progress',
                'Logging the first few records will put you ahead of the pace.',
            ];
        }

        $paceFloor = max(1, $expectedByNow * 0.9);
        if ($achieved >= $paceFloor) {
            return [
                'on_track',
                'success',
                'Good pace',
                sprintf('You\'re on track with %d of %d logged. Keep it up!', $achieved, $target),
            ];
        }

        $gap = max(0, round($expectedByNow - $achieved));
        return [
            'behind',
            'warning',
            'Heads up',
            $gap > 0
                ? sprintf('You\'re about %d record%s behind the %s pace (%d / %d).', $gap, $gap === 1 ? '' : 's', '9AM-5PM', $achieved, $target)
                : sprintf('You\'re tracking below the expected pace (%d / %d).', $achieved, $target),
        ];
    }

    private function previewModulesFromTargets(array $targets): array
    {
        $modules = [];

        foreach ($targets as $module => $value) {
            if ((int) $value > 0 && array_key_exists($module, self::MODULES)) {
                $modules[] = $module;
            }
        }

        if (empty($modules)) {
            $fallbackOrder = ['file_indexing', 'pic', 'pra'];
            foreach ($fallbackOrder as $key) {
                if (array_key_exists($key, self::MODULES)) {
                    $modules[] = $key;
                }
            }
        }

        if (empty($modules)) {
            $modules = array_keys(self::MODULES);
        }

        return array_slice(array_values(array_unique($modules)), 0, 3);
    }

    private function workStationDefinitions(): array
    {
        if ($this->workStationChoicesCache !== null) {
            return $this->workStationChoicesCache;
        }

        $definitions = collect(config('workstations.options', []))
            ->map(function ($option) {
                if (is_array($option)) {
                    return [
                        'label' => trim((string) ($option['label'] ?? $option['value'] ?? '')),
                        'module_key' => $option['module_key'] ?? $option['module'] ?? null,
                    ];
                }

                return [
                    'label' => trim((string) $option),
                    'module_key' => null,
                ];
            })
            ->filter(fn ($definition) => $definition['label'] !== '')
            ->values()
            ->all();

    return $this->workStationChoicesCache = $definitions;
    }

    private function timelineFromTable(string $table, string $userColumn, User $user, string $label, int $limit = 25): array
    {
        $query = DB::connection('sqlsrv')->table($table)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($table === 'file_indexings' && $userColumn === 'created_by') {
            $query->whereRaw('LOWER(LTRIM(RTRIM(created_by))) = ?', [$this->normalizeName($user->name)]);
        } else {
            $query->where($userColumn, $user->id);
        }

        return $query->get()->map(function ($row) use ($label, $table) {
            $row = (array) $row;
            return [
                'module' => $table,
                'timestamp' => isset($row['created_at']) ? Carbon::parse($row['created_at'])->toDateTimeString() : null,
                'label' => $label,
                'reference' => $row['file_number'] ?? $row['temp_file_id'] ?? $row['id'] ?? null,
                'meta' => $row,
            ];
        })->all();
    }

    private function matchFileIndexingUserIds($rawValue, Collection $users, array $nameIndex): array
    {
        return $this->matchUserIdentifiers($rawValue, $users, $nameIndex);
    }

    private function matchUserIdentifiers($rawValue, Collection $users, array $nameIndex): array
    {
        $value = trim((string) $rawValue);
        if ($value === '') {
            return [];
        }

        if (is_numeric($value)) {
            $userId = (int) $value;
            return $users->has($userId) ? [$userId] : [];
        }

        $normalized = $this->normalizeName($value);
        if ($normalized && isset($nameIndex[$normalized])) {
            return $nameIndex[$normalized];
        }

        if (str_contains($value, ',')) {
            $matched = [];
            foreach (explode(',', $value) as $segment) {
                $segmentKey = $this->normalizeName($segment);
                if ($segmentKey && isset($nameIndex[$segmentKey])) {
                    $matched = array_merge($matched, $nameIndex[$segmentKey]);
                }
            }

            if (!empty($matched)) {
                return array_values(array_unique($matched));
            }
        }

        return [];
    }

    private function normalizeWorkStationValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_strtolower($value);
    }

    private function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\(.*?\)/', '', $value);
        $value = preg_replace('/\s+-\s+\d+$/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtolower($value);
    }

    private function departmentColor(User $user): string
    {
        $color = optional($user->department)->color;
        if ($color) {
            return $color;
        }

        $seed = optional($user->department)->name ?? (string) $user->department_id ?? $user->name;
        return $this->colorFromSeed($seed);
    }

    private function colorFromSeed(string $seed): string
    {
        if ($seed === '') {
            return '#cbd5f5';
        }

        $palette = [
            '#2563eb',
            '#16a34a',
            '#db2777',
            '#f97316',
            '#0ea5e9',
            '#7c3aed',
            '#059669',
            '#d97706',
            '#ef4444',
            '#0f172a',
        ];

        $index = hexdec(substr(md5($seed), 0, 2)) % count($palette);
        return $palette[$index];
    }

    private function isSuperAdminUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (strcasecmp($user->type, 'super admin') === 0) {
            return true;
        }

        $roles = $user->assignedRoleNames();
        return in_array('super admin', $roles, true) || in_array('supper admin', $roles, true);
    }

    private function avatarUrl(User $user): ?string
    {
        if (empty($user->profile)) {
            return null;
        }

        if (str_starts_with($user->profile, 'http')) {
            return $user->profile;
        }

        return asset('storage/' . ltrim($user->profile, '/'));
    }

    private function dateSeries(Carbon $start, Carbon $end): array
    {
        if ($end->lessThan($start)) {
            return [];
        }

        $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());
        return iterator_to_array($period);
    }

    private function trendDirection(?float $current, ?float $previous): string
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return $current > 0 ? 'up' : 'flat';
        }

        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'flat';
    }
}
