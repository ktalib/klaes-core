<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders a QR code as a PNG data URI.
 *
 * The Legal Search print templates draw their QR client-side with qrcode.js,
 * which is unavailable when DomPDF renders the same report server-side for the
 * emailed PDF. bacon/bacon-qr-code ships only Imagick and SVG back-ends —
 * Imagick is not installed here and DomPDF's SVG support is patchy — so the
 * encoder's raw matrix is painted with GD instead.
 */
class QrPng
{
    /**
     * @return string|null A `data:image/png;base64,…` URI, or null if the code
     *                     could not be rendered (never throws — a missing QR
     *                     must not cost the requester their report).
     */
    public static function dataUri(?string $text, int $scale = 4, int $quietZone = 2): ?string
    {
        $text = trim((string) $text);

        if ($text === '' || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        try {
            $matrix = Encoder::encode($text, ErrorCorrectionLevel::L(), 'ISO-8859-1')->getMatrix();

            $width  = $matrix->getWidth();
            $height = $matrix->getHeight();
            $size   = ($width + $quietZone * 2) * $scale;

            $image = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefilledrectangle($image, 0, 0, $size, $size, $white);

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    if ($matrix->get($x, $y)) {
                        imagefilledrectangle(
                            $image,
                            ($x + $quietZone) * $scale,
                            ($y + $quietZone) * $scale,
                            ($x + $quietZone + 1) * $scale - 1,
                            ($y + $quietZone + 1) * $scale - 1,
                            $black
                        );
                    }
                }
            }

            ob_start();
            imagepng($image);
            $png = (string) ob_get_clean();
            imagedestroy($image);

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
