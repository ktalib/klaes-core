<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StPrintLabelBatch extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'st_print_label_batches';

    protected $fillable = [
        'batch_number',
        'prefix',
        'application_type',
        'sys_batch_no',
        'batch_size',
        'generated_count',
        'status',
        'full_label',
        'rack_primary',
        'rack_secondary',
        'shelf_number',
        'created_by',
        'updated_by',
        'printed_at',
    ];

    protected $casts = [
        'batch_size'      => 'integer',
        'sys_batch_no'    => 'integer',
        'generated_count' => 'integer',
        'shelf_number'    => 'integer',
        'created_by'      => 'integer',
        'updated_by'      => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'printed_at'      => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_GENERATED = 'generated';
    const STATUS_PRINTED   = 'printed';
    const STATUS_COMPLETED = 'completed';

    public function batchItems(): HasMany
    {
        return $this->hasMany(StPrintLabelBatchItem::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markAsPrinted(): void
    {
        $this->update(['status' => self::STATUS_PRINTED, 'printed_at' => now()]);
    }
}
