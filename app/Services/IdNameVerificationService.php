<?php

namespace App\Services;

use App\Models\LegalSearchOnlineVerification;

/**
 * ID NAME verification: does the full name an applicant typed also appear in the
 * text read off the identification document they uploaded?
 *
 * WHAT THIS PROVES, AND WHAT IT DOES NOT
 * A `verified` result means those two names agree. It is NOT evidence that the
 * document is genuine, that it has not been altered, that the uploader is the
 * person it depicts, or that the ID number is valid with the issuing authority.
 * Nothing downstream may describe it as identity verification.
 *
 * This class knows nothing about OCR engines, HTTP, or storage — it takes two
 * strings and returns a verdict, which is what makes it unit-testable without a
 * Tesseract installation and what lets the OCR provider be swapped later.
 */
class IdNameVerificationService
{
    /**
     * Diacritic map for the no-intl fallback. Generated from Unicode canonical
     * decomposition over the Latin ranges that appear on identification
     * documents; anything unmapped is stripped by normalize() as a non-letter.
     */
    private const ACCENT_MAP = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ç' => 'C', 'È' => 'E',
        'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
        'Ü' => 'U', 'Ý' => 'Y', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i',
        'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ù' => 'u',
        'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A',
        'ă' => 'a', 'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C',
        'ċ' => 'c', 'Č' => 'C', 'č' => 'c', 'Ď' => 'D', 'ď' => 'd', 'Ē' => 'E', 'ē' => 'e', 'Ĕ' => 'E',
        'ĕ' => 'e', 'Ė' => 'E', 'ė' => 'e', 'Ę' => 'E', 'ę' => 'e', 'Ě' => 'E', 'ě' => 'e', 'Ĝ' => 'G',
        'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g', 'Ġ' => 'G', 'ġ' => 'g', 'Ģ' => 'G', 'ģ' => 'g', 'Ĥ' => 'H',
        'ĥ' => 'h', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i', 'Ĭ' => 'I', 'ĭ' => 'i', 'Į' => 'I',
        'į' => 'i', 'İ' => 'I', 'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k', 'Ĺ' => 'L', 'ĺ' => 'l',
        'Ļ' => 'L', 'ļ' => 'l', 'Ľ' => 'L', 'ľ' => 'l', 'Ń' => 'N', 'ń' => 'n', 'Ņ' => 'N', 'ņ' => 'n',
        'Ň' => 'N', 'ň' => 'n', 'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o', 'Ő' => 'O', 'ő' => 'o',
        'Ŕ' => 'R', 'ŕ' => 'r', 'Ŗ' => 'R', 'ŗ' => 'r', 'Ř' => 'R', 'ř' => 'r', 'Ś' => 'S', 'ś' => 's',
        'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's', 'Ţ' => 'T', 'ţ' => 't',
        'Ť' => 'T', 'ť' => 't', 'Ũ' => 'U', 'ũ' => 'u', 'Ū' => 'U', 'ū' => 'u', 'Ŭ' => 'U', 'ŭ' => 'u',
        'Ů' => 'U', 'ů' => 'u', 'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u', 'Ŵ' => 'W', 'ŵ' => 'w',
        'Ŷ' => 'Y', 'ŷ' => 'y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z',
        'ž' => 'z', 'Ơ' => 'O', 'ơ' => 'o', 'Ư' => 'U', 'Ḁ' => 'A', 'ḁ' => 'a', 'Ḃ' => 'B', 'ḃ' => 'b',
        'Ḅ' => 'B', 'ḅ' => 'b', 'Ḇ' => 'B', 'ḇ' => 'b', 'Ḉ' => 'C', 'ḉ' => 'c', 'Ḋ' => 'D', 'ḋ' => 'd',
        'Ḍ' => 'D', 'ḍ' => 'd', 'Ḏ' => 'D', 'ḏ' => 'd', 'Ḑ' => 'D', 'ḑ' => 'd', 'Ḓ' => 'D', 'ḓ' => 'd',
        'Ḕ' => 'E', 'ḕ' => 'e', 'Ḗ' => 'E', 'ḗ' => 'e', 'Ḙ' => 'E', 'ḙ' => 'e', 'Ḛ' => 'E', 'ḛ' => 'e',
        'Ḝ' => 'E', 'ḝ' => 'e', 'Ḟ' => 'F', 'ḟ' => 'f', 'Ḡ' => 'G', 'ḡ' => 'g', 'Ḣ' => 'H', 'ḣ' => 'h',
        'Ḥ' => 'H', 'ḥ' => 'h', 'Ḧ' => 'H', 'ḧ' => 'h', 'Ḩ' => 'H', 'ḩ' => 'h', 'Ḫ' => 'H', 'ḫ' => 'h',
        'Ḭ' => 'I', 'ḭ' => 'i', 'Ḯ' => 'I', 'ḯ' => 'i', 'Ḱ' => 'K', 'ḱ' => 'k', 'Ḳ' => 'K', 'ḳ' => 'k',
        'Ḵ' => 'K', 'ḵ' => 'k', 'Ḷ' => 'L', 'ḷ' => 'l', 'Ḹ' => 'L', 'ḹ' => 'l', 'Ḻ' => 'L', 'ḻ' => 'l',
        'Ḽ' => 'L', 'ḽ' => 'l', 'Ḿ' => 'M', 'ḿ' => 'm', 'Ṁ' => 'M', 'ṁ' => 'm', 'Ṃ' => 'M', 'ṃ' => 'm',
        'Ṅ' => 'N', 'ṅ' => 'n', 'Ṇ' => 'N', 'ṇ' => 'n', 'Ṉ' => 'N', 'ṉ' => 'n', 'Ṋ' => 'N', 'ṋ' => 'n',
        'Ṍ' => 'O', 'ṍ' => 'o', 'Ṏ' => 'O', 'ṏ' => 'o', 'Ṑ' => 'O', 'ṑ' => 'o', 'Ṓ' => 'O', 'ṓ' => 'o',
        'Ṕ' => 'P', 'ṕ' => 'p', 'Ṗ' => 'P', 'ṗ' => 'p', 'Ṙ' => 'R', 'ṙ' => 'r', 'Ṛ' => 'R', 'ṛ' => 'r',
        'Ṝ' => 'R', 'ṝ' => 'r', 'Ṟ' => 'R', 'ṟ' => 'r', 'Ṡ' => 'S', 'ṡ' => 's', 'Ṣ' => 'S', 'ṣ' => 's',
        'Ṥ' => 'S', 'ṥ' => 's', 'Ṧ' => 'S', 'ṧ' => 's', 'Ṩ' => 'S', 'ṩ' => 's', 'Ṫ' => 'T', 'ṫ' => 't',
        'Ṭ' => 'T', 'ṭ' => 't', 'Ṯ' => 'T', 'ṯ' => 't', 'Ṱ' => 'T', 'ṱ' => 't', 'Ṳ' => 'U', 'ṳ' => 'u',
        'Ṵ' => 'U', 'ṵ' => 'u', 'Ṷ' => 'U', 'ṷ' => 'u', 'Ṹ' => 'U', 'ṹ' => 'u', 'Ṻ' => 'U', 'ṻ' => 'u',
        'Ṽ' => 'V', 'ṽ' => 'v', 'Ṿ' => 'V', 'ṿ' => 'v', 'Ẁ' => 'W', 'ẁ' => 'w', 'Ẃ' => 'W', 'ẃ' => 'w',
        'Ẅ' => 'W', 'ẅ' => 'w', 'Ẇ' => 'W', 'ẇ' => 'w', 'Ẉ' => 'W', 'ẉ' => 'w', 'Ẋ' => 'X', 'ẋ' => 'x',
        'Ẍ' => 'X', 'ẍ' => 'x', 'Ẏ' => 'Y', 'ẏ' => 'y', 'Ẑ' => 'Z', 'ẑ' => 'z', 'Ẓ' => 'Z', 'ẓ' => 'z',
        'Ẕ' => 'Z', 'ẕ' => 'z', 'ẖ' => 'h', 'ẗ' => 't', 'ẘ' => 'w', 'ẙ' => 'y', 'Ạ' => 'A', 'ạ' => 'a',
        'Ả' => 'A', 'ả' => 'a', 'Ấ' => 'A', 'ấ' => 'a', 'Ầ' => 'A', 'ầ' => 'a', 'Ẩ' => 'A', 'ẩ' => 'a',
        'Ẫ' => 'A', 'ẫ' => 'a', 'Ậ' => 'A', 'ậ' => 'a', 'Ắ' => 'A', 'ắ' => 'a', 'Ằ' => 'A', 'ằ' => 'a',
        'Ẳ' => 'A', 'ẳ' => 'a', 'Ẵ' => 'A', 'ẵ' => 'a', 'Ặ' => 'A', 'ặ' => 'a', 'Ẹ' => 'E', 'ẹ' => 'e',
        'Ẻ' => 'E', 'ẻ' => 'e', 'Ẽ' => 'E', 'ẽ' => 'e', 'Ế' => 'E', 'ế' => 'e', 'Ề' => 'E', 'ề' => 'e',
        'Ể' => 'E', 'ể' => 'e', 'Ễ' => 'E', 'ễ' => 'e', 'Ệ' => 'E', 'ệ' => 'e', 'Ỉ' => 'I', 'ỉ' => 'i',
        'Ị' => 'I', 'ị' => 'i', 'Ọ' => 'O', 'ọ' => 'o', 'Ỏ' => 'O', 'ỏ' => 'o', 'Ố' => 'O', 'ố' => 'o',
        'Ồ' => 'O', 'ồ' => 'o', 'Ổ' => 'O', 'ổ' => 'o', 'Ỗ' => 'O', 'ỗ' => 'o', 'Ộ' => 'O', 'ộ' => 'o',
        'Ớ' => 'O', 'ớ' => 'o', 'Ờ' => 'O', 'ờ' => 'o', 'Ở' => 'O', 'ở' => 'o', 'Ỡ' => 'O', 'ỡ' => 'o',
        'Ợ' => 'O', 'ợ' => 'o', 'Ụ' => 'U', 'ụ' => 'u', 'Ủ' => 'U', 'ủ' => 'u', 'Ứ' => 'U', 'ứ' => 'u',
        'Ừ' => 'U', 'ừ' => 'u', 'Ử' => 'U', 'ử' => 'u', 'Ữ' => 'U', 'ữ' => 'u', 'Ự' => 'U', 'ự' => 'u',
        'Ỳ' => 'Y', 'ỳ' => 'y', 'Ỵ' => 'Y', 'ỵ' => 'y', 'Ỷ' => 'Y', 'ỷ' => 'y', 'Ỹ' => 'Y', 'ỹ' => 'y',
    ];

    /**
     * Compare a typed name against text extracted from an ID.
     *
     * @return array{
     *     status: string,
     *     score: int,
     *     extracted_text: string,
     *     matched_name_parts: array<int, string>,
     *     missing_name_parts: array<int, string>
     * }
     */
    public function compare(string $enteredName, string $extractedText): array
    {
        $enteredParts = $this->nameParts($enteredName);

        // Nothing usable was typed, or the document yielded nothing readable.
        // Both are failures, but neither is the applicant lying — the caller
        // distinguishes them for messaging via `extracted_text`.
        if (empty($enteredParts) || trim($extractedText) === '') {
            return $this->result(
                LegalSearchOnlineVerification::STATUS_FAILED,
                0,
                $extractedText,
                [],
                $enteredParts
            );
        }

        $documentParts = $this->nameParts($extractedText);

        if (empty($documentParts)) {
            return $this->result(
                LegalSearchOnlineVerification::STATUS_FAILED,
                0,
                $extractedText,
                [],
                $enteredParts
            );
        }

        $matched = [];
        $missing = [];

        // Order-independent by construction: every typed component is looked for
        // anywhere in the document's components, so "Iorkua Kator Daniel" against
        // "DANIEL IORKUA KATOR" matches in full.
        foreach ($enteredParts as $part) {
            if ($this->partAppearsIn($part, $documentParts)) {
                $matched[] = $part;
            } else {
                $missing[] = $part;
            }
        }

        $score = (int) round((count($matched) / count($enteredParts)) * 100);

        return $this->result(
            $this->statusFor($score, count($matched)),
            $score,
            $extractedText,
            $matched,
            $missing
        );
    }

    /**
     * The verdict for a score.
     *
     * The matched-parts floor is not a tie-breaker, it is a veto: one name in
     * common ("IBRAHIM") is worth very little on its own, so a single match can
     * never be `verified` however the percentage lands. A two-part name where
     * only one part matches scores 50 and fails on the band anyway; the floor is
     * what stops a one-word entered name scoring 100.
     */
    private function statusFor(int $score, int $matchedCount): string
    {
        $verified = (int) config('id_verification.thresholds.verified', 80);
        $review   = (int) config('id_verification.thresholds.review', 60);
        $minParts = (int) config('id_verification.min_matching_parts', 2);

        if ($score >= $verified && $matchedCount >= $minParts) {
            return LegalSearchOnlineVerification::STATUS_VERIFIED;
        }

        // A high score carrying too few matched parts is exactly the case a human
        // should look at, rather than a silent pass or a flat rejection.
        if ($score >= $review) {
            return LegalSearchOnlineVerification::STATUS_REVIEW;
        }

        return LegalSearchOnlineVerification::STATUS_FAILED;
    }

    /**
     * Split a name (typed, or a whole OCR transcript) into comparable components.
     *
     * Normalization here is what makes the comparison survive real documents:
     * case is folded, punctuation and repeated whitespace collapse, and initials
     * are dropped as meaningless.
     *
     * @return array<int, string>
     */
    public function nameParts(string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        $minLength = (int) config('id_verification.min_part_length', 2);

        $parts = array_filter(
            explode(' ', $normalized),
            fn (string $part) => mb_strlen($part) >= $minLength
        );

        // Duplicates carry no extra information and would skew the score — a name
        // typed as "JOHN JOHN" must not count twice.
        return array_values(array_unique($parts));
    }

    /**
     * Normalize text for comparison.
     *
     * - upper-cased, so capitalization alone never fails a match;
     * - punctuation and separators become spaces (O'BRIEN, MARY-JANE, "SURNAME:");
     * - accents folded to ASCII;
     * - repeated whitespace collapsed.
     */
    public function normalize(string $value): string
    {
        $value = $this->foldAccents($value);
        $value = mb_strtoupper($value, 'UTF-8');

        // Anything that is not a letter or digit is a separator. This deliberately
        // splits hyphenated and apostrophed names into components rather than
        // gluing them, so "MARY-JANE" matches a document showing "MARY JANE".
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Is this typed component present among the document's components?
     *
     * Equality first, then a bounded fuzzy comparison for OCR noise. Substring
     * containment is deliberately NOT accepted: "DAN" must not match "DANIEL",
     * because a shortened name is a different name.
     *
     * @param array<int, string> $documentParts
     */
    private function partAppearsIn(string $part, array $documentParts): bool
    {
        if (in_array($part, $documentParts, true)) {
            return true;
        }

        $canonical = $this->foldOcrLookalikes($part);
        $maxDistance = (int) config('id_verification.max_edit_distance', 1);

        foreach ($documentParts as $candidate) {
            if ($canonical === $this->foldOcrLookalikes($candidate)) {
                return true;
            }

            // Only names long enough for an edit to be noise rather than identity
            // get the fuzzy pass — at 4 characters, one edit is a third of the word.
            if ($maxDistance > 0
                && mb_strlen($part) >= 5
                && abs(mb_strlen($part) - mb_strlen($candidate)) <= $maxDistance
                && levenshtein($canonical, $this->foldOcrLookalikes($candidate)) <= $maxDistance
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fold the glyph confusions OCR makes on printed IDs, so DAN1EL and DANIEL
     * are the same word. Applied only for comparison — never stored.
     */
    private function foldOcrLookalikes(string $value): string
    {
        return strtr($value, [
            '0' => 'O',
            '1' => 'I',
            '5' => 'S',
            '8' => 'B',
            '2' => 'Z',
            '6' => 'G',
        ]);
    }

    /**
     * Strip diacritics so an accented spelling compares equal to its ASCII form.
     *
     * iconv's //TRANSLIT is deliberately NOT used: its output is platform
     * dependent, and on this stack it renders an accented "e" as "'e", which
     * normalize() would then split into two bogus name parts ("AD", "ELE") --
     * turning a clean match into a failure. Both paths below are deterministic.
     */
    private function foldAccents(string $value): string
    {
        // intl covers the whole Latin range and is locale-independent.
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');

            if ($transliterator) {
                $folded = $transliterator->transliterate($value);

                if (is_string($folded)) {
                    return $folded;
                }
            }
        }

        return strtr($value, self::ACCENT_MAP);
    }

    /**
     * @param array<int, string> $matched
     * @param array<int, string> $missing
     * @return array{status: string, score: int, extracted_text: string, matched_name_parts: array<int, string>, missing_name_parts: array<int, string>}
     */
    private function result(string $status, int $score, string $text, array $matched, array $missing): array
    {
        return [
            'status'             => $status,
            'score'              => $score,
            'extracted_text'     => $text,
            'matched_name_parts' => array_values($matched),
            'missing_name_parts' => array_values($missing),
        ];
    }
}
