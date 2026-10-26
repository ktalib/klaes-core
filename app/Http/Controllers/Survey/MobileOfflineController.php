<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCasePhoto;
use App\Models\Survey\SurveyCompCase;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The SCF field app's offline support (user, 2026-10-25). The app is the mobile web
 * pages in a Flutter WebView; these endpoints let it work without a signal:
 *
 *   sw.js / manifest   the service worker (scoped to /survey-module/mobile/) that keeps
 *                      the pages and their assets on the phone, and the install manifest
 *   offline            the page shown for a screen that was never opened online
 *   session            a fresh CSRF token before each sync, and proof the officer is still signed in
 *   photos             geo-tagged case photos, uploaded one at a time after the case syncs
 *
 * Photos stay with the case (user decision), under storage/app/survey/case-photos/{case}/.
 */
class MobileOfflineController extends Controller
{
    /** Bump to make every phone refresh its cached pages and assets. */
    public const SW_VERSION = 'scf-2026-10-25-1';

    public function serviceWorker()
    {
        return response()
            ->view('survey_module.mobile.sw', ['version' => self::SW_VERSION, 'scope' => url('/survey-module/mobile') . '/'])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', '/survey-module/mobile/');
    }

    public function manifest()
    {
        return response()->json([
            'name'             => 'SCF Mobile · KLAES Survey',
            'short_name'       => 'SCF Mobile',
            'start_url'        => route('survey-module.mobile.index'),
            'scope'            => url('/survey-module/mobile') . '/',
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => '#29213D',
            'icons'            => [['src' => asset('assets/logo/SCF.png'), 'sizes' => '512x512', 'type' => 'image/png']],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function offline()
    {
        return view('survey_module.mobile.offline');
    }

    /** Called before every sync: a fresh token, or a 401 that tells the phone to sign in again. */
    public function session(Request $r)
    {
        return response()->json(['ok' => true, 'token' => csrf_token(), 'user' => $r->user()?->name])
            ->header('Cache-Control', 'no-store');
    }

    /** One photo for a case, with where and when it was taken. Safe to resend (client_uuid). */
    public function storePhoto(Request $r, SurveyCompCase $case)
    {
        $data = $r->validate([
            'photo'       => 'required|file|image|max:12288',
            'client_uuid' => 'nullable|string|max:40',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'accuracy_m'  => 'nullable|numeric|min:0|max:100000',
            'taken_at'    => 'nullable|date',
            'caption'     => 'nullable|string|max:255',
        ]);

        if (! empty($data['client_uuid']) && ($done = SurveyCasePhoto::where('client_uuid', $data['client_uuid'])->first())) {
            return response()->json(['ok' => true, 'id' => $done->id, 'duplicate' => true]);
        }

        $file = $r->file('photo');
        $name = (string) Str::uuid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $path = Storage::disk('local')->putFileAs('app/survey/case-photos/' . $case->id, $file, $name);

        try {
            $photo = SurveyCasePhoto::create([
                'survey_comp_case_id' => $case->id,
                'client_uuid'         => $data['client_uuid'] ?? null,
                'path'                => $path,
                'original_name'       => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime'                => $file->getMimeType(),
                'size'                => $file->getSize(),
                'latitude'            => $data['latitude'] ?? null,
                'longitude'           => $data['longitude'] ?? null,
                'accuracy_m'          => $data['accuracy_m'] ?? null,
                'taken_at'            => $data['taken_at'] ?? null,
                'caption'             => $data['caption'] ?? null,
            ]);
        } catch (QueryException $e) {
            // The same photo arrived twice at once: keep the first, drop this copy.
            Storage::disk('local')->delete($path);
            if (! empty($data['client_uuid']) && ($done = SurveyCasePhoto::where('client_uuid', $data['client_uuid'])->first())) {
                return response()->json(['ok' => true, 'id' => $done->id, 'duplicate' => true]);
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'id' => $photo->id]);
    }

    /** The photo itself, for the case card and the desktop case page. */
    public function showPhoto(SurveyCompCase $case, SurveyCasePhoto $photo)
    {
        abort_unless($photo->survey_comp_case_id === $case->id && Storage::disk('local')->exists($photo->path), 404);

        return response()->file(Storage::disk('local')->path($photo->path), [
            'Content-Type'  => $photo->mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
