<?php

namespace Tests\Unit\Services;

use App\Services\BarNumber\BarRollLookup;
use App\Services\BarNumber\NullBarRollLookup;
use App\Services\CallToBarVerificationService;
use Tests\TestCase;

/**
 * Call-to-Bar number checking, exercised directly.
 *
 * No roll API, no OCR engine, no database — the rules here decide whether a
 * lawyer can complete a search, so they must be provable in isolation.
 */
class CallToBarVerificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['id_verification.bar_number.min_length' => 4]);
    }

    private function service(?BarRollLookup $roll = null): CallToBarVerificationService
    {
        return new CallToBarVerificationService($roll ?? new NullBarRollLookup());
    }

    /** An individual supplies no number, so there is nothing to check. */
    public function test_an_absent_number_is_not_applicable(): void
    {
        $result = $this->service()->check(null, 'ANY TEXT', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_NOT_APPLICABLE, $result['status']);
        $this->assertSame('', $result['normalized']);
    }

    /** Found on the document is the strongest evidence available without a roll. */
    public function test_a_number_printed_on_the_id_is_matched(): void
    {
        $result = $this->service()->check('SCN123456', 'SUPREME COURT NUMBER SCN123456 CALLED 2015', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_MATCHED, $result['status']);
        $this->assertTrue($result['found_on_id']);
        $this->assertSame('id_document', $result['source']);
    }

    /** Formatting differences must not defeat the search. */
    public function test_formatting_is_normalised_on_both_sides(): void
    {
        $result = $this->service()->check('scn-123 456', 'SCN 123456', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_MATCHED, $result['status']);
        $this->assertSame('SCN123456', $result['normalized']);
    }

    /** The same OCR digit confusions the name comparison has to survive. */
    public function test_ocr_digit_confusion_still_matches(): void
    {
        // The document's "0" was read as "O", its "1" as "I".
        $result = $this->service()->check('SCN10456', 'ROLL NO SCNIO456', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_MATCHED, $result['status']);
    }

    /**
     * The ordinary outcome. A NIN slip does not print a call-to-bar number, so a
     * genuine lawyer lands here — and it must never read as a negative finding.
     */
    public function test_a_number_absent_from_the_id_is_unconfirmed_not_rejected(): void
    {
        $result = $this->service()->check('SCN123456', 'FEDERAL REPUBLIC OF NIGERIA DIGITAL NIN SLIP', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_UNCONFIRMED, $result['status']);
        $this->assertFalse($result['found_on_id']);
        $this->assertNotSame(CallToBarVerificationService::STATUS_REJECTED, $result['status']);
    }

    /** A short string would otherwise "appear" in any transcript by chance. */
    public function test_a_number_shorter_than_the_minimum_is_not_searched_for(): void
    {
        $result = $this->service()->check('12', 'SOME 12 DIGITS HERE', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_UNCONFIRMED, $result['status']);
    }

    /** A configured roll that confirms the number. */
    public function test_a_roll_can_confirm_a_number_absent_from_the_id(): void
    {
        $roll = new class implements BarRollLookup {
            public function lookup(string $barNumber, string $name): ?bool
            {
                return true;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $result = $this->service($roll)->check('SCN123456', 'NOTHING RELEVANT', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_MATCHED, $result['status']);
        $this->assertFalse($result['found_on_id']);
        $this->assertSame('roll', $result['source']);
    }

    /** A roll that positively rejects — the only path to `rejected`. */
    public function test_a_roll_can_reject_a_number(): void
    {
        $roll = new class implements BarRollLookup {
            public function lookup(string $barNumber, string $name): ?bool
            {
                return false;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $result = $this->service($roll)->check('SCN999999', 'NOTHING RELEVANT', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_REJECTED, $result['status']);
    }

    /**
     * A roll that throws is an outage, not a finding. Rejecting a practitioner
     * because a third-party service fell over would be indefensible.
     */
    public function test_a_roll_outage_is_unconfirmed_not_rejected(): void
    {
        $roll = new class implements BarRollLookup {
            public function lookup(string $barNumber, string $name): ?bool
            {
                throw new \RuntimeException('roll service timed out');
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $result = $this->service($roll)->check('SCN123456', 'NOTHING RELEVANT', 'Iorkua Daniel');

        $this->assertSame(CallToBarVerificationService::STATUS_UNCONFIRMED, $result['status']);
    }

    /** The shipped default answers "unknown" to everything. */
    public function test_the_null_roll_never_rejects(): void
    {
        $this->assertNull((new NullBarRollLookup())->lookup('SCN123456', 'Anyone'));
        $this->assertFalse((new NullBarRollLookup())->isAvailable());
    }
}
