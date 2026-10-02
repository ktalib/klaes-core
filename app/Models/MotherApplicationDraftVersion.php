<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MotherApplicationDraftVersion extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'mother_application_draft_versions';

    protected $fillable = [
        'draft_id',
        'version',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function draft()
    {
        return $this->belongsTo(MotherApplicationDraft::class, 'draft_id', 'draft_id');
    }
}
