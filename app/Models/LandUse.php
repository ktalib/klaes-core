<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
 
class LandUse extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'land_uses';

    public function purposes()
    {
        return $this->hasMany(Purpose::class, 'landuseid');
    }

    public function prefixes()
    {
        return $this->hasMany(Prefix::class, 'land_use_id');
    }

  




}



