<?php

namespace App\Jobs;

use App\Services\Attendance\AttendanceProcessingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDailyAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $date)
    {
    }

    public function handle(AttendanceProcessingService $service): void
    {
        $results = $service->process(Carbon::parse($this->date));

        Log::info('Daily attendance processing complete.', $results);
    }
}
