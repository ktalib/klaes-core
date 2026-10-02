<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpResettlementBill extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'op_resettlement_bills';
    protected $guarded = [];
}
