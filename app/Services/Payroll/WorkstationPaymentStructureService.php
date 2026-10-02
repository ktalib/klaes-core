<?php

namespace App\Services\Payroll;

use App\Models\Payroll\WorkstationPaymentStructure;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkstationPaymentStructureService
{
    public function dropdownOptions(?string $periodCode = null): array
    {
        return $this->structuresForPeriod($periodCode)
            ->mapWithKeys(function (WorkstationPaymentStructure $structure) {
                return [$structure->id => $this->formatLabel($structure)];
            })
            ->toArray();
    }

    public function structuresForPeriod(?string $periodCode = null): Collection
    {
        $structures = WorkstationPaymentStructure::query()
            ->orderBy('role_name')
            ->orderBy('work_days')
            ->orderByDesc('base_salary')
            ->get();

        if ($structures->isEmpty()) {
            return $structures;
        }

        $bounds = $this->resolvePeriodBounds($periodCode);

        return $structures->filter(function (WorkstationPaymentStructure $structure) use ($bounds) {
            if ($bounds) {
                [$start, $end] = $bounds;
                if ($structure->effective_from && $structure->effective_from->gt($end)) {
                    return false;
                }
                if ($structure->effective_to && $structure->effective_to->lt($start)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    protected function formatLabel(WorkstationPaymentStructure $structure): string
    {
        $role = $structure->role_name ?: $structure->code;
        $workDays = $structure->work_days !== null
            ? sprintf('%d %s', $structure->work_days, $structure->work_days === 1 ? 'day' : 'days')
            : 'flex';
        $amount = '₦' . number_format((float) $structure->base_salary, 2);

        return sprintf('%s • %s — %s', $role, $workDays, $amount);
    }

    protected function resolvePeriodBounds(?string $periodCode): ?array
    {
        if (!$periodCode) {
            return null;
        }

        try {
            [$year, $month] = explode('-', $periodCode);
            $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [$start, $end];
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
