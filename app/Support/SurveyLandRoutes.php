<?php

namespace App\Support;

use App\Http\Middleware\SurveyLandRecommendation;
use Illuminate\Support\Facades\Route;

/**
 * Letter of Grant under Survey (CR 03112026 item 10): the Lands Land Recommendation and RofO screens, with
 * every tool, on the same controllers. Each Lands route is repeated under Survey's own address and name, so
 * a tool added to Lands is here too. The views are Survey's own copies (survey_module/letter_of_grant), and
 * SurveyLandRecommendation keeps the pages in Survey. Lands' routes and views are not changed.
 */
class SurveyLandRoutes
{
    public const PREFIX = 'survey-module/letter-of-grant';
    public const NAME = 'survey-module.lg.';

    public static function mirror(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! preg_match('/^land-(recommendations|rofos)\./', $name)) continue;

            Route::match(array_values(array_diff($route->methods(), ['HEAD'])), self::PREFIX . '/' . $route->uri(), $route->getAction('uses'))
                ->name(self::NAME . $name)
                ->middleware(array_merge((array) $route->middleware(), [SurveyLandRecommendation::class]))
                ->where($route->wheres);
        }
    }
}
