<?php

namespace App\Support;

/**
 * The three Survey field apps (user, 2026-10-30). Each is its own Flutter WebView
 * APK (C:\xampp\htdocs\klaes_app\SCF and its clones) that opens the same Survey
 * mobile pages at /survey-module/mobile/login?app=<key>. The app a phone runs is
 * remembered in the session (each APK keeps its own cookies), and decides the
 * login branding, home page, menu and footer tabs.
 *
 *   scf   SCF Mobile: compensation fieldwork (register cases, farmers, trees, photos)
 *   cfo   Computation & Field Observations: readings, coordinates, beacon check,
 *         computation and Sketch Plan for an Instruction to Survey / Private report
 *   plot  Plot Confirmation: show an OP plot to its allottee (Showing History), and
 *         ground-check a plot against its layout and the Beacon Register
 */
final class SurveyMobileApps
{
    public const SESSION = 'survey_mobile_app';

    public const DEFAULT = 'scf';

    public const APPS = [
        'scf' => [
            'name'    => 'SCF Mobile',
            'title'   => 'SURVEY MOBILE',
            'tagline' => 'Register Compensation Case',
            'home'    => 'survey-module.mobile.index',
            'icon'    => 'fa-compass',
        ],
        'cfo' => [
            'name'    => 'Field Observations',
            'title'   => 'FIELD OBSERVATIONS',
            'tagline' => 'Computation & Field Observations',
            'home'    => 'survey-module.mobile.observations',
            'icon'    => 'fa-compass-drafting',
        ],
        'plot' => [
            'name'    => 'Plot Confirmation',
            'title'   => 'PLOT CONFIRMATION',
            'tagline' => 'Show OP plots · Ground checks',
            'home'    => 'survey-module.mobile.plots.index',
            'icon'    => 'fa-map-location-dot',
        ],
    ];

    public static function valid(?string $key): bool
    {
        return $key !== null && isset(self::APPS[$key]);
    }

    /** The app this phone runs; SCF when nothing says otherwise (the first app). */
    public static function current(): string
    {
        $key = session(self::SESSION);

        return self::valid($key) ? $key : self::DEFAULT;
    }

    /** Remember the app named in ?app= (the APK's start URL). */
    public static function remember(?string $key): void
    {
        if (self::valid($key)) {
            session([self::SESSION => $key]);
        }
    }

    public static function get(?string $key = null): array
    {
        $key = $key ?? self::current();

        return self::APPS[$key] + ['key' => $key];
    }

    public static function home(?string $key = null): string
    {
        return route(self::get($key)['home']);
    }
}
