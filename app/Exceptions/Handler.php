<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use App\Support\SurveyMobileApps;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('survey-module/mobile*')) {
                return null;
            }
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $appKey = SurveyMobileApps::fromRequest($request);
            $loginUrl = route('survey-module.mobile.login', ['app' => $appKey]);
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Sign in again to continue.',
                    'session_expired' => true,
                    'redirect' => $loginUrl,
                ], 419)->header('Cache-Control', 'no-store');
            }

            $response = response()->view('survey_module.mobile.session_expired', [
                'app' => SurveyMobileApps::get($appKey),
            ], 419);

            return $response->header('Cache-Control', 'no-store');
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Determine if the exception handler response should be JSON.
     *
     * Force JSON responses for any route under the /api prefix so Postman
     * requests without explicit Accept headers do not get redirected to the
     * login view.
     */
    protected function shouldReturnJson($request, Throwable $e)
    {
        if ($request->is('api/*')) {
            return true;
        }

        return parent::shouldReturnJson($request, $e);
    }
}
