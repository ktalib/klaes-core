<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An FCM registration token for one phone running the KLAES FTM app. */
class MobilePushToken extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'mobile_push_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'app_version',
        'last_seen_at',
        'last_pushed_at',
        'last_error',
    ];

    protected $casts = [
        'last_seen_at'   => 'datetime',
        'last_pushed_at' => 'datetime',
    ];
}
