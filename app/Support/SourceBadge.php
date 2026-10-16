<?php

namespace App\Support;

/**
 * One colour per file Source value (user, 2026-10-16), shared by the Land FC table
 * (resources/views/generate_fileno/mls_js.blade.php, which receives palette() as JSON)
 * and the OSS FC / FEFR tables (resources/views/lands_one_stop_shop/applications.blade.php),
 * so a value looks the same on both.
 *
 * Inline hex styles rather than Tailwind classes: pages load the v2 CDN, which has no
 * orange/amber/teal/... utilities.
 */
class SourceBadge
{
    /** Normalised value => [background, text, border]. */
    private const PALETTE = [
        'conversion'            => ['#eff6ff', '#1d4ed8', '#bfdbfe'], // blue
        'st conversion'         => ['#f0f9ff', '#0369a1', '#bae6fd'], // sky
        'direct allocation'     => ['#f0fdf4', '#15803d', '#bbf7d0'], // green
        'subdivision'           => ['#fffbeb', '#b45309', '#fde68a'], // amber
        'resettlement'          => ['#fff7ed', '#c2410c', '#fed7aa'], // orange
        'op resettlement'       => ['#fff7ed', '#c2410c', '#fed7aa'],
        'op direct allocation'  => ['#f5f3ff', '#6d28d9', '#ddd6fe'], // violet
        'transfer of title (op)' => ['#f5f3ff', '#6d28d9', '#ddd6fe'],
        'occupancy permit'      => ['#fff7ed', '#c2410c', '#fed7aa'],
        'mls commissioned'      => ['#f8fafc', '#334155', '#cbd5e1'], // slate
        'mls commissioned batch' => ['#f8fafc', '#334155', '#cbd5e1'],
        'recertification'       => ['#f0fdfa', '#0f766e', '#99f6e4'], // teal
        'change of purpose'     => ['#ecfeff', '#0e7490', '#a5f3fc'], // cyan
        're-grant'              => ['#fdf2f8', '#be185d', '#fbcfe8'], // pink
        'regrant'               => ['#fdf2f8', '#be185d', '#fbcfe8'],
        'extension'             => ['#f7fee7', '#4d7c0f', '#d9f99d'], // lime
        'extension file'        => ['#f7fee7', '#4d7c0f', '#d9f99d'],
        'plot extension'        => ['#fef2f2', '#b91c1c', '#fecaca'], // red
        'temporary'             => ['#fefce8', '#a16207', '#fef08a'], // yellow
        'temporary file'        => ['#fefce8', '#a16207', '#fef08a'],
        'merger'                => ['#eef2ff', '#4338ca', '#c7d2fe'], // indigo
        'separation'            => ['#fdf4ff', '#a21caf', '#f5d0fe'], // fuchsia
        'duplex'                => ['#ecfdf5', '#047857', '#a7f3d0'], // emerald
        're-issuance of fileno' => ['#fff1f2', '#be123c', '#fecdd3'], // rose
        'reissuance'            => ['#fff1f2', '#be123c', '#fecdd3'],
        'miscellaneous'         => ['#fafaf9', '#57534e', '#d6d3d1'], // stone
        'oss change of name'    => ['#faf5ff', '#7e22ce', '#e9d5ff'], // purple
        'indexing'              => ['#f1f5f9', '#475569', '#94a3b8'], // slate, darker border
    ];

    /** Readable text for stored codes such as "OSS_CHANGE_OF_NAME" or "indexing". */
    public static function label($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || (strpos($value, '_') === false && $value !== strtolower($value))) {
            return $value;
        }

        $words = explode(' ', ucwords(self::key($value)));
        $acronyms = ['Oss' => 'OSS', 'Mls' => 'MLS', 'St' => 'ST', 'Op' => 'OP', 'Of' => 'of'];

        return implode(' ', array_map(fn ($w) => $acronyms[$w] ?? $w, $words));
    }

    /** Anything not listed: neutral grey. */
    private const FALLBACK = ['#f3f4f6', '#374151', '#d1d5db'];

    /** Case/underscore/whitespace-insensitive key, e.g. "MLS_Commissioned" => "mls commissioned". */
    public static function key($value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', strtolower((string) $value))));
    }

    /** For the JS side: the whole map plus the fallback under '*'. */
    public static function palette(): array
    {
        return self::PALETTE + ['*' => self::FALLBACK];
    }

    /** Inline style for one value's pill. */
    public static function style($value): string
    {
        [$bg, $fg, $border] = self::PALETTE[self::key($value)] ?? self::FALLBACK;

        return "background:{$bg};color:{$fg};border:1px solid {$border}";
    }
}
