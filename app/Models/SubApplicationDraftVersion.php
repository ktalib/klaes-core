<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubApplicationDraftVersion extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'sub_application_draft_versions';

    protected $fillable = [
        'draft_id',
        'version',
        'snapshot',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];
}