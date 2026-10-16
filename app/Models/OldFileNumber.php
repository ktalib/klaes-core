<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (current file number, old file number) pair.
 *
 * Written only through App\Services\OldFileNumberService so the two mirrors
 * (mls_file_no.old_fileno and file_indexings.old_fileno) can never drift from
 * the ledger.
 */
class OldFileNumber extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'old_file_numbers';

    public const SOURCE_REISSUANCE = 'reissuance';
    public const SOURCE_EDIT       = 'edit';
    public const SOURCE_MANUAL     = 'manual';
    public const SOURCE_IMPORT     = 'import';
    // Move Commissioned File: the number the file carried before it was moved off a
    // physical duplicate (App\Services\FileNumber\CommissionedFileMoveService).
    public const SOURCE_MOVE       = 'physical_duplicate';

    protected $fillable = [
        'file_number',
        'old_file_number',
        'old_file_title',
        'source',
        'file_indexing_id',
        'created_by',
    ];

    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }
}
