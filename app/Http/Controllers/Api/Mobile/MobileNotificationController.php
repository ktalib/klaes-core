<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobilePushToken;
use App\Models\Notification;
use App\Services\Push\FcmPushService;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    /**
     * The mobile dashboard bell is a file-tracking surface, not a general
     * inbox. Without this scope every module the app knows nothing about
     * (Online Legal Search approvals, parcel updates, LAAS) surfaced there and
     * drowned the file movements the field officer actually opens the app for.
     *
     * file_search_request stays in: it is the app's own physical file lookup
     * flow and the dashboard has explicit SCB Monitor handling for it.
     */
    public const MODULES = ['file_tracking', 'file_search_request'];

    /**
     * The same rule as scopeToFileTracking(), for a single row. Push
     * notifications use it so the phone buzzes only for what the bell shows.
     */
    public static function belongsOnMobile(Notification $notification): bool
    {
        return in_array($notification->module, self::MODULES, true)
            || str_starts_with((string) $notification->type, 'file_tracking')
            || $notification->type === 'file_search_request';
    }

    public function __construct(
        protected UserNotificationService $notificationService
    ) {}

    /**
     * Restrict a notification query to the file-tracking surface.
     */
    protected function scopeToFileTracking($query)
    {
        return $query->where(function ($inner) {
            $inner->whereIn('module', self::MODULES)
                ->orWhere('type', 'like', 'file_tracking%')
                ->orWhere('type', 'file_search_request');
        });
    }

    /**
     * GET /api/mobile/notifications
     * Returns last 50 notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = $this->scopeToFileTracking(Notification::forUser($userId))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'body', 'type', 'module', 'is_read', 'read_at', 'created_at']);

        $unreadCount = $this->scopeToFileTracking(Notification::forUser($userId))->unread()->count();

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/mobile/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::forUser($request->user()->id)->find($id);

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $this->notificationService->markAsRead($notification);

        return response()->json(['success' => true, 'message' => 'Marked as read']);
    }

    /**
     * POST /api/mobile/push/register
     * The FTM app sends its FCM token on every dashboard load. A phone shared
     * between officers moves to whoever is signed in now.
     */
    public function registerPush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => 'required|string|max:255',
            'platform'    => 'nullable|string|in:android,ios',
            'device_name' => 'nullable|string|max:150',
            'app_version' => 'nullable|string|max:30',
        ]);

        MobilePushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $data['platform'] ?? 'android',
                'device_name'  => $data['device_name'] ?? null,
                'app_version'  => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'push_enabled' => app(FcmPushService::class)->enabled()]);
    }

    /**
     * POST /api/mobile/push/unregister — on logout, so the phone stops
     * receiving the previous officer's notifications.
     */
    public function unregisterPush(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:255']);

        MobilePushToken::where('token', $data['token'])->where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/mobile/notifications/mark-all-read
     * Mark all notifications for the authenticated user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        // Scoped to the same set the bell displays. The shared
        // markAllForUserAsRead() clears every module, which would silently
        // dismiss notifications this screen never showed the user.
        $this->scopeToFileTracking(Notification::forUser($request->user()->id))
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }
}
