<?php

namespace App\Services;

use App\Services\BarNumber\BarRollLookup;
use Illuminate\Support\Facades\Log;

/**
 * Check a lawyer's Call-to-Bar number as far as it can actually be checked.
 *
 * Two sources, in order:
 *   1. the text OCR read off the uploaded ID — free, and conclusive when it hits;
 *   2. an external roll of legal practitioners, if one is configured.
 *
 * WHAT AN UNCONFIRMED RESULT MEANS. Nigerian general-purpose IDs — the NIN slip,
 * driver's licence, voter's card — do not print a call-to-bar number, and no roll
 * API is wired up. So `unconfirmed` is the ordinary outcome for a perfectly
 * genuine practitioner, and the caller must not treat it as a negative finding.
 * It never blocks payment; the approving officer confirms the number instead.
 *
 * Like IdNameVerificationService, this takes strings and returns a verdict — no
 * HTTP, no storage — so it is unit-testable without a roll service.
 */
class CallToBarVerificationService
{
    public const STATUS_NOT_APPLICABLE = 'not_applicable';
    public const STATUS_MATCHED        = 'matched';
    public const STATUS_UNCONFIRMED    = 'unconfirmed';
    public const STATUS_REJECTED       = 'rejected';

    public function __construct(private readonly BarRollLookup $roll)
    {
    }

    /**
     * @return array{status: string, normalized: string, found_on_id: bool, source: string}
     */
    public function check(?string $barNumber, string $extractedText, string $name = ''): array
    {
        $normalized = $this->normalize($barNumber);

        // An individual, or a lawyer whose number never arrived. Validation is
        // what requires it; there is simply nothing to check here.
        if ($normalized === '') {
            return $this->result(self::STATUS_NOT_APPLICABLE, '', false, 'none');
        }

        if ($this->appearsIn($normalized, $extractedText)) {
            return $this->result(self::STATUS_MATCHED, $normalized, true, 'id_document');
        }

        // Nothing on the document. Ask the roll, if there is one.
        try {
            $verdict = $this->roll->lookup($normalized, $name);
        } catch (\Throwable $e) {
            // A roll outage is not a finding about the practitioner.
            Log::warning('Call-to-Bar roll lookup failed', ['error' => $e->getMessage()]);
            $verdict = null;
        }

        if ($verdict === true) {
            return $this->result(self::STATUS_MATCHED, $normalized, false, 'roll');
        }

        if ($verdict === false) {
            return $this->result(self::STATUS_REJECTED, $normalized, false, 'roll');
        }

        // null — no roll, or it could not say. Recorded, never held against them.
        return $this->result(self::STATUS_UNCONFIRMED, $normalized, false, 'none');
    }

    /**
     * Strip formatting so "SCN 123456", "scn-123456" and "SCN123456" are one value.
     * Stored and compared in this form.
     */
    public function normalize(?string $barNumber): string
    {
        $value = strtoupper(trim((string) $barNumber));

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    /**
     * Is the number present in the document text?
     *
     * The transcript is normalised the same way — so a number printed as
     * "SCN 123456" still matches — and OCR digit confusions are folded, the same
     * fix the name comparison needs.
     *
     * Numbers shorter than the configured minimum are not searched for at all: a
     * three-character string appears inside an OCR transcript by chance.
     */
    private function appearsIn(string $normalized, string $extractedText): bool
    {
        $minLength = (int) config('id_verification.bar_number.min_length', 4);

        if (strlen($normalized) < $minLength || trim($extractedText) === '') {
            return false;
        }

        $haystack = preg_replace('/[^A-Z0-9]/', '', strtoupper($extractedText)) ?? '';

        if ($haystack === '') {
            return false;
        }

        return str_contains($haystack, $normalized)
            || str_contains($this->foldOcrLookalikes($haystack), $this->foldOcrLookalikes($normalized));
    }

    /** Mirrors the name comparison's glyph folding, for the same OCR reasons. */
    private function foldOcrLookalikes(string $value): string
    {
        return strtr($value, [
            '0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B', '2' => 'Z', '6' => 'G',
        ]);
    }

    /**
     * @return array{status: string, normalized: string, found_on_id: bool, source: string}
     */
    private function result(string $status, string $normalized, bool $foundOnId, string $source): array
    {
        return [
            'status'      => $status,
            'normalized'  => $normalized,
            'found_on_id' => $foundOnId,
            'source'      => $source,
        ];
    }
}
