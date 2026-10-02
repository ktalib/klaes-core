<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registry whose digital copies are stored as raw folders on disk
 * (SLTR, Cadastral, KANGIS, Physical Planning). Seeded from
 * config/registry_sources.php; folders/documents are imported by `registry:sync`.
 */
class RegistrySource extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'registry_sources';

    protected $fillable = [
        'name',
        'code',
        'folder',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function fileFolders(): HasMany
    {
        return $this->hasMany(RegistryFileFolder::class, 'registry_source_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
