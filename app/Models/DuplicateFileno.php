<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateFileno extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'duplicate_fileno';

    protected $fillable = [
        'registry',
        'file_number',
        'file_title',
        'plot_number',
        'location',
        'comment',
        'category',
        'created_by',
        'source',
    ];

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by source type.
     */
    public function scopeManualEntries($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Scope to filter by CSV imports.
     */
    public function scopeCsvEntries($query)
    {
        return $query->where('source', 'csv');
    }
}
