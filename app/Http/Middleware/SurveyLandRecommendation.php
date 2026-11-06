<?php

namespace App\Http\Middleware;

use App\Support\SurveyLandRoutes;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Keeps Survey's Letter of Grant pages in Survey (CR 03112026 item 10). Lands' controllers do the work;
 * here their views resolve to Survey's copies, saves are Direct (recording: "Direct only, no conversion"),
 * and a redirect or link back to a Lands page stays on Survey's address.
 *
 * Survey's lists also show Lands' Direct records (user, 2026-11-06). Those are view only here: anything that would
 * change or print one is refused, because the record is Lands' own. Lands' own routes are not affected.
 */
class SurveyLandRecommendation
{
    /** GET pages that edit, or put a letter on paper. Show and the white copy stay open: they change nothing. */
    private const CHANGING_GETS = ['land-recommendations.edit', 'land-recommendations.batch-edit', 'land-recommendations.print', 'land-recommendations.batch-print', 'land-rofos.print'];

    /** POSTs that only read, and those that make a new record of Survey's own. */
    private const FREE_POSTS = ['land-recommendations.batch-file-details', 'land-recommendations.client-log', 'land-recommendations.store', 'land-recommendations.store-batch',
        'land-recommendations.batch-drafts.store', 'land-recommendations.batch-drafts.destroy', 'land-rofos.batch-print-status', 'land-rofos.batch-white-copy', 'land-rofos.issue-dates', 'land-rofos.batch-security-paper.preview'];

    public const VIEW_ONLY = 'This record belongs to Lands. It is view only here; make any change under Lands.';

    public function handle(Request $request, Closure $next)
    {
        if ($this->touchesLandsRecord($request)) {
            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => self::VIEW_ONLY], 403)
                : redirect()->route(SurveyLandRoutes::NAME . 'land-recommendations.index', ['type' => 'ROFO'])->with('error', self::VIEW_ONLY);
        }

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

    /** Would this request change or print a record that is Lands' own? */
    private function touchesLandsRecord(Request $request): bool
    {
        $route = $request->route();
        $name = substr((string) $route?->getName(), strlen(SurveyLandRoutes::NAME));
        $changes = $request->isMethodSafe() ? in_array($name, self::CHANGING_GETS, true) : ! in_array($name, self::FREE_POSTS, true);
        if (! $changes) return false;

        $id = $route->parameter('id') ?? $route->parameter('land_recommendation');
        $ids = array_filter(array_merge((array) $request->input('ids', []), $id !== null ? [$id] : []), 'is_numeric');
        $batch = $route->parameter('batchId') ?? $request->input('batch_id');
        $file = trim((string) $request->input('file_number', ''));
        if (! $ids && ! $batch && $file === '') return false;

        return DB::connection('sqlsrv')->table('land_recommendations')->whereNull('origin_module')
            ->where(function ($q) use ($ids, $batch, $file) {
                if ($ids) $q->orWhereIn('id', $ids);
                if ($batch) $q->orWhere('rofo_batch_id', $batch);
                if ($file !== '') $q->orWhere('file_number', $file);
            })->exists();
    }

    /** A Lands address as Survey's. Anything else is left alone. */
    public static function onSurvey(string $url): string
    {
        return preg_replace('#^((?:https?://[^/]+)?)/(land-(?:recommendations|rofos))(?=[/?]|$)#', '$1/' . SurveyLandRoutes::PREFIX . '/$2', $url);
    }
}
