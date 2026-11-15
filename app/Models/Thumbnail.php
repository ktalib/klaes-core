<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thumbnail extends Model
{

    protected static function booted()
    {
        // A Master-Deleted file's thumbnails rows are kept, crossed out (user, 2026-11-15):
        // gone to every screen except one that lifts this scope to show them read-only.
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where(fn ($q) => $q->whereNull('thumbnails.is_deleted')->orWhere('thumbnails.is_deleted', 0));
        });
    }

    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'thumbnails';

    protected $fillable = [
        'file_indexing_id',
        'scanning_id',
        'file_number',
        'page_number',
        'page_type_id',
        'thumbnail_path',
        'original_filename',
        'file_size',
        'mime_type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the file indexing that owns the thumbnail
     */
    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    /**
     * Get the scanning that owns the thumbnail
     */
    public function scanning()
    {
        return $this->belongsTo(Scanning::class, 'scanning_id');
    }

    /**
     * Scope a query to only include active thumbnails
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by page number
     */
    public function scopeOrderedByPage($query)
    {
        return $query->orderBy('page_number');
    }
}
