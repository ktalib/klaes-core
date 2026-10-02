<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\FileIndexing;

class BlindScanning extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'blind_scannings';

    protected $fillable = [
        'temp_file_id',
        'file_number',
        'local_pc_path',
        'original_filename',
        'document_path',
        'paper_size',
        'document_type',
        'a4_count',
        'a3_count',
        'total_pages',
        'notes',
        'status',
        'uploaded_by',
        'file_indexing_id',
        'converted_at',
        'registry_type',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONVERTED = 'converted';
    const STATUS_ARCHIVED = 'archived';

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    /**
     * Generate a unique temporary file ID
     */
    public static function generateTempFileId()
    {
        do {
            $tempId = 'BLIND_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -6));
        } while (self::where('temp_file_id', $tempId)->exists());

        return $tempId;
    }

    /**
     * Convert blind scan to regular scanning workflow
     */
    public function convertToUpload($fileIndexingId)
    {
        // Create a new scanning record
        $scanning = Scanning::create([
            'file_indexing_id' => $fileIndexingId,
            'document_path' => $this->document_path,
            'uploaded_by' => $this->uploaded_by,
            'status' => 'scanned',
            'original_filename' => $this->original_filename,
            'paper_size' => $this->paper_size,
            'document_type' => $this->document_type,
            'notes' => $this->notes,
        ]);

        // Update blind scanning record
        $this->update([
            'status' => self::STATUS_CONVERTED,
            'file_indexing_id' => $fileIndexingId,
            'converted_at' => now(),
        ]);

        return $scanning;
    }

    /**
     * Get the full file path
     */
    public function getFullPathAttribute()
    {
        return file_storage_path('app/public/' . $this->document_path);
    }

    /**
     * Get the file URL.
     *
     * Resolved through the EDMS layouts: once a blind scan is ingested the file
     * moves into SCAN_UPLOAD, so the stored BLIND_SCAN path can be stale.
     */
    public function getFileUrlAttribute()
    {
        $resolver = app(\App\Services\Edms\EdmsDocumentPathResolver::class);

        return $resolver->resolveUrl($this->document_path, [
            'file_number' => $this->file_number ?? null,
            'registry' => $this->registry ?? null,
            'paper_size' => $this->paper_size ?? null,
            'file_name' => $this->original_filename ?? null,
        ]) ?? asset('storage/' . ltrim((string) $this->document_path, '/'));
    }

    /**
     * Check if file exists on disk
     */
    public function fileExists()
    {
        return file_exists($this->full_path);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeAttribute()
    {
        if (!$this->fileExists()) {
            return 'File not found';
        }

        $bytes = filesize($this->full_path);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}