<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated This model is DEPRECATED as of Shadow File Commissioning implementation (Feb 2026)
 * 
 * Under the new matching-only architecture:
 * - Shadow file records are NO LONGER CREATED in sltr_shadow_files table
 * - File matching is tracked directly in fileNumber table using timestamp columns:
 *   • pp_sltr_date_matched
 *   • pp_sltr_time_matched
 * 
 * For new development, use FileNumber model with matching timestamp fields instead.
 * This model is kept for backward compatibility with existing shadow file records only.
 * 
 * @see App\Models\FileNumber For the current implementation
 */
class SltrShadowFile extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    // NOTE: This table reference remains for backward compatibility with existing shadow file records.
    // New matching operations use the 'fileNumber' table with timestamp columns instead.
    protected $table = 'sltr_shadow_files';

    protected $fillable = [
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
