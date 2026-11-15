<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FileIndexing;
use App\Models\User;
use App\Models\PageTyping;

class Scanning extends Model
{

    protected static function booted()
    {
        // A Master-Deleted file's scannings rows are kept, crossed out (user, 2026-11-15):
        // gone to every screen except one that lifts this scope to show them read-only.
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where(fn ($q) => $q->whereNull('scannings.is_deleted')->orWhere('scannings.is_deleted', 0));
        });
    }

    protected $connection = 'sqlsrv';
    protected $table = 'scannings';

    protected $fillable = [
        'file_indexing_id',
        'document_path',
        'uploaded_by',
        'status',
        'display_order',
        'definition',
        'definition_code',
        'original_filename',
        'paper_size',
        'document_type',
        'notes',
        'file_size',
        'is_pdf_converted',
        'parent_scan_id',
        'registry',
        'edms_file_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'display_order' => 'integer',
        'definition' => 'integer',
        'file_size' => 'integer',
        'is_pdf_converted' => 'boolean',
        'parent_scan_id' => 'integer',
    ];

    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function pagetypings()
    {
        return $this->hasMany(PageTyping::class, 'scanning_id');
    }
}