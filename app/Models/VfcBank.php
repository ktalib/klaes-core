<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VfcBank extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'vfc_banks';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'logo',
        'ussd',
        'ussd_transfer'
    ];
}
