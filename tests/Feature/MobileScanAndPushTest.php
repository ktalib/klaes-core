<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileTrackerController;
use App\Models\Notification;
use App\Models\User;
use App\Services\Push\FcmPushService;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * KLAES FTM app: the mobile scan decodes every KLAES QR through the File
 * Movement (Department) resolver, and push notifications mirror only what the
 * app's bell shows. Read-only paths only — sqlsrv writes throw in tests; the
 * logging and FCM paths were exercised through the rollback harness.
 */
class MobileScanAndPushTest extends TestCase
{
    // Straight to the controller: the route's XSS middleware reads mysql settings, absent from klaes_testing.
    private function scan(string $qr): TestResponse
    {
        $request = Request::create('/api/mobile/tracker/scan-and-log', 'POST', ['qr_code' => $qr, 'office_code' => 'MOB', 'office_name' => 'Mobile Scan', 'skip_sms' => true]);
        $user = User::query()->first();
        $request->setUserResolver(fn () => $user);

        return TestResponse::fromBaseResponse(app(MobileTrackerController::class)->scanAndLog($request));
    }

    public function test_unresolvable_klaes_qr_explains_itself(): void
    {
        $this->scan('OP Ack | Someone | Plot: 1 | Plan: x | 2026-10-01')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'OSS OP acknowledgement slips carry no file reference. Log this file manually.']);
    }

    public function test_fixed_string_qr_is_not_treated_as_a_file(): void
    {
        $this->scan('KANOLANDSURVEY12')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_unknown_payload_gets_the_resolver_message(): void
    {
        $this->scan('NOT-A-KLAES-CODE-' . bin2hex(random_bytes(4)))
            ->assertStatus(404)
            ->assertJsonPath('message', 'No KLAES file matches this QR. Log the file manually.');
    }

    public function test_push_scope_matches_the_bell(): void
    {
        $make = fn (array $a) => (new Notification())->forceFill($a);

        $this->assertTrue(MobileNotificationController::belongsOnMobile($make(['module' => 'file_tracking', 'type' => 'x'])));
        $this->assertTrue(MobileNotificationController::belongsOnMobile($make(['module' => 'system', 'type' => 'file_tracking_received'])));
        $this->assertTrue(MobileNotificationController::belongsOnMobile($make(['module' => 'system', 'type' => 'file_search_request'])));
        $this->assertFalse(MobileNotificationController::belongsOnMobile($make(['module' => 'laas', 'type' => 'laas_stage'])));
    }

    public function test_push_is_off_without_credentials(): void
    {
        config(['push.credentials' => storage_path('app/firebase/does-not-exist.json')]);

        $this->assertFalse((new FcmPushService())->enabled());
        $this->assertSame(0, (new FcmPushService())->sendToUser(1, 't', 'b'));
    }
}
