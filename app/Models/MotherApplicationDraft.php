<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class MotherApplicationDraft extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'mother_application_draft';

    protected $fillable = [
        'draft_id',
        'application_id',
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
        'np_file_no',
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
    ];

    protected static function booted()
    {
        static::creating(function (self $draft) {
            if (empty($draft->draft_id)) {
                $draft->draft_id = (string) Str::uuid();
            }

            if (empty($draft->version)) {
                $draft->version = 1;
            }
        });
    }

    public function versions()
    {
        return $this->hasMany(MotherApplicationDraftVersion::class, 'draft_id', 'draft_id');
    }

    public function collaborators()
    {
        return $this->hasMany(MotherApplicationDraftCollaborator::class, 'draft_id', 'draft_id');
    }

    public function scopeFreshFirst($query)
    {
        return $query->orderByDesc('last_saved_at')->orderByDesc('updated_at');
    }
}
