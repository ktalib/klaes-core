<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per scan, including failures — a forged QR presented at a counter is
 * exactly the event worth having a row for.
 *
 * raw_payload is populated ONLY when verification fails. A table of valid
 * plaintext tokens would let anyone with read access mint working copies of
 * every document ever issued.
 */
class DocumentScanLog extends Model
{
    public $timestamps = false;

    protected $connection = 'sqlsrv';
    protected $table = 'document_scan_logs';

    protected $fillable = [
        'document_qr_id',
        'qr_version_seen',
        'raw_payload',
        'scanned_at',
        'scanned_by',
        'channel',
        'ip_address',
        'user_agent',
        'result',
        'failure_reason',
    ];

    protected $casts = [
        'document_qr_id' => 'integer',
        'scanned_by'     => 'integer',
        'scanned_at'     => 'datetime',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(DocumentQrCode::class, 'document_qr_id');
    }

    public function succeeded(): bool
    {
        return in_array($this->result, ['authentic', 'review'], true);
    }
}
