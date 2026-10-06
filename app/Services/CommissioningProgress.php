<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Live "X of Y files" progress for a long commissioning run.
 *
 * The browser sends a random progress_token with the request and polls
 * GET commissioning-progress/{token} while it waits. The request writes its
 * count to the cache as it goes.
 *
 * Runs nest: a duplex opens the run with its whole total and then calls the batch
 * engine chunk by chunk. The engine's begin() sees the run already open, so it
 * reports into it, and setBase() shifts the engine's per-chunk count onto the
 * duplex's running total. State is static because it lives for one request.
 */
class CommissioningProgress
{
    private const TTL = 3600;

    private static ?string $token = null;
    private static int $total = 0;
    private static int $base = 0;
    private static string $stage = '';
    private static float $lastWrite = 0.0;

    /** Open a run. Returns true only to the caller that opened it (it must finish()). */
    public static function begin(?string $token, int $total, string $phase = 'Starting…', string $stage = ''): bool
    {
        if (self::$token !== null) {
            return false;
        }

        $token = self::cleanToken($token);
        if ($token === null) {
            return false;
        }

        self::$token = $token;
        self::$total = max(0, $total);
        self::$base = 0;
        self::$stage = $stage;
        self::write(0, $phase, true);

        return true;
    }

    public static function active(): bool
    {
        return self::$token !== null;
    }

    public static function base(): int
    {
        return self::$base;
    }

    /** Files already done before the current inner run (a duplex chunk) started. */
    public static function setBase(int $base, ?string $stage = null): void
    {
        self::$base = max(0, $base);
        if ($stage !== null) {
            self::$stage = $stage;
        }
        if (self::$token !== null) {
            self::write(0, null, true);
        }
    }

    /** $done counts files within the current inner run; the base is added on. */
    public static function report(int $done, ?string $phase = null): void
    {
        if (self::$token === null) {
            return;
        }
        self::write($done, $phase, false);
    }

    public static function finish(string $phase = 'Done'): void
    {
        if (self::$token === null) {
            return;
        }
        self::$base = 0;
        self::write(self::$total, $phase, true, true);
        self::$token = null;
    }

    public static function fail(string $message): void
    {
        if (self::$token === null) {
            return;
        }
        $current = Cache::get(self::key(self::$token)) ?: [];
        Cache::put(self::key(self::$token), array_merge($current, [
            'failed' => true,
            'phase'  => $message,
            'at'     => now()->toIso8601String(),
        ]), self::TTL);
        self::$token = null;
    }

    public static function get(?string $token): ?array
    {
        $token = self::cleanToken($token);

        return $token === null ? null : Cache::get(self::key($token));
    }

    private static function write(int $done, ?string $phase, bool $force, bool $finished = false): void
    {
        // A batch reports once per file; the browser polls about once a second, so
        // writing more often than a few times a second only costs disk.
        $now = microtime(true);
        if (!$force && ($now - self::$lastWrite) < 0.3) {
            return;
        }
        self::$lastWrite = $now;

        $key = self::key(self::$token);
        $previous = Cache::get($key) ?: [];

        Cache::put($key, [
            'done'     => min(self::$total ?: PHP_INT_MAX, self::$base + max(0, $done)),
            'total'    => self::$total,
            'phase'    => $phase ?? ($previous['phase'] ?? ''),
            'stage'    => self::$stage,
            'finished' => $finished,
            'failed'   => false,
            'at'       => now()->toIso8601String(),
        ], self::TTL);
    }

    private static function cleanToken(?string $token): ?string
    {
        $token = trim((string) $token);

        return preg_match('/^[A-Za-z0-9_-]{8,64}$/', $token) ? $token : null;
    }

    private static function key(string $token): string
    {
        return 'commissioning-progress:' . $token;
    }
}
