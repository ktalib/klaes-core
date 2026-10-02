<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GknSerialControl extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'gkn_serial_control';

    protected $fillable = [
        'prefix',
        'last_serial',
        'is_initialized',
        'initialized_at',
        'initialized_by',
        'is_locked'
    ];

    protected $casts = [
        'is_initialized' => 'boolean',
        'is_locked' => 'boolean',
        'last_serial' => 'integer',
        'initialized_at' => 'datetime'
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'initialized_by');
    }
}
