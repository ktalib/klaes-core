<?php
      
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lga extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'lgas';

    protected $fillable = [
        'name',
        'code',
        'slug',
        'is_active',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'lga_id');
    }
}
