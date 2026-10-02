<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDailySnapshot;
use App\Models\Payroll\PayrollAttendance;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollSalary;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\Attendance\AttendanceProcessingService;
use App\Services\Payroll\AttendanceService;
use App\Services\Payroll\PayrollSummaryService;
use App\Services\Payroll\RateService;
use App\Services\Payroll\SalaryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollDataController extends Controller
{
    protected array $expectedDaysCache = [];

    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceProcessingService $attendanceProcessingService,
        protected SalaryService $salaryService,
        protected PayrollSummaryService $summaryService,
        protected RateService $rateService,
    ) {
    }

    public function attendance(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return $this->notFound('No payroll periods available.');
        }

        $departmentId = $request->query('department_id');

        $query = PayrollAttendance::query()
            ->where('period_id', $period->id)
            ->with([
                'user' => fn ($relation) => $relation->select('id', 'first_name', 'last_name', 'department_id', 'work_station', 'man_hours_per_day'),
                'department:id,name',
            ])
            ->orderBy('hours_worked', 'desc');

        if ($departmentId !== null && $departmentId !== '') {
            $query->where('department_id', (int) $departmentId);
        }

        $records = $query->get()->map(function (PayrollAttendance $attendance) use ($period) {
            $supplement = $this->attendanceService->summarizeStoredAttendance($attendance, $period);
            $expectedDays = $this->expectedWorkingDaysForUser($period, $attendance->user);
            $shiftHours = (int) ($supplement['shift_hours'] ?? ($attendance->user?->man_hours_per_day ?? 8));
            $loginDays = (float) ($supplement['days_worked'] ?? $attendance->login_days ?? 0);

            $dailySummary = $supplement['daily_summary'] ?? [];
            $cumulativeHours = (float) ($supplement['worked_hours'] ?? $attendance->hours_worked);
            if (!empty($dailySummary)) {
                $lastEntry = $dailySummary[count($dailySummary) - 1];
                if (is_array($lastEntry) && array_key_exists('cumulative_hours', $lastEntry)) {
                    $cumulativeHours = (float) $lastEntry['cumulative_hours'];
                }
            }

            $sessionBreakdown = $attendance->session_breakdown ?? [];
            $autoLogoutSessions = 0;
            if (is_array($sessionBreakdown)) {
                foreach ($sessionBreakdown as $entry) {
                    if (!empty($entry['auto_logout'])) {
                        $autoLogoutSessions++;
                    }
                }
            }

            $attendancePercentage = 0.0;
            if ($expectedDays > 0) {
                $attendancePercentage = round(
                    min(100, ($loginDays / $expectedDays) * 100),
                    1
                );
            }

            return [
                'user_id' => $attendance->user_id,
                'user_name' => $attendance->user?->name,
                'department_id' => $attendance->department_id,
                'department_name' => $attendance->department?->name,
                'work_station' => $attendance->user?->work_station,
                'login_days' => $loginDays,
                'hours_worked' => (float) ($supplement['worked_hours'] ?? $attendance->hours_worked),
                'overtime_hours' => (float) ($supplement['overtime_hours'] ?? $attendance->overtime_hours),
                'shift_hours' => $shiftHours,
                'expected_login_days' => $expectedDays,
                'expected_hours' => round($shiftHours * $expectedDays, 2),
                'attendance_percentage' => $attendancePercentage,
                'cumulative_hours' => $cumulativeHours,
                'daily_summary' => $dailySummary,
                'auto_logout_sessions' => $autoLogoutSessions,
                'overtime_authorized' => (bool) ($supplement['allow_overtime'] ?? false),
                'overtime_cap_hours' => $supplement['overtime_cap'] ?? null,
                'session_breakdown' => $sessionBreakdown,
                'source_reference' => $attendance->source_reference,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Attendance data retrieved successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'attendance' => $records,
            ],
        ]);
    }

    public function dailyLogins(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return $this->notFound('No payroll periods available.');
        }

        $dateInput = $request->query('date');

        try {
            $targetDate = $dateInput
                ? Carbon::parse($dateInput, config('app.timezone'))
                : Carbon::now(config('app.timezone'));
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date supplied.',
                'data' => [],
            ], 422);
        }

        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd = $targetDate->copy()->endOfDay();
        $periodStart = $period->periodStart()->startOfDay();
        $periodEnd = $period->periodEnd()->endOfDay();

        if ($dayEnd->lt($periodStart) || $dayStart->gt($periodEnd)) {
            return response()->json([
                'success' => true,
                'message' => 'No login sessions within the selected payroll period.',
                'data' => [
                    'date' => $dayStart->toDateString(),
                    'items' => [],
                ],
            ]);
        }

        $logs = UserActivityLog::query()
            ->with([
                'user' => fn ($relation) => $relation->select(
                    'id',
                    'first_name',
                    'last_name',
                    'department_id',
                    'work_station',
                    'work_days_per_week',
                    'man_hours_per_day',
                    'shift_code',
                    'staff_type_category'
                ),
                'user.department:id,name',
            ])
            ->whereBetween('login_time', [$dayStart, $dayEnd])
            ->whereHas('user', function ($relation) {
                $relation->where('is_active', 1)
                    ->where('staff_type_category', 'MDC');
            })
            ->orderBy('user_id')
            ->orderBy('login_time')
            ->get();

        $userIds = $logs->pluck('user_id')->filter()->unique();
        $snapshotsByUser = collect();

        if ($userIds->isNotEmpty()) {
            $snapshotRecords = AttendanceDailySnapshot::query()
                ->whereIn('user_id', $userIds)
                ->whereDate('attendance_date', $dayStart->toDateString())
                ->orderByDesc('login_time')
                ->orderByDesc('id')
                ->get();

            $snapshotsByUser = $snapshotRecords->groupBy('user_id')->map(static function ($items) {
                return $items->first();
            });
        }

        $now = Carbon::now(config('app.timezone'));
        $attendanceConfig = config('attendance');
        $shiftDefinitions = $attendanceConfig['shifts'] ?? [];
        $lateToleranceMinutes = (int) ($attendanceConfig['late_tolerance_minutes'] ?? 0);

        $items = $logs->groupBy('user_id')->map(function ($sessions) use ($dayStart, $dayEnd, $now, $snapshotsByUser, $shiftDefinitions, $lateToleranceMinutes) {
            /** @var \Illuminate\Support\Collection $sessions */
            $sessions = $sessions->sortBy('login_time')->values();
            $user = $sessions->first()->user;
            if (!$user) {
                return null;
            }

            $snapshot = $snapshotsByUser->get($user->id);
            $derivedStatus = null;
            $shiftCode = $user->shift_code ?: 'full_day';
            $shiftDefinition = $shiftDefinitions[$shiftCode] ?? ($shiftDefinitions['full_day'] ?? null);
            $shiftStart = null;
            $shiftEnd = null;
            $lateThreshold = null;
            $lateLoginComputed = null;
            $latenessMinutes = null;
            $shiftLabel = $shiftDefinition['label'] ?? null;
            $shiftHoursValue = (int) ($user->man_hours_per_day ?? 0);
            $autoLogoutApplied = false;

            $firstLogin = null;
            $lastLogout = null;
            $activityCount = $sessions->count();
            $hasOpenSession = false;
            $intervals = [];
            $lastLogoutCandidate = null;

            foreach ($sessions as $index => $session) {
                /** @var UserActivityLog $session */
                $loginAt = $session->login_time ? $session->login_time->copy() : null;
                if (!$loginAt) {
                    continue;
                }

                $logoutAt = $session->logout_time ? $session->logout_time->copy() : null;
                $nextLoginAt = null;
                if ($sessions->has($index + 1)) {
                    $next = $sessions->get($index + 1);
                    if ($next && $next->login_time) {
                        $nextLoginAt = $next->login_time->copy();
                    }
                }

                $effectiveStart = $loginAt->lt($dayStart) ? $dayStart->copy() : $loginAt->copy();
                if (!$firstLogin || $effectiveStart->lt($firstLogin)) {
                    $firstLogin = $effectiveStart->copy();
                }

                if ($logoutAt) {
                    $effectiveEnd = $logoutAt->gt($dayEnd) ? $dayEnd->copy() : $logoutAt->copy();
                    if ($effectiveEnd->gt($effectiveStart)) {
                        $intervals[] = [$effectiveStart->copy(), $effectiveEnd->copy()];
                    }

                    if (!$lastLogout || $effectiveEnd->gt($lastLogout)) {
                        $lastLogout = $effectiveEnd->copy();
                    }
                    if (!$lastLogoutCandidate || $effectiveEnd->gt($lastLogoutCandidate)) {
                        $lastLogoutCandidate = $effectiveEnd->copy();
                    }
                } else {
                    $candidateEnd = null;
                    $sessionStillOpen = false;

                    if ($nextLoginAt && $nextLoginAt->gt($loginAt)) {
                        $candidateEnd = $nextLoginAt->copy();
                    } else {
                        $autoLogoutTarget = $shiftEnd ? $shiftEnd->copy() : null;
                        if (!$autoLogoutTarget) {
                            $hours = $shiftHoursValue > 0 ? $shiftHoursValue : 8;
                            $autoLogoutTarget = $effectiveStart->copy()->addHours($hours);
                        }

                        if ($autoLogoutTarget->gt($dayEnd)) {
                            $autoLogoutTarget = $dayEnd->copy();
                        }

                        if ($autoLogoutTarget->lessThanOrEqualTo($effectiveStart)) {
                            $autoLogoutTarget = $effectiveStart->copy()->addMinutes(1);
                        }

                        if ($now->greaterThanOrEqualTo($autoLogoutTarget)) {
                            $candidateEnd = $autoLogoutTarget->copy();
                            $autoLogoutApplied = true;
                        } else {
                            $candidateEnd = $now->lt($dayEnd) ? $now->copy() : $dayEnd->copy();
                            $sessionStillOpen = true;
                        }
                    }

                    if ($candidateEnd->gt($dayEnd)) {
                        $candidateEnd = $dayEnd->copy();
                    }

                    if ($candidateEnd->gt($effectiveStart)) {
                        $intervals[] = [$effectiveStart->copy(), $candidateEnd->copy()];
                    }

                    if ($sessionStillOpen) {
                        $hasOpenSession = true;
                    }

                    if (!$lastLogout || $candidateEnd->gt($lastLogout)) {
                        if (!$sessionStillOpen) {
                            $lastLogout = $candidateEnd->copy();
                        }
                    }

                    if (!$lastLogoutCandidate || $candidateEnd->gt($lastLogoutCandidate)) {
                        $lastLogoutCandidate = $candidateEnd->copy();
                    }
                }
            }

            if (empty($intervals)) {
                $totalMinutes = 0;
            } else {
                usort($intervals, static function ($left, $right) {
                    return $left[0]->lt($right[0]) ? -1 : ($left[0]->gt($right[0]) ? 1 : 0);
                });

                $merged = [];
                foreach ($intervals as [$start, $end]) {
                    if (empty($merged)) {
                        $merged[] = [$start->copy(), $end->copy()];
                        continue;
                    }

                    [$prevStart, $prevEnd] = $merged[count($merged) - 1];
                    if ($start->lte($prevEnd)) {
                        if ($end->gt($prevEnd)) {
                            $merged[count($merged) - 1][1] = $end->copy();
                        }
                    } else {
                        $merged[] = [$start->copy(), $end->copy()];
                    }
                }

                $totalMinutes = array_reduce($merged, static function ($carry, $range) {
                    [$start, $end] = $range;
                    return $carry + $end->diffInMinutes($start);
                }, 0);

                $daySpanMinutes = $dayEnd->diffInMinutes($dayStart);
                if ($totalMinutes > $daySpanMinutes) {
                    $totalMinutes = $daySpanMinutes;
                }
            }

            if ($shiftDefinition) {
                $startTime = $shiftDefinition['start'] ?? '09:00';
                try {
                    $shiftStart = Carbon::parse($dayStart->toDateString().' '.$startTime, config('app.timezone'));
                } catch (\Throwable $exception) {
                    $shiftStart = $dayStart->copy()->setTime(9, 0);
                }

                if ($shiftDefinition['overnight'] ?? false) {
                    // Overnight shifts start on the selected day; lateness still based on start time
                }

                if (!empty($shiftDefinition['end'])) {
                    try {
                        $candidateEnd = Carbon::parse($dayStart->toDateString().' '.$shiftDefinition['end'], config('app.timezone'));
                        if ($shiftDefinition['overnight'] ?? false) {
                            if ($candidateEnd->lessThanOrEqualTo($shiftStart)) {
                                $candidateEnd->addDay();
                            }
                        }
                        $shiftEnd = $candidateEnd;
                    } catch (\Throwable $exception) {
                        $shiftEnd = null;
                    }
                }

                if ($shiftStart) {
                    if (!$shiftEnd) {
                        $hours = $shiftHoursValue > 0 ? $shiftHoursValue : 8;
                        $shiftEnd = $shiftStart->copy()->addHours($hours);
                    }
                    $lateThreshold = $shiftStart->copy()->addMinutes($lateToleranceMinutes);
                }
            }

            if (!$shiftEnd && $shiftHoursValue > 0) {
                $fallbackStart = $shiftStart ? $shiftStart->copy() : $dayStart->copy()->setTime(9, 0);
                $shiftEnd = $fallbackStart->copy()->addHours(max(1, $shiftHoursValue));
            }

            if ($totalMinutes > 0) {
                $derivedStatus = 'present';
            } elseif ($snapshot && $snapshot->status) {
                $derivedStatus = $snapshot->status;
            } else {
                $derivedStatus = 'absent';
            }

            $status = $snapshot?->status ?? $derivedStatus;

            if ($firstLogin && $shiftStart) {
                if ($lateThreshold && $firstLogin->greaterThan($lateThreshold)) {
                    $lateLoginComputed = true;
                    $latenessMinutes = $lateThreshold->diffInMinutes($firstLogin);
                } else {
                    $lateLoginComputed = false;
                    $difference = $shiftStart->diffInMinutes($firstLogin, false);
                    $latenessMinutes = $difference > 0 ? $difference : 0;
                }
            }

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'department_name' => optional($user->department)->name ?? '—',
                'work_station' => $user->work_station,
                'shift_hours' => (int) ($user->man_hours_per_day ?? 0),
                'activity_count' => $activityCount,
                'shift_code' => $shiftCode,
                'shift_label' => $shiftLabel,
                'shift_start' => $shiftStart?->toDateTimeString(),
                'lateness_reference' => $lateThreshold?->toDateTimeString(),
                'first_login' => $firstLogin?->toDateTimeString(),
                'last_logout' => $hasOpenSession ? null : $lastLogout?->toDateTimeString(),
                'last_logout_fallback' => ($lastLogout ?? $lastLogoutCandidate)?->toDateTimeString(),
                'is_online' => $hasOpenSession,
                'total_minutes' => $totalMinutes,
                'total_hours' => $totalMinutes > 0 ? round($totalMinutes / 60, 2) : 0.0,
                'attendance_status' => $status,
                'late_login' => $lateLoginComputed ?? ($snapshot?->late_login ?? null),
                'lateness_minutes' => $latenessMinutes,
                'late_login_source' => $lateLoginComputed === null ? ($snapshot?->late_login !== null ? 'snapshot' : null) : 'computed',
                'auto_logout_applied' => $autoLogoutApplied,
            ];
        })->filter()->values();

        $sorted = $items->sortByDesc('total_minutes')->values();

        return response()->json([
            'success' => true,
            'message' => 'Daily login sessions retrieved successfully.',
            'data' => [
                'date' => $dayStart->toDateString(),
                'items' => $sorted,
            ],
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $users = $this->eligibleEmployeeQuery()
            ->with('department:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'department_id', 'work_station', 'user_level', 'man_hours_per_day']);

        $employees = $users->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'work_station' => $user->work_station,
            'department_id' => $user->department_id,
            'department_name' => $user->department?->name,
            'position' => $user->user_level,
            'shift_hours' => (int) $user->man_hours_per_day,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Eligible employees retrieved successfully.',
            'data' => [
                'employees' => $employees,
            ],
        ]);
    }

    public function salaries(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return $this->notFound('No payroll periods available.');
        }

        $departmentId = $request->query('department_id');
        $grid = $this->summaryService->getSalaryGrid($period, $departmentId !== null && $departmentId !== '' ? (int) $departmentId : null);

        return response()->json([
            'success' => true,
            'message' => 'Salary data retrieved successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'salaries' => $grid,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return $this->notFound('No payroll periods available.');
        }

        $departmentId = $request->query('department_id');
        $summary = $this->summaryService->getSummary($period, $departmentId !== null && $departmentId !== '' ? (int) $departmentId : null);

        return response()->json([
            'success' => true,
            'message' => 'Payroll summary retrieved successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'summary' => $summary,
            ],
        ]);
    }

    public function regenerateAttendance(Request $request, string $periodCode): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        $period = $this->findPeriodByCodeOrNull($periodCode);
        if (!$period) {
            return $this->notFound('Payroll period not found.');
        }
        $wipeExisting = $request->boolean('wipe_existing', true);
        $departmentId = $this->nullableInt($request->input('department_id'));

        $recalculated = DB::connection('sqlsrv')->transaction(function () use ($period, $wipeExisting, $departmentId) {
            $summaries = $this->attendanceService->regenerateForPeriod($period, $wipeExisting);
            $salaries = $this->salaryService->recalculateForPeriod($period, true, $departmentId);

            return [
                'attendance_count' => $summaries->count(),
                'salary_count' => $salaries->count(),
            ];
        });

        $snapshotRuns = [];
        if ($request->boolean('rebuild_snapshots', true)) {
            $snapshotDays = max(1, (int) $request->input('snapshot_days', 7));
            $timezone = config('app.timezone');
            $now = Carbon::now($timezone)->startOfDay();
            $periodEnd = $period->periodEnd()->copy()->startOfDay();
            if ($periodEnd->greaterThan($now)) {
                $periodEnd = $now;
            }

            $snapshotStart = $periodEnd->copy()->subDays($snapshotDays - 1);
            $periodStart = $period->periodStart()->copy()->startOfDay();
            if ($snapshotStart->lessThan($periodStart)) {
                $snapshotStart = $periodStart;
            }

            if ($snapshotStart->lessThanOrEqualTo($periodEnd)) {
                foreach (CarbonPeriod::create($snapshotStart, $periodEnd) as $snapshotDate) {
                    $result = $this->attendanceProcessingService->process($snapshotDate);
                    $snapshotRuns[] = [
                        'date' => $result['date'] ?? $snapshotDate->toDateString(),
                        'processed' => $result['processed'] ?? 0,
                        'present' => $result['present'] ?? 0,
                        'absent' => $result['absent'] ?? 0,
                        'skipped' => $result['skipped'] ?? 0,
                        'deactivated' => $result['deactivated'] ?? 0,
                    ];
                }
            }
        }

        $summary = $this->summaryService->getSummary($period, $departmentId);

        $this->logAudit(
            $period,
            $request->user()?->id,
            'attendance_regenerated',
            array_merge($recalculated, [
                'wipe_existing' => $wipeExisting,
                'department_id' => $departmentId,
                'snapshot_days' => $request->input('snapshot_days', 7),
                'snapshot_runs' => count($snapshotRuns),
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance regenerated successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'stats' => $recalculated,
                'summary' => $summary,
                'snapshots' => $snapshotRuns,
            ],
        ]);
    }

    public function syncAttendanceUsers(Request $request, string $periodCode): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        $period = $this->findPeriodByCodeOrNull($periodCode);
        if (!$period) {
            return $this->notFound('Payroll period not found.');
        }

        $departmentId = $this->nullableInt($request->input('department_id'));

        $eligibleQuery = $this->eligibleEmployeeQuery();
        if ($departmentId !== null) {
            $eligibleQuery->where('department_id', $departmentId);
        }

        $users = $eligibleQuery->get(['id', 'department_id']);
        if ($users->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No eligible employees found to synchronize.',
                'data' => [
                    'period' => $this->formatPeriod($period),
                    'stats' => [
                        'eligible' => 0,
                        'created' => 0,
                        'updated' => 0,
                        'unchanged' => 0,
                        'department_id' => $departmentId,
                    ],
                ],
            ]);
        }

        $created = 0;
        $updated = 0;
        $timestamp = Carbon::now(config('app.timezone'));

        DB::connection('sqlsrv')->transaction(function () use ($period, $users, &$created, &$updated, $timestamp) {
            $existing = PayrollAttendance::query()
                ->where('period_id', $period->id)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'department_id'])
                ->keyBy('user_id');

            foreach ($users as $user) {
                $departmentId = $user->department_id ?: null;

                if ($existing->has($user->id)) {
                    $record = $existing->get($user->id);
                    if ($record->department_id !== $departmentId) {
                        PayrollAttendance::query()
                            ->whereKey($record->id)
                            ->update([
                                'department_id' => $departmentId,
                                'updated_at' => $timestamp,
                            ]);
                        $updated++;
                    }
                    continue;
                }

                PayrollAttendance::create([
                    'period_id' => $period->id,
                    'user_id' => $user->id,
                    'department_id' => $departmentId,
                    'login_days' => 0,
                    'hours_worked' => 0,
                    'overtime_hours' => 0,
                    'session_breakdown' => [],
                    'source_reference' => 'sync_users',
                ]);
                $created++;
            }
        });

        $eligibleCount = $users->count();
        $unchanged = max($eligibleCount - $created - $updated, 0);

        $this->logAudit(
            $period,
            $request->user()?->id,
            'attendance_users_synced',
            [
                'eligible' => $eligibleCount,
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'department_id' => $departmentId,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance users synchronized successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'stats' => [
                    'eligible' => $eligibleCount,
                    'created' => $created,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'department_id' => $departmentId,
                ],
            ],
        ]);
    }

    public function recalculateSalaries(Request $request, string $periodCode): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        $period = $this->findPeriodByCodeOrNull($periodCode);
        if (!$period) {
            return $this->notFound('Payroll period not found.');
        }
        $preserveAdjustments = $request->boolean('preserve_adjustments', true);
        $departmentId = $this->nullableInt($request->input('department_id'));

        $salaries = $this->salaryService->recalculateForPeriod($period, $preserveAdjustments, $departmentId);
        $summary = $this->summaryService->getSummary($period, $departmentId);

        $this->logAudit(
            $period,
            $request->user()?->id,
            'salaries_recalculated',
            [
                'count' => $salaries->count(),
                'preserve_adjustments' => $preserveAdjustments,
                'department_id' => $departmentId,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Salaries recalculated successfully.',
            'data' => [
                'period' => $this->formatPeriod($period),
                'summary' => $summary,
            ],
        ]);
    }

    public function applyAdjustment(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        $data = $request->validate([
            'period_code' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user_id' => ['required', 'integer', 'exists:sqlsrv.users,id'],
            'base_salary' => ['nullable', 'numeric'],
            'bonuses' => ['nullable', 'numeric'],
            'extra_hours_value' => ['nullable', 'numeric'],
            'deductions' => ['nullable', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $period = $this->findPeriodByCodeOrNull($data['period_code']);
        if (!$period) {
            return $this->notFound('Payroll period not found.');
        }

        $baseValue = array_key_exists('base_salary', $data) ? (float) $data['base_salary'] : null;
        $bonusValue = array_key_exists('bonuses', $data) ? (float) $data['bonuses'] : null;
        $extraHoursValue = array_key_exists('extra_hours_value', $data) ? (float) $data['extra_hours_value'] : null;
        $deductionValue = array_key_exists('deductions', $data) ? (float) $data['deductions'] : null;

        $periodCodeForRate = $period->period_code ?? $period->periodStart()->format('Y-m');
        $structureBaseSalary = 0.0;
        $user = \App\Models\User::query()->find($data['user_id']);
        if ($user) {
            $structureBaseSalary = (float) $this->rateService->resolveWorkStationBaseSalary($user, $periodCodeForRate);
        }

        $salary = DB::connection('sqlsrv')->transaction(function () use ($period, $data, $baseValue, $bonusValue, $extraHoursValue, $deductionValue, $structureBaseSalary) {
            $salary = PayrollSalary::firstOrCreate(
                [
                    'period_id' => $period->id,
                    'user_id' => $data['user_id'],
                ],
                [
                    'base_salary' => 0,
                    'bonuses' => 0,
                    'extra_hours_value' => 0,
                    'deductions' => 0,
                    'net_salary' => 0,
                    'calculation_status' => 'pending',
                ]
            );

            if ($structureBaseSalary > 0) {
                $salary->base_salary = round($structureBaseSalary, 2);
            } elseif ($baseValue !== null) {
                $salary->base_salary = $baseValue;
            }

            if ($bonusValue !== null) {
                $salary->bonuses = $bonusValue;
            }

            if ($extraHoursValue !== null) {
                $salary->extra_hours_value = $extraHoursValue;
            }

            if ($deductionValue !== null) {
                $salary->deductions = $deductionValue;
            }

            if (array_key_exists('note', $data)) {
                $salary->note = $data['note'];
            }

            $salary->net_salary = round(
                (float) $salary->base_salary
                + (float) $salary->extra_hours_value
                + (float) $salary->bonuses
                - (float) $salary->deductions,
                2
            );

            $salary->save();

            return $salary->fresh(['user:id,first_name,last_name,department_id']);
        });

        $this->logAudit(
            $period,
            $request->user()?->id,
            'salary_adjusted',
            [
                'user_id' => $salary->user_id,
                'bonuses' => (float) $salary->bonuses,
                'extra_hours_value' => (float) $salary->extra_hours_value,
                'deductions' => (float) $salary->deductions,
                'note' => $salary->note,
            ],
            $salary->user_id
        );

        $summary = $this->summaryService->getSummary($period);

        return response()->json([
            'success' => true,
            'message' => 'Adjustment saved successfully.',
            'data' => [
                'salary' => [
                    'user_id' => $salary->user_id,
                    'user_name' => $salary->user?->name,
                    'base_salary' => (float) $salary->base_salary,
                    'bonuses' => (float) $salary->bonuses,
                    'extra_hours_value' => (float) $salary->extra_hours_value,
                    'deductions' => (float) $salary->deductions,
                    'net_salary' => (float) $salary->net_salary,
                    'status' => $salary->calculation_status,
                    'note' => $salary->note,
                ],
                'summary' => $summary,
            ],
        ]);
    }

    protected function expectedWorkingDaysForUser(PayrollPeriod $period, ?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $pattern = (int) ($user->work_days_per_week ?? 0);
        if ($pattern <= 0) {
            $pattern = 7;
        }
        $cacheKey = $period->id . ':' . $pattern;

        if (!array_key_exists($cacheKey, $this->expectedDaysCache)) {
            $start = $period->periodStart()->startOfDay();
            $end = $period->periodEnd()->endOfDay();
            $count = 0;

            foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
                if ($this->isWorkingDayForPattern($date, $pattern)) {
                    $count++;
                }
            }

            $this->expectedDaysCache[$cacheKey] = $count;
        }

        return $this->expectedDaysCache[$cacheKey];
    }

    protected function isWorkingDayForPattern(Carbon $date, int $workDaysPerWeek): bool
    {
        return match (true) {
            $workDaysPerWeek >= 7 => true,
            $workDaysPerWeek === 6 => $date->dayOfWeekIso <= 6,
            $workDaysPerWeek === 2 => in_array($date->dayOfWeekIso, [6, 7], true),
            default => $date->dayOfWeekIso <= 5,
        };
    }

    protected function resolvePeriod(Request $request): ?PayrollPeriod
    {
        try {
            if ($code = $request->query('period')) {
                return $this->findPeriodByCode($code);
            }

            if ($id = $request->query('period_id')) {
                return PayrollPeriod::query()->findOrFail($id);
            }
        } catch (ModelNotFoundException $exception) {
            return null;
        }

        return PayrollPeriod::query()->orderByDesc('start_date')->first();
    }

    protected function findPeriodByCode(string $periodCode): PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('period_code', $periodCode)
            ->firstOrFail();
    }

    protected function findPeriodByCodeOrNull(string $periodCode): ?PayrollPeriod
    {
        try {
            return $this->findPeriodByCode($periodCode);
        } catch (ModelNotFoundException $exception) {
            return null;
        }
    }

    protected function ensureAbility(Request $request, string $ability): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $roles = collect($user->assignedRoleNames())->map(fn ($role) => mb_strtolower($role));
        if ($roles->intersect(['payroll management', 'super admin', 'supper admin'])->isNotEmpty()) {
            return;
        }

        if (mb_strtolower((string) $user->type) === 'super admin') {
            return;
        }

        if ($ability === 'view payroll') {
            return;
        }

        if ($user->can($ability)) {
            return;
        }

        abort(403, 'Unauthorized');
    }

    protected function notFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], 404);
    }

    protected function formatPeriod(PayrollPeriod $period): array
    {
        return [
            'id' => $period->id,
            'period_code' => $period->period_code,
            'start_date' => $period->periodStart()->toDateString(),
            'end_date' => $period->periodEnd()->toDateString(),
            'status' => $period->status,
            'locked_at' => optional($period->locked_at)->toDateTimeString(),
        ];
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function logAudit(
        PayrollPeriod $period,
        ?int $actorId,
        string $action,
        array $payload = [],
        ?int $userId = null
    ): void {
        PayrollAuditLog::create([
            'period_id' => $period->id,
            'user_id' => $userId,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'source' => 'payroll_api',
        ]);
    }

    protected function eligibleEmployeeQuery(): Builder
    {
        return User::query()
            ->where('staff_type_category', 'MDC')
            ->where('is_active', 1)
            ->whereNotNull('work_station')
            ->where('work_station', '!=', '')
            ->whereNotNull('work_days_per_week')
            ->whereNotNull('man_hours_per_day');
    }
}
