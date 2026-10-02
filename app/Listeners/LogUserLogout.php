<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Jobs\SendStaffAttendanceSms;
use App\Models\StaffSmsLog;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use App\Services\StaffAttendanceNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogUserLogout
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Logout  $event
     * @return void
     */
    public function handle(Logout $event)
    {
        // Staff sign-outs only — see the matching guard in LogUserLogin. This one
        // matters more: it UPDATEs user_activity_logs by user_id, so a portal
        // applicant signing out would mark the staff user of the same id offline.
        if (!$event->user instanceof User) {
            return;
        }

        // Sign-outs a person performed, only — the matching guard to the one in
        // LogUserLogin. A script calling Auth::logout() would otherwise mark a
        // member of staff offline mid-shift, close the session row that carries
        // their attendance duration, and text them that their day had ended.
        if (!ActivityLogService::isInteractiveSignIn()) {
            return;
        }

        try {
            $user = $event->user;
            $userId = null;

            if (is_object($user)) {
                if (method_exists($user, 'getAuthIdentifier')) {
                    $userId = $user->getAuthIdentifier();
                } elseif (method_exists($user, '__get') || property_exists($user, 'id')) {
                    $userId = data_get($user, 'id');
                }
            }

            if ($userId) {
                // Mark current session as logged out
                $updated = UserActivityLog::where('user_id', $userId)
                    ->where('is_online', true)
                    ->update([
                        'is_online' => false,
                        'logout_time' => now(),
                        'status' => 'Offline',
                    ]);

                if ($updated > 0) {
                    ActivityLogService::flushOnlineCaches();
                }

                // Log the logout activity
                UserActivityLog::logActivity($userId, 'logout', [
                    'activity_description' => 'User logged out successfully'
                ]);

                // The day's sign-out SMS. Queued to run after the response, and
                // held back until the user has reached the end of their own
                // shift — StaffAttendanceSmsService makes that call, so signing
                // out for lunch sends nothing and spends nothing.
                SendStaffAttendanceSms::queueFor((int) $userId, StaffSmsLog::TYPE_LOGOUT);

                // The in-app notice, which now applies the same shift-end
                // gate as the SMS above, so a lunchtime sign-out leaves no bell
                // entry either. This listener is also the ONLY remaining source
                // of a sign-out notice: the auto-logout and administrator
                // force-logout paths in ActivityLogService were both quietened,
                // because a message about a session the user did not close was
                // reaching people who were still working.
                app(StaffAttendanceNotificationService::class)->logout(
                    $user,
                    null,
                    StaffAttendanceNotificationService::MODE_MANUAL
                );
            }
        } catch (\Exception $e) {
            \Log::error('Failed to log user logout: ' . $e->getMessage());
        }
    }
}