<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValuationOfficer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'valuation_officers';
    protected $primaryKey = 'name';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'rank',
    ];
}
