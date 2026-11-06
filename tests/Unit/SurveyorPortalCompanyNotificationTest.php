<?php

namespace Tests\Unit;

use App\Models\SmsSetting;
use App\Models\Survey\SurveyorPortalAccount;
use App\Models\Survey\SurveyorPortalNotification;
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
}
