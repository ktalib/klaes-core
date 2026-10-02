<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingSheet extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     * Required for KLAES application to use SQL Server
     */
    protected $connection = 'sqlsrv';

    /**
     * The table associated with the model.
     */
    protected $table = 'tracking_sheet';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'batch_id',
        'batch_name',
        'file_count',
        'selected_file_ids',
        'generated_by',
        'generated_at',
        'batch_type',
        'status',
        'print_count',
        'last_printed_at',
        'last_printed_by',
        'notes'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'selected_file_ids' => 'array',
        'generated_at' => 'datetime',
        'last_printed_at' => 'datetime',
        'file_count' => 'integer',
        'print_count' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'selected_file_ids', // Hide detailed file IDs in general API responses
    ];

    /**
     * Relationship to the user who generated the batch
     */
    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Relationship to the user who last printed the batch
     */
    public function lastPrintedBy()
    {
        return $this->belongsTo(User::class, 'last_printed_by');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by batch type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('batch_type', $type);
    }

    /**
     * Scope for searching by batch ID or name
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('batch_id', 'LIKE', "%{$search}%")
              ->orWhere('batch_name', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Increment the print count and update last printed info
     */
    public function incrementPrintCount($userId = null)
    {
        $this->increment('print_count');
        $this->update([
            'last_printed_at' => now(),
            'last_printed_by' => $userId ?? auth()->id(),
            'status' => 'printed'
        ]);
    }

    /**
     * Generate a unique batch ID
     */
    public static function generateBatchId($type = 'manual')
    {
        $prefix = match($type) {
            'auto_100' => 'AUTO100',
            'auto_200' => 'AUTO200',
            default => 'BATCH'
        };
        
        $date = now()->format('Ymd');
        $counter = static::where('batch_id', 'LIKE', "{$prefix}_{$date}_%")->count() + 1;
        
        return "{$prefix}_{$date}_" . str_pad($counter, 3, '0', STR_PAD_LEFT);
    }
}
