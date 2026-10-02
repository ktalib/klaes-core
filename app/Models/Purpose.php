<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purpose extends Model
{ 
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'purposes';
    public $timestamps = false;

    public function landUse()
    {
        return $this->belongsTo(LandUse::class, 'landuseid');
    }
}
