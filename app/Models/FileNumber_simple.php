<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FileNumber extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'fileNumber';
    
    protected $fillable = [
        'type',
        'kangisFileNo',
        'mlsfNo',
        'NewKANGISFileNo',
        'FileName',
        'created_by',
        'updated_by',
        'location',
        'is_deleted',
        'SOURCE',
        'commissioning_date',
        'decommissioning_date',
        'decommissioning_reason',
        'is_decommissioned'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'commissioning_date',
        'decommissioning_date'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_decommissioned' => 'boolean',
        'commissioning_date' => 'datetime',
        'decommissioning_date' => 'datetime'
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
        $decommissionedBy = auth()->user()->name ?? auth()->user()->email ?? 'System';
        
        DB::beginTransaction();
        
        try {
            // Update the file number record
            $this->update([
                'commissioning_date' => $commissioningDate,
                'decommissioning_date' => $decommissioningDate,
                'decommissioning_reason' => $reason,
                'is_decommissioned' => true
            ]);

            // Create record in decommissioned files table
            DecommissionedFiles::create([
                'file_number_id' => $this->id,
                'file_no' => $this->id,
                'mls_file_no' => $this->mlsfNo,
                'kangis_file_no' => $this->kangisFileNo,
                'new_kangis_file_no' => $this->NewKANGISFileNo,
                'file_name' => $this->FileName,
                'commissioning_date' => $commissioningDate,
                'decommissioning_date' => $decommissioningDate,
                'decommissioning_reason' => $reason,
                'decommissioned_by' => $decommissionedBy
            ]);

            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}