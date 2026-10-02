<?php

namespace App\Services\Ocr;

use RuntimeException;

/**
 * The OCR engine could not be run, or failed while running.
 *
 * Distinct from "the engine ran and found no text": that is an unreadable
 * document and the applicant is told to upload a clearer image, whereas this is
 * an infrastructure fault the applicant can do nothing about. The message on
 * this exception is technical and belongs in the log, never in a response.
 */
class OcrException extends RuntimeException
{
}
