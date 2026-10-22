<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An Android app distributed from System Admin > KLAES Mobile Apps.
 * icon_path / apk_path are relative to the private_files disk.
 */
class MobileApp extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'mobile_apps';

    protected $fillable = [
        'slug',
        'name',
        'short_description',
        'description',
        'version',
        'min_android',
        'icon_path',
        'apk_path',
        'apk_original_name',
        'apk_size',
        'apk_uploaded_at',
        'download_count',
        'updated_by',
    ];

    protected $casts = [
        'apk_uploaded_at' => 'datetime',
        'apk_size' => 'integer',
        'download_count' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function hasApk(): bool
    {
        return ! empty($this->apk_path);
    }

    public function apkSizeForHumans(): ?string
    {
        if (! $this->apk_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->apk_size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i ? 1 : 0).' '.$units[$i];
    }

    /** The public page people are sent to; it works without a KLAES login. */
    public function shareUrl(): string
    {
        return route('mobile-apps.public', $this);
    }
}
