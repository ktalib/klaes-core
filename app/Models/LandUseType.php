<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandUseType extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'land_use_types';
}
