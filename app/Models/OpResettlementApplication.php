<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpResettlementApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'op_resettlement_applications';
    protected $guarded = [];
}
