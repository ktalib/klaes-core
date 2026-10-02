<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FileindexingBatch extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'fileindexing_batch';
    protected $primaryKey = 'id';

    public const BATCH_CAPACITY = 100;

    protected $fillable = [
        'batch_number',
        'shelf_label_id',
        'full_label',
        'start_shelf_id',
        'end_shelf_id',
        'shelf_count',
        'used_shelves',
        'is_full',
        'is_active'
    ];

    protected $casts = [
        'batch_number' => 'integer',
        'start_shelf_id' => 'integer',
        'end_shelf_id' => 'integer',
    'shelf_count' => 'integer',
        'used_shelves' => 'integer',
        'is_full' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get file indexings assigned to this batch
     */
    public function fileIndexings()
    {
        return $this->hasMany(FileIndexing::class, 'batch_id', 'id');
    }

    /**
     * Get available shelves in this batch
     */
    public function getAvailableShelvesAttribute()
    {
    return max(0, self::BATCH_CAPACITY - $this->used_shelves);
    }

    /**
     * Check if batch has available space
     */
    public function hasAvailableSpace()
    {
        return $this->is_active && $this->used_shelves < self::BATCH_CAPACITY;
    }

    /**
     * Get the next available shelf in this batch
     */
    public function getNextAvailableShelf()
    {
        return DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
            ->where('id', $this->shelf_label_id ?: $this->start_shelf_id)
            ->first();
    }

    /**
     * Mark a shelf as used and update batch statistics
     */
    public function markShelfUsed($shelfId)
    {
        // Verify the shelf belongs to this batch
        if ($shelfId < $this->start_shelf_id || $shelfId > $this->end_shelf_id) {
            throw new \InvalidArgumentException("Shelf ID {$shelfId} does not belong to batch {$this->batch_number}");
        }

        DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
            ->where('id', $shelfId)
            ->update(['is_used' => 1]);

        $this->increment('used_shelves');

        if ($this->used_shelves >= self::BATCH_CAPACITY) {
            $this->update([
                'is_full' => true,
                'is_active' => false,
            ]);
        }
    }

    /**
     * Generate new batches based on available shelf labels
     */
    public static function generateBatches($batchesToGenerate = 100)
    {
        $batchSize = 100; // Shelves per batch
        $generated = 0;

        // Get the highest existing batch number
        $lastBatchNumber = self::max('batch_number') ?? 0;
        
        // Get available shelf labels that aren't assigned to any batch
        $assignedShelfIds = self::pluck('start_shelf_id')
            ->merge(self::pluck('end_shelf_id'))
            ->unique()
            ->sort();

        // Find gaps in shelf assignments and available shelves
        $maxShelfId = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')->max('id') ?? 0;
        
        for ($i = 0; $i < $batchesToGenerate && $generated < $batchesToGenerate; $i++) {
            $batchNumber = $lastBatchNumber + 1 + $i;
            $startShelfId = ($batchNumber - 1) * $batchSize + 1;
            $endShelfId = $batchNumber * $batchSize;

            // Check if we have enough shelf labels for this batch
            if ($endShelfId > $maxShelfId) {
                break; // No more shelves available
            }

            // Check if this batch range is already assigned
            $existingBatch = self::where('start_shelf_id', $startShelfId)
                ->where('end_shelf_id', $endShelfId)
                ->first();

            if ($existingBatch) {
                continue; // Skip if already exists
            }

            // Count actual shelves available in this range
            $availableShelvesInRange = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                ->whereBetween('id', [$startShelfId, $endShelfId])
                ->count();

            if ($availableShelvesInRange > 0) {
                // Create the batch
                self::create([
                    'batch_number' => $batchNumber,
                    'start_shelf_id' => $startShelfId,
                    'end_shelf_id' => $endShelfId,
                    'shelf_count' => $availableShelvesInRange,
                    'used_shelves' => 0,
                    'is_full' => false,
                    'is_active' => true
                ]);
                
                $generated++;
            }
        }

        return $generated;
    }

    /**
     * Get available batches for selection
     */
    public static function availableForSelection($search = '', $page = 1, $perPage = 0)
    {
        $search = trim((string) $search);
        $page = max((int) $page, 1);
        $perPage = (int) $perPage;

        $baseQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->select(
                'batch_no',
                DB::raw('COUNT(*) as file_count')
            )
            ->whereNotNull('batch_no')
            ->where(function ($query) {
                $query->where('batch_generated', 0)
                      ->orWhereNull('batch_generated');
            })
            ->where(function ($query) {
                $query->where('is_deleted', 0)
                      ->orWhereNull('is_deleted');
            });

        if ($search !== '') {
            $baseQuery->where('batch_no', 'like', "%" . $search . "%");
        }

        $query = (clone $baseQuery)
            ->groupBy('batch_no')
            ->orderByRaw('CASE WHEN TRY_CAST(batch_no AS INT) IS NOT NULL THEN TRY_CAST(batch_no AS INT) ELSE -2147483648 END DESC')
            ->orderBy('batch_no', 'DESC');

        $total = (clone $query)->get()->count();

        if ($total === 0) {
            return [
                'batches' => [],
                'pagination' => [
                    'more' => false,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
                'total' => 0,
            ];
        }

        if ($perPage <= 0 || $perPage > $total) {
            $perPage = $total;
        }

        $offset = ($page - 1) * $perPage;
        $batches = (clone $query)
            ->skip($offset)
            ->take($perPage)
            ->get();

        return [
            'batches' => $batches->map(function ($batch) {
                $fileCount = (int) $batch->file_count;
                return [
                    'id' => (string) $batch->batch_no,
                    'text' => sprintf('%s (%d files)', $batch->batch_no, $fileCount),
                    'file_count' => $fileCount,
                    'available_shelves' => max(0, self::BATCH_CAPACITY - $fileCount),
                    'used_shelves' => $fileCount,
                    'total_shelves' => self::BATCH_CAPACITY,
                ];
            })->all(),
            'pagination' => [
                'more' => ($offset + $perPage) < $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'total' => $total,
        ];
    }

    public static function getAvailableBatches($search = '', $page = 1, $perPage = 5)
    {
        return self::availableForSelection($search, $page, $perPage);
    }
}