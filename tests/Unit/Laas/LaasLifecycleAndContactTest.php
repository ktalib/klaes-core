<?php

namespace Tests\Unit\Laas;

use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Services\BulkSmsNgService;
use App\Services\Laas\LaasLineageService;
use PHPUnit\Framework\TestCase;

/**
 * Contact flags, the SYS- phone, and the file lifecycle — without the database.
 * See docs/plans/LAAS_PORTAL_REBUILD_PLAN.md §3.5 and §4.
 */
class LaasLifecycleAndContactTest extends TestCase
{
    public function test_the_system_phone_can_never_be_texted(): void
    {
        $phone = LaasApplicant::systemPhoneFor(3141);

        $this->assertSame('SYS-00003141', $phone);
        $this->assertTrue(LaasApplicant::isSystemPhone($phone));
        // Both normalisers must reject it, or the gateway would be handed it.
        $this->assertNull(LaasApplicant::normalizePhone($phone));
        $this->assertNull(BulkSmsNgService::normalizeNumber($phone));

        // Even the largest id keeps it undialable (8 digits; 10-11 would be texted).
        $this->assertNull(BulkSmsNgService::normalizeNumber(LaasApplicant::systemPhoneFor(99999999)));
    }

    public function test_a_digits_only_placeholder_would_have_been_texted(): void
    {
        // Why the SYS- prefix exists: any 11 digits starting with 0 go out.
        $this->assertNotNull(BulkSmsNgService::normalizeNumber('00000003141'));
    }

    public function test_a_flagged_account_has_no_textable_phone(): void
    {
        $applicant = new LaasApplicant(['phone' => 'SYS-00000007']);
        $this->assertNull($applicant->textablePhone());

        $applicant = new LaasApplicant(['phone' => '0803 123 4567']);
        $this->assertSame('08031234567', $applicant->textablePhone());
    }

    public function test_a_real_phone_settles_the_phone_reasons_only(): void
    {
        $this->assertSame(
            ['contact_status' => LaasApplicant::CONTACT_OK, 'contact_flag_reason' => 'no_email'],
            LaasApplicant::contactSettledAttributes('no_phone,no_email')
        );
        $this->assertSame(
            ['contact_status' => LaasApplicant::CONTACT_OK, 'contact_flag_reason' => null],
            LaasApplicant::contactSettledAttributes('shared_phone')
        );
    }

    public function test_retired_and_transferred_files_are_read_only(): void
    {
        $this->assertFalse((new LaasApplication(['lifecycle_status' => null]))->isReadOnly());
        $this->assertFalse((new LaasApplication(['lifecycle_status' => LaasApplication::LIFECYCLE_ACTIVE]))->isReadOnly());
        $this->assertTrue((new LaasApplication(['lifecycle_status' => LaasApplication::LIFECYCLE_RETIRED]))->isReadOnly());
        $this->assertTrue((new LaasApplication(['lifecycle_status' => LaasApplication::LIFECYCLE_TRANSFERRED_OUT]))->isReadOnly());
    }

    public function test_a_read_only_file_never_advances(): void
    {
        $application = new LaasApplication([
            'stage'            => LaasApplication::STAGE_FILENO_ASSIGNED,
            'lifecycle_status' => LaasApplication::LIFECYCLE_RETIRED,
        ]);

        // canAdvanceTo() alone would allow it; the workflow checks isReadOnly() first.
        $this->assertTrue($application->canAdvanceTo(LaasApplication::STAGE_LAND12_RAISED));
        $this->assertTrue($application->isReadOnly());
    }

    /** @dataProvider reasons */
    public function test_the_event_is_read_from_the_decommissioning_reason(?string $reason, ?string $event): void
    {
        $this->assertSame($event, LaasLineageService::eventFromReason($reason));
    }

    public static function reasons(): array
    {
        return [
            ['Plot Subdivision into 3 fragments', 'Subdivision'],
            ['Plot Merger to RES-2026-10', 'Merger'],
            ['Change of Purpose to CON-COM-2026-430', 'Change of Purpose'],
            ['ST Conversion → ST-RES-2026-12', 'ST Conversion'],
            ['Title Status: regranted — This File has been Re-granted from', 'Re-grant'],
            ['Plot Extension to RES-2026-11', 'Extension'],
            ['something else', 'Parcel update'],
            [null, null],
        ];
    }
}
