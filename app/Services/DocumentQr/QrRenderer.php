<?php

namespace App\Services\DocumentQr;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

/**
 * Renders QR codes locally.
 *
 * Every print path used to build an <img> pointing at
 * https://api.qrserver.com/v1/create-qr-code/?data=… which meant the payload —
 * including file title, plot, district and LGA on the tracking-sheet JSON
 * variant — was sent to a third party on every print, and printing degraded to
 * a broken-image box whenever the server had no outbound internet access.
 *
 * bacon/bacon-qr-code was already a dependency (see PhsAdminController), so
 * this needs no new package.
 */
class QrRenderer
{
    /**
     * Render as an SVG data URI, safe to drop straight into an <img src>.
     * Returns null on failure so a print template can fall back to text
     * rather than dying mid-page.
     */
    public function dataUri(string $content, ?int $size = null): ?string
    {
        $svg = $this->svg($content, $size);

        return $svg === null ? null : 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function svg(string $content, ?int $size = null): ?string
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(
                    $size ?? (int) config('document_qr.render.size', 140),
                    (int) config('document_qr.render.margin', 1)
                ),
                new SvgImageBackEnd()
            );

            return (new Writer($renderer))->writeString($content);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
