<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecommissionedFiles extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'decommissioned_files';
    
    protected $fillable = [
        'file_number_id',
        'file_no',
        'mls_file_no',
        'kangis_file_no',
        'new_kangis_file_no',
        'file_name',
        'commissioning_date',
        'decommissioning_date',
        'decommissioning_reason',
        'decommissioned_by'
    ];

    protected $dates = [
        'commissioning_date',
        'decommissioning_date',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'commissioning_date' => 'datetime',
        'decommissioning_date' => 'datetime'
    ];

    /**
     * Get the file number that was decommissioned
     */
    public function fileNumber()
    {
        return $this->belongsTo(FileNumber::class, 'file_number_id');
    }

    /**
     * Scope to get recently decommissioned files
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('decommissioning_date', '>=', now()->subDays($days));
    }

    /**
     * Scope to get files decommissioned by a specific user
     */
    public function scopeByUser($query, $user)
    {
        return $query->where('decommissioned_by', $user);
    }

    /**
     * Scope to search by file numbers
     */
    public function scopeSearchByFileNo($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('mls_file_no', 'like', "%{$search}%")
              ->orWhere('kangis_file_no', 'like', "%{$search}%")
              ->orWhere('new_kangis_file_no', 'like', "%{$search}%")
              ->orWhere('file_name', 'like', "%{$search}%");
        });
    }
}