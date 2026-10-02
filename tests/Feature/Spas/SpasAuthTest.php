<?php

namespace Tests\Feature\Spas;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The real token round trip: password in, Bearer token out, token used.
 *
 * WHY SEPARATE FROM SpasSyncApiTest
 * That suite authenticates with Sanctum::actingAs(), which fabricates a token
 * in memory and never touches the database. It proves the guards work; it
 * proves nothing about whether a token can actually be issued, persisted and
 * read back. Those are different failures, and the second kind only shows up in
 * production.
 *
 * USE App\Models\PersonalAccessToken, NOT THE VENDOR CLASS.
 * The vendor model has no connection of its own, so it follows
 * config('database.default') = mysql, where this project keeps no tokens. The
 * subclass in app/Models pins it to sqlsrv, and Sanctum is configured to use
 * that one. Reaching for `Laravel\Sanctum\PersonalAccessToken` here silently
 * queries an empty table: findToken() returns null and a delete() revokes
 * nothing, which reads as "logout is broken" when the application is fine.
 *
 * Both connections are transacted anyway — tokens land on sqlsrv, but login
 * touches mysql-backed side tables and nothing should survive a test.
 */
class SpasAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['sqlsrv', 'mysql'];

    private const PASSWORD = 'ZZ-test-password-9F3k';

    /**
     * Drop the resolved guard between requests in the same test.
     *
     * In production every request is a fresh process, so a revoked token is
     * re-read from the database and refused. In a test the container survives
     * between calls and the auth guard memoises the user it already resolved —
     * so a token deleted mid-test keeps working and reads as "revocation is
     * broken". This forces the re-read that production gets for free.
     */
    private function forgetAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeSurveyor(): User
    {
        $user = new User();
        $user->first_name = 'ZZTEST';
        $user->last_name = 'Surveyor';
        $user->username = 'zztest_'.Str::random(8);
        $user->email = 'zztest_'.Str::random(8).'@example.test';
        $user->password = Hash::make(self::PASSWORD);

        if (in_array('is_active', $user->getFillable(), true) || true) {
            $user->is_active = 1;
        }

        $user->save();

        return $user;
    }

    public function test_a_real_login_issues_a_usable_token(): void
    {
        $user = $this->makeSurveyor();

        $login = $this->postJson('/api/spas/auth/login', [
            'identifier'  => $user->username,
            'password'    => self::PASSWORD,
            'device_name' => 'zz-test-handset',
        ])->assertOk()->assertJsonStructure(['token', 'token_type', 'user', 'server_time']);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        // The token must actually authenticate a subsequent request. This is the
        // step Sanctum::actingAs() can never exercise.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/spas/lookup/lgas')
            ->assertOk();
    }

    public function test_the_issued_token_carries_the_spas_ability(): void
    {
        $user = $this->makeSurveyor();

        $token = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username,
            'password'   => self::PASSWORD,
        ])->assertOk()->json('token');

        $model = PersonalAccessToken::findToken($token);

        $this->assertNotNull($model, 'The issued token could not be found in the database.');
        $this->assertTrue($model->can('spas-mobile'));
        $this->assertFalse(
            $model->can('mobile-api'),
            'A SPAS token must not open the React Native app’s endpoints.'
        );
    }

    public function test_logging_in_again_from_the_same_device_replaces_the_old_token(): void
    {
        // One token per device name keeps logout predictable and stops a
        // surveyor's old handset holding a live token after a swap.
        $user = $this->makeSurveyor();

        $credentials = [
            'identifier'  => $user->username,
            'password'    => self::PASSWORD,
            'device_name' => 'zz-test-handset',
        ];

        $first = $this->postJson('/api/spas/auth/login', $credentials)->json('token');
        $second = $this->postJson('/api/spas/auth/login', $credentials)->json('token');

        $this->assertNotSame($first, $second);

        // The old one must be dead.
        $this->withHeader('Authorization', 'Bearer '.$first)
            ->getJson('/api/spas/lookup/lgas')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$second)
            ->getJson('/api/spas/lookup/lgas')
            ->assertOk();
    }

    public function test_logging_in_from_a_second_device_does_not_kill_the_first(): void
    {
        // A surveyor with a phone and a tablet must keep both working.
        $user = $this->makeSurveyor();

        $phone = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username, 'password' => self::PASSWORD, 'device_name' => 'zz-phone',
        ])->json('token');

        $tablet = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username, 'password' => self::PASSWORD, 'device_name' => 'zz-tablet',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$phone)->getJson('/api/spas/lookup/lgas')->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$tablet)->getJson('/api/spas/lookup/lgas')->assertOk();
    }

    public function test_logout_revokes_only_the_calling_device(): void
    {
        $user = $this->makeSurveyor();

        $phone = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username, 'password' => self::PASSWORD, 'device_name' => 'zz-phone',
        ])->json('token');

        $tablet = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username, 'password' => self::PASSWORD, 'device_name' => 'zz-tablet',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$phone)
            ->postJson('/api/spas/auth/logout')->assertOk();

        $this->forgetAuth();

        $this->withHeader('Authorization', 'Bearer '.$phone)
            ->getJson('/api/spas/lookup/lgas')->assertUnauthorized();

        $this->forgetAuth();

        $this->withHeader('Authorization', 'Bearer '.$tablet)
            ->getJson('/api/spas/lookup/lgas')->assertOk();
    }

    public function test_login_with_a_wrong_password_issues_no_token(): void
    {
        $user = $this->makeSurveyor();

        $before = PersonalAccessToken::where('name', 'zz-wrong')->count();

        $this->postJson('/api/spas/auth/login', [
            'identifier'  => $user->username,
            'password'    => 'not-the-password',
            'device_name' => 'zz-wrong',
        ])->assertStatus(401);

        $this->assertSame($before, PersonalAccessToken::where('name', 'zz-wrong')->count());
    }

    public function test_a_revoked_token_stops_working_immediately(): void
    {
        // This is the control that matters: SPAS tokens do not expire, because a
        // surveyor may be offline for days and must not be locked out mid-survey.
        // Revocation is therefore the only way to cut off a lost handset.
        $user = $this->makeSurveyor();

        $token = $this->postJson('/api/spas/auth/login', [
            'identifier' => $user->username, 'password' => self::PASSWORD, 'device_name' => 'zz-lost-phone',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/spas/lookup/lgas')->assertOk();

        $revoked = PersonalAccessToken::findToken($token);
        $this->assertNotNull($revoked, 'The token should be findable before revocation.');
        $revoked->delete();

        $this->forgetAuth();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/spas/lookup/lgas')->assertUnauthorized();
    }
}
