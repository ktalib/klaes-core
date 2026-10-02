<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollPeriod;
use App\Services\Payroll\PayrollSummaryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollPeriodController extends Controller
{
    public function __construct(
        protected PayrollSummaryService $summaryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        $query = PayrollPeriod::query()
            ->withCount(['attendance', 'salaries'])
            ->orderByDesc('start_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $limit = (int) $request->query('limit', 24);

        $periods = $query->limit(max(1, $limit))->get()->map(function (PayrollPeriod $period) {
            return [
                'id' => $period->id,
                'period_code' => $period->period_code,
                'start_date' => $period->periodStart()->toDateString(),
                'end_date' => $period->periodEnd()->toDateString(),
                'status' => $period->status,
                'locked_at' => optional($period->locked_at)->toDateTimeString(),
                'attendance_count' => $period->attendance_count,
                'salary_count' => $period->salaries_count,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Payroll periods retrieved successfully.',
            'data' => [
                'periods' => $periods,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        $data = $request->validate([
            'period_code' => ['required', 'regex:/^\\d{4}-(0[1-9]|1[0-2])$/'],
            'status' => ['nullable', 'string', 'max:32'],
            'autogenerate_assets' => ['sometimes', 'boolean'],
        ]);

        $periodCode = $data['period_code'];
        $start = Carbon::createFromFormat('Y-m', $periodCode)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $payload = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];

        if (!empty($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (!$request->user()) {
            abort(401, 'Unauthenticated');
        }

        $payload['created_by'] = $request->user()->id;

        $period = null;
        $created = false;

        DB::connection('sqlsrv')->transaction(function () use (&$period, &$created, $periodCode, $payload, $request) {
            $period = PayrollPeriod::firstOrCreate(
                ['period_code' => $periodCode],
                $payload
            );

            if (!$period->wasRecentlyCreated) {
                $period->fill($payload);
                if ($period->isDirty()) {
                    $period->save();
                }
            } else {
                $created = true;
            }

            $this->logAudit(
                $period,
                $request->user()->id,
                $created ? 'payroll_period_created' : 'payroll_period_updated',
                [
                    'payload' => $payload,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => $created ? 'Payroll period created.' : 'Payroll period updated.',
            'data' => [
                'period' => [
                    'id' => $period->id,
                    'period_code' => $period->period_code,
                    'start_date' => $period->periodStart()->toDateString(),
                    'end_date' => $period->periodEnd()->toDateString(),
                    'status' => $period->status,
                ],
            ],
        ], $created ? 201 : 200);
    }

    public function show(Request $request, string $periodCode): JsonResponse
    {
        $this->ensureAbility($request, 'view payroll');

        try {
            $period = $this->findPeriodByCode($periodCode);
        } catch (ModelNotFoundException $exception) {
            return $this->notFoundResponse('Payroll period not found.');
        }

        $summary = $this->summaryService->getSummary($period);

        return response()->json([
            'success' => true,
            'message' => 'Payroll period summary retrieved.',
            'data' => [
                'period' => [
                    'id' => $period->id,
                    'period_code' => $period->period_code,
                    'start_date' => $period->periodStart()->toDateString(),
                    'end_date' => $period->periodEnd()->toDateString(),
                    'status' => $period->status,
                    'locked_at' => optional($period->locked_at)->toDateTimeString(),
                ],
                'summary' => $summary,
            ],
        ]);
    }

    public function lock(Request $request, string $periodCode): JsonResponse
    {
        $this->ensureAbility($request, 'manage payroll');

        try {
            $period = $this->findPeriodByCode($periodCode);
        } catch (ModelNotFoundException $exception) {
            return $this->notFoundResponse('Payroll period not found.');
        }
        if ($period->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll period is already locked.',
                'data' => [],
            ], 409);
        }

        $period->markLocked($request->user()->id);

        $this->logAudit(
            $period,
            $request->user()->id,
            'payroll_period_locked',
            []
        );

        return response()->json([
            'success' => true,
            'message' => 'Payroll period locked successfully.',
            'data' => [
                'period' => [
                    'id' => $period->id,
                    'period_code' => $period->period_code,
                    'status' => $period->status,
                    'locked_at' => optional($period->locked_at)->toDateTimeString(),
                ],
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

        abort(403, 'Unauthorized');
    }

    protected function notFoundResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], 404);
    }

    protected function findPeriodByCode(string $periodCode): PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('period_code', $periodCode)
            ->firstOrFail();
    }

    protected function logAudit(PayrollPeriod $period, ?int $actorId, string $action, array $payload = []): void
    {
        PayrollAuditLog::create([
            'period_id' => $period->id,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'source' => 'payroll_api',
        ]);
    }
}
