<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single file folder discovered inside a registry source. The folder name is
 * the file number (e.g. SLTR-220944). Holds the scanned documents within.
 */
class RegistryFileFolder extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'registry_file_folders';

    protected $fillable = [
        'registry_source_id',
        'file_number',
        'relative_path',
        'document_count',
        'last_synced_at',
    ];

    protected $casts = [
        'document_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegistrySource::class, 'registry_source_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RegistryFileDocument::class, 'registry_file_folder_id');
    }
}
