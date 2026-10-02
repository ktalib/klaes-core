<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'user_notification_settings';

    protected $fillable = [
        'user_id',
        'enable_in_app',
        'enable_email',
        'enable_push',
        'play_sound',
        'flash_on_login',
        'preferences',
    ];

    protected $casts = [
        'enable_in_app' => 'boolean',
        'enable_email' => 'boolean',
        'enable_push' => 'boolean',
        'play_sound' => 'boolean',
        'flash_on_login' => 'boolean',
        'preferences' => 'array',
    ];

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'enable_in_app' => true,
                'enable_email' => false,
                'enable_push' => false,
                'play_sound' => true,
                'flash_on_login' => true,
            ]
        );
    }
}
