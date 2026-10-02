<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VfcWorker extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'vfc_workers';

    protected $fillable = [
        'user_id',
        'vfc_worker_id',
        'is_active',
        'deactivated_at',
        'deactivation_reason'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignments()
    {
        return $this->hasMany(ProjectWorker::class, 'user_id', 'user_id');
    }
}
