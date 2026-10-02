<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSessionLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'is_locked',
        'locked_at',
        'last_activity',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'last_activity' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if session should be locked (3 minutes of inactivity)
     */
    public function shouldBeLocked(): bool
    {
        if ($this->is_locked) {
            return true;
        }

        $lockThreshold = Carbon::now()->subMinutes(3);
        return $this->last_activity < $lockThreshold;
    }

    /**
     * Check if session should be logged out (15 minutes of inactivity)
     */
    public function shouldBeLoggedOut(): bool
    {
        $logoutThreshold = Carbon::now()->subMinutes(15);
        return $this->last_activity < $logoutThreshold;
    }

    /**
     * Lock the session
     */
    public function lockSession(): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => Carbon::now(),
        ]);
    }

    /**
     * Unlock the session
     */
    public function unlockSession(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'last_activity' => Carbon::now(),
        ]);
    }

    /**
     * Update last activity
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity' => Carbon::now(),
        ]);
    }
}
