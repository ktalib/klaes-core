<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single file's membership in a parcel-update / merger group.
 *
 * @see \App\Services\FileMergerService for how groups are derived and kept in sync.
 */
class FileMerger extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_merger';

    protected $fillable = [
        'merger_id',
        'fmerge_id',
        'role',
        'parent_file',
        'child_file',
        'file_number',
        'file_title',
        'location',
        'date_commissioned',
        'date_decommissioned',
        'source',
        'reason',
    ];

    protected $casts = [
        'date_commissioned' => 'datetime',
        'date_decommissioned' => 'datetime',
    ];

    public const ROLE_PARENT = 'parent';
    public const ROLE_CHILD = 'child';

    /**
     * All files that belong to the same merger group.
     */
    public function scopeForGroup($query, string $mergerId)
    {
        return $query->where('merger_id', $mergerId);
    }
}
