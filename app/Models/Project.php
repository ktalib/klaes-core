<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'vfc_projects';

    protected $fillable = [
        'project_name',
        'project_code',
        'project_fileno',
        'number_of_items',
        'street',
        'district',
        'lga',
        'state',
        'project_type',
        'project_type_other',
        'our_reference',
        'your_reference',
        'addressed_to',
        'apply_percentage',
        'user_id',
        'is_deleted',
        'deleted_at',
        'deleted_by'
    ];

    protected $casts = [
        'is_deleted' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Only projects that have not been soft-deleted from the VFC console.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', 0);
    }

    public function workers()
    {
        return $this->hasMany(ProjectWorker::class, 'project_id');
    }

    public function valuations()
    {
        return $this->hasMany(ValuationCompensation::class, 'project_id');
    }

    public function subProjects()
    {
        return $this->hasMany(SubProject::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
