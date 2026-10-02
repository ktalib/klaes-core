<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class UserActivityLogSetting extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'user_activity_log_settings';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'cleanup_interval',
        'retention_days',
        'refresh_interval',
        'records_per_page',
        'auto_logout_inactive',
        'track_failed_logins',
        'ip_based_alerts',
        'email_notifications',
        'timezone',
        'notes',
        'settings_data',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'retention_days' => 'integer',
        'refresh_interval' => 'integer',
        'records_per_page' => 'integer',
        'auto_logout_inactive' => 'boolean',
        'track_failed_logins' => 'boolean',
        'ip_based_alerts' => 'boolean',
        'email_notifications' => 'boolean',
        'settings_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $model): void {
            static::flushCache($model->user_id);
        });

        static::deleted(function (self $model): void {
            static::flushCache($model->user_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public static function getDefaults(): array
    {
        return [
            'cleanup_interval' => 'weekly',
            'retention_days' => 90,
            'refresh_interval' => 30,
            'records_per_page' => 25,
            'auto_logout_inactive' => false,
            'track_failed_logins' => true,
            'ip_based_alerts' => false,
            'email_notifications' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'notes' => null,
            'settings_data' => [],
        ];
    }

    protected static function cacheKey(?int $userId): string
    {
        return is_null($userId)
            ? 'user_activity_settings_global'
            : "user_activity_settings_{$userId}";
    }

    protected static function flushCache(?int $userId): void
    {
        Cache::forget(static::cacheKey($userId));
    }

    public static function clearCacheForUser(?int $userId): void
    {
        static::flushCache($userId);
    }

    public static function createDefaults(int $userId): self
    {
        $defaults = static::getDefaults();

        return static::create(array_merge($defaults, [
            'user_id' => $userId,
        ]));
    }

    public static function firstOrCreateDefaults(int $userId): self
    {
        $defaults = static::getDefaults();

        return static::firstOrCreate(
            ['user_id' => $userId],
            array_merge($defaults, ['user_id' => $userId])
        );
    }

    public static function getForUser(int $userId): ?self
    {
        return static::forUser($userId)->first();
    }

    public static function getSettings(?int $userId = null): array
    {
        $payload = Cache::remember(static::cacheKey($userId), 3600, function () use ($userId) {
            $query = static::query();

            $record = is_null($userId)
                ? $query->whereNull('user_id')->first()
                : $query->forUser($userId)->first();

            return $record ? $record->toArray() : null;
        });

        if (!$payload) {
            $defaults = static::getDefaults();
            $defaults['user_id'] = $userId;
            $defaults['id'] = null;

            return $defaults;
        }

        $defaults = static::getDefaults();
        $merged = array_merge(
            $defaults,
            collect($payload)->only(array_keys($defaults))->toArray()
        );

        $merged['id'] = $payload['id'] ?? null;
        $merged['user_id'] = $payload['user_id'] ?? $userId;

        return $merged;
    }

    public static function getGlobalSettings(): array
    {
        return static::getSettings();
    }

    public static function saveSettings(array $data, ?int $userId = null): self
    {
        $settings = static::updateOrCreate(
            ['user_id' => $userId],
            array_merge($data, ['user_id' => $userId])
        );

        static::flushCache($userId);

        return $settings;
    }

    public static function saveGlobalSettings(array $data): self
    {
        return static::saveSettings($data, null);
    }

    public static function getUserSettings(int $userId): array
    {
        $global = static::getGlobalSettings();
        $userSpecific = static::getSettings($userId);

        return array_merge($global, $userSpecific, ['user_id' => $userId]);
    }

    public static function getSettingsModel(?int $userId = null): ?self
    {
        return is_null($userId)
            ? static::whereNull('user_id')->first()
            : static::forUser($userId)->first();
    }

    public function setTimezone(string $timezone): bool
    {
        $updated = $this->update(['timezone' => $timezone]);

        static::flushCache($this->user_id);

        return $updated;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? config('app.timezone', 'UTC');
    }

    public function setNotes(?string $notes): bool
    {
        $updated = $this->update(['notes' => $notes]);

        static::flushCache($this->user_id);

        return $updated;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function hasNotes(): bool
    {
        return !empty($this->notes);
    }

    public static function shouldRunCleanup(): bool
    {
        $settings = static::getGlobalSettings();
        $lastCleanup = cache('activity_logs_last_cleanup', now()->subDay());

        return match ($settings['cleanup_interval']) {
            'daily' => $lastCleanup->diffInHours(now()) >= 24,
            'weekly' => $lastCleanup->diffInDays(now()) >= 7,
            'monthly' => $lastCleanup->diffInDays(now()) >= 30,
            default => false,
        };
    }

    public static function markCleanupCompleted(): void
    {
        cache(['activity_logs_last_cleanup' => now()], now()->addDays(31));
    }

    public static function getCleanupSettings(): array
    {
        $settings = static::getGlobalSettings();

        return [
            'interval' => $settings['cleanup_interval'],
            'retention_days' => $settings['retention_days'],
            'should_run' => static::shouldRunCleanup(),
            'last_run' => cache('activity_logs_last_cleanup', 'Never'),
        ];
    }

    public static function validateSettings(array $data)
    {
        $rules = [
            'cleanup_interval' => 'in:daily,weekly,monthly',
            'retention_days' => 'integer|min:1|max:365',
            'refresh_interval' => 'integer|min:10|max:300',
            'records_per_page' => 'integer|min:10|max:100',
            'auto_logout_inactive' => 'boolean',
            'track_failed_logins' => 'boolean',
            'ip_based_alerts' => 'boolean',
            'email_notifications' => 'boolean',
            'timezone' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];

        return Validator::make($data, $rules);
    }

    public function getDisplaySettings(): array
    {
        return [
            'cleanup_interval' => ucfirst($this->cleanup_interval),
            'retention_days' => $this->retention_days . ' days',
            'refresh_interval' => $this->refresh_interval . ' seconds',
            'records_per_page' => $this->records_per_page . ' records',
            'auto_logout_inactive' => $this->auto_logout_inactive ? 'Enabled' : 'Disabled',
            'track_failed_logins' => $this->track_failed_logins ? 'Enabled' : 'Disabled',
            'ip_based_alerts' => $this->ip_based_alerts ? 'Enabled' : 'Disabled',
            'email_notifications' => $this->email_notifications ? 'Enabled' : 'Disabled',
        ];
    }
}