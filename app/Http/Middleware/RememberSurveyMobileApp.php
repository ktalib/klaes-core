<?php

namespace App\Http\Middleware;

use App\Support\SurveyMobileApps;
use Closure;
use Illuminate\Http\Request;

class RememberSurveyMobileApp
{
    public function handle(Request $request, Closure $next)
    {
        $app = SurveyMobileApps::fromRequest($request);
        SurveyMobileApps::remember($app);

        $response = $next($request);

        return $response->withCookie(cookie(
            SurveyMobileApps::COOKIE,
            $app,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));
    }
}
