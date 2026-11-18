<?php

namespace App\Services\Desktop;

use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The web's sign-in code (LoginOtpService) for the desktop app, unchanged: same code, same SMS / email rules, quiet
 * hours, wrong-try limit and reuse window. The service keeps the half-finished sign-in in the session; the desktop has
 * no browser session, so each sign-in attempt gets a stand-in session kept in the cache under a random challenge id
 * that the app sends back with the code.
 */
class DesktopSignIn
{
    private const CACHE = 'desktop.signin.';

    public function __construct(private LoginOtpService $otp)
    {
    }

    /** Attach a session to the request: the challenge's own (if any) or a new empty one. */
    public function attach(Request $request, ?string $challenge = null): ?string
    {
        $store = new Store('desktop', new ArraySessionHandler(60));
        $store->start();
        if ($challenge !== null) {
            $saved = Cache::get(self::CACHE . $challenge);
            if (! is_array($saved)) {
                return null;
            }
            $store->replace($saved);
        }
        $request->setLaravelSession($store);

        return $challenge ?? Str::random(48);
    }

    public function save(Request $request, string $challenge): void
    {
        $minutes = max(1, (int) config('phone_verification.login_otp.challenge_minutes', 30));
        Cache::put(self::CACHE . $challenge, $request->session()->all(), now()->addMinutes($minutes));
    }

    public function forget(string $challenge): void
    {
        Cache::forget(self::CACHE . $challenge);
    }

    public function otp(): LoginOtpService
    {
        return $this->otp;
    }
}
