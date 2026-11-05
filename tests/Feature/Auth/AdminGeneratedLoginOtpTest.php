<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\BulkSmsNgService;
use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "Generate OTP" on the Users screen (LoginOtpService::issueByAdmin).
 *
 * Reads real accounts; writes nothing to sqlsrv (codes live in the cache). The SMS gateway
 * is replaced by a mock that fails the test if anything tries to text a code.
 */
class AdminGeneratedLoginOtpTest extends TestCase
{
    private LoginOtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['phone_verification.login_otp.enabled' => true]);

        $gateway = \Mockery::mock(BulkSmsNgService::class);
        $gateway->shouldNotReceive('sendFirstAccepted');
        $this->app->instance(BulkSmsNgService::class, $gateway);

        $this->otp = $this->app->make(LoginOtpService::class);
    }

    private function admin(): User
    {
        return User::query()->orderBy('id')->get()->first(fn (User $u) => $u->isSuperAdmin());
    }

    /** An active, non-Super-Admin account that must type a sign-in code. */
    private function codeUser(): ?User
    {
        return User::query()->where('is_otp_verified', 1)->orderBy('id')->limit(200)->get()
            ->first(fn (User $u) => (string) $u->is_active !== '0' && !$u->isSuperAdmin() && $this->otp->requiredFor($u));
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/login', 'POST');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(10)));

        return $request;
    }

    public function test_generated_code_completes_the_pending_sign_in_once(): void
    {
        $user = $this->codeUser();
        if (!$user) {
            $this->markTestSkipped('No account currently requires a sign-in code.');
        }

        $issued = $this->otp->issueByAdmin($user, $this->admin());

        $this->assertTrue($issued['issued'], $issued['message']);
        $this->assertMatchesRegularExpression('/^\d{4,10}$/', $issued['code']);
        $this->assertSame(LoginOtpService::CHANNEL_ADMIN, $this->otp->liveCode($user)['channel']);
        // Only the HMAC is stored, never the code.
        $this->assertStringNotContainsString($issued['code'], serialize(Cache::get(LoginOtpService::CODE_CACHE . $user->id)));

        // The officer signs in afterwards: no SMS goes out and the admin's code is kept.
        $request = $this->requestWithSession();
        $begin = $this->otp->begin($request, $user, false);
        $this->assertTrue($begin['sent']);
        $this->assertStringContainsString('ICT', $begin['message']);

        $wrong = $this->otp->verify($request, $issued['code'] === '000000' ? '111111' : '000000');
        $this->assertFalse($wrong['verified']);

        $right = $this->otp->verify($request, $issued['code']);
        $this->assertTrue($right['verified']);
        $this->assertSame((int) $user->id, (int) $right['user']->id);

        // Single use.
        $this->assertNull($this->otp->liveCode($user));
    }

    public function test_admin_code_survives_past_the_sms_reuse_window(): void
    {
        $user = $this->codeUser();
        if (!$user) {
            $this->markTestSkipped('No account currently requires a sign-in code.');
        }

        $this->otp->issueByAdmin($user, $this->admin());
        $this->travel(8)->minutes();

        $begin = $this->otp->begin($this->requestWithSession(), $user, false);

        $this->assertTrue($begin['sent']);
        $this->assertSame(LoginOtpService::CHANNEL_ADMIN, $this->otp->liveCode($user)['channel']);
    }

    public function test_endpoint_returns_the_code_to_an_admin(): void
    {
        $user = $this->codeUser();
        if (!$user) {
            $this->markTestSkipped('No account currently requires a sign-in code.');
        }

        \Illuminate\Support\Facades\Mail::fake();
        $admin = $this->admin();

        // XSS only sets locale/timezone from the MySQL settings table, absent in klaes_testing.
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);

        $response = $this->actingAs($admin)
            ->withSession([LoginOtpService::PASSED_KEY => $admin->id])
            ->postJson(route('users.login-otp', $user->id));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertMatchesRegularExpression('/^\d{4,10}$/', (string) $response->json('data.code'));
        $this->assertSame(LoginOtpService::CHANNEL_ADMIN, $this->otp->liveCode($user)['channel']);

        $this->actingAs($admin)
            ->withSession([LoginOtpService::PASSED_KEY => $admin->id])
            ->postJson(route('users.login-otp', $admin->id))
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_refuses_own_account(): void
    {
        $admin = $this->admin();
        $result = $this->otp->issueByAdmin($admin, $admin);

        $this->assertFalse($result['issued']);
        $this->assertNull($result['code']);
    }

    public function test_only_a_super_admin_may_issue_for_a_super_admin(): void
    {
        $user = $this->codeUser();
        $supers = User::query()->orderBy('id')->get()->filter(fn (User $u) => $u->isSuperAdmin())->values();
        if (!$user || $supers->count() < 1) {
            $this->markTestSkipped('Need a Super Admin and an ordinary account.');
        }

        $result = $this->otp->issueByAdmin($supers->first(), $user);

        $this->assertFalse($result['issued']);
        $this->assertStringContainsString('Super Admin', $result['message']);
    }

    public function test_explains_when_the_account_needs_no_code(): void
    {
        $user = User::query()->where('is_otp_verified', 0)->orderBy('id')->limit(50)->get()
            ->first(fn (User $u) => (string) $u->is_active !== '0' && !$u->isSuperAdmin());
        if (!$user) {
            $this->markTestSkipped('No unconfirmed account.');
        }

        $result = $this->otp->issueByAdmin($user, $this->admin());

        $this->assertFalse($result['issued']);
        $this->assertStringContainsString('no sign-in code is asked for', $result['message']);
        $this->assertNull($this->otp->liveCode($user));
    }
}
