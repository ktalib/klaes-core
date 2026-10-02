<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Jobs\SendStaffAttendanceSms;
use App\Models\StaffSmsLog;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\StaffAttendanceNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogUserLogin
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
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        // Staff sign-ins only. The portal guards (phs, online_ls, laas) fire this
        // same event, and their users are not App\Models\User — user_activity_logs
        // has a foreign key to users.id, so logging a portal account here either
        // violates that key or, worse, attributes the activity to whichever STAFF
        // user happens to hold the same id.
        if (!$event->user instanceof User) {
            return;
        }

        // Sign-ins a person performed, only. tinker, artisan, queue workers and
        // the scheduler all reach Auth::login() and fire this same event: 56 of
        // the first 66 "Sign-in recorded" cards came from scripts on the dev
        // machine, and each one also closed the user's live session with
        // "Session closed due to new login" — showing them Offline while they sat
        // in the app and truncating the day's attendance duration. Nothing below
        // this line should happen for a login nobody performed.
        if (!ActivityLogService::isInteractiveSignIn()) {
            // Left visible on purpose. A request with no User-Agent at all is
            // treated as a script here, so if a real client ever loses its
            // sign-in row this line is what says why.
            Log::debug('Skipped a non-interactive sign-in', [
                'user_id' => $event->user->id ?? null,
                'console' => app()->runningInConsole(),
                'user_agent' => request()->header('User-Agent'),
            ]);

            return;
        }

        /** @var User $user */
        $user = $event->user;
        $session = null;

        // A remember-me cookie re-authenticating an expired session is not a
        // sign-in the user carried out — Laravel fires this event from
        // SessionGuard::user() when it picks the recaller up. The session row
        // below is still worth writing (Online Users should show them), but
        // telling somebody they signed in when they only reloaded a page is
        // exactly the "alert while I am still online" complaint.
        $guard = Auth::guard($event->guard);
        $viaRemember = method_exists($guard, 'viaRemember') && $guard->viaRemember();

        try {
            $session = ActivityLogService::recordLogin($user);

            if (!$session) {
                Log::warning('Login event fired but activity log entry was not created', [
                    'user_id' => $event->user->id,
                    'reason' => 'recordLogin returned null',
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to record login activity', [
                'user_id' => $event->user->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }

        // The session row above is worth keeping for a recaller re-authentication.
        // The announcements below are not: both tell the user they signed in, and
        // they did not.
        if ($viaRemember) {
            return;
        }

        // The in-app notice. Written here and now rather than after the
        // response: the post-login flash (notification-flash.js) asks for
        // unread notifications as soon as the dashboard paints and would miss a
        // row written later. Unlike the SMS below it fires on EVERY sign-in,
        // and the session row carries the IP and browser the notice names — a
        // failed recordLogin above only costs those details, not the notice.
        try {
            app(StaffAttendanceNotificationService::class)->login($user, $session);
        } catch (\Throwable $exception) {
            Log::error('Failed to record login notification', [
                'user_id' => $event->user->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }

        // The day's sign-in SMS. Queued to run after the response so a slow
        // gateway can never hold up the sign-in itself; the once-a-day check
        // lives in StaffAttendanceSmsService, not here.
        try {
            SendStaffAttendanceSms::queueFor($event->user->id, StaffSmsLog::TYPE_LOGIN);
        } catch (\Throwable $exception) {
            Log::error('Failed to queue login SMS', [
                'user_id' => $event->user->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}