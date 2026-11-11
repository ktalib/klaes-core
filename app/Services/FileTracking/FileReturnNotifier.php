<?php

namespace App\Services\FileTracking;

use App\Models\FileTracker;
use App\Models\SmsSetting;
use App\Models\User;
use App\Services\Sms\KlaesSmsDispatcher;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "File returned" SMS + push (CR 11112026-2, item 2).
 *
 * When a logged-out file is logged back in to the registry as Log-in or Completed,
 * the officer it was logged out to is told the movement is closed: an SMS on
 * users.phone_number, and an in-app notification that NotificationPushObserver
 * mirrors to the KLAES FTM app as a push.
 *
 * The recipient is the officer on the tracker, never the clerk logging it back
 * (same rule as FileRequestSmsNotifier). No resolvable officer = no message.
 */
class FileReturnNotifier
{
    public const RETURN_STATUSES = ['Log-in', 'Completed'];

    public function __construct(
        private KlaesSmsDispatcher $dispatcher,
        private FileRequestSmsNotifier $requests,
        private UserNotificationService $notifications
    ) {
    }

    /** Never throws: a message must not break the log-back it reports on. */
    public function notifyReturned(FileTracker $tracker, string $status, string $registryName, string $logId): void
    {
        if (!in_array($status, self::RETURN_STATUSES, true)) {
            return;
        }

        try {
            $officerId = is_numeric($tracker->receiving_officer_id) ? (int) $tracker->receiving_officer_id : null;
            $officerName = trim((string) $tracker->receiving_officer_name);

            if ($officerId === null && $officerName === '') {
                return;
            }

            // Logging back a file that was out to yourself needs no message.
            if ($officerId !== null && $officerId === (int) Auth::id()) {
                return;
            }

            $fileNumber = (string) $tracker->file_number;
            $moment = $this->requests->smsMoment(now());

            $key = SmsSetting::KEY_FILE_RETURNED;
            if ($this->dispatcher->enabled($key)) {
                $this->dispatcher->queue($key, $this->requests->requesterPhone($officerId, $officerName ?: null), [
                    'FileNo' => $fileNumber,
                    'Registry' => $registryName,
                    'DateTime' => $moment,
                ], [
                    'dedupe_key' => $key . ':tracker:' . $tracker->id . ':' . $logId,
                    'file_number' => $fileNumber,
                    'subject_type' => 'file_tracker',
                    'subject_id' => $tracker->id,
                    'event_at' => now(),
                    'phone_confirmed' => true,
                ]);
            }

            // In-app notification, pushed to the officer's phone by the observer.
            if ($officerId !== null && ($user = User::find($officerId))) {
                $title = sprintf('File %s returned', $fileNumber);
                $body = sprintf('File %s logged out to you has been returned to %s @ %s. The movement is completed.', $fileNumber, $registryName, $moment);

                $this->notifications->create($user->id, 'file_tracking.returned', $title, $body, [
                    'file_number' => $fileNumber,
                    'file_name' => $tracker->file_title,
                    'office_name' => $registryName,
                    'tracking_id' => $tracker->tracking_id,
                ], [
                    'module' => 'file_tracking',
                    'subject' => $title,
                    'message' => $body,
                    'name' => $user->name,
                    'enabled_email' => false,
                    'enabled_sms' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FileReturnNotifier: file returned message not sent', [
                'tracker_id' => $tracker->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
