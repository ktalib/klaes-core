<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalRegistry extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'physical_registries';
}
