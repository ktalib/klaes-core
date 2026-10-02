<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class CadastralPrintLabelBatch extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_print_label_batches';

    protected $fillable = [
        'batch_number',
        'batch_size',
        'generated_count',
        'label_format',
        'orientation',
        'rack_system',
        'status',
        'created_by',
        'updated_by',
        'printed_at',
    ];

    protected $attributes = [
        'rack_system' => 'standard',
    ];

    protected $casts = [
        'batch_size' => 'integer',
        'generated_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_GENERATED = 'generated';
    const STATUS_PRINTED = 'printed';
    const STATUS_COMPLETED = 'completed';

    const FORMAT_STANDARD = 'standard';
    const FORMAT_COMPACT = 'compact';
    const FORMAT_QR_CODE = 'qr_code';
    const FORMAT_30_IN_1 = '30-in-1';

    const ORIENTATION_PORTRAIT = 'portrait';
    const ORIENTATION_LANDSCAPE = 'landscape';

    /**
     * Get the batch items for this batch
     */
    public function batchItems(): HasMany
    {
        return $this->hasMany(CadastralPrintLabelBatchItem::class, 'batch_id');
    }

    /**
     * Get the user who created this batch
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this batch
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for recent batches
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Generate a unique batch number
     */
    public static function generateBatchNumber($prefix = 'CADPLB')
    {
        $year = now()->year;
        $month = now()->format('m');

        // Get the next sequence number for this month
        $lastBatch = static::where('batch_number', 'like', "{$prefix}-{$year}-{$month}-%")
            ->orderBy('batch_number', 'desc')
            ->first();

        if ($lastBatch) {
            $lastNumber = (int) substr($lastBatch->batch_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%d-%s-%03d', $prefix, $year, $month, $nextNumber);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->batch_size == 0) {
            return 0;
        }

        return round(($this->generated_count / $this->batch_size) * 100, 1);
    }

    /**
     * Check if batch is complete
     */
    public function getIsCompleteAttribute()
    {
        return $this->generated_count >= $this->batch_size;
    }

    /**
     * Get remaining slots in batch
     */
    public function getRemainingSlotAttribute()
    {
        return max(0, $this->batch_size - $this->generated_count);
    }

    /**
     * Mark batch as printed
     */
    public function markAsPrinted()
    {
        $this->update([
            'status' => self::STATUS_PRINTED,
            'printed_at' => now(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Mark batch as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'updated_by' => auth()->id(),
        ]);
    }
}
