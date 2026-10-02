<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewInstrumentType extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'new_instrument_types';
    protected $fillable = ['name', 'description'];
}
