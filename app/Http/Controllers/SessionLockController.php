<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\UserSessionLock;
use Carbon\Carbon;

class SessionLockController extends Controller
{
    /**
     * Check session status and return lock state
     */
    public function checkSession(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'logged_out'], 401);
        }

        $sessionLock = UserSessionLock::where('user_id', Auth::id())
            ->where('session_id', session()->getId())
            ->first();

        if (!$sessionLock) {
            // Create new session lock record
            $sessionLock = UserSessionLock::create([
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'last_activity' => Carbon::now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Check if should be logged out (15 minutes)
        if ($sessionLock->shouldBeLoggedOut()) {
            $sessionLock->delete();
            Auth::logout();
            return response()->json(['status' => 'logged_out'], 401);
        }

        // Check if should be locked (3 minutes)
        if ($sessionLock->shouldBeLocked()) {
            $sessionLock->lockSession();
            return response()->json(['status' => 'locked']);
        }

        return response()->json([
            'status' => 'active',
            'is_locked' => $sessionLock->is_locked
        ]);
    }

    /**
     * Update session activity
     */
    public function updateActivity(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'logged_out'], 401);
        }

        $sessionLock = UserSessionLock::where('user_id', Auth::id())
            ->where('session_id', session()->getId())
            ->first();

        if ($sessionLock) {
            $sessionLock->updateActivity();
        }

        return response()->json(['status' => 'updated']);
    }

    /**
     * Unlock session with password verification
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Auth::check()) {
            return response()->json(['status' => 'logged_out'], 401);
        }

        $user = Auth::user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid password'
            ], 422);
        }

        $sessionLock = UserSessionLock::where('user_id', $user->id)
            ->where('session_id', session()->getId())
            ->first();

        if ($sessionLock) {
            $sessionLock->unlockSession();
        }

        return response()->json(['status' => 'unlocked']);
    }

    /**
     * Force logout
     */
    public function forceLogout(Request $request)
    {
        if (Auth::check()) {
            $sessionLock = UserSessionLock::where('user_id', Auth::id())
                ->where('session_id', session()->getId())
                ->first();

            if ($sessionLock) {
                $sessionLock->delete();
            }

            Auth::logout();
        }

        return response()->json(['status' => 'logged_out']);
    }
}
