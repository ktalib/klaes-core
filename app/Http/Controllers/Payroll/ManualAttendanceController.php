<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\ManualAttendance\ManualAttendanceEntry;
use App\Services\ManualAttendance\ManualAttendanceService;
use App\Support\Concerns\ResolvesWorkStations;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ManualAttendanceController extends Controller
{
    use ResolvesWorkStations;

    public function __construct(protected ManualAttendanceService $attendanceService)
    {
    }

    public function index(Request $request)
    {
        $workStations = $this->workStationOptions();
        $shiftOptions = $this->shiftOptions();
        $urlContext = (string) $request->query('url');
        $isEmbedded = in_array($urlContext, ['frontdesks', 'fontdesks'], true);

        return view('payroll.manual-attendance.index', [
            'workStations' => $workStations,
            'shiftOptions' => $shiftOptions,
            'defaultDate' => Carbon::now()->toDateString(),
            'isEmbedded' => $isEmbedded,
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:sqlsrv.departments,id'],
            'work_station' => ['nullable', 'string', 'max:150'],
            'search' => ['nullable', 'string', 'max:100'],
            'shift_code' => ['nullable', 'string', Rule::in(array_keys(config('attendance.shifts', [])))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:250'],
        ]);

        $date = Carbon::parse($validated['date']);
        $limit = (int) ($validated['per_page'] ?? 150);

        // Fetch only manual attendance staff (is_pc_access = 0)
        $employees = $this->attendanceService->listManualStaff(
            $date,
            $validated['search'] ?? null,
            $validated['department_id'] ?? null,
            $validated['work_station'] ?? null,
            $validated['shift_code'] ?? null,
            $limit > 0 ? $limit : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual attendance employees resolved successfully.',
            'data' => $employees->values()->toArray(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:sqlsrv.departments,id'],
            'work_station' => ['nullable', 'string', 'max:150'],
            'shift_code' => ['nullable', 'string', Rule::in(array_keys(config('attendance.shifts', [])))],
            'search_term' => ['nullable', 'string', 'max:100'],
        ]);

        $date = Carbon::parse($validated['date']);

        $query = $this->attendanceService->manualStaffTableQuery(
            $date,
            $validated['department_id'] ?? null,
            $validated['work_station'] ?? null,
            $validated['shift_code'] ?? null,
        );

        $searchTerm = $validated['search_term'] ?? (string) $request->input('search.value', '');

        $dataTable = DataTables::of($query)
            ->filter(function ($builder) use ($searchTerm) {
                $term = trim(mb_strtolower($searchTerm ?? ''));
                if ($term === '') {
                    return;
                } 

                $builder->where(function ($query) use ($term) {
                    $like = '%' . $term . '%';
                    $query->whereRaw('LOWER(users.first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(users.last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(users.username) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
                });
            });

        $payload = $dataTable->toArray();
        $rows = $payload['data'] ?? [];

        $payload['data'] = array_map(function (array $row) {
            $record = (object) $row;
            return $this->attendanceService->formatManualStaffRecord($record);
        }, $rows);

        return response()->json($payload);
    }

    protected function shiftOptions(): array
    {
        return collect(config('attendance.shifts', []))
            ->mapWithKeys(static function (array $shift, string $code) {
                $label = $shift['label'] ?? ucwords(str_replace('_', ' ', $code));

                return [$code => $label];
            })
            ->toArray();
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('action')) {
            $action = $request->input('action');

            if ($action === 'undo') {
                $validated = $request->validate([
                    'user_id' => ['required', 'integer', 'exists:sqlsrv.users,id'],
                    'attendance_date' => ['required', 'date'],
                    'action' => ['required', Rule::in(['undo'])],
                    'notes' => ['nullable', 'string', 'max:500'],
                ]);

                try {
                    $entry = $this->attendanceService->resetAttendance(
                        (int) $validated['user_id'],
                        $validated['attendance_date'],
                        $request->user()->id,
                        $validated['notes'] ?? null,
                    );
                } catch (\Throwable $exception) {
                    return response()->json([
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Manual attendance entry reverted.',
                    'data' => [
                        'entries' => [[
                            'user_id' => $entry->user_id,
                            'entry' => $this->transformEntryResponse($entry),
                        ]],
                    ],
                ]);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:sqlsrv.users,id'],
                'attendance_date' => ['required', 'date'],
                'action' => ['required', Rule::in(['check_in', 'check_out'])],
                'timestamp' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            $timestamp = Carbon::parse($validated['timestamp'], config('app.timezone'));

            try {
                if ($validated['action'] === 'check_in') {
                    $entry = $this->attendanceService->recordCheckIn(
                        (int) $validated['user_id'],
                        $validated['attendance_date'],
                        $timestamp,
                        $request->user()->id,
                        $validated['notes'] ?? null,
                    );

                    $message = 'Check-in recorded successfully.';
                } else {
                    $entry = $this->attendanceService->recordCheckOut(
                        (int) $validated['user_id'],
                        $validated['attendance_date'],
                        $timestamp,
                        $request->user()->id,
                        $validated['notes'] ?? null,
                    );

                    $message = 'Check-out recorded successfully.';
                }
            } catch (\Throwable $exception) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'entries' => [[
                        'user_id' => $entry->user_id,
                        'entry' => $this->transformEntryResponse($entry),
                    ]],
                ],
            ]);
        }

        if ($request->has('user_ids')) {
            $validated = $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer', 'distinct', 'exists:sqlsrv.users,id'],
                'attendance_date' => ['required', 'date'],
                'is_present' => ['required', 'boolean'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            try {
                $entries = $this->attendanceService->recordBulkPresence(
                    $validated['user_ids'],
                    $validated['attendance_date'],
                    (bool) $validated['is_present'],
                    $request->user()->id,
                    $validated['notes'] ?? null,
                );
            } catch (\Throwable $exception) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => sprintf('Attendance recorded for %d staff.', $entries->count()),
                'data' => [
                    'entries' => $entries->map(function (ManualAttendanceEntry $entry) {
                        return [
                            'user_id' => $entry->user_id,
                            'entry' => $this->transformEntryResponse($entry),
                        ];
                    })->values()->all(),
                ],
            ]);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:sqlsrv.users,id'],
            'attendance_date' => ['required', 'date'],
            'is_present' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->attendanceService->recordPresence(
            (int) $validated['user_id'],
            $validated['attendance_date'],
            (bool) $validated['is_present'],
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => [
                'entries' => [[
                    'user_id' => $entry->user_id,
                    'entry' => $this->transformEntryResponse($entry),
                ]],
            ],
        ]);
    }

    protected function transformEntryResponse(ManualAttendanceEntry $entry): array
    {
        return $this->attendanceService->formatEntryForResponse($entry);
    }

    public function transition(Request $request, ManualAttendanceEntry $entry): JsonResponse
    {
        // This endpoint removed; use store() for presence recording instead
        return response()->json([
            'error' => 'Use POST /manual-attendance/entries to record attendance.',
        ], 405);
    }
}
