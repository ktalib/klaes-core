<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class InstrumentType extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'InstrumentTypes';
    protected $primaryKey = 'InstrumentTypeID';

    protected $fillable = [
        'InstrumentName',
        'Description',
        'IsActive'
    ];

    protected $casts = [
        'IsActive' => 'boolean',
    ];

    public $timestamps = false;

    /**
     * Scope to get only active instrument types
     */
    public function scopeActive($query)
    {
        return $query->where('IsActive', 1);
    }

    /**
     * Get the name attribute (alias for InstrumentName)
     */
    public function getNameAttribute()
    {
        return $this->InstrumentName;
    }
}
