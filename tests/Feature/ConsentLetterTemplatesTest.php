<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsentApplicationController;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Each transaction type prints its own letter with the right fees and payee.
 *
 * Rendered through the preview action, which builds an unsaved sample consent
 * (COM-2023-652, ₦20,000,000.00), so no database row is read or written.
 */
class ConsentLetterTemplatesTest extends TestCase
{
    private function letter(string $type): string
    {
        $request = Request::create('/consent-applications/preview-demo', 'GET', ['type' => $type]);
        $html = app(ConsentApplicationController::class)->previewDemo($request)->render();

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));
    }

    /**
     * The preview carries the revised clause 3 (Sections A / B / C). Text is
     * whitespace-collapsed, so a table row reads "2 Registration fees (5%): …".
     */
    public function test_individual_to_individual_letter(): void
    {
        $text = $this->letter('individual_to_individual');

        $this->assertStringContainsString('CONSENT TO ASSIGN THE PROPERTY', $text);
        $this->assertStringContainsString('delegated to me by section 45 of the Act, and further to your application', $text);
        $this->assertStringContainsString('3. The Ministry hereby acknowledges the prior payments listed as items no ii & iii below, and also requests the applicant to pay Stamp Duty (as detailed under item iv below);', $text);
        $this->assertStringContainsString('5. Furthermore, kindly also note that the Deed of Assignment/Instrument Documents must be submitted within the stipulated Eighty-four (84) calendar days, failure of which penalty of the sum of ₦100.00 (One Hundred Naira only) shall be charged for each day of default until the last day of compliance.', $text);
        $this->assertStringContainsString('4. Please be advised that the payment detailed below must be remitted to the Kano State Board of Internal Revenue (KIRS) before the Ministry can proceed with further processing; i. The sum of Six Hundred Thousand Naira Only (₦600,000.00) being 3% for Stamp duty.', $text);
        $this->assertLessThan(strpos($text, '5. Furthermore, kindly also note'), strpos($text, '4. Please be advised that the payment'));
        $this->assertStringContainsString('i. Consideration (Valuation of the Property) ₦20,000,000.00 (Not to be paid)', $text);
        $this->assertStringContainsString('ii. Registration fees (5%) ₦1,000,000.00 (Already paid to MOL&PP)', $text);
        $this->assertStringContainsString('iii. Processing fees ₦12,000.00 (Already paid to MOL&PP)', $text);
        $this->assertStringContainsString('Total ₦1,012,000.00', $text);
        $this->assertStringContainsString('iv. Stamp Duty (3%) ₦600,000.00 (Outstanding, to be paid to KIRS)', $text);
        // The consideration now lives in Section A, not in clause 2.
        $this->assertStringNotContainsString('for consideration of', $text);
        // No Section A / B / C headings: the rows are referred to by item number.
        $this->assertStringNotContainsString('Section A', $text);
        // The specimen's "Individual to Individual" heading was a label, not letter text.
        $this->assertStringNotContainsString('Individual to Individual', $text);
    }

    public function test_company_letters_direct_stamp_duty_to_firs(): void
    {
        foreach (['individual_to_company', 'company_to_individual', 'company_to_company'] as $type) {
            $text = $this->letter($type);

            $this->assertStringContainsString('CONSENT TO ASSIGN THE PROPERTY', $text, $type);
            $this->assertStringContainsString('Total ₦1,012,000.00', $text, $type);
            $this->assertStringContainsString('iv. Stamp Duty (1.5%) ₦300,000.00 (Outstanding, to be paid to FIRS)', $text, $type);
        }
    }

    public function test_mortgage_letter(): void
    {
        $text = $this->letter('mortgage');

        $this->assertStringContainsString('CONSENT TO MORTGAGE THE PROPERTY', $text);
        $this->assertStringContainsString('the Deed of Mortgage/Instrument Documents must be submitted', $text);
        $this->assertStringNotContainsString('Deed of Assignment', $text);
        $this->assertStringContainsString('remitted to the Federal Inland Revenue Service (FIRS) before the Ministry can proceed with further processing; i. The sum of Seventy-Five Thousand Naira Only (₦75,000.00) being 0.375% for Stamp duty.', $text);
        $this->assertStringContainsString('ii. Registration fees (2%) ₦400,000.00 (Already paid to MOL&PP)', $text);
        $this->assertStringContainsString('iii. Processing fees ₦32,000.00 (Already paid to MOL&PP)', $text);
        $this->assertStringContainsString('Total ₦432,000.00', $text);
        $this->assertStringContainsString('iv. Stamp Duty (0.375%) ₦75,000.00 (Outstanding, to be paid to FIRS)', $text);
    }

    public function test_the_deed_named_follows_the_consent_type(): void
    {
        $this->assertStringContainsString('the Deed of Assignment/Instrument Documents', $this->letter('company_to_company'));
        $this->assertStringContainsString('the Deed of Gift/Instrument Documents', $this->letter('gift'));
        $this->assertStringContainsString('the Deed of Mortgage/Instrument Documents', $this->letter('mortgage'));

        // The acknowledgement sheet (page 2) follows suit.
        $this->assertStringContainsString('the registration of your Deed of Gift.', $this->letter('gift'));
        $this->assertStringContainsString('Consent to Mortgage Acknowledgement', $this->letter('mortgage'));
        $this->assertStringContainsString('the registration of your Deed of Mortgage.', $this->letter('mortgage'));
    }

    public function test_every_letter_carries_the_common_footer_and_signatory(): void
    {
        foreach (['individual_to_individual', 'company_to_company', 'mortgage', 'gift'] as $type) {
            $text = $this->letter($type);

            $this->assertStringContainsString('/Instrument Documents must be submitted within the stipulated Eighty-four (84) calendar days', $text, $type);
            $this->assertStringContainsString('ALH. ABDULJABBAR M. UMAR', $text, $type);
        }
    }
}
