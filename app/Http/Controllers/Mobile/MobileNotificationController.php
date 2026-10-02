<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {}

    /**
     * GET /api/mobile/notifications
     * Returns last 50 notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = Notification::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'body', 'type', 'is_read', 'read_at', 'created_at']);

        $unreadCount = Notification::forUser($userId)->unread()->count();

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
     * POST /api/mobile/notifications/mark-all-read
     * Mark all notifications for the authenticated user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllForUserAsRead($request->user()->id);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }
}
