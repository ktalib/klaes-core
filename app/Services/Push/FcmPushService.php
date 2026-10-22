<?php

namespace App\Services\Push;

use App\Models\MobilePushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to the KLAES FTM app via the FCM HTTP v1 API.
 *
 * Auth is a Google service-account key: a JWT signed with its private key is
 * swapped for an OAuth access token (cached ~55 min). No SDK dependency.
 * Every failure is logged and swallowed — a push is a courtesy on top of the
 * in-app notification row, never something a workflow should fail over.
 */
class FcmPushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_CACHE_KEY = 'fcm.access_token';

    private ?array $credentials = null;

    public function enabled(): bool
    {
        return (bool) config('push.enabled') && $this->credentials() !== null;
    }

    /**
     * Push to every device the user is signed in on. Returns how many sends succeeded.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        $tokens = MobilePushToken::where('user_id', $userId)->get();
        $sent = 0;
        foreach ($tokens as $row) {
            if ($this->sendToToken($row, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToToken(MobilePushToken $row, string $title, string $body, array $data = []): bool
    {
        $access = $this->accessToken();
        if (!$access) {
            return false;
        }

        // Data-only: the app draws the notification itself, which is the only way
        // to show the KLAES logo as the large icon whether or not it is open.
        // HIGH priority wakes the app's background handler to do that.
        $message = [
            'token'   => $row->token,
            // FCM data values must be strings.
            'data'    => array_map('strval', array_filter(
                ['title' => $title, 'body' => $body] + $data,
                fn ($v) => is_scalar($v)
            )),
            'android' => [
                'priority' => 'HIGH',
                'ttl'      => '86400s',
            ],
        ];

        try {
            $response = Http::withToken($access)
                ->timeout((int) config('push.timeout', 5))
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", ['message' => $message]);
        } catch (\Throwable $e) {
            Log::warning('FCM push failed', ['user_id' => $row->user_id, 'error' => $e->getMessage()]);
            return false;
        }

        if ($response->successful()) {
            $row->forceFill(['last_pushed_at' => now(), 'last_error' => null])->save();
            return true;
        }

        $detail = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
        $badToken = $response->status() === 400 && str_contains((string) $response->json('error.message'), 'registration token');

        // The app was uninstalled, the token rotated, or it was never valid: drop it for good.
        if ($response->status() === 404 || $detail === 'UNREGISTERED' || $badToken) {
            $row->delete();
            return false;
        }

        // A rejected access token: drop the cache so the next send fetches a fresh one.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        $row->forceFill(['last_error' => mb_substr($response->status() . ' ' . ($response->json('error.message') ?? $response->body()), 0, 500)])->save();
        Log::warning('FCM push rejected', ['user_id' => $row->user_id, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

        return false;
    }

    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $creds = $this->credentials();
        if (!$creds) {
            return null;
        }

        $now = time();
        $segments = [
            $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->b64(json_encode([
                'iss'   => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ])),
        ];

        $signature = '';
        if (!openssl_sign(implode('.', $segments), $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('FCM: could not sign the service-account JWT — check the private key in the credentials file.');
            return null;
        }
        $segments[] = $this->b64($signature);

        try {
            $response = Http::asForm()->timeout((int) config('push.timeout', 5))
                ->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => implode('.', $segments),
                ]);
        } catch (\Throwable $e) {
            Log::warning('FCM: OAuth token request failed', ['error' => $e->getMessage()]);
            return null;
        }

        $token = $response->json('access_token');
        if (!$response->successful() || !$token) {
            Log::warning('FCM: OAuth token request rejected', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
            return null;
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials ?: null;
        }

        $path = (string) config('push.credentials');
        $json = ($path !== '' && is_file($path)) ? json_decode((string) file_get_contents($path), true) : null;

        $this->credentials = (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])
            && ($json['project_id'] ?? config('push.project_id'))) ? $json : [];

        return $this->credentials ?: null;
    }

    private function projectId(): string
    {
        return (string) (config('push.project_id') ?: $this->credentials()['project_id']);
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
