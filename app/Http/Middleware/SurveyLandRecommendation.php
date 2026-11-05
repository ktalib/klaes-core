<?php

namespace App\Http\Middleware;

use App\Support\SurveyLandRoutes;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Keeps Survey's Letter of Grant pages in Survey (CR 03112026 item 10). Lands' controllers do the work;
 * here their views resolve to Survey's copies, saves are Direct (recording: "Direct only, no conversion"),
 * and a redirect or link back to a Lands page stays on Survey's address.
 */
class SurveyLandRecommendation
{
    public function handle(Request $request, Closure $next)
    {
        $finder = View::getFinder();
        $finder->prependLocation(resource_path('views/survey_module/letter_of_grant'));
        $finder->flush();

        if (! $request->isMethodSafe()) {
            $request->merge(['type' => 'Direct']);
        }

        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl(self::onSurvey($response->getTargetUrl()));
        } elseif ($response instanceof JsonResponse) {
            $to = '\\/' . str_replace('/', '\\/', SurveyLandRoutes::PREFIX);
            $response->setContent(preg_replace('#(?<!letter-of-grant)\\\\/land-(recommendations|rofos)\b#', $to . '\\\\/land-$1', $response->getContent()));
        }

        return $response;
    }

    /** A Lands address as Survey's. Anything else is left alone. */
    public static function onSurvey(string $url): string
    {
        return preg_replace('#^((?:https?://[^/]+)?)/(land-(?:recommendations|rofos))(?=[/?]|$)#', '$1/' . SurveyLandRoutes::PREFIX . '/$2', $url);
    }
}
