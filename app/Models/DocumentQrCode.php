<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per printable document instance that carries a QR code.
 *
 * This IS the document registry — there is no separate `documents` table. Under
 * Approach A a document instance and its QR are 1:1 (reprints share the row and
 * add a print log), so splitting them would only duplicate file_number and
 * tracking_id in two places that then drift.
 */
class DocumentQrCode extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'document_qr_codes';

    protected $fillable = [
        'document_type',
        'source_table',
        'source_id',
        'file_indexing_id',
        'file_number',
        'tracking_id',
        'tracking_id_source',
        'qr_version',
        'key_id',
        'token_hash',
        'status',
        'superseded_by_id',
        'issued_at',
        'issued_by',
        'print_count',
        'last_printed_at',
        'last_printed_by',
    ];

    protected $casts = [
        'source_id'        => 'integer',
        'file_indexing_id' => 'integer',
        'qr_version'       => 'integer',
        'key_id'           => 'integer',
        'print_count'      => 'integer',
        'issued_at'        => 'datetime',
        'last_printed_at'  => 'datetime',
    ];

    public function printLogs(): HasMany
    {
        return $this->hasMany(DocumentPrintLog::class, 'document_qr_id')
                    ->orderBy('print_number');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(DocumentScanLog::class, 'document_qr_id')
                    ->orderByDesc('scanned_at');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function type(): ?DocumentType
    {
        return DocumentType::tryFrom((string) $this->document_type);
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    /**
     * Legacy documents carry qr_version 0 and can never verify above "review":
     * an unsigned payload resolves a record but cannot prove the paper was not
     * altered.
     */
    public function isLegacy(): bool
    {
        return (int) $this->qr_version === 0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
