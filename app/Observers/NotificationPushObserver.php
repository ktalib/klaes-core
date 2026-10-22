<?php

namespace App\Observers;

use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Models\Notification;
use App\Models\User;
use App\Services\Push\FcmPushService;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors new file-tracking notifications to the user's phones as push
 * notifications. Fires only once the row is committed (a rolled-back
 * workflow never buzzes anyone) and sends after the response, so FCM
 * latency never sits inside a workflow's transaction.
 */
class NotificationPushObserver
{
    public $afterCommit = true;

    public function created(Notification $notification): void
    {
        // Template rows (no recipient) share this table.
        if (!$notification->user_id || !MobileNotificationController::belongsOnMobile($notification)) {
            return;
        }

        $push = app(FcmPushService::class);
        if (!$push->enabled()) {
            return;
        }

        $userId = (int) $notification->user_id;
        $id = $notification->id;
        $type = (string) $notification->type;
        $title = (string) ($notification->title ?: $notification->subject ?: 'KLAES FTM');
        $body = (string) ($notification->body ?: $notification->message ?: '');

        app()->terminating(function () use ($push, $userId, $id, $type, $title, $body) {
            try {
                // The app shows File Request notifications to SCB Monitors only.
                if ($type === 'file_search_request') {
                    $user = User::find($userId);
                    if (!$user || !($user->isSuperAdmin() || ($user->fr_permissions ?? '') === 'SCB')) {
                        return;
                    }
                }

                $push->sendToUser($userId, $title, $body, [
                    'notification_id' => $id,
                    'type'            => $type,
                    'path'            => config('push.open_path'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Push for notification failed', ['notification_id' => $id, 'error' => $e->getMessage()]);
            }
        });
    }
}
