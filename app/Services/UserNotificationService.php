<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Arr;

class UserNotificationService
{
    public function create(int $userId, string $type, string $title, string $body, array $data = [], array $overrides = []): Notification
    {
        $attributes = [
            // parentId() dereferences Auth::user() unconditionally, so it warns
            // and yields null when called from a guest context (e.g. the public
            // Online Legal Search portal notifying staff). Skip it when nobody
            // is signed in — the resulting value is null either way.
            'parent_id' => (function_exists('parentId') && \Auth::check()) ? parentId() : null,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => empty($data) ? null : $data,
            'module' => Arr::get($overrides, 'module', 'system'),
            'subject' => $overrides['subject'] ?? $title,
            'message' => $overrides['message'] ?? $body,
            'enabled_email' => Arr::get($overrides, 'enabled_email', false),
            'enabled_sms' => Arr::get($overrides, 'enabled_sms', false),
            'name' => $overrides['name'] ?? null,
            'short_code' => $overrides['short_code'] ?? null,
        ];

        return Notification::create($attributes);
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllForUserAsRead(int $userId): void
    {
        Notification::forUser($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
