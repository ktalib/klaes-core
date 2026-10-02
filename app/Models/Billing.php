<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'billing';
    protected $primaryKey = 'ID';
    protected $guarded    = [];
}
