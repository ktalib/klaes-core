<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Check for VFC Mobile App (exclude login page itself from redirection)
            if ($request->is('valuation-compensations/mobile*') && ! $request->is('valuation-compensations/mobile/login')) {
                return route('valuation-compensations.mobile.login');
            }
            
            // Check for Generic Mobile File Tracker (exclude login page itself)
            if ($request->is('mobile*') && ! $request->is('mobile/login')) {
                return route('mobile.login');
            }

            // PHS Portal (institutional SaaS) — send to its own login, not staff login.
            if ($request->is('phs*') && ! $request->is('phs/login') && ! $request->is('phs/register') && ! $request->is('phs')) {
                return route('phs.login');
            }

            return route('login');
        }
    }
}
