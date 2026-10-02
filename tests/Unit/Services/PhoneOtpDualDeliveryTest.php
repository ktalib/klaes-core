<?php

namespace Tests\Unit\Services;

use App\Mail\AccountVerificationCode;
use App\Models\User;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PhoneOtpDualDeliveryTest extends TestCase
{
    private function service(bool $smsSent = true): PhoneOtpService
    {
        config(['phone_verification.channels.email' => true, 'phone_verification.channels.sms' => true]);
        $gateway = Mockery::mock(BulkSmsNgService::class);
        $gateway->shouldReceive('sendFirstAccepted')->andReturnUsing(function ($phone, $messages) use ($smsSent) {
            $this->assertSame('2348031234567', $phone);
            $this->assertStringContainsString((string) $this->user->otp, implode(' ', $messages));
            return $smsSent ? 'accepted' : null;
        });
        $gateway->shouldReceive('lastStatusCode')->andReturn($smsSent ? '100' : '604');
        $gateway->shouldReceive('lastFailureReason')->andReturn('No credit');
        $service = Mockery::mock(PhoneOtpService::class, [$gateway])->makePartial();
        $service->shouldReceive('columnsReady', 'channelColumnReady')->andReturn(true);
        $service->shouldReceive('emailBelongsToAnotherAccount')->andReturn(false);
        return $service;
    }

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->andThrow(new \RuntimeException('Database disabled in OTP delivery tests'));
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->forceFill(['id' => 123, 'first_name' => 'Musa']);
        $this->user->shouldReceive('save')->andReturn(true);
    }

    public function test_both_deliveries_share_the_saved_code_and_resend_is_throttled(): void
    {
        $service = $this->service();
        $result = $service->sendToBoth($this->user, 'musa@gmail.com', '08031234567');
        $this->assertTrue($result['sent']);
        $this->assertSame('both', $this->user->otp_channel);
        Mail::assertSent(AccountVerificationCode::class, function ($mail) {
            return $mail->code === $this->user->otp && $mail->hasTo('musa@gmail.com');
        });
        $this->assertFalse($service->sendToBoth($this->user, 'musa@gmail.com', '08031234567')['sent']);
        Mail::assertSent(AccountVerificationCode::class, 1);
        $this->assertTrue($service->verifyCode($this->user, $this->user->otp)['verified']);
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_email_code_remains_usable_when_sms_fails(): void
    {
        $service = $this->service(false);
        $result = $service->sendToBoth($this->user, 'musa@gmail.com', '08031234567');
        $this->assertTrue($result['sent']);
        $this->assertStringContainsString('SMS delivery failed', $result['message']);
        $this->assertTrue($service->verifyCode($this->user, $this->user->otp)['verified']);
    }

    public function test_sms_is_attempted_when_email_throws(): void
    {
        $service = $this->service();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail server unavailable'));
        $result = $service->sendToBoth($this->user, 'musa@gmail.com', '08031234567');
        $this->assertTrue($result['sent']);
        $this->assertStringContainsString('email delivery failed', $result['message']);
        $this->assertTrue($service->verifyCode($this->user, $this->user->otp)['verified']);
    }

    public function test_both_delivery_failures_are_reported(): void
    {
        $service = $this->service(false);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail server unavailable'));
        $result = $service->sendToBoth($this->user, 'musa@gmail.com', '08031234567');
        $this->assertFalse($result['sent']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function test_invalid_phone_sends_nothing(): void
    {
        $service = $this->service();
        $this->assertFalse($service->sendToBoth($this->user, 'musa@gmail.com', 'invalid')['sent']);
        Mail::assertNothingSent();
        $this->assertNull($this->user->otp);
    }
}
