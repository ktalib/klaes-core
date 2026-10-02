<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\UserNotificationSetting;
use App\Services\StaffAttendanceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationCenterController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('notifications.index', [
            'initialUnreadCount' => Notification::forUser($user->id)->where('is_read', false)->count(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:5|max:50',
            'status' => ['sometimes', Rule::in(['all', 'unread', 'read'])],
            'search' => 'sometimes|string|max:100',
            'flash' => 'sometimes|boolean',
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['perPage'] ?? 15;
        $status = $validated['status'] ?? 'all';
        $search = $validated['search'] ?? null;
        $flashOnly = (bool) ($validated['flash'] ?? false);

        $query = Notification::query()
            ->forUser($user->id)
            ->orderByDesc('created_at');

        if ($status === 'unread' || $flashOnly) {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $payload = [
            'items' => $paginator->getCollection()->map(fn ($notification) => $this->transformNotification($notification))->values(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'unreadCount' => Notification::forUser($user->id)->where('is_read', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureOwnership($request->user(), $notification);

        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformNotification($notification),
        ]);
    }

    public function markUnread(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureOwnership($request->user(), $notification);

        if ($notification->is_read) {
            $notification->markAsUnread();
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformNotification($notification),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::forUser($user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->ensureOwnership($request->user(), $notification);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $settings = UserNotificationSetting::forUser($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $settings->toArray(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enable_in_app' => 'sometimes|boolean',
            'enable_email' => 'sometimes|boolean',
            'enable_push' => 'sometimes|boolean',
            'play_sound' => 'sometimes|boolean',
            'flash_on_login' => 'sometimes|boolean',
            'preferences' => 'sometimes|array',
        ]);

        $settings = UserNotificationSetting::forUser($request->user()->id);
        $settings->fill(Arr::only($validated, $settings->getFillable()))->save();

        return response()->json([
            'success' => true,
            'data' => $settings->fresh()->toArray(),
        ]);
    }

    protected function transformNotification(Notification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'title' => $notification->title ?? $notification->subject ?? 'Notification',
            'body' => $notification->body ?? $notification->message ?? '',
            'type' => $notification->type,
            'icon' => $this->iconFor($notification),
            'isRead' => (bool) $notification->is_read,
            'createdAt' => optional($notification->created_at)->toIso8601String(),
            'meta' => [
                'fileNumber' => $data['file_number'] ?? null,
                'fileName' => $data['file_name'] ?? null,
                'source' => $notification->module,
            ],
        ];
    }

    /**
     * The lucide icon notification-center.js draws for a row.
     *
     * Null keeps the default bell, which is right for every module that has no
     * icon of its own. Sign-in and sign-out notices arrive twice a day for
     * every member of staff, so they are the one kind worth being able to skip
     * past at a glance.
     */
    protected function iconFor(Notification $notification): ?string
    {
        return match ($notification->type) {
            StaffAttendanceNotificationService::TYPE_LOGIN => 'log-in',
            StaffAttendanceNotificationService::TYPE_LOGOUT => 'log-out',
            default => null,
        };
    }

    protected function ensureOwnership($user, Notification $notification): void
    {
        if ($notification->user_id !== $user->id) {
            abort(403, 'You are not allowed to modify this notification.');
        }
    }
}
