<?php

namespace App\Services\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

/**
 * Locally hosted Tesseract, via thiagoalessio/tesseract_ocr.
 *
 * The binary is NOT bundled — the server needs `tesseract-ocr` installed
 * (see docs/ID_NAME_VERIFICATION.md). isAvailable() exists so a deployment that
 * forgot it fails with a clear message at submit time rather than silently
 * failing every applicant's verification.
 */
class TesseractOcrReader implements OcrReader
{
    public function text(string $absolutePath): string
    {
        if (!is_readable($absolutePath)) {
            throw new OcrException('OCR source image is not readable: ' . $absolutePath);
        }

        try {
            $ocr = new TesseractOCR($absolutePath);

            $binary = config('id_verification.ocr.binary');
            if (!empty($binary)) {
                $ocr->executable($binary);
            }

            $language = (string) config('id_verification.ocr.language', 'eng');
            if ($language !== '') {
                $ocr->lang($language);
            }

            return (string) $ocr->run((int) config('id_verification.ocr.timeout', 30));
        } catch (TesseractNotFoundException $e) {
            throw new OcrException('Tesseract binary not found. Install tesseract-ocr on this server.', 0, $e);
        } catch (UnsuccessfulCommandException $e) {
            throw new OcrException('Tesseract exited unsuccessfully: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new OcrException('OCR failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isAvailable(): bool
    {
        if (!class_exists(TesseractOCR::class)) {
            return false;
        }

        try {
            $binary = config('id_verification.ocr.binary') ?: 'tesseract';

            // --version is the cheapest proof the binary exists and is executable.
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = @proc_open(escapeshellarg($binary) . ' --version', $descriptors, $pipes);

            if (!is_resource($process)) {
                return false;
            }

            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    stream_get_contents($pipe);
                    fclose($pipe);
                }
            }

            return proc_close($process) === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
