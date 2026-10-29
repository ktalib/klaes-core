<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gzip a large text response when the browser accepts it.
 *
 * Apache here runs without mod_deflate, so every page goes out uncompressed.
 * Most pages are small enough not to matter, but some embed their whole data
 * set for client-side paging - Instrument Registration ships ~20 MB of HTML,
 * which gzips to ~1 MB. Attach this to those pages rather than globally.
 *
 * Leaves alone anything already encoded (so turning mod_deflate on later
 * does not double-compress), streams and file downloads, non-text bodies,
 * and bodies too small to be worth it.
 */
class GzipResponse
{
    private const MIN_BYTES = 8192;

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        if (!function_exists('gzencode')
            || $response->headers->has('Content-Encoding')
            || stripos((string) $request->headers->get('Accept-Encoding'), 'gzip') === false) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', 'text/html');
        if (!preg_match('#^(text/|application/(json|javascript|xml))#i', $type)) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < self::MIN_BYTES) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $vary = $response->headers->get('Vary');
        $response->headers->set('Vary', $vary ? $vary . ', Accept-Encoding' : 'Accept-Encoding');

        return $response;
    }
}
