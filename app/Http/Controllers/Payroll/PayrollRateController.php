<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRate;
use App\Services\Payroll\RateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRateController extends Controller
{
    public function __construct(
        protected RateService $rateService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        [$resolvedPeriodCode, $resolvedPeriodDays] = $this->resolvePeriodMeta($request->query('period_code'));

        $query = PayrollRate::query()
            ->with([
                'user' => fn ($relation) => $relation
                    ->select(
                        'id',
                        'first_name',
                        'last_name',
                        'department_id',
                        'work_station',
                        'work_days_per_week',
                        'man_hours_per_day',
                        'staff_type_category',
                        'user_level',
                        'workstation_payment_structure_id',
                        'base_salary_override',
                        'base_salary_source'
                    )
                    ->with(['department:id,name']),
            ])
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('active')) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }

        $limit = (int) $request->query('limit', 50);

        $rates = $query->limit(max(1, $limit))->get()->map(function (PayrollRate $rate) use ($resolvedPeriodCode, $resolvedPeriodDays) {
            return $this->transformRate($rate, $resolvedPeriodCode, $resolvedPeriodDays);
        });

        return response()->json([
            'success' => true,
            'message' => 'Payroll rates retrieved successfully.',
            'data' => [
                'rates' => $rates,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll rates');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:sqlsrv.users,id'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'shift_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'effective_date' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_date'],
            'is_active' => ['sometimes', 'boolean'],
            'deactivate_existing' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_overtime' => ['sometimes', 'boolean'],
            'overtime_hours_cap' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = $request->user()?->id;
        $effectiveDate = Carbon::parse($data['effective_date'])->startOfDay();
        $expiresAt = !empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $deactivateExisting = array_key_exists('deactivate_existing', $data) ? (bool) $data['deactivate_existing'] : $isActive;

        if ($expiresAt && $expiresAt->lessThan($effectiveDate)) {
            throw ValidationException::withMessages([
                'expires_at' => 'The expires_at must be after the effective date.',
            ]);
        }

        [$resolvedPeriodCode, $resolvedPeriodDays] = $this->resolvePeriodMeta($request->input('period_code'));

        $rate = DB::connection('sqlsrv')->transaction(function () use (
            $data,
            $actorId,
            $effectiveDate,
            $expiresAt,
            $isActive,
            $deactivateExisting
        ) {
            if ($deactivateExisting) {
                $this->deactivateExistingRates($data['user_id'], $effectiveDate, $actorId);
            }

            $rate = PayrollRate::create([
                'user_id' => $data['user_id'],
                'daily_rate' => $data['daily_rate'],
                'shift_hours' => $data['shift_hours'],
                'effective_date' => $effectiveDate->toDateString(),
                'expires_at' => $expiresAt?->toDateString(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'is_active' => $isActive,
                'allow_overtime' => array_key_exists('allow_overtime', $data) ? (bool) $data['allow_overtime'] : false,
                'overtime_hours_cap' => array_key_exists('overtime_hours_cap', $data) && $data['overtime_hours_cap'] !== null
                    ? (float) $data['overtime_hours_cap']
                    : null,
                'overtime_notes' => $data['overtime_notes'] ?? ($data['note'] ?? null),
            ]);

            return $rate->fresh([
                'user:id,first_name,last_name,department_id,work_station,user_level,work_days_per_week,man_hours_per_day,staff_type_category,workstation_payment_structure_id,base_salary_override,base_salary_source',
                'user.department:id,name',
            ]);
        });

        $this->logAudit($rate->user_id, $actorId, 'payroll_rate_created', [
            'rate_id' => $rate->id,
            'daily_rate' => (float) $rate->daily_rate,
            'shift_hours' => (int) $rate->shift_hours,
            'effective_date' => $rate->effective_date,
            'expires_at' => $rate->expires_at,
            'is_active' => (bool) $rate->is_active,
            'allow_overtime' => (bool) $rate->allow_overtime,
            'overtime_hours_cap' => $rate->overtime_hours_cap !== null ? (float) $rate->overtime_hours_cap : null,
            'overtime_notes' => $rate->overtime_notes,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll rate created successfully.',
            'data' => [
                'rate' => $this->transformRate($rate, $resolvedPeriodCode, $resolvedPeriodDays),
            ],
        ], 201);
    }

    public function update(Request $request, int $rateId): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll rates');

        $rate = PayrollRate::query()
            ->with('user:id,first_name,last_name,department_id,work_station,user_level,work_days_per_week,man_hours_per_day,staff_type_category,workstation_payment_structure_id,base_salary_override,base_salary_source')
            ->findOrFail($rateId);

        $data = $request->validate([
            'daily_rate' => ['sometimes', 'numeric', 'min:0'],
            'shift_hours' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'effective_date' => ['sometimes', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'deactivate_existing' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_overtime' => ['sometimes', 'boolean'],
            'overtime_hours_cap' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = $request->user()?->id;

        [$resolvedPeriodCode, $resolvedPeriodDays] = $this->resolvePeriodMeta($request->input('period_code'));

        [$rate, $changes] = DB::connection('sqlsrv')->transaction(function () use ($data, $rate, $actorId) {
            $updates = [];
            $effectiveDate = Carbon::parse($rate->effective_date)->startOfDay();

            if (array_key_exists('daily_rate', $data)) {
                $updates['daily_rate'] = $data['daily_rate'];
            }

            if (array_key_exists('shift_hours', $data)) {
                $updates['shift_hours'] = (int) $data['shift_hours'];
            }

            if (array_key_exists('effective_date', $data)) {
                $effectiveDate = Carbon::parse($data['effective_date'])->startOfDay();
                $updates['effective_date'] = $effectiveDate->toDateString();
            }

            if (array_key_exists('expires_at', $data)) {
                $expiresAt = $data['expires_at'] ? Carbon::parse($data['expires_at'])->endOfDay() : null;

                if ($expiresAt && $expiresAt->lessThan($effectiveDate)) {
                    throw ValidationException::withMessages([
                        'expires_at' => 'The expires_at must be after the effective date.',
                    ]);
                }

                $updates['expires_at'] = $expiresAt?->toDateString();
            }

            $isActive = $data['is_active'] ?? $rate->is_active;
            $deactivateExisting = array_key_exists('deactivate_existing', $data)
                ? (bool) $data['deactivate_existing']
                : (bool) $isActive;

            if ($deactivateExisting && $isActive) {
                $this->deactivateExistingRates($rate->user_id, $effectiveDate, $actorId, $rate->id);
            }

            $updates['is_active'] = (bool) $isActive;
            $updates['updated_by'] = $actorId;

            if (array_key_exists('is_active', $data) && !$isActive && !array_key_exists('expires_at', $data)) {
                $updates['expires_at'] = Carbon::now()->toDateString();
            }

            if (array_key_exists('allow_overtime', $data)) {
                $updates['allow_overtime'] = (bool) $data['allow_overtime'];
            }

            if (array_key_exists('overtime_hours_cap', $data)) {
                $updates['overtime_hours_cap'] = $data['overtime_hours_cap'] !== null
                    ? (float) $data['overtime_hours_cap']
                    : null;
            }

            if (array_key_exists('overtime_notes', $data) || array_key_exists('note', $data)) {
                $updates['overtime_notes'] = $data['overtime_notes'] ?? ($data['note'] ?? $rate->overtime_notes);
            }

            $rate->fill($updates);
            $changes = $rate->getDirty();
            $rate->save();

            return [
                $rate->fresh([
                    'user:id,first_name,last_name,department_id,work_station,user_level,work_days_per_week,man_hours_per_day,staff_type_category,workstation_payment_structure_id,base_salary_override,base_salary_source',
                    'user.department:id,name',
                ]),
                $changes,
            ];
        });

        $this->logAudit($rate->user_id, $actorId, 'payroll_rate_updated', [
            'rate_id' => $rate->id,
            'changes' => $changes,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll rate updated successfully.',
            'data' => [
                'rate' => $this->transformRate($rate, $resolvedPeriodCode, $resolvedPeriodDays),
            ],
        ]);
    }

    protected function ensureAbility(Request $request, string $ability): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        if ($user->can($ability)) {
            return;
        }

        if ($ability === 'view payroll') {
            return;
        }

        $roleWhitelist = collect($user->assignedRoleNames());
        if ($roleWhitelist->isNotEmpty()) {
            $normalized = $roleWhitelist->map(fn ($role) => mb_strtolower($role));
            if ($normalized->intersect([
                'payroll management',
                'super admin',
                'supper admin',
            ])->isNotEmpty()) {
                return;
            }
        }

        if (mb_strtolower((string) $user->type) === 'super admin') {
            return;
        }

        abort(403, 'Unauthorized');
    }

    protected function deactivateExistingRates(int $userId, Carbon $effectiveDate, ?int $actorId = null, ?int $ignoreRateId = null): void
    {
        $closeDate = $effectiveDate->copy()->subDay();

        $existingRates = PayrollRate::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->when($ignoreRateId, fn ($query) => $query->where('id', '!=', $ignoreRateId))
            ->get();

        foreach ($existingRates as $existing) {
            $existing->is_active = false;
            if (!$existing->expires_at || Carbon::parse($existing->expires_at)->greaterThan($closeDate)) {
                $existing->expires_at = $closeDate->toDateString();
            }
            $existing->updated_by = $actorId;
            $existing->save();
        }
    }

    protected function transformRate(PayrollRate $rate, string $periodCode, ?int $periodDays): array
    {
        $workStationBase = $rate->user
            ? (float) $this->rateService->resolveWorkStationBaseSalary($rate->user, $periodCode)
            : 0.0;
        $configuredDailyRate = (float) $rate->daily_rate;
        $calculatedDailyRate = $periodDays && $workStationBase > 0
            ? round($workStationBase / $periodDays, 2)
            : 0.0;
        $effectiveDailyRate = $calculatedDailyRate > 0 ? $calculatedDailyRate : $configuredDailyRate;
        $dailyRateSource = $calculatedDailyRate > 0 ? 'workstation' : ($configuredDailyRate > 0 ? 'rate' : 'derived');

        return [
            'id' => $rate->id,
            'user_id' => $rate->user_id,
            'user_name' => $rate->user?->name,
            'department_id' => $rate->user?->department_id,
            'department_name' => $rate->user?->department?->name,
            'work_station' => $rate->user?->work_station,
            'position' => $rate->user?->user_level,
            'daily_rate' => $effectiveDailyRate,
            'configured_daily_rate' => $configuredDailyRate,
            'shift_hours' => (int) $rate->shift_hours,
            'effective_date' => $rate->effective_date,
            'expires_at' => $rate->expires_at,
            'is_active' => (bool) $rate->is_active,
            'status_label' => $rate->is_active ? 'Active' : 'Inactive',
            'created_at' => optional($rate->created_at)->toDateTimeString(),
            'updated_at' => optional($rate->updated_at)->toDateTimeString(),
            'work_station_base_salary' => $workStationBase,
            'daily_rate_source' => $dailyRateSource,
            'period_code' => $periodCode,
            'period_days' => $periodDays,
        ];
    }

    protected function resolvePeriodMeta(?string $periodCode): array
    {
        if ($periodCode) {
            $matched = PayrollPeriod::query()
                ->where('period_code', $periodCode)
                ->first();

            if ($matched) {
                $start = $matched->periodStart();
                $end = $matched->periodEnd();

                return [
                    $matched->period_code ?? $periodCode,
                    $end->diffInDays($start) + 1,
                ];
            }

            try {
                $parsed = Carbon::createFromFormat('Y-m', $periodCode)->startOfMonth();

                return [
                    $periodCode,
                    $parsed->daysInMonth,
                ];
            } catch (\Exception) {
                // ignore parse errors and fall through to defaults
            }
        }

        $current = PayrollPeriod::query()
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($current) {
            $start = $current->periodStart();
            $end = $current->periodEnd();

            return [
                $current->period_code ?? $start->format('Y-m'),
                $end->diffInDays($start) + 1,
            ];
        }

        $today = now();

        return [
            $periodCode ?? $today->format('Y-m'),
            $today->daysInMonth,
        ];
    }

    protected function logAudit(int $userId, ?int $actorId, string $action, array $payload = []): void
    {
        PayrollAuditLog::create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'source' => 'payroll_rates',
        ]);
    }
}
