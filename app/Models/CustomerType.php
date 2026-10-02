<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'customer_type';
    protected $primaryKey = 'customer_type_id';
    public $timestamps = false;

    protected $fillable = [
        'customer_type_name',
        'is_active'
    ];
}
