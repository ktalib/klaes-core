<?php

namespace Tests\Unit;

use App\Models\Survey\SurveyorPortalAccount as Account;
use PHPUnit\Framework\TestCase;

/** A Private registration belongs to the company; a Public one to the surveyor. */
class SurveyorPortalAccountHolderTest extends TestCase
{
    private function account(string $type, ?string $company): Account
    {
        return (new Account())->forceFill(['surveyor_type' => $type, 'name' => 'Surv. Sani Dahiru', 'company_name' => $company]);
    }

    public function test_a_private_account_is_held_by_its_company(): void
    {
        $this->assertSame('SD Survey Ltd', $this->account('Private', 'SD Survey Ltd')->holder_name);
    }

    public function test_a_public_account_is_held_by_the_surveyor(): void
    {
        $this->assertSame('Surv. Sani Dahiru', $this->account('Government', null)->holder_name);
    }

    public function test_one_company_is_recognised_however_it_is_written(): void
    {
        $one = ['company_id' => 1, 'company_name' => 'Peak Profile', 'company_rc_no' => 'RC 123456'];

        $this->assertSame('company', Account::sameCompanyAs($one, ['company_id' => 1, 'company_name' => 'x', 'company_rc_no' => null]));
        $this->assertSame('company', Account::sameCompanyAs($one, ['company_id' => null, 'company_name' => 'PEAK-PROFILE.', 'company_rc_no' => null]));
        $this->assertSame('rc', Account::sameCompanyAs($one, ['company_id' => null, 'company_name' => 'Other', 'company_rc_no' => 'rc-123456']));
        $this->assertSame('rc', Account::sameCompanyAs($one, ['company_id' => null, 'company_name' => 'Other', 'company_rc_no' => '123456']));
        $this->assertNull(Account::sameCompanyAs($one, ['company_id' => 2, 'company_name' => 'Other', 'company_rc_no' => 'RC 7']));
    }

    public function test_a_private_account_with_no_company_falls_back_to_the_surveyor(): void
    {
        $this->assertSame('Surv. Sani Dahiru', $this->account('Private', null)->holder_name);
    }
}
