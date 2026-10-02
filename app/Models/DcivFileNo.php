<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DcivFileNo extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dciv_file_no';

    protected $fillable = [
        'batch_no',
        'prefix',
        'year',
        'serial_number',
        'full_file_number',
        'file_name',
        'plot_no',
        'tp_no',
        'location',
        'lga',
        'tracking_id',
        'created_by',
        'commissioning_date',
        'commissioning_time',
        'land_use_id',
        'purpose_id',
        'is_deleted',
        'related_fileno',
        'dciv_reason'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'year' => 'integer',
        'serial_number' => 'integer',
        'land_use_id' => 'integer',
        'purpose_id' => 'integer'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['formatted_date', 'formatted_time'];

    /**
     * Get the user who commissioned the record.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the land use associated with this record.
     */
    public function landUse()
    {
        return $this->belongsTo(LandUse::class, 'land_use_id');
    }

    /**
     * Get the purpose associated with this record.
     */
    public function purpose()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    /**
     * Get the formatted commissioning date.
     */
    public function getFormattedDateAttribute()
    {
        try {
            return \Carbon\Carbon::parse($this->commissioning_date)->format('jS M, Y');
        } catch (\Exception $e) {
            return $this->commissioning_date;
        }
    }

    /**
     * Get the formatted commissioning time.
     */
    public function getFormattedTimeAttribute()
    {
        try {
            return \Carbon\Carbon::parse($this->commissioning_time)->format('h:i A');
        } catch (\Exception $e) {
            return $this->commissioning_time;
        }
    }
}
