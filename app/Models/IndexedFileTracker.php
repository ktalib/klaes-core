<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class IndexedFileTracker extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'indexed_file_trackers';

    protected $fillable = [
        'file_indexing_id',
        'tracking_id',
        'qr_code',
        'rfid_tag',
        'current_location',
        'current_handler',
        'current_department',
        'last_location_update',
        'status',
        'priority',
        'notes',
        'movement_history',
        'sheet_generated_at',
        'sheet_printed_at',
        'sheet_printed_by',
        'total_prints',
        'file_location',
        'shelf_number',
        'box_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'movement_history' => 'array',
        'sheet_generated_at' => 'datetime',
        'sheet_printed_at' => 'datetime',
        'last_location_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sheetPrintedBy()
    {
        return $this->belongsTo(User::class, 'sheet_printed_by');
    }

    // Mutators
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    // Helper methods
    public function generateTrackingId()
    {
        $year = date('Y');
        $sequence = str_pad($this->file_indexing_id, 3, '0', STR_PAD_LEFT);
        return "TRK-{$year}-{$sequence}";
    }

    public function addMovementRecord($location, $handler, $action, $method = 'Manual', $notes = '')
    {
        $movement = [
            'date' => date('Y-m-d'),
            'time' => date('g:i A'),
            'location' => $location,
            'handler' => $handler,
            'action' => $action,
            'method' => $method,
            'notes' => $notes,
            'timestamp' => now()->toISOString(),
        ];

        $history = $this->movement_history ?? [];
        array_unshift($history, $movement); // Add to beginning of array

        $this->movement_history = $history;
        $this->current_location = $location;
        $this->current_handler = $handler;
        $this->last_location_update = now();
        $this->save();

        return $this;
    }

    public function incrementPrintCount()
    {
        $this->increment('total_prints');
        $this->sheet_printed_at = now();
        if (Auth::check()) {
            $this->sheet_printed_by = Auth::id();
        }
        $this->save();
        
        return $this;
    }

    public function getLatestMovement()
    {
        $history = $this->movement_history ?? [];
        return count($history) > 0 ? $history[0] : null;
    }

    public function getStatusBadgeClass()
    {
        switch ($this->status) {
            case 'Active':
                return 'bg-green-600 text-white';
            case 'Archived':
                return 'bg-gray-600 text-white';
            case 'Lost':
                return 'bg-red-600 text-white';
            case 'Damaged':
                return 'bg-orange-600 text-white';
            default:
                return 'bg-blue-600 text-white';
        }
    }

    public function getPriorityBadgeClass()
    {
        switch ($this->priority) {
            case 'High':
                return 'bg-red-600 text-white';
            case 'Normal':
                return 'bg-gray-500 text-white';
            case 'Low':
                return 'bg-blue-500 text-white';
            default:
                return 'bg-gray-500 text-white';
        }
    }
}