<?php

namespace App\Http\Middleware;

use App\Support\SurveyMobileApps;
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
            
            // Survey Mobile — Register Compensation Case
            if ($request->is('survey-module/mobile*') && ! $request->is('survey-module/mobile/login')) {
                SurveyMobileApps::remember(SurveyMobileApps::fromRequest($request));

                return route('survey-module.mobile.login', ['app' => SurveyMobileApps::current()]);
            }

            // Address lookups called from the Survey field apps must return to the same app.
            if ($request->is('survey-module/lookup/*') && SurveyMobileApps::valid($request->query('app'))) {
                SurveyMobileApps::remember($request->query('app'));

                return route('survey-module.mobile.login', ['app' => SurveyMobileApps::current()]);
            }

            // Check for Generic Mobile File Tracker (exclude login page itself)
            if ($request->is('mobile*') && ! $request->is('mobile/login')) {
                return route('mobile.login');
            }

            // PHS Portal (institutional SaaS) — send to its own login, not staff login.
            if ($request->is('phs*') && ! $request->is('phs/login') && ! $request->is('phs/register') && ! $request->is('phs')) {
                return route('phs.login');
            }

            // MLPP-SG portal (was KSSGOF): its own sign-in, not the staff login.
            if ($request->is('mlpp-sg*', 'kssgof*')) {
                return route('surveyor-portal.login');
            }

            return route('login');
        }
    }
}
