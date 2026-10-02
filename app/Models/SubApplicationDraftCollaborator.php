<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubApplicationDraftCollaborator extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'sub_application_draft_collaborators';

    protected $fillable = [
        'draft_id',
        'user_id',
        'role',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}