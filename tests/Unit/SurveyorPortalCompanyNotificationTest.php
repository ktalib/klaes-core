<?php

namespace Tests\Unit;

use App\Models\SmsSetting;
use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use App\Models\Survey\SurveyorPortalNotification;
use App\Services\Sms\KlaesSmsDispatcher;
use App\Services\Survey\SurveyorPortalNotifier;
use App\Services\SurveyorPortalSettings;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SurveyorPortalCompanyNotificationTest extends TestCase
{
    public function test_company_approval_sms_has_a_control_centre_message(): void
    {
        $key = SmsSetting::KEY_SURVEY_COMPANY_APPROVED;
        $message = SmsSetting::definition($key);

        $this->assertTrue(SmsSetting::isKnownKey($key));
        $this->assertSame('Surveyor Portal', $message['group']);
        $this->assertSame(['Company', 'Prefix'], $message['tokens']);
        $this->assertStringContainsString('[Company]', $message['template']);
        $this->assertStringContainsString('[Prefix]', $message['template']);
    }

    public function test_portal_notice_scope_includes_only_the_accounts_own_company(): void
    {
        $account = new SurveyorPortalAccount();
        $account->id = 12;
        $account->company_id = 34;

        $query = SurveyorPortalNotification::visibleTo($account);

        $this->assertSame([12, 34], $query->getBindings());
        $this->assertStringContainsString('surveyor_portal_account_id', $query->toSql());
        $this->assertStringContainsString('company_id', $query->toSql());
    }

    public function test_beacon_request_sms_uses_one_configured_phone_and_keeps_sg_bell_notices(): void
    {
        $sgIds = [12, 13];
        $bell = Mockery::mock(UserNotificationService::class);
        $bell->shouldReceive('create')
            ->twice()
            ->with(
                Mockery::on(fn (int $id) => in_array($id, $sgIds, true)),
                'survey_beacon_request',
                'Beacon request BRQ-TEST',
                Mockery::type('string'),
                ['open_path' => parse_url(route('survey-module.beacon-requests.index'), PHP_URL_PATH)],
                ['module' => 'survey']
            );

        $sms = Mockery::mock(KlaesSmsDispatcher::class);
        $sms->shouldReceive('queue')
            ->once()
            ->with(
                SmsSetting::KEY_SURVEY_BEACON_REQUEST,
                '+234 803 073 3666',
                ['Ref' => 'BRQ-TEST', 'Name' => 'Test Surveyor', 'Qty' => '2'],
                ['dedupe_key' => 'brq-sg:123', 'phone_confirmed' => true]
            );

        $settings = Mockery::mock(SurveyorPortalSettings::class);
        $settings->shouldReceive('values')
            ->once()
            ->andReturn(['beacon_request_sms_phone' => '+234 803 073 3666']);

        $connection = Mockery::mock();
        $connection->shouldReceive('afterCommit')
            ->once()
            ->andReturnUsing(fn (\Closure $work) => $work());
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $notifier = new class($bell, $sms, $settings) extends SurveyorPortalNotifier {
            public function surveyorGenerals()
            {
                return collect([(object) ['id' => 12], (object) ['id' => 13]]);
            }
        };

        $request = new SurveyBeaconRequest();
        $request->forceFill([
            'id' => 123,
            'request_no' => 'BRQ-TEST',
            'surveyor_name' => 'Test Surveyor',
            'quantity' => 2,
            'layout_type' => 'Government',
            'source' => SurveyBeaconRequest::SOURCE_PORTAL,
        ]);
        $request->setRelation('account', null);

        $notifier->requestSubmitted($request);
    }

    public function test_beacon_request_sms_has_a_configured_default_recipient(): void
    {
        $this->assertSame(
            '+234 803 073 3666',
            SurveyorPortalSettings::DEFAULTS['beacon_request_sms_phone']
        );
    }
}
