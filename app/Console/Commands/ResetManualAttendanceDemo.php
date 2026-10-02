<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\ManualAttendance\ManualAttendanceAuditLog;
use App\Models\ManualAttendance\ManualAttendanceBatch;
use App\Models\ManualAttendance\ManualAttendanceEntry;
use App\Models\Payroll\WorkstationPaymentStructure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetManualAttendanceDemo extends Command
{
    protected $signature = 'manual-attendance:seed-demo {--force : Skip confirmation prompts}';

    protected $description = 'Clear manual attendance tables and seed demo users without PC access.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will delete all manual attendance entries, batches, and logs. Continue?')) {
            $this->warn('Operation cancelled.');
            return self::SUCCESS;
        }

        DB::connection('sqlsrv')->transaction(function () {
            $this->info('Clearing manual attendance audit logs...');
            ManualAttendanceAuditLog::query()->delete();

            $this->info('Clearing manual attendance entries...');
            ManualAttendanceEntry::query()->delete();

            $this->info('Clearing manual attendance batches...');
            ManualAttendanceBatch::query()->delete();

            $this->resetIdentity('manual_attendance_audit_logs');
            $this->resetIdentity('manual_attendance_entries');
            $this->resetIdentity('manual_attendance_batches');
        });

        $this->seedDemoUsers();

        $this->components->info('Manual attendance tables cleared and demo users seeded.');

        return self::SUCCESS;
    }

    protected function seedDemoUsers(): void
    {
        $this->info('Removing existing demo manual attendance users...');

        $demoEmailPrefix = 'manual.attendance.demo';

        User::query()
            ->where('email', 'like', $demoEmailPrefix . '%@example.com')
            ->delete();

        $department = $this->resolveDemoDepartment();
        $structure = $this->resolveDemoStructure();

        $workStation = config('workstations.options.7.label', 'Sorting Collation and Batching (SCB)');

        $password = Hash::make('Password123!');
        $now = Carbon::now();

        $this->info('Seeding demo users (Password123!)...');

        foreach (range(1, 5) as $index) {
            $firstName = 'Manual';
            $lastName = 'Tester ' . $index;
            $email = sprintf('%s%d@example.com', $demoEmailPrefix, $index);

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'username' => Str::slug($firstName . ' ' . $lastName),
                'password' => $password,
                'type' => 'user',
                'is_active' => 1,
                'is_pc_access' => 0,
                'assign_role' => null,
                'department_id' => $department->id,
                'work_station' => $workStation,
                'user_level' => 'Technical',
                'user_type' => 'manual',
                'work_days_per_week' => 5,
                'man_hours_per_day' => 8,
                'shift_code' => 'DAY',
                'auto_deactivate' => 0,
                'staff_type_category' => 'MDC',
                'workstation_payment_structure_id' => $structure->id,
                'subscription' => null,
                'subscription_expire_date' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->line(sprintf(' - Created %s (%s) [ID: %d]', $user->name, $user->email, $user->id));
        }
    }

    protected function resetIdentity(string $table): void
    {
        $quotedTable = str_replace('"', '""', $table);
        $statement = "DBCC CHECKIDENT ('{$quotedTable}', RESEED, 0)";

        try {
            DB::connection('sqlsrv')->statement($statement);
        } catch (\Throwable $exception) {
            $this->warn("Unable to reset identity for {$table}: " . $exception->getMessage());
        }
    }

    protected function resolveDemoDepartment(): Department
    {
        $department = Department::query()->orderBy('name')->first();
        if ($department) {
            return $department;
        }

        return Department::create([
            'name' => 'Manual Attendance Operations',
            'code' => 'MANUAL_ATT',
            'description' => 'Auto-generated department for manual attendance demo users.',
            'is_active' => 1,
        ]);
    }

    protected function resolveDemoStructure(): WorkstationPaymentStructure
    {
        $structure = WorkstationPaymentStructure::query()->orderBy('id')->first();
        if ($structure) {
            return $structure;
        }

        $today = Carbon::now()->toDateString();

        return WorkstationPaymentStructure::create([
            'code' => 'manual_attendance_demo',
            'role_name' => 'Manual Attendance Demo',
            'work_days' => 5,
            'base_salary' => 0,
            'effective_from' => $today,
            'effective_to' => null,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }
}
