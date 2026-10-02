<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CsvImportUpload extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'session_id',
        'import_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'test_control',
        'row_count',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $upload) {
            if (!$upload->id) {
                $upload->id = (string) Str::uuid();
            }
        });
    }
}
