<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XSS
{
    use \RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(\Auth::check())
        {
            \App::setLocale(\Auth::user()->lang);
            $timezone= getSettingsValByName('timezone');
            \Config::set('app.timezone', $timezone);

            // Only check for migrations if updater is enabled
            $updaterEnabled = config('installer.updaterEnabled');
            if($updaterEnabled === 'true' || $updaterEnabled === true)
            {
                try {
                    $directoryMigrations = $this->getMigrations();
                    $databaseMigrations = $this->getExecutedMigrations();
                    $total = count($directoryMigrations) - count($databaseMigrations);
                    
                    // Only redirect if there are actually pending migrations
                    // and the route exists (to prevent 404 errors)
                    if($total > 0 && \Route::has('LaravelUpdater::welcome'))
                    {
                        return redirect()->route('LaravelUpdater::welcome');
                    }
                } catch (\Exception $e) {
                    // If there's an error checking migrations, log it but don't break the app
                    \Log::warning('Migration check failed in XSS middleware: ' . $e->getMessage());
                }
            }
        }

        $data = $request->all();
        array_walk_recursive(
            $data, function (&$data){
            // $data = strip_tags($data);
        }
        );
        $request->merge($data);
        return $next($request);
    }
}
