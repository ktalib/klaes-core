<?php

namespace App\Models;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FileTracking extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_trackings';
    
    protected $fillable = [
        'file_indexing_id',
        'rfid_tag',
        'qr_code',
        'current_location',
        'current_holder',
        'current_handler',
        'origin_registry',
        'assigned_office',
        'assigned_to',
        'assigned_by',
        'date_received',
        'due_date',
        'status',
        'assignment_status',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'reject_note',
        'batch_no',
        'movement_history',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date_received' => 'datetime',
        'due_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'movement_history' => 'array',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_CHECKED_OUT = 'checked_out';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_RETURNED = 'returned';
    const STATUS_LOST = 'lost';
    const STATUS_ARCHIVED = 'archived';

    // Assignment status constants
    const ASSIGNMENT_PENDING = 'pending';
    const ASSIGNMENT_ACCEPTED = 'accepted';
    const ASSIGNMENT_REJECTED = 'rejected';
    const ASSIGNMENT_IN_TRANSIT = 'in-transit';

    /**
     * Relationship with FileIndexing
     */
    public function fileIndexing(): BelongsTo
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    /**
     * Get the current handler user
     */
    public function currentHandlerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler');
    }

    /**
     * Receiving/assigned user relationship.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Assigned office relationship.
     */
    public function assignedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'assigned_office');
    }

    /**
     * Check if the file is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->due_date) && 
               !in_array($this->status, [self::STATUS_RETURNED, self::STATUS_ARCHIVED]);
    }

    /**
     * Get days until due or overdue
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }
        
        return Carbon::now()->diffInDays($this->due_date, false);
    }

    /**
     * Add movement entry to history
     */
    public function addMovementEntry(array $movementData): void
    {
        try {
            // Get current history as array (this creates a copy, not a reference)
            $currentHistory = $this->movement_history ?? [];
            
            // Ensure it's an array
            if (!is_array($currentHistory)) {
                $currentHistory = [];
            }
            
            $movement = array_merge([
                'timestamp' => Carbon::now()->toISOString(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? 'System',
            ], $movementData);
            
            // Create new array with the new movement at the beginning
            $newHistory = array_merge([$movement], $currentHistory);
            
            // Keep only last 100 movements to prevent excessive data
            if (count($newHistory) > 100) {
                $newHistory = array_slice($newHistory, 0, 100);
            }
            
            // Use direct database update to avoid model casting issues
            DB::connection('sqlsrv')->table('file_trackings')
                ->where('id', $this->id)
                ->update([
                    'movement_history' => json_encode($newHistory),
                    'updated_at' => Carbon::now()
                ]);
                
            // Refresh the model to get the updated data
            $this->refresh();
            
        } catch (\Exception $e) {
            \Log::error('Error adding movement entry', [
                'tracking_id' => $this->id,
                'movement_data' => $movementData,
                'error' => $e->getMessage()
            ]);
            
            // Don't throw exception to avoid breaking the flow
            // Just log the error and continue
        }
    }

    /**
     * Update file location and add to movement history
     */
    public function updateLocation(string $newLocation, string $reason = null): void
    {
        $oldLocation = $this->current_location;
        
        $this->current_location = $newLocation;
        
        $this->addMovementEntry([
            'action' => 'location_change',
            'from_location' => $oldLocation,
            'to_location' => $newLocation,
            'reason' => $reason,
        ]);
    }

    /**
     * Update file handler and add to movement history
     */
    public function updateHandler(string $newHandler, string $reason = null): void
    {
        $oldHandler = $this->current_handler;
        
        $this->current_handler = $newHandler;
        
        $this->addMovementEntry([
            'action' => 'handler_change',
            'from_handler' => $oldHandler,
            'to_handler' => $newHandler,
            'reason' => $reason,
        ]);
    }

    /**
     * Update file status and add to movement history
     */
    public function updateStatus(string $newStatus, string $reason = null): void
    {
        $oldStatus = $this->status;
        
        $this->status = $newStatus;
        
        $this->addMovementEntry([
            'action' => 'status_change',
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'reason' => $reason,
        ]);
    }

    /**
     * Update assignment status helpers.
     */
    public function markAssignmentPending(): void
    {
        $this->assignment_status = self::ASSIGNMENT_PENDING;
        $this->assigned_at = Carbon::now();
        $this->accepted_at = null;
        $this->rejected_at = null;
        $this->reject_note = null;
    }

    public function markAssignmentAccepted(): void
    {
        $this->assignment_status = self::ASSIGNMENT_ACCEPTED;
        $this->accepted_at = Carbon::now();
        $this->rejected_at = null;
        $this->reject_note = null;
    }

    public function markAssignmentRejected(string $note = null): void
    {
        $this->assignment_status = self::ASSIGNMENT_REJECTED;
        $this->rejected_at = Carbon::now();
        $this->reject_note = $note;
    }

    public function getIsAssignedToCurrentUserAttribute(): bool
    {
        $userId = auth()->id();

        if (!$userId) {
            return false;
        }

        return (int) $this->assigned_to === (int) $userId;
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by location
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('current_location', 'LIKE', "%{$location}%");
    }

    /**
     * Scope for filtering by handler
     */
    public function scopeByHandler($query, $handler)
    {
        return $query->where('current_handler', 'LIKE', "%{$handler}%");
    }

    /**
     * Scope for overdue files
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', Carbon::now())
                    ->whereNotIn('status', [self::STATUS_RETURNED, self::STATUS_ARCHIVED]);
    }

    /**
     * Scope for active files
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_RETURNED, self::STATUS_ARCHIVED, self::STATUS_LOST]);
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = Carbon::now();
            $model->updated_at = Carbon::now();
            
            // Initialize movement history properly
            if (empty($model->movement_history)) {
                $model->movement_history = [];
            }

            if (empty($model->assignment_status)) {
                $model->assignment_status = self::ASSIGNMENT_PENDING;
            }
        });

        static::created(function ($model) {
            // Add initial creation entry after the model is created
            $history = $model->movement_history ?? [];
            array_unshift($history, [
                'action' => 'created',
                'timestamp' => Carbon::now()->toISOString(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? 'System',
                'initial_location' => $model->current_location,
                'initial_handler' => $model->current_handler,
                'initial_status' => $model->status,
            ]);
            
            // Use update to avoid indirect modification issue
            $model->update(['movement_history' => $history]);
        });

        static::updating(function ($model) {
            $model->updated_at = Carbon::now();
        });
    }
}