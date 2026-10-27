<?php

namespace App\Support;

/**
 * The short cause of a decommissioning (Subdivision, Merger, Re-grant, ...) read from its
 * stored reason text, with the colour its badge uses on the Decommissioned Files list
 * (user, 2026-10-26: show just the cause, the full reason on hover, each cause its own colour).
 *
 * The reasons are free text written by several workflows, e.g.
 *   "Title Status: resettled — ...", "Title Status: subdivision, change_of_purpose — ...",
 *   "Manual Linkage: Merger → CON-...", "Plot Subdivision into 217 fragments",
 *   "Subdivision → COM-2026-196, ...", "Re-grant → COM-2017-129", "Change of Purpose to CON-COM".
 * Anything unrecognised shows its own first words, in grey.
 */
class DecommissionCause
{
    /** Cause => [text colour, background colour]. Inline hex: the Tailwind v2 CDN lacks some palettes. */
    public const COLOURS = [
        'Subdivision' => ['#1e40af', '#dbeafe'],
        'Merger' => ['#6b21a8', '#f3e8ff'],
        'Change of Purpose' => ['#92400e', '#fef3c7'],
        'Re-grant' => ['#115e59', '#ccfbf1'],
        'Resettled' => ['#166534', '#dcfce7'],
        'Change of Name' => ['#3730a3', '#e0e7ff'],
        'Extension' => ['#155e75', '#cffafe'],
        'Recertification' => ['#3f6212', '#ecfccb'],
        'Sectional Titling' => ['#065f46', '#d1fae5'],
        'Amendment' => ['#075985', '#e0f2fe'],
        'Separation' => ['#9f1239', '#ffe4e6'],
        'Surrendered' => ['#9d174d', '#fce7f3'],
        'Litigation' => ['#9a3412', '#ffedd5'],
        'Flagged' => ['#b91c1c', '#fee2e2'],
        'Cancelled' => ['#7f1d1d', '#fecaca'],
        'Revoked' => ['#ffffff', '#b91c1c'],
        'Withdrawn' => ['#374151', '#e5e7eb'],
        'Closed' => ['#334155', '#e2e8f0'],
        'Superseded' => ['#854d0e', '#fef9c3'],
    ];

    private const OTHER = ['#4b5563', '#f3f4f6'];

    /** Title Status keys (title_status values) => cause. */
    private const TITLE_STATUS = [
        'resettled' => 'Resettled', 'subdivision' => 'Subdivision', 'regranted' => 'Re-grant',
        'change_of_name' => 'Change of Name', 'change_of_purpose' => 'Change of Purpose', 'merger' => 'Merger',
        'flagged' => 'Flagged', 'cancelled' => 'Cancelled', 'revoked' => 'Revoked', 'withdrawn' => 'Withdrawn',
        'amendment' => 'Amendment', 'litigation' => 'Litigation', 'surrendered' => 'Surrendered',
        'separation' => 'Separation', 'closed' => 'Closed', 'extension' => 'Extension',
    ];

    /** Free-text openings => cause, checked in order (case-insensitive). */
    private const PATTERNS = [
        '/^(plot\s+)?subdivi/i' => 'Subdivision',
        '/^(plot\s+)?merge/i' => 'Merger',
        '/^change\s+of\s+purpose/i' => 'Change of Purpose',
        '/^change\s+of\s+name/i' => 'Change of Name',
        '/^re-?grant/i' => 'Re-grant',
        '/^(plot\s+)?extension/i' => 'Extension',
        '/recertification/i' => 'Recertification',
        '/^ST\s/' => 'Sectional Titling',
        '/^resettle/i' => 'Resettled',
        '/^supersede/i' => 'Superseded',
    ];

    /** The cause for a stored reason, or null when the reason is empty. */
    public static function of(?string $reason): ?string
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return null;
        }

        // "Title Status: subdivision, change_of_purpose — ..." : the first status named.
        if (preg_match('/^Title\s+Status:\s*([a-z_]+)/i', $reason, $m)) {
            $key = strtolower($m[1]);

            return self::TITLE_STATUS[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        // "Manual Linkage: Merger → ..." : judge what follows the label.
        $body = preg_replace('/^Manual\s+Linkage:\s*/i', '', $reason);
        foreach (self::PATTERNS as $pattern => $cause) {
            if (preg_match($pattern, $body)) {
                return $cause;
            }
        }

        // Unrecognised: its opening words, up to an arrow, colon, dash or " to ".
        $head = preg_split('/\s*(→|->|:|—|\s-\s|\sto\s)\s*/u', $body, 2)[0];

        return mb_strlen($head) > 24 ? rtrim(mb_substr($head, 0, 24)) . '…' : $head;
    }

    /** @return array{0:string, 1:string} text and background colour for a cause */
    public static function colours(?string $cause): array
    {
        return self::COLOURS[$cause] ?? self::OTHER;
    }
}
