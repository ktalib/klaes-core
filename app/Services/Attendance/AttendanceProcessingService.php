<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceDailySnapshot;
use App\Models\Attendance\UserAbsenceCounter;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceProcessingService
{
    /**
     * Generate attendance snapshots, update counters, and auto-deactivate when thresholds trip.
     */
    public function process(Carbon|string|null $date = null): array
    {
        $targetDate = $this->normalizeDate($date);
        $results = [
            'date' => $targetDate->toDateString(),
            'processed' => 0,
            'skipped' => 0,
            'present' => 0,
            'absent' => 0,
            'deactivated' => 0,
        ];

        $config = config('attendance');

        User::query()
            ->select([
                'id',
                'shift_code',
                'work_days_per_week',
                'auto_deactivate',
                'is_pc_access',
                'is_active',
                'parent_id',
                'first_name',
                'last_name',
                'staff_type_category',
            ])
            ->where('is_pc_access', 1)
            ->whereNotNull('staff_type_category')
            ->whereIn('staff_type_category', ['MDC'])
            ->chunkById(100, function ($users) use (&$results, $targetDate, $config) {
                foreach ($users as $user) {
                    if (!$this->shouldProcess($user, $targetDate, $config)) {
                        $results['skipped']++;
                        continue;
                    }

                    $shift = $this->resolveShift($user, $config);
                    if (!$shift) {
                        $results['skipped']++;
                        continue;
                    }

                    $window = $this->buildWindow($targetDate, $shift, (int) ($config['early_check_in_minutes'] ?? 0));
                    $session = $this->findSession($user, $window['searchStart'], $window['searchEnd']);

                    $status = $session ? 'present' : 'absent';
                    $lateThreshold = $window['shiftStart']->copy()->addMinutes((int) ($config['late_tolerance_minutes'] ?? 0));
                    $lateLogin = $session && $session->login_time && $session->login_time->greaterThan($lateThreshold);

                    $meta = [
                        'window_start' => $window['searchStart']->toDateTimeString(),
                        'window_end' => $window['searchEnd']->toDateTimeString(),
                    ];

                    if ($session) {
                        $meta['activity_log_id'] = $session->id;
                        $meta['login_time'] = optional($session->login_time)->toDateTimeString();
                        $meta['logout_time'] = optional($session->logout_time)->toDateTimeString();
                    }

                    $snapshot = DB::connection('sqlsrv')->transaction(function () use (
                        $user,
                        $targetDate,
                        $shift,
                        $status,
                        $session,
                        $lateLogin,
                        $meta
                    ) {
                        return AttendanceDailySnapshot::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'attendance_date' => $targetDate->toDateString(),
                                'shift_code' => $shift['code'],
                            ],
                            [
                                'status' => $status,
                                'login_time' => $session?->login_time,
                                'logout_time' => $session?->logout_time,
                                'late_login' => $lateLogin,
                                'reason' => $session ? null : 'No login activity recorded',
                                'metadata' => $meta,
                            ]
                        );
                    });

                    $counters = $this->refreshCounters($user, $targetDate);
                    $deactivated = false;

                    if ($status === 'absent') {
                        if ($user->auto_deactivate && $user->is_active) {
                            $deactivated = $this->maybeDeactivateUser($user, $counters, $config);
                        }
                        $results['absent']++;
                    } else {
                        $results['present']++;
                    }

                    if ($deactivated) {
                        $this->flagSnapshotAsDeactivated($snapshot);
                        $results['deactivated']++;
                    }

                    $results['processed']++;
                }
            });

        return $results;
    }

    protected function normalizeDate(Carbon|string|null $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->startOfDay();
        }

        return Carbon::now()->startOfDay();
    }

    protected function shouldProcess(User $user, Carbon $date, array $config): bool
    {
        if ((int) $user->is_active !== 1) {
            return false;
        }

        if (in_array($date->toDateString(), Arr::wrap($config['holidays'] ?? []), true)) {
            return false;
        }

        return $this->isWorkingDay($user, $date);
    }

    protected function resolveShift(User $user, array $config): ?array
    {
        $code = $user->shift_code ?: 'full_day';
        $shifts = $config['shifts'] ?? [];

        $details = $shifts[$code] ?? $shifts['full_day'] ?? null;
        if (!$details) {
            return null;
        }

        return [
            'code' => $code,
            'start' => $details['start'],
            'end' => $details['end'],
            'overnight' => (bool) ($details['overnight'] ?? false),
            'label' => $details['label'] ?? $code,
        ];
    }

    protected function buildWindow(Carbon $date, array $shift, int $earlyMinutes): array
    {
        $shiftStart = Carbon::parse($date->toDateString().' '.$shift['start']);
        $shiftEnd = Carbon::parse($date->toDateString().' '.$shift['end']);

        if ($shift['overnight'] || $shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd = $shiftEnd->addDay();
        }

        $searchStart = $shiftStart->copy()->subMinutes(max($earlyMinutes, 0));
        $searchEnd = $shiftEnd->copy();

        return compact('shiftStart', 'shiftEnd', 'searchStart', 'searchEnd');
    }

    protected function findSession(User $user, Carbon $from, Carbon $to): ?UserActivityLog
    {
        return UserActivityLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('login_time', [$from, $to])
            ->orderBy('login_time')
            ->first();
    }

    protected function refreshCounters(User $user, Carbon $date): array
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $monthlyAbsences = AttendanceDailySnapshot::query()
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', 'absent')
            ->count();

        $recentSnapshots = AttendanceDailySnapshot::query()
            ->where('user_id', $user->id)
            ->where('attendance_date', '<=', $date->toDateString())
            ->orderBy('attendance_date', 'desc')
            ->limit(31)
            ->get();

        $consecutive = 0;
        $lastAbsentDate = null;
        foreach ($recentSnapshots as $snapshot) {
            if ($snapshot->status !== 'absent') {
                break;
            }
            $consecutive++;
            $lastAbsentDate ??= Carbon::parse($snapshot->attendance_date)->toDateString();
        }

        $counter = UserAbsenceCounter::firstOrNew(['user_id' => $user->id]);
        $counter->consecutive_absences = $consecutive;
        $counter->monthly_absences = $monthlyAbsences;
        $counter->month_key = $monthStart->format('Y-m');
        $counter->last_absent_date = $lastAbsentDate;
        $counter->save();

        return [
            'consecutive_absences' => $consecutive,
            'monthly_absences' => $monthlyAbsences,
            'last_absent_date' => $lastAbsentDate,
        ];
    }

    protected function maybeDeactivateUser(User $user, array $counters, array $config): bool
    {
        $maxConsecutive = (int) ($config['max_consecutive_absences'] ?? 0);
        $maxMonthly = (int) ($config['max_monthly_absences'] ?? 0);

        $tripConsecutive = $maxConsecutive > 0 && $counters['consecutive_absences'] >= $maxConsecutive;
        $tripMonthly = $maxMonthly > 0 && $counters['monthly_absences'] >= $maxMonthly;

        if (!$tripConsecutive && !$tripMonthly) {
            return false;
        }

        DB::connection('sqlsrv')->transaction(function () use ($user) {
            $user->is_active = 0;
            $user->save();
        });

        Log::warning('User auto-deactivated due to attendance thresholds.', [
            'user_id' => $user->id,
            'consecutive_absences' => $counters['consecutive_absences'],
            'monthly_absences' => $counters['monthly_absences'],
        ]);

        return true;
    }

    protected function isWorkingDay(User $user, Carbon $date): bool
    {
        $daysPerWeek = (int) ($user->work_days_per_week ?: 5);
        $dayOfWeek = (int) $date->dayOfWeekIso; // 1 (Mon) .. 7 (Sun)

        return match ($daysPerWeek) {
            7 => true,
            2 => in_array($dayOfWeek, [6, 7], true),
            default => $dayOfWeek <= 5,
        };
    }

    protected function flagSnapshotAsDeactivated(?AttendanceDailySnapshot $snapshot): void
    {
        if (!$snapshot) {
            return;
        }

        $metadata = $snapshot->metadata ?? [];
        if (!empty($metadata['auto_deactivated'])) {
            return;
        }

        $metadata['auto_deactivated'] = true;
        $snapshot->metadata = $metadata;
        $snapshot->save();
    }
}
