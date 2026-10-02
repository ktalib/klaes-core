<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FileIndexing;
use App\Models\User;

class ScanUpload extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'scan_uploads';

    protected $fillable = [
        'file_indexing_id',
        'file_name',
        'status',
        'uploaded_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
