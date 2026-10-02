<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caveat extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'caveats';

    protected $hidden = ['instrumentType'];

    protected $fillable = [
        'caveat_number',
        'encumbrance_type',
        'instrument_type_id',
        'file_number_id',
        'file_number_type',
        'file_number_kangis',
        'file_number_mlsf',
        'file_number_new_kangis',
        'prop_id',
        'registration_number',
        'serial_no',
        'page_no',
        'volume_no',
        'petitioner',
        'petitioner_address',
        // The caveator's phone number, for the caveat placed/lifted SMS. Added
        // with that feature -- the table had no way to reach the petitioner.
        'petitioner_phone',
        'grantee_name',
        'grantee_address',
        'location',
        'property_description',
        'start_date',
        'release_date',
        'status',
        'instructions',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public $timestamps = true; // created_at, updated_at

    protected $casts = [
        'start_date' => 'datetime',
        'release_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function instrumentType()
    {
        // References InstrumentTypes(InstrumentTypeID)
        return $this->belongsTo(InstrumentType::class, 'instrument_type_id', 'InstrumentTypeID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithStatus($query, $status)
    {
        if (!empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }
        return $query;
    }
}
