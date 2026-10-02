<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * TrackActivityLog Middleware
 * 
 * Automatically tracks user activity on authenticated routes.
 * Records IP address, device, browser, and platform information.
 * Updates heartbeat to keep session alive.
 */
class TrackActivityLog
{
    /**
     * List of routes to exclude from tracking
     */
    protected $excludedRoutes = [
        'api/activity/heartbeat',      // Don't track heartbeat requests
        'api/activity/online',         // Don't track online user queries
        'sanctum/csrf-cookie',         // Don't track CSRF cookie requests
    ];

    /**
     * List of URI patterns to exclude from tracking
     */
    protected $excludedPatterns = [
        '#\.(?:css|js|png|jpg|jpeg|gif|ico|svg)$#i',  // Static assets
        '#^health.*#i',                               // Health checks
        '#^api/health.*#i',                           // API health
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Only track authenticated users
        if (Auth::check()) {
            $user = Auth::user();

            // Check if route should be excluded
            if (!$this->shouldExclude($request)) {
                try {
                    // Update heartbeat for existing session
                    ActivityLogService::updateHeartbeat(
                        $user,
                        $request->input('file_number')  // Optional file number from request
                    );

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "Failed to update heartbeat in middleware: {$e->getMessage()}"
                    );
                    // Don't throw - allow request to continue even if tracking fails
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if the current route should be excluded from tracking
     */
    private function shouldExclude(Request $request): bool
    {
        $path = $request->path();

        // Check excluded routes
        foreach ($this->excludedRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        // Check excluded patterns
        foreach ($this->excludedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
