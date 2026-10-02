<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingType extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'building_types';

    protected $fillable = [
        'name',
        'is_active'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
