<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prefix extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'prefix';

    protected $fillable = ['prefix', 'land_use_id'];

    public function landUse()
    {
        return $this->belongsTo(LandUse::class, 'land_use_id');
    }
}
