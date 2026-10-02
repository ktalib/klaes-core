<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PageTyping extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'pagetypings';

    protected $fillable = [
        'file_indexing_id',
        'scanning_id',
        'page_type',
        'page_subtype',
        'serial_number',
        'definition',
        'page_code',
        'definition_code',
        'file_path',
        'typed_by',
        'page_number',
        'cover_type_id',
        'page_type_others',
        'page_subtype_others',
        'source',
        'qc_status',
        'qc_reviewed_by',
        'qc_reviewed_at',
        'qc_overridden',
        'qc_override_note',
        'has_qc_issues',
        'deleted_at',
        // Booklet management fields
        'booklet_id',
        'is_booklet_page',
        'booklet_sequence',
        // Serial suffix and BC+FC fields
        'serial_suffix',
        'is_bcfc_page',
        'bcfc_sequence',
        'bcfc_id',
        'registry',
        'edms_file_type',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'cover_type_id' => 'integer',
        'definition' => 'integer',
        'qc_overridden' => 'boolean',
        'has_qc_issues' => 'boolean',
        'is_booklet_page' => 'boolean',
        'is_bcfc_page' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'qc_reviewed_at' => 'datetime',
    ];

    // QC Status constants
    const QC_STATUS_PENDING = 'pending';
    const QC_STATUS_PASSED = 'passed';
    const QC_STATUS_FAILED = 'failed';

    // Source constants
    const SOURCE_MANUAL = 'manual';
    const SOURCE_PDF_SPLIT = 'pdf_split';
    const SOURCE_IMAGE_COPY = 'image_copy';
    const SOURCE_UPLOAD_MORE = 'upload_more';

    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    public function typedBy()
    {
        return $this->belongsTo(User::class, 'typed_by');
    }

    public function scanning()
    {
        return $this->belongsTo(Scanning::class, 'scanning_id');
    }

    public function qcReviewer()
    {
        return $this->belongsTo(User::class, 'qc_reviewed_by');
    }

    public function pageType()
    {
        return $this->belongsTo(PageType::class, 'page_type', 'id');
    }

    public function pageSubType()
    {
        return $this->belongsTo(PageSubType::class, 'page_subtype', 'id');
    }

    /**
     * Get the cover type for this page
     */
    public function coverType()
    {
        return $this->belongsTo(CoverType::class, 'cover_type_id', 'Id');
    }

    /**
     * Check if page typing has passed QC
     */
    public function hasPassedQC()
    {
        return $this->qc_status === self::QC_STATUS_PASSED;
    }

    /**
     * Check if page typing has failed QC
     */
    public function hasFailedQC()
    {
        return $this->qc_status === self::QC_STATUS_FAILED;
    }

    /**
     * Check if page typing is pending QC
     */
    public function isPendingQC()
    {
        return $this->qc_status === self::QC_STATUS_PENDING;
    }

    /**
     * Check if QC has been overridden
     */
    public function isQCOverridden()
    {
        return $this->qc_overridden === true;
    }

    /**
     * Check if this page typing is for a PDF page
     */
    public function isPdfPage()
    {
        return $this->source === self::SOURCE_PDF_SPLIT || strpos($this->file_path, '.pdf') !== false;
    }

    /**
     * Check if this page typing is for an image
     */
    public function isImagePage()
    {
        return $this->source === self::SOURCE_IMAGE_COPY || $this->isImageFile($this->file_path);
    }

    /**
     * Check if this page typing is from upload more
     */
    public function isUploadMore()
    {
        return $this->source === self::SOURCE_UPLOAD_MORE;
    }

    /**
     * Get the PDF page number if this is a PDF page
     */
    public function getPdfPageNumber()
    {
        if ($this->isPdfPage()) {
            preg_match('/page_(\d+)\.pdf/', $this->file_path, $matches);
            return isset($matches[1]) ? (int) $matches[1] : $this->page_number;
        }
        return null;
    }

    /**
     * Get the base file path without PDF page reference
     */
    public function getBaseFilePath()
    {
        if ($this->isPdfPage()) {
            return preg_replace('/page_\d+\.pdf/', 'combined.pdf', $this->file_path);
        }
        return $this->file_path;
    }

    /**
     * Check if file is an image based on extension
     */
    private function isImageFile($filename)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $imageExtensions);
    }

    /**
     * Get formatted page code
     */
    public function getFormattedPageCode()
    {
        return $this->page_code ?: 'UNTYPED';
    }

    /**
     * Get source display name
     */
    public function getSourceDisplayName()
    {
        switch ($this->source) {
            case self::SOURCE_PDF_SPLIT:
                return 'PDF Split';
            case self::SOURCE_IMAGE_COPY:
                return 'Image Copy';
            case self::SOURCE_UPLOAD_MORE:
                return 'Upload More';
            case self::SOURCE_MANUAL:
            default:
                return 'Manual';
        }
    }

    /**
     * Scope for filtering by source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope for filtering by QC status
     */
    public function scopeByQcStatus($query, $status)
    {
        return $query->where('qc_status', $status);
    }

    /**
     * Scope for upload more pages
     */
    public function scopeUploadMore($query)
    {
        return $query->where('source', self::SOURCE_UPLOAD_MORE);
    }
}
