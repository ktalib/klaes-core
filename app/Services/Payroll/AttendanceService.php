<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollAttendance;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ManualAttendance\ManualAttendanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        protected RateService $rateService,
        protected ManualAttendanceService $manualAttendanceService,
    ) {
    }

    public function regenerateForPeriod(PayrollPeriod $period, bool $wipeExisting = true): Collection
    {
        $summaries = $this->calculateAttendance($period);

        DB::connection('sqlsrv')->transaction(function () use ($period, $summaries, $wipeExisting) {
            if ($wipeExisting) {
                PayrollAttendance::query()->where('period_id', $period->id)->delete();
            }

            foreach ($summaries as $summary) {
                PayrollAttendance::updateOrCreate(
                    [
                        'period_id' => $period->id,
                        'user_id' => $summary['user_id'],
                    ],
                    [
                        'department_id' => $summary['department_id'],
                        'login_days' => $summary['login_days'],
                        'hours_worked' => $summary['hours_worked'],
                        'overtime_hours' => $summary['overtime_hours'],
                        'session_breakdown' => $summary['session_breakdown'],
                        'source_reference' => $summary['source_reference'],
                    ]
                );
            }

            $this->manualAttendanceService->syncManualEntriesToPayroll($period);
        });

        return $summaries;
    }

    public function calculateAttendance(PayrollPeriod $period): Collection
    {
        $start = $period->periodStart()->startOfDay();
        $periodEffectiveEnd = $period->periodEnd();
        $periodEnd = $periodEffectiveEnd->copy()->endOfDay();

        $logs = UserActivityLog::query()
            // Include sessions that started before the window but closed (or remained open) inside it.
            ->where(function ($query) use ($start, $periodEnd) {
                $query->whereBetween('login_time', [$start, $periodEnd])
                    ->orWhere(function ($crossing) use ($start, $periodEnd) {
                        $crossing->where('login_time', '<', $start)
                            ->where(function ($logoutWindow) use ($start, $periodEnd) {
                                $logoutWindow->whereBetween('logout_time', [$start, $periodEnd])
                                    ->orWhereNull('logout_time');
                            });
                    });
            })
            ->whereHas('user', function ($query) {
                $query->where('staff_type_category', 'MDC')
                    ->where('is_active', 1);
            })
            ->with(['user' => function ($query) {
                $query->select('id', 'department_id', 'staff_type_category', 'man_hours_per_day', 'work_days_per_week', 'first_name', 'last_name');
            }])
            ->orderBy('user_id')
            ->orderBy('login_time')
            ->get();

        return $logs->groupBy('user_id')->map(function (Collection $sessions) use ($start, $periodEffectiveEnd, $periodEnd) {
            /** @var User $user */
            $user = $sessions->first()->user;
            if (!$user) {
                return null;
            }

            $profile = $this->rateService->resolveShiftProfile($user, $periodEffectiveEnd);
            $shiftHours = (int) ($profile['hours'] ?? $this->rateService->resolveShiftHours($user, $periodEffectiveEnd));
            $allowOvertime = (bool) ($profile['allow_overtime'] ?? false);
            $overtimeCap = isset($profile['overtime_cap']) ? (float) $profile['overtime_cap'] : null;

            $sessionWindows = [];
            $breakdown = [];
            $autoLogoutSessions = 0;

            foreach ($sessions as $session) {
                $login = $this->parseCarbon($session->login_time);
                if (!$login) {
                    continue;
                }

                $rawLogout = $this->parseCarbon($session->logout_time);

                [$logout, $autoLoggedOut] = $this->resolveLogout(
                    $login,
                    $rawLogout,
                    $shiftHours,
                    $periodEnd,
                    $allowOvertime,
                    $overtimeCap
                );

                $clampedLogin = $login->lessThan($start) ? $start->copy() : $login->copy();
                $clampedLogout = $logout->copy();

                if ($clampedLogout->lessThanOrEqualTo($clampedLogin)) {
                    continue;
                }

                $diffMinutes = $clampedLogout->diffInMinutes($clampedLogin);
                $minutes = $session->duration_minutes !== null
                    ? min((int) $session->duration_minutes, $diffMinutes)
                    : $diffMinutes;
                if ($minutes <= 0) {
                    continue;
                }

                $sessionWindows[] = [
                    'login' => $clampedLogin,
                    'logout' => $clampedLogout,
                ];

                $breakdown[] = [
                    'login_at' => $login->toDateTimeString(),
                    'logout_at' => $logout->toDateTimeString(),
                    'raw_logout_at' => $rawLogout?->toDateTimeString(),
                    'minutes' => $minutes,
                    'status' => $session->status,
                    'auto_logout' => $autoLoggedOut,
                ];

                if ($autoLoggedOut) {
                    $autoLogoutSessions++;
                }
            }

            $dailySummary = $this->buildDailySummaries($sessionWindows, $shiftHours, $allowOvertime, $overtimeCap);

            $workedHours = $dailySummary['worked_hours'];
            $daysWorked = count($dailySummary['series']);
            $overtime = $allowOvertime ? $dailySummary['overtime_hours'] : 0.0;

            return [
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'login_days' => $daysWorked,
                'hours_worked' => $workedHours,
                'overtime_hours' => $overtime,
                'session_breakdown' => $breakdown,
                'source_reference' => 'user_activity_logs',
                'daily_summary' => $dailySummary['series'],
                'auto_logout_sessions' => $autoLogoutSessions,
                'overtime_authorized' => $allowOvertime,
            ];
        })->filter()->values();
    }

    protected function parseCarbon($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
    }

    protected function resolveLogout(
        Carbon $login,
        ?Carbon $logout,
        int $shiftHours,
        Carbon $periodEnd,
        bool $allowOvertime,
        ?float $overtimeCap
    ): array
    {
        $shiftHours = max(1, $shiftHours);
        $expected = $login->copy()->addHours($shiftHours);
        $effective = $logout ? $logout->copy() : $expected->copy();

        if ($effective->greaterThan($periodEnd)) {
            $effective = $periodEnd->copy();
        }

        if (!$allowOvertime) {
            if ($effective->greaterThan($expected)) {
                $effective = $expected->copy();
            }
        } elseif ($overtimeCap !== null) {
            $overtimeCapMinutes = max(0, (int) round($overtimeCap * 60));
            $maxAuthorized = $expected->copy()->addMinutes($overtimeCapMinutes);
            if ($effective->greaterThan($maxAuthorized)) {
                $effective = $maxAuthorized;
            }
        }

        $autoLogout = false;
        if (!$logout) {
            $autoLogout = true;
        } elseif ($effective->notEqualTo($logout)) {
            $autoLogout = true;
        }

        if ($effective->lessThanOrEqualTo($login)) {
            $effective = $login->copy()->addMinutes(1);
        }

        return [$effective, $autoLogout];
    }

    protected function buildDailySummaries(
        array $sessions,
        int $shiftHours,
        bool $allowOvertime,
        ?float $overtimeCap
    ): array {
        if (empty($sessions)) {
            return [
                'series' => [],
                'worked_hours' => 0.0,
                'overtime_hours' => 0.0,
                'truncated_days' => 0,
            ];
        }

        $rawDailyMinutes = [];

        foreach ($sessions as $window) {
            $login = $window['login'] ?? null;
            $logout = $window['logout'] ?? null;

            if (!$login instanceof Carbon || !$logout instanceof Carbon || $logout->lessThanOrEqualTo($login)) {
                continue;
            }

            $period = CarbonPeriod::create($login->copy()->startOfDay(), '1 day', $logout->copy()->startOfDay());

            foreach ($period as $day) {
                $dayStart = $day->copy()->startOfDay();
                $dayEnd = $day->copy()->endOfDay();

                $segmentStart = $login->greaterThan($dayStart) ? $login->copy() : $dayStart;
                $segmentEnd = $logout->lessThan($dayEnd) ? $logout->copy() : $dayEnd;

                if ($segmentEnd->lessThanOrEqualTo($segmentStart)) {
                    continue;
                }

                $minutes = $segmentEnd->diffInMinutes($segmentStart);
                if ($minutes <= 0) {
                    continue;
                }

                $key = $day->toDateString();
                $rawDailyMinutes[$key] = ($rawDailyMinutes[$key] ?? 0) + $minutes;
            }
        }

        if (empty($rawDailyMinutes)) {
            return [
                'series' => [],
                'worked_hours' => 0.0,
                'overtime_hours' => 0.0,
                'truncated_days' => 0,
            ];
        }

        ksort($rawDailyMinutes);

        $shiftMinutes = max(1, $shiftHours) * 60;
        $overtimeCapMinutes = $overtimeCap !== null ? max(0, (int) round($overtimeCap * 60)) : null;

        $series = [];
        $cumulativeMinutes = 0;
        $totalOvertimeMinutes = 0;
        $truncatedDays = 0;

        foreach ($rawDailyMinutes as $date => $minutes) {
            $allowedMinutes = $shiftMinutes;

            if ($allowOvertime) {
                if ($overtimeCapMinutes !== null) {
                    $allowedMinutes += $overtimeCapMinutes;
                } else {
                    $allowedMinutes = max($allowedMinutes, $minutes);
                }
            }

            $clampedMinutes = min($minutes, $allowedMinutes);
            if ($clampedMinutes < $minutes) {
                $truncatedDays++;
            }

            $dayOvertimeMinutes = 0;
            if ($allowOvertime) {
                $dayOvertimeMinutes = max(0, $clampedMinutes - $shiftMinutes);
                $totalOvertimeMinutes += $dayOvertimeMinutes;
            }

            $cumulativeMinutes += $clampedMinutes;

            $series[] = [
                'date' => $date,
                'hours' => round($clampedMinutes / 60, 2),
                'cumulative_hours' => round($cumulativeMinutes / 60, 2),
                'overtime_hours' => round($dayOvertimeMinutes / 60, 2),
                'raw_hours' => round($minutes / 60, 2),
                'truncated' => $clampedMinutes < $minutes,
            ];
        }

        return [
            'series' => $series,
            'worked_hours' => round($cumulativeMinutes / 60, 2),
            'overtime_hours' => $allowOvertime ? round($totalOvertimeMinutes / 60, 2) : 0.0,
            'truncated_days' => $truncatedDays,
        ];
    }

    public function summarizeStoredAttendance(PayrollAttendance $attendance, PayrollPeriod $period): array
    {
        $user = $attendance->user;
        if (!$user) {
            return [
                'shift_hours' => 0,
                'allow_overtime' => false,
                'overtime_cap' => null,
                'daily_summary' => [],
                'worked_hours' => 0.0,
                'overtime_hours' => 0.0,
                'days_worked' => 0,
            ];
        }

        $profile = $this->rateService->resolveShiftProfile($user, $period->periodEnd());
        $shiftHours = (int) ($profile['hours'] ?? max(1, $user->man_hours_per_day ?? 8));
        $allowOvertime = (bool) ($profile['allow_overtime'] ?? false);
        $overtimeCap = isset($profile['overtime_cap']) ? (float) $profile['overtime_cap'] : null;

        $periodStart = $period->periodStart()->startOfDay();
        $periodEnd = $period->periodEnd()->endOfDay();

        $sessions = $this->normalizeSessionWindows($attendance->session_breakdown ?? [], $periodStart, $periodEnd);
        $summary = $this->buildDailySummaries($sessions, $shiftHours, $allowOvertime, $overtimeCap);

        return [
            'shift_hours' => $shiftHours,
            'allow_overtime' => $allowOvertime,
            'overtime_cap' => $overtimeCap,
            'daily_summary' => $summary['series'],
            'worked_hours' => $summary['worked_hours'],
            'overtime_hours' => $allowOvertime ? $summary['overtime_hours'] : 0.0,
            'days_worked' => count($summary['series']),
        ];
    }

    protected function normalizeSessionWindows(array $breakdown, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        $windows = [];

        foreach ($breakdown as $entry) {
            $login = $this->parseCarbon($entry['login_at'] ?? null);
            $logout = $this->parseCarbon($entry['logout_at'] ?? null);

            if (!$login || !$logout || $logout->lessThanOrEqualTo($login)) {
                continue;
            }

            if ($periodStart && $login->lessThan($periodStart)) {
                $login = $periodStart->copy();
            }

            if ($periodEnd && $logout->greaterThan($periodEnd)) {
                $logout = $periodEnd->copy();
            }

            if ($logout->lessThanOrEqualTo($login)) {
                continue;
            }

            $windows[] = [
                'login' => $login,
                'logout' => $logout,
            ];
        }

        return $windows;
    }
}
