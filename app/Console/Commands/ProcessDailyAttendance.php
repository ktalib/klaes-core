<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailyAttendance as ProcessDailyAttendanceJob;
use App\Services\Attendance\AttendanceProcessingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessDailyAttendance extends Command
{
    protected $signature = 'attendance:process {date? : Target date (Y-m-d)} {--queue : Dispatch to the queue instead of running inline}';

    protected $description = 'Build attendance snapshots, update counters, and enforce auto-deactivation rules.';

    public function handle(AttendanceProcessingService $service): int
    {
        $dateInput = $this->argument('date');
        $date = $dateInput ? Carbon::parse($dateInput)->startOfDay() : Carbon::now()->startOfDay();

        if ($this->option('queue')) {
            ProcessDailyAttendanceJob::dispatch($date->toDateString());
            $this->info(sprintf('Attendance processing queued for %s.', $date->toDateString()));

            return self::SUCCESS;
        }

        $results = $service->process($date);

        $this->table(['Date', 'Processed', 'Present', 'Absent', 'Skipped', 'Deactivated'], [[
            $results['date'] ?? $date->toDateString(),
            $results['processed'] ?? 0,
            $results['present'] ?? 0,
            $results['absent'] ?? 0,
            $results['skipped'] ?? 0,
            $results['deactivated'] ?? 0,
        ]]);

        return self::SUCCESS;
    }
}
