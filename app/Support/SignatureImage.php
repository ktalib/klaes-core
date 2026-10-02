<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns a stored signature path into something a page or a PDF can render.
 *
 * `users.signature` holds a Storage-relative path (e.g.
 * "signing_officer_signatures/xxx.jpg"), but historic rows also carry
 * "public/…", "/storage/…", absolute URLs and inline data URIs. Printed output
 * needs an inline base64 data URI — DomPDF and "Save as PDF" both drop images
 * they have to fetch over the network — so resolve to that whenever the file
 * can be read, and fall back to a URL only when it cannot.
 */
class SignatureImage
{
    /**
     * @return string|null A data URI (preferred) or URL, or null when the path
     *                     is empty or the file is missing.
     */
    public static function dataUri(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) {
            return $path;
        }

        // Strip whichever prefix the stored value happens to carry, leaving the
        // path relative to the uploads root.
        $relative = ltrim($path, '/');
        foreach (['storage/', 'public/'] as $prefix) {
            if (Str::startsWith($relative, $prefix)) {
                $relative = ltrim(substr($relative, strlen($prefix)), '/');
                break;
            }
        }

        // Resolved against the filesystem rather than Storage::exists(), because
        // this install's `local` disk is rooted at storage/ (not the standard
        // storage/app) while uploads are written to the `public` disk at
        // storage/app/public — so a disk-relative lookup silently misses and
        // falls through to a URL that DomPDF cannot fetch.
        $absolutes = [
            storage_path('app/public/' . $relative),
            storage_path('app/' . $relative),
            storage_path($relative),
            storage_path('public/' . $relative),
            public_path('storage/' . $relative),
        ];

        foreach (array_unique($absolutes) as $absolute) {
            if (!is_file($absolute) || !is_readable($absolute)) {
                continue;
            }

            try {
                $contents = file_get_contents($absolute);
                if ($contents === false) {
                    continue;
                }

                $mimeType = self::mimeType($absolute);

                return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                // Fall through to URL-based rendering.
            }
        }

        // Nothing readable on disk — a browser may still resolve it over HTTP.
        return asset('storage/' . $relative);
    }

    /**
     * Best-effort MIME detection, defaulting to PNG.
     */
    protected static function mimeType(string $absolute): string
    {
        if (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($absolute);
            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return match (strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    /**
     * Same resolution, but only when the result can be embedded in a PDF.
     *
     * dataUri() falls back to a URL when the file cannot be read, which a
     * browser will happily load but DomPDF will not — it blocks remote images
     * by default. Returning that URL to a PDF renderer produces a report that
     * looks signed in preview and carries no signature once issued, so PDF
     * callers must use this and treat null as "not signed".
     */
    public static function embeddable(?string $path): ?string
    {
        $resolved = self::dataUri($path);

        return ($resolved !== null && Str::startsWith($resolved, 'data:')) ? $resolved : null;
    }
}
