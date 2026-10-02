<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollRate;
use App\Models\Payroll\WorkstationPaymentStructure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RateService
{
    public function findActiveRate(int $userId, ?Carbon $date = null): ?PayrollRate
    {
        $date = $date?->copy() ?? now();

        return PayrollRate::query()
            ->where('user_id', $userId)
            ->active()
            ->effectiveOn($date)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->first();
    }

    public function resolveShiftHours(User $user, ?Carbon $date = null): int
    {
        $profile = $this->resolveShiftProfile($user, $date);

        return (int) ($profile['hours'] ?? max(1, $user->man_hours_per_day ?? 8));
    }

    public function resolveShiftProfile(User $user, ?Carbon $date = null): array
    {
        $rate = $this->findActiveRate($user->id, $date);
        if ($rate) {
            return [
                'hours' => (int) max(1, $rate->shift_hours ?? 0),
                'allow_overtime' => (bool) $rate->allow_overtime,
                'overtime_cap' => $rate->overtime_hours_cap !== null
                    ? (float) $rate->overtime_hours_cap
                    : null,
            ];
        }

        return [
            'hours' => (int) max(1, $user->man_hours_per_day ?? 8),
            'allow_overtime' => false,
            'overtime_cap' => null,
        ];
    }

    public function resolveDailyRate(User $user, ?Carbon $date = null): float
    {
        $rate = $this->findActiveRate($user->id, $date);
        if ($rate) {
            return (float) $rate->daily_rate;
        }

        return 0.0;
    }

    public function resolveWorkStationBaseSalary(?User $user, ?string $periodCode = null): float
    {
        if (!$user) {
            return 0.0;
        }

        if (mb_strtolower((string) $user->staff_type_category) !== 'mdc') {
            return 0.0;
        }

        if ($user->base_salary_override && $user->base_salary_override > 0) {
            return (float) $user->base_salary_override;
        }

        if (empty($user->work_station)) {
            return 0.0;
        }

        if ($user->workstation_payment_structure_id) {
            $structure = WorkstationPaymentStructure::query()
                ->find($user->workstation_payment_structure_id);

            if ($structure && $this->structureApplies($structure, (int) $user->work_days_per_week, $periodCode)) {
                return (float) $structure->base_salary;
            }
        }

        $normalizedStation = $this->normalizeWorkStationValue($user->work_station);
        if (!$normalizedStation) {
            return 0.0;
        }

        $structure = $this->findWorkstationStructure(
            $normalizedStation,
            (int) $user->work_days_per_week,
            $periodCode
        );

        if ($structure) {
            return (float) $structure->base_salary;
        }

        return $this->resolveLegacyConfigAmount($normalizedStation, $periodCode);
    }

    public function syncBaselineRate(User $user, ?int $actorId = null): PayrollRate
    {
        $existing = $this->findActiveRate($user->id, now());
        if ($existing) {
            return $existing;
        }

        return DB::connection('sqlsrv')->transaction(function () use ($user, $actorId) {
            return PayrollRate::create([
                'user_id' => $user->id,
                'daily_rate' => 0,
                'shift_hours' => max(1, $user->man_hours_per_day ?? 8),
                'effective_date' => now()->toDateString(),
                'created_by' => $actorId,
                'is_active' => true,
            ]);
        });
    }

    protected function normalizeWorkStationValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->value();

        return $normalized !== '' ? $normalized : null;
    }

    protected function findWorkstationStructure(string $normalizedStation, ?int $workDays, ?string $periodCode): ?WorkstationPaymentStructure
    {
        $candidates = WorkstationPaymentStructure::query()
            ->where(function ($query) use ($normalizedStation) {
                $query->where('code', $normalizedStation)
                    ->orWhereHas('aliases', function ($aliasQuery) use ($normalizedStation) {
                        $aliasQuery->where('normalized_alias', $normalizedStation);
                    });
            })
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $bounds = $this->resolvePeriodBounds($periodCode);

        $filtered = $candidates->filter(fn (WorkstationPaymentStructure $structure) =>
            $this->structureApplies($structure, $workDays, $periodCode)
        );

        if ($filtered->isEmpty()) {
            $filtered = $candidates;
        }

        $sorted = $filtered->sortByDesc(function (WorkstationPaymentStructure $structure) use ($workDays) {
            $score = 0;
            if ($structure->work_days !== null && $workDays !== null && (int) $structure->work_days === (int) $workDays) {
                $score += 10;
            } elseif ($structure->work_days === null) {
                $score += 5;
            }

            if ($structure->effective_from) {
                $score += 2;
            }

            return $score;
        })->values();

        return $sorted->first();
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

    protected function resolveLegacyConfigAmount(string $normalizedStation, ?string $periodCode): float
    {
        $periods = config('payroll.workstation_categories.periods', []);
        $fallback = config('payroll.workstation_categories.fallback', []);

        $candidateSets = [];

        if ($periodCode && isset($periods[$periodCode])) {
            $candidateSets[] = $periods[$periodCode];
        }

        if ($periodCode) {
            $yearKey = substr($periodCode, 0, 4);
            if ($yearKey && isset($periods[$yearKey])) {
                $candidateSets[] = $periods[$yearKey];
            }
        }

        if (isset($periods['*'])) {
            $candidateSets[] = $periods['*'];
        }

        if ($fallback) {
            $candidateSets[] = $fallback;
        }

        foreach ($candidateSets as $set) {
            foreach ($set as $entry) {
                $amount = $entry['amount'] ?? null;
                if (!$amount) {
                    continue;
                }

                $aliases = $entry['aliases'] ?? [];
                foreach ($aliases as $alias) {
                    if ($this->normalizeWorkStationValue($alias) === $normalizedStation) {
                        return (float) $amount;
                    }
                }
            }
        }

        return 0.0;
    }

    protected function structureApplies(WorkstationPaymentStructure $structure, ?int $workDays, ?string $periodCode): bool
    {
        $bounds = $this->resolvePeriodBounds($periodCode);

        if ($bounds) {
            [$start, $end] = $bounds;
            if ($structure->effective_from && $structure->effective_from->gt($end)) {
                return false;
            }
            if ($structure->effective_to && $structure->effective_to->lt($start)) {
                return false;
            }
        }

        if ($structure->work_days !== null && $workDays !== null) {
            return (int) $structure->work_days === (int) $workDays;
        }

        return true;
    }
}
