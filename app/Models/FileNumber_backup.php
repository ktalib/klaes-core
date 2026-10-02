<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\DecommissionedFiles;

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
        'SOURCE'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_deleted' => 'boolean'
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
        // Get all decommissioned file IDs
        $decommissionedIds = [];
        $records = DecommissionedFiles::getAllRecords();
        
        foreach ($records as $record) {
            $decommissionedIds[] = $record['file_number_id'];
        }
        
        if (!empty($decommissionedIds)) {
            return $query->whereNotIn('id', $decommissionedIds);
        }
        
        return $query;
    }

    /**
     * Scope to get only decommissioned files
     */
    public function scopeDecommissioned($query)
    {
        // Get all decommissioned file IDs
        $decommissionedIds = [];
        $records = DecommissionedFiles::getAllRecords();
        
        foreach ($records as $record) {
            $decommissionedIds[] = $record['file_number_id'];
        }
        
        if (!empty($decommissionedIds)) {
            return $query->whereIn('id', $decommissionedIds);
        }
        
        return $query->whereRaw('1 = 0'); // Return no results if no decommissioned files
    }

    /**
     * Check if file is decommissioned
     */
    public function isDecommissioned()
    {
        return DecommissionedFiles::isFileDecommissioned($this->id);
    }

    /**
     * Decommission this file
     */
    public function decommission($reason, $decommissioningDate = null, $commissioningDate = null)
    {
        $decommissioningDate = $decommissioningDate ?: now();
        
        // Ensure storage exists
        DecommissionedFiles::ensureStorageExists();
        
        // Create record in decommissioned files log
        DecommissionedFiles::create([
            'file_number_id' => $this->id,
            'file_no' => $this->id,
            'mls_file_no' => $this->mlsfNo,
            'kangis_file_no' => $this->kangisFileNo,
            'new_kangis_file_no' => $this->NewKANGISFileNo,
            'file_name' => $this->FileName,
            'commissioning_date' => $commissioningDate ? $commissioningDate->toDateTimeString() : null,
            'decommissioning_date' => $decommissioningDate->toDateTimeString(),
            'decommissioning_reason' => $reason,
            'decommissioned_by' => auth()->user()->name ?? auth()->user()->email ?? 'System'
        ]);

        return true;
    }
}