<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubProject extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'vfc_sub_projects';

    protected $fillable = [
        'project_id',
        'name',
        'code'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function valuations()
    {
        return $this->hasMany(ValuationCompensation::class, 'sub_project_id');
    }
}
