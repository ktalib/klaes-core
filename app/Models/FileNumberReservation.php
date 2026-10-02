<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FileNumberReservation extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'file_number_reservations';

    protected $fillable = [
        'reservation_uuid',
        'prefix',
        'land_use',
        'year',
        'serial_number',
        'session_id',
        'reserved_by',
        'reserved_by_name',
        'reserved_by_ip',
        'reserved_at',
        'expires_at',
        'status',
        'confirmed_at',
        'generated_file_number',
        'metadata',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Default reservation duration in minutes
     */
    const RESERVATION_DURATION_MINUTES = 15;

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reservation_uuid)) {
                $model->reservation_uuid = (string) Str::uuid();
            }
            if (empty($model->reserved_at)) {
                $model->reserved_at = now();
            }
            if (empty($model->expires_at)) {
                $model->expires_at = now()->addMinutes(self::RESERVATION_DURATION_MINUTES);
            }
        });
    }

    /**
     * Scope for active (non-expired) reservations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'reserved')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired reservations
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'reserved')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope for confirmed reservations
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Check if this reservation is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'reserved' && $this->expires_at <= now();
    }

    /**
     * Check if this reservation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'reserved' && $this->expires_at > now();
    }

    /**
     * Mark this reservation as confirmed
     */
    public function confirm(string $generatedFileNumber = null): bool
    {
        $this->status = 'confirmed';
        $this->confirmed_at = now();
        
        if ($generatedFileNumber) {
            $this->generated_file_number = $generatedFileNumber;
        }

        return $this->save();
    }

    /**
     * Release this reservation back to the pool
     */
    public function release(): bool
    {
        $this->status = 'released';
        return $this->save();
    }

    /**
     * Mark this reservation as expired
     */
    public function markExpired(): bool
    {
        $this->status = 'expired';
        return $this->save();
    }

    /**
     * Get the user who reserved this
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    /**
     * Get time remaining in seconds
     */
    public function getTimeRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Extend the reservation expiration time
     */
    public function extend(int $minutes = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $minutes = $minutes ?? self::RESERVATION_DURATION_MINUTES;
        $this->expires_at = now()->addMinutes($minutes);

        return $this->save();
    }
}
