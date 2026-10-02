<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollAttendance;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollSalary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    public function __construct(
        protected RateService $rateService,
    ) {
    }

    public function recalculateForPeriod(
        PayrollPeriod $period,
        bool $preserveAdjustments = true,
        ?int $departmentId = null
    ): Collection {
        $attendanceByUser = PayrollAttendance::query()
            ->where('period_id', $period->id)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get()
            ->keyBy('user_id');

        $users = $this->resolveUsers($attendanceByUser->keys()->all(), $departmentId);
        if ($users->isEmpty()) {
            return collect();
        }

        $results = collect();
        $periodEnd = $period->periodEnd();
        $periodDays = max(1, $periodEnd->diffInDays($period->periodStart()) + 1);
        $periodCode = $period->period_code ?? $period->periodStart()->format('Y-m');

        DB::connection('sqlsrv')->transaction(function () use (
            $period,
            $periodEnd,
            $periodDays,
            $periodCode,
            $users,
            $attendanceByUser,
            $preserveAdjustments,
            &$results
        ) {
            foreach ($users as $user) {
                $attendance = $attendanceByUser->get($user->id);
                $snapshot = $this->snapshotForUser($user, $attendance, $periodEnd, $periodDays, $periodCode);

                $existing = PayrollSalary::query()
                    ->where('period_id', $period->id)
                    ->where('user_id', $user->id)
                    ->first();

                $bonuses = $preserveAdjustments && $existing ? (float) $existing->bonuses : 0.0;
                $autoDeduction = $snapshot['attendance_deduction'];
                $deductions = $autoDeduction;
                if ($preserveAdjustments && $existing && $this->shouldPreserveDeduction($existing->deductions, $autoDeduction)) {
                    $deductions = (float) $existing->deductions;
                }
                $note = $snapshot['note'];

                if ($preserveAdjustments && $existing && $note === null) {
                    $note = $existing->note;
                }

                $netSalary = round($snapshot['base_salary'] + $snapshot['extra_hours_value'] + $bonuses - $deductions, 2);

                PayrollSalary::updateOrCreate(
                    [
                        'period_id' => $period->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'base_salary' => $snapshot['base_salary'],
                        'bonuses' => $bonuses,
                        'extra_hours_value' => $snapshot['extra_hours_value'],
                        'deductions' => $deductions,
                        'net_salary' => $netSalary,
                        'computed_at' => now(),
                        'calculation_status' => $snapshot['status'],
                        'note' => $note,
                    ]
                );

                $results->push(array_merge($snapshot, [
                    'user_id' => $user->id,
                    'bonuses' => $bonuses,
                    'deductions' => $deductions,
                    'net_salary' => $netSalary,
                    'attendance_deduction' => $autoDeduction,
                ]));
            }
        });

        return $results;
    }

    protected function snapshotForUser(
        User $user,
        ?PayrollAttendance $attendance,
        Carbon $periodEnd,
        int $periodDays,
        string $periodCode
    ): array {
        $shiftHours = max(1, $this->rateService->resolveShiftHours($user, $periodEnd));
        $workStationBaseSalary = (float) $this->rateService->resolveWorkStationBaseSalary($user, $periodCode);
        $hasWorkStationBase = $workStationBaseSalary > 0;

        $configuredDailyRate = (float) $this->rateService->resolveDailyRate($user, $periodEnd);
        $baseSalary = $hasWorkStationBase
            ? round($workStationBaseSalary, 2)
            : ($configuredDailyRate > 0 ? round($configuredDailyRate * $periodDays, 2) : 0.0);

        $effectiveDailyRate = $periodDays > 0 && $baseSalary > 0
            ? round($baseSalary / $periodDays, 2)
            : 0.0;

        $dailyRateSource = $hasWorkStationBase
            ? 'workstation'
            : ($configuredDailyRate > 0 ? 'rate' : 'derived');

        $loginDays = $attendance ? max(0, (float) $attendance->login_days) : 0.0;
        $hoursWorked = $attendance ? max(0, (float) $attendance->hours_worked) : 0.0;
        $overtimeHours = $attendance ? max(0, (float) $attendance->overtime_hours) : 0.0;

        $issues = [];
        $hasAttendance = $attendance && $loginDays > 0;
        $hasBaseSalary = $baseSalary > 0;

        if (!$hasAttendance) {
            $issues[] = 'No attendance recorded for this period';
        }

        if (!$hasBaseSalary) {
            $issues[] = 'No active payroll rate set for this period';
        }

        $status = $issues
            ? (!$hasBaseSalary ? 'missing_rate' : 'missing_attendance')
            : 'calculated';

        $note = $issues ? implode('; ', $issues) . '.' : null;

        $hourlyRate = $effectiveDailyRate > 0 ? $effectiveDailyRate / $shiftHours : 0.0;
        $extraHoursValue = $status === 'calculated' && $overtimeHours > 0 && $hourlyRate > 0
            ? round($hourlyRate * $overtimeHours, 2)
            : 0.0;

        $attendanceDeduction = 0.0;
        if ($status === 'calculated' && $effectiveDailyRate > 0 && $periodDays > 0) {
            $absenceDays = max($periodDays - $loginDays, 0.0);
            if ($absenceDays > 0) {
                $attendanceDeduction = round($effectiveDailyRate * $absenceDays, 2);
            }
        } elseif ($status !== 'calculated' && $baseSalary > 0) {
            // Mirror structure salary in records but hold payout until attendance issues are cleared.
            $attendanceDeduction = $baseSalary;
        }

        return [
            'status' => $status,
            'note' => $note,
            'login_days' => $loginDays,
            'hours_worked' => $hoursWorked,
            'overtime_hours' => $overtimeHours,
            'daily_rate' => $effectiveDailyRate,
            'daily_rate_source' => $dailyRateSource,
            'shift_hours' => $shiftHours,
            'base_salary' => $baseSalary,
            'work_station_base_salary' => $workStationBaseSalary,
            'extra_hours_value' => $extraHoursValue,
            'attendance_deduction' => $attendanceDeduction,
        ];
    }

    protected function shouldPreserveDeduction(?float $existing, float $auto): bool
    {
        if ($existing === null) {
            return false;
        }

        return abs((float) $existing - $auto) > 0.01;
    }

    protected function resolveUsers(array $attendanceUserIds, ?int $departmentId = null): Collection
    {
        $eligible = User::query()
            ->where('staff_type_category', 'MDC')
            ->where('is_active', 1)
            ->whereNotNull('work_station')
            ->whereNotNull('work_days_per_week')
            ->whereNotNull('man_hours_per_day')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get([
                'id',
                'first_name',
                'last_name',
                'department_id',
                'man_hours_per_day',
                'work_days_per_week',
                'work_station',
                'staff_type_category',
                'is_active',
                'workstation_payment_structure_id',
                'base_salary_override',
                'base_salary_source',
            ])
            ->keyBy('id');

        $extraUserIds = collect($attendanceUserIds)->diff($eligible->keys())->all();
        if (!empty($extraUserIds)) {
            $extras = User::query()
                ->whereIn('id', $extraUserIds)
                ->where('staff_type_category', 'MDC')
                ->where('is_active', 1)
                ->whereNotNull('work_station')
                ->whereNotNull('work_days_per_week')
                ->whereNotNull('man_hours_per_day')
                ->get([
                    'id',
                    'first_name',
                    'last_name',
                    'department_id',
                    'man_hours_per_day',
                    'work_days_per_week',
                    'work_station',
                    'staff_type_category',
                    'is_active',
                    'workstation_payment_structure_id',
                    'base_salary_override',
                    'base_salary_source',
                ])
                ->keyBy('id');
            $eligible = $eligible->merge($extras);
        }

        return $eligible->values();
    }
}
