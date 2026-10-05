<?php

namespace Tests\Unit\Services;

use App\Mail\AccountVerificationCode;
use App\Models\Phs\PhsMember;
use App\Services\BulkSmsNgService;
use App\Services\Phs\PhsLoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PhsEmailLoginOtpTest extends TestCase
{
    private PhsMember $member;
    private Request $request;
    private PhsLoginOtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('No database access in OTP tests'));
        $gateway = Mockery::mock(BulkSmsNgService::class);
        $gateway->shouldNotReceive('sendFirstAccepted');
        $this->app->instance(BulkSmsNgService::class, $gateway);
        config([
            'cache.default' => 'array',
            'phone_verification.placeholder_email_domains' => [],
            'phone_verification.phs_login_otp.enabled' => true,
            'phone_verification.phs_login_otp.channel' => 'sms',
        ]);

        $this->member = new PhsMember();
        $this->member->forceFill([
            'id' => 987654,
            'name' => 'Portal Member',
            'email' => 'member@example.org',
            'phone' => '08031234567',
            'phone_verified_at' => now(),
        ]);
        $this->request = Request::create('/phs/login/verify/resend', 'POST', ['channel' => 'sms']);
        $this->request->setLaravelSession(new Store('phs-test', new ArraySessionHandler(120)));
        $this->otp = Mockery::mock(PhsLoginOtpService::class, [app(\App\Services\PhoneOtpService::class)])->makePartial();
        $this->otp->shouldReceive('pendingMember')->andReturn($this->member);
    }

    public function test_email_code_is_sent_and_verified_even_with_legacy_sms_configuration(): void
    {
        $this->assertTrue($this->otp->requiredFor($this->member));
        $this->assertTrue($this->otp->begin($this->request, $this->member, true)['sent']);
        $code = null;
        Mail::assertSent(AccountVerificationCode::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return $mail->hasTo('member@example.org');
        });
        $this->assertFalse($this->otp->send($this->request)['sent']);
        $this->assertTrue($this->otp->verify($this->request, $code)['verified']);
        $this->assertFalse($this->otp->verify($this->request, $code)['verified']);
        Mail::assertSent(AccountVerificationCode::class, 1);
    }

    public function test_old_sms_code_is_replaced_by_email(): void
    {
        Cache::put(PhsLoginOtpService::CODE_CACHE . $this->member->id, [
            'hash' => hash_hmac('sha256', 'phs|' . $this->member->id . '|123456', (string) config('app.key')),
            'sent_at' => now()->timestamp,
            'channel' => 'sms',
            'attempts' => 0,
        ], 600);
        $this->assertNull($this->otp->liveCode($this->member));
        $this->assertFalse($this->otp->verify($this->request, '123456')['verified']);
        $this->assertTrue($this->otp->begin($this->request, $this->member, false)['sent']);
        Mail::assertSent(AccountVerificationCode::class, 1);
        $this->assertSame('email', $this->otp->lastSend($this->member)['channel']);
    }

    public function test_phone_is_not_needed_for_email_otp(): void
    {
        $this->member->phone = null;
        $this->member->phone_verified_at = null;
        $this->assertTrue($this->otp->requiredFor($this->member));
        $this->assertTrue($this->otp->begin($this->request, $this->member, false)['sent']);
        Mail::assertSent(AccountVerificationCode::class, 1);
    }

    public function test_portal_routes_keep_email_gate_and_remove_phone_gate_and_sms_endpoints(): void
    {
        $routes = app('router')->getRoutes();
        $dashboard = $routes->getByName('phs.dashboard');
        $this->assertContains('auth:phs', $dashboard->gatherMiddleware());
        $this->assertContains('phs.otp', $dashboard->gatherMiddleware());
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'phs/')) {
                $this->assertNotContains('phs.phone', $route->gatherMiddleware());
                $this->assertStringNotContainsString('PhsPhoneSetupController', $route->getActionName());
            }
        }
        $this->assertNull($routes->getByName('phs.phone.setup.send'));
        $this->assertSame('/phs/dashboard', $routes->getByName('phs.phone.setup')->defaults['destination']);
    }

    public function test_code_screen_only_offers_email(): void
    {
        $html = view('phs.auth.login-otp', [
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'maskedEmail' => 'mem***@example.org',
            'organization' => 'Test Institution',
            'retryAfter' => 0,
            'ttlMinutes' => 10,
            'codeLength' => 6,
            'expiresIn' => 600,
        ])->render();
        $this->assertStringContainsString('We emailed', $html);
        $this->assertStringContainsString('Send another email', $html);
        $this->assertStringNotContainsString('Text it to me', $html);
        $this->assertStringNotContainsString('name="channel"', $html);
    }
}
