<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FileIndexingBatchService
{
    public const BATCH_CAPACITY = 100;

    /**
     * Reserve the next available batch slot and increment counters.
     *
     * Must be called inside an existing sqlsrv transaction so that
     * any failure rolls back both the batch counters and the file insert.
     *
     * @throws \RuntimeException when no shelves are available
     * @return array{batch_no:int,batch_id:int,shelf_label_id:int,shelf_location:string,serial_no:int}
     */
    public function assignNext(): array
    {
        $connection = DB::connection('sqlsrv');

        // Lock the current active batch for update so that concurrent writers queue
        $batch = $connection->table('fileindexing_batch')
            ->where('is_active', 1)
            ->where('is_full', 0)
            ->orderBy('batch_number')
            ->lockForUpdate()
            ->first();

        if (!$batch) {
            $batch = $this->createBatchLocked($connection);
        }

        if (!$batch) {
            throw new \RuntimeException('No available shelves remaining for file indexing.');
        }

        $shelf = $connection->table('Rack_Shelf_Labels')
            ->where('id', $batch->start_shelf_id)
            ->lockForUpdate()
            ->first();

        if (!$shelf) {
            throw new \RuntimeException("Shelf label {$batch->start_shelf_id} is missing.");
        }

        $nextSerial = (int) $batch->used_shelves + 1;
        $isFull = $nextSerial >= self::BATCH_CAPACITY;

        $connection->table('fileindexing_batch')
            ->where('id', $batch->id)
            ->update([
                'used_shelves' => $nextSerial,
                'is_full' => $isFull ? 1 : 0,
                'is_active' => $isFull ? 0 : 1,
                'updated_at' => Carbon::now(),
            ]);

        return [
            'batch_no' => (int) $batch->batch_number,
            'batch_id' => (int) $batch->id,
            'shelf_label_id' => (int) $shelf->id,
            'shelf_location' => (string) $shelf->full_label,
            'serial_no' => $nextSerial,
        ];
    }

    /**
     * Ensure only a single active batch exists at a time and return it.
     */
    protected function createBatchLocked($connection)
    {
        // Grab the next free shelf label
        $shelf = $connection->table('Rack_Shelf_Labels')
            ->where('is_used', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$shelf) {
            return null;
        }

        $nextBatchNumber = (int) $connection->table('fileindexing_batch')->max('batch_number') + 1;
        $now = Carbon::now();

        $batchId = $connection->table('fileindexing_batch')->insertGetId([
            'batch_number' => $nextBatchNumber,
            'shelf_label_id' => $shelf->id,
            'full_label' => $shelf->full_label,
            'start_shelf_id' => $shelf->id,
            'end_shelf_id' => $shelf->id,
            'shelf_count' => 1,
            'used_shelves' => 0,
            'is_full' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $connection->table('Rack_Shelf_Labels')
            ->where('id', $shelf->id)
            ->update([
                'is_used' => 1,
                'reserved_at' => $now,
            ]);

        return $connection->table('fileindexing_batch')
            ->where('id', $batchId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Get the currently active batch (without locking).
     */
    public function currentBatch()
    {
        return DB::connection('sqlsrv')->table('fileindexing_batch')
            ->where('is_active', 1)
            ->where('is_full', 0)
            ->orderBy('batch_number')
            ->first();
    }
}