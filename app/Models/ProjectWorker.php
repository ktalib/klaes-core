<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectWorker extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'vfc_project_workers';

    protected $fillable = [
        'project_id',
        'user_id',
        'worker_code'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
