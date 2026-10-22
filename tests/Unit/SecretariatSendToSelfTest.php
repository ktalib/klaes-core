<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FileTracking\SecretariatFileLogService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * File Movement refuses a send whose receiving officer is the sender, picked
 * from the list or typed under "Other". The check runs before any write.
 */
class SecretariatSendToSelfTest extends TestCase
{
    private function sender(): User
    {
        $user = User::where('is_active', 1)->whereNotNull('first_name')->first();
        if (!$user) {
            $this->markTestSkipped('No active user to act as sender.');
        }

        return $user;
    }

    /** Two active offices: [from, to]. */
    private function offices(): array
    {
        $codes = DB::connection('sqlsrv')->table('offices')->where('is_active', 1)->limit(2)->pluck('office_code')->all();
        if (count($codes) < 2) {
            $this->markTestSkipped('Needs two active offices.');
        }

        return $codes;
    }

    private function assertRefused(callable $send): void
    {
        try {
            $send();
            $this->fail('Sending to yourself was allowed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot send a file to yourself', $e->getMessage());
        }
    }

    public function test_picking_yourself_from_the_list_is_refused(): void
    {
        $user = $this->sender();
        [$from, $to] = $this->offices();

        $this->assertRefused(fn () => app(SecretariatFileLogService::class)
            ->forward($from, 0, $to, null, $user->id, null, $user));
    }

    public function test_typing_your_own_name_under_other_is_refused(): void
    {
        $user = $this->sender();
        [$from, $to] = $this->offices();
        $typed = '  ' . strtoupper(trim($user->first_name . '  ' . $user->last_name)) . ' ';

        $this->assertRefused(fn () => app(SecretariatFileLogService::class)
            ->forward($from, 0, $to, null, null, null, $user, null, $typed));
    }

    public function test_another_officer_passes_the_check(): void
    {
        $user = $this->sender();
        [$from, $to] = $this->offices();

        // Tracker 0 does not exist, so a send that passes the check stops there.
        $this->expectExceptionMessage('File tracker not found.');
        app(SecretariatFileLogService::class)->forward($from, 0, $to, null, null, null, $user, null, 'Someone Else Entirely');
    }
}
