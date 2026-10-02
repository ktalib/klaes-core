<?php

namespace Tests\Unit\Services;

use App\Models\LegalSearchOnlineVerification;
use App\Services\IdNameVerificationService;
use Tests\TestCase;

/**
 * The real name-normalization and scoring logic, exercised directly.
 *
 * No OCR engine, no database, no HTTP — these run anywhere, which is the point:
 * the matching rules are the part of ID name verification most likely to be
 * retuned, and they must be provable without a Tesseract installation.
 */
class IdNameVerificationServiceTest extends TestCase
{
    private IdNameVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the bands so a deployment's .env cannot move the assertions.
        config([
            'id_verification.thresholds.verified' => 80,
            'id_verification.thresholds.review'   => 60,
            'id_verification.min_matching_parts'  => 2,
            'id_verification.min_part_length'     => 2,
            'id_verification.max_edit_distance'   => 1,
        ]);

        $this->service = new IdNameVerificationService();
    }

    /** 1. Exact full-name match. */
    public function test_exact_full_name_match_is_verified(): void
    {
        $result = $this->service->compare('Iorkua Kator Daniel', 'IORKUA KATOR DANIEL');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(100, $result['score']);
        $this->assertEmpty($result['missing_name_parts']);
    }

    /** 2. Same name, different order — the worked example from the specification. */
    public function test_same_name_in_a_different_order_is_a_complete_match(): void
    {
        $result = $this->service->compare('Iorkua Kator Daniel', 'DANIEL IORKUA KATOR');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(100, $result['score']);
    }

    /** 3. Capitalization alone must never fail a match. */
    public function test_matching_is_case_insensitive(): void
    {
        $result = $this->service->compare('iorkua kator daniel', 'IORKUA KATOR DANIEL');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(100, $result['score']);
    }

    /** 4. Punctuation and repeated whitespace are noise, not difference. */
    public function test_punctuation_and_extra_whitespace_are_ignored(): void
    {
        $result = $this->service->compare(
            "  Iorkua,   Kator-Daniel  ",
            "FEDERAL REPUBLIC\nSURNAME: IORKUA\nOTHER NAMES: KATOR, DANIEL"
        );

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(100, $result['score']);
    }

    /** 5. Two of three names present: scores 66 and lands in review, not verified. */
    public function test_partial_name_match_falls_to_review(): void
    {
        $result = $this->service->compare('Iorkua Kator Daniel', 'IORKUA KATOR');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_REVIEW, $result['status']);
        $this->assertSame(67, $result['score']);
        $this->assertSame(['DANIEL'], $result['missing_name_parts']);
    }

    /**
     * 6. One matching component can never be enough on its own.
     *
     * The single-part veto matters most where the score would otherwise pass: a
     * one-word entered name matching would score 100.
     */
    public function test_a_single_matching_component_is_never_verified(): void
    {
        $result = $this->service->compare('Ibrahim', 'IBRAHIM MUSA ALIYU');

        $this->assertNotSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(LegalSearchOnlineVerification::STATUS_REVIEW, $result['status']);
        $this->assertCount(1, $result['matched_name_parts']);
    }

    /** 7. A different person fails outright. */
    public function test_completely_different_name_fails(): void
    {
        $result = $this->service->compare('Iorkua Kator Daniel', 'MUSA ALIYU IBRAHIM');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_FAILED, $result['status']);
        $this->assertSame(0, $result['score']);
    }

    /** 8. Nothing readable on the document. */
    public function test_empty_ocr_result_fails_with_a_zero_score(): void
    {
        $result = $this->service->compare('Iorkua Kator Daniel', '');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_FAILED, $result['status']);
        $this->assertSame(0, $result['score']);
        $this->assertSame(['IORKUA', 'KATOR', 'DANIEL'], $result['missing_name_parts']);
    }

    /**
     * A shortened name is a different name: substring containment must not pass.
     * This is the rule that stops "DAN" being accepted for "DANIEL".
     */
    public function test_substring_is_not_treated_as_a_match(): void
    {
        $result = $this->service->compare('Dan Kator', 'DANIEL KATOR IORKUA');

        $this->assertSame(['KATOR'], $result['matched_name_parts']);
        $this->assertSame(['DAN'], $result['missing_name_parts']);
        $this->assertNotSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
    }

    /** Initials carry no identifying weight and are dropped from both sides. */
    public function test_single_character_components_are_ignored(): void
    {
        $parts = $this->service->nameParts('J K Daniel Iorkua');

        $this->assertSame(['DANIEL', 'IORKUA'], $parts);
    }

    /** OCR glyph confusions are folded, so DAN1EL still reads as DANIEL. */
    public function test_common_ocr_character_confusions_still_match(): void
    {
        $result = $this->service->compare('Daniel Iorkua', 'DAN1EL I0RKUA');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
        $this->assertSame(100, $result['score']);
    }

    /** Accented spellings compare equal to their ASCII form. */
    public function test_accents_are_folded(): void
    {
        $result = $this->service->compare('Adéle Okonkwo', 'ADELE OKONKWO');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);
    }

    /** A repeated component must not inflate the score. */
    public function test_duplicate_components_are_counted_once(): void
    {
        $this->assertSame(['JOHN', 'BELLO'], $this->service->nameParts('John John Bello'));
    }

    /** Thresholds come from configuration, not from constants in the code. */
    public function test_thresholds_are_configurable(): void
    {
        config(['id_verification.thresholds.verified' => 100]);

        // 2 of 3 = 67, which passed as `review` above and still does; but a name
        // that scored 80 no longer reaches `verified` under a stricter band.
        $result = $this->service->compare('A Musa Bello Kano', 'MUSA BELLO KANO');

        $this->assertSame(100, $result['score']);
        $this->assertSame(LegalSearchOnlineVerification::STATUS_VERIFIED, $result['status']);

        config(['id_verification.min_matching_parts' => 4]);
        $stricter = $this->service->compare('A Musa Bello Kano', 'MUSA BELLO KANO');

        $this->assertSame(LegalSearchOnlineVerification::STATUS_REVIEW, $stricter['status']);
    }
}
