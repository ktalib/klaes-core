<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AuditLog
 *
 * Tracks all important system actions for compliance and audit purposes
 */
class AuditLog extends Model
{
    use SoftDeletes;

    protected $connection = 'sqlsrv';
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get audits by resource type
     */
    public function scopeByResource($query, $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope: Get audits by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Get audits by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get recent audits
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get display name for action
     */
    public function getActionDisplayName()
    {
        return match($this->action) {
            'CREATED' => 'Created',
            'UPDATED' => 'Updated',
            'DELETED' => 'Deleted',
            'DOWNLOADED' => 'Downloaded',
            'EXPORTED' => 'Exported',
            'VIEWED' => 'Viewed',
            'SHARED' => 'Shared',
            default => ucfirst(strtolower($this->action)),
        };
    }

    /**
     * Get display name for resource type
     */
    public function getResourceDisplayName()
    {
        return match($this->resource_type) {
            'report' => 'Report',
            'scheduled_report' => 'Scheduled Report',
            'user_activity_log' => 'Activity Log',
            'audit_log' => 'Audit Log',
            'export' => 'Export',
            default => ucfirst(str_replace('_', ' ', $this->resource_type)),
        };
    }

    /**
     * Check if this audit has recorded changes
     */
    public function hasRecordedChanges()
    {
        return $this->old_values && $this->new_values;
    }

    /**
     * Get list of changed fields
     */
    public function getChangedFields()
    {
        if (!$this->hasRecordedChanges()) {
            return [];
        }

        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changed = [];

        foreach ($new as $field => $value) {
            if (!isset($old[$field]) || $old[$field] !== $value) {
                $changed[$field] = [
                    'old' => $old[$field] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changed;
    }
}
