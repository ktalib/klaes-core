<?php

namespace App\Services\Ocr;

/**
 * A source of text for an image on disk.
 *
 * The Online Legal Search verification flow depends on this contract and never
 * on Tesseract directly, so the engine can be replaced (a cloud OCR API, a
 * different local binary) without touching the payment workflow.
 *
 * Implementations MUST throw OcrException on failure rather than returning an
 * empty string for it — the caller distinguishes "the document was unreadable"
 * from "OCR itself broke", and tells the applicant different things.
 */
interface OcrReader
{
    /**
     * Read the text from an image.
     *
     * @param  string  $absolutePath  A readable image on the local filesystem.
     * @return string  Extracted text; may legitimately be empty for a blank or
     *                 unreadable image.
     *
     * @throws OcrException when the engine is unavailable or fails outright.
     */
    public function text(string $absolutePath): string;

    /** Is the engine usable right now? Lets callers fail loudly at submit time. */
    public function isAvailable(): bool;
}
