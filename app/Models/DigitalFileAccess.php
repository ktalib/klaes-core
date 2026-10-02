<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class DigitalFileAccess extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'digital_file_accesses';

    protected $fillable = [
        'digital_request_id',
        'requester_user_id',
        'file_no',
        'file_title',
        'temp_folder_path',
        'files_copied',
        'expires_at',
        'granted_at',
        'access_status',
    ];

    protected $casts = [
        'files_copied' => 'array',
        'expires_at'   => 'datetime',
        'granted_at'   => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_ACTIVE  = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(DigitalFileRequest::class, 'digital_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(\App\Models\User::class, 'requester_user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Active accesses: status=active AND not yet past expires_at.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('access_status', self::STATUS_ACTIVE)
                     ->where('expires_at', '>', now());
    }

    /**
     * Expired: either status=expired OR past the expires_at date while still 'active'.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('access_status', self::STATUS_EXPIRED)
              ->orWhere(function ($q2) {
                  $q2->where('access_status', self::STATUS_ACTIVE)
                     ->where('expires_at', '<=', now());
              });
        });
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('access_status', self::STATUS_REVOKED);
    }

    // ── Helper methods ────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->access_status !== self::STATUS_ACTIVE
            || $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->access_status === self::STATUS_REVOKED;
    }

    /**
     * Whole working days remaining (0 if expired today or past).
     */
    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        $now  = Carbon::now()->startOfDay();
        $exp  = $this->expires_at->copy()->startOfDay();
        $days = 0;
        $cur  = $now->copy()->addDay();
        while ($cur->lte($exp)) {
            if ($cur->isWeekday()) {
                $days++;
            }
            $cur->addDay();
        }
        return $days;
    }

    /**
     * Absolute path to the temp folder on disk.
     */
    public function absoluteTempPath(): string
    {
        // temp_folder_path is stored with forward slashes; convert to OS separator for Windows
        $osSafe = str_replace('/', DIRECTORY_SEPARATOR, $this->temp_folder_path);
        return storage_path('app' . DIRECTORY_SEPARATOR . $osSafe);
    }

    /**
     * Badge colour class for the expiry countdown.
     */
    public function expiryBadgeClass(): string
    {
        $days = $this->daysRemaining();
        if ($days > 2) return 'text-green-700 bg-green-50 border-green-200';
        if ($days > 0) return 'text-amber-700 bg-amber-50 border-amber-200';
        return 'text-red-700 bg-red-50 border-red-200';
    }
}
