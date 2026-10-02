<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DecommissionedFiles extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'decommissioned_files';
    
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

    protected $casts = [
        'commissioning_date' => 'datetime',
        'decommissioning_date' => 'datetime'
    ];

    /**
     * Get the file number that was decommissioned
     */
    public function fileNumber()
    {
        return $this->belongsTo(FileNumber::class, 'file_number_id');
    }

    /**
     * Scope to get recently decommissioned files
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('decommissioning_date', '>=', now()->subDays($days));
    }

    /**
     * Scope to get files decommissioned by a specific user
     */
    public function scopeByUser($query, $user)
    {
        return $query->where('decommissioned_by', $user);
    }

    /**
     * Scope to search by file numbers
     */
    public function scopeSearchByFileNo($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('mls_file_no', 'like', "%{$search}%")
              ->orWhere('kangis_file_no', 'like', "%{$search}%")
              ->orWhere('new_kangis_file_no', 'like', "%{$search}%")
              ->orWhere('file_name', 'like', "%{$search}%");
        });
    }

    /**
     * Get decommissioning statistics
     */
    public static function getStatistics()
    {
        try {
            $stats = DB::connection('sqlsrv')->select('EXEC sp_GetDecommissioningStats');
            return $stats[0] ?? null;
        } catch (\Exception $e) {
            // Fallback to manual queries if stored procedure doesn't exist
            return (object) [
                'total_files' => FileNumber::where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })->count(),
                'active_files' => FileNumber::active()->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })->count(),
                'decommissioned_files' => self::count(),
                'recent_decommissioned' => self::recent(30)->count(),
                'this_month_decommissioned' => self::whereYear('decommissioning_date', now()->year)
                    ->whereMonth('decommissioning_date', now()->month)
                    ->count()
            ];
        }
    }

    /**
     * Search active files for selection
     */
    public static function searchActiveFiles($searchTerm, $limit = 20)
    {
        try {
            return DB::connection('sqlsrv')->select('EXEC sp_SearchActiveFiles ?, ?', [$searchTerm, $limit]);
        } catch (\Exception $e) {
            // Fallback to manual query if stored procedure doesn't exist
            return FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function($query) use ($searchTerm) {
                    $query->where('mlsfNo', 'like', "%{$searchTerm}%")
                          ->orWhere('kangisFileNo', 'like', "%{$searchTerm}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$searchTerm}%")
                          ->orWhere('FileName', 'like', "%{$searchTerm}%");
                })
                ->select(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName'])
                ->limit($limit)
                ->get();
        }
    }
}