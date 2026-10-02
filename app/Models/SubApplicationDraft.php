<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class SubApplicationDraft extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'sub_application_draft';

    protected $fillable = [
        'draft_id',
        'sub_application_id',
        'main_application_id',
        'form_state',
        'progress_percent',
        'last_completed_step',
        'auto_save_frequency',
        'is_locked',
        'locked_by',
        'locked_at',
        'version',
        'last_saved_by',
        'last_saved_at',
        'analytics',
        'collaborators',
        'last_error',
        'unit_file_no',
        'is_sua',
    ];

    protected $casts = [
        'form_state' => 'array',
        'progress_percent' => 'float',
        'last_completed_step' => 'integer',
        'auto_save_frequency' => 'integer',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'version' => 'integer',
        'last_saved_at' => 'datetime',
        'analytics' => 'array',
        'collaborators' => 'array',
        'is_sua' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (self $draft) {
            if (!$draft->draft_id) {
                $draft->draft_id = (string) Str::uuid();
            }
            if (!$draft->version) {
                $draft->version = 1;
            }
        });
    }

    public function versions()
    {
        return $this->hasMany(SubApplicationDraftVersion::class, 'draft_id', 'draft_id');
    }

    public function collaborators()
    {
        return $this->hasMany(SubApplicationDraftCollaborator::class, 'draft_id', 'draft_id');
    }

    public function scopeFreshFirst($query)
    {
        return $query->orderBy('last_saved_at', 'desc');
    }
}