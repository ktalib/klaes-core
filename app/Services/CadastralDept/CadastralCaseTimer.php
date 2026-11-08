<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\Cadastral\CadastralSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CadastralCaseTimer
{
    public function evaluateAll(): int
    {
        if (!Schema::connection('sqlsrv')->hasTable('cadastral_cases')) {
            return 0;
        }

        $settings = new CadastralSettings();
        $updated = 0;

        CadastralCase::query()
            ->where('status', CadastralCase::STATUS_IN_PROGRESS)
            ->whereNotNull('received_at')
            ->orderBy('received_at')
            ->chunkById(100, function ($cases) use (&$updated, $settings) {
                foreach ($cases as $case) {
                    if ($this->evaluate($case, $settings)) {
                        $updated++;
                    }
                }
            }, 'id');

        return $updated;
    }

    public function evaluate(CadastralCase $case, ?CadastralSettings $settings = null): bool
    {
        $settings ??= new CadastralSettings();

        if (!$case->received_at) {
            return false;
        }

        $case->sla_deadline ??= $this->computeSlaDeadline($case->received_at, $settings);

        if ($this->pausedByException($case)) {
            if ($case->timer_status !== 'on_time') {
                $case->timer_status = 'on_time';
                $case->saveQuietly();
            }

            return false;
        }

        $currentStatus = $case->timer_status ?? 'on_time';
        $now = now();
        $deadline = $case->sla_deadline ? Carbon::parse($case->sla_deadline) : $this->computeSlaDeadline($case->received_at, $settings);
        $totalHours = $this->hoursBetween($case->received_at, $deadline, $settings->workingDaysOnly());
        $elapsedHours = $this->hoursBetween($case->received_at, $now, $settings->workingDaysOnly());
        $reminderHours = $totalHours * ($settings->reminderPercent() / 100);
        $warningHours = $totalHours * ($settings->warningPercent() / 100);
        $penaltyDelayHours = max(0, $settings->penaltyDelayHours());

        $nextStatus = 'on_time';

        if ($now->greaterThanOrEqualTo($deadline)) {
            $nextStatus = 'default';
            if ($case->defaulted_at === null) {
                $case->defaulted_at = $now;
            }

            if ($settings->penaltyEnabled() && $now->greaterThanOrEqualTo($deadline->copy()->addHours($penaltyDelayHours))) {
                $nextStatus = 'penalty';
                if ($case->penalty_applied_at === null) {
                    $case->penalty_applied_at = $now;
                }
            }
        } elseif ($elapsedHours >= $warningHours) {
            $nextStatus = 'warning';
        } elseif ($elapsedHours >= $reminderHours) {
            $nextStatus = 'reminder';
        }

        if ($nextStatus !== $currentStatus) {
            $case->timer_status = $nextStatus;
            $case->saveQuietly();
            $this->logStatusChange($case, $currentStatus, $nextStatus, $settings);

            return true;
        }

        return false;
    }

    protected function pausedByException(CadastralCase $case): bool
    {
        return $case->exception_until && now()->lessThan(Carbon::parse($case->exception_until));
    }

    protected function computeSlaDeadline(Carbon $receivedAt, CadastralSettings $settings): Carbon
    {
        $hours = max(1, (int) $settings->slaHoursPerOffice());

        if (!$settings->workingDaysOnly()) {
            return $receivedAt->copy()->addHours($hours);
        }

        $deadline = $receivedAt->copy();
        $remaining = $hours;

        while ($remaining > 0) {
            if ($deadline->dayOfWeek >= Carbon::SATURDAY || $deadline->dayOfWeek <= Carbon::SUNDAY) {
                $deadline->addDay()->setTime(8, 0, 0);
                continue;
            }

            $workStart = $deadline->copy()->setTime(8, 0, 0);
            $workEnd = $deadline->copy()->setTime(17, 0, 0);

            if ($deadline->lt($workStart)) {
                $deadline = $workStart;
            }

            if ($deadline->gte($workEnd)) {
                $deadline = $deadline->copy()->addDay()->setTime(8, 0, 0);
                continue;
            }

            $availableHours = max(0, $workEnd->diffInHours($deadline, false));
            $minutesToAdd = min($remaining, $availableHours) * 60;
            $deadline = $deadline->copy()->addMinutes((int) $minutesToAdd);
            $remaining -= min($remaining, $availableHours);

            if ($remaining <= 0) {
                break;
            }

            $deadline = $deadline->copy()->setTime(8, 0, 0)->addDay();
            while ($deadline->dayOfWeek >= Carbon::SATURDAY || $deadline->dayOfWeek <= Carbon::SUNDAY) {
                $deadline->addDay();
            }
        }

        return $deadline;
    }

    protected function hoursBetween(Carbon $start, Carbon $end, bool $workingDaysOnly): float
    {
        if ($workingDaysOnly) {
            $hours = 0.0;
            $cursor = $start->copy()->startOfMinute();
            $stop = $end->copy()->startOfMinute();

            while ($cursor->lt($stop)) {
                if (!$cursor->isWeekend()) {
                    $windowStart = $cursor->copy()->setTime(8, 0, 0);
                    $windowEnd = $cursor->copy()->setTime(17, 0, 0);

                    if ($cursor->lt($windowStart)) {
                        $cursor = $windowStart;
                    }

                    if ($cursor->lt($windowEnd) && $cursor->lt($stop)) {
                        $hours += min($stop->diffInMinutes($cursor, false), $windowEnd->diffInMinutes($cursor, false)) / 60;
                    }
                }

                $cursor = $cursor->copy()->addDay()->setTime(8, 0, 0);
            }

            return max(0.0, $hours);
        }

        return max(0.0, $start->diffInMinutes($end, false) / 60.0);
    }

    protected function logStatusChange(CadastralCase $case, string $previous, string $next, CadastralSettings $settings): void
    {
        $message = sprintf(
            'Cadastral timer: %s -> %s for case %s (office %s, deadline %s).',
            $previous,
            $next,
            $case->case_ref ?: $case->file_number,
            $case->current_desk,
            $case->sla_deadline ? Carbon::parse($case->sla_deadline)->toDateTimeString() : 'n/a'
        );

        Log::info($message, [
            'cadastral_case_id' => $case->id,
            'from_status' => $previous,
            'to_status' => $next,
            'penalty_enabled' => $settings->penaltyEnabled(),
        ]);
    }
}
