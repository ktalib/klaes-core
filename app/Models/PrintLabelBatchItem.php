<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PrintLabelBatch;
use App\Models\FileIndexing;

class PrintLabelBatchItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'print_label_batch_items';
    
    protected $fillable = [
        'batch_id',
        'file_indexing_id',
        'file_number',
        'file_title',
        'plot_number',
        'district',
        'lga',
        'land_use_type',
        'shelf_location',
        'qr_code_data',
        'barcode_data',
        'label_position',
        'is_printed',
        'printed_at',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'file_indexing_id' => 'integer',
        'label_position' => 'integer',
        'is_printed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    /**
     * Get the batch this item belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PrintLabelBatch::class, 'batch_id');
    }

    /**
     * Get the file indexing record
     */
    public function fileIndexing(): BelongsTo
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    /**
     * Scope for printed items
     */
    public function scopePrinted($query)
    {
        return $query->where('is_printed', true);
    }

    /**
     * Scope for unprinted items
     */
    public function scopeUnprinted($query)
    {
        return $query->where('is_printed', false);
    }

    /**
     * Mark item as printed
     */
    public function markAsPrinted()
    {
        $this->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);
    }

    /**
     * Generate QR code data for this item
     */
    public function generateQrCodeData()
    {
        $data = [
            'file_number' => $this->file_number,
            'file_title' => $this->file_title,
            'plot_number' => $this->plot_number,
            'district' => $this->district,
            'lga' => $this->lga,
            'land_use_type' => $this->land_use_type,
            'shelf_location' => $this->shelf_location,
            'generated_at' => now()->toISOString(),
        ];
        
        return json_encode($data);
    }

    /**
     * Generate barcode data for this item
     */
    public function generateBarcodeData()
    {
        // Use file number as barcode data
        return $this->file_number;
    }

    /**
     * Auto-generate QR and barcode data when creating
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($item) {
            if (empty($item->qr_code_data)) {
                $item->qr_code_data = $item->generateQrCodeData();
            }
            
            if (empty($item->barcode_data)) {
                $item->barcode_data = $item->generateBarcodeData();
            }
        });
    }
}
