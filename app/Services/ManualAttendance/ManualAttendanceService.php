<?php

namespace App\Services\ManualAttendance;

use App\Models\Attendance\AttendanceDailySnapshot;
use App\Models\ManualAttendance\ManualAttendanceAuditLog;
use App\Models\ManualAttendance\ManualAttendanceBatch;
use App\Models\ManualAttendance\ManualAttendanceEntry;
use App\Models\Payroll\PayrollAttendance;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ManualAttendanceService
{
    public function __construct(protected DatabaseManager $database)
    {
    }

    public function getOrCreateBatch(Carbon $periodStart, Carbon $periodEnd, ?int $submittedBy = null): ManualAttendanceBatch
    {
        $batch = ManualAttendanceBatch::query()
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->latest('id')
            ->first();

        if ($batch) {
            return $batch;
        }

        return ManualAttendanceBatch::create([
            'batch_code' => $this->generateBatchCode($periodStart, $periodEnd),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'submitted_by' => $submittedBy,
        ]);
    }

    public function listEmployees(Carbon $date, ?string $searchTerm = null, ?int $departmentId = null, ?string $workStation = null, ?string $shiftCode = null): Collection
    {
        // Kept for backward compatibility; use listManualStaff() instead
        return $this->listManualStaff($date, $searchTerm, $departmentId, $workStation, $shiftCode);
    }

    public function listManualStaff(
        Carbon $date,
        ?string $searchTerm = null,
        ?int $departmentId = null,
        ?string $workStation = null,
        ?string $shiftCode = null,
        ?int $limit = null
    ): Collection
    {
        $query = User::query()
            ->where('is_active', 1)
            ->where('is_pc_access', 0) // Only manual attendance staff
            ->whereRaw('UPPER(staff_type_category) = ?', ['MDC'])
            ->with('department:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $workStationValue = $workStation !== null ? trim($workStation) : null;
        if ($workStationValue !== null && $workStationValue !== '') {
            $query->where('work_station', $workStationValue);
        }

        $shiftValue = $shiftCode !== null ? trim($shiftCode) : null;
        if ($shiftValue !== null && $shiftValue !== '') {
            $query->where('shift_code', $shiftValue);
        }

        if ($searchTerm) {
            $term = '%' . Str::lower($searchTerm) . '%';
            $query->where(function ($builder) use ($term) {
                $builder->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $users = $query->get([
            'id',
            'first_name',
            'last_name',
            'username',
            'email',
            'department_id',
            'work_station',
            'user_level',
            'man_hours_per_day',
            'shift_code',
            'staff_type_category',
            'passport_photo_path',
            'profile',
        ]);

        if ($users->isEmpty()) {
            return collect();
        }

        $dateString = $date->toDateString();

        $entryQuery = ManualAttendanceEntry::query()
            ->where('attendance_date', $dateString)
            ->whereIn('user_id', $users->pluck('id'));

        $entries = $entryQuery->get()->keyBy('user_id');

        return $users->map(function (User $user) use ($entries) {
            $entry = $entries->get($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'department_id' => $user->department_id,
                'department_name' => $user->department?->name,
                'work_station' => $user->work_station,
                'staff_type_category' => $user->staff_type_category,
                'shift_code' => $user->shift_code,
                'position' => $user->user_level,
                'shift_hours' => (float) ($user->man_hours_per_day ?? 0),
                'username' => $user->username,
                'email' => $user->email,
                'passport_photo_path' => $user->passport_photo_path,
                'passport_photo_url' => $this->resolvePhotoUrl($user),
                'entry' => $entry ? $this->formatEntryForResponse($entry) : null,
            ];
        })->values();
    }

    public function manualStaffTableQuery(
        Carbon $date,
        ?int $departmentId = null,
        ?string $workStation = null,
        ?string $shiftCode = null
    ): Builder {
        $dateString = $date->toDateString();

        $query = User::query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.email',
                'users.department_id',
                'dept.name as department_name',
                'users.work_station',
                'users.user_level',
                'users.man_hours_per_day',
                'users.shift_code',
                'users.staff_type_category',
                'users.passport_photo_path',
                'users.profile',
                'mae.id as entry_id',
                'mae.attendance_date as entry_attendance_date',
                'mae.is_present as entry_is_present',
                'mae.check_in_time as entry_check_in_time',
                'mae.check_out_time as entry_check_out_time',
                'mae.shift_code as entry_shift_code',
                'mae.login_hours as entry_login_hours',
                'mae.overtime_hours as entry_overtime_hours',
                'mae.status as entry_status',
                'mae.notes as entry_notes',
                'mae.metadata as entry_metadata',
            ])
            ->leftJoin('manual_attendance_entries as mae', function ($join) use ($dateString) {
                $join->on('mae.user_id', '=', 'users.id')
                    ->where('mae.attendance_date', '=', $dateString);
            })
            ->leftJoin('departments as dept', 'dept.id', '=', 'users.department_id')
            ->where('users.is_active', 1)
            ->where('users.is_pc_access', 0)
            ->whereRaw('UPPER(users.staff_type_category) = ?', ['MDC']);

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        $workStationValue = $workStation !== null ? trim($workStation) : null;
        if ($workStationValue !== null && $workStationValue !== '') {
            $query->where('users.work_station', $workStationValue);
        }

        $shiftValue = $shiftCode !== null ? trim($shiftCode) : null;
        if ($shiftValue !== null && $shiftValue !== '') {
            $query->where('users.shift_code', $shiftValue);
        }

        return $query;
    }

    public function recordPresence(
        int $userId,
        string $date,
        bool $isPresent,
        int $actorId,
        ?string $notes = null,
        ?Carbon $checkInAt = null,
        ?Carbon $checkOutAt = null,
        ?ManualAttendanceBatch $batch = null,
        bool $wrapInTransaction = true
    ): ManualAttendanceEntry {
        if ($isPresent) {
            if ($checkOutAt instanceof Carbon) {
                return $this->recordCheckOut($userId, $date, $checkOutAt, $actorId, $notes, $wrapInTransaction);
            }

            $checkInAt = $checkInAt instanceof Carbon ? $checkInAt : Carbon::now();

            return $this->recordCheckIn($userId, $date, $checkInAt, $actorId, $notes, $batch, $wrapInTransaction);
        }

        return $this->recordAbsence($userId, $date, $actorId, $notes, $batch, $wrapInTransaction);
    }

    public function recordBulkPresence(
        array $userIds,
        string $date,
        bool $isPresent,
        int $actorId,
        ?string $notes = null,
        ?Carbon $checkInAt = null
    ): Collection {
        $uniqueIds = collect($userIds)
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        $attendanceDate = Carbon::parse($date);
        $referenceCheckIn = $checkInAt instanceof Carbon ? $checkInAt->copy() : Carbon::now();

        return $this->database->connection('sqlsrv')->transaction(function () use ($uniqueIds, $attendanceDate, $isPresent, $actorId, $notes, $referenceCheckIn) {
            $periodStart = (clone $attendanceDate)->startOfMonth();
            $periodEnd = (clone $attendanceDate)->endOfMonth();

            $batch = $this->getOrCreateBatch($periodStart, $periodEnd, $actorId);

            $entries = $uniqueIds->map(function (int $userId) use ($attendanceDate, $isPresent, $actorId, $notes, $referenceCheckIn, $batch) {
                if ($isPresent) {
                    return $this->recordCheckIn(
                        $userId,
                        $attendanceDate->toDateString(),
                        $referenceCheckIn->copy(),
                        $actorId,
                        $notes,
                        $batch,
                        false
                    )->fresh();
                }

                return $this->recordAbsence(
                    $userId,
                    $attendanceDate->toDateString(),
                    $actorId,
                    $notes,
                    $batch,
                    false
                )->fresh();
            });

            return $entries->values();
        });
    }

    public function resetAttendance(int $userId, string $date, int $actorId, ?string $notes = null): ManualAttendanceEntry
    {
        $attendanceDate = Carbon::parse($date);

        return $this->database->connection('sqlsrv')->transaction(function () use ($userId, $attendanceDate, $actorId, $notes) {
            $entry = ManualAttendanceEntry::query()
                ->where('user_id', $userId)
                ->where('attendance_date', $attendanceDate->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$entry) {
                throw new \RuntimeException('No manual attendance entry found to undo.');
            }

            $user = User::query()->find($userId);
            if (!$user) {
                throw new \RuntimeException("User not found: {$userId}");
            }

            $previousPayload = [
                'is_present' => (bool) $entry->is_present,
                'check_in_time' => optional($entry->check_in_time)->toDateTimeString(),
                'check_out_time' => optional($entry->check_out_time)->toDateTimeString(),
                'hours_worked' => (float) $entry->login_hours,
                'overtime_hours' => (float) $entry->overtime_hours,
                'status' => $entry->status,
            ];

            $entry->is_present = false;
            $entry->check_in_time = null;
            $entry->check_out_time = null;
            $entry->login_hours = 0;
            $entry->overtime_hours = 0;
            $entry->status = 'draft';
            $entry->notes = $notes ?? null;
            $entry->metadata = null;
            $entry->captured_by = $actorId;
            $entry->captured_at = Carbon::now();
            $entry->save();

            ManualAttendanceAuditLog::create([
                'entry_id' => $entry->id,
                'action' => 'reset',
                'performed_by' => $actorId,
                'payload' => [
                    'notes' => $notes,
                    'previous' => $previousPayload,
                ],
            ]);

            $this->rebuildPayrollAttendanceForUser($userId, $attendanceDate);
            $this->clearManualSnapshot($user, $attendanceDate);

            return $entry->fresh();
        });
    }

    public function recordCheckIn(
        int $userId,
        string $date,
        Carbon $checkInAt,
        int $actorId,
        ?string $notes = null,
        ?ManualAttendanceBatch $batch = null,
        bool $wrapInTransaction = true
    ): ManualAttendanceEntry
    {
        $attendanceDate = Carbon::parse($date);
        $periodStart = (clone $attendanceDate)->startOfMonth();
        $periodEnd = (clone $attendanceDate)->endOfMonth();

        $resolvedBatch = $batch ?? $this->getOrCreateBatch($periodStart, $periodEnd, $actorId);

        $callback = function () use ($resolvedBatch, $userId, $attendanceDate, $checkInAt, $actorId, $notes) {
            $user = User::query()->find($userId);
            if (!$user) {
                throw new \RuntimeException("User not found: {$userId}");
            }

            $entry = ManualAttendanceEntry::query()->firstOrNew([
                'user_id' => $userId,
                'attendance_date' => $attendanceDate->toDateString(),
            ]);

            $entry->batch_id = $resolvedBatch->id;
            $entry->is_present = true;
            $entry->check_in_time = $checkInAt->copy();
            $entry->check_out_time = null;
            $entry->shift_code = (string) $this->resolveShiftHours($user);
            $entry->login_hours = 0;
            $entry->overtime_hours = 0;
            $entry->status = 'approved';
            if ($notes !== null) {
                $entry->notes = $notes;
            }
            $entry->captured_by = $actorId;
            $entry->captured_at = Carbon::now();

            $entry->save();
            $entry->refresh();

            ManualAttendanceAuditLog::create([
                'entry_id' => $entry->id,
                'action' => 'check_in',
                'performed_by' => $actorId,
                'payload' => [
                    'check_in_time' => $entry->check_in_time?->toDateTimeString(),
                    'notes' => $notes,
                ],
            ]);

            $this->syncPayrollAttendance($entry);
            $this->syncDailySnapshot($user, $attendanceDate, $entry->check_in_time, null, 'present');

            return $entry->fresh();
        };

        if ($wrapInTransaction) {
            return $this->database->connection('sqlsrv')->transaction($callback);
        }

        return $callback();
    }

    public function recordCheckOut(
        int $userId,
        string $date,
        Carbon $checkOutAt,
        int $actorId,
        ?string $notes = null,
        bool $wrapInTransaction = true
    ): ManualAttendanceEntry
    {
        $attendanceDate = Carbon::parse($date);

        $callback = function () use ($attendanceDate, $userId, $checkOutAt, $actorId, $notes) {
            $entry = ManualAttendanceEntry::query()
                ->where('user_id', $userId)
                ->where('attendance_date', $attendanceDate->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$entry) {
                throw new \RuntimeException('Cannot check out before checking in.');
            }

            if (!$entry->check_in_time) {
                $entry->check_in_time = $checkOutAt->copy();
            }

            $user = User::query()->find($userId);
            if (!$user) {
                throw new \RuntimeException("User not found: {$userId}");
            }

            $checkIn = $entry->check_in_time instanceof Carbon
                ? $entry->check_in_time->copy()
                : Carbon::parse($entry->check_in_time);

            $checkoutTime = $checkOutAt->copy();
            if ($checkoutTime->lessThan($checkIn)) {
                $checkoutTime = $checkIn->copy();
            }

            $workedHours = $this->calculateWorkedHours($checkIn, $checkoutTime);
            $shiftHours = $this->resolveShiftHours($user);
            $overtime = max(0, round($workedHours - $shiftHours, 2));

            $entry->check_out_time = $checkoutTime;
            $entry->is_present = true;
            $entry->login_hours = $workedHours;
            $entry->overtime_hours = $overtime;
            $entry->status = 'approved';
            if ($notes !== null) {
                $entry->notes = $notes;
            }

            $entry->save();
            $entry->refresh();

            ManualAttendanceAuditLog::create([
                'entry_id' => $entry->id,
                'action' => 'check_out',
                'performed_by' => $actorId,
                'payload' => [
                    'check_in_time' => $checkIn->toDateTimeString(),
                    'check_out_time' => $checkoutTime->toDateTimeString(),
                    'hours_worked' => $workedHours,
                    'overtime_hours' => $overtime,
                    'notes' => $notes,
                ],
            ]);

            $this->syncPayrollAttendance($entry);
            $this->syncDailySnapshot($user, $attendanceDate, $entry->check_in_time, $entry->check_out_time, 'present');

            return $entry->fresh();
        };

        if ($wrapInTransaction) {
            return $this->database->connection('sqlsrv')->transaction($callback);
        }

        return $callback();
    }

    protected function recordAbsence(
        int $userId,
        string $date,
        int $actorId,
        ?string $notes = null,
        ?ManualAttendanceBatch $batch = null,
        bool $wrapInTransaction = true
    ): ManualAttendanceEntry
    {
        $attendanceDate = Carbon::parse($date);
        $periodStart = (clone $attendanceDate)->startOfMonth();
        $periodEnd = (clone $attendanceDate)->endOfMonth();

        $resolvedBatch = $batch ?? $this->getOrCreateBatch($periodStart, $periodEnd, $actorId);
        $user = User::query()->find($userId);
        if (!$user) {
            throw new \RuntimeException("User not found: {$userId}");
        }

        $callback = function () use ($resolvedBatch, $user, $attendanceDate, $actorId, $notes) {
            $entry = ManualAttendanceEntry::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'attendance_date' => $attendanceDate->toDateString(),
                ],
                [
                    'batch_id' => $resolvedBatch->id,
                    'is_present' => false,
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'login_hours' => 0,
                    'overtime_hours' => 0,
                    'status' => 'approved',
                    'notes' => $notes,
                    'captured_by' => $actorId,
                    'captured_at' => Carbon::now(),
                ]
            );

            ManualAttendanceAuditLog::create([
                'entry_id' => $entry->id,
                'action' => 'marked_absent',
                'performed_by' => $actorId,
                'payload' => [
                    'is_present' => false,
                    'notes' => $notes,
                ],
            ]);

            $this->syncPayrollAttendance($entry);
            $this->syncDailySnapshot($user, $attendanceDate, null, null, 'absent');

            return $entry->fresh();
        };

        if ($wrapInTransaction) {
            return $this->database->connection('sqlsrv')->transaction($callback);
        }

        return $callback();
    }

    public function formatManualStaffRecord(object $record): array
    {
        $fullName = trim(sprintf('%s %s', $record->first_name ?? '', $record->last_name ?? ''));
        if ($fullName === '') {
            $fullName = $record->username ?? $record->email ?? 'Staff Member';
        }

        return [
            'id' => (int) $record->id,
            'name' => $fullName,
            'department_id' => isset($record->department_id) ? (int) $record->department_id : null,
            'department_name' => $record->department_name ?? null,
            'work_station' => $record->work_station ?? null,
            'staff_type_category' => $record->staff_type_category ?? null,
            'shift_code' => $record->shift_code ?? null,
            'position' => $record->user_level ?? null,
            'shift_hours' => (float) ($record->man_hours_per_day ?? 0),
            'passport_photo_path' => $record->passport_photo_path ?? null,
            'passport_photo_url' => $this->resolvePhotoUrlFromParts($record->passport_photo_path ?? null, $record->profile ?? null),
            'profile' => $record->profile ?? null,
            'username' => $record->username ?? null,
            'email' => $record->email ?? null,
            'entry' => $this->formatManualEntryFromRow($record),
        ];
    }

    public function formatEntryForResponse(ManualAttendanceEntry $entry): array
    {
        return $this->buildEntryPayload([
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'attendance_date' => $entry->attendance_date,
            'is_present' => $entry->is_present,
            'check_in_time' => $entry->check_in_time,
            'check_out_time' => $entry->check_out_time,
            'shift_code' => $entry->shift_code,
            'login_hours' => $entry->login_hours,
            'overtime_hours' => $entry->overtime_hours,
            'notes' => $entry->notes,
            'status' => $entry->status,
            'metadata' => $entry->metadata,
        ]);
    }

    public function formatManualEntryFromRow(object $record): ?array
    {
        if (empty($record->entry_id)) {
            return null;
        }

        return $this->buildEntryPayload([
            'id' => $record->entry_id,
            'user_id' => $record->id,
            'attendance_date' => $record->entry_attendance_date ?? null,
            'is_present' => $record->entry_is_present ?? null,
            'check_in_time' => $record->entry_check_in_time ?? null,
            'check_out_time' => $record->entry_check_out_time ?? null,
            'shift_code' => $record->entry_shift_code ?? null,
            'login_hours' => $record->entry_login_hours ?? null,
            'overtime_hours' => $record->entry_overtime_hours ?? null,
            'notes' => $record->entry_notes ?? null,
            'status' => $record->entry_status ?? null,
            'metadata' => $record->entry_metadata ?? null,
        ]);
    }

    protected function buildEntryPayload(array $attributes): array
    {
        $attendanceDate = $this->normalizeCarbon($attributes['attendance_date'] ?? null);
        if ($attendanceDate instanceof Carbon) {
            $attendanceDate = $attendanceDate->copy()->startOfDay();
        }

        $checkIn = $this->normalizeCarbon($attributes['check_in_time'] ?? null);
        $checkOut = $this->normalizeCarbon($attributes['check_out_time'] ?? null);

        return [
            'id' => isset($attributes['id']) ? (int) $attributes['id'] : null,
            'user_id' => isset($attributes['user_id']) ? (int) $attributes['user_id'] : null,
            'attendance_date' => $attendanceDate?->toDateString(),
            'is_present' => isset($attributes['is_present']) ? (bool) $attributes['is_present'] : false,
            'check_in_time' => $checkIn?->toDateTimeString(),
            'check_out_time' => $checkOut?->toDateTimeString(),
            'shift_type' => $attributes['shift_code'] ?? null,
            'hours_worked' => (float) ($attributes['login_hours'] ?? 0),
            'overtime_hours' => (float) ($attributes['overtime_hours'] ?? 0),
            'notes' => $attributes['notes'] ?? null,
            'status' => $attributes['status'] ?? null,
            'locked_until' => $attendanceDate ? $attendanceDate->copy()->endOfDay()->toIso8601String() : null,
            'metadata' => $this->decodeMetadata($attributes['metadata'] ?? null),
        ];
    }

    protected function normalizeCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function decodeMetadata(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    protected function resolveShiftHours(User $user): float
    {
        $hours = (float) ($user->man_hours_per_day ?? 0);
        if ($hours <= 0) {
            return 8.0;
        }

        return round($hours, 2);
    }

    protected function calculateWorkedHours(Carbon $checkIn, Carbon $checkOut): float
    {
        $minutes = max(0, $checkIn->diffInMinutes($checkOut));
        return round($minutes / 60, 2);
    }

    protected function syncDailySnapshot(User $user, Carbon $attendanceDate, ?Carbon $checkIn, ?Carbon $checkOut, string $status): void
    {
        $shiftCode = $user->shift_code ?: 'manual';

        AttendanceDailySnapshot::updateOrCreate(
            [
                'user_id' => $user->id,
                'attendance_date' => $attendanceDate->toDateString(),
                'shift_code' => $shiftCode,
            ],
            [
                'status' => $status,
                'login_time' => $checkIn,
                'logout_time' => $checkOut,
                'late_login' => false,
                'reason' => null,
                'metadata' => [
                    'source' => 'manual_attendance',
                    'captured_at' => Carbon::now(config('app.timezone'))->toDateTimeString(),
                ],
            ]
        );
    }

    protected function clearManualSnapshot(User $user, Carbon $attendanceDate): void
    {
        $shiftCode = $user->shift_code ?: 'manual';

        AttendanceDailySnapshot::query()
            ->where('user_id', $user->id)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->where('shift_code', $shiftCode)
            ->where(function ($query) {
                $query->whereNull('metadata')
                    ->orWhereRaw("JSON_VALUE(metadata, '$.source') = ?", ['manual_attendance']);
            })
            ->delete();
    }

    protected function syncPayrollAttendance(ManualAttendanceEntry $entry): void
    {
        $attendanceDate = $entry->attendance_date instanceof Carbon
            ? $entry->attendance_date->copy()
            : Carbon::parse($entry->attendance_date);

        $this->rebuildPayrollAttendanceForUser($entry->user_id, $attendanceDate);
    }

    protected function rebuildPayrollAttendanceForUser(int $userId, Carbon $attendanceDate): void
    {
        $period = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $attendanceDate->toDateString())
            ->whereDate('end_date', '>=', $attendanceDate->toDateString())
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$period) {
            return;
        }

        $user = User::query()->select('id', 'department_id', 'staff_type_category')->find($userId);
        if (!$user || Str::upper($user->staff_type_category) !== 'MDC') {
            return;
        }

        $periodStart = $period->periodStart()->toDateString();
        $periodEnd = $period->periodEnd()->toDateString();

        $entries = ManualAttendanceEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->orderBy('attendance_date')
            ->get();

        if ($entries->isEmpty()) {
            PayrollAttendance::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->delete();

            return;
        }

        $summaries = $this->summarizeEntries($entries);

        $loginDays = collect($summaries)->filter(static fn (array $day) => $day['is_present'])->count();
        $hoursWorked = (float) collect($summaries)->sum(static fn (array $day) => $day['hours']);
        $overtimeHours = (float) collect($summaries)->sum(static fn (array $day) => $day['overtime_hours']);

        PayrollAttendance::updateOrCreate(
            [
                'period_id' => $period->id,
                'user_id' => $userId,
            ],
            [
                'department_id' => $user->department_id,
                'login_days' => $loginDays,
                'hours_worked' => $hoursWorked,
                'overtime_hours' => $overtimeHours,
                'session_breakdown' => $this->formatManualSessionBreakdown($summaries),
                'source_reference' => 'manual_attendance',
            ]
        );
    }

    public function syncManualEntriesToPayroll(PayrollPeriod $period): void
    {
        $periodStart = $period->periodStart()->toDateString();
        $periodEnd = $period->periodEnd()->toDateString();

        $entriesByUser = ManualAttendanceEntry::query()
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->orderBy('user_id')
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('user_id');

        foreach ($entriesByUser as $userId => $entries) {
            if ($entries->isEmpty()) {
                continue;
            }

            $user = $entries->first()->user()->select('id', 'department_id', 'staff_type_category')->first()
                ?? User::query()->select('id', 'department_id', 'staff_type_category')->find($userId);

            if (!$user || Str::upper($user->staff_type_category) !== 'MDC') {
                continue;
            }

            $summaries = $this->summarizeEntries($entries);
            if (empty($summaries)) {
                continue;
            }

            $loginDays = collect($summaries)->filter(static fn (array $day) => $day['is_present'])->count();
            $hoursWorked = (float) collect($summaries)->sum(static fn (array $day) => $day['hours']);
            $overtimeHours = (float) collect($summaries)->sum(static fn (array $day) => $day['overtime_hours']);

            PayrollAttendance::updateOrCreate(
                [
                    'period_id' => $period->id,
                    'user_id' => $userId,
                ],
                [
                    'department_id' => $user->department_id,
                    'login_days' => $loginDays,
                    'hours_worked' => $hoursWorked,
                    'overtime_hours' => $overtimeHours,
                    'session_breakdown' => $this->formatManualSessionBreakdown($summaries),
                    'source_reference' => 'manual_attendance',
                ]
            );
        }
    }

    protected function summarizeEntries(Collection $entries): array
    {
        return $entries
            ->sortBy(static fn (ManualAttendanceEntry $record) => Carbon::parse($record->attendance_date)->timestamp)
            ->groupBy(static fn (ManualAttendanceEntry $record) => Carbon::parse($record->attendance_date)->toDateString())
            ->map(function (Collection $records, string $date) {
                $orderedRecords = $records->sortBy(function (ManualAttendanceEntry $record) {
                    $time = $record->check_in_time instanceof Carbon
                        ? $record->check_in_time
                        : ($record->check_in_time ? Carbon::parse($record->check_in_time) : null);

                    return $time ? $time->timestamp : PHP_INT_MAX;
                });

                $checkInTimes = $orderedRecords
                    ->map(function (ManualAttendanceEntry $record) {
                        if (!$record->check_in_time) {
                            return null;
                        }

                        $checkIn = $record->check_in_time instanceof Carbon
                            ? $record->check_in_time->copy()
                            : Carbon::parse($record->check_in_time);

                        return $checkIn->format('H:i');
                    })
                    ->filter()
                    ->values()
                    ->all();

                $notes = $orderedRecords
                    ->pluck('notes')
                    ->filter()
                    ->implode('; ');

                return [
                    'date' => Carbon::parse($date)->toDateString(),
                    'is_present' => $orderedRecords->contains(fn (ManualAttendanceEntry $record) => (bool) $record->is_present),
                    'hours' => (float) $orderedRecords->sum('login_hours'),
                    'overtime_hours' => (float) $orderedRecords->sum('overtime_hours'),
                    'check_in_times' => $checkInTimes,
                    'notes' => $notes ?: null,
                    'reference_ids' => $orderedRecords->pluck('id')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function formatManualSessionBreakdown(array $summaries): array
    {
        return array_map(static function (array $day) {
            return [
                'date' => $day['date'],
                'is_present' => $day['is_present'],
                'hours' => $day['hours'],
                'overtime_hours' => $day['overtime_hours'],
                'check_in_times' => $day['check_in_times'],
                'notes' => $day['notes'],
                'source' => 'manual_attendance',
                'reference_ids' => $day['reference_ids'],
            ];
        }, $summaries);
    }

    public function transitionEntryStatus(ManualAttendanceEntry $entry, string $status, int $actorId, ?string $notes = null): ManualAttendanceEntry
    {
        $validStatuses = ['draft', 'review', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException('Invalid manual attendance status.');
        }

        return $this->database->connection('sqlsrv')->transaction(function () use ($entry, $status, $actorId, $notes) {
            $entry->status = $status;
            if (in_array($status, ['review', 'approved', 'rejected'], true)) {
                $entry->reviewed_by = $actorId;
                $entry->reviewed_at = Carbon::now();
            }
            if ($notes !== null) {
                $entry->notes = $notes;
            }
            $entry->save();

            ManualAttendanceAuditLog::create([
                'entry_id' => $entry->id,
                'action' => 'status_changed',
                'performed_by' => $actorId,
                'payload' => [
                    'status' => $status,
                    'notes' => $notes,
                ],
            ]);

            return $entry;
        });
    }

    protected function generateBatchCode(Carbon $periodStart, Carbon $periodEnd): string
    {
        return 'MAB-' . $periodStart->format('Ym') . '-' . $periodEnd->format('d') . '-' . strtoupper(Str::random(4));
    }

    protected function resolvePhotoUrl(User $user): string
    {
        return $this->resolvePhotoUrlFromParts($user->passport_photo_path, $user->profile);
    }

    protected function resolvePhotoUrlFromParts(?string $passportPhotoPath, ?string $profilePath): string
    {
        if ($passportPhotoPath) {
            return asset('storage/' . ltrim($passportPhotoPath, '/'));
        }

        if ($profilePath) {
            return asset('storage/app/public/' . ltrim($profilePath, '/'));
        }

        return asset('storage/upload/profile/avatar.png');
    }
}
