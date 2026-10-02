<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MotherApplicationDraftCollaborator extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'mother_application_draft_collaborators';

    protected $fillable = [
        'draft_id',
        'user_id',
        'invited_by',
        'role',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function draft()
    {
        return $this->belongsTo(MotherApplicationDraft::class, 'draft_id', 'draft_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
