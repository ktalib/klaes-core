<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Which public portal a request belongs to, from its path (user, 2026-11-11: an error page on a portal showed the
 * main site's card, and its Home / Sign In took the user out of the portal to the main KLAES site). The error pages
 * use it so every link stays inside the portal. Null for the staff site.
 */
class PortalContext
{
    /** path prefix => [name, home route, sign-in route or null] */
    private const PORTALS = [
        'mlpp-sg'             => ['MLPP-SG Portal', 'surveyor-portal.home', 'surveyor-portal.login'],
        'kssgof'              => ['MLPP-SG Portal', 'surveyor-portal.home', 'surveyor-portal.login'],   // old address, redirects
        'laas'                => ['LAAS Portal', 'laas.landing', 'laas.login'],
        // ALAES (the Abia copy) is left out on purpose (user, 2026-11-11: "don't mind about ALAES, just leave it").
        'online-legal-search' => ['Online Legal Search', 'ols.landing', null],
        'phs'                 => ['PHS Portal', 'phs.landing', 'phs.login'],
    ];

    /** @return array{name:string, prefix:string, home:string, login:?string, back:string}|null */
    public static function fromRequest(?Request $request = null): ?array
    {
        $request ??= request();
        $first = strtolower((string) explode('/', trim($request->path(), '/'))[0]);
        if (! isset(self::PORTALS[$first])) return null;

        [$name, $home, $login] = self::PORTALS[$first];
        $homeUrl = Route::has($home) ? route($home) : url('/' . $first);
        $loginUrl = $login && Route::has($login) ? route($login) : null;

        // "Go Back" only to a page of the same portal; anything else (the main site, another portal) goes home.
        $previous = url()->previous();
        $prevPath = strtolower(trim((string) parse_url($previous, PHP_URL_PATH), '/'));
        $back = ($prevPath === $first || str_starts_with($prevPath, $first . '/')) && $previous !== $request->fullUrl() ? $previous : $homeUrl;

        return ['name' => $name, 'prefix' => $first, 'home' => $homeUrl, 'login' => $loginUrl, 'back' => $back];
    }
}
