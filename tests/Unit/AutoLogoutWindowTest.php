<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use App\Services\Payroll\RateService;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * When the auto-logout job considers a session finished. Nothing here reaches a
 * database: the session, the user and the shift profile are all in memory.
 */
class AutoLogoutWindowTest extends TestCase
{
    private const SETTINGS = ['track_overtime' => true, 'overtime_extension_minutes' => 120];

    private function evaluate(string $shift, int $hours, string $login, string $now): array
    {
        $timezone = config('app.timezone');

        $session = new UserActivityLog();
        $session->login_time = Carbon::parse($login, $timezone);

        $user = new User();
        $user->forceFill(['id' => 1, 'shift_code' => $shift]);

        $rates = $this->createMock(RateService::class);
        $rates->method('resolveShiftProfile')->willReturn([
            'hours' => $hours,
            'allow_overtime' => false,
            'overtime_cap' => null,
        ]);

        $method = new ReflectionMethod(ActivityLogService::class, 'evaluateAutoLogoutWindow');
        $method->setAccessible(true);

        return $method->invoke(null, $session, $user, $rates, Carbon::parse($now, $timezone), 10, self::SETTINGS);
    }

    public function test_a_sign_in_after_shift_end_is_not_ended_by_the_next_run(): void
    {
        // Morning shift ends 13:00. This is the 11-20 minute kick.
        $result = $this->evaluate('morning', 4, '2026-11-04 14:00', '2026-11-04 14:20');

        $this->assertFalse($result['should_logout']);
    }

    public function test_a_sign_in_after_shift_end_keeps_its_full_shift_allowance(): void
    {
        $this->assertFalse(
            $this->evaluate('morning', 4, '2026-11-04 14:00', '2026-11-04 18:05')['should_logout']
        );

        $result = $this->evaluate('morning', 4, '2026-11-04 14:00', '2026-11-04 18:10');

        $this->assertTrue($result['should_logout']);
        $this->assertSame('2026-11-04 18:00:00', $result['logout_time']->format('Y-m-d H:i:s'));
    }

    public function test_a_full_day_sign_in_after_five_keeps_its_eight_hours(): void
    {
        $this->assertFalse(
            $this->evaluate('full_day', 8, '2026-11-04 17:45', '2026-11-04 18:05')['should_logout']
        );
    }

    public function test_the_shift_end_time_cuts_nothing_short(): void
    {
        // Morning shift ends 13:00; this sign-in's four hours run to 14:00.
        $this->assertFalse(
            $this->evaluate('morning', 4, '2026-11-04 10:00', '2026-11-04 13:30')['should_logout']
        );

        $result = $this->evaluate('morning', 4, '2026-11-04 10:00', '2026-11-04 14:10');

        $this->assertTrue($result['should_logout']);
        $this->assertSame('2026-11-04 14:00:00', $result['logout_time']->format('Y-m-d H:i:s'));
        $this->assertSame('Attendance closed (shift_hours)', $result['description']);
    }
}
