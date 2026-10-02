<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CadastralShadowFile extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_shadow_files';

    protected $fillable = [
        'ref_number',
        'full_number',
        'file_name',
        'plot_no',
        'location',
        'lga',
        'tracking_id',
        'created_by',
        'date_matched',
        'time_matched',
        'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'date_matched' => 'date',
    ];

    /**
     * Get the user who matched the record.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the formatted matched date.
     */
    public function getFormattedDateAttribute()
    {
        try {
            return \Carbon\Carbon::parse($this->date_matched)->format('jS M, Y');
        } catch (\Exception $e) {
            return $this->date_matched;
        }
    }

    /**
     * Get the formatted matched time.
     */
    public function getFormattedTimeAttribute()
    {
        try {
            return \Carbon\Carbon::parse($this->time_matched)->format('h:i A');
        } catch (\Exception $e) {
            return $this->time_matched;
        }
    }
}
