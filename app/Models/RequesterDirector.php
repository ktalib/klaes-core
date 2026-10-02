<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequesterDirector extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $fillable = [
        'department',
        'first_name',
        'last_name',
        'full_name',
    ];
}
