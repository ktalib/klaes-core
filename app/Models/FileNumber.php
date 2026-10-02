<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileNumber extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'fileNumber';
    public $timestamps = false;
    
    protected $fillable = [
        'type',
        'kangisFileNo',
        'mlsfNo',
        'NewKANGISFileNo',
        'FileName',
        'tracking_id',
        'created_by',
        'updated_by',
        'location',
        'lga',
        'plot_no',
        'tp_no',
        'related_fileno',
        'is_deleted',
        'SOURCE',
        'commissioning_date',
        'decommissioning_date',
        'decommissioning_reason',
        'is_decommissioned',
        // Shadow File Matching Flags & Timestamps
        'pp_lands_matching',
        'pp_st_matching',
        'pp_sltr_matching',
        'pp_lands_date_matched',
        'pp_lands_time_matched',
        'pp_st_date_matched',
        'pp_st_time_matched',
        'pp_sltr_date_matched',
        'pp_sltr_time_matched',
        'has_temp_file',
        'temp_file_no',
        'kangis_fileno_placeholder',
        'kangis_fileno_resolved',
        // Applicant contact. Same story as MlsFileNo: the columns were added in
        // Feb 2026 but never listed here, so every mass-assignment dropped them
        // and both stayed empty (phone_no had 117 rows, 47 of them the literal
        // string "080"). The commissioning SMS writes them for real now.
        'phone_no',
        'rep_phone_no',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'commissioning_date',
        'decommissioning_date',
        'pp_lands_date_matched',
        'pp_st_date_matched',
        'pp_sltr_date_matched'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_decommissioned' => 'boolean',
        'pp_lands_matching' => 'boolean',
        'pp_st_matching' => 'boolean',
        'pp_sltr_matching' => 'boolean',
        'has_temp_file' => 'boolean',
        'kangis_fileno_placeholder' => 'string',
        'kangis_fileno_resolved' => 'string',
        'commissioning_date' => 'datetime',
        'decommissioning_date' => 'datetime',
        'pp_lands_date_matched' => 'date',
        'pp_st_date_matched' => 'date',
        'pp_sltr_date_matched' => 'date'
    ];

    /**
     * Scope to get only generated file numbers
     */
    public function scopeGenerated($query)
    {
        return $query->where('type', 'Generated');
    }

    /**
     * Get the next serial number for a given year and land use type
     */
    public static function getNextSerial($year = null, $landUsePrefix = null)
    {
        $year = $year ?: date('Y');
        
        $query = self::where('type', 'Generated')
                    ->where('mlsfNo', 'like', '%-' . $year . '-%');
        
        if ($landUsePrefix) {
            $query->where('mlsfNo', 'like', $landUsePrefix . '-%');
        }
        
        $lastRecord = $query->orderByRaw('CAST(RIGHT(mlsfNo, 4) AS INT) DESC')->first();
        
        if ($lastRecord) {
            $lastSerial = (int) substr($lastRecord->mlsfNo, -4);
            return $lastSerial + 1;
        }
        
        return 1;
    }

    /**
     * Generate MLSF number
     */
    public static function generateMlsfNo($landUse, $year, $serial)
    {
        $paddedSerial = str_pad($serial, 4, '0', STR_PAD_LEFT);
        return $landUse . '-' . $year . '-' . $paddedSerial;
    }

    /**
     * Check if MLSF number exists
     */
    public static function mlsfExists($mlsfNo)
    {
        return self::where('mlsfNo', $mlsfNo)->exists();
    }

    /**
     * Get the decommissioned file record if exists
     */
    public function decommissionedFile()
    {
        return $this->hasOne(DecommissionedFiles::class, 'file_number_id');
    }

    /**
     * Scope to get only active (non-decommissioned) files
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0);
        });
    }

    /**
     * Scope to get only decommissioned files
     */
    public function scopeDecommissioned($query)
    {
        return $query->where('is_decommissioned', 1);
    }

    /**
     * Check if file is decommissioned
     */
    public function isDecommissioned()
    {
        return $this->is_decommissioned == 1;
    }

    /**
     * Decommission this file
     */
    public function decommission($reason, $decommissioningDate = null, $commissioningDate = null)
    {
        $decommissioningDate = $decommissioningDate ?: now();

        $updatePayload = [
            'decommissioning_date' => $decommissioningDate,
            'decommissioning_reason' => $reason,
            'is_decommissioned' => true,
            'updated_by' => auth()->user()->name ?? auth()->user()->email ?? 'System'
        ];

        if ($commissioningDate !== null) {
            $updatePayload['commissioning_date'] = $commissioningDate;
        }

        return $this->update($updatePayload);
    }
}