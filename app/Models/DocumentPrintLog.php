<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per printing event.
 *
 * print_count on document_qr_codes stays as a denormalised counter for list
 * screens, but this table is the record of truth — "printed 3 times" loses the
 * who and the why, which is the entire question the registry asks.
 */
class DocumentPrintLog extends Model
{
    public $timestamps = false;

    protected $connection = 'sqlsrv';
    protected $table = 'document_print_logs';

    protected $fillable = [
        'document_qr_id',
        'print_number',
        'copy_type',
        'printed_by',
        'printed_at',
        'reason',
        'batch_reference',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'document_qr_id' => 'integer',
        'print_number'   => 'integer',
        'printed_at'     => 'datetime',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(DocumentQrCode::class, 'document_qr_id');
    }

    public function isOriginal(): bool
    {
        return $this->copy_type === 'original';
    }
}
