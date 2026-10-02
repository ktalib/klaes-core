<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DecommissionedFiles extends Model
{
    protected $fillable = [
        'file_number_id',
        'file_no',
        'mls_file_no',
        'kangis_file_no',
        'new_kangis_file_no',
        'file_name',
        'commissioning_date',
        'decommissioning_date',
        'decommissioning_reason',
        'decommissioned_by'
    ];

    protected $dates = [
        'commissioning_date',
        'decommissioning_date',
        'created_at',
        'updated_at'
    ];

    // Use file-based storage since we can't create database tables
    private static $logFile = 'decommissioned_files.json';

    /**
     * Create a new decommissioned file record
     */
    public static function create($data)
    {
        $record = array_merge($data, [
            'id' => self::getNextId(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString()
        ]);

        $records = self::getAllRecords();
        $records[] = $record;
        
        Storage::put(self::$logFile, json_encode($records, JSON_PRETTY_PRINT));
        
        return (object) $record;
    }

    /**
     * Get all decommissioned file records
     */
    public static function getAllRecords()
    {
        if (!Storage::exists(self::$logFile)) {
            return [];
        }
        
        $content = Storage::get(self::$logFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Get paginated records for DataTables
     */
    public static function getPaginatedRecords($start = 0, $length = 10, $search = '')
    {
        $records = self::getAllRecords();
        
        // Apply search filter
        if (!empty($search)) {
            $records = array_filter($records, function($record) use ($search) {
                return stripos($record['mls_file_no'], $search) !== false ||
                       stripos($record['kangis_file_no'], $search) !== false ||
                       stripos($record['new_kangis_file_no'], $search) !== false ||
                       stripos($record['file_name'], $search) !== false;
            });
        }
        
        // Sort by decommissioning_date desc
        usort($records, function($a, $b) {
            return strtotime($b['decommissioning_date']) - strtotime($a['decommissioning_date']);
        });
        
        $total = count($records);
        $filtered = array_slice($records, $start, $length);
        
        return [
            'data' => $filtered,
            'total' => $total,
            'filtered' => $total
        ];
    }

    /**
     * Get count of all records
     */
    public static function count()
    {
        return count(self::getAllRecords());
    }

    /**
     * Get recently decommissioned files
     */
    public static function recent($days = 30)
    {
        $records = self::getAllRecords();
        $cutoffDate = now()->subDays($days);
        
        return array_filter($records, function($record) use ($cutoffDate) {
            return Carbon::parse($record['decommissioning_date'])->gte($cutoffDate);
        });
    }

    /**
     * Find a record by ID
     */
    public static function find($id)
    {
        $records = self::getAllRecords();
        
        foreach ($records as $record) {
            if ($record['id'] == $id) {
                return (object) $record;
            }
        }
        
        return null;
    }

    /**
     * Check if a file is decommissioned
     */
    public static function isFileDecommissioned($fileNumberId)
    {
        $records = self::getAllRecords();
        
        foreach ($records as $record) {
            if ($record['file_number_id'] == $fileNumberId) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get next available ID
     */
    private static function getNextId()
    {
        $records = self::getAllRecords();
        
        if (empty($records)) {
            return 1;
        }
        
        $maxId = max(array_column($records, 'id'));
        return $maxId + 1;
    }

    /**
     * Scope to search by file numbers
     */
    public function scopeSearchByFileNo($query, $search)
    {
        // This is for compatibility - not used in file-based approach
        return $query;
    }

    /**
     * Ensure storage directory exists
     */
    public static function ensureStorageExists()
    {
        // Create storage directory if it doesn't exist
        if (!Storage::exists('')) {
            Storage::makeDirectory('');
        }
        return true;
    }
}