<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log for authenticated users
        if (Auth::check()) {
            $this->logUserActivity($request);
        }

        return $response;
    }

    /**
     * Log user activity
     */
    private function logUserActivity(Request $request)
    {
        try {
            $user = Auth::user();
            $sessionId = session()->getId();
            
            // Check if there's already an active session for this user
            $existingLog = UserActivityLog::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->where('is_online', true)
                ->first();

            if (!$existingLog) {
                // Fallback: create a fresh login record for this session
                ActivityLogService::recordLogin($user);
            } else {
                // Update last activity time and keep session marked online
                $existingLog->update([
                    'last_seen_at' => now(),
                    'status' => 'Online',
                    'is_online' => true,
                ]);
            }

            // Mark old sessions as offline (sessions older than 30 minutes without activity)
            $staleSessions = UserActivityLog::where('user_id', $user->id)
                ->where('is_online', true)
                ->where('updated_at', '<', now()->subMinutes(30))
                ->update([
                    'is_online' => false,
                    'logout_time' => now(),
                    'status' => 'Offline',
                ]);

            if ($staleSessions > 0) {
                ActivityLogService::flushOnlineCaches();
            }

        } catch (\Exception $e) {
            // Log the error but don't break the application
            \Log::error('Failed to log user activity: ' . $e->getMessage());
        }
    }
}