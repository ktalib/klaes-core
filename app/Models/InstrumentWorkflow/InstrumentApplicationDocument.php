<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored document: either on an application (the scanned signed receipt) or
 * on an instrument capture (the supporting documents sent to BIR). Replacing a
 * document soft-deletes the old row, so a BIR snapshot still names the file and
 * checksum it was sent with.
 */
class InstrumentApplicationDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_application_documents';

    public const TYPE_SIGNED_RECEIPT = 'signed_receipt';

    protected $fillable = [
        'application_id',
        'instrument_capture_id',
        'submission_version',
        'doc_type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'sha256',
        'uploaded_by',
        'uploaded_at',
        'deleted_at',
        'deleted_by',
        'dms_scanning_id',
        'dms_pagetyping_id',
        'page_type_id',
        'page_subtype_id',
        'page_type_label',
        'page_number',
        'page_ordinal',
    ];

    protected $casts = [
        'size' => 'integer',
        'submission_version' => 'integer',
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
        'page_type_id' => 'integer',
        'page_subtype_id' => 'integer',
        'page_number' => 'integer',
        'page_ordinal' => 'integer',
    ];

    /** Every document type the workflow knows, BIR ones first (system-issued, then uploaded). */
    public static function typeLabels(): array
    {
        return config('instrument_workflow.documents.bir_system', [])
            + config('instrument_workflow.documents.bir_required', [])
            + config('instrument_workflow.documents.bir_optional', [])
            + [self::TYPE_SIGNED_RECEIPT => 'Signed Payment Receipt (scanned)'];
    }

    /** True for a document KLAES issued itself (receipt, LIC) rather than one an officer uploaded. */
    public function isSystemIssued(): bool
    {
        return array_key_exists($this->doc_type, (array) config('instrument_workflow.documents.bir_system', []));
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->doc_type] ?? $this->doc_type;
    }

    /**
     * What the document is, as the officer chose it: the DMS Page Type it was
     * filed under, falling back to the workflow's own document type for the rows
     * written before supporting documents carried a Page Type.
     */
    public function pageLabel(): string
    {
        return $this->page_type_label ?: $this->typeLabel();
    }

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }
}

