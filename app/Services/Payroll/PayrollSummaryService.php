<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollAttendance;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollSalary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollSummaryService
{
    public function __construct(
        protected RateService $rateService,
    ) {
    }

    public function getSummary(PayrollPeriod $period, ?int $departmentId = null): array
    {
        $totals = $this->aggregateTotals($period, $departmentId);
        $attendanceStats = $this->attendanceSnapshot($period, $departmentId);

        return array_merge($totals, $attendanceStats);
    }

    public function getSalaryGrid(PayrollPeriod $period, ?int $departmentId = null): Collection
    {
        $attendance = PayrollAttendance::query()
            ->where('period_id', $period->id)
            ->select(['user_id', 'login_days', 'hours_worked', 'overtime_hours'])
            ->get()
            ->keyBy('user_id');

        $query = PayrollSalary::query()
            ->where('period_id', $period->id)
            ->with([
                'user:id,first_name,last_name,department_id,work_station,work_days_per_week,man_hours_per_day,staff_type_category,workstation_payment_structure_id,base_salary_override,base_salary_source',
                'user.department:id,name',
            ]);

        if ($departmentId) {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('department_id', $departmentId));
        }

        $periodDays = max(1, $period->periodEnd()->diffInDays($period->periodStart()) + 1);
        $periodCode = $period->period_code ?? $period->periodStart()->format('Y-m');

        return $query->get()->map(function (PayrollSalary $salary) use ($attendance, $period, $periodDays, $periodCode) {
            $attendanceRow = $attendance->get($salary->user_id);
            $loginDays = $attendanceRow ? (float) $attendanceRow->login_days : 0.0;
            $hoursWorked = $attendanceRow ? (float) $attendanceRow->hours_worked : 0.0;
            $overtimeHours = $attendanceRow ? (float) $attendanceRow->overtime_hours : 0.0;

            $activeRate = $this->rateService->findActiveRate($salary->user_id, $period->periodEnd());
            $workStationBase = $salary->user
                ? (float) $this->rateService->resolveWorkStationBaseSalary($salary->user, $periodCode)
                : 0.0;
            $hasWorkStationBase = $workStationBase > 0;
            $baseSalary = $hasWorkStationBase
                ? round($workStationBase, 2)
                : (float) $salary->base_salary;
            $rateDaily = $periodDays > 0 && $baseSalary > 0
                ? round($baseSalary / $periodDays, 2)
                : 0.0;
            $rateShiftHours = $activeRate ? (float) $activeRate->shift_hours : null;
            $shiftHours = $rateShiftHours ?? ($loginDays > 0 ? $hoursWorked / max($loginDays, 1) : null);
            if ($shiftHours === null && $salary->user?->man_hours_per_day) {
                $shiftHours = (float) $salary->user->man_hours_per_day;
            }
            $dailyRateSource = $hasWorkStationBase ? 'workstation' : ($activeRate ? 'rate' : 'derived');

            return [
                'user_id' => $salary->user_id,
                'user_name' => $salary->user?->name,
                'department_id' => $salary->user?->department_id,
                'department_name' => $salary->user?->department?->name,
                'work_station' => $salary->user?->work_station,
                'base_salary' => $baseSalary,
                'work_station_base_salary' => $workStationBase,
                'bonuses' => (float) $salary->bonuses,
                'extra_hours_value' => (float) $salary->extra_hours_value,
                'deductions' => (float) $salary->deductions,
                'net_salary' => (float) $salary->net_salary,
                'status' => $salary->calculation_status,
                'note' => $salary->note,
                'login_days' => $loginDays,
                'hours_worked' => $hoursWorked,
                'overtime_hours' => $overtimeHours,
                'daily_rate' => $rateDaily,
                'daily_rate_source' => $dailyRateSource,
                'rate_effective_date' => $activeRate?->effective_date,
                'shift_hours' => $shiftHours !== null ? (float) $shiftHours : null,
                'period_days' => $periodDays,
            ];
        });
    }

    protected function aggregateTotals(PayrollPeriod $period, ?int $departmentId = null): array
    {
        $query = PayrollSalary::query()
            ->where('period_id', $period->id);

        if ($departmentId) {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('department_id', $departmentId));
        }

        $aggregates = $query->selectRaw('
            COUNT(*) as staff_count,
            SUM(CASE WHEN calculation_status = ? THEN 1 ELSE 0 END) as calculated_count,
            SUM(CASE WHEN calculation_status != ? THEN 1 ELSE 0 END) as pending_count,
            SUM(base_salary) as base_total,
            SUM(bonuses) as bonus_total,
            SUM(extra_hours_value) as overtime_total,
            SUM(deductions) as deduction_total,
            SUM(net_salary) as net_total
        ', ['calculated', 'calculated'])->first();

        if (!$aggregates) {
            return [
                'staff_count' => 0,
                'calculated_count' => 0,
                'pending_count' => 0,
                'base_total' => 0.0,
                'bonus_total' => 0.0,
                'overtime_total' => 0.0,
                'deduction_total' => 0.0,
                'net_total' => 0.0,
            ];
        }

        return [
            'staff_count' => (int) $aggregates->staff_count,
            'calculated_count' => (int) $aggregates->calculated_count,
            'pending_count' => (int) $aggregates->pending_count,
            'base_total' => (float) $aggregates->base_total,
            'bonus_total' => (float) $aggregates->bonus_total,
            'overtime_total' => (float) $aggregates->overtime_total,
            'deduction_total' => (float) $aggregates->deduction_total,
            'net_total' => (float) $aggregates->net_total,
        ];
    }

    protected function attendanceSnapshot(PayrollPeriod $period, ?int $departmentId = null): array
    {
        $query = PayrollAttendance::query()
            ->where('period_id', $period->id);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as attendance_count,
            SUM(login_days) as total_login_days,
            SUM(hours_worked) as total_hours_worked,
            SUM(overtime_hours) as total_overtime_hours
        ')->first();

        if (!$stats) {
            return [
                'attendance_count' => 0,
                'total_login_days' => 0.0,
                'total_hours_worked' => 0.0,
                'total_overtime_hours' => 0.0,
            ];
        }

        return [
            'attendance_count' => (int) $stats->attendance_count,
            'total_login_days' => (float) $stats->total_login_days,
            'total_hours_worked' => (float) $stats->total_hours_worked,
            'total_overtime_hours' => (float) $stats->total_overtime_hours,
        ];
    }
}
