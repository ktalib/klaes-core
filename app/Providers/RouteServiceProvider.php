<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware(['web', 'XSS'])
                ->prefix('api/mobile')
                ->group(function () {
                    foreach (glob(base_path('routes/mobile/*.php')) ?: [] as $mobileRouteFile) {
                        require $mobileRouteFile;
                    }
                });

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/apps.php'));
                
            Route::middleware('web')
                ->group(base_path('routes/apps2.php'));

            Route::middleware('web')
                ->group(base_path('routes/app3.php'));

            Route::middleware('web')
                ->group(base_path('routes/survey_module.php'));

            // Cadastral Department: registry, reports, charting/index cards,
            // plan & description. Separate from the legacy cadastral.* screens.
            Route::middleware('web')
                ->group(base_path('routes/cadastral_module.php'));

            Route::middleware('web')
                ->group(base_path('routes/phs.php'));

            Route::middleware('web')
                ->group(base_path('routes/online_legal_search.php'));

            Route::middleware('web')
                ->group(base_path('routes/laas.php'));

            Route::middleware('web')
                ->group(base_path('routes/bills_api.php'));

            Route::middleware('web')
                ->group(base_path('routes/land_registration.php'));

            Route::middleware('web')
                ->group(base_path('routes/configurable_entries.php'));

            Route::middleware('web')
                ->group(base_path('routes/vfs.php'));

            // KANGIS → Title Deed Plan Management: the back page of the ST CofO.
            Route::middleware('web')
                ->group(base_path('routes/tdp.php'));

            // PageTyping routes removed — file no longer present
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
