<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prepare a TEMPORARY copy of an uploaded ID for OCR.
 *
 * The stored original is never touched: it is the document an authorized
 * reviewer will look at, so it keeps its full resolution and quality. Everything
 * here happens on a throwaway file that the caller deletes — including when OCR
 * throws (see the finally block in OnlineLsIdVerificationController).
 *
 * Uses GD, which this project already relies on; no Imagick dependency is added.
 */
class OcrImagePreprocessor
{
    /**
     * Write a preprocessed copy and return its absolute path, or null when the
     * image could not be prepared (the caller then OCRs the original).
     *
     * Preprocessing is best-effort by design: a failure here should cost accuracy,
     * never the applicant's submission.
     */
    public function prepare(string $sourcePath): ?string
    {
        if (!config('id_verification.preprocess.enabled', true)) {
            return null;
        }

        $image = null;
        $target = null;

        try {
            if (!is_readable($sourcePath)) {
                return null;
            }

            $image = $this->load($sourcePath);
            if (!$image) {
                return null;
            }

            $image = $this->applyExifOrientation($image, $sourcePath);
            $image = $this->downscale($image);

            // Grayscale then a contrast lift: Tesseract reads printed text off a
            // flat, high-contrast image far more reliably than off a phone photo.
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -(int) config('id_verification.preprocess.contrast', 15));

            $target = $this->tempPath();
            if (!imagepng($image, $target, 2)) {
                $this->discard($target);

                return null;
            }

            return $target;
        } catch (\Throwable $e) {
            // Never surfaced to the applicant, and carries no image content.
            Log::warning('ID verification image preprocessing failed', [
                'error' => $e->getMessage(),
            ]);

            if ($target) {
                $this->discard($target);
            }

            return null;
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
    }

    /** Delete a temporary file produced by prepare(). Safe to call with null. */
    public function discard(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    private function tempPath(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'ols_ocr_' . Str::random(24) . '.png';
    }

    private function load(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * Rotate to match the camera's EXIF orientation. A sideways ID reads as
     * gibberish, and phone uploads are sideways constantly.
     */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);
            $orientation = (int) ($exif['Orientation'] ?? 0);

            $angle = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angle === 0) {
                return $image;
            }

            $rotated = imagerotate($image, $angle, 0);
            if ($rotated instanceof \GdImage) {
                imagedestroy($image);

                return $rotated;
            }
        } catch (\Throwable $e) {
            // A missing or malformed EXIF block is normal, not an error.
        }

        return $image;
    }

    /**
     * Cap the long edge. Oversized images cost OCR time without adding legible
     * detail, and a 12MP phone photo is mostly noise at document scale.
     */
    private function downscale(\GdImage $image): \GdImage
    {
        $max = (int) config('id_verification.preprocess.max_dimension', 2000);
        $width = imagesx($image);
        $height = imagesy($image);
        $longEdge = max($width, $height);

        if ($max <= 0 || $longEdge <= $max) {
            return $image;
        }

        $scale = $max / $longEdge;
        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        if ($resized instanceof \GdImage) {
            imagedestroy($image);

            return $resized;
        }

        return $image;
    }
}
