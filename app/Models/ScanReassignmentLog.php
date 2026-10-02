<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ScanReassignmentLog
 *
 * Audit log for scan document reassignments (misplaced file corrections)
 */
class ScanReassignmentLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'scan_reassignment_logs';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'scanning_id',
        'from_file_number',
        'to_file_number',
        'from_file_indexing_id',
        'to_file_indexing_id',
        'from_path',
        'to_path',
        'reason',
        'reassigned_by',
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'scanning_id' => 'integer',
        'from_file_indexing_id' => 'integer',
        'to_file_indexing_id' => 'integer',
        'reassigned_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the scanning record that was reassigned
     */
    public function scanning()
    {
        return $this->belongsTo(Scanning::class, 'scanning_id', 'id');
    }

    /**
     * Get the original file indexing record
     */
    public function fromFileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'from_file_indexing_id', 'id');
    }

    /**
     * Get the new file indexing record
     */
    public function toFileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'to_file_indexing_id', 'id');
    }

    /**
     * Get the user who performed the reassignment
     */
    public function reassignedBy()
    {
        return $this->belongsTo(User::class, 'reassigned_by', 'id');
    }
}
