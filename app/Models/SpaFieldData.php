<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaFieldData extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_field_data';

    protected $fillable = [
        'spa_application_id', 'file_number', 'surveyor_id',
        'inspection_date', 'coordinates', 'parcel_geometry', 'findings', 'photos',
        'status', 'created_by',
        // Offline sync (database/sql/2026_08_15_spas_offline_sync_schema.sql):
        // client_uuid makes a push idempotent on retry; spa_application_client_uuid
        // links an inspection to a parent that has no server id yet.
        'client_uuid', 'spa_application_client_uuid',
    ];

    protected $casts = [
        'coordinates'     => 'array',
        'parcel_geometry' => 'array',
        'photos'          => 'array',
        'inspection_date' => 'date',
        // The sqlsrv driver hands these back as strings ("10"), which then
        // serialise into the sync API as strings and break a strict id
        // comparison on the device. Cast so the JSON contract is stable.
        'spa_application_id' => 'integer',
        'surveyor_id'        => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function surveyor()
    {
        return $this->belongsTo(\App\Models\User::class, 'surveyor_id');
    }
}
