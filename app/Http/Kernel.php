<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\Cors::class, // Add this line
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\Verify2FA::class,
            \App\Http\Middleware\RequireLoginOtp::class,
            \App\Http\Middleware\RequireProfilePhoto::class,
            // Second gate, and it defers to the first: a user is asked for their
            // photo, then for their phone number, never both at once.
            \App\Http\Middleware\RequirePhoneVerification::class,
            /*
             | Module permissions. Last in the group on purpose: it asks who the user is and
             | what module the route belongs to, so it must run after the session is started
             | and after the two account gates above have had their say.
             |
             | Fail-open for routes config/module_permissions.php does not map, recording them
             | instead, until strict_routes is turned on. See the middleware's own note.
             */
            \App\Http\Middleware\EnforceModulePermission::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        // Sanctum ships these but does not register them. Without the aliases a
        // token's abilities are decorative: `createToken($name, ['spas-mobile'])`
        // records the ability and nothing ever checks it, so a token minted for
        // one client works against every other client's endpoints.
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        'XSS' => \App\Http\Middleware\XSS::class,
        'track.activity' => \App\Http\Middleware\TrackActivityLog::class,
        'phs.admin' => \App\Http\Middleware\EnsurePhsSuperAdmin::class,
        // The LAAS Portal's sign-in-code backstop. Route middleware, not part of
        // the `web` group: it applies only behind `auth:laas`. See the class.
        'laas.otp' => \App\Http\Middleware\RequireLaasLoginOtp::class,
        // The same for the PHS Portal, behind `auth:phs`.
        'phs.otp' => \App\Http\Middleware\RequirePhsLoginOtp::class,
        // Holds a PHS member at the mobile-number card until one is proved.
        // Runs after phs.otp so the two cards are never shown at once.
        'phs.phone' => \App\Http\Middleware\RequirePhsPhone::class,
        'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
    ];
}
